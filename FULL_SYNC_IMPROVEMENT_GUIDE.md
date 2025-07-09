# 🔧 Panduan Perbaikan Full Sync - Backend HCI

## 📋 Overview

Dokumentasi ini menjelaskan perbaikan yang dilakukan pada sistem **Full Sync** untuk mengatasi masalah gagal mengambil semua data dari mesin absensi.

## ❌ Masalah Sebelumnya

1. **Timeout HTTP**: Full sync memakan waktu lama dan sering timeout
2. **Memory Limit**: Memproses data besar menyebabkan out of memory
3. **Koneksi Timeout**: Koneksi ke mesin timeout saat mengambil banyak data
4. **Error Handling**: Error tidak ter-handle dengan baik
5. **Logging**: Kurang detail dalam proses tracking

## ✅ Solusi Yang Diimplementasikan

### 1. **Improved Timeout Settings**

#### File: `config/attendance.php`
```php
'machine' => [
    // ... existing settings ...
    'full_sync_timeout' => env('ATTENDANCE_MACHINE_FULL_TIMEOUT', 60),
    'max_execution_time' => env('ATTENDANCE_MAX_EXECUTION_TIME', 240),
    'memory_limit' => env('ATTENDANCE_MEMORY_LIMIT', '512M'),
],
```

#### Environment Variables (.env)
```bash
# Tambahkan ke file .env
ATTENDANCE_MACHINE_FULL_TIMEOUT=60
ATTENDANCE_MAX_EXECUTION_TIME=240
ATTENDANCE_MEMORY_LIMIT=512M
```

### 2. **Enhanced Controller Method**

#### File: `app/Http/Controllers/AttendanceController.php`

**Perbaikan di method `syncFromMachine()`:**

- ✅ Set longer timeout (5 menit)
- ✅ Increase memory limit (512M)
- ✅ Detailed logging per step
- ✅ Better error handling dengan details
- ✅ Response format yang kompatibel dengan frontend
- ✅ Progress tracking

```php
public function syncFromMachine(Request $request): JsonResponse
{
    // Set longer timeout untuk full sync
    set_time_limit(300); // 5 menit timeout
    ini_set('memory_limit', '512M'); // Increase memory limit
    
    // ... step-by-step implementation dengan logging detail ...
}
```

### 3. **Improved Machine Service**

#### File: `app/Services/AttendanceMachineService.php`

**Perbaikan di method `pullAttendanceData()`:**

- ✅ Timeout yang lebih panjang (60 detik)
- ✅ Stream timeout untuk membaca response besar
- ✅ Bigger chunk size (8KB)
- ✅ Progress monitoring setiap 10KB
- ✅ Maximum execution time (4 menit)
- ✅ Better error messages

**Perbaikan di method `processAttendanceData()`:**

- ✅ **Chunking**: Proses data dalam batch 100 records
- ✅ Progress logging per chunk
- ✅ Optimized database queries (exists() vs first())
- ✅ Better memory management
- ✅ Detailed statistics

```php
// Process in chunks untuk menghindari memory issues
$chunkSize = 100; // Process 100 records at a time
$chunks = array_chunk($attendanceData, $chunkSize);

foreach ($chunks as $chunkIndex => $chunk) {
    // Process each chunk dengan logging progress
}
```

### 4. **Enhanced Error Response Format**

Response format yang kompatibel dengan frontend Vue:

```json
{
    "success": true,
    "message": "Full sync berhasil! Pulled: 1500, Processed: 1200, Synced: 35 users",
    "data": {
        "pull_result": {
            "success": true,
            "message": "FULL SYNC: Berhasil memproses 1200 dari 1500 data absensi",
            "data": [...], // Array data untuk frontend stats
            "stats": {...},
            "total_from_machine": 1500
        },
        "process_result": {
            "success": true,
            "processed": 1200
        },
        "sync_results": {
            "total_users": 35,
            "synced_count": 35
        },
        "summary": {
            "total_pulled": 1500,
            "total_processed": 1200,
            "total_synced": 35,
            "operation": "FULL SYNC - Semua data dari mesin"
        }
    }
}
```

## 🧪 Testing

### Script Testing Otomatis

File: `test_full_sync_improved.php`

Jalankan test dengan:
```bash
php test_full_sync_improved.php
```

**Test coverage:**
1. ✅ Koneksi ke mesin
2. ✅ Pull semua data dari mesin
3. ✅ Proses logs ke attendance  
4. ✅ Auto-sync employee linking
5. ✅ Statistik final
6. ✅ API endpoint testing

### Manual Testing Via Frontend

1. Buka dashboard attendance
2. Klik tombol **"Full Sync"**
3. Konfirmasi dialog warning
4. Monitor progress di console browser
5. Lihat hasil sync di popup dan statistics

