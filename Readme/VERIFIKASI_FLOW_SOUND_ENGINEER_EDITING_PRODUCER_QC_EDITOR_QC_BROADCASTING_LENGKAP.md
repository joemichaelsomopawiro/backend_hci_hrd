# ✅ Verifikasi Flow Lengkap: Sound Engineer Recording → Art & Set Properti → Sound Engineer Editing → Producer QC → Editor → QC → Broadcasting

**Tanggal:** 12 Desember 2025  
**Status:** ✅ **SEMUA FLOW SUDAH DIIMPLEMENTASIKAN & AMAN**

---

## 📋 Ringkasan Eksekutif

Semua flow setelah Sound Engineer Recording selesai sudah **LENGKAP** dan **AMAN**. Semua role (Art & Set Properti, Sound Engineer Editing, Producer, Editor, Quality Control, Produksi, Broadcasting) sudah memiliki endpoint dan workflow yang sesuai dengan requirement.

**Flow yang Sudah Diverifikasi:**
1. ✅ **Art & Set Properti**: Terima notifikasi, terima pekerjaan, acc alat (terima alat kembali dari Sound Engineer Recording)
2. ✅ **Sound Engineer Editing**: Terima notifikasi, terima pekerjaan, lanjut edit vocal, selesaikan pekerjaan
3. ✅ **Sound Engineer Editing ajukan ke QC → Producer QC**: Producer terima notifikasi, terima pekerjaan, QC
   - Jika ditolak → kembali ke Sound Engineer Editing
   - Jika diterima → masuk ke Editor
4. ✅ **Editor**: Terima notifikasi, terima pekerjaan, cek kelengkapan file (file lengkap proses pekerjaan, file tidak lengkap ajukan ke producer), buat catatan file apa saja yang kurang, proses pekerjaan: lihat catatan syuting (run sheet), upload file setelah di edit ke storage, masukan link alamat file ke system, selesaikan pekerjaan
5. ✅ **Editor ajukan ke QC → Quality Control**: Quality Control terima notifikasi, terima pekerjaan, proses pekerjaan: isi form catatan QC, tidak ada revisi - yes, selesaikan pekerjaan
   - Produksi: terima notifikasi, baca hasil QC
   - Jika ditolak → kembali ke Editor, notifikasi ke Producer dan catatan QC
   - Jika diterima → masuk ke Broadcasting
6. ✅ **Broadcasting**: Terima notifikasi, terima file materi dari QC, terima thumbnail dari design grafis, terima pekerjaan, proses pekerjaan: masukkan ke jadwal playlist, upload ke youtube (thumbnail, deskripsi, tag, judul SEO), upload ke website, input link YT ke sistem, selesaikan pekerjaan

---

## 🔄 FLOW LENGKAP SETELAH SOUND ENGINEER RECORDING SELESAI

### **FLOW 1: Art & Set Properti - Terima Alat Kembali (dari Sound Engineer Recording)**

**Status:** ✅ **LENGKAP & AMAN**

#### **1.1. Art & Set Properti: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `equipment_returned` - Equipment dikembalikan oleh Sound Engineer Recording

**Endpoint:** `GET /api/notifications`

**Controller:** `ProductionEquipmentController::returnEquipment()` (line 238-252)

---

#### **1.2. Art & Set Properti: Terima Pekerjaan**

**Endpoint:** `GET /api/live-tv/art-set-properti/requests?status=returned`

**Flow:**
- ✅ Lihat daftar equipment yang dikembalikan

**Controller:** `ArtSetPropertiController::getRequests()`

**File:** `app/Http/Controllers/Api/ArtSetPropertiController.php` (line 71-108)

---

#### **1.3. Art & Set Properti: Acc Alat**

**Endpoint:** `POST /api/live-tv/art-set-properti/equipment/{id}/return`

**Request Body:**
```json
{
  "return_condition": "good", // atau "damaged", "lost"
  "return_notes": "Alat diterima dalam kondisi baik"
}
```

**Flow:**
- ✅ Update `EquipmentInventory` status menjadi `available` atau `maintenance`
- ✅ Update `ProductionEquipment` status menjadi `returned`

