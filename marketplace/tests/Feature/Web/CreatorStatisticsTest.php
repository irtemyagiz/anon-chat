<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Services\Analytics\CreatorStatistics;
use App\Services\Sales\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMarketplace;
use Tests\TestCase;

class CreatorStatisticsTest extends TestCase
{
    use InteractsWithMarketplace, RefreshDatabase;

    public function test_statistics_reflect_real_sales_downloads_and_followers_per_period(): void
    {
        $creator = User::factory()->creator()->create();
        $product = $this->publishedProduct(1000, $creator);
        $product->forceFill(['views_count' => 50])->save();
        $buyer = User::factory()->create();
        $this->givePoints($buyer, 1000);
        app(PurchaseService::class)->purchase($buyer, $product, null, 'k');

        $file = $product->files()->create(['disk' => 'private', 'path' => 'x.stl', 'original_name' => 'x.stl', 'extension' => 'stl', 'mime_type' => 'model/stl', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64)]);
        DB::table('downloads')->insert(['user_id' => $buyer->id, 'product_id' => $product->id, 'product_file_id' => $file->id, 'created_at' => now()]);
        DB::table('downloads')->insert(['user_id' => $buyer->id, 'product_id' => $product->id, 'product_file_id' => $file->id, 'created_at' => now()->subDays(60)]);
        DB::table('follows')->insert(['follower_id' => $buyer->id, 'creator_id' => $creator->id, 'created_at' => now()]);

        $statistics = app(CreatorStatistics::class);

        $daily = $statistics->series($creator->id, 'gunluk');
        $this->assertCount(30, $daily);
        $this->assertSame(1, end($daily)['sales']);
        $this->assertSame(800, end($daily)['revenue_cents'], 'Varsayılan %20 komisyon sonrası creator payı');
        $this->assertSame(1, array_sum(array_column($daily, 'downloads')), '60 gün önceki indirme 30 günlük dönemde yok');
        $this->assertSame(1, end($daily)['followers']);

        $monthly = $statistics->series($creator->id, 'aylik');
        $this->assertCount(12, $monthly);
        $this->assertSame(2, array_sum(array_column($monthly, 'downloads')));
        $this->assertCount(12, $statistics->series($creator->id, 'haftalik'));

        $row = $statistics->products($creator->id)->sole();
        $this->assertSame(800, $row->revenue_cents);
        $this->assertSame(200, $row->conversion_bps, '1 satış / 50 görüntülenme = %2');

        $this->actingAs($creator)->get(route('creator.statistics'))->assertOk()->assertSee('$8.00')->assertSee('%2,0');
        $this->get(route('creator.statistics', ['donem' => 'aylik']))->assertOk()->assertSee('Son 12 ay');
        $this->get(route('creator.statistics', ['donem' => 'gecersiz']))->assertOk();

        $other = User::factory()->creator()->create();
        $this->actingAs($other)->get(route('creator.statistics'))->assertOk()->assertDontSee($product->title)->assertSee('Bu dönemde henüz hareket yok');
    }
}
