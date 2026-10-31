<?php

namespace App\Http\Controllers\Creator;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\CreatorPayoutRequest;
use App\Rules\Iban;
use App\Rules\TurkishIdentityNumber;
use App\Services\Earnings\CreatorEarningService;
use App\Services\Earnings\PayoutService;
use App\Services\Earnings\VerificationService;
use App\Services\Points\PointConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Creator hakedişleri: satıcı doğrulaması ve ödeme talepleri.
 */
class PayoutController extends Controller
{
    public function index(Request $request, CreatorEarningService $earnings, VerificationService $verifications): View
    {
        $user = $request->user();

        return view('creator.payouts', [
            'balance' => $earnings->lockBalance($user->id),
            'verification' => $verifications->latest($user->creatorProfile),
            'methods' => self::methodsFor($user->country_code),
            'minimumCents' => (int) config('marketplace.earnings.minimum_payout_cents'),
            'requests' => CreatorPayoutRequest::query()->where('creator_id', $user->id)->with('payout')->latest('id')->paginate(15),
        ]);
    }

    public function verify(Request $request, VerificationService $verifications): RedirectResponse
    {
        $isTurkish = $request->input('country_code') === 'TR';
        $isIndividual = $request->input('entity_type') === 'individual';

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'min:3', 'max:160'],
            'entity_type' => ['required', Rule::in(['individual', 'sole_proprietor', 'company'])],
            'country_code' => ['required', Rule::in(array_keys(config('countries')))],
            'identity_number' => $isTurkish && $isIndividual ? ['required', new TurkishIdentityNumber] : ['nullable', 'string', 'max:40'],
            'tax_number' => $isTurkish && ! $isIndividual ? ['required', 'digits_between:10,11'] : ['nullable', 'string', 'max:40'],
            'tax_office' => $isTurkish && ! $isIndividual ? ['required', 'string', 'max:120'] : ['nullable', 'string', 'max:120'],
            'address' => ['required', 'string', 'min:10', 'max:500'],
            'document' => ['nullable', 'file', 'max:10240'],
            'consent' => ['accepted'],
        ]);

        try {
            $verifications->submit($request->user()->creatorProfile, collect($data)->except('document', 'consent')->all(), $request->file('document'));
        } catch (MarketplaceException $exception) {
            return back()->withInput()->withErrors(['document' => $exception->getMessage()]);
        }

        return back()->with('status', __('Doğrulama bilgilerin alındı. İnceleme sonrası hakediş talebi oluşturabilirsin.'));
    }

    public function store(Request $request, PayoutService $payouts): RedirectResponse
    {
        $user = $request->user();
        $methods = self::methodsFor($user->country_code);
        $method = $methods[$request->input('method')] ?? null;

        $rules = [
            'method' => ['required', Rule::in(array_keys($methods))],
            'amount' => ['required', 'string', 'max:12'],
            'details.account_holder' => ['required', 'string', 'max:120'],
        ];

        foreach ($method['fields'] ?? [] as $field) {
            $rules["details.{$field}"] = match ($field) {
                'iban' => ['required', new Iban($method['currency'] === 'TRY' ? 'TR' : null)],
                'swift' => ['required', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
                'email' => ['required', 'email'],
                'bank_name' => ['required', 'string', 'max:120'],
                default => ['required', 'string', 'max:120'],
            };
        }

        $data = $request->validate($rules, attributes: [
            'details.account_holder' => __('hesap sahibi'), 'details.iban' => 'IBAN', 'details.swift' => 'SWIFT/BIC',
            'details.email' => __('e-posta'), 'details.bank_name' => __('banka adı'), 'amount' => __('tutar'),
        ]);

        try {
            $cents = PointConverter::parseUsdToCents($data['amount']);
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors(['amount' => __('Tutarı 50.00 biçiminde girin.')]);
        }

        $details = collect($data['details'])->only($method['fields'])->map(fn ($value, $key) => $key === 'iban' ? Iban::normalize($value) : trim($value))->all();

        try {
            $payouts->request($user, $cents, $method['currency'], $data['method'], $details);
        } catch (MarketplaceException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('status', __('Hakediş talebin oluşturuldu.'));
    }

    /**
     * @return array<string, array{label: string, currency: string, fields: list<string>}>
     */
    public static function methodsFor(?string $countryCode): array
    {
        $methods = config('marketplace.payouts.methods');

        return $methods[$countryCode ?? ''] ?? $methods['default'];
    }
}
