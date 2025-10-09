# 📺 Program Regular Management System - Dokumentasi Lengkap

## 🎯 Overview

Sistem **Program Regular Management** adalah sistem baru yang menggantikan sistem program management lama dengan pendekatan yang lebih terstruktur dan sistematis. Sistem ini dirancang khusus untuk mengelola program televisi/broadcast yang tayang mingguan dengan 53 episode per tahun.

### ✨ Fitur Utama

1. **Production Teams Independen** - Tim produksi yang tidak terikat ke program tertentu
2. **Auto-Generate 53 Episodes** - Otomatis membuat 53 episode saat program dibuat
3. **Auto-Calculate Deadlines** - Deadline otomatis untuk setiap role (Editor: 7 hari, Kreatif/Produksi: 9 hari)
4. **Google Spreadsheet Integration** - Proposal program terintegrasi dengan Google Sheets
5. **Unified Approval System** - Sistem approval terpusat untuk semua workflow
6. **Role-Based Access** - 6 role wajib + Producer sebagai leader

---

## 🏗️ Struktur Database

### 1. Production Teams (`production_teams`)

Tim produksi independen yang dipimpin oleh Producer.

**Kolom:**
- `id` - Primary key
- `name` - Nama tim (unique)
- `description` - Deskripsi tim
- `producer_id` - Foreign key ke users (Producer sebagai leader)
- `is_active` - Status aktif/non-aktif
- `created_by` - Foreign key ke users
- `timestamps` - created_at, updated_at
- `deleted_at` - Soft delete

**Relasi:**
- `belongsTo` User (producer)
- `belongsTo` User (createdBy)
- `hasMany` ProductionTeamMember
- `hasMany` ProgramRegular

---

### 2. Production Team Members (`production_team_members`)

Anggota tim dengan role spesifik.

**6 Role Wajib:**
1. `kreatif` - Kreatif
2. `musik_arr` - Musik Arranger
3. `sound_eng` - Sound Engineer
4. `produksi` - Produksi
5. `editor` - Editor
6. `art_set_design` - Art & Set Design

**Kolom:**
- `id` - Primary key
- `production_team_id` - Foreign key ke production_teams
- `user_id` - Foreign key ke users
- `role` - Enum (6 role di atas)
- `is_active` - Status aktif/non-aktif
- `joined_at` - Tanggal bergabung
- `left_at` - Tanggal keluar (nullable)
- `notes` - Catatan tambahan
- `timestamps`

**Constraint:**
- Unique: `production_team_id`, `user_id`, `role` (satu user bisa punya multiple roles tapi tidak boleh duplikat)

**Relasi:**
- `belongsTo` ProductionTeam
- `belongsTo` User

---

### 3. Program Regular (`program_regular`)

Program mingguan dengan 53 episode.

**Kolom:**
- `id` - Primary key
- `name` - Nama program
- `description` - Deskripsi program
- `production_team_id` - Foreign key ke production_teams
- `manager_program_id` - Foreign key ke users (Manager Program)
- `start_date` - Tanggal mulai episode pertama
- `air_time` - Jam tayang (contoh: 19:00)
- `duration_minutes` - Durasi program (default: 60)
- `broadcast_channel` - Channel broadcast
- `status` - Enum status program
- `target_views_per_episode` - Target views per episode
- Workflow fields (submitted_by, approved_by, rejected_by, dll)
- `timestamps`
- `deleted_at`

**Status Program:**
- `draft` - Baru dibuat
- `pending_approval` - Menunggu approval
- `approved` - Sudah diapprove
- `in_production` - Sedang produksi
- `completed` - Selesai (53 episode sudah tayang)
- `cancelled` - Dibatalkan
- `rejected` - Ditolak

**Relasi:**
- `belongsTo` ProductionTeam
- `belongsTo` User (managerProgram)
- `belongsTo` User (submittedBy, approvedBy, rejectedBy)
- `hasMany` ProgramEpisode
- `hasOne` ProgramProposal
- `morphMany` ProgramApproval

---

### 4. Program Episodes (`program_episodes`)

Episode program (53 episode per program).

**Kolom:**
- `id` - Primary key
- `program_regular_id` - Foreign key ke program_regular
- `episode_number` - Nomor episode (1-53)
- `title` - Judul episode
- `description` - Deskripsi
- `air_date` - Tanggal & jam tayang
- `production_date` - Tanggal syuting
- `format_type` - 'weekly' atau 'quarterly'
- `kwartal` - 1-4 (jika quarterly)
- `pelajaran` - 1-14 (jika quarterly)
- `status` - Enum status episode
- `rundown` - Rundown episode
- `script` - Script
- `talent_data` - JSON (host, narasumber)
- `location` - Lokasi syuting
- `notes` - Catatan
- `production_notes` - JSON (catatan produksi)
- `timestamps`

