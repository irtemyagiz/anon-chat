<?php

namespace App\Services\Accounts;

use App\Enums\PayoutRequestStatus;
use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Models\CreatorPayoutRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Earnings\CreatorEarningService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * KVKK/GDPR kapsamında hesap silme.
 *
 * Kişisel veriler anonimleştirilir ve hesap kapatılır. Mali kayıtlar (sipariş,
 * puan defteri, hakediş, ödeme) yasal saklama yükümlülüğü nedeniyle silinmez;
 * yalnızca kişiyle bağı anonim kullanıcı kaydına kalır.
 */
class AccountDeletionService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly CreatorEarningService $earnings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Silmeyi engelleyen durumlar. Boşsa hesap silinebilir.
     *
     * @return list<string>
     */
    public function blockers(User $user): array
    {
        $blockers = [];

        if ($user->isAdmin()) {
            $blockers[] = __('Yönetici hesapları silinemez; önce yönetici rolünün kaldırılması gerekir.');
        }

        if ($user->creatorProfile !== null) {
            $balance = $this->earnings->lockBalance($user->id);

            if ((int) $balance->pending_cents + (int) $balance->available_cents > 0) {
                $blockers[] = __('Ödenmemiş hakedişin var. Önce hakediş talebi oluşturup ödemeyi tamamla.');
            }

            if (CreatorPayoutRequest::query()->where('creator_id', $user->id)->whereIn('status', [PayoutRequestStatus::Pending, PayoutRequestStatus::UnderReview, PayoutRequestStatus::Approved])->exists()) {
                $blockers[] = __('Sonuçlanmamış hakediş talebin var.');
            }
        }

        return $blockers;
    }

    public function delete(User $user): void
    {
        $remainingPoints = $this->wallets->balanceOf($user);

        DB::transaction(function () use ($user, $remainingPoints): void {
            $this->audit->record('user.deleted', $user, null, null, $user, ['remaining_points' => $remainingPoints]);

            if ($user->creatorProfile !== null) {
                $user->products()->where('status', ProductStatus::Published)->update(['status' => ProductStatus::Unpublished]);
                $user->creatorProfile->forceFill(['status' => 'suspended', 'description' => null, 'social_links' => null, 'rejection_reason' => __('Hesap sahibi tarafından kapatıldı')])->save();
            }

            if ($user->avatar_path) {
                Storage::disk(config('marketplace.storage.public_media_disk'))->delete($user->avatar_path);
            }

            $anonymous = 'silinmis_'.Str::lower(Str::random(10));

            $user->forceFill([
                'username' => $anonymous,
                'first_name' => __('Silinmiş'),
                'last_name' => __('kullanıcı'),
                'email' => $anonymous.'@deleted.invalid',
                'password' => Str::random(64),
                'avatar_path' => null,
                'bio' => null,
                'country_code' => null,
                'display_currency' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'last_login_ip' => null,
                'status' => UserStatus::Banned,
                'suspension_reason' => 'account_deleted',
            ])->save();

            $user->settings()->delete();
            $user->notifications()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('favorites')->where('user_id', $user->id)->delete();
            DB::table('follows')->where('follower_id', $user->id)->orWhere('creator_id', $user->id)->delete();
            $user->collections()->delete();

            $user->delete();
        });
    }
}
