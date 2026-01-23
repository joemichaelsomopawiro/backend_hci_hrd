# 📚 README SISTEM PROGRAM MUSIK HOPE CHANNEL
## Dokumentasi Utama Sistem Program Musik

> **Dokumentasi utama untuk sistem program musik Hope Channel yang sudah lengkap diimplementasikan sesuai workflow.**

**Versi:** 1.0.0  
**Tanggal:** {{ date('Y-m-d H:i:s') }}  
**Status:** ✅ **PRODUCTION READY**

---

## 📋 DAFTAR ISI

1. [Quick Start](#quick-start)
2. [Dokumentasi Lengkap](#dokumentasi-lengkap)
3. [Workflow Overview](#workflow-overview)
4. [File Storage System](#file-storage-system)
5. [API Endpoints](#api-endpoints)
6. [Testing Guide](#testing-guide)

---

## 🚀 QUICK START

### **1. Setup Database:**

```bash
php artisan migrate
```

### **2. Generate Episodes untuk Program:**

```bash
POST /api/live-tv/manager-program/programs/{programId}/generate-episodes
```

Sistem akan otomatis:
- Generate 52 episode per tahun
- Hitung deadline otomatis (7 hari Editor, 9 hari Creative/Production)
- Buat notifikasi deadline ke semua role terkait

### **3. Assign Team ke Episode:**

```bash
POST /api/live-tv/manager-program/episodes/{episodeId}/assign-team
```

Tim yang dibuat:
- Producer (bisa lebih dari 1)
- Music Arranger
- Creative
- Sound Engineer
- Production
- Editor

---

## 📚 DOKUMENTASI LENGKAP

### **Dokumentasi Utama:**

1. **DOKUMENTASI_FINAL_SISTEM_PROGRAM_MUSIK.md** - Dokumentasi final lengkap dengan semua endpoint
2. **DOKUMENTASI_LENGKAP_SISTEM_PROGRAM_MUSIK_VERIFIKASI.md** - Verifikasi lengkap per role
3. **RINGKASAN_IMPLEMENTASI_FILE_LINK_LENGKAP.md** - Implementasi file storage link-based

### **Dokumentasi Workflow:**

1. **FLOW_COMPLETE_MUSIC_ARRANGER_TO_CREATIVE.md** - Flow dari Music Arranger ke Creative
2. **FLOW_AFTER_PRODUCER_APPROVE_CREATIVE_WORK.md** - Flow setelah Producer approve Creative
3. **FLOW_EDITOR_QC_BROADCASTING.md** - Flow Editor ke QC Broadcasting
4. **FLOW_PROMOSI_TO_DESIGN_GRAFIS_EDITOR_PROMOSI.md** - Flow Promosi ke Design Grafis & Editor Promosi

### **Dokumentasi Testing:**

1. **GUIDE_TESTING_SISTEM_PROGRAM_MUSIK.md** - Panduan testing lengkap
2. **TESTING_WORKFLOW_SISTEM_PROGRAM_MUSIK.md** - Testing workflow

---

## 🔄 WORKFLOW OVERVIEW

### **Main Workflow:**

```
1. Program Manager
   └─ Buat Program → Buat Tim → Generate 52 Episode → Submit Jadwal

2. Broadcasting Manager
   └─ Review Jadwal → Approve/Revise

3. Music Arranger
   └─ Pilih Lagu & Penyanyi → Arr Lagu (link) → Submit ke Producer

4. Producer
   └─ Approve/Reject/Edit → QC Arrangement → Approve Creative

5. Multiple Roles Activated:
   ├─ Sound Engineer → Recording (link) → Editing (link) → QC Producer
   ├─ Creative → Script, Storyboard, Budget → Submit
   ├─ Production → Request Alat → Syuting (links) → Kembalikan Alat
   └─ Promotion → BTS Video (link) → Foto Talent (links)

6. Editor
   └─ Cek File Lengkap → Edit Video (link) → Submit ke Broadcasting Manager QC

7. Broadcasting Manager
   └─ QC Final → Approve → Broadcasting

8. Promotion Flow:
   ├─ Design Grafis → Thumbnail → QC Promosi
   ├─ Editor Promosi → Edit Promosi (links) → QC Promosi
   └─ Broadcasting → Upload YouTube → Promotion → Share Sosmed
```

---

## 📁 FILE STORAGE SYSTEM

### **Sistem Menggunakan Link (Bukan Upload Langsung):**

**Alasan:**
- Keterbatasan storage hosting (20GB)
- File disimpan di server eksternal
- Sistem hanya menyimpan link ke file

### **Model yang Mendukung `file_link`:**

| Model | Field | Tipe |
|-------|-------|------|
| `MusicArrangement` | `file_link` | text |
| `SoundEngineerRecording` | `file_link` | text |
| `SoundEngineerEditing` | `vocal_file_link`, `final_file_link` | text |
| `EditorWork` | `file_link` | text |
| `PromotionWork` | `file_links` | json (array) |
| `ProduksiWork` | `shooting_file_links` | json (array) |

### **Priority Logic:**

1. **Jika `file_link` ada, gunakan `file_link`**
2. **Jika `file_link` tidak ada, gunakan `file_path`** (backward compatibility)

---

## 🔌 API ENDPOINTS

### **Program Manager:**
- `POST /api/live-tv/manager-program/programs/{programId}/generate-episodes` - Generate 52 episode
- `POST /api/live-tv/manager-program/episodes/{episodeId}/assign-team` - Assign team
- `PUT /api/live-tv/manager-program/deadlines/{deadlineId}` - Edit deadline

### **Music Arranger:**
- `POST /api/live-tv/music-arranger/arrangements` - Pilih lagu & penyanyi
- `PUT /api/live-tv/music-arranger/arrangements/{id}` - Upload link arr lagu (`file_link`)
- `POST /api/live-tv/music-arranger/arrangements/{id}/submit` - Submit ke Producer

### **Producer:**
- `POST /api/live-tv/producer/approvals/{approvalId}/approve` - Approve
- `POST /api/live-tv/producer/approvals/{approvalId}/reject` - Reject
- `PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer` - Edit langsung

### **Sound Engineer:**
- `PUT /api/live-tv/roles/sound-engineer/recordings/{id}` - Recording vocal (`file_link`)
- `PUT /api/live-tv/sound-engineer-editing/works/{id}` - Edit vocal (`final_file_link`)
- `POST /api/live-tv/sound-engineer-editing/works/{id}/submit` - Submit ke Producer QC

### **Editor:**
- `PUT /api/live-tv/editor/works/{id}` - Edit video (`file_link`)
- `POST /api/live-tv/editor/works/{id}/check-file-completeness` - Cek kelengkapan file
- `POST /api/live-tv/editor/works/{id}/submit` - Submit ke Broadcasting Manager QC

### **Promotion:**
- `POST /api/live-tv/promosi/works/{id}/upload-bts-video` - BTS video (`file_link`)
- `POST /api/live-tv/promosi/works/{id}/upload-talent-photos` - Foto talent (`file_links` array)

**Lihat dokumentasi lengkap di:** `DOKUMENTASI_FINAL_SISTEM_PROGRAM_MUSIK.md`

---

## 🧪 TESTING GUIDE

### **1. Test Episode Generation:**

```bash
POST /api/live-tv/manager-program/programs/{programId}/generate-episodes
```

**Expected:**
- 52 episode dibuat
- Deadline Editor: 7 hari sebelum tayang
- Deadline Creative/Production: 9 hari sebelum tayang

### **2. Test File Link:**

```bash
PUT /api/live-tv/music-arranger/arrangements/{id}
{
  "file_link": "https://drive.google.com/file/d/xxx/view?usp=sharing"
}
```

**Expected:**
- `file_link` tersimpan di database
- Notifikasi ke Producer

### **3. Test Workflow Lengkap:**

Ikuti workflow dari Music Arranger → Producer → Creative → Production → Editor → Broadcasting

**Lihat panduan lengkap di:** `GUIDE_TESTING_SISTEM_PROGRAM_MUSIK.md`

---

## ✅ STATUS IMPLEMENTASI

### **✅ LENGKAP (100%):**

- [x] Semua 15 role sudah diimplementasikan
- [x] Semua workflow sudah sesuai requirement
- [x] Sistem file storage link-based sudah lengkap
- [x] Sistem otomatis (episode, deadline) sudah ada
- [x] Notification system sudah ada
- [x] Migration sudah dijalankan
- [x] Backward compatibility terjaga

---

## 📞 SUPPORT

Untuk pertanyaan atau masalah, lihat dokumentasi lengkap di folder `Readme/Program_Musik/`.

---

**Sistem Program Musik Hope Channel - Production Ready!** 🎉
