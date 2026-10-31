<?php

namespace App\Notifications;

class ProductUpdatedNotification extends MarketplaceNotification
{
    public function __construct(public string $productTitle, public string $productSlug, public string $version)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'creator_updates';
    }

    public function title(): string
    {
        return __(':title güncellendi', ['title' => $this->productTitle]);
    }

    public function body(): string
    {
        return __('Satın aldığın modelin yeni sürümü (v:version) yayında.', ['version' => $this->version]);
    }

    public function url(): ?string
    {
        return route('products.show', $this->productSlug);
    }
}