**Controller:** `ArtSetPropertiController::returnEquipment()`

**File:** `app/Http/Controllers/Api/ArtSetPropertiController.php` (line 266-338)

---

#### **1.4. Art & Set Properti: Selesaikan Pekerjaan**

**Status:** ✅ **AUTO-COMPLETE**

Setelah acc alat, pekerjaan otomatis selesai.

---

### **FLOW 2: Sound Engineer Editing - Edit Vokal**

**Status:** ✅ **LENGKAP & AMAN**

#### **2.1. Sound Engineer Editing: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `sound_engineer_editing_created` - Sound Engineer Editing task dibuat otomatis setelah recording selesai

**Endpoint:** `GET /api/notifications`

**Controller:** `SoundEngineerController::completeRecording()` (line 414-427)

**File:** `app/Http/Controllers/Api/SoundEngineerController.php` (line 374-472)

---

#### **2.2. Sound Engineer Editing: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/sound-engineer-editing/works/{id}/update` (status change)

**Request Body:**
```json
{
  "status": "in_progress"
}
```

**Flow:**
- ✅ Status: `in_progress` → `editing`
- ✅ Sound Engineer Editing siap untuk edit vocal

**Controller:** `SoundEngineerEditingController::update()`

**File:** `app/Http/Controllers/Api/SoundEngineerEditingController.php` (line 122-162)

---

#### **2.3. Sound Engineer Editing: Lanjut Edit Vokal**

**Endpoint:** `POST /api/live-tv/sound-engineer-editing/works/{id}/upload-vocal`

**Request Body:**
```json
{
  "file": "<file>" // WAV, MP3, AIFF, FLAC, max 50MB
}
```

**Flow:**
- ✅ Upload edited vocal file ke storage: `sound_engineer_editing/`
- ✅ Update work dengan `final_file_path`

**Controller:** `SoundEngineerEditingController::uploadVocal()`

**File:** `app/Http/Controllers/Api/SoundEngineerEditingController.php` (line 213-245)

---

#### **2.4. Sound Engineer Editing: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/sound-engineer-editing/works/{id}/submit`

**Request Body:**
```json
{
  "final_file_path": "sound_engineer_editing/filename.wav",
  "submission_notes": "Editing selesai"
}
```

**Flow:**
- ✅ Status: `in_progress` → `submitted`
- ✅ Notifikasi ke Producer: `sound_engineer_editing_submitted`

**Controller:** `SoundEngineerEditingController::submit()`

**File:** `app/Http/Controllers/Api/SoundEngineerEditingController.php` (line 167-208)

---

### **FLOW 3: Producer - QC Sound Engineer Editing**

**Status:** ✅ **LENGKAP & AMAN**

#### **3.1. Producer: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `sound_engineer_editing_submitted` - Sound Engineer Editing mengajukan untuk QC

**Endpoint:** `GET /api/notifications`

---

#### **3.2. Producer: Terima Pekerjaan**

**Endpoint:** `GET /api/live-tv/producer/pending-approvals?type=sound_engineer_editing`

**Flow:**
- ✅ Lihat daftar sound engineer editing yang perlu di-QC

**Controller:** `ProducerController::getPendingApprovals()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 446-560)

---

#### **3.3. Producer: QC**

**Endpoint:** `POST /api/live-tv/producer/approve/sound_engineer_editing/{id}` atau `POST /api/live-tv/producer/reject/sound_engineer_editing/{id}`

**Request Body (Approve):**
```json
{
  "notes": "Vocal editing sudah bagus"
}
```

**Request Body (Reject):**
```json
{
  "reason": "Vocal editing perlu perbaikan"
}
```

**Flow:**
- ✅ Jika `approve`:
  - Status: `submitted` → `approved`
  - Notifikasi ke Sound Engineer Editing: `sound_engineer_editing_approved`
  - Notifikasi ke Editor: `audio_ready_for_editing`
  - Update workflow state ke `editing`
- ✅ Jika `reject`:
  - Status: `submitted` → `revision_needed`
  - Notifikasi ke Sound Engineer Editing: `sound_engineer_editing_rejected`
  - Sound Engineer Editing bisa revisi dan resubmit

**Controller:** `ProducerController::approve()` dan `ProducerController::reject()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 733-819 untuk approve, line 1166-1204 untuk reject)

