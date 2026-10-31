<?php

namespace App\Notifications;

class SupportTicketRepliedNotification extends MarketplaceNotification
{
    public function __construct(public string $ticketUuid, public string $subject)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'support';
    }

    public function title(): string
    {
        return __('Destek talebine yanıt geldi');
    }

    public function body(): string
    {
        return __('“:subject” talebine destek ekibi yanıt verdi.', ['subject' => $this->subject]);
    }

    public function url(): ?string
    {
        return route('support.show', $this->ticketUuid);
    }
}
