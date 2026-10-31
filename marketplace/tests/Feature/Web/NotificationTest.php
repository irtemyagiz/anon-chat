<?php

namespace Tests\Feature\Web;

use App\Enums\CreatorStatus;
use App\Models\PointPackage;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\CommentRepliedNotification;
use App\Notifications\CreatorPublishedNotification;
use App\Notifications\CreatorReviewedNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\NewFollowerNotification;
use App\Notifications\NewSaleNotification;
use App\Notifications\PointsToppedUpNotification;
use App\Notifications\ProductReviewedNotification;
use App\Notifications\PurchaseCompletedNotification;
use App\Notifications\WelcomeNotification;
use App\Services\Accounts\AccountService;
use App\Services\Community\CommunityService;
use App\Services\Community\ReviewService;
use App\Services\Payments\PointTopUpService;
use App\Services\Products\ProductManager;
use App\Services\Sales\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    public function test_purchase_notifies_buyer_and_creator(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $product = $this->publishedProduct(1000);
        $this->givePoints($buyer, 1000);

        app(PurchaseService::class)->purchase($buyer, $product, null, 'n1');

        Notification::assertSentTo($buyer, PurchaseCompletedNotification::class);
        Notification::assertSentTo($product->creator, NewSaleNotification::class, fn (NewSaleNotification $n) => $n->creatorAmountCents === 800);
    }

    public function test_verified_top_up_notifies_user_and_suspicious_payment_alerts_only_payment_staff(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->withRole(Role::Moderator)->create();
        $user = User::factory()->create();
        $purchase = app(PointTopUpService::class)->createPurchase($user, PointPackage::query()->where('code', 'starter')->sole(), 'sandbox');

        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase, amount: 1));
        Notification::assertSentTo($admin, AdminAlertNotification::class);
        Notification::assertNotSentTo($moderator, AdminAlertNotification::class);
        Notification::assertNotSentTo($user, PointsToppedUpNotification::class);

        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase));
        Notification::assertSentTo($user, PointsToppedUpNotification::class, fn ($n) => $n->points === 500);
    }

    public function test_creator_lifecycle_notifications(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = app(AccountService::class)->register(['username' => 'ada', 'first_name' => 'Ada', 'last_name' => 'K', 'email' => 'ada@example.com', 'password' => 'Sifre1234']);
        Notification::assertSentTo($user, WelcomeNotification::class);

        $profile = app(AccountService::class)->applyAsCreator($user, ['designer_name' => 'Ada', 'store_name' => 'Ada Atölye']);
        Notification::assertSentTo($admin, AdminAlertNotification::class, fn ($n) => str_contains($n->alertTitle, 'creator'));

        app(AccountService::class)->reviewCreator($profile, CreatorStatus::Approved, $admin);
        Notification::assertSentTo($user, CreatorReviewedNotification::class, fn ($n) => $n->status === 'approved');
    }

    public function test_product_approval_notifies_creator_and_followers(): void
    {
        Notification::fake();
        $creator = User::factory()->creator()->create();
        $fan = User::factory()->create();
        app(CommunityService::class)->toggleFollow($fan, $creator->creatorProfile);
        Notification::assertSentTo($creator, NewFollowerNotification::class);

        $product = $this->publishedProduct(500, $creator);
        $product->forceFill(['status' => 'pending_review', 'published_at' => null])->save();
        app(ProductManager::class)->approve($product, User::factory()->admin()->create());

        Notification::assertSentTo($creator, ProductReviewedNotification::class, fn ($n) => $n->approved);
        Notification::assertSentTo($fan, CreatorPublishedNotification::class);
    }

    public function test_comment_notifications_skip_self_actions(): void
    {
        Notification::fake();
        $product = $this->publishedProduct(500);
        $author = User::factory()->create();

        $comment = app(ReviewService::class)->comment($author, $product, 'Soru var');
        Notification::assertSentTo($product->creator, NewCommentNotification::class);

        app(ReviewService::class)->comment($product->creator, $product, 'Yanıtım', $comment);
        Notification::assertSentTo($author, CommentRepliedNotification::class);

        Notification::fake();
        app(ReviewService::class)->comment($product->creator, $product, 'Kendi notum');
        Notification::assertNothingSent();
    }

    public function test_mail_channel_follows_user_preferences(): void
    {
        $user = User::factory()->create();
        $notification = new NewFollowerNotification('Ali');

        $this->assertSame(['database'], $notification->via($user), 'Topluluk bildirimleri varsayılan olarak yalnızca site içi');
        $this->assertSame(['database', 'mail'], (new PurchaseCompletedNotification('Model', 'model'))->via($user));

        $this->actingAs($user)->put(route('account.notifications.preferences'), ['mail' => ['community']])->assertRedirect();

        $this->assertSame(['database', 'mail'], $notification->via($user));
        $this->assertSame(['database'], (new PurchaseCompletedNotification('Model', 'model'))->via($user));
    }

    public function test_bell_page_and_safe_redirects(): void
    {
        $user = User::factory()->create();
        $user->notify(new PurchaseCompletedNotification('Spiral Vazo', 'spiral-vazo'));
        $user->notify(new AdminAlertNotification('Dış bağlantı', 'Test', 'https://kotu-site.example/phish'));

        $this->actingAs($user)->get(route('home'))->assertSee('Spiral Vazo kütüphanene eklendi');
        $this->get(route('account.notifications'))->assertOk()->assertSee('Satın alma tamamlandı');

        $safe = $user->notifications()->where('data->title', 'Satın alma tamamlandı')->first();
        $this->get(route('account.notifications.open', $safe->id))->assertRedirect(route('account.library'));
        $this->assertNotNull($safe->fresh()->read_at);

        $external = $user->notifications()->where('data->title', 'Dış bağlantı')->first();
        $this->get(route('account.notifications.open', $external->id))->assertRedirect(route('account.notifications'));

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('account.notifications.open', $safe->id))->assertNotFound();

        $this->actingAs($user)->post(route('account.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
