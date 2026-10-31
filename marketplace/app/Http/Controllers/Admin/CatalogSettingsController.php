<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FileFormat;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kategoriler (sınırsız alt kategori) ve izin verilen dosya formatları.
 */
class CatalogSettingsController extends Controller
{
    public function categories(): View
    {
        return view('admin.categories', [
            'categories' => Category::query()->withCount('products')->orderBy('sort_order')->get()->groupBy(fn (Category $c) => $c->parent_id ?? 0),
            'parents' => Category::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function storeCategory(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validateCategory($request);

        $category = Category::query()->create([
            ...$this->categoryAttributes($data),
            'slug' => $this->uniqueSlug($data['slug'] ?? null ?: $data['name_tr']),
        ]);

        $this->forgetNavigationCache();
        $audit->record('category.created', $category, null, ['slug' => $category->slug], $request->user());

        return back()->with('status', __('Kategori eklendi.'));
    }

    public function updateCategory(Request $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validateCategory($request, $category);

        $category->update([...$this->categoryAttributes($data), 'is_active' => $request->boolean('is_active')]);

        $this->forgetNavigationCache();
        $audit->record('category.updated', $category, null, $category->only('slug', 'is_active', 'parent_id'), $request->user());

        return back()->with('status', __('Kategori güncellendi.'));
    }

    public function fileFormats(): View
    {
        return view('admin.file-formats', ['formats' => FileFormat::query()->orderBy('extension')->get()]);
    }

    public function toggleFileFormat(Request $request, FileFormat $format, AuditLogger $audit): RedirectResponse
    {
        $format->update(['is_active' => ! $format->is_active]);
        $audit->record('file_format.toggled', $format, null, ['is_active' => $format->is_active], $request->user());

        return back()->with('status', __(':ext formatı güncellendi.', ['ext' => strtoupper($format->extension)]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name_tr' => ['required', 'string', 'max:80'],
            'name_en' => ['nullable', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
            'parent_id' => ['nullable', Rule::exists('categories', 'id'), Rule::notIn(array_filter([$category?->id]))],
            'description_tr' => ['nullable', 'string', 'max:500'],
            'meta_description_tr' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function categoryAttributes(array $data): array
    {
        return [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => ['tr' => $data['name_tr'], 'en' => ($data['name_en'] ?? null) ?: $data['name_tr']],
            'description' => filled($data['description_tr'] ?? null) ? ['tr' => $data['description_tr']] : null,
            'meta_description' => filled($data['meta_description_tr'] ?? null) ? ['tr' => $data['meta_description_tr']] : null,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'kategori';
        $slug = $base;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(3));
        }

        return $slug;
    }

    private function forgetNavigationCache(): void
    {
        foreach (['tr', 'en'] as $locale) {
            Cache::forget("nav.categories.{$locale}");
        }
    }
}
