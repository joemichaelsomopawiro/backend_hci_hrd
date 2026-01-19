# Flow Setelah Producer Approve Creative Work

## ✅ STATUS: **LENGKAP - SEMUA ROLE SUDAH READY**

Setelah Producer approve Creative Work, sistem akan auto-create task untuk 4 role berikut:

---

## 🔄 WORKFLOW LENGKAP

```
Producer Approve Creative Work
    ↓
Auto-Create:
    ├─► BudgetRequest → General Affairs ✅
    ├─► PromotionWork → Promosi ✅
    ├─► ProduksiWork → Produksi ✅
    └─► SoundEngineerRecording → Sound Engineer ✅
```

---

## 1. ✅ GENERAL AFFAIRS

### **Flow:**
1. ✅ **Menerima Permohonan Dana** - Auto-create saat Producer approve
2. ✅ **Memproses** - Approve/Reject budget request
3. ✅ **Memberikan ke Producer** - Process payment & notify Producer

### **Endpoints:**

#### **1.1. Terima Permohonan Dana (Auto-Create)**
**Dipicu oleh:** Producer approve Creative Work  
**Model:** `BudgetRequest`  
**Notification Type:** `budget_request_created`

**Auto-create di:** `ProducerController::finalApproveCreativeWork()` (Line 4444-4479)

**Hasil:**
- ✅ BudgetRequest dibuat dengan `request_type = 'creative_work'`
- ✅ **General Affairs di-notify** ✅

---

#### **1.2. Get Budget Requests**
**Endpoint:** `GET /api/live-tv/general-affairs/budget-requests`

**Query Parameters:**
- `status` (optional): Filter by status
- `program_id` (optional): Filter by program
- `date_from` (optional): Filter by date
- `date_to` (optional): Filter by date

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "request_type": "creative_work",
        "title": "Permohonan Dana untuk Episode 001",
        "description": "Permohonan dana untuk creative work Episode 001. Budget: Rp 5.000.000",
        "requested_amount": 5000000,
        "status": "pending",
        "program": {...},
        "requested_by": {...}
      }
    ]
  }
}
```

**Tersedia di Postman:** ✅ Yes

---

#### **1.3. Get Budget Requests from Creative Work**
**Endpoint:** `GET /api/live-tv/general-affairs/budget-requests/from-creative-work`

**Fungsi:** Get khusus budget requests dari Creative Work

**Tersedia di Postman:** ✅ Yes

---

#### **1.4. Approve Budget Request**
**Endpoint:** `POST /api/live-tv/general-affairs/budget-requests/{id}/approve`

**Request Body:**
```json
{
  "approved_amount": 5000000,
  "approval_notes": "Budget disetujui",
  "payment_method": "transfer",
  "payment_schedule": "2026-02-01"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Budget request approved successfully",
  "data": {
    "id": 1,
    "status": "approved",
    "approved_amount": 5000000,
    "approved_by": 3,
    "approved_at": "2026-01-27T14:00:00.000000Z"
  }
}
```

**Fitur:**
- ✅ Status berubah menjadi `approved`
- ✅ **Producer di-notify** tentang approval ✅

**Tersedia di Postman:** ✅ Yes

---

#### **1.5. Reject Budget Request**
**Endpoint:** `POST /api/live-tv/general-affairs/budget-requests/{id}/reject`

**Request Body:**
```json
{
  "rejection_reason": "Budget terlalu besar"
}
```

**Fitur:**
- ✅ Status berubah menjadi `rejected`
- ✅ **Producer di-notify** tentang rejection ✅

**Tersedia di Postman:** ✅ Yes

---

#### **1.6. Process Payment (Memberikan ke Producer)** ⭐
**Endpoint:** `POST /api/live-tv/general-affairs/budget-requests/{id}/process-payment`

**Request Body:**
```json
{
  "payment_date": "2026-02-01",
  "payment_receipt": "receipt_file_path",
  "payment_notes": "Dana sudah ditransfer"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment processed successfully. Producer has been notified.",
  "data": {
    "budget_request": {...},
    "payment_status": "paid"
  }
}
```

**Fitur:**
- ✅ Status budget request berubah menjadi `paid`
- ✅ **Producer di-notify** bahwa dana telah diberikan ✅
- ✅ Notification type: `fund_released`

**Kode:** `GeneralAffairsController::processPayment()` (Line 209-261)

**Tersedia di Postman:** ✅ Yes

---

## 2. ✅ PROMOSI

### **Flow:**
1. ✅ **Terima Notifikasi** - Auto-create saat Producer approve
2. ✅ **Terima Jadwal Syuting** - Accept shooting schedule
3. ✅ **Terima Pekerjaan** - Accept work
4. ✅ **Buat Video BTS** - Create & upload BTS video
5. ✅ **Buat Foto Talent** - Create & upload talent photos
6. ✅ **Upload File ke Storage** - Upload files
7. ✅ **Input Alamat File ke Sistem** - Save file paths
8. ✅ **Selesaikan Pekerjaan** - Complete work

### **Status:**
- ✅ **Routes sudah ada** di `routes/live_tv_api.php`
- ✅ **Controller sudah diimplementasikan** (`PromosiController.php`)
- ✅ **Auto-create PromotionWork** sudah ada di ProducerController
- ✅ **Notification** sudah dikirim saat Producer approve

### **Endpoints:**

#### **2.1. Get Promotion Works**
**Endpoint:** `GET /api/live-tv/promosi/works`  
**Status:** ✅ **IMPLEMENTED**

**Query Parameters:**
- `status` (optional): Filter by status
- `episode_id` (optional): Filter by episode

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "episode_id": 1,
        "work_type": "bts_video",
        "title": "BTS Video & Talent Photos - Episode 001",
        "status": "planning",
        "shooting_date": "2026-02-01",
        "file_paths": []
      }
    ]
  }
}
```

