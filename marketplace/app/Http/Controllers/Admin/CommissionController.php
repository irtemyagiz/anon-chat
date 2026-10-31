<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionScope;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(SettingsService $settings): View
    {
        return view('admin.commissions', [
            'defaultRate' => $settings->integer('commission.default_rate_bps'),
            'rules' => CommissionRule::query()->latest('id')->paginate(25),
            'categories' => Category::query()->whereNull('parent_id')->orderBy('sort_order')->get(),
            'creators' => User::query()->whereIn('id', CommissionRule::query()->whereNotNull('creator_id')->pluck('creator_id'))->with('creatorProfile')->get()->keyBy('id'),
            'categoryNames' => Category::query()->get()->mapWithKeys(fn (Category $category) => [$category->id => $category->localizedName()]),
        ]);
    }

    public function updateDefault(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate(['percent' => ['required', 'numeric', 'min:0', 'max:90']]);
        $settings->set('commission.default_rate_bps', (int) round($data['percent'] * 100), $request->user(), 'commission');

        return back()->with('status', __('Varsayılan komisyon güncellendi. Yeni oran yalnızca bundan sonraki satışlara uygulanır.'));
    }

    public function storeRule(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in([CommissionScope::Creator->value, CommissionScope::Category->value, CommissionScope::Campaign->value])],
            'name' => ['required', 'string', 'max:120'],
            'percent' => ['required', 'numeric', 'min:0', 'max:90'],
            'creator' => ['required_if:scope,creator', 'nullable', 'string', 'max:190'],
            'category_id' => ['required_if:scope,category', 'nullable', Rule::exists('categories', 'id')],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['required_if:scope,campaign', 'nullable', 'date', 'after:starts_at', 'after:now'],
        ]);

        $creator = null;

        if (filled($data['creator'] ?? null)) {
            $creator = User::query()->where('email', $data['creator'])->orWhere('username', $data['creator'])->first();

            if ($creator === null || $creator->creatorProfile === null) {
                return back()->withInput()->withErrors(['creator' => __('Bu e-posta veya kullanıcı adıyla creator bulunamadı.')]);
            }
        }

        $rule = CommissionRule::query()->create([
            'scope' => $data['scope'],
            'name' => $data['name'],
            'rate_bps' => (int) round($data['percent'] * 100),
            'creator_id' => $data['scope'] === 'creator' ? $creator?->id : null,
            'category_id' => $data['scope'] === 'category' ? $data['category_id'] : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => true,
        ]);
        $rule->forceFill(['created_by' => $request->user()->id])->save();

        $audit->record('commission_rule.created', $rule, null, $rule->only('scope', 'rate_bps', 'creator_id', 'category_id', 'starts_at', 'ends_at'), $request->user());

        return back()->with('status', __('Komisyon kuralı eklendi.'));
    }

    public function toggleRule(Request $request, CommissionRule $rule, AuditLogger $audit): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        $audit->record('commission_rule.toggled', $rule, null, ['is_active' => $rule->is_active], $request->user());

        return back()->with('status', __('Kural güncellendi.'));
    }
}
