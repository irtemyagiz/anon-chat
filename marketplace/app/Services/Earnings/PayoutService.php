<?php

namespace App\Services\Earnings;

use App\Enums\EarningStatus;
use App\Enums\PayoutRequestStatus;
use App\Exceptions\MarketplaceException;
use App\Models\CreatorEarning;
use App\Models\CreatorPayout;
use App\Models\CreatorPayoutRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creator ödeme talepleri.
 *
 * Talep oluşturulduğunda tutar kullanılabilir bakiyeden rezerve edilir; ret
 * halinde rezerv çözülür, ödeme yapıldığında bakiyeden düşülür. Kimlik ve vergi
 * doğrulaması tamamlanmamış satıcılar talep oluşturamaz.
 */
class PayoutService
{
    public function __construct(
        private readonly CreatorEarningService $earnings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, string>  $details  Banka/ödeme bilgileri (şifreli saklanır)
     */
    public function request(User $creator, int $amountCents, string $currency, string $method, array $details): CreatorPayoutRequest
    {
        if (! $creator->isApprovedCreator() || ! $creator->creatorProfile->isVerified()) {
            throw new MarketplaceException('Ödeme talebi için satıcı kimlik ve vergi doğrulamasının tamamlanması gerekir.');
        }

        $minimum = (int) config('marketplace.earnings.minimum_payout_cents');

        if ($amountCents < $minimum) {
            throw new MarketplaceException('Talep edilen tutar minimum ödeme tutarının altında.');
        }

        return DB::transaction(function () use ($creator, $amountCents, $currency, $method, $details): CreatorPayoutRequest {
            $balance = $this->earnings->lockBalance($creator->id);

            if ($amountCents > $balance->withdrawableCents()) {
                throw new MarketplaceException('Talep edilen tutar kullanılabilir bakiyenizi aşıyor.');
            }

            $balance->reserved_cents += $amountCents;
            $balance->save();

            return CreatorPayoutRequest::forceCreate([
                'uuid' => (string) Str::uuid(),
                'creator_id' => $creator->id,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'method' => $method,
                'payout_details' => $details,
                'status' => PayoutRequestStatus::Pending,
            ]);
        });
    }

    public function approve(CreatorPayoutRequest $request, User $admin): CreatorPayoutRequest
    {
        return $this->transition($request, $admin, [PayoutRequestStatus::Pending, PayoutRequestStatus::UnderReview], PayoutRequestStatus::Approved);
    }

    public function reject(CreatorPayoutRequest $request, User $admin, string $note): CreatorPayoutRequest
    {
        return DB::transaction(function () use ($request, $admin, $note): CreatorPayoutRequest {
            $request = $this->transition($request, $admin, [PayoutRequestStatus::Pending, PayoutRequestStatus::UnderReview, PayoutRequestStatus::Approved], PayoutRequestStatus::Rejected, $note);

            $balance = $this->earnings->lockBalance($request->creator_id);
            $balance->reserved_cents -= $request->amount_cents;
            $balance->save();

            return $request;
        });
    }

    /**
     * Banka transferi yapıldıktan sonra yönetici tarafından işaretlenir.
     */
    public function markPaid(
        CreatorPayoutRequest $request,
        User $admin,
        string $paidCurrency,
        int $paidAmountMinor,
        ?string $reference = null,
        ?int $exchangeRateMicros = null,
    ): CreatorPayout {
        return DB::transaction(function () use ($request, $admin, $paidCurrency, $paidAmountMinor, $reference, $exchangeRateMicros): CreatorPayout {
            $request = $this->transition($request, $admin, [PayoutRequestStatus::Approved], PayoutRequestStatus::Paid);

            $balance = $this->earnings->lockBalance($request->creator_id);
            $balance->reserved_cents -= $request->amount_cents;
            $balance->available_cents -= $request->amount_cents;
            $balance->paid_cents += $request->amount_cents;
            $balance->save();

            $payout = CreatorPayout::forceCreate([
                'creator_payout_request_id' => $request->id,
                'creator_id' => $request->creator_id,
                'amount_cents' => $request->amount_cents,
                'paid_currency' => $paidCurrency,
                'paid_amount_minor' => $paidAmountMinor,
                'exchange_rate_micros' => $exchangeRateMicros,
                'reference' => $reference,
                'paid_by' => $admin->id,
                'paid_at' => now(),
            ]);

            $this->attachEarnings($payout);

            return $payout;
        });
    }

    /**
     * Ödenen tutarın karşıladığı en eski kullanılabilir kazançları "ödendi" olarak işaretler.
     */
    private function attachEarnings(CreatorPayout $payout): void
    {
        $remaining = $payout->amount_cents;

        $earnings = CreatorEarning::query()
            ->where('creator_id', $payout->creator_id)
            ->where('status', EarningStatus::Available)
            ->orderBy('released_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($earnings as $earning) {
            if ($earning->net_cents > $remaining) {
                break;
            }

            $earning->forceFill(['status' => EarningStatus::Paid, 'creator_payout_id' => $payout->id])->save();
            $remaining -= $earning->net_cents;
        }
    }

    /**
     * @param  list<PayoutRequestStatus>  $from
     */
    private function transition(CreatorPayoutRequest $request, User $admin, array $from, PayoutRequestStatus $to, ?string $note = null): CreatorPayoutRequest
    {
        return DB::transaction(function () use ($request, $admin, $from, $to, $note): CreatorPayoutRequest {
            /** @var CreatorPayoutRequest $request */
            $request = CreatorPayoutRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! in_array($request->status, $from, true)) {
                throw new MarketplaceException("Talep {$request->status->value} durumundayken {$to->value} yapılamaz.");
            }

            $old = $request->status;
            $request->forceFill([
                'status' => $to,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note ?? $request->review_note,
            ])->save();

            $this->audit->record('payout.'.$to->value, $request, ['status' => $old->value], ['status' => $to->value], $admin, array_filter([
                'amount_cents' => $request->amount_cents,
                'note' => $note,
            ]));

            return $request;
        });
    }
}
