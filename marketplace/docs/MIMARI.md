# Sistem Mimarisi

Bu doküman 3D dijital tasarım pazaryerinin mimarisini, para/puan kurallarını ve
temel iş akışlarını açıklar. Veritabanı tabloları için bkz. [VERITABANI.md](VERITABANI.md),
kurulum için bkz. [KURULUM.md](KURULUM.md).

## 1. Genel yapı

```
Tarayıcı (Blade + Tailwind + Alpine.js + Three.js)
        │
        ▼
Laravel 13 (PHP 8.3+)
 ├─ HTTP katmanı      Controller → FormRequest (doğrulama) → Policy (yetki)
 ├─ Servis katmanı    Tüm iş kuralları burada; controller'lar ince tutulur
 │    ├─ Points       PointConverter (1 USD = 100 POINT)
 │    ├─ Wallet       WalletService (çift taraflı puan defteri)
 │    ├─ Currency     ExchangeRateService, CurrencyResolver, PriceFormatter
 │    ├─ Payments     PaymentGateway arayüzü + adaptörler, PointTopUpService
 │    ├─ Sales        PurchaseService, CommissionResolver
 │    ├─ Earnings     CreatorEarningService, PayoutService
 │    ├─ Settings     SettingsService (yönetim paneli ayarları)
 │    └─ Audit        AuditLogger (değiştirilemez denetim kaydı)
 ├─ Kuyruk (Queue)    Dosya tarama, önizleme üretimi, e-posta, bildirim
 └─ Zamanlayıcı       Kur güncelleme (saatlik), kazanç serbest bırakma (saatlik)
        │
        ▼
MySQL 8 / MariaDB 10.4+          Laravel Storage (private disk veya S3 uyumlu)
```

Tek kullanıcı tablosu vardır. "Creator" ayrı bir hesap türü değil, bir **roldür**
(`user_roles`) ve onaylı bir `creator_profiles` kaydı gerektirir. Bu sayede bir
creator aynı hesapla alışveriş de yapabilir.

## 2. Para ve puan kuralları

| Kural | Uygulama |
|---|---|
| 1 USD = 100 POINT | `PointConverter::POINTS_PER_USD`, yapılandırılamaz sabit |
| Float yok | USD **sent** (tamsayı), TRY **kuruş** (tamsayı), puan tamsayı |
| Kur hassasiyeti | `rate_micros` = kur × 1.000.000 (tamsayı). 40,5 TL → `40500000` |
| Ürün fiyatı | Creator USD girer (`3.99`) → `price_cents = 399` → **399 POINT** |
| TL gösterim | `kuruş = round(usd_cent × rate_micros / 1.000.000)` |
| Minimum yükleme | 500 POINT (varsayılan), `system_settings` ile değiştirilebilir |
| Komisyon | Baz puan (bps): `2000` = %20. Yuvarlama farkı platformda kalır |

1 USD = 100 POINT olduğundan **1 sent = 1 puan** olur. Bu nedenle ürün fiyatları
`price_cents` alanında tutulur ve puan karşılığı aynı sayıdır.

### Kur değişimi geçmişi etkilemez

- Puan yükleme siparişi (`point_purchases`) oluşturulurken tahsil edilecek tutar
  (`charge_amount_minor`) ve kullanılan kur (`exchange_rate_micros`) sabitlenir.
- Yüklenen puan miktarı yalnızca USD referansına bağlıdır; kur değişse de değişmez.
- Satış kalemleri (`order_items`) fiyatı, komisyon oranını ve paylaşım tutarlarını
  satış anında kopyalar. Komisyon sonradan değişse de eski satışlar etkilenmez.

## 3. Puan cüzdanı: çift taraflı defter

Her puan hareketi bir `wallet_transactions` kaydı ve **toplamı sıfır olan** en az
iki `wallet_ledger_entries` bacağından oluşur. Kullanıcıya giren her puan bir
sistem hesabından çıkar:

| Sistem hesabı (`wallets.system_code`) | Anlamı |
|---|---|
| `topup_clearing` | Ödeme sağlayıcısından gelen puan yüklemelerinin karşılığı |
| `sales_revenue` | Ürün satın alımlarında harcanan puanlar |
| `promotions` | Promosyon puanları |
| `adjustments` | Yönetici düzeltmeleri |

