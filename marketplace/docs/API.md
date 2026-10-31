# REST API (v1)

Temel adres: `https://alanadiniz.com/api/v1`. Tüm yanıtlar JSON'dur. İsteklerde
`Accept: application/json` başlığı gönderin.

- **Sürümleme:** adres yolunda (`/api/v1`). Geriye dönük uyumsuz değişiklikler `v2` altında yayınlanır.
- **Hız sınırı:** dakikada 60 istek (oturum açılmışsa kullanıcı, değilse IP başına).
  Belirteç alma dakikada 5, satın alma dakikada 20, indirme bağlantısı dakikada 30 istekle sınırlıdır.
  Aşılırsa `429 Too Many Requests` döner.
- **Dil:** `Accept-Language: en` ile İngilizce kategori/lisans adları ve hata mesajları alınır; varsayılan Türkçe.
- **Para birimi:** fiyatlarda `?currency=TRY` veya `?currency=USD` kullanılabilir. Verilmezse
  kullanıcının ülkesine göre seçilir. TL tutarı güncel USD/TRY kurundan hesaplanır;
  kur alınamamışsa USD gösterilir.
- **Tutarlar:** ondalık sayı kullanılmaz. `usd_cents` (sent), `points` (1 USD = 100 POINT),
  `amount_minor` (kuruş/sent) tam sayıdır; `formatted` yalnızca gösterim içindir.

## Kimlik doğrulama

Kişisel erişim belirteci (Bearer token) kullanılır. Belirteçler varsayılan olarak 30 gün
geçerlidir (`API_TOKEN_EXPIRATION_MINUTES`). Hesap askıya alınırsa veya silinirse tüm
belirteçler iptal edilir. API yalnızca kullanıcı düzeyindeki işlemleri sunar; yönetim paneli
işlemleri iki aşamalı doğrulama gerektirdiği için API'den yapılamaz.

### Belirteç al

`POST /auth/tokens`

```json
{ "email": "ornek@eposta.com", "password": "…", "device_name": "Mobil uygulama" }
```

`201 Created`

```json
{
  "token": "1|Qm9…",
  "token_type": "Bearer",
  "expires_at": "2026-11-30T10:00:00+00:00",
  "user": { "uuid": "…", "username": "…", "email": "…", "email_verified": true, "is_creator": false }
}
```

Hatalar: `422` (bilgiler hatalı), `403` (hesap aktif değil).

Sonraki isteklerde: `Authorization: Bearer 1|Qm9…`

### Belirteci iptal et

`DELETE /auth/tokens/current` → `{ "revoked": true }`

## Katalog (herkese açık)

### Kategoriler

`GET /categories`

```json
{ "data": [ { "id": 3, "slug": "figurler", "name": "Figürler", "parent_id": null, "products_count": 12, "url": "…" } ] }
```

### Ürün listesi

`GET /products`

| Parametre | Açıklama |
|---|---|
| `q` | Başlık, kısa açıklama, etiket veya mağaza adında arama |
| `kategori` | Kategori slug'ı (alt kategoriler dahil) |
| `etiket` | Etiket slug'ı |
| `fiyat` | `ucretsiz` veya `ucretli` |
| `min`, `max` | USD fiyat aralığı, ör. `min=2.50&max=10` |
| `format` | Dosya uzantısı, ör. `stl`, `3mf` |
| `lisans` | `ticari` → ticari kullanım lisansı olanlar |
| `teknoloji` | `fdm`, `sla`, `sls`, `other` |
| `sirala` | `yeni` (varsayılan), `populer`, `cok-satan`, `cok-indirilen`, `ucuz`, `pahali`, `puan`, `begeni` |
| `per_page` | 1–48 (varsayılan 24) |
| `page` | Sayfa |

```json
{
  "data": [
    {
      "uuid": "…", "slug": "katmanli-masa-organizeri", "title": "Katmanlı Masa Organizeri",
      "short_description": "…", "is_free": false,
      "price": { "points": 399, "usd_cents": 399, "currency": "TRY", "amount_minor": 15960, "formatted": "159,60 TL" },
      "category": { "slug": "organizasyon", "name": "Organizasyon" },
      "designer": { "store_name": "Ada Atölye", "store_slug": "ada-atolye" },
      "cover_image": { "url": "…", "small": "…-480.webp", "medium": "…-960.webp" },
      "stats": { "likes": 4, "downloads": 10, "sales": 7, "rating_average": "4.50", "reviews": 2 },
      "published_at": "…", "url": "…"
    }
  ],
  "links": { "first": "…", "next": "…" },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 24, "total": 61 }
}
```

