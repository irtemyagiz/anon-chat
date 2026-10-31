<?php

namespace App\Http\Controllers;

use App\Enums\CreatorStatus;
use App\Models\CreatorProfile;
use App\Services\Catalog\ProductSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DesignerController extends Controller
{
    public function index(): View
    {
        return view('designers.index', [
            'designers' => CreatorProfile::query()
                ->where('status', CreatorStatus::Approved)
                ->with('user')
                ->withCount(['products' => fn ($q) => $q->published()])
                ->orderByDesc('products_count')
                ->paginate(24),
        ]);
    }

    public function show(CreatorProfile $profile, ProductSearch $search): View
    {
        abort_unless($profile->status === CreatorStatus::Approved, 404);

        return view('designers.show', [
            'profile' => $profile->load('user'),
            'products' => $search->query([])->where('creator_id', $profile->user_id)->paginate(24),
            'followersCount' => DB::table('follows')->where('creator_id', $profile->user_id)->count(),
            'isFollowing' => auth()->check() && DB::table('follows')->where(['follower_id' => auth()->id(), 'creator_id' => $profile->user_id])->exists(),
        ]);
    }
}
