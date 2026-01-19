# Flow Broadcasting → Promosi Sharing

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Broadcasting complete work → Promosi sharing tasks dengan bukti upload.

---

## 🔄 WORKFLOW LENGKAP

```
Broadcasting Complete Work
    ↓
Auto-Create PromotionWork:
    ├─► share_facebook ✅
    └─► share_wa_group ✅
    ↓
Update Story IG & Reels Facebook dengan YouTube & Website URL ✅
    ↓
Notify Promosi dengan YouTube & Website URL ✅
    ↓
Promosi:
    1. Terima Notifikasi ✅
    2. Terima Link YouTube ✅
    3. Terima Link Website ✅
    4. Terima Pekerjaan ✅
    5. Share ke Facebook + Bukti ✅
    6. Upload Story IG + Bukti ✅
    7. Upload Reels Facebook + Bukti ✅
    8. Share ke WA Group + Bukti ✅
```

---

## 📋 DETAIL WORKFLOW

### **1. BROADCASTING - SELESAI PEKERJAAN**

#### **1.1. Broadcasting - Proses Pekerjaan**
**Status:** ✅ **SUDAH ADA**

Semua endpoint sudah ada:
- ✅ Masukan ke Jadwal Playlist
- ✅ Upload di YouTube (dengan SEO: thumbnail, deskripsi, tag, judul)
- ✅ Upload ke Website
- ✅ Input link YT ke sistem

---

#### **1.2. Broadcasting - Selesai Pekerjaan**
**Endpoint:** `POST /api/live-tv/broadcasting/works/{id}/complete-work`

**Status:** ✅ **SUDAH DIPERBAIKI** (Auto-create PromotionWork & Notify Promosi)

**Kode:** `BroadcastingController::completeWork()` (Line 801-960+)

**Fitur:**
- ✅ Validasi YouTube atau Website URL harus sudah diisi
- ✅ Update status menjadi `published`
- ✅ Set published_time
- ✅ **Notifikasi ke Manager Program** ✅
- ✅ **Auto-create PromotionWork untuk sharing** ✅:
  - `share_facebook` - Share link website ke Facebook
  - `share_wa_group` - Share ke grup Promosi WA
- ✅ **Update Story IG & Reels Facebook** dengan YouTube & Website URL ✅
- ✅ **Notifikasi ke Promosi** dengan YouTube & Website URL ✅

**Auto-Create PromotionWork:**
```php
// Create PromotionWork untuk Share Facebook
$shareFacebookWork = PromotionWork::firstOrCreate(
    ['episode_id' => $episode->id, 'work_type' => 'share_facebook'],
    [
        'title' => "Share Link Website ke Facebook - Episode {$episode->episode_number}",
        'description' => "Share link website Episode {$episode->episode_number} ke Facebook. YouTube URL dan Website URL sudah tersedia.",
        'status' => 'planning',
        'social_media_links' => [
            'youtube_url' => $work->youtube_url,
            'website_url' => $work->website_url,
            'thumbnail_path' => $work->thumbnail_path
        ]
    ]
);

// Create PromotionWork untuk Share WA Group
$shareWAGroupWork = PromotionWork::firstOrCreate(
    ['episode_id' => $episode->id, 'work_type' => 'share_wa_group'],
    [
        'title' => "Share ke Grup Promosi WA - Episode {$episode->episode_number}",
        'description' => "Share link Episode {$episode->episode_number} ke grup Promosi WA. YouTube URL dan Website URL sudah tersedia.",
        'status' => 'planning',
        'social_media_links' => [
            'youtube_url' => $work->youtube_url,
            'website_url' => $work->website_url,
            'thumbnail_path' => $work->thumbnail_path
        ]
    ]
);
```

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
  "description": "...",
  "share_facebook_work_id": 15,
  "share_wa_group_work_id": 16
}
```

---

### **2. PROMOSI - TERIMA NOTIFIKASI**

#### **2.1. Promosi - Terima Notifikasi**
**Dipicu oleh:** Broadcasting complete work  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `broadcasting_published_promosi_sharing`

**Data yang dikirim:**
- ✅ `youtube_url`
- ✅ `website_url`
- ✅ `thumbnail_path`
- ✅ `share_facebook_work_id`
- ✅ `share_wa_group_work_id`

---

#### **2.2. Promosi - Terima Link YouTube**
**Status:** ✅ **SUDAH ADA**

**Data yang tersedia:**
- ✅ `youtube_url` - dari notifikasi Broadcasting
- ✅ `youtube_url` - juga tersimpan di `PromotionWork.social_media_links.youtube_url`

---

#### **2.3. Promosi - Terima Link Website**
**Status:** ✅ **SUDAH ADA**

**Data yang tersedia:**
- ✅ `website_url` - dari notifikasi Broadcasting
- ✅ `website_url` - juga tersimpan di `PromotionWork.social_media_links.website_url`

---

#### **2.4. Promosi - Terima Pekerjaan**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/accept-work`

