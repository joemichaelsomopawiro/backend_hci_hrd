# Flow Sound Engineer Editing → Producer QC → Editor

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Sound Engineer Editing → Producer QC → Editor.

---

## 🔄 WORKFLOW LENGKAP

```
Sound Engineer Recording Complete
    ↓
Auto-Create: SoundEngineerEditing
    ↓
Sound Engineer Editing:
    1. Terima Notifikasi ✅
    2. Terima Pekerjaan ✅
    3. Lanjut Edit Vocal ✅
    4. Selesai Pekerjaan (Submit) ✅
    ↓
Ajukan Ke Producer untuk QC
    ↓
Producer:
    1. Terima Notifikasi ✅
    2. QC (Approve/Reject) ✅
    ↓
    ├─► Jika REJECT:
    │   └─► Kembali ke Sound Engineer Editing (revision_needed)
    │       └─► Sound Engineer Editing:
    │           1. Terima Notifikasi ✅
    │           2. Terima Pekerjaan ✅
    │           3. Lanjut Edit Vocal ✅
    │           4. Selesai Pekerjaan (Resubmit) ✅
    │       └─► Kembali ke Producer untuk QC
    │
    └─► Jika APPROVE:
        └─► Lanjut ke Editor
            Editor:
            1. Terima Notifikasi ✅
            2. Terima Pekerjaan ✅
            3. Cek Kelengkapan File ✅
            4. Buat Catatan file kurang (jika perlu) ✅
            5. Proses Pekerjaan ✅
            6. Lihat Run Sheet ✅
            7. Upload File Hasil Edit ✅
            8. Input Link File ✅
            9. Selesai Pekerjaan ✅
```

---

## 📋 DETAIL WORKFLOW

### **1. SOUND ENGINEER EDITING → PRODUCER (QC)**

#### **1.1. Sound Engineer Editing - Submit untuk QC**
**Endpoint:** `POST /api/live-tv/sound-engineer-editing/works/{id}/submit`

**Status:** ✅ **SUDAH ADA**

**Kode:** `SoundEngineerEditingController::submit()` (Line 286-358)

**Fitur:**
- ✅ Submit editing work ke Producer untuk QC
- ✅ Validasi status harus `in_progress` atau `revision_needed` (untuk resubmit)
- ✅ Status berubah menjadi `submitted`
- ✅ **Notifikasi ke Producer** ✅
- ✅ Reset rejection fields jika resubmit dari `revision_needed`

**Notification Type:** `sound_engineer_editing_submitted`

**Request Body:**
```json
{
  "final_file_path": "path/to/final/audio/file.mp3",
  "submission_notes": "Audio editing selesai, siap untuk QC"
}
```

---

### **2. PRODUCER - QC SOUND ENGINEER EDITING**

#### **2.1. Producer - Terima Notifikasi**
**Dipicu oleh:** Sound Engineer Editing submit untuk QC  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `sound_engineer_editing_submitted`

**Notifikasi dikirim di:** `SoundEngineerEditingController::submit()` → `notifyProducerForQC()` (Line 421-438)

**Data yang dikirim:**
- ✅ `work_id` (editing_id)
- ✅ `episode_id`
- ✅ `episode_title`

**Cara akses:**
- Producer bisa lihat di `GET /api/live-tv/producer/pending-approvals` (field: `sound_engineer_editing`)

---

#### **2.2. Producer - QC (Approve/Reject)**

**Endpoint Approve:** `POST /api/live-tv/producer/approve/{id}`  
**Endpoint Reject:** `POST /api/live-tv/producer/reject/{id}`

**Request Body:**
```json
{
  "type": "sound_engineer_editing",
  "notes": "Audio quality bagus, approved" // untuk approve
  // atau
  "reason": "Audio masih ada noise, perlu perbaikan" // untuk reject
}
```

**Status:** ✅ **SUDAH ADA**

**Kode Approve:** `ProducerController::approveItem()` (Line 902-1006)  
**Kode Reject:** `ProducerController::rejectItem()` (Line 1407-1463)

---

#### **2.2.1. Producer - APPROVE Sound Engineer Editing**

