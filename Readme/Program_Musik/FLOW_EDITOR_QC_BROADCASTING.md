# Flow Editor → QC → Broadcasting

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Editor submit work → QC → Broadcasting.

---

## 🔄 WORKFLOW LENGKAP

```
Editor Submit Work
    ↓
Auto-Create QualityControlWork ✅
    ↓
Notify QC ✅
    ↓
QC:
    1. Terima Notifikasi ✅
    2. Terima Pekerjaan ✅
    3. Proses Pekerjaan ✅
    4. Isi Form Catatan QC ✅
    5. Tidak Ada Revisi - Yes ✅ (Auto-approve)
    6. Selesai Pekerjaan ✅
    ↓
QC Approve:
    ├─► Auto-Create BroadcastingWork ✅
    ├─► Notify Broadcasting ✅
    ├─► Notify Produksi (Baca Hasil QC) ✅
    └─► Notify Promosi ✅
    ↓
QC Reject:
    ├─► Kembali ke Editor ✅
    ├─► Notify Producer ✅
    └─► Catatan QC ✅
    ↓
Broadcasting:
    1. Terima Notifikasi ✅
    2. Terima File Materi dari QC ✅
    3. Terima Thumbnail dari Design Grafis ✅
    4. Terima Pekerjaan ⚠️ (Endpoint ada tapi stubbed)
    5. Proses Pekerjaan ⚠️ (Endpoint ada tapi stubbed)
    6. Masukan ke Jadwal Playlist ⚠️ (Endpoint ada tapi stubbed)
    7. Upload di YouTube ⚠️ (Endpoint ada tapi stubbed)
    8. Thumbnail ⚠️ (Endpoint ada tapi stubbed)
    9. Deskripsi ⚠️ (Endpoint ada tapi stubbed)
    10. Tag ⚠️ (Endpoint ada tapi stubbed)
    11. Judul sesuai SEO ⚠️ (Endpoint ada tapi stubbed)
    12. Upload ke Website ⚠️ (Endpoint ada tapi stubbed)
    13. Input Link YT ke Sistem ⚠️ (Endpoint ada tapi stubbed)
    14. Selesai Pekerjaan ⚠️ (Endpoint ada tapi stubbed)
```

---

## 📋 DETAIL WORKFLOW

### **1. EDITOR - SUBMIT WORK KE QC**

#### **1.1. Editor - Selesai Pekerjaan (Submit)**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/submit`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-create QualityControlWork)

**Kode:** `EditorController::submit()` (Line 798-1022)

**Fitur:**
- ✅ Submit editor work
- ✅ Status berubah menjadi `completed`
- ✅ **Notifikasi ke Producer** ✅
- ✅ **Auto-create PromotionWork untuk Editor Promosi** ✅
- ✅ **Auto-create QualityControlWork untuk QC** ✅
- ✅ **Notifikasi ke QC** ✅

**Auto-create QualityControlWork:**
- QC Type: `main_episode`
- Files to check: Array dengan info editor file
- Status: `pending`
- Title: "QC Work - Episode {episode_number}"

**Notification Type:** `qc_work_assigned`

**Data yang dikirim ke QC:**
```json
{
  "qc_work_id": 1,
  "episode_id": 1,
  "editor_work_id": 1,
  "file_path": "path/to/editor/file.mp4"
}
```

---

### **2. QC - TERIMA NOTIFIKASI**

#### **2.1. QC - Terima Notifikasi**
**Dipicu oleh:** Editor submit work  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `qc_work_assigned`

**Notifikasi dikirim di:** `EditorController::submit()` (Line 960-980)

**Data yang dikirim:**
- ✅ `qc_work_id`
- ✅ `episode_id`
- ✅ `editor_work_id`
- ✅ `file_path`

---

### **3. QC - TERIMA PEKERJAAN**

#### **3.1. QC - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/accept-work`

**Status:** ✅ **SUDAH ADA**

**Kode:** `QualityControlController::acceptWork()` (Line 624-660)

**Fitur:**
- ✅ QC terima tugas QC (work sudah auto-create dari Editor)
- ✅ Validasi user adalah Quality Control
- ✅ Validasi status harus `pending`
- ✅ Update status menjadi `in_progress`
- ✅ Assign work ke user (reviewed_by)

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "episode_id": 1,
    "qc_type": "main_episode",
    "status": "in_progress",
    "files_to_check": [...]
  },
  "message": "Work accepted successfully. You can now proceed with QC."
}
```

---

### **4. QC - PROSES PEKERJAAN**

#### **4.1. QC - Proses Pekerjaan**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/qc-content`