**Kode:** `PromosiController::index()` (Line 17-71)

**Tersedia di Postman:** ✅ Yes

---

#### **2.2. Accept Shooting Schedule**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/accept-schedule`  
**Status:** ✅ **IMPLEMENTED**

**Request Body:**
```json
{
  "shooting_date": "2026-02-01",
  "shooting_time": "10:00",
  "location_data": {
    "location": "Studio A"
  },
  "shooting_notes": "Catatan shooting"
}
```

**Fitur:**
- ✅ Otomatis mengambil jadwal dari Creative Work jika ada
- ✅ Update `shooting_date`, `shooting_time`, dan `location_data`
- ✅ Mendukung custom shooting date/time jika berbeda dari Creative Work

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "shooting_date": "2026-02-01",
    "shooting_time": "10:00",
    "location_data": {
      "location": "Studio A"
    }
  }
}
```

**Kode:** `PromosiController::acceptSchedule()` (Line 177-232)

**Tersedia di Postman:** ✅ Yes

---

#### **2.3. Accept Work**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/accept-work`  
**Status:** ✅ **IMPLEMENTED**

**Fungsi:** Terima pekerjaan, status berubah menjadi `shooting`

**Response:**
```json
{
  "success": true,
  "message": "Work accepted successfully. You can now upload BTS video and talent photos.",
  "data": {
    "id": 1,
    "status": "shooting",
    "created_by": 5
  }
}
```

**Fitur:**
- ✅ Status berubah dari `planning` menjadi `shooting`
- ✅ Set `created_by` ke user yang accept

**Kode:** `PromosiController::acceptWork()` (Line 239-284)

**Tersedia di Postman:** ✅ Yes

---

#### **2.4. Upload BTS Video**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/upload-bts-video`  
**Status:** ✅ **IMPLEMENTED**

**Request:**
- `bts_video`: File (mp4, mov, avi, mkv, max 100MB)

