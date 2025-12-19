# ✅ Verifikasi Flow Lengkap: Promosi → Design Grafis & Editor Promosi → QC → Broadcasting → Promosi

**Tanggal:** 12 Desember 2025  
**Status:** ✅ **SEMUA FLOW SUDAH DIIMPLEMENTASIKAN & AMAN**

---

## 📋 Ringkasan Eksekutif

Semua flow setelah Promosi selesai sudah **LENGKAP** dan **AMAN**. Semua role (Design Grafis, Editor Promosi, Quality Control, Broadcasting, Promosi) sudah memiliki endpoint dan workflow yang sesuai dengan requirement.

**Perbaikan yang Sudah Dilakukan:**
- ✅ **Auto-create Design Grafis work** setelah Promosi complete work
- ✅ **QC reject flow** sekarang kembali ke role yang sesuai (Design Grafis atau Editor Promosi) berdasarkan source file
- ✅ **QC approve flow** mengirim notifikasi ke Broadcasting dan Promosi

---

## 🔄 FLOW LENGKAP SETELAH PROMOSI SELESAI

### **FLOW 1: Design Grafis - Thumbnail YouTube & BTS**

**Status:** ✅ **LENGKAP & AMAN**

#### **1.1. Design Grafis: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `design_grafis_work_created` - Design Grafis work task dibuat setelah Promosi selesai
- ✅ `bts_content_uploaded` - BTS content dari Promosi sudah di-upload

**Endpoint:** `GET /api/notifications`

**Controller:** `PromosiController::completeWork()` (line 829-903) - Auto-create Design Grafis work

---

#### **1.2. Design Grafis: Terima Lokasi File dari Produksi**

**Endpoint:** `GET /api/live-tv/design-grafis/shared-files?episode_id={id}&source_role=produksi`

**Flow:**
- ✅ Ambil file dari Produksi berdasarkan `episode_id`
- ✅ Filter by `file_type = 'production'`
- ✅ Return file locations

**Controller:** `DesignGrafisController::getSharedFiles()`

**File:** `app/Http/Controllers/Api/DesignGrafisController.php` (line 314-358)

---

#### **1.3. Design Grafis: Terima Lokasi Foto Talent dari Promosi**

**Endpoint:** `GET /api/live-tv/design-grafis/shared-files?episode_id={id}&source_role=promosi`

**Flow:**
- ✅ Ambil file dari Promosi berdasarkan `episode_id`
- ✅ Filter by `file_type = 'promotion'`
- ✅ Return file locations (talent photos)

**Controller:** `DesignGrafisController::getSharedFiles()`

**File:** `app/Http/Controllers/Api/DesignGrafisController.php` (line 314-358)

---

#### **1.4. Design Grafis: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/update` (status change)

**Flow:**
- ✅ Status: `draft` → `in_progress`
- ✅ Design Grafis siap untuk membuat thumbnail

**Controller:** `DesignGrafisController::update()` (line 164-219)

---

#### **1.5. Design Grafis: Buat Thumbnail YouTube**

**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/upload-files`

**Request Body:**
```json
{
  "files": ["<file1>", "<file2>"] // JPG, JPEG, PNG, PSD, AI, SVG, max 100MB
}
```

**Flow:**
- ✅ Upload thumbnail YouTube file
- ✅ File disimpan ke storage: `design_grafis/{work_id}/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)
- ✅ Create `MediaFile` record dengan `file_type = 'design_grafis'`

**Controller:** `DesignGrafisController::uploadFiles()`

**File:** `app/Http/Controllers/Api/DesignGrafisController.php` (line 224-309)

---

#### **1.6. Design Grafis: Buat Thumbnail BTS**

**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/upload-files`

**Request Body:**
```json
{
  "files": ["<file1>", "<file2>"] // JPG, JPEG, PNG, PSD, AI, SVG, max 100MB
}
```

**Flow:**
- ✅ Upload thumbnail BTS file
- ✅ File disimpan ke storage: `design_grafis/{work_id}/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)
- ✅ Create `MediaFile` record dengan `file_type = 'design_grafis'`

