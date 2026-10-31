<?php

namespace Tests\Feature\Web;

use App\Enums\CreatorStatus;
use App\Enums\PointPurchaseStatus;
use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Exceptions\InvalidUploadException;
use App\Http\Middleware\RequireTwoFactor;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\FileFormat;
use App\Models\PaymentProvider;
use App\Models\PointPackage;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Payments\PointTopUpService;
use App\Services\Products\ProductManager;
use App\Services\Sales\PurchaseService;
use App\Services\Security\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function adminPages(): array
    {
        return collect([
            'admin.dashboard', 'admin.creators.index', 'admin.products.index', 'admin.users.index', 'admin.points.index',
            'admin.payments.index', 'admin.payments.webhooks', 'admin.exchange-rates.index', 'admin.commissions.index',
            'admin.categories.index', 'admin.file-formats.index', 'admin.logs',
        ])->mapWithKeys(fn (string $route) => [$route => [$route]])->all();
    }

    #[DataProvider('adminPages')]
    public function test_admin_pages_render(string $route): void
    {
        $this->actingAsAdmin();

        $this->get(route($route))->assertOk();
    }

    public function test_non_staff_cannot_enter(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->creator()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_must_set_up_two_factor_before_using_the_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.setup'));
        $this->get(route('admin.two-factor.setup'))->assertOk()->assertSee('QR');

        $secret = session('two_factor_setup_secret');
        $this->post(route('admin.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('admin.two-factor.confirm'), ['code' => $this->currentTotp($secret)])->assertRedirect(route('admin.two-factor.setup'));

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertNotSame($secret, DB::table('users')->where('id', $admin->id)->value('two_factor_secret'), 'Gizli anahtar şifreli saklanır');
        $this->assertCount(8, session('recovery_codes'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_two_factor_challenge_rejects_wrong_and_replayed_codes(): void
    {
        $admin = User::factory()->admin()->create();
        $secret = app(TwoFactorAuthenticator::class)->generateSecret();
        $admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertRedirect(route('admin.two-factor.challenge'));

        $this->post(route('admin.two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertDatabaseHas('security_events', ['user_id' => $admin->id, 'type' => 'admin.two_factor_failed']);

        $code = $this->currentTotp($secret);
        $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.users.index'));
        $this->get(route('admin.users.index'))->assertOk();

        $this->withSession([RequireTwoFactor::SESSION_KEY => null]);
        $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_recovery_code_works_exactly_once(): void
    {
        $admin = User::factory()->admin()->create();
        $codes = app(TwoFactorAuthenticator::class)->enable($admin, app(TwoFactorAuthenticator::class)->generateSecret());

        $this->actingAs($admin)->post(route('admin.two-factor.verify'), ['recovery_code' => strtolower($codes[0])])->assertRedirect();
        $this->get(route('admin.dashboard'))->assertOk();

        $this->withSession([RequireTwoFactor::SESSION_KEY => null]);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_moderator_sees_only_permitted_modules(): void
    {
        $this->actingAsAdmin(User::factory()->withRole(Role::Moderator)->create());

        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.payments.index'));
        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.commissions.index'))->assertForbidden();
        $this->put(route('admin.commissions.default'), ['percent' => 1])->assertForbidden();
    }

    public function test_creator_application_is_approved_from_the_panel(): void
    {
        $admin = $this->actingAsAdmin();
        $creator = User::factory()->creator(CreatorStatus::Pending)->create();

        $this->get(route('admin.creators.index'))->assertSee($creator->creatorProfile->store_name);
        $this->put(route('admin.creators.update', $creator->creatorProfile), ['status' => 'rejected'])->assertSessionHasErrors('reason');
        $this->put(route('admin.creators.update', $creator->creatorProfile), ['status' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($creator->fresh()->isApprovedCreator());
        $this->assertDatabaseHas('audit_logs', ['event' => 'creator.approved', 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('admin_logs', ['admin_id' => $admin->id, 'action' => 'admin.creators.update']);
    }

    public function test_product_moderation_from_the_panel(): void
    {
        $admin = $this->actingAsAdmin();
        $product = $this->publishedProduct(500);
        $product->forceFill(['status' => ProductStatus::PendingReview, 'published_at' => null])->save();

        $this->get(route('admin.products.show', $product))->assertOk()->assertSee($product->title);
        $this->post(route('admin.products.decide', $product), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->post(route('admin.products.decide', $product), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ProductStatus::Published, $product->fresh()->status);

        $this->post(route('admin.products.feature', $product));
        $this->assertTrue($product->fresh()->is_featured);

        $this->post(route('admin.products.decide', $product), ['decision' => 'unpublish', 'note' => 'Telif hakkı şikâyeti'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(ProductStatus::Unpublished, $product->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'product.taken_down', 'actor_id' => $admin->id]);
    }

    public function test_user_suspension_rules(): void
    {
        $admin = $this->actingAsAdmin();
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->put(route('admin.users.status', $user), ['status' => 'suspended'])->assertSessionHasErrors('reason');
        $this->put(route('admin.users.status', $user), ['status' => 'suspended', 'reason' => 'Sahte ödeme denemesi'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);

        $this->put(route('admin.users.status', $admin), ['status' => 'suspended', 'reason' => 'kendimi'])->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_role_changes_are_admin_only_and_cannot_remove_own_admin_role(): void
    {
        $admin = $this->actingAsAdmin();
        $user = User::factory()->withRole(Role::Customer)->create();

        $this->put(route('admin.users.roles', $user), ['roles' => ['moderator', 'creator']])->assertSessionHasErrors('roles.1');
        $this->put(route('admin.users.roles', $user), ['roles' => ['moderator']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasRole(Role::Moderator));
        $this->assertTrue($user->fresh()->hasRole(Role::Customer));
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.roles_changed', 'actor_id' => $admin->id]);

        $this->put(route('admin.users.roles', $admin), ['roles' => []])->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_wallet_adjustment_requires_reason_and_is_audited(): void
    {
        $admin = $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->post(route('admin.users.adjust', $user), ['points' => 250, 'reason' => 'kısa', 'request_id' => (string) Str::uuid()])->assertSessionHasErrors('reason');
        $this->post(route('admin.users.adjust', $user), ['points' => -50, 'reason' => 'Bakiyeden fazla düşüm denemesi', 'request_id' => (string) Str::uuid()])->assertSessionHasErrors('points');

        $requestId = (string) Str::uuid();
        $this->post(route('admin.users.adjust', $user), ['points' => 250, 'reason' => 'Destek talebi #42 telafisi', 'request_id' => $requestId])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.users.adjust', $user), ['points' => 250, 'reason' => 'Destek talebi #42 telafisi', 'request_id' => $requestId]);

        $this->assertSame(250, $this->balanceOf($user), 'Aynı form iki kez gönderilse de bir kez işlenir');
        $this->assertSame($admin->id, AuditLog::query()->where('event', 'wallet.adjusted')->sole()->actor_id);
        $this->get(route('admin.users.show', $user))->assertSee('Yönetici düzeltmesi');
    }

    public function test_point_packages_follow_the_fixed_rate_and_used_packages_are_not_deleted(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.points.packages.store'), ['name_tr' => 'Mega', 'points' => 7500])->assertRedirect()->assertSessionHasNoErrors();
        $mega = PointPackage::query()->where('points', 7500)->sole();
        $this->assertSame(7500, $mega->price_usd_cents, '7500 POINT = 75 USD');

        $starter = PointPackage::query()->where('code', 'starter')->sole();
        $purchase = app(PointTopUpService::class)->createPurchase(User::factory()->create(), $starter, 'sandbox');
        $this->delete(route('admin.points.packages.destroy', $starter))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($starter->fresh()->is_active);
        $this->assertNotNull($purchase->fresh()->point_package_id);

        $this->delete(route('admin.points.packages.destroy', $mega));
        $this->assertModelMissing($mega);

        $this->put(route('admin.points.minimum'), ['minimum' => 1000])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1000, app(PointTopUpService::class)->minimumTopUpPoints());
    }

    public function test_exchange_rate_management(): void
    {
        $admin = $this->actingAsAdmin();

        $this->post(route('admin.exchange-rates.manual'), ['rate' => 'abc'])->assertSessionHasErrors('rate');
        $this->post(route('admin.exchange-rates.manual'), ['rate' => '42,15'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('admin.exchange-rates.index'))->assertSee('42.150000');
        $this->assertDatabaseHas('audit_logs', ['event' => 'exchange_rate.manual_set', 'actor_id' => $admin->id]);

        Http::fake(['*' => Http::response('down', 500)]);
        $this->post(route('admin.exchange-rates.refresh'))->assertSessionHasErrors('rate');

        $this->post(route('admin.exchange-rates.auto'));
        $this->artisan('marketplace:refresh-exchange-rates')->expectsOutput('Otomatik kur güncelleme kapalı.')->assertSuccessful();
    }

    public function test_commission_settings_apply_to_new_sales_only(): void
    {
        $this->actingAsAdmin();
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 3000);
        $before = app(PurchaseService::class)->purchase($buyer, $this->publishedProduct(1000), null, 'a')->items()->sole();

        $this->put(route('admin.commissions.default'), ['percent' => 15])->assertRedirect()->assertSessionHasNoErrors();
        $after = app(PurchaseService::class)->purchase($buyer, $this->publishedProduct(1000), null, 'b')->items()->sole();

        $this->assertSame(2000, $before->fresh()->commission_bps);
        $this->assertSame(1500, $after->commission_bps);

        $creator = User::factory()->creator()->create(['email' => 'vip@example.com']);
        $this->post(route('admin.commissions.rules.store'), ['scope' => 'creator', 'name' => 'VIP', 'percent' => 5, 'creator' => 'yok@example.com'])->assertSessionHasErrors('creator');
        $this->post(route('admin.commissions.rules.store'), ['scope' => 'creator', 'name' => 'VIP', 'percent' => 5, 'creator' => 'vip@example.com'])->assertRedirect()->assertSessionHasNoErrors();
        $vipSale = app(PurchaseService::class)->purchase($buyer, $this->publishedProduct(1000, $creator), null, 'c')->items()->sole();
        $this->assertSame(500, $vipSale->commission_bps);
    }

    public function test_refund_and_provider_controls(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();
        $purchase = app(PointTopUpService::class)->createPurchase($user, PointPackage::query()->where('code', 'starter')->sole(), 'sandbox');
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase));

        $this->post(route('admin.payments.refund', $purchase), ['reason' => 'Kullanıcı talebi'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(PointPurchaseStatus::Refunded, $purchase->fresh()->status);
        $this->assertSame(0, $this->balanceOf($user));

        $stripe = PaymentProvider::query()->where('code', 'stripe')->sole();
        $this->post(route('admin.payments.providers.toggle', $stripe))->assertSessionHasErrors('provider');
        $this->assertFalse($stripe->fresh()->is_enabled, 'Entegrasyonu olmayan sağlayıcı açılamaz');
    }

    public function test_category_and_file_format_management(): void
    {
        $this->actingAsAdmin();
        $parent = Category::query()->where('slug', 'figurler')->sole();

        $this->post(route('admin.categories.store'), ['name_tr' => 'Anime Figürleri', 'parent_id' => $parent->id])->assertRedirect()->assertSessionHasNoErrors();
        $child = Category::query()->where('slug', 'anime-figurleri')->sole();
        $this->assertSame($parent->id, $child->parent_id);
        $this->get('/kategori/figurler')->assertSee('Anime Figürleri');

        $this->put(route('admin.categories.update', $child), ['name_tr' => 'Anime Figürleri', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');

        Storage::fake('private');
        $stl = FileFormat::query()->where('extension', 'stl')->sole();
        $this->post(route('admin.file-formats.toggle', $stl));
        $this->assertFalse($stl->fresh()->is_active);

        $creator = User::factory()->creator()->create();
        $product = Product::factory()->for($creator, 'creator')->create();
        $this->expectException(InvalidUploadException::class);
        app(ProductManager::class)->addFiles($product, [new UploadedFile(base_path('tests/Fixtures/cube.stl'), 'a.stl', null, null, true)]);
    }

    public function test_admin_actions_are_logged_without_secrets(): void
    {
        $admin = $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->put(route('admin.users.status', $user), ['status' => 'suspended', 'reason' => 'Test', 'password' => 'gizli123']);

        $log = DB::table('admin_logs')->where('admin_id', $admin->id)->where('action', 'admin.users.status')->sole();
        $this->assertStringContainsString('suspended', $log->payload);
        $this->assertStringNotContainsString('gizli123', $log->payload);
        $this->assertStringNotContainsString('_token', $log->payload);

        $this->get(route('admin.logs', ['tur' => 'yonetici']))->assertSee('admin.users.status');
    }
}
