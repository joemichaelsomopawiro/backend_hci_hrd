# Sistem Sync Bulanan - Hope Channel Indonesia

## 📋 Overview

Sistem sync bulanan dirancang untuk menarik data absensi dari mesin sesuai dengan bulan dan tahun saat ini secara otomatis. Sistem ini memastikan bahwa data yang ditarik selalu sesuai dengan periode waktu yang sedang berjalan.

## 🎯 Fitur Utama

### ✅ **Auto-Detection Bulan & Tahun**
- Otomatis mendeteksi bulan dan tahun saat ini
- Tidak akan menarik data dari tahun lalu
- Mengikuti kalender dunia (realtime)

### ✅ **Filter Data Cerdas**
- Menarik semua data dari mesin
- Filter hanya data untuk bulan saat ini
- Menghindari duplikasi data

### ✅ **Processing Optimized**
- Processing dalam chunks untuk menghindari memory issues
- Auto-sync employee linking
- Sinkronisasi employee_id otomatis

## 🔧 Cara Penggunaan

### 1. **Via Script PHP**
```bash
# Sync bulanan langsung
php monthly_sync.php
```

### 2. **Via API Endpoint**
```bash
# POST request ke API
curl -X POST http://localhost:8000/api/attendance/sync-current-month
```

### 3. **Via Test Script**
```bash
# Test sync bulanan
php test_monthly_sync.php
```

## 📊 Contoh Penggunaan

### Skenario 1: Sync di Awal Bulan
```bash
# Tanggal: 1 Juli 2025
php monthly_sync.php
# Hasil: Menarik data Juli 2025 (1-31 Juli)
```

### Skenario 2: Sync di Tengah Bulan
```bash
# Tanggal: 15 Juli 2025
php monthly_sync.php
# Hasil: Menarik data Juli 2025 (1-31 Juli)
```

### Skenario 3: Sync di Akhir Bulan
```bash
# Tanggal: 31 Juli 2025
php monthly_sync.php
# Hasil: Menarik data Juli 2025 (1-31 Juli)
```

## 🌐 API Endpoint

### POST `/api/attendance/sync-current-month`

**Description:** Sync data absensi untuk bulan saat ini

**Request:**
```http
POST /api/attendance/sync-current-month
Content-Type: application/json
```

**Response Success:**
```json
{
    "success": true,
    "message": "Monthly sync berhasil untuk July 2025",
    "data": {
        "month": "July",
        "year": 2025,
        "month_number": 7,
        "monthly_stats": {
            "total_from_machine": 1500,
            "month_filtered": 450,
            "processed_to_logs": 450,
            "processed_to_attendances": 420,
            "start_date": "2025-07-01",
            "end_date": "2025-07-31"
        },
        "auto_sync_result": {
            "total_users": 25,
            "synced_count": 23
        },
        "employee_id_sync": {
            "updated_count": 15
        }
    }
}
```

**Response Error:**
```json
{
    "success": false,
    "message": "Tidak dapat terhubung ke mesin: Connection timeout"
}
```

## 📁 File Structure

```
backend_hci/
├── monthly_sync.php              # Script sync bulanan utama
├── test_monthly_sync.php         # Script test sync bulanan
├── app/
│   ├── Services/
│   │   └── AttendanceMachineService.php  # Method pullCurrentMonthAttendanceData()
│   └── Http/Controllers/
│       └── AttendanceController.php      # Method syncCurrentMonth()
└── routes/
    └── api.php                   # Route /sync-current-month
```

## 🔍 Detail Implementasi

### 1. **AttendanceMachineService::pullCurrentMonthAttendanceData()**

Method ini melakukan:
- Deteksi bulan dan tahun saat ini
- Pull semua data dari mesin
- Filter data untuk bulan saat ini
- Process data ke database

```php
public function pullCurrentMonthAttendanceData(AttendanceMachine $machine = null): array
{
    $currentDate = Carbon::now();
    $currentYear = $currentDate->year;
    $currentMonth = $currentDate->month;
    
    // Pull semua data dari mesin
    // Filter untuk bulan saat ini
    // Process ke database
}
```

### 2. **AttendanceController::syncCurrentMonth()**

