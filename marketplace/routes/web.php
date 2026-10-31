<?php

use App\Http\Controllers\Admin\CatalogSettingsController;
use App\Http\Controllers\Admin\CommissionController as AdminCommissionController;
use App\Http\Controllers\Admin\CreatorController as AdminCreatorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExchangeRateController as AdminExchangeRateController;
use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PointController as AdminPointController;
use App\Http\Controllers\Admin\ProductModerationController;
use App\Http\Controllers\Admin\TwoFactorController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
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
| Yönetim paneli: admin.access izni + iki aşamalı doğrulama zorunlu.
| Her değişiklik isteği admin_logs tablosuna yazılır.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'admin'])->prefix('yonetim')->name('admin.')->group(function (): void {
    Route::get('/iki-asamali-dogrulama/kurulum', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/iki-asamali-dogrulama/kurulum', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.confirm');
    Route::get('/iki-asamali-dogrulama', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/iki-asamali-dogrulama', [TwoFactorController::class, 'verify'])->middleware('throttle:20,1')->name('two-factor.verify');

    Route::middleware(['admin.2fa', 'admin.log'])->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::middleware('can:creators.manage')->group(function (): void {
            Route::get('/creatorlar', [AdminCreatorController::class, 'index'])->name('creators.index');
            Route::put('/creatorlar/{profile:id}', [AdminCreatorController::class, 'update'])->name('creators.update');
        });

        Route::middleware('can:products.moderate')->group(function (): void {
            Route::get('/urunler', [ProductModerationController::class, 'index'])->name('products.index');
            Route::get('/urunler/{product:uuid}', [ProductModerationController::class, 'show'])->name('products.show');
            Route::post('/urunler/{product:uuid}/karar', [ProductModerationController::class, 'decide'])->name('products.decide');
            Route::post('/urunler/{product:uuid}/one-cikar', [ProductModerationController::class, 'feature'])->name('products.feature');
        });

        Route::middleware('can:users.manage')->group(function (): void {
            Route::get('/kullanicilar', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('/kullanicilar/{user:uuid}', [AdminUserController::class, 'show'])->name('users.show');
            Route::put('/kullanicilar/{user:uuid}/durum', [AdminUserController::class, 'updateStatus'])->name('users.status');
            Route::put('/kullanicilar/{user:uuid}/roller', [AdminUserController::class, 'updateRoles'])->name('users.roles');
            Route::post('/kullanicilar/{user:uuid}/puan-duzeltme', [AdminUserController::class, 'adjustWallet'])->name('users.adjust');
        });

        Route::middleware('can:payments.manage')->group(function (): void {
            Route::get('/puanlar', [AdminPointController::class, 'index'])->name('points.index');
            Route::put('/puanlar/minimum', [AdminPointController::class, 'updateMinimum'])->name('points.minimum');
            Route::post('/puanlar/paketler', [AdminPointController::class, 'storePackage'])->name('points.packages.store');
            Route::put('/puanlar/paketler/{package}', [AdminPointController::class, 'updatePackage'])->name('points.packages.update');
            Route::delete('/puanlar/paketler/{package}', [AdminPointController::class, 'destroyPackage'])->name('points.packages.destroy');
            Route::get('/odemeler', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('/odemeler/webhooklar', [AdminPaymentController::class, 'webhooks'])->name('payments.webhooks');
            Route::post('/odemeler/{purchase:uuid}/iade', [AdminPaymentController::class, 'refund'])->name('payments.refund');
            Route::post('/odemeler/saglayicilar/{provider}', [AdminPaymentController::class, 'toggleProvider'])->name('payments.providers.toggle');
        });

        Route::middleware('can:exchange_rates.manage')->group(function (): void {
            Route::get('/doviz', [AdminExchangeRateController::class, 'index'])->name('exchange-rates.index');
            Route::post('/doviz/manuel', [AdminExchangeRateController::class, 'manual'])->name('exchange-rates.manual');
            Route::post('/doviz/guncelle', [AdminExchangeRateController::class, 'refresh'])->middleware('throttle:6,1')->name('exchange-rates.refresh');
            Route::post('/doviz/otomatik', [AdminExchangeRateController::class, 'toggleAuto'])->name('exchange-rates.auto');
        });

        Route::middleware('can:commissions.manage')->group(function (): void {
            Route::get('/komisyon', [AdminCommissionController::class, 'index'])->name('commissions.index');
            Route::put('/komisyon/varsayilan', [AdminCommissionController::class, 'updateDefault'])->name('commissions.default');
            Route::post('/komisyon/kurallar', [AdminCommissionController::class, 'storeRule'])->name('commissions.rules.store');
            Route::post('/komisyon/kurallar/{rule}', [AdminCommissionController::class, 'toggleRule'])->name('commissions.rules.toggle');
        });

        Route::middleware('can:categories.manage')->group(function (): void {
            Route::get('/kategoriler', [CatalogSettingsController::class, 'categories'])->name('categories.index');
            Route::post('/kategoriler', [CatalogSettingsController::class, 'storeCategory'])->name('categories.store');
            Route::put('/kategoriler/{category:id}', [CatalogSettingsController::class, 'updateCategory'])->name('categories.update');
        });

        Route::middleware('can:settings.manage')->group(function (): void {
            Route::get('/dosya-formatlari', [CatalogSettingsController::class, 'fileFormats'])->name('file-formats.index');
            Route::post('/dosya-formatlari/{format}', [CatalogSettingsController::class, 'toggleFileFormat'])->name('file-formats.toggle');
        });

        Route::get('/loglar', AdminLogController::class)->middleware('can:logs.view')->name('logs');
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
