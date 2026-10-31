<?php

namespace App\Services\Community;

use App\Enums\EntitlementSource;
use App\Exceptions\MarketplaceException;
use App\Models\Comment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\PurchaseEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Değerlendirmeler ve yorumlar.
 *
 * Sahte değerlendirmeye karşı: yıldızlı değerlendirmeyi yalnızca ürünü edinmiş
 * (satın almış veya ücretsiz indirmiş) kullanıcılar yapabilir; satın alanlara
 * "doğrulanmış satın alma" rozeti verilir. Creator kendi ürününü değerlendiremez.
 *
 * Spam'e karşı (yorumlar): e-posta doğrulaması, hız sınırı, aynı metni tekrar
 * gönderme engeli, bağlantı sınırı; 3 farklı kullanıcı şikâyetinde yorum otomatik gizlenir.
 */
class ReviewService
{
    private const MAX_LINKS = 2;

    private const AUTO_HIDE_REPORTS = 3;

    public function review(User $user, Product $product, int $rating, ?string $title, ?string $body): ProductReview
    {
        if ($user->id === $product->creator_id) {
            throw new MarketplaceException(__('Kendi modelini değerlendiremezsin.'));
        }

        $entitlement = PurchaseEntitlement::query()
            ->where(['user_id' => $user->id, 'product_id' => $product->id])
            ->whereNull('revoked_at')
            ->first();

        if ($entitlement === null) {
            throw new MarketplaceException(__('Değerlendirme yapabilmek için modeli satın almış veya indirmiş olmalısın.'));
        }

        $this->assertNotSpam($user, (string) $body);

        return DB::transaction(function () use ($user, $product, $rating, $title, $body, $entitlement): ProductReview {
            $review = ProductReview::query()->where(['product_id' => $product->id, 'user_id' => $user->id])->first() ?? new ProductReview;
            $review->product_id = $product->id;
            $review->user_id = $user->id;
            $review->fill(['rating' => $rating, 'title' => $title, 'body' => $body]);
            $review->is_verified_purchase = $entitlement->source === EntitlementSource::Purchase;
            $review->save();

            $this->refreshRating($product);

            return $review;
        });
    }

    public function reply(ProductReview $review, User $creator, string $reply): void
    {
        if ($review->product->creator_id !== $creator->id) {
            throw new MarketplaceException(__('Yalnızca ürünün tasarımcısı yanıt verebilir.'));
        }

        $review->forceFill(['creator_reply' => $reply, 'creator_replied_at' => now()])->save();
    }

    public function comment(User $user, Product $product, string $body, ?Comment $parent = null): Comment
    {
        if ($parent !== null && ($parent->product_id !== $product->id || $parent->parent_id !== null)) {
            throw new MarketplaceException(__('Bu yoruma yanıt verilemez.'));
        }

        $this->assertNotSpam($user, $body);

        $comment = new Comment(['body' => trim($body)]);
        $comment->product_id = $product->id;
        $comment->user_id = $user->id;
        $comment->parent_id = $parent?->id;
        $comment->ip_address = request()->ip();
        $comment->save();

        return $comment;
    }

    public function report(Comment $comment, User $reporter, string $reason): void
    {
        DB::transaction(function () use ($comment, $reporter, $reason): void {
            $inserted = DB::table('comment_reports')->insertOrIgnore([
                'comment_id' => $comment->id,
                'user_id' => $reporter->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            if ($inserted === 0) {
                return;
            }

            $comment->increment('reports_count');

            if ($comment->reports_count >= self::AUTO_HIDE_REPORTS) {
                $comment->forceFill(['status' => Comment::Hidden])->save();
            }
        });
    }

    public function refreshRating(Product $product): void
    {
        $stats = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', 'published')
            ->selectRaw('count(*) as total, avg(rating) as average')
            ->first();

        $product->forceFill([
            'reviews_count' => (int) $stats->total,
            'rating_average' => round((float) $stats->average, 2),
        ])->save();
    }

    private function assertNotSpam(User $user, string $body): void
    {
        if (preg_match_all('~https?://~i', $body) > self::MAX_LINKS) {
            throw new MarketplaceException(__('Mesajda en fazla :count bağlantı olabilir.', ['count' => self::MAX_LINKS]));
        }

        $fingerprint = 'comment-body:'.$user->id.':'.md5(Str::lower(preg_replace('/\s+/', ' ', trim($body))));

        if ($body !== '' && ! Cache::add($fingerprint, true, now()->addMinutes(10))) {
            throw new MarketplaceException(__('Aynı mesajı kısa süre içinde tekrar gönderemezsin.'));
        }
    }
}
