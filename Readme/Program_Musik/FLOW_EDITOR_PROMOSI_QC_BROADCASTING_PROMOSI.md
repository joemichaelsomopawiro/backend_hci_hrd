# Flow Editor Promosi → QC → Broadcasting & Promosi

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Editor Promosi submit ke QC, QC approve/reject, kemudian lanjut ke Broadcasting dan Promosi untuk sharing.

---

## 🔄 WORKFLOW LENGKAP

```
Editor Promosi Complete Work
    ↓
Submit ke QC ✅
    ↓
Auto-Create QualityControlWork ✅
Notify QC ✅
    ↓
QC Terima Pekerjaan ✅
QC Proses Pekerjaan ✅
QC Berbagai Konten:
    - QC Video BTS ✅
    - QC Iklan Episode TV ✅
    - QC Highlight Episode IG ✅
    - QC Highlight Episode TV ✅
    - QC Highlight Episode Facebook ✅
    ↓
QC Approve/Reject ✅
    ↓
┌─────────────────────┬─────────────────────┐
│   QC Reject         │   QC Approve        │
│   ↓                 │   ↓                 │
│   Kembali ke        │   Update Status:    │
│   Editor Promosi    │   - Editor Promosi: approved ✅
│   (status: editing) │   ↓                 │
│   ↓                 │   Auto-Create:      │
│   Editor Promosi    │   BroadcastingWork ✅
│   Revisi & Resubmit │   ↓                 │
│                     │   Notify:           │
│                     │   - Broadcasting ✅
│                     │   - Promosi (info) ✅
│                     │   ↓                 │
│                     │   Broadcasting:     │
│                     │   - Upload YT ✅    │
│                     │   - Upload Web ✅   │
│                     │   - Complete ✅     │
│                     │   ↓                 │
│                     │   Auto-Create:      │
│                     │   - share_facebook ✅
│                     │   - share_wa_group ✅
│                     │   ↓                 │
│                     │   Notify Promosi ✅ │
│                     │   ↓                 │
│                     │   Promosi:          │
│                     │   - Share FB ✅     │
│                     │   - Story IG ✅     │
│                     │   - Reels FB ✅     │
│                     │   - WA Group ✅     │
│                     │   - Upload Bukti ✅ │
└─────────────────────┴─────────────────────┘
```

---

## 📋 DETAIL WORKFLOW

### **1. EDITOR PROMOSI - SELESAI PEKERJAAN**

#### **1.1. Editor Promosi - Proses Pekerjaan**
**Status:** ✅ **SUDAH ADA**

Editor Promosi melakukan berbagai editing tasks:
- Edit Video BTS
- Edit Iklan Episode TV
- Buat Highlight Episode IG
- Buat Highlight Episode TV
- Buat Highlight Episode Facebook

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

---

#### **1.2. Editor Promosi - Submit ke QC**
**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/submit-to-qc`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-create QualityControlWork dengan mapping work_type → qc_type)

**Kode:** `EditorPromosiController::submitToQC()` (Line 672-835+)

**Fitur:**
- ✅ Validasi file_paths harus ada
- ✅ Validasi status harus `editing`, `completed`, atau `review`
- ✅ Map `work_type` ke `qc_type`:
  - `bts_video` → `bts_video`
  - `iklan_episode_tv` → `advertisement_tv`
  - `highlight_ig` → `highlight_ig`
  - `highlight_tv` → `highlight_tv`
  - `highlight_facebook` → `highlight_facebook`
- ✅ **Auto-create QualityControlWork** ✅ (atau update jika sudah ada)
- ✅ Simpan file locations dengan `promotion_work_id` ke `editor_promosi_file_locations`
- ✅ Update status menjadi `review`
- ✅ **Notifikasi ke QC users** ✅

**Notification Type:** `editor_promosi_submitted_to_qc`

**Data yang dikirim:**
```json
{
  "promotion_work_id": 1,
  "qc_work_id": 5,
  "episode_id": 1,
  "work_type": "bts_video",
  "qc_type": "bts_video"
}
```

---

### **2. QC - TERIMA PEKERJAAN**

#### **2.1. QC - Terima Notifikasi**
**Dipicu oleh:** Editor Promosi submit ke QC  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `editor_promosi_submitted_to_qc`

---

#### **2.2. QC - Terima Lokasi File dari Editor Promosi**
**Status:** ✅ **SUDAH ADA** (Auto-disimpan saat Editor Promosi submit)

**Catatan:** File locations sudah tersimpan di `QualityControlWork.editor_promosi_file_locations` saat Editor Promosi submit.

**Endpoint Alternatif:** `POST /api/live-tv/quality-control/works/{id}/receive-editor-promosi-files`

---

#### **2.3. QC - Terima Lokasi File dari Design Grafis**
**Status:** ✅ **SUDAH ADA**

**Catatan:** File locations bisa ditambahkan melalui endpoint `POST /api/live-tv/quality-control/works/{id}/receive-design-grafis-files` atau auto-disimpan saat Design Grafis submit.

---

#### **2.4. QC - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/accept-work`

