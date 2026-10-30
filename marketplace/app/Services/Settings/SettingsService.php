<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Yönetim panelinden değiştirilebilen sistem ayarları.
 *
 * Değer önce system_settings tablosundan, yoksa config('marketplace.*')
 * varsayılanından okunur. Tüm ayarlar tek bir önbellek anahtarında tutulur.
 */
class SettingsService
{
    private const CACHE_KEY = 'marketplace.system_settings';

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return config("marketplace.{$key}", $default);
    }

    public function integer(string $key): int
    {
        return (int) $this->get($key);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }

    public function set(string $key, mixed $value, ?User $actor = null, string $group = 'general', bool $encrypted = false): void
    {
        $old = $this->get($key);

        SystemSetting::query()->updateOrCreate(['key' => $key], [
            'group' => $group,
            'value' => $encrypted ? Crypt::encryptString((string) $value) : json_encode($value),
            'type' => get_debug_type($value),
            'is_encrypted' => $encrypted,
        ]);

        Cache::forget(self::CACHE_KEY);

        $this->audit->record('settings.updated', null, [$key => $encrypted ? '***' : $old], [$key => $encrypted ? '***' : $value], $actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => SystemSetting::query()->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [
                $setting->key => $setting->is_encrypted
                    ? Crypt::decryptString((string) $setting->value)
                    : json_decode((string) $setting->value, true),
            ])
            ->all());
    }
}
