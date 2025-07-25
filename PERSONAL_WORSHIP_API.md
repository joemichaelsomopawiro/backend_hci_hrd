# Personal Worship Attendance API Documentation

## Overview
API untuk melihat data absensi renungan pagi dan absensi kantor untuk employee tertentu. Endpoint ini memerlukan autentikasi dan dapat digunakan oleh semua role untuk melihat data pribadi mereka.

## Base URL
```
/api/personal
```

## Authentication
Semua endpoint memerlukan authentication dengan Bearer Token:
```
Authorization: Bearer {token}
```

## Endpoints

### 1. Get Personal Worship Attendance

**GET** `/api/personal/worship-attendance`

Mengambil data absensi renungan pagi untuk employee tertentu.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `employee_id` | integer | ✅ | ID employee dari tabel employees |

#### Example Request
```bash
curl -X GET "http://127.0.0.1:8000/api/personal/worship-attendance?employee_id=123" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "employee": {
      "id": 123,
      "nama_lengkap": "John Doe",
      "jabatan_saat_ini": "Staff"
    },
    "worship_attendances": [
      {
        "id": 1,
        "date": "2025-01-15",
        "day_name": "Wednesday",
        "attendance_time": "07:15",
        "status": "present",
        "status_label": "Hadir",
        "attendance_method": "online",
        "attendance_source": "zoom",
        "testing_mode": false,
        "created_at": "2025-01-15 07:15:00"
      }
    ],
    "total_records": 1
  },
  "message": "Data absensi renungan pagi berhasil diambil"
}
```

### 2. Get Combined Attendance

**GET** `/api/personal/combined-attendance`

Mengambil data absensi gabungan (renungan pagi + kantor) untuk employee tertentu.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `employee_id` | integer | ✅ | ID employee dari tabel employees |
| `start_date` | string | ❌ | Tanggal mulai (format: YYYY-MM-DD), default: 30 hari yang lalu |
| `end_date` | string | ❌ | Tanggal akhir (format: YYYY-MM-DD), default: hari ini |

#### Example Request
```bash
curl -X GET "http://127.0.0.1:8000/api/personal/combined-attendance?employee_id=123&start_date=2025-01-01&end_date=2025-01-31" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "employee": {
      "id": 123,
      "nama_lengkap": "John Doe",
      "jabatan_saat_ini": "Staff"
    },
    "attendance_records": [
      {
        "date": "2025-01-15",
        "day_name": "Wednesday",
        "day_number": 3,
        "worship_attendance": {
          "status": "present",
          "status_label": "Hadir",
          "attendance_time": "07:15",
          "attendance_method": "online",
          "attendance_source": "zoom"
        },
        "office_attendance": null,
        "leave_status": null,
        "combined_status": "present"
      },
      {
        "date": "2025-01-14",
        "day_name": "Tuesday",
        "day_number": 2,
        "worship_attendance": null,
        "office_attendance": {
          "status": "present_ontime",
          "status_label": "Hadir Tepat Waktu",
          "check_in": "08:00",
          "check_out": "17:00",
          "work_hours": 8
        },
        "leave_status": null,
        "combined_status": "present_ontime"
      }
    ],
    "statistics": {
      "total_days": 22,
      "worship_present": 5,
      "worship_late": 1,
      "worship_absent": 2,
      "office_present": 8,
      "office_late": 2,
      "office_absent": 1,
      "leave_days": 3,
      "total_work_hours": 64
    },
    "date_range": {
      "start_date": "2025-01-01",
      "end_date": "2025-01-31"
    },
    "total_records": 22
  },
  "message": "Data absensi gabungan berhasil diambil"
}
```

## Status Values

### Worship Attendance Status
- `present`: Hadir
- `late`: Terlambat
- `absent`: Tidak Hadir
- `leave`: Cuti

### Office Attendance Status
- `present_ontime`: Hadir Tepat Waktu
- `present_late`: Terlambat
- `absent`: Tidak Hadir
- `on_leave`: Sedang Cuti
- `sick_leave`: Cuti Sakit

### Leave Types
- `annual`: Cuti Tahunan
- `sick`: Cuti Sakit
- `emergency`: Cuti Darurat
- `maternity`: Cuti Melahirkan
- `paternity`: Cuti Ayah
- `marriage`: Cuti Menikah
- `bereavement`: Cuti Duka

## Business Logic

### Day Mapping
- **Senin (1)**: Renungan pagi
- **Selasa (2)**: Absensi kantor
- **Rabu (3)**: Renungan pagi
- **Kamis (4)**: Absensi kantor
- **Jumat (5)**: Renungan pagi
- **Sabtu (6)**: Weekend (tidak dihitung)
- **Minggu (0)**: Weekend (tidak dihitung)

### Priority Order
1. **Leave Status**: Jika employee sedang cuti, status = leave
2. **Worship Attendance**: Untuk hari Senin, Rabu, Jumat
3. **Office Attendance**: Untuk hari Selasa, Kamis
4. **Default**: Absent jika tidak ada data

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

## Usage Examples

### Frontend Integration
```javascript
// Get worship attendance
const getWorshipAttendance = async (employeeId) => {
  const response = await fetch(`/api/personal/worship-attendance?employee_id=${employeeId}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};

// Get combined attendance
const getCombinedAttendance = async (employeeId, startDate, endDate) => {
  const params = new URLSearchParams({
    employee_id: employeeId,
    start_date: startDate,
    end_date: endDate
  });
  
  const response = await fetch(`/api/personal/combined-attendance?${params}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};
```

## Notes

1. **Authentication Required**: Semua endpoint memerlukan token autentikasi
2. **Employee Validation**: Employee ID harus valid dan ada di database
3. **Date Range**: Untuk combined attendance, default range adalah 30 hari terakhir
4. **Weekend Filtering**: Data weekend (Sabtu-Minggu) tidak dihitung dalam combined attendance
5. **Statistics**: Statistik dihitung otomatis berdasarkan data yang ada
6. **Performance**: Data dibatasi maksimal 30 hari untuk worship attendance 