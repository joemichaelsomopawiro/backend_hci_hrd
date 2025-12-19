# ✅ Verifikasi: Producer Edit Song Proposal

## 📋 Status Implementasi

### ✅ **SUDAH LENGKAP** - Producer bisa edit song proposal yang diajukan Music Arranger

---

## 🔍 Checklist Implementasi

### 1. ✅ Endpoint Edit Song/Singer
**File:** `routes/live_tv_api.php` (line 638)
```php
Route::put('/arrangements/{arrangementId}/edit-song-singer', [ProducerController::class, 'editArrangementSongSinger']);
```

**Endpoint:** 
- `PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer`

**Status:** ✅ **SUDAH ADA**

---

### 2. ✅ Method editArrangementSongSinger()
**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2066-2196)

**Fitur:**
- ✅ Validasi user adalah Producer
- ✅ Validasi productionTeam (support episode.productionTeam atau episode.program.productionTeam)
- ✅ Validasi status: bisa edit jika status `song_proposal`, `submitted`, atau `arrangement_submitted`
- ✅ Support edit `song_title`, `singer_name`, `song_id`, `singer_id`
- ✅ Auto-get song title dari database jika `song_id` diberikan
- ✅ Menggunakan method `producerModify()` untuk update
- ✅ Notifikasi ke Music Arranger setelah edit
- ✅ Logging untuk debugging

**Status:** ✅ **SUDAH LENGKAP**

---

### 3. ✅ Method producerModify()
**File:** `app/Models/MusicArrangement.php` (line 157)

**Fitur:**
- ✅ Menyimpan original values di `original_song_title` dan `original_singer_name`
- ✅ Menyimpan modified values di `producer_modified_song_title` dan `producer_modified_singer_name`
- ✅ Set flag `producer_modified = true`
- ✅ Set `producer_modified_at = now()`

**Status:** ✅ **SUDAH ADA**

---

### 4. ✅ Endpoint Get Songs
**File:** `routes/live_tv_api.php` (line 630)
```php
Route::get('/songs', [ProducerController::class, 'getAvailableSongs']);
```

**Endpoint:**
- `GET /api/live-tv/producer/songs`

**Method:** `ProducerController::getAvailableSongs()` (line 2165)

**Status:** ✅ **SUDAH ADA**

---

### 5. ✅ Endpoint Get Singers
**File:** `routes/live_tv_api.php` (line 631)
```php
Route::get('/singers', [ProducerController::class, 'getAvailableSingers']);
```

**Endpoint:**
- `GET /api/live-tv/producer/singers`

**Method:** `ProducerController::getAvailableSingers()` (line 2209)

**Status:** ✅ **SUDAH ADA**

---

## 📝 Request/Response Format

### Edit Song Proposal
**Request:**
```http
PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer
Authorization: Bearer {token}
Content-Type: application/json

{
  "song_title": "New Song Title",
  "singer_name": "New Singer Name",
  "song_id": 6,  // Optional: pilih dari database
  "singer_id": 11,  // Optional: pilih dari database
  "modification_notes": "Perlu ganti karena lagu sebelumnya sudah digunakan"
}
```

**Response Success:**
```json
{
  "success": true,
  "data": {
    "id": 37,
    "song_title": "New Song Title",
    "singer_name": "New Singer Name",
    "original_song_title": "Old Song Title",
    "original_singer_name": "Old Singer Name",
    "producer_modified": true,
    "producer_modified_at": "2025-12-17T21:00:00.000000Z",
    "status": "song_proposal"
  },
  "message": "Arrangement song/singer modified successfully. Music Arranger has been notified."
}
```

**Response Error (Status tidak sesuai):**
```json
{
  "success": false,
  "message": "Can only modify arrangement with status \"song_proposal\", \"submitted\", or \"arrangement_submitted\""
}
```

**Response Error (Tidak ada production team):**
```json
{
  "success": false,
  "message": "Arrangement does not have a production team assigned."
}
```

**Response Error (Unauthorized):**
```json
{
  "success": false,
  "message": "Unauthorized: This arrangement is not from your production team."
}
```

---

