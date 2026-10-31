# Yönetici Kılavuzu

Yönetim paneli: `/yonetim`. Panel Türkçedir. Kurulum için [KURULUM.md](KURULUM.md),
teknik ayrıntılar için [MIMARI.md](MIMARI.md).

## 1. Erişim ve güvenlik

- İlk yönetici komut satırından atanır: `php artisan marketplace:make-admin eposta@adresi.com`.
- **İki aşamalı doğrulama zorunludur.** İlk girişte doğrulama uygulamasıyla (Google/Microsoft
  Authenticator vb.) QR kodu okutun ve **kurtarma kodlarını** güvenli bir yerde saklayın. Her
  kurtarma kodu bir kez kullanılabilir. Telefonu kaybeden yönetici kurtarma koduyla girip 2FA'yı yeniden kurar.
- **Roller:** `admin` (tüm yetkiler), `moderator` (ürün ve yorum moderasyonu, destek), `creator`, `customer`.
  Moderatör menüde yalnızca yetkili olduğu modülleri görür. Rolleri yalnızca yöneticiler değiştirebilir;
  kimse kendi yönetici rolünü kaldıramaz.
- Panelde yapılan her değişiklik **Loglar → Yönetici işlemleri** bölümüne (şifre ve belirteçler hariç),
  finansal ve kritik işlemler eski/yeni değerleriyle **Denetim kayıtları** bölümüne yazılır.

## 2. Dashboard

Kullanıcı, creator, ürün, satış, puan yükleme, komisyon geliri ve bekleyen işler (creator
başvuruları, inceleme bekleyen ürünler, hakediş talepleri, şikâyetler, destek talepleri) özetlenir.
Bekleyen işler için ayrıca site içi bildirim ve (tercihe göre) e-posta gelir.

## 3. Creator başvuruları

Başvuruyu açın; mağaza bilgilerini inceleyin. **Onayla** creator panelini açar; **Reddet** için
gerekçe yazın (başvurana iletilir). Onaylı bir creator gerektiğinde askıya alınabilir.

## 4. Ürün moderasyonu