**Status Episode:**
- `planning` - Masih planning
- `ready_to_produce` - Siap produksi
- `in_production` - Sedang syuting
- `post_production` - Editing
- `ready_to_air` - Siap tayang
- `aired` - Sudah tayang
- `cancelled` - Dibatalkan

**Relasi:**
- `belongsTo` ProgramRegular
- `hasMany` EpisodeDeadline
- `morphMany` ProgramApproval

---

### 5. Episode Deadlines (`episode_deadlines`)

Deadline untuk setiap role di setiap episode.

**Kolom:**
- `id` - Primary key
- `program_episode_id` - Foreign key ke program_episodes
- `role` - Enum (6 role wajib)
- `deadline_date` - Tanggal & jam deadline
- `is_completed` - Boolean
- `completed_at` - Waktu selesai
- `completed_by` - Foreign key ke users
- `notes` - Catatan
- `status` - pending/in_progress/completed/overdue/cancelled
- `reminder_sent` - Boolean (reminder sudah dikirim?)
- `reminder_sent_at` - Waktu reminder dikirim
- `timestamps`

**Aturan Deadline:**
- **Editor**: 7 hari sebelum tayang
- **Kreatif, Musik Arr, Sound Eng, Produksi, Art Set Design**: 9 hari sebelum tayang

**Contoh Perhitungan:**
```
Episode 1 tayang: 10 Januari 2025, 19:00
- Deadline Editor: 3 Januari 2025 (7 hari sebelum)
- Deadline Kreatif: 1 Januari 2025 (9 hari sebelum)

Episode 2 tayang: 17 Januari 2025, 19:00 (7 hari setelah episode 1)
- Deadline Editor: 10 Januari 2025
- Deadline Kreatif: 8 Januari 2025
```

**Relasi:**
- `belongsTo` ProgramEpisode
- `belongsTo` User (completedBy)

---

### 6. Program Proposals (`program_proposals`)

Proposal program yang terintegrasi dengan Google Spreadsheet.

**Kolom:**
- `id` - Primary key
- `program_regular_id` - Foreign key ke program_regular (nullable)
- `spreadsheet_id` - ID Google Spreadsheet
- `spreadsheet_url` - Full URL spreadsheet
- `sheet_name` - Nama sheet (default: Sheet1)
- `proposal_title` - Judul proposal
- `proposal_description` - Deskripsi
- `format_type` - 'mingguan' atau 'kwartal'
- `kwartal_data` - JSON (data kwartal jika format quarterly)
- `schedule_options` - JSON (opsi jadwal)
- `status` - Enum status proposal
- `last_synced_at` - Waktu sync terakhir
- `auto_sync` - Boolean (auto sync dari spreadsheet)
- Workflow fields (reviewed_by, review_notes, dll)
- `created_by` - Foreign key ke users
- `timestamps`
- `deleted_at`

**Status Proposal:**
- `draft` - Draft
- `submitted` - Sudah disubmit
- `under_review` - Sedang direview
- `approved` - Disetujui
- `rejected` - Ditolak
- `needs_revision` - Perlu revisi

**Relasi:**
- `belongsTo` ProgramRegular
- `belongsTo` User (reviewedBy)
- `belongsTo` User (createdBy)

---

### 7. Program Approvals (`program_approvals`)

Sistem approval terpusat untuk semua workflow.

**Kolom:**
- `id` - Primary key
- `approvable_id` - ID entity yang di-approve (polymorphic)
- `approvable_type` - Tipe entity (polymorphic)
- `approval_type` - Enum jenis approval
- `requested_by` - Foreign key ke users
- `requested_at` - Waktu request
- `request_notes` - Catatan request
- `request_data` - JSON (data yang direquest)
- `current_data` - JSON (data saat ini, untuk perbandingan)
- `status` - Enum status approval
- Reviewer fields (reviewed_by, reviewed_at, review_notes)
- Approver fields (approved_by, approved_at, approval_notes)
- Rejecter fields (rejected_by, rejected_at, rejection_notes)
- `priority` - low/normal/high/urgent
- `due_date` - Tanggal deadline approval
- `timestamps`

**Jenis Approval:**
- `program_proposal` - Approval proposal program
- `program_schedule` - Approval jadwal tayang program
- `episode_rundown` - Approval rundown episode
- `production_schedule` - Approval jadwal syuting
- `schedule_change` - Approval perubahan jadwal
- `schedule_cancellation` - Approval pembatalan jadwal
- `deadline_extension` - Approval perpanjangan deadline

**Status Approval:**
- `pending` - Menunggu review
- `reviewed` - Sudah direview
- `approved` - Disetujui
- `rejected` - Ditolak
- `cancelled` - Dibatalkan
- `auto_approved` - Auto-approved

