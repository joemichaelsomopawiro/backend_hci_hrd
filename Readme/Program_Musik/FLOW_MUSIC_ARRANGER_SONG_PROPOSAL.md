# Flow Music Arranger - Pilih Lagu, Penyanyi & Ajukan ke Producer

## ✅ STATUS: **SUDAH LENGKAP & READY**

Setelah Music Arranger di-assign ke team/episode, dia bisa melakukan:

1. ✅ **Pilih Lagu** (dari database atau input manual)
2. ✅ **Pilih Penyanyi** (opsional - dari database atau input manual)
3. ✅ **Ajukan ke Producer**

---

## 📋 ENDPOINT YANG TERSEDIA

### 1. **Get Available Songs**
**Endpoint:** 
- `GET /api/live-tv/music-arranger/songs` (Alternative path)
- `GET /api/live-tv/roles/music-arranger/songs` ✅ (Recommended)

**Fungsi:** Get list semua lagu yang tersedia di database

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Amazing Grace",
      "status": "available",
      "created_at": "2026-01-27T10:00:00.000000Z"
    }
  ]
}
```

**Tersedia di Postman:** ✅ Yes

---

### 2. **Get Available Singers**
**Endpoint:**
- `GET /api/live-tv/music-arranger/singers` (Alternative path)
- `GET /api/live-tv/roles/music-arranger/singers` ✅ (Recommended)

**Fungsi:** Get list semua penyanyi (users dengan role 'Singer') yang tersedia

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "Singer"
    }
  ]
}
```

**Tersedia di Postman:** ✅ Yes

---

### 3. **Create Arrangement (Pilih Lagu & Penyanyi)**
**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements`

**Fungsi:** Buat arrangement baru dengan memilih lagu dan penyanyi (opsional)

**Request Body (Form Data atau JSON):**
```json
{
  "episode_id": 1,                    // Required
  "song_id": 5,                       // Optional: ID lagu dari database
  "song_title": "Lagu Baru",          // Required if song_id not provided
  "singer_id": 10,                    // Optional: ID penyanyi dari database
  "singer_name": "Penyanyi",          // Optional: Nama penyanyi (manual input)
  "arrangement_notes": "Catatan...",  // Optional
  "file": null                        // Optional: File audio (jika ada, status = 'draft')
}
```

**Keterangan:**
- **Song:** Bisa pilih dari database (`song_id`) atau input manual (`song_title`)
  - Jika input manual dan belum ada di database, otomatis dibuat song baru
- **Singer:** Opsional. Bisa pilih dari database (`singer_id`) atau input manual (`singer_name`)
  - Jika input manual dan belum ada, otomatis dibuat User baru dengan role 'Singer'
- **Status:**
  - Jika **tidak ada file**: Status = `song_proposal` → **Otomatis notify Producer** ✅
  - Jika **ada file**: Status = `draft` (belum di-submit)

**Response:**
```json
{
  "success": true,
  "message": "Music arrangement created successfully",
  "data": {
    "id": 1,
    "episode_id": 1,
    "song_id": 5,
    "song_title": "Lagu Baru",
    "singer_id": 10,
    "singer_name": "Penyanyi",
    "status": "song_proposal",
    "created_by": 3
  }
}
```

**Notification:**
- ✅ Producer otomatis mendapat notifikasi jika status = `song_proposal`
- Notification type: `song_proposal_submitted`

**Tersedia di Postman:** ✅ Yes

---

### 4. **Submit Song Proposal ke Producer**
**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit-song-proposal`

**Fungsi:** Submit song proposal yang sudah dibuat ke Producer untuk approval

**Request:** No body required

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "song_proposal",
    "submitted_at": "2026-01-27T10:30:00.000000Z"
  }
}
```

**Fitur:**
- ✅ Update status menjadi `song_proposal`
- ✅ Set `submitted_at` timestamp
- ✅ Producer bisa melihat di daftar pending approvals

**Catatan:**
- Jika create arrangement tanpa file, status sudah `song_proposal` dan Producer sudah di-notify saat create
- Endpoint ini berguna untuk mengubah status dari `draft` ke `song_proposal` atau resubmit

**Tersedia di Postman:** ✅ Yes

---

## 🔄 WORKFLOW LENGKAP

### **Step 1: Get Available Songs & Singers (Optional)**
```http
GET /api/live-tv/roles/music-arranger/songs
GET /api/live-tv/roles/music-arranger/singers
```

**Note:** Alternatif path juga tersedia:
- `GET /api/live-tv/music-arranger/songs`
- `GET /api/live-tv/music-arranger/singers`

### **Step 2: Create Arrangement (Pilih Lagu & Penyanyi)**
```http
POST /api/live-tv/roles/music-arranger/arrangements
Content-Type: application/json

