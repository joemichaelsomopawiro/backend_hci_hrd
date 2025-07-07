# API Export Absensi - Hope Channel Indonesia

Dokumentasi untuk API export data absensi harian dan bulanan.

## Endpoint Export

### 1. Export Absensi Harian
**GET** `/api/attendance/export/daily`

Export data absensi untuk tanggal tertentu dalam format CSV.

#### Parameter Query
- `date` (optional): Tanggal dalam format Y-m-d (default: hari ini)
- `format` (optional): Format export - 'csv' atau 'excel' (default: 'csv')

#### Contoh Request
```bash
# Export absensi hari ini
GET /api/attendance/export/daily

# Export absensi tanggal tertentu
GET /api/attendance/export/daily?date=2024-01-15

# Export dalam format Excel
GET /api/attendance/export/daily?date=2024-01-15&format=excel
```

#### Response Success
```json
{
    "success": true,
    "message": "Export berhasil",
    "data": {
        "filename": "Absensi_15-01-2024_Hope_Channel_Indonesia.csv",
        "download_url": "http://127.0.0.1:8000/storage/exports/Absensi_15-01-2024_Hope_Channel_Indonesia.csv",
        "total_records": 25,
        "date": "2024-01-15"
    }
}
```

#### Format CSV Harian
```
LAPORAN ABSENSI HARIAN
Tanggal: 15 January 2024
Hope Channel Indonesia

No,ID Pegawai,Tanggal Absen,Scan Masuk,Scan Pulang,Jam Kerja
1,1001,2024-01-15,07:30:00,16:30:00,8.00 jam
2,1002,2024-01-15,07:45:00,16:45:00,7.75 jam
3,1003,2024-01-15,08:00:00,16:30:00,7.50 jam
```

---

### 2. Export Absensi Bulanan
**GET** `/api/attendance/export/monthly`

Export data absensi untuk bulan tertentu dalam format CSV dengan format tabel matrix.

#### Parameter Query
- `year` (optional): Tahun (default: tahun ini)
- `month` (optional): Bulan 1-12 (default: bulan ini)
- `format` (optional): Format export - 'csv' atau 'excel' (default: 'csv')

#### Contoh Request
```bash
# Export absensi bulan ini
GET /api/attendance/export/monthly

# Export absensi bulan tertentu
GET /api/attendance/export/monthly?year=2024&month=1

# Export dalam format Excel
GET /api/attendance/export/monthly?year=2024&month=1&format=excel
```

#### Response Success
```json
{
    "success": true,
    "message": "Export berhasil",
    "data": {
        "filename": "Absensi_January_2024_Hope_Channel_Indonesia.csv",
        "download_url": "http://127.0.0.1:8000/storage/exports/Absensi_January_2024_Hope_Channel_Indonesia.csv",
        "total_employees": 30,
        "month": "January 2024",
        "days_in_month": 31
    }
}
```

#### Format CSV Bulanan
```
Absensi January 2024 Hope Channel Indonesia

Nama Karyawan,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31
John Doe,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR
Jane Smith,HADIR,HADIR,IN,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR
Bob Johnson,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR,HADIR,,,HADIR,HADIR,HADIR,HADIR

Keterangan:
HADIR = Hadir (Tap In & Tap Out)
IN = Hanya Tap In
ABSEN = Tidak Hadir
```

## Keterangan Status Absensi

### Export Harian
- **Scan Masuk**: Waktu tap in (format: HH:mm:ss)
- **Scan Pulang**: Waktu tap out (format: HH:mm:ss)
- **Jam Kerja**: Total jam kerja dalam format "X.XX jam"

### Export Bulanan
- **HADIR**: Karyawan hadir dengan tap in dan tap out lengkap
- **IN**: Karyawan hanya tap in (belum tap out)
- **ABSEN**: Karyawan tidak hadir sama sekali
- **Kosong**: Hari libur atau tidak ada data

## File Output

File export akan disimpan di:
- **Path**: `storage/app/public/exports/`
- **URL Download**: `http://127.0.0.1:8000/storage/exports/[filename]`

### Nama File
- **Harian CSV**: `Absensi_[DD-MM-YYYY]_Hope_Channel_Indonesia.csv`
- **Harian Excel**: `Absensi_[DD-MM-YYYY]_Hope_Channel_Indonesia.xls`
- **Bulanan CSV**: `Absensi_[Month]_[Year]_Hope_Channel_Indonesia.csv`
- **Bulanan Excel**: `Absensi_[Month]_[Year]_Hope_Channel_Indonesia.xls`

## Error Handling

### Validation Error (422)
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "date": ["The date field must be a valid date."],
        "year": ["The year must be between 2020 and 2030."],
        "month": ["The month must be between 1 and 12."]
    }
}
```

### Server Error (500)
```json
{
    "success": false,
    "message": "Terjadi kesalahan: [error message]"
}
```

## Implementasi Frontend

### Vue.js Example
```javascript
// Export harian
async exportDailyData(date = null, format = 'csv') {
    try {
        const params = new URLSearchParams();
        if (date) params.append('date', date);
        if (format) params.append('format', format);
        
        const response = await fetch(`/api/attendance/export/daily?${params}`);
        const result = await response.json();
        
        if (result.success) {
            // Download file
            window.open(result.data.download_url, '_blank');
        } else {
            console.error('Export failed:', result.message);
        }
    } catch (error) {
        console.error('Error exporting:', error);
    }
}

// Export bulanan
async exportMonthlyData(year = null, month = null, format = 'csv') {
    try {
        const params = new URLSearchParams();
        if (year) params.append('year', year);
        if (month) params.append('month', month);
        if (format) params.append('format', format);
        
        const response = await fetch(`/api/attendance/export/monthly?${params}`);
        const result = await response.json();
        
        if (result.success) {
            // Download file
            window.open(result.data.download_url, '_blank');
        } else {
            console.error('Export failed:', result.message);
        }
    } catch (error) {
        console.error('Error exporting:', error);
    }
}

// Contoh penggunaan
// Export CSV harian
await exportDailyData('2024-01-15', 'csv');

// Export Excel harian
await exportDailyData('2024-01-15', 'excel');

// Export CSV bulanan
await exportMonthlyData(2024, 1, 'csv');

// Export Excel bulanan
await exportMonthlyData(2024, 1, 'excel');
```

## Catatan Teknis

1. **Data Source**: Menggunakan tabel `attendances` dan `employee_attendance`
2. **Format**: CSV untuk kompatibilitas maksimal
3. **Encoding**: UTF-8
4. **Storage**: File disimpan di storage Laravel dengan symbolic link ke public
5. **Performance**: Query dioptimasi dengan eager loading untuk relasi employee

## Troubleshooting

### File tidak bisa diakses
1. Pastikan symbolic link sudah dibuat: `php artisan storage:link`
2. Cek permission folder `storage/app/public/exports/`
3. Pastikan web server bisa mengakses folder storage

### Data kosong
1. Cek apakah ada data di tabel `attendances`
2. Cek apakah ada data di tabel `employee_attendance` dengan `is_active = true`
3. Cek relasi antara `user_pin` dan `machine_user_id`

### Error 404
1. Pastikan route sudah terdaftar di `routes/api.php`
2. Cek apakah controller `AttendanceExportController` sudah dibuat
3. Pastikan namespace dan import sudah benar 