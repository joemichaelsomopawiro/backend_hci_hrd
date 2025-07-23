# Summary Sistem Upload Excel Attendance

## 🎯 Tujuan Sistem

Menggantikan proses autosync dari mesin absensi dengan input manual melalui file Excel. User tinggal upload file Excel yang didapat dari software kantor, kemudian sistem otomatis akan mengupdate tabel attendance yang sudah ada.

## 📋 Mapping Data Excel ke Database

| Kolom Excel | Field Database | Keterangan |
|-------------|----------------|------------|
| **No. ID** | `card_number` | Nomor ID karyawan dari mesin |
| **Nama** | `user_name` | Nama lengkap karyawan |
| **Tanggal** | `date` | Tanggal absensi (format Y-m-d) |
| **Scan Masuk** | `check_in` | Waktu scan masuk (format H:i:s) |
| **Scan Pulang** | `check_out` | Waktu scan pulang (format H:i:s) |
| **Absent** | `status` | Status: absent/present_late/present_ontime |
| **Jml Jam Kerja** | `work_hours` | Jam kerja dalam format desimal |
| **Jml Kehadiran** | - | **DIABAIKAN** |

## 🔍 Logic Mapping Employee

Sistem akan mencari `employee_id` berdasarkan nama di kolom "Nama":

1. **Exact Match**: Nama lengkap sama persis
2. **Partial Match**: Nama mengandung string yang sama
3. **Case Insensitive**: Nama sama tanpa memperhatikan huruf besar/kecil

## 📊 Logic Status Attendance

Status ditentukan berdasarkan kolom "Absent":

- **`absent`**: Jika kolom Absent = `True`
- **`present_late`**: Jika Absent = `False` dan scan masuk setelah jam kerja (default 07:30)
- **`present_ontime`**: Jika Absent = `False` dan scan masuk sebelum/sesuai jam kerja

## 🛠️ File yang Dibuat

### 1. Controller
- `app/Http/Controllers/AttendanceExcelUploadController.php`
  - Upload Excel
  - Preview data
  - Download template
  - Validation rules

### 2. Service
- `app/Services/AttendanceExcelUploadService.php`
  - Proses file Excel
  - Mapping data
  - Validasi format
  - Simpan ke database

### 3. Routes
- `routes/api.php` (ditambahkan)
  - `/api/attendance/upload-excel` (POST)
  - `/api/attendance/upload-excel/preview` (POST)
  - `/api/attendance/upload-excel/template` (GET)
  - `/api/attendance/upload-excel/download-template` (GET)
  - `/api/attendance/upload-excel/validation-rules` (GET)

### 4. Dokumentasi
- `ATTENDANCE_EXCEL_UPLOAD_SYSTEM.md` - Dokumentasi lengkap
- `EXCEL_UPLOAD_QUICKSTART.md` - Panduan cepat
- `EXCEL_UPLOAD_SYSTEM_SUMMARY.md` - Summary ini

### 5. Test Files
- `test_excel_upload_system.php` - Test lengkap
- `test_excel_upload_simple.php` - Test sederhana

## 🚀 Cara Penggunaan

### 1. Download Template
```bash
curl -X GET http://localhost/backend_hci/public/api/attendance/upload-excel/template
```

### 2. Upload Excel
```bash
curl -X POST http://localhost/backend_hci/public/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx"
```

### 3. Preview Data
```bash
curl -X POST http://localhost/backend_hci/public/api/attendance/upload-excel/preview \
  -F "excel_file=@attendance_data.xlsx"
```

## 📝 Format Excel yang Didukung

### Header Wajib
```
No. ID | Nama | Tanggal | Scan Masuk | Scan Pulang | Absent | Jml Jam Kerja | Jml Kehadiran
```

### Format Data
- **Tanggal**: `14-Jul-25`, `14/07/2025`, `2025-07-14`
- **Waktu**: `07:05`, `07:05:00`
- **Absent**: `True` atau `False`
- **Jam Kerja**: `09:34` (akan dikonversi ke 9.57 jam)

## 🔧 Fitur Utama

### 1. Upload & Processing
- ✅ Upload file Excel (.xlsx, .xls)
- ✅ Validasi format header dan data
- ✅ Mapping otomatis ke database
- ✅ Overwrite protection
- ✅ Date range filter

### 2. Preview & Validation
- ✅ Preview data sebelum upload
- ✅ Validasi format tanggal/waktu
- ✅ Error reporting per baris
- ✅ Sample data display

### 3. Template System
- ✅ Generate template Excel
- ✅ Download template file
- ✅ Sample data di template

### 4. Employee Mapping
- ✅ Auto-mapping berdasarkan nama
- ✅ Multiple matching strategies
- ✅ Fallback handling

## 🛡️ Error Handling

### Common Errors
1. **Header Invalid**: Format header tidak sesuai
2. **Date Format**: Format tanggal tidak dikenali
3. **Time Format**: Format waktu tidak valid
4. **Employee Not Found**: Nama tidak ditemukan di database
5. **Duplicate Data**: Data sudah ada (jika tidak overwrite)

### Logging
- Semua proses di-log di `storage/logs/laravel.log`
- Detail error per baris
- Statistik upload (total, created, updated, skipped)

## 🔄 Integrasi dengan Sistem Existing

### 1. Attendance Model
- Menggunakan model `Attendance` yang sudah ada
- Field: `card_number`, `user_name`, `employee_id`, `date`, `check_in`, `check_out`, `status`, `work_hours`

### 2. Employee Model
- Mapping dengan model `Employee` berdasarkan `nama_lengkap`
- Auto-set `employee_id` jika ditemukan

### 3. Existing Logic
- Menggunakan logic status yang sudah ada
- Integrasi dengan sistem cuti (jika ada)
- Perhitungan jam kerja otomatis

## 📊 Response Format

### Success Response
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
        "errors": [],
        "sample_data": [...]
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Format header tidak valid",
    "errors": {
        "header": ["Kolom yang diperlukan tidak ditemukan"]
    }
}
```

## 🎯 Workflow Penggunaan

1. **Admin** export data dari software kantor ke Excel
2. **Admin** upload file Excel ke sistem
3. **Sistem** validasi format dan mapping data
4. **Sistem** cari employee berdasarkan nama
5. **Sistem** simpan/update data ke tabel attendance
6. **Admin** verifikasi data di dashboard

## 🔮 Future Enhancements

### 1. UI Integration
- Web interface untuk upload
- Drag & drop file upload
- Real-time progress indicator

### 2. Advanced Features
- Batch upload multiple files
- Background job processing
- Data validation rules customization

### 3. Reporting
- Upload history report
- Error summary report
- Data comparison report

## ✅ Status Implementasi

- ✅ Controller & Service
- ✅ Routes & API endpoints
- ✅ Data mapping & validation
- ✅ Employee mapping logic
- ✅ Error handling & logging
- ✅ Template generation
- ✅ Preview functionality
- ✅ Documentation
- ✅ Test files

## 🚀 Ready to Use

Sistem sudah siap digunakan! User tinggal:
1. Export data dari software kantor ke Excel
2. Upload file Excel ke sistem
3. Data otomatis masuk ke tabel attendance

Sistem akan menangani semua mapping, validasi, dan error handling secara otomatis. 