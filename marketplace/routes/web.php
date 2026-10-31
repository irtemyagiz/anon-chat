<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Admin\CatalogSettingsController;
use App\Http\Controllers\Admin\CommissionController as AdminCommissionController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CreatorController as AdminCreatorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ExchangeRateController as AdminExchangeRateController;
use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PayoutController as AdminPayoutController;
use App\Http\Controllers\Admin\PointController as AdminPointController;
use App\Http\Controllers\Admin\ProductModerationController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Admin\TwoFactorController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\Creator\CommunityController as CreatorCommunityController;
use App\Http\Controllers\Creator\DashboardController as CreatorDashboardController;
use App\Http\Controllers\Creator\PayoutController as CreatorPayoutController;
use App\Http\Controllers\Creator\ProductActionController as CreatorProductActionController;
use App\Http\Controllers\Creator\ProductController as CreatorProductController;
use App\Http\Controllers\Creator\SalesController as CreatorSalesController;
use App\Http\Controllers\Creator\StoreSettingsController;
use App\Http\Controllers\CreatorApplicationController;
use App\Http\Controllers\DesignerController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SandboxCheckoutController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SupportController;
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
Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemaps/{section}-{page}.xml', [SeoController::class, 'sitemapSection'])->where(['section' => '[a-z]+', 'page' => '[0-9]+'])->name('sitemap.section');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/modeller', [ProductController::class, 'index'])->name('products.index');
Route::get('/model/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/kategoriler', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/kategori/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tasarimcilar', [DesignerController::class, 'index'])->name('designers.index');
Route::get('/magaza/{profile}', [DesignerController::class, 'show'])->name('designers.show');
Route::get('/tasarim-yukle', [CreatorApplicationController::class, 'upload'])->name('upload');
Route::get('/koleksiyon/{username}/{slug}', [CommunityController::class, 'showCollection'])->name('collections.show');
Route::get('/sayfa/{slug}', [PageController::class, 'show'])->name('pages.show');
Route::get('/blog', [PageController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [PageController::class, 'post'])->name('blog.show');
Route::get('/sss', [PageController::class, 'faq'])->name('faq');
Route::post('/model/{product}/sikayet', [ReviewController::class, 'reportProduct'])->middleware('throttle:5,10')->name('products.report');
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
    | Hesap ve topluluk
    */
    Route::prefix('hesabim')->name('account.')->group(function (): void {
        Route::get('/', [AccountController::class, 'library'])->name('library');
        Route::get('/favoriler', [AccountController::class, 'favorites'])->name('favorites');
        Route::get('/koleksiyonlar', [AccountController::class, 'collections'])->name('collections');
        Route::get('/takip', [AccountController::class, 'following'])->name('following');
        Route::get('/ayarlar', [AccountController::class, 'edit'])->name('settings');
        Route::put('/ayarlar', [AccountController::class, 'update'])->name('settings.update');
        Route::put('/ayarlar/sifre', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('settings.password');
        Route::get('/koleksiyon-secenekleri/{product}', [AccountController::class, 'collectionOptions'])->name('collection-options');
        Route::get('/bildirimler', [NotificationController::class, 'index'])->name('notifications');
        Route::get('/bildirimler/{id}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('/bildirimler/tumunu-oku', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::put('/bildirim-tercihleri', [NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::delete('/', [AccountController::class, 'destroy'])->middleware('throttle:5,1')->name('destroy');
    });

    Route::prefix('destek')->name('support.')->group(function (): void {
        Route::get('/', [SupportController::class, 'index'])->name('index');
        Route::get('/yeni', [SupportController::class, 'create'])->name('create');
        Route::post('/', [SupportController::class, 'store'])->middleware('throttle:5,10')->name('store');
        Route::get('/ek/{message}', [SupportController::class, 'attachment'])->name('attachment');
        Route::get('/{ticket}', [SupportController::class, 'show'])->name('show');
        Route::post('/{ticket}/yanit', [SupportController::class, 'reply'])->middleware('throttle:10,1')->name('reply');
        Route::post('/{ticket}/kapat', [SupportController::class, 'close'])->name('close');
    });

    Route::middleware('throttle:60,1')->group(function (): void {
        Route::post('/model/{product}/favori', [CommunityController::class, 'favorite'])->name('products.favorite');
        Route::post('/model/{product}/begen', [CommunityController::class, 'like'])->name('products.like');
        Route::post('/magaza/{profile}/takip', [CommunityController::class, 'follow'])->name('designers.follow');
        Route::post('/koleksiyonlar', [CommunityController::class, 'storeCollection'])->name('collections.store');
        Route::put('/koleksiyonlar/{collection}', [CommunityController::class, 'updateCollection'])->name('collections.update');
        Route::delete('/koleksiyonlar/{collection}', [CommunityController::class, 'destroyCollection'])->name('collections.destroy');
        Route::post('/koleksiyonlar/{collection}/model/{product}', [CommunityController::class, 'toggleInCollection'])->name('collections.toggle');
    });

    Route::middleware('verified')->group(function (): void {
        Route::post('/model/{product}/degerlendirme', [ReviewController::class, 'storeReview'])->middleware('throttle:10,1')->name('products.review');
        Route::post('/model/{product}/yorum', [ReviewController::class, 'storeComment'])->middleware('throttle:comments')->name('products.comment');
        Route::delete('/yorum/{comment}', [ReviewController::class, 'destroyComment'])->name('comments.destroy');
        Route::post('/yorum/{comment}/sikayet', [ReviewController::class, 'reportComment'])->middleware('throttle:20,1')->name('comments.report');
        Route::post('/degerlendirme/{review}/yanit', [ReviewController::class, 'reply'])->middleware('throttle:20,1')->name('reviews.reply');
    });

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
        Route::get('/hakedisler', [CreatorPayoutController::class, 'index'])->name('payouts');
        Route::post('/hakedisler/dogrulama', [CreatorPayoutController::class, 'verify'])->middleware('throttle:5,1')->name('payouts.verify');
        Route::post('/hakedisler', [CreatorPayoutController::class, 'store'])->middleware('throttle:10,1')->name('payouts.store');
        Route::get('/yorumlar', [CreatorCommunityController::class, 'reviews'])->name('reviews');
        Route::get('/takipciler', [CreatorCommunityController::class, 'followers'])->name('followers');
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

        Route::middleware('can:comments.moderate')->group(function (): void {
            Route::get('/yorumlar', [ModerationController::class, 'comments'])->name('moderation.comments');
            Route::put('/yorumlar/{comment}', [ModerationController::class, 'updateComment'])->name('moderation.comments.update');
            Route::put('/degerlendirmeler/{review}', [ModerationController::class, 'updateReview'])->name('moderation.reviews.update');
        });

        Route::middleware('can:products.moderate')->group(function (): void {
            Route::get('/sikayetler', [ModerationController::class, 'reports'])->name('moderation.reports');
            Route::put('/sikayetler/{report}', [ModerationController::class, 'resolveReport'])->name('moderation.reports.resolve');
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

        Route::middleware('can:payouts.manage')->group(function (): void {
            Route::get('/hakedisler', [AdminPayoutController::class, 'index'])->name('payouts.index');
            Route::get('/hakedisler/dogrulamalar', [AdminPayoutController::class, 'verifications'])->name('payouts.verifications');
            Route::put('/hakedisler/dogrulamalar/{verification}', [AdminPayoutController::class, 'reviewVerification'])->name('payouts.verifications.review');
            Route::get('/hakedisler/dogrulamalar/{verification}/belge', [AdminPayoutController::class, 'document'])->name('payouts.verifications.document');
            Route::get('/hakedisler/{payoutRequest:uuid}', [AdminPayoutController::class, 'show'])->name('payouts.show');
            Route::post('/hakedisler/{payoutRequest:uuid}/karar', [AdminPayoutController::class, 'decide'])->name('payouts.decide');
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

        Route::middleware('can:support.manage')->group(function (): void {
            Route::get('/destek', [AdminSupportController::class, 'index'])->name('support.index');
            Route::get('/destek/{ticket}', [AdminSupportController::class, 'show'])->name('support.show');
            Route::post('/destek/{ticket}/yanit', [AdminSupportController::class, 'reply'])->name('support.reply');
            Route::post('/destek/{ticket}/kapat', [AdminSupportController::class, 'close'])->name('support.close');
        });

        Route::middleware('can:content.manage')->group(function (): void {
            Route::get('/icerik/sayfalar', [ContentController::class, 'pages'])->name('content.pages');
            Route::get('/icerik/sayfalar/yeni', [ContentController::class, 'editPage'])->name('content.pages.create');
            Route::post('/icerik/sayfalar', [ContentController::class, 'savePage'])->name('content.pages.store');
            Route::get('/icerik/sayfalar/{page}', [ContentController::class, 'editPage'])->name('content.pages.edit');
            Route::put('/icerik/sayfalar/{page}', [ContentController::class, 'savePage'])->name('content.pages.update');
            Route::delete('/icerik/sayfalar/{page}', [ContentController::class, 'destroyPage'])->name('content.pages.destroy');
            Route::get('/icerik/sss', [ContentController::class, 'faqs'])->name('content.faqs');
            Route::post('/icerik/sss', [ContentController::class, 'saveFaq'])->name('content.faqs.store');
            Route::put('/icerik/sss/{faq}', [ContentController::class, 'saveFaq'])->name('content.faqs.update');
            Route::delete('/icerik/sss/{faq}', [ContentController::class, 'destroyFaq'])->name('content.faqs.destroy');
            Route::get('/icerik/bannerlar', [ContentController::class, 'banners'])->name('content.banners');
            Route::post('/icerik/bannerlar', [ContentController::class, 'saveBanner'])->name('content.banners.store');
            Route::put('/icerik/bannerlar/{banner}', [ContentController::class, 'saveBanner'])->name('content.banners.update');
            Route::delete('/icerik/bannerlar/{banner}', [ContentController::class, 'destroyBanner'])->name('content.banners.destroy');
            Route::get('/seo', [AdminSettingsController::class, 'seo'])->name('seo');
            Route::put('/seo', [AdminSettingsController::class, 'updateSeo'])->name('seo.update');
        });

        Route::middleware('can:settings.manage')->group(function (): void {
            Route::get('/ayarlar', [AdminSettingsController::class, 'edit'])->name('settings');
            Route::put('/ayarlar', [AdminSettingsController::class, 'update'])->name('settings.update');
            Route::put('/ayarlar/eposta', [AdminSettingsController::class, 'updateMail'])->name('settings.mail');
            Route::post('/ayarlar/eposta/test', [AdminSettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.mail.test');
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
