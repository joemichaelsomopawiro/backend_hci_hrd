# Flow Promosi → Design Grafis & Editor Promosi

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Promosi complete work → Design Grafis (thumbnail BTS) & Editor Promosi (edit BTS, highlight, iklan).

---

## 🔄 WORKFLOW LENGKAP

```
Promosi Complete Work
    ↓
Auto-Create:
    ├─► DesignGrafisWork (thumbnail_bts) ✅
    └─► PromotionWork untuk Editor Promosi (5 work types) ✅
    ↓
Notify Design Grafis ✅
Notify Editor Promosi ✅
    ↓
Design Grafis:
    1. Terima Notifikasi ✅
    2. Terima Lokasi File dari Produksi ✅
    3. Terima Lokasi Foto Talent dari Promosi ✅
    4. Terima Pekerjaan ✅
    5. Buat Thumbnail YouTube ✅
    6. Buat Thumbnail BTS ✅
    7. Selesai Pekerjaan ✅
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
```

---

## 📋 DETAIL WORKFLOW

### **1. PROMOSI - SELESAI PEKERJAAN**

#### **1.1. Promosi - Selesai Pekerjaan (Complete Work)**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/complete-work`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-create DesignGrafisWork & PromotionWork)

**Kode:** `PromosiController::completeWork()` (Line 517-750+)

**Fitur:**
- ✅ Validasi BTS video dan talent photos harus sudah di-upload
- ✅ Status berubah menjadi `editing`
- ✅ **Notifikasi ke Producer** ✅
- ✅ **Auto-create DesignGrafisWork untuk Thumbnail BTS** ✅
- ✅ **Auto-create 5 PromotionWork untuk Editor Promosi** ✅:
  - `bts_video` - Edit Video BTS
  - `highlight_ig` - Buat Highlight Episode IG
  - `highlight_tv` - Buat Highlight Episode TV
  - `highlight_facebook` - Buat Highlight Episode Facebook
  - `iklan_episode_tv` - Edit Iklan Episode TV
- ✅ **Notifikasi ke Design Grafis** ✅
- ✅ **Notifikasi ke Editor Promosi** ✅

---

### **2. AUTO-CREATE DESIGNGRAFISWORK (THUMBNAIL BTS)**

#### **2.1. Auto-Create DesignGrafisWork untuk Thumbnail BTS**
**Dipicu oleh:** Promosi complete work  
**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `PromosiController::completeWork()` (Line 614-699)

**Kondisi:**
- ✅ Hanya create jika ada talent photos
- ✅ Jika sudah ada, update dengan file terbaru

**Data yang disimpan:**
- ✅ `work_type`: `thumbnail_bts`
- ✅ `source_files`: 
  - `promotion_work_id`
  - `talent_photos` (array)
  - `bts_video` (optional)
- ✅ `status`: `draft`

**Notification Type:** `promosi_files_available_for_design`

**Data yang dikirim ke Design Grafis:**
```json
{
  "promotion_work_id": 1,
  "design_grafis_work_id": 10,
  "episode_id": 1,
  "talent_photos_count": 5,
  "bts_video_available": true
}
```

---

### **3. AUTO-CREATE PROMOTIONWORK (EDITOR PROMOSI)**

#### **3.1. Auto-Create PromotionWork untuk Editor Promosi**
**Dipicu oleh:** Promosi complete work  
**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `PromosiController::completeWork()` (Line 701-758)

**5 Work Types yang dibuat:**
1. `bts_video` - Edit Video BTS
2. `highlight_ig` - Buat Highlight Episode IG
3. `highlight_tv` - Buat Highlight Episode TV
4. `highlight_facebook` - Buat Highlight Episode Facebook
5. `iklan_episode_tv` - Edit Iklan Episode TV

**Data yang disimpan:**
- ✅ `file_paths`: 
  - `promotion_work_id` (source dari Promosi)
  - `bts_files` (array BTS video files)
  - `talent_photos` (array talent photo files)
- ✅ `status`: `editing`

**Notification Type:** `promosi_bts_files_available`

---

### **4. DESIGN GRAFIS - TERIMA NOTIFIKASI**

#### **4.1. Design Grafis - Terima Notifikasi**
**Dipicu oleh:** Promosi complete work  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `promosi_files_available_for_design`

**Notifikasi dikirim di:** `PromosiController::completeWork()` (Line 653-669)

**Data yang dikirim:**
- ✅ `promotion_work_id`
- ✅ `design_grafis_work_id`
- ✅ `episode_id`
- ✅ `talent_photos_count`
- ✅ `bts_video_available`

---

#### **4.2. Design Grafis - Terima Lokasi File dari Produksi**
**Via Auto-Create dari Produksi:** ✅ **SUDAH ADA**

