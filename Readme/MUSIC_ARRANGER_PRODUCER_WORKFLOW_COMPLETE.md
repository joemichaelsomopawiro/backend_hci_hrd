# 🎵 Music Arranger & Producer Workflow - Complete

Dokumentasi lengkap workflow yang sudah disesuaikan sesuai requirement.

---

## 📋 Workflow Lengkap

### **TAHAP 1: Music Arranger - Ajukan Lagu & Penyanyi**

```
Music Arranger:
1. Pilih Lagu (dari database atau input manual)
2. Pilih Penyanyi (opsional - dari database atau input manual)
3. Ajukan ke Producer (TANPA arrangement file)
```

**Endpoint:**
- `POST /api/live-tv/roles/music-arranger/arrangements` - Create arrangement (tanpa file)
- `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit-song-proposal` - Submit song proposal

**Request Body:**
```json
{
  "episode_id": 1,
  "song_id": 5,              // Optional: pilih dari database
  "song_title": "Amazing Grace", // Required jika song_id tidak ada
  "singer_id": 10,           // Optional: pilih dari database
  "singer_name": "John Doe", // Optional
  "arrangement_notes": "Lagu untuk episode ini"
}
```

**Status:** `song_proposal` (tanpa file arrangement)

**Notifikasi ke Producer:** `song_proposal_submitted`

---

### **TAHAP 2: Producer - Review Lagu & Penyanyi**

```
Producer:
1. Terima notifikasi
2. Terima atau tidak usulan lagu & penyanyi
3. Dapat mengganti usulan dari Music Arranger
4. Selesai pekerjaan
```

#### **2.1 Terima Notifikasi**

**Endpoint:**
- `GET /api/live-tv/producer/approvals` - Melihat semua pending approvals

**Response:**
```json
{
  "success": true,
  "data": {
    "song_proposals": [
      {
        "id": 1,
        "episode_id": 1,
        "song_title": "Amazing Grace",
        "singer_name": "John Doe",
        "status": "song_proposal",
        "created_by": 2
      }
    ]
  }
}
```

#### **2.2 Terima atau Tidak Usulan**

**Approve Song Proposal:**
- `POST /api/live-tv/producer/approvals/{id}/approve`
- Request: `{"type": "song_proposal", "notes": "Lagu approved"}`
- Status: `song_proposal` → `song_approved`
- Notifikasi ke Music Arranger: `song_proposal_approved`

**Reject Song Proposal:**
- `POST /api/live-tv/producer/approvals/{id}/reject`
- Request: `{"type": "song_proposal", "reason": "Lagu sudah pernah digunakan"}`
- Status: `song_proposal` → `song_rejected`
- Notifikasi ke Music Arranger: `song_proposal_rejected`
- Notifikasi ke Sound Engineers: `song_proposal_rejected_help_needed`

#### **2.3 Ganti Usulan dari Music Arranger**

**Endpoint:**
- `PUT /api/live-tv/producer/arrangements/{id}/edit-song-singer`

**Request Body:**
```json
{
  "song_title": "New Song Title",
  "singer_name": "New Singer Name",
  "song_id": 6,
  "singer_id": 11,
  "modification_notes": "Perlu ganti karena lagu sebelumnya sudah digunakan"
}
```

**Status:** Tetap `song_proposal` (belum approve)
**Notifikasi ke Music Arranger:** `arrangement_modified_by_producer`

#### **2.4 Selesai Pekerjaan**

Setelah approve/reject/edit, pekerjaan Producer selesai otomatis.

---

### **TAHAP 3A: Music Arranger - Arrange Lagu (Jika APPROVE)**

```
Music Arranger:
1. Terima Notifikasi (song approved)
2. Terima Pekerjaan
3. Arr Lagu (arrange lagu, upload file)
4. Selesaikan Pekerjaan
```

#### **3A.1 Terima Notifikasi**

Music Arranger menerima notifikasi `song_proposal_approved`.

