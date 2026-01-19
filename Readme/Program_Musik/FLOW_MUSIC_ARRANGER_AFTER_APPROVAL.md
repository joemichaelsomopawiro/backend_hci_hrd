# Flow Music Arranger - Setelah Producer Approve Song Proposal

## ✅ STATUS: **SUDAH LENGKAP & READY**

Music Arranger dapat melakukan setelah Producer approve song proposal:

1. ✅ **Terima Notifikasi** (dari Producer: Song proposal approved)
2. ✅ **Terima Pekerjaan** (Accept Work - mulai arrange)
3. ✅ **Arrange Lagu** (Upload file arrangement)
4. ✅ **Selesaikan Pekerjaan** (Submit arrangement ke Producer)

---

## 📋 ENDPOINT YANG TERSEDIA

### 1. **Terima Notifikasi**
**Endpoint:** `GET /api/live-tv/notifications`

**Fungsi:** Get notifikasi bahwa Producer telah approve song proposal

**Query Parameters:**
- `type` (optional): Filter by notification type
  - `song_proposal_approved` - Song proposal di-approve Producer
  - `arrangement_modified_by_producer` - Producer mengubah song/singer
- `read` (optional): Filter by read status (`true`/`false`)
- `page` (optional): Pagination

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "type": "song_proposal_approved",
        "title": "Usulan Lagu & Penyanyi Diterima",
        "message": "Usulan lagu 'Lagu Pilihan' dengan penyanyi 'Penyanyi Favorit' telah diterima. Silakan arrange lagu.",
        "data": {
          "arrangement_id": 1,
          "episode_id": 1,
          "review_notes": "Lagu dan penyanyi sesuai, lanjut arrange"
        },
        "read_at": null,
        "created_at": "2026-01-27T10:30:00.000000Z"
      }
    ],
    "total": 1
  }
}
```

**Tersedia di Postman:** ✅ Yes

---

### 2. **Get My Arrangements (Filter Song Approved)**
**Endpoint:** `GET /api/live-tv/roles/music-arranger/arrangements?status=song_approved`

**Fungsi:** Get semua arrangements milik Music Arranger yang sudah di-approve Producer (status: `song_approved`)

**Query Parameters:**
- `status` (optional): Filter by status (`song_approved`, `arrangement_in_progress`, `arrangement_submitted`)
- `episode_id` (optional): Filter by episode
- `ready_for_arrangement` (optional): `true` untuk hanya yang siap di-arrange

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "episode_id": 1,
      "song_title": "Lagu Pilihan",
      "singer_name": "Penyanyi Favorit",
      "status": "song_approved",
      "reviewed_by": 5,
      "reviewed_at": "2026-01-27T10:30:00.000000Z",
      "created_at": "2026-01-27T10:00:00.000000Z"
    }
  ]
}
```

**Tersedia di Postman:** ✅ Yes

---

### 3. **Terima Pekerjaan (Accept Work)** ⭐
**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work`

**Fungsi:** Music Arranger menerima pekerjaan untuk arrange lagu setelah Producer approve song proposal

**Request Body:** (Optional)
```json
{}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "arrangement_in_progress",
    "song_title": "Lagu Pilihan",
    "singer_name": "Penyanyi Favorit"
  }
}
```

**Fitur:**
- ✅ Update status menjadi `arrangement_in_progress`
- ✅ Music Arranger siap mulai arrange lagu
- ✅ Bisa langsung upload file arrangement atau submit arrangement

**Validasi:**
- ✅ Arrangement harus milik Music Arranger (`created_by === user->id`)
- ✅ Status harus `song_approved` (tidak wajib, tapi umumnya setelah approve)

**Tersedia di Postman:** ✅ Yes

---

### 4. **Arrange Lagu (Upload Arrangement File)** ⭐
**Endpoint:** `PUT /api/live-tv/roles/music-arranger/arrangements/{id}`

**Fungsi:** Upload file arrangement audio setelah accept work

**Request:** Multipart form data
- `file` (required): Audio file (mp3, wav, midi, max 100MB)
- `arrangement_notes` (optional): Catatan arrangement

**Response:**
```json
{
  "success": true,
  "message": "File uploaded successfully.",
  "data": {
    "id": 1,
    "file_path": "music-arrangements/xyz123.mp3",
    "file_name": "arrangement.mp3",
    "file_size": 5242880,
    "mime_type": "audio/mpeg",
    "status": "arrangement_submitted",
    "arrangement_notes": "Catatan arrangement"
  }
}
```

**Fitur:**
- ✅ Upload file arrangement audio
- ✅ Jika status `song_approved`, setelah upload otomatis menjadi `arrangement_submitted` (auto-submit)
- ✅ Jika status `arrangement_rejected`, setelah upload tetap `arrangement_rejected` (perlu submit manual)
- ✅ Jika status `arrangement_in_progress`, setelah upload tetap `arrangement_in_progress` (perlu submit manual)

**Validasi:**
- ✅ Arrangement harus milik Music Arranger (`created_by === user->id`)
- ✅ File format: mp3, wav, midi (maksimal 100MB)

**Catatan:**
- Jika status `song_approved` dan upload file, status langsung jadi `arrangement_submitted` dan Producer di-notify
- Jika status `arrangement_in_progress`, setelah upload perlu submit manual

**Tersedia di Postman:** ✅ Yes

---

### 5. **Selesaikan Pekerjaan (Submit Arrangement)** ⭐
**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit`

