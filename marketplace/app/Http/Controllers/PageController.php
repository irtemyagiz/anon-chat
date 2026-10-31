<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = $this->find(['page', 'legal'], $slug);

        return view('pages.show', ['page' => $page]);
    }

    public function blog(): View
    {
        return view('pages.blog', [
            'posts' => Page::query()->visible()->where('type', 'blog')->where('locale', app()->getLocale())->latest('published_at')->paginate(12),
        ]);
    }

    public function post(string $slug): View
    {
        return view('pages.show', ['page' => $this->find(['blog'], $slug)]);
    }

    public function faq(): View
    {
        return view('pages.faq', [
            'faqs' => Faq::query()->where('is_active', true)->where('locale', app()->getLocale())->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Geçerli dildeki sayfa; yoksa Türkçe sürüme düşer.
     *
     * @param  list<string>  $types
     */
    private function find(array $types, string $slug): Page
    {
        return Page::query()->visible()->whereIn('type', $types)->where('slug', $slug)
            ->orderByRaw('case when locale = ? then 0 else 1 end', [app()->getLocale()])
            ->firstOrFail();
    }
}
