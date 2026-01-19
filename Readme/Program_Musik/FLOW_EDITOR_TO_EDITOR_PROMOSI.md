# Flow Editor → Editor Promosi

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Editor submit work → Editor Promosi edit BTS, Highlight, dan Iklan TV.

---

## 🔄 WORKFLOW LENGKAP

```
Editor Submit Work
    ↓
Auto-Create PromotionWork (5 work types):
    ├─► bts_video - Edit Video BTS
    ├─► highlight_ig - Buat Highlight Episode IG
    ├─► highlight_tv - Buat Highlight Episode TV
    ├─► highlight_facebook - Buat Highlight Episode Facebook
    └─► iklan_episode_tv - Edit Iklan Episode TV
    ↓
Notify Editor Promosi
    ↓
Editor Promosi:
    1. Terima Notifikasi ✅
    2. Terima Lokasi File dari Editor ✅
    3. Terima Lokasi File dari BTS ✅
    4. Terima Pekerjaan ✅
    5. Edit Video BTS ✅
    6. Edit Iklan Episode TV ✅
    7. Buat Highlight Episode IG ✅
    8. Buat Highlight Episode TV ✅
    9. Buat Highlight Episode Facebook ✅
    10. Selesai Pekerjaan ✅
    ↓
Submit ke QC
```

---

## 📋 DETAIL WORKFLOW

### **1. EDITOR - SUBMIT WORK**

#### **1.1. Editor - Selesai Pekerjaan (Submit)**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/submit`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-create PromotionWork)

**Kode:** `EditorController::submit()` (Line 798-950)

**Fitur:**
- ✅ Submit editor work ke Producer
- ✅ Status berubah menjadi `completed`
- ✅ **Notifikasi ke Producer** ✅
- ✅ **Auto-create 5 PromotionWork** ✅:
  - `bts_video` - Edit Video BTS
  - `highlight_ig` - Buat Highlight Episode IG
  - `highlight_tv` - Buat Highlight Episode TV
  - `highlight_facebook` - Buat Highlight Episode Facebook
  - `iklan_episode_tv` - Edit Iklan Episode TV
- ✅ **Notifikasi ke Editor Promosi** ✅

**Auto-create PromotionWork:**
- Status: `editing` (siap untuk diterima Editor Promosi)
- File paths: Menyimpan info editor_file_path dari EditorWork
- Title: Auto-generate berdasarkan work_type

**Notification Type:** `editor_files_available`

**Data yang dikirim ke Editor Promosi:**
```json
{
  "editor_work_id": 1,
  "episode_id": 1,
  "editor_file_path": "path/to/edited/file.mp4",
  "promotion_works": [
    {
      "id": 10,
      "work_type": "bts_video",
      "title": "Edit Video BTS - Episode 1"
    },
    {
      "id": 11,
      "work_type": "highlight_ig",
      "title": "Buat Highlight Episode IG - Episode 1"
    },
    // ... dst
  ]
}
```

---

### **2. EDITOR PROMOSI - TERIMA NOTIFIKASI**

#### **2.1. Editor Promosi - Terima Notifikasi**
**Dipicu oleh:** Editor submit work  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `editor_files_available`

**Notifikasi dikirim di:** `EditorController::submit()` (Line 917-938)

**Data yang dikirim:**
- ✅ `editor_work_id`
- ✅ `episode_id`
- ✅ `editor_file_path`
- ✅ `promotion_works` (array dengan 5 work yang dibuat)

---

#### **2.2. Editor Promosi - Terima Lokasi File dari Editor**
**Via Auto-Create PromotionWork:** ✅ **SUDAH DITAMBAHKAN**

**Data yang tersedia:**
- ✅ PromotionWork sudah dibuat dengan `file_paths` berisi info editor file
- ✅ Editor file path tersimpan di `PromotionWork.file_paths.editor_file_path`
- ✅ Editor file bisa diakses via `getSourceFiles` dengan `source_role=editor`

---