## 📊 Performance Improvements

### Before vs After

| Aspek | Sebelum | Sesudah |
|-------|---------|---------|
| **Timeout** | 10 detik | 60 detik (connection) + 300 detik (HTTP) |
| **Memory Limit** | Default PHP | 512M |
| **Data Processing** | All at once | Chunked (100 records/batch) |
| **Error Handling** | Basic | Detailed dengan trace |
| **Logging** | Minimal | Step-by-step progress |
| **Database Queries** | first() | exists() (optimized) |

### Expected Performance

- **Data Pull**: 1-3 menit untuk ~1500 records
- **Data Processing**: 30-60 detik 
- **Employee Linking**: 10-30 detik
- **Total Time**: 2-5 menit (vs 30-60 detik timeout sebelumnya)

## 🔧 Configuration Recommendations

### Production Settings

```bash
# .env untuk production
ATTENDANCE_MACHINE_FULL_TIMEOUT=90
ATTENDANCE_MAX_EXECUTION_TIME=300
ATTENDANCE_MEMORY_LIMIT=1024M

# PHP settings (php.ini)
max_execution_time = 300
memory_limit = 1024M
post_max_size = 64M
upload_max_filesize = 64M
```

### Development Settings

```bash
# .env untuk development
ATTENDANCE_MACHINE_FULL_TIMEOUT=60
ATTENDANCE_MAX_EXECUTION_TIME=240
ATTENDANCE_MEMORY_LIMIT=512M
```

## 🚀 Usage Guidelines

### Kapan Menggunakan Full Sync

✅ **Gunakan untuk:**
- Setup awal sistem
- Setelah maintenance panjang mesin
- Recovery data historical
- Migrasi sistem

❌ **Jangan gunakan untuk:**
- Sync harian rutin (gunakan **Refresh**)
- Testing kecil
- Update data real-time

### Best Practices

1. **Jalankan saat traffic rendah** (malam/weekend)
2. **Pastikan koneksi stabil** ke mesin
3. **Monitor log** untuk troubleshooting
4. **Backup database** sebelum full sync besar
5. **Gunakan Refresh** untuk sync harian

## 📝 Monitoring & Troubleshooting

### Log Files Location

- **Laravel Logs**: `storage/logs/laravel.log`
- **Sync Logs**: Database table `attendance_sync_logs`

### Key Log Patterns

```bash
# Successful full sync
grep "Full Sync: Completed successfully" storage/logs/laravel.log

# Full sync errors
grep "Full Sync: Fatal error" storage/logs/laravel.log

# Connection issues
grep "Full Pull: Error pulling" storage/logs/laravel.log
```

### Common Issues & Solutions

#### 1. **Timeout Error**
```
Error: Full sync error: Koneksi gagal ke 10.10.10.85:80
```
**Solusi:**
- Periksa koneksi network ke mesin
- Pastikan IP dan port benar
- Restart mesin absensi

#### 2. **Memory Limit Error**
```
Error: Allowed memory size exhausted
```
**Solusi:**
- Tingkatkan `ATTENDANCE_MEMORY_LIMIT`
- Kurangi chunk size di config

#### 3. **Database Lock**
```
Error: SQLSTATE[HY000]: General error: 1205 Lock wait timeout
```
**Solusi:**
- Tunggu operasi database lain selesai
- Restart database service
- Jalankan full sync saat traffic rendah

## 📞 Support

Jika masih mengalami masalah:

1. **Jalankan test script**: `php test_full_sync_improved.php`
2. **Periksa log**: `tail -f storage/logs/laravel.log`
3. **Cek connectivity**: `ping 10.10.10.85`
4. **Verify database**: Query `attendance_sync_logs` table

## 🔄 Rollback Instructions

Jika perlu rollback ke versi sebelumnya:

1. **Restore config/attendance.php** (remove new timeout settings)
2. **Restore AttendanceController.php** method `syncFromMachine()`
3. **Restore AttendanceMachineService.php** methods
4. **Remove test files**: `test_full_sync_improved.php`

## ✅ Verification Checklist

Setelah perbaikan, pastikan:

- [ ] Full sync bisa mengambil semua data dari mesin
- [ ] Tidak ada timeout error
- [ ] Frontend menampilkan progress dengan benar
- [ ] Statistics ditampilkan dengan akurat
- [ ] Log level appropriate (tidak spam)
- [ ] Regular refresh masih berfungsi normal
- [ ] Database performance tidak terdampak

---

**📅 Updated**: {{ Date }}  
**👨‍💻 By**: Backend HCI Team  
**🔖 Version**: 1.0 - Full Sync Improvement 