<?php

namespace App\Http\Controllers\Creator;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Creator\ProductRequest;
use App\Models\Product;
use App\Services\Products\ProductManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        return view('creator.products.index', [
            'products' => Product::query()
                ->where('creator_id', $request->user()->id)
                ->withCount(['files' => fn ($q) => $q->where('is_active', true)])
                ->latest('updated_at')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('creator.products.form', ['product' => new Product, 'categories' => ProductRequest::categoryOptions()]);
    }

    public function store(ProductRequest $request, ProductManager $products): RedirectResponse
    {
        $product = $products->create($request->user(), $request->productData());

        return $this->storeUploads($request, $products, $product, __('Ürün taslak olarak kaydedildi.'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('creator.products.form', [
            'product' => $product->load(['images', 'tags', 'licenses.license', 'files' => fn ($q) => $q->where('is_active', true)]),
            'categories' => ProductRequest::categoryOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductManager $products): RedirectResponse
    {
        $this->authorize('update', $product);
        $products->update($product, $request->productData());

        return $this->storeUploads($request, $products, $product, __('Değişiklikler kaydedildi.'));
    }

    /**
     * Yüklemelerden biri reddedilirse ürün bilgileri yine de kaydedilmiş olur;
     * kullanıcı düzenleme sayfasında hangi dosyanın neden reddedildiğini görür.
     */
    private function storeUploads(ProductRequest $request, ProductManager $products, Product $product, string $message): RedirectResponse
    {
        try {
            if ($request->hasFile('images')) {
                $products->addImages($product, $request->file('images'));
            }

            if ($request->hasFile('files')) {
                $products->addFiles($product, $request->file('files'));
            }

            if ($request->hasFile('preview')) {
                $products->setPreview($product, $request->file('preview'));
            }
        } catch (MarketplaceException $exception) {
            return redirect()->route('creator.products.edit', $product)->withErrors(['uploads' => $exception->getMessage()]);
        }

        return redirect()->route('creator.products.edit', $product)->with('status', $message);
    }
}
