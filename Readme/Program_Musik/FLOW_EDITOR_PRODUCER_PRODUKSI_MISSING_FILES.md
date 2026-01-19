# Flow Editor → Producer → Produksi (Missing Files & Request)

## ✅ STATUS: **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

Dokumentasi ini menjelaskan flow lengkap dari Editor report missing files → Producer review → Producer request Produksi → Produksi handle request.

---

## 🔄 WORKFLOW LENGKAP

```
Editor Check File Completeness
    ↓ (file tidak lengkap)
Editor Report Missing Files
    ↓
Producer Terima Notifikasi
    ↓
Producer Lihat Missing Files Report
    ↓
Producer Request Produksi Action:
    ├─► Reshoot (jika file bermasalah)
    ├─► Complete Files (jika file belum komplit)
    └─► Fix (perbaikan)
    ↓
Produksi Terima Notifikasi
    ↓
Produksi Lihat Producer Requests
    ↓
Produksi Accept/Reject Request
    ↓ (jika accept)
    ├─► Jika Reshoot: Reset shooting files, status in_progress
    ├─► Jika Complete Files: Status in_progress
    └─► Jika Fix: Status in_progress
    ↓
Produksi Proses Pekerjaan (ulang)
    ├─► Input Run Sheet
    ├─► Upload Shooting Results
    ├─► Input File Links
    ├─► Return Equipment
    └─► Complete Work
    ↓
Kembali ke Editor (file sudah lengkap)
```

---

## 📋 DETAIL WORKFLOW

### **1. EDITOR - CEK KELENGKAPAN FILE**

#### **1.1. Editor - Cek Kelengkapan File**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/check-file-completeness`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::checkFileCompleteness()` (Line 269-357)

**Fitur:**
- ✅ Cek file dari Produksi (shooting files)
- ✅ Cek audio dari Sound Engineer Editing (approved)
- ✅ Auto-update source_files dengan info approved_audio dan produksi_work
- ✅ Jika lengkap → Auto-proceed to editing
- ✅ Jika tidak lengkap → Return missing files info

**Response jika file tidak lengkap:**
```json
{
  "success": true,
  "data": {
    "work": {...},
    "file_complete": false,
    "missing_files": [
      "Production shooting files",
      "Approved audio file from Sound Engineer"
    ],
    "has_production_files": false,
    "has_audio": false
  },
  "message": "Files are not complete. Please report missing files to Producer."
}
```

---

#### **1.2. Editor - Buat Catatan File Kurang/Perlu Perbaikan**
**Endpoint:** `POST /api/live-tv/editor/works/{id}/report-missing-files`

**Status:** ✅ **SUDAH ADA**

**Kode:** `EditorController::reportMissingFiles()` (Line 388-477)

**Fitur:**
- ✅ Input daftar file yang kurang atau perlu perbaikan
- ✅ Update file_notes dengan catatan missing files
- ✅ **Notifikasi ke Producer** ✅
- ✅ Update file_complete status

**Request Body:**
```json
{
  "missing_files": [
    {
      "file_type": "production_shooting",
      "description": "File syuting scene 3 tidak ada",
      "notes": "Scene 3 perlu di-syuting ulang karena kualitas video buruk"
    },
    {
      "file_type": "audio",
      "description": "Audio file belum approved",
      "notes": "Sound Engineer belum submit audio untuk QC"
    }
  ],
  "notes": "File tidak lengkap, perlu perbaikan dari Produksi dan Sound Engineer"
}
```

**Notification Type:** `editor_missing_files_reported`

**Data yang dikirim ke Producer:**
```json
{
  "editor_work_id": 1,
  "episode_id": 1,
  "missing_files": [...],
  "notes": "...",
  "editor_id": 5
}
```

---

### **2. PRODUCER - HANDLE EDITOR MISSING FILES**

#### **2.1. Producer - Terima Notifikasi**
**Dipicu oleh:** Editor report missing files  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `editor_missing_files_reported`

**Notifikasi dikirim di:** `EditorController::reportMissingFiles()` (Line 441-453)

---

#### **2.2. Producer - Lihat Missing Files Report**
**Endpoint:** `GET /api/live-tv/producer/editor-missing-files`

**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `ProducerController::getEditorMissingFiles()` (Line 4966-5008)

