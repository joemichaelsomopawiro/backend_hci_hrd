# Update Distribution Manager Endpoints

## ✅ UPDATE YANG SUDAH DILAKUKAN

### 1. Permission Update
**File:** `app/Http/Controllers/Api/ManagerProgramController.php`

Distribution Manager sekarang bisa mengakses endpoint berikut (sama seperti Manager Program):
- ✅ `getProgramPerformance()` - View program performance
- ✅ `getWeeklyPerformance()` - Get weekly performance report (tarik data mingguan)
- ✅ `setTargetViews()` - Set target views per episode
- ✅ `monitorEpisodeWorkflow()` - Monitor semua workflow episode
- ✅ `getUnderperformingPrograms()` - Get list program yang tidak berkembang
- ✅ `closeProgram()` - Close program reguler yang tidak berkembang

**Permission Check:**
```php
if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram', 'Distribution Manager'])) {
    return response()->json([
        'success' => false,
        'message' => 'Only Manager Program or Distribution Manager can access this'
    ], 403);
}
```

---

### 2. Endpoint Baru untuk Assign Work
**File:** `app/Http/Controllers/Api/DistributionManagerController.php`

**Endpoint 1: Assign Work to Distribution Team**
- **Route:** `POST /api/live-tv/distribution/episodes/{episodeId}/assign-work`
- **Method:** `assignWork()`
- **Fungsi:** Membagi pekerjaan ke distribution team berdasarkan jabatan
- **Roles yang didukung:**
  - `broadcasting` → Broadcasting
  - `promotion` → Promotion
  - `graphic_design` → Graphic Design
  - `social_media` → Social Media
  - `editor_promotion` → Editor Promotion

**Request Body:**
```json
{
  "role": "broadcasting",
  "user_ids": [1, 2, 3],
  "work_type": "upload_youtube",
  "notes": "Upload episode ke YouTube",
  "deadline": "2026-02-15 23:59:59"
}
```

**Work Types:**
- `upload_youtube`
- `upload_website`
- `create_promotion`
- `design_poster`
- `social_media_post`

**Fitur:**
- ✅ Validasi user memiliki role yang benar
- ✅ Auto-create notification untuk setiap user yang di-assign
- ✅ Track assignment dengan audit trail

---