**Controller:** `DesignGrafisController::uploadFiles()`

**File:** `app/Http/Controllers/Api/DesignGrafisController.php` (line 224-309)

---

#### **1.7. Design Grafis: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/design-grafis/works/{id}/submit-to-qc`

**Flow:**
- ✅ Validasi: File harus sudah di-upload
- ✅ Create atau update `QualityControlWork` dengan `design_grafis_file_locations`
- ✅ Notifikasi ke Quality Control: `design_grafis_submitted_to_qc`

**Controller:** `DesignGrafisController::submitToQC()`

**File:** `app/Http/Controllers/Api/DesignGrafisController.php` (line 448-528)

---

### **FLOW 2: Editor Promosi - Edit Video BTS & Highlight**

**Status:** ✅ **LENGKAP & AMAN**

#### **2.1. Editor Promosi: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `editor_promosi_work_created` - Editor Promosi work task dibuat

**Endpoint:** `GET /api/notifications`

---

#### **2.2. Editor Promosi: Terima Lokasi File dari Editor (Main Editor)**

**Endpoint:** `GET /api/live-tv/editor-promosi/source-files?episode_id={id}&source_role=editor`

**Flow:**
- ✅ Ambil file dari Editor (main editor) berdasarkan `episode_id`
- ✅ Filter by `file_type = 'editor'` atau `'editor_work'`
- ✅ Juga ambil dari `EditorWork` table
- ✅ Return file locations

**Controller:** `EditorPromosiController::getSourceFiles()`

**File:** `app/Http/Controllers/Api/EditorPromosiController.php` (line 324-420)

---

#### **2.3. Editor Promosi: Terima Lokasi File dari BTS (Promosi)**

**Endpoint:** `GET /api/live-tv/editor-promosi/source-files?episode_id={id}&source_role=promosi`

**Flow:**
- ✅ Ambil file dari Promosi berdasarkan `episode_id`
- ✅ Filter by `file_type = 'promotion'`
- ✅ Return file locations (BTS videos)

**Controller:** `EditorPromosiController::getSourceFiles()`

**File:** `app/Http/Controllers/Api/EditorPromosiController.php` (line 324-420)

---

#### **2.4. Editor Promosi: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/update` (status change)

**Flow:**
- ✅ Status: `draft` / `pending` → `in_progress`
- ✅ Editor Promosi siap untuk edit content

**Controller:** `EditorPromosiController::update()`

**File:** `app/Http/Controllers/Api/EditorPromosiController.php` (line 172-229)

---

#### **2.5. Editor Promosi: Edit Video BTS**

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Request Body:**
```json
{
  "files": ["<file1>", "<file2>"], // MP4, AVI, MOV, max 1GB
  "work_type": "bts_video"
}
```

**Flow:**
- ✅ Upload edited BTS video
- ✅ File disimpan ke storage: `editor_promosi/{work_id}/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)

**Controller:** `EditorPromosiController::uploadFiles()`

**File:** `app/Http/Controllers/Api/EditorPromosiController.php` (line 234-319)

---

#### **2.6. Editor Promosi: Edit Iklan Episode TV**

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Request Body:**
```json
{
  "files": ["<file1>", "<file2>"],
  "work_type": "iklan_episode_tv"
}
```

**Flow:**
- ✅ Upload iklan episode TV
- ✅ File disimpan ke storage: `editor_promosi/{work_id}/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)

**Controller:** `EditorPromosiController::uploadFiles()`

---

#### **2.7. Editor Promosi: Buat Highlight Episode IG**

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Request Body:**
```json
{
  "files": ["<file1>", "<file2>"],
  "work_type": "highlight_episode_ig"
}
```