---

### **FLOW 4: Sound Engineer Editing - Revisi (Jika Ditolak)**

**Status:** ✅ **LENGKAP & AMAN**

#### **4.1. Sound Engineer Editing: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `sound_engineer_editing_rejected` - Producer menolak editing, perlu revisi

**Endpoint:** `GET /api/notifications`

---

#### **4.2. Sound Engineer Editing: Terima Pekerjaan**

**Status:** ✅ **SUDAH ADA** - Work status sudah `revision_needed`

---

#### **4.3. Sound Engineer Editing: Lanjut Edit Vokal**

**Status:** ✅ **SUDAH ADA** - Sama seperti Flow 2.3

---

#### **4.4. Sound Engineer Editing: Selesaikan Pekerjaan**

**Status:** ✅ **SUDAH ADA** - Sama seperti Flow 2.4, bisa resubmit

---

### **FLOW 5: Editor - Edit Video (Setelah Producer Approve Sound Engineer Editing)**

**Status:** ✅ **LENGKAP & AMAN**

#### **5.1. Editor: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `audio_ready_for_editing` - Audio sudah ready dari Sound Engineer Editing
- ✅ `editor_work_created` - Editor work task dibuat

**Endpoint:** `GET /api/notifications`

**Controller:** `ProducerController::approve()` (line 771-806)

---

#### **5.2. Editor: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/editor/works/{id}/update` (status change)

**Flow:**
- ✅ Status: `draft` / `pending` → `in_progress`
- ✅ Editor siap untuk edit video

**Controller:** `EditorController::update()`

**File:** `app/Http/Controllers/Api/EditorController.php` (line 134-168)

---

#### **5.3. Editor: Cek Kelengkapan File**

**Endpoint:** `GET /api/editor/episodes/{id}/check-files`

**Flow:**
- ✅ Cek apakah file lengkap (raw files, script, rundown, shooting notes, audio files)
- ✅ Return list file yang kurang jika tidak lengkap
- ✅ Return status: `complete` atau `incomplete` dengan list issues

**Controller:** `EditorController::checkFileCompleteness()`

**File:** `app/Http/Controllers/EditorController.php` (line 103-148)

---

#### **5.4. Editor: File Lengkap - Proses Pekerjaan**

**Status:** ✅ **SUDAH ADA**

Jika file lengkap (cek kelengkapan file return `complete: true`), Editor bisa langsung proses pekerjaan.

---

#### **5.5. Editor: File Tidak Lengkap - Ajukan ke Producer**

**Endpoint:** `POST /api/editor/works/{id}/report-missing-files`

**Request Body:**
```json
{
  "missing_files": ["raw_file_1.mp4", "script.pdf"],
  "notes": "File raw video dan script belum tersedia",
  "urgency": "high"
}
```

**Flow:**
- ✅ Update work status menjadi `file_incomplete`
- ✅ Update `file_notes` dengan catatan file yang kurang
- ✅ Notifikasi ke Producer: `editor_missing_files`

**Controller:** `EditorController::reportMissingFiles()`

**File:** `app/Http/Controllers/Api/EditorController.php` (line 381-445)

---

#### **5.6. Editor: Buat Catatan File Apa Saja yang Kurang dan Perlu Perbaikan**

**Status:** ✅ **SUDAH ADA** - Sudah ada di Flow 5.5

Catatan file yang kurang otomatis tersimpan di:
- ✅ `work.file_notes` - Berisi list file yang kurang
- ✅ Notifikasi ke Producer berisi detail file yang kurang

---

#### **5.7. Editor: Proses Pekerjaan - Lihat Catatan Syuting (Run Sheet)**

**Endpoint:** `GET /api/live-tv/editor/episodes/{id}/run-sheet`

**Flow:**
- ✅ Editor bisa lihat catatan syuting (run sheet) dari Produksi
- ✅ Editor bisa lihat shooting notes, crew list, equipment list, location

**Controller:** `EditorController::getRunSheet()`