#### **3A.2 Terima Pekerjaan**

**Endpoint:**
- `POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work`

**Flow:**
- Status: `song_approved` → `arrangement_in_progress`
- Music Arranger siap untuk arrange lagu

#### **3A.3 Arr Lagu**

**Endpoint:**
- `PUT /api/live-tv/roles/music-arranger/arrangements/{id}` - Update arrangement dengan upload file

**Request Body:**
```json
{
  "file": "<file>", // Upload arrangement file (MP3/WAV/MIDI)
  "arrangement_notes": "Arrangement notes"
}
```

**Status:** `arrangement_in_progress` (setelah upload file)

#### **3A.4 Selesaikan Pekerjaan**

**Endpoint:**
- `POST /api/live-tv/roles/music-arranger/arrangements/{id}/complete-work`

**Request Body:**
```json
{
  "completion_notes": "Arrangement selesai"
}
```

**Flow:**
- Status: `arrangement_in_progress` → `arrangement_submitted`
- Notifikasi ke Producer: `music_arrangement_completed`
- Producer bisa review arrangement file

---

### **TAHAP 3B: Music Arranger - Arrange Lagu (Jika TIDAK APPROVE)**

```
Music Arranger:
1. Terima Notifikasi (song rejected)
2. Terima Pekerjaan
3. Arr Lagu (arrange lagu, upload file)
4. Selesaikan Pekerjaan
```

**Flow sama dengan 3A**, tapi Music Arranger bisa:
- Revisi song/singer sesuai feedback
- Upload arrangement file
- Submit untuk review ulang

---

### **TAHAP 3C: Sound Engineer - Bantu Perbaikan (Jika TIDAK APPROVE)**

```
Sound Engineer:
1. Terima Notifikasi (song proposal rejected)
2. Bantu Perbaikan
3. Selesaikan Pekerjaan
```

#### **3C.1 Terima Notifikasi**

Sound Engineer menerima notifikasi `song_proposal_rejected_help_needed`.

#### **3C.2 Bantu Perbaikan**

**Endpoint:**
- `GET /api/live-tv/roles/sound-engineer/rejected-song-proposals` - List song proposals yang rejected
- `POST /api/live-tv/roles/sound-engineer/song-proposals/{arrangementId}/help-fix` - Bantu perbaikan

**Request Body:**
```json
{
  "help_notes": "Saran lagu yang lebih sesuai",
  "suggested_song_title": "New Song Title",
  "suggested_singer_name": "New Singer Name",
  "song_id": 6,
  "singer_id": 11
}
```

**Flow:**
- Sound Engineer memberikan saran perbaikan
- Status: `song_rejected` → `song_proposal` (reset untuk resubmission)
- Notifikasi ke Music Arranger: `sound_engineer_helping_song_proposal`
- Notifikasi ke Producer: `sound_engineer_helping_song_proposal_producer`

#### **3C.3 Selesai Pekerjaan**

Setelah memberikan help, pekerjaan Sound Engineer selesai otomatis.

---

### **TAHAP 4: Producer - Review Arrangement File**

Setelah Music Arranger submit arrangement file:

**Approve Arrangement File:**
- `POST /api/live-tv/producer/approvals/{id}/approve`
- Request: `{"type": "music_arrangement", "notes": "Arrangement approved"}`
- Status: `arrangement_submitted` → `arrangement_approved`
- Workflow lanjut ke Sound Engineer Recording

**Reject Arrangement File:**
- `POST /api/live-tv/producer/approvals/{id}/reject`
- Request: `{"type": "music_arrangement", "reason": "Perlu perbaikan"}`
- Status: `arrangement_submitted` → `arrangement_rejected`
- Music Arranger bisa revisi atau Sound Engineer bisa bantu

---

## 🔄 Complete Status Flow

