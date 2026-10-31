<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Product;
use App\Models\PurchaseEntitlement;
use App\Services\Accounts\AccountDeletionService;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\CurrencyResolver;
use App\Services\Files\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Kullanıcı hesabı: kütüphane (satın alınan/indirilen modeller), favoriler,
 * koleksiyonlar, takip edilen tasarımcılar ve profil ayarları.
 */
class AccountController extends Controller
{
    public function library(Request $request): View
    {
        return view('account.library', [
            'entitlements' => PurchaseEntitlement::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('revoked_at')
                ->with(['product' => fn ($q) => $q->withTrashed()->with(['creator.creatorProfile', 'files' => fn ($f) => $f->where('is_active', true)])])
                ->latest('granted_at')
                ->paginate(20),
        ]);
    }

    public function favorites(Request $request): View
    {
        return view('account.favorites', [
            'products' => $request->user()->favorites()->published()->with(['creator.creatorProfile', 'category'])->orderByPivot('created_at', 'desc')->paginate(24),
        ]);
    }

    public function collections(Request $request): View
    {
        return view('account.collections', [
            'collections' => $request->user()->collections()->withCount('products')->latest()->get(),
        ]);
    }

    public function following(Request $request): View
    {
        return view('account.following', [
            'creators' => $request->user()->following()->with('creatorProfile')->orderByPivot('created_at', 'desc')->paginate(24),
        ]);
    }

    /**
     * Ürün sayfasındaki "Koleksiyona ekle" menüsü için kullanıcının koleksiyonları.
     */
    public function collectionOptions(Request $request, Product $product): array
    {
        return $request->user()->collections()->latest()->get(['id', 'name'])->map(fn (Collection $collection) => [
            'id' => $collection->id,
            'name' => $collection->name,
            'in' => $collection->products()->whereKey($product->id)->exists(),
        ])->all();
    }

    public function edit(Request $request): View
    {
        return view('account.settings', ['user' => $request->user()]);
    }

    public function update(Request $request, ImageProcessor $images, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
            'country_code' => ['required', Rule::in(array_keys(config('countries')))],
            'display_currency' => ['nullable', Rule::in(config('marketplace.currency.supported'))],
            'avatar' => ['nullable', 'file', 'max:10240'],
        ]);

        $oldCountry = $user->country_code;
        $user->fill(collect($data)->except('avatar')->all());

        if ($request->hasFile('avatar')) {
            try {
                $disk = config('marketplace.storage.public_media_disk');
                $stored = $images->store($request->file('avatar'), $disk, "avatars/{$user->uuid}", 400);
            } catch (MarketplaceException $exception) {
                return back()->withErrors(['avatar' => $exception->getMessage()]);
            }

            if ($user->avatar_path) {
                Storage::disk($disk)->delete($user->avatar_path);
            }

            $user->avatar_path = $stored['path'];
        }

        $user->save();

        if ($oldCountry !== $user->country_code) {
            /** Fatura ülkesi ödeme para birimini belirlediği için değişiklik denetim kaydına yazılır. */
            $audit->record('user.country_changed', $user, ['country' => $oldCountry], ['country' => $user->country_code], $user);
            $request->session()->forget(CurrencyResolver::SESSION_KEY);
        }

        return back()->with('status', __('Profilin güncellendi.'));
    }

    public function destroy(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password'], 'confirm' => ['accepted']]);
        $user = $request->user();

        if ($blockers = $deletion->blockers($user)) {
            return back()->withErrors(['delete' => implode(' ', $blockers)]);
        }

        $deletion->delete($user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Hesabın silindi. Kişisel verilerin anonimleştirildi.'));
    }

    public function updatePassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();
        $audit->record('security.password_changed', $user, actor: $user);

        /** Diğer cihazlardaki oturumlar kapatılır. */
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', __('Şifren güncellendi. Diğer cihazlardaki oturumların kapatıldı.'));
    }
}
