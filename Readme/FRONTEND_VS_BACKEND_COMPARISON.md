# 🔍 Perbandingan Frontend vs Backend Google Calendar API

## ✅ Frontend Bisa, Backend Tidak

Jika frontend dengan hardcoded API key bisa bekerja, berarti:
1. ✅ API key valid
2. ✅ Google Calendar API sudah di-enable
3. ✅ Calendar ID yang digunakan frontend pasti benar

## 🔍 Yang Perlu Dicek

### 1. Calendar ID yang Digunakan Frontend

Cek di frontend code, Calendar ID apa yang digunakan? Kemungkinan:
- `id.indonesian#holiday@group.v.calendar.google.com` (raw)
- `id.indonesian%23holiday%40group.v.calendar.google.com` (encoded)
- `en.indonesian#holiday@group.v.calendar.google.com` (English version)

### 2. Format Request di Frontend

Cek bagaimana frontend membuat request:
- URL format
- Parameter yang dikirim
- Encoding Calendar ID

### 3. Test Langsung dengan API Key

Test di browser dengan API key yang sama:

```
https://www.googleapis.com/calendar/v3/calendars/id.indonesian%23holiday%40group.v.calendar.google.com/events?key=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk&timeMin=2025-01-01T00:00:00Z&timeMax=2025-12-31T23:59:59Z&singleEvents=true&orderBy=startTime
```

## 🔧 Solusi Cepat

### Opsi 1: Gunakan Calendar ID yang Sama dengan Frontend

Jika frontend menggunakan Calendar ID tertentu, gunakan yang sama di backend `.env`:

```env
GOOGLE_CALENDAR_ID=<sama dengan yang digunakan frontend>
```

### Opsi 2: Test dengan Beberapa Format

Coba beberapa format Calendar ID:

**Format 1 (Raw):**
```env
GOOGLE_CALENDAR_ID=id.indonesian#holiday@group.v.calendar.google.com
```

**Format 2 (English):**
```env
GOOGLE_CALENDAR_ID=en.indonesian#holiday@group.v.calendar.google.com
```

**Format 3 (Sudah Encoded):**
```env
GOOGLE_CALENDAR_ID=id.indonesian%23holiday%40group.v.calendar.google.com
```

Setelah update, clear config:
```bash
php artisan config:clear
php artisan cache:clear
```

### Opsi 3: Cek Log Backend

Cek log untuk melihat URL yang digunakan:

```bash
tail -f storage/logs/laravel.log
```

Cari:
- `Google Calendar API Request` → cek URL
- `Google Calendar API Error` → cek error detail

## 🎯 Action Items

1. **Cek Calendar ID di Frontend** → Copy Calendar ID yang digunakan frontend
2. **Update .env** → Gunakan Calendar ID yang sama
3. **Clear Config** → `php artisan config:clear`
4. **Test Endpoint** → `GET /api/calendar/national-holidays?year=2025`
5. **Cek Log** → Lihat detail error jika masih gagal

## 📝 Catatan

- API key restrictions mungkin perlu waktu 5 menit untuk apply
- Pastikan Calendar ID format sama dengan yang digunakan frontend
- Backend akan otomatis encode Calendar ID saat request


