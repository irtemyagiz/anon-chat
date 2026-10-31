<?php

namespace App\Notifications;

class ReviewRepliedNotification extends MarketplaceNotification
{
    public function __construct(public string $productTitle, public string $productSlug)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'community';
    }

    public function title(): string
    {
        return __('Tasarımcı değerlendirmene yanıt verdi');
    }

    public function body(): string
    {
        return __(':title için yaptığın değerlendirmeye yanıt verildi.', ['title' => $this->productTitle]);
    }

    public function url(): ?string
    {
        return route('products.show', $this->productSlug).'#degerlendirmeler';
    }
}
