<?php

namespace App\Http\Resources\V1;

use App\Models\WalletLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kullanıcının cüzdan defterindeki bir satır. amount pozitifse giriş, negatifse çıkış.
 *
 * @mixin WalletLedgerEntry
 */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->transaction->reference,
            'type' => $this->transaction->type->value,
            'description' => $this->transaction->description,
            'amount_points' => (int) $this->amount,
            'balance_after_points' => (int) $this->balance_after,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
