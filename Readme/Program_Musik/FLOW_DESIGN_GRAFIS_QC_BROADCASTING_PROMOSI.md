# Flow Design Grafis → QC → Broadcasting & Promosi

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Design Grafis submit ke QC, QC approve/reject, kemudian lanjut ke Broadcasting dan Promosi untuk sharing.

---

## 🔄 WORKFLOW LENGKAP

```
Design Grafis Complete Work
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
    - QC Thumbnail YT ✅
    - QC Thumbnail BTS ✅
    ↓
QC Approve/Reject ✅
    ↓
┌─────────────────────┬─────────────────────┐
│   QC Reject         │   QC Approve        │
│   ↓                 │   ↓                 │
│   Kembali ke        │   Update Status:    │
│   Design Grafis     │   - Design Grafis: approved ✅
│   (revision_needed) │   - Editor Promosi: approved ✅
│   ↓                 │   ↓                 │
│   Design Grafis     │   Auto-Create:      │
│   Revisi & Resubmit │   BroadcastingWork ✅
│                     │   ↓                 │
│                     │   Notify:           │
│                     │   - Broadcasting ✅
│                     │   - Promosi (ready for sharing) ✅
│                     │   ↓                 │
│                     │   Broadcasting:     │
│                     │   - Terima Pekerjaan ✅
│                     │   - Upload YouTube ✅
│                     │   - Upload Website ✅
│                     │   - Schedule Playlist ✅
│                     │   - Complete Work ✅
│                     │   ↓                 │
│                     │   Notify Promosi dengan:
│                     │   - YouTube URL ✅
│                     │   - Website URL ✅
│                     │   ↓                 │
│                     │   Promosi:          │
│                     │   - Terima Notifikasi ✅
│                     │   - Share ke Facebook ✅
│                     │   - Story IG ✅
│                     │   - Reels Facebook ✅
│                     │   - Grup WA ✅
│                     │   - Masukan Bukti ✅
└─────────────────────┴─────────────────────┘
```

---

## 📋 DETAIL WORKFLOW

### **1. DESIGN GRAFIS - SUBMIT KE QC**

#### **1.1. Design Grafis - Selesai Pekerjaan**
**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/complete-work`

**Status:** ✅ **SUDAH ADA**

**Fitur:**
- ✅ Validasi file_path atau file_paths harus ada
- ✅ Status berubah menjadi `completed`
- ✅ Notify Producer

---

#### **1.2. Design Grafis - Submit ke QC**
**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/submit-to-qc`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-create QualityControlWork)

**Kode:** `DesignGrafisController::submitToQC()` (Line 811-900+)

**Fitur:**
- ✅ Validasi status harus `completed`
- ✅ Map `work_type` ke `qc_type`:
  - `thumbnail_youtube` → `thumbnail_yt`
  - `thumbnail_bts` → `thumbnail_bts`
- ✅ **Auto-create QualityControlWork** ✅
- ✅ Simpan file locations ke `design_grafis_file_locations`
- ✅ Update status menjadi `reviewed`
- ✅ **Notifikasi ke QC users** ✅

**Notification Type:** `design_grafis_submitted_to_qc`

**Data yang dikirim:**
```json
{
  "design_grafis_work_id": 1,
  "qc_work_id": 5,
  "episode_id": 1,
  "work_type": "thumbnail_youtube",
  "qc_type": "thumbnail_yt"
}
```

---

### **2. QC - TERIMA PEKERJAAN**

#### **2.1. QC - Terima Notifikasi**
**Dipicu oleh:** Design Grafis submit ke QC  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `design_grafis_submitted_to_qc`

---

#### **2.2. QC - Terima Lokasi File dari Editor Promosi**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/receive-editor-promosi-files`

**Status:** ✅ **SUDAH ADA**

**Kode:** `QualityControlController::receiveEditorPromosiFiles()` (Line 520-566)

**Fitur:**
- ✅ Input file locations dari Editor Promosi
- ✅ Simpan ke `editor_promosi_file_locations`

---

#### **2.3. QC - Terima Lokasi File dari Design Grafis**
**Status:** ✅ **SUDAH ADA** (Auto-disimpan saat Design Grafis submit)

**Catatan:** File locations sudah tersimpan di `QualityControlWork.design_grafis_file_locations` saat Design Grafis submit.

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
- ✅ QC berbagai konten:
  - Video BTS (`bts_video`)
  - Iklan Episode TV (`iklan_episode_tv`)
  - Highlight Episode IG (`highlight_ig`)
  - Highlight Episode TV (`highlight_tv`)
  - Highlight Episode Facebook (`highlight_facebook`)
  - Thumbnail YT (`thumbnail_yt`)
  - Thumbnail BTS (`thumbnail_bts`)