### Ürün detayı

`GET /products/{slug}` — listedeki alanlara ek olarak `description`, `images`, `licenses`
(her lisansın `id` ve fiyatı), `tags`, `files` (ad, uzantı, boyut; indirme adresi değil),
`versions` (sürüm notları) ve `print` (baskı bilgileri). Yayında olmayan ürün `404` döner.

### Tasarımcı

`GET /designers/{store_slug}` — mağaza bilgileri ve son 24 ürün.

### Döviz kuru

`GET /exchange-rate`

```json
{ "data": { "base": "USD", "quote": "TRY", "rate": "40.125000", "source": "frankfurter", "fetched_at": "…", "points_per_usd": 100 } }
```

## Hesap (belirteç gerekir)

### Profil

`GET /me` → kullanıcı bilgileri.

### Puan cüzdanı

`GET /me/wallet?per_page=20`

```json
{
  "data": [
    { "reference": "…", "type": "purchase", "description": "… satın alındı", "amount_points": -399, "balance_after_points": 601, "created_at": "…" }
  ],
  "balance_points": 601,
  "meta": { "…": "…" }
}
```

`type`: `top_up`, `purchase`, `purchase_refund`, `top_up_refund`, `chargeback`, `admin_adjustment`, `promotion`, `reversal`.
Puan yükleme (ödeme) API üzerinden yapılmaz; kullanıcı web sitesindeki ödeme sayfasına yönlendirilir.

### Kütüphane

`GET /me/library` → satın alınan ve ücretsiz edinilen ürünler (`acquired_at`, `source`, `license`, `product`).

### İndirme bağlantıları

`GET /me/library/{slug}/downloads`

```json
{
  "data": [
    { "name": "model.stl", "extension": "stl", "size_bytes": 1048576, "available": true, "url": "https://…/api/v1/downloads/…?expires=…&user=…&signature=…" }
  ],
  "expires_in_minutes": 10
}
```

Bağlantılar 10 dakika geçerlidir ve yalnızca o kullanıcıya aittir; imza değiştirilirse `403` döner.
Güvenlik taraması tamamlanmamış dosyalarda `available: false`, `url: null` olur.
Ürüne erişim hakkı yoksa `403`.

### Satın alma

`POST /products/{slug}/purchase`

```json
{ "request_id": "b6f0c7a2-…-uuid", "license_id": 12, "coupon": "YAZ2026" }
```

- `request_id` zorunlu ve istemci tarafından üretilen bir UUID'dir. Aynı `request_id` ile tekrar
  gönderilen istek ikinci kez puan düşmez, aynı siparişi döndürür (ağ hatasında güvenle tekrar deneyin).
- `license_id` verilmezse varsayılan (kişisel) lisans kullanılır.
- Ücretsiz üründe istek kütüphaneye ekler: `{"status": "claimed"}`.
- E-posta adresi doğrulanmamış kullanıcılar satın alamaz (`403`).

`201 Created`

```json
{ "status": "completed", "order": { "number": "ORD-261031-AB12CD34", "total_points": 359, "created_at": "…" }, "balance_points": 241 }
```

| Durum | Anlamı |
|---|---|
| `402` | Yetersiz puan: `shortfall_points` ve puan yükleme sayfası `top_up_url` döner |
| `409` | Bu ürün (aynı lisans) zaten kütüphanende |
| `422` | Kupon geçersiz, ürün satışta değil, kendi ürünün vb. (`message`) |

## Hata biçimi

```json
{ "message": "…", "errors": { "alan": ["…"] } }
```

`401` belirteç yok/geçersiz/süresi dolmuş, `403` yetki yok, `404` bulunamadı, `422` doğrulama hatası, `429` hız sınırı.

## Örnek (curl)

```bash
curl -s -X POST https://alanadiniz.com/api/v1/auth/tokens -H "Accept: application/json" -d "email=ornek@eposta.com" -d "password=…" -d "device_name=cli"
```

```bash
curl -s "https://alanadiniz.com/api/v1/products?format=stl&sirala=populer&currency=USD" -H "Accept: application/json"
```

```bash
curl -s -X POST https://alanadiniz.com/api/v1/products/katmanli-masa-organizeri/purchase -H "Accept: application/json" -H "Authorization: Bearer 1|…" -d "request_id=$(uuidgen)"
```
