# ✅ Verifikasi Flow Lengkap: Producer, Music Arranger, Sound Engineer, Creative

**Tanggal:** 12 Desember 2025  
**Status:** ✅ **SEMUA FLOW SUDAH DIIMPLEMENTASIKAN & AMAN**

---

## 📋 Ringkasan Eksekutif

Semua flow yang diminta sudah **LENGKAP** dan **AMAN**. Semua role (Producer, Music Arranger, Sound Engineer, Creative) sudah memiliki endpoint dan workflow yang sesuai dengan requirement.

---

## 🔄 FLOW LENGKAP

### **FLOW 1: Producer - Review Song Proposal**

**Status:** ✅ **LENGKAP & AMAN**

#### **1.1. Producer: Terima Notifikasi**

**Endpoint:** `GET /api/live-tv/producer/approvals`

**Notifikasi yang Diterima:**
- ✅ `song_proposal_submitted` - Music Arranger mengajukan lagu & penyanyi

**Controller:** `ProducerController::getApprovals()`

**Response:**
```json
{
  "success": true,
  "data": {
    "song_proposals": [
      {
        "id": 1,
        "episode_id": 1,
        "song_title": "Amazing Grace",
        "singer_name": "John Doe",
        "status": "song_proposal",
        "created_by": 2
      }
    ]
  }
}
```

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 76-150)

---

#### **1.2. Producer: Terima atau Tidak Usulan Lagu dan Penyanyi**

**Endpoints:**
- ✅ `POST /api/live-tv/producer/approvals/{id}/approve` - Approve song proposal
- ✅ `POST /api/live-tv/producer/approvals/{id}/reject` - Reject song proposal

**Request Body (Approve):**
```json
{
  "type": "song_proposal",
  "notes": "Lagu approved"
}
```

**Request Body (Reject):**
```json
{
  "type": "song_proposal",
  "reason": "Lagu sudah pernah digunakan"
}
```

**Flow:**
- ✅ Approve: Status `song_proposal` → `song_approved`
- ✅ Reject: Status `song_proposal` → `song_rejected`
- ✅ Notifikasi ke Music Arranger: `song_proposal_approved` / `song_proposal_rejected`
- ✅ Notifikasi ke Sound Engineers: `song_proposal_rejected_help_needed` (jika reject)

**Controller:** `ProducerController::approve()`, `reject()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 152-202, 640-690)

---

#### **1.3. Producer: Dapat Mengganti Usulan dari Music Arranger**

**Endpoint:** `PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer`

**Request Body:**
```json
{
  "song_title": "New Song Title",
  "singer_name": "New Singer Name",
  "song_id": 6,
  "singer_id": 11,
  "modification_notes": "Perlu ganti karena lagu sebelumnya sudah digunakan"
}
```

**Flow:**
- ✅ Producer dapat edit song/singer arrangement yang status `song_proposal`
- ✅ Original values disimpan di `original_song_title` dan `original_singer_name`
- ✅ Modified values disimpan di `producer_modified_song_title` dan `producer_modified_singer_name`
- ✅ Flag `producer_modified` di-set menjadi `true`
- ✅ Status tetap `song_proposal` (belum approve)
- ✅ Notifikasi ke Music Arranger: `arrangement_modified_by_producer`

**Controller:** `ProducerController::editArrangementSongSinger()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 1673-1750)

---

#### **1.4. Producer: Selesaikan Pekerjaan**

**Status:** ✅ **AUTO-COMPLETE**

Setelah approve/reject/edit, pekerjaan Producer selesai otomatis. Tidak perlu endpoint khusus.

---

#### **1.5. Masuk Kembali ke Music Arranger**

**Status:** ✅ **AUTO-NOTIFY**

Setelah Producer approve/reject/edit, Music Arranger otomatis menerima notifikasi dan dapat melanjutkan workflow.

---

### **FLOW 2: Music Arranger - Arrange Lagu**

**Status:** ✅ **LENGKAP & AMAN**

