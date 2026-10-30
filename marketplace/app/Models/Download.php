<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['*'])]
class Download extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ProductFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class, 'product_file_id');
    }
}
