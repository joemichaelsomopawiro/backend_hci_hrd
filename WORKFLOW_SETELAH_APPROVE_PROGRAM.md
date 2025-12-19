# 🔄 WORKFLOW PROGRAM MUSIK SETELAH DISTRIBUTION MANAGER APPROVE

**Tanggal:** 13 Desember 2025  
**Status:** ✅ **DOKUMENTASI LENGKAP**

---

## 📋 OVERVIEW

Setelah **Distribution Manager** approve program dari **Manager Program**, workflow program musik akan berlanjut ke fase produksi. Dokumentasi ini menjelaskan langkah-langkah lengkap setelah approval.

---

## ✅ YANG TERJADI SAAT DISTRIBUTION MANAGER APPROVE PROGRAM

### **1. Status Program Berubah**
- **Status:** `pending_approval` → `approved`
- **Approved By:** ID Distribution Manager
- **Approved At:** Timestamp approval

### **2. Semua Episodes Di-Update**
- **Status Episodes:** `draft` → `approved_for_production`
- **Workflow State:** `episode_generated`
- **Assigned To Role:** `manager_program`
- **Assigned To User:** Manager Program ID

### **3. Notifikasi Dikirim**
- Notifikasi ke **Manager Program** bahwa program sudah di-approve
- Notifikasi ke **Production Team Members** (jika sudah di-assign)

---

## 🎯 WORKFLOW SETELAH APPROVAL

### **PHASE 1: MANAGER PROGRAM - ASSIGN TEAM & PREPARE EPISODE**

Setelah program di-approve, Manager Program perlu:

#### **1.1. Assign Production Team ke Episode**

**Endpoint:**
```http
POST /api/live-tv/manager-program/episodes/{episode_id}/assign-team
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "production_team_id": 1,
  "notes": "Assign team untuk episode 1"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "episode_id": 1,
    "production_team_id": 1,
    "team_assigned_at": "2025-12-13 10:00:00",
    "team_assigned_by": 1,
    "team_assignment_notes": "Assign team untuk episode 1"
  },
  "message": "Team assigned successfully"
}
```

**✅ Checklist:**
- [ ] Production Team sudah dibuat
- [ ] Production Team sudah punya semua members (kreatif, musik_arr, sound_eng, produksi, editor, art_set_design)
- [ ] Team `is_ready_for_production: true`
- [ ] Team sudah di-assign ke episode

---

### **PHASE 2: MUSIC ARRANGER - SONG PROPOSAL & ARRANGEMENT**

Setelah team di-assign, **Music Arranger** mulai bekerja:

#### **2.1. Create Arrangement (Ajukan Lagu & Penyanyi)**

**Endpoint:**
```http
POST /api/live-tv/music-arranger/arrangements
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "episode_id": 1,
  "song_title": "Lagu Test",
  "singer_name": "Penyanyi Test",
  "notes": "Proposal lagu untuk episode 1"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "episode_id": 1,
    "song_title": "Lagu Test",
    "singer_name": "Penyanyi Test",
    "status": "song_proposal",
    "created_at": "2025-12-13 10:00:00"
  },
  "message": "Arrangement created successfully"
}
```

#### **2.2. Submit Song Proposal**

**Endpoint:**
```http
POST /api/live-tv/music-arranger/arrangements/{id}/submit
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "song_proposal",
    "submitted_at": "2025-12-13 10:05:00"
  },
  "message": "Song proposal submitted successfully"
}
```

**✅ Checklist:**
- [ ] Arrangement dibuat
- [ ] Song proposal di-submit
- [ ] Notifikasi dikirim ke Producer

---

### **PHASE 3: PRODUCER - REVIEW & APPROVE SONG PROPOSAL**

**Producer** review song proposal:

#### **3.1. Approve Song Proposal**

**Endpoint:**
```http
POST /api/live-tv/producer/arrangements/{id}/approve-song
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "notes": "Lagu disetujui, siap untuk arrangement"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "song_approved",
    "approved_at": "2025-12-13 10:10:00"
  },
  "message": "Song proposal approved successfully"
}
```