#### **2.1. Music Arranger: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `song_proposal_approved` - Song proposal diterima
- ✅ `song_proposal_rejected` - Song proposal ditolak
- ✅ `arrangement_modified_by_producer` - Producer mengubah song/singer

**Endpoint:** `GET /api/notifications`

---

#### **2.2. Music Arranger: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work`

**Flow:**
- ✅ Status: `song_approved` / `song_rejected` / `arrangement_rejected` → `arrangement_in_progress`
- ✅ Music Arranger siap untuk arrange lagu

**Controller:** `MusicArrangerController::acceptWork()`

**File:** `app/Http/Controllers/Api/MusicArrangerController.php` (line 724-765)

---

#### **2.3. Music Arranger: Arr Lagu**

**Endpoint:** `PUT /api/live-tv/roles/music-arranger/arrangements/{id}`

**Request Body:**
```json
{
  "file": "<file>", // Upload arrangement file (MP3/WAV/MIDI)
  "arrangement_notes": "Arrangement notes"
}
```

**Flow:**
- ✅ Upload arrangement file
- ✅ Status: `arrangement_in_progress` (setelah upload file)

**Controller:** `MusicArrangerController::update()`

**File:** `app/Http/Controllers/Api/MusicArrangerController.php` (line 336-418)

---

#### **2.4. Music Arranger: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/complete-work`

**Request Body:**
```json
{
  "completion_notes": "Arrangement selesai"
}
```

**Flow:**
- ✅ Status: `arrangement_in_progress` → `arrangement_submitted`
- ✅ Notifikasi ke Producer: `music_arrangement_completed`
- ✅ Producer bisa review arrangement file

**Controller:** `MusicArrangerController::completeWork()`

**File:** `app/Http/Controllers/Api/MusicArrangerController.php` (line 771-854)

---

#### **2.5. Music Arranger: Ajukan ke Producer**

**Status:** ✅ **AUTO-SUBMIT**

Setelah `completeWork()`, arrangement file otomatis di-submit ke Producer dengan status `arrangement_submitted`.

---

### **FLOW 3: Producer - QC Music (Manual)**

**Status:** ✅ **LENGKAP & AMAN**

**Catatan:** Producer melakukan QC music secara manual dengan approve/reject arrangement file.

#### **3.1. Producer: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `music_arrangement_submitted` - Music Arranger submit arrangement file
- ✅ `music_arrangement_completed` - Music Arranger selesai arrange lagu

**Endpoint:** `GET /api/live-tv/producer/approvals`

---

#### **3.2. Producer: QC Music Secara Manual**

**Endpoint:** `POST /api/live-tv/producer/approvals/{id}/approve` atau `POST /api/live-tv/producer/approvals/{id}/reject`

**Request Body (Approve):**
```json
{
  "type": "music_arrangement",
  "notes": "Arrangement quality bagus, approved!"
}
```

**Request Body (Reject):**
```json
{
  "type": "music_arrangement",
  "reason": "Tempo terlalu cepat, perlu diperlambat"
}
```

**Flow:**
- ✅ Producer review arrangement file (QC manual)
- ✅ Approve: Status `arrangement_submitted` → `arrangement_approved`
- ✅ Reject: Status `arrangement_submitted` → `arrangement_rejected`
- ✅ Notifikasi ke Music Arranger: `music_arrangement_approved` / `music_arrangement_rejected`
- ✅ Jika approve: Auto-create Sound Engineer Recording task
- ✅ Jika approve: Auto-create Creative Work task

