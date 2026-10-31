<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\Page;
use App\Services\Audit\AuditLogger;
use App\Services\Files\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sayfalar (hakkımızda, yasal metinler, blog), SSS ve ana sayfa bannerları.
 */
class ContentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function pages(Request $request): View
    {
        return view('admin.content.pages', [
            'pages' => Page::query()->orderBy('type')->orderBy('slug')->orderBy('locale')->get(),
        ]);
    }

    public function editPage(?Page $page = null): View
    {
        return view('admin.content.page-form', ['page' => $page ?? new Page(['type' => 'page', 'locale' => 'tr'])]);
    }

    public function savePage(Request $request, ?Page $page = null): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Page::TYPES))],
            'locale' => ['required', Rule::in(['tr', 'en'])],
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9-]+$/'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:200000'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'published_at' => ['nullable', 'date'],
        ]);

        $slug = Str::slug($data['slug'] ?? '' ?: $data['title']);
        $duplicate = Page::query()->where(['type' => $data['type'], 'slug' => $slug, 'locale' => $data['locale']])->when($page, fn ($q) => $q->whereKeyNot($page->id))->exists();

        if ($duplicate) {
            return back()->withInput()->withErrors(['slug' => __('Bu dil ve türde aynı adresli bir sayfa zaten var.')]);
        }

        $page ??= new Page;
        $page->fill([...$data, 'slug' => $slug, 'is_published' => $request->boolean('is_published')]);
        $page->author_id ??= $request->user()->id;
        $page->save();

        $this->audit->record('page.saved', $page, null, $page->only('type', 'slug', 'locale', 'is_published'), $request->user());

        return redirect()->route('admin.content.pages.edit', $page)->with('status', __('Sayfa kaydedildi.'));
    }

    public function destroyPage(Request $request, Page $page): RedirectResponse
    {
        $this->audit->record('page.deleted', null, $page->only('type', 'slug', 'locale'), null, $request->user());
        $page->delete();

        return redirect()->route('admin.content.pages')->with('status', __('Sayfa silindi.'));
    }

    public function faqs(): View
    {
        return view('admin.content.faqs', ['faqs' => Faq::query()->orderBy('locale')->orderBy('sort_order')->get()]);
    }

    public function saveFaq(Request $request, ?Faq $faq = null): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(['tr', 'en'])],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        ($faq ?? new Faq)->fill([...$data, 'sort_order' => $data['sort_order'] ?? 0, 'is_active' => $request->boolean('is_active', true)])->save();

        return back()->with('status', __('Soru kaydedildi.'));
    }

    public function destroyFaq(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('status', __('Soru silindi.'));
    }

    public function banners(): View
    {
        return view('admin.content.banners', ['banners' => Banner::query()->orderBy('placement')->orderBy('sort_order')->get()]);
    }

    public function saveBanner(Request $request, ImageProcessor $images, ?Banner $banner = null): RedirectResponse
    {
        $data = $request->validate([
            'title_tr' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'subtitle_tr' => ['nullable', 'string', 'max:240'],
            'link_url' => ['nullable', 'string', 'max:255', 'regex:/^(\/|https:\/\/)/'],
            'link_label_tr' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => ['nullable', 'file', 'max:10240'],
        ]);

        $banner ??= new Banner(['placement' => 'home']);
        $banner->fill([
            'title' => ['tr' => $data['title_tr'], 'en' => ($data['title_en'] ?? null) ?: $data['title_tr']],
            'subtitle' => filled($data['subtitle_tr'] ?? null) ? ['tr' => $data['subtitle_tr']] : null,
            'link_url' => $data['link_url'] ?? null,
            'link_label' => filled($data['link_label_tr'] ?? null) ? ['tr' => $data['link_label_tr']] : null,
            'sort_order' => $data['sort_order'] ?? 0,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->hasFile('image')) {
            try {
                $disk = config('marketplace.storage.public_media_disk');
                $stored = $images->store($request->file('image'), $disk, 'banners', 2400);
            } catch (MarketplaceException $exception) {
                return back()->withInput()->withErrors(['image' => $exception->getMessage()]);
            }

            if ($banner->image_path) {
                Storage::disk($disk)->delete($banner->image_path);
            }

            $banner->image_path = $stored['path'];
        }

        $banner->save();

        return back()->with('status', __('Banner kaydedildi.'));
    }

    public function destroyBanner(Banner $banner): RedirectResponse
    {
        if ($banner->image_path) {
            Storage::disk(config('marketplace.storage.public_media_disk'))->delete($banner->image_path);
        }

        $banner->delete();

        return back()->with('status', __('Banner silindi.'));
    }
}
