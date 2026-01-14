# 🔍 ANALISIS STATUS ENDPOINT 404 - MANAGER PROGRAM

**Dokumen ini menganalisis endpoint yang mengembalikan 404 dan memberikan rekomendasi perbaikan.**

**Tanggal Analisis:** 2026-01-14

---

## 📊 RINGKASAN

Dari error log frontend, ditemukan **8 endpoint** yang mengembalikan 404. Setelah analisis routes backend, ditemukan bahwa:

- ✅ **6 endpoint** sebenarnya **SUDAH TERSEDIA** di backend (mungkin ada masalah path atau routing)
- ❌ **2 endpoint** **BELUM TERSEDIA** dan perlu dibuat

---

## ✅ ENDPOINT YANG SUDAH TERSEDIA

### 1. `/live-tv/programs` ✅

**Status:** ✅ **Tersedia**

**Route:** `GET /api/live-tv/programs` (line 55 di `routes/live_tv_api.php`)

**Controller:** `ProgramController@index`

**Kemungkinan Masalah:**
- Frontend mungkin menggunakan path yang salah
- Perlu verifikasi path yang digunakan frontend

**Rekomendasi:**
- Pastikan frontend menggunakan: `GET /api/live-tv/programs`
- Verifikasi base URL di frontend

---

### 2. `/live-tv/episodes` ✅

**Status:** ✅ **Tersedia**

**Route:** `GET /api/live-tv/episodes` (line 156 di `routes/live_tv_api.php`)

**Controller:** `EpisodeController@index`

**Kemungkinan Masalah:**
- Frontend mungkin menggunakan path yang salah
- Perlu verifikasi path yang digunakan frontend

**Rekomendasi:**
- Pastikan frontend menggunakan: `GET /api/live-tv/episodes`
- Verifikasi base URL di frontend

---

### 3. `/live-tv/manager-program/programs/underperforming` ✅

**Status:** ✅ **Tersedia**

**Route:** `GET /api/live-tv/manager-program/programs/underperforming` (line 116 di `routes/live_tv_api.php`)

**Controller:** `ManagerProgramController@getUnderperformingPrograms`

**Kemungkinan Masalah:**
- Frontend mungkin menggunakan path yang salah
- Perlu verifikasi path yang digunakan frontend

**Rekomendasi:**
- Pastikan frontend menggunakan: `GET /api/live-tv/manager-program/programs/underperforming`
- Verifikasi base URL di frontend

---

### 4. `/live-tv/production-teams` ✅

**Status:** ✅ **Tersedia**

**Route:** `GET /api/live-tv/production-teams` (line 257 di `routes/live_tv_api.php`)

**Controller:** `ProductionTeamController@index`

**Kemungkinan Masalah:**
- Frontend mungkin menggunakan path yang salah
- Perlu verifikasi path yang digunakan frontend

**Rekomendasi:**
- Pastikan frontend menggunakan: `GET /api/live-tv/production-teams`
- Verifikasi base URL di frontend

---

### 5. `/live-tv/notifications` ✅

**Status:** ✅ **Tersedia**

**Route:** `GET /api/live-tv/notifications` (line 202 di `routes/live_tv_api.php`)

**Controller:** `NotificationController@index`

**Kemungkinan Masalah:**
- Frontend mungkin menggunakan path yang salah
- Perlu verifikasi path yang digunakan frontend

**Rekomendasi:**
- Pastikan frontend menggunakan: `GET /api/live-tv/notifications`
- Verifikasi base URL di frontend

---

### 6. `/live-tv/unified-notifications` ✅

**Status:** ✅ **Tersedia**

**Route:** `GET /api/live-tv/unified-notifications` (line 230 di `routes/live_tv_api.php`)

**Controller:** `UnifiedNotificationController@index`

**Kemungkinan Masalah:**
- Frontend mungkin menggunakan path yang salah
- Perlu verifikasi path yang digunakan frontend

**Rekomendasi:**
- Pastikan frontend menggunakan: `GET /api/live-tv/unified-notifications`
- Verifikasi base URL di frontend

---

## ❌ ENDPOINT YANG BELUM TERSEDIA

### 7. `/live-tv/manager-program/approvals` ❌

**Status:** ❌ **Belum Tersedia**

**Error dari Frontend:**
```
GET /api/live-tv/manager-program/approvals?include_completed=true 404 (Not Found)
```

