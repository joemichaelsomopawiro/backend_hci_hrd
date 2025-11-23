# 📅 Format Calendar ID untuk Google Calendar API

## ❌ Masalah: Error 404 "Not Found"

Error 404 biasanya terjadi karena:
1. Calendar ID format salah
2. Calendar ID encoding salah
3. Calendar ID tidak valid

## ✅ Format Calendar ID yang Benar

### Untuk Indonesia Public Holidays:

**Format Raw (untuk .env):**
```env
GOOGLE_CALENDAR_ID=id.indonesian#holiday@group.v.calendar.google.com
```

**Format Encoded (untuk URL):**
```
id.indonesian%23holiday%40group.v.calendar.google.com
```

**Penjelasan:**
- `#` → `%23`
- `@` → `%40`

### Alternatif Calendar ID:

1. **Indonesia (id.indonesian):**
   ```
   id.indonesian#holiday@group.v.calendar.google.com
   ```

2. **English Indonesia (en.indonesian):**
   ```
   en.indonesian%23holiday%40group.v.calendar.google.com
   ```

3. **Format sudah encoded (jika raw tidak bekerja):**
   ```
   id.indonesian%23holiday%40group.v.calendar.google.com
   ```

## 🔧 Cara Test Calendar ID

### 1. Test di Browser

Buka URL ini di browser (ganti YOUR_API_KEY):
```
https://www.googleapis.com/calendar/v3/calendars/id.indonesian%23holiday%40group.v.calendar.google.com/events?key=YOUR_API_KEY&timeMin=2025-01-01T00:00:00Z&timeMax=2025-12-31T23:59:59Z&singleEvents=true&orderBy=startTime
```

**Jika berhasil:** Akan return JSON dengan events
**Jika error 404:** Calendar ID salah atau tidak valid

### 2. Test dengan cURL

```bash
curl "https://www.googleapis.com/calendar/v3/calendars/id.indonesian%23holiday%40group.v.calendar.google.com/events?key=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk&timeMin=2025-01-01T00:00:00Z&timeMax=2025-12-31T23:59:59Z&singleEvents=true&orderBy=startTime"
```

### 3. Test via Backend Endpoint

```bash
GET http://localhost:8000/api/calendar/test-google-connection
```

## 📝 Setup di .env

**Gunakan format RAW (tidak encoded):**
```env
GOOGLE_CALENDAR_API_KEY=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk
GOOGLE_CALENDAR_ID=id.indonesian#holiday@group.v.calendar.google.com
```

**Backend akan otomatis encode saat request ke Google API.**

## 🔍 Debugging

### Cek Log Laravel

```bash
tail -f storage/logs/laravel.log
```

Cari log:
- `Google Calendar API Request` → cek URL dan encoded_calendar_id
- `Google Calendar API Error` → cek error message

### Cek Response Error

Jika masih error 404, coba:
1. Pastikan API key valid
2. Pastikan Google Calendar API sudah di-enable
3. Coba Calendar ID alternatif
4. Test langsung di browser dengan URL di atas

## ✅ Checklist

- [ ] API key sudah benar di `.env`
- [ ] Calendar ID format benar (raw, tidak encoded)
- [ ] Sudah clear config: `php artisan config:clear`
- [ ] Server sudah di-restart
- [ ] Test di browser dengan URL langsung
- [ ] Cek log untuk detail error


