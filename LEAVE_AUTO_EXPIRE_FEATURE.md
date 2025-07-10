# Fitur Auto-Expire Leave Requests

## 📋 Overview

Fitur ini secara otomatis mengubah status permohonan cuti menjadi "expired" jika manager lupa untuk approve/reject dan tanggal mulai cuti sudah terlewati.

## 🎯 Skenario Penggunaan

**Contoh Kasus:**
- Pegawai mengajukan cuti tanggal 10-14 Januari
- Manager lupa untuk approve/reject
- Tanggal 15 Januari (sudah lewat tanggal mulai cuti)
- Sistem otomatis mengubah status menjadi "expired"
- Manager melihat status "expired" dan tidak bisa lagi approve/reject

## 🔧 Implementasi Teknis

### 1. Database Migration

**File:** `database/migrations/2025_01_28_000001_add_expired_status_to_leave_requests_table.php`

```sql
-- Menambahkan status 'expired' ke enum overall_status
ALTER TABLE leave_requests MODIFY COLUMN overall_status 
ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending';
```

### 2. Artisan Command

**File:** `app/Console/Commands/ExpireLeaveRequests.php`

**Command:** `php artisan leave:expire`

**Fungsi:**
- Mencari semua leave request dengan status 'pending'
- Mengecek apakah start_date sudah lewat
- Mengubah status menjadi 'expired'
- Menambahkan rejection_reason otomatis

**Scheduled:** Berjalan otomatis setiap hari jam 07:00

### 3. Model Updates

**File:** `app/Models/LeaveRequest.php`

**Method Baru:**
- `isExpired()`: Mengecek apakah status expired
- `canBeProcessed()`: Mengecek apakah masih bisa diproses
- `checkAndExpire()`: Auto-expire jika tanggal sudah lewat
- `getStatusBadgeAttribute()`: Badge color untuk status expired (dark)

### 4. Controller Updates

**File:** `app/Http/Controllers/LeaveRequestController.php`

**Perubahan pada method:**
- `approve()`: Cek expired sebelum approve
- `reject()`: Cek expired sebelum reject  
- `destroy()`: Cek expired sebelum delete

## 🚀 Cara Menjalankan

### Manual Testing

```bash
# Jalankan migration
php artisan migrate

# Test command manual
php artisan leave:expire

# Test dengan file testing
php test_leave_expire_feature.php
```

### Automatic Scheduling

Command sudah terjadwal otomatis di `app/Console/Kernel.php`:

```php
$schedule->command('leave:expire')
         ->dailyAt('07:00')
         ->withoutOverlapping()
         ->appendOutputTo(storage_path('logs/leave-expire.log'));
```

## 📊 Status Flow

```
Pending → (tanggal mulai cuti lewat) → Expired
   ↓
Approved/Rejected (jika diproses sebelum expired)
```

## 🎨 UI/Frontend Integration

### Status Badge Colors

```javascript
const statusColors = {
    'pending': 'warning',    // Kuning
    'approved': 'success',   // Hijau
    'rejected': 'danger',    // Merah
    'expired': 'dark'        // Hitam/Abu-abu gelap
};
```

### Status Labels

```javascript
const statusLabels = {
    'pending': 'Menunggu Persetujuan',
    'approved': 'Disetujui',
    'rejected': 'Ditolak',
    'expired': 'Expired'
};
```

## 🔒 Security & Validation

### Prevent Actions on Expired Requests

```php
// Sebelum approve/reject/delete
if (!$leaveRequest->canBeProcessed()) {
    $message = $leaveRequest->isExpired() 
        ? 'Permohonan cuti sudah expired' 
        : 'Permohonan cuti sudah diproses';
    return response()->json(['error' => $message], 400);
}
```

## 📝 API Response Examples

### GET /api/leave-requests

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "employee_id": 123,
            "leave_type": "annual",
            "start_date": "2025-01-10",
            "end_date": "2025-01-14",
            "overall_status": "expired",
            "rejection_reason": "Permohonan cuti otomatis expired karena sudah melewati tanggal mulai cuti tanpa persetujuan.",
            "status_badge": "dark"
        }
    ]
}
```

### PUT /api/leave-requests/{id}/approve (pada expired request)

```json
{
    "success": false,
    "message": "Permohonan cuti sudah expired karena melewati tanggal mulai cuti"
}
```

## 📋 Testing Checklist

- ✅ Migration berhasil menambahkan status 'expired'
- ✅ Command `leave:expire` berjalan dengan benar
- ✅ Scheduled task berjalan setiap hari jam 07:00
- ✅ Method `checkAndExpire()` berfungsi
- ✅ Controller mencegah approval pada expired request
- ✅ Controller mencegah rejection pada expired request
- ✅ Controller mencegah delete pada expired request
- ✅ Badge color untuk expired status
- ✅ Logging ke `storage/logs/leave-expire.log`

## 🔍 Monitoring & Logs

### Log File Location
```
storage/logs/leave-expire.log
```

### Log Format
```
[2025-01-28 07:00:01] Expired: Leave request ID 123 for employee John Doe (Start date: 10/01/2025)
[2025-01-28 07:00:01] Total 5 permohonan cuti berhasil di-expire.
```

### Manual Check
```bash
# Lihat log expire
tail -f storage/logs/leave-expire.log

# Cek status expired di database
php artisan tinker
>>> App\Models\LeaveRequest::where('overall_status', 'expired')->count()
```

## 🚨 Troubleshooting

### Command Tidak Berjalan

1. Cek cron job/task scheduler
2. Pastikan Laravel scheduler berjalan:
   ```bash
   php artisan schedule:run
   ```

### Status Tidak Berubah

1. Cek tanggal sistem server
2. Pastikan timezone sudah benar di `config/app.php`
3. Test manual dengan `php artisan leave:expire`

### Permission Issues

1. Pastikan folder `storage/logs` writable
2. Cek permission file log

## 🎯 Benefits

1. **Otomatisasi**: Tidak perlu manual handling expired requests
2. **Clarity**: Manager tahu mana request yang sudah expired
3. **Data Integrity**: Mencegah approval pada request yang sudah tidak valid
4. **User Experience**: Feedback yang jelas untuk user
5. **Audit Trail**: Log lengkap untuk monitoring

## 🔄 Future Enhancements

1. **Email Notification**: Kirim email sebelum expire
2. **Grace Period**: Tambahkan masa tenggang sebelum expire
3. **Manager Notification**: Notifikasi ke manager sebelum expire
4. **Dashboard Widget**: Tampilkan statistik expired requests
5. **Bulk Actions**: Bulk approve/reject sebelum expire

---

**Status:** ✅ **IMPLEMENTED & READY TO USE**

**Last Updated:** 28 Januari 2025