**✅ Checklist:**
- [ ] Song proposal di-approve
- [ ] Notifikasi dikirim ke Music Arranger
- [ ] Status berubah ke `song_approved`

---

### **PHASE 4: MUSIC ARRANGER - ARRANGE LAGU**

Setelah song proposal di-approve, **Music Arranger** arrange lagu:

#### **4.1. Accept Work**

**Endpoint:**
```http
POST /api/live-tv/music-arranger/arrangements/{id}/accept
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "arrangement_in_progress",
    "accepted_at": "2025-12-13 10:15:00"
  },
  "message": "Work accepted successfully"
}
```

#### **4.2. Upload Arrangement File**

**Endpoint:**
```http
POST /api/live-tv/music-arranger/arrangements/{id}/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
arrangement_file: [file]
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "arrangement_file_path": "arrangements/1234567890_arrangement.mp3",
    "arrangement_file_name": "arrangement.mp3",
    "arrangement_file_size": 5242880,
    "arrangement_file_mime_type": "audio/mpeg"
  },
  "message": "Arrangement file uploaded successfully"
}
```

#### **4.3. Submit Arrangement**

**Endpoint:**
```http
POST /api/live-tv/music-arranger/arrangements/{id}/submit
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "arrangement_submitted",
    "submitted_at": "2025-12-13 10:20:00"
  },
  "message": "Arrangement submitted successfully"
}
```

**✅ Checklist:**
- [ ] Work di-accept
- [ ] Arrangement file di-upload
- [ ] Arrangement di-submit
- [ ] Notifikasi dikirim ke Producer

---

### **PHASE 5: PRODUCER - REVIEW & APPROVE ARRANGEMENT**

**Producer** review arrangement file:

#### **5.1. Approve Arrangement**

**Endpoint:**
```http
POST /api/live-tv/producer/arrangements/{id}/approve-arrangement
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "notes": "Arrangement disetujui, siap untuk recording"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "arrangement_approved",
    "approved_at": "2025-12-13 10:25:00"
  },
  "message": "Arrangement approved successfully"
}
```

**✅ Checklist:**
- [ ] Arrangement di-approve
- [ ] Notifikasi dikirim ke:
  - Music Arranger
  - Sound Engineer
  - Creative

---

### **PHASE 6: PARALLEL WORK - SOUND ENGINEER & CREATIVE**

Setelah arrangement di-approve, **Sound Engineer** dan **Creative** bekerja secara paralel:

#### **6A. SOUND ENGINEER - RECORDING**

**6A.1. Create Recording Task**

**Endpoint:**
```http
POST /api/live-tv/sound-engineer/recordings
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "episode_id": 1,
  "arrangement_id": 1,
  "scheduled_date": "2025-12-15 10:00:00",
  "notes": "Recording vokal untuk episode 1"
}
```

**6A.2. Start Recording**

**Endpoint:**
```http
POST /api/live-tv/sound-engineer/recordings/{id}/start
Authorization: Bearer {token}
```

**6A.3. Upload Recording File**

