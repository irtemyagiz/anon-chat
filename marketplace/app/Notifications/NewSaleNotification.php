<?php

namespace App\Notifications;

use App\Services\Points\PointConverter;

class NewSaleNotification extends MarketplaceNotification
{
    public function __construct(public string $productTitle, public int $creatorAmountCents)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return __('Yeni satış: :title', ['title' => $this->productTitle]);
    }

    public function body(): string
    {
        return __('Bu satıştan kazancın: $:amount (iade süresi dolunca kullanılabilir olur).', ['amount' => PointConverter::formatCents($this->creatorAmountCents)]);
    }

    public function url(): ?string
    {
        return route('creator.sales');
    }
}