Örnek: kullanıcı 1500 puanla 700 puanlık ürün alır.

| Bacak | Cüzdan | Tutar | Sonraki bakiye |
|---|---|---:|---:|
| 1 | Kullanıcı | −700 | 800 |
| 2 | sales_revenue | +700 | … |

Güvenlik garantileri (`WalletService::post`):

1. **Transaction:** bakiye güncellemesi ve defter kaydı aynı DB transaction'ındadır.
2. **Satır kilidi:** ilgili cüzdanlar `SELECT … FOR UPDATE` ile, deadlock'u önlemek
   için her zaman id sırasıyla kilitlenir.
3. **İdempotency:** her işlemin benzersiz `idempotency_key` değeri vardır
   (`topup:{uuid}`, `order:{uuid}` …). Aynı anahtarla gelen ikinci çağrı yeni
   kayıt oluşturmaz, ilkini döndürür. Eşzamanlı iki çağrıda unique kısıt devreye girer.
4. **Silme yok:** kayıtlar güncellenmez ve silinmez; hatalar `reverse()` ile ters
   kayıtla düzeltilir.
5. **Negatif bakiye:** kullanıcı cüzdanı yalnızca ödeme itirazı (chargeback) ve
   yönetici ters kaydında eksiye düşebilir; satın almada asla.

`ConcurrentSpendingTest` bu garantileri 8 paralel süreçle gerçek MariaDB üzerinde
doğrular. Kilitler kaldırıldığında test kırılır (mutasyon kontrolü yapıldı).

## 4. Puan yükleme akışı

```
Kullanıcı ─► Paket seçer ─► PointTopUpService::createPurchase
                              • minimum kontrolü
                              • ödeme para birimi = fatura ülkesi (TR → TRY, diğer → USD)
                              • TRY ise güncel kur zorunlu (48 saatten eski kurla ödeme yok)
                              • tutar + kur sabitlenir, durum = pending
          ─► startCheckout ─► sağlayıcı ödeme sayfasına yönlendirme
Sağlayıcı ─► POST /webhooks/payments/{provider}
                              • imza doğrulama (geçersizse 400 + denetim kaydı)
                              • (provider, event_id) unique → aynı olay bir kez işlenir
                              • sipariş satırı kilitlenir, zaten "paid" ise yok sayılır
                              • tutar ve para birimi siparişle birebir eşleşmeli
                              • puan cüzdana yazılır (idempotency: topup:{uuid})
```

Başarısız ödeme puan oluşturmaz. Ödeme itirazı (chargeback) puanları zorunlu
olarak geri alır.

### Ödeme sağlayıcı adaptörleri

`App\Services\Payments\PaymentGateway` arayüzü: `createCheckout`, `parseWebhook`
(imza doğrulama dahil), `refund`. Bir sağlayıcının kullanılabilmesi için:

1. `config/marketplace.php → payments.gateways` altında sınıfı tanımlı olmalı,
2. `payment_providers.is_enabled = true` olmalı (yönetim paneli),
3. API anahtarları `.env` içinde olmalı.

Adaptörler (`app/Services/Payments/Gateways`):

