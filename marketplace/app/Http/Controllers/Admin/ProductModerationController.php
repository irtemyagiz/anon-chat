<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Products\ProductManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductModerationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('durum', ProductStatus::PendingReview->value);

        return view('admin.products.index', [
            'status' => $status,
            'products' => Product::query()
                ->with(['creator.creatorProfile', 'category'])
                ->withCount(['files' => fn ($q) => $q->where('is_active', true)])
                ->when(in_array($status, array_column(ProductStatus::cases(), 'value'), true), fn ($q) => $q->where('status', $status))
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
                ->latest('updated_at')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function show(Product $product): View
    {
        return view('admin.products.show', [
            'product' => $product->load(['creator.creatorProfile', 'category', 'images', 'tags', 'licenses.license', 'files']),
        ]);
    }

    public function decide(Request $request, Product $product, ProductManager $products): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,unpublish'],
            'note' => ['required_unless:decision,approve', 'nullable', 'string', 'max:255'],
        ]);

        try {
            match ($data['decision']) {
                'approve' => $products->approve($product, $request->user()),
                'reject' => $products->reject($product, $request->user(), $data['note']),
                'unpublish' => $this->takeDown($product, $products, $request, $data['note']),
            };
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        return back()->with('status', __('Ürün durumu güncellendi.'));
    }

    public function feature(Request $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        $product->forceFill(['is_featured' => ! $product->is_featured])->save();
        $audit->record('product.featured_toggled', $product, null, ['is_featured' => $product->is_featured], $request->user());

        return back()->with('status', $product->is_featured ? __('Ürün öne çıkarıldı.') : __('Ürün öne çıkanlardan kaldırıldı.'));
    }

    private function takeDown(Product $product, ProductManager $products, Request $request, string $note): void
    {
        $products->unpublish($product);
        $product->forceFill(['moderation_note' => $note])->save();
        app(AuditLogger::class)->record('product.taken_down', $product, ['status' => 'published'], ['status' => 'unpublished'], $request->user(), ['note' => $note]);
    }
}
