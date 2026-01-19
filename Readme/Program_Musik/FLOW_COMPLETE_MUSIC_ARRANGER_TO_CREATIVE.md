# Flow Lengkap: Music Arranger → Producer → Creative

## ✅ STATUS: **SUDAH LENGKAP & READY**

Workflow lengkap dari Music Arranger submit arrangement sampai Creative selesai pekerjaan, termasuk handling rejection dan Sound Engineer help.

---

## 📋 OVERVIEW WORKFLOW

```
Music Arranger
    ↓ Submit Arrangement
Producer
    ↓ Terima Notifikasi
    ↓ QC Music (Review & Approve/Reject)
    ↓ Jika REJECT → Kembali ke Music Arranger atau Sound Engineer
    ↓ Jika APPROVE → Auto-create Creative Work
Creative
    ↓ Terima Notifikasi
    ↓ Terima Pekerjaan
    ↓ Buat Script, Storyboard, Budget, Jadwal, Lokasi
    ↓ Selesai Pekerjaan
```

---

## 🔄 DETAIL WORKFLOW

### **PHASE 1: Music Arranger Submit Arrangement**

**Music Arranger:**
1. Upload arrangement file
2. Submit arrangement ke Producer

**Endpoint:**
```http
POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit
```

**Hasil:**
- ✅ Status: `arrangement_submitted`
- ✅ **Producer di-notify** ✅

---

### **PHASE 2: Producer QC Music**

#### **Step 1: Producer Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=music_arrangement_submitted
```

**Response:**
```json
{
  "type": "music_arrangement_submitted",
  "title": "Arrangement Baru",
  "message": "Music Arranger John telah mengirim arrangement 'Lagu Pilihan' untuk Episode 001",
  "data": {
    "arrangement_id": 1,
    "episode_id": 1
  }
}
```

---

#### **Step 2: Producer Lihat Pending Arrangements**
```http
GET /api/live-tv/producer/approvals
```

**Response:**
```json
{
  "music_arrangements": [
    {
      "id": 1,
      "episode_id": 1,
      "song_title": "Lagu Pilihan",
      "status": "arrangement_submitted",
      "file_path": "music-arrangements/xyz.mp3",
      "created_at": "2026-01-27T10:00:00.000000Z"
    }
  ]
}
```

---

#### **Step 3: Producer QC Music (Approve/Reject)**

**Option A: Approve Arrangement**
```http
POST /api/live-tv/producer/approvals/{id}/approve
Content-Type: application/json

{
  "type": "music_arrangement",
  "notes": "Arrangement bagus, lanjut ke creative work"
}
```

**Hasil:**
- ✅ Status: `arrangement_approved`
- ✅ **Music Arranger di-notify** ✅
- ✅ **Auto-create Recording Task untuk Sound Engineer** ✅
- ✅ **Auto-create Creative Work untuk Creative** ✅ (via MusicArrangementObserver)
- ✅ **Creative di-notify** ✅

---

**Option B: Reject Arrangement**
```http
POST /api/live-tv/producer/approvals/{id}/reject
Content-Type: application/json

{
  "type": "music_arrangement",
  "reason": "Arrangement perlu perbaikan"
}
```

**Hasil:**
- ✅ Status: `arrangement_rejected`
- ✅ **Music Arranger di-notify** ✅
- ✅ **Sound Engineers di-notify** (bisa bantu perbaikan) ✅

---

### **PHASE 3A: Jika REJECT - Kembali ke Music Arranger**

#### **Step 1: Music Arranger Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=music_arrangement_rejected
```

**Response:**
```json
{
  "type": "music_arrangement_rejected",
  "title": "Arrangement Ditolak",
  "message": "Arrangement 'Lagu Pilihan' ditolak. Alasan: Arrangement perlu perbaikan. Sound Engineer dapat membantu perbaikan.",
  "data": {
    "arrangement_id": 1,
    "episode_id": 1,
    "rejection_reason": "Arrangement perlu perbaikan"
  }
}
```

---

#### **Step 2: Music Arranger Terima Pekerjaan (Opsional)**
```http
POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work
```

**Hasil:**
- ✅ Status: `arrangement_in_progress` (atau tetap `arrangement_rejected` jika belum upload file)

---

#### **Step 3: Music Arranger Perbaiki & Upload File**
```http
PUT /api/live-tv/roles/music-arranger/arrangements/{id}
Content-Type: multipart/form-data

file: [audio file baru]
```

**Hasil:**
- ✅ File ter-upload
- ✅ Status tetap `arrangement_rejected` (perlu submit ulang)

---

#### **Step 4: Music Arranger Submit Ulang**
```http
POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit
```

**Hasil:**
- ✅ Status: `arrangement_submitted`
- ✅ **Producer di-notify** (resubmit) ✅
- ✅ Kembali ke **PHASE 2** (Producer QC Music)

---

### **PHASE 3B: Jika REJECT - Sound Engineer Bantu Perbaikan**

