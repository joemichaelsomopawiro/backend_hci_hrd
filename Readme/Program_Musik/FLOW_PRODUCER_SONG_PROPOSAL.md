# Flow Producer - Song Proposal dari Music Arranger

## ✅ STATUS: **SUDAH LENGKAP & READY**

Producer dapat melakukan:

1. ✅ **Terima Notifikasi** (Song Proposal dari Music Arranger)
2. ✅ **Terima atau Tidak Usulan Lagu & Penyanyi** (Approve/Reject)
3. ✅ **Dapat Mengganti Usulan dari Music Arranger** (Edit Song/Singer)
4. ✅ **Selesai Pekerjaan** (Approve arrangement yang sudah selesai)

---

## 📋 ENDPOINT YANG TERSEDIA

### 1. **Terima Notifikasi**
**Endpoint:** `GET /api/live-tv/notifications`

**Fungsi:** Get semua notifikasi untuk Producer (termasuk song proposal, arrangement submitted, dll)

**Query Parameters:**
- `type` (optional): Filter by notification type
  - `song_proposal_submitted` - Usulan lagu baru dari Music Arranger
  - `music_arrangement_submitted` - Arrangement file baru dari Music Arranger
- `read` (optional): Filter by read status (`true`/`false`)
- `page` (optional): Pagination

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "song_proposal_submitted",
      "title": "Usulan Lagu Baru",
      "message": "Music Arranger John mengirim usulan lagu untuk Episode 001",
      "data": {
        "arrangement_id": 1,
        "episode_id": 1
      },
      "read_at": null,
      "created_at": "2026-01-27T10:00:00.000000Z"
    }
  ]
}
```

**Tersedia di Postman:** ✅ Yes

---

### 2. **Get Pending Approvals**
**Endpoint:** `GET /api/live-tv/producer/approvals` atau `GET /api/live-tv/producer/pending-approvals`

**Fungsi:** Get semua pending approvals untuk Producer (song proposals, arrangements, creative works, dll)

**Response:**
```json
{
  "success": true,
  "data": {
    "song_proposals": [
      {
        "id": 1,
        "episode_id": 1,
        "song_title": "Lagu Pilihan",
        "singer_name": "Penyanyi",
        "status": "song_proposal",
        "created_by": 3,
        "created_at": "2026-01-27T10:00:00.000000Z"
      }
    ],
    "music_arrangements": [
      {
        "id": 2,
        "episode_id": 1,
        "song_title": "Lagu Pilihan",
        "status": "arrangement_submitted",
        "file_path": "music-arrangements/...",
        "created_at": "2026-01-27T10:00:00.000000Z"
      }
    ]
  }
}
```

**Tersedia di Postman:** ✅ Yes

---

### 3. **Approve Song Proposal**
**Endpoint:** `POST /api/live-tv/producer/approvals/{id}/approve`

**Fungsi:** Approve usulan lagu & penyanyi dari Music Arranger

**Request Body:**
```json
{
  "type": "song_proposal",
  "notes": "Lagu dan penyanyi sesuai, silakan lanjut arrange"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "song_approved",
    "reviewed_by": 5,
    "reviewed_at": "2026-01-27T10:30:00.000000Z",
    "review_notes": "Lagu dan penyanyi sesuai, silakan lanjut arrange"
  },
  "message": "Song proposal approved successfully. Music Arranger has been notified."
}
```

**Fitur:**
- ✅ Update status menjadi `song_approved`
- ✅ Set `reviewed_by` dan `reviewed_at`
- ✅ **Notifikasi ke Music Arranger** ✅
- ✅ Music Arranger bisa lanjut arrange lagu

**Tersedia di Postman:** ✅ Yes

---

### 4. **Reject Song Proposal**
**Endpoint:** `POST /api/live-tv/producer/approvals/{id}/reject`

**Fungsi:** Reject usulan lagu & penyanyi dari Music Arranger

**Request Body:**
```json
{
  "type": "song_proposal",
  "reason": "Lagu tidak sesuai dengan tema episode"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "song_rejected",
    "rejection_reason": "Lagu tidak sesuai dengan tema episode",
    "reviewed_by": 5,
    "reviewed_at": "2026-01-27T10:30:00.000000Z"
  },
  "message": "Song proposal rejected successfully. Music Arranger and Sound Engineers have been notified."
}
```

**Fitur:**
- ✅ Update status menjadi `song_rejected`
- ✅ Set `rejection_reason`
- ✅ **Notifikasi ke Music Arranger** ✅
- ✅ **Notifikasi ke Sound Engineers** (mereka bisa bantu perbaikan) ✅

**Tersedia di Postman:** ✅ Yes

---

### 5. **Edit/Ganti Usulan Song & Singer** ⭐
**Endpoint:** `PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer`

**Fungsi:** Producer dapat mengganti usulan lagu & penyanyi dari Music Arranger

**Request Body:**
```json
{
  "song_title": "Lagu Baru (Diganti)",
  "singer_name": "Penyanyi Baru (Diganti)",
  "song_id": 5,
  "singer_id": 10,
  "modification_notes": "Lagu dan penyanyi diganti karena lebih sesuai"
}
```

**Keterangan:**
- Bisa ganti song dengan `song_title` (manual) atau `song_id` (dari database)
- Bisa ganti singer dengan `singer_name` (manual) atau `singer_id` (dari database)
- Bisa ganti salah satu saja (song atau singer)
- Hanya bisa edit jika status: `song_proposal`, `submitted`, atau `arrangement_submitted`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "original_song_title": "Lagu Lama",
    "song_title": "Lagu Baru (Diganti)",
    "original_singer_name": "Penyanyi Lama",
    "singer_name": "Penyanyi Baru (Diganti)",
    "producer_modified": true,
    "producer_modified_at": "2026-01-27T10:35:00.000000Z"
  },
  "message": "Arrangement song/singer modified successfully. Music Arranger has been notified."
}
```