**Analisis:**
- Tidak ada route GET untuk list semua approvals di `routes/live_tv_api.php`
- Hanya ada route POST untuk override approval: `POST /api/live-tv/manager-program/approvals/{approvalId}/override` (line 134)

**Endpoint yang Terkait (Sudah Ada):**
- ✅ `GET /api/live-tv/manager-program/rundown-edit-requests` (line 141)
- ✅ `GET /api/live-tv/manager-program/special-budget-approvals` (line 146)
- ✅ `POST /api/live-tv/manager-program/approvals/{approvalId}/override` (line 134)

**Rekomendasi:**
1. **Buat endpoint baru** untuk list semua approvals:
   ```php
   Route::get('/approvals', [ManagerProgramController::class, 'getAllApprovals'])
       ->middleware('throttle:60,1');
   ```

2. **Implementasi method** di `ManagerProgramController`:
   ```php
   public function getAllApprovals(Request $request): JsonResponse
   {
       // Get all approvals (rundown edits, special budgets, dll)
       // Support filter: include_completed, status, type
   }
   ```

3. **Response Format:**
   ```json
   {
     "success": true,
     "data": {
       "rundown_edits": [...],
       "special_budgets": [...],
       "other_approvals": [...]
     },
     "message": "Approvals retrieved successfully"
   }
   ```

---

### 8. `/live-tv/manager-program/schedules` ❌

**Status:** ❌ **Belum Tersedia**

**Error dari Frontend:**
```
GET /api/live-tv/manager-program/schedules?status=scheduled,confirmed&include_cancelled=false 404 (Not Found)
```

**Analisis:**
- Tidak ada route GET untuk list semua schedules di `routes/live_tv_api.php`
- Hanya ada route untuk cancel/reschedule: 
  - `POST /api/live-tv/manager-program/schedules/{scheduleId}/cancel` (line 130)
  - `POST /api/live-tv/manager-program/schedules/{scheduleId}/reschedule` (line 131)

**Endpoint yang Terkait (Sudah Ada):**
- ✅ `GET /api/live-tv/schedules/shooting` (line 637)
- ✅ `GET /api/live-tv/schedules/airing` (line 638)
- ✅ `GET /api/live-tv/manager-program/revised-schedules` (line 151)

**Rekomendasi:**
1. **Buat endpoint baru** untuk list semua schedules:
   ```php
   Route::get('/schedules', [ManagerProgramController::class, 'getAllSchedules'])
       ->middleware('throttle:60,1');
   ```

2. **Implementasi method** di `ManagerProgramController`:
   ```php
   public function getAllSchedules(Request $request): JsonResponse
   {
       // Get all schedules untuk programs yang dikelola oleh Manager Program ini
       // Support filter: status, include_cancelled, program_id, date_range
   }
   ```

3. **Response Format:**
   ```json
   {
     "success": true,
     "data": {
       "schedules": [...],
       "pagination": {...}
     },
     "message": "Schedules retrieved successfully"
   }
   ```

---

## 🔧 REKOMENDASI IMPLEMENTASI

### Prioritas 1: Endpoint yang Belum Tersedia

#### 1. Buat Endpoint `/live-tv/manager-program/approvals`

**File yang Perlu Dimodifikasi:**
- `routes/live_tv_api.php` - Tambahkan route
- `app/Http/Controllers/Api/ManagerProgramController.php` - Tambahkan method

**Implementasi:**
```php
// routes/live_tv_api.php (line 133, setelah override approval)
Route::get('/approvals', [ManagerProgramController::class, 'getAllApprovals'])
    ->middleware('throttle:60,1');
```

```php
// ManagerProgramController.php
public function getAllApprovals(Request $request): JsonResponse
{
    try {
        $user = auth()->user();
        
        if (!in_array(strtolower($user->role), ['manager program', 'program manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can access this'
            ], 403);
        }
        
        $includeCompleted = $request->boolean('include_completed', false);
        
        // Get rundown edit requests
        $rundownEdits = ProgramApproval::where('approval_type', 'rundown_edit')
            ->when(!$includeCompleted, function($q) {
                $q->whereIn('status', ['pending', 'reviewed']);
            })
            ->with(['approvable.episode.program', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get special budget approvals
        $specialBudgets = ProgramApproval::where('approval_type', 'special_budget')
            ->when(!$includeCompleted, function($q) {
                $q->whereIn('status', ['pending', 'reviewed']);
            })
            ->with(['approvable.episode.program', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'rundown_edits' => $rundownEdits,
                'special_budgets' => $specialBudgets,
                'total_pending' => $rundownEdits->where('status', 'pending')->count() + 
                                  $specialBudgets->where('status', 'pending')->count()
            ],
            'message' => 'Approvals retrieved successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving approvals: ' . $e->getMessage()
        ], 500);
    }
}
```

