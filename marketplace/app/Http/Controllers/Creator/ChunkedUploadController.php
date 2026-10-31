<?php

namespace App\Http\Controllers\Creator;

use App\Exceptions\InvalidUploadException;
use App\Http\Controllers\Controller;
use App\Models\FileFormat;
use App\Models\Product;
use App\Models\UploadSession;
use App\Services\Products\ProductManager;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Büyük ürün dosyaları için parçalı ve kaldığı yerden devam ettirilebilir yükleme.
 *
 * Akış: oturum aç → parçaları sırayla gönder (offset = sunucunun aldığı bayt) →
 * tamamla. Tamamlanan dosya, normal yüklemeyle aynı güvenlik kontrollerinden
 * (uzantı, içerik, arşiv, zararlı yazılım taraması) geçer.
 */
class ChunkedUploadController extends Controller
{
    private const MAX_OPEN_SESSIONS = 10;

    public function store(Request $request, Product $product, SettingsService $settings): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $extension = Str::lower(pathinfo($data['name'], PATHINFO_EXTENSION));
        $maxBytes = $settings->integer('storage.max_file_size_mb') * 1024 * 1024;

        if (! FileFormat::query()->where('extension', $extension)->where('is_active', true)->exists()) {
            return $this->error(__(':ext uzantılı dosyalar kabul edilmiyor.', ['ext' => strtoupper($extension ?: '?')]));
        }

        if ($data['size'] > $maxBytes) {
            return $this->error(__('Dosya boyutu en fazla :mb MB olabilir.', ['mb' => intdiv($maxBytes, 1024 * 1024)]));
        }

        if ($product->files()->where('is_active', true)->count() >= (int) config('marketplace.storage.max_files_per_product')) {
            return $this->error(__('Bir ürüne en fazla :count dosya eklenebilir.', ['count' => config('marketplace.storage.max_files_per_product')]));
        }

        if (UploadSession::query()->where('user_id', $request->user()->id)->where('expires_at', '>', now())->count() >= self::MAX_OPEN_SESSIONS) {
            return $this->error(__('Aynı anda en fazla :count yükleme yapılabilir. Devam eden yüklemeleri tamamlayın.', ['count' => self::MAX_OPEN_SESSIONS]));
        }

        $session = UploadSession::query()->forceCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'original_name' => Str::limit(basename(str_replace('\\', '/', $data['name'])), 250, ''),
            'extension' => $extension,
            'size_bytes' => $data['size'],
            'expires_at' => now()->addHours((int) config('marketplace.storage.upload_session_hours')),
        ]);

        if (! is_dir(dirname($session->partPath()))) {
            mkdir(dirname($session->partPath()), 0750, true);
        }

        touch($session->partPath());

        return response()->json($this->state($session), 201);
    }

    public function show(Request $request, UploadSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return response()->json($this->state($session));
    }

    /**
     * Parça ekler. offset, sunucunun şu ana kadar aldığı bayt sayısına eşit olmalıdır;
     * değilse 409 ile doğru konum döner ve istemci oradan devam eder.
     */
    public function chunk(Request $request, UploadSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        $offset = filter_var($request->query('offset'), FILTER_VALIDATE_INT);
        $chunkBytes = $this->chunkBytes();
        $handle = fopen($session->partPath(), 'c+b');

        if ($handle === false) {
            return $this->error(__('Yükleme oturumu bulunamadı. Lütfen dosyayı yeniden seçin.'), 410);
        }

        try {
            /** Aynı oturuma eşzamanlı parça gönderimi sıraya alınır. */
            flock($handle, LOCK_EX);
            $session->refresh();

            if ($offset !== $session->received_bytes) {
                return response()->json([...$this->state($session), 'message' => __('Yükleme kaldığı yerden devam ediyor.')], 409);
            }

            ftruncate($handle, $session->received_bytes);
            fseek($handle, $session->received_bytes);
            $written = stream_copy_to_stream($request->getContent(true), $handle, $chunkBytes + 1);

            if ($written === false || $written === 0 || $written > $chunkBytes || $session->received_bytes + $written > $session->size_bytes) {
                ftruncate($handle, $session->received_bytes);

                return $this->error(__('Geçersiz dosya parçası.'));
            }

            fflush($handle);
            $session->forceFill(['received_bytes' => $session->received_bytes + $written, 'expires_at' => now()->addHours((int) config('marketplace.storage.upload_session_hours'))])->save();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return response()->json($this->state($session));
    }

    public function complete(Request $request, UploadSession $session, ProductManager $products): JsonResponse
    {
        $this->authorizeSession($request, $session);

        if (! $session->isComplete() || filesize($session->partPath()) !== $session->size_bytes) {
            return response()->json([...$this->state($session), 'message' => __('Dosyanın tamamı henüz yüklenmedi.')], 409);
        }

        $product = $session->product;
        $this->authorize('update', $product);

        try {
            $products->addFiles($product, [new UploadedFile($session->partPath(), $session->original_name, null, UPLOAD_ERR_OK, true)]);
        } catch (InvalidUploadException $exception) {
            $session->discard();

            return $this->error($exception->getMessage());
        }

        $session->discard();
        $file = $product->files()->latest('id')->first();

        return response()->json([
            'complete' => true,
            'file' => ['name' => $file->original_name, 'size' => $file->size_bytes],
            'message' => __('Dosya eklendi. Güvenlik taraması tamamlanınca indirilebilir olur.'),
        ]);
    }

    public function destroy(Request $request, UploadSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        $session->discard();

        return response()->json(['deleted' => true]);
    }

    private function authorizeSession(Request $request, UploadSession $session): void
    {
        abort_unless($session->user_id === $request->user()->id && $session->expires_at->isFuture(), 404);
    }

    private function chunkBytes(): int
    {
        return max(1024, (int) round((float) config('marketplace.storage.chunk_size_mb') * 1024 * 1024));
    }

    /**
     * @return array{id: string, received_bytes: int, size_bytes: int, chunk_size: int, chunk_url: string, status_url: string, complete_url: string}
     */
    private function state(UploadSession $session): array
    {
        return [
            'id' => $session->id,
            'received_bytes' => $session->received_bytes,
            'size_bytes' => $session->size_bytes,
            'chunk_size' => $this->chunkBytes(),
            'chunk_url' => route('creator.uploads.chunk', $session),
            'status_url' => route('creator.uploads.show', $session),
            'complete_url' => route('creator.uploads.complete', $session),
        ];
    }

    private function error(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
