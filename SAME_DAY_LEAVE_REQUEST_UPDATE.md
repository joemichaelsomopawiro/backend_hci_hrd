# Same-Day Leave Request Update

## 📋 Overview

Sistem cuti telah diupdate untuk memungkinkan karyawan mengajukan cuti di hari yang sama (same-day leave request), terutama untuk situasi darurat.

## 🔧 Perubahan yang Dilakukan

### 1. LeaveRequestController.php - Method store()

**File:** `app/Http/Controllers/LeaveRequestController.php`

**Perubahan Utama:**
- ✅ **Menghapus batasan `after_or_equal:today`** pada validasi `start_date`
- ✅ **Menambahkan logika khusus** untuk cuti darurat (emergency leave)
- ✅ **Memungkinkan cuti di hari yang sama** untuk semua jenis cuti
- ✅ **Menambahkan logging** untuk same-day leave requests

### 2. Validasi Baru

#### Sebelum Update:
```php
'start_date' => 'required|date|after_or_equal:today',
```

#### Setelah Update:
```php
'start_date' => 'required|date',

// Validasi khusus untuk tanggal masa lalu (hanya untuk non-emergency leave)
$startDate = Carbon::parse($request->start_date);
$today = Carbon::today();

if ($request->leave_type !== 'emergency' && $startDate->lt($today)) {
    return response()->json([
        'success' => false, 
        'message' => 'Tanggal mulai cuti tidak boleh di masa lalu kecuali untuk cuti darurat'
    ], 400);
}

// Peringatan untuk cuti di hari yang sama (bukan error, hanya info)
if ($startDate->eq($today)) {
    \Log::info('Same-day leave request submitted', [
        'employee_id' => $user->employee_id,
        'leave_type' => $request->leave_type,
        'start_date' => $request->start_date
    ]);
}
```

## 🎯 Fitur Baru

### 1. Same-Day Leave Request
- ✅ **Semua jenis cuti** dapat diajukan di hari yang sama
- ✅ **Tidak ada error** saat mengajukan cuti untuk hari ini
- ✅ **Logging otomatis** untuk tracking same-day requests

### 2. Emergency Leave Flexibility
- ✅ **Cuti darurat** dapat diajukan bahkan untuk tanggal masa lalu
- ✅ **Fleksibilitas maksimal** untuk situasi emergency
- ✅ **Validasi yang lebih smart** berdasarkan jenis cuti

### 3. Improved Validation Logic
- ✅ **Validasi bertingkat** berdasarkan jenis cuti
- ✅ **Error message yang jelas** untuk setiap kondisi
- ✅ **Logging untuk audit trail**

## 📊 Skenario Penggunaan

### Skenario 1: Cuti Darurat Hari Ini
```json
POST /api/leave-requests
{
    "leave_type": "emergency",
    "start_date": "2025-01-30",
    "end_date": "2025-01-30",
    "reason": "Keluarga sakit mendadak"
}
```
**Result:** ✅ **BERHASIL** - Cuti darurat dapat diajukan di hari yang sama

### Skenario 2: Cuti Sakit Hari Ini
```json
POST /api/leave-requests
{
    "leave_type": "sick",
    "start_date": "2025-01-30",
    "end_date": "2025-01-30",
    "reason": "Demam tinggi"
}
```
**Result:** ✅ **BERHASIL** - Cuti sakit dapat diajukan di hari yang sama

### Skenario 3: Cuti Tahunan Hari Ini
```json
POST /api/leave-requests
{
    "leave_type": "annual",
    "start_date": "2025-01-30",
    "end_date": "2025-01-30",
    "reason": "Urusan mendadak"
}
```
**Result:** ✅ **BERHASIL** - Cuti tahunan dapat diajukan di hari yang sama

### Skenario 4: Cuti untuk Tanggal Masa Lalu (Non-Emergency)
```json
POST /api/leave-requests
{
    "leave_type": "annual",
    "start_date": "2025-01-29",
    "end_date": "2025-01-29",
    "reason": "Lupa mengajukan kemarin"
}
```
**Result:** ❌ **DITOLAK** - "Tanggal mulai cuti tidak boleh di masa lalu kecuali untuk cuti darurat"

### Skenario 5: Cuti Darurat untuk Tanggal Masa Lalu
```json
POST /api/leave-requests
{
    "leave_type": "emergency",
    "start_date": "2025-01-29",
    "end_date": "2025-01-29",
    "reason": "Kecelakaan kemarin, baru bisa lapor sekarang"
}
```
**Result:** ✅ **BERHASIL** - Cuti darurat dapat diajukan untuk tanggal masa lalu

## 🔍 Logging & Monitoring

