<?php

namespace Tests\Unit;

use App\Services\Audit\AuditLogger;
use App\Services\Security\TwoFactorAuthenticator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TotpTest extends TestCase
{
    /**
     * RFC 6238 Ek B test vektörleri (SHA-1, "12345678901234567890" anahtarı, son 6 hane).
     */
    public function test_codes_match_rfc_6238_vectors(): void
    {
        $authenticator = new TwoFactorAuthenticator(new AuditLogger);
        $codeAt = new ReflectionMethod($authenticator, 'codeAt');
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'] as $time => $expected) {
            $this->assertSame($expected, $codeAt->invoke($authenticator, $secret, intdiv($time, 30)));
        }
    }

    public function test_generated_secret_is_base32(): void
    {
        $secret = (new TwoFactorAuthenticator(new AuditLogger))->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    }
}
