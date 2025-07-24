# Manual Worship Attendance API Documentation

## 📋 Overview

Fitur input manual absensi renungan memungkinkan GA (General Affairs) untuk input absensi secara manual untuk hari Selasa dan Kamis. Fitur ini melengkapi sistem absensi online yang sudah ada untuk hari Senin, Rabu, dan Jumat.

## 🎯 Fitur Utama

### 1. **Input Manual Absensi**
- GA dapat input absensi manual untuk hari Selasa & Kamis
- Form dengan tanggal, tabel pegawai, dropdown status
- Validasi tanggal (hanya Selasa & Kamis)
- Validasi role (hanya GA/Admin)

### 2. **Logika Hari Ibadah**
- **Senin, Rabu, Jumat**: Online via Zoom (read-only)
- **Selasa, Kamis**: Offline manual input (GA input)
- **Weekend**: Tidak ada ibadah

### 3. **Filter Metode Absensi**
- Filter berdasarkan metode (Online/Manual)
- Menampilkan semua data dengan pembedaan metode

## 🔧 Database Changes

### Migration: `add_attendance_method_to_morning_reflection_attendance_table`

```sql
ALTER TABLE morning_reflection_attendance 
ADD COLUMN attendance_method ENUM('online', 'manual') DEFAULT 'online',
ADD COLUMN attendance_source ENUM('zoom', 'manual_input') DEFAULT 'zoom';
```

### Model Updates

```php
// app/Models/MorningReflectionAttendance.php
protected $fillable = [
    'employee_id',
    'date',
    'status',
    'join_time',
    'testing_mode',
    'attendance_method',  // BARU
    'attendance_source'   // BARU
];

// Scopes baru
public function scopeByAttendanceMethod($query, $method)
public function scopeByAttendanceSource($query, $source)
public function scopeManualInput($query)
public function scopeOnline($query)
```

## 🚀 API Endpoints

### 1. Store Manual Worship Attendance

**POST** `/api/ga-dashboard/manual-worship-attendance`

**Authentication**: Required (GA/Admin only)

**Request Body:**
```json
{
  "tanggal": "2024-01-23",
  "attendance_data": [
    {
      "pegawai_id": 123,
      "status": "present"
    },
    {
      "pegawai_id": 124,
      "status": "late"
    },
    {
      "pegawai_id": 125,
      "status": "absent"
    }
  ]
}
```

**Response Success:**
```json
{
  "success": true,
  "message": "Data absensi manual berhasil disimpan",
  "data": {
    "saved_count": 3,
    "total_data": 3,
    "date": "2024-01-23",
    "errors": []
  }
}
```

**Response Error:**
```json
{
  "success": false,
  "message": "Input manual hanya diperbolehkan untuk hari Selasa dan Kamis"
}
```

### 2. Get Employees for Manual Input

**GET** `/api/ga-dashboard/employees-for-manual-input`

**Authentication**: Required (GA/Admin only)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "pegawai_id": 123,
      "nama_lengkap": "John Doe",
      "jabatan": "Staff"
    }
  ],
  "message": "Daftar pegawai berhasil diambil",
  "total_records": 1
}
```

### 3. Update Existing Data

**POST** `/api/ga-dashboard/update-existing-worship-data`

**Authentication**: Required (GA/Admin only)

**Response:**
```json
{
  "success": true,
  "message": "Data existing berhasil diupdate",
  "data": {
    "updated_count": 50
  }
}
```

### 4. Get Worship Attendance with Method Filter

**GET** `/api/ga-dashboard/worship-attendance?attendance_method=manual`

**Query Parameters:**
- `date` (optional): Filter tanggal
- `attendance_method` (optional): Filter metode (online/manual)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "pegawai_id": 123,
      "nama_lengkap": "John Doe",
      "status": "present",
      "tanggal": "2024-01-23",
      "attendance_method": "manual",
      "attendance_source": "manual_input",
      "created_at": "2024-01-23 08:00:00"
    }
  ],
  "message": "Data absensi renungan berhasil diambil",
  "total_records": 1
}
```

## 🔒 Validasi & Security

### 1. **Role Validation**
- Hanya user dengan role `General Affairs` atau `Admin` yang dapat mengakses
- Middleware: `ValidateGARole`

### 2. **Date Validation**
- Manual input hanya diperbolehkan untuk hari Selasa (2) dan Kamis (4)
- Validasi menggunakan Carbon untuk pengecekan dayOfWeek

### 3. **Data Validation**
- `pegawai_id`: Harus valid dan exists di tabel employees
- `status`: Harus present, late, atau absent
- `tanggal`: Format YYYY-MM-DD, minimal 2020-01-01