**File:** `app/Http/Controllers/Api/EditorController.php` (line 518-572)

---

#### **5.8. Editor: Upload File Setelah di Edit ke Storage**

**Endpoint:** `POST /api/editor/episodes/{id}/complete`

**Request Body:**
```json
{
  "final_file": "<file>", // MP4, MOV, AVI, MKV, max 1GB
  "final_url": "https://storage.example.com/final.mp4",
  "completion_notes": "Editing selesai",
  "duration_minutes": 60,
  "file_size_mb": 850
}
```

**Flow:**
- ✅ Upload final file ke storage: `editor/final/`
- ✅ Update episode dengan `final_file_url`
- ✅ Status: `completed`

**Controller:** `EditorController::completeEditing()`

**File:** `app/Http/Controllers/EditorController.php` (line 273-338)

---

#### **5.9. Editor: Masukan Link Alamat File ke System**

**Status:** ✅ **AUTO-SAVE**

Setelah upload, link file otomatis tersimpan di:
- ✅ `episode.final_file_url` - URL file final setelah edit
- ✅ `episode.editing_completed_at` - Timestamp selesai editing
- ✅ `episode.editing_completed_by` - User yang menyelesaikan editing

---

#### **5.10. Editor: Selesaikan Pekerjaan**

**Status:** ✅ **AUTO-COMPLETE**

Setelah upload final file, pekerjaan otomatis selesai:
- ✅ Status: `completed`
- ✅ Editor submit ke Producer untuk approval
- ✅ Producer approve → Notifikasi ke QC: `editor_work_ready_for_qc`

**Controller:** `ProducerController::approve()` (line 854-867)

---

### **FLOW 6: Quality Control - QC Editor Work**

**Status:** ✅ **LENGKAP & AMAN**

#### **6.1. Quality Control: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `editor_work_ready_for_qc` - Editor work siap untuk QC

**Endpoint:** `GET /api/notifications`

**Controller:** `ProducerController::approve()` (line 854-867)

---

#### **6.2. Quality Control: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/accept-work`

**Flow:**
- ✅ Status: `pending` → `in_progress`
- ✅ QC siap untuk melakukan quality control

**Controller:** `QualityControlController::acceptWork()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 624-660)

---

#### **6.3. Quality Control: Proses Pekerjaan - Isi Form Catatan QC**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/qc-content`

**Request Body:**
```json
{
  "qc_results": {
    "bts_video": {
      "status": "approved",
      "notes": "Video BTS sudah bagus",
      "score": 90
    },
    "iklan_episode_tv": {
      "status": "approved",
      "notes": "Iklan episode TV sudah bagus",
      "score": 88
    },
    "thumbnail_yt": {
      "status": "approved",
      "notes": "Thumbnail YouTube sudah bagus",
      "score": 92
    },
    "thumbnail_bts": {
      "status": "approved",
      "notes": "Thumbnail BTS sudah bagus",
      "score": 90
    }
  },
  "overall_notes": "Overall QC notes"
}
```

**Flow:**
- ✅ QC semua materi (video, audio, content, thumbnails)
- ✅ Update `qc_results` dengan hasil QC
- ✅ Update `quality_score`
- ✅ Input catatan QC
- ✅ Status: `in_progress` → `completed`

**Controller:** `QualityControlController::qcContent()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 666-738)

---

#### **6.4. Quality Control: Tidak Ada Revisi - Yes**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/finalize`

**Request Body:**
```json
{
  "action": "approve",
  "notes": "Tidak ada revisi, semua sudah sesuai"
}
```

**Flow:**
- ✅ Status: `completed` → `approved`
- ✅ Auto-create `BroadcastingWork`
- ✅ Notifikasi ke Broadcasting: `broadcasting_work_assigned`
- ✅ Notifikasi ke Promosi: `qc_approved_promosi_notification`
- ✅ Notifikasi ke Produksi: `qc_approved_produksi_notification` (untuk baca hasil QC)