#### **2.3. Editor Promosi - Terima Lokasi File dari BTS**
**Via Auto-Fetch Source Files:** ✅ **SUDAH DITAMBAHKAN**

**Saat accept work:**
- ✅ Auto-fetch BTS files dari PromotionWork dengan `work_type=bts_video` atau `bts_photo`
- ✅ BTS files tersimpan di `PromotionWork.file_paths.source_files.bts_files`

**Via getSourceFiles endpoint:**
- ✅ Endpoint: `GET /api/live-tv/editor-promosi/source-files?episode_id=X&source_role=promotion`
- ✅ Return BTS files dari PromotionWork (Promosi team)

---

### **3. EDITOR PROMOSI - TERIMA PEKERJAAN**

#### **3.1. Editor Promosi - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/accept-work`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-fetch source files)

**Kode:** `EditorPromosiController::acceptWork()` (Line 514-589)

**Fitur:**
- ✅ Editor Promosi terima tugas editing (work sudah auto-create dari Editor)
- ✅ Validasi user adalah Editor Promotion
- ✅ Validasi status bisa `draft`, `planning`, atau `editing`
- ✅ **Auto-fetch source files** dari Editor dan BTS ✅
- ✅ Update status menjadi `editing`
- ✅ Assign work ke user
- ✅ **Notifikasi ke Producer** ✅

**Auto-fetch Source Files:**
- ✅ Files dari Editor (EditorWork dengan status `completed` atau `approved`)
- ✅ Files dari BTS (PromotionWork dengan `work_type=bts_video` atau `bts_photo`)

**Notification Type:** `editor_promosi_work_accepted`

---

### **4. EDITOR PROMOSI - PROSES PEKERJAAN**

#### **4.1. Editor Promosi - Edit Video BTS**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::uploadFiles()` (Line 240-315)

**Fitur:**
- ✅ Upload file hasil editing BTS video
- ✅ Simpan ke `file_paths` array
- ✅ Create MediaFile record
- ✅ Validasi work_type harus `bts_video`

**Request Body:**
```json
{
  "files": [file1, file2, ...] // Video files (mp4, avi, mov)
}
```

---

#### **4.2. Editor Promosi - Edit Iklan Episode TV**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::uploadFiles()` (Line 240-315)

**Fitur:**
- ✅ Upload file hasil editing iklan TV
- ✅ Simpan ke `file_paths` array
- ✅ Create MediaFile record
- ✅ Validasi work_type harus `iklan_episode_tv`

---

#### **4.3. Editor Promosi - Buat Highlight Episode IG**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::uploadFiles()` (Line 240-315)

**Fitur:**
- ✅ Upload file highlight IG
- ✅ Simpan ke `file_paths` array
- ✅ Create MediaFile record
- ✅ Validasi work_type harus `highlight_ig`

---

#### **4.4. Editor Promosi - Buat Highlight Episode TV**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::uploadFiles()` (Line 240-315)

**Fitur:**
- ✅ Upload file highlight TV
- ✅ Simpan ke `file_paths` array
- ✅ Create MediaFile record
- ✅ Validasi work_type harus `highlight_tv`

---

#### **4.5. Editor Promosi - Buat Highlight Episode Facebook**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::uploadFiles()` (Line 240-315)

**Fitur:**
- ✅ Upload file highlight Facebook
- ✅ Simpan ke `file_paths` array
- ✅ Create MediaFile record
- ✅ Validasi work_type harus `highlight_facebook`

---

### **5. EDITOR PROMOSI - SELESAI PEKERJAAN**

#### **5.1. Editor Promosi - Submit ke QC**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/submit-to-qc`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::submitToQC()` (Line 560-642)

**Fitur:**
- ✅ Submit hasil editing ke Quality Control
- ✅ Auto-create QualityControlWork
- ✅ **Notifikasi ke QC** ✅
- ✅ Update status menjadi `review`

**Auto-create QualityControlWork:**
- ✅ QC Type: `main_episode`
- ✅ Editor Promosi file locations: Array file paths dari PromotionWork
- ✅ Status: `pending`

