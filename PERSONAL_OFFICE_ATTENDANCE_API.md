# Personal Office Attendance API Documentation

## Overview
API untuk mengambil data absensi kantor pribadi yang digunakan di dashboard frontend. Endpoint ini menyediakan data summary (hadir, izin, sakit) dan detail absensi (tanggal, scan masuk, scan pulang, status) untuk ditampilkan di card "Rekap Absensi".

## Base URL
```
/api/personal
```

## Authentication
Endpoint memerlukan authentication dengan Bearer Token:
```
Authorization: Bearer {token}
```

## Endpoints

### 1. Get Personal Office Attendance

**GET** `/api/personal/office-attendance`

Mengambil data absensi kantor untuk employee tertentu dengan statistik dan detail records.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `employee_id` | integer | ✅ | ID employee dari tabel employees |
| `start_date` | string | ❌ | Tanggal mulai (format: YYYY-MM-DD), default: awal bulan ini |
| `end_date` | string | ❌ | Tanggal akhir (format: YYYY-MM-DD), default: hari ini |

#### Example Request
```bash
curl -X GET "http://127.0.0.1:8000/api/personal/office-attendance?employee_id=8" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "employee": {
      "id": 8,
      "nama_lengkap": "Jelly Jeclien Lukas",
      "jabatan_saat_ini": "HR"
    },
    "attendance_records": [
      {
        "id": 509,
        "date": "2025-07-11",
        "day_name": "Friday",
        "check_in": "09:08",
        "check_out": "10:35",
        "status": "present_late",
        "status_label": "Terlambat",
        "work_hours": "1.43",
        "late_minutes": 0,
        "early_leave_minutes": 0,
        "overtime_hours": "0.00"
      },
      {
        "id": 508,
        "date": "2025-07-10",
        "day_name": "Thursday",
        "check_in": "07:07",
        "check_out": "16:32",
        "status": "present_ontime",
        "status_label": "Hadir Tepat Waktu",
        "work_hours": "9.42",
        "late_minutes": 0,
        "early_leave_minutes": 0,
        "overtime_hours": "0.00"
      }
    ],
    "statistics": {
      "hadir": 3,
      "izin": 0,
      "sakit": 0,
      "total_work_hours": 16.63,
      "total_overtime_hours": 0,
      "total_late_minutes": 0,
      "total_early_leave_minutes": 0
    },
    "date_range": {
      "start_date": "2025-07-01",
      "end_date": "2025-07-28"
    },
    "total_records": 5
  },
  "message": "Data absensi kantor berhasil diambil"
}
```

## Data Structure

### Employee Object
```json
{
  "id": 8,
  "nama_lengkap": "Jelly Jeclien Lukas",
  "jabatan_saat_ini": "HR"
}
```

### Attendance Record Object
```json
{
  "id": 509,
  "date": "2025-07-11",
  "day_name": "Friday",
  "check_in": "09:08",
  "check_out": "10:35",
  "status": "present_late",
  "status_label": "Terlambat",
  "work_hours": "1.43",
  "late_minutes": 0,
  "early_leave_minutes": 0,
  "overtime_hours": "0.00"
}
```

### Statistics Object
```json
{
  "hadir": 3,
  "izin": 0,
  "sakit": 0,
  "total_work_hours": 16.63,
  "total_overtime_hours": 0,
  "total_late_minutes": 0,
  "total_early_leave_minutes": 0
}
```

## Status Values

| Status Code | Status Label | Description |
|-------------|--------------|-------------|
| `present_ontime` | Hadir Tepat Waktu | Hadir sesuai jadwal |
| `present_late` | Terlambat | Hadir tapi terlambat |
| `absent` | Tidak Hadir | Tidak hadir tanpa alasan |
| `permission` | Izin | Izin yang disetujui |
| `sick_leave` | Sakit | Cuti sakit yang disetujui |
| `on_leave` | Cuti | Cuti tahunan yang disetujui |
| `weekend` | Weekend | Hari libur weekend |
| `holiday` | Hari Libur | Hari libur nasional |

## Business Logic

