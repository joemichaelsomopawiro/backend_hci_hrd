# 🎨 PROGRAM REGULAR - PANDUAN LENGKAP UNTUK FRONTEND

**Tanggal**: 15 Januari 2025  
**Status Backend**: ✅ **100% LENGKAP** - Siap untuk integrasi frontend!

---

## 📋 DAFTAR ISI

1. [Overview Sistem](#overview-sistem)
2. [Base URL & Authentication](#base-url--authentication)
3. [Role & Permission](#role--permission)
4. [Workflow Lengkap](#workflow-lengkap)
5. [API Endpoints Lengkap](#api-endpoints-lengkap)
6. [Request/Response Format](#requestresponse-format)
7. [Status & Enum Values](#status--enum-values)
8. [File Upload Guide](#file-upload-guide)
9. [Error Handling](#error-handling)
10. [Contoh Implementasi Frontend](#contoh-implementasi-frontend)

---

## 🎯 OVERVIEW SISTEM

### **Deskripsi**
Sistem Program Regular adalah sistem manajemen program reguler dengan workflow lengkap dari konsep hingga distribusi. Setiap program memiliki 53 episode per tahun (weekly schedule).

### **3 Role Utama:**
1. **Manager Program** - Membuat program, konsep, approve/reject, submit ke distribusi
2. **Producer** - Approve konsep, buat jadwal produksi, produksi, editing, upload file
3. **Manager Distribusi** - Verify program, buat jadwal tayang, laporan distribusi

### **Fitur Utama:**
- ✅ Konsep Program dengan approval workflow
- ✅ Auto-generate 53 episode per tahun
- ✅ Jadwal Produksi & Syuting per Episode
- ✅ Upload File setelah Editing
- ✅ Jadwal Tayang
- ✅ Laporan Distribusi
- ✅ Revisi tidak terbatas dengan history tracking
- ✅ Notifikasi terintegrasi

---

## 🔐 BASE URL & AUTHENTICATION

### **Base URL**
```
http://your-domain.com/api/program-regular
```

### **Authentication**
Semua endpoint memerlukan **Bearer Token** (Sanctum):

```javascript
headers: {
  'Authorization': `Bearer ${token}`,
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}
```

### **Contoh Setup Axios**
```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://your-domain.com/api/program-regular',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Add token interceptor
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

---

## 👥 ROLE & PERMISSION

### **Role yang Bisa Mengakses:**

| Role | Endpoint Prefix | Akses |
|------|----------------|-------|
| **Manager Program** | `/manager-program/*` | Full access untuk semua fitur Manager Program |
| **Producer** | `/producer/*` | Full access untuk semua fitur Producer |
| **Manager Distribusi** | `/distribusi/*` | Full access untuk semua fitur Manager Distribusi |
| **Semua Role** | `/manager-program/programs` | Bisa lihat list semua program |
| **Semua Role** | `/revisions/*` | Bisa request revisi |

### **Permission Check di Frontend:**
```javascript
// Contoh check role
const userRole = localStorage.getItem('user_role');

if (userRole === 'Manager Program') {
  // Show Manager Program features
} else if (userRole === 'Producer') {
  // Show Producer features
} else if (userRole === 'Manager Distribusi') {
  // Show Manager Distribusi features
}
```

---

## 🔄 WORKFLOW LENGKAP

### **Flow 1: Manager Program → Producer**

```
1. Manager Program membuat program baru
   POST /manager-program/programs

2. Manager Program membuat konsep
   POST /manager-program/programs/{id}/concepts

3. Producer melihat konsep untuk approval
   GET /producer/concepts

4. Producer approve/reject konsep
   POST /producer/concepts/{id}/approve
   POST /producer/concepts/{id}/reject

5. Jika reject → Manager Program bisa revisi (createConcept lagi)
   POST /manager-program/programs/{id}/concepts

6. Jika approve → Producer buat jadwal produksi
   POST /producer/programs/{id}/production-schedules
```

### **Flow 2: Producer → Manager Program**

```
1. Producer buat jadwal syuting per episode
   POST /producer/programs/{id}/production-schedules
   (dengan episode_id)

2. Producer update status episode: production
   PUT /producer/episodes/{id}/status
   { "status": "production" }

3. Producer update status episode: editing
   PUT /producer/episodes/{id}/status
   { "status": "editing" }

4. Producer upload file setelah editing
   POST /producer/episodes/{id}/files
   (multipart/form-data)

5. Revisi? (Decision)
   - Jika Ya: POST /revisions/programs/{id}/request
   - Jika Tidak: Lanjut ke step 6

6. Producer submit ke Manager Program
   POST /producer/programs/{id}/submit-to-manager

7. Manager Program approve/reject
   POST /manager-program/programs/{id}/approve
   POST /manager-program/programs/{id}/reject
```

### **Flow 3: Manager Program → Manager Distribusi**

```
1. Manager Program submit ke Manager Distribusi
   POST /manager-program/programs/{id}/submit-to-distribusi

2. Manager Distribusi verify program
   POST /distribusi/programs/{id}/verify

3. Manager Distribusi buat jadwal tayang
   POST /distribusi/programs/{id}/distribution-schedules
```

### **Flow 4: Manager Distribusi → Complete**

```
1. Tayang? (Decision)
   - Jika Ya: POST /distribusi/episodes/{id}/mark-aired
   - Jika Tidak: Update/delete schedule lalu buat baru

2. Manager Distribusi buat laporan distribusi
   POST /distribusi/programs/{id}/distribution-reports
```

---

## 📡 API ENDPOINTS LENGKAP

### **1. MANAGER PROGRAM ENDPOINTS**

#### **1.1. Create Program**
```http
POST /manager-program/programs
```

**Request Body:**
```json
{
  "name": "Nama Program",
  "description": "Deskripsi program",
  "start_date": "2025-01-01",
  "air_time": "19:00:00",
  "duration_minutes": 60,
  "broadcast_channel": "TVRI",
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
    "name": "Nama Program",
    "status": "draft",
    "episodes": [...], // 53 episodes auto-generated
    ...
  }
}
```

#### **1.2. List All Programs**
```http
GET /manager-program/programs?page=1&per_page=15&status=draft&year=2025
```

**Query Parameters:**
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 15)
- `status` (optional): Filter by status
- `year` (optional): Filter by program_year
- `search` (optional): Search by name

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [...],
    "total": 10,
    "per_page": 15
  }
}
```

#### **1.3. Show Program Detail**
```http
GET /manager-program/programs/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Nama Program",
    "status": "concept_pending",
    "concepts": [...],
    "episodes": [...],
    "production_schedules": [...],
    "distribution_schedules": [...],
    ...
  }
}
```

#### **1.4. Update Program**
```http
PUT /manager-program/programs/{id}
```

**Request Body:**
```json
{
  "name": "Nama Program Updated",
  "description": "Deskripsi updated",
  "air_time": "20:00:00",
  "duration_minutes": 90
}
```

#### **1.5. Delete Program (Soft Delete)**
```http
DELETE /manager-program/programs/{id}
```

#### **1.6. Create Concept**
```http
POST /manager-program/programs/{id}/concepts
```

**Request Body:**
```json
{
  "concept": "Konsep program lengkap",
  "objectives": "Tujuan program",
  "target_audience": "Target audience",
  "content_outline": "Outline konten",
  "format_description": "Deskripsi format"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Konsep berhasil dibuat",
  "data": {
    "id": 1,
    "program_id": 1,
    "status": "pending_approval",
    ...
  }
}
```

#### **1.7. Approve Program (from Producer)**
```http
POST /manager-program/programs/{id}/approve
```

**Request Body:**
```json
{
  "notes": "Program sudah bagus, approved"
}
```

#### **1.8. Reject Program (from Producer)**
```http
POST /manager-program/programs/{id}/reject
```

**Request Body:**
```json
{
  "notes": "Perlu revisi pada bagian editing"
}
```

#### **1.9. Submit to Manager Distribusi**
```http
POST /manager-program/programs/{id}/submit-to-distribusi
```

**Request Body:**
```json
{
  "notes": "Program siap untuk distribusi"
}
```

#### **1.10. View Schedules**
```http
GET /manager-program/programs/{id}/schedules
```

**Response:**
```json
{
  "success": true,
  "data": {
    "production_schedules": [...],
    "distribution_schedules": [...]
  }
}
```

#### **1.11. View Distribution Reports**
```http
GET /manager-program/programs/{id}/distribution-reports
```

#### **1.12. View Revision History**
```http
GET /manager-program/programs/{id}/revision-history?page=1&per_page=15
```

#### **1.13. Update Episode**
```http
PUT /manager-program/episodes/{id}
```

**Request Body:**
```json
{
  "title": "Episode 1 Title",
  "description": "Episode description",
  "air_date": "2025-01-05",
  "production_date": "2025-01-01"
}
```

#### **1.14. Delete Episode**
```http
DELETE /manager-program/episodes/{id}
```

---

### **2. PRODUCER ENDPOINTS**

#### **2.1. List Concepts for Approval**
```http
GET /producer/concepts?page=1&per_page=15&status=pending_approval
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "program": {...},
        "concept": "...",
        "status": "pending_approval",
        ...
      }
    ]
  }
}
```

#### **2.2. Approve Concept**
```http
POST /producer/concepts/{id}/approve
```

**Request Body:**
```json
{
  "notes": "Konsep sudah bagus, approved"
}
```

#### **2.3. Reject Concept**
```http
POST /producer/concepts/{id}/reject
```

**Request Body:**
```json
{
  "notes": "Perlu revisi konsep"
}
```

#### **2.4. Create Production Schedule**
```http
POST /producer/programs/{id}/production-schedules
```

**Request Body:**
```json
{
  "episode_id": 1, // Optional, jika untuk episode tertentu
  "scheduled_date": "2025-01-10",
  "scheduled_time": "09:00:00",
  "schedule_notes": "Jadwal syuting episode 1"
}
```

#### **2.5. Update Production Schedule**
```http
PUT /producer/production-schedules/{id}
```

**Request Body:**
```json
{
  "scheduled_date": "2025-01-12",
  "scheduled_time": "10:00:00",
  "schedule_notes": "Jadwal diubah"
}
```

#### **2.6. Delete Production Schedule**
```http
DELETE /producer/production-schedules/{id}
```

#### **2.7. Update Episode Status**
```http
PUT /producer/episodes/{id}/status
```

**Request Body:**
```json
{
  "status": "production" // atau "editing", "ready_for_review"
}
```

**Valid Status Values:**
- `scheduled`
- `production`
- `editing`
- `ready_for_review`
- `manager_approved`
- `aired`
- `cancelled`

#### **2.8. Update Episode**
```http
PUT /producer/episodes/{id}
```

**Request Body:**
```json
{
  "title": "Episode Title",
  "description": "Description",
  "production_notes": "Notes produksi",
  "editing_notes": "Notes editing"
}
```

#### **2.9. Delete Episode**
```http
DELETE /producer/episodes/{id}
```

#### **2.10. Upload File**
```http
POST /producer/episodes/{id}/files
```

**Request:** `multipart/form-data`

**Form Data:**
```
file: [File]
category: "edited_video" // raw_footage, edited_video, thumbnail, script, rundown, other
description: "File description (optional)"
```

**Response:**
```json
{
  "success": true,
  "message": "File berhasil diupload",
  "data": {
    "id": 1,
    "file_name": "video_edited.mp4",
    "file_path": "program-regular/files/...",
    "file_url": "http://...",
    "file_size": 1024000,
    "category": "edited_video"
  }
}
```

#### **2.11. Submit to Manager Program**
```http
POST /producer/programs/{id}/submit-to-manager
```

**Request Body:**
```json
{
  "notes": "Produksi dan editing sudah selesai"
}
```

#### **2.12. View Distribution Schedules**
```http
GET /producer/programs/{id}/distribution-schedules
```

#### **2.13. View Distribution Reports**
```http
GET /producer/programs/{id}/distribution-reports
```

#### **2.14. View Revision History**
```http
GET /producer/programs/{id}/revision-history
```

---

### **3. MANAGER DISTRIBUSI ENDPOINTS**

#### **3.1. List Programs for Distribusi**
```http
GET /distribusi/programs?page=1&per_page=15&status=submitted_to_distribusi
```

#### **3.2. Verify Program**
```http
POST /distribusi/programs/{id}/verify
```

**Request Body:**
```json
{
  "notes": "Program sudah diverifikasi, siap untuk distribusi"
}
```

#### **3.3. View Program Concept**
```http
GET /distribusi/programs/{id}/concept
```

#### **3.4. View Production Schedules**
```http
GET /distribusi/programs/{id}/production-schedules
```

#### **3.5. View Shooting Schedule (per Episode)**
```http
GET /distribusi/episodes/{id}/shooting-schedule
```

**Response:**
```json
{
  "success": true,
  "data": {
    "episode": {...},
    "production_schedules": [...]
  }
}
```

#### **3.6. View Program Files**
```http
GET /distribusi/programs/{id}/files?category=edited_video
```

**Query Parameters:**
- `category` (optional): Filter by category
- `episode_id` (optional): Filter by episode

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "file_name": "video.mp4",
      "file_url": "http://...",
      "category": "edited_video",
      "file_size": 1024000,
      "episode": {...}
    }
  ]
}
```

#### **3.7. Create Distribution Schedule**
```http
POST /distribusi/programs/{id}/distribution-schedules
```

**Request Body:**
```json
{
  "episode_id": 1,
  "schedule_date": "2025-01-15",
  "schedule_time": "19:00:00",
  "channel": "TVRI",
  "schedule_notes": "Jadwal tayang episode 1"
}
```

#### **3.8. Update Distribution Schedule**
```http
PUT /distribusi/distribution-schedules/{id}
```

**Request Body:**
```json
{
  "schedule_date": "2025-01-16",
  "schedule_time": "20:00:00",
  "channel": "TVRI",
  "schedule_notes": "Jadwal diubah"
}
```

#### **3.9. Delete Distribution Schedule**
```http
DELETE /distribusi/distribution-schedules/{id}
```

#### **3.10. Mark Episode as Aired**
```http
POST /distribusi/episodes/{id}/mark-aired
```

**Request Body:**
```json
{
  "notes": "Episode sudah tayang"
}
```

#### **3.11. Create Distribution Report**
```http
POST /distribusi/programs/{id}/distribution-reports
```

**Request Body:**
```json
{
  "episode_id": 1, // Optional
  "report_title": "Laporan Distribusi Bulan Januari",
  "report_content": "Isi laporan distribusi",
  "distribution_data": {
    "platform": "TVRI",
    "views": 10000,
    "engagement": 500
  },
  "analytics_data": {
    "peak_viewers": 5000,
    "average_viewers": 3000
  },
  "report_period_start": "2025-01-01",
  "report_period_end": "2025-01-31"
}
```

#### **3.12. List Distribution Reports**
```http
GET /distribusi/distribution-reports?page=1&per_page=15&program_id=1
```

#### **3.13. Update Distribution Report**
```http
PUT /distribusi/distribution-reports/{id}
```

#### **3.14. Delete Distribution Report**
```http
DELETE /distribusi/distribution-reports/{id}
```

#### **3.15. View Revision History**
```http
GET /distribusi/programs/{id}/revision-history
```

---

### **4. REVISION ENDPOINTS (Semua Role)**

#### **4.1. Request Revision**
```http
POST /revisions/programs/{id}/request
```

**Request Body:**
```json
{
  "revision_type": "concept", // concept, production, editing, distribution
  "revision_reason": "Perlu revisi pada konsep program",
  "before_data": {
    "field": "old_value"
  },
  "after_data": {
    "field": "new_value"
  },
  "notes": "Detail revisi yang diminta"
}
```

#### **4.2. Get Revision History**
```http
GET /revisions/programs/{id}/history?page=1&per_page=15
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "revision_type": "concept",
        "status": "pending",
        "revision_reason": "...",
        "requester": {...},
        "reviewer": null,
        "created_at": "2025-01-15 10:00:00"
      }
    ]
  }
}
```

#### **4.3. Approve Revision (Manager Program Only)**
```http
POST /revisions/{id}/approve
```

**Request Body:**
```json
{
  "notes": "Revisi disetujui"
}
```

#### **4.4. Reject Revision (Manager Program Only)**
```http
POST /revisions/{id}/reject
```

**Request Body:**
```json
{
  "notes": "Revisi ditolak karena..."
}
```

---

## 📊 STATUS & ENUM VALUES

### **Program Status**
```javascript
const PROGRAM_STATUS = {
  DRAFT: 'draft',
  CONCEPT_PENDING: 'concept_pending',
  CONCEPT_APPROVED: 'concept_approved',
  CONCEPT_REJECTED: 'concept_rejected',
  PRODUCTION_SCHEDULED: 'production_scheduled',
  IN_PRODUCTION: 'in_production',
  EDITING: 'editing',
  SUBMITTED_TO_MANAGER: 'submitted_to_manager',
  MANAGER_APPROVED: 'manager_approved',
  MANAGER_REJECTED: 'manager_rejected',
  SUBMITTED_TO_DISTRIBUSI: 'submitted_to_distribusi',
  DISTRIBUSI_APPROVED: 'distribusi_approved',
  DISTRIBUSI_REJECTED: 'distribusi_rejected',
  SCHEDULED: 'scheduled',
  DISTRIBUTED: 'distributed',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled'
};
```

### **Concept Status**
```javascript
const CONCEPT_STATUS = {
  DRAFT: 'draft',
  PENDING_APPROVAL: 'pending_approval',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  REVISED: 'revised'
};
```

### **Episode Status**
```javascript
const EPISODE_STATUS = {
  SCHEDULED: 'scheduled',
  PRODUCTION: 'production',
  EDITING: 'editing',
  READY_FOR_REVIEW: 'ready_for_review',
  MANAGER_APPROVED: 'manager_approved',
  AIRED: 'aired',
  CANCELLED: 'cancelled'
};
```

### **File Category**
```javascript
const FILE_CATEGORY = {
  RAW_FOOTAGE: 'raw_footage',
  EDITED_VIDEO: 'edited_video',
  THUMBNAIL: 'thumbnail',
  SCRIPT: 'script',
  RUNDOWN: 'rundown',
  OTHER: 'other'
};
```

### **Revision Type**
```javascript
const REVISION_TYPE = {
  CONCEPT: 'concept',
  PRODUCTION: 'production',
  EDITING: 'editing',
  DISTRIBUTION: 'distribution'
};
```

### **Revision Status**
```javascript
const REVISION_STATUS = {
  PENDING: 'pending',
  APPROVED: 'approved',
  REJECTED: 'rejected'
};
```

---

## 📁 FILE UPLOAD GUIDE

### **Endpoint**
```http
POST /producer/episodes/{id}/files
```

### **Content-Type**
```
multipart/form-data
```

### **Form Fields**
- `file` (required): File yang diupload
- `category` (required): Kategori file (enum)
- `description` (optional): Deskripsi file

### **File Size Limit**
- **Max Size**: 100GB (konfigurasi backend)
- **Allowed Types**: Semua file type (validasi di backend)

### **Contoh Upload dengan Axios**
```javascript
const uploadFile = async (episodeId, file, category, description = '') => {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('category', category);
  if (description) {
    formData.append('description', description);
  }

  try {
    const response = await api.post(
      `/producer/episodes/${episodeId}/files`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        onUploadProgress: (progressEvent) => {
          const percentCompleted = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
          console.log(`Upload Progress: ${percentCompleted}%`);
        }
      }
    );
    return response.data;
  } catch (error) {
    console.error('Upload error:', error);
    throw error;
  }
};
```

### **Contoh Upload dengan Fetch**
```javascript
const uploadFile = async (episodeId, file, category, description = '') => {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('category', category);
  if (description) {
    formData.append('description', description);
  }

  const response = await fetch(
    `${baseURL}/producer/episodes/${episodeId}/files`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    }
  );

  return await response.json();
};
```

---

## ⚠️ ERROR HANDLING

### **Standard Error Response Format**
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field_name": ["Error message for field"]
  }
}
```

### **HTTP Status Codes**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

### **Contoh Error Handling di Frontend**
```javascript
try {
  const response = await api.post('/manager-program/programs', data);
  // Success handling
} catch (error) {
  if (error.response) {
    // Server responded with error
    const { status, data } = error.response;
    
    if (status === 422) {
      // Validation errors
      const errors = data.errors;
      // Display validation errors
    } else if (status === 401) {
      // Unauthorized - redirect to login
      router.push('/login');
    } else {
      // Other errors
      alert(data.message || 'Terjadi kesalahan');
    }
  } else {
    // Network error
    alert('Tidak dapat terhubung ke server');
  }
}
```

---

## 💻 CONTOH IMPLEMENTASI FRONTEND

### **1. Setup API Service**

**File: `services/programRegularService.js`**
```javascript
import api from './api'; // Your axios instance

const BASE_URL = '/program-regular';

export const programRegularService = {
  // Manager Program
  createProgram: (data) => api.post(`${BASE_URL}/manager-program/programs`, data),
  listPrograms: (params) => api.get(`${BASE_URL}/manager-program/programs`, { params }),
  getProgram: (id) => api.get(`${BASE_URL}/manager-program/programs/${id}`),
  updateProgram: (id, data) => api.put(`${BASE_URL}/manager-program/programs/${id}`, data),
  deleteProgram: (id) => api.delete(`${BASE_URL}/manager-program/programs/${id}`),
  createConcept: (programId, data) => api.post(`${BASE_URL}/manager-program/programs/${programId}/concepts`, data),
  approveProgram: (id, data) => api.post(`${BASE_URL}/manager-program/programs/${id}/approve`, data),
  rejectProgram: (id, data) => api.post(`${BASE_URL}/manager-program/programs/${id}/reject`, data),
  submitToDistribusi: (id, data) => api.post(`${BASE_URL}/manager-program/programs/${id}/submit-to-distribusi`, data),
  viewSchedules: (id) => api.get(`${BASE_URL}/manager-program/programs/${id}/schedules`),
  viewDistributionReports: (id) => api.get(`${BASE_URL}/manager-program/programs/${id}/distribution-reports`),
  viewRevisionHistory: (id, params) => api.get(`${BASE_URL}/manager-program/programs/${id}/revision-history`, { params }),
  updateEpisode: (id, data) => api.put(`${BASE_URL}/manager-program/episodes/${id}`, data),
  deleteEpisode: (id) => api.delete(`${BASE_URL}/manager-program/episodes/${id}`),

  // Producer
  listConceptsForApproval: (params) => api.get(`${BASE_URL}/producer/concepts`, { params }),
  approveConcept: (id, data) => api.post(`${BASE_URL}/producer/concepts/${id}/approve`, data),
  rejectConcept: (id, data) => api.post(`${BASE_URL}/producer/concepts/${id}/reject`, data),
  createProductionSchedule: (programId, data) => api.post(`${BASE_URL}/producer/programs/${programId}/production-schedules`, data),
  updateProductionSchedule: (id, data) => api.put(`${BASE_URL}/producer/production-schedules/${id}`, data),
  deleteProductionSchedule: (id) => api.delete(`${BASE_URL}/producer/production-schedules/${id}`),
  updateEpisodeStatus: (id, data) => api.put(`${BASE_URL}/producer/episodes/${id}/status`, data),
  updateEpisode: (id, data) => api.put(`${BASE_URL}/producer/episodes/${id}`, data),
  deleteEpisode: (id) => api.delete(`${BASE_URL}/producer/episodes/${id}`),
  uploadFile: (episodeId, formData) => api.post(`${BASE_URL}/producer/episodes/${episodeId}/files`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  }),
  submitToManager: (id, data) => api.post(`${BASE_URL}/producer/programs/${id}/submit-to-manager`, data),
  viewDistributionSchedules: (id) => api.get(`${BASE_URL}/producer/programs/${id}/distribution-schedules`),
  viewDistributionReports: (id) => api.get(`${BASE_URL}/producer/programs/${id}/distribution-reports`),
  viewRevisionHistory: (id, params) => api.get(`${BASE_URL}/producer/programs/${id}/revision-history`, { params }),

  // Manager Distribusi
  listProgramsForDistribusi: (params) => api.get(`${BASE_URL}/distribusi/programs`, { params }),
  verifyProgram: (id, data) => api.post(`${BASE_URL}/distribusi/programs/${id}/verify`, data),
  viewProgramConcept: (id) => api.get(`${BASE_URL}/distribusi/programs/${id}/concept`),
  viewProductionSchedules: (id) => api.get(`${BASE_URL}/distribusi/programs/${id}/production-schedules`),
  viewShootingSchedule: (episodeId) => api.get(`${BASE_URL}/distribusi/episodes/${episodeId}/shooting-schedule`),
  viewProgramFiles: (id, params) => api.get(`${BASE_URL}/distribusi/programs/${id}/files`, { params }),
  createDistributionSchedule: (programId, data) => api.post(`${BASE_URL}/distribusi/programs/${programId}/distribution-schedules`, data),
  updateDistributionSchedule: (id, data) => api.put(`${BASE_URL}/distribusi/distribution-schedules/${id}`, data),
  deleteDistributionSchedule: (id) => api.delete(`${BASE_URL}/distribusi/distribution-schedules/${id}`),
  markAsAired: (episodeId, data) => api.post(`${BASE_URL}/distribusi/episodes/${episodeId}/mark-aired`, data),
  createDistributionReport: (programId, data) => api.post(`${BASE_URL}/distribusi/programs/${programId}/distribution-reports`, data),
  listDistributionReports: (params) => api.get(`${BASE_URL}/distribusi/distribution-reports`, { params }),
  updateDistributionReport: (id, data) => api.put(`${BASE_URL}/distribusi/distribution-reports/${id}`, data),
  deleteDistributionReport: (id) => api.delete(`${BASE_URL}/distribusi/distribution-reports/${id}`),
  viewRevisionHistory: (id, params) => api.get(`${BASE_URL}/distribusi/programs/${id}/revision-history`, { params }),

  // Revisions
  requestRevision: (programId, data) => api.post(`${BASE_URL}/revisions/programs/${programId}/request`, data),
  getRevisionHistory: (programId, params) => api.get(`${BASE_URL}/revisions/programs/${programId}/history`, { params }),
  approveRevision: (id, data) => api.post(`${BASE_URL}/revisions/${id}/approve`, data),
  rejectRevision: (id, data) => api.post(`${BASE_URL}/revisions/${id}/reject`, data),
};
```

### **2. Contoh Component Vue.js**

**File: `components/ProgramRegular/CreateProgram.vue`**
```vue
<template>
  <div>
    <form @submit.prevent="handleSubmit">
      <div>
        <label>Nama Program</label>
        <input v-model="form.name" required />
      </div>
      
      <div>
        <label>Deskripsi</label>
        <textarea v-model="form.description" required></textarea>
      </div>
      
      <div>
        <label>Tanggal Mulai</label>
        <input type="date" v-model="form.start_date" required />
      </div>
      
      <div>
        <label>Waktu Tayang</label>
        <input type="time" v-model="form.air_time" required />
      </div>
      
      <div>
        <label>Durasi (menit)</label>
        <input type="number" v-model="form.duration_minutes" required />
      </div>
      
      <div>
        <label>Channel</label>
        <input v-model="form.broadcast_channel" required />
      </div>
      
      <div>
        <label>Tahun Program</label>
        <input type="number" v-model="form.program_year" required />
      </div>
      
      <button type="submit" :disabled="loading">
        {{ loading ? 'Menyimpan...' : 'Buat Program' }}
      </button>
    </form>
  </div>
</template>

<script>
import { programRegularService } from '@/services/programRegularService';

export default {
  data() {
    return {
      form: {
        name: '',
        description: '',
        start_date: '',
        air_time: '',
        duration_minutes: 60,
        broadcast_channel: '',
        program_year: new Date().getFullYear()
      },
      loading: false
    };
  },
  methods: {
    async handleSubmit() {
      this.loading = true;
      try {
        const response = await programRegularService.createProgram(this.form);
        this.$toast.success('Program berhasil dibuat');
        this.$router.push(`/program-regular/${response.data.data.id}`);
      } catch (error) {
        if (error.response?.status === 422) {
          // Handle validation errors
          const errors = error.response.data.errors;
          // Display errors
        } else {
          this.$toast.error(error.response?.data?.message || 'Terjadi kesalahan');
        }
      } finally {
        this.loading = false;
      }
    }
  }
};
</script>
```

### **3. Contoh File Upload Component**

**File: `components/ProgramRegular/FileUpload.vue`**
```vue
<template>
  <div>
    <input
      type="file"
      ref="fileInput"
      @change="handleFileSelect"
      :accept="acceptTypes"
    />
    
    <select v-model="category" required>
      <option value="">Pilih Kategori</option>
      <option value="raw_footage">Raw Footage</option>
      <option value="edited_video">Edited Video</option>
      <option value="thumbnail">Thumbnail</option>
      <option value="script">Script</option>
      <option value="rundown">Rundown</option>
      <option value="other">Other</option>
    </select>
    
    <input
      type="text"
      v-model="description"
      placeholder="Deskripsi (optional)"
    />
    
    <button @click="uploadFile" :disabled="!selectedFile || uploading">
      {{ uploading ? `Uploading... ${uploadProgress}%` : 'Upload File' }}
    </button>
    
    <div v-if="uploadedFile">
      <p>File berhasil diupload!</p>
      <a :href="uploadedFile.file_url" target="_blank">Download</a>
    </div>
  </div>
</template>

<script>
import { programRegularService } from '@/services/programRegularService';

export default {
  props: {
    episodeId: {
      type: Number,
      required: true
    }
  },
  data() {
    return {
      selectedFile: null,
      category: '',
      description: '',
      uploading: false,
      uploadProgress: 0,
      uploadedFile: null,
      acceptTypes: '*/*' // Atau spesifik: 'video/*,image/*'
    };
  },
  methods: {
    handleFileSelect(event) {
      this.selectedFile = event.target.files[0];
    },
    async uploadFile() {
      if (!this.selectedFile || !this.category) {
        alert('Pilih file dan kategori');
        return;
      }

      const formData = new FormData();
      formData.append('file', this.selectedFile);
      formData.append('category', this.category);
      if (this.description) {
        formData.append('description', this.description);
      }

      this.uploading = true;
      try {
        const response = await programRegularService.uploadFile(
          this.episodeId,
          formData,
          (progressEvent) => {
            this.uploadProgress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
          }
        );
        this.uploadedFile = response.data;
        this.$toast.success('File berhasil diupload');
        this.$emit('uploaded', response.data);
      } catch (error) {
        this.$toast.error(error.response?.data?.message || 'Upload gagal');
      } finally {
        this.uploading = false;
      }
    }
  }
};
</script>
```

---

## ✅ CHECKLIST IMPLEMENTASI FRONTEND

### **Phase 1: Setup & Authentication**
- [ ] Setup API service dengan axios/fetch
- [ ] Setup authentication interceptor
- [ ] Setup error handling global
- [ ] Setup loading states

### **Phase 2: Manager Program Features**
- [ ] Create Program form
- [ ] List Programs dengan pagination & filter
- [ ] Program Detail page
- [ ] Create Concept form
- [ ] Approve/Reject Program
- [ ] Submit to Distribusi
- [ ] View Schedules
- [ ] View Distribution Reports
- [ ] View Revision History
- [ ] Manage Episodes (Edit/Delete)

### **Phase 3: Producer Features**
- [ ] List Concepts for Approval
- [ ] Approve/Reject Concept
- [ ] Create/Update/Delete Production Schedule
- [ ] Update Episode Status
- [ ] Upload File component
- [ ] Submit to Manager Program
- [ ] View Distribution Schedules
- [ ] View Distribution Reports
- [ ] View Revision History

### **Phase 4: Manager Distribusi Features**
- [ ] List Programs for Distribusi
- [ ] Verify Program
- [ ] View Program Concept
- [ ] View Production Schedules
- [ ] View Shooting Schedule per Episode
- [ ] View Program Files
- [ ] Create/Update/Delete Distribution Schedule
- [ ] Mark Episode as Aired
- [ ] Create/Update/Delete Distribution Report
- [ ] View Revision History

### **Phase 5: Revision Features**
- [ ] Request Revision form
- [ ] Revision History list
- [ ] Approve/Reject Revision (Manager Program)

### **Phase 6: UI/UX Enhancements**
- [ ] Status badges dengan warna
- [ ] Workflow visualization
- [ ] Notifikasi real-time
- [ ] File preview/download
- [ ] Calendar view untuk schedules
- [ ] Dashboard dengan statistics

---

## 📝 NOTES PENTING

1. **Auto-generate Episodes**: Saat create program, backend otomatis generate 53 episode. Tidak perlu create episode manual di frontend.

2. **Status Workflow**: Pastikan status mengikuti workflow yang benar. Jangan skip status.

3. **File Upload**: Gunakan `multipart/form-data` untuk upload file. Max size 100GB.

4. **Pagination**: Semua list endpoint support pagination dengan `page` dan `per_page`.

5. **Soft Delete**: Delete = Archive. Data tidak benar-benar dihapus.

6. **Revisi**: Revisi bisa dilakukan tidak terbatas sampai disetujui Manager Program.

7. **Notifikasi**: Backend sudah terintegrasi dengan sistem notifikasi. Frontend hanya perlu listen untuk notifikasi baru.

---

## 🎉 KESIMPULAN

**Backend sudah 100% lengkap dan siap untuk integrasi frontend!**

Semua endpoint sudah tersedia, dokumentasi lengkap, dan workflow sudah jelas. Silakan mulai implementasi frontend sesuai dengan panduan di atas.

**Total Endpoints**: 35+ endpoints  
**Total Tables**: 8 tables  
**Status**: ✅ **READY FOR FRONTEND DEVELOPMENT**

---

**Last Updated**: 15 Januari 2025  
**Backend Status**: ✅ **100% COMPLETE**