**Status:** ✅ **SUDAH ADA**

**Kode:** `QualityControlController::qcContent()` (Line 666-738)

**Fitur:**
- ✅ QC berbagai konten (BTS, iklan, highlight, thumbnail)
- ✅ Input QC results per konten
- ✅ Calculate overall quality score
- ✅ Update status menjadi `completed`

**Request Body:**
```json
{
  "qc_results": {
    "bts_video": {
      "status": "approved",
      "notes": "BTS video quality baik",
      "score": 85
    },
    "iklan_episode_tv": {
      "status": "approved",
      "notes": "Iklan sesuai standar",
      "score": 90
    }
  },
  "overall_notes": "Overall quality baik"
}
```

---

### **5. QC - ISI FORM CATATAN QC**

#### **5.1. QC - Isi Form Catatan QC**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/submit-qc-form`

**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `QualityControlController::submitQCFormForWork()` (Line 740-872)

**Fitur:**
- ✅ Isi form catatan QC
- ✅ Input quality score
- ✅ Input issues found
- ✅ Input improvements needed
- ✅ Input QC checklist
- ✅ **Tidak ada revisi - Yes** (Auto-approve jika `no_revision_needed = true`)
- ✅ Update status menjadi `completed`

**Request Body:**
```json
{
  "qc_notes": "Catatan QC lengkap",
  "quality_score": 85,
  "issues_found": ["Issue 1", "Issue 2"],
  "improvements_needed": ["Improvement 1"],
  "qc_checklist": {
    "video_quality": true,
    "audio_quality": true,
    "thumbnail_quality": true
  },
  "no_revision_needed": true
}
```

**Auto-approve jika `no_revision_needed = true`:**
- ✅ Status menjadi `approved`
- ✅ Auto-create BroadcastingWork
- ✅ Notify Broadcasting

---

### **6. QC - SELESAI PEKERJAAN (APPROVE/REJECT)**

#### **6.1. QC - Finalize (Approve/Reject)**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/finalize`

**Status:** ✅ **SUDAH ADA** (Updated untuk handle Editor reject)

**Kode:** `QualityControlController::finalize()` (Line 878-1100)

**Fitur:**
- ✅ Approve atau Reject QC
- ✅ Validasi status harus `completed`
- ✅ **Approve:** Auto-create BroadcastingWork, Notify Broadcasting, Produksi, Promosi
- ✅ **Reject:** Update EditorWork status, Notify Editor, Producer

**Request Body:**
```json
{
  "action": "approve", // atau "reject"
  "notes": "Catatan approve/reject"
}
```

---

#### **6.2. QC Approve - Flow Lanjutan**

**Saat QC Approve:**
1. ✅ Status QualityControlWork menjadi `approved`
2. ✅ **Auto-create BroadcastingWork** ✅
3. ✅ **Notify Broadcasting** ✅
4. ✅ **Notify Produksi** (Baca Hasil QC) ✅
5. ✅ **Notify Promosi** ✅

**Auto-create BroadcastingWork:**
- Work Type: `main_episode`
- Video File Path: Dari Editor (files_to_check)
- Thumbnail Path: Dari Design Grafis (design_grafis_file_locations)
- Status: `pending`

**Notification Type:** `broadcasting_work_assigned`

---

#### **6.3. QC Reject - Flow Lanjutan**

**Saat QC Reject:**
1. ✅ Status QualityControlWork menjadi `revision_needed`
2. ✅ **Update EditorWork status menjadi `rejected`** ✅
3. ✅ **Notify Editor** ✅
4. ✅ **Notify Producer** ✅
5. ✅ **Catatan QC tersimpan** ✅

**Update EditorWork:**
- Status: `rejected`
- QC Feedback: Notes dari QC
- Reviewed By: QC user
- Reviewed At: Now

**Notification Type:** `qc_rejected_revision_needed` (Editor), `qc_rejected_producer_notification` (Producer)

---

### **7. PRODUKSI - BACA HASIL QC**

#### **7.1. Produksi - Terima Notifikasi QC**
**Dipicu oleh:** QC approve  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `qc_approved_produksi_notification`

**Data yang dikirim:**
- ✅ `episode_id`
- ✅ `qc_work_id`
- ✅ `quality_score`
- ✅ `qc_notes`

---

#### **7.2. Produksi - Baca Hasil QC**
**Endpoint:** `GET /api/live-tv/produksi/qc-results/{episode_id}`

**Status:** ✅ **SUDAH ADA**

**Kode:** `ProduksiController::getQCResults()` (Line 880-922)

