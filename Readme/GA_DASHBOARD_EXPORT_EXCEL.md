# GA Dashboard Export Excel - Dokumentasi Lengkap

## 📋 Overview

Fitur export Excel untuk GA Dashboard memungkinkan export data absensi ibadah dan data cuti dalam format Excel (.xlsx) dengan styling yang menarik dan informatif.

## 🎯 Fitur Utama

### 1. **Export Absensi Ibadah**
- Format tabel tahunan dengan checkbox untuk setiap hari kerja
- Senin, Rabu, Jumat: Data dari `morning_reflection_attendance`
- Selasa, Kamis: Data dari `attendances` (tap kartu)
- Integrasi dengan data cuti
- Warna background berdasarkan status kehadiran

### 2. **Export Data Cuti**
- Format tabel detail dengan semua informasi cuti
- Warna background berdasarkan status cuti
- Informasi lengkap karyawan dan approval

## 🔧 Implementasi Backend

### Endpoints

#### 1. Export Worship Attendance
```http
GET /api/ga-dashboard/export-worship-attendance
```

**Parameters:**
- `year` (optional): Tahun untuk export (default: tahun sekarang)
- `all` (optional): Include data testing jika `true`

**Response:** File Excel (.xlsx)

#### 2. Export Leave Requests
```http
GET /api/ga-dashboard/export-leave-requests
```

**Parameters:**
- `year` (optional): Tahun untuk export (default: tahun sekarang)
- `all` (optional): Include data testing jika `true`

**Response:** File Excel (.xlsx)

### Controller Methods

#### `exportWorshipAttendance()`
- Mengambil semua employee
- Generate tanggal Senin-Jumat untuk tahun tertentu
- Ambil data absensi ibadah (Senin, Rabu, Jumat)
- Ambil data absensi kantor (Selasa, Kamis)
- Ambil data cuti
- Gabungkan semua data
- Buat file Excel dengan styling

#### `exportLeaveRequests()`
- Ambil semua data cuti untuk tahun tertentu
- Buat file Excel dengan informasi lengkap
- Styling berdasarkan status cuti

### Helper Methods

#### `generateWorkDays($year)`
- Generate semua tanggal Senin-Jumat untuk tahun tertentu
- Return array tanggal dalam format Y-m-d

#### `getWorshipAttendanceData($year, $allData)`
- Ambil data dari tabel `morning_reflection_attendance`
- Filter hanya hari Senin, Rabu, Jumat
- Filter testing mode jika diperlukan

#### `getOfficeAttendanceData($year, $allData)`
- Ambil data dari tabel `attendances`
- Filter hanya hari Selasa, Kamis
- Tentukan status berdasarkan waktu check-in:
  - ≤ 08:00: Hadir
  - 08:01-08:05: Terlambat
  - 08:06-08:10: Terlambat
  - > 08:10: Absen

#### `getLeaveData($year, $allData)`
- Ambil data cuti yang sudah disetujui
- Generate data untuk setiap hari dalam rentang cuti
- Hanya untuk hari kerja (Senin-Jumat)

#### `combineAttendanceData()`
- Gabungkan semua data absensi
- Prioritas: Cuti > Absensi Ibadah > Absensi Kantor
- Generate data absent untuk hari yang tidak ada data

## 📊 Format Excel

### 1. Absensi Ibadah

**Header:**
- Kolom A: No
- Kolom B: Nama Karyawan
- Kolom C-ZZ: Tanggal (format: "Sen\n01/01", "Sel\n02/01", dst)

**Data:**
- Checkbox dengan warna background:
  - ✅ Hijau: Hadir
  - ✅ Kuning: Terlambat
  - Kosong Merah: Absen
  - Kode Orange: Cuti (C1, C2, dst)

**Footer:**
- Daftar alasan cuti dengan kode referensi

### 2. Data Cuti

**Header:**
- No, Nama Karyawan, Jenis Cuti, Tanggal Mulai, Tanggal Selesai
- Total Hari, Alasan, Status, Disetujui Oleh, Tanggal Persetujuan, Catatan

**Data:**
- Warna background berdasarkan status:
  - Hijau: Disetujui
  - Merah: Ditolak
  - Abu-abu: Kadaluarsa
  - Kuning: Menunggu

## 🎨 Styling Excel

### Header Style
```php
$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4'] // Biru
    ],
    'font' => [
        'color' => ['rgb' => 'FFFFFF'],
        'bold' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
```

### Checkbox Style
```php
$checkboxStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'font' => [
        'bold' => true,
        'size' => 10
    ]
];
```

## 🔍 Logika Data

### Prioritas Data Absensi
1. **Cuti** (prioritas tertinggi)
   - Jika ada cuti yang disetujui, status = "Cuti"
   - Background orange dengan kode referensi

2. **Absensi Ibadah** (Senin, Rabu, Jumat)
   - Data dari `morning_reflection_attendance`
   - Status: Hadir, Terlambat, Absen

3. **Absensi Kantor** (Selasa, Kamis)
   - Data dari `attendances`
   - Status berdasarkan waktu check-in