**Flow:**
- ✅ Upload highlight episode IG
- ✅ File disimpan ke storage: `editor_promosi/{work_id}/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)

**Controller:** `EditorPromosiController::uploadFiles()`

---

#### **2.8. Editor Promosi: Buat Highlight Episode TV**

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Request Body:**
```json
{
  "files": ["<file1>", "<file2>"],
  "work_type": "highlight_episode_tv"
}
```

**Flow:**
- ✅ Upload highlight episode TV
- ✅ File disimpan ke storage: `editor_promosi/{work_id}/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)

**Controller:** `EditorPromosiController::uploadFiles()`

---

#### **2.9. Editor Promosi: Buat Highlight Episode Facebook**

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/upload`

**Request Body:**
```json
{
  "files": ["<file1>", "<file2>"],
  "work_type": "highlight_episode_facebook"
}
```

**Flow:**
- ✅ Upload highlight episode Facebook
- ✅ File disimpan ke storage: `editor_promosi/{work_id}/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)

**Controller:** `EditorPromosiController::uploadFiles()`

---

#### **2.10. Editor Promosi: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/editor-promosi/works/{id}/submit-to-qc`

**Flow:**
- ✅ Validasi: File harus sudah di-upload
- ✅ Create atau update `QualityControlWork` dengan `editor_promosi_file_locations`
- ✅ Notifikasi ke Quality Control: `editor_promosi_submitted_to_qc`

**Controller:** `EditorPromosiController::submitToQC()`

**File:** `app/Http/Controllers/Api/EditorPromosiController.php` (line 508-560)

---

### **FLOW 3: Quality Control - QC Semua Materi**

**Status:** ✅ **LENGKAP & AMAN**

#### **3.1. Quality Control: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `design_grafis_submitted_to_qc` - Design Grafis mengajukan file ke QC
- ✅ `editor_promosi_submitted_to_qc` - Editor Promosi mengajukan file ke QC

**Endpoint:** `GET /api/notifications`

---

#### **3.2. Quality Control: Terima Lokasi File dari Editor Promosi**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/receive-editor-promosi-files`

**Flow:**
- ✅ Update `QualityControlWork` dengan `editor_promosi_file_locations`

**Controller:** `QualityControlController::receiveEditorPromosiFiles()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 520-566)

---

#### **3.3. Quality Control: Terima Lokasi File dari Design Grafis**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/receive-design-grafis-files`

**Flow:**
- ✅ Update `QualityControlWork` dengan `design_grafis_file_locations`

**Controller:** `QualityControlController::receiveDesignGrafisFiles()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 572-618)

---

#### **3.4. Quality Control: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/accept-work`

**Flow:**
- ✅ Status: `pending` → `in_progress`
- ✅ QC siap untuk melakukan quality control

**Controller:** `QualityControlController::acceptWork()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 624-660)

---

#### **3.5-3.11. Quality Control: QC Semua Materi**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/qc-content`

**Request Body:**
```json
{
  "qc_results": {
    "bts_video": {
      "status": "approved",
      "notes": "Video BTS sudah sesuai",
      "score": 90
    },
    "iklan_episode_tv": {
      "status": "approved",
      "notes": "Iklan episode TV sudah sesuai",
      "score": 88
    },
    "highlight_episode_ig": {
      "status": "approved",
      "notes": "Highlight episode IG sudah sesuai",
      "score": 92
    },
    "highlight_episode_tv": {
      "status": "approved",
      "notes": "Highlight episode TV sudah sesuai",
      "score": 91
    },
    "highlight_episode_facebook": {
      "status": "approved",
      "notes": "Highlight episode Facebook sudah sesuai",
      "score": 89
    },
    "thumbnail_yt": {
      "status": "approved",
      "notes": "Thumbnail YouTube sudah sesuai",
      "score": 93
    },
    "thumbnail_bts": {
      "status": "approved",
      "notes": "Thumbnail BTS sudah sesuai",
      "score": 91
    }
  },
  "overall_notes": "Semua materi sudah sesuai standar"
}
```

**Flow:**
- ✅ QC video BTS
- ✅ QC iklan episode TV
- ✅ QC highlight episode IG
- ✅ QC highlight episode TV
- ✅ QC highlight episode Facebook
- ✅ QC thumbnail YouTube
- ✅ QC thumbnail BTS
- ✅ Update `qc_checklist` dengan hasil QC

