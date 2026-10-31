<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tüm yanıtlara güvenlik başlıklarını ekler.
 *
 * CSP her istek için üretilen bir nonce kullanır; Vite etiketleri ve satır içi betikler
 * bu nonce ile işaretlenir. Alpine.js ifadeleri çalışma anında derlediği için
 * script-src 'unsafe-eval' içerir; satır içi olay işleyicileri (onclick vb.) engellidir.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=(), fullscreen=(self "https://www.youtube-nocookie.com" "https://player.vimeo.com")');

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age='.config('marketplace.security.hsts_max_age').'; includeSubDomains');
        }

        $mode = config('marketplace.security.csp');

        if ($mode !== 'off' && ! Vite::isRunningHot()) {
            $headers->set($mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', $this->policy($nonce, $request));
        }

        return $response;
    }

    private function policy(string $nonce, Request $request): string
    {
        $assets = implode(' ', $this->externalSources());
        $frames = implode(' ', config('marketplace.security.frame_sources'));

        $directives = [
            'default-src' => "'self'",
            'script-src' => trim("'self' 'nonce-{$nonce}' 'unsafe-eval' {$assets}"),
            'style-src' => trim("'self' 'unsafe-inline' {$assets}"),
            'img-src' => trim("'self' data: blob: {$assets}"),
            'font-src' => trim("'self' data: {$assets}"),
            'connect-src' => trim("'self' {$assets}"),
            'media-src' => trim("'self' {$assets}"),
            'frame-src' => $frames,
            'frame-ancestors' => "'none'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
        ];

        $policy = collect($directives)->map(fn (string $value, string $name) => "{$name} {$value}")->implode('; ');

        return $request->isSecure() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /**
     * CDN (ASSET_URL), harici medya diski (S3 vb.) ve elle eklenen kaynaklar.
     *
     * @return list<string>
     */
    private function externalSources(): array
    {
        $sources = preg_split('/\s+/', (string) config('marketplace.security.csp_extra_sources'), -1, PREG_SPLIT_NO_EMPTY);

        foreach ([config('app.asset_url'), $this->mediaDiskUrl()] as $url) {
            $origin = $this->origin($url);

            if ($origin !== null && $origin !== $this->origin(config('app.url'))) {
                $sources[] = $origin;
            }
        }

        return array_values(array_unique($sources));
    }

    private function mediaDiskUrl(): ?string
    {
        try {
            return Storage::disk(config('marketplace.storage.public_media_disk'))->url('');
        } catch (\Throwable) {
            return null;
        }
    }

    private function origin(?string $url): ?string
    {
        $parts = $url ? parse_url($url) : false;

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