## 🧪 Testing Guide

### Test Case 1: Edit Song Proposal (Status: song_proposal)
1. **Setup:**
   - Music Arranger buat song proposal (status: `song_proposal`)
   - Producer login

2. **Action:**
   ```bash
   PUT /api/live-tv/producer/arrangements/{id}/edit-song-singer
   {
     "song_title": "Updated Song Title",
     "singer_name": "Updated Singer Name"
   }
   ```

3. **Expected Result:**
   - ✅ Status code: 200
   - ✅ `producer_modified = true`
   - ✅ `original_song_title` tetap sama
   - ✅ `song_title` berubah ke "Updated Song Title"
   - ✅ Music Arranger menerima notifikasi

4. **Check Log:**
   - Cari: `Producer editArrangementSongSinger - Validation passed`

---

### Test Case 2: Edit dengan song_id dari database
1. **Setup:**
   - Get available songs: `GET /api/live-tv/producer/songs`
   - Pilih `song_id` dari response

2. **Action:**
   ```bash
   PUT /api/live-tv/producer/arrangements/{id}/edit-song-singer
   {
     "song_id": 6
   }
   ```

3. **Expected Result:**
   - ✅ `song_title` otomatis ter-update dari database
   - ✅ `song_id` ter-update

---

### Test Case 3: Edit Arrangement yang sudah submit file
1. **Setup:**
   - Music Arranger upload file (status: `arrangement_submitted`)

2. **Action:**
   ```bash
   PUT /api/live-tv/producer/arrangements/{id}/edit-song-singer
   {
     "song_title": "Updated Song Title"
   }
   ```

3. **Expected Result:**
   - ✅ Status code: 200
   - ✅ Song title ter-update
   - ✅ Status tetap `arrangement_submitted`

---

### Test Case 4: Error - Status tidak sesuai
1. **Setup:**
   - Arrangement dengan status `arrangement_approved`

2. **Action:**
   ```bash
   PUT /api/live-tv/producer/arrangements/{id}/edit-song-singer
   {
     "song_title": "Updated Song Title"
   }
   ```

3. **Expected Result:**
   - ✅ Status code: 400
   - ✅ Error message: "Can only modify arrangement with status..."

---

## 🔍 Debugging

### Log yang akan muncul:

**Success:**
```
[INFO] Producer editArrangementSongSinger - Validation passed
{
  "arrangement_id": 37,
  "status": "song_proposal",
  "producer_id": 2,
  "production_team_id": 47
}
```

**Error - No production team:**
```
[WARNING] Producer editArrangementSongSinger - No production team found
{
  "arrangement_id": 37,
  "episode_id": 2010,
  "episode_production_team_id": null,
  "program_id": 101,
  "program_production_team_id": null
}
```

**Error - Unauthorized:**
```
[WARNING] Producer editArrangementSongSinger - Unauthorized access
{
  "arrangement_id": 37,
  "producer_id": 2,
  "production_team_producer_id": 3
}
```

---

## ✅ Kesimpulan

**Semua fitur sudah lengkap:**
- ✅ Endpoint edit song/singer sudah ada
- ✅ Validasi sudah benar
- ✅ Support edit song proposal (status: `song_proposal`)
- ✅ Support edit arrangement file (status: `submitted` atau `arrangement_submitted`)
- ✅ Endpoint songs dan singers sudah ada
- ✅ Notifikasi ke Music Arranger sudah ada
- ✅ Logging untuk debugging sudah ada

**Yang perlu dilakukan:**
1. Pastikan frontend menggunakan endpoint yang benar:
   - `GET /api/live-tv/producer/songs` (bukan `/live-tv/songs` atau `/live-tv/roles/music-arranger/songs`)
   - `GET /api/live-tv/producer/singers` (bukan `/live-tv/singers` atau `/live-tv/roles/music-arranger/singers`)
   - `PUT /api/live-tv/producer/arrangements/{id}/edit-song-singer`

2. Test dengan Postman atau frontend untuk memastikan tidak ada error

3. Cek log Laravel jika ada error untuk melihat detail masalahnya

---

**Last Updated:** 2025-12-17

