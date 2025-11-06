# GA Dashboard API Documentation

## Overview
API ini dibuat khusus untuk GA Dashboard yang menampilkan **SEMUA** data absensi renungan pagi dan permohonan cuti tanpa batasan role. Berbeda dengan endpoint lain yang membatasi data berdasarkan hierarki role, endpoint ini memberikan akses penuh ke semua data di database.

## Base URL
```
http://127.0.0.1:8000/api/ga-dashboard
```

## Authentication
Semua endpoint memerlukan authentication dengan Bearer Token:
```
Authorization: Bearer {token}
```

## Endpoints

### 1. Get All Worship Attendance Data
**GET** `/ga-dashboard/worship-attendance`

Mendapatkan semua data absensi renungan pagi untuk semua karyawan tanpa batasan role.

#### Query Parameters
- `date` (optional): Filter berdasarkan tanggal (format: YYYY-MM-DD)
- `all` (optional): Jika `true`, ambil semua data tanpa filter tanggal

#### Example Request
```bash
# Ambil data untuk tanggal tertentu
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/worship-attendance?date=2025-01-15" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"

# Ambil semua data tanpa filter tanggal
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/worship-attendance?all=true" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 123,
      "name": "Natanael Detamor Karokaro",
      "position": "General Affairs",
      "date": "2025-01-15",
      "attendance_time": "07:15",
      "status": "present",
      "testing_mode": false,
      "created_at": "2025-01-15T07:15:00.000000Z",
      "raw_data": { /* raw database data */ }
    }
  ],
  "message": "Data absensi renungan pagi berhasil diambil",
  "total_records": 1
}
```

### 2. Get Worship Statistics
**GET** `/ga-dashboard/worship-statistics`

Mendapatkan statistik absensi renungan pagi untuk tanggal tertentu.

#### Query Parameters
- `date` (optional): Tanggal untuk statistik (default: hari ini)

#### Example Request
```bash
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/worship-statistics?date=2025-01-15" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "total": 50,
    "present": 35,
    "late": 10,
    "absent": 5,
    "date": "2025-01-15"
  }
}
```

### 3. Get All Leave Requests
**GET** `/ga-dashboard/leave-requests`

Mendapatkan semua data permohonan cuti untuk semua karyawan tanpa batasan role.

#### Example Request
```bash
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/leave-requests" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 123,
      "employee": {
        "id": 123,
        "nama_lengkap": "Natanael Detamor Karokaro",
        "jabatan_saat_ini": "General Affairs"
      },
      "leave_type": "annual",
      "start_date": "2025-01-20",
      "end_date": "2025-01-22",
      "total_days": 3,
      "reason": "Liburan keluarga",
      "notes": null,
      "overall_status": "approved",
      "status": "approved",
      "approved_by": 456,
      "approved_at": "2025-01-16T10:30:00.000000Z",
      "created_at": "2025-01-15T14:20:00.000000Z",
      "updated_at": "2025-01-16T10:30:00.000000Z",
      "raw_data": { /* raw database data */ }
    }
  ],
  "message": "Data permohonan cuti berhasil diambil",
  "total_records": 1
}
```

### 4. Get Leave Statistics
**GET** `/ga-dashboard/leave-statistics`

Mendapatkan statistik permohonan cuti secara keseluruhan.

#### Example Request
```bash
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/leave-statistics" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### Example Response
```json
{
  "success": true,
  "data": {
    "total": 100,
    "pending": 15,
    "approved": 70,
    "rejected": 10,
    "expired": 5
  }
}
```

## Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 401 | Unauthorized (token tidak valid) |
| 500 | Internal Server Error |

## Error Response Format
```json
{
  "success": false,
  "message": "Error description"
}
```

## Frontend Integration

### Update Frontend URLs
Ganti endpoint di frontend dari:
```javascript
// Old endpoints (with role restrictions)
const response = await fetch('/api/morning-reflection/attendance');
const leaveResponse = await fetch('/api/leave-requests?all=true');
```

Menjadi:
```javascript
// New GA Dashboard endpoints (no role restrictions)
const response = await fetch('/api/ga-dashboard/worship-attendance');
const leaveResponse = await fetch('/api/ga-dashboard/leave-requests');
```

### Example Frontend Usage
```javascript
// Load worship attendance data
async loadData() {
  try {
    const response = await fetch('/api/ga-dashboard/worship-attendance', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    if (result.success) {
      this.attendanceData = result.data;
      this.calculateStats();
    }
  } catch (error) {
    console.error('Error loading data:', error);
  }
}

// Load leave requests data
async loadLeaveData() {
  try {
    const response = await fetch('/api/ga-dashboard/leave-requests', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    if (result.success) {
      this.leaveData = result.data;
      this.calculateLeaveStats();
    }
  } catch (error) {
    console.error('Error loading leave data:', error);
  }
}
```

## Key Features

1. **No Role Restrictions**: Menampilkan semua data tanpa batasan hierarki role
2. **Complete Data Access**: Akses penuh ke semua record di database
3. **Optimized Queries**: Menggunakan JOIN untuk performa yang lebih baik
4. **Comprehensive Logging**: Logging detail untuk debugging
5. **Error Handling**: Error handling yang robust
6. **Frontend Compatible**: Response format yang kompatibel dengan frontend existing

## Security Considerations

- Endpoint ini hanya boleh diakses oleh user yang terautentikasi
- Tidak ada validasi role tambahan - semua user yang login bisa akses
- Logging dilakukan untuk audit trail
- Data sensitif tidak diekspos dalam response

## Testing

### Test dengan cURL
```bash
# Test worship attendance
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/worship-attendance" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test leave requests
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/leave-requests" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test dengan Postman
1. Set method: GET
2. Set URL: `http://127.0.0.1:8000/api/ga-dashboard/worship-attendance`
3. Add header: `Authorization: Bearer YOUR_TOKEN`
4. Send request

## Migration Notes

Jika Anda menggunakan endpoint lama, update frontend untuk menggunakan endpoint baru:

```javascript
// OLD
const worshipData = await fetch('/api/morning-reflection/attendance');
const leaveData = await fetch('/api/leave-requests?all=true');

// NEW
const worshipData = await fetch('/api/ga-dashboard/worship-attendance');
const leaveData = await fetch('/api/ga-dashboard/leave-requests');
```

Endpoint lama masih tersedia untuk kompatibilitas, tapi endpoint baru ini memberikan akses yang lebih lengkap dan konsisten.