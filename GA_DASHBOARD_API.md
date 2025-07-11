# GA Dashboard API Documentation

## Overview
Dokumentasi ini menjelaskan endpoint API yang tersedia untuk GA (General Affairs) Dashboard untuk mengakses data cuti dan absensi semua karyawan.

## Base URL
```
http://localhost:8000/api
```

## Authentication
Semua endpoint GA Dashboard memerlukan autentikasi menggunakan Bearer Token dan role GA/Admin.

```http
Authorization: Bearer {your_token_here}
```

## Endpoints

### 1. Get All Leave Requests
**Endpoint**: `GET /ga/dashboard/leave-requests`

**Deskripsi**: Mengambil semua data cuti dari seluruh karyawan dengan filtering dan pagination.

**Authorization**: Bearer Token (Role: General Affairs, Admin)

**Query Parameters**:
```
?status=pending          // Filter berdasarkan status (pending, approved, rejected)
?leave_type=annual       // Filter berdasarkan jenis cuti
?employee_id=1           // Filter berdasarkan employee ID
?start_date=2024-01-01   // Filter tanggal mulai cuti
?end_date=2024-01-31     // Filter tanggal akhir cuti
?per_page=15             // Jumlah data per halaman (default: 15)
?page=1                  // Halaman yang diminta
```

**Response Success (200)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee": {
        "id": 1,
        "nama_lengkap": "John Doe"
      },
      "leave_type": "annual",
      "start_date": "2024-01-15",
      "end_date": "2024-01-17",
      "duration": 3,
      "reason": "Liburan keluarga",
      "overall_status": "approved",
      "created_at": "2024-01-10T08:00:00.000000Z",
      "updated_at": "2024-01-12T10:30:00.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 67
  },
  "message": "Data cuti berhasil diambil"
}
```

**Response Error (401)**:
```json
{
  "success": false,
  "message": "User tidak terautentikasi"
}
```

**Response Error (403)**:
```json
{
  "success": false,
  "message": "Unauthorized. Hanya GA/Admin yang dapat mengakses endpoint ini."
}
```

### 2. Get All Attendances
**Endpoint**: `GET /ga/dashboard/attendances`

**Deskripsi**: Mengambil semua data absensi karyawan dengan filtering dan pagination.

**Authorization**: Bearer Token (Role: General Affairs, Admin)

**Query Parameters**:
```
?date=2024-01-15         // Filter berdasarkan tanggal (default: hari ini)
?employee_id=1           // Filter berdasarkan employee ID
?status=present          // Filter berdasarkan status absensi
?per_page=15             // Jumlah data per halaman (default: 15)
?page=1                  // Halaman yang diminta
```

**Response Success (200)**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 1,
      "date": "2024-01-15",
      "check_in": "08:00:00",
      "check_out": "17:00:00",
      "status": "present",
      "work_hours": 8.0,
      "employee": {
        "id": 1,
        "full_name": "John Doe"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  },
  "message": "Data absensi berhasil diambil"
}
```

### 3. Get Leave Statistics
**Endpoint**: `GET /ga/dashboard/leave-statistics`

**Deskripsi**: Mengambil statistik cuti untuk dashboard GA.

**Authorization**: Bearer Token (Role: General Affairs, Admin)

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "status_summary": {
      "pending": 5,
      "approved": 23,
      "rejected": 2,
      "this_month": 15,
      "this_year": 89
    },
    "type_summary": {
      "annual": 45,
      "sick": 20,
      "maternity": 3,
      "paternity": 2,
      "marriage": 1,
      "emergency": 8
    }
  },
  "message": "Statistik cuti berhasil diambil"
}
```

### 4. Get Attendance Statistics
**Endpoint**: `GET /ga/dashboard/attendance-statistics`

**Deskripsi**: Mengambil statistik absensi renungan pagi untuk dashboard GA.

**Authorization**: Bearer Token (Role: General Affairs, Admin)

**Response Success (200)**:
```json
{
  "success": true,
  "data": {
    "morning_reflection_attendance": {
      "today_present": 25,
      "today_late": 3,
      "today_absent": 2,
      "monthly_total": 450
    },
    "date": "2024-01-15"
  },
  "message": "Statistik absensi renungan pagi berhasil diambil"
}
```

### 5. Get All Leaves (Alternative Endpoint)
**Endpoint**: `GET /ga/leaves`

**Deskripsi**: Endpoint alternatif untuk mengambil data cuti dengan otorisasi berdasarkan hierarki role.

**Authorization**: Bearer Token

**Response**: Sama seperti endpoint `/ga/dashboard/leave-requests` tetapi dengan filtering berdasarkan role hierarchy.

## Error Handling

### Common Error Responses

**500 Internal Server Error**:
```json
{
  "success": false,
  "message": "Terjadi kesalahan saat mengambil data"
}
```

**422 Validation Error**:
```json
{
  "success": false,
  "errors": {
    "field_name": ["Error message"]
  }
}
```

## Usage Examples

### JavaScript/Fetch Example
```javascript
// Get all leave requests with filters
const response = await fetch('/api/ga/dashboard/leave-requests?status=pending&per_page=20', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

const data = await response.json();
console.log(data);
```

### cURL Example
```bash
# Get all leave requests
curl -X GET "http://localhost:8000/api/ga/dashboard/leave-requests" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"

# Get attendances for specific date
curl -X GET "http://localhost:8000/api/ga/dashboard/attendances?date=2024-01-15" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

## Security & Access Control

- Semua endpoint memerlukan autentikasi dengan Bearer Token
- Hanya user dengan role "General Affairs" atau "Admin" yang dapat mengakses endpoint dashboard
- Data filtering dan pagination diterapkan untuk optimasi performa
- Logging error untuk monitoring dan debugging

## Frontend Integration Tips

1. **Pagination**: Gunakan parameter `page` dan `per_page` untuk implementasi pagination
2. **Filtering**: Kombinasikan multiple filter parameters untuk pencarian yang lebih spesifik
3. **Error Handling**: Selalu handle response error dengan proper user feedback
4. **Loading States**: Implementasikan loading indicator saat melakukan API calls
5. **Token Management**: Pastikan token disimpan dengan aman dan handle token expiration

## Rate Limiting

Endpoint ini tidak memiliki rate limiting khusus, tetapi mengikuti rate limiting global aplikasi.

## Changelog

### Version 1.1.0 (2024-01-15)
- **FIXED**: JOIN query in getAllLeaveRequests endpoint
- **FIXED**: employee.nama_lengkap field mapping (was using incorrect 'full_name')
- **ADDED**: Data validation before sending response
- **ADDED**: Fallback values for null/missing employee data
- **ADDED**: Logging for data integrity issues
- **IMPROVED**: Error handling and data consistency

### Version 1.0.0 (2024-01-15)
- Initial implementation of GA Dashboard API
- Added getAllLeaveRequests endpoint
- Added getAllAttendances endpoint
- Added proper authentication and authorization
- Added filtering and pagination support