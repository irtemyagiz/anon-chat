<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const PERMISSIONS = [
        'admin.access' => 'Yönetim paneline erişim',
        'users.manage' => 'Kullanıcıları yönet',
        'creators.manage' => 'Creator başvuru ve mağazalarını yönet',
        'products.moderate' => 'Ürünleri onayla / reddet',
        'categories.manage' => 'Kategorileri yönet',
        'wallets.adjust' => 'Puan bakiyesi düzeltmesi yap',
        'payments.manage' => 'Ödemeleri ve iadeleri yönet',
        'exchange_rates.manage' => 'Döviz kurlarını yönet',
        'commissions.manage' => 'Komisyon oranlarını yönet',
        'payouts.manage' => 'Hakediş taleplerini yönet',
        'content.manage' => 'İçerik ve SEO yönetimi',
        'settings.manage' => 'Sistem ayarları',
        'logs.view' => 'Logları görüntüle',
        'comments.moderate' => 'Yorumları denetle',
        'support.manage' => 'Destek taleplerini yanıtla',
    ];

    /**
     * @var array<string, array{label: string, permissions: list<string>|string}>
     */
    private const ROLES = [
        Role::Admin => ['label' => 'Yönetici', 'permissions' => '*'],
        Role::Moderator => ['label' => 'Moderatör', 'permissions' => ['admin.access', 'products.moderate', 'comments.moderate', 'support.manage']],
        Role::Creator => ['label' => 'Creator', 'permissions' => []],
        Role::Customer => ['label' => 'Kullanıcı', 'permissions' => []],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $label) {
            Permission::query()->updateOrCreate(['name' => $name], ['label' => $label]);
        }

        foreach (self::ROLES as $name => $definition) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['label' => $definition['label']]);

            $permissions = $definition['permissions'] === '*'
                ? Permission::query()->pluck('id')
                : Permission::query()->whereIn('name', $definition['permissions'])->pluck('id');

            $role->permissions()->sync($permissions);
        }
    }
}