**Status:** ✅ **SUDAH ADA**

**Kode:** `PromosiController::acceptWork()` (Line 233-281)

**Fitur:**
- ✅ Validasi status harus `planning`
- ✅ Update status menjadi `shooting` (atau sesuai kebutuhan)
- ✅ Assign work ke user
- ✅ **Notifikasi ke Producer** ✅

**Catatan:** PromotionWork untuk `share_facebook` dan `share_wa_group` sudah auto-create dengan status `planning` dan `social_media_links` sudah berisi YouTube & Website URL.

---

### **3. PROMOSI - SHARING TASKS**

#### **3.1. Promosi - Share Link Website ke Facebook**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/share-facebook`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::shareFacebook()` (Line 913-984)

**Fitur:**
- ✅ Validasi work_type harus `share_facebook`
- ✅ Upload bukti screenshot/posting (jpg, jpeg, png - max 10MB)
- ✅ Input Facebook post URL (optional)
- ✅ Input notes (optional)
- ✅ Simpan bukti ke sistem di `social_media_links.facebook_share`
- ✅ Update status menjadi `published`

**Request Body:**
```json
{
  "proof_file": "<file>",
  "facebook_post_url": "https://facebook.com/...",
  "notes": "Posted at 10:00 AM"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Facebook share completed successfully. Proof has been saved to system.",
  "data": {
    "id": 15,
    "work_type": "share_facebook",
    "status": "published",
    "social_media_links": {
      "youtube_url": "https://youtube.com/...",
      "website_url": "https://website.com/...",
      "facebook_share": {
        "proof_file_path": "promosi/sharing_proofs/15/...",
        "proof_file_url": "http://...",
        "facebook_post_url": "https://facebook.com/...",
        "shared_at": "2025-01-27 10:00:00",
        "shared_by": 5,
        "notes": "Posted at 10:00 AM"
      }
    }
  }
}
```

---

#### **3.2. Promosi - Buat Video HL untuk Story IG**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/upload-story-ig`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::uploadStoryIG()` (Line 990-1080)

**Fitur:**
- ✅ Validasi work_type harus `story_ig`
- ✅ Upload video highlight untuk Story IG (mp4, mov, avi - max 100MB)
- ✅ Upload bukti posting (jpg, jpeg, png - max 10MB)
- ✅ Input Story URL (optional)
- ✅ Input notes (optional)
- ✅ Simpan video ke `file_paths` dan bukti ke `social_media_links.story_ig`
- ✅ Update status menjadi `published`

**Request Body:**
```json
{
  "video_file": "<file>",
  "proof_file": "<file>",
  "story_url": "https://instagram.com/stories/...",
  "notes": "Posted at 10:00 AM"
}
```

---

#### **3.3. Promosi - Buat Video HL untuk Reels Facebook**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/upload-reels-facebook`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::uploadReelsFacebook()` (Line 1082-1170)

