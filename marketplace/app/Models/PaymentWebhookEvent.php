<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['*'])]
class PaymentWebhookEvent extends Model
{
    protected function casts(): array
    {
        return [
            'status' => WebhookEventStatus::class,
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
