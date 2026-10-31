<?php

namespace App\Http\Controllers;

use App\Enums\CreatorStatus;
use App\Models\Category;
use App\Models\CreatorProfile;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * XML site haritası (bölümlere ayrılmış, büyük kataloglar için sayfalı) ve robots.txt.
 */
class SeoController extends Controller
{
    private const PER_FILE = 5000;

    public function sitemapIndex(): Response
    {
        $xml = Cache::remember('sitemap.index', 3600, function (): string {
            $sections = ['static' => 1, 'categories' => 1, 'pages' => 1,
                'products' => max(1, (int) ceil(Product::query()->published()->count() / self::PER_FILE)),
                'creators' => max(1, (int) ceil(CreatorProfile::query()->where('status', CreatorStatus::Approved)->count() / self::PER_FILE)),
            ];

            $entries = '';
            foreach ($sections as $section => $pages) {
                for ($page = 1; $page <= $pages; $page++) {
                    $entries .= '<sitemap><loc>'.e(route('sitemap.section', ['section' => $section, 'page' => $page])).'</loc></sitemap>';
                }
            }

            return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</sitemapindex>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function sitemapSection(string $section, int $page): Response
    {
        abort_unless(in_array($section, ['static', 'categories', 'pages', 'products', 'creators'], true) && $page >= 1, 404);

        $xml = Cache::remember("sitemap.{$section}.{$page}", 3600, function () use ($section, $page): string {
            $urls = match ($section) {
                'static' => collect(['home', 'products.index', 'categories.index', 'designers.index', 'creator.apply', 'faq', 'blog.index'])
                    ->map(fn (string $route) => [route($route), null]),
                'categories' => Category::query()->where('is_active', true)->get(['slug', 'updated_at'])
                    ->map(fn (Category $c) => [route('categories.show', $c->slug), $c->updated_at]),
                'pages' => Page::query()->visible()->get(['type', 'slug', 'locale', 'updated_at'])
                    ->map(fn (Page $p) => [($p->type === 'blog' ? route('blog.show', $p->slug) : route('pages.show', $p->slug)).($p->locale === 'en' ? '?lang=en' : ''), $p->updated_at]),
                'products' => Product::query()->published()->orderBy('id')->forPage($page, self::PER_FILE)->get(['slug', 'updated_at'])
                    ->map(fn (Product $p) => [route('products.show', $p->slug), $p->updated_at]),
                'creators' => CreatorProfile::query()->where('status', CreatorStatus::Approved)->orderBy('id')->forPage($page, self::PER_FILE)->get(['store_slug', 'updated_at'])
                    ->map(fn (CreatorProfile $c) => [route('designers.show', $c->store_slug), $c->updated_at]),
            };

            $entries = $urls->map(fn (array $url) => '<url><loc>'.e($url[0]).'</loc>'
                .($url[1] ? '<lastmod>'.$url[1]->toAtomString().'</lastmod>' : '')
                .(str_contains($url[0], '?') ? '' : '<xhtml:link rel="alternate" hreflang="en" href="'.e($url[0].'?lang=en').'"/>')
                .'</url>')->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'.$entries.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Özel sayfalar (hesap, cüzdan, panel, ödeme, indirme) taranmaz.
     */
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /hesabim', 'Disallow: /cuzdan', 'Disallow: /puan-yukle', 'Disallow: /panel', 'Disallow: /yonetim', 'Disallow: /destek', 'Disallow: /indir', 'Disallow: /payments', 'Disallow: /creator/durum', 'Disallow: /*?*sirala=', '', 'Sitemap: '.route('sitemap.index')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
