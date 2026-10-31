<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Exceptions\InsufficientPointsException;
use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Services\Accounts\AccountService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        return view('admin.users.index', [
            'users' => User::query()
                ->with('roles', 'wallet')
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('email', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")))
                ->when($request->filled('rol'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $request->query('rol'))))
                ->when($request->filled('durum'), fn ($q) => $q->where('status', $request->query('durum')))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function show(User $user, WalletService $wallets): View
    {
        $wallet = $wallets->walletFor($user)->refresh();

        return view('admin.users.show', [
            'user' => $user->load('roles', 'creatorProfile'),
            'wallet' => $wallet,
            'entries' => WalletLedgerEntry::query()->where('wallet_id', $wallet->id)->with('transaction')->latest('id')->limit(30)->get(),
            'orders' => Order::query()->where('user_id', $user->id)->with('items')->latest()->limit(20)->get(),
        ]);
    }

    public function updateStatus(Request $request, User $user, AccountService $accounts): RedirectResponse
    {
        $this->authorize('users.manage');
        $data = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
            'reason' => ['required_unless:status,active', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $accounts->setStatus($user, UserStatus::from($data['status']), $request->user(), $data['reason'] ?? null);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', __('Hesap durumu güncellendi.'));
    }

    public function updateRoles(Request $request, User $user, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['roles' => ['array'], 'roles.*' => [Rule::in([Role::Admin, Role::Moderator])]]);

        /** Creator rolü başvuru sürecinden gelir; buradan verilmez veya kaldırılmaz. */
        $roles = [...($data['roles'] ?? []), ...($user->creatorProfile ? [Role::Creator] : [])];

        try {
            $accounts->syncRoles($user, $roles, $request->user());
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['roles' => $exception->getMessage()]);
        }

        return back()->with('status', __('Roller güncellendi.'));
    }

    public function adjustWallet(Request $request, User $user, WalletService $wallets): RedirectResponse
    {
        $this->authorize('wallets.adjust');
        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'request_id' => ['required', 'uuid'],
        ]);

        try {
            $wallets->adjust($user, (int) $data['points'], $data['reason'], $request->user(), 'admin-adjust:'.$data['request_id']);
        } catch (InsufficientPointsException $exception) {
            return back()->withErrors(['points' => __('Kullanıcının bakiyesi bu düzeltme için yetersiz (mevcut: :points).', ['points' => $exception->available])]);
        }

        return back()->with('status', __('Puan düzeltmesi kaydedildi.'));
    }
}
