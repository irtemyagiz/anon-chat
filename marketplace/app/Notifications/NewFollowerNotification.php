<?php

namespace App\Notifications;

class NewFollowerNotification extends MarketplaceNotification
{
    public function __construct(public string $followerName)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'community';
    }

    public function title(): string
    {
        return __('Yeni takipçi');
    }

    public function body(): string
    {
        return __(':name mağazanı takip etmeye başladı.', ['name' => $this->followerName]);
    }

    public function url(): ?string
    {
        return route('creator.followers');
    }
}
