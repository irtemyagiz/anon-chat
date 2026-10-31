<?php

namespace App\Notifications;

class ProductReviewedNotification extends MarketplaceNotification
{
    public function __construct(public string $productTitle, public string $productUuid, public bool $approved, public ?string $note = null)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'moderation';
    }

    public function title(): string
    {
        return $this->approved ? __('Ürünün yayında: :title', ['title' => $this->productTitle]) : __('Ürünün onaylanmadı: :title', ['title' => $this->productTitle]);
    }

    public function body(): string
    {
        return $this->approved ? __('Ürünün inceleme sonrası yayına alındı.') : __('İnceleme notu: :note', ['note' => (string) $this->note]);
    }

    public function url(): ?string
    {
        return route('creator.products.edit', $this->productUuid);
    }
}
