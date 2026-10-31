<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ProductReport;
use App\Models\ProductReview;
use App\Services\Audit\AuditLogger;
use App\Services\Community\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Yorum, değerlendirme ve içerik şikâyeti moderasyonu.
 */
class ModerationController extends Controller
{
    public function comments(Request $request): View
    {
        $tab = $request->query('tur', 'sikayetli');

        return view('admin.moderation.comments', [
            'tab' => $tab,
            'comments' => match ($tab) {
                'degerlendirmeler' => ProductReview::query()->with(['user:id,username', 'product:id,title,slug'])->latest('id')->paginate(30)->withQueryString(),
                'gizli' => Comment::query()->where('status', Comment::Hidden)->with(['user:id,username', 'product:id,title,slug'])->latest('id')->paginate(30)->withQueryString(),
                'tumu' => Comment::query()->with(['user:id,username', 'product:id,title,slug'])->latest('id')->paginate(30)->withQueryString(),
                default => Comment::query()->where('reports_count', '>', 0)->with(['user:id,username', 'product:id,title,slug'])->orderByDesc('reports_count')->paginate(30)->withQueryString(),
            },
        ]);
    }

    public function updateComment(Request $request, Comment $comment, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['hide', 'publish', 'delete'])]]);

        match ($data['action']) {
            'hide' => $comment->forceFill(['status' => Comment::Hidden])->save(),
            'publish' => $comment->forceFill(['status' => Comment::Published, 'reports_count' => 0])->save(),
            'delete' => $comment->delete(),
        };

        $audit->record('comment.'.$data['action'], $comment, actor: $request->user(), context: ['excerpt' => mb_substr($comment->body, 0, 120)]);

        return back()->with('status', __('Yorum güncellendi.'));
    }

    public function updateReview(Request $request, ProductReview $review, ReviewService $reviews, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'hidden'])]]);

        $review->forceFill(['status' => $data['status']])->save();
        $reviews->refreshRating($review->product);
        $audit->record('review.'.$data['status'], $review, actor: $request->user());

        return back()->with('status', __('Değerlendirme güncellendi.'));
    }

    public function reports(Request $request): View
    {
        $status = $request->query('durum', 'open');

        return view('admin.moderation.reports', [
            'status' => $status,
            'reports' => ProductReport::query()
                ->with(['product.creator.creatorProfile', 'reporter:id,username,email'])
                ->when(in_array($status, ['open', 'resolved', 'dismissed'], true), fn ($q) => $q->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function resolveReport(Request $request, ProductReport $report, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['resolved', 'dismissed'])],
            'resolution_note' => ['required', 'string', 'max:1000'],
        ]);

        $report->forceFill([...$data, 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();
        $audit->record('product_report.'.$data['status'], $report, actor: $request->user(), context: ['product' => $report->product_id]);

        return back()->with('status', __('Şikâyet kapatıldı.'));
    }
}
