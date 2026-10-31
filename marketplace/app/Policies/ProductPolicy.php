<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Ürünü yalnızca sahibi olan onaylı creator düzenleyebilir.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->id === $product->creator_id && $user->isApprovedCreator();
    }

    public function moderate(User $user): bool
    {
        return $user->hasPermission('products.moderate');
    }
}
