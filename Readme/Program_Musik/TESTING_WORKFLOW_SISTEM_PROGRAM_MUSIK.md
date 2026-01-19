# 🧪 TESTING WORKFLOW SISTEM PROGRAM MUSIK

**Dokumen ini berisi panduan lengkap untuk testing sistem program musik secara sistematis.**

---

## 📋 DAFTAR ISI

1. [Persiapan Testing](#persiapan-testing)
2. [Testing Phase 1: Setup Program](#testing-phase-1-setup-program)
3. [Testing Phase 2: Music Arrangement](#testing-phase-2-music-arrangement)
4. [Testing Phase 3: Recording](#testing-phase-3-recording)
5. [Testing Phase 4: Creative Work](#testing-phase-4-creative-work)
6. [Testing Phase 5: Production](#testing-phase-5-production)
7. [Testing Phase 6: Editing](#testing-phase-6-editing)
8. [Testing Phase 7: Quality Control](#testing-phase-7-quality-control)
9. [Testing Phase 8: Broadcasting](#testing-phase-8-broadcasting)
10. [Testing Keamanan & Performance](#testing-keamanan--performance)
11. [Checklist Testing Lengkap](#checklist-testing-lengkap)

---

## 🚀 PERSIAPAN TESTING

### 1. **Setup Environment**

```bash
# Pastikan backend running
cd C:\laragon\www\backend_hci_hrd
php artisan serve

# Atau jika menggunakan Laragon, pastikan service running
```

### 2. **Persiapan Data Testing**

**User Accounts yang Diperlukan:**
- ✅ Manager Program (1 user)
- ✅ Manager Broadcasting (1 user)
- ✅ Producer (1 user)
- ✅ Music Arranger (1 user)
- ✅ Sound Engineer (1 user)
- ✅ Creative (1 user)
- ✅ Production (1 user)
- ✅ Editor (1 user)
- ✅ Quality Control (1 user)
- ✅ Broadcasting (1 user)

**Base URL:**
```
http://127.0.0.1:8000/api/live-tv
```

**Authentication:**
- Semua endpoint memerlukan Bearer Token
- Login endpoint: `POST /api/login`

---

## 📝 TESTING PHASE 1: SETUP PROGRAM

### **Step 1.1: Manager Program - Create Program**

**Endpoint:** `POST /api/live-tv/programs`

**Request Body:**
```json
{
  "title": "Program Musik Test",
  "description": "Program musik untuk testing",
  "category": "music",
  "start_date": "2025-01-01",
  "end_date": "2025-12-31",
  "manager_program_id": 1
}
```

**Expected Response:**
- Status: `201 Created`
- Data program dibuat
- 53 episodes auto-generate

**Verification:**
- ✅ Program dibuat dengan status `draft`
- ✅ 53 episodes ter-generate (episode_number 1-53)
- ✅ Episodes memiliki status `draft`

---

### **Step 1.2: Manager Program - Assign Production Team**

**Endpoint:** `POST /api/live-tv/manager-program/episodes/{episodeId}/assign-team`

**Request Body:**
```json
{
  "production_team_id": 1,
  "team_notes": "Team untuk episode testing"
}
```

**Expected Response:**
- Status: `200 OK`
- Team assigned ke episode

**Verification:**
- ✅ Production team ter-assign
- ✅ Team members ter-notifikasi

---

### **Step 1.3: Manager Program - Submit Program**

**Endpoint:** `POST /api/live-tv/programs/{programId}/submit`

**Expected Response:**
- Status: `200 OK`
- Program status berubah menjadi `pending_approval`

**Verification:**
- ✅ Program status: `pending_approval`
- ✅ Manager Broadcasting ter-notifikasi

---

### **Step 1.4: Manager Broadcasting - Approve Program**

**Endpoint:** `POST /api/live-tv/manager-broadcasting/schedules/{scheduleId}/approve`

**Request Body:**
```json
{
  "notes": "Program approved untuk testing"
}
```

**Expected Response:**
- Status: `200 OK`
- Program status: `approved`

**Verification:**
- ✅ Program status: `approved`
- ✅ Manager Program ter-notifikasi

---

## 🎵 TESTING PHASE 2: MUSIC ARRANGEMENT

### **Step 2.1: Music Arranger - Create Arrangement (Song Proposal)**

**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements`

**Request Body:**
```json
{
  "episode_id": 1,
  "song_title": "Lagu Test",
  "singer_name": "Penyanyi Test",
  "arrangement_notes": "Arrangement notes untuk testing"
}
```

**Expected Response:**
- Status: `201 Created`
- Arrangement dibuat dengan status `song_proposal`

**Verification:**
- ✅ Arrangement dibuat
- ✅ Status: `song_proposal`
- ✅ Producer ter-notifikasi

---

### **Step 2.2: Music Arranger - Submit Song Proposal**

**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit-song-proposal`

**Expected Response:**
- Status: `200 OK`
- Status: `song_proposal` (siap untuk review Producer)

**Verification:**
- ✅ Status tetap `song_proposal`
- ✅ Producer ter-notifikasi

---

### **Step 2.3: Producer - Approve Song Proposal**

**Endpoint:** `POST /api/live-tv/producer/approvals/{id}/approve`

**Request Body:**
```json
{
  "type": "song_proposal",
  "notes": "Song proposal approved"
}
```

**Expected Response:**
- Status: `200 OK`
- Arrangement status: `song_approved`

**Verification:**
- ✅ Status: `song_approved`
- ✅ Music Arranger ter-notifikasi

---

### **Step 2.4: Music Arranger - Accept Work**

**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work`

**Expected Response:**
- Status: `200 OK`
- Status: `arrangement_in_progress`

**Verification:**
- ✅ Status: `arrangement_in_progress`

---

### **Step 2.5: Music Arranger - Upload Arrangement File**

**Endpoint:** `PUT /api/live-tv/roles/music-arranger/arrangements/{id}`

**Request:** Multipart form data
- `file`: Audio file (mp3, wav, midi)
- `arrangement_notes`: Optional notes

**Expected Response:**
- Status: `200 OK`
- File uploaded

**Verification:**
- ✅ File ter-upload
- ✅ File path tersimpan

---

### **Step 2.6: Music Arranger - Submit Arrangement**

**Endpoint:** `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit`

**Expected Response:**
- Status: `200 OK`
- Status: `arrangement_submitted`

**Verification:**
- ✅ Status: `arrangement_submitted`
- ✅ Producer ter-notifikasi

---

### **Step 2.7: Producer - Approve Arrangement**

**Endpoint:** `POST /api/live-tv/producer/approvals/{id}/approve`

**Request Body:**
```json
{
  "type": "music_arrangement",
  "notes": "Arrangement approved"
}
```

**Expected Response:**
- Status: `200 OK`
- Arrangement status: `arrangement_approved`
- Recording task auto-created
- Creative work auto-created

**Verification:**
- ✅ Status: `arrangement_approved`
- ✅ Recording task ter-create untuk Sound Engineer
- ✅ Creative work ter-create untuk Creative
- ✅ Sound Engineer ter-notifikasi
- ✅ Creative ter-notifikasi

---

## 🎤 TESTING PHASE 3: RECORDING

### **Step 3.1: Sound Engineer - View Approved Arrangements**

**Endpoint:** `GET /api/live-tv/roles/sound-engineer/approved-arrangements`

**Expected Response:**
- Status: `200 OK`
- List arrangements dengan status `arrangement_approved`

**Verification:**
- ✅ Arrangements yang approved muncul
- ✅ Data lengkap (episode, song, singer)

---

### **Step 3.2: Sound Engineer - Create Recording Task**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/arrangements/{arrangementId}/create-recording`

**Request Body:**
```json
{
  "recording_date": "2025-01-15",
  "recording_time": "10:00",
  "notes": "Recording untuk testing"
}
```

**Expected Response:**
- Status: `201 Created`
- Recording task ter-create

**Verification:**
- ✅ Recording task ter-create
- ✅ Status: `draft`

---

### **Step 3.3: Sound Engineer - Accept Work**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/recordings/{id}/accept-work`

**Expected Response:**
- Status: `200 OK`
- Status: `in_progress`

**Verification:**
- ✅ Status: `in_progress`

---

### **Step 3.4: Sound Engineer - Start Recording**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/recordings/{id}/start`

**Expected Response:**
- Status: `200 OK`
- Status: `in_progress`
- Start time tersimpan

**Verification:**
- ✅ Status: `in_progress`
- ✅ Start time tersimpan

---

### **Step 3.5: Sound Engineer - Upload Recording File**

**Endpoint:** `PUT /api/live-tv/roles/sound-engineer/recordings/{id}`

**Request:** Multipart form data
- `file`: Audio file (mp3, wav)

**Expected Response:**
- Status: `200 OK`
- File uploaded

**Verification:**
- ✅ File ter-upload
- ✅ File path tersimpan

---

### **Step 3.6: Sound Engineer - Complete Recording**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/recordings/{id}/complete`

**Expected Response:**
- Status: `200 OK`
- Status: `completed`

**Verification:**
- ✅ Status: `completed`
- ✅ Producer ter-notifikasi

---

## 🎬 TESTING PHASE 4: CREATIVE WORK

### **Step 4.1: Creative - View Creative Works**

**Endpoint:** `GET /api/live-tv/roles/creative/works`

**Expected Response:**
- Status: `200 OK`
- List creative works

**Verification:**
- ✅ Creative works muncul
- ✅ Data lengkap (episode, arrangement)

---

### **Step 4.2: Creative - Accept Work**

**Endpoint:** `POST /api/live-tv/roles/creative/works/{id}/accept-work`

**Expected Response:**
- Status: `200 OK`
- Status: `in_progress`

**Verification:**
- ✅ Status: `in_progress`

---

### **Step 4.3: Creative - Update Creative Work (Script, Storyboard, Budget)**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "script": "Script untuk episode testing",
  "storyboard": "Storyboard description",
  "budget_request": {
    "total_amount": 5000000,
    "items": [
      {
        "item": "Props",
        "amount": 2000000
      },
      {
        "item": "Location",
        "amount": 3000000
      }
    ]
  }
}
```

**Expected Response:**
- Status: `200 OK`
- Creative work updated

**Verification:**
- ✅ Script tersimpan
- ✅ Storyboard tersimpan
- ✅ Budget request tersimpan

---

### **Step 4.4: Creative - Submit Creative Work**

**Endpoint:** `POST /api/live-tv/roles/creative/works/{id}/submit`

**Expected Response:**
- Status: `200 OK`
- Status: `submitted`

**Verification:**
- ✅ Status: `submitted`
- ✅ Producer ter-notifikasi

---

### **Step 4.5: Producer - Approve Creative Work**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/final-approval`

**Request Body:**
```json
{
  "action": "approve",
  "notes": "Creative work approved"
}
```

**Expected Response:**
- Status: `200 OK`
- Status: `approved`
- Production work auto-created

**Verification:**
- ✅ Status: `approved`
- ✅ Production work ter-create
- ✅ Production ter-notifikasi

---

## 🎥 TESTING PHASE 5: PRODUCTION

### **Step 5.1: Production - Accept Work**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/accept-work`

**Expected Response:**
- Status: `200 OK`
- Status: `in_progress`

**Verification:**
- ✅ Status: `in_progress`

---

### **Step 5.2: Production - Request Equipment**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/request-equipment`

**Request Body:**
```json
{
  "equipment_list": [
    {
      "equipment_name": "Camera",
      "quantity": 2,
      "return_date": "2025-01-20",
      "notes": "Camera untuk shooting"
    }
  ],
  "request_notes": "Equipment request untuk testing"
}
```

**Expected Response:**
- Status: `200 OK`
- Equipment requests ter-create

**Verification:**
- ✅ Equipment requests ter-create
- ✅ Art & Set Properti ter-notifikasi

---

### **Step 5.3: Production - Create Run Sheet**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet`

**Request Body:**
```json
{
  "shooting_date": "2025-01-18",
  "shooting_location": "Studio Test",
  "notes": "Run sheet untuk testing",
  "timeline": [
    {
      "time": "09:00",
      "activity": "Setup",
      "notes": "Setup equipment"
    },
    {
      "time": "10:00",
      "activity": "Shooting",
      "notes": "Start shooting"
    }
  ]
}
```

**Expected Response:**
- Status: `200 OK`
- Run sheet ter-create

**Verification:**
- ✅ Run sheet tersimpan
- ✅ Timeline tersimpan

---

### **Step 5.4: Production - Upload Shooting Results**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results`

**Request:** Multipart form data
- `files[]`: Video files (mp4, mov, avi)

**Expected Response:**
- Status: `200 OK`
- Files uploaded

**Verification:**
- ✅ Files ter-upload
- ✅ File paths tersimpan

---

### **Step 5.5: Production - Complete Work**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/complete-work`

**Expected Response:**
- Status: `200 OK`
- Status: `completed`
- Editor work auto-created

**Verification:**
- ✅ Status: `completed`
- ✅ Editor work ter-create
- ✅ Editor ter-notifikasi

---

## ✂️ TESTING PHASE 6: EDITING

### **Step 6.1: Editor - View Editor Works**

**Endpoint:** `GET /api/live-tv/roles/editor/works`

**Expected Response:**
- Status: `200 OK`
- List editor works

**Verification:**
- ✅ Editor works muncul

---

### **Step 6.2: Editor - Accept Work**

**Endpoint:** `POST /api/live-tv/roles/editor/works/{id}/accept-work`

**Expected Response:**
- Status: `200 OK`
- Status: `in_progress`

**Verification:**
- ✅ Status: `in_progress`

---

### **Step 6.3: Editor - Upload Edited File**

**Endpoint:** `PUT /api/live-tv/roles/editor/works/{id}`

**Request:** Multipart form data
- `file`: Video file (mp4, mov)

**Expected Response:**
- Status: `200 OK`
- File uploaded

**Verification:**
- ✅ File ter-upload

---

### **Step 6.4: Editor - Submit Editor Work**

**Endpoint:** `POST /api/live-tv/roles/editor/works/{id}/submit`

**Expected Response:**
- Status: `200 OK`
- Status: `submitted`
- QC work auto-created

**Verification:**
- ✅ Status: `submitted`
- ✅ QC work ter-create
- ✅ Quality Control ter-notifikasi

---

## ✅ TESTING PHASE 7: QUALITY CONTROL

### **Step 7.1: Quality Control - View QC Works**

**Endpoint:** `GET /api/live-tv/roles/quality-control/controls`

**Expected Response:**
- Status: `200 OK`
- List QC works

**Verification:**
- ✅ QC works muncul

---

### **Step 7.2: Quality Control - Start QC**

**Endpoint:** `POST /api/live-tv/roles/quality-control/controls/{id}/start`

**Expected Response:**
- Status: `200 OK`
- Status: `in_progress`

**Verification:**
- ✅ Status: `in_progress`

---

### **Step 7.3: Quality Control - Submit QC Form**

**Endpoint:** `POST /api/live-tv/roles/quality-control/controls/{id}/submit-form`

**Request Body:**
```json
{
  "qc_results": {
    "audio_quality": "good",
    "video_quality": "good",
    "sync_check": "passed",
    "notes": "QC passed untuk testing"
  }
}
```

**Expected Response:**
- Status: `200 OK`
- QC form submitted

**Verification:**
- ✅ QC results tersimpan

---

### **Step 7.4: Quality Control - Approve**

**Endpoint:** `POST /api/live-tv/roles/quality-control/controls/{id}/approve`

**Request Body:**
```json
{
  "notes": "QC approved"
}
```

**Expected Response:**
- Status: `200 OK`
- Status: `approved`
- Broadcasting work auto-created

**Verification:**
- ✅ Status: `approved`
- ✅ Broadcasting work ter-create
- ✅ Broadcasting ter-notifikasi

---

## 📺 TESTING PHASE 8: BROADCASTING

### **Step 8.1: Broadcasting - View Broadcasting Works**

**Endpoint:** `GET /api/live-tv/roles/broadcasting/works`

**Expected Response:**
- Status: `200 OK`
- List broadcasting works

**Verification:**
- ✅ Broadcasting works muncul

---

### **Step 8.2: Broadcasting - Accept Work**

**Endpoint:** `POST /api/live-tv/roles/broadcasting/works/{id}/accept-work`

**Expected Response:**
- Status: `200 OK`
- Status: `in_progress`

**Verification:**
- ✅ Status: `in_progress`

---

### **Step 8.3: Broadcasting - Upload to YouTube**

**Endpoint:** `POST /api/live-tv/roles/broadcasting/works/{id}/upload-youtube`

**Request Body:**
```json
{
  "youtube_url": "https://youtube.com/watch?v=test123",
  "title": "Video Title",
  "description": "Video Description",
  "tags": ["tag1", "tag2"]
}
```

**Expected Response:**
- Status: `200 OK`
- YouTube URL tersimpan

**Verification:**
- ✅ YouTube URL tersimpan

---

### **Step 8.4: Broadcasting - Upload to Website**

**Endpoint:** `POST /api/live-tv/roles/broadcasting/works/{id}/upload-website`

**Request Body:**
```json
{
  "website_url": "https://website.com/video/123"
}
```

**Expected Response:**
- Status: `200 OK`
- Website URL tersimpan

**Verification:**
- ✅ Website URL tersimpan

---

### **Step 8.5: Broadcasting - Complete Work**

**Endpoint:** `POST /api/live-tv/roles/broadcasting/works/{id}/complete-work`

**Expected Response:**
- Status: `200 OK`
- Status: `completed`

**Verification:**
- ✅ Status: `completed`
- ✅ Episode status: `completed`

---

## 🔒 TESTING KEAMANAN & PERFORMANCE

### **Security Testing**

1. **Authentication Testing**
   - ✅ Test tanpa token → Harus return 401
   - ✅ Test dengan invalid token → Harus return 401
   - ✅ Test dengan expired token → Harus return 401

2. **Role Validation Testing**
   - ✅ Test Music Arranger endpoint dengan role lain → Harus return 403
   - ✅ Test Producer endpoint dengan role lain → Harus return 403
   - ✅ Test semua endpoint dengan role yang tidak sesuai → Harus return 403

3. **Input Validation Testing**
   - ✅ Test dengan invalid data → Harus return 422
   - ✅ Test dengan XSS payload → Harus di-sanitize
   - ✅ Test dengan SQL injection → Harus di-handle dengan benar

4. **File Upload Security Testing**
   - ✅ Test upload file dengan extension tidak valid → Harus return 422
   - ✅ Test upload file terlalu besar → Harus return 422
   - ✅ Test upload file dengan MIME type tidak valid → Harus return 422

---

### **Performance Testing**

1. **Rate Limiting Testing**
   - ✅ Test GET endpoint > 60 requests/min → Harus return 429
   - ✅ Test POST endpoint > 20 requests/min → Harus return 429
   - ✅ Test upload endpoint > 10 requests/min → Harus return 429

2. **Caching Testing**
   - ✅ Test index endpoint → Response time harus cepat setelah cache
   - ✅ Test show endpoint → Response time harus cepat setelah cache
   - ✅ Test cache invalidation setelah create/update → Cache harus clear

3. **Query Performance Testing**
   - ✅ Test endpoint dengan banyak data → Response time < 2 seconds
   - ✅ Test endpoint dengan nested relations → Tidak ada N+1 query

---

## ✅ CHECKLIST TESTING LENGKAP

### **Phase 1: Setup Program**
- [ ] Manager Program create program
- [ ] 53 episodes auto-generate
- [ ] Assign production team
- [ ] Submit program
- [ ] Manager Broadcasting approve program

### **Phase 2: Music Arrangement**
- [ ] Music Arranger create arrangement
- [ ] Submit song proposal
- [ ] Producer approve song proposal
- [ ] Music Arranger accept work
- [ ] Upload arrangement file
- [ ] Submit arrangement
- [ ] Producer approve arrangement

### **Phase 3: Recording**
- [ ] Sound Engineer view approved arrangements
- [ ] Create recording task
- [ ] Accept work
- [ ] Start recording
- [ ] Upload recording file
- [ ] Complete recording

### **Phase 4: Creative Work**
- [ ] Creative view works
- [ ] Accept work
- [ ] Update creative work (script, storyboard, budget)
- [ ] Submit creative work
- [ ] Producer approve creative work

### **Phase 5: Production**
- [ ] Production accept work
- [ ] Request equipment
- [ ] Create run sheet
- [ ] Upload shooting results
- [ ] Complete work

### **Phase 6: Editing**
- [ ] Editor view works
- [ ] Accept work
- [ ] Upload edited file
- [ ] Submit editor work

### **Phase 7: Quality Control**
- [ ] QC view works
- [ ] Start QC
- [ ] Submit QC form
- [ ] Approve QC

### **Phase 8: Broadcasting**
- [ ] Broadcasting view works
- [ ] Accept work
- [ ] Upload to YouTube
- [ ] Upload to Website
- [ ] Complete work

### **Security & Performance**
- [ ] Authentication testing
- [ ] Role validation testing
- [ ] Input validation testing
- [ ] File upload security testing
- [ ] Rate limiting testing
- [ ] Caching testing
- [ ] Query performance testing

---

## 📝 CATATAN PENTING

1. **Testing harus dilakukan secara berurutan** sesuai flow di atas
2. **Setiap step harus diverifikasi** sebelum lanjut ke step berikutnya
3. **Periksa notification** di setiap step untuk memastikan workflow berjalan
4. **Periksa status** di database untuk memastikan update berjalan
5. **Periksa log** jika ada error untuk troubleshooting
6. **Test dengan berbagai skenario**: approve, reject, resubmit, dll
7. **Test audit logging** - Periksa `storage/logs/audit-*.log`
8. **Test caching** - Periksa response time sebelum dan setelah cache

---

## 🔍 DEBUGGING

Jika ada error saat testing:

1. **Cek Error Log:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Cek Audit Log:**
   ```bash
   tail -f storage/logs/audit-*.log
   ```

3. **Cek Database:**
   - Periksa status di database
   - Periksa relasi data
   - Periksa foreign key constraints

4. **Cek API Response:**
   - Periksa response status code
   - Periksa response message
   - Periksa response data structure

---

**Last Updated:** 2025-01-15  
**Created By:** AI Assistant  
**Version:** 1.0

