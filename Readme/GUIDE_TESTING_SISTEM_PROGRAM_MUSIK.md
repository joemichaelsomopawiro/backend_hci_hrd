# 🧪 GUIDE TESTING SISTEM PROGRAM MUSIK

**Tanggal:** 2025-12-12  
**Versi:** 1.0

---

## 📋 DAFTAR ISI

1. [Overview Sistem](#overview-sistem)
2. [Role dan Tugas](#role-dan-tugas)
3. [Flow Lengkap Sistem](#flow-lengkap-sistem)
4. [Step-by-Step Testing Guide](#step-by-step-testing-guide)
5. [Endpoint yang Digunakan](#endpoint-yang-digunakan)
6. [Data Testing](#data-testing)
7. [Troubleshooting](#troubleshooting)

---

## 🚀 QUICK START TESTING

### **Langkah Cepat untuk Testing:**

1. **Setup Database & User**
   ```bash
   php artisan migrate
   php artisan db:seed --class=ProgramMusicTestSeeder  # Jika ada
   ```

2. **Login sebagai Manager Program**
   - Email: `manager.program@test.com`
   - Password: `password`

3. **Create Program Pertama**
   - Buka form create program
   - Isi data minimal:
     - Name: "Program Test"
     - Manager Program ID: (user ID yang login)
     - Start Date: (tanggal mendatang)
     - Air Time: "19:00"
   - Submit

4. **Verifikasi**
   - Cek apakah 53 episodes ter-generate
   - Cek status program = "draft"

5. **Lanjutkan ke Step 3** di [Step-by-Step Testing Guide](#step-by-step-testing-guide)

---

## 🎯 OVERVIEW SISTEM

Sistem Program Musik adalah sistem manajemen produksi program musik TV yang melibatkan berbagai role dalam workflow produksi. Sistem ini mengelola 53 episode program musik dengan berbagai tahapan dari perencanaan hingga tayang.

### **Komponen Utama:**
- **Program**: Program musik utama (53 episode)
- **Episode**: Setiap episode dalam program
- **Workflow States**: Status workflow setiap episode
- **Production Team**: Tim produksi yang terlibat
- **Notifications**: Notifikasi untuk setiap role

---

## 👥 ROLE DAN TUGAS

### 1. **Manager Program** (Program Manager)
- Membuat program baru
- Assign tim produksi ke episode
- Monitor progress semua episode
- Approve/reject budget requests
- Override approval jika diperlukan

### 2. **Producer**
- Review dan approve/reject:
  - Song proposals (lagu & penyanyi)
  - Music arrangements
  - Creative works
  - Budget requests
- Assign tim produksi
- Monitor produksi

### 3. **Music Arranger**
- Buat arrangement baru
- Ajukan lagu & penyanyi (song proposal)
- Arrange lagu setelah song proposal approved
- Submit arrangement file untuk review

### 4. **Sound Engineer**
- Terima arrangement yang sudah approved
- Buat recording task
- Record vokal
- Submit recording untuk review

### 5. **Creative**
- Terima pekerjaan setelah arrangement approved
- Buat script, storyboard, budget
- Submit creative work untuk review

### 6. **Produksi**
- Terima pekerjaan setelah creative work approved
- Request equipment
- Input run sheet (catatan syuting)
- Upload hasil syuting

### 7. **Editor**
- Terima pekerjaan setelah recording approved
- Edit video/audio
- Submit hasil editing

### 8. **Quality Control (QC)**
- QC semua konten (audio, video, design)
- Approve/reject hasil QC

### 9. **Broadcasting**
- Upload ke YouTube
- Upload ke website
- Schedule tayang

### 10. **Manager Broadcasting**
- Approve/reject schedule tayang
- Monitor broadcasting

---

## 🔄 FLOW LENGKAP SISTEM

### **PHASE 1: SETUP PROGRAM**

```
Manager Program
    ↓
1. Create Program
    ↓
2. System auto-generate 53 episodes
    ↓
3. Assign Production Team ke Episode
    ↓
4. Submit Program untuk Approval
    ↓
Manager Broadcasting
    ↓
5. Approve/Reject Program
```

### **PHASE 2: MUSIC ARRANGEMENT**

```
Music Arranger
    ↓
1. Create Arrangement (Ajukan Lagu & Penyanyi)
    ↓
2. Submit Song Proposal
    ↓
Producer
    ↓
3. Approve/Reject Song Proposal
    ↓
Music Arranger (jika approved)
    ↓
4. Accept Work
    ↓
5. Upload Arrangement File
    ↓
6. Submit Arrangement
    ↓
Producer
    ↓
7. Approve/Reject Arrangement
```

### **PHASE 3: RECORDING**

```
Sound Engineer
    ↓
1. Terima Arrangement yang Approved
    ↓
2. Create Recording Task
    ↓
3. Accept Work
    ↓
4. Start Recording
    ↓
5. Upload Recording File
    ↓
6. Complete Recording
    ↓
Producer
    ↓
7. Review Recording
```

### **PHASE 4: CREATIVE WORK**

```
Creative
    ↓
1. Terima Pekerjaan (setelah Arrangement Approved)
    ↓
2. Accept Work
    ↓
3. Input Script, Storyboard, Budget
    ↓
4. Submit Creative Work
    ↓
Producer
    ↓
5. Review Creative Work
    ↓
6. Approve/Reject Creative Work
```

### **PHASE 5: PRODUCTION**

```
Produksi
    ↓
1. Terima Pekerjaan (setelah Creative Work Approved)
    ↓
2. Accept Work
    ↓
3. Request Equipment
    ↓
4. Input Run Sheet
    ↓
5. Upload Shooting Results
    ↓
6. Complete Work
```

### **PHASE 6: EDITING**

```
Editor
    ↓
1. Terima Pekerjaan (setelah Recording Approved)
    ↓
2. Accept Work
    ↓
3. Edit Video/Audio
    ↓
4. Submit Editor Work
```

### **PHASE 7: QUALITY CONTROL**

```
Quality Control
    ↓
1. Terima Pekerjaan (setelah Editor Work Submitted)
    ↓
2. Accept Work
    ↓
3. Start QC
    ↓
4. QC Content
    ↓
5. Complete QC
    ↓
6. Approve/Reject QC
```

### **PHASE 8: BROADCASTING**

```
Broadcasting
    ↓
1. Terima Pekerjaan (setelah QC Approved)
    ↓
2. Accept Work
    ↓
3. Upload ke YouTube
    ↓
4. Upload ke Website
    ↓
5. Submit Schedule Options
    ↓
Manager Broadcasting
    ↓
6. Approve/Reject Schedule
    ↓
Broadcasting
    ↓
7. Publish Schedule
```

---

## 📋 FLOW ASSIGN TIM PRODUKSI KE EPISODE (DETAIL)

### **Cara Kerja Sistem:**

1. **Manager Program membuat Production Team** (kosong dulu)
   - Team hanya berisi: name, description, producer_id
   - Belum ada members

2. **Manager Program menambahkan members ke team**
   - Bisa tambah satu per satu atau batch
   - Setiap member punya role: `kreatif`, `musik_arr`, `sound_eng`, `produksi`, `editor`, `art_set_design`
   - **Bisa ada banyak pegawai dengan role yang sama** (misalnya 5 editor)

3. **Manager Program assign team ke episode**
   - Setelah team lengkap (minimal 1 orang per role)
   - Bisa assign ke 1 episode atau semua episode program

### **Contoh: Team dengan 5 Editor**

**Step 1: Buat Team**
```json
POST /api/live-tv/production-teams
{
  "name": "Tim Produksi Musik A",
  "producer_id": 5
}
```

**Step 2: Tambah Members (termasuk 5 editor)**
```json
// Tambah Editor 1
POST /api/live-tv/production-teams/1/members
{"user_id": 10, "role": "editor"}

// Tambah Editor 2
POST /api/live-tv/production-teams/1/members
{"user_id": 11, "role": "editor"}

// Tambah Editor 3, 4, 5... dst
// Juga tambah role lain: kreatif, musik_arr, sound_eng, produksi, art_set_design
```

**Step 3: Assign Team ke Episode**
```json
POST /api/live-tv/manager-program/episodes/1/assign-team
{
  "production_team_id": 1,
  "notes": "Assign team dengan 5 editor"
}
```

**Hasil:**
- Semua 5 editor akan mendapat notifikasi
- Saat episode dikerjakan, Producer/Manager bisa pilih editor mana yang akan mengerjakan
- Sistem bisa track workload per editor

### **Endpoint Penting:**

- `GET /api/live-tv/production-teams/available-users/{role}` - List user per role
- `GET /api/live-tv/production-teams/{id}/statistics` - Cek apakah team ready
- `GET /api/live-tv/production-teams/{id}/workload` - Lihat workload per member

---

## 🧪 STEP-BY-STEP TESTING GUIDE

### **STEP 1: SETUP - Login sebagai Manager Program**

1. **Login** dengan user role `Manager Program`
2. **Verifikasi Dashboard**:
   - GET `/api/live-tv/manager-program/dashboard`
   - Harus return data dashboard

### **STEP 2: CREATE PROGRAM**

1. **Buka Form Create Program**
2. **Isi Data Program**:
   ```json
   {
     "name": "Program Musik Test 2025",
     "description": "Program musik untuk testing",
     "category": "musik",
     "manager_program_id": <user_id>,
     "production_team_id": <production_team_id>,
     "start_date": "2025-12-15",
     "air_time": "19:00",
     "duration_minutes": 60,
     "broadcast_channel": "TVRI",
     "target_views_per_episode": 10000
   }
   ```
3. **Submit**:
   - POST `/api/live-tv/programs`
   - **Expected**: Program created, 53 episodes auto-generated
4. **Verifikasi**:
   - GET `/api/live-tv/programs/{program_id}`
   - Harus return program dengan 53 episodes

### **STEP 3: CREATE & ASSIGN PRODUCTION TEAM**

#### **3.1. Membuat Production Team**

**Flow:**
1. **Buat Production Team** (Team kosong dulu):
   ```json
   POST /api/live-tv/production-teams
   {
     "name": "Tim Produksi Musik A",
     "description": "Tim untuk program musik",
     "producer_id": <producer_user_id>,
     "created_by": <manager_program_user_id> // optional, auto dari auth
   }
   ```

2. **Tambahkan Members ke Team** (satu per satu atau batch):
   ```json
   POST /api/live-tv/production-teams/{team_id}/members
   {
     "user_id": <user_id>,
     "role": "kreatif", // atau: musik_arr, sound_eng, produksi, editor, art_set_design
     "notes": "Optional notes"
   }
   ```

**Catatan Penting:**
- ✅ **Bisa ada banyak pegawai dengan role yang sama** (misalnya 5 editor)
- ✅ Setiap role bisa memiliki multiple members
- ✅ Team harus memiliki minimal 1 orang untuk setiap role (6 roles wajib):
  - `kreatif` - Creative
  - `musik_arr` - Music Arranger
  - `sound_eng` - Sound Engineer
  - `produksi` - Production
  - `editor` - Editor
  - `art_set_design` - Art & Set Design

**Contoh: Team dengan Multiple Editors**
```json
// Tambah Editor 1
POST /api/live-tv/production-teams/1/members
{
  "user_id": 10,
  "role": "editor",
  "notes": "Editor utama"
}

// Tambah Editor 2
POST /api/live-tv/production-teams/1/members
{
  "user_id": 11,
  "role": "editor",
  "notes": "Editor cadangan"
}

// Tambah Editor 3, 4, 5... dst
```

**Cek Team Status:**
```json
GET /api/live-tv/production-teams/{team_id}
// Response akan include:
// - is_ready_for_production: true/false
// - roles_summary: distribusi role
// - missing_roles: role yang belum ada
```

#### **3.2. Assign Team ke Episode**

Setelah team lengkap, assign ke episode:

```json
POST /api/live-tv/manager-program/episodes/{episode_id}/assign-team
{
  "production_team_id": <team_id>,
  "notes": "Assign team untuk episode ini"
}
```

**Atau assign ke semua episode program:**
```json
POST /api/live-tv/manager-program/programs/{program_id}/assign-team
{
  "production_team_id": <team_id>,
  "apply_to_all_episodes": true,
  "notes": "Assign team ke semua episode"
}
```

**Verifikasi:**
- GET `/api/live-tv/episodes/{episode_id}`
- Harus return episode dengan `production_team_id` dan team members

#### **3.3. Cara Kerja Multiple Members dengan Role Sama**

**Skenario:** Ada 5 Editor dalam 1 team

1. **Semua Editor dalam Team**:
   - Semua 5 editor terdaftar sebagai members dengan role `editor`
   - Semua akan mendapat notifikasi saat team di-assign ke episode

2. **Saat Episode Dikerjakan**:
   - Producer/Manager bisa pilih editor mana yang akan mengerjakan episode tertentu
   - Atau sistem bisa auto-assign berdasarkan workload
   - Editor yang dipilih akan mendapat notifikasi khusus

3. **Workload Distribution**:
   - GET `/api/live-tv/production-teams/{team_id}/workload`
   - Menampilkan workload per member per role

**Endpoint untuk Get Available Users per Role:**
```json
GET /api/live-tv/production-teams/available-users/{role}
// Contoh: GET /api/live-tv/production-teams/available-users/editor
// Return: List semua user dengan role "editor" yang aktif
// Response:
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "Editor 1",
      "email": "editor1@example.com",
      "role": "editor"
    },
    {
      "id": 11,
      "name": "Editor 2",
      "email": "editor2@example.com",
      "role": "editor"
    }
    // ... dst
  ]
}
```

### **STEP 4: ASSIGN PRODUCTION TEAM (Lanjutan)**

1. **Pilih Episode** (misalnya episode 1)
2. **Assign Team**:
   - POST `/api/live-tv/manager-program/episodes/{episode_id}/assign-team`
   - Body: `{ "production_team_id": <team_id> }`
3. **Verifikasi**:
   - GET `/api/live-tv/episodes/{episode_id}`
   - Harus return episode dengan production team

### **STEP 4: SUBMIT PROGRAM**

1. **Submit Program untuk Approval**:
   - POST `/api/live-tv/programs/{program_id}/submit`
2. **Verifikasi Status**:
   - Status program harus `pending_approval`
   - Notification harus terkirim ke Manager Broadcasting

### **STEP 5: APPROVE PROGRAM (Manager Broadcasting)**

1. **Login** sebagai `Manager Broadcasting`
2. **Lihat Pending Approvals**:
   - GET `/api/live-tv/manager-broadcasting/schedules`
3. **Approve Program**:
   - POST `/api/live-tv/manager-broadcasting/schedules/{id}/approve`
4. **Verifikasi**:
   - Status program harus `approved`
   - Episodes harus `approved_for_production`

### **STEP 6: MUSIC ARRANGER - CREATE ARRANGEMENT**

1. **Login** sebagai `Music Arranger`
2. **Lihat Episodes**:
   - GET `/api/live-tv/roles/music-arranger/arrangements`
3. **Create Arrangement**:
   - POST `/api/live-tv/roles/music-arranger/arrangements`
   - Body:
     ```json
     {
       "episode_id": <episode_id>,
       "song_title": "Lagu Test",
       "singer_name": "Penyanyi Test",
       "arrangement_notes": "Notes arrangement"
     }
     ```
4. **Verifikasi**:
   - Status harus `song_proposal`
   - Notification harus terkirim ke Producer

### **STEP 7: PRODUCER - APPROVE SONG PROPOSAL**

1. **Login** sebagai `Producer`
2. **Lihat Pending Approvals**:
   - GET `/api/live-tv/producer/approvals`
3. **Approve Song Proposal**:
   - POST `/api/live-tv/producer/approvals/{id}/approve`
   - Body: `{ "type": "song_proposal", "notes": "Approved" }`
4. **Verifikasi**:
   - Status arrangement harus `song_approved`
   - Notification harus terkirim ke Music Arranger

### **STEP 8: MUSIC ARRANGER - ARRANGE LAGU**

1. **Login** sebagai `Music Arranger`
2. **Accept Work**:
   - POST `/api/live-tv/roles/music-arranger/arrangements/{id}/accept-work`
3. **Upload Arrangement File**:
   - PUT `/api/live-tv/roles/music-arranger/arrangements/{id}`
   - Body: Form data dengan file audio
4. **Submit Arrangement**:
   - POST `/api/live-tv/roles/music-arranger/arrangements/{id}/submit`
5. **Verifikasi**:
   - Status harus `arrangement_submitted`
   - Notification harus terkirim ke Producer

### **STEP 9: PRODUCER - APPROVE ARRANGEMENT**

1. **Login** sebagai `Producer`
2. **Approve Arrangement**:
   - POST `/api/live-tv/producer/approvals/{id}/approve`
   - Body: `{ "type": "music_arrangement", "notes": "Approved" }`
3. **Verifikasi**:
   - Status harus `arrangement_approved`
   - Recording task harus auto-created untuk Sound Engineer
   - Creative work task harus auto-created untuk Creative

### **STEP 10: SOUND ENGINEER - RECORDING**

1. **Login** sebagai `Sound Engineer`
2. **Lihat Recording Tasks**:
   - GET `/api/live-tv/roles/sound-engineer/recordings`
3. **Accept Work**:
   - POST `/api/live-tv/roles/sound-engineer/recordings/{id}/accept-work`
4. **Start Recording**:
   - POST `/api/live-tv/roles/sound-engineer/recordings/{id}/start`
5. **Upload Recording File**:
   - PUT `/api/live-tv/roles/sound-engineer/recordings/{id}`
6. **Complete Recording**:
   - POST `/api/live-tv/roles/sound-engineer/recordings/{id}/complete`
7. **Verifikasi**:
   - Status harus `completed`
   - Notification harus terkirim ke Producer

### **STEP 11: CREATIVE - CREATIVE WORK**

1. **Login** sebagai `Creative`
2. **Lihat Creative Works**:
   - GET `/api/live-tv/roles/creative/works`
3. **Accept Work**:
   - POST `/api/live-tv/roles/creative/works/{id}/accept-work`
4. **Update Creative Work**:
   - PUT `/api/live-tv/roles/creative/works/{id}`
   - Body:
     ```json
     {
       "script_content": "Script content",
       "storyboard_data": {...},
       "budget_data": {...},
       "recording_schedule": "2025-12-20",
       "shooting_schedule": "2025-12-21",
       "shooting_location": "Studio"
     }
     ```
5. **Submit Creative Work**:
   - POST `/api/live-tv/roles/creative/works/{id}/submit`
6. **Verifikasi**:
   - Status harus `submitted`
   - Notification harus terkirim ke Producer

### **STEP 12: PRODUCER - APPROVE CREATIVE WORK**

1. **Login** sebagai `Producer`
2. **Approve Creative Work**:
   - POST `/api/live-tv/producer/approvals/{id}/approve`
   - Body: `{ "type": "creative_work", "notes": "Approved" }`
3. **Verifikasi**:
   - Status harus `approved`
   - Produksi work harus auto-created

### **STEP 13: PRODUKSI - PRODUCTION**

1. **Login** sebagai `Produksi`
2. **Lihat Produksi Works**:
   - GET `/api/live-tv/roles/produksi/works`
3. **Accept Work**:
   - POST `/api/live-tv/roles/produksi/works/{id}/accept-work`
4. **Request Equipment**:
   - POST `/api/live-tv/roles/produksi/works/{id}/request-equipment`
5. **Create Run Sheet**:
   - POST `/api/live-tv/roles/produksi/works/{id}/create-run-sheet`
6. **Upload Shooting Results**:
   - POST `/api/live-tv/roles/produksi/works/{id}/upload-shooting-results`
7. **Complete Work**:
   - POST `/api/live-tv/roles/produksi/works/{id}/complete-work`

### **STEP 14: EDITOR - EDITING**

1. **Login** sebagai `Editor`
2. **Lihat Editor Works**:
   - GET `/api/live-tv/roles/editor/works`
3. **Accept Work**:
   - POST `/api/live-tv/roles/editor/works/{id}/accept-work`
4. **Update Editor Work**:
   - PUT `/api/live-tv/roles/editor/works/{id}`
5. **Submit Editor Work**:
   - POST `/api/live-tv/roles/editor/works/{id}/submit`

### **STEP 15: QUALITY CONTROL**

1. **Login** sebagai `Quality Control`
2. **Lihat QC Works**:
   - GET `/api/live-tv/roles/quality-control/controls`
3. **Accept Work**:
   - POST `/api/live-tv/roles/quality-control/works/{id}/accept-work`
4. **Start QC**:
   - POST `/api/live-tv/roles/quality-control/controls/{id}/start`
5. **Complete QC**:
   - POST `/api/live-tv/roles/quality-control/controls/{id}/complete`
6. **Approve QC**:
   - POST `/api/live-tv/roles/quality-control/controls/{id}/approve`

### **STEP 16: BROADCASTING**

1. **Login** sebagai `Broadcasting`
2. **Lihat Broadcasting Works**:
   - GET `/api/live-tv/roles/broadcasting/works`
3. **Accept Work**:
   - POST `/api/live-tv/roles/broadcasting/works/{id}/accept-work`
4. **Upload YouTube**:
   - POST `/api/live-tv/roles/broadcasting/works/{id}/upload-youtube`
5. **Upload Website**:
   - POST `/api/live-tv/roles/broadcasting/works/{id}/upload-website`
6. **Submit Schedule Options**:
   - POST `/api/live-tv/manager-program/programs/{program_id}/submit-schedule-options`

### **STEP 17: MANAGER BROADCASTING - APPROVE SCHEDULE**

1. **Login** sebagai `Manager Broadcasting`
2. **Lihat Schedule Options**:
   - GET `/api/live-tv/manager-broadcasting/schedule-options`
3. **Approve Schedule**:
   - POST `/api/live-tv/manager-broadcasting/schedule-options/{id}/approve`
4. **Verifikasi**:
   - Schedule harus `approved`
   - Episode siap tayang

---

## 📡 ENDPOINT YANG DIGUNAKAN

### **Manager Program**
- `GET /api/live-tv/manager-program/dashboard` - Dashboard
- `POST /api/live-tv/programs` - Create program
- `GET /api/live-tv/programs/{id}` - Get program
- `POST /api/live-tv/manager-program/episodes/{id}/assign-team` - Assign team
- `POST /api/live-tv/programs/{id}/submit` - Submit program

### **Music Arranger**
- `GET /api/live-tv/roles/music-arranger/arrangements` - List arrangements
- `POST /api/live-tv/roles/music-arranger/arrangements` - Create arrangement
- `POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work` - Accept work
- `PUT /api/live-tv/roles/music-arranger/arrangements/{id}` - Update arrangement
- `POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit` - Submit arrangement

### **Producer**
- `GET /api/live-tv/producer/approvals` - List approvals
- `POST /api/live-tv/producer/approvals/{id}/approve` - Approve
- `POST /api/live-tv/producer/approvals/{id}/reject` - Reject

### **Sound Engineer**
- `GET /api/live-tv/roles/sound-engineer/recordings` - List recordings
- `POST /api/live-tv/roles/sound-engineer/recordings/{id}/accept-work` - Accept work
- `POST /api/live-tv/roles/sound-engineer/recordings/{id}/start` - Start recording
- `POST /api/live-tv/roles/sound-engineer/recordings/{id}/complete` - Complete recording

### **Creative**
- `GET /api/live-tv/roles/creative/works` - List works
- `POST /api/live-tv/roles/creative/works/{id}/accept-work` - Accept work
- `PUT /api/live-tv/roles/creative/works/{id}` - Update work
- `POST /api/live-tv/roles/creative/works/{id}/submit` - Submit work

### **Produksi**
- `GET /api/live-tv/roles/produksi/works` - List works
- `POST /api/live-tv/roles/produksi/works/{id}/accept-work` - Accept work
- `POST /api/live-tv/roles/produksi/works/{id}/request-equipment` - Request equipment
- `POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet` - Create run sheet
- `POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results` - Upload results

### **Editor**
- `GET /api/live-tv/roles/editor/works` - List works
- `POST /api/live-tv/roles/editor/works/{id}/accept-work` - Accept work
- `PUT /api/live-tv/roles/editor/works/{id}` - Update work
- `POST /api/live-tv/roles/editor/works/{id}/submit` - Submit work

### **Quality Control**
- `GET /api/live-tv/roles/quality-control/controls` - List controls
- `POST /api/live-tv/roles/quality-control/works/{id}/accept-work` - Accept work
- `POST /api/live-tv/roles/quality-control/controls/{id}/start` - Start QC
- `POST /api/live-tv/roles/quality-control/controls/{id}/complete` - Complete QC
- `POST /api/live-tv/roles/quality-control/controls/{id}/approve` - Approve QC

### **Broadcasting**
- `GET /api/live-tv/roles/broadcasting/works` - List works
- `POST /api/live-tv/roles/broadcasting/works/{id}/accept-work` - Accept work
- `POST /api/live-tv/roles/broadcasting/works/{id}/upload-youtube` - Upload YouTube
- `POST /api/live-tv/roles/broadcasting/works/{id}/upload-website` - Upload website

### **Manager Broadcasting**
- `GET /api/live-tv/manager-broadcasting/schedules` - List schedules
- `POST /api/live-tv/manager-broadcasting/schedules/{id}/approve` - Approve schedule
- `GET /api/live-tv/manager-broadcasting/schedule-options` - List schedule options
- `POST /api/live-tv/manager-broadcasting/schedule-options/{id}/approve` - Approve schedule option

---

## 📊 DATA TESTING

### **User Test Accounts (Harus dibuat di database)**

1. **Manager Program**
   - Email: `manager.program@test.com`
   - Role: `Manager Program`
   - Password: `password`

2. **Producer**
   - Email: `producer@test.com`
   - Role: `Producer`
   - Password: `password`

3. **Music Arranger**
   - Email: `music.arranger@test.com`
   - Role: `Music Arranger`
   - Password: `password`

4. **Sound Engineer**
   - Email: `sound.engineer@test.com`
   - Role: `Sound Engineer`
   - Password: `password`

5. **Creative**
   - Email: `creative@test.com`
   - Role: `Creative`
   - Password: `password`

6. **Produksi**
   - Email: `produksi@test.com`
   - Role: `Produksi`
   - Password: `password`

7. **Editor**
   - Email: `editor@test.com`
   - Role: `Editor`
   - Password: `password`

8. **Quality Control**
   - Email: `quality.control@test.com`
   - Role: `Quality Control`
   - Password: `password`

9. **Broadcasting**
   - Email: `broadcasting@test.com`
   - Role: `Broadcasting`
   - Password: `password`

10. **Manager Broadcasting**
    - Email: `manager.broadcasting@test.com`
    - Role: `Manager Broadcasting`
    - Password: `password`

### **Production Team Test Data**

```json
{
  "name": "Tim Produksi Test",
  "producer_id": <producer_user_id>,
  "members": [
    {
      "user_id": <music_arranger_user_id>,
      "role": "music_arr",
      "is_active": true
    },
    {
      "user_id": <sound_engineer_user_id>,
      "role": "sound_eng",
      "is_active": true
    },
    {
      "user_id": <creative_user_id>,
      "role": "creative",
      "is_active": true
    },
    {
      "user_id": <produksi_user_id>,
      "role": "produksi",
      "is_active": true
    },
    {
      "user_id": <editor_user_id>,
      "role": "editor",
      "is_active": true
    }
  ]
}
```

---

## 🔍 TROUBLESHOOTING

### **Error: 401 Unauthorized**
- **Penyebab**: Token tidak valid atau expired
- **Solusi**: Login ulang dan dapatkan token baru

### **Error: 403 Forbidden**
- **Penyebab**: Role tidak sesuai atau tidak memiliki akses
- **Solusi**: Pastikan user memiliki role yang benar

### **Error: 404 Not Found**
- **Penyebab**: Endpoint tidak ditemukan atau ID tidak valid
- **Solusi**: Periksa URL endpoint dan pastikan ID valid

### **Error: 422 Validation Failed (Create Production Team)**

**Penyebab Umum:**
1. **Nama team sudah ada** (unique constraint)
   - Error: `The name has already been taken`
   - Solusi: Gunakan nama team yang berbeda

2. **Producer ID tidak valid**
   - Error: `The selected producer id is invalid`
   - Solusi: Pastikan `producer_id` adalah ID user yang valid dan ada di database

3. **Created By tidak valid** (jika dikirim)
   - Error: `The selected created by is invalid`
   - Solusi: Hapus field `created_by` dari request (akan auto dari auth) atau pastikan ID valid

4. **Field yang tidak diharapkan**
   - Error: `The field is not allowed`
   - Solusi: Hanya kirim field: `name`, `description`, `producer_id`, `created_by` (optional)

**Request Body yang Benar:**
```json
POST /api/live-tv/production-teams
{
  "name": "Tim Produksi Musik A",  // Required, unique
  "description": "Tim untuk program musik",  // Optional
  "producer_id": 5,  // Required, must exist in users table
  "created_by": 1  // Optional, auto dari auth jika tidak dikirim
}
```

**Cara Debug:**
1. Cek response error untuk detail field yang salah
2. Cek log Laravel: `storage/logs/laravel.log`
3. Verifikasi `producer_id` ada di database:
   ```sql
   SELECT id, name, email FROM users WHERE id = <producer_id>
   ```
4. Cek apakah nama team sudah ada:
   ```sql
   SELECT id, name FROM production_teams WHERE name = '<nama_team>'
   ```

### **Error: 422 Validation Failed (Umum)**
- **Penyebab**: Data yang dikirim tidak valid
- **Solusi**: Periksa request body, pastikan semua field required ada dan valid

### **Error: 500 Internal Server Error**
- **Penyebab**: Error di server (database, logic, dll)
- **Solusi**: 
  - Periksa log di `storage/logs/laravel.log`
  - Pastikan migration sudah dijalankan
  - Periksa database connection

### **Episode tidak auto-generate**
- **Penyebab**: Method `generateEpisodes()` tidak dipanggil
- **Solusi**: Pastikan `ProgramWorkflowService::createProgram()` dipanggil

### **Notification tidak terkirim**
- **Penyebab**: Notification service error atau user tidak ditemukan
- **Solusi**: 
  - Periksa log notification
  - Pastikan user target ada di database
  - Periksa production team assignment

### **Status tidak berubah**
- **Penyebab**: Workflow state tidak update atau validation gagal
- **Solusi**: 
  - Periksa status sebelumnya (harus sesuai requirement)
  - Periksa role validation
  - Periksa workflow state service

---

## ✅ CHECKLIST TESTING

### **Setup**
- [ ] Semua user test account sudah dibuat
- [ ] Production team sudah dibuat
- [ ] Database migration sudah dijalankan
- [ ] Token authentication berfungsi

### **Phase 1: Setup Program**
- [ ] Manager Program bisa create program
- [ ] 53 episodes auto-generate
- [ ] Assign production team berfungsi
- [ ] Submit program berfungsi
- [ ] Manager Broadcasting bisa approve program

### **Phase 2: Music Arrangement**
- [ ] Music Arranger bisa create arrangement
- [ ] Song proposal bisa di-submit
- [ ] Producer bisa approve/reject song proposal
- [ ] Music Arranger bisa accept work
- [ ] Upload arrangement file berfungsi
- [ ] Submit arrangement berfungsi
- [ ] Producer bisa approve arrangement

### **Phase 3: Recording**
- [ ] Recording task auto-created setelah arrangement approved
- [ ] Sound Engineer bisa accept work
- [ ] Start recording berfungsi
- [ ] Upload recording file berfungsi
- [ ] Complete recording berfungsi
- [ ] Producer bisa review recording

### **Phase 4: Creative Work**
- [ ] Creative work auto-created setelah arrangement approved
- [ ] Creative bisa accept work
- [ ] Update creative work berfungsi
- [ ] Submit creative work berfungsi
- [ ] Producer bisa approve creative work

### **Phase 5-8: Production, Editing, QC, Broadcasting**
- [ ] Semua workflow berjalan sesuai flow
- [ ] Status update dengan benar
- [ ] Notification terkirim ke role yang tepat
- [ ] File upload berfungsi
- [ ] Schedule approval berfungsi

### **Security & Performance**
- [ ] Rate limiting berfungsi
- [ ] Audit logging tercatat
- [ ] Query optimization bekerja (tidak ada N+1 query)
- [ ] Caching berfungsi (jika diimplementasikan)

---

## 📝 CATATAN PENTING

1. **Testing harus dilakukan secara berurutan** sesuai flow di atas
2. **Setiap step harus diverifikasi** sebelum lanjut ke step berikutnya
3. **Periksa notification** di setiap step untuk memastikan workflow berjalan
4. **Periksa status** di database untuk memastikan update berjalan
5. **Periksa log** jika ada error untuk troubleshooting
6. **Test dengan berbagai skenario**: approve, reject, resubmit, dll

---

**Last Updated:** 2025-12-12  
**Created By:** AI Assistant