### 1. Attendance Calculation
- **Hadir**: Menghitung dari status `present_ontime` dan `present_late`
- **Izin**: Menghitung dari status `permission` + cuti izin yang disetujui
- **Sakit**: Menghitung dari status `sick_leave` + cuti sakit yang disetujui

### 2. Work Hours Calculation
- **Work Hours**: Total jam kerja dari semua absensi
- **Overtime Hours**: Total jam lembur
- **Late Minutes**: Total menit terlambat
- **Early Leave Minutes**: Total menit pulang cepat

### 3. Date Range
- **Default**: Awal bulan ini sampai hari ini
- **Custom**: Bisa diatur dengan parameter `start_date` dan `end_date`

## Error Responses

### 422 - Validation Error
```json
{
  "success": false,
  "message": "Parameter employee_id diperlukan"
}
```

### 404 - Employee Not Found
```json
{
  "success": false,
  "message": "Employee tidak ditemukan"
}
```

### 500 - Server Error
```json
{
  "success": false,
  "message": "Terjadi kesalahan: {error_message}"
}
```

## Frontend Integration

### 1. Dashboard Card Display
```javascript
// Data untuk ditampilkan di card "Rekap Absensi"
const statistics = response.data.statistics;

// Display summary
console.log(`Total Hadir: ${statistics.hadir} hari`);
console.log(`Izin: ${statistics.izin} hari`);
console.log(`Sakit: ${statistics.sakit} hari`);

// Display detail table
const records = response.data.attendance_records;
// Format: Tanggal | Scan Masuk | Scan Pulang | Status
```

### 2. Table Structure
```html
<!-- Tabel detail absensi -->
<table>
  <thead>
    <tr>
      <th>Tanggal</th>
      <th>Scan Masuk</th>
      <th>Scan Pulang</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <tr v-for="record in attendance_records">
      <td>{{ formatDate(record.date) }}</td>
      <td>{{ record.check_in || '-' }}</td>
      <td>{{ record.check_out || '-' }}</td>
      <td>{{ record.status_label }}</td>
    </tr>
  </tbody>
</table>
```

### 3. Empty State
```javascript
// Jika tidak ada data
if (response.data.total_records === 0) {
  // Tampilkan "Tidak ada data absensi"
}
```

## Testing

### Test Script
Gunakan file `test_endpoint_simple.php` untuk testing:
```bash
php test_endpoint_simple.php
```

### Manual Testing
```bash
# Test dengan cURL
curl -X GET "http://127.0.0.1:8000/api/personal/office-attendance?employee_id=8" \
  -H "Authorization: Bearer {token}"

# Test dengan date range
curl -X GET "http://127.0.0.1:8000/api/personal/office-attendance?employee_id=8&start_date=2025-07-01&end_date=2025-07-31" \
  -H "Authorization: Bearer {token}"
```

## Database Tables Used

### 1. employees
- `id`: Primary key
- `nama_lengkap`: Nama lengkap employee
- `jabatan_saat_ini`: Posisi/jabatan

### 2. attendances
- `id`: Primary key
- `employee_id`: Foreign key ke employees
- `date`: Tanggal absensi
- `check_in`: Waktu scan masuk
- `check_out`: Waktu scan pulang
- `status`: Status absensi
- `work_hours`: Jam kerja
- `late_minutes`: Menit terlambat
- `early_leave_minutes`: Menit pulang cepat
- `overtime_hours`: Jam lembur

### 3. leave_requests
- `id`: Primary key
- `employee_id`: Foreign key ke employees
- `leave_type`: Jenis cuti (permission, sick_leave, annual)
- `start_date`: Tanggal mulai cuti
- `end_date`: Tanggal selesai cuti
- `overall_status`: Status approval (approved, pending, rejected)

## Notes

1. **Authentication Required**: Semua request harus menyertakan Bearer Token
2. **Employee ID Required**: Parameter `employee_id` wajib diisi
3. **Date Format**: Gunakan format YYYY-MM-DD untuk tanggal
4. **Time Format**: Waktu dalam format HH:mm (24-hour)
5. **Statistics**: Dihitung otomatis dari data absensi dan cuti
6. **Empty Data**: Jika tidak ada data, akan mengembalikan array kosong dengan statistics 0 