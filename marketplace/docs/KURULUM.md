# Kurulum

## A. Windows + XAMPP (yerel geliştirme)

### 0. Ön koşullar

| Gereksinim | Kontrol |
|---|---|
| **PHP 8.3 veya üstü** | `C:\xampp\php\php.exe -v` |
| Composer | `composer -V` ([getcomposer.org](https://getcomposer.org/download/)) |
| Git | `git --version` ([git-scm.com](https://git-scm.com/download/win)) |
| XAMPP MySQL/MariaDB | XAMPP Control Panel → MySQL **Start** |

> **Önemli:** XAMPP'ın resmî paketleri uzun süre PHP 8.2 ile dağıtıldı. Laravel 13
> PHP 8.3+ ister. `php -v` 8.2 gösteriyorsa iki yol var:
> 1. [windows.php.net](https://windows.php.net/download/) adresinden **PHP 8.3/8.4 Thread Safe x64**
>    zip'ini indirip `C:\php83` klasörüne açın ve aşağıdaki komutlarda `php` yerine
>    `C:\php83\php.exe` kullanın (MySQL için XAMPP'ı kullanmaya devam edersiniz).
> 2. PHP 8.3+ içeren güncel bir XAMPP sürümü kurun.

`php.ini` dosyasında (`C:\xampp\php\php.ini`) yükleme sınırlarını artırın ve Apache'yi
yeniden başlatın. Ürün düzenleme sayfası büyük dosyaları 4 MB'lık parçalarla gönderdiği
için oradaki yüklemelerde bu sınırlar sorun olmaz; yeni ürün formundaki doğrudan yükleme
ise bu değerlere bağlıdır:

```
upload_max_filesize = 512M
post_max_size = 520M
max_file_uploads = 40
memory_limit = 512M
```

Aynı dosyada şu eklentilerin başındaki `;` işaretini kaldırın:

```
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=zip
extension=gd
```

### 1. Kodu indirin

Kodu `htdocs` **dışında** bir klasöre klonlamanız önerilir. `htdocs` içindeki her dosya
Apache tarafından web'e açılır; buna `.env` dosyası da dahildir.

```bash
cd C:\projeler
```
```bash
git clone -b claude/serene-darwin-vk39lo https://github.com/irtemyagiz/anon-chat.git
```
```bash
cd anon-chat\marketplace
```

`C:\xampp\htdocs` içine klonlamak isterseniz, siteyi aşağıdaki **B. Apache sanal
sunucu** adımıyla yalnızca `public` klasörünü açarak çalıştırın.

### 2. Bağımlılıklar ve ortam dosyası

```bash
composer install
```
```bash
copy .env.example .env
```
```bash
php artisan key:generate
```

`.env` dosyasını açın ve `SANDBOX_PAYMENT_SECRET` için rastgele bir değer girin:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

### 3. Veritabanı

phpMyAdmin'i açın (`http://localhost/phpmyadmin`) ve **marketplace** adında,
karşılaştırma ayarı `utf8mb4_unicode_ci` olan bir veritabanı oluşturun.
`.env` içindeki XAMPP varsayılanları (`root`, şifre boş) hazırdır.

```bash
php artisan migrate --seed
```
```bash
php artisan storage:link
```

Seed işlemi yalnızca referans verileri yükler: roller, 17 kategori, lisanslar, dosya
formatları, 5 puan paketi ve ödeme sağlayıcı kayıtları. **Ürün, kullanıcı veya
satış verisi oluşturmaz.**

### 4. Çalıştırma

```bash
php artisan serve
```

Adres: `http://localhost:8000`. Döviz kurunu ilk kez çekmek için:

```bash
php artisan marketplace:refresh-exchange-rates --force
```

Zamanlanmış görevler (kur güncelleme, kazanç serbest bırakma) için ayrı bir
terminalde şunu açık bırakın:

```bash
php artisan schedule:work
```

Derlenmiş CSS/JS dosyaları (`public/build`) depoda bulunur; **Node.js kurmanız gerekmez.**
Tasarımı değiştirecekseniz Node.js 20+ kurup `npm install` ve `npm run build` çalıştırın.

### 5. İlk kullanım

1. `http://localhost:8000/kayit` adresinden hesap açın.
2. Yerel ortamda e-posta gönderilmez (`MAIL_MAILER=log`). Doğrulama sayfasında
   **"E-postamı doğrula"** bağlantısı görünür; ona tıklayın.
3. Kendinizi yönetici yapın:
   ```bash
   php artisan marketplace:make-admin sizin@eposta.com
   ```
   Menüde **Yönetim paneli** görünür. İlk girişte telefonunuzdaki doğrulama uygulamasıyla
   (Google/Microsoft Authenticator vb.) QR kodu okutup iki aşamalı doğrulamayı kurmanız
   istenir. Kurtarma kodlarını saklayın.
4. Puan yükleme: Cüzdan → Puan yükle → paket seçin → **Sandbox (Test)** → "Ödemeyi başarılı tamamla".
   Gerçek para çekilmez; puanlar imzalı test webhook'u ile yüklenir.
5. Creator başvuruları **Yönetim paneli → Creator başvuruları** sayfasından onaylanır.
6. Onaylı creator, menüdeki **Tasarım Yükle** ile ürün ekler ve **İncelemeye gönder** der.
   Ürün **Yönetim paneli → Ürün moderasyonu** sayfasından onaylanır.

   Aynı işlemler komut satırından da yapılabilir:
   ```bash
   php artisan marketplace:approve-creator creator@eposta.com
   ```
   ```bash
   php artisan marketplace:approve-product urun-seo-adresi
   ```

> Türkiye'den kayıt olan kullanıcılar TL ile öder. TL fiyat için kur gerekir:
> `php artisan marketplace:refresh-exchange-rates --force` komutu internetten
> güncel kuru çeker. Kur alınamazsa TL ile ödeme güvenlik gereği engellenir.

### 6. Testler

```bash
php artisan test
```

Testler varsayılan olarak bellek içi SQLite kullanır (`pdo_sqlite` gerekli).
Gerçek eşzamanlılık testleri MySQL/MariaDB ve `pcntl` eklentisi ister. `pcntl`
Windows'ta bulunmadığından bu testler yalnızca Linux/macOS veya CI üzerinde çalışır:

```bash
DB_CONNECTION=mariadb DB_DATABASE=marketplace_test DB_USERNAME=root php artisan test --group=concurrency
```

## B. Apache sanal sunucu (isteğe bağlı)

`C:\xampp\apache\conf\extra\httpd-vhosts.conf` dosyasına ekleyin. Yolu kendi
klasörünüze göre değiştirin:

```apache
<VirtualHost *:80>
    ServerName marketplace.test
    DocumentRoot "C:/projeler/anon-chat/marketplace/public"
    <Directory "C:/projeler/anon-chat/marketplace/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`C:\Windows\System32\drivers\etc\hosts` dosyasına (yönetici olarak) `127.0.0.1 marketplace.test`
satırını ekleyin, Apache'yi yeniden başlatın ve `.env` içinde
`APP_URL=http://marketplace.test` yapın.

## C. cPanel (üretim) — özet

Ayrıntılı rehber Faz 30'da hazırlanacak. Temel kurallar:

1. **MultiPHP Manager** üzerinden PHP 8.3+ seçin. Gerekli eklentiler: pdo_mysql,
   mbstring, openssl, fileinfo, zip, gd, curl.
2. Uygulamayı `public_html` **dışına** yükleyin (ör. `/home/kullanici/marketplace`).
   Domainin belge kökünü `marketplace/public` yapın. Bu mümkün değilse
   `public_html` yerine `public` klasörüne sembolik bağlantı kullanın.
   `.env`, `storage` ve kaynak kod hiçbir zaman web'den erişilebilir olmamalıdır.
3. `.env` dosyasında `APP_ENV=production` ve `APP_DEBUG=false` olmalıdır.
   `SANDBOX_PAYMENT_SECRET` boş bırakılmalıdır (sandbox production'da zaten çalışmaz).
4. Cron Jobs (her dakika):
   ```
   * * * * * /usr/local/bin/php /home/kullanici/marketplace/artisan schedule:run >> /dev/null 2>&1
   ```
5. Kuyruk: `.env` içinde `QUEUE_CONNECTION=database` yapın. cPanel'de kalıcı worker
   çalıştırılamıyorsa her dakika cron ile
   `php artisan queue:work --stop-when-empty --max-time=55` çalıştırın. Büyük STL
   işleme için harici kuyruk veya VPS gerekebilir.
6. Zararlı dosya taraması: `FILE_SCANNER=clamav`. Sunucuda ClamAV yoksa (çoğu paylaşımlı
   cPanel'de yoktur) dosyalar indirilemez durumda kalır; ClamAV'lı bir sunucu veya harici
   tarama servisi gerekir (bkz. MIMARI.md §11).
7. SSL (AutoSSL / Let's Encrypt) etkin olmalı. Ödeme sağlayıcıları webhook'ları
   yalnızca HTTPS adreslere gönderir. Production'da oturum çerezi yalnızca HTTPS ile
   gönderilir ve HSTS başlığı eklenir; site HTTP üzerinden açılırsa giriş yapılamaz.
   Görseller veya CSS/JS bir CDN'den sunulacaksa `ASSET_URL` girin (CSP'ye otomatik eklenir).
8. Kurulum sonrası çalıştırılacak komutlar:
   ```bash
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
