<?php

namespace App\Http\Controllers;

use App\Services\Catalog\ProductSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Başlıktaki arama kutusunun canlı önerileri. Yalnızca yayındaki ürünler ve onaylı
 * tasarımcılar döner; en az 2 karakter gerekir.
 */
class SearchSuggestionController extends Controller
{
    public function __invoke(Request $request, ProductSearch $search): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return response()->json(['products' => [], 'designers' => [], 'categories' => [], 'tags' => []]);
        }

        return response()->json($search->suggestions($term))->header('Cache-Control', 'private, max-age=60');
    }
}