**Endpoint 2: Get Available Workers**
- **Route:** `GET /api/live-tv/distribution/available-workers/{role}`
- **Method:** `getAvailableWorkers()`
- **Fungsi:** Get list available workers berdasarkan role untuk assign work

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "Broadcasting"
    }
  ],
  "message": "Available workers retrieved successfully"
}
```

---

### 3. Routes Update
**File:** `routes/live_tv_api.php`

**Distribution Routes:**
```php
Route::prefix('distribution')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/dashboard', [DistributionManagerController::class, 'dashboard'])->middleware('throttle:60,1');
    Route::get('/available-workers/{role}', [DistributionManagerController::class, 'getAvailableWorkers'])->middleware('throttle:60,1');
    Route::post('/episodes/{episodeId}/assign-work', [DistributionManagerController::class, 'assignWork'])->middleware('throttle:sensitive');
});
```

---

### 4. Postman Collection Update
**File:** `Postman_Collection_HCI_HRD_Complete_Flow.json`

**Endpoint yang ditambahkan/diperbarui untuk Distribution Manager:**

1. ✅ **Get Available Workers (by Role)**
   - `GET /api/live-tv/distribution/available-workers/{role}`
   - Auto-save worker_user_id untuk testing selanjutnya

2. ✅ **Assign Work to Distribution Team**
   - `POST /api/live-tv/distribution/episodes/{episodeId}/assign-work`
   - Support semua role distribution team
   - Support semua work types

3. ✅ **Endpoint yang bisa diakses via Manager Program:**
   - Monitor Episode Workflow
   - Get Program Performance
   - Get Weekly Performance
   - Set Target Views
   - Get Underperforming Programs
   - Close Program

---

## 📋 RINGKASAN FITUR DISTRIBUTION MANAGER

### ✅ Fitur yang 100% Ready:

1. **✅ Menerima Notifikasi Program**
   - Via `/api/live-tv/notifications`
   - Filter: `schedule_options_submitted`, `program_submitted`, dll

2. **✅ Menerima Opsi Jadwal**
   - Via `/api/live-tv/manager-broadcasting/schedule-options`
   - Bisa approve/reject opsi jadwal dari Manager Program

3. **✅ Merevisi Jadwal & Notify Manager Program**
   - Via `/api/live-tv/manager-broadcasting/schedules/{id}/revise`
   - Otomatis notify Manager Program setelah revisi

4. **✅ Membagi Pekerjaan (Berdasarkan Jabatan)** ⭐ NEW
   - Via `/api/live-tv/distribution/episodes/{episodeId}/assign-work`
   - Bisa assign ke multiple users dengan role yang sama
   - Auto-notify setiap user yang di-assign

5. **✅ Membuat Target Pencapaian Views & Tarik Data Mingguan**
   - Via `/api/live-tv/manager-program/programs/{programId}/target-views` (PUT)
   - Via `/api/live-tv/manager-program/programs/{programId}/weekly-performance` (GET)

6. **✅ Monitoring Semua Pekerjaan Hingga Penayangan**
   - Via `/api/live-tv/manager-program/episodes/{episodeId}/monitor-workflow`
   - Dashboard: `/api/live-tv/distribution/dashboard`

7. **✅ Menutup Program Reguler yang Tidak Berkembang**
   - Via `/api/live-tv/manager-program/programs/{programId}/close`
   - Via `/api/live-tv/manager-program/programs/underperforming` (GET) untuk list

---

## 🎯 ENDPOINT SUMMARY

### Distribution Manager Endpoints

#### Schedule Management (via Manager Broadcasting)
- `GET /api/live-tv/manager-broadcasting/schedules` - Get schedules
- `GET /api/live-tv/manager-broadcasting/schedule-options` - Get schedule options
- `POST /api/live-tv/manager-broadcasting/schedules/{id}/approve` - Approve schedule
- `POST /api/live-tv/manager-broadcasting/schedules/{id}/reject` - Reject schedule
- `POST /api/live-tv/manager-broadcasting/schedules/{id}/revise` - Revise schedule

#### Work Assignment (NEW)
- `GET /api/live-tv/distribution/available-workers/{role}` - Get available workers
- `POST /api/live-tv/distribution/episodes/{episodeId}/assign-work` - Assign work

#### Dashboard & Statistics
- `GET /api/live-tv/distribution/dashboard` - Dashboard overview
- `GET /api/live-tv/manager-broadcasting/statistics` - Statistics

#### Program Management (Shared with Manager Program)
- `GET /api/live-tv/manager-program/programs/{id}/performance` - Program performance
- `GET /api/live-tv/manager-program/programs/{id}/weekly-performance` - Weekly performance
- `PUT /api/live-tv/manager-program/programs/{id}/target-views` - Set target views
- `GET /api/live-tv/manager-program/programs/underperforming` - Underperforming programs
- `POST /api/live-tv/manager-program/programs/{id}/close` - Close program

#### Episode Monitoring (Shared with Manager Program)
- `GET /api/live-tv/manager-program/episodes/{id}/monitor-workflow` - Monitor workflow

---

## ✅ KESIMPULAN

**Semua fitur yang diminta sudah tersedia:**

1. ✅ Menerima Notifikasi Program
2. ✅ Menerima Opsi Jadwal
3. ✅ Merevisi Jadwal & Memberitahu Manager Program
4. ✅ Membagi Pekerjaan (Berdasarkan Jabatan) ⭐ NEW
5. ✅ Membuat Target Pencapaian Views & Tarik Data Mingguan
6. ✅ Monitoring Semua Pekerjaan Hingga Penayangan
7. ✅ Menutup Program Reguler yang Tidak Berkembang

**Status:** ✅ **READY FOR TESTING**

---

**Last Updated:** 2026-01-27