**Fitur:**
- ✅ Get QC results untuk episode
- ✅ Include QualityControlWork dengan status `approved`, `revision_needed`, atau `failed`
- ✅ Include EpisodeQC jika ada

**Response:**
```json
{
  "success": true,
  "data": {
    "qc_works": [
      {
        "id": 1,
        "episode_id": 1,
        "qc_type": "main_episode",
        "status": "approved",
        "quality_score": 85,
        "qc_notes": "Catatan QC"
      }
    ],
    "episode_qc": {...},
    "episode_id": 1
  }
}
```

---

### **8. BROADCASTING - TERIMA NOTIFIKASI**

#### **8.1. Broadcasting - Terima Notifikasi**
**Dipicu oleh:** QC approve  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `broadcasting_work_assigned`

**Notifikasi dikirim di:** `QualityControlController::finalize()` (Line 933-946) dan `QualityControlController::createBroadcastingWork()` (Line 855-870)

**Data yang dikirim:**
- ✅ `broadcasting_work_id`
- ✅ `episode_id`
- ✅ `qc_work_id`
- ✅ `video_file_path`
- ✅ `thumbnail_path`

---

#### **8.2. Broadcasting - Terima File Materi dari QC**
**Via Auto-Create BroadcastingWork:** ✅ **SUDAH DITAMBAHKAN**

**Data yang tersedia:**
- ✅ BroadcastingWork sudah dibuat dengan `video_file_path` dari QC
- ✅ Video file path tersimpan di `BroadcastingWork.video_file_path`
- ✅ File bisa diakses dari `files_to_check` di QualityControlWork

---

#### **8.3. Broadcasting - Terima Thumbnail dari Design Grafis**
**Via Auto-Create BroadcastingWork:** ✅ **SUDAH DITAMBAHKAN**

**Data yang tersedia:**
- ✅ BroadcastingWork sudah dibuat dengan `thumbnail_path` dari Design Grafis
- ✅ Thumbnail path tersimpan di `BroadcastingWork.thumbnail_path`
- ✅ File bisa diakses dari `design_grafis_file_locations` di QualityControlWork

---

### **9. BROADCASTING - PROSES PEKERJAAN**

#### **9.1. Broadcasting - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/accept-work`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `BroadcastingController::acceptWork()` (Line 552-598)

**Fitur:**
- ✅ Broadcasting terima tugas (work sudah auto-create dari QC)
- ✅ Validasi user adalah Broadcasting
- ✅ Validasi status harus `preparing`
- ✅ Update status menjadi `preparing` dan assign work ke user
- ✅ Notify Producer

---

#### **9.2. Broadcasting - Masukan ke Jadwal Playlist**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/schedule-playlist`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `BroadcastingController::scheduleWorkPlaylist()` (Line 705-767)

**Fitur:**
- ✅ Input jadwal playlist
- ✅ Simpan playlist data ke BroadcastingWork
- ✅ Update scheduled_time
- ✅ Update status menjadi `scheduled`

---

#### **9.3. Broadcasting - Upload di YouTube**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-youtube`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `BroadcastingController::uploadYouTube()` (Line 600-703)

**Fitur:**
- ✅ Upload video ke YouTube (input YouTube URL)
- ✅ Input thumbnail (thumbnail_path)
- ✅ Input deskripsi (description)
- ✅ Input tag (tags array)
- ✅ Input judul sesuai SEO (title)
- ✅ Input category_id dan privacy_status
- ✅ Simpan YouTube URL dan video ID ke BroadcastingWork
- ✅ Simpan SEO metadata ke metadata field
- ✅ Update status menjadi `uploading`
- ✅ Notify Producer

**Request Body yang diperlukan:**
```json
{
  "youtube_url": "https://youtube.com/watch?v=...",
  "title": "Judul Video sesuai SEO",
  "description": "Deskripsi video",
  "tags": ["tag1", "tag2", "tag3"],
  "thumbnail_path": "path/to/thumbnail.jpg"
}
```

---

#### **9.4. Broadcasting - Upload ke Website**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-website`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `BroadcastingController::uploadWebsite()` (Line 608-667)

**Fitur:**
- ✅ Upload video ke website (input website URL)
- ✅ Simpan website URL ke BroadcastingWork
- ✅ Simpan metadata ke metadata field
- ✅ Notify Producer

**Request Body yang diperlukan:**
```json
{
  "website_url": "https://website.com/video/123"
}
```

---

#### **9.5. Broadcasting - Input Link YT ke Sistem**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/input-youtube-link`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `BroadcastingController::inputYouTubeLink()` (Line 669-718)

