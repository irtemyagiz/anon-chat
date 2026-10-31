<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Notifications\MarketplaceNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.notifications', [
            'notifications' => $request->user()->notifications()->paginate(30),
        ]);
    }

    /**
     * Bildirimi okundu işaretler ve hedef sayfaya yönlendirir. Yalnızca site içi adreslere yönlendirilir.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return is_string($url) && Str::startsWith($url, url('/'))
            ? redirect()->to($url)
            : redirect()->route('account.notifications');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', __('Tüm bildirimler okundu olarak işaretlendi.'));
    }

    public function preferences(Request $request): RedirectResponse
    {
        $enabled = (array) $request->input('mail', []);

        foreach (array_keys(MarketplaceNotification::CATEGORIES) as $category) {
            $request->user()->settings()->updateOrCreate(
                ['key' => "notify.mail.{$category}"],
                ['value' => in_array($category, $enabled, true) ? '1' : '0'],
            );
        }

        return back()->with('status', __('Bildirim tercihlerin kaydedildi.'));
    }
}