#### 2. Buat Endpoint `/live-tv/manager-program/schedules`

**File yang Perlu Dimodifikasi:**
- `routes/live_tv_api.php` - Tambahkan route
- `app/Http/Controllers/Api/ManagerProgramController.php` - Tambahkan method

**Implementasi:**
```php
// routes/live_tv_api.php (line 131, setelah reschedule)
Route::get('/schedules', [ManagerProgramController::class, 'getAllSchedules'])
    ->middleware('throttle:60,1');
```

```php
// ManagerProgramController.php
public function getAllSchedules(Request $request): JsonResponse
{
    try {
        $user = auth()->user();
        
        if (!in_array(strtolower($user->role), ['manager program', 'program manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can access this'
            ], 403);
        }
        
        // Get programs managed by this user
        $programIds = Program::where('manager_program_id', $user->id)
            ->pluck('id');
        
        $query = \App\Models\ProgramSchedule::whereIn('program_id', $programIds)
            ->with(['program', 'episode']);
        
        // Filter by status
        if ($request->has('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }
        
        // Filter cancelled
        if (!$request->boolean('include_cancelled', false)) {
            $query->where('status', '!=', 'cancelled');
        }
        
        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('scheduled_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('scheduled_date', '<=', $request->end_date);
        }
        
        $schedules = $query->orderBy('scheduled_date', 'desc')
            ->paginate($request->get('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data' => $schedules,
            'message' => 'Schedules retrieved successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving schedules: ' . $e->getMessage()
        ], 500);
    }
}
```

---

### Prioritas 2: Verifikasi Endpoint yang Sudah Tersedia

Untuk endpoint yang sudah tersedia tapi masih mengembalikan 404, perlu dilakukan:

1. **Verifikasi Base URL di Frontend**
   - Pastikan base URL: `http://localhost:8000/api`
   - Pastikan tidak ada typo di path

2. **Test Endpoint dengan Postman/curl**
   ```bash
   # Test programs endpoint
   curl -X GET "http://localhost:8000/api/live-tv/programs" \
     -H "Authorization: Bearer {token}"
   
   # Test episodes endpoint
   curl -X GET "http://localhost:8000/api/live-tv/episodes" \
     -H "Authorization: Bearer {token}"
   ```

3. **Cek Route List**
   ```bash
   php artisan route:list --path=live-tv
   ```

4. **Verifikasi Middleware**
   - Pastikan user sudah login dan memiliki token valid
   - Pastikan role user sesuai dengan yang diizinkan

---

## 📝 CHECKLIST IMPLEMENTASI

### Backend (Laravel)

- [ ] Buat method `getAllApprovals()` di `ManagerProgramController`
- [ ] Buat method `getAllSchedules()` di `ManagerProgramController`
- [ ] Tambahkan route `GET /api/live-tv/manager-program/approvals`
- [ ] Tambahkan route `GET /api/live-tv/manager-program/schedules`
- [ ] Test endpoint dengan Postman/curl
- [ ] Update dokumentasi API

### Frontend (Vue.js)

- [ ] Verifikasi base URL di `musicWorkflowService.js`
- [ ] Verifikasi path endpoint yang digunakan
- [ ] Test endpoint setelah backend ready
- [ ] Update error handling jika diperlukan

---

## 🎯 KESIMPULAN

1. **6 endpoint** sudah tersedia di backend, kemungkinan masalah di path atau routing
2. **2 endpoint** belum tersedia dan perlu dibuat:
   - `/live-tv/manager-program/approvals`
   - `/live-tv/manager-program/schedules`

3. **Prioritas:**
   - **Tinggi:** Buat 2 endpoint yang belum tersedia
   - **Sedang:** Verifikasi endpoint yang sudah tersedia tapi masih 404
   - **Rendah:** Update dokumentasi setelah implementasi

---

## 📚 REFERENSI

- [API Documentation - Manager Program](./API_DOCUMENTATION_MANAGER_PROGRAM.md)
- [Routes File](./routes/live_tv_api.php)
- [ManagerProgramController](./app/Http/Controllers/Api/ManagerProgramController.php)

---

**Dibuat oleh:** AI Assistant  
**Terakhir Diupdate:** 2026-01-14
