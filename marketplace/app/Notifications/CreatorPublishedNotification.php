<?php

namespace App\Notifications;

class CreatorPublishedNotification extends MarketplaceNotification
{
    public function __construct(public string $storeName, public string $productTitle, public string $productSlug)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'creator_updates';
    }

    public function title(): string
    {
        return __(':store yeni bir model yayınladı', ['store' => $this->storeName]);
    }

    public function body(): string
    {
        return __(':title şimdi yayında.', ['title' => $this->productTitle]);
    }

    public function url(): ?string
    {
        return route('products.show', $this->productSlug);
    }
}
