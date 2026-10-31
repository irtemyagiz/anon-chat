<?php

namespace App\Http\Controllers;

use App\Exceptions\MarketplaceException;
use App\Models\Comment;
use App\Models\Product;
use App\Models\ProductReport;
use App\Models\ProductReview;
use App\Services\Community\ReviewService;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function storeReview(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->isPublished(), 404);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->reviews->review($request->user(), $product, (int) $data['rating'], $data['title'] ?? null, $data['body'] ?? null);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()])->withFragment('degerlendirmeler');
        }

        return back()->with('status', __('Değerlendirmen kaydedildi.'))->withFragment('degerlendirmeler');
    }

    public function reply(Request $request, ProductReview $review): RedirectResponse
    {
        $data = $request->validate(['reply' => ['required', 'string', 'min:2', 'max:1000']]);

        try {
            $this->reviews->reply($review, $request->user(), $data['reply']);
        } catch (MarketplaceException $exception) {
            abort(403, $exception->getMessage());
        }

        return back()->with('status', __('Yanıtın yayınlandı.'));
    }

    public function storeComment(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->isPublished(), 404);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = isset($data['parent_id']) ? Comment::query()->where('status', Comment::Published)->find($data['parent_id']) : null;

        try {
            $this->reviews->comment($request->user(), $product, $data['body'], $parent);
        } catch (MarketplaceException $exception) {
            return back()->withInput()->withErrors(['comment' => $exception->getMessage()])->withFragment('yorumlar');
        }

        return back()->with('status', __('Yorumun yayınlandı.'))->withFragment('yorumlar');
    }

    public function destroyComment(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 403);
        $comment->delete();

        return back()->with('status', __('Yorum silindi.'))->withFragment('yorumlar');
    }

    public function reportComment(Request $request, Comment $comment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', Rule::in(['spam', 'abuse', 'other'])]]);

        if ($comment->user_id !== $request->user()->id) {
            $this->reviews->report($comment, $request->user(), $data['reason']);
        }

        return back()->with('status', __('Şikâyetin alındı. Teşekkürler.'))->withFragment('yorumlar');
    }

    /**
     * İçerik şikâyeti (telif, uygunsuz içerik...). Telif sahipleri hesap açmadan e-posta ile bildirebilir.
     */
    public function reportProduct(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ProductReport::REASONS))],
            'details' => ['required', 'string', 'min:20', 'max:3000'],
            'reporter_email' => [Rule::requiredIf($request->user() === null), 'nullable', 'email'],
        ]);

        $report = new ProductReport($data);
        $report->product_id = $product->id;
        $report->reporter_id = $request->user()?->id;
        $report->reporter_email ??= $request->user()?->email;
        $report->save();

        app(Notifier::class)->staff('products.moderate', __('Yeni içerik şikâyeti'), __(':title için “:reason” bildirimi yapıldı.', ['title' => $product->title, 'reason' => __(ProductReport::REASONS[$report->reason])]), route('admin.moderation.reports'));

        return back()->with('status', __('Bildiriminiz alındı. Ekibimiz inceleyecek.'));
    }
}