**Relasi:**
- `morphTo` approvable
- `belongsTo` User (requestedBy, reviewedBy, approvedBy, rejectedBy)

---

## 🔌 API Endpoints

### Production Teams

#### **GET** `/api/production-teams`
List semua tim produksi.

**Query Parameters:**
- `is_active` (boolean) - Filter by status aktif
- `producer_id` (int) - Filter by producer
- `ready_for_production` (boolean) - Filter tim yang ready (punya semua role)
- `search` (string) - Search by nama
- `per_page` (int, default: 15) - Jumlah per halaman
- `sort_by` (string, default: created_at) - Sorting field
- `sort_order` (string, default: desc) - asc/desc

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Tim Producer 1",
        "description": "Tim Produksi Utama",
        "producer_id": 5,
        "is_active": true,
        "producer": {
          "id": 5,
          "name": "John Producer"
        },
        "members": [...],
        "roles_summary": {
          "kreatif": {"label": "Kreatif", "count": 2, "has_member": true},
          "musik_arr": {"label": "Musik Arranger", "count": 1, "has_member": true},
          ...
        },
        "missing_roles": [],
        "ready_for_production": true
      }
    ],
    "total": 10
  },
  "message": "Production teams retrieved successfully"
}
```

---

#### **POST** `/api/production-teams`
Buat tim produksi baru.

**Request Body:**
```json
{
  "name": "Tim Producer 2",
  "description": "Tim Produksi Kedua",
  "producer_id": 6,
  "created_by": 1,
  "members": [
    {"user_id": 10, "role": "kreatif", "notes": "Lead Kreatif"},
    {"user_id": 11, "role": "musik_arr"},
    {"user_id": 12, "role": "sound_eng"},
    {"user_id": 13, "role": "produksi"},
    {"user_id": 14, "role": "editor"},
    {"user_id": 15, "role": "art_set_design"}
  ]
}
```

**Validasi:**
- `name` - Required, unique
- `producer_id` - Required, exists in users
- `members` - Required, min 6 (harus ada semua role)
- Setiap role wajib harus ada minimal 1 orang

**Response:** Tim yang baru dibuat dengan relationships

---

#### **GET** `/api/production-teams/{id}`
Detail tim produksi.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Tim Producer 1",
    "producer": {...},
    "members": [
      {
        "id": 1,
        "user": {"id": 10, "name": "User 1"},
        "role": "kreatif",
        "role_label": "Kreatif",
        "joined_at": "2025-01-01",
        "is_active": true
      },
      ...
    ],
    "programs": [...],
    "roles_summary": {...},
    "missing_roles": [],
    "ready_for_production": true
  }
}
```

---

#### **PUT** `/api/production-teams/{id}`
Update tim produksi.

**Request Body:**
```json
{
  "name": "Tim Producer 1 Updated",
  "description": "Updated description",
  "producer_id": 6,
  "is_active": false
}
```

---

#### **DELETE** `/api/production-teams/{id}`
Hapus tim produksi (soft delete).

**Validasi:**
- Tidak bisa delete jika ada program aktif (status: approved/in_production)

---

#### **POST** `/api/production-teams/{id}/members`
Tambah member ke tim.

**Request Body:**
```json
{
  "members": [
    {"user_id": 20, "role": "kreatif", "notes": "Additional kreatif"},
    {"user_id": 21, "role": "editor"}
  ]
}
```

---

#### **DELETE** `/api/production-teams/{id}/members`
Hapus member dari tim.

**Request Body:**
```json
{
  "members": [
    {"user_id": 20, "role": "kreatif"},
    {"user_id": 21, "role": "editor"}
  ]
}
```

**Validasi:**
- Tidak bisa hapus jika dia adalah orang terakhir untuk role tersebut

---

#### **GET** `/api/production-teams/{id}/available-users`
List users yang available untuk ditambahkan ke tim (belum jadi member).

---

#### **GET** `/api/production-teams/producers`
List semua user yang bisa jadi producer.

---

### Program Regular

#### **GET** `/api/program-regular`
List semua program regular.

