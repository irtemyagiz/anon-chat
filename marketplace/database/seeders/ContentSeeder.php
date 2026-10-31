<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Yasal metin taslakları, hakkımızda sayfası ve SSS. Mevcut kayıtların üzerine
 * yazmaz (yönetim panelindeki düzenlemeler korunur). Yasal metinler TASLAKTIR;
 * yayına alınmadan önce bir hukukçu tarafından incelenmelidir.
 */
class ContentSeeder extends Seeder
{
    private const DRAFT_NOTICE = "> **TASLAK — Hukuki inceleme gereklidir.** Bu metin şablon olarak hazırlanmıştır. Şirket unvanı, adres, MERSİS/vergi bilgileri ve süreler doldurulmalı; yayına alınmadan önce bir hukukçu tarafından incelenmelidir. Bu uyarıyı yönetim panelinden kaldırabilirsiniz.\n\n";

    public function run(): void
    {
        $pages = [
            ['page', 'hakkimizda', 'Hakkımızda', false, <<<'MD'
Katman, bağımsız tasarımcıların 3D baskıya hazır dijital modellerini paylaştığı ve sattığı bir pazaryeridir.

## Ne yapıyoruz?

- Tasarımcılara kendi mağazalarını açma, modellerini satma ve ücretsiz paylaşma imkânı sunuyoruz.
- Alıcılara puan sistemiyle hızlı ve güvenli satın alma, satın aldıkları dosyalara her zaman erişim sağlıyoruz.
- Ücretli dosyaları herkese açık tutmuyor, indirmeleri kısa süreli güvenli bağlantılarla sunuyoruz.

## İletişim

Sorularınız için hesabınızdan destek talebi oluşturabilirsiniz.
MD],
            ['legal', 'kullanim-sartlari', 'Kullanım Şartları', true, <<<'MD'
## 1. Taraflar ve kapsam
Bu şartlar, [ŞİRKET UNVANI] ("Platform") ile platformu kullanan üyeler arasındaki ilişkiyi düzenler. Üye olarak bu şartları kabul etmiş sayılırsınız.

## 2. Üyelik
- Üyelik için 18 yaşını doldurmuş olmak veya yasal temsilci onayı gerekir.
- Hesap bilgilerinizin doğruluğundan ve şifrenizin gizliliğinden siz sorumlusunuz.

## 3. Puan sistemi
- Puanlar yalnızca platformdaki dijital ürünlerin satın alınmasında kullanılır. **1 USD = 100 POINT** sabit orandır.
- Puanlar nakde çevrilemez, başka kullanıcılara devredilemez ve satılamaz.
- Harcanmamış puanların iadesi destek talebiyle değerlendirilir; harcanmış puanlar iade edilmez.
- Ödeme itirazı (chargeback) halinde ilgili puanlar hesaptan düşülür.

## 4. Dijital ürünler ve lisanslar
- Satın aldığınız ürünü seçtiğiniz lisans koşullarına göre kullanabilirsiniz. Kişisel lisans ticari kullanıma izin vermez.
- Dijital dosyaların kendisinin paylaşılması, yeniden satılması veya dağıtılması yasaktır.

## 5. Creator (satıcı) yükümlülükleri
- Yüklediğiniz içeriğin tüm haklarına sahip olduğunuzu beyan edersiniz.
- Platform komisyonu yönetim panelinde ilan edilen oranda satış bedelinden düşülür.
- Hakediş ödemeleri satıcı kimlik/vergi doğrulaması tamamlandıktan sonra yapılır.

## 6. Yasaklı içerik
Telif hakkı ihlali, yasa dışı, zararlı yazılım içeren veya yanıltıcı içerikler kaldırılır; ilgili hesaplar askıya alınabilir.

## 7. Sorumluluğun sınırlandırılması
Basılan fiziksel ürünlerin kullanımından doğan zararlardan platform sorumlu tutulamaz.

## 8. Değişiklikler ve uyuşmazlıklar
Şartlar güncellenebilir; güncel metin bu sayfada yayımlanır. Uyuşmazlıklarda [İL] mahkemeleri ve icra daireleri yetkilidir.
MD],
            ['legal', 'gizlilik-ve-kvkk', 'Gizlilik ve KVKK Aydınlatma Metni', true, <<<'MD'
## Veri sorumlusu
[ŞİRKET UNVANI], [ADRES] — 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamında veri sorumlusudur.

## İşlenen kişisel veriler
- **Kimlik ve iletişim:** ad, soyad, kullanıcı adı, e-posta, ülke
- **İşlem güvenliği:** IP adresi, oturum ve giriş kayıtları, iki aşamalı doğrulama bilgileri
- **Müşteri işlem:** puan yükleme, satın alma, indirme ve destek kayıtları
- **Satıcılar için ek olarak:** yasal ad/unvan, T.C. kimlik veya vergi numarası, adres, banka/ödeme bilgileri (şifreli saklanır)

## İşleme amaçları ve hukuki sebepler
Sözleşmenin kurulması ve ifası, yasal yükümlülükler (vergi, ticaret ve elektronik ticaret mevzuatı), meşru menfaat (güvenlik, dolandırıcılığın önlenmesi) ve açık rızanız (pazarlama iletileri).

## Aktarım
Ödeme kuruluşlarına (ödemenin gerçekleştirilmesi), barındırma ve e-posta hizmet sağlayıcılarına ve yetkili kamu kurumlarına kanuni sınırlar içinde aktarılabilir. Yurt dışına aktarım yapılması halinde KVKK m.9 hükümlerine uyulur.

## Saklama süresi
Mali kayıtlar ilgili mevzuatta öngörülen süre boyunca saklanır. Hesabınızı sildiğinizde kişisel verileriniz anonimleştirilir.

## Haklarınız (KVKK m.11)
Verilerinizin işlenip işlenmediğini öğrenme, düzeltilmesini veya silinmesini isteme ve itiraz etme haklarına sahipsiniz. Başvurularınızı destek talebi veya [E-POSTA] adresi üzerinden iletebilirsiniz. Hesabınızı **Profil ve ayarlar → Hesabı sil** bölümünden silebilirsiniz.
MD],
            ['legal', 'mesafeli-satis-sozlesmesi', 'Mesafeli Satış Sözleşmesi', true, <<<'MD'
## 1. Taraflar
**Satıcı / aracı hizmet sağlayıcı:** [ŞİRKET UNVANI], [ADRES], [MERSİS NO], [E-POSTA]
**Alıcı:** Platforma üye olan ve satın alma işlemini gerçekleştiren kişi.

## 2. Konu
Bu sözleşme, alıcının platform üzerinden elektronik ortamda satın aldığı puan paketlerinin ve dijital içeriklerin satışına ilişkin tarafların hak ve yükümlülüklerini 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği'ne göre düzenler.

## 3. Ürün ve ödeme bilgileri
Puan paketinin içeriği, fiyatı ve ödeme para birimi ödeme sayfasında gösterilir. TL ödemelerde ödeme anındaki kur sabitlenir ve işlem kaydına yazılır.

## 4. Teslimat
Puanlar, ödeme kuruluşu tarafından ödemenin onaylanmasının ardından hesaba anında yüklenir. Dijital içerikler satın alma ile birlikte hesabınızdaki kütüphaneye eklenir.

## 5. Cayma hakkı
Mesafeli Sözleşmeler Yönetmeliği m.15/1-ğ uyarınca, elektronik ortamda anında ifa edilen hizmetler ve tüketiciye anında teslim edilen gayri maddi mallarda, **ifaya başlandıktan sonra cayma hakkı kullanılamaz**. Alıcı, satın alma öncesinde bu konuda bilgilendirildiğini kabul eder. Harcanmamış puanlar için iade talebi ayrıca değerlendirilir (bkz. İade ve Cayma Politikası).

## 6. Uyuşmazlık
Tüketici şikâyetleri için Ticaret Bakanlığı'nca belirlenen parasal sınırlar dahilinde tüketici hakem heyetleri ve tüketici mahkemeleri yetkilidir.
MD],
            ['legal', 'iade-ve-cayma', 'İade ve Cayma Politikası', true, <<<'MD'
## Puan paketleri
- **Harcanmamış puanlar:** Satın alma tarihinden itibaren [14] gün içinde, hiç kullanılmamış puanlar için destek talebiyle iade isteyebilirsiniz. İade, ödemenin yapıldığı yönteme yapılır.
- **Harcanmış puanlar:** Dijital içerik satın alımında kullanılan puanlar iade edilmez.

## Dijital ürünler
Dijital dosyalar satın alma anında erişime açıldığı için kural olarak iade edilmez. Ancak:
- Dosya bozuk, eksik veya açıklamayla açıkça uyumsuzsa,
- Teknik bir hata nedeniyle aynı ürün iki kez ücretlendirildiyse,

destek talebi oluşturabilirsiniz. Haklı bulunan taleplerde puanlar hesabınıza iade edilir ve ürün kütüphanenizden kaldırılır.

## Ödeme itirazları
Bankanız üzerinden ödeme itirazı başlatılması halinde ilgili puanlar hesabınızdan düşülür.
MD],
            ['legal', 'telif-hakki-politikasi', 'Telif Hakkı ve İçerik Kaldırma Politikası', true, <<<'MD'
Platform, 5846 sayılı Fikir ve Sanat Eserleri Kanunu ve 5651 sayılı Kanun kapsamında hak sahiplerinin taleplerini ciddiyetle değerlendirir.

## Bildirim nasıl yapılır?
Hak ihlali içeren ürün sayfasındaki **"Bu içeriği bildir"** bağlantısından "Telif hakkı ihlali" seçeneğiyle bildirim yapabilirsiniz. Bildiriminizde:
- Orijinal eserin bağlantısı veya hak sahipliğinizi gösteren bilgi,
- İhlal eden ürünün açıklaması,
- İletişim e-posta adresiniz

yer almalıdır. Hesap açmanız gerekmez.

## Süreç
Bildirimler en geç [3] iş günü içinde incelenir. İhlal tespit edilirse ürün yayından kaldırılır ve satıcı bilgilendirilir. Tekrarlayan ihlallerde satıcı hesabı askıya alınır.
MD],
            ['legal', 'cerez-politikasi', 'Çerez Politikası', true, <<<'MD'
Platform yalnızca hizmetin çalışması için gerekli çerezleri kullanır:

| Çerez | Amaç | Süre |
|---|---|---|
| Oturum çerezi | Giriş durumunuzun korunması | Oturum |
| XSRF-TOKEN | Form güvenliği (CSRF koruması) | Oturum |
| theme | Açık/koyu tema tercihiniz | 1 yıl |

Reklam veya üçüncü taraf takip çerezi kullanılmaz. Bu durum değişirse açık rızanız alınacaktır.
MD],
        ];

        foreach ($pages as [$type, $slug, $title, $isDraft, $body]) {
            Page::query()->firstOrCreate(['type' => $type, 'slug' => $slug, 'locale' => 'tr'], [
                'title' => $title,
                'body' => ($isDraft ? self::DRAFT_NOTICE : '').$body,
                'is_published' => true,
                'published_at' => now(),
            ]);
        }

        $faqs = [
            ['Puan nedir, nasıl çalışır?', "Katman'da tüm satın alımlar puanla yapılır. 1 USD = 100 POINT sabittir. Bir kez puan yükleyip istediğin modeli tek tıkla alabilirsin."],
            ['En az ne kadar puan yükleyebilirim?', 'Minimum yükleme 500 POINT (5 USD karşılığı) ve üzeridir. Türkiye\'deki kullanıcılar TL ile, güncel kur üzerinden öder.'],
            ['Kur değişirse puanım değişir mi?', 'Hayır. Yüklediğin puan miktarı sabittir; ödeme anındaki kur işlem kaydına yazılır ve sonradan değişmez.'],
            ['Satın aldığım dosyalara nereden ulaşırım?', 'Hesabım → Kütüphanem sayfasından satın aldığın ve indirdiğin tüm modellere istediğin zaman ulaşabilirsin.'],
            ['Puanlarımı nakde çevirebilir veya başkasına gönderebilir miyim?', 'Hayır. Puanlar yalnızca platformdaki dijital ürünlerde kullanılabilir; nakde çevrilemez ve devredilemez.'],
            ['Harcamadığım puanları iade alabilir miyim?', 'Harcanmamış puanlar için destek talebi oluşturabilirsin. Ayrıntılar İade ve Cayma Politikası sayfasındadır.'],
            ['Creator olarak nasıl kazanç elde ederim?', 'Creator başvurun onaylandıktan sonra ürün yükleyip fiyatını USD olarak belirlersin. Her satıştan komisyon düşüldükten sonra kalan tutar hakedişine eklenir.'],
            ['Hakedişimi ne zaman alabilirim?', 'Satış kazancı iade süresi dolduktan sonra kullanılabilir olur. Satıcı doğrulamanı tamamladıktan sonra Creator paneli → Hakedişler sayfasından talep oluşturabilirsin.'],
        ];

        foreach ($faqs as $index => [$question, $answer]) {
            Faq::query()->firstOrCreate(['locale' => 'tr', 'question' => $question], ['answer' => $answer, 'sort_order' => ($index + 1) * 10, 'is_active' => true]);
        }
    }
}
