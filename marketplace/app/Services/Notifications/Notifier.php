<?php

namespace App\Services\Notifications;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Bildirim gönderimi için tek giriş noktası. Yönetici uyarıları ilgili izne
 * sahip (ve yöneticilere) aktif personele gider.
 */
class Notifier
{
    /**
     * @param  User|iterable<User>|null  $recipients
     */
    public function send(User|iterable|null $recipients, Notification $notification): void
    {
        if ($recipients === null) {
            return;
        }

        NotificationFacade::send($recipients, $notification);
    }

    public function staff(string $permission, string $title, string $body, ?string $url = null): void
    {
        $recipients = User::query()
            ->where('status', UserStatus::Active)
            ->whereHas('roles', fn (Builder $roles) => $roles
                ->where('name', Role::Admin)
                ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('name', $permission)))
            ->get();

        $this->send($recipients, new AdminAlertNotification($title, $body, $url));
    }
}
