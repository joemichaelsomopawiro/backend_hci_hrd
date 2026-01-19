# Verifikasi Flow Produksi, Sound Engineer, dan Art & Set Properti

## ✅ STATUS: **SEBAGIAN LENGKAP, BEBERAPA ENDPOINT BELUM DITEMUKAN**

Dokumentasi ini memverifikasi workflow yang dijelaskan user terhadap implementasi backend yang ada.

---

## 🔄 WORKFLOW YANG DIHARAPKAN

### **1. PRODUKSI → ART & SET PROPERTI → PRODUKSI**

```
Produksi:
1. Terima Notifikasi ✅
2. Terima Pekerjaan ✅
3. Input list alat (Ajukan ke Art & Set Properti) ✅
4. Ajukan kebutuhan ✅
5. Selesai Pekerjaan ❌ (masih belum sesuai - seharusnya hanya selesai request, belum selesai work)

Art & Set Properti:
1. Terima Notifikasi ✅
2. Terima Pekerjaan ❓ (tidak ada endpoint eksplisit)
3. ACC Alat ✅
4. Selesai Pekerjaan ❓ (tidak ada endpoint eksplisit)

Produksi (Lanjutan setelah Art & Set Properti ACC):
1. Terima Notifikasi ✅ (setelah Art & Set Properti approve)
2. Terima Pekerjaan ❓ (tidak ada endpoint khusus)
3. Proses Pekerjaan ❓ (tidak ada endpoint khusus)
4. Input form catatan Syuting (Run sheet) ✅
5. Setelah syuting upload hasil syuting ke storage ✅
6. Input link file di sistem alamat storage ✅
7. Kembalikan alat ke Art & set properti ✅
8. Selesai Pekerjaan ✅
```

---

### **2. SOUND ENGINEER → ART & SET PROPERTI → SOUND ENGINEER**

```
Sound Engineer:
1. Terima Notifikasi ✅
2. Terima Jadwal Rekaman Vocal ✅
3. Terima pekerjaan ✅
4. Input list Alat (ajukan ke art & set properti) ✅
5. Selesai Pekerjaan ❌ (masih belum sesuai - seharusnya hanya selesai request)

Art & Set Properti:
1. Terima Notifikasi ✅
2. Terima Pekerjaan ❓ (tidak ada endpoint eksplisit)
3. ACC Alat ✅
4. Selesai Pekerjaan ❓ (tidak ada endpoint eksplisit)

Sound Engineer (Lanjutan setelah Art & Set Properti ACC):
1. Terima Notifikasi ✅ (setelah Art & Set Properti approve)
2. Terima pekerjaan ❓ (tidak ada endpoint khusus)
3. Lakukan Recording ✅ (startRecording)
4. Kembalikan alat Ke Art properti ✅
5. Kirim File rekaman ke storage input link sistem ✅ (uploadAudioFiles)
6. Selesai Pekerjaan ✅ (completeRecording)
```

---

## 📋 VERIFIKASI DETAIL

### **1. PRODUKSI → ART & SET PROPERTI**

#### **✅ Produksi - Input List Alat:**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/request-equipment`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `ProduksiController::requestEquipment()` (Line 137-285)

**Fitur:**
- ✅ Input equipment_list
- ✅ Cek ketersediaan alat (tidak bisa request jika sedang dipakai)
- ✅ Create ProductionEquipment request
- ✅ **Notifikasi ke Art & Set Properti** ✅

**Notification Type:** `equipment_request_created`

---

#### **✅ Art & Set Properti - Terima Notifikasi:**
**Dipicu oleh:** Produksi/Sound Engineer request equipment  
**Status:** ✅ **SUDAH ADA**

**Notification dikirim di:**
- `ProduksiController::requestEquipment()` (Line 249-264)
- `SoundEngineerController::requestEquipment()` (Line 1673-1687)

---

#### **❓ Art & Set Properti - Terima Pekerjaan:**
**Endpoint yang dicari:** `POST /api/live-tv/art-set-properti/requests/{id}/accept-work`  
**Status:** ❌ **TIDAK DITEMUKAN**

**Yang ada:**
- `GET /api/live-tv/art-set-properti/requests` - Get equipment requests
- `POST /api/live-tv/art-set-properti/requests/{id}/approve` - Approve request

**Kesimpulan:**
- ❌ Tidak ada endpoint eksplisit untuk "accept work"
- ✅ Langsung approve request equipment (bisa dianggap sebagai "accept work")

---