**Endpoint:**
```http
POST /api/live-tv/sound-engineer/recordings/{id}/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**6A.4. Complete Recording**

**Endpoint:**
```http
POST /api/live-tv/sound-engineer/recordings/{id}/complete
Authorization: Bearer {token}
```

---

#### **6B. CREATIVE - SCRIPT & STORYBOARD**

**6B.1. Accept Work**

**Endpoint:**
```http
POST /api/live-tv/creative/works/{id}/accept-work
Authorization: Bearer {token}
```

**6B.2. Create Creative Work**

**Endpoint:**
```http
POST /api/live-tv/creative/works
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "episode_id": 1,
  "script": "Script video clip...",
  "storyboard": "Storyboard description...",
  "shooting_date": "2025-12-16 08:00:00",
  "shooting_location": "Studio A",
  "budget_talent": 5000000,
  "notes": "Creative work untuk episode 1"
}
```

**6B.3. Submit Creative Work**

**Endpoint:**
```http
POST /api/live-tv/creative/works/{id}/submit
Authorization: Bearer {token}
```

---

### **PHASE 7: PRODUCER - REVIEW CREATIVE WORK**

**Producer** review creative work (script, storyboard, budget):

#### **7.1. Review Creative Work**

**Endpoint:**
```http
POST /api/live-tv/producer/creative-works/{id}/review
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "script_approved": true,
  "storyboard_approved": true,
  "budget_approved": true,
  "budget_review_notes": "Budget disetujui",
  "notes": "Creative work disetujui, siap untuk produksi"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "script_approved": true,
    "storyboard_approved": true,
    "budget_approved": true,
    "status": "approved"
  },
  "message": "Creative work reviewed successfully"
}
```

**✅ Checklist:**
- [ ] Script di-approve
- [ ] Storyboard di-approve
- [ ] Budget di-approve
- [ ] Notifikasi dikirim ke Creative dan Production Team

---

### **PHASE 8: PRODUCTION - SHOOTING**

Setelah creative work di-approve, **Production** mulai shooting:

#### **8.1. Accept Work**

**Endpoint:**
```http
POST /api/live-tv/production/works/{id}/accept
Authorization: Bearer {token}
```

#### **8.2. Request Equipment**

**Endpoint:**
```http
POST /api/live-tv/production/works/{id}/request-equipment
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "equipment_list": [
    {
      "name": "Kamera",
      "quantity": 2,
      "notes": "Kamera untuk shooting"
    },
    {
      "name": "Lighting",
      "quantity": 4,
      "notes": "Lighting untuk studio"
    }
  ]
}
```

#### **8.3. Complete Shooting**

**Endpoint:**
```http
POST /api/live-tv/production/works/{id}/complete
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
raw_files: [files]
shooting_notes: "Catatan shooting..."
```

---

### **PHASE 9: EDITOR - EDITING**

Setelah recording dan shooting selesai, **Editor** mulai editing:

#### **9.1. Accept Work**

**Endpoint:**
```http
POST /api/live-tv/editor/works/{id}/accept
Authorization: Bearer {token}
```

#### **9.2. Start Editing**

**Endpoint:**
```http
POST /api/live-tv/editor/works/{id}/start
Authorization: Bearer {token}
```

#### **9.3. Upload Edited File**

**Endpoint:**
```http
POST /api/live-tv/editor/works/{id}/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
```
edited_file: [file]
editing_notes: "Catatan editing..."
```

#### **9.4. Submit Editor Work**

**Endpoint:**
```http
POST /api/live-tv/editor/works/{id}/submit
Authorization: Bearer {token}
```

---

### **PHASE 10: QUALITY CONTROL - QC**

Setelah editing selesai, **Quality Control** review:

#### **10.1. Start QC**

**Endpoint:**
```http
POST /api/live-tv/qc/works/{id}/start
Authorization: Bearer {token}
```

#### **10.2. Complete QC**

**Endpoint:**
```http
POST /api/live-tv/qc/works/{id}/complete
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "qc_notes": "QC selesai, video sudah sesuai standar",
  "issues_found": []
}
```

#### **10.3. Approve QC**

**Endpoint:**
```http
POST /api/live-tv/qc/works/{id}/approve
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "notes": "Video disetujui untuk broadcasting"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "qc_approved",
    "approved_at": "2025-12-13 15:00:00"
  },
  "message": "QC approved successfully"
}
```

**✅ Checklist:**
- [ ] QC selesai
- [ ] QC di-approve
- [ ] Notifikasi dikirim ke Broadcasting

---

### **PHASE 11: BROADCASTING - UPLOAD & SCHEDULE**

Setelah QC di-approve, **Broadcasting** upload dan schedule:

#### **11.1. Accept Work**