**Query Parameters:**
- `status` (string) - Filter by status
- `production_team_id` (int) - Filter by tim
- `manager_program_id` (int) - Filter by manager
- `search` (string) - Search by nama
- `per_page` (int, default: 15)
- `sort_by` (string, default: created_at)
- `sort_order` (string, default: desc)

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Program Kebaktian Mingguan",
        "status": "in_production",
        "production_team": {...},
        "manager_program": {...},
        "start_date": "2025-01-10",
        "air_time": "19:00",
        "progress_percentage": 25.5,
        "next_episode": {...},
        "is_completed": false
      }
    ]
  }
}
```

---

#### **POST** `/api/program-regular`
Buat program baru (auto-generate 53 episodes + deadlines).

**Request Body:**
```json
{
  "name": "Program Kebaktian Mingguan",
  "description": "Program ibadah mingguan Hope Channel",
  "production_team_id": 1,
  "manager_program_id": 2,
  "start_date": "2025-01-10",
  "air_time": "19:00",
  "duration_minutes": 60,
  "broadcast_channel": "Hope Channel Indonesia",
  "target_views_per_episode": 10000
}
```

**Validasi:**
- Tim produksi harus ready (punya semua role)
- `start_date` harus >= hari ini

**Proses:**
1. Create program
2. Auto-generate 53 episodes (setiap minggu dari start_date)
3. Auto-generate deadlines untuk setiap episode (6 role × 53 episode = 318 deadlines)

**Response:**
```json
{
  "success": true,
  "data": {...},
  "message": "Program created successfully with 53 episodes and deadlines",
  "total_episodes": 53
}
```

---

#### **GET** `/api/program-regular/{id}`
Detail program dengan statistik.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Kebaktian Mingguan",
    "production_team": {
      "id": 1,
      "name": "Tim Producer 1",
      "producer": {...},
      "members": [...]
    },
    "episodes": [...],
    "proposal": {...},
    "progress_percentage": 25.5,
    "next_episode": {...}
  },
  "statistics": {
    "total_episodes": 53,
    "aired_episodes": 13,
    "upcoming_episodes": 40,
    "overdue_episodes": 0,
    "progress_percentage": 24.53
  }
}
```

---

#### **PUT** `/api/program-regular/{id}`
Update program.

**Validasi:**
- Tidak bisa update jika status: completed/cancelled

---

#### **DELETE** `/api/program-regular/{id}`
Hapus program (soft delete).

**Validasi:**
- Tidak bisa delete jika status: approved/in_production

---

#### **POST** `/api/program-regular/{id}/submit-approval`
Submit program untuk approval.

**Request Body:**
```json
{
  "user_id": 2,
  "notes": "Program siap untuk direview"
}
```

**Validasi:**
- Hanya program dengan status `draft` yang bisa disubmit

---

#### **POST** `/api/program-regular/{id}/approve`
Approve program.

**Request Body:**
```json
{
  "user_id": 3,
  "notes": "Program disetujui untuk produksi"
}
```

**Validasi:**
- Hanya program dengan status `pending_approval` yang bisa diapprove

---

#### **POST** `/api/program-regular/{id}/reject`
Reject program.

**Request Body:**
```json
{
  "user_id": 3,
  "notes": "Budget tidak mencukupi"
}
```

---

#### **GET** `/api/program-regular/{id}/dashboard`
Dashboard program dengan statistik lengkap.

**Response:**
```json
{
  "success": true,
  "data": {
    "program": {...},
    "episodes_stats": {
      "total": 53,
      "aired": 13,
      "upcoming": 40,
      "overdue": 0,
      "progress_percentage": 24.53
    },
    "deadlines_stats": {
      "total": 318,
      "completed": 78,
      "overdue": 5,
      "completion_percentage": 24.53
    },
    "next_episodes": [...],
    "recent_aired": [...]
  }
}
```

---

#### **GET** `/api/program-regular/available-teams`
List tim produksi yang available (ready for production).

---

### Program Episodes

#### **GET** `/api/program-episodes`
List episodes.

**Query Parameters:**
- `program_regular_id` (int) - Filter by program
- `status` (string) - Filter by status
- `format_type` (string) - weekly/quarterly
- `upcoming` (boolean) - Filter upcoming episodes
- `aired` (boolean) - Filter aired episodes
- `overdue` (boolean) - Filter overdue episodes
- `per_page` (int, default: 15)
- `sort_by` (string, default: episode_number)
- `sort_order` (string, default: asc)

---

#### **GET** `/api/program-episodes/{id}`
Detail episode dengan deadlines.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "episode_number": 1,
    "title": "Episode 1",
    "air_date": "2025-01-10 19:00:00",
    "status": "planning",
    "program_regular": {...},
    "deadlines": [
      {
        "id": 1,
        "role": "editor",
        "role_label": "Editor",
        "deadline_date": "2025-01-03 19:00:00",
        "is_completed": false,
        "status": "pending",
        "days_until_deadline": 5
      },
      {
        "id": 2,
        "role": "kreatif",
        "role_label": "Kreatif",
        "deadline_date": "2025-01-01 19:00:00",
        "is_completed": true,
        "completed_at": "2024-12-30 10:00:00",
        "completed_by": {...}
      },
      ...
    ],
    "days_until_air": 12,
    "progress_percentage": 16.67,
    "is_overdue": false,
    "overdue_deadlines": [],
    "upcoming_deadlines": [...]
  }
}
```

---

#### **PUT** `/api/program-episodes/{id}`
Update episode.

**Request Body:**
```json
{
  "title": "Episode 1 - Tema Kasih",
  "description": "Episode tentang kasih Tuhan",
  "production_date": "2025-01-05",
  "rundown": "Rundown lengkap episode...",
  "script": "Script episode...",
  "talent_data": {
    "host": "John Doe",
    "narasumber": ["Jane Smith", "Bob Johnson"]
  },
  "location": "Studio A",
  "notes": "Catatan khusus"
}
```

**Validasi:**
- Tidak bisa update jika status: aired

---

#### **PATCH** `/api/program-episodes/{id}/status`
Update status episode.

**Request Body:**
```json
{
  "status": "in_production"
}
```

---

#### **GET** `/api/program-episodes/{id}/deadlines`
List semua deadline episode.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "role": "editor",
      "role_label": "Editor",
      "deadline_date": "2025-01-03 19:00:00",
      "is_completed": false,
      "is_overdue": false,
      "days_until_deadline": 5,
      "status": "pending"
    },
    ...
  ]
}
```