**Response:**
```json
{
  "success": true,
  "message": "BTS video uploaded successfully. File path has been saved to system.",
  "data": {
    "id": 1,
    "file_paths": [
      {
        "type": "bts_video",
        "file_path": "promosi/bts_videos/1234_abc123_video.mp4",
        "file_name": "1234_abc123_video.mp4",
        "original_name": "bts_video.mp4",
        "file_size": 52428800,
        "mime_type": "video/mp4",
        "uploaded_at": "2026-01-27 14:00:00",
        "uploaded_by": 5
      }
    ]
  }
}
```

**Fitur:**
- ✅ Upload file video (mp4, mov, avi, mkv, max 100MB)
- ✅ File disimpan di `storage/promosi/bts_videos/`
- ✅ Path disimpan di `file_paths` array dengan type `bts_video`
- ✅ **Alamat file otomatis tersimpan ke sistem** ✅
- ✅ File name di-generate dengan safe naming (timestamp + random + original name)

**Kode:** `PromosiController::uploadBTSVideo()` (Line 291-381)

**Tersedia di Postman:** ✅ Yes

---

#### **2.5. Upload Talent Photos**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/upload-talent-photos`  
**Status:** ✅ **IMPLEMENTED**

**Request:**
- `talent_photos[]`: Array of files (jpg, jpeg, png, max 10MB per foto)

**Response:**
```json
{
  "success": true,
  "message": "Talent photos uploaded successfully. File paths have been saved to system.",
  "data": {
    "work": {...},
    "uploaded_photos": [
      {
        "type": "talent_photo",
        "file_path": "promosi/talent_photos/1234_abc123_photo1.jpg",
        "file_name": "1234_abc123_photo1.jpg",
        "original_name": "talent1.jpg",
        "file_size": 2097152,
        "mime_type": "image/jpeg",
        "url": "http://example.com/storage/promosi/talent_photos/1234_abc123_photo1.jpg",
        "uploaded_at": "2026-01-27 14:00:00",
        "uploaded_by": 5
      }
    ]
  }
}
```

**Fitur:**
- ✅ Upload multiple foto (jpg, jpeg, png, max 10MB per foto)
- ✅ File disimpan di `storage/promosi/talent_photos/`
- ✅ Path disimpan di `file_paths` array dengan type `talent_photo`
- ✅ **Alamat file otomatis tersimpan ke sistem** ✅
- ✅ URL otomatis di-generate untuk public access

**Kode:** `PromosiController::uploadTalentPhotos()` (Line 388-488)

**Tersedia di Postman:** ✅ Yes

---

#### **2.6. Complete Work**
**Endpoint:** `POST /api/live-tv/promosi/works/{id}/complete-work`  
**Status:** ✅ **IMPLEMENTED**

**Request Body:**
```json
{
  "completion_notes": "Pekerjaan selesai"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Work completed successfully. Producer has been notified.",
  "data": {
    "id": 1,
    "status": "editing"
  }
}
```

**Fitur:**
- ✅ Status berubah menjadi `editing` (siap untuk review/edit lebih lanjut)
- ✅ Validasi: BTS video dan talent photos harus sudah di-upload
- ✅ **Producer di-notify** ✅
- ✅ Error message jika BTS video atau talent photos belum di-upload

**Validasi:**
- ✅ BTS video harus sudah di-upload
- ✅ Talent photos harus sudah di-upload
- ✅ Hanya bisa complete jika status `shooting`

**Kode:** `PromosiController::completeWork()` (Line 518-641)

**Tersedia di Postman:** ✅ Yes

---

### **Auto-Create Logic (SUDAH ADA):**

**Kode:** `ProducerController::finalApproveCreativeWork()` (Line 4508-4534)

