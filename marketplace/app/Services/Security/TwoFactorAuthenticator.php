<?php

namespace App\Services\Security;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Zaman tabanlı tek kullanımlık şifre (TOTP, RFC 6238) ile iki aşamalı doğrulama.
 * Google Authenticator, Microsoft Authenticator, 1Password vb. uygulamalarla uyumludur.
 *
 * Gizli anahtar ve kurtarma kodları veritabanında şifreli saklanır. Aynı kod
 * geçerlilik penceresi içinde ikinci kez kullanılamaz (tekrar oynatma koruması).
 */
class TwoFactorAuthenticator
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    /** Saat kaymasına karşı önceki/sonraki pencere de kabul edilir. */
    private const WINDOW = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(private readonly AuditLogger $audit) {}

    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $chunk) => self::BASE32[bindec(str_pad($chunk, 5, '0'))], str_split($bits, 5)));
    }

    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = rawurlencode(config('app.name'));

        return "otpauth://totp/{$issuer}:".rawurlencode($user->email)."?secret={$secret}&issuer={$issuer}&digits=".self::DIGITS.'&period='.self::PERIOD;
    }

    public function verify(string $secret, string $code, ?User $user = null): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = intdiv(time(), self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) {
                return $user === null || Cache::add("2fa-used:{$user->id}:".($counter + $offset), true, self::PERIOD * 3);
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function enable(User $user, string $secret): array
    {
        $recoveryCodes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $code) => hash('sha256', $code), $recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->audit->record('security.two_factor_enabled', $user, actor: $user);

        return $recoveryCodes;
    }

    /**
     * Kurtarma kodu tek kullanımlıktır; kullanılan kod listeden çıkarılır.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hash = hash('sha256', Str::upper(trim($code)));
        $codes = $user->two_factor_recovery_codes ?? [];

        if (! in_array($hash, $codes, true)) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$hash]))])->save();
        $this->audit->record('security.recovery_code_used', $user, actor: $user, context: ['remaining' => count($codes) - 1]);

        return true;
    }

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && filled($user->two_factor_secret);
    }

    private function codeAt(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N2', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $bits = '';

        foreach (str_split(strtoupper($secret)) as $character) {
            $position = strpos(self::BASE32, $character);

            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        return implode('', array_map(fn (string $byte) => chr(bindec($byte)), str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8)));
    }
}