---

#### **POST** `/api/program-episodes/{episodeId}/deadlines/{deadlineId}/complete`
Mark deadline sebagai completed.

**Request Body:**
```json
{
  "user_id": 10,
  "notes": "Editing selesai"
}
```

**Validasi:**
- Deadline belum completed sebelumnya

---

#### **POST** `/api/program-episodes/{id}/submit-rundown`
Submit rundown untuk approval.

**Request Body:**
```json
{
  "user_id": 2,
  "notes": "Rundown episode 1 siap direview"
}
```

**Validasi:**
- Rundown tidak boleh kosong

**Response:** Episode + Approval record yang baru dibuat

---

#### **GET** `/api/program-episodes/upcoming?days=7`
List upcoming episodes (default: 7 hari ke depan).

---

### Program Proposals

#### **GET** `/api/program-proposals`
List proposals.

**Query Parameters:**
- `status` (string)
- `program_regular_id` (int)
- `format_type` (string)
- `search` (string)
- `per_page` (int, default: 15)

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "proposal_title": "Proposal Program Kebaktian 2025",
        "format_type": "mingguan",
        "status": "approved",
        "spreadsheet_id": "19eF1kyIALqQtDxvUvA_Uz0LD1oktMKx8DOTLcH8c2tc",
        "full_spreadsheet_url": "https://docs.google.com/spreadsheets/d/...",
        "embedded_url": "https://docs.google.com/spreadsheets/d/.../edit?widget=true...",
        "needs_sync": false,
        "last_synced_at": "2025-01-08 10:00:00"
      }
    ]
  }
}
```

---

#### **POST** `/api/program-proposals`
Buat proposal baru.

**Request Body:**
```json
{
  "program_regular_id": 1,
  "spreadsheet_id": "19eF1kyIALqQtDxvUvA_Uz0LD1oktMKx8DOTLcH8c2tc",
  "spreadsheet_url": "https://docs.google.com/spreadsheets/d/...",
  "sheet_name": "Sheet1",
  "proposal_title": "Proposal Program Kebaktian 2025",
  "proposal_description": "Proposal lengkap program kebaktian mingguan",
  "format_type": "mingguan",
  "auto_sync": true,
  "created_by": 2
}
```

---

#### **GET** `/api/program-proposals/{id}`
Detail proposal.

---

#### **PUT** `/api/program-proposals/{id}`
Update proposal.

**Validasi:**
- Tidak bisa update jika status: approved/rejected

---

#### **DELETE** `/api/program-proposals/{id}`
Hapus proposal.

**Validasi:**
- Tidak bisa delete jika status: approved

---

#### **POST** `/api/program-proposals/{id}/sync`
Manual sync data dari Google Spreadsheet.

**Response:**
```json
{
  "success": true,
  "data": {...},
  "message": "Proposal synced successfully from spreadsheet",
  "last_synced_at": "2025-01-09 14:30:00"
}
```

---

#### **GET** `/api/program-proposals/{id}/embedded-view`
Get data untuk embedded spreadsheet view.

**Response:**
```json
{
  "success": true,
  "data": {
    "proposal_id": 1,
    "proposal_title": "Proposal Program Kebaktian 2025",
    "spreadsheet_id": "19eF1kyIALqQtDxvUvA_Uz0LD1oktMKx8DOTLcH8c2tc",
    "embedded_url": "https://docs.google.com/spreadsheets/d/.../edit?widget=true&headers=false&embedded=true",
    "full_url": "https://docs.google.com/spreadsheets/d/...",
    "last_synced_at": "2025-01-08 10:00:00",
    "auto_sync": true
  }
}
```

---

#### **POST** `/api/program-proposals/{id}/submit`
Submit proposal untuk review.

---

#### **POST** `/api/program-proposals/{id}/review`
Mark proposal sebagai under review.

**Request Body:**
```json
{
  "reviewer_id": 3
}
```

---

#### **POST** `/api/program-proposals/{id}/approve`
Approve proposal.

**Request Body:**
```json
{
  "reviewer_id": 3,
  "notes": "Proposal disetujui"
}
```

---

#### **POST** `/api/program-proposals/{id}/reject`
Reject proposal.

**Request Body:**
```json
{
  "reviewer_id": 3,
  "notes": "Budget tidak mencukupi"
}
```

---

#### **POST** `/api/program-proposals/{id}/request-revision`
Request revision untuk proposal.

**Request Body:**
```json
{
  "reviewer_id": 3,
  "notes": "Mohon tambahkan detail budget"
}
```

---

### Program Approvals

#### **GET** `/api/program-approvals`
List approvals.

**Query Parameters:**
- `status` (string)
- `approval_type` (string)
- `priority` (string)
- `requested_by` (int)
- `approved_by` (int)
- `per_page` (int, default: 15)

---

#### **POST** `/api/program-approvals`
Create approval request.

**Request Body:**
```json
{
  "approvable_id": 1,
  "approvable_type": "App\\Models\\ProgramRegular",
  "approval_type": "program_schedule",
  "requested_by": 2,
  "request_notes": "Request approval jadwal tayang",
  "request_data": {"new_air_time": "20:00"},
  "current_data": {"air_time": "19:00"},
  "priority": "normal",
  "due_date": "2025-01-15"
}
```

**Approval Types:**
- `program_proposal`
- `program_schedule`
- `episode_rundown`
- `production_schedule`
- `schedule_change`
- `schedule_cancellation`
- `deadline_extension`

---

#### **GET** `/api/program-approvals/{id}`
Detail approval.

---

#### **PUT** `/api/program-approvals/{id}`
Update approval request.

**Validasi:**
- Tidak bisa update jika status: approved/rejected/cancelled

---

#### **POST** `/api/program-approvals/{id}/review`
Mark approval sebagai reviewed.

**Request Body:**
```json
{
  "reviewer_id": 3,
  "notes": "Sedang direview oleh manager"
}
```

---

#### **POST** `/api/program-approvals/{id}/approve`
Approve request.

**Request Body:**
```json
{
  "approver_id": 3,
  "notes": "Disetujui"
}
```

---

#### **POST** `/api/program-approvals/{id}/reject`
Reject request.

**Request Body:**
```json
{
  "rejecter_id": 3,
  "notes": "Jadwal bentrok dengan program lain"
}
```

---

#### **POST** `/api/program-approvals/{id}/cancel`
Cancel approval request (by requester).

---

#### **GET** `/api/program-approvals/pending`
List pending approvals.

**Query Parameters:**
- `approval_type` (string)
- `priority` (string)
- `per_page` (int, default: 15)

---

#### **GET** `/api/program-approvals/overdue`
List overdue approvals (melewati due_date).

---

#### **GET** `/api/program-approvals/urgent`
List urgent approvals.

---

#### **GET** `/api/program-approvals/history`
Approval history (approved/rejected/cancelled).

**Query Parameters:**
- `approval_type` (string)
- `per_page` (int, default: 15)

---

## 🚀 Cara Menggunakan Sistem

### 1. Setup Production Team

**Langkah:**

1. **Buat Tim Baru**
```bash
POST /api/production-teams
{
  "name": "Tim Producer 1",
  "description": "Tim Produksi Utama",
  "producer_id": 5,
  "created_by": 1,
  "members": [
    {"user_id": 10, "role": "kreatif"},
    {"user_id": 11, "role": "musik_arr"},
    {"user_id": 12, "role": "sound_eng"},
    {"user_id": 13, "role": "produksi"},
    {"user_id": 14, "role": "editor"},
    {"user_id": 15, "role": "art_set_design"}
  ]
}
```

2. **Verifikasi Tim Ready**
```bash
GET /api/production-teams/1
# Check: ready_for_production = true
```

---

### 2. Buat Proposal Program

1. **Buat Proposal dengan Google Spreadsheet**
```bash
POST /api/program-proposals
{
  "spreadsheet_id": "19eF1kyIALqQtDxvUvA_Uz0LD1oktMKx8DOTLcH8c2tc",
  "spreadsheet_url": "https://docs.google.com/spreadsheets/d/...",
  "proposal_title": "Proposal Program Kebaktian 2025",
  "format_type": "mingguan",
  "auto_sync": true,
  "created_by": 2
}
```

2. **Submit Proposal**
```bash
POST /api/program-proposals/1/submit
```

3. **Approve Proposal**
```bash
POST /api/program-proposals/1/approve
{
  "reviewer_id": 3,
  "notes": "Proposal disetujui"
}
```

---

### 3. Buat Program Regular

1. **Buat Program (Auto-generate 53 Episodes)**
```bash
POST /api/program-regular
{
  "name": "Program Kebaktian Mingguan",
  "description": "Program ibadah mingguan Hope Channel",
  "production_team_id": 1,
  "manager_program_id": 2,
  "start_date": "2025-01-10",
  "air_time": "19:00",
  "duration_minutes": 60,
  "broadcast_channel": "Hope Channel Indonesia"
}
```

**Sistem akan otomatis:**
- Create 53 episodes (1 episode per minggu)
- Create 318 deadlines (6 role × 53 episode)
- Set deadline otomatis:
  - Editor: 7 hari sebelum tayang
  - Kreatif/Produksi/dll: 9 hari sebelum tayang

2. **Submit Program untuk Approval**
```bash
POST /api/program-regular/1/submit-approval
{
  "user_id": 2,
  "notes": "Program siap untuk direview"
}
```

3. **Approve Program**
```bash
POST /api/program-regular/1/approve
{
  "user_id": 3,
  "notes": "Program disetujui untuk produksi"
}
```

---

### 4. Manage Episodes

1. **Lihat Episode yang Akan Datang**
```bash
GET /api/program-episodes?program_regular_id=1&upcoming=true&per_page=10
```

2. **Update Episode (Rundown, Script, dll)**
```bash
PUT /api/program-episodes/1
{
  "title": "Episode 1 - Tema Kasih",
  "rundown": "Rundown lengkap...",
  "script": "Script episode...",
  "talent_data": {
    "host": "John Doe",
    "narasumber": ["Jane Smith"]
  }
}
```

3. **Submit Rundown untuk Approval**
```bash
POST /api/program-episodes/1/submit-rundown
{
  "user_id": 2,
  "notes": "Rundown siap direview"
}
```

4. **Mark Deadline Completed**
```bash
POST /api/program-episodes/1/deadlines/5/complete
{
  "user_id": 14,
  "notes": "Editing selesai"
}
```

5. **Update Status Episode**
```bash
PATCH /api/program-episodes/1/status
{
  "status": "in_production"
}
```

---

### 5. Monitor Dashboard

**Get Program Dashboard**
```bash
GET /api/program-regular/1/dashboard
```

**Response:**
```json
{
  "episodes_stats": {
    "total": 53,
    "aired": 13,
    "upcoming": 40,
    "overdue": 0,
    "progress_percentage": 24.53
  },
  "deadlines_stats": {
    "total": 318,
    "completed": 78,
    "overdue": 5,
    "completion_percentage": 24.53
  },
  "next_episodes": [...],
  "recent_aired": [...]
}
```

---

## 📊 Flow Diagram

### Program Creation Flow

```
1. Manager Program membuat Tim Produksi
   ↓
