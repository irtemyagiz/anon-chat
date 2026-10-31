<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatorApplicationRequest;
use App\Services\Accounts\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Tasarımlarını Paylaş, Kazanmaya Başla" — creator kaydı ve başvuru durumu.
 */
class CreatorApplicationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->creatorProfile !== null) {
            return redirect()->route('creator.status');
        }

        return view('creator.apply');
    }

    public function store(CreatorApplicationRequest $request, AccountService $accounts): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            $user = $accounts->register($request->safe()->only('username', 'first_name', 'last_name', 'email', 'password', 'country_code'));
            Auth::login($user);
            $request->session()->regenerate();
        }

        $accounts->applyAsCreator($user, $request->safe()->only('designer_name', 'store_name', 'description'));

        return redirect()->route('creator.status')->with('status', __('Creator başvurunuz alındı.'));
    }

    public function status(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->creatorProfile;

        if ($profile === null) {
            return redirect()->route('creator.apply');
        }

        return view('creator.status', ['profile' => $profile]);
    }

    /**
     * Menüdeki "Tasarım Yükle" bağlantısı kullanıcının durumuna göre doğru sayfaya gider.
     */
    public function upload(Request $request): RedirectResponse
    {
        return $request->user()?->creatorProfile !== null
            ? redirect()->route('creator.status')
            : redirect()->route('creator.apply');
    }
}