```php
// Auto-create PromosiWork task
$promosiUsers = \App\Models\User::where('role', 'Promotion')->get();
if ($promosiUsers->isNotEmpty()) {
    $promosiWork = \App\Models\PromotionWork::create([
        'episode_id' => $creativeWork->episode_id,
        'work_type' => 'bts_video',
        'title' => "BTS Video & Talent Photos - Episode {$creativeWork->episode->episode_number}",
        'description' => "Buat video BTS dan foto talent untuk Episode {$creativeWork->episode->episode_number}",
        'shooting_date' => $creativeWork->shooting_schedule,
        'status' => 'planning'
    ]);

    // Notify Promosi users
    foreach ($promosiUsers as $promosiUser) {
        Notification::create([
            'user_id' => $promosiUser->id,
            'type' => 'promosi_work_assigned',
            'title' => 'Tugas Promosi Baru',
            'message' => "Anda mendapat tugas untuk membuat video BTS dan foto talent...",
            // ...
        ]);
    }
}
```

**Status:** ✅ **Auto-create & notification SUDAH ADA**  
**Controller:** ✅ **SUDAH DIIMPLEMENTASIKAN**

---

## 3. ✅ PRODUKSI

### **Flow:**
1. ✅ **Terima Notifikasi** - Auto-create saat Producer approve
2. ✅ **Terima Pekerjaan** - Accept work
3. ✅ **Input List Alat** - Request equipment ke Art & Set Properti
4. ✅ **Ajukan Kebutuhan** - Request needs
5. ✅ **Selesai Pekerjaan** - Complete work

### **Endpoints:**

#### **3.1. Terima Notifikasi (Auto-Create)**
**Dipicu oleh:** Producer approve Creative Work  
**Model:** `ProduksiWork`  
**Notification Type:** `produksi_work_assigned`

**Auto-create di:** `ProducerController::finalApproveCreativeWork()` (Line 4536-4559)

**Hasil:**
- ✅ ProduksiWork dibuat
- ✅ **Produksi users di-notify** ✅

---

#### **3.2. Accept Work**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/accept-work`

**Fungsi:** Terima pekerjaan, status berubah menjadi `in_progress`

**Response:**
```json
{
  "success": true,
  "message": "Work accepted successfully. You can now input equipment list and needs.",
  "data": {
    "id": 1,
    "status": "in_progress"
  }
}
```

**Kode:** `ProduksiController::acceptWork()` (Line 70-131)

**Tersedia di Postman:** ✅ Yes

---

#### **3.3. Input List Alat (Ajukan ke Art & Set Properti)** ⭐
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/request-equipment`

**Request Body:**
```json
{
  "equipment_list": [
    {
      "equipment_name": "Kamera DSLR",
      "quantity": 2,
      "return_date": "2026-02-10",
      "notes": "Untuk shooting"
    }
  ],
  "request_notes": "Perlu untuk shooting episode"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Equipment requests created successfully. Art & Set Properti has been notified.",
  "data": {
    "work": {...},
    "equipment_requests": [...]
  }
}
```

**Fitur:**
- ✅ **Cek ketersediaan alat**: Validasi alat available di inventory
- ✅ **Cek status alat**: Validasi alat tidak sedang dipakai (status `approved` atau `in_use` di ProductionEquipment)
- ✅ **Jika alat sedang dipakai**: Request ditolak dengan error message
- ✅ Create `ProductionEquipment` request untuk setiap alat yang available
- ✅ **Art & Set Properti di-notify** ✅

**Validasi:**
- ✅ Alat tidak bisa di-request jika sedang dipakai
- ✅ Cek `EquipmentInventory` untuk status `available`
- ✅ Cek `ProductionEquipment` untuk status `approved` atau `in_use`

**Kode:** `ProduksiController::requestEquipment()` (Line 137-285)

**Tersedia di Postman:** ✅ Yes

---

#### **3.4. Ajukan Kebutuhan**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/request-needs`

**Request Body:**
```json
{
  "needs_list": [
    {
      "item_name": "Kabel Extension",
      "quantity": 5,
      "description": "Untuk peralatan shooting"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Needs request submitted successfully. Producer has been notified.",
  "data": {
    "work": {...}
  }
}
```