| Sağlayıcı | Ödeme | Bildirimin doğrulanması | İade |
|---|---|---|---|
| **Sandbox** | Site içi test sayfası (gerçek para yok, production'da çalışmaz) | HMAC-SHA256, `X-Sandbox-Signature` | Yerel |
| **iyzico** | Ödeme Formu (Checkout Form), `paymentPageUrl` yönlendirmesi; `IYZWSv2` imzalı istekler | Gelen istekteki `token` ile ödeme sonucu iyzico'dan kimlik doğrulamalı olarak **yeniden sorgulanır**; istek içeriğine güvenilmez. Tarayıcı dönüşü ve sunucu bildirimi aynı olay kimliğiyle tekilleşir | `POST /payment/refund` |
| **PayTR** | iFrame API: token alınır, ödeme formu site içinde iframe ile açılır | `hash = base64(HMAC-SHA256(merchant_oid + salt + status + total_amount))`, yanıt düz metin `OK` | `POST /odeme/iade` |
| **Stripe** | Checkout Session yönlendirmesi, `Idempotency-Key` | `Stripe-Signature` (t + v1), 5 dakika zaman toleransı; itiraz olayı ödeme kaydından eşleştirilir | `POST /v1/refunds` |

Adaptörler sağlayıcıların belgelenmiş API biçimlerine göre yazıldı ve `Http::fake` ile
gerçek imza hesaplarıyla test edildi (`tests/Feature/PaymentGatewaysTest.php`). **Canlı veya
sağlayıcı sandbox hesabıyla henüz denenmedi**; ticari hesap açıldıktan sonra her sağlayıcının
kendi test ortamında uçtan uca deneme yapılmalıdır. Anahtarlar girilmeden sağlayıcı panelden
açılamaz ve webhook adresi 404 döner.

Webhook yanıtı sağlayıcıya göre değişir (`RespondsToWebhook`): PayTR düz metin `OK` ister,
iyzico'da kullanıcının tarayıcısı sonuç sayfasına yönlendirilir.

## 5. Satın alma akışı

`PurchaseService::purchase` tek bir transaction içinde ve **alıcının cüzdan satırı
kilitliyken** çalışır:

1. Ürün yayında mı, alıcı aktif mi, kendi ürünü değil mi?
2. Aynı idempotency anahtarıyla sipariş varsa onu döndür (çift tıklama koruması).
3. Aynı ürün + lisans için aktif hak varsa `AlreadyOwnedException`.
4. Kupon kilitlenir ve doğrulanır, indirim uygulanır. Ürün sayfasındaki "Uygula"
   düğmesi `PurchaseService::previewCoupon` ile indirimli tutarı önceden gösterir;
   önizleme kuponu kullanmaz. Kupon indirimi creator'ın payından düşer (komisyon
   indirimli tutar üzerinden hesaplanır). Kullanılmış kupon silinemez, yalnızca durdurulur.
5. Bakiye yetersizse `InsufficientPointsException` fırlatılır; eksik tutar `shortfall()`
   ile alınır ve arayüz minimum yükleme paketini önerir. Mevcut puanlar silinmez.
6. Komisyon oranı belirlenir: **kampanya > creator > kategori (üst kategorilere doğru) > varsayılan**.
7. Sipariş + kalem oluşur, puan düşülür, indirme hakkı (`purchase_entitlements`)
   tanımlanır, creator kazancı `pending` olarak kaydedilir.

`purchase_entitlements (user_id, product_id, license_id)` unique kısıtı çift satın
almayı veritabanı seviyesinde de engeller.

## 6. Creator kazançları (puan cüzdanından ayrı)

Creator kazancı **USD sent cinsinden parasal hakediştir** ve `creator_earnings` /
`creator_balances` tablolarında tutulur. Puan cüzdanına hiçbir zaman yazılmaz.

```
satış ──► pending ──(14 gün bekleme, iade/itiraz riski)──► available ──► paid
             └──────────── iade ────────────► reversed ◄────────┘
```

- Bekleme süresi `MARKETPLACE_EARNING_HOLD_DAYS` (varsayılan 14 gün).
- Ödenmiş bir kazanç sonradan iade edilirse kullanılabilir bakiye eksiye düşer ve
  sonraki kazançlardan mahsup edilir.
- Ödeme talebi: tutar kullanılabilir bakiyeden **rezerve** edilir. Ret halinde rezerv
  çözülür, ödendi işaretlendiğinde bakiyeden düşülür. **Satıcı kimlik/vergi
  doğrulaması onaylanmadan talep oluşturulamaz.**
- Banka bilgileri (`payout_details`) ve vergi/kimlik numaraları şifreli saklanır.
  Yönetici ekranında maskeli gösterilir (`••••••5678`).

## 7. Döviz kuru servisi

- Varsayılan sağlayıcı: **Frankfurter** (ECB referans kurları, API anahtarı gerekmez).
  `ExchangeRateProvider` arayüzüyle başka sağlayıcı eklenebilir.
- Her başarılı çekim yeni satır olarak saklanır (kur geçmişi). Her deneme
  `exchange_rate_logs` tablosuna yazılır.