**Fitur:**
- ✅ Update song/singer langsung di arrangement
- ✅ Track original values (`original_song_title`, `original_singer_name`)
- ✅ Set flag `producer_modified = true`
- ✅ **Notifikasi ke Music Arranger** bahwa Producer telah mengganti ✅
- ✅ Jika arrangement sudah diperbaiki Sound Engineer, Sound Engineer juga di-notify

**Tersedia di Postman:** ✅ Yes

---

### 6. **Get Available Songs & Singers (untuk Edit)**
**Endpoint:**
- `GET /api/live-tv/producer/songs` - List songs untuk dipilih saat edit
- `GET /api/live-tv/producer/singers` - List singers untuk dipilih saat edit

**Fungsi:** Get list songs dan singers yang tersedia (sama seperti Music Arranger)

**Tersedia di Postman:** ✅ Yes

---

### 7. **Approve Arrangement (Selesai Pekerjaan)** ⭐
**Endpoint:** `POST /api/live-tv/producer/approvals/{id}/approve`

**Fungsi:** Approve arrangement file yang sudah dibuat Music Arranger (selesai pekerjaan)

**Request Body:**
```json
{
  "type": "music_arrangement",
  "notes": "Arrangement sudah bagus, lanjut ke recording"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "status": "arrangement_approved",
    "reviewed_by": 5,
    "reviewed_at": "2026-01-27T11:00:00.000000Z"
  },
  "message": "Arrangement approved successfully. Music Arranger, Sound Engineer, and Creative have been notified."
}
```

**Fitur:**
- ✅ Update status menjadi `arrangement_approved`
- ✅ **Notifikasi ke Music Arranger** ✅
- ✅ **Auto-create Recording Task untuk Sound Engineer** ✅
- ✅ **Auto-create Creative Work untuk Creative** ✅
- ✅ **Notifikasi ke Sound Engineer & Creative** ✅

**Tersedia di Postman:** ✅ Yes

---

## 🔄 WORKFLOW LENGKAP

### **Step 1: Producer Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=song_proposal_submitted
```

**Hasil:**
- ✅ Producer melihat notifikasi ada usulan lagu baru dari Music Arranger

---

### **Step 2: Producer Lihat Pending Approvals**
```http
GET /api/live-tv/producer/approvals
```

**Hasil:**
- ✅ Producer melihat daftar song proposals yang pending
- ✅ Melihat detail: song_title, singer_name, episode, dll

---

### **Step 3: Producer Edit Song/Singer (Opsional)**
```http
PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer
Content-Type: application/json

{
  "song_title": "Lagu yang Lebih Sesuai",
  "singer_name": "Penyanyi yang Lebih Cocok",
  "modification_notes": "Ganti karena lebih sesuai tema"
}
```

**Hasil:**
- ✅ Song/singer diganti
- ✅ Music Arranger di-notify tentang perubahan

---

### **Step 4: Producer Approve atau Reject**

**Option A: Approve**
```http
POST /api/live-tv/producer/approvals/{id}/approve
Content-Type: application/json

{
  "type": "song_proposal",
  "notes": "Lagu dan penyanyi sesuai, lanjut arrange"
}
```

**Hasil:**
- ✅ Status: `song_approved`
- ✅ Music Arranger di-notify
- ✅ Music Arranger bisa lanjut arrange lagu

**Option B: Reject**
```http
POST /api/live-tv/producer/approvals/{id}/reject
Content-Type: application/json