#### **✅ Art & Set Properti - ACC Alat:**
**Endpoint:** `POST /api/live-tv/art-set-properti/requests/{id}/approve`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `ArtSetPropertiController::approveRequest()` (Line 113-194)

**Fitur:**
- ✅ Approve equipment request
- ✅ Create EquipmentInventory record (status: `assigned`)
- ✅ Update ProductionEquipment status menjadi `approved`
- ✅ **Notifikasi ke Produksi/Sound Engineer** ✅

**Notification Type:** `equipment_approved`  
**Notifikasi dikirim di:** `ArtSetPropertiController::notifyEquipmentApproved()` (Line 389-401)

---

#### **❓ Art & Set Properti - Selesai Pekerjaan:**
**Endpoint yang dicari:** `POST /api/live-tv/art-set-properti/requests/{id}/complete-work`  
**Status:** ❌ **TIDAK DITEMUKAN**

**Yang ada:**
- Setelah approve, tidak ada endpoint untuk "complete work" secara eksplisit
- Approve equipment request sudah dianggap selesai

**Kesimpulan:**
- ❌ Tidak ada endpoint eksplisit untuk "complete work"
- ✅ Setelah approve, pekerjaan dianggap selesai (equipment sudah diberikan)

---

#### **✅ Produksi - Terima Notifikasi (Setelah Art & Set Properti ACC):**
**Dipicu oleh:** Art & Set Properti approve equipment request  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `equipment_approved`  
**Notifikasi dikirim di:** `ArtSetPropertiController::notifyEquipmentApproved()` (Line 389-401)

---

#### **❓ Produksi - Terima Pekerjaan (Setelah Art & Set Properti ACC):**
**Endpoint yang dicari:** `POST /api/live-tv/roles/produksi/works/{id}/accept-equipment-approved`  
**Status:** ❌ **TIDAK DITEMUKAN**

**Kesimpulan:**
- Tidak ada endpoint khusus untuk "accept pekerjaan" setelah equipment approved
- Produksi bisa langsung lanjut proses pekerjaan setelah menerima notifikasi equipment approved

---

#### **❓ Produksi - Proses Pekerjaan:**
**Endpoint yang dicari:** `POST /api/live-tv/roles/produksi/works/{id}/process-work`  
**Status:** ❌ **TIDAK DITEMUKAN**

**Kesimpulan:**
- Tidak ada endpoint eksplisit untuk "proses pekerjaan"
- Setelah equipment approved, Produksi langsung lanjut ke input run sheet

---

#### **✅ Produksi - Input Form Catatan Syuting (Run Sheet):**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `ProduksiController::createRunSheet()` (Line 464-557)

**Fitur:**
- ✅ Input run sheet data
- ✅ Create ShootingRunSheet record
- ✅ Link ke ProduksiWork

---

#### **✅ Produksi - Upload Hasil Syuting ke Storage:**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `ProduksiController::uploadShootingResults()` (Line 562-724)

**Fitur:**
- ✅ Upload multiple files (mp4, avi, mov, mkv, max 1GB)
- ✅ File disimpan di `storage/produksi/shooting_results/{work_id}/`
- ✅ Create MediaFile record
- ✅ Update ProduksiWork dengan shooting_files
- ✅ **Auto-generate URL** ✅
- ✅ **Input link file di sistem sudah otomatis** ✅

---

#### **✅ Produksi - Input Link File di Sistem:**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/input-file-links`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `ProduksiController::inputFileLinks()` (Line 726-805)

**Fitur:**
- ✅ Input file_links (array)
- ✅ Update shooting_files dan shooting_file_links
- ✅ Support manual input link jika file diupload di tempat lain

**Catatan:** Upload shooting results sudah otomatis input link, endpoint ini untuk manual input link.

---

#### **✅ Produksi - Kembalikan Alat ke Art & Set Properti:**
**Endpoint:** `POST /api/live-tv/roles/produksi/equipment/{id}/return`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `ProductionEquipmentController::returnEquipment()` (Line 198-263)

**Fitur:**
- ✅ Return equipment dengan kondisi (good, damaged, lost)
- ✅ Update ProductionEquipment status menjadi `returned`
- ✅ **Notifikasi ke Art & Set Properti** ✅

**Notification Type:** `equipment_returned`

---

#### **✅ Produksi - Selesai Pekerjaan:**
**Endpoint:** `POST /api/live-tv/roles/produksi/works/{id}/complete-work`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `ProduksiController::completeWork()` (Line 388-423)

**Fitur:**
- ✅ Status berubah menjadi `completed`
- ✅ Notifikasi ke Producer

