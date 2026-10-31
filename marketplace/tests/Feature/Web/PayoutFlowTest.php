<?php

namespace Tests\Feature\Web;

use App\Enums\PayoutRequestStatus;
use App\Enums\VerificationStatus;
use App\Models\CreatorPayoutRequest;
use App\Models\CreatorVerification;
use App\Models\Role;
use App\Models\User;
use App\Services\Earnings\CreatorEarningService;
use App\Services\Sales\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class PayoutFlowTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private const VALID_TCKN = '10000000146';

    private const VALID_TR_IBAN = 'TR33 0006 1005 1978 6457 8413 26';

    /**
     * 10 adet 10 USD'lik satış: 80 USD kullanılabilir hakediş.
     */
    private function creatorWithEarnings(string $country = 'TR'): User
    {
        $creator = User::factory()->creator()->create(['country_code' => $country]);
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 10_000);

        for ($i = 0; $i < 10; $i++) {
            app(PurchaseService::class)->purchase($buyer, $this->publishedProduct(1000, $creator), null, "s{$i}");
        }

        $this->travel(15)->days();
        app(CreatorEarningService::class)->releaseMatured();

        return $creator->fresh();
    }

    private function verificationData(array $overrides = []): array
    {
        return ['legal_name' => 'Ada Kaya', 'entity_type' => 'individual', 'country_code' => 'TR', 'identity_number' => self::VALID_TCKN, 'address' => 'Örnek Mah. Test Sok. No:1 İstanbul', 'consent' => '1', ...$overrides];
    }

    private function approveVerification(User $creator): void
    {
        $this->actingAs($creator)->post(route('creator.payouts.verify'), $this->verificationData());
        CreatorVerification::query()->latest('id')->first()->forceFill(['status' => VerificationStatus::Approved])->save();
    }

    public function test_verification_validates_turkish_identity_number_and_encrypts_it(): void
    {
        $creator = User::factory()->creator()->create(['country_code' => 'TR']);

        $this->actingAs($creator)->post(route('creator.payouts.verify'), $this->verificationData(['identity_number' => '12345678901']))->assertSessionHasErrors('identity_number');
        $this->post(route('creator.payouts.verify'), $this->verificationData(['consent' => null]))->assertSessionHasErrors('consent');
        $this->post(route('creator.payouts.verify'), $this->verificationData())->assertRedirect()->assertSessionHasNoErrors();

        $verification = CreatorVerification::query()->sole();
        $this->assertSame(VerificationStatus::Pending, $verification->status);
        $this->assertStringNotContainsString(self::VALID_TCKN, DB::table('creator_verifications')->value('identity_number'));

        $this->post(route('creator.payouts.verify'), $this->verificationData())->assertSessionHasErrors('document');
        $this->assertSame(1, CreatorVerification::query()->count(), 'Bekleyen doğrulama varken ikincisi gönderilemez');
    }

    public function test_verification_document_must_be_a_real_pdf_or_image(): void
    {
        Storage::fake('private');
        $creator = User::factory()->creator()->create(['country_code' => 'TR']);

        $this->actingAs($creator)->post(route('creator.payouts.verify'), $this->verificationData(['document' => UploadedFile::fake()->createWithContent('kimlik.pdf', '<?php echo 1;')]))->assertSessionHasErrors('document');
        $this->post(route('creator.payouts.verify'), $this->verificationData(['document' => UploadedFile::fake()->createWithContent('kimlik.pdf', '%PDF-1.4 test')]))->assertRedirect()->assertSessionHasNoErrors();

        $path = CreatorVerification::query()->sole()->document_path;
        Storage::disk('private')->assertExists($path);
        $this->assertStringNotContainsString('kimlik', $path);
    }

    public function test_payout_requires_verification_valid_iban_and_sufficient_balance(): void
    {
        $creator = $this->creatorWithEarnings();

        $this->actingAs($creator)->get(route('creator.payouts'))->assertOk()->assertSee('$80.00');
        $this->post(route('creator.payouts.store'), ['method' => 'bank_transfer_try', 'amount' => '60', 'details' => ['account_holder' => 'Ada Kaya', 'iban' => self::VALID_TR_IBAN]])->assertSessionHasErrors('amount');

        $this->approveVerification($creator);

        $this->post(route('creator.payouts.store'), ['method' => 'bank_transfer_try', 'amount' => '60', 'details' => ['account_holder' => 'Ada Kaya', 'iban' => 'TR330006100519786457841327']])->assertSessionHasErrors('details.iban');
        $this->post(route('creator.payouts.store'), ['method' => 'swift_usd', 'amount' => '60', 'details' => ['account_holder' => 'Ada Kaya']])->assertSessionHasErrors('method');
        $this->post(route('creator.payouts.store'), ['method' => 'bank_transfer_try', 'amount' => '95', 'details' => ['account_holder' => 'Ada Kaya', 'iban' => self::VALID_TR_IBAN]])->assertSessionHasErrors('amount');

        $this->post(route('creator.payouts.store'), ['method' => 'bank_transfer_try', 'amount' => '60.50', 'details' => ['account_holder' => 'Ada Kaya', 'iban' => self::VALID_TR_IBAN]])->assertRedirect()->assertSessionHasNoErrors();

        $request = CreatorPayoutRequest::query()->sole();
        $this->assertSame(6050, $request->amount_cents);
        $this->assertSame('TRY', $request->currency);
        $this->assertSame('TR330006100519786457841326', $request->payout_details['iban']);
        $this->assertSame(6050, $creator->creatorBalance()->sole()->reserved_cents);
    }

    public function test_international_creator_is_offered_usd_methods(): void
    {
        $creator = $this->creatorWithEarnings('DE');
        $this->approveVerification($creator);

        $this->actingAs($creator)->post(route('creator.payouts.store'), ['method' => 'bank_transfer_try', 'amount' => '60', 'details' => ['account_holder' => 'A', 'iban' => self::VALID_TR_IBAN]])->assertSessionHasErrors('method');
        $this->post(route('creator.payouts.store'), ['method' => 'wise_usd', 'amount' => '60', 'details' => ['account_holder' => 'Anna Berg', 'email' => 'anna@example.com']])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('USD', CreatorPayoutRequest::query()->sole()->currency);
    }

    public function test_admin_reviews_verification_and_pays_out_in_try(): void
    {
        $creator = $this->creatorWithEarnings();
        $this->actingAs($creator)->post(route('creator.payouts.verify'), $this->verificationData());
        $admin = $this->actingAsAdmin();
        $this->setUsdTryRate('40');

        $verification = CreatorVerification::query()->sole();
        $this->get(route('admin.payouts.verifications'))->assertOk()->assertSee(self::VALID_TCKN);
        $this->put(route('admin.payouts.verifications.review', $verification), ['status' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($creator->creatorProfile->fresh()->isVerified());

        $this->actingAs($creator)->post(route('creator.payouts.store'), ['method' => 'bank_transfer_try', 'amount' => '50', 'details' => ['account_holder' => 'Ada Kaya', 'iban' => self::VALID_TR_IBAN]]);
        $request = CreatorPayoutRequest::query()->sole();

        $this->actingAsAdmin($admin);
        $this->get(route('admin.payouts.show', $request))->assertOk()->assertSee('TR330006100519786457841326');
        $this->assertDatabaseHas('audit_logs', ['event' => 'payout.details_viewed', 'actor_id' => $admin->id]);

        $this->post(route('admin.payouts.decide', $request), ['decision' => 'paid', 'paid_amount' => '2000,00', 'reference' => 'EFT-1'])->assertSessionHasErrors('decision');
        $this->post(route('admin.payouts.decide', $request), ['decision' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('admin.payouts.show', $request))->assertSee('2000.00', false);
        $this->post(route('admin.payouts.decide', $request), ['decision' => 'paid', 'paid_amount' => '2000,00', 'reference' => 'EFT-1', 'exchange_rate' => '40'])->assertRedirect()->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(PayoutRequestStatus::Paid, $request->status);
        $this->assertSame(200000, $request->payout->paid_amount_minor);
        $this->assertSame(40_000_000, $request->payout->exchange_rate_micros);

        $balance = $creator->creatorBalance()->sole();
        $this->assertSame(3000, $balance->available_cents);
        $this->assertSame(0, $balance->reserved_cents);
        $this->assertSame(5000, $balance->paid_cents);

        $this->actingAs($creator)->get(route('creator.payouts'))->assertSee('EFT-1');
    }

    public function test_rejected_payout_releases_the_reserve(): void
    {
        $creator = $this->creatorWithEarnings();
        $this->approveVerification($creator);
        $this->actingAs($creator)->post(route('creator.payouts.store'), ['method' => 'bank_transfer_try', 'amount' => '80', 'details' => ['account_holder' => 'Ada', 'iban' => self::VALID_TR_IBAN]]);
        $request = CreatorPayoutRequest::query()->sole();

        $this->actingAsAdmin();
        $this->post(route('admin.payouts.decide', $request), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->post(route('admin.payouts.decide', $request), ['decision' => 'reject', 'note' => 'IBAN sahibi ile satıcı adı uyuşmuyor'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, $creator->creatorBalance()->sole()->reserved_cents);
        $this->assertSame(PayoutRequestStatus::Rejected, $request->fresh()->status);
    }

    public function test_moderator_cannot_access_payouts(): void
    {
        $this->actingAsAdmin(User::factory()->withRole(Role::Moderator)->create());

        $this->get(route('admin.payouts.index'))->assertForbidden();
    }
}
