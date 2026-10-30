<?php

namespace Database\Factories;

use App\Enums\CreatorStatus;
use App\Models\CreatorProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => Str::lower(fake()->unique()->bothify('user_????_####')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'country_code' => 'US',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function turkish(): static
    {
        return $this->state(fn (array $attributes) => ['country_code' => 'TR']);
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->roles()->attach(Role::query()->firstOrCreate(['name' => $role], ['label' => ucfirst($role)]));
        });
    }

    public function admin(): static
    {
        return $this->withRole(Role::Admin);
    }

    /**
     * Onaylanmış creator mağazası olan kullanıcı.
     */
    public function creator(CreatorStatus $status = CreatorStatus::Approved): static
    {
        return $this->withRole(Role::Creator)->afterCreating(function (User $user) use ($status): void {
            CreatorProfile::factory()->for($user)->create()->forceFill(['status' => $status])->save();
        });
    }
}
