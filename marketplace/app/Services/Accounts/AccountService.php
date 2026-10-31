<?php

namespace App\Services\Accounts;

use App\Enums\CreatorStatus;
use App\Enums\UserStatus;
use App\Exceptions\MarketplaceException;
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

    public function reviewCreator(CreatorProfile $profile, CreatorStatus $status, User $admin, ?string $reason = null): void
    {
        $old = $profile->status;

        if ($old === $status) {
            return;
        }

        $profile->forceFill([
            'status' => $status,
            'approved_at' => $status === CreatorStatus::Approved ? now() : $profile->approved_at,
            'approved_by' => $status === CreatorStatus::Approved ? $admin->id : $profile->approved_by,
            'rejection_reason' => in_array($status, [CreatorStatus::Rejected, CreatorStatus::Suspended], true) ? $reason : null,
        ])->save();

        $this->audit->record('creator.'.$status->value, $profile, ['status' => $old->value], ['status' => $status->value], $admin, array_filter(['reason' => $reason]));
    }

    /**
     * Hesabı askıya alır veya yeniden etkinleştirir. Kişi kendi hesabını ve
     * (yönetici değilse) bir yöneticinin hesabını değiştiremez.
     */
    public function setStatus(User $user, UserStatus $status, User $admin, ?string $reason = null): void
    {
        if ($user->is($admin)) {
            throw new MarketplaceException(__('Kendi hesabınızın durumunu değiştiremezsiniz.'));
        }

        if ($user->isAdmin() && ! $admin->isAdmin()) {
            throw new MarketplaceException(__('Yönetici hesapları yalnızca yöneticiler tarafından değiştirilebilir.'));
        }

        $old = $user->status;

        $user->forceFill([
            'status' => $status,
            'suspended_at' => $status === UserStatus::Active ? null : now(),
            'suspension_reason' => $status === UserStatus::Active ? null : $reason,
        ])->save();

        if ($status !== UserStatus::Active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $this->audit->record('user.status_changed', $user, ['status' => $old->value], ['status' => $status->value], $admin, array_filter(['reason' => $reason]));
    }

    /**
     * Rolleri günceller. Yalnızca yöneticiler rol değiştirebilir; kimse kendi
     * yönetici rolünü kaldıramaz (panelde yöneticisiz kalma riskine karşı).
     *
     * @param  list<string>  $roleNames
     */
    public function syncRoles(User $user, array $roleNames, User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw new MarketplaceException(__('Rolleri yalnızca yöneticiler değiştirebilir.'));
        }

        if ($user->is($admin) && ! in_array(Role::Admin, $roleNames, true)) {
            throw new MarketplaceException(__('Kendi yönetici rolünüzü kaldıramazsınız.'));
        }

        $roleNames = array_values(array_unique([...$roleNames, Role::Customer]));
        $old = $user->roles->pluck('name')->sort()->values()->all();

        $user->roles()->sync(Role::query()->whereIn('name', $roleNames)->pluck('id')->mapWithKeys(fn (int $id) => [$id => ['assigned_by' => $admin->id, 'created_at' => now()]])->all());
        $user->unsetRelation('roles');

        $this->audit->record('user.roles_changed', $user, ['roles' => $old], ['roles' => collect($roleNames)->sort()->values()->all()], $admin);
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