**Fitur:**
- ✅ Update `needs_list` di ProduksiWork
- ✅ **Producer di-notify** ✅

**Kode:** `ProduksiController::requestNeeds()` (Line 251-336)

**Tersedia di Postman:** ✅ Yes

---

#### **3.5. Complete Work**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/complete-work`

**Request Body:**
```json
{
  "completion_notes": "Pekerjaan selesai"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Work completed successfully. Producer has been notified.",
  "data": {
    "id": 1,
    "status": "completed"
  }
}
```

**Fitur:**
- ✅ Status berubah menjadi `completed`
- ✅ **Producer di-notify** ✅

**Kode:** `ProduksiController::completeWork()` (Line 342-370)

**Tersedia di Postman:** ✅ Yes

---

## 4. ✅ SOUND ENGINEER

### **Flow:**
1. ✅ **Terima Notifikasi** - Auto-create saat Producer approve (jika ada recording_schedule)
2. ✅ **Terima Jadwal Rekaman Vocal** - Accept recording schedule
3. ✅ **Terima Pekerjaan** - Accept work
4. ✅ **Input List Alat** - Request equipment ke Art & Set Properti
5. ✅ **Selesai Pekerjaan** - Complete work

### **Endpoints:**

#### **4.1. Terima Notifikasi (Auto-Create)**
**Dipicu oleh:** Producer approve Creative Work (jika ada `recording_schedule`)  
**Model:** `SoundEngineerRecording`  
**Notification Type:** `vocal_recording_task_created`

**Auto-create di:** `ProducerController::finalApproveCreativeWork()` (Line 4561-4604)

**Hasil:**
- ✅ SoundEngineerRecording dibuat dengan `music_arrangement_id = null` (vocal recording)
- ✅ **Sound Engineer di-notify** ✅

---

#### **4.2. Accept Recording Schedule**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/accept-schedule`

**Fungsi:** Terima jadwal rekaman vokal

**Response:**
```json
{
  "success": true,
  "message": "Recording schedule accepted successfully",
  "data": {
    "id": 1,
    "recording_schedule": "2026-02-01 10:00:00",
    "status": "scheduled"
  }
}
```

**Kode:** `SoundEngineerController::acceptRecordingSchedule()` (Line 1459-1506)

**Tersedia di Postman:** ✅ Yes

---

#### **4.3. Accept Work**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/accept-work`

**Fungsi:** Terima pekerjaan, status berubah menjadi `in_progress`

**Response:**
```json
{
  "success": true,
  "message": "Work accepted successfully. You can now input equipment list and proceed with recording.",
  "data": {
    "id": 1,
    "status": "in_progress"
  }
}
```

**Kode:** `SoundEngineerController::acceptWork()` (Line 1512-1573)

**Tersedia di Postman:** ✅ Yes

---

#### **4.4. Input List Alat (Ajukan ke Art & Set Properti)** ⭐
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/request-equipment`

**Request Body:**
```json
{
  "equipment_list": [
    {
      "equipment_name": "Microphone",
      "quantity": 2,
      "return_date": "2026-02-05",
      "notes": "Untuk rekaman vokal"
    }
  ],
  "request_notes": "Perlu untuk rekaman"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Equipment requests created successfully. Art & Set Properti has been notified.",
  "data": {
    "recording": {...},
    "equipment_requests": [...]
  }
}
```

**Fitur:**
- ✅ **Cek ketersediaan alat**: Validasi alat available di inventory
- ✅ **Cek status alat**: Validasi alat tidak sedang dipakai (status `approved` atau `in_use`)
- ✅ **Jika alat sedang dipakai**: Request ditolak dengan error message
- ✅ Create `ProductionEquipment` request untuk setiap alat yang available
- ✅ **Art & Set Properti di-notify** ✅

**Validasi:**
- ✅ Alat tidak bisa di-request jika sedang dipakai
- ✅ Cek `EquipmentInventory` untuk status `available`
- ✅ Cek `ProductionEquipment` untuk status `approved` atau `in_use`

**Kode:** `SoundEngineerController::requestEquipment()` (Line 1579-1704)

**Tersedia di Postman:** ✅ Yes

---

#### **4.5. Complete Work**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/complete-work`