### 4. **Duplicate Check**
- Cek apakah sudah ada data untuk tanggal & pegawai tersebut
- Jika ada, update data existing
- Jika tidak ada, buat data baru

## 🛠️ Implementation Details

### 1. **Service Layer**
```php
// app/Services/ManualAttendanceService.php
class ManualAttendanceService
{
    public function storeManualAttendance(array $attendanceData, string $date)
    public function validateWorshipDay(string $date)
    public function validateAttendanceData(array $data)
    public function mapStatusToDatabase(string $status): string
    public function mapStatusToFrontend(string $status): string
    public function updateExistingData()
    public function getWorshipAttendanceWithMethod($date = null, $attendanceMethod = null)
}
```

### 2. **Controller**
```php
// app/Http/Controllers/ManualWorshipAttendanceController.php
class ManualWorshipAttendanceController extends Controller
{
    public function store(ManualWorshipAttendanceRequest $request)
    public function index(Request $request)
    public function updateExistingData()
    public function getEmployeesForManualInput()
}
```

### 3. **Request Validation**
```php
// app/Http/Requests/ManualWorshipAttendanceRequest.php
class ManualWorshipAttendanceRequest extends FormRequest
{
    public function rules(): array
    public function messages(): array
    public function withValidator($validator)
    private function validateWorshipDay($validator)
}
```

## 📊 Status Mapping

| Frontend | Database | Label |
|----------|----------|-------|
| `present` | `Hadir` | Hadir |
| `late` | `Terlambat` | Terlambat |
| `absent` | `Absen` | Tidak Hadir |

## 🔄 Data Flow

### 1. **Input Manual Process**
```
Frontend Form → Request Validation → Service Layer → Database Transaction → Response
```

### 2. **Data Retrieval Process**
```
Request → Controller → Service → Model Query → Response Transformation → JSON Response
```

### 3. **Update Existing Data Process**
```
Command → Service → Database Update → Logging → Summary Report
```

## 🧪 Testing

### 1. **Feature Tests**
```bash
php artisan test tests/Feature/ManualWorshipAttendanceTest.php
```

### 2. **Command Testing**
```bash
# Dry run - lihat data yang akan diupdate
php artisan worship:update-existing-data --dry-run

# Update data existing
php artisan worship:update-existing-data
```

## 📝 Usage Examples

### 1. **Input Manual Attendance**
```bash
curl -X POST "http://127.0.0.1:8000/api/ga-dashboard/manual-worship-attendance" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "tanggal": "2024-01-23",
    "attendance_data": [
      {
        "pegawai_id": 123,
        "status": "present"
      }
    ]
  }'
```

### 2. **Get Manual Attendance Data**
```bash
curl -X GET "http://127.0.0.1:8000/api/ga-dashboard/worship-attendance?attendance_method=manual" \
  -H "Authorization: Bearer {token}"
```

### 3. **Update Existing Data**
```bash
curl -X POST "http://127.0.0.1:8000/api/ga-dashboard/update-existing-worship-data" \
  -H "Authorization: Bearer {token}"
```

## 🚨 Error Handling

### 1. **Validation Errors**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "tanggal": ["Input manual hanya diperbolehkan untuk hari Selasa dan Kamis"],
    "attendance_data.0.pegawai_id": ["Pegawai tidak ditemukan"]
  }
}
```

### 2. **Authorization Errors**
```json
{
  "success": false,
  "message": "Unauthorized. Hanya GA/Admin yang dapat mengakses endpoint ini."
}
```

### 3. **Database Errors**
```json
{
  "success": false,
  "message": "Terjadi kesalahan: Database connection failed"
}
```

## 📈 Monitoring & Logging

### 1. **Log Entries**
- Manual attendance requests
- Data updates
- Validation errors
- Authorization failures

### 2. **Metrics**
- Number of manual inputs per day
- Success/failure rates
- User activity tracking

## 🔮 Future Enhancements

### 1. **Bulk Operations**
- Import from Excel/CSV
- Bulk status updates
- Mass employee assignment

### 2. **Advanced Filtering**
- Date range filtering
- Employee department filtering
- Status combination filtering

### 3. **Reporting**
- Manual vs Online attendance comparison
- Weekly/monthly summaries
- Trend analysis

## 📋 Checklist Implementation

- [x] Database migration
- [x] Model updates
- [x] Service layer
- [x] Controller implementation
- [x] Request validation
- [x] Middleware creation
- [x] Route registration
- [x] Resource transformation
- [x] Command for data migration
- [x] Feature tests
- [x] Documentation
- [x] Error handling
- [x] Logging implementation 