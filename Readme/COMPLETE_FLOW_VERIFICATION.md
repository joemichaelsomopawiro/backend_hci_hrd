# ✅ Verifikasi Lengkap Flow Producer Approve/Reject Creative Work

## 📋 Daftar Isi
1. [Flow REJECT Creative Work](#flow-reject-creative-work)
2. [Flow APPROVE Creative Work](#flow-approve-creative-work)
3. [Flow General Affairs](#flow-general-affairs)
4. [Flow Promosi](#flow-promosi)
5. [Flow Produksi](#flow-produksi)
6. [Flow Sound Engineer](#flow-sound-engineer)

---

## 🔴 Flow REJECT Creative Work

### **Skenario**: Producer menolak Creative Work

**Flow yang diharapkan:**
1. ✅ Kembali ke Kreatif ATAU Producer dapat Mengedit Untuk Perbaikan
2. ✅ Kreatif perbaiki dan ajukan kembali ke produser

### ✅ **Implementasi Backend**

#### 1. Producer Reject Creative Work
**Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/final-approval`

**Request**:
```json
{
  "action": "reject",
  "notes": "Alasan penolakan"
}
```

**Kode**: `app/Http/Controllers/Api/ProducerController.php:4275`
- ✅ Memanggil `$creativeWork->reject($user->id, $request->notes)`
- ✅ Status berubah menjadi `rejected`
- ✅ Notifikasi dikirim ke Creative

#### 2. Producer Edit Creative Work (Untuk Perbaikan)
**Endpoint**: `PUT /api/live-tv/producer/creative-works/{id}/edit`

**Kode**: `app/Http/Controllers/Api/ProducerController.php:3788-3818`
- ✅ Jika status `rejected`, otomatis berubah menjadi `revised`
- ✅ Reset semua review fields (`script_approved`, `storyboard_approved`, `budget_approved`)
- ✅ Producer bisa edit: `script_content`, `storyboard_data`, `budget_data`, `shooting_schedule`, `recording_schedule`
- ✅ Notifikasi dikirim ke Creative bahwa Producer telah mengedit

#### 3. Creative Revise Creative Work
**Endpoint**: `PUT /api/live-tv/roles/creative/works/{id}/revise`

**Kode**: `app/Http/Controllers/Api/CreativeController.php:645-660`
- ✅ Hanya bisa revise jika status `rejected`
- ✅ Reset review fields
- ✅ Status berubah menjadi `revised`

#### 4. Creative Resubmit Creative Work
**Endpoint**: `POST /api/live-tv/roles/creative/works/{id}/resubmit`

**Kode**: `app/Http/Controllers/Api/CreativeController.php:666-704`
- ✅ Hanya bisa resubmit jika status `revised`
- ✅ Validasi: script, storyboard, dan budget harus lengkap
- ✅ Status berubah menjadi `submitted`
- ✅ Notifikasi dikirim ke Producer

**✅ Status**: **SEMUA FLOW REJECT SUDAH DIIMPLEMENTASIKAN**

---

## 🟢 Flow APPROVE Creative Work

### **Skenario**: Producer approve Creative Work (storyboard, budget, semua yang diajukan creative)

**Flow yang diharapkan:**
- ✅ General Affairs, Promosi, Produksi, Sound Engineer akan menerima kerjaan

### ✅ **Implementasi Backend**

**Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/final-approval`

**Request**:
```json
{
  "action": "approve",
  "notes": "Catatan approval"
}
```

**Kode**: `app/Http/Controllers/Api/ProducerController.php:4054-4265`

**Yang dilakukan saat approve:**
1. ✅ Auto-approve sub-reviews jika masih null (Quick Approve)
2. ✅ Validasi: script, storyboard, dan budget harus approved
3. ✅ Memanggil `$creativeWork->approve($user->id, $request->notes)`
4. ✅ **Auto-create BudgetRequest** ke General Affairs (line 4093-4110)
5. ✅ **Auto-create PromotionWork** (line 4160-4183)
6. ✅ **Auto-create ProduksiWork** (line 4188-4208)
7. ✅ **Auto-create SoundEngineerRecording** (line 4229-4250)
8. ✅ **Notifications dikirim** ke semua role
9. ✅ Workflow state berubah menjadi `production_planning`

**✅ Status**: **SEMUA AUTO-CREATE SUDAH DIIMPLEMENTASIKAN**

---

## 💰 Flow General Affairs

### **Flow yang diharapkan:**
1. ✅ Menerima Permohonan Dana
2. ✅ Memproses dan memberikan ke pada Producer

### ✅ **Implementasi Backend**

#### 1. Menerima Permohonan Dana
**Endpoint**: `GET /api/live-tv/roles/general-affairs/budget-requests/from-creative-work`

**Kode**: `app/Http/Controllers/Api/GeneralAffairsController.php:266-309`
- ✅ Menampilkan semua BudgetRequest dengan `request_type = 'creative_work'`
- ✅ Default filter: status `pending`
- ✅ Bisa filter by `status` dan `program_id`

**Endpoint**: `GET /api/live-tv/roles/general-affairs/budget-requests`
- ✅ Menampilkan semua budget requests

**Endpoint**: `GET /api/live-tv/roles/general-affairs/budget-requests/{id}`
- ✅ Detail budget request

#### 2. Approve Budget Request
**Endpoint**: `POST /api/live-tv/roles/general-affairs/budget-requests/{id}/approve`

**Kode**: `app/Http/Controllers/Api/GeneralAffairsController.php:approve()`
- ✅ Status berubah menjadi `approved`
- ✅ Notifikasi dikirim ke Producer

#### 3. Memproses dan Memberikan ke Producer
**Endpoint**: `POST /api/live-tv/roles/general-affairs/budget-requests/{id}/process-payment`

**Kode**: `app/Http/Controllers/Api/GeneralAffairsController.php:194-261`
- ✅ Status berubah menjadi `paid`
- ✅ Input `payment_receipt`, `payment_notes`, `payment_date`
- ✅ Notifikasi dikirim ke Producer bahwa dana telah diberikan
- ✅ Notification type: `fund_released`

**✅ Status**: **SEMUA FLOW GENERAL AFFAIRS SUDAH DIIMPLEMENTASIKAN**

---

## 📸 Flow Promosi

### **Flow yang diharapkan:**
1. ✅ Terima Notifikasi
2. ✅ Terima Jadwal Syuting
3. ✅ Terima Pekerjaan
4. ✅ Buat Video BTS
5. ✅ Buat Foto Talent
6. ✅ Upload file ke storage
7. ✅ Input alamat file ke sistem
8. ✅ Selesaikan Pekerjaan

### ✅ **Implementasi Backend**

#### 1. Terima Notifikasi
**Notification Type**: `promosi_work_assigned`
- ✅ Otomatis dikirim saat Producer approve Creative Work
- ✅ Berisi: `promotion_work_id`, `episode_id`, `shooting_date`

#### 2. Terima Jadwal Syuting
**Endpoint**: `POST /api/live-tv/roles/promosi/works/{id}/accept-schedule`

**Kode**: `app/Http/Controllers/Api/PromosiController.php:547-594`
- ✅ Mengambil `shooting_schedule` dari Creative Work
- ✅ Update `shooting_date` dan `shooting_time` di PromotionWork
- ✅ Update `location_data` dengan shooting location

#### 3. Terima Pekerjaan
**Endpoint**: `POST /api/live-tv/roles/promosi/works/{id}/accept-work`

**Kode**: `app/Http/Controllers/Api/PromosiController.php:600-638`
- ✅ Status berubah dari `planning` menjadi `shooting`
- ✅ Set `created_by` ke user yang accept

#### 4. Buat Video BTS & Upload
**Endpoint**: `POST /api/live-tv/roles/promosi/works/{id}/upload-bts-video`

**Kode**: `app/Http/Controllers/Api/PromosiController.php:644-712`
- ✅ Upload file video (mp4, avi, mov, max 100MB)
- ✅ File disimpan di `storage/promosi/bts_videos/`
- ✅ Path disimpan di `file_paths` array dengan type `bts_video`
- ✅ URL otomatis di-generate

#### 5. Buat Foto Talent & Upload
**Endpoint**: `POST /api/live-tv/roles/promosi/works/{id}/upload-talent-photos`

**Kode**: `app/Http/Controllers/Api/PromosiController.php:718-784`
- ✅ Upload multiple foto (jpg, jpeg, png, max 10MB per foto)
- ✅ File disimpan di `storage/promosi/talent_photos/`
- ✅ Path disimpan di `file_paths` array dengan type `talent_photo`
- ✅ URL otomatis di-generate

#### 6. Input Alamat File ke Sistem
- ✅ **Sudah otomatis**: File path dan URL sudah tersimpan di `file_paths` array saat upload
- ✅ Bisa diakses via `GET /api/live-tv/roles/promosi/works/{id}`

#### 7. Selesaikan Pekerjaan
**Endpoint**: `POST /api/live-tv/roles/promosi/works/{id}/complete-work`

**Kode**: `app/Http/Controllers/Api/PromosiController.php:790-850`
- ✅ Status berubah menjadi `completed`
- ✅ Validasi: BTS video dan talent photos harus sudah di-upload
- ✅ Notifikasi dikirim ke Producer

**✅ Status**: **SEMUA FLOW PROMOSI SUDAH DIIMPLEMENTASIKAN**

---

## 🎬 Flow Produksi

### **Flow yang diharapkan:**
1. ✅ Terima Notifikasi
2. ✅ Terima Pekerjaan
3. ✅ Input list alat (Ajukan ke Art & Set Properti) * Alat tidak bisa di request jika sedang di pake
4. ✅ Ajukan kebutuhan
5. ✅ Selesai Pekerjaan

### ✅ **Implementasi Backend**

#### 1. Terima Notifikasi
**Notification Type**: `produksi_work_assigned`
- ✅ Otomatis dikirim saat Producer approve Creative Work
- ✅ Berisi: `produksi_work_id`, `episode_id`, `creative_work_id`

#### 2. Terima Pekerjaan
**Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/accept-work`

**Kode**: `app/Http/Controllers/Api/ProduksiController.php:70-105`
- ✅ Status berubah dari `pending` menjadi `in_progress`
- ✅ Memanggil `$work->acceptWork($user->id)`

#### 3. Input List Alat (Ajukan ke Art & Set Properti)
**Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/request-equipment`

**Kode**: `app/Http/Controllers/Api/ProduksiController.php:111-245`
- ✅ Input `equipment_list` (array of equipment dengan name, quantity, return_date, notes)
- ✅ **Cek ketersediaan alat**: 
  - Cek `EquipmentInventory` untuk status `available`
  - Cek `ProductionEquipment` untuk status `approved` atau `in_use`
  - **Jika alat sedang dipakai atau tidak tersedia, request ditolak** (line 174-182)
- ✅ Create `ProductionEquipment` request untuk setiap alat yang available
- ✅ Update `equipment_list` dan `equipment_requests` di ProduksiWork
- ✅ Notifikasi dikirim ke Art & Set Properti

#### 4. Ajukan Kebutuhan
**Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/request-needs`

**Kode**: `app/Http/Controllers/Api/ProduksiController.php:251-336`
- ✅ Input `needs_list` (array of needs dengan item_name, quantity, description)
- ✅ Update `needs_list` dan `needs_requests` di ProduksiWork
- ✅ Notifikasi dikirim ke Producer

#### 5. Selesai Pekerjaan
**Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/complete-work`

**Kode**: `app/Http/Controllers/Api/ProduksiController.php:342-370`
- ✅ Status berubah menjadi `completed`
- ✅ Memanggil `$work->completeWork($user->id, $request->notes)`
- ✅ Notifikasi dikirim ke Producer

**✅ Status**: **SEMUA FLOW PRODUKSI SUDAH DIIMPLEMENTASIKAN**

---

## 🎤 Flow Sound Engineer

### **Flow yang diharapkan:**
1. ✅ Terima Notifikasi
2. ✅ Terima Jadwal Rekaman Vocal
3. ✅ Terima pekerjaan
4. ✅ Input list Alat (ajukan ke art & set properti)
5. ✅ Selesai Pekerjaan

### ✅ **Implementasi Backend**

#### 1. Terima Notifikasi
**Notification Type**: `vocal_recording_task_created`
- ✅ Otomatis dikirim saat Producer approve Creative Work (jika ada `recording_schedule`)
- ✅ Berisi: `recording_id`, `episode_id`, `recording_date`

#### 2. Terima Jadwal Rekaman Vocal
**Endpoint**: `POST /api/live-tv/sound-engineer/recordings/{id}/accept-schedule`

**Kode**: `app/Http/Controllers/Api/SoundEngineerController.php:1459-1506`
- ✅ Mengambil `recording_schedule` dari Creative Work
- ✅ Update `recording_schedule` di SoundEngineerRecording
- ✅ Status berubah menjadi `scheduled`

#### 3. Terima Pekerjaan
**Endpoint**: `POST /api/live-tv/sound-engineer/recordings/{id}/accept-work`

**Kode**: `app/Http/Controllers/Api/SoundEngineerController.php:1512-1556`
- ✅ Status berubah dari `draft` atau `pending` menjadi `in_progress`
- ✅ Validasi: hanya bisa accept jika status `draft` atau `pending`

#### 4. Input List Alat (Ajukan ke Art & Set Properti)
**Endpoint**: `POST /api/live-tv/roles/sound-engineer/recordings/{id}/request-equipment`

**Kode**: `app/Http/Controllers/Api/SoundEngineerController.php:1562-1687`
- ✅ Input `equipment_list` (array of equipment dengan name, quantity, return_date, notes)
- ✅ **Cek ketersediaan alat**: 
  - Cek `EquipmentInventory` untuk status `available`
  - Cek `ProductionEquipment` untuk status `approved` atau `in_use`
  - **Jika alat sedang dipakai atau tidak tersedia, request ditolak** (line 1618-1627)
- ✅ Create `ProductionEquipment` request untuk setiap alat yang available
- ✅ Update `equipment_used` di SoundEngineerRecording
- ✅ Notifikasi dikirim ke Art & Set Properti

#### 5. Selesai Pekerjaan
**Endpoint**: `POST /api/live-tv/sound-engineer/recordings/{id}/complete-work`

**Kode**: `app/Http/Controllers/Api/SoundEngineerController.php:1693-1780`
- ✅ Status berubah menjadi `completed`
- ✅ Validasi: equipment harus sudah di-request
- ✅ Notifikasi dikirim ke Producer

**✅ Status**: **SEMUA FLOW SOUND ENGINEER SUDAH DIIMPLEMENTASIKAN**

---

## 📊 Summary Verifikasi

| Flow | Status | Keterangan |
|------|--------|------------|
| **REJECT Flow** | ✅ | Producer reject → Producer edit OR Creative revise → Creative resubmit |
| **APPROVE Flow** | ✅ | Auto-create BudgetRequest, PromotionWork, ProduksiWork, SoundEngineerRecording |
| **General Affairs** | ✅ | Terima permohonan → Approve → Process payment → Berikan ke Producer |
| **Promosi** | ✅ | Terima notifikasi → Terima jadwal → Terima pekerjaan → Upload BTS → Upload foto → Complete |
| **Produksi** | ✅ | Terima notifikasi → Terima pekerjaan → Request equipment (cek availability) → Request needs → Complete |
| **Sound Engineer** | ✅ | Terima notifikasi → Terima jadwal → Terima pekerjaan → Request equipment (cek availability) → Complete |

---

## 🔍 Catatan Penting

### 1. Equipment Availability Check
- ✅ **Produksi**: Cek availability sebelum create request (line 164-182)
- ✅ **Sound Engineer**: Cek availability sebelum create request (line 1608-1627)
- ✅ Jika alat sedang dipakai (`in_use`), request akan ditolak dengan error message

### 2. Auto-Create Records
- ✅ Semua records (BudgetRequest, PromotionWork, ProduksiWork, SoundEngineerRecording) **otomatis dibuat** saat Producer approve
- ✅ Notifications **otomatis dikirim** ke semua role yang relevan

### 3. File Upload
- ✅ **Promosi**: BTS video dan talent photos disimpan di `storage/promosi/`
- ✅ File paths otomatis tersimpan di `file_paths` array
- ✅ URL otomatis di-generate

### 4. Route Prefix
- ✅ Semua route menggunakan prefix `/api/live-tv/roles/{role}/`
- ✅ Route General Affairs: `/api/live-tv/roles/general-affairs/`
- ✅ Route Promosi: `/api/live-tv/roles/promosi/`
- ✅ Route Produksi: `/api/live-tv/roles/produksi/`
- ✅ Route Sound Engineer: `/api/live-tv/sound-engineer/` (tanpa `roles/`)

---

## ✅ Kesimpulan

**SEMUA FLOW YANG DISEBUTKAN SUDAH DIIMPLEMENTASIKAN DENGAN BENAR DI BACKEND**

Semua endpoint, validasi, dan notifications sudah tersedia dan berfungsi sesuai dengan flow yang dijelaskan.