**Fitur:**
- ✅ Status berubah menjadi `approved`
- ✅ **Notifikasi ke Sound Engineer** ✅
- ✅ **Notifikasi ke Editor** ✅ (audio ready for editing)
- ✅ Update workflow state ke `editing`

**Notification Types:**
- `sound_engineer_editing_approved` → Sound Engineer
- `audio_ready_for_editing` → Editor

**Data yang dikirim ke Editor:**
```json
{
  "editing_id": 1,
  "episode_id": 1,
  "audio_file_path": "path/to/approved/audio.mp3"
}
```

**Kode:** `ProducerController::approveItem()` (Line 958-1005)

---

#### **2.2.2. Producer - REJECT Sound Engineer Editing**

**Fitur:**
- ✅ Status berubah menjadi `revision_needed`
- ✅ **Notifikasi ke Sound Engineer** ✅
- ✅ Sound Engineer bisa accept work lagi dan resubmit

**Notification Type:** `sound_engineer_editing_rejected`

**Data yang dikirim:**
```json
{
  "editing_id": 1,
  "episode_id": 1,
  "rejection_reason": "Audio masih ada noise, perlu perbaikan"
}
```

**Kode:** `ProducerController::rejectItem()` (Line 1443-1462)

---

### **3. SOUND ENGINEER EDITING - RESUBMIT SETELAH REJECT**

#### **3.1. Sound Engineer Editing - Terima Notifikasi (Setelah Reject)**
**Notification Type:** `sound_engineer_editing_rejected`

**Status:** ✅ **SUDAH ADA**

---

#### **3.2. Sound Engineer Editing - Terima Pekerjaan (Untuk Revisi)**
**Endpoint:** `POST /api/live-tv/sound-engineer-editing/works/{id}/accept-work`

**Status:** ✅ **SUDAH DIPERBAIKI**

**Perubahan:**
- ✅ Sekarang bisa accept work dari status `revision_needed`
- ✅ Auto-reset rejection fields saat accept work

**Kode:** `SoundEngineerEditingController::acceptWork()` (Line 188-233)

**Fitur:**
- ✅ Reset `rejected_by`, `rejected_at`, `rejection_reason`
- ✅ Reset `submitted_at` untuk memungkinkan resubmission
- ✅ Status berubah menjadi `in_progress`
- ✅ **Notifikasi ke Producer** ✅

---

#### **3.3. Sound Engineer Editing - Lanjut Edit Vocal (Revisi)**
**Endpoint:** `PUT /api/live-tv/sound-engineer-editing/works/{id}`

**Status:** ✅ **SUDAH ADA**

---

#### **3.4. Sound Engineer Editing - Resubmit**
**Endpoint:** `POST /api/live-tv/sound-engineer-editing/works/{id}/submit`

**Status:** ✅ **SUDAH DIPERBAIKI**

**Perubahan:**
- ✅ Sekarang bisa submit dari status `revision_needed`
- ✅ Auto-reset rejection fields saat resubmit

**Kode:** `SoundEngineerEditingController::submit()` (Line 315-335)

**Fitur:**
- ✅ Validasi status `in_progress` atau `revision_needed`
- ✅ Reset rejection fields
- ✅ Status berubah menjadi `submitted`
- ✅ **Notifikasi ke Producer** ✅

---

### **4. EDITOR - SETELAH PRODUCER APPROVE**

#### **4.1. Editor - Terima Notifikasi**
**Dipicu oleh:** Producer approve Sound Engineer Editing  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `audio_ready_for_editing`

**Notifikasi dikirim di:** `ProducerController::approveItem()` (Line 981-992)

**Data yang dikirim:**
- ✅ `editing_id` (SoundEngineerEditing ID)
- ✅ `episode_id`
- ✅ `audio_file_path` (final approved audio)

---

#### **4.2. Editor - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/accept-work`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::acceptWork()` (Line 200-263)

---

#### **4.3. Editor - Cek Kelengkapan File**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/check-file-completeness`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::checkFileCompleteness()` (Line 269-349)

**Fitur:**
- ✅ Cek file dari Produksi (shooting files)
- ✅ Cek audio dari Sound Engineer Editing (approved)
- ✅ Auto-update source_files dengan info audio dan produksi
- ✅ Jika lengkap → Auto-proceed to editing
- ✅ Jika tidak lengkap → Return missing files info