{
  "episode_id": 1,
  "song_title": "Lagu Pilihan",
  "singer_name": "Penyanyi Favorit",  // Optional
  "arrangement_notes": "Catatan arrangement"
}
```

**Hasil:**
- ✅ Arrangement dibuat
- ✅ Status: `song_proposal`
- ✅ **Producer otomatis mendapat notifikasi** ✅

### **Step 3: Submit Song Proposal (Opsional - jika create dengan file/draft)**
```http
POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit-song-proposal
```

**Catatan:** 
- Jika create arrangement **tanpa file**, status sudah `song_proposal` dan Producer **sudah di-notify** saat create. Step ini tidak perlu.
- Step ini hanya perlu jika:
  - Create arrangement dengan file (status = `draft`) dan ingin submit ke Producer
  - Atau perlu resubmit arrangement yang sudah dibuat sebelumnya

---

## 📝 CONTOH KASUS PENGGUNAAN

### **Kasus 1: Pilih Lagu dari Database, Tanpa Penyanyi**
```json
{
  "episode_id": 1,
  "song_id": 5,
  "song_title": null,
  "singer_name": null,
  "arrangement_notes": "Menggunakan lagu dari database"
}
```

**Hasil:**
- Song diambil dari database (song_id = 5)
- Tidak ada penyanyi (opsional)
- Status: `song_proposal`
- Producer di-notify ✅

---

### **Kasus 2: Input Manual Lagu & Penyanyi**
```json
{
  "episode_id": 1,
  "song_title": "Lagu Baru Karya Sendiri",
  "singer_name": "Penyanyi Baru",
  "arrangement_notes": "Lagu original"
}
```

**Hasil:**
- Jika lagu belum ada → Otomatis dibuat song baru di database ✅
- Jika penyanyi belum ada → Otomatis dibuat User baru dengan role 'Singer' ✅
- Status: `song_proposal`
- Producer di-notify ✅

---

### **Kasus 3: Pilih dari Database (Lagu & Penyanyi)**
```json
{
  "episode_id": 1,
  "song_id": 5,
  "singer_id": 10,
  "arrangement_notes": "Pilih dari database"
}
```

**Hasil:**
- Song dan Singer diambil dari database
- Status: `song_proposal`
- Producer di-notify ✅

---

## 📚 CARA SONGS & SINGERS MASUK DATABASE

### **1. Auto-Create saat Create Arrangement** ⭐ (Cara Utama)

Saat Music Arranger create arrangement dengan input manual:

**Untuk Songs:**
- Jika Music Arranger input `song_title` yang belum ada di database
- **Otomatis dibuat Song baru** dengan:
  - `title`: dari input `song_title`
  - `status`: `'available'`
  - `created_by`: ID Music Arranger yang membuat
  - Field lain (artist, genre, lyrics, dll) bisa di-update nanti

**Untuk Singers:**
- Jika Music Arranger input `singer_name` yang belum ada di database
- **Otomatis dibuat User baru** dengan:
  - `name`: dari input `singer_name`
  - `role`: `'Singer'`
  - `email`: Auto-generate dari name (format: `name@singer.local`)
  - `password`: Default password (bisa diubah nanti)
  - `email_verified_at`: Set ke current time

**Contoh:**
```json
// Input manual song yang belum ada
POST /api/live-tv/roles/music-arranger/arrangements
{
  "episode_id": 1,
  "song_title": "Lagu Baru Karya Sendiri",  // Belum ada di DB
  "singer_name": "Penyanyi Baru"            // Belum ada di DB
}

