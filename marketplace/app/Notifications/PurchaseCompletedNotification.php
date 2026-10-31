<?php

namespace App\Notifications;

class PurchaseCompletedNotification extends MarketplaceNotification
{
    public function __construct(public string $productTitle, public string $productSlug)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'purchases';
    }

    public function title(): string
    {
        return __('Satın alma tamamlandı');
    }

    public function body(): string
    {
        return __(':title kütüphanene eklendi. Dosyaları indirebilirsin.', ['title' => $this->productTitle]);
    }

    public function url(): ?string
    {
        return route('account.library');
    }
}
