<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'legal_name', 'entity_type', 'tax_number', 'tax_office',
    'identity_number', 'country_code', 'address', 'document_path',
])]
#[Hidden(['tax_number', 'identity_number', 'address'])]
class CreatorVerification extends Model
{
    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            'tax_number' => 'encrypted',
            'identity_number' => 'encrypted',
            'address' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CreatorProfile, $this>
     */
    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(CreatorProfile::class);
    }
}