// Hasil:
// ✅ Song "Lagu Baru Karya Sendiri" otomatis dibuat di database
// ✅ User "Penyanyi Baru" dengan role Singer otomatis dibuat
```

---

### **2. Database Seeder** (Data Awal)

Untuk data awal/sample, bisa menggunakan Database Seeder:

**Seeder yang Tersedia:**
- `SongSeeder` - Seed sample songs ke database
- `SingerSeeder` - Seed sample singers ke database
- `MusicTestDataSeeder` - Seed semua data test (songs, singers, users, dll)

**Cara Run Seeder:**
```bash
php artisan db:seed --class=SongSeeder
php artisan db:seed --class=SingerSeeder
# atau
php artisan db:seed --class=MusicTestDataSeeder
```

---

### **3. Manual via Database** (Untuk Admin/Developer)

**Saat ini TIDAK ADA endpoint khusus untuk CRUD Songs/Singers.**

Endpoint yang ada:
- ✅ `GET /api/live-tv/music-arranger/songs` - List songs (READ ONLY)
- ✅ `GET /api/live-tv/roles/music-arranger/songs` - List songs (READ ONLY) - Recommended
- ✅ `GET /api/live-tv/music-arranger/singers` - List singers (READ ONLY)
- ✅ `GET /api/live-tv/roles/music-arranger/singers` - List singers (READ ONLY) - Recommended

**Jika perlu manage Songs/Singers:**
- Via database langsung (phpMyAdmin, MySQL Workbench, dll)
- Atau perlu dibuat endpoint CRUD baru (belum ada)

**Note:** Di `routes/api.php` ada endpoint untuk CRUD songs/singers tapi **DISABLED/COMMENTED** (line 476-495). Endpoint tersebut bisa di-enable jika diperlukan.

---

## ✅ VALIDATION & SECURITY

### **Access Control:**
- ✅ Hanya Music Arranger yang di-assign ke ProductionTeam episode yang bisa create arrangement
- ✅ Validasi: `productionTeam->members()->where('role', 'musik_arr')->where('user_id', user->id)->exists()`

### **Auto-Create Features:**
- ✅ Jika `song_title` manual dan belum ada → **Auto-create Song** di database
- ✅ Jika `singer_name` manual dan belum ada → **Auto-create User dengan role 'Singer'** di database
- ✅ Auto-generate email untuk singer baru (format: `name@singer.local`)
- ✅ Auto-check duplicate (jika sudah ada, gunakan yang sudah ada)

### **Notification:**
- ✅ Producer otomatis di-notify saat create arrangement dengan status `song_proposal`
- ✅ Notification type: `song_proposal_submitted`

---

## 🔧 REKOMENDASI (Jika Perlu Endpoint Manage Songs/Singers)

Jika diperlukan endpoint untuk manage Songs dan Singers secara manual, bisa ditambahkan:

**Songs Management:**
```php
POST   /api/live-tv/songs              // Create song
GET    /api/live-tv/songs              // List songs
GET    /api/live-tv/songs/{id}         // Get song detail
PUT    /api/live-tv/songs/{id}         // Update song
DELETE /api/live-tv/songs/{id}         // Delete song
```

**Singers Management:**
```php
POST   /api/live-tv/singers            // Create singer (User dengan role Singer)
GET    /api/live-tv/singers            // List singers
GET    /api/live-tv/singers/{id}       // Get singer detail
PUT    /api/live-tv/singers/{id}       // Update singer
DELETE /api/live-tv/singers/{id}       // Delete singer
```

**Access Control:**
- Manager Program / Producer bisa manage songs & singers
- Music Arranger hanya bisa READ (view list)

**Status:** ⚠️ Belum diimplementasikan, tapi bisa ditambahkan jika diperlukan.

---

## 📋 POSTMAN COLLECTION

Semua endpoint sudah tersedia di Postman Collection:

1. ✅ **Get Available Songs** - `GET /api/live-tv/roles/music-arranger/songs` (Recommended) atau `GET /api/live-tv/music-arranger/songs`
2. ✅ **Get Available Singers** - `GET /api/live-tv/roles/music-arranger/singers` (Recommended) atau `GET /api/live-tv/music-arranger/singers`
3. ✅ **Create Arrangement** - `POST /api/live-tv/roles/music-arranger/arrangements`
4. ✅ **Submit Song Proposal** - `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit-song-proposal`

**Collection:** `Postman_Collection_HCI_HRD_Complete_Flow.json`
**Folder:** `Music Arranger`

---

## 🎯 KESIMPULAN

### ✅ **Fitur Sudah Lengkap:**

1. ✅ **Pilih Lagu** - Bisa dari database atau input manual
2. ✅ **Pilih Penyanyi** - Opsional, bisa dari database atau input manual
3. ✅ **Ajukan ke Producer** - Otomatis notify saat create arrangement

### ✅ **Yang Sudah Bekerja:**

- ✅ Auto-create Song jika belum ada
- ✅ Auto-create Singer (User) jika belum ada
- ✅ Auto-notify Producer saat create arrangement
- ✅ Validation & access control lengkap
- ✅ Postman collection sudah tersedia

### 📋 **Cara Songs & Singers Masuk Database:**

1. **⭐ Auto-Create (Cara Utama):**
   - Saat Music Arranger create arrangement dengan input manual
   - Jika song_title/singer_name belum ada → Otomatis dibuat di database

2. **Database Seeder:**
   - Untuk data awal/sample
   - Run: `php artisan db:seed --class=SongSeeder`

3. **Manual via Database:**
   - Saat ini **tidak ada endpoint CRUD khusus**
   - Hanya ada endpoint GET untuk list songs/singers
   - Manage songs/singers bisa via database langsung

**Status:** ✅ **READY FOR TESTING**

---

**Last Updated:** 2026-01-27
