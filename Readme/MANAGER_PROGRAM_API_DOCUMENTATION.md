# 📋 Dokumentasi API Manager Program - Program Musik

## 🎯 Overview

Manager Program memiliki otoritas penuh untuk mengelola program musik dari awal hingga penayangan. Dokumentasi ini menjelaskan semua API endpoints yang tersedia untuk role Manager Program.

**Base URL:** `/api/live-tv`

**Authentication:** Semua endpoint memerlukan `Bearer Token` (Sanctum)

---

## 📚 Daftar Isi

1. [Dashboard & Monitoring](#1-dashboard--monitoring)
2. [Membuat Program](#2-membuat-program)
3. [Membagi Kelompok Team Kerja](#3-membagi-kelompok-team-kerja)
4. [Generate Episode & Deadline](#4-generate-episode--deadline)
5. [Edit Deadline](#5-edit-deadline)
6. [Opsi Jadwal Tayang](#6-opsi-jadwal-tayang)
7. [Target Pencapaian Views](#7-target-pencapaian-views)
8. [Monitoring Pekerjaan](#8-monitoring-pekerjaan)
9. [Menutup Program](#9-menutup-program)
10. [Intervensi Approval & Jadwal](#10-intervensi-approval--jadwal)
11. [Quality Control Monitoring](#11-quality-control-monitoring)
12. [Rundown Edit Approval](#12-rundown-edit-approval)

---

## 1. Dashboard & Monitoring

### 1.1 Get Dashboard

**Endpoint:** `GET /api/live-tv/manager-program/dashboard`

**Description:** Mendapatkan data dashboard Manager Program termasuk statistics, upcoming deadlines, dan recent activities.

**Response:**
```json
{
  "success": true,
  "data": {
    "statistics": {
      "total_programs": 10,
      "active_programs": 5,
      "draft_programs": 2,
      "total_episodes": 530,
      "pending_approvals": 3,
      "budget_requests": 1
    },
    "programs": [...],
    "upcoming_deadlines": [
      {
        "id": 1,
        "episode_id": 1,
        "role": "editor",
        "deadline_date": "2025-12-17 10:00:00",
        "is_completed": false,
        "episode": {
          "id": 1,
          "episode_number": 1,
          "title": "Episode 1",
          "program": {
            "id": 1,
            "name": "Program Musik Live"
          }
        }
      }
    ],
    "recent_activities": [...]
  },
  "message": "Dashboard data retrieved successfully"
}
```

---

## 2. Membuat Program

### 2.1 Create Program

**Endpoint:** `POST /api/live-tv/programs`

**Description:** Membuat program baru dengan proposal file (lampiran).

**Request Body (Form Data):**
```
name: string (required)
description: string (optional)
manager_program_id: integer (required) - ID Manager Program
production_team_id: integer (optional)
start_date: date (required) - Format: YYYY-MM-DD
air_time: time (required) - Format: HH:mm (contoh: 19:00)
duration_minutes: integer (optional, default: 60)
broadcast_channel: string (optional)
target_views_per_episode: integer (optional)
proposal_file: file (optional) - PDF/DOC/DOCX, max 10MB
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Live",
    "description": "Program musik live setiap minggu",
    "status": "draft",
    "manager_program_id": 1,
    "production_team_id": 1,
    "start_date": "2025-01-01",
    "air_time": "19:00:00",
    "duration_minutes": 60,
    "broadcast_channel": "Hope Channel",
    "target_views_per_episode": 10000,
    "proposal_file_path": "programs/proposals/1234567890_proposal.pdf",
    "proposal_file_name": "proposal.pdf",
    "proposal_file_size": 1024000,
    "proposal_file_mime_type": "application/pdf",
    "episodes": [], // Akan auto-generate 53 episodes
    "managerProgram": {...},
    "productionTeam": {...}
  },
  "message": "Program created successfully with 53 episodes generated"
}
```

**Notes:**
- Setelah program dibuat, sistem akan **otomatis generate 53 episodes** (1 tahun)
- Setiap episode akan **otomatis generate deadlines**:
  - Editor: 7 hari sebelum tayang
  - Creative, Production, Music Arranger, Sound Engineer: 9 hari sebelum tayang

---

## 3. Membagi Kelompok Team Kerja

### 3.1 Assign Team ke Episode

**Endpoint:** `POST /api/live-tv/manager-program/episodes/{episodeId}/assign-team`

**Description:** Membagi/mengganti production team untuk episode tertentu.

**Request Body:**
```json
{
  "production_team_id": 1,
  "notes": "Assign team untuk episode ini"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "episode_id": 1,
    "production_team_id": 1,
    "team_assigned_at": "2025-12-10 10:00:00",
    "team_assigned_by": 1,
    "team_assignment_notes": "Assign team untuk episode ini"
  },
  "message": "Team assigned successfully"
}
```

**Notes:**
- Producer dapat mengganti semua kelompok kerja (kreatif, music arranger, sound engineer, produksi, editor) melalui production team assignment
- Notifikasi otomatis dikirim ke semua team members

---

## 4. Generate Episode & Deadline

### 4.1 Generate Episodes (Manual)

**Endpoint:** `POST /api/live-tv/manager-program/programs/{programId}/generate-episodes`

**Description:** Generate episodes secara manual dengan custom parameters.

**Request Body:**
```json
{
  "number_of_episodes": 53,
  "start_date": "2025-01-01",
  "interval_days": 7,
  "regenerate": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "program_id": 1,
    "episodes_generated": 53,
    "episodes": [
      {
        "id": 1,
        "episode_number": 1,
        "title": "Episode 1",
        "air_date": "2025-01-01",
        "deadlines": [
          {
            "id": 1,
            "role": "editor",
            "deadline_date": "2024-12-25 19:00:00", // 7 hari sebelum
            "auto_generated": true
          },
          {
            "id": 2,
            "role": "kreatif",
            "deadline_date": "2024-12-23 19:00:00", // 9 hari sebelum
            "auto_generated": true
          },
          {
            "id": 3,
            "role": "produksi",
            "deadline_date": "2024-12-23 19:00:00", // 9 hari sebelum
            "auto_generated": true
          },
          {
            "id": 4,
            "role": "musik_arr",
            "deadline_date": "2024-12-23 19:00:00", // 9 hari sebelum
            "auto_generated": true
          },
          {
            "id": 5,
            "role": "sound_eng",
            "deadline_date": "2024-12-23 19:00:00", // 9 hari sebelum
            "auto_generated": true
          }
        ]
      }
    ]
  },
  "message": "Episodes generated successfully"
}
```

**Auto-Deadline Rules:**
- **Editor:** 7 hari sebelum tayang
- **Creative, Production, Music Arranger, Sound Engineer:** 9 hari sebelum tayang

**Contoh Perhitungan:**
```
Episode 1 tayang: 10 Januari 2025, 19:00
- Deadline Editor: 3 Januari 2025 (7 hari sebelum)
- Deadline Creative/Production/Music Arr/Sound Eng: 1 Januari 2025 (9 hari sebelum)
```

---

## 5. Edit Deadline

### 5.1 Edit Deadline

**Endpoint:** `PUT /api/live-tv/manager-program/deadlines/{deadlineId}`

**Description:** Manager Program dapat mengedit deadline jika ada kebutuhan khusus (misal: ada perbaikan di QC).

**Request Body:**
```json
{
  "deadline_date": "2025-01-05 10:00:00",
  "reason": "Ada perbaikan di QC, deadline perlu diperpanjang",
  "description": "Deadline editing episode - diperpanjang"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "episode_id": 1,
    "role": "editor",
    "deadline_date": "2025-01-05 10:00:00",
    "changed_deadline_date": "2025-01-05 10:00:00",
    "change_reason": "Ada perbaikan di QC, deadline perlu diperpanjang",
    "changed_by": 1,
    "changed_at": "2025-12-10 10:00:00",
    "auto_generated": false
  },
  "message": "Deadline updated successfully"
}
```

**Notes:**
- Setelah deadline diubah, notifikasi otomatis dikirim ke role yang terkait
- Deadline yang diubah akan ditandai `auto_generated: false`

---

## 6. Opsi Jadwal Tayang

### 6.1 Submit Schedule Options

**Endpoint:** `POST /api/live-tv/manager-program/programs/{programId}/submit-schedule-options`

**Description:** Membuat opsi jadwal tayang dan mengajukan ke Manager Broadcasting.

**Request Body:**
```json
{
  "schedule_options": [
    {
      "air_date": "2025-01-01",
      "air_time": "19:00:00",
      "notes": "Opsi 1: Senin malam"
    },
    {
      "air_date": "2025-01-02",
      "air_time": "20:00:00",
      "notes": "Opsi 2: Selasa malam"
    }
  ],
  "submission_notes": "Mohon review opsi jadwal tayang"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "program_id": 1,
    "schedule_options": [
      {
        "id": 1,
        "air_date": "2025-01-01",
        "air_time": "19:00:00",
        "status": "pending_approval",
        "notes": "Opsi 1: Senin malam"
      }
    ],
    "submitted_at": "2025-12-10 10:00:00"
  },
  "message": "Schedule options submitted successfully"
}
```

### 6.2 Get Schedule Options

**Endpoint:** `GET /api/live-tv/manager-program/programs/{programId}/schedule-options`

**Description:** Melihat opsi jadwal tayang yang sudah diajukan.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "program_id": 1,
      "air_date": "2025-01-01",
      "air_time": "19:00:00",
      "status": "pending_approval",
      "approved_by": null,
      "approved_at": null,
      "notes": "Opsi 1: Senin malam"
    }
  ],
  "message": "Schedule options retrieved successfully"
}
```

---

## 7. Target Pencapaian Views

### 7.1 Update Episode Views

**Endpoint:** `PUT /api/live-tv/manager-program/episodes/{episodeId}/views`

**Description:** Update views episode secara manual (untuk tarik data mingguan).

**Request Body:**
```json
{
  "actual_views": 15000
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "episode_id": 1,
    "actual_views": 15000,
    "target_views": 10000,
    "performance": "above_target",
    "growth_rate": 50.0,
    "views_last_updated": "2025-12-10 10:00:00"
  },
  "message": "Views updated successfully"
}
```

### 7.2 Get Program Performance

**Endpoint:** `GET /api/live-tv/manager-program/programs/{programId}/performance`

**Description:** Melihat performance program (target vs actual views).

**Response:**
```json
{
  "success": true,
  "data": {
    "program_id": 1,
    "program_name": "Program Musik Live",
    "total_episodes": 53,
    "episodes_with_views": 10,
    "average_views": 12500.50,
    "target_views": 10000,
    "performance_status": "good",
    "performance_percentage": 125.0,
    "recommendation": "Program performing well, continue production"
  },
  "message": "Program performance retrieved successfully"
}
```

**Performance Status:**
- `excellent`: ≥150% dari target
- `good`: 100-149% dari target
- `fair`: 75-99% dari target
- `poor`: <75% dari target
- `pending`: Belum ada data

### 7.3 Get Weekly Performance

**Endpoint:** `GET /api/live-tv/manager-program/programs/{programId}/weekly-performance`

**Description:** Mendapatkan laporan performance mingguan (tarik data mingguan).

**Query Parameters:**
- `week` (optional): Week number (1-52)
- `year` (optional): Year (default: current year)

**Response:**
```json
{
  "success": true,
  "data": {
    "program_id": 1,
    "week": 1,
    "year": 2025,
    "episodes": [
      {
        "episode_number": 1,
        "air_date": "2025-01-01",
        "actual_views": 15000,
        "target_views": 10000,
        "performance": "above_target"
      }
    ],
    "total_views": 15000,
    "average_views": 15000,
    "target_total": 10000,
    "performance_percentage": 150.0
  },
  "message": "Weekly performance report retrieved successfully"
}
```

---

## 8. Monitoring Pekerjaan

### 8.1 Get Quality Controls

**Endpoint:** `GET /api/live-tv/manager-program/programs/{programId}/quality-controls`

**Description:** Monitoring semua quality control untuk program.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "episode_id": 1,
      "episode_number": 1,
      "qc_by": 5,
      "decision": "approved",
      "quality_score": 85,
      "video_quality_score": 90,
      "audio_quality_score": 85,
      "content_quality_score": 80,
      "status": "approved",
      "reviewed_at": "2025-12-10 10:00:00",
      "notes": "Quality baik, siap tayang"
    }
  ],
  "message": "Quality controls retrieved successfully"
}
```

### 8.2 Get Episode Quality Controls

**Endpoint:** `GET /api/live-tv/manager-program/episodes/{episodeId}/quality-controls`

**Description:** Monitoring quality control untuk episode tertentu.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "episode_id": 1,
      "decision": "approved",
      "quality_score": 85,
      "revision_points": null,
      "reviewed_at": "2025-12-10 10:00:00"
    }
  ],
  "message": "Episode quality controls retrieved successfully"
}
```

**Workflow Monitoring:**
- Semua episode track workflow state dari awal hingga penayangan
- Dapat dilihat melalui endpoint episode: `GET /api/live-tv/episodes/{id}/workflow-history`

---

## 9. Menutup Program

### 9.1 Close Program

**Endpoint:** `POST /api/live-tv/manager-program/programs/{programId}/close`

**Description:** Menutup program regular yang tidak berkembang.

**Request Body:**
```json
{
  "reason": "Program tidak mencapai target views selama 3 bulan berturut-turut"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Live",
    "status": "cancelled",
    "rejection_notes": "Program tidak mencapai target views selama 3 bulan berturut-turut",
    "rejected_by": 1,
    "rejected_at": "2025-12-10 10:00:00"
  },
  "message": "Program closed successfully"
}
```

### 9.2 Evaluate All Programs

**Endpoint:** `POST /api/live-tv/manager-program/evaluate-all-programs`

**Description:** Evaluasi semua program aktif untuk menentukan program yang perlu ditutup.

**Response:**
```json
{
  "success": true,
  "data": {
    "evaluated_at": "2025-12-10 10:00:00",
    "total_programs": 10,
    "programs_evaluated": 10,
    "programs_recommended_for_closure": [
      {
        "program_id": 5,
        "program_name": "Program A",
        "reason": "Average views below 50% of target for 3 consecutive months",
        "recommendation": "close"
      }
    ]
  },
  "message": "Programs evaluated successfully"
}
```

---

## 10. Intervensi Approval & Jadwal

### 10.1 Override Approval

**Endpoint:** `POST /api/live-tv/manager-program/approvals/{approvalId}/override`

**Description:** Manager Program dapat mengintervensi approval di semua bidang (override authority).

**Request Body:**
```json
{
  "action": "approve", // atau "reject"
  "reason": "Override approval karena urgent",
  "notes": "Approval di-override oleh Manager Program"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "approval_type": "episode_rundown_edit",
    "status": "approved",
    "approved_by": 1,
    "approved_at": "2025-12-10 10:00:00",
    "approval_notes": "Override approval karena urgent (Overridden by Manager Program)"
  },
  "message": "Approval approved successfully (Override authority)"
}
```

**Notes:**
- Manager Program dapat override approval untuk semua workflow stage
- Notifikasi otomatis dikirim ke semua pihak terkait

### 10.2 Cancel Schedule

**Endpoint:** `POST /api/live-tv/manager-program/schedules/{scheduleId}/cancel`

**Description:** Membatalkan jadwal shooting.

**Request Body:**
```json
{
  "reason": "Ada perubahan mendadak",
  "notify_team": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "schedule_type": "shooting",
    "status": "cancelled",
    "cancelled_at": "2025-12-10 10:00:00",
    "cancellation_reason": "Ada perubahan mendadak"
  },
  "message": "Schedule cancelled successfully"
}
```

### 10.3 Reschedule

**Endpoint:** `POST /api/live-tv/manager-program/schedules/{scheduleId}/reschedule`

**Description:** Merubah jadwal shooting.

**Request Body:**
```json
{
  "new_schedule_date": "2025-01-15",
  "new_schedule_time": "10:00:00",
  "reason": "Jadwal sebelumnya bentrok",
  "notify_team": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "schedule_type": "shooting",
    "schedule_date": "2025-01-15",
    "schedule_time": "10:00:00",
    "status": "rescheduled",
    "rescheduled_at": "2025-12-10 10:00:00",
    "reschedule_reason": "Jadwal sebelumnya bentrok"
  },
  "message": "Schedule rescheduled successfully"
}
```

---

## 11. Rundown Edit Approval

### 11.1 Get Rundown Edit Requests

**Endpoint:** `GET /api/live-tv/manager-program/rundown-edit-requests`

**Description:** Melihat semua permintaan edit rundown dari Producer.

**Query Parameters:**
- `status` (optional): `pending`, `approved`, `rejected`
- `program_id` (optional): Filter by program

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "approval_type": "episode_rundown_edit",
      "status": "pending",
      "requested_by": 2,
      "request_notes": "Perlu update rundown untuk segment baru",
      "request_data": {
        "new_rundown": "Updated rundown content..."
      },
      "current_data": {
        "old_rundown": "Old rundown content..."
      },
      "episode": {
        "id": 1,
        "episode_number": 1,
        "title": "Episode 1"
      },
      "requestedBy": {
        "id": 2,
        "name": "Producer Name"
      }
    }
  ],
  "message": "Rundown edit requests retrieved successfully"
}
```

### 11.2 Approve Rundown Edit

**Endpoint:** `POST /api/live-tv/manager-program/rundown-edit-requests/{approvalId}/approve`

**Description:** Approve permintaan edit rundown dari Producer.

**Request Body:**
```json
{
  "approval_notes": "Rundown edit disetujui"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "approved",
    "approved_by": 1,
    "approved_at": "2025-12-10 10:00:00",
    "approval_notes": "Rundown edit disetujui",
    "episode": {
      "id": 1,
      "rundown": "Updated rundown content..."
    }
  },
  "message": "Rundown edit request approved successfully"
}
```

### 11.3 Reject Rundown Edit

**Endpoint:** `POST /api/live-tv/manager-program/rundown-edit-requests/{approvalId}/reject`

**Description:** Reject permintaan edit rundown dari Producer.

**Request Body:**
```json
{
  "rejection_reason": "Rundown tidak sesuai dengan konsep program"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "rejected",
    "rejected_by": 1,
    "rejected_at": "2025-12-10 10:00:00",
    "rejection_notes": "Rundown tidak sesuai dengan konsep program"
  },
  "message": "Rundown edit request rejected successfully"
}
```

---

## 📊 Workflow Lengkap Program Musik

```
1. Manager Program membuat program
   ↓ (Upload proposal file)
2. Auto-generate 53 episodes
   ↓ (Auto-generate deadlines)
3. Assign production team ke episode
   ↓
4. Producer mengelola workflow:
   - Music Arranger → Sound Engineer → Editor
   - Creative → Production
   ↓
5. Quality Control
   ↓ (Jika ada perbaikan, Manager Program bisa edit deadline)
6. Broadcasting
   ↓
7. Penayangan
   ↓ (Manager Program update views mingguan)
8. Monitoring performance
   ↓ (Jika tidak berkembang, tutup program)
```

---

## 🔑 Key Features

### ✅ Sudah Diimplementasikan

1. ✅ **Proposal Program dengan Lampiran**
   - Upload file proposal saat create program
   - Support PDF, DOC, DOCX (max 10MB)

2. ✅ **Auto-Generate Episode & Deadline**
   - Auto-generate 53 episodes saat program dibuat
   - Auto-generate deadlines:
     - Editor: 7 hari sebelum tayang
     - Creative, Production, Music Arranger, Sound Engineer: 9 hari sebelum tayang

3. ✅ **Edit Deadline**
   - Manager Program dapat edit deadline dengan alasan
   - Notifikasi otomatis ke role terkait

4. ✅ **Opsi Jadwal Tayang**
   - Submit multiple schedule options
   - Ajukan ke Manager Broadcasting untuk approval

5. ✅ **Target Pencapaian Views**
   - Update views manual per episode
   - Weekly performance report
   - Program performance tracking

6. ✅ **Monitoring Lengkap**
   - Dashboard dengan statistics
   - Quality control monitoring
   - Workflow state tracking

7. ✅ **Menutup Program**
   - Close program yang tidak berkembang
   - Auto-evaluate semua program

8. ✅ **Intervensi Approval & Jadwal**
   - Override approval di semua bidang
   - Cancel jadwal shooting
   - Reschedule jadwal

---

## 📝 Contoh Request/Response

### Contoh: Create Program dengan Proposal

**Request:**
```bash
POST /api/live-tv/programs
Content-Type: multipart/form-data
Authorization: Bearer {token}

name: Program Musik Live
description: Program musik live setiap minggu
manager_program_id: 1
production_team_id: 1
start_date: 2025-01-01
air_time: 19:00
duration_minutes: 60
broadcast_channel: Hope Channel
target_views_per_episode: 10000
proposal_file: [file]
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Live",
    "proposal_file_path": "programs/proposals/1234567890_proposal.pdf",
    "episodes": [
      {
        "id": 1,
        "episode_number": 1,
        "air_date": "2025-01-01",
        "deadlines": [
          {
            "role": "editor",
            "deadline_date": "2024-12-25 19:00:00"
          },
          {
            "role": "kreatif",
            "deadline_date": "2024-12-23 19:00:00"
          }
        ]
      }
    ]
  }
}
```

---

## 🚨 Error Handling

Semua endpoint mengembalikan error dalam format:

```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field_name": ["Error detail"]
  }
}
```

**HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## 📌 Notes Penting

1. **Auto-Deadline Calculation:**
   - Deadline dihitung dari `air_date` episode
   - Editor: `air_date - 7 days`
   - Lainnya: `air_date - 9 days`

2. **Team Assignment:**
   - Producer dapat mengganti semua kelompok kerja melalui production team assignment
   - Setiap production team memiliki: kreatif, music arranger, sound engineer, produksi, editor

3. **Override Authority:**
   - Manager Program memiliki otoritas override untuk semua approval
   - Semua override akan dicatat dengan reason

4. **Performance Tracking:**
   - Views diupdate manual per episode
   - Weekly report dapat di-generate untuk analisis

5. **Program Closure:**
   - Program dapat ditutup manual atau auto-evaluated
   - Notifikasi otomatis ke production team saat program ditutup

---

## 🔗 Related Endpoints

- **Program Management:** `/api/live-tv/programs`
- **Episode Management:** `/api/live-tv/episodes`
- **Production Team:** `/api/live-tv/production-teams`
- **Workflow States:** `/api/live-tv/workflow-states`

---

**Last Updated:** December 10, 2025

