# 📚 API DOCUMENTATION - MANAGER PROGRAM

**Dokumen ini berisi dokumentasi lengkap API untuk Manager Program, termasuk request/response format, HTTP methods, dan contoh penggunaan.**

---

## 🔑 AUTENTIKASI

Semua endpoint memerlukan autentikasi Bearer Token:
```
Authorization: Bearer {token}
```

---

## 📋 DAFTAR ENDPOINT

### 1. **CREATE PROGRAM (Auto-Generate 52 Episode Setiap Sabtu)**

#### Endpoint
```
POST /api/live-tv/programs
```

#### HTTP Method
`POST`

#### Description
Membuat program baru. Sistem akan **otomatis generate 52 episode** dengan logic:
- Episode 1 = **Sabtu pertama di tahun program** (dari `start_date`)
- Setiap episode tayang **setiap Sabtu** (mingguan)
- Total 52 episode (1 tahun = 52 minggu)
- Deadline otomatis dibuat: Editor (7 hari sebelum), Creative & Produksi (9 hari sebelum)

#### Request Body (Form Data atau JSON)
```json
{
  "name": "Hope Musik",
  "description": "Program musik mingguan",
  "category": "musik",
  "manager_program_id": 2,
  "production_team_id": 1,
  "start_date": "2026-01-01",
  "air_time": "19:00",
  "duration_minutes": 60,
  "broadcast_channel": "Saptu Alam",
  "target_views_per_episode": 100000,
  "proposal_file": "<file>" // Optional: PDF/DOC/DOCX, max 10MB
}
```

#### Request Validation
- `name` (required, string, max: 255)
- `description` (optional, string)
- `category` (optional, in: musik, live_tv, regular, special, other)
- `manager_program_id` (required, exists: users, id)
- `production_team_id` (optional, exists: production_teams, id)
- `start_date` (required, date, after_or_equal: today)
- `air_time` (required, date_format: H:i)
- `duration_minutes` (optional, integer, min: 1)
- `broadcast_channel` (optional, string, max: 255)
- `target_views_per_episode` (optional, integer, min: 0)
- `proposal_file` (optional, file, mimes: pdf,doc,docx, max: 10240 KB)

#### Success Response (201 Created)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Hope Musik",
    "description": "Program musik mingguan",
    "category": "musik",
    "status": "draft",
    "manager_program_id": 2,
    "production_team_id": 1,
    "start_date": "2026-01-01",
    "air_time": "19:00:00",
    "duration_minutes": 60,
    "broadcast_channel": "Saptu Alam",
    "target_views_per_episode": 100000,
    "proposal_file_path": "programs/proposals/abc123.pdf",
    "proposal_file_name": "proposal.pdf",
    "created_at": "2026-01-06T10:00:00.000000Z",
    "updated_at": "2026-01-06T10:00:00.000000Z",
    "manager_program": {
      "id": 2,
      "name": "Manager Name"
    },
    "production_team": {
      "id": 1,
      "name": "Production Team Name"
    },
    "episodes": [
      {
        "id": 1,
        "episode_number": 1,
        "title": "Episode 1",
        "description": "Episode 1 dari program Hope Musik",
        "air_date": "2026-01-03T00:00:00.000000Z",
        "status": "draft",
        "current_workflow_state": "program_created"
      },
      {
        "id": 2,
        "episode_number": 2,
        "title": "Episode 2",
        "air_date": "2026-01-10T00:00:00.000000Z",
        "status": "draft"
      }
      // ... sampai Episode 52
    ]
  },
  "message": "Program created successfully with 52 episodes generated"
}
```

#### Auto-Generate Logic
Saat program dibuat, sistem akan:

1. **Detect Sabtu pertama di tahun program:**
   - Ambil tahun dari `start_date`
   - Cari Sabtu pertama di bulan Januari tahun tersebut
   - Contoh: Tahun 2026, Sabtu pertama = 3 Januari 2026

2. **Generate 52 episode:**
   - Episode 1: 3 Januari 2026 (Sabtu pertama)
   - Episode 2: 10 Januari 2026 (Sabtu berikutnya)
   - Episode 3: 17 Januari 2026
   - ... sampai Episode 52

3. **Auto-generate deadlines per episode:**
   - **Editor**: 7 hari sebelum tayang
   - **Creative**: 9 hari sebelum tayang
   - **Produksi**: 9 hari sebelum tayang
   - **Music Arranger**: 9 hari sebelum tayang
   - **Sound Engineer**: 9 hari sebelum tayang

#### Example Timeline (Tahun 2026)
```
Episode 1:
  - Tayang: 3 Januari 2026 (Sabtu)
  - Deadline Editor: 27 Desember 2025 (7 hari sebelum)
  - Deadline Creative: 25 Desember 2025 (9 hari sebelum)