- Sağlayıcı hata verirse son başarılı kur kullanılır.
- Önceki kurdan %25'ten fazla sapan değer reddedilir (bozuk veri koruması).
- Yönetici manuel kur girebilir. Bu işlem denetim kaydına yazılır.
- Kur değeri, float hassasiyet kaybını önlemek için ham JSON metninden okunur.

### Görüntüleme ve ödeme para birimi

| Amaç | Öncelik sırası |
|---|---|
| Görüntüleme | kullanıcı tercihi → hesap ülkesi → oturum tercihi → IP ülke ipucu (CF-IPCountry) → USD |
| Ödeme | hesaptaki **fatura ülkesi** (TR → TRY, diğer → USD) |

IP yalnızca ziyaretçiler için bir ipucudur. VPN kullanan bir kullanıcı ödeme
anında IP'ye göre değil hesap bilgisine göre fiyatlandırılır.

## 8. Dosya güvenliği

**Depolama**
- Ücretli dosyalar `private` diskinde (`storage/app/private/products`) veya özel bir S3
  bucket'ında, ürün UUID'si altında **rastgele adlarla** tutulur. Orijinal ad yalnızca
  indirme sırasında gösterilir. `public_html` altında hiçbir ürün dosyası bulunmaz.
- `ProductFile` modelinde `disk` ve `path` alanları gizlidir (JSON çıktısına girmez).
- Herkese açık olan yalnızca görseller ve **3D önizleme dosyasıdır**. Satılan dosyalar
  hiçbir zaman önizleme olarak sunulmaz.
- **Otomatik önizleme** (`GeneratePreviewModel` + `StlDecimator`): creator önizleme
  yüklemezse, taraması temiz çıkan ilk STL dosyasından vertex clustering ile kaba bir
  önizleme üretilir. Model en uzun kenarı 80 hücreye bölünen ızgaraya indirgenir
  (`AUTO_PREVIEW_GRID`), en fazla 60.000 üçgen; hücreden küçük ayrıntılar kaybolduğu
  için önizleme baskı kalitesinde değildir. 200 MB üzerindeki dosyalar atlanır.
  Creator kendi önizlemesini yüklerse otomatik olanın yerini alır.
- **Parçalı yükleme** (`ChunkedUploadController`): ürün düzenleme sayfasında dosyalar
  4 MB'lık parçalar hâlinde gönderilir; sunucunun `upload_max_filesize` sınırı yalnızca
  parça için geçerlidir. Her parça, sunucunun aldığı bayt sayısıyla (`offset`) eşleşmek
  zorundadır ve dosya kilidi altında eklenir; bağlantı koparsa aynı dosya seçilince
  kalınan yerden devam edilir. Tamamlanan dosya normal yüklemeyle aynı kontrollerden geçer.

**Yükleme doğrulaması** (`UploadInspector`, HTTP isteği sırasında)
1. Uzantı, yönetim panelinde aktif olan `file_formats` listesinde olmalı.
2. Boyut sınırı (`MAX_PRODUCT_FILE_MB`, önizleme için `MAX_PREVIEW_FILE_MB`).
3. Sunucunun tespit ettiği MIME türü o biçim için izinli olmalı (tarayıcının bildirdiği türe güvenilmez).
4. İçerik gerçekten o biçimde olmalı: ikili/ASCII STL yapısı, OBJ köşe satırları, `%PDF-`,
   `ISO-10303-21` (STEP), DXF `SECTION`, RAR imzası, 3MF içinde `3D/3dmodel.model`.
5. Metin dosyalarında `<?php` ve `<script` reddedilir. SVG'de betik, olay işleyicisi,
   `foreignObject`, `DOCTYPE/ENTITY` reddedilir.
6. ZIP/3MF arşivleri: en fazla 2000 giriş, dizin dışına çıkan yollar (`../`, mutlak yol)
   yasak, şifreli giriş yasak, çalıştırılabilir uzantılar (exe, bat, sh, php, js…) yasak,
   sıkıştırma oranı 100:1 üzeri reddedilir (sıkıştırma bombası), toplam açılmış boyut ≤ 4 GB.
7. Bir istekteki dosyalardan biri bile geçersizse hiçbiri kaydedilmez.

