# Google AI Pro — Sales Platform

> Platform penjualan langganan **Google AI Pro 12 bulan** dengan antarmuka admin via **Telegram Bot** dan sistem pembayaran **QRIS dinamis**.

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| 🧙 Setup Wizard | Konfigurasi pertama kali (DB → Telegram → Produk) tanpa edit file |
| 🏠 Landing Page | Google-style minimalis, responsive, premium |
| 🛒 Checkout Flow | Pilih metode aktivasi (SSO / Link) → QRIS payment |
| 💳 QRIS Dinamis | Admin upload foto QRIS → sistem decode → generate QR dengan nominal otomatis |
| 🤖 Telegram Admin | Full admin via bot: order, stats, settings, broadcast |
| 📊 Traffic Logging | Setiap kunjungan & aksi dicatat + notif Telegram |
| 🔒 Security | Prepared statements, .htaccess block, CSRF-safe |

---

## 🚀 Quick Start

### 1. Clone & Letakkan di Laragon
```bash
cd C:\laragon\www
git clone https://github.com/NixxyHorwitz/geminipro.git googlepro
```

### 2. Buat Database
```sql
CREATE DATABASE googlepro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Install Schema
```
Akses: http://localhost/googlepro/setup.php
```

### 4. Jalankan Setup Wizard

**Step 1 — Database**
- Host: `127.0.0.1`
- Database: `googlepro`
- Username: `root`
- Password: *(kosong untuk Laragon default)*

**Step 2 — Telegram**
1. Buat bot di [@BotFather](https://t.me/botfather) → copy token
2. Cari chat ID Anda di [@userinfobot](https://t.me/userinfobot)
3. Masukkan URL publik website (harus HTTPS untuk production)

**Step 3 — Produk**
- Set harga (default: Rp 309.000)
- Google OAuth (opsional, untuk fitur SSO)

---

## 🤖 Telegram Bot Commands

```
/start      → Menu utama dengan tombol inline
/orders     → Daftar order (pending/semua)
/stats      → Statistik revenue & traffic
/qris       → Upload QRIS baru
/settings   → Ubah harga, URL, sync webhook
/broadcast  → Pesan ke semua buyer
/help       → Panduan perintah
```

### Admin Flow: Konfirmasi Order
```
Bot notif masuk → klik ✅ Konfirmasi / ❌ Tolak
→ Jika tolak: ketik alasan → order ter-reject
→ Jika konfirmasi: buyer mendapat aktivasi
```

---

## 💳 QRIS Dinamis

Sistem menggunakan **EMV QR Code** standard (QRIS Nasional):

1. Admin kirim **foto QRIS** ke bot
2. Sistem decode QR dari gambar (via `zbarimg`)
3. Parse string EMV → ubah nominal (tag `54`) → recalculate CRC-16
4. Saat checkout, generate QR baru dengan nominal harga produk
5. Tampil di halaman pembayaran sebagai gambar

> **Jika zbarimg tidak ada:** sistem minta admin paste raw string QRIS manual (bisa didapat via app scanner QR).

---

## 📁 Struktur Direktori

```
googlepro/
├── index.php           ← Landing page
├── setup.php           ← Setup wizard
├── checkout.php        ← Pembayaran QRIS
├── webhook.php         ← Telegram webhook
├── bootstrap.php       ← App init + autoloader
├── .env                ← Config (di-generate setup wizard, tidak di-commit)
├── assets/css/         ← Stylesheets
├── src/                ← Core classes
│   ├── Config.php
│   ├── Database.php
│   ├── TelegramBot.php
│   ├── QrisHelper.php
│   ├── Logger.php
│   └── Order.php
├── bot/                ← Bot logic
│   ├── BotHandler.php
│   └── commands/
├── install/schema.sql  ← Database schema
└── uploads/            ← QRIS images (tidak di-commit)
```

---

## 🔧 Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache dengan `mod_rewrite`
- Laragon (dev) atau hosting dengan HTTPS (production)
- Telegram Bot Token
- *(Opsional)* `zbarimg` untuk auto-decode QRIS dari foto

---

## 🔐 Security

- `.env` ter-exclude dari git
- `src/` dan `bot/` tidak bisa diakses langsung via HTTP (`.htaccess`)
- `uploads/` di-block akses publik
- Semua query menggunakan **PDO prepared statements**
- Webhook divalidasi dengan **secret token**

---

## 📞 Support

Hubungi admin via Telegram yang terdaftar di setup.
