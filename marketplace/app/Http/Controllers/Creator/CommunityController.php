<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function reviews(Request $request): View
    {
        $creatorId = $request->user()->id;

        return view('creator.reviews', [
            'reviews' => ProductReview::query()
                ->whereHas('product', fn ($q) => $q->where('creator_id', $creatorId))
                ->where('status', 'published')
                ->with(['user:id,username,first_name', 'product:id,title,slug'])
                ->latest('id')
                ->paginate(20, pageName: 'degerlendirme'),
            'comments' => Comment::query()
                ->whereHas('product', fn ($q) => $q->where('creator_id', $creatorId))
                ->where('status', Comment::Published)
                ->whereNull('parent_id')
                ->with(['user:id,username', 'product:id,title,slug'])
                ->latest('id')
                ->paginate(20, pageName: 'yorum'),
        ]);
    }

    public function followers(Request $request): View
    {
        return view('creator.followers', [
            'followers' => DB::table('follows')
                ->join('users', 'users.id', '=', 'follows.follower_id')
                ->where('follows.creator_id', $request->user()->id)
                ->select('users.username', 'users.first_name', 'follows.created_at')
                ->orderByDesc('follows.created_at')
                ->paginate(40),
        ]);
    }
}
