<?php

namespace App\Http\Controllers\Creator;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductImage;
use App\Services\Products\ProductManager;
use Illuminate\Http\RedirectResponse;

class ProductActionController extends Controller
{
    public function submit(Product $product, ProductManager $products): RedirectResponse
    {
        $this->authorize('update', $product);

        try {
            $missing = $products->submit($product);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['submit' => $exception->getMessage()]);
        }

        if ($missing !== []) {
            return back()->withErrors(['submit' => implode(' ', $missing)]);
        }

        return back()->with('status', $product->isPublished()
            ? __('Ürün yayınlandı.')
            : __('Ürün incelemeye gönderildi. Onaylandığında yayına alınacak.'));
    }

    public function unpublish(Product $product, ProductManager $products): RedirectResponse
    {
        $this->authorize('update', $product);

        try {
            $products->unpublish($product);
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['submit' => $exception->getMessage()]);
        }

        return back()->with('status', __('Ürün yayından kaldırıldı. Daha önce satın alanlar dosyalara erişmeye devam eder.'));
    }

    public function destroyFile(Product $product, ProductFile $file, ProductManager $products): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($file->product_id === $product->id, 404);

        $products->removeFile($file);

        return back()->with('status', __('Dosya kaldırıldı.'));
    }

    public function destroyImage(Product $product, ProductImage $image, ProductManager $products): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($image->product_id === $product->id, 404);

        $products->removeImage($image);

        return back()->with('status', __('Görsel kaldırıldı.'));
    }

    public function destroyPreview(Product $product, ProductManager $products): RedirectResponse
    {
        $this->authorize('update', $product);
        $products->removePreview($product);

        return back()->with('status', __('3D önizleme kaldırıldı.'));
    }
}