**File yang dicek:**
1. **Produksi Files:** Dari `ProduksiWork.shooting_files`
2. **Approved Audio:** Dari `SoundEngineerEditing` dengan status `approved`

---

#### **4.4. Editor - Buat Catatan File Kurang (Jika Perlu)**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/report-missing-files`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::reportMissingFiles()` (Line 351-434)

---

#### **4.5. Editor - Proses Pekerjaan**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/process-work`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::processWork()` (Line 436-494)

---

#### **4.6. Editor - Lihat Run Sheet**
**Endpoint:** `GET /api/live-tv/editor/episodes/{id}/run-sheet`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::getRunSheet()` (Line 496-550)

---

#### **4.7. Editor - Upload File Hasil Edit**
**Endpoint:** `PUT /api/live-tv/editor/works/{id}` (dengan file upload)

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::update()` (Line 577-675)

---

#### **4.8. Editor - Input Link File**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/input-file-links`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::inputFileLinks()` (Line 677-751)

---

#### **4.9. Editor - Selesai Pekerjaan**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/submit`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::submit()` (Line 753-837)

**Fitur:**
- ✅ Submit editor work ke Producer
- ✅ Status berubah menjadi `completed`
- ✅ **Notifikasi ke Producer** ✅

---

## 📋 RINGKASAN ENDPOINT

### **Sound Engineer Editing:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/accept-work` | POST | Terima pekerjaan (termasuk setelah reject) | ✅ |
| `/works/{id}` | PUT | Update editing (edit vocal) | ✅ |
| `/works/{id}/submit` | POST | Submit untuk QC Producer (termasuk resubmit) | ✅ |

### **Producer:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/pending-approvals` | GET | Lihat pending approvals (termasuk Sound Engineer Editing) | ✅ |
| `/approve/{id}` | POST | Approve Sound Engineer Editing | ✅ |
| `/reject/{id}` | POST | Reject Sound Engineer Editing | ✅ |

### **Editor:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/accept-work` | POST | Terima pekerjaan | ✅ |
| `/works/{id}/check-file-completeness` | POST | Cek kelengkapan file (termasuk approved audio) | ✅ |
| `/works/{id}/report-missing-files` | POST | Lapor file kurang | ✅ |
| `/works/{id}/process-work` | POST | Proses pekerjaan | ✅ |
| `/episodes/{id}/run-sheet` | GET | Lihat run sheet | ✅ |
| `/works/{id}` | PUT | Upload file hasil edit | ✅ |
| `/works/{id}/input-file-links` | POST | Input link file | ✅ |
| `/works/{id}/submit` | POST | Submit work ke Producer | ✅ |
| `/episodes/{episodeId}/approved-audio` | GET | Get approved audio files | ✅ |

---

## ✅ YANG SUDAH BENAR

1. ✅ Sound Engineer Editing submit → Notify Producer untuk QC
2. ✅ Producer approve → Notify Sound Engineer dan Editor
3. ✅ Producer reject → Notify Sound Engineer, status jadi `revision_needed`
4. ✅ Sound Engineer bisa accept work dari status `revision_needed`
5. ✅ Sound Engineer bisa resubmit dari status `revision_needed`
6. ✅ Editor bisa akses approved audio via `checkFileCompleteness`
7. ✅ Editor bisa akses approved audio via `getApprovedAudioFiles`
8. ✅ Editor bisa akses approved audio via `show` (EditorWork detail)

---

## 🔄 FLOW REJECT & RESUBMIT

```
Producer Reject
    ↓
Status: revision_needed
Notifikasi ke Sound Engineer
    ↓
Sound Engineer Accept Work
    ↓
Status: in_progress
Rejection fields di-reset
    ↓
Sound Engineer Edit Vocal
    ↓
Sound Engineer Resubmit
    ↓
Status: submitted
Rejection fields di-reset
    ↓
Kembali ke Producer untuk QC
```

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Sound Engineer Editing → Producer QC → Editor
- ✅ Producer Reject → Sound Engineer Editing (revisi) → Resubmit
- ✅ Producer Approve → Editor (dengan approved audio)
- ✅ Editor workflow lengkap sudah ada

---

**Last Updated:** 2026-01-27
