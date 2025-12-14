# ✅ Verifikasi Flow Setelah Producer Approve Creative Work

**Tanggal:** 12 Desember 2025  
**Status:** ✅ **SEMUA FLOW SUDAH DIIMPLEMENTASIKAN & AMAN**

---

## 📋 Ringkasan Eksekutif

Semua flow setelah Producer approve creative work sudah **LENGKAP** dan **AMAN**. Semua role (General Affairs, Promosi, Produksi, Sound Engineer) sudah memiliki endpoint dan workflow yang sesuai dengan requirement.

---

## 🔄 FLOW LENGKAP SETELAH PRODUCER APPROVE

### **FLOW 1: General Affairs - Permohonan Dana**

**Status:** ✅ **LENGKAP & AMAN**

#### **1.1. General Affairs: Menerima Permohonan Dana**

**Notifikasi yang Diterima:**
- ✅ `budget_request_created` - Producer memohon dana setelah approve creative work

**Endpoint:** `GET /api/live-tv/general-affairs/budget-requests/from-creative-work`

**Controller:** `GeneralAffairsController::getCreativeWorkBudgetRequests()`

**File:** `app/Http/Controllers/Api/GeneralAffairsController.php` (line 266-309)

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "program_id": 1,
        "request_type": "creative_work",
        "title": "Permohonan Dana untuk Episode 1",
        "description": "Permohonan dana untuk creative work Episode 1. Budget: Rp 5.000.000",
        "requested_amount": 5000000,
        "status": "pending",
        "requested_by": 3,
        "requested_at": "2025-12-10 10:00:00"
      }
    ]
  }
}
```

---

#### **1.2. General Affairs: Memproses Permohonan Dana**

**Endpoint:** `POST /api/live-tv/general-affairs/budget-requests/{id}/approve`

**Request Body:**
```json
{
  "approved_amount": 5000000,
  "approval_notes": "Budget disetujui",
  "payment_method": "transfer",
  "payment_schedule": "2025-12-15"
}
```

**Flow:**
- ✅ Approve budget request
- ✅ Status: `pending` → `approved`
- ✅ Notifikasi ke Producer: `budget_request_approved`

**Controller:** `GeneralAffairsController::approve()`

**File:** `app/Http/Controllers/Api/GeneralAffairsController.php` (line 81-134)

---

#### **1.3. General Affairs: Memberikan Dana ke Producer**

**Endpoint:** `POST /api/live-tv/general-affairs/budget-requests/{id}/process-payment`

**Request Body:**
```json
{
  "payment_receipt": "receipt_number_12345",
  "payment_notes": "Dana telah ditransfer",
  "payment_date": "2025-12-15"
}
```

**Flow:**
- ✅ Process payment
- ✅ Status: `approved` → `paid`
- ✅ Notifikasi ke Producer: `fund_released`
- ✅ Message: "Dana sebesar Rp X telah diberikan oleh General Affairs"

**Controller:** `GeneralAffairsController::processPayment()`

**File:** `app/Http/Controllers/Api/GeneralAffairsController.php` (line 193-260)

---

### **FLOW 2: Promosi - BTS Video & Foto Talent**

**Status:** ✅ **LENGKAP & AMAN**

#### **2.1. Promosi: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `promotion_work_created` - Promotion work task dibuat setelah Producer approve creative work

**Endpoint:** `GET /api/notifications`

---

#### **2.2. Promosi: Terima Jadwal Syuting**

**Endpoint:** `POST /api/live-tv/roles/promosi/works/{id}/accept-schedule`

**Flow:**
- ✅ Ambil shooting schedule dari Creative Work
- ✅ Update work dengan shooting schedule dan location
- ✅ Status: tetap atau update

**Controller:** `PromosiController::acceptSchedule()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 546-593)

---

#### **2.3. Promosi: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/promosi/works/{id}/accept-work`

**Flow:**
- ✅ Status: `planning` → `shooting`
- ✅ Promosi siap untuk mulai bekerja