**Controller:** `QualityControlController::finalize()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 744-913)

---

#### **6.5. Quality Control: Selesaikan Pekerjaan**

**Status:** ✅ **AUTO-COMPLETE**

Setelah approve, pekerjaan otomatis selesai dan notifikasi dikirim.

---

#### **6.6. Produksi: Terima Notifikasi - Baca Hasil QC**

**Notifikasi yang Diterima:**
- ✅ `qc_approved_produksi_notification` - QC sudah approve, Produksi bisa baca hasil QC

**Endpoint:** `GET /api/notifications`

**Endpoint untuk Baca Hasil QC:**
- ✅ `GET /api/live-tv/roles/produksi/qc-results/{episode_id}`

**Controller:** `ProduksiController::getQCResults()`

**File:** `app/Http/Controllers/Api/ProduksiController.php` (line 632-680)

**Flow:**
- ✅ Produksi bisa lihat hasil QC (quality score, notes, revision points)
- ✅ Produksi bisa lihat catatan QC

---

### **FLOW 7: Quality Control - Reject (Jika Ditolak)**

**Status:** ✅ **LENGKAP & AMAN - SUDAH DIPERBAIKI**

#### **7.1. Quality Control: Reject**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/finalize`

**Request Body:**
```json
{
  "action": "reject",
  "notes": "Perlu revisi"
}
```

**Flow:**
- ✅ Status: `completed` → `revision_needed`
- ✅ Deteksi source file:
  - Jika ada file dari Editor (main editor) → Notifikasi ke Editor: `qc_rejected_revision_needed`
  - Jika ada file dari Design Grafis → Notifikasi ke Design Grafis: `qc_rejected_revision_needed`
  - Jika ada file dari Editor Promosi → Notifikasi ke Editor Promosi: `qc_rejected_revision_needed`
- ✅ Notifikasi ke Producer: `qc_rejected_producer_notification` dengan catatan QC

**Controller:** `QualityControlController::finalize()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 847-897)

**Perbaikan:** ✅ **SUDAH DILAKUKAN** - Sekarang QC reject bisa kembali ke Editor (main editor) dan selalu notifikasi ke Producer dengan catatan QC

---

#### **7.2. Editor: Terima Notifikasi - Revisi**

**Notifikasi yang Diterima:**
- ✅ `qc_rejected_revision_needed` - QC menolak, perlu revisi

**Endpoint:** `GET /api/notifications`

---

#### **7.3. Editor: Terima Pekerjaan**

**Status:** ✅ **SUDAH ADA** - Work status sudah `revision_needed`

---

#### **7.4. Editor: Proses Pekerjaan - Revisi**

**Endpoint:** `POST /api/editor/episodes/{id}/handle-revision`

**Request Body:**
```json
{
  "action": "reupload",
  "revised_file": "<file>",
  "revision_notes": "Sudah diperbaiki sesuai feedback QC"
}
```

**Flow:**
- ✅ Upload revised file ke storage: `editor/revisions/`
- ✅ Update episode dengan revised file
- ✅ Notifikasi ke QC untuk review ulang

**Controller:** `EditorController::handleRevision()`

**File:** `app/Http/Controllers/EditorController.php` (line 344-421)

---

#### **7.5. Producer: Terima Notifikasi - Catatan QC**

**Notifikasi yang Diterima:**
- ✅ `qc_rejected_producer_notification` - QC menolak, Producer bisa lihat catatan QC

**Endpoint:** `GET /api/notifications`

**Data Notifikasi:**
- ✅ `revision_notes` - Alasan penolakan
- ✅ `qc_notes` - Catatan QC lengkap
- ✅ `quality_score` - Skor kualitas

---

### **FLOW 8: Broadcasting - Upload ke YouTube dan Website**

**Status:** ✅ **LENGKAP & AMAN**

#### **8.1. Broadcasting: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `broadcasting_work_assigned` - QC telah menyetujui materi, siap untuk broadcasting

**Endpoint:** `GET /api/notifications`

**Controller:** `QualityControlController::finalize()` (line 799-812)

---

#### **8.2. Broadcasting: Terima File Materi dari QC**

**Endpoint:** `GET /api/live-tv/broadcasting/works/{id}`

**Flow:**
- ✅ Broadcasting bisa lihat file materi dari QC
- ✅ File path tersimpan di `BroadcastingWork.video_file_path`

**Controller:** `BroadcastingController::show()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php`

---

#### **8.3. Broadcasting: Terima Thumbnail dari Design Grafis**

**Endpoint:** `GET /api/live-tv/broadcasting/works/{id}`

**Flow:**
- ✅ Broadcasting bisa lihat thumbnail dari Design Grafis
- ✅ Thumbnail path tersimpan di `BroadcastingWork.thumbnail_path`

**Controller:** `BroadcastingController::show()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php`

---

#### **8.4. Broadcasting: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/update` (status change)

