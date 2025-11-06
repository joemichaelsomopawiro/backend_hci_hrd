# Quick Start Guide - Sistem Absensi Solution X304

## Setup Awal

### 1. Jalankan Migration
```bash
php artisan migrate
```

### 2. Setup Data Mesin
```bash
php artisan db:seed --class=AttendanceMachineSeeder
```

### 3. Test Koneksi ke Mesin
```bash
# Test koneksi sederhana
curl "http://localhost:8000/api/attendance/machine/status"

# Atau via artisan command
php artisan attendance:sync --force
```

## Setup Employee

### Update Nomor Kartu Employee
```sql
-- Contoh update nomor kartu untuk beberapa employee
UPDATE employees SET NumCard = '1234567890' WHERE id = 1;
UPDATE employees SET NumCard = '1234567891' WHERE id = 2;
UPDATE employees SET NumCard = '1234567892' WHERE id = 3;
```

## Testing API

### 1. Dashboard Attendance
```bash
curl "http://localhost:8000/api/attendance/dashboard"
```

### 2. Sync Data dari Mesin
```bash
curl -X POST "http://localhost:8000/api/attendance/sync"
```

### 3. Proses Logs Manual
```bash
curl -X POST "http://localhost:8000/api/attendance/process"
```

### 4. List Attendance Hari Ini
```bash
curl "http://localhost:8000/api/attendance/list"
```

### 5. Summary Attendance
```bash
curl "http://localhost:8000/api/attendance/summary?start_date=2025-01-26&end_date=2025-01-26"
```

## Command Line

### Sync Manual
```bash
# Sync saja (tanpa process)
php artisan attendance:sync

# Sync + process sekaligus
php artisan attendance:sync --process

# Force sync meskipun koneksi error
php artisan attendance:sync --force --process
```

### Lihat Scheduled Tasks
```bash
php artisan schedule:list
```

### Test Scheduled Tasks
```bash
php artisan schedule:run
```

## Development Testing

### 1. Test dengan Data Dummy
Buat data dummy di `attendance_logs` untuk testing:

```sql
INSERT INTO attendance_logs (
    attendance_machine_id, 
    employee_id, 
    user_pin, 
    datetime, 
    verified_method, 
    verified_code, 
    status_code, 
    is_processed, 
    created_at, 
    updated_at
) VALUES (
    1, -- ID mesin (dari attendance_machines)
    1, -- ID employee
    '1234567890', -- NIK atau NumCard employee
    '2025-01-26 08:15:00', -- Waktu check-in
    'card', 
    4, 
    'check_in', 
    false,
    NOW(),
    NOW()
), (
    1,
    1,
    '1234567890',
    '2025-01-26 17:30:00', -- Waktu check-out
    'card',
    4,
    'check_out',
    false,
    NOW(),
    NOW()
);
```

### 2. Proses Data Dummy
```bash
php artisan attendance:sync --process
```

### 3. Cek Hasil
```bash
curl "http://localhost:8000/api/attendance/list?date=2025-01-26"
```

## Production Setup

### 1. Setup Cron Job
Tambahkan ke crontab:
```bash
* * * * * cd /path/to/backend_hci && php artisan schedule:run >> /dev/null 2>&1
```

### 2. Setup Log Rotation
```bash
# Tambahkan ke logrotate.d
/path/to/backend_hci/storage/logs/attendance-*.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 0644 www-data www-data
}
```

### 3. Monitor Logs
```bash
# Monitor attendance sync
tail -f storage/logs/attendance-sync.log

# Monitor Laravel logs
tail -f storage/logs/laravel.log | grep attendance
```

## Troubleshooting

### Error: "Mesin tidak ditemukan"
```bash
# Cek data mesin
php artisan tinker
>>> App\Models\AttendanceMachine::all()
```

### Error: "Koneksi gagal"
```bash
# Test ping ke mesin
ping 10.10.10.85

# Test port
telnet 10.10.10.85 80
```

### Error: "Employee tidak ditemukan"
```bash
# Cek mapping employee
php artisan tinker
>>> App\Models\Employee::whereNotNull('NumCard')->get(['id', 'nik', 'NumCard'])
```

### Data tidak diproses
```bash
# Cek logs yang belum diproses
curl "http://localhost:8000/api/attendance/logs?processed=false"

# Force process
php artisan attendance:sync --process --force
```

## Endpoints Penting

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/attendance/dashboard` | Dashboard utama |
| GET | `/api/attendance/list` | List attendance |
| GET | `/api/attendance/summary` | Summary periode |
| POST | `/api/attendance/sync` | Sync dari mesin |
| POST | `/api/attendance/process` | Proses logs |
| GET | `/api/attendance/machine/status` | Status mesin |

## Monitoring Production

### Key Metrics
- **Attendance Rate**: Persentase kehadiran harian
- **Sync Success Rate**: Persentase sync berhasil
- **Processing Lag**: Delay antara tap dan record terproses

### Alerts
- Koneksi mesin down > 1 jam
- Sync gagal > 3 kali berturut-turut
- Data tidak diproses > 30 menit

---
**Next Steps**: Integrasi dengan frontend, setup notification, laporan payroll 