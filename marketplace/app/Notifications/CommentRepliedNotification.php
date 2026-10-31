<?php

namespace App\Notifications;

class CommentRepliedNotification extends MarketplaceNotification
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
        return __('Yorumuna yanıt geldi');
    }

    public function body(): string
    {
        return __(':title sayfasındaki yorumuna yanıt verildi.', ['title' => $this->productTitle]);
    }

    public function url(): ?string
    {
        return route('products.show', $this->productSlug).'#yorumlar';
    }
}