**Catatan:** Produksi saat upload shooting results sudah auto-create DesignGrafisWork untuk `thumbnail_youtube` dan `thumbnail_bts`.  
**File dari Produksi tersedia di:** `DesignGrafisWork.source_files.produksi_files`

---

#### **4.3. Design Grafis - Terima Lokasi Foto Talent dari Promosi**
**Via Auto-Create dari Promosi:** ✅ **SUDAH DITAMBAHKAN**

**Data yang tersedia:**
- ✅ DesignGrafisWork untuk `thumbnail_bts` sudah dibuat dengan `source_files.promosi_files.talent_photos`
- ✅ Foto talent tersimpan di `DesignGrafisWork.source_files.talent_photos`
- ✅ File juga tersimpan di `PromotionWork.file_paths` (type: `talent_photo`)

---

### **5. DESIGN GRAFIS - TERIMA PEKERJAAN**

#### **5.1. Design Grafis - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/accept-work`

**Status:** ✅ **SUDAH ADA**

**Kode:** `DesignGrafisController::acceptWork()` (Line 195-262)

**Fitur:**
- ✅ Auto-fetch source files dari Produksi dan Promosi
- ✅ Update status menjadi `in_progress`
- ✅ Assign work ke user
- ✅ Notify Producer

**Auto-fetch Source Files:**
- ✅ Files dari Produksi (shooting results)
- ✅ Files dari Promosi (talent photos)

---

### **6. DESIGN GRAFIS - BUAT THUMBNAIL**

#### **6.1. Design Grafis - Buat Thumbnail YouTube**
**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/upload-thumbnail-youtube`

**Status:** ✅ **SUDAH ADA**

**Kode:** `DesignGrafisController::uploadThumbnailYouTube()` (Line 264-345)

**Fitur:**
- ✅ Upload thumbnail YouTube (jpg, jpeg, png, webp - max 10MB)
- ✅ Validasi work_type harus `thumbnail_youtube`
- ✅ Simpan ke `file_path` dan `file_paths`

---

#### **6.2. Design Grafis - Buat Thumbnail BTS**
**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/upload-thumbnail-bts`

**Status:** ✅ **SUDAH ADA**

**Kode:** `DesignGrafisController::uploadThumbnailBTS()` (Line 347-428)

**Fitur:**
- ✅ Upload thumbnail BTS (jpg, jpeg, png, webp - max 10MB)
- ✅ Validasi work_type harus `thumbnail_bts`
- ✅ Simpan ke `file_path` dan `file_paths`

---

#### **6.3. Design Grafis - Selesai Pekerjaan**
**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/complete-work`

**Status:** ✅ **SUDAH ADA**

**Kode:** `DesignGrafisController::completeWork()` (Line 465-545)

**Fitur:**
- ✅ Validasi file_path atau file_paths harus ada
- ✅ Status berubah menjadi `completed`
- ✅ Notify Producer

---

### **7. EDITOR PROMOSI - TERIMA NOTIFIKASI**

#### **7.1. Editor Promosi - Terima Notifikasi**
**Dipicu oleh:** Promosi complete work  
**Status:** ✅ **SUDAH DITAMBAHKAN**

**Notification Type:** `promosi_bts_files_available`

**Notifikasi dikirim di:** `PromosiController::completeWork()` (Line 758-783)

**Data yang dikirim:**
- ✅ `promotion_work_id`
- ✅ `episode_id`
- ✅ `bts_files_available`
- ✅ `talent_photos_count`
- ✅ `promotion_works` (array dengan 5 work yang dibuat)

---

#### **7.2. Editor Promosi - Terima Lokasi File dari Editor**
**Via Auto-Create dari Editor:** ✅ **SUDAH ADA**

**Catatan:** Editor saat submit work sudah auto-create PromotionWork untuk Editor Promosi.  
**File dari Editor tersedia di:** `PromotionWork.file_paths.editor_file_path` atau via `fetchSourceFilesForWork()`

---

#### **7.3. Editor Promosi - Terima Lokasi File dari BTS**
**Via Auto-Create dari Promosi:** ✅ **SUDAH DITAMBAHKAN**

**Data yang tersedia:**
- ✅ PromotionWork untuk Editor Promosi sudah dibuat dengan `file_paths.bts_files`
- ✅ BTS files tersimpan di `PromotionWork.file_paths.bts_files`
- ✅ File juga bisa di-fetch via `fetchSourceFilesForWork()` yang auto-filter hanya BTS video

---

### **8. EDITOR PROMOSI - TERIMA PEKERJAAN**

#### **8.1. Editor Promosi - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/accept-work`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-fetch source files)