**Controller:** `PromosiController::acceptWork()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 599-637)

---

#### **2.4. Promosi: Buat Video BTS**

**Endpoint:** `POST /api/live-tv/roles/promosi/works/{id}/upload-bts-video`

**Request Body:**
```json
{
  "bts_video": "<file>" // MP4, AVI, MOV, max 100MB
}
```

**Flow:**
- ✅ Upload BTS video file
- ✅ File disimpan ke storage: `promosi/bts_videos/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)
- ✅ Include: filename, path, url, size, mime_type, uploaded_at

**Controller:** `PromosiController::uploadBTSVideo()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 643-711)

---

#### **2.5. Promosi: Buat Foto Talent**

**Endpoint:** `POST /api/live-tv/roles/promosi/works/{id}/upload-talent-photos`

**Request Body:**
```json
{
  "talent_photos": ["<file1>", "<file2>", ...] // JPG, JPEG, PNG, max 10MB per photo
}
```

**Flow:**
- ✅ Upload talent photos (multiple files)
- ✅ File disimpan ke storage: `promosi/talent_photos/`
- ✅ Alamat file tersimpan di `file_paths` (JSON array)
- ✅ Include: filename, path, url, size, mime_type, uploaded_at

**Controller:** `PromosiController::uploadTalentPhotos()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 717-783)

---

#### **2.6. Promosi: Upload File ke Storage**

**Status:** ✅ **AUTO-UPLOAD**

Setelah upload BTS video atau talent photos, file otomatis tersimpan ke storage dan alamat file tersimpan di sistem.

---

#### **2.7. Promosi: Input Alamat File ke System**

**Status:** ✅ **AUTO-SAVE**

Alamat file otomatis tersimpan di `file_paths` (JSON array) dengan format:
```json
[
  {
    "type": "bts_video",
    "filename": "video.mp4",
    "path": "promosi/bts_videos/1234567890_video.mp4",
    "url": "http://domain.com/storage/promosi/bts_videos/1234567890_video.mp4",
    "size": 5000000,
    "mime_type": "video/mp4",
    "uploaded_at": "2025-12-10 10:00:00"
  },
  {
    "type": "talent_photo",
    "filename": "photo1.jpg",
    "path": "promosi/talent_photos/1234567890_0_photo1.jpg",
    "url": "http://domain.com/storage/promosi/talent_photos/1234567890_0_photo1.jpg",
    "size": 2000000,
    "mime_type": "image/jpeg",
    "uploaded_at": "2025-12-10 10:00:00"
  }
]
```

---

#### **2.8. Promosi: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/promosi/works/{id}/complete-work`

**Flow:**
- ✅ Validasi: BTS video dan talent photos harus sudah di-upload
- ✅ Status: `shooting` → `published`
- ✅ Notifikasi ke Producer: `promosi_work_completed`

**Controller:** `PromosiController::completeWork()`

**File:** `app/Http/Controllers/Api/PromosiController.php` (line 789-863)

---

### **FLOW 3: Produksi - Input List Alat & Kebutuhan**

**Status:** ✅ **LENGKAP & AMAN**

#### **3.1. Produksi: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `produksi_work_created` - Produksi work task dibuat setelah Producer approve creative work

**Endpoint:** `GET /api/notifications`

---

#### **3.2. Produksi: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/accept-work`

**Flow:**
- ✅ Status: `pending` → `in_progress`
- ✅ Produksi siap untuk input equipment list dan needs

**Controller:** `ProduksiController::acceptWork()`

**File:** `app/Http/Controllers/Api/ProduksiController.php` (line 66-101)

---

#### **3.3. Produksi: Input List Alat (Ajukan ke Art & Set Properti)**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/request-equipment`

**Request Body:**
```json
{
  "equipment_list": [
    {
      "equipment_name": "Kamera DSLR",
      "quantity": 2,
      "return_date": "2025-12-20",
      "notes": "Untuk shooting episode"
    }
  ],
  "request_notes": "Equipment untuk shooting"
}
```