**Controller:** `QualityControlController::qcContent()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 666-738)

---

#### **3.12. Quality Control: Selesaikan Pekerjaan (Approve/Reject)**

**Endpoint:** `POST /api/live-tv/quality-control/works/{id}/finalize`

**Request Body:**
```json
{
  "action": "approve", // atau "reject"
  "notes": "QC notes"
}
```

**Flow:**

**Jika APPROVE:**
- ✅ Status: `approved`
- ✅ Auto-create `BroadcastingWork`
- ✅ Notifikasi ke Broadcasting: `broadcasting_work_assigned`
- ✅ Notifikasi ke Promosi: `qc_approved_promosi_notification`

**Jika REJECT:**
- ✅ Status: `revision_needed`
- ✅ Deteksi source file:
  - Jika ada `design_grafis_file_locations` → Notifikasi ke **Design Grafis**
  - Jika ada `editor_promosi_file_locations` → Notifikasi ke **Editor Promosi**
- ✅ Notifikasi: `qc_rejected_revision_needed`

**Controller:** `QualityControlController::finalize()`

**File:** `app/Http/Controllers/Api/QualityControlController.php` (line 744-887)

**Perbaikan:** ✅ **SUDAH DILAKUKAN** - QC reject sekarang kembali ke role yang sesuai berdasarkan source file

---

### **FLOW 4: Broadcasting - Upload ke YouTube & Website**

**Status:** ✅ **LENGKAP & AMAN**

#### **4.1. Broadcasting: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `broadcasting_work_assigned` - QC approved, siap untuk broadcasting

**Endpoint:** `GET /api/notifications`

---

#### **4.2. Broadcasting: Terima File Materi dari QC**

**Status:** ✅ **AUTO-RECEIVE**

File materi dari QC otomatis tersimpan di `BroadcastingWork` saat QC approve:
- ✅ `video_file_path` - File video dari QC

**Controller:** `QualityControlController::finalize()` (line 788-797)

---

#### **4.3. Broadcasting: Terima Thumbnail dari Design Grafis**

**Status:** ✅ **AUTO-RECEIVE**

Thumbnail dari Design Grafis otomatis tersimpan di `BroadcastingWork` saat QC approve:
- ✅ `thumbnail_path` - Thumbnail dari Design Grafis

**Controller:** `QualityControlController::finalize()` (line 794)

---

#### **4.4. Broadcasting: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/accept-work` (implied via status update)

**Flow:**
- ✅ Status: `pending` → `in_progress`
- ✅ Broadcasting siap untuk upload

---

#### **4.5. Broadcasting: Masukkan ke Jadwal Playlist**

**Endpoint:** `POST /api/live-tv/broadcasting/schedules`

**Request Body:**
```json
{
  "episode_id": 1,
  "platform": "youtube",
  "schedule_date": "2025-12-15",
  "title": "Episode 1",
  "description": "Episode description",
  "tags": ["tag1", "tag2"],
  "thumbnail_path": "path/to/thumbnail"
}
```

**Flow:**
- ✅ Create `BroadcastingSchedule` untuk playlist
- ✅ Status: `pending`

**Controller:** `BroadcastingController::store()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 64-121)

---

#### **4.6. Broadcasting: Upload ke YouTube**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-youtube`

**Request Body:**
```json
{
  "seo_title": "Episode 1 - Kasih Tuhan | Hope Channel Indonesia",
  "seo_description": "Episode tentang kasih Tuhan yang sempurna...",
  "seo_tags": ["kasih", "tuhan", "hope channel"],
  "youtube_category": "Education",
  "youtube_privacy": "public",
  "thumbnail_url": "https://storage/thumbnail.jpg"
}
```

**Flow:**
- ✅ Upload video ke YouTube dengan metadata SEO
- ✅ Include thumbnail, deskripsi, tag, judul sesuai SEO
- ✅ Update `BroadcastingWork` dengan YouTube metadata