Episode 2:
  - Tayang: 10 Januari 2026 (Sabtu)
  - Deadline Editor: 3 Januari 2026 (7 hari sebelum)
  - Deadline Creative: 1 Januari 2026 (9 hari sebelum)

Episode 3:
  - Tayang: 17 Januari 2026 (Sabtu)
  - Deadline Editor: 10 Januari 2026
  - Deadline Creative: 8 Januari 2026

... sampai Episode 52
```

#### Error Response (422 Validation Error)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."],
    "start_date": ["The start date must be a date after or equal to today."]
  }
}
```

#### Error Response (403 Forbidden)
```json
{
  "success": false,
  "message": "Unauthorized: Only Manager Program can create programs or manager_program_id must match logged in user"
}
```

#### Frontend Integration Example
```javascript
// Create Program dengan FormData (untuk file upload)
const createProgram = async (programData, proposalFile) => {
  const formData = new FormData();
  formData.append('name', programData.name);
  formData.append('description', programData.description);
  formData.append('category', programData.category);
  formData.append('manager_program_id', programData.manager_program_id);
  formData.append('production_team_id', programData.production_team_id);
  formData.append('start_date', programData.start_date); // Format: YYYY-MM-DD
  formData.append('air_time', programData.air_time); // Format: HH:mm
  formData.append('duration_minutes', programData.duration_minutes);
  formData.append('broadcast_channel', programData.broadcast_channel);
  formData.append('target_views_per_episode', programData.target_views_per_episode);
  
  if (proposalFile) {
    formData.append('proposal_file', proposalFile);
  }
  
  try {
    const response = await axios.post(
      '/api/live-tv/programs',
      formData,
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'multipart/form-data'
        }
      }
    );
    
    console.log('Program created:', response.data);
    console.log('Episodes generated:', response.data.data.episodes.length); // Should be 52
    
    return response.data;
  } catch (error) {
    console.error('Error creating program:', error.response.data);
    throw error;
  }
};

// Usage
createProgram({
  name: 'Hope Musik',
  description: 'Program musik mingguan',
  category: 'musik',
  manager_program_id: 2,
  production_team_id: 1,
  start_date: '2026-01-01',
  air_time: '19:00',
  duration_minutes: 60,
  broadcast_channel: 'Saptu Alam',
  target_views_per_episode: 100000
}, proposalFile);
```

#### Important Notes
- **Auto-generate episodes**: 52 episode otomatis dibuat saat program dibuat
- **Sabtu pertama**: Sistem otomatis detect Sabtu pertama di tahun program
- **Deadline auto-generate**: Setiap episode otomatis punya deadline untuk semua role
- **File upload**: Proposal file optional, max 10MB
- **Status default**: Program dibuat dengan status `draft`

---

### 2. **SET/UPDATE TARGET VIEWS PER PROGRAM**

#### Endpoint
```
PUT /api/live-tv/manager-program/programs/{programId}/target-views
```

#### HTTP Method
`PUT`

#### Description
Set atau update target views per episode untuk program tertentu.

#### Request Body
```json
{
  "target_views_per_episode": 100000
}
```

#### Request Validation
- `target_views_per_episode` (required, integer, min: 0)

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "program_id": 1,
    "program_name": "Program Name",
    "old_target_views": 80000,
    "new_target_views": 100000,
    "average_views_per_episode": 95000.50,
    "performance_status": "good"
  },
  "message": "Target views updated successfully"
}
```

#### Error Response (422 Validation Error)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "target_views_per_episode": [
      "The target views per episode must be an integer.",
      "The target views per episode must be at least 0."
    ]
  }
}
```

#### Error Response (403 Forbidden)
```json
{
  "success": false,
  "message": "Only Manager Program can set target views"
}
```

---

### 2. **GET WEEKLY PERFORMANCE REPORT**

#### Endpoint
```
GET /api/live-tv/manager-program/programs/{programId}/weekly-performance
```

#### HTTP Method
`GET`

#### Description
Mendapatkan data performa mingguan program dengan comparison target vs actual views.