**Flow:**
- ✅ Validasi: Equipment tidak bisa di-request jika sedang dipakai
- ✅ Check equipment availability dari `EquipmentInventory`
- ✅ Check equipment in_use dari `ProductionEquipment`
- ✅ Jika equipment tidak tersedia atau sedang dipakai → return error dengan detail
- ✅ Jika tersedia → Create `ProductionEquipment` request
- ✅ Notifikasi ke Art & Set Properti: `equipment_request_created`

**Controller:** `ProduksiController::requestEquipment()`

**File:** `app/Http/Controllers/Api/ProduksiController.php` (line 107-241)

**Validasi Equipment:**
```php
// Check if equipment is available (not in_use or assigned)
$availableCount = EquipmentInventory::where('equipment_name', $equipmentName)
    ->whereIn('status', ['available'])
    ->count();

// Also check ProductionEquipment for in_use status
$inUseCount = ProductionEquipment::where('equipment_list', 'like', '%' . $equipmentName . '%')
    ->whereIn('status', ['approved', 'in_use'])
    ->count();

if ($availableCount < $quantity || $inUseCount > 0) {
    // Equipment tidak tersedia atau sedang dipakai
    return error;
}
```

---

#### **3.4. Produksi: Ajukan Kebutuhan**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/request-needs`

**Request Body:**
```json
{
  "needs_list": [
    {
      "item_name": "Konsumsi",
      "quantity": 50,
      "description": "Makan siang untuk crew"
    }
  ],
  "request_notes": "Kebutuhan untuk shooting"
}
```

**Flow:**
- ✅ Update work dengan `needs_list`
- ✅ Notifikasi ke Producer: `produksi_needs_requested`

**Controller:** `ProduksiController::requestNeeds()`

**File:** `app/Http/Controllers/Api/ProduksiController.php` (line 247-332)

---

#### **3.5. Produksi: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/complete-work`

**Flow:**
- ✅ Status: `in_progress` → `completed`
- ✅ Notifikasi ke Producer: `produksi_work_completed`

**Controller:** `ProduksiController::completeWork()`

**File:** `app/Http/Controllers/Api/ProduksiController.php` (line 338-400)

---

### **FLOW 4: Sound Engineer - Rekaman Vokal**

**Status:** ✅ **LENGKAP & AMAN**

#### **4.1. Sound Engineer: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `vocal_recording_task_created` - Recording task dibuat setelah Producer approve creative work
- ✅ `recording_task_created` - Recording task untuk arrangement approved

**Endpoint:** `GET /api/notifications`

---

#### **4.2. Sound Engineer: Terima Jadwal Rekaman Vokal**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/recordings/{id}/accept-schedule`

**Flow:**
- ✅ Ambil recording schedule dari Creative Work
- ✅ Update recording dengan `recording_schedule`
- ✅ Status: `draft` → `scheduled`

**Controller:** `SoundEngineerController::acceptRecordingSchedule()`

**File:** `app/Http/Controllers/Api/SoundEngineerController.php` (line 1294-1341)

---

#### **4.3. Sound Engineer: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/recordings/{id}/accept-work`

**Flow:**
- ✅ Status: `draft` / `pending` → `in_progress`
- ✅ Sound Engineer siap untuk input equipment list dan proceed dengan recording

**Controller:** `SoundEngineerController::acceptWork()`

**File:** `app/Http/Controllers/Api/SoundEngineerController.php` (line 1347-1391)

---

#### **4.4. Sound Engineer: Input List Alat (Ajukan ke Art & Set Properti)**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/recordings/{id}/request-equipment`

**Request Body:**
```json
{
  "equipment_list": [
    {
      "equipment_name": "Microphone",
      "quantity": 2,
      "return_date": "2025-12-20",
      "notes": "Untuk rekaman vokal"
    }
  ],
  "request_notes": "Equipment untuk rekaman vokal"
}
```