{
  "type": "song_proposal",
  "reason": "Lagu tidak sesuai tema"
}
```

**Hasil:**
- ✅ Status: `song_rejected`
- ✅ Music Arranger di-notify
- ✅ Sound Engineers di-notify (bisa bantu perbaikan)

---

### **Step 5: Producer Approve Arrangement (Selesai Pekerjaan)**

Setelah Music Arranger upload arrangement file dan submit:

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
- ✅ **Pekerjaan Music Arranger SELESAI** ✅
- ✅ Auto-create Recording Task (Sound Engineer)
- ✅ Auto-create Creative Work (Creative)
- ✅ Semua pihak di-notify

---

## 📝 CONTOH KASUS PENGGUNAAN

### **Kasus 1: Producer Langsung Approve**
```
1. Music Arranger submit song proposal
2. Producer terima notifikasi
3. Producer approve tanpa edit
4. Music Arranger lanjut arrange
```

**Flow:**
```http
GET /api/live-tv/producer/approvals
POST /api/live-tv/producer/approvals/{id}/approve
{
  "type": "song_proposal",
  "notes": "OK"
}
```

---

### **Kasus 2: Producer Edit Sebelum Approve**
```
1. Music Arranger submit song proposal: "Lagu A" dengan "Penyanyi X"
2. Producer terima notifikasi
3. Producer edit: Ganti ke "Lagu B" dengan "Penyanyi Y"
4. Music Arranger terima notifikasi edit
5. Producer approve dengan song/singer yang sudah diganti
6. Music Arranger arrange dengan song/singer baru
```

**Flow:**
```http
GET /api/live-tv/producer/approvals
PUT /api/live-tv/producer/arrangements/{id}/edit-song-singer
{
  "song_title": "Lagu B",
  "singer_name": "Penyanyi Y",
  "modification_notes": "Lebih sesuai tema"
}
POST /api/live-tv/producer/approvals/{id}/approve
{
  "type": "song_proposal"
}
```

---

### **Kasus 3: Producer Reject**
```
1. Music Arranger submit song proposal
2. Producer terima notifikasi
3. Producer reject dengan alasan
4. Music Arranger terima notifikasi reject
5. Sound Engineer bisa bantu perbaikan
6. Music Arranger submit ulang
```

**Flow:**
```http
GET /api/live-tv/producer/approvals
POST /api/live-tv/producer/approvals/{id}/reject
{
  "type": "song_proposal",
  "reason": "Lagu tidak sesuai"
}
```

---

## ✅ VALIDATION & SECURITY

### **Access Control:**
- ✅ Hanya Producer yang di-assign ke ProductionTeam episode yang bisa approve/reject/edit
- ✅ Validasi: `productionTeam->producer_id === user->id`

### **Edit Restrictions:**
- ✅ Hanya bisa edit jika status: `song_proposal`, `submitted`, atau `arrangement_submitted`
- ✅ Tidak bisa edit jika sudah `arrangement_approved`

### **Notifications:**
- ✅ Music Arranger di-notify saat Producer approve/reject/edit
- ✅ Sound Engineers di-notify saat reject (untuk bantu perbaikan)
- ✅ Semua pihak di-notify saat approve arrangement (selesai pekerjaan)

---

## 📋 POSTMAN COLLECTION

Semua endpoint sudah tersedia di Postman Collection:

1. ✅ **Get Notifications** - `GET /api/live-tv/notifications`
2. ✅ **Get Pending Approvals** - `GET /api/live-tv/producer/approvals`
3. ✅ **Approve Song Proposal** - `POST /api/live-tv/producer/approvals/{id}/approve`
4. ✅ **Reject Song Proposal** - `POST /api/live-tv/producer/approvals/{id}/reject`
5. ✅ **Edit Song/Singer** - `PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer`
6. ✅ **Get Available Songs** - `GET /api/live-tv/producer/songs`
7. ✅ **Get Available Singers** - `GET /api/live-tv/producer/singers`
8. ✅ **Approve Arrangement** - `POST /api/live-tv/producer/approvals/{id}/approve` (type: `music_arrangement`)

**Collection:** `Postman_Collection_HCI_HRD_Complete_Flow.json`
**Folder:** `Producer`

---

## 🎯 KESIMPULAN

### ✅ **Fitur Sudah Lengkap:**

1. ✅ **Terima Notifikasi** - Via `/api/live-tv/notifications` atau `/api/live-tv/producer/approvals`
2. ✅ **Terima atau Tidak Usulan Lagu & Penyanyi** - Via approve/reject endpoint
3. ✅ **Dapat Mengganti Usulan dari Music Arranger** - Via edit-song-singer endpoint
4. ✅ **Selesai Pekerjaan** - Via approve arrangement (type: `music_arrangement`)

### ✅ **Yang Sudah Bekerja:**

- ✅ Auto-notification saat Music Arranger submit
- ✅ Producer bisa edit song/singer sebelum approve
- ✅ Producer bisa approve/reject song proposal
- ✅ Producer bisa approve arrangement (selesai pekerjaan Music Arranger)
- ✅ Auto-create Recording Task dan Creative Work saat approve arrangement
- ✅ Notifikasi ke semua pihak terkait
- ✅ Postman collection sudah tersedia

**Status:** ✅ **READY FOR FRONTEND INTEGRATION**

---

**Last Updated:** 2026-01-27