2. Tambah Producer + 6 Role Wajib
   ↓
3. Verifikasi Tim Ready (hasAllRequiredRoles)
   ↓
4. Buat Proposal Program (Google Spreadsheet)
   ↓
5. Submit Proposal → Manager Broadcasting
   ↓
6. Manager Broadcasting Approve Proposal
   ↓
7. Buat Program Regular
   ↓ (AUTO)
8. Generate 53 Episodes
   ↓ (AUTO)
9. Generate 318 Deadlines (6 role × 53 episode)
   ↓
10. Submit Program untuk Approval
    ↓
11. Manager Broadcasting Approve Program
    ↓
12. Status Program: "approved" → "in_production"
```

### Episode Production Flow

```
1. Episode Status: "planning"
   ↓
2. Kreatif membuat Rundown
   ↓
3. Submit Rundown untuk Approval
   ↓
4. Manager Broadcasting Approve Rundown
   ↓
5. Status: "ready_to_produce"
   ↓
6. Jadwal Syuting ditentukan
   ↓
7. Status: "in_production" (Syuting)
   ↓
8. Selesai Syuting
   ↓
9. Editor edit (Deadline: 7 hari sebelum tayang)
   ↓
10. Kreatif/Produksi/dll review (Deadline: 9 hari sebelum tayang)
    ↓
