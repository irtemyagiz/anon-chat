<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutRequestStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\CreatorPayoutRequest;
use App\Models\CreatorVerification;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\DecimalRate;
use App\Services\Currency\ExchangeRateService;
use App\Services\Earnings\PayoutService;
use App\Services\Earnings\VerificationService;
use App\Services\Points\PointConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hakediş talepleri ve satıcı doğrulamaları. Tam hesap bilgileri yalnızca talep
 * detayında gösterilir ve her görüntüleme denetim kaydına yazılır.
 */
class PayoutController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('durum', PayoutRequestStatus::Pending->value);

        return view('admin.payouts.index', [
            'status' => $status,
            'requests' => CreatorPayoutRequest::query()
                ->with('creator.creatorProfile')
                ->when(in_array($status, array_column(PayoutRequestStatus::cases(), 'value'), true), fn ($q) => $q->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'totals' => CreatorPayoutRequest::query()->selectRaw('status, count(*) as count, sum(amount_cents) as cents')->groupBy('status')->get()->keyBy(fn ($row) => $row->status->value),
        ]);
    }

    public function show(Request $request, CreatorPayoutRequest $payoutRequest, ExchangeRateService $rates, AuditLogger $audit): View
    {
        $audit->record('payout.details_viewed', $payoutRequest, actor: $request->user());
        $rate = $payoutRequest->currency === 'TRY' ? $rates->current('TRY') : null;

        return view('admin.payouts.show', [
            'payoutRequest' => $payoutRequest->load('creator.creatorProfile', 'payout'),
            'verification' => $payoutRequest->creator->creatorProfile?->verifications()->latest('id')->first(),
            'rate' => $rate,
            'suggestedMinor' => $rate ? DecimalRate::multiply($payoutRequest->amount_cents, $rate->rate_micros) : $payoutRequest->amount_cents,
        ]);
    }

    public function decide(Request $request, CreatorPayoutRequest $payoutRequest, PayoutService $payouts): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'paid'])],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
            'paid_amount' => ['required_if:decision,paid', 'nullable', 'string', 'max:15'],
            'reference' => ['required_if:decision,paid', 'nullable', 'string', 'max:191'],
            'exchange_rate' => ['nullable', 'string', 'regex:/^\d{1,4}([.,]\d{1,6})?$/'],
        ]);

        try {
            match ($data['decision']) {
                'approve' => $payouts->approve($payoutRequest, $request->user()),
                'reject' => $payouts->reject($payoutRequest, $request->user(), $data['note']),
                'paid' => $payouts->markPaid(
                    $payoutRequest,
                    $request->user(),
                    $payoutRequest->currency,
                    /** "1234,56" veya "1234.56": ödeme para biriminin en küçük birimine çevrilir. */
                    PointConverter::parseUsdToCents($data['paid_amount']),
                    $data['reference'],
                    filled($data['exchange_rate'] ?? null) ? DecimalRate::toMicros($data['exchange_rate']) : null,
                ),
            };
        } catch (InvalidArgumentException) {
            return back()->withErrors(['paid_amount' => __('Ödenen tutarı 1234,56 biçiminde girin.')]);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        return back()->with('status', __('Hakediş talebi güncellendi.'));
    }

    public function verifications(): View
    {
        return view('admin.payouts.verifications', [
            'verifications' => CreatorVerification::query()->with('creatorProfile.user')->latest('id')->paginate(25),
        ]);
    }

    public function reviewVerification(Request $request, CreatorVerification $verification, VerificationService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([VerificationStatus::Approved->value, VerificationStatus::Rejected->value])],
            'note' => ['required_if:status,rejected', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $service->review($verification, VerificationStatus::from($data['status']), $request->user(), $data['note'] ?? null);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', __('Doğrulama güncellendi.'));
    }

    public function document(Request $request, CreatorVerification $verification, AuditLogger $audit): StreamedResponse
    {
        abort_if($verification->document_path === null, 404);
        $audit->record('creator.verification_document_viewed', $verification, actor: $request->user());

        return Storage::disk('private')->download($verification->document_path, 'belge-'.$verification->id.'.'.pathinfo($verification->document_path, PATHINFO_EXTENSION));
    }
}
