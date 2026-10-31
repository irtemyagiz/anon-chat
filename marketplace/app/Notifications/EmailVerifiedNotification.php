<?php

namespace App\Notifications;

class EmailVerifiedNotification extends MarketplaceNotification
{
    public function category(): string
    {
        return 'purchases';
    }

    public function title(): string
    {
        return __('E-posta adresin doğrulandı');
    }

    public function body(): string
    {
        return __('Artık puan yükleyebilir, satın alma yapabilir ve yorum yazabilirsin.');
    }

    public function url(): ?string
    {
        return route('top-up.create');
    }
}
