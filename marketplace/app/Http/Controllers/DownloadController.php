<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\ProductFile;
use App\Models\PurchaseEntitlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Güvenli dosya indirme.
 *
 * Ürün sayfası yalnızca hak sahibine kısa süreli imzalı bağlantı üretir. Bağlantı
 * açıldığında oturum, imza, hak ve dosya durumu tekrar kontrol edilir; dosya özel
 * diskten akıtılır, herkese açık bir adresi yoktur.
 */
class DownloadController extends Controller
{
    public static function temporaryUrl(ProductFile $file): string
    {
        return URL::temporarySignedRoute('downloads.file', now()->addMinutes((int) config('marketplace.storage.download_link_ttl_minutes')), $file);
    }

    public function __invoke(Request $request, ProductFile $file): StreamedResponse
    {
        $user = $request->user();
        $product = $file->product;
        $isCreator = $product->creator_id === $user->id;

        $entitlement = PurchaseEntitlement::query()
            ->where(['user_id' => $user->id, 'product_id' => $product->id])
            ->whereNull('revoked_at')
            ->first();

        abort_unless($isCreator || ($entitlement !== null && $product->isPublished()), 403);
        abort_unless($file->isDownloadable(), 404);

        Download::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_file_id' => $file->id,
            'purchase_entitlement_id' => $entitlement?->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        if (! $isCreator) {
            $product->increment('downloads_count');
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
