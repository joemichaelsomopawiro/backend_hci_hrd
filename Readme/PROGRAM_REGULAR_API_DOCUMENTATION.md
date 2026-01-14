# 📚 PROGRAM REGULAR - API DOCUMENTATION

**Version**: 1.0.0  
**Base URL**: `/api/program-regular`  
**Authentication**: Required (Sanctum)

---

## 📋 OVERVIEW

API untuk sistem Program Regular dengan workflow:
1. **Manager Program** → Create program & konsep
2. **Producer** → Approve konsep, produksi, editing
3. **Manager Program** → Approve program dari Producer
4. **Manager Distribusi** → Verify, jadwal tayang, distribusi, laporan

---

## 🔐 AUTHENTICATION

Semua endpoint memerlukan authentication dengan Sanctum token:
```
Authorization: Bearer {token}
```

---

## 👨‍💼 MANAGER PROGRAM ROUTES

### 1. Create Program
**POST** `/api/program-regular/manager-program/programs`

**Authorization**: Manager Program only

**Request Body:**
```json
{
  "name": "Program Name",
  "description": "Program description",
  "start_date": "2025-01-15",
  "air_time": "19:00",
  "duration_minutes": 60,
  "broadcast_channel": "Channel Name",
  "program_year": 2025
}
```

**Response:**
```json
{
  "success": true,
  "message": "Program berhasil dibuat",
  "data": {
    "id": 1,
    "name": "Program Name",
    "status": "draft",
    "episodes": [...]
  }
}
```

---

### 2. List Programs
**GET** `/api/program-regular/manager-program/programs`

**Authorization**: All authenticated users (semua divisi bisa lihat)

**Query Parameters:**
- `status` (optional): Filter by status
- `program_year` (optional): Filter by year
- `manager_program_id` (optional): Filter by manager
- `producer_id` (optional): Filter by producer
- `search` (optional): Search by name
- `per_page` (optional): Items per page (default: 15)

---

### 3. Detail Program
**GET** `/api/program-regular/manager-program/programs/{id}`

**Authorization**: All authenticated users

---

### 4. Create Konsep
**POST** `/api/program-regular/manager-program/programs/{id}/concepts`

**Authorization**: Manager Program only

**Request Body:**
```json
{
  "concept": "Konsep program...",
  "objectives": "Tujuan program...",
  "target_audience": "Target audience...",
  "content_outline": "Outline konten...",
  "format_description": "Deskripsi format..."
}
```

---

### 5. Approve Program
**POST** `/api/program-regular/manager-program/programs/{id}/approve`

**Authorization**: Manager Program only

**Request Body:**
```json
{
  "notes": "Approval notes (optional)"
}
```

---

### 6. Reject Program
**POST** `/api/program-regular/manager-program/programs/{id}/reject`

**Authorization**: Manager Program only

**Request Body:**
```json
{
  "notes": "Rejection reason (required)"
}
```

---

### 7. Submit to Distribusi
**POST** `/api/program-regular/manager-program/programs/{id}/submit-to-distribusi`

**Authorization**: Manager Program only

---

### 8. View Schedules
**GET** `/api/program-regular/manager-program/programs/{id}/schedules`

**Authorization**: All authenticated users

---

### 9. View Distribution Reports
**GET** `/api/program-regular/manager-program/programs/{id}/distribution-reports`

**Authorization**: All authenticated users

---

## 🎬 PRODUCER ROUTES

### 1. List Concepts for Approval
**GET** `/api/program-regular/producer/concepts`

**Authorization**: Producer only

**Query Parameters:**
- `per_page` (optional): Items per page

---

### 2. Approve Concept
**POST** `/api/program-regular/producer/concepts/{id}/approve`

**Authorization**: Producer only

**Request Body:**
```json
{
  "notes": "Approval notes (optional)"
}
```

---

### 3. Reject Concept
**POST** `/api/program-regular/producer/concepts/{id}/reject`

**Authorization**: Producer only

**Request Body:**
```json
{
  "notes": "Rejection reason (required)"
}
```

---

### 4. Create Production Schedule
**POST** `/api/program-regular/producer/programs/{id}/production-schedules`

**Authorization**: Producer only

**Request Body:**
```json
{
  "episode_id": 1,
  "scheduled_date": "2025-01-20",
  "scheduled_time": "09:00",
  "schedule_notes": "Notes..."
}
```

---

### 5. Update Episode Status
**PUT** `/api/program-regular/producer/episodes/{id}/status`

**Authorization**: Producer only

