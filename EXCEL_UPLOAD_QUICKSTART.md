# Quickstart Guide - Sistem Upload Excel Attendance

## 🚀 Langkah Cepat untuk Menggunakan Sistem

### 1. Download Template Excel
```bash
# Download template Excel
curl -X GET http://api.hopechannel.id/api/attendance/upload-excel/template

# Download file template langsung
curl -X GET http://api.hopechannel.id/api/attendance/upload-excel/download-template -o attendance_template.xlsx
```

### 2. Isi Data Excel
Gunakan template yang sudah didownload dan isi dengan format:

| No. ID | Nama | Tanggal | Scan Masuk | Scan Pulang | Absent | Jml Jam Kerja | Jml Kehadiran |
|--------|------|---------|------------|-------------|--------|---------------|---------------|
| 1 | E.H Michael Palar | 14-Jul-25 | | | True | | |
| 2 | Budi Dharmadi | 14-Jul-25 | 07:05 | 16:40 | False | 09:34 | 09:34 |
| 20111201 | Steven Albert Reynold | 14-Jul-25 | 09:10 | 16:25 | False | 07:15 | 07:15 |

**Mapping ke Database:**
- No. ID → `card_number`
- Nama → `user_name` (juga untuk mencari `employee_id`)
- Scan Masuk → `check_in`
- Scan Pulang → `check_out`
- Absent → `status`
- Jml Jam Kerja → `work_hours`
- Jml Kehadiran → **diabaikan**

### 3. Preview Data (Opsional)
```bash
# Preview data sebelum upload
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel/preview \
  -F "excel_file=@attendance_data.xlsx"
```

### 4. Upload Excel
```bash
# Upload sederhana
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx"

# Upload dengan overwrite data yang sudah ada
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx" \
  -F "overwrite_existing=true"

# Upload dengan filter tanggal tertentu
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx" \
  -F "date_range_start=2025-07-01" \
  -F "date_range_end=2025-07-31"
```

### 5. Verifikasi Data
```bash
# Cek data yang sudah diupload
curl -X GET "http://api.hopechannel.id/api/attendance/list?date=2025-07-14&per_page=10"
```

## 📋 Format Data yang Didukung

### Format Tanggal
- ✅ `14-Jul-25` (DD-MMM-YY)
- ✅ `14/07/2025` (DD/MM/YYYY)
- ✅ `2025-07-14` (YYYY-MM-DD)
- ✅ `14-07-2025` (DD-MM-YYYY)

### Format Waktu
- ✅ `07:05` (HH:MM)
- ✅ `07:05:00` (HH:MM:SS)

### Status Absent
- ✅ `True` (Absen)
- ✅ `False` (Hadir)

## 🔧 Troubleshooting

### Error: "Format header tidak valid"
**Solusi:** Pastikan header Excel sesuai dengan format yang diperlukan:
```
No. ID | Nama | Tanggal | Scan Masuk | Scan Pulang | Absent | Jml Jam Kerja | Jml Kehadiran
```

### Error: "Format tanggal tidak valid"
**Solusi:** Gunakan format tanggal yang didukung:
- `14-Jul-25` ✅
- `14/07/2025` ✅
- `2025-07-14` ✅

### Error: "Employee tidak ditemukan"
**Solusi:** 
1. Pastikan nama di Excel sama dengan nama di database
2. Cek apakah NIK atau NumCard sesuai
3. Gunakan preview untuk cek mapping

### Error: "Data attendance sudah ada"
**Solusi:** Gunakan parameter `overwrite_existing=true` untuk update data yang sudah ada

## 📊 Contoh Response Sukses

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
        "sample_data": [
            {
                "user_pin": "1",
                "user_name": "E.H Michael Palar",
                "date": "2025-07-14",
                "status": "absent",
                "work_hours": null
            }
        ]
    }
}
```

## 🎯 Tips Penggunaan

### 1. Gunakan Preview Terlebih Dahulu
```bash
# Selalu preview sebelum upload untuk memastikan format benar
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel/preview \
  -F "excel_file=@attendance_data.xlsx"
```

### 2. Upload dengan Date Range untuk File Besar
```bash
# Upload hanya data bulan Juli 2025
curl -X POST http://api.hopechannel.id/api/attendance/upload-excel \
  -F "excel_file=@attendance_data.xlsx" \
  -F "date_range_start=2025-07-01" \
  -F "date_range_end=2025-07-31"
```

### 3. Backup Data Sebelum Overwrite
```bash
# Export data existing sebelum overwrite
curl -X GET "http://api.hopechannel.id/api/attendance/export/daily?date=2025-07-14&format=excel"
```

### 4. Monitor Log untuk Debug
```bash
# Cek log Laravel untuk detail error
tail -f storage/logs/laravel.log
```

## 🔗 API Endpoints Lengkap

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `/api/attendance/upload-excel` | POST | Upload file Excel |
| `/api/attendance/upload-excel/preview` | POST | Preview data Excel |
| `/api/attendance/upload-excel/template` | GET | Generate template |
| `/api/attendance/upload-excel/download-template` | GET | Download template file |
| `/api/attendance/upload-excel/validation-rules` | GET | Get validation rules |

## 📞 Support

Jika mengalami masalah:
1. Cek log di `storage/logs/laravel.log`
2. Gunakan preview untuk validasi format
3. Pastikan format data sesuai dengan yang didukung
4. Hubungi admin sistem untuk bantuan lebih lanjut

## 🚀 Next Steps

Setelah berhasil upload:
1. Verifikasi data di dashboard attendance
2. Cek perhitungan jam kerja otomatis
3. Integrasi dengan sistem cuti (jika ada)
4. Generate laporan attendance 