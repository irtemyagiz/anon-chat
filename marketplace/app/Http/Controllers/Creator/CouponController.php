<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Points\PointConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Creator'ın kendi ürünleri için indirim kuponları. İndirim creator'ın payından düşer
 * (komisyon indirimli tutar üzerinden hesaplanır).
 */
class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $creatorId = $request->user()->id;

        return view('creator.coupons', [
            'coupons' => Coupon::query()->where('creator_id', $creatorId)->with('product:id,title,slug')->latest('id')->paginate(20),
            'products' => Product::query()->where('creator_id', $creatorId)->where('is_free', false)->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $creatorId = $request->user()->id;
        $request->merge(['code' => Coupon::normalizeCode((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->where('creator_id', $creatorId)],
            'discount_type' => ['required', Rule::in([Coupon::Percent, Coupon::Fixed])],
            'percent' => ['required_if:discount_type,'.Coupon::Percent, 'nullable', 'integer', 'min:1', 'max:100'],
            'amount' => ['required_if:discount_type,'.Coupon::Fixed, 'nullable', 'string', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('creator_id', $creatorId)->whereNull('deleted_at')],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => array_filter(['nullable', 'date', 'after:now', $request->filled('starts_at') ? 'after:starts_at' : null]),
        ], [
            'code.regex' => __('Kupon kodu yalnızca harf, rakam, tire ve alt çizgi içerebilir.'),
        ]);

        $value = $data['discount_type'] === Coupon::Percent
            ? (int) $data['percent'] * 100
            : PointConverter::parseUsdToCents($data['amount']);

        if ($value < 1) {
            throw ValidationException::withMessages(['amount' => __('İndirim tutarı en az $0.01 olmalıdır.')]);
        }

        $coupon = Coupon::query()->forceCreate([
            'creator_id' => $creatorId,
            'product_id' => $data['product_id'] ?? null,
            'code' => $data['code'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $value,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => true,
        ]);

        $audit->record('coupon.created', $coupon, null, $coupon->only('code', 'discount_type', 'discount_value', 'product_id'), $request->user());

        return back()->with('status', __('Kupon oluşturuldu: :code', ['code' => $coupon->code]));
    }

    public function toggle(Request $request, Coupon $coupon): RedirectResponse
    {
        abort_unless($coupon->creator_id === $request->user()->id, 403);

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('status', $coupon->is_active ? __('Kupon etkinleştirildi.') : __('Kupon durduruldu.'));
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        abort_unless($coupon->creator_id === $request->user()->id, 403);

        if ($coupon->redemptions_count > 0) {
            return back()->withErrors(['coupon' => __('Kullanılmış kupon silinemez; satış kayıtlarında yer aldığı için yalnızca durdurulabilir.')]);
        }

        $coupon->delete();

        return back()->with('status', __('Kupon silindi.'));
    }
}