```
1. Music Arranger:
   song_proposal (ajukan lagu & penyanyi)
   ↓
2. Producer:
   ├─ Approve → song_approved
   │   ↓
   │   Music Arranger:
   │   arrangement_in_progress (arrange lagu)
   │   ↓
   │   arrangement_submitted (submit file)
   │   ↓
   │   Producer:
   │   arrangement_approved (approve file)
   │
   └─ Reject → song_rejected
       ↓
       Music Arranger:
       arrangement_in_progress (revisi & arrange)
       ↓
       arrangement_submitted
       
       OR
       
       Sound Engineer:
       Bantu perbaikan → song_proposal (reset)
       ↓
       Music Arranger:
       arrangement_in_progress
```

---

## 📊 Status Enum

| Status | Deskripsi | Action Available |
|--------|-----------|------------------|
| `song_proposal` | Ajukan lagu & penyanyi (tanpa file) | Producer: Approve/Reject/Edit |
| `song_approved` | Lagu & penyanyi approved | Music Arranger: Accept Work |
| `song_rejected` | Lagu & penyanyi rejected | Music Arranger: Revisi, Sound Engineer: Bantu |
| `arrangement_in_progress` | Sedang arrange lagu | Music Arranger: Upload file, Complete Work |
| `arrangement_submitted` | Arrangement file submitted | Producer: Approve/Reject |
| `arrangement_approved` | Arrangement file approved | Workflow lanjut ke Sound Engineer |
| `arrangement_rejected` | Arrangement file rejected | Music Arranger: Revisi, Sound Engineer: Bantu |

---

## 📋 Endpoints Summary

### **Music Arranger:**

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/arrangements` | POST | Create arrangement (ajukan lagu & penyanyi) |
| `/arrangements/{id}/submit-song-proposal` | POST | Submit song proposal |
| `/arrangements/{id}/accept-work` | POST | Terima pekerjaan setelah song approved/rejected |
| `/arrangements/{id}` | PUT | Update arrangement (upload file) |
| `/arrangements/{id}/complete-work` | POST | Selesaikan pekerjaan (auto-submit) |
| `/arrangements/{id}/submit` | POST | Submit arrangement file |
| `/songs` | GET | List available songs |
| `/singers` | GET | List available singers |

### **Producer:**

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/approvals` | GET | List pending approvals (termasuk song_proposals) |
| `/approvals/{id}/approve` | POST | Approve song proposal atau arrangement file |
| `/approvals/{id}/reject` | POST | Reject song proposal atau arrangement file |
| `/arrangements/{id}/edit-song-singer` | PUT | Edit song/singer sebelum approve |

### **Sound Engineer:**

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/rejected-song-proposals` | GET | List song proposals yang rejected |
| `/song-proposals/{id}/help-fix` | POST | Bantu perbaikan song proposal |

---

## ✅ Checklist Implementasi

- ✅ Migration untuk update status enum
- ✅ MusicArrangerController: Create dengan status song_proposal jika tanpa file
- ✅ MusicArrangerController: submitSongProposal() method
- ✅ MusicArrangerController: acceptWork() method
- ✅ MusicArrangerController: completeWork() method
- ✅ ProducerController: Handle song_proposal approval/rejection
- ✅ ProducerController: Edit song/singer untuk song_proposal
- ✅ SoundEngineerController: getRejectedSongProposals() method
- ✅ SoundEngineerController: helpFixSongProposal() method
- ✅ Routes untuk semua endpoint baru
- ✅ Notifikasi untuk semua flow

---

## 🎯 Kesimpulan

**Status:** ✅ **100% COMPLETE**

Semua workflow sudah disesuaikan sesuai requirement:
1. ✅ Music Arranger ajukan lagu & penyanyi dulu (tanpa file)
2. ✅ Producer approve/reject/edit lagu & penyanyi
3. ✅ Music Arranger arrange lagu setelah song approved/rejected
4. ✅ Sound Engineer bantu perbaikan setelah song rejected
5. ✅ Producer review arrangement file setelah Music Arranger selesai

**Workflow sudah lengkap dan terintegrasi!**

---

**Last Updated:** December 10, 2025

