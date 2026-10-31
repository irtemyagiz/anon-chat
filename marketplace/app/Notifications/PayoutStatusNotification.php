<?php

namespace App\Notifications;

use App\Services\Points\PointConverter;

class PayoutStatusNotification extends MarketplaceNotification
{
    public function __construct(public string $status, public int $amountCents, public ?string $note = null)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'payouts';
    }

    public function title(): string
    {
        return match ($this->status) {
            'paid' => __('Hakediş ödemen yapıldı'), 'approved' => __('Hakediş talebin onaylandı'), 'rejected' => __('Hakediş talebin reddedildi'), default => __('Hakediş talebin güncellendi')
        };
    }

    public function body(): string
    {
        return __('Tutar: $:amount', ['amount' => PointConverter::formatCents($this->amountCents)]).($this->note ? ' · '.$this->note : '');
    }

    public function url(): ?string
    {
        return route('creator.payouts');
    }
}
