<?php

namespace Tests\Feature\Web;

use App\Enums\CreatorStatus;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\ProductReport;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\User;
use App\Services\Sales\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private function buy(User $buyer, $product): void
    {
        $this->givePoints($buyer, $product->price_cents);
        app(PurchaseService::class)->purchase($buyer, $product, null, (string) Str::uuid());
    }

    public function test_like_and_favorite_toggle_with_consistent_counts(): void
    {
        $product = $this->publishedProduct(500);
        $user = User::factory()->create();

        $this->post(route('products.like', $product))->assertRedirect(route('login'));

        $this->actingAs($user)->postJson(route('products.like', $product))->assertOk()->assertJson(['liked' => true, 'count' => 1]);
        $this->postJson(route('products.like', $product))->assertJson(['liked' => false, 'count' => 0]);
        $this->postJson(route('products.like', $product))->assertJson(['liked' => true, 'count' => 1]);
        $this->assertSame(1, $product->fresh()->likes_count);

        $this->postJson(route('products.favorite', $product))->assertJson(['favorited' => true]);
        $this->get(route('account.favorites'))->assertSee($product->title);
    }

    public function test_follow_rules_and_follower_lists(): void
    {
        $creator = User::factory()->creator()->create();
        $pending = User::factory()->creator(CreatorStatus::Pending)->create();
        $fan = User::factory()->create(['first_name' => 'Hayran']);

        $this->actingAs($fan)->postJson(route('designers.follow', $creator->creatorProfile))->assertJson(['following' => true]);
        $this->postJson(route('designers.follow', $pending->creatorProfile))->assertStatus(422);
        $this->actingAs($creator)->postJson(route('designers.follow', $creator->creatorProfile))->assertStatus(422);

        $this->actingAs($fan)->get(route('designers.show', $creator->creatorProfile))->assertSee('1 takipçi')->assertSee('Takip ediliyor');
        $this->get(route('account.following'))->assertSee($creator->creatorProfile->store_name);
        $this->actingAs($creator)->get(route('creator.followers'))->assertSee('Hayran');
    }

    public function test_collections_privacy_and_ownership(): void
    {
        $product = $this->publishedProduct(500);
        $owner = User::factory()->create(['username' => 'koleksiyoncu']);

        $this->actingAs($owner)->postJson(route('collections.store'), ['name' => 'Masa Düzeni', 'product' => $product->id])->assertOk();
        $collection = Collection::query()->sole();
        $this->assertTrue($collection->products()->whereKey($product->id)->exists());

        $this->get(route('collections.show', ['koleksiyoncu', 'masa-duzeni']))->assertOk()->assertSee($product->title);
        $this->get(route('home'))->assertSee('Masa Düzeni');

        $this->put(route('collections.update', $collection), ['name' => 'Masa Düzeni', 'is_public' => 0]);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('collections.show', ['koleksiyoncu', 'masa-duzeni']))->assertNotFound();
        $this->actingAs($other)->postJson(route('collections.toggle', [$collection, $product]))->assertForbidden();
        $this->actingAs($other)->delete(route('collections.destroy', $collection))->assertForbidden();
        $this->actingAs($owner)->getJson(route('account.collection-options', $product))->assertJson([['name' => 'Masa Düzeni', 'in' => true]]);
    }

    public function test_only_owners_can_review_and_paid_buyers_get_verified_badge(): void
    {
        $product = $this->publishedProduct(500);
        $stranger = User::factory()->create();
        $buyer = User::factory()->create(['first_name' => 'Alıcı']);

        $this->actingAs($stranger)->post(route('products.review', $product), ['rating' => 5])->assertSessionHasErrors('review');
        $this->assertSame(0, ProductReview::query()->count());

        $this->buy($buyer, $product);
        $this->actingAs($buyer)->post(route('products.review', $product), ['rating' => 4, 'body' => 'Sorunsuz basıldı.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('products.review', $product), ['rating' => 2, 'body' => 'Güncelledim.'])->assertRedirect()->assertSessionHasNoErrors();

        $review = ProductReview::query()->sole();
        $this->assertTrue($review->is_verified_purchase);
        $this->assertSame(2, $review->rating, 'Kullanıcı başına tek değerlendirme; tekrar gönderim günceller');
        $this->assertSame(1, $product->fresh()->reviews_count);
        $this->assertEquals(2.0, (float) $product->fresh()->rating_average);
        $this->get(route('products.show', $product))->assertSee('Doğrulanmış satın alma');

        $this->actingAs($product->creator)->post(route('products.review', $product), ['rating' => 5])->assertSessionHasErrors('review');
    }

    public function test_free_download_review_is_not_marked_as_verified_purchase(): void
    {
        $product = $this->publishedProduct(0);
        $user = User::factory()->create();
        app(PurchaseService::class)->claimFree($user, $product);

        $this->actingAs($user)->post(route('products.review', $product), ['rating' => 5]);

        $this->assertFalse(ProductReview::query()->sole()->is_verified_purchase);
    }

    public function test_only_the_product_creator_can_reply_to_reviews(): void
    {
        $product = $this->publishedProduct(500);
        $buyer = User::factory()->create();
        $this->buy($buyer, $product);
        $this->actingAs($buyer)->post(route('products.review', $product), ['rating' => 5]);
        $review = ProductReview::query()->sole();

        $this->actingAs(User::factory()->creator()->create())->post(route('reviews.reply', $review), ['reply' => 'Sahte yanıt'])->assertForbidden();
        $this->actingAs($product->creator)->post(route('reviews.reply', $review), ['reply' => 'Teşekkürler!'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Teşekkürler!', $review->fresh()->creator_reply);
        $this->actingAs($product->creator)->get(route('creator.reviews'))->assertSee('Teşekkürler!');
    }

    public function test_comment_spam_protection(): void
    {
        $product = $this->publishedProduct(500);
        $user = User::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())->post(route('products.comment', $product), ['body' => 'Merhaba'])->assertRedirect(route('verification.notice'));

        $this->actingAs($user)->post(route('products.comment', $product), ['body' => 'Hangi tablaya sığar?'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('products.comment', $product), ['body' => 'Hangi  tablaya sığar?'])->assertSessionHasErrors('comment');
        $this->post(route('products.comment', $product), ['body' => 'https://a.test https://b.test https://c.test'])->assertSessionHasErrors('comment');

        $this->assertSame(1, Comment::query()->count());
    }

    public function test_comments_are_rendered_escaped(): void
    {
        $product = $this->publishedProduct(500);

        $this->actingAs(User::factory()->create())->post(route('products.comment', $product), ['body' => '<script>alert("x")</script> güzel model']);

        $this->get(route('products.show', $product))->assertDontSee('<script>alert("x")</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_replies_deletion_and_reporting(): void
    {
        $product = $this->publishedProduct(500);
        $author = User::factory()->create();
        $this->actingAs($author)->post(route('products.comment', $product), ['body' => 'Soru: PETG ile olur mu?']);
        $comment = Comment::query()->sole();

        $this->actingAs($product->creator)->post(route('products.comment', $product), ['body' => 'Evet, sorunsuz.', 'parent_id' => $comment->id]);
        $this->get(route('products.show', $product))->assertSee('Evet, sorunsuz.')->assertSee('Tasarımcı');

        $this->actingAs(User::factory()->create())->delete(route('comments.destroy', $comment))->assertForbidden();

        $this->actingAs($author)->post(route('comments.report', $comment), ['reason' => 'spam']);
        $this->assertSame(0, $comment->fresh()->reports_count, 'Kendi yorumunu şikâyet edemez');

        $reporter = User::factory()->create();
        $this->actingAs($reporter)->post(route('comments.report', $comment), ['reason' => 'spam']);
        $this->actingAs($reporter)->post(route('comments.report', $comment), ['reason' => 'spam']);
        $this->assertSame(1, $comment->fresh()->reports_count, 'Aynı kullanıcı bir kez şikâyet edebilir');

        foreach (range(1, 2) as $i) {
            $this->actingAs(User::factory()->create())->post(route('comments.report', $comment), ['reason' => 'abuse']);
        }

        $this->assertSame(Comment::Hidden, $comment->fresh()->status);
        $this->get(route('products.show', $product))->assertDontSee('PETG ile olur mu?');
    }

    public function test_guest_can_report_copyright_with_email(): void
    {
        $product = $this->publishedProduct(500);

        $this->post(route('products.report', $product), ['reason' => 'copyright', 'details' => 'Kısa'])->assertSessionHasErrors(['details', 'reporter_email']);
        $this->post(route('products.report', $product), ['reason' => 'copyright', 'details' => 'Bu model benim orijinal tasarımımın kopyası: https://ornek.test/model', 'reporter_email' => 'sahip@example.com'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('sahip@example.com', ProductReport::query()->sole()->reporter_email);
    }

    public function test_admin_moderation_of_comments_reviews_and_reports(): void
    {
        $product = $this->publishedProduct(500);
        $buyer = User::factory()->create();
        $this->buy($buyer, $product);
        $this->actingAs($buyer)->post(route('products.review', $product), ['rating' => 1, 'body' => 'Uygunsuz içerik']);
        $this->post(route('products.comment', $product), ['body' => 'Spam yorum']);
        $this->post(route('products.report', $product), ['reason' => 'inappropriate', 'details' => 'Açıklamada uygunsuz ifadeler var, incelenmeli.']);

        $admin = $this->actingAsAdmin(User::factory()->withRole(Role::Moderator)->create());

        $review = ProductReview::query()->sole();
        $this->put(route('admin.moderation.reviews.update', $review), ['status' => 'hidden'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->reviews_count);

        $comment = Comment::query()->sole();
        $this->put(route('admin.moderation.comments.update', $comment), ['action' => 'hide'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(Comment::Hidden, $comment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'comment.hide', 'actor_id' => $admin->id]);

        $report = ProductReport::query()->sole();
        $this->get(route('admin.moderation.reports'))->assertSee('Açıklamada uygunsuz');
        $this->put(route('admin.moderation.reports.resolve', $report), ['status' => 'resolved', 'resolution_note' => 'Açıklama düzeltildi'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('resolved', $report->fresh()->status);
    }

    public function test_library_lists_owned_models_with_download_links(): void
    {
        $product = $this->publishedProduct(500);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get(route('account.library'))->assertSee('Kütüphanen boş');
        $this->buy($buyer, $product);

        $this->get(route('account.library'))->assertSee($product->title)->assertSee('Satın alındı');
    }

    public function test_profile_settings_and_password_change(): void
    {
        $user = User::factory()->create(['country_code' => 'US']);

        $this->actingAs($user)->put(route('account.settings.update'), ['first_name' => 'Yeni', 'last_name' => 'Ad', 'country_code' => 'TR'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('TR', $user->fresh()->country_code);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.country_changed', 'actor_id' => $user->id]);

        $this->put(route('account.settings.password'), ['current_password' => 'yanlis', 'password' => 'YeniSifre9', 'password_confirmation' => 'YeniSifre9'])->assertSessionHasErrors('current_password');
        $this->put(route('account.settings.password'), ['current_password' => 'password', 'password' => 'YeniSifre9', 'password_confirmation' => 'YeniSifre9'])->assertRedirect()->assertSessionHasNoErrors();

        $this->post('/cikis');
        $this->post('/giris', ['email' => $user->email, 'password' => 'YeniSifre9']);
        $this->assertAuthenticatedAs($user);
    }
}