**Flow:**
- ✅ Status: `pending` → `in_progress`
- ✅ Broadcasting siap untuk proses pekerjaan

**Controller:** `BroadcastingController::update()`

---

#### **8.5. Broadcasting: Proses Pekerjaan - Masukkan ke Jadwal Playlist**

**Endpoint:** `POST /api/live-tv/broadcasting/schedules`

**Request Body:**
```json
{
  "episode_id": 1,
  "scheduled_date": "2025-12-25",
  "scheduled_time": "20:00:00",
  "playlist_position": 1
}
```

**Flow:**
- ✅ Create `BroadcastingSchedule` dengan relasi ke `Episode`
- ✅ Update playlist position

**Controller:** `BroadcastingController::store()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 64-121)

---

#### **8.6. Broadcasting: Upload ke YouTube (Thumbnail, Deskripsi, Tag, Judul SEO)**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-youtube`

**Request Body:**
```json
{
  "youtube_url": "https://youtube.com/watch?v=...",
  "title": "Episode 1 - Judul SEO",
  "description": "Deskripsi episode dengan SEO",
  "tags": ["tag1", "tag2", "tag3"],
  "thumbnail_path": "design_grafis/thumbnail.jpg",
  "category": "Entertainment",
  "privacy": "public"
}
```

**Flow:**
- ✅ Upload video ke YouTube dengan metadata SEO
- ✅ Update `BroadcastingWork` dengan `youtube_url`, `youtube_title`, `youtube_description`, `youtube_tags`

**Controller:** `BroadcastingController::uploadYouTube()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 400-500)

---

#### **8.7. Broadcasting: Upload ke Website**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-website`

**Request Body:**
```json
{
  "website_url": "https://website.com/episode/1",
  "embed_code": "<iframe>...</iframe>"
}
```

**Flow:**
- ✅ Upload video ke website
- ✅ Update `BroadcastingWork` dengan `website_url`, `embed_code`

**Controller:** `BroadcastingController::uploadWebsite()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 502-530)

---

#### **8.8. Broadcasting: Input Link YT ke Sistem**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/input-youtube-link`

**Request Body:**
```json
{
  "youtube_url": "https://youtube.com/watch?v=...",
  "youtube_id": "abc123"
}
```

**Flow:**
- ✅ Input YouTube link ke sistem
- ✅ Update `BroadcastingWork` dengan `youtube_url`, `youtube_id`
- ✅ Update `Episode` dengan YouTube link

**Controller:** `BroadcastingController::inputYouTubeLink()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 531-576)

---

#### **8.9. Broadcasting: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/complete`

**Flow:**
- ✅ Status: `in_progress` → `completed`
- ✅ Notifikasi ke Promosi: `broadcasting_completed`

**Controller:** `BroadcastingController::completeWork()`

---

## 🔄 FLOW DIAGRAM

