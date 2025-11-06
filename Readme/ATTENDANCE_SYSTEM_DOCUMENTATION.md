# Dokumentasi Sistem Absensi Solution X304

## Deskripsi Sistem

Sistem absensi terintegrasi dengan mesin Solution X304 yang menggunakan SOAP Web Service untuk komunikasi. Sistem ini mendukung:

- **Kartu RFID**: Nomor kartu 10 digit
- **Sidik Jari (Fingerprint)**: Sensor sidik jari berkualitas tinggi
- **Logika Tap**: Tap pertama = check-in, tap terakhir = check-out
- **Integrasi Cuti**: Otomatis mendeteksi status cuti dari sistem leave request
- **Perhitungan Otomatis**: Jam kerja, lembur, keterlambatan

## Spesifikasi Mesin Solution X304

- **Model**: Solution X304
- **IP Address**: 10.10.10.85
- **Port**: 80 (SOAP Web Service)
- **Kapasitas**: 6.000 user, 100.000 transaksi
- **Fitur**: TFT LCD 3", USB Port, Access Control, Web Server
- **Jam Kerja**: 07:30 - 16:30 WIB

## Struktur Database

### 1. Tabel `employees` (Updated)
Ditambah kolom:
```sql
NumCard VARCHAR(10) UNIQUE NULLABLE -- Nomor kartu absensi 10 digit
```

### 2. Tabel `attendance_machines`
```sql
id BIGINT PRIMARY KEY
name VARCHAR(100) -- Nama mesin
ip_address VARCHAR(15) UNIQUE -- IP mesin (10.10.10.85)
port INT DEFAULT 80 -- Port SOAP service
comm_key VARCHAR(10) DEFAULT '0' -- Communication key
device_id VARCHAR(50) -- Device ID
serial_number VARCHAR(50) -- Serial number
status ENUM('active','inactive','maintenance') DEFAULT 'active'
last_sync_at TIMESTAMP -- Waktu sync terakhir
settings JSON -- Pengaturan mesin
description TEXT
created_at, updated_at TIMESTAMP
```

### 3. Tabel `attendance_logs`
```sql
id BIGINT PRIMARY KEY
attendance_machine_id BIGINT FK
employee_id BIGINT FK
user_pin VARCHAR(20) -- PIN dari mesin (NIK atau NumCard)
datetime TIMESTAMP -- Waktu tap dari mesin
verified_method ENUM('card','fingerprint','face','password') DEFAULT 'card'
verified_code INT -- Kode verifikasi dari mesin
status_code ENUM('check_in','check_out','break_out','break_in','overtime_in','overtime_out') DEFAULT 'check_in'
is_processed BOOLEAN DEFAULT false -- Status proses
raw_data TEXT -- Data mentah dari mesin
created_at, updated_at TIMESTAMP
```

### 4. Tabel `attendances`
```sql
id BIGINT PRIMARY KEY
employee_id BIGINT FK
date DATE -- Tanggal absensi
check_in TIME -- Waktu tap pertama
check_out TIME -- Waktu tap terakhir
status ENUM('present_ontime','present_late','absent','on_leave','sick_leave','permission') DEFAULT 'absent'
work_hours DECIMAL(5,2) -- Total jam kerja
overtime_hours DECIMAL(5,2) DEFAULT 0 -- Jam lembur
late_minutes INT DEFAULT 0 -- Menit terlambat
early_leave_minutes INT DEFAULT 0 -- Menit pulang cepat
total_taps INT DEFAULT 0 -- Total tap dalam sehari
notes TEXT
created_at, updated_at TIMESTAMP
UNIQUE(employee_id, date)
```

### 5. Tabel `attendance_sync_logs`
```sql
id BIGINT PRIMARY KEY
attendance_machine_id BIGINT FK
operation ENUM('pull_data','push_user','delete_user','clear_data','sync_time','restart_machine','test_connection')
status ENUM('success','failed','partial') DEFAULT 'failed'
message TEXT -- Pesan hasil operasi
details JSON -- Detail operasi
records_processed INT DEFAULT 0 -- Jumlah record diproses
started_at TIMESTAMP -- Waktu mulai
completed_at TIMESTAMP -- Waktu selesai
duration DECIMAL(8,3) -- Durasi (detik)
created_at, updated_at TIMESTAMP
```

## Logika Sistem

### 1. Flow Data
1. **Employee tap** di mesin → Data masuk ke `attendance_logs`
2. **Processing service** memproses logs → Dibuat record di `attendances`
3. **Attendance record** menghitung jam kerja, status, lembur otomatis

### 2. Logika Check-in/Check-out
- **Tap pertama hari itu** = Check-in
- **Tap terakhir hari itu** = Check-out (jika beda minimal 1 menit dari check-in)
- **Multiple tap** = Semua direcord di logs, tapi yang dipakai hanya pertama dan terakhir