- İncelemedeki ürünü açın: görseller, dosyalar (tarama durumu, SHA-256), açıklama, lisanslar, fiyat.
- **Onayla** yayınlar; **Reddet** not ister (creator'a gösterilir); **Yayından kaldır** katalogdan çıkarır
  (satın alanların erişimi sürer); **Öne çıkar** ana sayfadaki "Öne çıkan modeller" bölümüne ekler.
- Taraması "zararlı" çıkan dosyalar otomatik pasifleşir ve güvenlik olayı oluşturur.
- Ürünlerin incelemesiz yayınlanması **Sistem ayarları**ndan açılabilir (önerilmez).

## 5. Şikâyetler ve yorum moderasyonu

- **İçerik şikâyetleri:** telif hakkı ve içerik şikâyetleri. Gerekirse ürünü yayından kaldırın ve
  şikâyeti sonuçlandırın.
- **Yorum moderasyonu:** şikâyet edilen yorumlar; 3 farklı kullanıcı şikâyet eden yorum otomatik
  gizlenir. Yorumu yayına alın veya gizli tutun.

## 6. Kullanıcılar

Arama, profil, roller, durum (aktif/askıda). **Askıya alma** tüm oturumları ve API belirteçlerini
iptal eder. **Puan düzeltme** gerekçe ister ve geri alınamaz; hata ters kayıtla düzeltilir.
Kullanıcının cüzdan hareketleri ve satın almaları buradan görülür.

## 7. Puan sistemi

- 1 USD = 100 POINT sabittir. **Minimum yükleme** buradan değiştirilir.
- **Paketler:** ad, puan, fiyat (USD), bonus, sıra, etkin/pasif. Satışı yapılmış paket silinmez, pasifleştirilir.
- Özet kartlar: toplam yüklenen puan, kullanıcılardaki bakiye, eksi bakiyeli cüzdan (itiraz sonrası),
  yönetici düzeltmeleri, iade edilen ve itiraz edilen puanlar.

## 8. Ödemeler

- **Ödeme sağlayıcıları:** Sandbox (yalnızca test ortamı), iyzico, PayTR, Stripe. Bir sağlayıcı ancak
  API anahtarları `.env` dosyasına girildikten sonra açılabilir; eksik değişkenler kartta yazar.
  Kartta görünen **Bildirim adresi**ni sağlayıcının paneline girin (bkz. KURULUM.md → Ödeme sağlayıcıları).
- **Puan yüklemeleri:** durum (ödendi, bekliyor, başarısız, iade, itiraz), kullanıcı, tutar, kur.
- **İade:** yalnızca kullanıcının **harcanmamış** puanı yetiyorsa yapılır. İade önce puanları düşer,
  sonra sağlayıcıda para iadesini başlatır; sağlayıcı reddederse işlem tamamen geri alınır.
- **İtiraz (chargeback):** sağlayıcıdan itiraz bildirimi gelince puanlar düşülür ve kayıt "itiraz" olur.
- **Webhook kayıtları:** her bildirim, imza durumu ve işlem sonucuyla. İmzası geçersiz bildirimler
  ayrıca yöneticilere bildirilir.

## 9. Döviz kuru

Kur, zamanlanmış görevle sağlayıcıdan (varsayılan Frankfurter/ECB) çekilir. **Manuel kur** girilebilir;
otomatik güncelleme kapatılabilir. Bir önceki değerden aşırı sapan kur güvenlik gereği reddedilir.
Kur geçmişi ve sağlayıcı kayıtları burada listelenir. Kur alınamazsa TL ile ödeme geçici olarak durur.

## 10. Komisyon

Varsayılan oran (başlangıç %20) ve kurallar: **kampanya > creator > kategori > varsayılan** önceliğiyle
uygulanır. Değişiklikler yalnızca yeni satışları etkiler; geçmiş satışların oranı değişmez.

## 11. Hakedişler

- **Satıcı doğrulamaları:** kimlik/vergi ve banka bilgisi. Tam bilgi yalnızca hakediş yetkisiyle
  görülür ve her görüntüleme denetim kaydına yazılır. Onaylayın veya gerekçeyle reddedin.
- **Talepler:** onaylayın, reddedin (rezerv bakiyeye döner) veya **Ödendi** olarak işaretleyin
  (ödeme referansı, TL ödemede uygulanan kur). Ödemeyi bankadan/sağlayıcıdan siz yaparsınız;
  panel kaydı tutar.

## 12. Katalog ayarları

- **Kategoriler:** TR/EN ad, açıklama ve SEO başlığı/açıklaması, görsel, üst kategori, sıra, etkinlik.
- **Dosya formatları:** izin verilen uzantılar ve MIME türleri. Pasif format yeni yüklemelerde kabul edilmez.

## 13. Destek talepleri

Talepleri durum ve önceliğe göre izleyin, yanıtlayın (kullanıcıya bildirim ve e-posta gider),
kapatın. Puan iadesi talebinde ilgili yükleme bağlantısından **Ödemeler**e geçip iadeyi yapın.

## 14. İçerik ve SEO

- **İçerik:** sayfalar (yasal metinler dahil, TR/EN), blog yazıları, SSS, ana sayfa bannerları.
  Yasal metinler taslaktır; yayından önce hukukçu incelemesi gerekir.
- **SEO:** sayfa bazında başlık/açıklama ve indekslenmeme ayarı. Site haritası (`/sitemap.xml`) ve
  `robots.txt` otomatik üretilir.

## 15. Sistem ayarları

Site adı, logo, paylaşım görseli, iletişim bilgileri, SMTP (e-posta), dosya boyutu sınırları,
ürün incelemesi, otomatik kur güncelleme ve **bakım modu** (yöneticiler siteyi kullanmaya devam eder).

## 16. Rutin kontroller

- Günlük: bekleyen ürün, başvuru, şikâyet, destek ve hakediş talepleri (Dashboard).
- Haftalık: **Webhook kayıtları**nda başarısız bildirimler, **Loglar**da güvenlik olayları, kur geçmişi.
- Sunucuda zamanlanmış görevlerin (cron) ve kuyruk işçisinin çalıştığını kontrol edin; aksi hâlde
  kur güncellenmez, kazançlar serbest kalmaz ve dosya taraması ilerlemez.
