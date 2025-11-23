# 🧪 Test Google Calendar API

## ✅ Setup Sudah Benar

File `.env` sudah memiliki:
```env
GOOGLE_CALENDAR_API_KEY=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk
GOOGLE_CALENDAR_ID=id.indonesian#holiday@group.v.calendar.google.com
```

## 🔍 Test Langsung di Browser

### 1. Test Calendar ID di Browser

Buka URL ini di browser (ganti YOUR_API_KEY dengan API key Anda):

```
https://www.googleapis.com/calendar/v3/calendars/id.indonesian%23holiday%40group.v.calendar.google.com/events?key=AIzaSyDJTsYUNl6X1KXWj8T6s9uq9pJUB8RgDNk&timeMin=2025-01-01T00:00:00Z&timeMax=2025-12-31T23:59:59Z&singleEvents=true&orderBy=startTime
```

**Jika berhasil:** Akan muncul JSON dengan events
**Jika error 404:** Calendar ID tidak valid atau tidak ada

### 2. Alternatif Calendar ID untuk Test

Coba Calendar ID alternatif:

**Option 1 (Indonesia - id.indonesian):**
```
id.indonesian%23holiday%40group.v.calendar.google.com
```

**Option 2 (English - en.indonesian):**
```
en.indonesian%23holiday%40group.v.calendar.google.com
```

**Option 3 (Format lain):**
```
id.indonesian#holiday@group.v.calendar.google.com
```

## 🧪 Test via Backend Endpoint

### 1. Test Connection (Public - Tidak Perlu Auth)

```bash
GET http://localhost:8000/api/calendar/test-google-connection
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

**Response error:**
```json
{
  "success": false,
  "message": "Google Calendar API connection failed",
  "status_code": 404,
  "error": "...",
  "has_api_key": true,
  "calendar_id": "id.indonesian#holiday@group.v.calendar.google.com"
}
```

### 2. Test Get National Holidays (Public)

```bash
GET http://localhost:8000/api/calendar/national-holidays?year=2025
```

## 🔧 Troubleshooting Error 404

### Masalah: Calendar ID Tidak Ditemukan

**Solusi 1: Cek Calendar ID di Browser**

Test langsung di browser dengan URL di atas. Jika masih 404, berarti Calendar ID tidak valid.

**Solusi 2: Coba Calendar ID Alternatif**

Update di `.env`:
```env
# Coba option 1
GOOGLE_CALENDAR_ID=id.indonesian#holiday@group.v.calendar.google.com

# Atau option 2
GOOGLE_CALENDAR_ID=en.indonesian#holiday@group.v.calendar.google.com
```

Lalu:
```bash
php artisan config:clear
php artisan cache:clear
```

**Solusi 3: Cek API Key Restrictions**

1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Pilih project Anda
3. Buka "APIs & Services" > "Credentials"
4. Klik API key Anda
5. Cek "API restrictions":
   - Jika di-restrict, pastikan "Google Calendar API" sudah di-enable
   - Atau ubah ke "Don't restrict key" untuk testing

**Solusi 4: Cek Log Laravel**

```bash
tail -f storage/logs/laravel.log
```

Cari:
- `Google Calendar API Request` → cek URL yang digunakan
- `Google Calendar API Error` → cek error detail

## 📝 Checklist

- [ ] API key sudah benar di `.env`
- [ ] Calendar ID sudah benar di `.env`
- [ ] Sudah clear config: `php artisan config:clear`
- [ ] Server sudah di-restart
- [ ] Test di browser dengan URL langsung
- [ ] Test via endpoint `/api/calendar/test-google-connection`
- [ ] Cek log untuk detail error

## 🎯 Next Steps

Jika masih error 404 setelah test di browser:

1. **Calendar ID mungkin tidak valid** → Coba Calendar ID alternatif
2. **API key tidak punya akses** → Cek API key restrictions
3. **Google Calendar API belum di-enable** → Enable di Google Cloud Console


