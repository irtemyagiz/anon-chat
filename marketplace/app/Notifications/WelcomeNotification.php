<?php

namespace App\Notifications;

class WelcomeNotification extends MarketplaceNotification
{
    public function category(): string
    {
        return 'purchases';
    }

    public function title(): string
    {
        return __('Katman\'a hoş geldin');
    }

    public function body(): string
    {
        return __('Hesabın oluşturuldu. Modelleri keşfetmeye başlayabilir, puan yükleyerek satın alma yapabilirsin.');
    }

    public function url(): ?string
    {
        return route('products.index');
    }
}
