# 🧪 **GUIDE TESTING DOWNLOAD FILE ARRANGEMENT - POSTMAN**

## 📋 **STEP-BY-STEP TESTING DI POSTMAN**

### **STEP 1: LOGIN SEBAGAI PRODUCER**

**Endpoint:**
```
POST http://localhost:8000/api/login
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
  "login": "producer@example.com",
  "password": "password"
}
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "user": {
      "id": 1,
      "name": "Producer Name",
      "email": "producer@example.com",
      "role": "Producer"
    }
  }
}
```

**⚠️ IMPORTANT:** Copy token dari response untuk digunakan di step berikutnya!

---

### **STEP 2: GET PENDING APPROVALS (UNTUK DAPAT ARRANGEMENT ID)**

**Endpoint:**
```
GET http://localhost:8000/api/live-tv/producer/approvals
```

**Headers:**
```
Authorization: Bearer {TOKEN_DARI_STEP_1}
Accept: application/json
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "music_arrangements": [
      {
        "id": 1,
        "episode_id": 1,
        "song_title": "Daniel Caesar - Who Knows",
        "singer_name": "John Doe",
        "status": "arrangement_submitted",
        "file_path": "music-arrangements/xxxxx.mp3",
        "file_name": "Daniel Caesar - Who Knows (Official Lyric Video) [glscfhJyZHo].mp3",
        "file_url": "http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/1/file",
        "file_size": 5242880,
        "mime_type": "audio/mpeg",
        "created_by": 2,
        "episode": {...},
        "createdBy": {...}
      }
    ]
  }
}
```

**⚠️ IMPORTANT:** Copy `id` dari arrangement (contoh: `1`) untuk digunakan di step berikutnya!

---

### **STEP 3: TEST DOWNLOAD FILE ARRANGEMENT**

**Endpoint:**
```
GET http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/{ARRANGEMENT_ID}/file
```

**⚠️ PENTING:** Ganti `{ARRANGEMENT_ID}` dengan ID yang benar dari response Step 2!

**Contoh:**
Dari response Step 2, ambil `id` dari `music_arrangements`:
```json
{
  "id": 32,  // <-- INI ID YANG BENAR
  ...
}
```

Maka URL-nya:
```
GET http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/32/file
```

**❌ SALAH:**
```
GET http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/{1}/file
GET http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/1/file  // Jika ID bukan 1
```

**✅ BENAR:**
```
GET http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/32/file  // Gunakan ID yang benar
```

**Headers:**
```
Authorization: Bearer {TOKEN_DARI_STEP_1}
Accept: audio/mpeg, audio/*, */*
```

**⚠️ PENTING:**
- **JANGAN** set `Content-Type` di headers (biarkan Postman auto-detect)
- **Accept** header bisa di-set ke `audio/mpeg` atau `*/*`
- Pastikan menggunakan **Bearer Token** dari login

---

### **STEP 4: VERIFY RESPONSE**

**Expected Response:**
- **Status Code:** `200 OK`
- **Content-Type:** `audio/mpeg` (atau sesuai mime_type file)
- **Body:** File audio binary (bisa langsung di-download/play di Postman)

**Headers yang Diterima:**
```
Content-Type: audio/mpeg
Content-Disposition: inline; filename="Daniel Caesar - Who Knows (Official Lyric Video) [glscfhJyZHo].mp3"
Accept-Ranges: bytes
Content-Length: 5242880
```

---

## 🔍 **ALTERNATIVE: TEST DENGAN MUSIC ARRANGER**

Jika ingin test sebagai Music Arranger (creator):

### **STEP 1: LOGIN SEBAGAI MUSIC ARRANGER**

**Endpoint:**
```
POST http://localhost:8000/api/login
```

**Body:**
```json
{
  "login": "musicarranger@example.com",
  "password": "password"
}
```

### **STEP 2: GET MY ARRANGEMENTS**

**Endpoint:**
```
GET http://localhost:8000/api/live-tv/roles/music-arranger/arrangements
```

