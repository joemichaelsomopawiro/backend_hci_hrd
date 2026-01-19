# 📋 STATUS FITUR MANAGER DISTRIBUSI

**Tanggal:** 2026-01-27  
**Role:** Distribution Manager / Manager Broadcasting

---

## ✅ FITUR YANG SUDAH BISA

### 1. **✅ Menerima Notifikasi Program**

**Status:** ✅ **SUDAH BISA**

**Cara Kerja:**
- Sistem otomatis mengirim notifikasi ke Distribution Manager saat:
  - Manager Program submit program untuk approval
  - Manager Program mengirim opsi jadwal tayang
  - Ada perubahan jadwal yang perlu review

**Endpoint:**
- `GET /api/live-tv/notifications?type=schedule_options_submitted,program_submitted`

**Notifikasi Type:**
- `schedule_options_submitted` - Manager Program mengirim opsi jadwal
- `program_submitted` - Program di-submit untuk approval
- `schedule_option_approved` - Opsi jadwal diterima
- `schedule_option_rejected` - Opsi jadwal ditolak

---

### 2. **✅ Menerima Opsi Jadwal**

**Status:** ✅ **SUDAH BISA**

**Cara Kerja:**
- Manager Program mengirim opsi jadwal tayang (bisa multiple options)
- Distribution Manager bisa melihat semua opsi jadwal yang pending
- Bisa approve/reject salah satu opsi

**Endpoint:**
- `GET /api/live-tv/manager-broadcasting/schedule-options` - List semua opsi jadwal pending
- `POST /api/live-tv/manager-broadcasting/schedule-options/{id}/approve` - Approve pilih salah satu opsi
- `POST /api/live-tv/manager-broadcasting/schedule-options/{id}/reject` - Reject semua opsi

**Request Body (Approve):**
```json
{
  "selected_option_index": 0,
  "review_notes": "Opsi pertama dipilih"
}
```

**Fitur:**
- Bisa pilih salah satu dari multiple opsi
- Otomatis create BroadcastingSchedule
- Otomatis notify Manager Program

---

### 3. **✅ Merevisi Jadwal & Memberitahu Manager Program**

**Status:** ✅ **SUDAH BISA**

**Cara Kerja:**
- Distribution Manager bisa revise jadwal yang sudah approved
- Otomatis notify Manager Program dengan jadwal baru
- Otomatis notify Broadcasting team

**Endpoint:**
- `POST /api/live-tv/manager-broadcasting/schedules/{id}/revise`

**Request Body:**
```json
{
  "new_schedule_date": "2026-02-15 21:00:00",
  "reason": "Jadwal perlu diubah karena bentrok",
  "notes": "Jadwal baru untuk tayang"
}
```

**Fitur:**
- ✅ Otomatis notify Manager Program
- ✅ Otomatis notify Broadcasting team
- ✅ Track history perubahan jadwal
- ✅ Audit trail tercatat

---

### 4. **❌ Membagi Pekerjaan (Berdasarkan Jabatan)**

**Status:** ❌ **BELUM ADA ENDPOINT KHUSUS**

**Catatan:**
- Saat ini pembagian pekerjaan dilakukan melalui:
  - Production Team assignment (Manager Program)
  - Producer assign team ke Creative Work
  - Workflow state assignment otomatis

**Rekomendasi:**
- Perlu endpoint khusus untuk Distribution Manager assign work ke distribution team (Social Media, Promotion, Broadcasting, dll)
- Atau bisa menggunakan mekanisme yang sama seperti Manager Program assign team

---

### 5. **✅ Membuat Target Pencapaian Views (Tarik Data Mingguan)**

**Status:** ✅ **SUDAH BISA** (via Manager Program endpoint, perlu verifikasi akses)

**Endpoint yang Tersedia:**
- `PUT /api/live-tv/manager-program/programs/{programId}/target-views` - Set target views
- `GET /api/live-tv/manager-program/programs/{programId}/weekly-performance` - Tarik data mingguan

**Request Body (Set Target):**
```json
{
  "target_views_per_episode": 100000
}
```

**Response (Weekly Performance):**
- Data per minggu
- Total views per minggu
- Achievement percentage
- Episodes per minggu

**Catatan:**
- Endpoint ini saat ini hanya untuk Manager Program
- **PERLU:** Tambahkan endpoint khusus untuk Distribution Manager atau share akses

---

### 6. **✅ Memonitoring Semua Pekerjaan Hingga Penayangan**

**Status:** ✅ **SUDAH BISA** (partial - perlu diperluas)

**Endpoint yang Tersedia:**
- `GET /api/live-tv/distribution/dashboard` - Dashboard overview
- `GET /api/live-tv/manager-program/episodes/{episodeId}/monitor-workflow` - Monitor workflow episode
- `GET /api/live-tv/manager-broadcasting/schedules` - List schedules
- `GET /api/live-tv/programs/{programId}/episodes` - List episodes program

**Fitur Dashboard:**
- Today schedules
- Pending approvals
- Active programs
- Recent activity

**Fitur Monitor Workflow:**
- Timeline semua tahap produksi:
  1. Music Arrangement
  2. Creative Work
  3. Sound Recording
  4. Production
  5. Editing
  6. Quality Control
  7. Broadcasting
- Progress percentage
- Days until air
- Blocking issues

**Keterbatasan:**
- Monitoring workflow masih menggunakan endpoint Manager Program
- **PERLU:** Buat endpoint khusus untuk Distribution Manager dengan view yang lebih fokus ke distribusi

---

### 7. **✅ Menutup Program Reguler yang Tidak Berkembang**

**Status:** ✅ **SUDAH BISA** (via Manager Program endpoint, perlu verifikasi akses)