**Fungsi:** Submit arrangement ke Producer untuk review (selesai pekerjaan)

**Request Body:** (Optional)
```json
{}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "arrangement_submitted",
    "submitted_at": "2026-01-27T11:00:00.000000Z",
    "song_title": "Lagu Pilihan",
    "singer_name": "Penyanyi Favorit"
  },
  "message": "Arrangement submitted successfully. Producer has been notified."
}
```

**Fitur:**
- ✅ Update status menjadi `arrangement_submitted`
- ✅ Set `submitted_at` timestamp
- ✅ **Notifikasi ke Producer** untuk review ✅
- ✅ Pekerjaan Music Arranger SELESAI, tunggu Producer approve

**Validasi:**
- ✅ Arrangement harus milik Music Arranger (`created_by === user->id`)
- ✅ Status harus salah satu dari:
  - `song_approved` - First time submit
  - `arrangement_in_progress` - Submit setelah accept work
  - `arrangement_rejected` / `rejected` - Resubmit setelah reject (harus sudah upload file dulu)
  - `arrangement_submitted` - Re-submit

**Catatan:**
- Jika status `arrangement_rejected` atau `rejected`, harus upload file dulu sebelum submit
- Setelah submit, Producer akan di-notify untuk review
- Producer akan approve/reject arrangement

**Tersedia di Postman:** ✅ Yes

---

## 🔄 WORKFLOW LENGKAP

### **Step 1: Producer Approve Song Proposal**
```http
POST /api/live-tv/producer/approvals/{id}/approve
Content-Type: application/json

{
  "type": "song_proposal",
  "notes": "Lagu dan penyanyi sesuai, lanjut arrange"
}
```

**Hasil:**
- ✅ Status arrangement: `song_approved`
- ✅ **Music Arranger di-notify** ✅

---

### **Step 2: Music Arranger Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=song_proposal_approved
```

**Hasil:**
- ✅ Music Arranger melihat notifikasi bahwa song proposal di-approve
- ✅ Music Arranger tahu ada pekerjaan yang harus dikerjakan

---

### **Step 3: Music Arranger Terima Pekerjaan**
```http
POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work
```

**Hasil:**
- ✅ Status: `arrangement_in_progress`
- ✅ Music Arranger siap mulai arrange lagu

---

### **Step 4: Music Arranger Arrange Lagu (Upload File)**

**Option A: Upload dengan Accept Work (Recommended)**
```http
PUT /api/live-tv/roles/music-arranger/arrangements/{id}
Content-Type: multipart/form-data

file: [audio file]
arrangement_notes: "Catatan arrangement"
```

**Hasil:**
- ✅ File ter-upload
- ✅ Status tetap `arrangement_in_progress` (jika sudah accept work)
- ✅ Music Arranger perlu submit setelah upload

**Option B: Upload Tanpa Accept Work (Auto-Submit)**
Jika status masih `song_approved`:
```http
PUT /api/live-tv/roles/music-arranger/arrangements/{id}
Content-Type: multipart/form-data

file: [audio file]
```

**Hasil:**
- ✅ File ter-upload
- ✅ Status otomatis jadi `arrangement_submitted`
- ✅ Producer di-notify
- ✅ Pekerjaan SELESAI (tidak perlu submit lagi)

---

### **Step 5: Music Arranger Selesaikan Pekerjaan (Submit)**

**Hanya perlu jika status `arrangement_in_progress`:**
```http
POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit
```

**Hasil:**
- ✅ Status: `arrangement_submitted`
- ✅ **Producer di-notify** untuk review ✅
- ✅ Pekerjaan Music Arranger SELESAI
- ✅ Tunggu Producer approve/reject

---

### **Step 6: Producer Approve Arrangement (Selesai Final)**
```http
POST /api/live-tv/producer/approvals/{id}/approve
Content-Type: application/json

{
  "type": "music_arrangement",
  "notes": "Arrangement bagus, lanjut ke recording"
}
```

**Hasil:**
- ✅ Status: `arrangement_approved`
- ✅ **Pekerjaan Music Arranger FINAL SELESAI** ✅
- ✅ Auto-create Recording Task (Sound Engineer)
- ✅ Auto-create Creative Work (Creative)
- ✅ Semua pihak di-notify

---

## 📝 CONTOH KASUS PENGGUNAAN

### **Kasus 1: Workflow Normal (Dengan Accept Work)**
```
1. Producer approve song proposal
   → Status: song_approved
   → Music Arranger di-notify

