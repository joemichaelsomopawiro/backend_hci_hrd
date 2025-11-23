# 🔍 Panduan Debugging Calendar API

## ❌ Masalah: Kalender Terus Loading, Data Tidak Muncul

### 🔧 Langkah-langkah Debugging

#### 1. **Cek Environment Variables**

Pastikan file `.env` sudah memiliki:

```env
GOOGLE_CALENDAR_API_KEY=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk
GOOGLE_CALENDAR_ID=id.indonesian#holiday@group.v.calendar.google.com
```

**Cek apakah sudah ada:**
```bash
# Di terminal backend
php artisan tinker
>>> config('services.google.calendar_api_key')
# Harus return: "AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk"
```

**Jika null/kosong:**
- Pastikan sudah menambahkan ke `.env`
- Jalankan: `php artisan config:clear`
- Restart server Laravel

---

#### 2. **Test Endpoint Langsung**

Test endpoint di browser atau Postman:

```bash
GET http://localhost:8000/api/calendar/national-holidays?year=2025
Headers:
  Authorization: Bearer YOUR_TOKEN
  Accept: application/json
```

**Response yang diharapkan:**
```json
{
  "success": true,
  "data": [
    {
      "date": "2025-01-01",
      "name": "Tahun Baru",
      "description": null,
      "type": "national",
      "is_active": true,
      "google_event_id": "..."
    }
  ],
  "year": 2025,
  "count": 15
}
```

**Jika error:**
- Cek response error message
- Cek log di `storage/logs/laravel.log`

---

#### 3. **Cek Log Laravel**

```bash
# Buka file log
tail -f storage/logs/laravel.log

# Atau di Windows
type storage\logs\laravel.log
```

**Cari error seperti:**
- `Google Calendar API key not configured`
- `Google Calendar API Error`
- `Calendar Controller Error`

---

#### 4. **Test Koneksi Google Calendar**

```bash
GET http://localhost:8000/api/calendar/test-google-connection
Headers:
  Authorization: Bearer YOUR_TOKEN
```

**Response sukses:**
```json
{
  "success": true,
  "message": "Google Calendar API connection successful",
  "status_code": 200,
  "has_api_key": true,
  "calendar_id": "id.indonesian#holiday@group.v.calendar.google.com"
}
```

**Jika gagal:**
- Cek apakah API key valid
- Cek apakah Google Calendar API sudah di-enable di Google Cloud Console
- Cek apakah API key tidak di-restrict

---

#### 5. **Cek Frontend Console**

Buka browser console (F12) dan cek:

**Jika ada error:**
```javascript
❌ Error fetching national holidays from API: ...
```

**Kemungkinan masalah:**
- Endpoint tidak ditemukan (404)
- Authentication error (401)
- Server error (500)
- Network error

**Cek Network Tab:**
- Request ke `/api/calendar/national-holidays?year=2025`
- Status code: harus 200
- Response body: harus ada `success: true`

---

#### 6. **Clear Cache**

Jika sudah update `.env` atau config:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

**Restart server:**
```bash
# Stop server (Ctrl+C)
# Start lagi
php artisan serve
```

---

#### 7. **Test dengan cURL**

```bash
curl -X GET "http://localhost:8000/api/calendar/national-holidays?year=2025" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -v
```

**Cek:**
- Status code (harus 200)
- Response body
- Error message jika ada

---

## ✅ Checklist Troubleshooting

- [ ] API key sudah ditambahkan ke `.env`
- [ ] Sudah jalankan `php artisan config:clear`
- [ ] Server Laravel sudah di-restart
- [ ] Endpoint `/api/calendar/national-holidays` bisa diakses
- [ ] Response format sesuai (ada `success: true` dan `data: [...]`)
- [ ] Tidak ada error di `storage/logs/laravel.log`
- [ ] Frontend console tidak ada error
- [ ] Network request status code 200

---

## 🐛 Common Issues

### Issue 1: "Google Calendar API key not configured"

**Solusi:**
1. Tambahkan ke `.env`:
   ```env
   GOOGLE_CALENDAR_API_KEY=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk
   ```
2. Clear config: `php artisan config:clear`
3. Restart server

---

### Issue 2: "Google Calendar API Error: API key not valid"

**Solusi:**
1. Cek apakah API key benar di Google Cloud Console
2. Cek apakah Google Calendar API sudah di-enable
3. Cek apakah API key tidak di-restrict terlalu ketat

---

### Issue 3: "Failed to fetch from Google Calendar API"

**Solusi:**
1. Cek koneksi internet
2. Cek apakah Google Calendar API service sedang down
3. Cek log untuk detail error

---

### Issue 4: Frontend terus loading

**Kemungkinan:**
- Backend return error tapi frontend tidak handle
- Response format tidak sesuai
- Network timeout

**Solusi:**
1. Cek Network tab di browser
2. Cek response body
3. Cek apakah ada error di console
4. Pastikan endpoint return `success: true`

---

## 📞 Jika Masih Error

1. **Cek log lengkap:**
   ```bash
   tail -100 storage/logs/laravel.log
   ```

2. **Test endpoint langsung:**
   ```bash
   curl -v http://localhost:8000/api/calendar/national-holidays?year=2025
   ```

3. **Cek apakah API key valid:**
   - Buka Google Cloud Console
   - Test API key di browser: `https://www.googleapis.com/calendar/v3/calendars/id.indonesian%23holiday%40group.v.calendar.google.com/events?key=YOUR_API_KEY&timeMin=2025-01-01T00:00:00Z&timeMax=2025-12-31T23:59:59Z`


