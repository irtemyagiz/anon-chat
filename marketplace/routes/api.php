<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\DownloadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1 (/api/v1). Belgeler: docs/API.md
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::get('/categories', [CatalogController::class, 'categories'])->name('categories');
    Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('/products/{product}', [CatalogController::class, 'product'])->name('products.show');
    Route::get('/designers/{profile}', [CatalogController::class, 'designer'])->name('designers.show');
    Route::get('/exchange-rate', [CatalogController::class, 'exchangeRate'])->name('exchange-rate');

    Route::post('/auth/tokens', [AuthTokenController::class, 'store'])->middleware('throttle:5,1')->name('tokens.store');

    Route::get('/downloads/{file:uuid}', [DownloadController::class, 'api'])->middleware(['signed', 'throttle:30,1'])->name('downloads.file');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function (): void {
        Route::delete('/auth/tokens/current', [AuthTokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('/me', [AccountController::class, 'me'])->name('me');
        Route::get('/me/wallet', [AccountController::class, 'wallet'])->name('me.wallet');
        Route::get('/me/library', [AccountController::class, 'library'])->name('me.library');
        Route::get('/me/library/{product}/downloads', [AccountController::class, 'downloads'])->name('me.downloads');
        Route::post('/products/{product}/purchase', [PurchaseController::class, 'store'])->middleware('throttle:20,1')->name('products.purchase');
    });
});
