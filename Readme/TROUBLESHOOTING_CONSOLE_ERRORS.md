# 🔍 Troubleshooting Console Errors

## 📋 Error yang Terjadi

### 1. **Error fetching user history: "Primary endpoint failed, fallback endpoint dimatikan"**

**Error:**
```
Error fetching user history: Error: Primary endpoint failed, fallback endpoint dimatikan.
at MorningReflectionAttendanceService.getUserHistory
```

**Penyebab:**
- Backend tidak running di `http://127.0.0.1:8000`
- Endpoint `/api/morning-reflection/weekly-attendance` atau `/api/morning-reflection/attendance` tidak tersedia
- Frontend service mencoba fallback endpoint tapi fallback dimatikan

**Solusi:**

#### A. Pastikan Backend Running
```bash
# Di terminal backend
php artisan serve
# Atau
php artisan serve --host=127.0.0.1 --port=8000
```

#### B. Cek Endpoint Tersedia
Endpoint yang diperlukan:
- `GET /api/morning-reflection/attendance` ✅ (ada di routes/api.php line 335)
- `GET /api/morning-reflection/weekly-attendance` ✅ (ada di routes/api.php line 336)

#### C. Perbaiki Frontend Service
Jika fallback endpoint dimatikan, pastikan primary endpoint bisa diakses:

```javascript
// Di morning_reflection_attendance_service.js
async getUserHistory(employeeId, startDate, endDate) {
  try {
    // Primary endpoint
    const response = await api.get('/morning-reflection/weekly-attendance', {
      params: { employee_id: employeeId, start_date: startDate, end_date: endDate }
    });
    return response.data;
  } catch (error) {
    console.error('Error fetching user history:', error);
    // Jangan disable fallback, tapi handle error dengan baik
    throw new Error('Gagal memuat riwayat: ' + error.message);
  }
}
```

---

### 2. **GET http://127.0.0.1:8000/api/leave-requests net::ERR_CONNECTION_REFUSED**

**Error:**
```
GET http://127.0.0.1:8000/api/leave-requests net::ERR_CONNECTION_REFUSED
Backend connection failed, using offline mode: Failed to fetch
```

**Penyebab:**
- Backend tidak running
- Port 8000 tidak tersedia
- Firewall memblokir koneksi
- CORS issue

**Solusi:**

#### A. Start Backend Server
```bash
# Di terminal backend
cd C:\laragon\www\backend_hci_hrd
php artisan serve
```

#### B. Cek Route Tersedia
Route `/api/leave-requests` sudah ada di `routes/api.php` line 134:
```php
Route::prefix('leave-requests')->middleware('auth:sanctum')->group(function () {
    // Routes...
});
```

#### C. Perbaiki Frontend Error Handling
```javascript
// Di Layout.vue atau component yang fetch leave-requests
async fetchLeaveRequests() {
  try {
    const response = await api.get('/leave-requests');
    return response.data;
  } catch (error) {
    if (error.code === 'ERR_CONNECTION_REFUSED' || error.code === 'ERR_NETWORK') {
      console.warn('Backend tidak tersedia, menggunakan offline mode');
      // Return cached data atau empty array
      return { success: false, data: [], message: 'Backend tidak tersedia' };
    }
    throw error;
  }
}
```

---

### 3. **SyntaxError: Invalid or unexpected token dengan kode PHP**

**Error:**
```
VM363:1 Uncaught SyntaxError: Invalid or unexpected token
\App\Helpers\AuditLogger::logCritical('approve_arrangement', $arrangement, [
    'status' => 'approved'
], $request);
```

**Penyebab:**
- **KRITIS:** Kode PHP ter-expose ke frontend
- Response error menampilkan kode PHP sebagai string
- File PHP ter-akses langsung oleh browser
- Debug mode ON dan error handler menampilkan kode PHP

**Solusi:**

#### A. Pastikan APP_DEBUG=false di Production
```env
# .env
APP_DEBUG=false
APP_ENV=production
```