**Controller:** `ProducerController::approve()`, `reject()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 203-360, 717-800)

---

#### **3.3. Producer: Selesaikan Pekerjaan**

**Status:** ✅ **AUTO-COMPLETE**

Setelah approve/reject, pekerjaan Producer selesai otomatis.

---

### **FLOW 3A: Jika Ditolak (No) - Music Arranger & Sound Engineer**

**Status:** ✅ **LENGKAP & AMAN**

#### **3A.1. Masuk Kembali ke Music Arranger dan Sound Engineer**

**Status:** ✅ **AUTO-NOTIFY**

Setelah Producer reject arrangement, Music Arranger dan Sound Engineer otomatis menerima notifikasi:
- ✅ Music Arranger: `music_arrangement_rejected`
- ✅ Sound Engineer: `arrangement_rejected_help_needed`

---

#### **3A.2. Music Arranger: Terima Notifikasi, Terima Pekerjaan, Arr Lagu, Selesaikan Pekerjaan**

**Notifikasi:** `music_arrangement_rejected`

**Endpoints:**
- ✅ `POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work` - Terima pekerjaan
- ✅ `PUT /api/live-tv/roles/music-arranger/arrangements/{id}` - Arr lagu (upload file baru)
- ✅ `POST /api/live-tv/roles/music-arranger/arrangements/{id}/complete-work` - Selesaikan pekerjaan

**Flow:**
- ✅ Status: `arrangement_rejected` → `arrangement_in_progress` → `arrangement_submitted`
- ✅ Music Arranger dapat revisi arrangement file
- ✅ Submit ulang ke Producer untuk review

**Controller:** `MusicArrangerController::acceptWork()`, `update()`, `completeWork()`

**File:** `app/Http/Controllers/Api/MusicArrangerController.php` (line 724-854)

---

#### **3A.3. Sound Engineer: Terima Notifikasi, Bantu Perbaikan Arr Lagu, Selesaikan Pekerjaan**

**Notifikasi:** `arrangement_rejected_help_needed`

**Endpoints:**
- ✅ `GET /api/live-tv/roles/sound-engineer/rejected-arrangements` - List rejected arrangements
- ✅ `POST /api/live-tv/roles/sound-engineer/arrangements/{arrangementId}/help-fix` - Bantu perbaikan

**Request Body:**
```json
{
  "help_notes": "Saran perbaikan arrangement",
  "suggested_fixes": "Perlu perbaikan tempo dan mixing",
  "file_path": "optional_fixed_file_path"
}
```

**Flow:**
- ✅ Sound Engineer memberikan saran perbaikan
- ✅ Status: `arrangement_rejected` → tetap `arrangement_rejected` (dengan help notes)
- ✅ Notifikasi ke Music Arranger: `sound_engineer_helping_arrangement`
- ✅ Music Arranger dapat menggunakan saran untuk revisi

**Controller:** `SoundEngineerController::helpFixArrangement()`

**File:** `app/Http/Controllers/Api/SoundEngineerController.php` (line 1178-1286)

---

### **FLOW 3B: Jika Diterima (Yes) - Creative**

**Status:** ✅ **LENGKAP & AMAN**

#### **3B.1. Creative: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `creative_work_created` - Creative work task dibuat setelah arrangement approved

**Endpoint:** `GET /api/notifications`

---

#### **3B.2. Creative: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/creative/works/{id}/accept-work`

**Flow:**
- ✅ Status: `draft` → `in_progress`
- ✅ Creative siap untuk mulai bekerja

**Controller:** `CreativeController::acceptWork()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 375-416)

---

#### **3B.3. Creative: Tulis Script Cerita Video Klip Lagu**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "script_content": "Script cerita video klip lagu..."
}
```

**Field:** `script_content` (text)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **3B.4. Creative: Buat Storyboard**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "storyboard_data": {
    "scenes": [...]
  }
}
```

**Field:** `storyboard_data` (JSON)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **3B.5. Creative: Input Jadwal Rekaman Suara**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "recording_schedule": "2025-12-20 10:00:00"
}
```

**Field:** `recording_schedule` (datetime)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **3B.6. Creative: Input Jadwal Syuting**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "shooting_schedule": "2025-12-25 08:00:00"
}
```

**Field:** `shooting_schedule` (datetime)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **3B.7. Creative: Lokasi Syuting**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "shooting_location": "Studio HCI, Jakarta"
}
```

**Field:** `shooting_location` (string)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **3B.8. Creative: Buat Budget Bayar Talent**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "budget_data": {
    "talent_budget": 5000000,
    "production_budget": 3000000,
    "other_budget": 1000000
  }
}
```

**Field:** `budget_data` (JSON)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **3B.9. Creative: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/creative/works/{id}/complete-work`

