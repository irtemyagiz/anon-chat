<?php

namespace App\Notifications;

class PointsToppedUpNotification extends MarketplaceNotification
{
    public function __construct(public int $points)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'purchases';
    }

    public function title(): string
    {
        return __(':points POINT yüklendi', ['points' => number_format($this->points, 0, ',', '.')]);
    }

    public function body(): string
    {
        return __('Ödemen onaylandı ve puanlar cüzdanına eklendi.');
    }

    public function url(): ?string
    {
        return route('wallet');
    }
}