2. Music Arranger terima notifikasi
   → GET /api/live-tv/notifications?type=song_proposal_approved

3. Music Arranger accept work
   → POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work
   → Status: arrangement_in_progress

4. Music Arranger upload file arrangement
   → PUT /api/live-tv/roles/music-arranger/arrangements/{id}
   → Status: arrangement_in_progress (masih, perlu submit)

5. Music Arranger submit arrangement
   → POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit
   → Status: arrangement_submitted
   → Producer di-notify

6. Producer approve arrangement
   → Status: arrangement_approved
   → Pekerjaan Music Arranger SELESAI
```

---

### **Kasus 2: Upload Langsung Tanpa Accept Work (Auto-Submit)**
```
1. Producer approve song proposal
   → Status: song_approved
   → Music Arranger di-notify

2. Music Arranger langsung upload file
   → PUT /api/live-tv/roles/music-arranger/arrangements/{id}
   → Status: arrangement_submitted (auto-submit)
   → Producer di-notify

3. Producer approve arrangement
   → Status: arrangement_approved
   → Pekerjaan Music Arranger SELESAI
```

**Note:** Jika status `song_approved` dan upload file, otomatis submit dan Producer di-notify.

---

### **Kasus 3: Resubmit Setelah Reject**
```
1. Producer reject arrangement
   → Status: arrangement_rejected
   → Music Arranger di-notify

2. Music Arranger upload file baru
   → PUT /api/live-tv/roles/music-arranger/arrangements/{id}
   → Status: arrangement_rejected (tetap, perlu submit)

3. Music Arranger submit ulang
   → POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit
   → Status: arrangement_submitted
   → Producer di-notify (resubmit)

4. Producer approve arrangement
   → Status: arrangement_approved
   → Pekerjaan Music Arranger SELESAI
```

---

## ✅ VALIDATION & SECURITY

### **Access Control:**
- ✅ Hanya Music Arranger yang membuat arrangement yang bisa accept/upload/submit
- ✅ Validasi: `arrangement->created_by === user->id`

### **Status Restrictions:**

**Accept Work:**
- Tidak ada status restriction (bisa accept kapan saja)

**Upload File:**
- Bisa upload jika arrangement milik Music Arranger
- Auto-submit jika status `song_approved`

**Submit:**
- Status harus: `song_approved`, `arrangement_in_progress`, `arrangement_rejected`, `rejected`, atau `arrangement_submitted`
- Jika status `arrangement_rejected` atau `rejected`, harus sudah upload file dulu

### **Notifications:**
- ✅ Music Arranger di-notify saat Producer approve song proposal
- ✅ Music Arranger di-notify saat Producer modify song/singer
- ✅ Producer di-notify saat Music Arranger submit arrangement
- ✅ Music Arranger di-notify saat Producer approve/reject arrangement

---

## 📋 POSTMAN COLLECTION

Semua endpoint sudah tersedia di Postman Collection:

1. ✅ **Get Notifications** - `GET /api/live-tv/notifications`
2. ✅ **Get My Arrangements** - `GET /api/live-tv/roles/music-arranger/arrangements`
3. ✅ **Accept Work** - `POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work`
4. ✅ **Upload Arrangement File** - `PUT /api/live-tv/roles/music-arranger/arrangements/{id}`
5. ✅ **Submit Arrangement** - `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit`

**Collection:** `Postman_Collection_HCI_HRD_Complete_Flow.json`
**Folder:** `Music Arranger`

---

## 🎯 KESIMPULAN

### ✅ **Fitur Sudah Lengkap:**

1. ✅ **Terima Notifikasi** - Via `/api/live-tv/notifications?type=song_proposal_approved`
2. ✅ **Terima Pekerjaan** - Via `accept-work` endpoint
3. ✅ **Arrange Lagu** - Via upload file endpoint (PUT)
4. ✅ **Selesaikan Pekerjaan** - Via submit endpoint (POST)

### ✅ **Yang Sudah Bekerja:**

- ✅ Notifikasi otomatis saat Producer approve song proposal
- ✅ Music Arranger bisa accept work (opsional, untuk tracking)
- ✅ Music Arranger bisa upload file arrangement
- ✅ Auto-submit jika upload saat status `song_approved`
- ✅ Manual submit jika upload saat status `arrangement_in_progress`
- ✅ Submit arrangement ke Producer (selesai pekerjaan)
- ✅ Notifikasi ke Producer saat submit
- ✅ Resubmit setelah reject
- ✅ Postman collection sudah tersedia

**Status:** ✅ **READY FOR FRONTEND INTEGRATION**

---

**Last Updated:** 2026-01-27
