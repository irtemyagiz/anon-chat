<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * Ana sayfa vitrin önbelleğinin anahtarları. Önbellekte yalnızca kimlik listeleri ve
 * sayılar tutulur (cache.serializable_classes kapalı olduğundan nesne saklanmaz).
 */
class CatalogCache
{
    public const HomeSections = 'home.sections';

    public const CategoryCounts = 'home.category_counts';

    public const Seconds = 300;

    public static function forget(): void
    {
        Cache::forget(self::HomeSections);
        Cache::forget(self::CategoryCounts);
    }
}