### 3. Status Kehadiran
- **present_ontime**: Hadir sebelum/tepat 07:30
- **present_late**: Hadir setelah 07:30
- **absent**: Tidak ada tap sama sekali
- **on_leave**: Ada cuti yang disetujui (annual, emergency, maternity, dll)
- **sick_leave**: Cuti sakit
- **permission**: Izin

### 4. Perhitungan Otomatis
- **Jam Kerja**: (check_out - check_in) - 1 jam (lunch break) jika > 4 jam
- **Lembur**: Jam kerja setelah 16:30
- **Keterlambatan**: Menit setelah 07:30
- **Pulang Cepat**: Menit sebelum 16:30

## API Endpoints

### Dashboard & Summary
```http
GET /api/attendance/dashboard
GET /api/attendance/summary?start_date=2025-01-01&end_date=2025-01-31
```

### Data Attendance
```http
GET /api/attendance/list?date=2025-01-26&employee_id=1&status=present_late
GET /api/attendance/employee/1?start_date=2025-01-01&end_date=2025-01-31
GET /api/attendance/logs?date=2025-01-26&processed=false
```

### Sync & Processing
```http
POST /api/attendance/sync
POST /api/attendance/process
POST /api/attendance/process-today
POST /api/attendance/reprocess
Content-Type: application/json
{
    "date": "2025-01-26"
}
```

### Machine Management
```http
GET /api/attendance/machine/status
PUT /api/attendance/1/recalculate
```

## Setup & Installation

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Run Seeders
```bash
php artisan db:seed --class=AttendanceMachineSeeder
```

### 3. Setup Employee Cards
Update kolom `NumCard` pada tabel employees:
```sql
UPDATE employees SET NumCard = '1234567890' WHERE id = 1;
```

## Cara Penggunaan

### 1. Setup Awal
1. Pastikan mesin Solution X304 sudah terhubung ke jaringan dengan IP 10.10.10.85
2. Upload data karyawan ke mesin (otomatis via API)
3. Distribusikan kartu RFID ke karyawan

### 2. Operasional Harian
1. **Karyawan tap** di mesin saat datang dan pulang
2. **Sistem otomatis sync** data dari mesin (bisa dijadwalkan)
3. **Data diproses** menjadi attendance record dengan status yang sesuai
4. **Dashboard menampilkan** summary kehadiran real-time

### 3. Monitoring
- Cek status koneksi mesin via API
- Monitor logs sync untuk troubleshooting
- Review attendance summary untuk analisis

## Contoh Response API

### Dashboard Response
```json
{
    "success": true,
    "data": {
        "summary": {
            "date": "2025-01-26",
            "total_employees": 50,
            "present_ontime": 35,
            "present_late": 10,
            "absent": 3,
            "on_leave": 2,
            "attendance_rate": 90.0
        },
        "latest_attendances": [...],
        "machine_status": {
            "name": "Solution X304 - Main Office",
            "ip_address": "10.10.10.85",
            "connected": true,
            "last_sync": "2025-01-26T08:30:00Z"
        }
    }
}
```

### Sync Response
```json
{
    "success": true,
    "message": "Sync berhasil",
    "data": {
        "pull_result": {
            "success": true,
            "message": "Berhasil memproses 25 data absensi"
        },
        "process_result": {
            "success": true,
            "processed": 15
        }
    }
}
```

## Troubleshooting

### Koneksi Mesin Gagal
1. Cek IP address dan port mesin
2. Pastikan firewall tidak memblokir port 80
3. Test ping ke IP mesin

### Data Tidak Sync
1. Cek log di `attendance_sync_logs`
2. Pastikan format datetime sesuai
3. Cek mapping employee (NIK/NumCard)

### Status Salah
1. Review logic di model Attendance
2. Cek integrasi dengan leave request
3. Recalculate attendance jika perlu

## Maintenance

### Backup Data
```sql
-- Backup attendance logs (penting!)
SELECT * FROM attendance_logs WHERE datetime >= '2025-01-01';

-- Backup attendance records
SELECT * FROM attendances WHERE date >= '2025-01-01';
```

### Clear Old Data
```sql
-- Hapus logs lama (> 1 tahun)
DELETE FROM attendance_logs WHERE datetime < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Hapus sync logs lama (> 6 bulan)
DELETE FROM attendance_sync_logs WHERE started_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

### Performance Tips
- Index pada `datetime` di attendance_logs
- Partisi tabel berdasarkan bulan jika data besar
- Regular cleanup logs lama
- Monitor query performance

## Integrasi dengan Sistem Lain

### Leave Request Integration
Sistem otomatis cek leave request yang approved untuk menentukan status kehadiran.

### Payroll Integration
Data jam kerja dan lembur bisa digunakan untuk perhitungan gaji.

### Reporting Integration
Export data attendance untuk laporan manajemen.

---

**Developed by**: Backend HCI Team
**Last Updated**: January 26, 2025
**Version**: 1.0 