**Headers:**
```
Authorization: Bearer {TOKEN}
Accept: application/json
```

### **STEP 3: TEST DOWNLOAD FILE**

Sama seperti Step 3 di atas, gunakan `id` dari response Step 2.

---

## ✅ **CHECKLIST TESTING**

- [ ] Login berhasil dan dapat token
- [ ] Get approvals/arrangements berhasil dan dapat arrangement ID
- [ ] Download file endpoint return status `200 OK`
- [ ] Response headers correct (`Content-Type: audio/mpeg`)
- [ ] File bisa di-download di Postman
- [ ] File bisa di-play di Postman (jika support audio preview)

---

## 🐛 **TROUBLESHOOTING**

### **Error 401 Unauthorized**
- **Penyebab:** Token tidak valid atau expired
- **Solusi:** Login ulang dan dapat token baru

### **Error 403 Forbidden**
- **Penyebab:** User tidak punya akses ke arrangement tersebut
- **Solusi:** 
  - Pastikan login sebagai Producer dari ProductionTeam yang sama
  - Atau login sebagai Music Arranger yang create arrangement tersebut

### **Error 404 Not Found**
- **Penyebab:** Arrangement ID tidak ditemukan atau file tidak ada
- **Solusi:** 
  - Cek arrangement ID benar
  - Cek file_path di database ada
  - Cek file benar-benar ada di `storage/app/public/music-arrangements/`

### **Error 500 Internal Server Error**
- **Penyebab:** File tidak ada di storage
- **Solusi:** 
  - Cek file ada di `storage/app/public/music-arrangements/`
  - Pastikan storage link sudah dibuat: `php artisan storage:link`

---

## 📝 **CONTOH FULL REQUEST DI POSTMAN**

### **Collection Variables (Opsional):**
```
base_url = http://localhost:8000
token = (akan di-set setelah login)
arrangement_id = (akan di-set setelah get approvals)
```

### **Request 1: Login**
```
POST {{base_url}}/api/login
Body: {
  "login": "producer@example.com",
  "password": "password"
}
```

### **Request 2: Get Approvals**
```
GET {{base_url}}/api/live-tv/producer/approvals
Headers: {
  "Authorization": "Bearer {{token}}"
}
```

### **Request 3: Download File**
```
GET {{base_url}}/api/live-tv/roles/music-arranger/arrangements/{{arrangement_id}}/file
Headers: {
  "Authorization": "Bearer {{token}}",
  "Accept": "audio/mpeg"
}
```

---

## 🎯 **TESTING SCENARIOS**

### **Scenario 1: Producer Download File**
1. Login sebagai Producer
2. Get approvals → dapat arrangement ID
3. Download file dengan arrangement ID
4. ✅ Should return audio file

### **Scenario 2: Music Arranger Download Own File**
1. Login sebagai Music Arranger
2. Get my arrangements → dapat arrangement ID
3. Download file dengan arrangement ID
4. ✅ Should return audio file

### **Scenario 3: Unauthorized Access**
1. Login sebagai User lain (bukan Producer/Music Arranger)
2. Try download file arrangement ID yang bukan miliknya
3. ✅ Should return 403 Forbidden

### **Scenario 4: File Not Found**
1. Login sebagai Producer
2. Try download dengan arrangement ID yang tidak ada
3. ✅ Should return 404 Not Found

---

## 📌 **NOTES**

1. **File URL di Response API:**
   - `file_url` sekarang menggunakan endpoint: `/api/live-tv/roles/music-arranger/arrangements/{id}/file`
   - Frontend bisa langsung pakai `file_url` ini untuk audio player

2. **Audio Streaming:**
   - Endpoint support range requests (`Accept-Ranges: bytes`)
   - Bisa untuk audio streaming di browser

3. **File Name dengan Karakter Khusus:**
   - Endpoint handle encoding otomatis
   - Tidak perlu manual URL encode di frontend

---

**Last Updated:** {{ date('Y-m-d H:i:s') }}