#### **Step 1: Sound Engineer Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=song_proposal_rejected_help_needed
```

**Atau lihat rejected arrangements:**
```http
GET /api/live-tv/roles/sound-engineer/rejected-arrangements
```

---

#### **Step 2: Sound Engineer Bantu Perbaikan**

**Untuk Song Proposal yang ditolak:**
```http
POST /api/live-tv/roles/sound-engineer/arrangements/{id}/help-fix-song-proposal
Content-Type: application/json

{
  "help_notes": "Saran: Gunakan lagu X dengan penyanyi Y",
  "suggested_song_title": "Lagu Alternatif",
  "suggested_singer_name": "Penyanyi Alternatif"
}
```

**Untuk Arrangement yang ditolak:**
```http
POST /api/live-tv/roles/sound-engineer/arrangements/{id}/help-fix-arrangement
Content-Type: multipart/form-data

help_notes: "Arrangement perlu perbaikan pada bagian chorus"
suggested_fixes: "Tingkatkan volume chorus, tambah reverb"
file: [file arrangement yang diperbaiki] (optional)
```

**Hasil:**
- ✅ Arrangement diperbaiki Sound Engineer
- ✅ Status: `arrangement_submitted` (atau `song_proposal` untuk song proposal)
- ✅ **Music Arranger di-notify** ✅
- ✅ **Producer di-notify** (ada perbaikan dari Sound Engineer) ✅
- ✅ Kembali ke **PHASE 2** (Producer QC Music)

---

### **PHASE 4: Jika APPROVE - Creative Work**

#### **Step 1: Creative Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=creative_work_created
```

**Auto-create terjadi via MusicArrangementObserver:**
- Ketika Producer approve arrangement (status: `arrangement_approved`)
- Creative Work otomatis dibuat dengan status: `draft`
- Creative di-notify

---

#### **Step 2: Creative Terima Pekerjaan**
```http
POST /api/live-tv/roles/creative/works/{id}/accept-work
```

**Hasil:**
- ✅ Status: `in_progress` (jika ada status ini) atau tetap `draft`

---

#### **Step 3: Creative Buat Script**
```http
PUT /api/live-tv/roles/creative/works/{id}
Content-Type: application/json

{
  "script_content": "Script cerita video klip lengkap..."
}
```

**Field:** `script_content` (text)

---

#### **Step 4: Creative Buat Storyboard**
```http
PUT /api/live-tv/roles/creative/works/{id}
Content-Type: application/json

{
  "storyboard_data": {
    "scenes": [
      {
        "scene_number": 1,
        "description": "Opening scene",
        "image_url": "...",
        "duration": "00:00:05"
      }
    ]
  }
}
```

**Field:** `storyboard_data` (JSON array)

---

#### **Step 5: Creative Input Jadwal Rekaman Suara**
```http
PUT /api/live-tv/roles/creative/works/{id}
Content-Type: application/json

{
  "recording_schedule": "2026-01-30 10:00:00"
}
```

**Field:** `recording_schedule` (datetime)

---

#### **Step 6: Creative Input Jadwal Syuting**
```http
PUT /api/live-tv/roles/creative/works/{id}
Content-Type: application/json

{
  "shooting_schedule": "2026-02-01 08:00:00"
}
```

**Field:** `shooting_schedule` (datetime)

---

#### **Step 7: Creative Input Lokasi Syuting**
```http
PUT /api/live-tv/roles/creative/works/{id}
Content-Type: application/json

{
  "shooting_location": "Studio A, Jl. Raya Contoh No. 123"
}
```

**Field:** `shooting_location` (string, max 255)

---

#### **Step 8: Creative Buat Budget (Bayar Talent)**
```http
PUT /api/live-tv/roles/creative/works/{id}
Content-Type: application/json

{
  "budget_data": {
    "talent_fee": {
      "category": "Talent Fee",
      "description": "Bayar talent penyanyi",
      "amount": 5000000,
      "currency": "IDR"
    },
    "equipment_rental": {
      "category": "Equipment Rental",
      "description": "Sewa kamera, lighting",
      "amount": 2000000,
      "currency": "IDR"
    },
    "location_fee": {
      "category": "Location Fee",
      "description": "Biaya lokasi syuting",
      "amount": 1000000,
      "currency": "IDR"
    }
  }
}
```

**Field:** `budget_data` (JSON array/object)
- ✅ Support format array of objects atau key-value object
- ✅ Auto-calculate `total_budget`
- ✅ Support multiple budget items

---

#### **Step 9: Creative Selesaikan Pekerjaan (Submit)**
```http
POST /api/live-tv/roles/creative/works/{id}/submit
```

**Hasil:**
- ✅ Status: `submitted`
- ✅ **Producer di-notify** untuk review ✅

---

## 📋 ENDPOINT SUMMARY

### **Music Arranger**
| Action | Endpoint | Method |
|--------|----------|--------|
| Submit Arrangement | `/api/live-tv/roles/music-arranger/arrangements/{id}/submit` | POST |
| Accept Work | `/api/live-tv/roles/music-arranger/arrangements/{id}/accept-work` | POST |
| Upload File | `/api/live-tv/roles/music-arranger/arrangements/{id}` | PUT |

