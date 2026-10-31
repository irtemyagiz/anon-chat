<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Creator\DashboardController as CreatorDashboardController;
use App\Http\Controllers\Creator\ProductActionController as CreatorProductActionController;
use App\Http\Controllers\Creator\ProductController as CreatorProductController;
use App\Http\Controllers\Creator\SalesController as CreatorSalesController;
use App\Http\Controllers\Creator\StoreSettingsController;
use App\Http\Controllers\CreatorApplicationController;
use App\Http\Controllers\DesignerController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SandboxCheckoutController;
use App\Http\Controllers\TopUpController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Herkese açık sayfalar
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/modeller', [ProductController::class, 'index'])->name('products.index');
Route::get('/model/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/kategoriler', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/kategori/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tasarimcilar', [DesignerController::class, 'index'])->name('designers.index');
Route::get('/magaza/{profile}', [DesignerController::class, 'show'])->name('designers.show');
Route::get('/tasarim-yukle', [CreatorApplicationController::class, 'upload'])->name('upload');
Route::post('/tercihler', PreferenceController::class)->middleware('throttle:30,1')->name('preferences.update');

/*
|--------------------------------------------------------------------------
| Kimlik doğrulama
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/kayit', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/kayit', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/giris', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/giris', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/sifremi-unuttum', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/sifremi-unuttum', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/sifre-sifirla/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/sifre-sifirla', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::get('/creator-ol', [CreatorApplicationController::class, 'create'])->name('creator.apply');
Route::post('/creator-ol', [CreatorApplicationController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth')->group(function (): void {
    Route::post('/cikis', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/e-posta-dogrula', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/e-posta-dogrula/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/e-posta-dogrula/tekrar', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::get('/creator/durum', [CreatorApplicationController::class, 'status'])->name('creator.status');

    /*
    | Creator paneli: yalnızca onaylı creator'lar
    */
    Route::middleware(['verified', 'creator'])->prefix('panel')->name('creator.')->group(function (): void {
        Route::get('/', CreatorDashboardController::class)->name('dashboard');
        Route::get('/urunler', [CreatorProductController::class, 'index'])->name('products.index');
        Route::get('/urunler/yeni', [CreatorProductController::class, 'create'])->name('products.create');
        Route::post('/urunler', [CreatorProductController::class, 'store'])->middleware('throttle:30,1')->name('products.store');
        Route::get('/urunler/{product:uuid}', [CreatorProductController::class, 'edit'])->name('products.edit');
        Route::put('/urunler/{product:uuid}', [CreatorProductController::class, 'update'])->middleware('throttle:60,1')->name('products.update');
        Route::post('/urunler/{product:uuid}/gonder', [CreatorProductActionController::class, 'submit'])->name('products.submit');
        Route::post('/urunler/{product:uuid}/yayindan-kaldir', [CreatorProductActionController::class, 'unpublish'])->name('products.unpublish');
        Route::delete('/urunler/{product:uuid}/dosyalar/{file:uuid}', [CreatorProductActionController::class, 'destroyFile'])->name('products.files.destroy');
        Route::delete('/urunler/{product:uuid}/gorseller/{image}', [CreatorProductActionController::class, 'destroyImage'])->name('products.images.destroy');
        Route::delete('/urunler/{product:uuid}/onizleme', [CreatorProductActionController::class, 'destroyPreview'])->name('products.preview.destroy');
        Route::get('/satislar', [CreatorSalesController::class, 'sales'])->name('sales');
        Route::get('/gelirler', [CreatorSalesController::class, 'earnings'])->name('earnings');
        Route::get('/magaza', [StoreSettingsController::class, 'edit'])->name('store.edit');
        Route::put('/magaza', [StoreSettingsController::class, 'update'])->name('store.update');
    });

    Route::middleware('verified')->group(function (): void {
        Route::get('/cuzdan', WalletController::class)->name('wallet');
        Route::get('/puan-yukle', [TopUpController::class, 'create'])->name('top-up.create');
        Route::post('/puan-yukle', [TopUpController::class, 'store'])->middleware('throttle:10,1')->name('top-up.store');
        Route::get('/puan-yukle/sonuc/{purchase}', [TopUpController::class, 'result'])->name('top-up.result');
        Route::post('/model/{product}/satin-al', [PurchaseController::class, 'store'])->middleware('throttle:20,1')->name('products.purchase');
        Route::get('/indir/{file:uuid}', DownloadController::class)->middleware(['signed', 'throttle:30,1'])->name('downloads.file');
    });
});

/*
|--------------------------------------------------------------------------
| Ödeme sağlayıcıları
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->where('provider', '[a-z0-9_]+')
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments');

if (! app()->isProduction()) {
    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::get('/payments/sandbox/{purchase}', [SandboxCheckoutController::class, 'show'])->name('sandbox.checkout');
        Route::post('/payments/sandbox/{purchase}', [SandboxCheckoutController::class, 'complete'])->name('sandbox.complete');
    });
}