**Görseller** (`ImageProcessor`): GD ile yeniden kodlanır. Bu işlem EXIF/konum bilgisini
siler, görsele gizlenmiş içerikleri temizler, en uzun kenarı 2400 px ile sınırlar ve
WebP'ye çevirir.

**Zararlı yazılım taraması** (`ScanProductFile` job'u, kuyrukta)
- `FILE_SCANNER=clamav`: ClamAV (`clamdscan` / `clamscan`). Zararlı bulunan dosya
  pasifleştirilir, `security_events` ve denetim kaydına yazılır.
- `FILE_SCANNER=none`: geliştirme ortamında dosyayı temiz sayar. **Production'da dosyalar
  `pending` kalır ve indirilemez**; tarayıcı yapılandırılmadan satış yapılamaz.
- Tarama durumu `clean` olmayan dosya indirilemez ve ürün sayfasında "Güvenlik taraması" olarak görünür.

**İndirme**: oturum → aktif satın alma hakkı (veya ürünün sahibi) → dosya aktif ve
`clean` mi → 10 dakikalık imzalı bağlantı (`DOWNLOAD_LINK_TTL_MINUTES`) → `downloads`
kaydı → dosya özel diskten akıtılır.

**Ürün yaşam döngüsü**: `draft` → (görsel + dosya + açıklama zorunlu) → `pending_review`
→ yönetici onayı (Yönetim paneli → Ürün moderasyonu) → `published`. Reddedilen ürün not
ile `rejected` olur. Yayından kaldırılan ürünü önceden satın alanlar indirmeye devam eder.
Yayındaki üründe sürüm numarası değişirse sürüm notu kaydedilir ve satın alanlara
bildirim gider. Satışı veya indirme hakkı olan ürün silinemez, yalnızca yayından kaldırılır.

## 9. Güvenlik özeti

| Konu | Önlem |
|---|---|
| CSRF | Laravel `PreventRequestForgery`. Webhook'lar hariç tutulur, imza ile korunur |
| Webhook | HMAC imza doğrulama, olay tekilleştirme, tutar/para birimi eşleşmesi |
| Rate limit | `throttle:webhooks` (dakikada 120/IP). Giriş/indirme için ayrı limitler |
| Yetki | Rol + izin tabloları, Gate (noktalı yetenekler izinlerden), Laravel Policy'leri |
| Yönetici 2FA | TOTP (RFC 6238, test vektörleriyle doğrulandı), QR ile kurulum, tek kullanımlık kurtarma kodları (özetlenerek saklanır), tekrar oynatma koruması, 5 denemede kilit, başarısız denemeler güvenlik olayı |
| Yönetici işlemleri | Her POST/PUT/DELETE `admin_logs` tablosuna (şifre/token hariç); kritik işlemler ayrıca `audit_logs` tablosuna eski/yeni değerle |
| Yorum spam'i | E-posta doğrulaması, dakikada 5 / günde 100 yorum, aynı metni 10 dakika içinde tekrar gönderme engeli, en fazla 2 bağlantı, Blade ile kaçışlı çıktı (XSS), 3 farklı kullanıcı şikâyetinde otomatik gizleme |
| Ödeme bilgileri | IBAN/hesap bilgisi ve kimlik/vergi no şifreli; listede maskeli; tam bilgi yalnızca hakediş yetkisiyle ve her görüntüleme denetim kaydına |
| Rol yükseltme | Rolleri yalnızca yönetici değiştirir; kimse kendi yönetici rolünü kaldıramaz veya kendini askıya alamaz; creator rolü yalnızca başvuru onayıyla verilir |
| Mass assignment | Finansal modeller tamamen korumalıdır (`#[Guarded(['*'])]`), yalnızca servisler yazar |
| Hassas veri | Şifreli cast (vergi no, kimlik no, IBAN, 2FA secret) |
| Denetim | Puan düzeltme, kur, komisyon, iade, hakediş ve ayar değişiklikleri `audit_logs` tablosuna |
| Geliştirme sıkılığı | Production dışında lazy loading ve sessizce atılan alanlar hata verir |
| HTTP başlıkları | `SecurityHeaders` middleware: istek başına nonce'lu CSP (production'da uygulanır, diğer ortamlarda yalnızca raporlanır), `frame-ancestors 'none'`, `object-src 'none'`, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`; HTTPS üzerinde production'da HSTS. Alpine.js ifadeleri için `unsafe-eval` gerekir; satır içi olay işleyicisi kullanılmaz. Video yalnızca youtube-nocookie ve Vimeo'dan gömülebilir |
| Oturum çerezi | `HttpOnly`, `SameSite=Lax`; production'da varsayılan olarak `Secure` |
| Bağımlılıklar | `composer audit` ve `npm audit` temiz; CI her değişiklikte tekrar denetler |
| Önbellek | `cache.serializable_classes` kapalı: önbellekten nesne geri yüklenmez (unserialize saldırılarına karşı); önbellekte yalnızca diziler tutulur |

### Performans

- **Görseller:** yüklenen her ürün görseli WebP'ye çevrilir ve 480/960 px küçük boyutları
  üretilir (`ImageProcessor::PRODUCT_VARIANTS`). Kartlar `srcset` + `sizes` ile ekran
  genişliğine uygun dosyayı, galeri küçük resimleri 480 px'i yükler; tüm liste görselleri
  `loading="lazy"`. Var olan görseller için `php artisan marketplace:image-variants`.
  AVIF kullanılmıyor: GD'nin AVIF desteği çoğu cPanel sunucusunda yok ve kodlama yavaş.
- **Ana sayfa:** vitrin sıralamaları ve kategori sayıları 5 dakika önbelleğe alınır
  (`CatalogCache`); ürün yayına girip çıktığında veya öne çıkarıldığında önbellek
  hemen temizlenir. Önbellekteki kimlikler yüklenirken yayında olup olmadıkları tekrar kontrol edilir.
- **Ön yüz:** Three.js ve QR kod kütüphanesi yalnızca gerektiğinde ayrı parça olarak yüklenir;
  ana JS paketi küçük kalır. CSS/JS bir CDN'den sunulacaksa `ASSET_URL` yeterlidir.
- **Sorgular:** listeler ilişkileri önceden yükler (N+1 yok; production dışında lazy loading hata verir).

### Sürekli entegrasyon

`.github/workflows/marketplace.yml`: her push/PR'da Pint stil kontrolü, testler (SQLite ve
MariaDB 10.11), gerçek süreçlerle eşzamanlı harcama testleri, `composer audit`, ön yüz
derlemesi ve `npm audit`.

## 10. Faz durumu

| Faz | Durum |
|---|---|
| 1. Proje kurulumu | ✅ Laravel 13, yapılandırma, private disk |
| 2. Veritabanı mimarisi | ✅ 15 migration, 71 tablo (Laravel sistem tabloları dahil), SQLite ve MariaDB 10.11 üzerinde doğrulandı |
| 3. Kayıt ve giriş | ✅ Kayıt, giriş (5 hatalı denemede kilit), e-posta doğrulama, şifre sıfırlama, askıya alınmış hesap engeli |
| 4. Creator hesabı | ✅ Ayrı kayıt sayfası, mevcut hesabı yükseltme, başvuru durumu, yönetim panelinden onay |
| 5. Tasarım sistemi | ✅ "Katman" kimliği, renk/tipografi tokenları, açık/koyu tema (tercih saklanır), mobil menü |
| 6. Ana sayfa | ✅ Tüm bölümler, katalog boşken profesyonel boş durum ekranı |
| 7. Kategoriler | ✅ Liste ve kategori sayfası (alt kategoriler dahil filtreleme), kategori görseli, TR/EN açıklama ve SEO alanları |
| 8. Ürün yükleme | ✅ Görseller, indirilebilir dosyalar, 3D önizleme, lisanslar, baskı bilgileri, SEO; doğrulama + tarama; inceleme akışı; parçalı ve devam ettirilebilir büyük dosya yükleme; STL'den otomatik kaba önizleme; sürüm notu; satışsız ürünü silme |
| 9. Ürün detay | ✅ Görsel galerisi + tam ekran, 3D önizleme, video, lisans seçimi, kupon önizlemeli satın alma, teknik bilgiler ve baskı önerileri, güncelleme geçmişi, mağaza takibi, değerlendirme ve yorumlar, benzer modeller |
| 10. 3D görüntüleyici | ✅ Three.js; STL/OBJ/3MF; döndürme, yakınlaştırma, ışık, arka plan, ortalama, tabla, tam ekran; sekme açılınca tembel yüklenir |
| 11. Puan cüzdanı | ✅ Servis + cüzdan sayfası (bakiye, toplamlar, işlem geçmişi) |
| 12. Döviz kuru servisi | ✅ Sağlayıcı, geçmiş, manuel kur, sapma koruması, zamanlayıcı |
| 13. Puan yükleme | ✅ Paket seçimi → sağlayıcı → imzalı webhook → sonuç sayfası (sandbox ile uçtan uca) |
| 15. Satın alma | ✅ Onay adımı, yetersiz bakiyede eksik puanla yükleme sayfasına yönlendirme |
| 16. Güvenli indirme | ✅ Hak kontrolü, 10 dakikalık imzalı bağlantı, tarama durumu kontrolü, indirme kaydı |
| 17. Creator paneli | ✅ Genel bakış (8 kart + 30 günlük satış grafiği), ürünlerim, ürün formu, istatistikler (günlük/haftalık/aylık satış, gelir, indirme, takipçi; ürün bazında dönüşüm), satışlar, gelirler, hakedişler, kuponlar (yüzde/sabit, ürün, limit, tarih), yorumlar, takipçiler, mağaza ayarları |
| 18–19. Komisyon, hakediş, ödeme talebi | ✅ Satıcı doğrulama (T.C. kimlik no sağlaması, şifreli saklama, belge), ülkeye göre ödeme yöntemi (TR: IBAN/TL, diğer: SWIFT/Wise/Payoneer USD), IBAN mod-97 doğrulaması, rezerv; yönetici: doğrulama onayı, talep onay/ret, TL ödemede kur kaydı, hesap bilgisi görüntüleme denetimi |
| 20. Favoriler ve koleksiyonlar | ✅ Favori, beğeni (sayaç tutarlı), koleksiyon (herkese açık/gizli, ürün sayfasından ekleme), ana sayfada koleksiyonlar |
| 21. Takip sistemi | ✅ Mağaza takibi, takip ettiklerim, creator takipçi listesi |
| 22. Yorum ve değerlendirme | ✅ Yıldızlı değerlendirme yalnızca ürünü edinenler; satın alanlara "doğrulanmış satın alma" rozeti; creator yanıtı; yorum + yanıt; spam koruması; 3 şikâyette otomatik gizleme; içerik/telif şikâyeti (ziyaretçi e-postayla); yönetici moderasyonu |
| 25. Yönetim paneli | ✅ Dashboard (11 metrik + bekleyen işler), creator başvuruları, ürün moderasyonu (onay/ret/yayından kaldırma/öne çıkarma), kullanıcılar (arama, askıya alma, rol, puan düzeltme, işlem ve satın alma geçmişi), puan paketleri + minimum yükleme + ekonomi raporu, ödemeler (iade, sağlayıcılar, webhook kayıtları, itirazlar), döviz kuru (manuel/otomatik/geçmiş/sağlayıcı kayıtları), komisyon (varsayılan + creator/kategori/kampanya kuralları), kategoriler (görsel, TR/EN SEO), dosya formatları, hakedişler ve satıcı doğrulamaları, içerik/SEO, sistem ayarları, destek, loglar |
| 23. Arama ve filtreleme | ✅ Metin, kategori, etiket, ücretli/ücretsiz, dosya formatı, ticari lisans, baskı teknolojisi, USD fiyat aralığı; 8 sıralama; başlıkta yazarken ürün/tasarımcı/kategori/etiket önerileri (klavyeyle gezinme) |
| 27. Güvenlik sertleştirme | ✅ Nonce'lu CSP, HSTS, çerçeveleme/MIME/referrer/izin başlıkları, güvenli oturum çerezi, bağımlılık denetimi (bkz. §9) |
| 28. Performans | ✅ Görsel küçük boyutları + srcset + lazy loading, ana sayfa önbelleği (anında temizlenir), tembel yüklenen 3D/QR kütüphaneleri, CDN desteği |
| 29. Otomatik testler ve CI | ✅ 229 test + 2 eşzamanlılık testi (SQLite ve MariaDB); GitHub Actions iş akışı |
| 24. Bildirimler | ✅ 17 tür; site içi + kategori bazlı e-posta tercihi; yönetici uyarıları izne göre |
| 26. SEO ve çok dillilik | ✅ Sitemap (bölümlü), robots.txt, JSON-LD (Product, BreadcrumbList, WebSite+SearchAction, Organization, ProfilePage), og:image, hreflang + ?lang=en, sayfa bazında meta ayarları; Türkçe/İngilizce arayüz (yönetim paneli Türkçe), çeviri kapsamı testi |
| — İçerik, destek, KVKK | ✅ Sayfa/blog/SSS/banner yönetimi, yasal metin taslakları (TR+EN, hukuki inceleme gerekli), sistem/SMTP/bakım modu ayarları, destek talepleri ve puan iadesi talebi, hesap silme (anonimleştirme) |
| 14. Ödeme sağlayıcı entegrasyonları | ✅ iyzico (Ödeme Formu), PayTR (iFrame), Stripe (Checkout) adaptörleri: ödeme başlatma, bildirim doğrulama, iade, itiraz; anahtarlar girilene kadar kapalı (bkz. §4). Canlı hesapla deneme ticari hesap açıldıktan sonra yapılacak |
| — REST API | ✅ `/api/v1`: katalog, tasarımcı, kur, belirteçle oturum, profil, cüzdan, kütüphane, imzalı indirme bağlantısı, idempotent satın alma ([API.md](API.md)) |
| — Kılavuzlar | ✅ [Kullanıcı](KULLANICI_KILAVUZU.md) ve [yönetici](YONETICI_KILAVUZU.md) kılavuzları |
| 30. cPanel dağıtımı | ⏳ Ayrıntılı dağıtım rehberi (KURULUM.md §C şu an özet) |

## 11. Karar bekleyen noktalar

1. **Marka adı ve logo:** "Katman" seçildi. Marka tescili ve alan adı uygunluğu henüz kontrol edilmedi.
2. **Hukuki uygunluk (puan sistemi):** Puanların ödeme hizmetleri / elektronik para
   mevzuatı, tüketici hakları (cayma hakkı, kullanılmamış puan iadesi) ve vergi
   açısından durumu üretim öncesinde bir uzmana sorulmalı. Kod, puan transferini ve
   nakde çevirmeyi hiç desteklemez; kullanılmamış puan iadesi yalnızca harcanmamış
   bakiye kadar yapılabilir.
3. **Ödeme sağlayıcıları:** iyzico/PayTR (TRY) ve Stripe (USD) için ticari hesap
   gerekli. Stripe, Türkiye'de yerleşik şirketlere doğrudan hesap açmıyor. Uluslararası
   tahsilat için Paddle, Lemon Squeezy (Merchant of Record) veya yurt dışı şirket
   seçenekleri değerlendirilmeli. Merchant of Record modeli KDV/VAT yükünü de üstlenir.
4. **Creator ödemeleri:** TRY'ye çevrim kuru, ödeme günü ve yöntemleri (IBAN, Wise,
   Payoneer) netleştirilmeli.
5. **Zararlı dosya taraması:** cPanel'de ClamAV çoğu zaman yoktur. Harici tarama API'si
   (ör. VirusTotal, Cloudmersive) veya ClamAV'lı ayrı bir sunucu seçilmeli.
6. **Ödeme sağlayıcılarına gönderilen alıcı bilgileri:** iyzico ve PayTR, dijital ürünlerde de
   adres, şehir, telefon ve (iyzico) T.C. kimlik no ister. Platform bu bilgileri kullanıcıdan
   toplamadığı için `.env` içindeki `PAYMENT_BUYER_*` değerleri gönderilir (varsayılanlar:
   "Dijital teslimat - fiziksel gönderim yok", İstanbul, `05000000000`, `11111111111`). Bu
   uygulama sağlayıcıyla sözleşme sırasında onaylatılmalı; sağlayıcı gerçek bilgi isterse
   ödeme sayfasından önce kullanıcıdan fatura bilgisi toplayan bir adım eklenmelidir.
