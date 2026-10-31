<?php

namespace App\Services\Community;

use App\Enums\CreatorStatus;
use App\Exceptions\MarketplaceException;
use App\Models\Collection;
use App\Models\CreatorProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Favori, beğeni, takip ve koleksiyon işlemleri. Toggle işlemleri birincil
 * anahtar kısıtına dayanır; eşzamanlı çift tıklamada sayaçlar bozulmaz.
 */
class CommunityService
{
    public function toggleFavorite(User $user, Product $product): bool
    {
        return $this->togglePivot('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    /**
     * Beğeni sayacı (products.likes_count) pivot kaydıyla aynı transaction içinde güncellenir.
     */
    public function toggleLike(User $user, Product $product): bool
    {
        return DB::transaction(function () use ($user, $product): bool {
            $liked = $this->togglePivot('product_likes', ['user_id' => $user->id, 'product_id' => $product->id]);
            $liked ? $product->increment('likes_count') : $product->where('id', $product->id)->where('likes_count', '>', 0)->decrement('likes_count');

            return $liked;
        });
    }

    public function toggleFollow(User $follower, CreatorProfile $profile): bool
    {
        if ($profile->user_id === $follower->id) {
            throw new MarketplaceException(__('Kendi mağazanı takip edemezsin.'));
        }

        if ($profile->status !== CreatorStatus::Approved) {
            throw new MarketplaceException(__('Bu mağaza takip edilemiyor.'));
        }

        return $this->togglePivot('follows', ['follower_id' => $follower->id, 'creator_id' => $profile->user_id]);
    }

    public function createCollection(User $user, string $name, ?string $description, bool $isPublic): Collection
    {
        if ($user->collections()->count() >= 100) {
            throw new MarketplaceException(__('En fazla 100 koleksiyon oluşturabilirsin.'));
        }

        $base = Str::slug($name) ?: 'koleksiyon';
        $slug = $base;

        while ($user->collections()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $user->collections()->create(['name' => $name, 'slug' => $slug, 'description' => $description, 'is_public' => $isPublic]);
    }

    public function toggleInCollection(Collection $collection, Product $product): bool
    {
        if (! $product->isPublished()) {
            throw new MarketplaceException(__('Yalnızca yayındaki modeller koleksiyona eklenebilir.'));
        }

        if ($collection->products()->count() >= 500 && ! $collection->products()->whereKey($product->id)->exists()) {
            throw new MarketplaceException(__('Bir koleksiyonda en fazla 500 model olabilir.'));
        }

        return $this->togglePivot('collection_products', ['collection_id' => $collection->id, 'product_id' => $product->id]);
    }

    /**
     * Kayıt varsa siler (false), yoksa ekler (true).
     *
     * @param  array<string, int>  $keys
     */
    private function togglePivot(string $table, array $keys): bool
    {
        if (DB::table($table)->where($keys)->delete() > 0) {
            return false;
        }

        try {
            DB::table($table)->insert([...$keys, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            /** Eşzamanlı ikinci istek kaydı zaten ekledi. */
        }

        return true;
    }
}