**Request Body:**
```json
{
  "completion_notes": "Rekaman selesai"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Work completed successfully. Producer has been notified.",
  "data": {
    "id": 1,
    "status": "completed"
  }
}
```

**Fitur:**
- ✅ Status berubah menjadi `completed`
- ✅ **Producer di-notify** ✅

**Kode:** `SoundEngineerController::completeWork()` (Line 1710-1750)

**Tersedia di Postman:** ✅ Yes

---

## 📋 RINGKASAN STATUS

| Role | Auto-Create | Notifikasi | Endpoints | Status |
|------|-------------|------------|-----------|--------|
| **General Affairs** | ✅ | ✅ | ✅ Lengkap | ✅ **READY** |
| **Promosi** | ✅ | ✅ | ✅ Lengkap | ✅ **READY** |
| **Produksi** | ✅ | ✅ | ✅ Lengkap | ✅ **READY** |
| **Sound Engineer** | ✅ | ✅ | ✅ Lengkap | ✅ **READY** |

---

## ✅ SEMUA SUDAH DIIMPLEMENTASIKAN

### **PromosiController**

**File:** `app/Http/Controllers/Api/PromosiController.php`

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Method yang sudah diimplementasikan:**
1. ✅ `index()` - Get promotion works
2. ✅ `store()` - Create promotion work (optional, biasanya auto-create)
3. ✅ `acceptSchedule()` - Terima jadwal syuting
4. ✅ `acceptWork()` - Terima pekerjaan
5. ✅ `uploadBTSVideo()` - Upload BTS video
6. ✅ `uploadTalentPhotos()` - Upload foto talent
7. ✅ `uploadBTSContent()` - Legacy/alternative upload endpoint
8. ✅ `completeWork()` - Selesaikan pekerjaan
9. ✅ `statistics()` - Get statistics

**Model:** `PromotionWork` (sudah ada di migration)

**Fitur Tambahan:**
- ✅ File upload dengan safe naming
- ✅ Validasi file type & size
- ✅ Auto-save file paths ke sistem
- ✅ Notification ke Producer saat complete
- ✅ Audit logging
- ✅ Cache optimization

---

## ✅ YANG SUDAH BERFUNGSI

### **Auto-Create Logic (ProducerController):**
- ✅ BudgetRequest → General Affairs
- ✅ PromotionWork → Promosi ✅
- ✅ ProduksiWork → Produksi
- ✅ SoundEngineerRecording → Sound Engineer

### **Notification Flow:**
- ✅ Semua role di-notify saat Producer approve
- ✅ Notifikasi berisi detail task yang perlu dikerjakan

### **Equipment Request Validation:**
- ✅ Produksi: Cek alat tidak sedang dipakai
- ✅ Sound Engineer: Cek alat tidak sedang dipakai
- ✅ Error message jika alat sedang dipakai

---

## 🎯 KESIMPULAN

### **Yang Sudah Lengkap:**
1. ✅ **General Affairs** - 100% ready
2. ✅ **Produksi** - 100% ready
3. ✅ **Sound Engineer** - 100% ready

### **Yang Sudah Lengkap:**
4. ✅ **Promosi** - Controller sudah diimplementasikan lengkap

**Status:**
- ✅ Semua method sudah diimplementasikan
- ✅ File upload dengan safe naming
- ✅ Auto-save file paths ke sistem
- ✅ Validasi lengkap
- ✅ Notification ke Producer
- ✅ Audit logging

---

**Last Updated:** 2026-01-27
