<?php

namespace App\Notifications;

class AdminAlertNotification extends MarketplaceNotification
{
    public function __construct(public string $alertTitle, public string $alertBody, public ?string $alertUrl = null)
    {
        parent::__construct();
    }

    public function category(): string
    {
        return 'admin';
    }

    public function title(): string
    {
        return $this->alertTitle;
    }

    public function body(): string
    {
        return $this->alertBody;
    }

    public function url(): ?string
    {
        return $this->alertUrl;
    }
}