**Endpoint:**
```http
POST /api/live-tv/roles/broadcasting/works/{id}/accept-work
Authorization: Bearer {token}
```

#### **11.2. Upload YouTube**

**Endpoint:**
```http
POST /api/live-tv/roles/broadcasting/works/{id}/upload-youtube
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "youtube_url": "https://youtube.com/watch?v=...",
  "youtube_video_id": "abc123",
  "seo_title": "Title untuk SEO",
  "seo_description": "Description untuk SEO",
  "seo_tags": "tag1, tag2, tag3"
}
```

#### **11.3. Upload Website**

**Endpoint:**
```http
POST /api/live-tv/roles/broadcasting/works/{id}/upload-website
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "website_url": "https://website.com/episode/1"
}
```

#### **11.4. Submit Schedule Options**

**Endpoint:**
```http
POST /api/live-tv/manager-program/programs/{program_id}/submit-schedule-options
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "episode_id": 1,
  "schedule_options": [
    {
      "air_date": "2025-12-20 19:00:00",
      "channel": "Hope Channel",
      "notes": "Option 1"
    },
    {
      "air_date": "2025-12-21 19:00:00",
      "channel": "Hope Channel",
      "notes": "Option 2"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "schedule_options": [...]
  },
  "message": "Schedule options submitted successfully"
}
```

**✅ Checklist:**
- [ ] Video di-upload ke YouTube
- [ ] Video di-upload ke Website
- [ ] Schedule options di-submit
- [ ] Notifikasi dikirim ke Manager Broadcasting

---

### **PHASE 12: MANAGER BROADCASTING - APPROVE SCHEDULE**

**Manager Broadcasting** (Distribution Manager) approve schedule:

#### **12.1. Approve Schedule**

**Endpoint:**
```http
POST /api/live-tv/manager-broadcasting/schedule-options/{id}/approve
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "notes": "Schedule disetujui"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "approved",
    "approved_at": "2025-12-13 16:00:00"
  },
  "message": "Schedule approved successfully"
}
```

**✅ Checklist:**
- [ ] Schedule di-approve
- [ ] Notifikasi dikirim ke Broadcasting
- [ ] Episode siap untuk publish

---

### **PHASE 13: BROADCASTING - PUBLISH**

Setelah schedule di-approve, **Broadcasting** publish:

#### **13.1. Publish Schedule**

**Endpoint:**
```http
POST /api/live-tv/roles/broadcasting/schedules/{id}/publish
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "published",
    "published_at": "2025-12-13 16:05:00"
  },
  "message": "Schedule published successfully"
}
```

**✅ Checklist:**
- [ ] Schedule di-publish
- [ ] Episode status berubah ke `aired`
- [ ] Episode siap tayang

---

## 📊 STATUS FLOW SUMMARY

```
Program:
draft → pending_approval → approved → in_production → completed

Episode:
draft → approved_for_production → in_production → post_production → ready_to_air → aired

Arrangement:
song_proposal → song_approved → arrangement_in_progress → arrangement_submitted → arrangement_approved

Recording:
draft → in_progress → completed → reviewed

Creative Work:
draft → in_progress → submitted → approved

Production:
draft → in_progress → completed

Editor:
draft → in_progress → submitted

QC:
draft → in_progress → completed → approved

Broadcasting:
draft → in_progress → youtube_uploaded → website_uploaded → schedule_submitted → schedule_approved → published
```

---

## 🔔 NOTIFIKASI FLOW