**Fitur:**
- ✅ Validasi work_type harus `reels_facebook`
- ✅ Upload video highlight untuk Reels Facebook (mp4, mov, avi - max 100MB)
- ✅ Upload bukti posting (jpg, jpeg, png - max 10MB)
- ✅ Input Reels URL (optional)
- ✅ Input notes (optional)
- ✅ Simpan video ke `file_paths` dan bukti ke `social_media_links.reels_facebook`
- ✅ Update status menjadi `published`

**Request Body:**
```json
{
  "video_file": "<file>",
  "proof_file": "<file>",
  "reels_url": "https://facebook.com/reel/...",
  "notes": "Posted at 10:00 AM"
}
```

---

#### **3.4. Promosi - Share ke Grup Promosi WA**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/share-wa-group`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Kode:** `PromosiController::shareWAGroup()` (Line 1172-1248)

**Fitur:**
- ✅ Validasi work_type harus `share_wa_group`
- ✅ Upload bukti screenshot (jpg, jpeg, png - max 10MB)
- ✅ Input group name (optional)
- ✅ Input notes (optional)
- ✅ Simpan bukti ke sistem di `social_media_links.wa_group_share`
- ✅ Update status menjadi `published`

**Request Body:**
```json
{
  "proof_file": "<file>",
  "group_name": "Grup Promosi HCI",
  "notes": "Shared at 10:00 AM"
}
```

**Response:**
```json
{
  "success": true,
  "message": "WA Group share completed successfully. Proof has been saved to system.",
  "data": {
    "id": 16,
    "work_type": "share_wa_group",
    "status": "published",
    "social_media_links": {
      "youtube_url": "https://youtube.com/...",
      "website_url": "https://website.com/...",
      "wa_group_share": {
        "proof_file_path": "promosi/sharing_proofs/16/...",
        "proof_file_url": "http://...",
        "group_name": "Grup Promosi HCI",
        "shared_at": "2025-01-27 10:00:00",
        "shared_by": 5,
        "notes": "Shared at 10:00 AM"
      }
    }
  }
}
```

---

## 📋 RINGKASAN ENDPOINT

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
| `/works` | GET | List semua PromotionWork (termasuk sharing tasks) | ✅ |
| `/works/{id}` | GET | Get PromotionWork detail | ✅ |
| `/works/{id}/accept-work` | POST | Terima pekerjaan sharing (work_type: share_facebook, share_wa_group, story_ig, reels_facebook) | ✅ |
| `/works/{id}/share-facebook` | POST | Share ke Facebook + upload bukti | ✅ |
| `/works/{id}/upload-story-ig` | POST | Upload Story IG video + upload bukti | ✅ |
| `/works/{id}/upload-reels-facebook` | POST | Upload Reels Facebook video + upload bukti | ✅ |
| `/works/{id}/share-wa-group` | POST | Share ke WA group + upload bukti | ✅ |

---

## ✅ YANG SUDAH BENAR

1. ✅ Broadcasting complete work → Auto-create PromotionWork untuk `share_facebook` dan `share_wa_group`
2. ✅ Broadcasting complete work → Update Story IG & Reels Facebook dengan YouTube & Website URL
3. ✅ Broadcasting complete work → Notify Promosi dengan YouTube & Website URL
4. ✅ Promosi terima notifikasi
5. ✅ Promosi terima link YouTube dan Website (via notifikasi dan social_media_links)
6. ✅ Promosi terima pekerjaan (accept work untuk sharing tasks)
7. ✅ Promosi share ke Facebook dengan bukti upload
8. ✅ Promosi upload Story IG dengan bukti upload
9. ✅ Promosi upload Reels Facebook dengan bukti upload
10. ✅ Promosi share ke WA group dengan bukti upload

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Broadcasting → Promosi auto-create PromotionWork sudah ada
- ✅ Broadcasting → Promosi notification dengan YouTube & Website URL sudah ada
- ✅ Promosi accept work untuk sharing tasks sudah support
- ✅ Semua sharing endpoints sudah diimplementasikan dengan bukti upload
- ✅ Semua bukti disimpan di `social_media_links` dengan struktur yang jelas

Semua endpoint sudah tersedia dan siap digunakan untuk frontend integration.

---

**Last Updated:** 2025-01-27