**Request Body:**
```json
{
  "script_content": "Script cerita video klip lagu...",
  "storyboard_data": {...},
  "recording_schedule": "2025-12-20 10:00:00",
  "shooting_schedule": "2025-12-25 08:00:00",
  "shooting_location": "Studio HCI, Jakarta",
  "budget_data": {
    "talent_budget": 5000000,
    "production_budget": 3000000,
    "other_budget": 1000000
  },
  "completion_notes": "Semua pekerjaan selesai"
}
```

**Flow:**
- ✅ Validasi: Semua field required harus ada
- ✅ Status: `in_progress` → `submitted`
- ✅ Notifikasi ke Producer: `creative_work_submitted`
- ✅ Producer bisa review creative work

**Controller:** `CreativeController::completeWork()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 423-487)

---

## 📊 STATUS FLOW DIAGRAM

```
1. Music Arranger:
   song_proposal (ajukan lagu & penyanyi)
   ↓
2. Producer:
   ├─ Approve → song_approved
   │   ↓
   │   Music Arranger:
   │   arrangement_in_progress (arrange lagu)
   │   ↓
   │   arrangement_submitted (submit file)
   │   ↓
   │   Producer:
   │   arrangement_approved (QC manual - approve)
   │   ↓
   │   Creative:
   │   draft → in_progress → submitted
   │
   ├─ Reject → song_rejected
   │   ↓
   │   Music Arranger:
   │   arrangement_in_progress (revisi)
   │   ↓
   │   arrangement_submitted
   │   OR
   │   Sound Engineer:
   │   helpFixArrangement (bantu perbaikan)
   │   ↓
   │   Music Arranger:
   │   arrangement_in_progress
   │
   └─ Edit → song_proposal (modified)
       ↓
       Music Arranger:
       arrangement_in_progress
```

---

## 🔒 KEAMANAN

### ✅ Role Validation
- ✅ Producer: `if ($user->role !== 'Producer')`
- ✅ Music Arranger: `if ($user->role !== 'Music Arranger')`
- ✅ Sound Engineer: `if (!$this->isSoundEngineer($user))`
- ✅ Creative: `if ($user->role !== 'Creative')`

### ✅ Authorization
- ✅ Producer hanya bisa approve/reject arrangement dari ProductionTeam mereka
- ✅ Music Arranger hanya bisa update arrangement yang mereka buat sendiri
- ✅ Sound Engineer hanya bisa help arrangement dari ProductionTeam mereka
- ✅ Creative hanya bisa update creative work yang mereka buat sendiri

### ✅ Input Validation
- ✅ Laravel Validator untuk semua input
- ✅ Required fields validation
- ✅ Type validation
- ✅ Size/limit validation

### ✅ Status Validation
- ✅ Status checks sebelum setiap action
- ✅ Workflow state management

---

## ✅ KESIMPULAN

### Status: **LENGKAP & AMAN**

Semua flow yang diminta sudah diimplementasikan:

1. ✅ **Producer - Review Song Proposal** - Terima notifikasi, approve/reject/edit, selesai pekerjaan
2. ✅ **Music Arranger - Arrange Lagu** - Terima notifikasi, terima pekerjaan, arr lagu, selesaikan pekerjaan, ajukan ke producer
3. ✅ **Producer - QC Music** - Terima notifikasi, QC manual (approve/reject), selesaikan pekerjaan
4. ✅ **Jika Ditolak - Music Arranger & Sound Engineer** - Notifikasi, terima pekerjaan, bantu perbaikan, selesaikan pekerjaan
5. ✅ **Jika Diterima - Creative** - Terima notifikasi, terima pekerjaan, script, storyboard, jadwal rekaman, jadwal syuting, lokasi, budget, selesaikan pekerjaan

### Keamanan: **AMAN**
- ✅ Role validation di semua endpoint
- ✅ Authorization checks
- ✅ Input validation
- ✅ Status validation
- ✅ Workflow state management

---

**Last Updated:** 12 Desember 2025  
**Status:** ✅ **VERIFIED & COMPLETE - READY FOR PRODUCTION**