#### Query Parameters
Tidak ada query parameters.

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "program": {
      "id": 1,
      "name": "Program Name",
      "target_views_per_episode": 100000,
      "total_actual_views": 500000,
      "average_views_per_episode": 100000,
      "performance_status": "good"
    },
    "weekly_data": [
      {
        "week": "2026-01",
        "week_start": "2026-01-03",
        "week_end": "2026-01-09",
        "episodes": [
          {
            "episode_number": 1,
            "title": "Episode 1",
            "actual_views": 95000,
            "target_views": 100000,
            "achievement_percentage": 95.00,
            "air_date": "2026-01-03"
          }
        ],
        "total_views": 95000,
        "target_total_views": 100000,
        "average_views": 95000,
        "achievement_percentage": 95.00
      }
    ],
    "total_aired_episodes": 1,
    "overall_achievement_percentage": 95.00
  },
  "message": "Weekly performance report retrieved successfully"
}
```

---

### 3. **EDIT DEADLINE**

#### Endpoint
```
PUT /api/live-tv/manager-program/deadlines/{deadlineId}
```

#### HTTP Method
`PUT`

#### Description
Edit deadline untuk kebutuhan khusus (misalnya karena ada perbaikan di QC).

#### Request Body
```json
{
  "deadline_date": "2026-01-15 23:59:59",
  "reason": "Perpanjangan deadline karena ada perbaikan di QC",
  "description": "Deadline di-extend 3 hari karena revisi QC"
}
```

#### Request Validation
- `deadline_date` (required, date)
- `reason` (required, string, max: 1000)
- `description` (optional, string, max: 500)

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "episode_id": 1,
    "role": "editor",
    "deadline_date": "2026-01-15T23:59:59.000000Z",
    "changed_deadline_date": "2026-01-15T23:59:59.000000Z",
    "change_reason": "Perpanjangan deadline karena ada perbaikan di QC",
    "changed_by": 2,
    "changed_at": "2026-01-06T10:00:00.000000Z"
  },
  "message": "Deadline updated successfully"
}
```

---

### 4. **MONITOR EPISODE WORKFLOW**

#### Endpoint
```
GET /api/live-tv/manager-program/episodes/{episodeId}/monitor-workflow
```

#### HTTP Method
`GET`

#### Description
Monitoring semua pekerjaan episode dari awal hingga penayangan.

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "episode": {
      "id": 1,
      "episode_number": 1,
      "title": "Episode 1",
      "air_date": "2026-01-03T00:00:00.000000Z",
      "status": "in_production",
      "current_workflow_state": "editing",
      "days_until_air": 5,
      "is_overdue": false
    },
    "program": {
      "id": 1,
      "name": "Program Name"
    },
    "workflow_steps": {
      "music_arrangement": {
        "step_name": "Music Arrangement",
        "status": "completed",
        "data": {
          "id": 1,
          "status": "approved",
          "song_title": "Lagu Contoh",
          "arranger_name": "Arranger Name",
          "created_at": "2025-12-20T10:00:00.000000Z",
          "updated_at": "2025-12-25T14:00:00.000000Z",
          "approved_at": "2025-12-25T14:00:00.000000Z"
        },
        "deadline": {
          "id": 1,
          "role": "musik_arr",
          "deadline_date": "2025-12-25T23:59:59.000000Z",
          "status": "completed",
          "is_completed": true
        }
      },
      "creative_work": {
        "step_name": "Creative Work",
        "status": "completed",
        "data": { ... },
        "deadline": { ... }
      },
      "sound_recording": { ... },
      "production": { ... },
      "editing": { ... },
      "quality_control": { ... },
      "broadcasting": { ... }
    },
    "progress": {
      "percentage": 57.14,
      "completed_steps": 4,
      "total_steps": 7
    },
    "timeline": [
      {
        "id": 1,
        "state": "program_created",
        "state_label": "Program Created",
        "assigned_to_role": "manager_program",
        "assigned_to_user": "Manager Name",
        "notes": "Program created, ready for production",
        "created_at": "2025-12-01T10:00:00.000000Z",
        "updated_at": "2025-12-01T10:00:00.000000Z"
      }
    ],
    "deadlines": [
      {
        "id": 1,
        "role": "editor",
        "role_label": "Editor",
        "deadline_date": "2025-12-27T23:59:59.000000Z",
        "status": "pending",
        "is_completed": false,
        "is_overdue": false,
        "completed_at": null
      }
    ],
    "production_team": {
      "id": 1,
      "name": "Production Team Name",
      "members": [
        {
          "id": 1,
          "user_id": 5,
          "user_name": "Creative Name",
          "role": "kreatif"
        }
      ]
    }
  },
  "message": "Episode workflow monitoring data retrieved successfully"
}
```

---

### 5. **GET UNDERPERFORMING PROGRAMS**

#### Endpoint
```
GET /api/live-tv/manager-program/programs/underperforming
```

#### HTTP Method
`GET`

#### Description
Mendapatkan list program yang tidak berkembang (poor performance).

#### Query Parameters
- `performance_status` (optional): `poor` (default) atau `warning`
- `min_episodes` (optional): Minimal episode aired, default: 4
- `page` (optional): Halaman untuk pagination, default: 1
- `per_page` (optional): Items per page, default: 15

#### Example Request
```
GET /api/live-tv/manager-program/programs/underperforming?performance_status=poor&min_episodes=4&page=1&per_page=15
```

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "programs": [
      {
        "id": 1,
        "name": "Program Name",
        "status": "active",
        "performance_status": "poor",
        "target_views_per_episode": 100000,
        "average_views_per_episode": 25000.50,
        "total_actual_views": 100000,
        "achievement_percentage": 25.00,
        "aired_episodes": 4,
        "total_episodes": 52,
        "auto_close_enabled": true,
        "last_performance_check": "2026-01-06T10:00:00.000000Z",
        "created_at": "2025-12-01T10:00:00.000000Z",
        "manager_program": {
          "id": 2,
          "name": "Manager Name"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 5,
      "last_page": 1
    },
    "summary": {
      "total_underperforming": 5,
      "total_poor": 3,
      "total_warning": 2,
      "average_achievement": 28.50
    }
  },
  "message": "Underperforming programs retrieved successfully"
}
```