**Fitur:**
- ✅ Get semua Editor missing files reports
- ✅ Filter hanya dari production team Producer
- ✅ Include Editor Work detail dan file_notes
- ✅ Include missing_files array dan notes

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "notification_id": 10,
      "editor_work": {
        "id": 1,
        "episode_id": 1,
        "file_notes": "...",
        "createdBy": {...}
      },
      "missing_files": [
        {
          "file_type": "production_shooting",
          "description": "File syuting scene 3 tidak ada",
          "notes": "..."
        }
      ],
      "notes": "File tidak lengkap, perlu perbaikan",
      "reported_at": "2025-01-27 10:00:00",
      "editor": {...}
    }
  ],
  "message": "Editor missing files reports retrieved successfully"
}
```

---

#### **2.3. Producer - Request Produksi Action**
**Endpoint:** `POST /api/live-tv/producer/request-produksi-action`

**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `ProducerController::requestProduksiAction()` (Line 5010-5120)

**Fitur:**
- ✅ Request Produksi untuk action:
  - `reshoot` - Syuting ulang (jika file bermasalah)
  - `complete_files` - Melengkapi file yang kurang
  - `fix` - Perbaikan
- ✅ Validasi Producer memiliki akses ke ProduksiWork
- ✅ Simpan request ke `producer_requests` (JSON array)
- ✅ **Notifikasi ke Produksi** ✅
- ✅ Update ProduksiWork status ke `in_progress` jika sudah completed

**Request Body:**
```json
{
  "produksi_work_id": 1,
  "request_type": "reshoot", // atau "complete_files" atau "fix"
  "reason": "File syuting scene 3 bermasalah, perlu syuting ulang",
  "missing_files": [ // Untuk complete_files
    {
      "file_type": "production_shooting",
      "description": "File syuting scene 3"
    }
  ],
  "shooting_schedule": "2025-01-28 10:00:00", // Untuk reshoot
  "editor_work_id": 1 // Link ke Editor Work yang report missing files
}
```

**Notification Type:** `producer_request_produksi_action`

**Data yang dikirim ke Produksi:**
```json
{
  "produksi_work_id": 1,
  "episode_id": 1,
  "request_id": "req_xxx",
  "request_type": "reshoot",
  "reason": "...",
  "missing_files": [...],
  "shooting_schedule": "2025-01-28 10:00:00",
  "requested_by": 2,
  "requested_by_name": "Producer Name"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "produksi_work": {...},
    "request": {
      "id": "req_xxx",
      "request_type": "reshoot",
      "status": "pending",
      "requested_at": "2025-01-27 10:00:00"
    }
  },
  "message": "Request sent to Produksi successfully. Produksi team has been notified."
}
```

---

### **3. PRODUKSI - HANDLE PRODUCER REQUEST**

#### **3.1. Produksi - Terima Notifikasi**
**Dipicu oleh:** Producer request Produksi action  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `producer_request_produksi_action`

**Notifikasi dikirim di:** `ProducerController::requestProduksiAction()` (Line 5080-5100)

---

#### **3.2. Produksi - Lihat Producer Requests**
**Endpoint:** `GET /api/live-tv/produksi/producer-requests`

**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `ProduksiController::getProducerRequests()` (Line 925-976)

**Fitur:**
- ✅ Get semua pending Producer requests
- ✅ Filter hanya requests dengan status `pending`
- ✅ Include ProduksiWork detail dan episode info

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "request_id": "req_xxx",
      "produksi_work_id": 1,
      "episode_id": 1,
      "episode_number": "EP001",
      "request_type": "reshoot",
      "reason": "File syuting scene 3 bermasalah, perlu syuting ulang",
      "missing_files": [],
      "shooting_schedule": "2025-01-28 10:00:00",
      "requested_by": "Producer Name",
      "requested_at": "2025-01-27 10:00:00",
      "work": {...}
    }
  ],
  "message": "Producer requests retrieved successfully"
}
```

---

#### **3.3. Produksi - Terima Request dari Producer**
**Endpoint:** `POST /api/live-tv/produksi/producer-requests/{produksi_work_id}/accept`

**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `ProduksiController::acceptProducerRequest()` (Line 978-1080)

**Fitur:**
- ✅ Accept atau Reject Producer request
- ✅ Update request status di `producer_requests` array
- ✅ Jika accept reshoot: Reset shooting files dan status ke `in_progress`
- ✅ Jika accept complete_files/fix: Update status ke `in_progress`
- ✅ **Notifikasi ke Producer** ✅

**Request Body:**
```json
{
  "request_id": "req_xxx",
  "action": "accept", // atau "reject"
  "notes": "Akan syuting ulang scene 3 sesuai jadwal"
}
```

**Notification Type:** `produksi_accepted_producer_request`

**Data yang dikirim ke Producer:**
```json
{
  "produksi_work_id": 1,
  "episode_id": 1,
  "request_id": "req_xxx",
  "action": "accept",
  "notes": "..."
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "in_progress",
    "producer_requests": [...]
  },
  "message": "Producer request accepted successfully. Producer has been notified."
}
```

---

#### **3.4. Produksi - Proses Pekerjaan (Ulang)**

Setelah accept Producer request, Produksi akan melakukan workflow normal:

1. **Input Run Sheet** (jika reshoot)
   - Endpoint: `POST /api/live-tv/produksi/works/{id}/create-run-sheet`

2. **Upload Shooting Results**
   - Endpoint: `POST /api/live-tv/produksi/works/{id}/upload-shooting-results`
   - Auto-create EditorWork dan DesignGrafisWork

