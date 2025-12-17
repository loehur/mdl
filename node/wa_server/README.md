# WhatsApp Multi-Session Service

Service untuk menjalankan multiple WhatsApp sessions secara bersamaan menggunakan Baileys library.

## Features

- ✅ Multiple WhatsApp sessions dalam satu service
- ✅ QR Code untuk setiap session
- ✅ Auto-reconnect
- ✅ Webhook integration
- ✅ REST API
- ✅ Graceful shutdown

## Requirements

- Node.js >= 18.0.0
- npm atau yarn

## Installation

1. Install dependencies:
```bash
npm install
```

2. Configure webhook URL di `config.js`:
```javascript
module.exports = "https://your-webhook-url.com/webhook";
```

3. Start service:
```bash
npm start
```

Atau untuk development dengan auto-reload:
```bash
npm run dev
```

## API Documentation

### Base URL
```
http://localhost:8033
```

### Endpoints

#### 1. Health Check
```http
GET /
```

**Response:**
```json
{
  "status": true,
  "message": "WhatsApp Multi-Session Service is running",
  "version": "2.0.0",
  "active_sessions": 2
}
```

---

#### 2. Create Session
Membuat session WhatsApp baru.

```http
POST /create-session
Content-Type: application/json

{
  "sessionId": "session1"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Session \"session1\" created successfully",
  "sessionId": "session1"
}
```

---

#### 3. List Sessions
Menampilkan semua active sessions.

```http
GET /list-sessions
```

**Response:**
```json
{
  "status": true,
  "sessions": [
    {
      "sessionId": "session1",
      "logged_in": true,
      "qr_status": false,
      "user": {
        "id": "628123456789:1@s.whatsapp.net",
        "name": "My WhatsApp"
      }
    }
  ],
  "total": 1
}
```

---

#### 4. Check Status
Mengecek status session (untuk mendapatkan QR code atau status login).

```http
POST /cek-status
Content-Type: application/json

{
  "sessionId": "session1"
}
```

**Response (belum login, QR ready):**
```json
{
  "status": true,
  "logged_in": false,
  "qr_ready": true,
  "qr_string": "data:image/png;base64,..."
}
```

**Response (sudah login):**
```json
{
  "status": true,
  "logged_in": true,
  "user": {
    "id": "628123456789:1@s.whatsapp.net",
    "name": "My WhatsApp"
  }
}
```

---

#### 5. Send Message
Mengirim pesan WhatsApp.

```http
POST /send-message
Content-Type: application/json

{
  "sessionId": "session1",
  "number": "081234567890",
  "message": "Hello World!"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Message sent successfully",
  "response": {
    "key": {...},
    "message": {...}
  }
}
```

---

#### 6. Delete Session
Menghapus session dan cleanup credentials.

```http
POST /delete-session
Content-Type: application/json

{
  "sessionId": "session1"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Session \"session1\" deleted successfully"
}
```

---

## Usage Example

### 1. Create dan Login Session

```bash
# 1. Create session
curl -X POST http://localhost:8033/create-session \
  -H "Content-Type: application/json" \
  -d '{"sessionId": "wa1"}'

# 2. Get QR Code
curl -X POST http://localhost:8033/cek-status \
  -H "Content-Type: application/json" \
  -d '{"sessionId": "wa1"}'

# Scan QR code dengan WhatsApp
```

### 2. Send Message

```bash
curl -X POST http://localhost:8033/send-message \
  -H "Content-Type: application/json" \
  -d '{
    "sessionId": "wa1",
    "number": "081234567890",
    "message": "Hello from Multi-Session Service!"
  }'
```

### 3. Multiple Sessions

```bash
# Create session 2
curl -X POST http://localhost:8033/create-session \
  -H "Content-Type: application/json" \
  -d '{"sessionId": "wa2"}'

# List all sessions
curl http://localhost:8033/list-sessions
```

## Environment Variables

Anda bisa set PORT via environment variable:

```bash
PORT=8080 npm start
```

## File Structure

```
.
├── index.js              # Main application file
├── config.js             # Webhook configuration
├── package.json          # Project dependencies
├── .gitignore           # Git ignore rules
├── README.md            # Documentation
├── auth_mdl_session1/   # Session 1 credentials (auto-created)
├── auth_mdl_session2/   # Session 2 credentials (auto-created)
└── node_modules/        # Dependencies
```

## Notes

- Setiap session memiliki auth directory sendiri: `auth_mdl_{sessionId}`
- Sessions akan auto-reconnect jika koneksi terputus
- Service menangani graceful shutdown (Ctrl+C)
- Webhook akan menerima update message dengan `sessionId`

## Troubleshooting

### Error: Session not found
- Pastikan session sudah dibuat dengan `/create-session`

### Error: Session is not logged in
- Scan QR code terlebih dahulu via `/cek-status`

### Connection issues
- Check internet connection
- Service akan auto-reconnect

## License

ISC

## Author

MDL Team