---

### 6. **CLOSE PROGRAM**

#### Endpoint
```
POST /api/live-tv/manager-program/programs/{programId}/close
```

#### HTTP Method
`POST`

#### Description
Menutup program secara manual dengan alasan.

#### Request Body
```json
{
  "reason": "Program ditutup karena performa rendah (achievement < 30%)"
}
```

#### Request Validation
- `reason` (required, string, max: 1000)

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Name",
    "status": "cancelled",
    "rejection_notes": "Program ditutup karena performa rendah (achievement < 30%)",
    "rejected_by": 2,
    "rejected_at": "2026-01-06T10:00:00.000000Z"
  },
  "message": "Program closed successfully"
}
```

---

### 7. **CANCEL SCHEDULE**

#### Endpoint
```
POST /api/live-tv/manager-program/schedules/{scheduleId}/cancel
```

#### HTTP Method
`POST`

#### Description
Cancel jadwal syuting/recording dengan override authority.

#### Request Body
```json
{
  "reason": "Cancel jadwal karena ada perubahan mendadak",
  "notify_team": true
}
```

#### Request Validation
- `reason` (required, string, max: 1000)
- `notify_team` (optional, boolean, default: true)

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "music_submission_id": 1,
    "schedule_type": "recording",
    "scheduled_datetime": "2026-01-10T14:00:00.000000Z",
    "status": "cancelled",
    "cancellation_reason": "Cancel jadwal karena ada perubahan mendadak (Cancelled by Manager Program)",
    "cancelled_by": 2,
    "cancelled_at": "2026-01-06T10:00:00.000000Z"
  },
  "message": "Schedule cancelled successfully"
}
```

---

### 8. **RESCHEDULE SCHEDULE**

#### Endpoint
```
POST /api/live-tv/manager-program/schedules/{scheduleId}/reschedule
```

#### HTTP Method
`POST`

#### Description
Reschedule (ubah) jadwal syuting/recording dengan override authority.

#### Request Body
```json
{
  "new_datetime": "2026-01-15 14:00:00",
  "reason": "Reschedule karena konflik dengan jadwal lain",
  "location": "Studio A",
  "location_address": "Jl. Contoh No. 123",
  "schedule_notes": "Catatan tambahan",
  "notify_team": true
}
```