3. **Input File Links**
   - Endpoint: `POST /api/live-tv/produksi/works/{id}/input-file-links`

4. **Return Equipment**
   - Endpoint: `POST /api/live-tv/production/equipment/{id}/return`

5. **Complete Work**
   - Endpoint: `POST /api/live-tv/produksi/works/{id}/complete-work`

**Status:** ✅ **SUDAH ADA** (endpoint sudah ada, tinggal digunakan ulang)

---

### **4. KEMBALI KE EDITOR**

Setelah Produksi complete work, Editor akan:
- ✅ Terima notifikasi: `produksi_shooting_completed`
- ✅ EditorWork sudah auto-update dengan file baru
- ✅ Editor bisa cek kelengkapan file lagi
- ✅ Jika lengkap, Editor bisa lanjut proses editing

**Status:** ✅ **SUDAH ADA**

---

## 📋 RINGKASAN ENDPOINT

### **Editor:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/works/{id}/check-file-completeness` | POST | Cek kelengkapan file | ✅ |
| `/works/{id}/report-missing-files` | POST | Report missing files ke Producer | ✅ |

### **Producer:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/editor-missing-files` | GET | Lihat Editor missing files reports | ✅ |
| `/request-produksi-action` | POST | Request Produksi action (reshoot/complete/fix) | ✅ |

### **Produksi:**
| Endpoint | Method | Fungsi | Status |
|----------|--------|--------|--------|
| `/producer-requests` | GET | Lihat Producer requests | ✅ |
| `/producer-requests/{produksi_work_id}/accept` | POST | Accept/Reject Producer request | ✅ |

---

## 🔄 FLOW DETAIL PER REQUEST TYPE

### **1. RESHOOT (Syuting Ulang)**

```
Producer Request Reshoot
    ↓
Produksi Accept Request
    ↓
Reset shooting_files = null
Reset shooting_file_links = null
Status = in_progress
    ↓
Produksi:
    1. Input Run Sheet (baru)
    2. Request Equipment
    3. Request Needs
    4. Upload Shooting Results (baru)
    5. Input File Links
    6. Return Equipment
    7. Complete Work
    ↓
Editor Terima File Baru
```

---

### **2. COMPLETE FILES (Melengkapi File)**

```
Producer Request Complete Files
    ↓
Produksi Accept Request
    ↓
Status = in_progress
    ↓
Produksi:
    1. Upload Missing Files
    2. Update shooting_files dengan file tambahan
    3. Input File Links
    4. Complete Work
    ↓
Editor Terima File Lengkap
```

---

### **3. FIX (Perbaikan)**

```
Producer Request Fix
    ↓
Produksi Accept Request
    ↓
Status = in_progress
    ↓
Produksi:
    1. Perbaiki file yang bermasalah
    2. Upload file perbaikan
    3. Update shooting_files
    4. Input File Links
    5. Complete Work
    ↓
Editor Terima File Perbaikan
```

---

## 📊 DATABASE STRUCTURE

### **ProduksiWork.producer_requests (JSON Array):**

```json
[
  {
    "id": "req_xxx",
    "request_type": "reshoot|complete_files|fix",
    "reason": "Alasan request",
    "status": "pending|accepted|rejected",
    "requested_by": 2,
    "requested_by_name": "Producer Name",
    "requested_at": "2025-01-27 10:00:00",
    "accepted_by": 3,
    "accepted_by_name": "Produksi Name",
    "accepted_at": "2025-01-27 11:00:00",
    "notes": "Catatan dari Produksi",
    "editor_work_id": 1,
    "missing_files": [
      {
        "file_type": "production_shooting",
        "description": "..."
      }
    ],
    "shooting_schedule": "2025-01-28 10:00:00"
  }
]
```

---

## ✅ YANG SUDAH BENAR

1. ✅ Editor bisa cek kelengkapan file
2. ✅ Editor bisa report missing files ke Producer
3. ✅ Producer terima notifikasi missing files
4. ✅ Producer bisa lihat semua missing files reports
5. ✅ Producer bisa request Produksi untuk reshoot/complete/fix
6. ✅ Produksi terima notifikasi request dari Producer
7. ✅ Produksi bisa lihat semua Producer requests
8. ✅ Produksi bisa accept/reject request
9. ✅ Jika accept reshoot: auto-reset shooting files
10. ✅ Produksi bisa proses pekerjaan ulang
11. ✅ Editor terima file baru setelah Produksi complete

---

## 🎯 KESIMPULAN

**Status:** ✅ **LENGKAP - SEMUA FLOW SUDAH DIIMPLEMENTASIKAN**

- ✅ Editor → Producer (missing files report)
- ✅ Producer → Produksi (request action)
- ✅ Produksi → Producer (accept/reject)
- ✅ Produksi → Editor (file baru)

Semua endpoint sudah tersedia dan siap digunakan untuk frontend integration.

---

**Last Updated:** 2025-01-27