#### B. Cek Error Handler Tidak Expose Kode
Pastikan error handler tidak menampilkan kode PHP:

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof \Exception) {
        // Jangan expose kode PHP
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan pada server'
            // Jangan include stack trace atau kode PHP
        ], 500);
    }
    
    return parent::render($request, $exception);
}
```

#### C. Pastikan File PHP Tidak Ter-akses Langsung
Cek `.htaccess` atau web server config:

```apache
# public/.htaccess
# Block direct access to PHP files in app/ directory
<FilesMatch "\.php$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

#### D. Hapus Kode PHP dari Response
Jika ada response yang mengandung kode PHP, pastikan tidak ada:

```php
// ❌ JANGAN LAKUKAN INI
return response()->json([
    'code' => '\App\Helpers\AuditLogger::logCritical(...)'
]);

// ✅ LAKUKAN INI
\App\Helpers\AuditLogger::logCritical('approve_arrangement', $arrangement, [
    'status' => 'approved'
], $request);

return response()->json([
    'success' => true,
    'message' => 'Arrangement approved'
]);
```

#### E. Cek Apakah Ada File yang Ter-expose
Cek apakah ada file PHP yang bisa diakses langsung:
- `app/Helpers/AuditLogger.php` - seharusnya tidak bisa diakses langsung
- Pastikan web server root di `public/` bukan di root project

---

## 🔧 Perbaikan yang Diperlukan

### Backend:

1. ✅ **Pastikan Backend Running**
   ```bash
   php artisan serve
   ```

2. ✅ **Cek APP_DEBUG=false**
   ```env
   APP_DEBUG=false
   ```

3. ✅ **Perbaiki Error Handler**
   - Jangan expose kode PHP di response
   - Return generic error message

4. ✅ **Cek Route Tersedia**
   - `/api/leave-requests` ✅
   - `/api/morning-reflection/attendance` ✅
   - `/api/morning-reflection/weekly-attendance` ✅

### Frontend:

1. ✅ **Perbaiki Error Handling**
   - Handle `ERR_CONNECTION_REFUSED` dengan baik
   - Jangan crash jika backend tidak tersedia
   - Tampilkan user-friendly error message

2. ✅ **Perbaiki Fallback Logic**
   - Jangan disable fallback endpoint
   - Handle error dengan graceful degradation

3. ✅ **Cek API Base URL**
   - Pastikan base URL benar: `http://127.0.0.1:8000` untuk localhost
   - Atau `https://api.hopemedia.id` untuk production

---

## 📝 Checklist Perbaikan

### Backend:
- [ ] Backend server running (`php artisan serve`)
- [ ] `APP_DEBUG=false` di production
- [ ] Error handler tidak expose kode PHP
- [ ] Route `/api/leave-requests` tersedia
- [ ] Route `/api/morning-reflection/attendance` tersedia
- [ ] Route `/api/morning-reflection/weekly-attendance` tersedia
- [ ] CORS configured dengan benar
- [ ] `.htaccess` block direct access ke PHP files

### Frontend:
- [ ] Error handling untuk `ERR_CONNECTION_REFUSED`
- [ ] Fallback endpoint logic diperbaiki
- [ ] API base URL configuration benar
- [ ] User-friendly error messages
- [ ] Offline mode handling

---

## 🚨 Priority Fixes

### 🔴 CRITICAL (Fix Immediately):
1. **SyntaxError dengan kode PHP** - Ini security issue, kode PHP tidak boleh ter-expose
2. **Error Handler** - Pastikan tidak expose kode PHP di response

### 🟡 HIGH (Fix Soon):
1. **Backend Connection** - Pastikan backend running
2. **Error Handling** - Perbaiki frontend error handling

### 🟢 MEDIUM (Nice to Have):
1. **Fallback Logic** - Improve fallback endpoint handling
2. **Offline Mode** - Better offline mode support

---

## 🔍 Debugging Steps

1. **Cek Backend Running:**
   ```bash
   curl http://127.0.0.1:8000/api/leave-requests
   ```

2. **Cek Error Log:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Cek Browser Console:**
   - Network tab - lihat request/response
   - Console tab - lihat error details

4. **Cek Response:**
   - Pastikan response JSON, bukan kode PHP
   - Pastikan tidak ada stack trace di response

---

**Last Updated:** 2025-12-12
**Status:** 🔴 **NEEDS IMMEDIATE FIX**