11. All Deadlines Completed
    ↓
12. Status: "ready_to_air"
    ↓
13. Tayang
    ↓
14. Status: "aired"
```

---

## 🔔 Notifications & Reminders

### Auto Notifications

Sistem akan otomatis mengirim notifikasi untuk:

1. **Deadline Reminder** (1 hari sebelum deadline)
2. **Overdue Deadline Alert** (saat deadline terlewat)
3. **Episode Air Reminder** (3 hari sebelum tayang)
4. **Approval Request Notification** (saat ada approval request)
5. **Approval Decision Notification** (approved/rejected)

---

## 💡 Tips & Best Practices

### Production Team

1. **Satu Producer = Satu Tim**
   - Jika ada 2 Producer, buat 2 tim terpisah
   - Setiap tim independen dan bisa handle multiple program

2. **6 Role Wajib**
   - Pastikan setiap tim punya minimal 1 orang untuk setiap role
   - Boleh ada lebih dari 1 orang per role
   - Satu orang bisa punya multiple roles

3. **Tim Aktif/Non-Aktif**
   - Set `is_active = false` jika tim tidak aktif
   - Sistem akan filter hanya tim aktif untuk program baru

---

### Program Regular

1. **Start Date Planning**
   - Pilih start_date minimal 2-3 minggu dari sekarang
   - Ini memberikan waktu untuk planning episode pertama

2. **53 Episodes = 1 Tahun**
   - Program akan otomatis generate 53 episode
   - Episode tayang setiap minggu pada hari & jam yang sama

3. **Deadline Calculation**
   - Editor: 7 hari sebelum tayang
   - Role lain: 9 hari sebelum tayang
   - Deadline sudah auto-calculated saat program dibuat

---

### Episodes & Deadlines

1. **Episode Planning**
   - Isi rundown & script minimal 2 minggu sebelum syuting
   - Submit rundown untuk approval sebelum status jadi "ready_to_produce"

2. **Deadline Management**
   - Monitor overdue deadlines di dashboard
   - Mark deadline completed saat pekerjaan selesai
   - Request deadline extension jika butuh lebih banyak waktu

3. **Episode Status Updates**
   - Update status episode sesuai progress
   - Jangan skip status (ikuti urutan: planning → ready_to_produce → in_production → post_production → ready_to_air → aired)

---

### Approvals

1. **Approval Types**
   - Program Proposal: Approval budget & concept
   - Program Schedule: Approval jadwal tayang
   - Episode Rundown: Approval rundown episode
   - Schedule Change: Approval perubahan jadwal syuting

2. **Priority Levels**
   - `urgent`: Butuh approval dalam 24 jam
   - `high`: Butuh approval dalam 3 hari
   - `normal`: Approval biasa
   - `low`: Tidak mendesak

3. **Approval Flow**
   - pending → reviewed → approved/rejected
   - Bisa cancel approval request jika belum approved/rejected

---

## 🔧 Database Migration

Untuk menjalankan sistem baru, jalankan migrations:

```bash
php artisan migrate
```

**Migrations yang akan dijalankan:**
1. `2025_10_09_000001_create_production_teams_table.php`
2. `2025_10_09_000002_create_production_team_members_table.php`
3. `2025_10_09_000003_create_program_regular_table.php`
4. `2025_10_09_000004_create_program_episodes_table.php`
5. `2025_10_09_000005_create_episode_deadlines_table.php`
6. `2025_10_09_000006_create_program_proposals_table.php`
7. `2025_10_09_000007_create_program_approvals_table.php`

---

## 🎨 Frontend Integration

### Google Spreadsheet Embedded View

Untuk menampilkan Google Spreadsheet di frontend:

```javascript
// Get embedded view data
const response = await fetch('/api/program-proposals/1/embedded-view');
const data = await response.json();