---

### **Producer**
| Action | Endpoint | Method |
|--------|----------|--------|
| Get Notifications | `/api/live-tv/notifications?type=music_arrangement_submitted` | GET |
| Get Approvals | `/api/live-tv/producer/approvals` | GET |
| Approve Arrangement | `/api/live-tv/producer/approvals/{id}/approve` | POST |
| Reject Arrangement | `/api/live-tv/producer/approvals/{id}/reject` | POST |

**Approve Body:**
```json
{
  "type": "music_arrangement",
  "notes": "Arrangement bagus"
}
```

**Reject Body:**
```json
{
  "type": "music_arrangement",
  "reason": "Perlu perbaikan"
}
```

---

### **Sound Engineer**
| Action | Endpoint | Method |
|--------|----------|--------|
| Get Rejected Arrangements | `/api/live-tv/roles/sound-engineer/rejected-arrangements` | GET |
| Get Rejected Song Proposals | `/api/live-tv/roles/sound-engineer/rejected-song-proposals` | GET |
| Help Fix Song Proposal | `/api/live-tv/roles/sound-engineer/arrangements/{id}/help-fix-song-proposal` | POST |
| Help Fix Arrangement | `/api/live-tv/roles/sound-engineer/arrangements/{id}/help-fix-arrangement` | POST |

---

### **Creative**
| Action | Endpoint | Method |
|--------|----------|--------|
| Get Notifications | `/api/live-tv/notifications?type=creative_work_created` | GET |
| Get My Works | `/api/live-tv/roles/creative/works` | GET |
| Accept Work | `/api/live-tv/roles/creative/works/{id}/accept-work` | POST |
| Update Work (Script, Storyboard, Budget, Jadwal, Lokasi) | `/api/live-tv/roles/creative/works/{id}` | PUT |
| Submit Work | `/api/live-tv/roles/creative/works/{id}/submit` | POST |

**Update Body (Semua Field):**
```json
{
  "script_content": "Script...",
  "storyboard_data": {...},
  "budget_data": {...},
  "recording_schedule": "2026-01-30 10:00:00",
  "shooting_schedule": "2026-02-01 08:00:00",
  "shooting_location": "Studio A"
}
```

---

## ✅ VERIFIKASI SEMUA FITUR

### **Producer QC Music**
- ✅ Terima notifikasi arrangement submitted
- ✅ Lihat pending approvals
- ✅ Approve arrangement (auto-create Creative Work)
- ✅ Reject arrangement (notify Music Arranger & Sound Engineers)
- ✅ **Catatan:** "QC Music" = Approve/Reject arrangement (tidak ada endpoint terpisah untuk QC)

---

### **Music Arranger - Jika Reject**
- ✅ Terima notifikasi rejection
- ✅ Terima pekerjaan
- ✅ Upload file arrangement baru
- ✅ Submit ulang ke Producer

---

### **Sound Engineer - Bantu Perbaikan**
- ✅ Terima notifikasi rejected arrangement
- ✅ Lihat rejected arrangements
- ✅ Bantu perbaikan song proposal (jika song proposal ditolak)
- ✅ Bantu perbaikan arrangement (jika arrangement ditolak)
- ✅ Upload file arrangement yang diperbaiki (optional)
- ✅ Notify Music Arranger & Producer

---

### **Creative - Jika Approve**
- ✅ Terima notifikasi creative work created (auto-create)
- ✅ Terima pekerjaan
- ✅ Tulis Script (`script_content`)
- ✅ Buat Storyboard (`storyboard_data`)
- ✅ Input Jadwal Rekaman (`recording_schedule`)
- ✅ Input Jadwal Syuting (`shooting_schedule`)
- ✅ Input Lokasi Syuting (`shooting_location`)
- ✅ Buat Budget bayar talent (`budget_data`)
- ✅ Selesai pekerjaan (submit)

---

## 🎯 KESIMPULAN

### ✅ **Semua Fitur Sudah Ada:**

1. ✅ **Producer QC Music** - Via approve/reject arrangement endpoint
2. ✅ **Music Arranger - Reject Flow** - Lengkap dengan accept work, upload, submit ulang
3. ✅ **Sound Engineer - Bantu Perbaikan** - Lengkap dengan help fix endpoints
4. ✅ **Creative Work** - Lengkap dengan semua field yang diminta:
   - ✅ Script cerita video klip
   - ✅ Storyboard
   - ✅ Jadwal rekaman suara
   - ✅ Jadwal syuting
   - ✅ Lokasi syuting
   - ✅ Budget bayar talent
5. ✅ **Auto-create Creative Work** - Via MusicArrangementObserver
6. ✅ **Notifikasi** - Lengkap untuk semua role

**Status:** ✅ **READY FOR FRONTEND INTEGRATION**

---

**Last Updated:** 2026-01-27