**Kode:** `EditorPromosiController::acceptWork()` (Line 580-653)

**Fitur:**
- ✅ Editor Promosi terima tugas editing
- ✅ Validasi user adalah Editor Promotion
- ✅ Validasi status bisa `draft`, `planning`, atau `editing`
- ✅ **Auto-fetch source files** dari Editor dan BTS ✅
- ✅ Update status menjadi `editing`
- ✅ Assign work ke user
- ✅ **Notifikasi ke Producer** ✅

**Auto-fetch Source Files:**
- ✅ Files dari Editor (EditorWork dengan status `completed` atau `approved`)
- ✅ Files dari BTS (PromotionWork dengan `work_type=bts_video` atau `bts_photo`, filter hanya `type=bts_video`)

---

### **9. EDITOR PROMOSI - PROSES PEKERJAAN**

#### **9.1. Editor Promosi - Edit Video BTS**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::uploadFiles()` (Line 240-315)

**Fitur:**
- ✅ Upload file hasil editing BTS video
- ✅ Simpan ke `file_paths` array
- ✅ Validasi work_type harus `bts_video`

---

#### **9.2. Editor Promosi - Edit Iklan Episode TV**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Fitur:**
- ✅ Upload file hasil editing iklan TV
- ✅ Validasi work_type harus `iklan_episode_tv`

---

#### **9.3. Editor Promosi - Buat Highlight Episode IG**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Fitur:**
- ✅ Upload file highlight IG
- ✅ Validasi work_type harus `highlight_ig`

---

#### **9.4. Editor Promosi - Buat Highlight Episode TV**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Fitur:**
- ✅ Upload file highlight TV
- ✅ Validasi work_type harus `highlight_tv`

---

#### **9.5. Editor Promosi - Buat Highlight Episode Facebook**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Status:** ✅ **SUDAH ADA**

**Fitur:**
- ✅ Upload file highlight Facebook
- ✅ Validasi work_type harus `highlight_facebook`

---

### **10. EDITOR PROMOSI - SELESAI PEKERJAAN**

#### **10.1. Editor Promosi - Submit ke QC**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/submit-to-qc`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorPromosiController::submitToQC()` (Line 657-738)

**Fitur:**
- ✅ Submit hasil editing ke Quality Control
- ✅ Auto-create QualityControlWork
- ✅ Notify QC

---

## 📋 RINGKASAN ENDPOINT

### **Promosi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/complete-work` | POST | Selesai pekerjaan (auto-create DesignGrafisWork & PromotionWork) | ✅ |

### **Design Grafis:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works` | GET | List semua works | ✅ |
| `/works/{id}/accept-work` | POST | Terima pekerjaan (auto-fetch source files) | ✅ |
| `/works/{id}/upload-thumbnail-youtube` | POST | Upload thumbnail YouTube | ✅ |
| `/works/{id}/upload-thumbnail-bts` | POST | Upload thumbnail BTS | ✅ |
| `/works/{id}/complete-work` | POST | Selesai pekerjaan | ✅ |

### **Editor Promosi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works` | GET | List semua works | ✅ |
| `/works/{id}/accept-work` | POST | Terima pekerjaan (auto-fetch source files) | ✅ |
| `/works/{id}/upload` | POST | Upload file hasil editing | ✅ |
| `/source-files` | GET | Get source files (Editor atau BTS) | ✅ |
| `/works/{id}/submit-to-qc` | POST | Submit ke QC | ✅ |

---

## ✅ YANG SUDAH BENAR

1. ✅ Promosi complete work → Auto-create DesignGrafisWork untuk thumbnail BTS
2. ✅ Promosi complete work → Auto-create 5 PromotionWork untuk Editor Promosi
3. ✅ Notifikasi ke Design Grafis saat Promosi complete
4. ✅ Notifikasi ke Editor Promosi saat Promosi complete
5. ✅ Design Grafis bisa terima file dari Produksi dan Promosi
6. ✅ Design Grafis bisa buat thumbnail YouTube dan BTS
7. ✅ Editor Promosi bisa terima file dari Editor dan BTS
8. ✅ Editor Promosi bisa edit semua work types
9. ✅ Editor Promosi submit ke QC setelah selesai

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Promosi complete → Auto-create DesignGrafisWork (thumbnail BTS)
- ✅ Promosi complete → Auto-create PromotionWork (Editor Promosi - 5 types)
- ✅ Design Grafis terima notifikasi dan file
- ✅ Editor Promosi terima notifikasi dan file
- ✅ Semua endpoint sudah tersedia dan siap digunakan

Semua endpoint sudah tersedia dan siap digunakan untuk frontend integration.

---

**Last Updated:** 2025-01-27