- ✅ Input QC notes, quality score, issues found, improvements needed
- ✅ Option untuk auto-approve jika tidak ada revisi

---

#### **3.2. QC - Selesai Pekerjaan (Approve/Reject)**
**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/finalize`

**Status:** ✅ **SUDAH DIPERBAIKI**

**Kode:** `QualityControlController::finalize()` (Line 878-1117)

---

### **4. QC REJECT → KEMBALI KE DESIGN GRAFIS**

#### **4.1. QC Reject Design Grafis**
**Status:** ✅ **SUDAH DIPERBAIKI**

**Fitur:**
- ✅ Update DesignGrafisWork status menjadi `revision_needed`
- ✅ Simpan QC feedback ke `qc_feedback`
- ✅ **Notifikasi ke Design Grafis** ✅

**Notification Type:** `qc_rejected_revision_needed`

**Data yang dikirim:**
```json
{
  "episode_id": 1,
  "qc_work_id": 5,
  "revision_notes": "Perlu perbaikan...",
  "source": "design_grafis",
  "design_grafis_work_ids": [1, 2]
}
```

---

#### **4.2. Design Grafis - Terima Notifikasi Reject**
**Status:** ✅ **SUDAH ADA**

**Design Grafis akan:**
- ✅ Terima notifikasi reject
- ✅ Baca feedback dari QC
- ✅ Revisi pekerjaan
- ✅ Submit ulang ke QC

---

### **5. QC APPROVE → BROADCASTING & PROMOSI**

#### **5.1. QC Approve - Auto-Update Status**
**Status:** ✅ **SUDAH DIPERBAIKI**

**Fitur:**
- ✅ Update DesignGrafisWork status menjadi `approved`
- ✅ Update Editor Promosi PromotionWork status menjadi `approved`
- ✅ Update QualityControlWork status menjadi `approved`

---

#### **5.2. QC Approve - Auto-Create BroadcastingWork**
**Status:** ✅ **SUDAH DIPERBAIKI**

**Fitur:**
- ✅ Auto-create BroadcastingWork jika ada file dari Editor atau Design Grafis
- ✅ Simpan video file path dari Editor
- ✅ Simpan thumbnail path dari Design Grafis (prioritaskan `thumbnail_youtube`)
- ✅ Status: `preparing`
- ✅ **Notifikasi ke Broadcasting** ✅

**Notification Type:** `broadcasting_work_assigned`

**Data yang dikirim:**
```json
{
  "broadcasting_work_id": 10,
  "episode_id": 1,
  "qc_work_id": 5,
  "video_file_path": "storage/editor/...",
  "thumbnail_path": "storage/design_grafis/...",
  "has_design_grafis_files": true
}
```

---

#### **5.3. QC Approve - Notify Promosi (Ready for Sharing)**
**Status:** ✅ **SUDAH DITAMBAHKAN**

**Notification Type:** `qc_approved_promosi_ready_for_sharing`

**Data yang dikirim:**
```json
{
  "episode_id": 1,
  "qc_work_id": 5,
  "broadcasting_work_id": 10,
  "has_editor_promosi_files": true
}
```

**Catatan:** Promosi akan menerima notifikasi lanjutan setelah Broadcasting complete work dengan YouTube URL dan Website URL.

---

### **6. BROADCASTING - TERIMA PEKERJAAN**

#### **6.1. Broadcasting - Terima Notifikasi**
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `broadcasting_work_assigned`

---

#### **6.2. Broadcasting - Terima File Materi dari QC**
**Status:** ✅ **SUDAH ADA**

**Data yang tersedia:**
- ✅ `video_file_path` - dari Editor (via QC)
- ✅ `thumbnail_path` - dari Design Grafis (via QC)

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

#### **7.1. Broadcasting - Proses Pekerjaan**
**Status:** ✅ **SUDAH ADA**

---

#### **7.2. Broadcasting - Masukan ke Jadwal Playlist**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/schedule-work-playlist`

**Status:** ✅ **SUDAH ADA**

**Kode:** `BroadcastingController::scheduleWorkPlaylist()` (Line 731-800)

**Fitur:**
- ✅ Input playlist data, scheduled_time
- ✅ Update status menjadi `scheduled`

---

#### **7.3. Broadcasting - Upload di YouTube**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-youtube`

**Status:** ✅ **SUDAH ADA**

**Kode:** `BroadcastingController::uploadYouTube()` (Line 600-703)

