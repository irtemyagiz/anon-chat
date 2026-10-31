<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * İçerik sayfaları: "page" (hakkımızda vb.), "legal" (yasal metinler), "blog".
 * Gövde Markdown'dır; HTML girdisi temizlenerek işlenir.
 */
#[Fillable(['type', 'slug', 'locale', 'title', 'excerpt', 'body', 'meta_title', 'meta_description', 'is_published', 'published_at'])]
class Page extends Model
{
    public const TYPES = ['page' => 'Sayfa', 'legal' => 'Yasal metin', 'blog' => 'Blog yazısı'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_published', true)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
