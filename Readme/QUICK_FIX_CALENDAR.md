# ⚡ Quick Fix - Calendar API Error 404

## ✅ Informasi dari Frontend

**Calendar ID:** `id.indonesian#holiday@group.v.calendar.google.com`  
**API Key:** `AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk`

## 🔧 Langkah Perbaikan

### 1. Pastikan .env Sudah Benar

```env
GOOGLE_CALENDAR_API_KEY=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk
GOOGLE_CALENDAR_ID=id.indonesian#holiday@group.v.calendar.google.com
```

### 2. Clear Config Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### 3. Test Langsung di Browser

Buka URL ini di browser untuk verifikasi API key dan Calendar ID:

```
https://www.googleapis.com/calendar/v3/calendars/id.indonesian%23holiday%40group.v.calendar.google.com/events?key=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk&timeMin=2025-01-01T00:00:00Z&timeMax=2025-12-31T23:59:59Z&singleEvents=true&orderBy=startTime
```

**Jika berhasil:** Akan muncul JSON dengan events  
**Jika error 404:** Calendar ID atau API key tidak valid

### 4. Test Backend Endpoint

```bash
GET http://localhost:8000/api/calendar/national-holidays?year=2025
```

### 5. Cek Log Laravel

```bash
tail -f storage/logs/laravel.log
```

Cari:
- `Google Calendar API Request` → cek URL yang digunakan
- `Google Calendar API Error` → cek error detail

## 🐛 Jika Masih Error 404

### Kemungkinan Masalah:

1. **API Key Restrictions** - Perlu waktu 5 menit untuk apply
2. **Calendar ID Format** - Pastikan tidak ada spasi atau karakter aneh
3. **Config Cache** - Pastikan sudah clear semua cache

### Solusi:

1. **Tunggu 5 menit** setelah update API key restrictions
2. **Test di browser** dengan URL di atas
3. **Cek log** untuk detail error
4. **Restart server** Laravel

## ✅ Checklist

- [ ] `.env` sudah benar (Calendar ID dan API Key)
- [ ] Sudah clear config: `php artisan config:clear`
- [ ] Test di browser dengan URL Google Calendar API langsung
- [ ] Test endpoint backend: `/api/calendar/national-holidays?year=2025`
- [ ] Cek log untuk detail error
- [ ] Server sudah di-restart