4. **Default: Absen**
   - Jika tidak ada data sama sekali
   - Background merah, checkbox kosong

### Logika Waktu Check-in Kantor
```php
if ($checkInTime <= $eightOClock) {
    return 'Hadir';        // ≤ 08:00
} elseif ($checkInTime <= $eightFive) {
    return 'Terlambat';    // 08:01-08:05
} elseif ($checkInTime <= $eightTen) {
    return 'Terlambat';    // 08:06-08:10
} else {
    return 'Absen';        // > 08:10
}
```

## 🧪 Testing

### File Test
`test_export_worship_attendance.php`

**Fitur Test:**
1. Login untuk mendapatkan token
2. Test export worship attendance tahun 2025
3. Test export leave requests tahun 2025
4. Test export tanpa parameter (default tahun sekarang)

**Cara Menjalankan:**
```bash
php test_export_worship_attendance.php
```

### Expected Output
```
=== TEST EXPORT WORSHIP ATTENDANCE ===

1. Login untuk mendapatkan token...
✅ Login berhasil. Token: eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...

2. Test export worship attendance untuk tahun 2025...
HTTP Code: 200
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
✅ Export berhasil! File disimpan sebagai: Data_Absensi_Ibadah_2025_2025-01-27_15-30-45.xlsx
File size: 45,678 bytes

3. Test export leave requests untuk tahun 2025...
HTTP Code: 200
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
✅ Export berhasil! File disimpan sebagai: Data_Cuti_2025_2025-01-27_15-30-46.xlsx
File size: 23,456 bytes

=== SEMUA TEST SELESAI ===
```

## 📁 File Structure

```
backend_hci/
├── app/Http/Controllers/
│   └── GaDashboardController.php          # Controller dengan method export
├── routes/
│   └── api.php                           # Routes untuk export
├── composer.json                         # Dependency PhpSpreadsheet
├── test_export_worship_attendance.php    # File test
└── GA_DASHBOARD_EXPORT_EXCEL.md          # Dokumentasi ini
```

## 🔧 Dependencies

### PhpSpreadsheet
```json
{
    "require": {
        "phpoffice/phpspreadsheet": "^1.29"
    }
}
```

**Classes yang digunakan:**
- `PhpOffice\PhpSpreadsheet\Spreadsheet`
- `PhpOffice\PhpSpreadsheet\Writer\Xlsx`
- `PhpOffice\PhpSpreadsheet\Style\Fill`
- `PhpOffice\PhpSpreadsheet\Style\Alignment`
- `PhpOffice\PhpSpreadsheet\Style\Border`

## 🚀 Cara Penggunaan

### 1. Via Frontend
```javascript
// Export worship attendance
async exportWorshipData() {
    const response = await fetch('/api/ga-dashboard/export-worship-attendance?year=2025', {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Data_Absensi_Ibadah_2025.xlsx';
    link.click();
}

// Export leave requests
async exportLeaveData() {
    const response = await fetch('/api/ga-dashboard/export-leave-requests?year=2025', {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Data_Cuti_2025.xlsx';
    link.click();
}
```

### 2. Via cURL
```bash
# Export worship attendance
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/export-worship-attendance?year=2025" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" \
  --output "Data_Absensi_Ibadah_2025.xlsx"

# Export leave requests
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/export-leave-requests?year=2025" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" \
  --output "Data_Cuti_2025.xlsx"
```

## 📝 Notes

### Performance Considerations
- Export untuk tahun penuh bisa menghasilkan file besar
- Pertimbangkan pagination atau filtering untuk data yang sangat besar
- Cache hasil export untuk penggunaan berulang

### Security
- Endpoint dilindungi dengan authentication
- Validasi parameter input
- Logging untuk audit trail

### Maintenance
- Update dependency PhpSpreadsheet secara berkala
- Monitor memory usage untuk export data besar
- Backup file Excel yang dihasilkan

## 🐛 Troubleshooting

### Common Issues

1. **File Excel kosong**
   - Cek apakah ada data di database
   - Cek parameter year dan all
   - Cek log error

2. **Memory limit exceeded**
   - Kurangi jumlah data yang di-export
   - Gunakan parameter filtering
   - Increase PHP memory limit

3. **File corrupt**
   - Cek dependency PhpSpreadsheet
   - Cek PHP version compatibility
   - Cek disk space

### Debug Steps
1. Cek log Laravel: `storage/logs/laravel.log`
2. Test dengan data minimal
3. Cek response headers
4. Validate Excel file dengan aplikasi lain

## 📈 Future Enhancements

### Planned Features
1. **Export dengan filter tambahan**
   - Filter by department
   - Filter by employee
   - Filter by date range

2. **Format export tambahan**
   - PDF export
   - CSV export
   - JSON export

3. **Advanced styling**
   - Custom themes
   - Conditional formatting
   - Charts dan grafik

4. **Batch processing**
   - Export multiple years
   - Scheduled export
   - Email notification

### Performance Improvements
1. **Caching**
   - Cache hasil export
   - Background processing
   - Queue system

2. **Optimization**
   - Lazy loading
   - Database indexing
   - Memory optimization 