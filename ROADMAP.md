# Anonim Chat — Proje Yol Haritası (MVP)

> Durum: **Plan onaylandı · uygulama henüz başlamadı** · adım adım ilerleyeceğiz.

---

## 1. Hedef

Anonim, 1'e 1, rastgele metin sohbet uygulaması. Kayıt yok, telefon/email yok. Modern Omegle hissi, Türkiye odaklı başlangıç (TR + EN).

### Gelir (MVP sonrası)
- AdMob (banner + interstitial + rewarded)
- RevenueCat abonelik (Premium 4.99$/ay, Gold 9.99$/ay)

### MVP'de OLMAYACAK
Reklam · Abonelik · Push · Sesli mesaj · Foto · Çeviri · AI moderasyon · Native build · Arkadaş listesi · Başarımlar · Coin

---

## 2. Teknik Yığın

| Katman | Tercih |
|---|---|
| Mobil | Expo SDK 54 + React Native |
| Backend | Node.js + Express + Socket.IO |
| DB | MySQL (sunucuda kurulu, phpMyAdmin) |
| ORM | Sequelize |
| Auth | Anonim — cihaz UUID + JWT |
| Reverse proxy | Nginx |
| Süreç yöneticisi | PM2 |
| Dağıtım | api.alanadi.com (HTTPS + WSS) |
| Test | Expo Go (iPhone + Android) |

---

## 3. Repo Yapısı (planlanan)

```
anon-chat/
├── mobile/              # Expo SDK 54 uygulaması
│   ├── App.js
│   ├── src/
│   │   ├── screens/     # WelcomeScreen, Rules, Nickname, Interests, Matching, Chat, PostChat, Profile
│   │   ├── components/  # Button, Avatar, MessageBubble, vb.
│   │   ├── services/    # api.js, socket.js, i18n.js
│   │   ├── store/       # Zustand store
│   │   ├── theme.js
│   │   └── utils/
│   └── assets/
├── server/              # Node.js + Express + Socket.IO
│   ├── src/
│   │   ├── index.js
│   │   ├── routes/
│   │   ├── sockets/
│   │   ├── models/      # Sequelize modelleri
│   │   └── lib/
│   ├── migrations/
│   └── package.json
└── README.md
```

> Şu an proje düz Expo iskelet. `server/` ve `mobile/` ayrımı **Hafta 1**'de yapılacak.

---

## 4. MySQL Şeması (planlanan)

```sql
users          (id, device_id, nickname, avatar_color, country_code, created_at, last_seen)
interests      (id, name)
user_interests (user_id, interest_id)
messages       (id, room_id, sender_id, content, created_at)
reports        (id, reporter_id, reported_id, room_id, reason, created_at)
bans           (id, device_id, reason, expires_at, created_at)
```

---

## 5. 4 Haftalık Sprint

### Hafta 1 — Backend + Auth
- Repo yapısı (mobile/ + server/)
- Express + Socket.IO iskeleti
- Sequelize ile MySQL bağlantısı
- Migration'lar
- `/auth`, `/users/me`, `/interests` REST endpoint'leri
- Cihaz UUID → JWT
- **Mobil:** ApiClient, AuthContext, socket client
- **Çıktı:** Login olunca nickname kaydedebiliyor

### Hafta 2 — Onboarding
- WelcomeScreen (mevcut, güncellenecek)
- RulesScreen (kullanım kuralları, 18+ onay)
- NicknameScreen (rumuz + renk seç)
- InterestsScreen (çoklu etiket)
- ProfileScreen
- i18n (TR + EN)
- **Çıktı:** Açılışta kurallar → nickname → ilgi → ana ekran

### Hafta 3 — Eşleşme + Sohbet
- Backend: eşleşme kuyruğu
- Backend: room oluşturma + mesaj iletimi
- MatchingScreen (animasyonlu bekleme, iptal)
- ChatScreen (mesaj balonları, typing indicator, input)
- "Sıradaki" + sohbeti sonlandırma
- **Çıktı:** İki cihazda gerçek zamanlı sohbet

### Hafta 4 — Güvenlik + Polish
- Şikayet butonu + PostChatScreen
- Engel listesi (3 rapor = 24 saat ban)
- Mesaj limitleri + rate limiting
- Hata yönetimi + loglama
- Deploy rehberi
- **Çıktı:** Yayına hazır MVP

---

## 6. Sonraki Aşamalar (MVP sonrası)

| Hafta | İş |
|---|---|
| 5 | AdMob entegrasyonu |
| 6 | RevenueCat + Paywall |
| 7 | Push notification |
| 8 | Sesli mesaj |
| 9 | AI moderasyon + çeviri |
| 10 | Native build (TestFlight + Play Store) |

---

## 7. Görev Dağılımı

| Ben | Sen |
|---|---|
| Tüm React Native kodları | Subdomain DNS kaydı |
| Tüm Node.js backend kodu | SSL sertifikası (Certbot) |
| MySQL şeması + migration | Sunucuya deploy (PM2) |
| Nginx config | MySQL DB + kullanıcı oluşturma |
| Kurulum dokümantasyonu | — |

---

## 8. Mevcut Durum

| Öğe | Durum |
|---|---|
| Mobil proje iskeleti | ✅ Expo SDK 54, `C:\dev\anon-chat` |
| Welcome screen | ✅ Tamamlandı |
| Backend | ❌ Yok |
| Sunucu deploy | ❌ Yapılmadı |
| iPhone Expo Go bağlantısı | ⏳ `cd C:\dev\anon-chat` + `npx expo start --tunnel` ile test edilebilir |

---

## 9. Komutlar (Hızlı Referans)

### Mobil (PC)
```bash
cd C:\dev\anon-chat
npm install
npx expo start --tunnel --clear    # iPhone + Android için
```

### Sunucu (Linux VPS)
```bash
# Kurulum
sudo apt update && sudo apt install -y nodejs npm nginx mysql-server certbot python3-certbot-nginx
sudo npm install -g pm2
sudo certbot --nginx -d api.alanadi.com

# Deploy
cd /srv/anon-chat/server
npm install --production
npx sequelize-cli db:migrate
pm2 start src/index.js --name anon-chat
pm2 save && pm2 startup
```

---

## 10. Karar Özeti

- Backend: Kendi Linux VPS · Node + Express + Socket.IO + MySQL
- Pazar: TR önce, sonra global (TR + EN)
- Para: Dengeli 50/50 (MVP sonrası)
- Platform: iOS + Android (Expo Go ile test, native build sonra)
- Moderasyon: Orta (topluluk raporu + basit filtre)
- Bütçe: $0 (kendi sunucu + ücretsiz araçlar)
- Tempo: Yoğun, 4 hafta MVP
