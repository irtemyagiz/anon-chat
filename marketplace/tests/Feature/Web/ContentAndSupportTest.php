<?php

namespace Tests\Feature\Web;

use App\Enums\PointPurchaseStatus;
use App\Models\Banner;
use App\Models\Order;
use App\Models\Page;
use App\Models\PointPackage;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use App\Notifications\SupportTicketRepliedNotification;
use App\Services\Payments\PointTopUpService;
use App\Services\Sales\PurchaseService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class ContentAndSupportTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    public function test_seeded_legal_pages_are_linked_and_marked_as_drafts(): void
    {
        $this->get('/')->assertSee(route('pages.show', 'kullanim-sartlari'))->assertSee('Gizlilik ve KVKK');
        $this->get(route('pages.show', 'mesafeli-satis-sozlesmesi'))->assertOk()->assertSee('Cayma hakkı')->assertSee('Hukuki inceleme gereklidir');
        $this->get('/kayit')->assertSee(route('pages.show', 'kullanim-sartlari'));
        $this->get(route('faq'))->assertOk()->assertSee('Puan nedir');
    }

    public function test_page_markdown_strips_html_and_unpublished_pages_are_hidden(): void
    {
        Page::query()->create(['type' => 'page', 'slug' => 'deneme', 'locale' => 'tr', 'title' => 'Deneme', 'body' => "## Başlık\n<script>alert(1)</script>\n[kötü](javascript:alert(1))", 'is_published' => true]);
        Page::query()->create(['type' => 'page', 'slug' => 'gizli', 'locale' => 'tr', 'title' => 'Gizli', 'body' => 'x', 'is_published' => false]);

        $this->get(route('pages.show', 'deneme'))->assertOk()->assertSee('<h2>Başlık</h2>', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('javascript:alert', false);
        $this->get(route('pages.show', 'gizli'))->assertNotFound();
    }

    public function test_admin_manages_pages_faqs_and_banners(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('admin.content.pages.store'), ['type' => 'blog', 'locale' => 'tr', 'title' => 'İlk Yazımız', 'body' => 'Merhaba **dünya**', 'is_published' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('blog.index'))->assertSee('İlk Yazımız');
        $this->get(route('blog.show', 'ilk-yazimiz'))->assertSee('<strong>dünya</strong>', false);
        $this->post(route('admin.content.pages.store'), ['type' => 'blog', 'locale' => 'tr', 'title' => 'İlk Yazımız', 'body' => 'x'])->assertSessionHasErrors('slug');

        $this->post(route('admin.content.faqs.store'), ['locale' => 'tr', 'question' => 'Yeni soru?', 'answer' => 'Cevap.'])->assertRedirect();
        $this->get(route('faq'))->assertSee('Yeni soru?');

        $this->post(route('admin.content.banners.store'), ['title_tr' => 'Yaz kampanyası', 'link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('link_url');
        $this->post(route('admin.content.banners.store'), ['title_tr' => 'Yaz kampanyası', 'link_url' => '/modeller', 'link_label_tr' => 'Keşfet', 'image' => UploadedFile::fake()->image('b.jpg', 1600, 600)])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists(Banner::query()->sole()->image_path);
        $this->get('/')->assertSee('Yaz kampanyası');
    }

    public function test_moderator_cannot_manage_content_or_settings(): void
    {
        $this->actingAsAdmin(User::factory()->withRole(Role::Moderator)->create());

        $this->get(route('admin.content.pages'))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
        $this->get(route('admin.support.index'))->assertOk();
    }

    public function test_site_settings_and_maintenance_mode(): void
    {
        $this->actingAsAdmin();
        $this->put(route('admin.settings.update'), ['site_name' => 'Katman Beta', 'max_file_size_mb' => 50, 'maintenance' => 1, 'maintenance_message' => 'Yeni sürüm yükleniyor'])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/')->assertOk()->assertSee('Katman Beta', false);
        $this->assertSame(50, app(SettingsService::class)->integer('storage.max_file_size_mb'));

        auth()->logout();
        $this->flushSession();
        $this->get('/')->assertStatus(503)->assertSee('Yeni sürüm yükleniyor');
        $this->get('/giris')->assertOk();

        $purchase = app(PointTopUpService::class)->createPurchase(User::factory()->create(), PointPackage::query()->where('code', 'starter')->sole(), 'sandbox');
        $this->sendSandboxWebhook($this->paymentSucceededPayload($purchase))->assertOk();
    }

    public function test_smtp_settings_are_encrypted_and_test_mail_is_sent(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();

        $this->put(route('admin.settings.mail'), ['host' => 'mail.example.com', 'port' => 587, 'username' => 'noreply', 'password' => 'smtp-gizli', 'encryption' => 'tls', 'from_address' => 'noreply@example.com', 'from_name' => 'Katman'])->assertRedirect()->assertSessionHasNoErrors();

        $stored = DB::table('system_settings')->where('key', 'mail.password')->value('value');
        $this->assertStringNotContainsString('smtp-gizli', $stored);
        $this->assertSame('smtp-gizli', app(SettingsService::class)->get('mail.password'));

        $this->post(route('admin.settings.mail.test'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.updated', 'actor_id' => $admin->id]);
    }

    public function test_route_seo_overrides(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.seo.update'), ['seo' => ['home' => ['tr' => ['meta_title' => 'STL Modelleri ve 3D Baskı Dosyaları', 'meta_description' => 'Özel açıklama']], 'designers.index' => ['tr' => ['noindex' => 1]]]])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/')->assertSee('<title>STL Modelleri ve 3D Baskı Dosyaları · Katman</title>', false)->assertSee('content="Özel açıklama"', false);
        $this->get('/tasarimcilar')->assertSee('noindex, nofollow', false);
    }

    public function test_support_ticket_flow(): void
    {
        Notification::fake();
        $support = User::factory()->withRole(Role::Moderator)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('support.store'), ['subject' => 'İndirme çalışmıyor', 'category' => 'download', 'body' => 'Dosyayı indirirken hata alıyorum.'])->assertRedirect();
        $ticket = SupportTicket::query()->sole();
        Notification::assertSentTo($support, AdminAlertNotification::class);

        $this->actingAs(User::factory()->create())->get(route('support.show', $ticket))->assertNotFound();

        $this->actingAsAdmin($support);
        $this->post(route('admin.support.reply', $ticket), ['body' => 'Bağlantıyı yeniledik, tekrar dener misin?'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('answered', $ticket->fresh()->status);
        Notification::assertSentTo($user, SupportTicketRepliedNotification::class);

        $this->actingAs($user)->post(route('support.reply', $ticket), ['body' => 'Oldu, teşekkürler!'])->assertRedirect();
        $this->assertSame('awaiting_staff', $ticket->fresh()->status);
        $this->post(route('support.close', $ticket));
        $this->post(route('support.reply', $ticket), ['body' => 'Bir şey daha'])->assertSessionHasErrors('body');
    }

    public function test_refund_ticket_requires_own_paid_purchase_and_attachments_are_validated(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = app(PointTopUpService::class)->createPurchase($user, PointPackage::query()->where('code', 'starter')->sole(), 'sandbox');
        $mine->forceFill(['status' => PointPurchaseStatus::Paid])->save();
        $theirs = app(PointTopUpService::class)->createPurchase($other, PointPackage::query()->where('code', 'starter')->sole(), 'sandbox');
        $theirs->forceFill(['status' => PointPurchaseStatus::Paid])->save();

        $base = ['subject' => 'Puan iadesi istiyorum', 'category' => 'refund', 'body' => 'Yanlışlıkla yükledim, hiç kullanmadım.'];
        $this->actingAs($user)->post(route('support.store'), $base)->assertSessionHasErrors('point_purchase_id');
        $this->post(route('support.store'), [...$base, 'point_purchase_id' => $theirs->id])->assertSessionHasErrors('point_purchase_id');
        $this->post(route('support.store'), [...$base, 'point_purchase_id' => $mine->id, 'attachment' => UploadedFile::fake()->createWithContent('dekont.pdf', '<?php')])->assertSessionHasErrors('body');
        $this->post(route('support.store'), [...$base, 'point_purchase_id' => $mine->id, 'attachment' => UploadedFile::fake()->createWithContent('dekont.pdf', '%PDF-1.4')])->assertRedirect()->assertSessionHasNoErrors();

        $ticket = SupportTicket::query()->sole();
        $this->assertSame($mine->id, $ticket->point_purchase_id);
        $this->assertSame('high', $ticket->priority);

        $message = $ticket->messages()->sole();
        $this->get(route('support.attachment', $message))->assertOk();
        $this->actingAs($other)->get(route('support.attachment', $message))->assertNotFound();
    }

    public function test_account_deletion_anonymizes_personal_data_and_keeps_financial_records(): void
    {
        $user = User::factory()->create(['email' => 'silinecek@example.com', 'first_name' => 'Gizli']);
        $product = $this->publishedProduct(500);
        $this->givePoints($user, 500);
        app(PurchaseService::class)->purchase($user, $product, null, 'del-1');

        $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'yanlis', 'confirm' => 1])->assertSessionHasErrors('password');
        $this->delete(route('account.destroy'), ['password' => 'password', 'confirm' => 1])->assertRedirect(route('home'));

        $this->assertGuest();
        $deleted = User::withTrashed()->find($user->id);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertStringEndsWith('@deleted.invalid', $deleted->email);
        $this->assertNotSame('Gizli', $deleted->first_name);
        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count(), 'Mali kayıtlar saklanır');
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deleted']);

        $this->post('/giris', ['email' => 'silinecek@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_creator_with_unpaid_earnings_cannot_delete_account(): void
    {
        $creator = User::factory()->creator()->create();
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        app(PurchaseService::class)->purchase($buyer, $this->publishedProduct(1000, $creator), null, 'c1');

        $this->actingAs($creator)->delete(route('account.destroy'), ['password' => 'password', 'confirm' => 1])->assertSessionHasErrors('delete');
        $this->assertNull($creator->fresh()->deleted_at);
    }
}
