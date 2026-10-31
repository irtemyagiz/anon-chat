<?php

namespace Tests\Feature\Web;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function registrationData(array $overrides = []): array
    {
        return [
            'first_name' => 'Deniz',
            'last_name' => 'Yılmaz',
            'username' => 'deniz',
            'email' => 'Deniz@Example.com',
            'country_code' => 'TR',
            'password' => 'Sifre1234',
            'password_confirmation' => 'Sifre1234',
            'terms' => '1',
            ...$overrides,
        ];
    }

    public function test_registration_creates_customer_with_wallet_and_sends_verification(): void
    {
        Notification::fake();

        $this->post('/kayit', $this->registrationData())->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'deniz@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole(Role::Customer));
        $this->assertFalse($user->hasRole(Role::Admin));
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 0]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_terms_and_strong_password_and_unique_email(): void
    {
        User::factory()->create(['email' => 'deniz@example.com']);

        $this->post('/kayit', $this->registrationData(['terms' => null, 'password' => 'kisa', 'password_confirmation' => 'kisa']))
            ->assertSessionHasErrors(['terms', 'password', 'email']);
        $this->assertGuest();
    }

    public function test_registration_cannot_assign_roles_or_balance(): void
    {
        $this->post('/kayit', $this->registrationData(['roles' => [1], 'status' => 'active', 'balance' => 99999]));

        $user = User::query()->where('email', 'deniz@example.com')->sole();
        $this->assertSame([Role::Customer], $user->roles->pluck('name')->all());
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 0]);
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->post('/giris', ['email' => 'a@example.com', 'password' => 'yanlis'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/giris', ['email' => 'A@example.com', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $this->post('/cikis')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_is_locked_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/giris', ['email' => 'a@example.com', 'password' => 'yanlis']);
        }

        $response = $this->post('/giris', ['email' => 'a@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Çok fazla deneme yapıldı.', $response->getSession()->get('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->post('/giris', ['email' => 'a@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unverified_user_cannot_reach_wallet_until_email_is_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/cuzdan')->assertRedirect(route('verification.notice'));

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/cuzdan')->assertOk();
    }

    public function test_password_reset_does_not_reveal_whether_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'a@example.com']);

        $known = $this->post('/sifremi-unuttum', ['email' => 'a@example.com'])->getSession()->get('status');
        $unknown = $this->post('/sifremi-unuttum', ['email' => 'yok@example.com'])->getSession()->get('status');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post('/sifre-sifirla', ['token' => $notification->token, 'email' => $user->email, 'password' => 'YeniSifre99', 'password_confirmation' => 'YeniSifre99'])
                ->assertRedirect(route('login'));

            return true;
        });

        $this->post('/giris', ['email' => 'a@example.com', 'password' => 'YeniSifre99']);
        $this->assertAuthenticatedAs($user);
    }
}