**Flow:**
- ✅ Validasi: Equipment tidak bisa di-request jika sedang dipakai
- ✅ Check equipment availability dari `EquipmentInventory`
- ✅ Check equipment in_use dari `ProductionEquipment`
- ✅ Jika equipment tidak tersedia atau sedang dipakai → return error dengan detail
- ✅ Jika tersedia → Create `ProductionEquipment` request
- ✅ Notifikasi ke Art & Set Properti: `equipment_request_created`

**Controller:** `SoundEngineerController::requestEquipment()`

**File:** `app/Http/Controllers/Api/SoundEngineerController.php` (line 1397-1522)

**Validasi Equipment:**
```php
// Check if equipment is available (not in_use or assigned)
$availableCount = EquipmentInventory::where('equipment_name', $equipmentName)
    ->whereIn('status', ['available'])
    ->count();

// Also check ProductionEquipment for in_use status
$inUseCount = ProductionEquipment::where('equipment_list', 'like', '%' . $equipmentName . '%')
    ->whereIn('status', ['approved', 'in_use'])
    ->count();

if ($availableCount < $quantity || $inUseCount > 0) {
    // Equipment tidak tersedia atau sedang dipakai
    return error;
}
```

---

#### **4.5. Sound Engineer: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/sound-engineer/recordings/{id}/complete-work`

**Flow:**
- ✅ Status: `in_progress` → `completed`
- ✅ Notifikasi ke Producer: `sound_engineer_recording_completed`

**Controller:** `SoundEngineerController::completeWork()`

**File:** `app/Http/Controllers/Api/SoundEngineerController.php` (line 1528-1600)

---

## 📊 STATUS FLOW DIAGRAM

```
Producer Approve Creative Work
↓
├─ General Affairs:
│  ├─ Terima notifikasi (budget_request_created)
│  ├─ Approve budget request
│  └─ Process payment → Berikan dana ke Producer
│
├─ Promosi:
│  ├─ Terima notifikasi (promotion_work_created)
│  ├─ Terima jadwal syuting
│  ├─ Terima pekerjaan
│  ├─ Upload BTS video (auto-save ke storage & system)
│  ├─ Upload foto talent (auto-save ke storage & system)
│  └─ Selesaikan pekerjaan
│
├─ Produksi:
│  ├─ Terima notifikasi (produksi_work_created)
│  ├─ Terima pekerjaan
│  ├─ Input list alat → Ajukan ke Art & Set Properti
│  │  └─ Validasi: Equipment tidak bisa di-request jika sedang dipakai
│  ├─ Ajukan kebutuhan
│  └─ Selesaikan pekerjaan
│
└─ Sound Engineer:
   ├─ Terima notifikasi (vocal_recording_task_created)
   ├─ Terima jadwal rekaman vokal
   ├─ Terima pekerjaan
   ├─ Input list alat → Ajukan ke Art & Set Properti
   │  └─ Validasi: Equipment tidak bisa di-request jika sedang dipakai
   └─ Selesaikan pekerjaan
```

---

## 🔒 KEAMANAN

### ✅ Role Validation
- ✅ General Affairs: `if ($user->role !== 'General Affairs')`
- ✅ Promosi: `if ($user->role !== 'Promosi')`
- ✅ Produksi: `if ($user->role !== 'Produksi')`
- ✅ Sound Engineer: `if (!$this->isSoundEngineer($user))`

### ✅ Authorization
- ✅ Promosi hanya bisa update work yang mereka buat sendiri
- ✅ Produksi hanya bisa update work yang mereka buat sendiri
- ✅ Sound Engineer hanya bisa update recording yang mereka buat sendiri
- ✅ General Affairs dapat melihat semua budget requests

### ✅ Input Validation
- ✅ Laravel Validator untuk semua input
- ✅ Required fields validation
- ✅ Type validation
- ✅ Size/limit validation
- ✅ File type validation (MP4, AVI, MOV, JPG, JPEG, PNG)

### ✅ Equipment Availability Validation
- ✅ Check equipment availability dari `EquipmentInventory`
- ✅ Check equipment in_use dari `ProductionEquipment`
- ✅ Equipment tidak bisa di-request jika sedang dipakai
- ✅ Return error dengan detail jika equipment tidak tersedia