**Controller:** `BroadcastingController::uploadYouTube()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 400-500)

---

#### **4.7. Broadcasting: Upload ke Website**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/upload-website`

**Request Body:**
```json
{
  "website_url": "https://hopechannel.id/programs/episode-1",
  "metadata": {
    "title": "Episode 1",
    "description": "Episode description"
  }
}
```

**Flow:**
- ✅ Upload video ke website
- ✅ Update episode dengan `website_url`

**Controller:** `BroadcastingController::uploadWebsite()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 506-570)

---

#### **4.8. Broadcasting: Input Link YouTube ke Sistem**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/input-youtube-link`

**Request Body:**
```json
{
  "youtube_url": "https://www.youtube.com/watch?v=xxx"
}
```

**Flow:**
- ✅ Update episode dengan `youtube_url`
- ✅ Update `BroadcastingWork` dengan YouTube link

**Controller:** `BroadcastingController::inputYouTubeLink()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 576-640)

---

#### **4.9. Broadcasting: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/complete`

**Flow:**
- ✅ Status: `completed`
- ✅ Notifikasi ke Promosi: `broadcasting_completed_promosi_ready`

**Controller:** `BroadcastingController::complete()`

**File:** `app/Http/Controllers/Api/BroadcastingController.php` (line 646-710)

---

### **FLOW 5: Promosi - Share ke Social Media**

**Status:** ✅ **LENGKAP & AMAN**

#### **5.1. Promosi: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `qc_approved_promosi_notification` - QC approved, siap untuk promosi
- ✅ `broadcasting_completed_promosi_ready` - Broadcasting selesai, link YouTube dan website tersedia

**Endpoint:** `GET /api/notifications`

---

#### **5.2. Promosi: Terima Link YouTube**

**Endpoint:** `POST /api/live-tv/promosi/episodes/{id}/receive-links`

**Request Body:**
```json
{
  "youtube_url": "https://www.youtube.com/watch?v=xxx",
  "website_url": "https://hopechannel.id/programs/episode-1"
}
```

**Flow:**
- ✅ Terima link YouTube dan website dari Broadcasting
- ✅ Update episode dengan `youtube_url` dan `website_url`

**Controller:** `PromosiController::receiveLinks()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 917-960)

---

#### **5.3. Promosi: Terima Link Website**

**Status:** ✅ **SAME AS ABOVE**

Link website diterima bersamaan dengan link YouTube di endpoint `receiveLinks()`.

---

#### **5.4. Promosi: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/promosi/works/{id}/accept-promotion-work`

**Flow:**
- ✅ Status: `planning` / `pending` → `in_progress`
- ✅ Promosi siap untuk share ke social media

**Controller:** `PromosiController::acceptPromotionWork()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 966-1004)

---

#### **5.5. Promosi: Share Link Website ke Facebook (dengan Bukti)**

**Endpoint:** `POST /api/live-tv/promosi/episodes/{id}/share-facebook`

**Request Body:**
```json
{
  "website_url": "https://hopechannel.id/programs/episode-1",
  "proof_file": "<file>", // JPG, JPEG, PNG, max 5MB
  "post_url": "https://facebook.com/posts/xxx",
  "notes": "Share notes"
}
```

**Flow:**
- ✅ Upload proof file (screenshot bukti share)
- ✅ File disimpan ke storage: `promosi/facebook_proofs/`
- ✅ Alamat file tersimpan di `promosi_social_shares` (JSON array)

**Controller:** `PromosiController::shareFacebook()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 1010-1077)

---

#### **5.6. Promosi: Buat Video Highlight untuk Story IG (dengan Bukti)**

**Endpoint:** `POST /api/live-tv/promosi/episodes/{id}/create-ig-story-highlight`

**Request Body:**
```json
{
  "video_file": "<file>", // MP4, MOV, max 100MB
  "proof_file": "<file>", // JPG, JPEG, PNG, max 5MB
  "story_url": "https://instagram.com/stories/xxx",
  "notes": "Story notes"
}
```

