<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CreatorStatus;
use App\Http\Controllers\Controller;
use App\Models\CreatorProfile;
use App\Services\Accounts\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreatorController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('durum', CreatorStatus::Pending->value);

        return view('admin.creators', [
            'status' => $status,
            'profiles' => CreatorProfile::query()
                ->with('user')
                ->withCount('products')
                ->when(in_array($status, array_column(CreatorStatus::cases(), 'value'), true), fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function update(Request $request, CreatorProfile $profile, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(CreatorStatus::class)->except([CreatorStatus::Pending])],
            'reason' => [Rule::requiredIf(in_array($request->input('status'), ['rejected', 'suspended'], true)), 'nullable', 'string', 'max:255'],
        ]);

        $accounts->reviewCreator($profile, CreatorStatus::from($data['status']), $request->user(), $data['reason'] ?? null);

        return back()->with('status', __(':store güncellendi.', ['store' => $profile->store_name]));
    }
}
