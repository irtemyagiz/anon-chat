<?php

namespace App\Notifications;

class NewCommentNotification extends MarketplaceNotification
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
        return __('Modeline yeni yorum');
    }

    public function body(): string
    {
        return __(':title sayfasına yeni bir yorum yazıldı.', ['title' => $this->productTitle]);
    }

    public function url(): ?string
    {
        return route('products.show', $this->productSlug).'#yorumlar';
    }
}