**Status:** ✅ **SUDAH ADA**

**Kode:** `QualityControlController::acceptWork()` (Line 624-660)

**Fitur:**
- ✅ Validasi status harus `pending`
- ✅ Update status menjadi `in_progress`
- ✅ Assign work ke user

---

### **3. QC - PROSES PEKERJAAN**

#### **3.1. QC - Proses Pekerjaan & QC Berbagai Konten**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/submit-qc-form`

**Status:** ✅ **SUDAH ADA**

**Kode:** `QualityControlController::submitQCFormForWork()` (Line 744-817)

**Endpoint Alternatif:** `POST /api/live-tv/quality-control/works/{id}/qc-content`

**Kode:** `QualityControlController::qcContent()` (Line 666-738)

**Fitur:**
- ✅ QC berbagai konten dari Editor Promosi:
  - Video BTS (`bts_video`)
  - Iklan Episode TV (`advertisement_tv` / `iklan_episode_tv`)
  - Highlight Episode IG (`highlight_ig`)
  - Highlight Episode TV (`highlight_tv`)
  - Highlight Episode Facebook (`highlight_facebook`)
- ✅ QC Thumbnail dari Design Grafis:
  - Thumbnail YT (`thumbnail_yt`)
  - Thumbnail BTS (`thumbnail_bts`)
- ✅ Input QC notes, quality score, issues found, improvements needed
- ✅ Option untuk auto-approve jika tidak ada revisi

---

#### **3.2. QC - Selesai Pekerjaan (Approve/Reject)**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/finalize`

**Status:** ✅ **SUDAH DIPERBAIKI**

**Kode:** `QualityControlController::finalize()` (Line 878-1210+)

---

### **4. QC REJECT → KEMBALI KE EDITOR PROMOSI**

#### **4.1. QC Reject Editor Promosi**
**Status:** ✅ **SUDAH DIPERBAIKI**

**Fitur:**
- ✅ Update PromotionWork status menjadi `editing`
- ✅ Simpan review notes
- ✅ Set reviewed_by dan reviewed_at
- ✅ **Notifikasi ke Editor Promosi** ✅

**Notification Type:** `qc_rejected_revision_needed`

**Data yang dikirim:**
```json
{
  "episode_id": 1,
  "qc_work_id": 5,
  "revision_notes": "Perlu perbaikan...",
  "source": "editor_promosi",
  "promotion_work_ids": [1, 2, 3]
}
```

---

#### **4.2. Editor Promosi - Terima Notifikasi Reject**
**Status:** ✅ **SUDAH ADA**

**Editor Promosi akan:**
- ✅ Terima notifikasi reject
- ✅ Baca feedback dari QC
- ✅ Revisi pekerjaan (status: `editing`)
- ✅ Submit ulang ke QC

**Catatan:** Editor Promosi bisa accept work lagi dari status `editing`, `rejected`, atau `review` untuk resubmission.

---

### **5. QC APPROVE → BROADCASTING & PROMOSI**

#### **5.1. QC Approve - Auto-Update Status**
**Status:** ✅ **SUDAH DIPERBAIKI**

**Fitur:**
- ✅ Update Editor Promosi PromotionWork status menjadi `approved`
- ✅ Update QualityControlWork status menjadi `approved`

---

#### **5.2. QC Approve - Auto-Create BroadcastingWork**
**Status:** ✅ **SUDAH DIPERBAIKI**

**Fitur:**
- ✅ Auto-create BroadcastingWork jika ada file dari Editor atau Design Grafis
- ✅ Simpan video file path dari Editor (main episode)
- ✅ Simpan thumbnail path dari Design Grafis (prioritaskan `thumbnail_youtube`)
- ✅ Status: `preparing`
- ✅ **Notifikasi ke Broadcasting** ✅ (dengan info file dari Editor, Design Grafis, dan Editor Promosi)

**Notification Type:** `broadcasting_work_assigned`

---

#### **5.3. QC Approve - Notify Promosi (Info Editor Promosi Files Ready)**
**Status:** ✅ **SUDAH DITAMBAHKAN**

**Notification Type:** `qc_approved_editor_promosi_ready`

**Data yang dikirim:**
```json
{
  "episode_id": 1,
  "qc_work_id": 5,
  "broadcasting_work_id": 10,
  "editor_promosi_work_types": ["bts_video", "highlight_ig", "highlight_tv"]
}
```

**Catatan:** Notifikasi utama untuk sharing akan dikirim setelah Broadcasting complete work.

---

### **6. BROADCASTING - TERIMA PEKERJAAN**

#### **6.1. Broadcasting - Terima Notifikasi**
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `broadcasting_work_assigned`

---

#### **6.2. Broadcasting - Terima File Materi dari QC**
**Status:** ✅ **SUDAH ADA**

**Data yang tersedia:**
- ✅ `video_file_path` - dari Editor (main episode)
- ✅ `thumbnail_path` - dari Design Grafis
- ✅ Info tentang Editor Promosi files (untuk referensi)

