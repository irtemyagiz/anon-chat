<?php

namespace App\Notifications;

class CreatorReviewedNotification extends MarketplaceNotification
{
    public function __construct(public string $status, public ?string $reason = null)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'moderation';
    }

    public function title(): string
    {
        return $this->status === 'approved' ? __('Creator başvurun onaylandı') : __('Creator başvurun hakkında güncelleme');
    }

    public function body(): string
    {
        return $this->status === 'approved' ? __('Mağazan açıldı. Artık ürün yükleyebilirsin.') : __('Durum: :status. :reason', ['status' => $this->status, 'reason' => (string) $this->reason]);
    }

    public function url(): ?string
    {
        return route('creator.status');
    }
}
