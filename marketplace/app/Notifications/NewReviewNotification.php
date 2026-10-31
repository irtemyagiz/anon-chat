<?php

namespace App\Notifications;

class NewReviewNotification extends MarketplaceNotification
{
    public function __construct(public string $productTitle, public int $rating)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'community';
    }

    public function title(): string
    {
        return __('Yeni değerlendirme: :title', ['title' => $this->productTitle]);
    }

    public function body(): string
    {
        return __(':rating yıldızlı yeni bir değerlendirme aldın.', ['rating' => $this->rating]);
    }

    public function url(): ?string
    {
        return route('creator.reviews');
    }
}
