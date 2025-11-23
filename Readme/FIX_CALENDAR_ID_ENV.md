# 🔧 Fix Calendar ID di .env

## ❌ Masalah

Dari log, Calendar ID yang digunakan hanya: `id.indonesian` (tidak lengkap!)

Seharusnya: `id.indonesian#holiday@group.v.calendar.google.com`

## 🔍 Penyebab

Karakter `#` di file `.env` dianggap sebagai **komentar** di Laravel, sehingga bagian setelah `#` diabaikan.

## ✅ Solusi

### Opsi 1: Quote Calendar ID (RECOMMENDED)

Di file `.env`, tambahkan **quotes** (tanda kutip) di sekitar Calendar ID:

```env
GOOGLE_CALENDAR_API_KEY=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk
GOOGLE_CALENDAR_ID="id.indonesian#holiday@group.v.calendar.google.com"
```

**PENTING:** Gunakan **double quotes** (`"`) bukan single quotes (`'`)

### Opsi 2: Gunakan Format Encoded

Atau gunakan format yang sudah di-encode:

```env
GOOGLE_CALENDAR_API_KEY=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk
GOOGLE_CALENDAR_ID=id.indonesian%23holiday%40group.v.calendar.google.com
```

Tapi ini tidak direkomendasikan karena backend akan encode lagi.

## 🔧 Langkah Perbaikan

### 1. Update .env

Tambahkan quotes di Calendar ID:

```env
GOOGLE_CALENDAR_ID="id.indonesian#holiday@group.v.calendar.google.com"
```

### 2. Clear Config

```bash
php artisan config:clear
php artisan cache:clear
```

### 3. Restart Server

Restart server Laravel agar perubahan `.env` ter-load.

### 4. Test

```bash
GET http://localhost:8000/api/calendar/national-holidays?year=2025
```

### 5. Cek Log

```bash
tail -f storage/logs/laravel.log
```

Cari: `GoogleCalendarService initialized` → cek `calendar_id` harus lengkap

## ✅ Verifikasi

Setelah update, cek di log:

```
[INFO] GoogleCalendarService initialized
{
  "calendar_id": "id.indonesian#holiday@group.v.calendar.google.com",
  "calendar_id_length": 47,
  "has_api_key": true
}
```

Jika `calendar_id_length` kurang dari 40, berarti masih tidak lengkap.

## 📝 Catatan

- **JANGAN** hapus quotes setelah update
- **JANGAN** tambahkan spasi di dalam quotes
- Pastikan tidak ada karakter aneh sebelum atau sesudah Calendar ID