**Fitur:**
- ✅ Upload ke YouTube (input YouTube URL)
- ✅ Input thumbnail (thumbnail_path)
- ✅ Input deskripsi (description)
- ✅ Input tag (tags array)
- ✅ Input judul sesuai SEO (title)
- ✅ Input category_id dan privacy_status
- ✅ Simpan YouTube URL dan video ID
- ✅ Simpan SEO metadata
- ✅ Update status menjadi `uploading`
- ✅ Notify Producer

---

#### **7.4. Broadcasting - Upload ke Website**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-website`

**Status:** ✅ **SUDAH ADA**

**Kode:** `BroadcastingController::uploadWebsite()` (Line 584-662)

**Fitur:**
- ✅ Upload ke website (input website URL)
- ✅ Simpan website URL
- ✅ Simpan metadata
- ✅ Notify Producer

---

#### **7.5. Broadcasting - Input Link YT ke Sistem**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/input-youtube-link`

**Status:** ✅ **SUDAH ADA**

**Kode:** `BroadcastingController::inputYouTubeLink()` (Line 668-718)

**Fitur:**
- ✅ Input YouTube link
- ✅ Auto-extract video ID
- ✅ Simpan YouTube URL dan video ID

---

#### **7.6. Broadcasting - Selesai Pekerjaan**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/complete-work`

**Status:** ✅ **SUDAH DIPERBAIKI** (Notify Promosi dengan YouTube & Website URL)

**Kode:** `BroadcastingController::completeWork()` (Line 801-888)

**Fitur:**
- ✅ Validasi YouTube atau Website URL harus sudah diisi
- ✅ Update status menjadi `published`
- ✅ Set published_time
- ✅ **Notifikasi ke Manager Program** ✅
- ✅ **Notifikasi ke Promosi dengan YouTube URL dan Website URL** ✅

**Notification Type:** `broadcasting_published_promosi_sharing`

**Data yang dikirim ke Promosi:**
```json
{
  "broadcasting_work_id": 10,
  "episode_id": 1,
  "youtube_url": "https://youtube.com/...",
  "website_url": "https://website.com/...",
  "thumbnail_path": "storage/design_grafis/...",
  "title": "...",
  "description": "..."
}
```

---

### **8. PROMOSI - SHARING**

#### **8.1. Promosi - Terima Notifikasi**
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `broadcasting_published_promosi_sharing`

---

#### **8.2. Promosi - Terima Link YouTube**
**Status:** ✅ **SUDAH ADA**

**Data yang tersedia:**
- ✅ `youtube_url` - dari notifikasi Broadcasting

---

#### **8.3. Promosi - Terima Link Website**
**Status:** ✅ **SUDAH ADA**

**Data yang tersedia:**
- ✅ `website_url` - dari notifikasi Broadcasting

---

#### **8.4. Promosi - Terima Pekerjaan**
**Status:** ✅ **SUDAH ADA** (Auto-create saat Broadcasting complete)

**Catatan:** PromotionWork untuk `share_facebook` dan `share_wa_group` sudah auto-create saat Broadcasting complete work. Promosi tinggal accept work menggunakan endpoint yang sudah ada.

**Endpoint:** `POST /api/live-tv/promosi/works/{id}/accept-work`

**Fitur:**
- ✅ Accept PromotionWork dengan `work_type` = `share_facebook`, `share_wa_group`, `story_ig`, atau `reels_facebook`
- ✅ YouTube URL dan Website URL sudah tersedia di `social_media_links`
- ✅ Status: `planning` → `shooting` atau sesuai kebutuhan

---

#### **8.5. Promosi - Share Link Website ke Facebook**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/share-facebook`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::shareFacebook()` (Line 913-980)

**Fitur:**
- ✅ Validasi work_type harus `share_facebook`
- ✅ Upload bukti screenshot/posting (jpg, jpeg, png - max 10MB)
- ✅ Input Facebook post URL (optional)
- ✅ Simpan bukti ke sistem di `social_media_links.facebook_share`
- ✅ Update status menjadi `published`

---

#### **8.6. Promosi - Buat Video HL untuk Story IG**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/upload-story-ig`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::uploadStoryIG()` (Line 982-1070)

**Fitur:**
- ✅ Validasi work_type harus `story_ig`
- ✅ Upload video highlight untuk Story IG (mp4, mov, avi - max 100MB)
- ✅ Upload bukti posting (jpg, jpeg, png - max 10MB)
- ✅ Input Story URL (optional)
- ✅ Simpan video ke `file_paths` dan bukti ke `social_media_links.story_ig`
- ✅ Update status menjadi `published`