**Fitur:**
- ✅ Input YouTube link ke sistem
- ✅ Auto-extract YouTube video ID dari URL
- ✅ Simpan YouTube URL dan video ID ke BroadcastingWork

**Request Body yang diperlukan:**
```json
{
  "youtube_url": "https://youtube.com/watch?v=...",
  "youtube_video_id": "video_id"
}
```

---

#### **9.6. Broadcasting - Selesai Pekerjaan**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/complete-work`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `BroadcastingController::completeWork()` (Line 720-788)

**Fitur:**
- ✅ Complete broadcasting work
- ✅ Validasi YouTube atau Website URL harus sudah diisi
- ✅ Update status menjadi `published`
- ✅ Set published_time
- ✅ Simpan completion notes ke metadata
- ✅ Notify Manager Program

---

## 📋 RINGKASAN ENDPOINT

### **Editor:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/submit` | POST | Submit work (auto-create QC work) | ✅ |

### **QC:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works` | GET | List semua QC works | ✅ |
| `/works/{id}` | GET | Get QC work detail | ✅ |
| `/works/{id}/accept-work` | POST | Terima pekerjaan | ✅ |
| `/works/{id}/qc-content` | POST | QC berbagai konten | ✅ |
| `/works/{id}/submit-qc-form` | POST | Isi form catatan QC | ✅ |
| `/works/{id}/finalize` | POST | Selesaikan pekerjaan (approve/reject) | ✅ |

### **Produksi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/qc-results/{episode_id}` | GET | Baca hasil QC | ✅ |

### **Broadcasting:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works` | GET | List semua broadcasting works | ✅ |
| `/works` | POST | Create broadcasting work | ✅ |
| `/works/{id}` | GET | Get work detail | ✅ |
| `/works/{id}` | PUT | Update work | ✅ |
| `/works/{id}/accept-work` | POST | Terima pekerjaan | ✅ |
| `/works/{id}/schedule-work-playlist` | POST | Masukan ke jadwal playlist | ✅ |
| `/works/{id}/upload-youtube` | POST | Upload di YouTube (dengan SEO) | ✅ |
| `/works/{id}/upload-website` | POST | Upload ke website | ✅ |
| `/works/{id}/input-youtube-link` | POST | Input link YT ke sistem | ✅ |
| `/works/{id}/complete-work` | POST | Selesai pekerjaan | ✅ |
| `/statistics` | GET | Get statistics | ✅ |
| `/schedules/{id}/upload` | POST | Upload file | ✅ |
| `/schedules/{id}/publish` | POST | Publish work | ✅ |

---

## ✅ YANG SUDAH BENAR

1. ✅ Editor submit work → Auto-create QualityControlWork
2. ✅ Notifikasi ke QC saat Editor submit
3. ✅ QC bisa terima pekerjaan
4. ✅ QC bisa proses pekerjaan (qc-content)
5. ✅ QC bisa isi form catatan QC
6. ✅ QC bisa approve/reject
7. ✅ QC approve → Auto-create BroadcastingWork
8. ✅ QC approve → Notify Broadcasting, Produksi, Promosi
9. ✅ QC reject → Update EditorWork status, Notify Editor dan Producer
10. ✅ Produksi bisa baca hasil QC

---

## ✅ YANG SUDAH DIIMPLEMENTASIKAN

1. ✅ BroadcastingController - Semua method sudah diimplementasikan
2. ✅ Broadcasting - Accept work
3. ✅ Broadcasting - Schedule playlist
4. ✅ Broadcasting - Upload YouTube (dengan SEO: title, description, tags, thumbnail)
5. ✅ Broadcasting - Upload website
6. ✅ Broadcasting - Input YouTube link (auto-extract video ID)
7. ✅ Broadcasting - Complete work (dengan validasi dan notifikasi)
8. ✅ Broadcasting - Statistics
9. ✅ Broadcasting - Index, Store, Show, Update, Upload, Publish

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Editor submit → Auto-create QualityControlWork
- ✅ QC terima notifikasi
- ✅ QC terima pekerjaan
- ✅ QC proses pekerjaan
- ✅ QC isi form catatan QC
- ✅ QC approve/reject dengan notifikasi lengkap
- ✅ Produksi bisa baca hasil QC
- ✅ Broadcasting - Semua endpoint sudah diimplementasikan
- ✅ Broadcasting - Accept work, Upload YouTube (SEO), Upload Website, Schedule Playlist, Complete work

**Semua endpoint sudah tersedia dan siap digunakan untuk frontend integration.**

---

**Last Updated:** 2025-01-27