**Flow:**
- ✅ Upload video highlight untuk Story IG
- ✅ Upload proof file (screenshot bukti upload)
- ✅ File disimpan ke storage: `promosi/ig_story_highlights/` dan `promosi/ig_story_proofs/`
- ✅ Alamat file tersimpan di `promosi_ig_story_urls` (JSON array)

**Controller:** `PromosiController::createIGStoryHighlight()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 1083-1155)

---

#### **5.7. Promosi: Buat Video Highlight untuk Reels Facebook (dengan Bukti)**

**Endpoint:** `POST /api/live-tv/promosi/episodes/{id}/create-fb-reels-highlight`

**Request Body:**
```json
{
  "video_file": "<file>", // MP4, MOV, max 100MB
  "proof_file": "<file>", // JPG, JPEG, PNG, max 5MB
  "reels_url": "https://facebook.com/reels/xxx",
  "notes": "Reels notes"
}
```

**Flow:**
- ✅ Upload video highlight untuk Reels Facebook
- ✅ Upload proof file (screenshot bukti upload)
- ✅ File disimpan ke storage: `promosi/fb_reels_highlights/` dan `promosi/fb_reels_proofs/`
- ✅ Alamat file tersimpan di `promosi_fb_reels_urls` (JSON array)

**Controller:** `PromosiController::createFBReelsHighlight()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 1161-1233)

---

#### **5.8. Promosi: Share ke Grup Promosi WA (dengan Bukti)**

**Endpoint:** `POST /api/live-tv/promosi/episodes/{id}/share-wa-group`

**Request Body:**
```json
{
  "group_name": "Grup Promosi HCI",
  "proof_file": "<file>", // JPG, JPEG, PNG, max 5MB
  "message": "Link episode baru: https://hopechannel.id/programs/episode-1",
  "notes": "Share notes"
}
```

**Flow:**
- ✅ Upload proof file (screenshot bukti share)
- ✅ File disimpan ke storage: `promosi/wa_group_proofs/`
- ✅ Alamat file tersimpan di `promosi_wa_group_shares` (JSON array)

**Controller:** `PromosiController::shareWAGroup()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 1239-1311)

---

#### **5.9. Promosi: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/promosi/works/{id}/complete-promotion-work`

**Flow:**
- ✅ Status: `completed`
- ✅ Notifikasi ke Producer: `promotion_work_completed`

**Controller:** `PromosiController::completePromotionWork()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 1317-1350)

---

## 🔄 FLOW DIAGRAM

```
Promosi Complete Work
│
├─ Auto-create Design Grafis Work (Thumbnail YouTube & BTS)
│
├─ FLOW 1: Design Grafis
│  ├─ Terima notifikasi
│  ├─ Terima lokasi file dari Produksi
│  ├─ Terima lokasi foto talent dari Promosi
│  ├─ Terima pekerjaan
│  ├─ Buat thumbnail YouTube
│  ├─ Buat thumbnail BTS
│  └─ Ajukan ke QC
│
└─ FLOW 2: Editor Promosi
   ├─ Terima notifikasi
   ├─ Terima lokasi file dari Editor (main editor)
   ├─ Terima lokasi file dari BTS (Promosi)
   ├─ Terima pekerjaan
   ├─ Edit video BTS
   ├─ Edit iklan episode TV
   ├─ Buat highlight episode IG
   ├─ Buat highlight episode TV
   ├─ Buat highlight episode Facebook
   └─ Ajukan ke QC