---

### **2. SOUND ENGINEER → ART & SET PROPERTI → SOUND ENGINEER**

#### **✅ Sound Engineer - Input List Alat:**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/request-equipment`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `SoundEngineerController::requestEquipment()` (Line 1579-1704)

**Fitur:**
- ✅ Input equipment_list
- ✅ Cek ketersediaan alat (tidak bisa request jika sedang dipakai)
- ✅ Create ProductionEquipment request
- ✅ **Notifikasi ke Art & Set Properti** ✅

---

#### **✅ Sound Engineer - Terima Notifikasi (Setelah Art & Set Properti ACC):**
**Dipicu oleh:** Art & Set Properti approve equipment request  
**Status:** ✅ **SUDAH ADA**

**Notification Type:** `equipment_approved`

---

#### **❓ Sound Engineer - Terima Pekerjaan (Setelah Art & Set Properti ACC):**
**Endpoint yang dicari:** `POST /api/live-tv/sound-engineer/recordings/{id}/accept-equipment-approved`  
**Status:** ❌ **TIDAK DITEMUKAN**

**Kesimpulan:**
- Tidak ada endpoint khusus untuk "accept pekerjaan" setelah equipment approved
- Sound Engineer bisa langsung lanjut recording setelah menerima notifikasi equipment approved

---

#### **✅ Sound Engineer - Lakukan Recording:**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/start-recording`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `SoundEngineerController::startRecording()` (Line 328-382)

**Fitur:**
- ✅ Status berubah dari `draft` menjadi `recording`
- ✅ Validasi hanya bisa start jika status `draft`

---

#### **✅ Sound Engineer - Kembalikan Alat ke Art & Set Properti:**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/return-equipment`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `SoundEngineerController::returnEquipment()` (Line 1784-1975)

**Fitur:**
- ✅ Return multiple equipment dalam satu request
- ✅ Update ProductionEquipment status menjadi `returned`
- ✅ Update EquipmentInventory status menjadi `returned`
- ✅ Validasi equipment belongs to recording's episode
- ✅ Validasi equipment requested by current user
- ✅ Validasi equipment status (harus approved atau in_use)
- ✅ Support return condition per equipment (good, damaged, lost)
- ✅ **Notifikasi ke Art & Set Properti** ✅

**Request Body:**
```json
{
  "equipment_request_ids": [1, 2],
  "return_condition": [
    {
      "equipment_request_id": 1,
      "condition": "good",
      "notes": "Kondisi baik"
    },
    {
      "equipment_request_id": 2,
      "condition": "damaged",
      "notes": "Ada kerusakan"
    }
  ],
  "return_notes": "Semua alat sudah dikembalikan"
}
```

**Notification Type:** `equipment_returned`

---

#### **✅ Sound Engineer - Kirim File Rekaman ke Storage:**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/upload-audio`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `SoundEngineerRecordingController::uploadAudioFiles()` (Line 109-175)

**Alternatif di:** `SoundEngineerController::update()` dengan file upload (Line 1353-1381)

**Fitur:**
- ✅ Upload audio files (mp3, wav, ogg, aac, max 100MB)
- ✅ File disimpan di `storage/audio_recordings/`
- ✅ **Auto-generate URL** ✅
- ✅ **Input link file di sistem sudah otomatis** ✅ (melalui `uploadAudioFiles()` method di model)

---

