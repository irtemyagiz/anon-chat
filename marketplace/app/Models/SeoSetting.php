<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['route_name', 'locale', 'meta_title', 'meta_description', 'og_image_path', 'noindex'])]
class SeoSetting extends Model
{
    /** Yönetim panelinden meta bilgisi düzenlenebilen sayfalar. */
    public const ROUTES = [
        'home' => 'Ana sayfa',
        'products.index' => 'Model kataloğu',
        'categories.index' => 'Kategoriler',
        'designers.index' => 'Tasarımcılar',
        'creator.apply' => 'Creator ol',
        'faq' => 'Sık sorulan sorular',
        'blog.index' => 'Blog',
    ];

    protected function casts(): array
    {
        return ['noindex' => 'boolean'];
    }
}
