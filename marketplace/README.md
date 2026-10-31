# Katman — 3D Dijital Tasarım Pazaryeri

Creator ekonomisi ve puan cüzdanı tabanlı, çoklu satıcılı 3D dijital tasarım pazaryeri.
Laravel 13 · PHP 8.3+ · MySQL 8 / MariaDB · Blade + Tailwind + Alpine.js + Three.js.

> Marka: **Katman** — 3D baskının katman katman oluşumundan. Logo: kaydırılmış üç katman.

## Temel kurallar

- **1 USD = 100 POINT** (sabit). Minimum yükleme 500 POINT (yönetim panelinden değiştirilebilir).
- Tüm dijital ürün satın alımları puanla yapılır. Creator fiyatı USD olarak girer.
- Türkiye'deki kullanıcılar TL, diğerleri USD fiyat görür. TL, güncel USD/TRY kurundan hesaplanır.
- Ödeme doğrulanmadan puan yüklenmez. Kur değişimi yüklenen puanı ve geçmiş ödemeleri etkilemez.
- Creator kazançları puan cüzdanından tamamen ayrı tutulan, USD bazlı hakedişlerdir.
- Ücretli dosyalar satın alınmadan indirilemez ve hiçbir zaman public dizinde tutulmaz.
- İlk kurulumda ürün, kullanıcı veya satış verisi yoktur.

## Dokümanlar

| Doküman | İçerik |
|---|---|
| [docs/MIMARI.md](docs/MIMARI.md) | Mimari, puan defteri, ödeme/satın alma/hakediş akışları, güvenlik, faz durumu, karar bekleyen noktalar |
| [docs/VERITABANI.md](docs/VERITABANI.md) | Tablo tablo veritabanı şeması ve kısıtlar |
| [docs/KURULUM.md](docs/KURULUM.md) | XAMPP (Windows) yerel kurulum, Apache sanal sunucu, cPanel özeti |
| [docs/API.md](docs/API.md) | REST API v1: kimlik doğrulama, uçlar, örnekler, hata kodları |
| [docs/KULLANICI_KILAVUZU.md](docs/KULLANICI_KILAVUZU.md) | Alıcı ve tasarımcı kullanım kılavuzu |
| [docs/YONETICI_KILAVUZU.md](docs/YONETICI_KILAVUZU.md) | Yönetim paneli kılavuzu |

## Hızlı başlangıç

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Testler

```bash
php artisan test
```

Puan dönüşümü, cüzdan defteri, puan yükleme ve webhook güvenliği, döviz kuru,
satın alma, komisyon, iade, creator hakedişi, ödeme talepleri, kayıt/giriş, creator
başvurusu, puan yükleme akışı, güvenli indirme, creator paneli ve dosya yükleme güvenliği
(sahte uzantı, zararlı arşiv, SVG betiği, zararlı dosya taraması), yönetim paneli, yönetici
2FA, rol yükseltme koruması, hakediş akışı (IBAN/TCKN doğrulaması) ve topluluk özellikleri
(değerlendirme, yorum spam koruması, koleksiyon gizliliği), bildirimler, içerik ve destek,
SEO ve çeviri kapsamı, arama ve filtreler, kuponlar, creator istatistikleri, parçalı
yükleme, otomatik önizleme, güvenlik başlıkları, ana sayfa önbelleği, ödeme sağlayıcı
adaptörleri (iyzico, PayTR, Stripe) ve REST API için 229 test bulunur.
Her değişiklikte GitHub Actions bu testleri SQLite ve MariaDB üzerinde çalıştırır.
Ayrıca MySQL/MariaDB gerektiren 2 eşzamanlılık testi vardır (`--group=concurrency`).
