# Veritabanı Şeması

MySQL 8 / MariaDB 10.4+ (XAMPP) için tasarlanmıştır. Testler SQLite (bellek içi)
ve MariaDB 10.11 üzerinde çalıştırılmıştır. Migration dosyaları:
`database/migrations/2026_10_30_*` (ilk şema) ve `2026_10_31_*` (sonraki eklemeler).

## Genel kurallar

- Tüm finansal değerler **tamsayıdır**: puan (`points`, `balance`), USD sent
  (`*_cents`), para biriminin en küçük birimi (`*_minor`), kur (`rate_micros`), oran (`*_bps`).
  FLOAT/DOUBLE hiçbir yerde kullanılmaz.
- Dışarıya açılan kimlikler `uuid` alanıdır. Sıralı `id` URL'lerde kullanılmaz.
- Defter tabloları (`wallet_transactions`, `wallet_ledger_entries`,
  `wallet_adjustments`, `audit_logs`) yalnızca ekleme yapılan (append-only) tablolardır.
- Çok dilli alanlar JSON tutulur: `{"tr": "...", "en": "..."}`.
- Durum alanları `string` tutulur ve PHP enum'larıyla (`app/Enums`) doğrulanır.

## Kullanıcı ve yetki

| Tablo | Amaç | Önemli kısıtlar |
|---|---|---|
| `users` | Tek kullanıcı tabanı (alıcı + creator + yönetici) | `uuid`, `username`, `email` unique. Soft delete. 2FA alanları şifreli |
| `roles`, `permissions` | Rol ve izinler (admin, moderator, creator, customer) | `name` unique |
| `role_permissions`, `user_roles` | Çoka-çok ilişkiler | Bileşik birincil anahtar |
| `user_settings` | Kullanıcı tercihleri | (`user_id`, `key`) unique |
| `sessions` | Oturumlar (Laravel) | — |
| `creator_profiles` | Mağaza: tasarımcı adı, mağaza adı/slug, logo, kapak, sosyal bağlantılar, onay durumu | `user_id` unique, `store_slug` unique |
| `creator_verifications` | Satıcı kimlik/vergi doğrulaması | Vergi no, kimlik no ve adres şifreli |

## Katalog

| Tablo | Amaç |
|---|---|
| `categories` | Sınırsız alt kategori (`parent_id`), SEO alanları, sıralama |
| `tags`, `product_tags` | Etiketler |
| `licenses` | Lisans türleri (kişisel, ticari) |
| `file_formats` | Yönetilebilir dosya formatları: uzantı, izinli MIME türleri, arşiv/önizleme bayrakları |
| `products` | Ürün: başlık, slug, açıklama, `price_cents`, `is_free`, durum, baskı bilgileri, boyutlar, sayaçlar, SEO. `preview_model_path` herkese açık 3D önizleme; `preview_auto_generated` önizlemenin STL'den otomatik sadeleştirilerek üretildiğini gösterir |
| `product_categories` | Ek kategoriler |
| `product_licenses` | Ürünün lisans bazında fiyatı. (`product_id`, `license_id`) unique |
| `product_versions` | Sürüm geçmişi ve değişiklik notları |
| `product_files` | İndirilebilir dosyalar: disk, gizli yol, boyut, SHA-256, tarama durumu |
| `product_images` | Galeri görselleri ve boyut varyantları (WebP/AVIF) |
| `product_reports` | Telif hakkı ve içerik şikâyetleri |
| `upload_sessions` | Parçalı (chunked) büyük dosya yüklemeleri: UUID, creator, ürün, bildirilen boyut, alınan bayt, son kullanma. Parçalar yerel diskte `chunks/{id}.part` dosyasında birleşir; süresi dolanlar saatlik görevle silinir |

`products` indeksleri: `(status, published_at)`, `(creator_id, status)`, `price_cents`,
`is_free`, `is_featured`, `print_technology`.

## Para birimi

| Tablo | Amaç |
|---|---|
| `currencies` | USD, TRY |
| `exchange_rates` | Kur geçmişi. Her güncelleme yeni satırdır. Manuel kur `is_manual` + `set_by` |
| `exchange_rate_logs` | Her sağlayıcı denemesi: başarı, hata, süre |

## Puan cüzdanı ve ödeme