#### Request Validation
- `new_datetime` (required, date, must be after now)
- `reason` (required, string, max: 1000)
- `location` (optional, string, max: 255)
- `location_address` (optional, string, max: 500)
- `schedule_notes` (optional, string, max: 1000)
- `notify_team` (optional, boolean, default: true)

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "music_submission_id": 1,
    "schedule_type": "recording",
    "scheduled_datetime": "2026-01-10T14:00:00.000000Z",
    "rescheduled_datetime": "2026-01-15T14:00:00.000000Z",
    "location": "Studio A",
    "location_address": "Jl. Contoh No. 123",
    "status": "rescheduled",
    "reschedule_reason": "Reschedule karena konflik dengan jadwal lain (Rescheduled by Manager Program)",
    "rescheduled_by": 2,
    "rescheduled_at": "2026-01-06T10:00:00.000000Z"
  },
  "message": "Schedule rescheduled successfully"
}
```

---

### 9. **OVERRIDE APPROVAL**

#### Endpoint
```
POST /api/live-tv/manager-program/approvals/{approvalId}/override
```

#### HTTP Method
`POST`

#### Description
Override approval/rejection di semua bidang dengan override authority.

#### Request Body
```json
{
  "action": "approve",
  "reason": "Override approval karena alasan khusus",
  "notes": "Catatan tambahan"
}
```

#### Request Validation
- `action` (required, in: approve, reject)
- `reason` (required, string, max: 1000)
- `notes` (optional, string, max: 500)

#### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "approvable_type": "App\\Models\\MusicArrangement",
    "approvable_id": 1,
    "approval_type": "music_arrangement",
    "status": "approved",
    "approved_by": 2,
    "approved_at": "2026-01-06T10:00:00.000000Z",
    "approval_notes": "Override approval karena alasan khusus (Overridden by Manager Program)\nCatatan tambahan"
  },
  "message": "Approval approved successfully (Override authority)"
}
```

---

## 📊 SUMMARY TABLE

| No | Endpoint | Method | Description | Key Fields |
|---|---|---|---|---|
| 1 | `/programs` | POST | Create program (auto-generate 52 episode setiap Sabtu) | `name`, `start_date`, `manager_program_id`, `proposal_file` |
| 2 | `/programs/{id}/target-views` | PUT | Set/Update target views | `target_views_per_episode` |
| 3 | `/programs/{id}/weekly-performance` | GET | Get weekly performance | - |
| 4 | `/deadlines/{id}` | PUT | Edit deadline | `deadline_date`, `reason` |
| 5 | `/episodes/{id}/monitor-workflow` | GET | Monitor workflow | - |
| 6 | `/programs/underperforming` | GET | Get underperforming programs | `performance_status`, `min_episodes`, `page`, `per_page` |
| 7 | `/programs/{id}/close` | POST | Close program | `reason` |
| 8 | `/schedules/{id}/cancel` | POST | Cancel schedule | `reason`, `notify_team` |
| 9 | `/schedules/{id}/reschedule` | POST | Reschedule schedule | `new_datetime`, `reason`, `location` |
| 10 | `/approvals/{id}/override` | POST | Override approval | `action`, `reason`, `notes` |

---

## 🔄 HTTP METHODS YANG DIGUNAKAN

- **GET**: Untuk mengambil data (read)
- **POST**: Untuk membuat/trigger action (create/action)
- **PUT**: Untuk update data (update)

---

## ⚠️ ERROR HANDLING

### Common Error Responses

#### 403 Forbidden (Unauthorized)
```json
{
  "success": false,
  "message": "Only Manager Program can access this"
}
```

#### 404 Not Found
```json
{
  "success": false,
  "message": "Resource not found"
}
```

#### 422 Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": [
      "Error message 1",
      "Error message 2"
    ]
  }
}
```

#### 500 Server Error
```json
{
  "success": false,
  "message": "Failed to process request",
  "error": "Error details"
}
```

---

## 🎯 FRONTEND INTEGRATION TIPS

### 1. **State Management**
- Simpan token di localStorage/sessionStorage
- Handle token refresh untuk expired token
- Gunakan interceptors untuk auto-attach token

### 2. **Error Handling**
- Check `success` field di response
- Display error messages dari `errors` object
- Handle network errors (timeout, connection failed)

### 3. **Loading States**
- Show loading indicator saat API call
- Disable buttons saat processing
- Show success/error notifications

### 4. **Pagination**
- Implement pagination untuk list endpoints
- Use `page` dan `per_page` parameters
- Display pagination controls

### 5. **Form Validation**
- Validate di frontend sebelum submit
- Match validation rules dengan backend
- Show inline error messages

---

## 📝 CATATAN PENTING

1. **Authentication**: Semua endpoint memerlukan Bearer Token
2. **Base URL**: `/api/live-tv/manager-program`
3. **Content-Type**: `application/json`
4. **Date Format**: ISO 8601 (YYYY-MM-DDTHH:mm:ss.sssZ)
5. **Timezone**: UTC (backend convert ke timezone user jika diperlukan)

---

## 🔗 RELATED DOCUMENTATION

- [Complete Workflow API Documentation](./COMPLETE_WORKFLOW_API_DOCUMENTATION.md)
- [Frontend Integration Guide](./FRONTEND_INTEGRATION_GUIDE.md)
- [Testing Guide](./GUIDE_TESTING_SISTEM_PROGRAM_MUSIK.md)