│
└─ FLOW 3: Quality Control
   ├─ Terima notifikasi
   ├─ Terima lokasi file dari Editor Promosi
   ├─ Terima lokasi file dari Design Grafis
   ├─ Terima pekerjaan
   ├─ QC video BTS
   ├─ QC iklan episode TV
   ├─ QC highlight episode IG
   ├─ QC highlight episode TV
   ├─ QC highlight episode Facebook
   ├─ QC thumbnail YouTube
   ├─ QC thumbnail BTS
   ├─ Selesaikan pekerjaan
   │
   ├─ Jika DITOLAK (No):
   │  ├─ Jika ada file dari Design Grafis → Kembali ke Design Grafis
   │  └─ Jika ada file dari Editor Promosi → Kembali ke Editor Promosi
   │
   └─ Jika DITERIMA (Yes):
      ├─ FLOW 4: Broadcasting
      │  ├─ Terima notifikasi
      │  ├─ Terima file materi dari QC (auto)
      │  ├─ Terima thumbnail dari Design Grafis (auto)
      │  ├─ Terima pekerjaan
      │  ├─ Masukkan ke jadwal playlist
      │  ├─ Upload ke YouTube (thumbnail, deskripsi, tag, judul sesuai SEO)
      │  ├─ Upload ke website
      │  ├─ Input link YouTube ke sistem
      │  └─ Selesaikan pekerjaan
      │
      └─ FLOW 5: Promosi
         ├─ Terima notifikasi (dari QC approve & Broadcasting complete)
         ├─ Terima link YouTube
         ├─ Terima link website
         ├─ Terima pekerjaan
         ├─ Share link website ke Facebook (dengan bukti)
         ├─ Buat video highlight untuk Story IG (dengan bukti)
         ├─ Buat video highlight untuk Reels Facebook (dengan bukti)
         ├─ Share ke grup promosi WA (dengan bukti)
         └─ Selesaikan pekerjaan