#### **✅ Sound Engineer - Selesai Pekerjaan:**
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/complete-recording`  
**Status:** ✅ **SUDAH ADA**

**Kode:** `SoundEngineerController::completeRecording()` (Line 387-488)

**Fitur:**
- ✅ Status berubah menjadi `completed`
- ✅ Auto-create SoundEngineerEditing task
- ✅ Notifikasi ke Producer

---

## 📋 RINGKASAN STATUS

| Flow | Step | Status | Endpoint/Notes |
|------|------|--------|----------------|
| **Produksi** | Terima Notifikasi | ✅ | Auto-notify saat Producer approve |
| | Terima Pekerjaan | ✅ | `POST /api/live-tv/roles/produksi/works/{id}/accept-work` |
| | Input list alat | ✅ | `POST /api/live-tv/roles/produksi/works/{id}/request-equipment` |
| | Ajukan kebutuhan | ✅ | `POST /api/live-tv/roles/produksi/works/{id}/request-needs` |
| | Selesai Pekerjaan (request) | ⚠️ | Masih menggunakan `complete-work`, seharusnya hanya selesai request |
| **Art & Set Properti** | Terima Notifikasi | ✅ | Auto-notify saat Produksi/Sound Engineer request |
| | Terima Pekerjaan | ❓ | Tidak ada endpoint eksplisit, langsung approve |
| | ACC Alat | ✅ | `POST /api/live-tv/art-set-properti/requests/{id}/approve` |
| | Selesai Pekerjaan | ❓ | Tidak ada endpoint eksplisit, approve = selesai |
| **Produksi (Lanjutan)** | Terima Notifikasi (setelah ACC) | ✅ | Notification: `equipment_approved` |
| | Terima Pekerjaan | ❓ | Tidak ada endpoint khusus, langsung lanjut proses |
| | Proses Pekerjaan | ❓ | Tidak ada endpoint eksplisit |
| | Input Run Sheet | ✅ | `POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet` |
| | Upload hasil syuting | ✅ | `POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results` |
| | Input link file | ✅ | `POST /api/live-tv/roles/produksi/works/{id}/input-file-links` |
| | Kembalikan alat | ✅ | `POST /api/live-tv/roles/produksi/equipment/{id}/return` |
| | Selesai Pekerjaan | ✅ | `POST /api/live-tv/roles/produksi/works/{id}/complete-work` |
| **Sound Engineer** | Terima Notifikasi | ✅ | Auto-notify saat Producer approve |
| | Terima Jadwal Rekaman | ✅ | `POST /api/live-tv/sound-engineer/recordings/{id}/accept-schedule` |
| | Terima pekerjaan | ✅ | `POST /api/live-tv/sound-engineer/recordings/{id}/accept-work` |
| | Input list alat | ✅ | `POST /api/live-tv/sound-engineer/recordings/{id}/request-equipment` |
| | Selesai Pekerjaan (request) | ⚠️ | Masih menggunakan `complete-work`, seharusnya hanya selesai request |
| **Art & Set Properti** | Terima Notifikasi | ✅ | Auto-notify saat Sound Engineer request |
| | Terima Pekerjaan | ❓ | Tidak ada endpoint eksplisit, langsung approve |
| | ACC Alat | ✅ | `POST /api/live-tv/art-set-properti/requests/{id}/approve` |
| | Selesai Pekerjaan | ❓ | Tidak ada endpoint eksplisit, approve = selesai |
| **Sound Engineer (Lanjutan)** | Terima Notifikasi (setelah ACC) | ✅ | Notification: `equipment_approved` |
| | Terima pekerjaan | ❓ | Tidak ada endpoint khusus |
| | Lakukan Recording | ✅ | `POST /api/live-tv/sound-engineer/recordings/{id}/start-recording` |
| | **Kembalikan alat** | ✅ | `POST /api/live-tv/sound-engineer/recordings/{id}/return-equipment` |
| | Upload file rekaman | ✅ | `POST /api/live-tv/sound-engineer/recordings/{id}/upload-audio` |
| | Input link sistem | ✅ | Auto-save saat upload |
| | Selesai Pekerjaan | ✅ | `POST /api/live-tv/sound-engineer/recordings/{id}/complete-recording` |

---

## ✅ YANG SUDAH DITAMBAHKAN

### **1. Sound Engineer - Return Equipment** ✅
**Endpoint:** `POST /api/live-tv/sound-engineer/recordings/{id}/return-equipment`

**Fungsi:**
- Return equipment setelah selesai recording
- Update ProductionEquipment status menjadi `returned`
- Update EquipmentInventory status menjadi `returned`
- Notifikasi ke Art & Set Properti

**Request Body:**
```json
{
  "equipment_request_ids": [1, 2, 3],
  "return_condition": [
    {
      "equipment_request_id": 1,
      "condition": "good",
      "notes": "Alat dalam kondisi baik"
    },
    {
      "equipment_request_id": 2,
      "condition": "damaged",
      "notes": "Ada sedikit kerusakan di bagian kabel"
    }
  ],
  "return_notes": "Semua alat sudah dikembalikan"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "recording": {...},
    "returned_equipment": [...]
  },
  "message": "Equipment returned successfully. Art & Set Properti has been notified."
}
```

**Kode:** `SoundEngineerController::returnEquipment()` (Line 1784-1975)

**Status:** ✅ **SUDAH DITAMBAHKAN**

---

### **2. Art & Set Properti - Accept Returned Equipment** ✅
**Endpoint:** `POST /api/live-tv/art-set-properti/equipment/{id}/accept-returned`

**Status:** ✅ **SUDAH DITAMBAHKAN**

**Kode:** `ArtSetPropertiController::acceptReturnedEquipment()` (Line 340-450)

**Fitur:**
- ✅ Art & Set Properti verify & konfirmasi equipment yang dikembalikan
- ✅ Update return_notes dengan verification notes
- ✅ Update EquipmentInventory status menjadi `available` jika kondisi baik (optional)
- ✅ **Notifikasi ke Production/Sound Engineer** ✅

**Notification Type:** `equipment_return_confirmed`

**Request Body:**
```json
{
  "verification_notes": "Alat diterima dalam kondisi baik",
  "set_available": true
}
```

---

### **3. Produksi - Accept Work & Process Work (Setelah Equipment Approved)**
**Endpoint yang perlu ditambahkan:**
- `POST /api/live-tv/roles/produksi/works/{id}/accept-equipment-approved` (optional)
- `POST /api/live-tv/roles/produksi/works/{id}/process-work` (optional)

**Catatan:**
- Ini optional, karena setelah equipment approved, Produksi bisa langsung lanjut ke run sheet
- Jika user ingin tracking lebih detail, bisa ditambahkan endpoint ini

**Status:** ❓ **OPTIONAL**

---

### **4. Sound Engineer - Accept Work (Setelah Equipment Approved)**
**Endpoint yang perlu ditambahkan:**
- `POST /api/live-tv/sound-engineer/recordings/{id}/accept-equipment-approved` (optional)

**Catatan:**
- Ini optional, karena setelah equipment approved, Sound Engineer bisa langsung lanjut recording
- Jika user ingin tracking lebih detail, bisa ditambahkan endpoint ini

**Status:** ❓ **OPTIONAL**

---

## ⚠️ YANG PERLU DIPERBAIKI

### **1. Produksi - Selesai Pekerjaan (Saat Request Equipment)**
**Masalah:** Endpoint `complete-work` digunakan untuk menyelesaikan work secara keseluruhan, bukan hanya selesai request equipment.

**Solusi:**
- Setelah request equipment, Produksi belum selesai work-nya
- Produksi baru selesai work setelah upload shooting results, return equipment, dan complete work

**Status:** ⚠️ **SUDAH BENAR** - Tidak perlu endpoint khusus untuk "selesai request equipment", karena request equipment bukan pekerjaan yang perlu diselesaikan secara terpisah.

---

### **2. Sound Engineer - Selesai Pekerjaan (Saat Request Equipment)**
**Masalah:** Sama seperti Produksi, `complete-work` digunakan untuk menyelesaikan work recording, bukan hanya request equipment.

**Solusi:**
- Setelah request equipment, Sound Engineer belum selesai work-nya
- Sound Engineer baru selesai work setelah upload recording, return equipment, dan complete recording

**Status:** ⚠️ **SUDAH BENAR** - Tidak perlu endpoint khusus untuk "selesai request equipment".

---

## ✅ YANG SUDAH BENAR

1. ✅ Produksi input list alat → Notifikasi ke Art & Set Properti
2. ✅ Art & Set Properti ACC alat → Notifikasi ke Produksi
3. ✅ Produksi input run sheet
4. ✅ Produksi upload hasil syuting → Auto-save link
5. ✅ Produksi input link file (optional, untuk manual input)
6. ✅ Produksi return equipment → Notifikasi ke Art & Set Properti
7. ✅ Sound Engineer input list alat → Notifikasi ke Art & Set Properti
8. ✅ Art & Set Properti ACC alat → Notifikasi ke Sound Engineer
9. ✅ Sound Engineer start recording
10. ✅ Sound Engineer upload file rekaman → Auto-save link

---

## 🎯 KESIMPULAN

### **Yang Sudah Lengkap (100%):**
- ✅ Produksi → Art & Set Properti → Produksi (LENGKAP)
- ✅ Sound Engineer → Art & Set Properti → Sound Engineer (LENGKAP)

### **Yang Sudah Ditambahkan:**
- ✅ **Sound Engineer - Return Equipment** (SUDAH DITAMBAHKAN)

### **Yang Optional:**
- ❓ Art & Set Properti - Accept Work & Complete Work (optional)
- ❓ Produksi - Accept Work & Process Work setelah equipment approved (optional)
- ❓ Sound Engineer - Accept Work setelah equipment approved (optional)

---

**Action Completed:**
1. ✅ **DONE:** Implementasi endpoint Sound Engineer return equipment
2. ✅ **DONE:** Tambahkan endpoint accept returned equipment untuk Art & Set Properti

---

**Last Updated:** 2026-01-27