```
1. Distribution Manager Approve Program
   → Notifikasi ke Manager Program
   → Notifikasi ke Production Team Members

2. Music Arranger Submit Song Proposal
   → Notifikasi ke Producer

3. Producer Approve Song Proposal
   → Notifikasi ke Music Arranger

4. Music Arranger Submit Arrangement
   → Notifikasi ke Producer

5. Producer Approve Arrangement
   → Notifikasi ke Music Arranger
   → Notifikasi ke Sound Engineer
   → Notifikasi ke Creative

6. Sound Engineer Complete Recording
   → Notifikasi ke Producer

7. Creative Submit Creative Work
   → Notifikasi ke Producer

8. Producer Approve Creative Work
   → Notifikasi ke Creative
   → Notifikasi ke Production Team

9. Production Complete Shooting
   → Notifikasi ke Producer

10. Editor Submit Editor Work
    → Notifikasi ke QC

11. QC Approve
    → Notifikasi ke Broadcasting

12. Broadcasting Submit Schedule Options
    → Notifikasi ke Manager Broadcasting

13. Manager Broadcasting Approve Schedule
    → Notifikasi ke Broadcasting

14. Broadcasting Publish
    → Notifikasi ke Manager Program
    → Notifikasi ke Production Team
```

---

## ✅ CHECKLIST LENGKAP WORKFLOW

### **Setup Phase**
- [ ] Program dibuat oleh Manager Program
- [ ] 53 Episodes auto-generated
- [ ] Production Team dibuat
- [ ] Members ditambahkan ke team
- [ ] Team di-assign ke episode
- [ ] Program di-submit untuk approval
- [ ] Distribution Manager approve program

### **Music Phase**
- [ ] Music Arranger create arrangement
- [ ] Music Arranger submit song proposal
- [ ] Producer approve song proposal
- [ ] Music Arranger accept work
- [ ] Music Arranger upload arrangement file
- [ ] Music Arranger submit arrangement
- [ ] Producer approve arrangement

### **Parallel Phase (Sound Engineer & Creative)**
- [ ] Sound Engineer create recording task
- [ ] Sound Engineer start recording
- [ ] Sound Engineer upload recording file
- [ ] Sound Engineer complete recording
- [ ] Creative accept work
- [ ] Creative create creative work
- [ ] Creative submit creative work
- [ ] Producer review creative work
- [ ] Producer approve creative work

### **Production Phase**
- [ ] Production accept work
- [ ] Production request equipment
- [ ] Production complete shooting

### **Post-Production Phase**
- [ ] Editor accept work
- [ ] Editor start editing
- [ ] Editor upload edited file
- [ ] Editor submit editor work

### **Quality Control Phase**
- [ ] QC start QC
- [ ] QC complete QC
- [ ] QC approve

### **Broadcasting Phase**
- [ ] Broadcasting accept work
- [ ] Broadcasting upload YouTube
- [ ] Broadcasting upload website
- [ ] Broadcasting submit schedule options
- [ ] Manager Broadcasting approve schedule
- [ ] Broadcasting publish schedule
- [ ] Episode aired

---

## 📝 CATATAN PENTING

1. **Workflow bisa paralel:** Sound Engineer dan Creative bisa bekerja bersamaan setelah arrangement di-approve
2. **Workflow bisa sequential:** Production harus menunggu Creative Work di-approve
3. **Workflow bisa revisi:** Jika ada yang ditolak, kembali ke step sebelumnya
4. **Notifikasi otomatis:** Setiap perubahan status akan trigger notifikasi
5. **Deadline tracking:** Setiap role punya deadline yang harus dipenuhi

---

## 🚀 QUICK START

Setelah Distribution Manager approve program:

1. **Manager Program:**
   - Assign Production Team ke Episode
   - Monitor progress

2. **Music Arranger:**
   - Create Arrangement
   - Submit Song Proposal

3. **Producer:**
   - Review & Approve Song Proposal
   - Review & Approve Arrangement
   - Review & Approve Creative Work

4. **Sound Engineer & Creative:**
   - Bekerja paralel setelah Arrangement di-approve

5. **Production → Editor → QC → Broadcasting:**
   - Sequential workflow sampai episode aired

---

**Dokumentasi ini menjelaskan workflow lengkap setelah Distribution Manager approve program. Setiap phase harus diselesaikan sebelum lanjut ke phase berikutnya (kecuali yang bisa paralel).**