### ✅ File Upload Security
- ✅ Mime type validation
- ✅ File size validation (max 100MB untuk video, 10MB untuk photo)
- ✅ Secure file storage
- ✅ Auto-save file path ke system

---

## 📋 DAFTAR ENDPOINT

### **General Affairs Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Get Budget Requests | `/api/live-tv/general-affairs/budget-requests/from-creative-work` | GET | ✅ |
| Approve Budget Request | `/api/live-tv/general-affairs/budget-requests/{id}/approve` | POST | ✅ |
| Process Payment | `/api/live-tv/general-affairs/budget-requests/{id}/process-payment` | POST | ✅ |

### **Promosi Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Accept Schedule | `/api/live-tv/roles/promosi/works/{id}/accept-schedule` | POST | ✅ |
| Accept Work | `/api/live-tv/roles/promosi/works/{id}/accept-work` | POST | ✅ |
| Upload BTS Video | `/api/live-tv/roles/promosi/works/{id}/upload-bts-video` | POST | ✅ |
| Upload Talent Photos | `/api/live-tv/roles/promosi/works/{id}/upload-talent-photos` | POST | ✅ |
| Complete Work | `/api/live-tv/roles/promosi/works/{id}/complete-work` | POST | ✅ |

### **Produksi Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Accept Work | `/api/live-tv/roles/produksi/works/{id}/accept-work` | POST | ✅ |
| Request Equipment | `/api/live-tv/roles/produksi/works/{id}/request-equipment` | POST | ✅ |
| Request Needs | `/api/live-tv/roles/produksi/works/{id}/request-needs` | POST | ✅ |
| Complete Work | `/api/live-tv/roles/produksi/works/{id}/complete-work` | POST | ✅ |

### **Sound Engineer Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Accept Schedule | `/api/live-tv/roles/sound-engineer/recordings/{id}/accept-schedule` | POST | ✅ |
| Accept Work | `/api/live-tv/roles/sound-engineer/recordings/{id}/accept-work` | POST | ✅ |
| Request Equipment | `/api/live-tv/roles/sound-engineer/recordings/{id}/request-equipment` | POST | ✅ |
| Complete Work | `/api/live-tv/roles/sound-engineer/recordings/{id}/complete-work` | POST | ✅ |

**Total Endpoint:** 17+ endpoint

---

## ✅ KESIMPULAN

### Status: **LENGKAP & AMAN**

Semua flow yang diminta sudah diimplementasikan:

1. ✅ **General Affairs** - Menerima permohonan dana, memproses, memberikan ke Producer
2. ✅ **Promosi** - Terima notifikasi, terima jadwal syuting, terima pekerjaan, buat video BTS, buat foto talent, upload file ke storage, input alamat file ke system, selesaikan pekerjaan
3. ✅ **Produksi** - Terima notifikasi, terima pekerjaan, input list alat (ajukan ke Art & Set Properti dengan validasi equipment tidak bisa di-request jika sedang dipakai), ajukan kebutuhan, selesaikan pekerjaan
4. ✅ **Sound Engineer** - Terima notifikasi, terima jadwal rekaman vokal, terima pekerjaan, input list alat (ajukan ke Art & Set Properti dengan validasi equipment tidak bisa di-request jika sedang dipakai), selesaikan pekerjaan

### Keamanan: **AMAN**
- ✅ Role validation di semua endpoint
- ✅ Authorization checks (ownership validation)
- ✅ Input validation & sanitization
- ✅ File upload security
- ✅ Equipment availability validation (tidak bisa di-request jika sedang dipakai)
- ✅ Auto-save file path ke system

### Total Endpoint: **17+ endpoint** untuk General Affairs, Promosi, Produksi, dan Sound Engineer

---

**Last Updated:** 12 Desember 2025  
**Status:** ✅ **VERIFIED & COMPLETE - READY FOR PRODUCTION**

