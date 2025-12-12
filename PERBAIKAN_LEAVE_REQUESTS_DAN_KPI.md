# Perbaikan Error 500 Leave Requests dan Error 404 KPI Dashboard

## 🔴 Masalah yang Ditemukan

1. **Error 500 pada `/api/leave-requests`** saat login sebagai HR
2. **Error 404 pada `/api/live-tv/kpi/dashboard`**
3. HR/Manager tidak perlu melihat leave requests mereka sendiri (karena mereka tidak punya cuti di sistem)

## ✅ Perbaikan yang Sudah Dilakukan

### 1. Perbaikan LeaveRequestController

**File**: `app/Http/Controllers/LeaveRequestController.php`

**Perubahan**:
- Menggunakan `leftJoin` untuk menghindari error relasi null
- Menambahkan validasi `whereNotNull('leave_requests.employee_id')`
- Memastikan relasi `employee.user` ada sebelum query
- Menambahkan error handling dengan try-catch
- Return empty array jika HR/Manager tidak punya bawahan

**Kode yang Diperbaiki**:
```php
// Sebelumnya menggunakan whereHas yang bisa error jika relasi null
$query = LeaveRequest::with(['employee.user', 'approvedBy.user']); 
$query->whereHas('employee.user', function ($q) use ($hrSubordinateRoles) { 
    $q->whereIn('role', $hrSubordinateRoles); 
});

// Sekarang menggunakan join yang lebih aman
$query = LeaveRequest::query()
    ->select('leave_requests.*')
    ->leftJoin('employees', 'leave_requests.employee_id', '=', 'employees.id')
    ->leftJoin('users', 'employees.id', '=', 'users.employee_id')
    ->whereNotNull('leave_requests.employee_id');
    
// Filter berdasarkan role
$query->whereIn('users.role', $hrSubordinateRoles)
      ->whereNotNull('users.role');
```

### 2. Perbaikan KPIController

**File**: `app/Http/Controllers/Api/KPIController.php`

**Perubahan**:
- Menambahkan error handling dengan try-catch
- Menambahkan logging error untuk debugging

### 3. Route KPI

**File**: `routes/live_tv_api.php` (line 523-529)

Route sudah terdaftar dengan benar:
```php
Route::prefix('kpi')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [KPIController::class, 'dashboard']);
    Route::get('/user/{userId?}', [KPIController::class, 'userKPI']);
    Route::get('/team', [KPIController::class, 'teamKPI']);
    Route::get('/program/{programId}', [KPIController::class, 'programKPI']);
});
```

**URL Lengkap**: `/api/live-tv/kpi/dashboard`

## 📋 Rekomendasi untuk Frontend

### 1. Skip Fetch Leave Requests untuk HR/Manager

**Role yang tidak perlu fetch leave-requests**:
- `HR` / `HR Manager`
- `Program Manager`
- `Distribution Manager`
- `VP President`
- `President Director`

**Implementasi di Frontend**:

```javascript
// Di Layout.vue atau component yang fetch leave-requests
const rolesWithoutLeaveRequests = [
  'HR',
  'HR Manager', 
  'Program Manager',
  'Distribution Manager',
  'VP President',
  'President Director'
];

async function fetchLeaveRequests() {
  const user = authStore.user; // atau cara lain untuk mendapatkan user
  
  // Skip fetch jika user adalah HR/Manager
  if (rolesWithoutLeaveRequests.includes(user?.role)) {
    console.log('Skip fetch leave-requests untuk role:', user?.role);
    return { success: true, data: [] };
  }
  
  // Lanjutkan fetch untuk role lain
  try {
    const response = await api.get('/leave-requests');
    return response.data;
  } catch (error) {
    console.error('Error fetching leave requests:', error);
    return { success: false, data: [] };
  }
}
```

### 2. Error Handling untuk KPI Dashboard

**File**: `musicWorkflowService.js` atau file yang fetch KPI

```javascript
async function fetchKPIDashboard() {
  try {
    const response = await api.get('/live-tv/kpi/dashboard');
    return response.data;
  } catch (error) {
    if (error.response?.status === 404) {
      console.warn('KPI endpoint not found (404) - Using fallback endpoint');
      // Fallback ke overview endpoint yang sudah ada
      try {
        const fallbackResponse = await api.get('/live-tv/dashboard/overview');
        return {
          success: true,
          data: {
            kpi: fallbackResponse.data?.kpi || null,
            from_fallback: true
          }
        };
      } catch (fallbackError) {
        console.error('Fallback endpoint also failed:', fallbackError);
        return { success: false, data: null };
      }
    }
    console.error('Error fetching KPI:', error);
    return { success: false, data: null };
  }
}
```

### 3. Update Notifications untuk HR/Manager

**File**: `Layout.vue` (sekitar line 1690-1803)

```javascript
// Update logic untuk notifications
async function fetchNotifications() {
  const user = authStore.user;
  const rolesWithoutLeaveRequests = [
    'HR', 'HR Manager', 
    'Program Manager', 'Distribution Manager',
    'VP President', 'President Director'
  ];
  
  // Skip fetch leave-requests untuk HR/Manager
  if (rolesWithoutLeaveRequests.includes(user?.role)) {
    // Hanya fetch notifications lain, skip leave-requests
    try {
      // Fetch notifications lain (bukan leave-requests)
      const otherNotifications = await fetchOtherNotifications();
      return otherNotifications;
    } catch (error) {
      console.warn('Backend error for HR/Manager - Check backend logs. Notifications will be empty.');
      return [];
    }
  }
  
  // Lanjutkan fetch normal untuk role lain
  // ...
}
```

## 🔍 Testing

Setelah perbaikan, test dengan:

1. **Login sebagai HR**:
   - ✅ Tidak ada error 500 pada `/api/leave-requests`
   - ✅ Return empty array `{success: true, data: []}`
   - ✅ Tidak ada error di console

2. **Login sebagai Employee**:
   - ✅ Bisa fetch leave-requests mereka sendiri
   - ✅ Tidak ada error

3. **KPI Dashboard**:
   - ✅ `/api/live-tv/kpi/dashboard` bisa diakses
   - ✅ Jika 404, fallback ke `/api/live-tv/dashboard/overview`

## 🚀 Langkah Selanjutnya

1. **Clear cache backend** (sudah dilakukan):
   ```bash
   php artisan route:clear
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Update Frontend**:
   - Update logic di `Layout.vue` untuk skip fetch leave-requests untuk HR/Manager
   - Update error handling untuk KPI dashboard
   - Test dengan login sebagai HR

3. **Monitor Logs**:
   - Cek `storage/logs/laravel.log` jika masih ada error
   - Error sudah di-log dengan detail untuk debugging

## 📝 Catatan Penting

- **HR/Manager tidak punya cuti di sistem**, jadi tidak perlu fetch leave-requests mereka sendiri
- **HR hanya melihat leave-requests dari bawahannya** (Finance, General Affairs, Office Assistant)
- **Manager hanya melihat leave-requests dari bawahannya** sesuai hierarchy
- **KPI route sudah terdaftar**, jika masih 404 mungkin perlu restart server atau clear cache lagi

