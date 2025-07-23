# Sistem Upload Excel Attendance

## Deskripsi Sistem

Sistem upload Excel attendance memungkinkan admin untuk mengupload file Excel yang berisi data absensi dari software kantor dan mengupdate tabel attendance yang sudah ada. Sistem ini menggantikan proses autosync dari mesin absensi dengan input manual melalui file Excel.

## Fitur Utama

1. **Upload File Excel**: Upload file Excel dengan format yang sudah ditentukan
2. **Preview Data**: Preview data Excel sebelum upload untuk memastikan format benar
3. **Template Download**: Download template Excel untuk memudahkan input data
4. **Validasi Data**: Validasi format data dan mapping dengan employee yang ada
5. **Overwrite Protection**: Opsi untuk overwrite data yang sudah ada atau skip
6. **Date Range Filter**: Filter data berdasarkan range tanggal tertentu
7. **Error Handling**: Penanganan error yang detail dengan log yang lengkap

## Format Excel yang Didukung

### Header yang Diperlukan
```
No. ID | Nama | Tanggal | Scan Masuk | Scan Pulang | Absent | Jml Jam Kerja | Jml Kehadiran
```

### Format Data
- **No. ID**: Nomor ID karyawan (akan disimpan ke `card_number`)
- **Nama**: Nama lengkap karyawan (akan disimpan ke `user_name` dan digunakan untuk mencari `employee_id`)
- **Tanggal**: Format DD-MMM-YY (contoh: 14-Jul-25)
- **Scan Masuk**: Format HH:MM (contoh: 07:05) - akan disimpan ke `check_in`
- **Scan Pulang**: Format HH:MM (contoh: 16:40) - akan disimpan ke `check_out`
- **Absent**: True/False - akan menentukan `status`
- **Jml Jam Kerja**: Format HH:MM (contoh: 09:34) - akan disimpan ke `work_hours`
- **Jml Kehadiran**: Format HH:MM (contoh: 09:34) - **DIABAIKAN**

### Format Tanggal yang Didukung
- DD-MMM-YY (14-Jul-25)
- DD/MM/YYYY (14/07/2025)
- YYYY-MM-DD (2025-07-14)
- DD-M-YYYY (14-07-2025)
- DD-M-YY (14-Jul-2025)

### Format Waktu yang Didukung
- HH:MM (07:05)
- HH:MM:SS (07:05:00)

## API Endpoints

### 1. Upload Excel
```
POST /api/attendance/upload-excel
```

**Parameters:**
- `excel_file` (required): File Excel (.xlsx, .xls)
- `overwrite_existing` (optional): Boolean, default false
- `date_range_start` (optional): Date format Y-m-d
- `date_range_end` (optional): Date format Y-m-d

**Response:**
```json
{
    "success": true,
    "message": "Berhasil memproses 25 data attendance",
    "data": {
        "total_rows": 30,
        "processed": 25,
        "created": 20,
        "updated": 5,
        "skipped": 3,
        "errors": ["Baris 15: Format tanggal tidak valid: 32-Jul-25"],
        "sample_data": [...]
    }
}
```

### 2. Preview Excel
```
POST /api/attendance/upload-excel/preview
```

**Parameters:**
- `excel_file` (required): File Excel (.xlsx, .xls)

**Response:**
```json
{
    "success": true,
    "data": {
        "total_rows": 30,
        "preview_rows": 10,
        "preview_data": [...],
        "errors": [],
        "header": ["No. ID", "Nama", "Tanggal", ...]
    }
}
```

### 3. Download Template
```
GET /api/attendance/upload-excel/template
```

**Response:**
```json
{
    "success": true,
    "message": "Template berhasil dibuat",
    "data": {
        "template_path": "/path/to/template",
        "download_url": "http://api.hopechannel.id/api/attendance/upload-excel/download-template"
    }
}
```

### 4. Download Template File
```
GET /api/attendance/upload-excel/download-template
```

**Response:** File Excel template

### 5. Get Validation Rules
```
GET /api/attendance/upload-excel/validation-rules
```

**Response:**
```json
{
    "success": true,
    "data": {
        "required_columns": {
            "No. ID": "Nomor ID karyawan (bisa NIK atau ID mesin)",
            "Nama": "Nama lengkap karyawan",
            "Tanggal": "Tanggal absensi (format: DD-MMM-YY)",
            "Scan Masuk": "Waktu scan masuk (format: HH:MM)",
            "Scan Pulang": "Waktu scan pulang (format: HH:MM)",
            "Absent": "Status absen (True/False)",
            "Jml Jam Kerja": "Total jam kerja (format: HH:MM)",
            "Jml Kehadiran": "Total kehadiran (format: HH:MM)"
        },
        "file_requirements": {
            "format": "Excel (.xlsx, .xls)",
            "max_size": "10MB",
            "encoding": "UTF-8"
        },
        "date_formats": {
            "accepted": ["DD-MMM-YY", "DD/MM/YYYY", "YYYY-MM-DD"],
            "example": "14-Jul-25, 14/07/2025, 2025-07-14"
        },
        "time_formats": {
            "accepted": ["HH:MM", "HH:MM:SS"],
            "example": "07:05, 16:40"
        }
    }
}
```