// Display in iframe
<iframe 
  src={data.data.embedded_url}
  width="100%"
  height="600px"
  frameborder="0"
></iframe>
```

---

## 📝 TODO: Google Sheets API Integration

Saat ini, fungsi `syncFromSpreadsheet()` di `ProgramProposal` model hanya update `last_synced_at`. 

Untuk implementasi lengkap, perlu:

1. **Setup Google Sheets API**
   - Enable Google Sheets API di Google Cloud Console
   - Create Service Account
   - Download credentials JSON

2. **Install Google Client Library**
```bash
composer require google/apiclient
```

3. **Implement Sync Function**
```php
public function syncFromSpreadsheet(): bool
{
    $client = new \Google_Client();
    $client->setAuthConfig(storage_path('credentials.json'));
    $client->addScope(\Google_Service_Sheets::SPREADSHEETS_READONLY);
    
    $service = new \Google_Service_Sheets($client);
    $range = $this->sheet_name . '!A1:Z1000';
    
    $response = $service->spreadsheets_values->get($this->spreadsheet_id, $range);
    $values = $response->getValues();
    
    // Parse values and update proposal data
    // ...
    
    $this->update(['last_synced_at' => now()]);
    return true;
}
```

---

## ✅ Summary

Sistem **Program Regular Management** adalah sistem lengkap untuk mengelola program broadcast dengan fitur:

✅ Production Teams independen dengan 6 role wajib  
✅ Auto-generate 53 episodes + 318 deadlines  
✅ Google Spreadsheet integration untuk proposals  
✅ Unified approval system  
✅ Role-based access control  
✅ Dashboard & statistics  
✅ Notifications & reminders  

**Status:** ✅ **SISTEM SIAP DIGUNAKAN**

Untuk pertanyaan atau bantuan, hubungi tim development. 🚀