**Request Body:**
```json
{
  "status": "production|editing|ready_for_review",
  "notes": "Status notes (optional)"
}
```

---

### 6. Upload File
**POST** `/api/program-regular/producer/episodes/{id}/files`

**Authorization**: Producer only

**Request Body (multipart/form-data):**
- `file`: File to upload (max 100GB)
- `category`: `raw_footage|edited_video|thumbnail|script|rundown|other`
- `description`: File description (optional)

---

### 7. Submit to Manager
**POST** `/api/program-regular/producer/programs/{id}/submit-to-manager`

**Authorization**: Producer only

---

## 📺 MANAGER DISTRIBUSI ROUTES

### 1. List Programs for Distribusi
**GET** `/api/program-regular/distribusi/programs`

**Authorization**: Manager Distribusi only

**Query Parameters:**
- `per_page` (optional): Items per page

---

### 2. Verify Program
**POST** `/api/program-regular/distribusi/programs/{id}/verify`

**Authorization**: Manager Distribusi only

**Request Body:**
```json
{
  "verified": true,
  "notes": "Verification notes (optional)"
}
```

---

### 3. Create Distribution Schedule
**POST** `/api/program-regular/distribusi/programs/{id}/distribution-schedules`

**Authorization**: Manager Distribusi only

**Request Body:**
```json
{
  "episode_id": 1,
  "schedule_date": "2025-01-25",
  "schedule_time": "19:00",
  "channel": "Channel Name",
  "schedule_notes": "Notes..."
}
```

---

### 4. Mark Episode as Aired
**POST** `/api/program-regular/distribusi/episodes/{id}/mark-aired`

**Authorization**: Manager Distribusi only

---

### 5. Create Distribution Report
**POST** `/api/program-regular/distribusi/programs/{id}/distribution-reports`

**Authorization**: Manager Distribusi only

**Request Body:**
```json
{
  "episode_id": 1,
  "report_title": "Report Title",
  "report_content": "Report content...",
  "distribution_data": {...},
  "analytics_data": {...},
  "report_period_start": "2025-01-01",
  "report_period_end": "2025-01-31"
}
```

---

### 6. List Distribution Reports
**GET** `/api/program-regular/distribusi/distribution-reports`

**Authorization**: Manager Distribusi only

**Query Parameters:**
- `program_id` (optional): Filter by program
- `status` (optional): Filter by status
- `per_page` (optional): Items per page

---

## 🔄 REVISION ROUTES

### 1. Request Revision
**POST** `/api/program-regular/revisions/programs/{id}/request`

**Authorization**: All authenticated users

**Request Body:**
```json
{
  "revision_type": "concept|production|editing|distribution",
  "revision_reason": "Reason for revision...",
  "after_data": {...}
}
```

---

### 2. Get Revision History
**GET** `/api/program-regular/revisions/programs/{id}/history`

**Authorization**: All authenticated users

**Query Parameters:**
- `revision_type` (optional): Filter by type
- `per_page` (optional): Items per page

---

### 3. Approve Revision
**POST** `/api/program-regular/revisions/{id}/approve`

**Authorization**: Manager Program only

**Request Body:**
```json
{
  "notes": "Approval notes (optional)"
}
```

---

### 4. Reject Revision
**POST** `/api/program-regular/revisions/{id}/reject`

**Authorization**: Manager Program only

**Request Body:**
```json
{
  "notes": "Rejection reason (required)"
}
```

---

## 📊 STATUS FLOW

```
draft → concept_pending → concept_approved → production_scheduled → 
in_production → editing → submitted_to_manager → manager_approved → 
submitted_to_distribusi → distribusi_approved → scheduled → distributed → completed
```

---

## 🔔 NOTIFICATIONS

Sistem notifikasi otomatis untuk:
- Konsep dibuat → Notify Producer
- Konsep approved/rejected → Notify Manager Program
- Program submitted → Notify Manager Program
- Program approved/rejected → Notify Producer
- Program submitted ke distribusi → Notify Manager Distribusi
- Revisi requested → Notify reviewer

---

## ✅ COMPLETENESS STATUS

**Backend Program Regular: 100% COMPLETE** ✅

- ✅ Database (8 tables dengan prefix `pr_`)
- ✅ Models (8 models dengan relationships)
- ✅ Services (6 services untuk business logic)
- ✅ Controllers (4 controllers untuk semua workflow)
- ✅ API Routes (20+ endpoints)
- ✅ Notification Integration

---

**Last Updated**: 15 Januari 2025