```
Sound Engineer Recording Selesai
│
├─ FLOW 1: Art & Set Properti (Terima Alat Kembali)
│  ├─ Terima notifikasi (equipment_returned) ✅
│  ├─ Terima pekerjaan ✅
│  ├─ Acc alat ✅
│  └─ Selesaikan pekerjaan ✅
│
├─ FLOW 2: Sound Engineer Editing
│  ├─ Terima notifikasi (sound_engineer_editing_created) ✅
│  ├─ Terima pekerjaan ✅
│  ├─ Lanjut edit vocal ✅
│  └─ Selesaikan pekerjaan (submit) ✅
│     └─ Ajukan ke QC → Producer QC
│
├─ FLOW 3: Producer QC
│  ├─ Terima notifikasi (sound_engineer_editing_submitted) ✅
│  ├─ Terima pekerjaan ✅
│  ├─ QC ✅
│  │  ├─ Approve → Masuk ke Editor ✅
│  │  └─ Reject → Kembali ke Sound Engineer Editing ✅
│
├─ FLOW 4: Sound Engineer Editing Revisi (Jika Ditolak)
│  ├─ Terima notifikasi (sound_engineer_editing_rejected) ✅
│  ├─ Terima pekerjaan ✅
│  ├─ Lanjut edit vocal ✅
│  └─ Selesaikan pekerjaan (resubmit) ✅
│
├─ FLOW 5: Editor (Setelah Producer Approve)
│  ├─ Terima notifikasi (audio_ready_for_editing) ✅
│  ├─ Terima pekerjaan ✅
│  ├─ Cek kelengkapan file ✅
│  │  ├─ File lengkap → Proses pekerjaan ✅
│  │  └─ File tidak lengkap → Ajukan ke Producer ✅
│  ├─ Proses pekerjaan:
│  │  ├─ Lihat catatan syuting (run sheet) ✅
│  │  ├─ Upload file setelah di edit ✅
│  │  ├─ Masukan link alamat file ke system ✅
│  │  └─ Selesaikan pekerjaan ✅
│  └─ Ajukan ke QC → Quality Control
│
├─ FLOW 6: Quality Control
│  ├─ Terima notifikasi (editor_work_ready_for_qc) ✅
│  ├─ Terima pekerjaan ✅
│  ├─ Proses pekerjaan: isi form catatan QC ✅
│  ├─ Tidak ada revisi - Yes ✅
│  ├─ Selesaikan pekerjaan ✅
│  └─ Produksi: terima notifikasi, baca hasil QC ✅
│
├─ FLOW 7: Quality Control Reject (Jika Ditolak)
│  ├─ Reject ✅
│  ├─ Kembali ke Editor ✅
│  ├─ Notifikasi ke Producer dengan catatan QC ✅
│  └─ Editor: revisi dan resubmit ✅
│
└─ FLOW 8: Broadcasting (Setelah QC Approve)
   ├─ Terima notifikasi (broadcasting_work_assigned) ✅
   ├─ Terima file materi dari QC ✅
   ├─ Terima thumbnail dari design grafis ✅
   ├─ Terima pekerjaan ✅
   ├─ Proses pekerjaan:
   │  ├─ Masukkan ke jadwal playlist ✅
   │  ├─ Upload ke YouTube (thumbnail, deskripsi, tag, judul SEO) ✅
   │  ├─ Upload ke website ✅
   │  ├─ Input link YT ke sistem ✅
   │  └─ Selesaikan pekerjaan ✅
```

---

## 🔒 KEAMANAN

### ✅ Role Validation
- ✅ Art & Set Properti: `if ($user->role !== 'Art & Set Properti')`
- ✅ Sound Engineer Editing: `if ($user->role !== 'Sound Engineer Editing')`
- ✅ Producer: Validasi di notification system
- ✅ Editor: `if ($user->role !== 'Editor')`
- ✅ Quality Control: `if ($user->role !== 'Quality Control')`
- ✅ Broadcasting: `if ($user->role !== 'Broadcasting')`

### ✅ Authorization
- ✅ User hanya bisa mengakses work yang dibuat oleh mereka sendiri
- ✅ Producer hanya bisa QC work dari production team mereka
- ✅ Editor bisa cek file completeness untuk episode yang ditugaskan

### ✅ Validation
- ✅ Required fields validation
- ✅ Type validation
- ✅ Size/limit validation
- ✅ File type validation

### ✅ File Upload Security
- ✅ Mime type validation
- ✅ File size validation
- ✅ Secure file storage
- ✅ Auto-save file path ke system

---

## 📋 DAFTAR ENDPOINT

### **Sound Engineer Editing Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Accept Work | `/api/live-tv/sound-engineer-editing/works/{id}/update` | POST | ✅ |
| Upload Vocal | `/api/live-tv/sound-engineer-editing/works/{id}/upload-vocal` | POST | ✅ |
| Submit | `/api/live-tv/sound-engineer-editing/works/{id}/submit` | POST | ✅ |