### Same-Day Leave Request Log
Setiap kali ada pengajuan cuti di hari yang sama, sistem akan mencatat:

```php
\Log::info('Same-day leave request submitted', [
    'employee_id' => $user->employee_id,
    'leave_type' => $request->leave_type,
    'start_date' => $request->start_date
]);
```

**Log Location:** `storage/logs/laravel.log`

**Log Format:**
```
[2025-01-30 10:30:00] local.INFO: Same-day leave request submitted {"employee_id":123,"leave_type":"emergency","start_date":"2025-01-30"}
```

## 📋 Validation Rules Summary

| Jenis Cuti | Same-Day | Past Date | Validation |
|------------|----------|-----------|------------|
| **Emergency** | ✅ Allowed | ✅ Allowed | Paling fleksibel |
| **Sick** | ✅ Allowed | ❌ Blocked | Hari ini & masa depan |
| **Annual** | ✅ Allowed | ❌ Blocked | Hari ini & masa depan |
| **Maternity** | ✅ Allowed | ❌ Blocked | Hari ini & masa depan |
| **Paternity** | ✅ Allowed | ❌ Blocked | Hari ini & masa depan |
| **Marriage** | ✅ Allowed | ❌ Blocked | Hari ini & masa depan |
| **Bereavement** | ✅ Allowed | ❌ Blocked | Hari ini & masa depan |

## 🚀 Benefits

### 1. Fleksibilitas Maksimal
- ✅ **Cuti darurat** dapat diajukan kapan saja
- ✅ **Same-day requests** untuk semua jenis cuti
- ✅ **Tidak ada hambatan teknis** untuk situasi mendesak

### 2. User Experience Improved
- ✅ **Tidak ada error frustrating** saat butuh cuti mendadak
- ✅ **Proses yang smooth** untuk emergency situations
- ✅ **Error messages yang jelas** dan informatif

### 3. Business Logic yang Smart
- ✅ **Validasi berbeda** untuk setiap jenis cuti
- ✅ **Emergency leave** mendapat perlakuan khusus
- ✅ **Audit trail** untuk monitoring

## 🔧 Technical Implementation

### Files Modified:
1. **`app/Http/Controllers/LeaveRequestController.php`**
   - Method: `store()`
   - Lines: 98-126 (validation section)

### Dependencies:
- ✅ **Carbon** (already imported)
- ✅ **Laravel Validation**
- ✅ **Laravel Logging**

### Database Impact:
- ✅ **No database changes required**
- ✅ **Existing schema supports all scenarios**

## 🧪 Testing Scenarios

### Test Case 1: Same-Day Emergency Leave
```bash
curl -X POST http://localhost:8000/api/leave-requests \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "leave_type": "emergency",
    "start_date": "2025-01-30",
    "end_date": "2025-01-30",
    "reason": "Family emergency"
  }'
```

### Test Case 2: Same-Day Sick Leave
```bash
curl -X POST http://localhost:8000/api/leave-requests \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "leave_type": "sick",
    "start_date": "2025-01-30",
    "end_date": "2025-01-30",
    "reason": "Sudden illness"
  }'
```

### Test Case 3: Past Date Non-Emergency (Should Fail)
```bash
curl -X POST http://localhost:8000/api/leave-requests \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "leave_type": "annual",
    "start_date": "2025-01-29",
    "end_date": "2025-01-29",
    "reason": "Forgot to apply yesterday"
  }'
```

## 📈 Expected Responses

### Success Response (Same-Day Leave):
```json
{
    "success": true,
    "message": "Permohonan cuti berhasil diajukan",
    "data": {
        "id": 123,
        "employee_id": 456,
        "leave_type": "emergency",
        "start_date": "2025-01-30",
        "end_date": "2025-01-30",
        "total_days": 1,
        "reason": "Family emergency",
        "overall_status": "pending",
        "created_at": "2025-01-30T10:30:00.000000Z"
    }
}
```

### Error Response (Past Date Non-Emergency):
```json
{
    "success": false,
    "message": "Tanggal mulai cuti tidak boleh di masa lalu kecuali untuk cuti darurat"
}
```

## 🎯 Summary

Update ini berhasil mengatasi masalah utama:
- ✅ **Same-day leave requests** sekarang dapat diajukan
- ✅ **Emergency leave** mendapat fleksibilitas maksimal
- ✅ **Validation logic** yang lebih smart dan user-friendly
- ✅ **Logging** untuk monitoring dan audit
- ✅ **Backward compatibility** terjaga

Sistem sekarang mendukung skenario real-world dimana karyawan mungkin perlu mengajukan cuti mendadak di hari yang sama, terutama untuk situasi darurat.