Method ini melakukan:
- Test koneksi ke mesin
- Pull data bulanan
- Process logs
- Auto-sync employee
- Sinkronisasi employee_id

### 3. **Filter Logic**

```php
private function filterCurrentMonthData(array $attendanceData, int $year, int $month): array
{
    $monthData = [];
    
    foreach ($attendanceData as $data) {
        $logDate = Carbon::parse($data['datetime']);
        
        // Cek apakah data dari bulan dan tahun yang ditargetkan
        if ($logDate->year === $year && $logDate->month === $month) {
            $monthData[] = $data;
        }
    }
    
    return $monthData;
}
```

## 📈 Monitoring & Logging

### Log Messages
```
Monthly Pull: Starting sync for current month
Monthly Pull: Sending SOAP request
Monthly Pull: Reading response from machine...
Monthly Pull: Parsing attendance data...
Monthly Pull: Processing current month data to database...
Monthly Processing: Processing chunk 1/5
Monthly Processing: Progress update
Monthly Pull: Completed successfully
```

### Database Queries
```sql
-- Cek data untuk bulan saat ini
SELECT COUNT(*) FROM attendance_logs 
WHERE YEAR(datetime) = 2025 AND MONTH(datetime) = 7;

SELECT COUNT(*) FROM attendances 
WHERE YEAR(date) = 2025 AND MONTH(date) = 7;
```

## 🚀 Keunggulan Sistem

### ✅ **Otomatis & Cerdas**
- Tidak perlu input tanggal manual
- Selalu mengikuti bulan dan tahun saat ini
- Tidak akan menarik data lama

### ✅ **Efisien**
- Processing dalam chunks
- Memory management yang baik
- Timeout handling

### ✅ **Lengkap**
- Auto-sync employee
- Sinkronisasi data
- Error handling

### ✅ **Monitoring**
- Logging detail
- Progress tracking
- Statistik lengkap

## 🔧 Konfigurasi

### Environment Variables
```env
ATTENDANCE_MACHINE_FULL_TIMEOUT=60
ATTENDANCE_MACHINE_IP=10.10.10.85
```

### Timeout Settings
- **Connection Timeout:** 60 detik
- **Processing Timeout:** 300 detik (5 menit)
- **Memory Limit:** 512MB

## 📊 Statistik Output

Setelah sync berhasil, Anda akan mendapatkan:

1. **Total data dari mesin**
2. **Data yang difilter untuk bulan ini**
3. **Data yang diproses ke logs**
4. **Data yang diproses ke attendances**
5. **Auto-sync employee results**
6. **Employee ID sync results**

## 🎯 Use Cases

### 1. **Sync Rutin Bulanan**
```bash
# Jalankan di awal bulan untuk backup data
php monthly_sync.php
```

### 2. **Sync untuk Export Excel**
```bash
# Sync dulu, lalu export
php monthly_sync.php
curl "http://localhost:8000/api/attendance/export/monthly?year=2025&month=7&format=excel"
```

### 3. **Sync untuk Monitoring**
```bash
# Test sync untuk memastikan data lengkap
php test_monthly_sync.php
```

## ⚠️ Important Notes

1. **Tidak akan menarik data lama** - Sistem hanya menarik data untuk bulan saat ini
2. **Mengikuti kalender dunia** - Selalu menggunakan tahun saat ini
3. **Memory intensive** - Gunakan server dengan memory yang cukup
4. **Timeout handling** - Proses bisa memakan waktu 2-5 menit

## 🔄 Workflow

```
1. Deteksi bulan & tahun saat ini
   ↓
2. Test koneksi ke mesin
   ↓
3. Pull semua data dari mesin
   ↓
4. Filter data untuk bulan saat ini
   ↓
5. Process data ke database
   ↓
6. Auto-sync employee
   ↓
7. Sinkronisasi employee_id
   ↓
8. Return hasil lengkap
```

## 📞 Support

Jika ada masalah dengan sistem sync bulanan:

1. Cek log di `storage/logs/laravel.log`
2. Test koneksi ke mesin
3. Cek memory dan timeout settings
4. Gunakan test script untuk debugging

---

**Sistem ini memastikan Anda selalu mendapatkan data absensi yang akurat dan sesuai dengan periode waktu yang sedang berjalan!** 🎉 