**Notification Type:** `editor_promosi_submitted_to_qc`

---

## 📋 RINGKASAN ENDPOINT

### **Editor:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/submit` | POST | Submit work (auto-create PromotionWork) | ✅ |

### **Editor Promosi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works` | GET | List semua works | ✅ |
| `/works/{id}` | GET | Get work detail | ✅ |
| `/works/{id}/accept-work` | POST | Terima pekerjaan (auto-fetch source files) | ✅ |
| `/works/{id}/upload` | POST | Upload file hasil editing | ✅ |
| `/source-files` | GET | Get source files (Editor atau BTS) | ✅ |
| `/works/{id}/submit-to-qc` | POST | Submit ke QC | ✅ |

---

## 🔄 DETAIL AUTO-CREATE PROMOTIONWORK

Saat Editor submit work, sistem akan auto-create 5 PromotionWork dengan detail:

### **1. BTS Video:**
```json
{
  "work_type": "bts_video",
  "title": "Edit Video BTS - Episode 1",
  "description": "Editing task untuk Edit Video BTS. File referensi dari Editor sudah tersedia.",
  "status": "editing",
  "file_paths": {
    "editor_work_id": 1,
    "editor_file_path": "path/to/editor/file.mp4",
    "available": true
  }
}
```

### **2. Highlight IG:**
```json
{
  "work_type": "highlight_ig",
  "title": "Buat Highlight Episode IG - Episode 1",
  "status": "editing"
}
```

### **3. Highlight TV:**
```json
{
  "work_type": "highlight_tv",
  "title": "Buat Highlight Episode TV - Episode 1",
  "status": "editing"
}
```

### **4. Highlight Facebook:**
```json
{
  "work_type": "highlight_facebook",
  "title": "Buat Highlight Episode Facebook - Episode 1",
  "status": "editing"
}
```

### **5. Iklan Episode TV:**
```json
{
  "work_type": "iklan_episode_tv",
  "title": "Edit Iklan Episode TV - Episode 1",
  "status": "editing"
}
```

---

## 📊 SOURCE FILES STRUCTURE

Setelah Editor Promosi accept work, `file_paths` akan berisi:

```json
{
  "editor_work_id": 1,
  "editor_file_path": "path/to/editor/file.mp4",
  "editor_file_name": "episode_edited.mp4",
  "available": true,
  "fetched_at": "2025-01-27 10:00:00",
  "accepted_at": "2025-01-27 10:05:00",
  "accepted_by": 5,
  "source_files": {
    "editor_files": [
      {
        "editor_work_id": 1,
        "file_path": "path/to/editor/file.mp4",
        "file_name": "episode_edited.mp4",
        "work_type": "main_episode"
      }
    ],
    "bts_files": [
      {
        "promotion_work_id": 3,
        "work_type": "bts_video",
        "file_path": "path/to/bts/video.mp4",
        "file_name": "bts_video.mp4"
      }
    ],
    "fetched_at": "2025-01-27 10:05:00"
  }
}
```

---

## ✅ YANG SUDAH BENAR

1. ✅ Editor submit work → Auto-create 5 PromotionWork
2. ✅ Notifikasi ke Editor Promosi saat Editor submit
3. ✅ Editor Promosi bisa lihat semua works yang sudah dibuat
4. ✅ Editor Promosi accept work → Auto-fetch source files dari Editor dan BTS
5. ✅ Editor Promosi bisa upload file untuk setiap work type
6. ✅ Editor Promosi submit ke QC setelah selesai
7. ✅ Auto-create QualityControlWork saat submit ke QC

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Editor submit → Auto-create PromotionWork (5 types)
- ✅ Editor Promosi terima notifikasi
- ✅ Editor Promosi accept work → Auto-fetch source files
- ✅ Editor Promosi bisa edit semua work types
- ✅ Editor Promosi submit ke QC

Semua endpoint sudah tersedia dan siap digunakan untuk frontend integration.

---

**Last Updated:** 2025-01-27