## Logic Pemrosesan Data

### 1. Mapping Employee
Sistem akan mencari employee berdasarkan nama di kolom "Nama":
1. Nama lengkap (exact match)
2. Nama dengan partial match (jika exact match tidak ditemukan)
3. Nama dengan case insensitive (jika masih tidak ditemukan)

**Note:** No. ID akan disimpan ke `card_number` tapi tidak digunakan untuk mencari employee

### 2. Status Attendance
Status attendance ditentukan berdasarkan kolom "Absent":
- **absent**: Jika kolom Absent = True
- **present_late**: Jika Absent = False dan scan masuk setelah jam kerja (default 07:30)
- **present_ontime**: Jika Absent = False dan scan masuk sebelum/sesuai jam kerja

### 3. Jam Kerja
- Menggunakan kolom "Jml Jam Kerja" dari Excel
- Format HH:MM dikonversi ke desimal (contoh: 09:34 = 9.57 jam)
- Kolom "Jml Kehadiran" diabaikan

### 4. Duplicate Handling
- Jika `overwrite_existing = false`: Skip data yang sudah ada
- Jika `overwrite_existing = true`: Update data yang sudah ada

## Struktur Database

### Tabel `attendances` (Updated)
Data dari Excel akan disimpan ke kolom:
- `card_number`: No. ID dari Excel
- `user_name`: Nama dari Excel
- `employee_id`: ID employee yang dicari berdasarkan nama (jika ditemukan)
- `date`: Tanggal dari Excel (format Y-m-d)
- `check_in`: Scan Masuk dari Excel (format H:i:s)
- `check_out`: Scan Pulang dari Excel (format H:i:s)
- `status`: Status berdasarkan kolom Absent (absent/present_late/present_ontime)
- `work_hours`: Jam kerja dari kolom "Jml Jam Kerja" (format desimal)
- `notes`: Catatan import dari Excel

## Contoh Penggunaan

### 1. Upload Excel Sederhana
```bash
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx"
```

### 2. Upload dengan Overwrite
```bash
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx" \
  -F "overwrite_existing=true"
```

### 3. Upload dengan Date Range
```bash
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx" \
  -F "date_range_start=2025-07-01" \
  -F "date_range_end=2025-07-31"
```

### 4. Preview Data
```bash
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel/preview \
  -F "excel_file=@attendance_data.xlsx"
```

## Error Handling

### Common Errors
1. **Format Header Invalid**: Header tidak sesuai dengan yang diperlukan
2. **Format Tanggal Invalid**: Tanggal tidak bisa diparse
3. **Format Waktu Invalid**: Waktu tidak bisa diparse
4. **Data Kosong**: Baris data kosong atau tidak lengkap
5. **Employee Not Found**: Nama/ID tidak ditemukan di database

### Log Files
Semua proses upload akan di-log di:
- `storage/logs/laravel.log`
- Detail error per baris
- Statistik upload (total, created, updated, skipped)

## Keamanan

### File Validation
- Hanya file Excel (.xlsx, .xls) yang diterima
- Maksimal ukuran file 10MB
- Validasi encoding UTF-8

### Data Validation
- Validasi format tanggal dan waktu
- Sanitasi input data
- Transaction rollback jika terjadi error

### Access Control
- Semua endpoint dapat diakses tanpa authentication (sesuai kebutuhan)
- Log semua aktivitas upload
- Rate limiting dapat ditambahkan jika diperlukan

## Integrasi dengan Sistem Existing

### 1. Attendance Model
Sistem menggunakan model `Attendance` yang sudah ada dengan field:
- `user_pin`, `user_name`, `card_number`
- `date`, `check_in`, `check_out`
- `status`, `work_hours`, `notes`

### 2. Employee Model
Mapping dengan model `Employee` berdasarkan:
- `nama_lengkap`
- `nik`
- `NumCard`

### 3. Existing Logic
- Menggunakan logic status yang sudah ada
- Menggunakan perhitungan jam kerja yang sudah ada
- Integrasi dengan sistem cuti yang sudah ada

## Troubleshooting

### 1. File Tidak Bisa Diupload
- Cek format file (.xlsx atau .xls)
- Cek ukuran file (max 10MB)
- Cek permission folder storage

### 2. Data Tidak Masuk Database
- Cek format header Excel
- Cek format tanggal dan waktu
- Cek log error di storage/logs/laravel.log

### 3. Employee Tidak Ditemukan
- Pastikan nama di Excel sama dengan nama di database
- Cek apakah NIK atau NumCard sesuai
- Gunakan preview untuk cek mapping

### 4. Performance Issues
- Gunakan date range untuk file besar
- Split file besar menjadi beberapa file kecil
- Monitor memory usage saat upload file besar

## Future Enhancements

### 1. Batch Processing
- Upload multiple files sekaligus
- Background job untuk file besar
- Progress tracking untuk upload lama

### 2. Advanced Validation
- Custom validation rules
- Business rule validation
- Data consistency check

### 3. Reporting
- Upload history report
- Error summary report
- Data comparison report

### 4. UI Integration
- Web interface untuk upload
- Drag & drop file upload
- Real-time progress indicator 