---

#### **8.7. Promosi - Buat Video HL untuk Reels Facebook**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/upload-reels-facebook`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::uploadReelsFacebook()` (Line 1072-1160)

**Fitur:**
- ✅ Validasi work_type harus `reels_facebook`
- ✅ Upload video highlight untuk Reels Facebook (mp4, mov, avi - max 100MB)
- ✅ Upload bukti posting (jpg, jpeg, png - max 10MB)
- ✅ Input Reels URL (optional)
- ✅ Simpan video ke `file_paths` dan bukti ke `social_media_links.reels_facebook`
- ✅ Update status menjadi `published`

---

#### **8.8. Promosi - Share ke Grup Promosi WA**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/share-wa-group`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::shareWAGroup()` (Line 1162-1238)

**Fitur:**
- ✅ Validasi work_type harus `share_wa_group`
- ✅ Upload bukti screenshot (jpg, jpeg, png - max 10MB)
- ✅ Input group name (optional)
- ✅ Simpan bukti ke sistem di `social_media_links.wa_group_share`
- ✅ Update status menjadi `published`

---

## 📋 RINGKASAN ENDPOINT

### **Design Grafis:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/complete-work` | POST | Selesai pekerjaan | ✅ |
| `/works/{id}/submit-to-qc` | POST | Submit ke QC (auto-create QualityControlWork) | ✅ |

### **QC:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works` | GET | List semua QC works | ✅ |
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
| `/works/{id}/complete-work` | POST | Selesai pekerjaan (notify Promosi) | ✅ |

### **Promosi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/accept-work` | POST | Terima pekerjaan sharing (work_type: share_facebook, share_wa_group, story_ig, reels_facebook) | ✅ |
| `/works/{id}/share-facebook` | POST | Share ke Facebook + upload bukti | ✅ |
| `/works/{id}/upload-story-ig` | POST | Upload Story IG video + upload bukti | ✅ |
| `/works/{id}/upload-reels-facebook` | POST | Upload Reels Facebook video + upload bukti | ✅ |
| `/works/{id}/share-wa-group` | POST | Share ke WA group + upload bukti | ✅ |

---

## ✅ YANG SUDAH BENAR

1. ✅ Design Grafis submit ke QC → Auto-create QualityControlWork
2. ✅ QC terima notifikasi dan pekerjaan
3. ✅ QC bisa QC berbagai konten (BTS, Iklan TV, Highlight, Thumbnail)
4. ✅ QC reject → Kembali ke Design Grafis (status: revision_needed)
5. ✅ QC approve → Auto-create BroadcastingWork
6. ✅ QC approve → Update Design Grafis & Editor Promosi status menjadi approved
7. ✅ Broadcasting terima file dari QC
8. ✅ Broadcasting upload YouTube dan Website
9. ✅ Broadcasting complete → Notify Promosi dengan YouTube & Website URL

---

## ✅ YANG SUDAH DIIMPLEMENTASIKAN

1. ✅ Design Grafis submit ke QC → Auto-create QualityControlWork
2. ✅ QC terima notifikasi dan pekerjaan
3. ✅ QC bisa QC berbagai konten (BTS, Iklan TV, Highlight, Thumbnail)
4. ✅ QC reject → Kembali ke Design Grafis (status: revision_needed)
5. ✅ QC approve → Auto-create BroadcastingWork
6. ✅ QC approve → Update Design Grafis & Editor Promosi status menjadi approved
7. ✅ Broadcasting terima file dari QC
8. ✅ Broadcasting upload YouTube dan Website
9. ✅ Broadcasting complete → Auto-create PromotionWork untuk sharing (share_facebook, share_wa_group)
10. ✅ Broadcasting complete → Notify Promosi dengan YouTube & Website URL
11. ✅ Promosi - Endpoint untuk share ke Facebook dengan bukti
12. ✅ Promosi - Endpoint untuk upload Story IG dengan bukti
13. ✅ Promosi - Endpoint untuk upload Reels Facebook dengan bukti
14. ✅ Promosi - Endpoint untuk share ke WA group dengan bukti

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Design Grafis → QC flow sudah lengkap
- ✅ QC → Broadcasting flow sudah lengkap
- ✅ Broadcasting → Promosi auto-create PromotionWork untuk sharing
- ✅ Broadcasting → Promosi notification dengan YouTube & Website URL
- ✅ Promosi sharing endpoints sudah diimplementasikan dengan bukti upload

---

**Last Updated:** 2025-01-27
