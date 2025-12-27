# Unified Notification System

## Overview

Sistem notifikasi terpadu yang menggabungkan semua notifikasi dari berbagai sumber ke dalam satu endpoint yang sama. Semua notifikasi (cuti, program musik, workflow, dll) ditampilkan di satu tempat berdasarkan role user.

## Endpoint

### Base URL
```
/api/live-tv/unified-notifications
```

### 1. Get All Notifications
```
GET /api/live-tv/unified-notifications
```

**Query Parameters:**
- `status` (optional): `unread`, `read`, atau kosong untuk semua
- `type` (optional): Filter by type notification
- `priority` (optional): Filter by priority (`low`, `normal`, `high`, `urgent`)
- `limit` (optional): Jumlah notifikasi (default: 50)

**Response:**
```json
{
  "success": true,
  "data": {
    "notifications": [
      {
        "id": "main_123",
        "source": "main",
        "type": "workflow_state_change",
        "title": "New Task Assigned",
        "message": "You have been assigned to work on Episode 1",
        "priority": "normal",
        "status": "unread",
        "is_read": false,
        "read_at": null,
        "created_at": "2025-01-15 10:00:00",
        "data": {
          "episode_id": 1,
          "program_id": 1
        },
        "episode_id": 1,
        "program_id": 1
      },
      {
        "id": "leave_456",
        "source": "leave_request",
        "type": "leave_approval_required",
        "title": "Permohonan Cuti Menunggu Persetujuan",
        "message": "John Doe mengajukan permohonan cuti dari 2025-01-20 sampai 2025-01-25",
        "priority": "high",
        "status": "unread",
        "is_read": false,
        "read_at": null,
        "created_at": "2025-01-15 09:00:00",
        "data": {
          "leave_request_id": 456,
          "employee_id": 10,
          "employee_name": "John Doe",
          "start_date": "2025-01-20",
          "end_date": "2025-01-25",
          "leave_type": "annual",
          "days": 5
        }
      },
      {
        "id": "program_789",
        "source": "program",
        "type": "approval_request",
        "title": "Program Pending Approval",
        "message": "A program is pending your approval: Program Name",
        "priority": "normal",
        "status": "unread",
        "is_read": false,
        "read_at": null,
        "created_at": "2025-01-15 08:00:00",
        "data": {},
        "episode_id": null,
        "program_id": 1
      }
    ],
    "statistics": {
      "total": 15,
      "unread": 10,
      "read": 5
    }
  },
  "message": "Notifications retrieved successfully"
}
```

### 2. Get Unread Count
```
GET /api/live-tv/unified-notifications/unread-count
```

**Response:**
```json
{
  "success": true,
  "data": {
    "unread_count": 10
  },
  "message": "Unread count retrieved successfully"
}
```

### 3. Mark Notification as Read
```
POST /api/live-tv/unified-notifications/{id}/read
```

**Note:** ID format adalah `{source}_{id}` (contoh: `main_123`, `program_456`, `leave_789`)

**Response:**
```json
{
  "success": true,
  "message": "Notification marked as read successfully"
}
```

### 4. Mark All Notifications as Read
```
POST /api/live-tv/unified-notifications/mark-all-read
```

**Response:**
```json
{
  "success": true,
  "data": {
    "marked_count": 10
  },
  "message": "10 notifications marked as read successfully"
}
```

### 5. Get Notifications by Type
```
GET /api/live-tv/unified-notifications/by-type/{type}
```

