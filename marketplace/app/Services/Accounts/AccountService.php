<?php

namespace App\Services\Accounts;

use App\Enums\CreatorStatus;
use App\Models\CreatorProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Wallet\WalletService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hesap oluşturma ve creator başvurusu.
 *
 * Standart kullanıcı ile creator aynı kullanıcı tablosunu kullanır; creator
 * olmak "creator" rolü + creator_profiles kaydı demektir. Creator başvurusu
 * yönetici onayına kadar "pending" durumunda bekler.
 */
class AccountService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{username: string, first_name: string, last_name: string, email: string, password: string, country_code?: string|null, locale?: string|null}  $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'username' => Str::lower($data['username']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'country_code' => $data['country_code'] ?? null,
                'locale' => $data['locale'] ?? app()->getLocale(),
            ]);

            $user->roles()->attach(Role::query()->where('name', Role::Customer)->value('id'));
            $this->wallets->walletFor($user);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }

    /**
     * @param  array{designer_name: string, store_name: string, description?: string|null}  $data
     */
    public function applyAsCreator(User $user, array $data): CreatorProfile
    {
        return DB::transaction(function () use ($user, $data): CreatorProfile {
            $profile = $user->creatorProfile()->create([
                'designer_name' => $data['designer_name'],
                'store_name' => $data['store_name'],
                'store_slug' => $this->uniqueStoreSlug($data['store_name']),
                'description' => $data['description'] ?? null,
                'terms_accepted_at' => now(),
            ]);

            $profile->forceFill(['status' => CreatorStatus::Pending])->save();
            $user->setRelation('creatorProfile', $profile);

            $creatorRoleId = Role::query()->where('name', Role::Creator)->value('id');
            $user->roles()->syncWithoutDetaching([$creatorRoleId]);
            $user->unsetRelation('roles');

            $this->audit->record('creator.applied', $profile, null, ['store' => $profile->store_slug], $user);

            return $profile;
        });
    }

    private function uniqueStoreSlug(string $storeName): string
    {
        $base = Str::slug($storeName) ?: 'magaza';
        $slug = $base;

        while (CreatorProfile::query()->where('store_slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