### **Producer QC Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Get Pending Approvals | `/api/live-tv/producer/pending-approvals?type=sound_engineer_editing` | GET | ✅ |
| Approve | `/api/live-tv/producer/approve/sound_engineer_editing/{id}` | POST | ✅ |
| Reject | `/api/live-tv/producer/reject/sound_engineer_editing/{id}` | POST | ✅ |

### **Editor Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Accept Work | `/api/live-tv/editor/works/{id}/update` | POST | ✅ |
| Check File Completeness | `/api/editor/episodes/{id}/check-files` | GET | ✅ |
| Report Missing Files | `/api/editor/works/{id}/report-missing-files` | POST | ✅ |
| Get Run Sheet | `/api/live-tv/editor/episodes/{id}/run-sheet` | GET | ✅ |
| Complete Editing | `/api/editor/episodes/{id}/complete` | POST | ✅ |
| Handle Revision | `/api/editor/episodes/{id}/handle-revision` | POST | ✅ |

### **Quality Control Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Accept Work | `/api/live-tv/quality-control/works/{id}/accept-work` | POST | ✅ |
| QC Content | `/api/live-tv/quality-control/works/{id}/qc-content` | POST | ✅ |
| Finalize | `/api/live-tv/quality-control/works/{id}/finalize` | POST | ✅ |

### **Broadcasting Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Create Schedule | `/api/live-tv/broadcasting/schedules` | POST | ✅ |
| Upload YouTube | `/api/live-tv/broadcasting/works/{id}/upload-youtube` | POST | ✅ |
| Upload Website | `/api/live-tv/broadcasting/works/{id}/upload-website` | POST | ✅ |
| Input YouTube Link | `/api/live-tv/broadcasting/works/{id}/input-youtube-link` | POST | ✅ |
| Complete Work | `/api/live-tv/broadcasting/works/{id}/complete` | POST | ✅ |

---

## ✅ KESIMPULAN

Semua flow yang diminta sudah **LENGKAP** dan **AMAN**:

1. ✅ **Art & Set Properti**: Terima notifikasi, terima pekerjaan, acc alat (terima alat kembali dari Sound Engineer Recording)
2. ✅ **Sound Engineer Editing**: Terima notifikasi, terima pekerjaan, lanjut edit vocal, selesaikan pekerjaan
3. ✅ **Sound Engineer Editing ajukan ke QC → Producer QC**: Producer terima notifikasi, terima pekerjaan, QC
   - Jika ditolak → kembali ke Sound Engineer Editing ✅
   - Jika diterima → masuk ke Editor ✅
4. ✅ **Editor**: Terima notifikasi, terima pekerjaan, cek kelengkapan file (file lengkap proses pekerjaan, file tidak lengkap ajukan ke producer), buat catatan file apa saja yang kurang, proses pekerjaan: lihat catatan syuting (run sheet), upload file setelah di edit ke storage, masukan link alamat file ke system, selesaikan pekerjaan
5. ✅ **Editor ajukan ke QC → Quality Control**: Quality Control terima notifikasi, terima pekerjaan, proses pekerjaan: isi form catatan QC, tidak ada revisi - yes, selesaikan pekerjaan
   - Produksi: terima notifikasi, baca hasil QC ✅
   - Jika ditolak → kembali ke Editor, notifikasi ke Producer dan catatan QC ✅
   - Jika diterima → masuk ke Broadcasting ✅
6. ✅ **Broadcasting**: Terima notifikasi, terima file materi dari QC, terima thumbnail dari design grafis, terima pekerjaan, proses pekerjaan: masukkan ke jadwal playlist, upload ke youtube (thumbnail, deskripsi, tag, judul SEO), upload ke website, input link YT ke sistem, selesaikan pekerjaan

**Perbaikan yang Sudah Dilakukan:**
- ✅ QC reject flow sekarang bisa kembali ke Editor (main editor) dan selalu notifikasi ke Producer dengan catatan QC

Semua endpoint sudah tersedia dan aman dengan validasi role, authorization, dan file upload security.