**Query Parameters:**
- `status` (optional): `unread`, `read`
- `limit` (optional): Jumlah notifikasi (default: 50)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "leave_456",
      "source": "leave_request",
      "type": "leave_approval_required",
      ...
    }
  ],
  "message": "Notifications of type 'leave_approval_required' retrieved successfully"
}
```

## Notification Sources

### 1. Main Notifications (`source: "main"`)
- Notifikasi dari model `Notification`
- ID format: `main_{id}`
- Types: `workflow_state_change`, `deadline_reminder`, `deadline_overdue`, dll

### 2. Program Notifications (`source: "program"`)
- Notifikasi dari model `ProgramNotification`
- ID format: `program_{id}`
- Types: `approval_request`, `script_submitted`, `rundown_approved`, dll

### 3. Music Notifications (`source: "music"`)
- Notifikasi dari model `MusicNotification`
- ID format: `music_{id}`
- Types: `request_submitted`, `request_taken`, `request_approved`, `request_rejected`

### 4. Music Workflow Notifications (`source: "music_workflow"`)
- Notifikasi dari model `MusicWorkflowNotification`
- ID format: `music_workflow_{id}`
- Types: Berbagai tipe workflow musik

### 5. Leave Request Notifications (`source: "leave_request"`)
- Notifikasi untuk permohonan cuti
- ID format: `leave_{id}`
- Types:
  - `leave_approval_required` - Untuk atasan yang perlu approve
  - `leave_approved` - Untuk karyawan yang cutinya disetujui
  - `leave_rejected` - Untuk karyawan yang cutinya ditolak

## Notification Types

### Leave Request
- `leave_approval_required` - Permohonan cuti menunggu persetujuan
- `leave_approved` - Permohonan cuti disetujui
- `leave_rejected` - Permohonan cuti ditolak

### Program/Workflow
- `approval_request` - Request approval
- `script_submitted` - Script submitted
- `rundown_approved` - Rundown approved
- `workflow_state_change` - Perubahan workflow state

### Music
- `request_submitted` - Music request submitted
- `request_taken` - Music request taken
- `request_approved` - Music request approved
- `request_rejected` - Music request rejected

## Priority Levels

- `low` - Prioritas rendah
- `normal` - Prioritas normal (default)
- `high` - Prioritas tinggi
- `urgent` - Prioritas mendesak

## Status

- `unread` - Belum dibaca
- `read` - Sudah dibaca

## Frontend Integration

### Contoh: Get All Notifications
```javascript
async function getAllNotifications(filters = {}) {
  const params = new URLSearchParams();
  if (filters.status) params.append('status', filters.status);
  if (filters.type) params.append('type', filters.type);
  if (filters.priority) params.append('priority', filters.priority);
  if (filters.limit) params.append('limit', filters.limit);

  const response = await fetch(
    `/api/live-tv/unified-notifications?${params}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    }
  );

  return await response.json();
}
```

### Contoh: Mark as Read
```javascript
async function markAsRead(notificationId) {
  const response = await fetch(
    `/api/live-tv/unified-notifications/${notificationId}/read`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    }
  );

  return await response.json();
}
```

### Contoh: Get Unread Count (untuk badge)
```javascript
async function getUnreadCount() {
  const response = await fetch(
    '/api/live-tv/unified-notifications/unread-count',
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    }
  );

  const data = await response.json();
  return data.data.unread_count;
}
```

## Filtering by Role

Notifikasi otomatis difilter berdasarkan role user:

- **Leave Request Notifications:**
  - User dengan role yang bisa approve (Manager, HR, dll) akan melihat notifikasi permohonan cuti dari bawahan mereka
  - User biasa akan melihat notifikasi status cuti mereka sendiri (approved/rejected)

- **Program Notifications:**
  - Notifikasi dikirim berdasarkan role yang relevan (Producer, Manager, dll)

- **Music Notifications:**
  - Notifikasi dikirim ke role yang relevan (Music Arranger, Producer, dll)

## Migration Notes

Endpoint lama masih tersedia untuk backward compatibility:
- `/api/live-tv/notifications` - Main notifications
- `/api/notifications` - Program notifications
- `/api/music/notifications` - Music notifications

**Rekomendasi:** Gunakan endpoint unified untuk frontend baru.

## Benefits

1. **Satu Tempat untuk Semua Notifikasi** - Semua notifikasi dari berbagai sumber ditampilkan di satu tempat
2. **Filter Berdasarkan Role** - Notifikasi otomatis difilter berdasarkan role user
3. **Konsisten** - Format response yang konsisten untuk semua jenis notifikasi
4. **Mudah Digunakan** - Satu endpoint untuk semua kebutuhan notifikasi