**Endpoint yang Tersedia:**
- `GET /api/live-tv/manager-program/programs/underperforming` - List program underperform
- `POST /api/live-tv/manager-program/programs/{programId}/close` - Close program

**Request Body (Close Program):**
```json
{
  "reason": "Program tidak berkembang, performa rendah"
}
```

**Fitur:**
- Get list program dengan performa buruk (poor/warning)
- Filter by performance status
- Filter by minimum episodes
- Close program dengan alasan
- Otomatis notify Manager Program

**Catatan:**
- Endpoint ini saat ini hanya untuk Manager Program
- **PERLU:** Tambahkan endpoint khusus untuk Distribution Manager atau share akses

---

## 📊 RINGKASAN STATUS

| No | Fitur | Status | Endpoint | Catatan |
|---|---|---|---|---|
| 1 | Menerima Notifikasi Program | ✅ | `GET /notifications` | Sudah otomatis |
| 2 | Menerima Opsi Jadwal | ✅ | `GET /schedule-options` | Lengkap |
| 3 | Merevisi Jadwal & Notify | ✅ | `POST /schedules/{id}/revise` | Lengkap |
| 4 | Membagi Pekerjaan | ❌ | - | **Perlu ditambahkan** |
| 5 | Target Views & Weekly Data | ⚠️ | Via Manager Program | **Perlu share akses** |
| 6 | Monitoring Pekerjaan | ⚠️ | Via Manager Program | **Perlu endpoint khusus** |
| 7 | Close Program | ⚠️ | Via Manager Program | **Perlu share akses** |

---

## 🔧 REKOMENDASI PENAMBAHAN

### 1. **Endpoint Khusus Distribution Manager**

**Tambahkan endpoint berikut dengan prefix `/api/live-tv/distribution`:**

```php
// Program Management
GET  /distribution/programs                      // List programs
GET  /distribution/programs/{id}                 // Detail program
GET  /distribution/programs/{id}/performance     // Program performance
GET  /distribution/programs/{id}/weekly-performance // Weekly performance
PUT  /distribution/programs/{id}/target-views    // Set target views
GET  /distribution/programs/underperforming      // Underperform programs
POST /distribution/programs/{id}/close           // Close program

// Episode Monitoring
GET  /distribution/episodes/{id}/workflow        // Monitor workflow

// Work Assignment (BARU)
POST /distribution/episodes/{id}/assign-work     // Assign work to distribution team
GET  /distribution/available-workers/{role}      // Get available workers by role
```

### 2. **Controller Update**

**File:** `app/Http/Controllers/Api/DistributionManagerController.php`

**Tambahkan methods:**
- `setTargetViews()` - Set target views
- `getWeeklyPerformance()` - Get weekly performance
- `getUnderperformingPrograms()` - Get underperforming programs
- `closeProgram()` - Close program
- `monitorEpisodeWorkflow()` - Monitor workflow
- `assignWorkToTeam()` - Assign work to distribution team members

### 3. **Permission Update**

**File:** `app/Http/Controllers/Api/ManagerProgramController.php`

**Update permission untuk endpoint berikut agar Distribution Manager juga bisa akses:**
- `setTargetViews()` - Tambahkan `'Distribution Manager'` ke allowed roles
- `getWeeklyPerformance()` - Tambahkan `'Distribution Manager'` ke allowed roles
- `getUnderperformingPrograms()` - Tambahkan `'Distribution Manager'` ke allowed roles
- `closeProgram()` - Tambahkan `'Distribution Manager'` ke allowed roles
- `monitorEpisodeWorkflow()` - Tambahkan `'Distribution Manager'` ke allowed roles

---

## 📝 ENDPOINT YANG SUDAH TERSEDIA (POSTMAN)

### Notifikasi & Schedule Options
1. ✅ Get Notifications
2. ✅ Get Schedule Options
3. ✅ Approve Schedule Option
4. ✅ Reject Schedule Option

### Schedule Management
5. ✅ Get Schedules
6. ✅ Approve Schedule
7. ✅ Reject Schedule
8. ✅ Revise Schedule (dengan auto-notify)

### Broadcasting Works
9. ✅ Get Broadcasting Works
10. ✅ Approve Broadcasting Work

### Dashboard & Statistics
11. ✅ Dashboard
12. ✅ Get Statistics

### Monitoring (via Manager Program endpoints)
13. ✅ Monitor Episode Workflow
14. ✅ Get Program Performance
15. ✅ Get Weekly Performance
16. ✅ Get All Programs
17. ✅ Get Program Episodes
18. ✅ Get Underperforming Programs
19. ✅ Set Target Views
20. ✅ Close Program

---

## ✅ KESIMPULAN

### **Fitur yang 100% Ready:**
1. ✅ Menerima Notifikasi Program
2. ✅ Menerima Opsi Jadwal
3. ✅ Merevisi Jadwal & Memberitahu Manager Program

### **Fitur yang Bisa Digunakan (via Manager Program endpoint):**
4. ⚠️ Membuat Target Views & Weekly Data
5. ⚠️ Monitoring Pekerjaan
6. ⚠️ Menutup Program

### **Fitur yang Perlu Ditambahkan:**
7. ❌ Membagi Pekerjaan (Berdasarkan Jabatan)

---

## 🚀 NEXT STEPS

1. **Tambahkan permission Distribution Manager** ke endpoint Manager Program yang relevan
2. **Buat endpoint khusus Distribution Manager** untuk better separation of concerns
3. **Tambahkan endpoint Assign Work** untuk distribution team
4. **Update documentation** setelah endpoint ditambahkan

---

**Last Updated:** 2026-01-27
