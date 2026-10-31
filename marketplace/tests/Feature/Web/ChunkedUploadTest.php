<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class ChunkedUploadTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    private User $creator;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('private');
        Storage::fake('public');
        config(['marketplace.storage.chunk_size_mb' => 1 / 1024]);

        $this->creator = User::factory()->creator()->create();
        $this->product = Product::factory()->for($this->creator, 'creator')->create();
    }

    /**
     * 100 üçgenli geçerli binary STL (5084 bayt).
     */
    private function stlBytes(int $triangles = 100): string
    {
        $body = '';

        for ($i = 0; $i < $triangles; $i++) {
            $body .= pack('g12v', ...[0, 0, 1, $i, 0, 0, $i + 1, 0, 0, $i, 1, 0, 0]);
        }

        return str_pad('test', 80).pack('V', $triangles).$body;
    }

    private function sendChunk(string $url, int $offset, string $bytes): TestResponse
    {
        return $this->call('POST', $url.'?offset='.$offset, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $bytes);
    }

    public function test_large_file_is_uploaded_in_chunks_resumed_and_inspected(): void
    {
        $bytes = $this->stlBytes();
        $this->actingAs($this->creator);

        $session = $this->postJson(route('creator.uploads.store', $this->product), ['name' => 'buyuk-model.stl', 'size' => strlen($bytes)])
            ->assertCreated()->assertJsonPath('received_bytes', 0)->assertJsonPath('chunk_size', 1024)->json();

        $this->sendChunk($session['chunk_url'], 0, substr($bytes, 0, 1024))->assertOk()->assertJson(['received_bytes' => 1024]);
        $this->sendChunk($session['chunk_url'], 0, substr($bytes, 0, 1024))->assertStatus(409)->assertJson(['received_bytes' => 1024]);
        $this->postJson($session['complete_url'])->assertStatus(409);

        /** Bağlantı koptu: istemci durumu sorar ve kaldığı yerden devam eder. */
        $offset = $this->getJson($session['status_url'])->assertOk()->json('received_bytes');
        while ($offset < strlen($bytes)) {
            $offset = $this->sendChunk($session['chunk_url'], $offset, substr($bytes, $offset, 1024))->assertOk()->json('received_bytes');
        }

        $this->postJson($session['complete_url'])->assertOk()->assertJson(['complete' => true, 'file' => ['name' => 'buyuk-model.stl', 'size' => strlen($bytes)]]);

        $file = $this->product->files()->sole();
        Storage::disk('private')->assertExists($file->path);
        $this->assertSame(hash('sha256', $bytes), $file->sha256);
        $this->assertSame(0, UploadSession::query()->count());
        $this->assertSame([], Storage::disk('local')->files('chunks'), 'Parça dosyası silinir');
    }

    public function test_session_rules_and_ownership(): void
    {
        $this->actingAs($this->creator);

        $this->postJson(route('creator.uploads.store', $this->product), ['name' => 'betik.php', 'size' => 10])->assertStatus(422);
        $this->postJson(route('creator.uploads.store', $this->product), ['name' => 'dev.stl', 'size' => 10 * 1024 * 1024 * 1024])->assertStatus(422);

        $session = $this->postJson(route('creator.uploads.store', $this->product), ['name' => 'model.stl', 'size' => 2000])->assertCreated()->json();
        $this->sendChunk($session['chunk_url'], 0, str_repeat('a', 1025))->assertStatus(422);
        $this->sendChunk($session['chunk_url'], 0, str_repeat('a', 1000))->assertOk();
        $this->sendChunk($session['chunk_url'], 1000, str_repeat('a', 1001))->assertStatus(422, 'Bildirilen boyut aşılamaz');

        $intruder = User::factory()->creator()->create();
        $this->actingAs($intruder)->getJson($session['status_url'])->assertNotFound();
        $this->sendChunk($session['chunk_url'], 1000, str_repeat('a', 1000))->assertNotFound();
        $this->actingAs($intruder)->postJson(route('creator.uploads.store', $this->product), ['name' => 'model.stl', 'size' => 10])->assertForbidden();
    }

    public function test_spoofed_content_is_rejected_on_completion(): void
    {
        $bytes = '<?php system($_GET["c"]); ?>';
        $this->actingAs($this->creator);

        $session = $this->postJson(route('creator.uploads.store', $this->product), ['name' => 'model.stl', 'size' => strlen($bytes)])->json();
        $this->sendChunk($session['chunk_url'], 0, $bytes)->assertOk();

        $this->postJson($session['complete_url'])->assertStatus(422);
        $this->assertSame(0, $this->product->files()->count());
        $this->assertSame(0, UploadSession::query()->count(), 'Reddedilen oturum ve parça silinir');
    }

    public function test_expired_sessions_are_pruned(): void
    {
        $this->actingAs($this->creator);
        $session = $this->postJson(route('creator.uploads.store', $this->product), ['name' => 'model.stl', 'size' => 2000])->json();
        $model = UploadSession::query()->sole();

        $this->travel(25)->hours();
        $this->getJson($session['status_url'])->assertNotFound();
        $this->artisan('marketplace:prune-uploads')->assertSuccessful();

        $this->assertModelMissing($model);
        $this->assertFileDoesNotExist($model->partPath());
    }
}