| Tablo | Amaç | Önemli kısıtlar |
|---|---|---|
| `wallets` | Kullanıcı cüzdanı (`user_id`) veya sistem hesabı (`system_code`) | Her ikisi de unique. `balance` signed BIGINT |
| `wallet_transactions` | İş olayı: tür, açıklama, konu (morph), ters kayıt bağlantısı | `reference` (uuid) ve `idempotency_key` unique |
| `wallet_ledger_entries` | Defter bacakları: `amount` (±), `balance_after` | İndeks (`wallet_id`, `id`) |
| `wallet_adjustments` | Yönetici düzeltmesinin gerekçesi ve yöneticisi | `wallet_transaction_id` unique |
| `point_packages` | Paketler: puan, USD sent fiyatı, (ileride) bonus | `code` unique |
| `payment_providers` | Sağlayıcı kaydı: mod, açık/kapalı, para birimleri, ülkeler | `code` unique |
| `point_purchases` | Yükleme siparişi: puan, USD, tahsil tutarı, **sabitlenmiş kur**, durum | (`provider`, `provider_reference`) unique |
| `payment_transactions` | Sağlayıcı tarafındaki işlem kayıtları | (`provider`, `provider_transaction_id`, `type`) unique |
| `payment_webhook_events` | Ham webhook, imza geçerliliği, işleme durumu | (`provider`, `event_id`) unique → tekrar işlenmez |
| `payment_refunds` | Yükleme iadeleri | — |
| `payment_disputes` | Ödeme itirazları (chargeback) | — |

## Satış ve hakediş

| Tablo | Amaç | Önemli kısıtlar |
|---|---|---|
| `coupons` | Creator kuponları (yüzde / sabit), kullanım limiti, tarih aralığı | (`creator_id`, `code`) unique |
| `orders` | Puanla satın alma siparişi | `number`, `uuid`, `idempotency_key` unique |
| `order_items` | Satış kalemi: liste fiyatı, indirim, ödenen puan, **komisyon oranı ve paylaşım tutarları (snapshot)** | İndeks (`creator_id`, `created_at`) |
| `purchase_entitlements` | İndirme hakkı (satın alma / ücretsiz / hediye), iptal bilgisi | (`user_id`, `product_id`, `license_id`) unique |
| `downloads` | İndirme kayıtları (IP, user agent) | — |
| `commission_rules` | Varsayılan / creator / kategori / kampanya oranları, tarih aralığı | — |
| `creator_balances` | Creator özet bakiyesi: pending, available, reserved, paid, reversed (USD sent) | `creator_id` unique |
| `creator_earnings` | Satış bazında kazanç ve durumu | `order_item_id` unique |
| `creator_payout_requests` | Ödeme talepleri. Banka bilgisi şifreli | — |
| `creator_payouts` | Yapılan ödemeler: tutar, ödeme para birimi, kur, referans | `creator_payout_request_id` unique |

## Topluluk

`favorites`, `product_likes`, `collections`, `collection_products`, `follows`,
`product_reviews` (kullanıcı başına ürün başına tek değerlendirme, doğrulanmış
satın alma rozeti, creator yanıtı), `comments` (iç içe yanıt, moderasyon durumu),
`notifications` (Laravel bildirimleri).

## Yönetim

`system_settings` (grup/anahtar/değer, isteğe bağlı şifreli), `audit_logs`
(eski/yeni değer, işlemi yapan, IP), `admin_logs`, `security_events`,
`support_tickets`, `support_messages`, `banners`, `pages` (sayfa/blog/yasal metin,
dil bazında), `faqs`, `seo_settings`.

## İlişki özeti

```
users 1─1 creator_profiles 1─n creator_verifications
users 1─1 wallets 1─n wallet_ledger_entries n─1 wallet_transactions
users 1─n point_purchases 1─n payment_transactions / payment_refunds / payment_disputes
users(creator) 1─n products 1─n product_licenses n─1 licenses
products 1─n product_files / product_images / product_versions
users 1─n orders 1─n order_items 1─1 creator_earnings n─1 creator_payouts
order_items 1─1 purchase_entitlements n─1 users
users(creator) 1─1 creator_balances, 1─n creator_payout_requests 1─1 creator_payouts
categories 1─n categories (parent), 1─n products
```
