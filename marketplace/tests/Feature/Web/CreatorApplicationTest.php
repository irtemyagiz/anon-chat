<?php

namespace Tests\Feature\Web;

use App\Enums\CreatorStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorApplicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function storeData(): array
    {
        return ['designer_name' => 'Ada Tasarım', 'store_name' => 'Ada Atölye', 'description' => 'Organizer ve dekor modelleri', 'terms' => '1'];
    }

    public function test_page_has_dedicated_creator_headline(): void
    {
        $this->get('/creator-ol')->assertOk()->assertSee('Tasarımlarını Paylaş, Kazanmaya Başla');
    }

    public function test_guest_can_register_directly_as_creator(): void
    {
        $this->post('/creator-ol', [
            ...$this->storeData(),
            'first_name' => 'Ada', 'last_name' => 'Kaya', 'username' => 'adakaya', 'email' => 'ada@example.com',
            'country_code' => 'TR', 'password' => 'Sifre1234', 'password_confirmation' => 'Sifre1234',
        ])->assertRedirect(route('creator.status'));

        $user = User::query()->where('email', 'ada@example.com')->sole();
        $this->assertTrue($user->hasRole(Role::Creator));
        $this->assertTrue($user->hasRole(Role::Customer), 'Creator aynı zamanda alışveriş yapabilir');
        $this->assertSame(CreatorStatus::Pending, $user->creatorProfile->status);
        $this->assertSame('ada-atolye', $user->creatorProfile->store_slug);
        $this->assertFalse($user->isApprovedCreator());
        $this->assertDatabaseHas('audit_logs', ['event' => 'creator.applied', 'actor_id' => $user->id]);
    }

    public function test_existing_user_upgrades_to_creator_without_account_fields(): void
    {
        $user = User::factory()->withRole(Role::Customer)->create();

        $this->actingAs($user)->post('/creator-ol', $this->storeData())->assertRedirect(route('creator.status'));

        $this->assertNotNull($user->fresh()->creatorProfile);
        $this->get(route('creator.status'))->assertOk()->assertSee('Başvurun inceleniyor');
    }

    public function test_user_cannot_apply_twice_or_self_approve(): void
    {
        $user = User::factory()->creator(CreatorStatus::Pending)->create();

        $this->actingAs($user)->post('/creator-ol', [...$this->storeData(), 'status' => 'approved'])->assertForbidden();
        $this->assertSame(CreatorStatus::Pending, $user->creatorProfile->fresh()->status);
    }

    public function test_upload_link_routes_by_account_state(): void
    {
        $this->get('/tasarim-yukle')->assertRedirect(route('creator.apply'));
        $this->actingAs(User::factory()->creator(CreatorStatus::Pending)->create())->get('/tasarim-yukle')->assertRedirect(route('creator.status'));
        $this->actingAs(User::factory()->creator()->create())->get('/tasarim-yukle')->assertRedirect(route('creator.products.create'));
    }
}
