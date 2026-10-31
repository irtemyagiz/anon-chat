<?php

namespace App\Notifications;

class VerificationReviewedNotification extends MarketplaceNotification
{
    public function __construct(public bool $approved, public ?string $note = null)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'payouts';
    }

    public function title(): string
    {
        return $this->approved ? __('Satıcı doğrulaman onaylandı') : __('Satıcı doğrulaman reddedildi');
    }

    public function body(): string
    {
        return $this->approved ? __('Artık hakediş talebi oluşturabilirsin.') : __('Gerekçe: :note', ['note' => (string) $this->note]);
    }

    public function url(): ?string
    {
        return route('creator.payouts');
    }
}