---

#### **6.3. Broadcasting - Terima Thumbnail dari Design Grafis**
**Status:** ✅ **SUDAH ADA**

**Data yang tersedia:**
- ✅ `thumbnail_path` - sudah disimpan di BroadcastingWork saat QC approve

---

#### **6.4. Broadcasting - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/accept-work`

**Status:** ✅ **SUDAH ADA**

---

### **7. BROADCASTING - PROSES PEKERJAAN**

Semua endpoint sudah ada dan sama seperti flow Design Grafis → QC → Broadcasting:

- ✅ Proses pekerjaan
- ✅ Masukan ke Jadwal Playlist
- ✅ Upload di YouTube (dengan SEO: thumbnail, deskripsi, tag, judul)
- ✅ Upload ke Website
- ✅ Input link YT ke sistem
- ✅ Selesai Pekerjaan (auto-create PromotionWork untuk sharing & notify Promosi)

---

### **8. PROMOSI - SHARING**

Semua endpoint sudah ada dan sama seperti flow Design Grafis → QC → Broadcasting → Promosi:

- ✅ Terima Notifikasi
- ✅ Terima Link YouTube dan Website
- ✅ Terima Pekerjaan (auto-create PromotionWork untuk sharing)
- ✅ Share link Website ke Facebook (dengan bukti)
- ✅ Buat Video HL untuk Story IG (dengan bukti)
- ✅ Buat Video HL untuk Reels Facebook (dengan bukti)
- ✅ Share ke grup Promosi WA (dengan bukti)

---

## 📋 RINGKASAN ENDPOINT

### **Editor Promosi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/accept-work` | POST | Terima pekerjaan (support resubmission dari rejected/review) | ✅ |
| `/works/{id}/upload` | POST | Upload file hasil editing (BTS, Iklan TV, Highlight) | ✅ |
| `/works/{id}/submit-to-qc` | POST | Submit ke QC (auto-create QualityControlWork dengan mapping) | ✅ |

### **QC:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/accept-work` | POST | Terima pekerjaan | ✅ |
| `/works/{id}/receive-editor-promosi-files` | POST | Terima file dari Editor Promosi | ✅ |
| `/works/{id}/receive-design-grafis-files` | POST | Terima file dari Design Grafis | ✅ |
| `/works/{id}/submit-qc-form` | POST | Submit QC form | ✅ |
| `/works/{id}/qc-content` | POST | QC berbagai konten | ✅ |
| `/works/{id}/finalize` | POST | Approve/Reject | ✅ |

### **Broadcasting:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/accept-work` | POST | Terima pekerjaan | ✅ |
| `/works/{id}/schedule-work-playlist` | POST | Masukan ke jadwal playlist | ✅ |
| `/works/{id}/upload-youtube` | POST | Upload di YouTube (dengan SEO) | ✅ |
| `/works/{id}/upload-website` | POST | Upload ke website | ✅ |
| `/works/{id}/input-youtube-link` | POST | Input link YT ke sistem | ✅ |
| `/works/{id}/complete-work` | POST | Selesai pekerjaan (auto-create PromotionWork & notify Promosi) | ✅ |

### **Promosi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/accept-work` | POST | Terima pekerjaan sharing | ✅ |
| `/works/{id}/share-facebook` | POST | Share ke Facebook + upload bukti | ✅ |
| `/works/{id}/upload-story-ig` | POST | Upload Story IG video + upload bukti | ✅ |
| `/works/{id}/upload-reels-facebook` | POST | Upload Reels Facebook video + upload bukti | ✅ |
| `/works/{id}/share-wa-group` | POST | Share ke WA group + upload bukti | ✅ |

---

## ✅ YANG SUDAH BENAR

1. ✅ Editor Promosi submit ke QC → Auto-create QualityControlWork dengan mapping work_type → qc_type
2. ✅ QC terima notifikasi dan pekerjaan
3. ✅ QC bisa QC berbagai konten dari Editor Promosi (BTS, Iklan TV, Highlight)
4. ✅ QC reject → Kembali ke Editor Promosi (status: editing)
5. ✅ QC approve → Auto-create BroadcastingWork
6. ✅ QC approve → Update Editor Promosi status menjadi approved
7. ✅ Broadcasting terima file dari QC (Editor + Design Grafis + info Editor Promosi)
8. ✅ Broadcasting upload YouTube dan Website
9. ✅ Broadcasting complete → Auto-create PromotionWork untuk sharing & notify Promosi
10. ✅ Promosi sharing endpoints sudah diimplementasikan dengan bukti upload

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Editor Promosi → QC flow sudah lengkap
- ✅ QC → Broadcasting flow sudah lengkap
- ✅ Broadcasting → Promosi auto-create & notification sudah ada
- ✅ Promosi sharing endpoints sudah diimplementasikan dengan bukti
- ✅ QC reject → Editor Promosi revisi & resubmit sudah support

Semua endpoint sudah tersedia dan siap digunakan untuk frontend integration.

---

**Last Updated:** 2025-01-27