```

---

## 🔒 KEAMANAN

### ✅ Role Validation
- ✅ Design Grafis: `if ($user->role !== 'Design Grafis')`
- ✅ Editor Promosi: `if ($user->role !== 'Editor Promosi')`
- ✅ Quality Control: `if ($user->role !== 'Quality Control')`
- ✅ Broadcasting: `if ($user->role !== 'Broadcasting')`
- ✅ Promosi: `if ($user->role !== 'Promosi')`

### ✅ Authorization
- ✅ User hanya bisa mengakses work yang dibuat oleh mereka sendiri
- ✅ Producer bisa mengakses semua work untuk review

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

## ✅ PERBAIKAN YANG SUDAH DILAKUKAN

### **1. ✅ Auto-create Design Grafis Work setelah Promosi Complete Work**

**File:** `app/Http/Controllers/Api/PromosiController.php`

**Status:** ✅ **SUDAH DITAMBAHKAN**

**Perubahan:**
- ✅ Auto-create `DesignGrafisWork` untuk thumbnail YouTube dan BTS setelah Promosi complete work
- ✅ Notifikasi ke Design Grafis users: `design_grafis_work_created`
- ✅ Cek apakah sudah ada Design Grafis work untuk episode ini sebelum create

**Line:** 829-903

---

### **2. ✅ QC Reject Flow - Kembali ke Role yang Sesuai**

**File:** `app/Http/Controllers/Api/QualityControlController.php`

**Status:** ✅ **SUDAH DIPERBAIKI**

**Perubahan:**
- ✅ QC reject sekarang mendeteksi source file:
  - Jika ada `design_grafis_file_locations` → Notifikasi ke **Design Grafis**
  - Jika ada `editor_promosi_file_locations` → Notifikasi ke **Editor Promosi**
- ✅ Notifikasi dikirim ke role yang sesuai berdasarkan source file

**Line:** 847-871

---

### **3. ✅ QC Approve Flow - Notifikasi ke Broadcasting dan Promosi**

**File:** `app/Http/Controllers/Api/QualityControlController.php`

**Status:** ✅ **SUDAH ADA**

**Perubahan:**
- ✅ Auto-create `BroadcastingWork` saat QC approve
- ✅ Notifikasi ke Broadcasting: `broadcasting_work_assigned`
- ✅ Notifikasi ke Promosi: `qc_approved_promosi_notification`

**Line:** 778-845

---

## 📋 DAFTAR ENDPOINT

### **Design Grafis Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Get Shared Files | `/api/live-tv/design-grafis/shared-files?episode_id={id}&source_role={role}` | GET | ✅ |
| Accept Work | `/api/live-tv/design-grafis/works/{id}/update` | POST | ✅ |
| Upload Files | `/api/live-tv/design-grafis/works/{id}/upload-files` | POST | ✅ |
| Submit to QC | `/api/live-tv/design-grafis/works/{id}/submit-to-qc` | POST | ✅ |

### **Editor Promosi Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Get Source Files | `/api/live-tv/editor-promosi/source-files?episode_id={id}&source_role={role}` | GET | ✅ |
| Accept Work | `/api/live-tv/editor-promosi/works/{id}/update` | POST | ✅ |
| Upload Files | `/api/live-tv/editor-promosi/works/{id}/upload` | POST | ✅ |
| Submit to QC | `/api/live-tv/editor-promosi/works/{id}/submit-to-qc` | POST | ✅ |

### **Quality Control Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Receive Editor Promosi Files | `/api/live-tv/quality-control/works/{id}/receive-editor-promosi-files` | POST | ✅ |
| Receive Design Grafis Files | `/api/live-tv/quality-control/works/{id}/receive-design-grafis-files` | POST | ✅ |
| Accept Work | `/api/live-tv/quality-control/works/{id}/accept-work` | POST | ✅ |
| QC Content | `/api/live-tv/quality-control/works/{id}/qc-content` | POST | ✅ |
| Finalize (Approve/Reject) | `/api/live-tv/quality-control/works/{id}/finalize` | POST | ✅ |

### **Broadcasting Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Accept Work | `/api/live-tv/broadcasting/works/{id}/accept-work` | POST | ✅ |
| Schedule Playlist | `/api/live-tv/broadcasting/schedules` | POST | ✅ |
| Upload YouTube | `/api/live-tv/broadcasting/works/{id}/upload-youtube` | POST | ✅ |
| Upload Website | `/api/live-tv/broadcasting/works/{id}/upload-website` | POST | ✅ |
| Input YouTube Link | `/api/live-tv/broadcasting/works/{id}/input-youtube-link` | POST | ✅ |
| Complete Work | `/api/live-tv/broadcasting/works/{id}/complete` | POST | ✅ |

### **Promosi Endpoints (Setelah Broadcasting):**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Receive Links | `/api/live-tv/promosi/episodes/{id}/receive-links` | POST | ✅ |
| Accept Promotion Work | `/api/live-tv/promosi/works/{id}/accept-promotion-work` | POST | ✅ |
| Share Facebook | `/api/live-tv/promosi/episodes/{id}/share-facebook` | POST | ✅ |
| Create IG Story Highlight | `/api/live-tv/promosi/episodes/{id}/create-ig-story-highlight` | POST | ✅ |
| Create FB Reels Highlight | `/api/live-tv/promosi/episodes/{id}/create-fb-reels-highlight` | POST | ✅ |
| Share WA Group | `/api/live-tv/promosi/episodes/{id}/share-wa-group` | POST | ✅ |
| Complete Promotion Work | `/api/live-tv/promosi/works/{id}/complete-promotion-work` | POST | ✅ |

---

## ✅ KESIMPULAN

Semua flow yang diminta sudah **LENGKAP** dan **AMAN**:

1. ✅ **Auto-create Design Grafis work** setelah Promosi selesai
2. ✅ **Flow Design Grafis** → QC → Broadcasting → Promosi
3. ✅ **Flow Editor Promosi** → QC → Broadcasting & Promosi
4. ✅ **QC reject flow** kembali ke role yang sesuai (Design Grafis atau Editor Promosi)
5. ✅ **QC approve flow** mengirim notifikasi ke Broadcasting dan Promosi
6. ✅ **Broadcasting flow** upload ke YouTube dan website
7. ✅ **Promosi flow** share ke social media dengan bukti

Semua endpoint sudah tersedia dan aman dengan validasi role, authorization, dan file upload security.

