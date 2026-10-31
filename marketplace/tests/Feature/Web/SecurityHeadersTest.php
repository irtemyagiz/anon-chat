<?php

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_security_headers_are_sent_on_every_response(): void
    {
        foreach (['/', '/olmayan-sayfa', route('robots')] as $url) {
            $response = $this->get($url);
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        }

        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_enforced_csp_uses_a_fresh_nonce_that_matches_inline_scripts(): void
    {
        config(['marketplace.security.csp' => 'enforce']);

        $first = $this->get('/');
        $policy = $first->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        $first->assertHeaderMissing('Content-Security-Policy-Report-Only');

        preg_match("/'nonce-([^']+)'/", $policy, $match);
        $this->assertNotEmpty($match[1] ?? null);
        $first->assertSee('nonce="'.$match[1].'"', false);

        foreach (["frame-ancestors 'none'", "object-src 'none'", "base-uri 'self'", 'https://www.youtube-nocookie.com'] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }

        $this->assertStringNotContainsString($match[1], $this->get('/')->headers->get('Content-Security-Policy'), 'Her istekte yeni nonce');
    }

    public function test_csp_modes_and_external_media_origin(): void
    {
        config(['marketplace.security.csp' => 'report', 'app.asset_url' => 'https://cdn.example.com/katman']);
        $response = $this->get('/');
        $response->assertHeaderMissing('Content-Security-Policy');
        $this->assertStringContainsString('https://cdn.example.com', $response->headers->get('Content-Security-Policy-Report-Only'));

        config(['marketplace.security.csp' => 'off']);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_hsts_only_over_https_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
