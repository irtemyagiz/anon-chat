# Anonim Chat

Anonim, 1'e 1, rastgele metin sohbet uygulaması.

## Yapı

```
.
├── server/              # Node.js backend (Express + Socket.IO + Postgres)
│   ├── src/
│   │   ├── index.js     # giriş noktası
│   │   ├── models/      # Sequelize modelleri
│   │   ├── routes/      # REST endpoint'leri
│   │   ├── sockets/     # Socket.IO handler'ları
│   │   ├── middleware/  # auth middleware
│   │   ├── queue/       # eşleşme kuyruğu
│   │   └── lib/         # yardımcılar
│   └── package.json
├── (mobile/)            # Expo SDK 54 uygulaması — kök dizinde (App.js, src/, vb.)
├── render.yaml          # Render Blueprint
└── ROADMAP.md           # proje yol haritası
```

## Backend

### Lokal geliştirme
```bash
cd server
cp .env.example .env
# .env içine DATABASE_URL ve JWT_SECRET ekle
npm install
npm run dev
```

### Deploy (Render)
1. GitHub'da repo oluştur
2. Render → New → Blueprint → repo URL'ini yapıştır
3. render.yaml otomatik Web Service + Postgres kurar

## API

| Method | Path | Açıklama |
|---|---|---|
| GET | `/api/health` | Sağlık kontrolü |
| POST | `/api/auth/device` | Cihaz ID ile giriş → JWT |
| GET | `/api/auth/me` | Mevcut kullanıcı |
| PUT | `/api/auth/me` | Profil güncelle (nickname, interests, age) |
| GET | `/api/interests` | İlgi etiketleri |
| POST | `/api/interests/seed` | Varsayılan etiketleri yükle (admin) |

## Socket.IO olayları

| Olay | Yön | Payload |
|---|---|---|
| `match:start` | client → server | `{ interestIds: number[] }` |
| `match:waiting` | server → client | — |
| `match:found` | server → client | `{ roomId, peer }` |
| `match:cancel` | client → server | — |
| `match:timeout` | server → client | — |
| `chat:message` | client → server | `{ content }` |
| `chat:message` | server → client | `{ id, content, senderId, createdAt, flagged }` |
| `chat:typing` | bidirectional | `{ typing }` |
| `chat:leave` | client → server | — |
| `chat:ended` | server → client | `{ by }` |
| `chat:report` | client → server | `{ reason, note }` |
| `banned` | server → client | `{ expiresAt }` |
