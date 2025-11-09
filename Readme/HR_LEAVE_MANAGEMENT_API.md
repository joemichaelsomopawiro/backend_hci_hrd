# HR Leave Management API Documentation

## Overview
Sistem manajemen cuti ini memungkinkan HR untuk mengelola jatah cuti karyawan dengan mudah. Sistem terintegrasi penuh antara tabel `leave_quotas` dan `leave_requests` untuk memastikan tracking yang akurat.

## Fitur Utama

### 1. **Integrasi Otomatis**
- Ketika cuti disetujui, jatah cuti otomatis berkurang
- Validasi jatah cuti saat pengajuan
- Tracking real-time penggunaan cuti

### 2. **Manajemen Jatah Cuti**
- CRUD operations untuk jatah cuti individual
- Bulk update untuk multiple karyawan
- Reset jatah cuti tahunan
- Ringkasan penggunaan cuti

## API Endpoints

### Basic CRUD Operations

#### 1. Get All Leave Quotas
```http
GET /api/leave-quotas
```

**Query Parameters:**
- `employee_id` (optional): Filter by employee ID
- `year` (optional): Filter by year

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 1,
      "year": 2024,
      "annual_leave_quota": 12,
      "annual_leave_used": 5,
      "sick_leave_quota": 12,
      "sick_leave_used": 2,
      "emergency_leave_quota": 3,
      "emergency_leave_used": 0,
      "maternity_leave_quota": 90,
      "maternity_leave_used": 0,
      "paternity_leave_quota": 7,
      "paternity_leave_used": 0,
      "marriage_leave_quota": 3,
      "marriage_leave_used": 0,
      "bereavement_leave_quota": 3,
      "bereavement_leave_used": 0,
      "employee": {
        "id": 1,
        "name": "John Doe",
        "employee_id": "EMP001"
      }
    }
  ]
}
```

#### 2. Create Leave Quota
```http
POST /api/leave-quotas
```

**Request Body:**
```json
{
  "employee_id": 1,
  "year": 2024,
  "annual_leave_quota": 12,
  "sick_leave_quota": 12,
  "emergency_leave_quota": 3,
  "maternity_leave_quota": 90,
  "paternity_leave_quota": 7,
  "marriage_leave_quota": 3,
  "bereavement_leave_quota": 3
}
```

#### 3. Update Leave Quota
```http
PUT /api/leave-quotas/{id}
```

**Request Body:**
```json
{
  "annual_leave_quota": 15,
  "annual_leave_used": 3
}
```

### HR Special Endpoints

#### 4. Bulk Update Leave Quotas
```http
POST /api/leave-quotas/bulk-update
```

**Use Case:** Update multiple employees' quotas at once

**Request Body:**
```json
{
  "updates": [
    {
      "employee_id": 1,
      "year": 2024,
      "annual_leave_quota": 15,
      "sick_leave_quota": 12
    },
    {
      "employee_id": 2,
      "year": 2024,
      "annual_leave_quota": 12,
      "emergency_leave_quota": 5
    }
  ]
}
```

#### 5. Reset Annual Quotas
```http
POST /api/leave-quotas/reset-annual
```

**Use Case:** Reset all employees' quotas for new year

**Request Body:**
```json
{
  "year": 2025,
  "default_quotas": {
    "annual_leave_quota": 12,
    "sick_leave_quota": 12,
    "emergency_leave_quota": 3,
    "maternity_leave_quota": 90,
    "paternity_leave_quota": 7,
    "marriage_leave_quota": 3,
    "bereavement_leave_quota": 3
  }
}
```

#### 6. Get Usage Summary
```http
GET /api/leave-quotas/usage-summary
```

**Query Parameters:**
- `year` (optional): Filter by year
- `employee_id` (optional): Filter by employee

**Response:**
```json
{
  "success": true,
  "data": [...],
  "summary": {
    "total_employees": 50,
    "leave_types_summary": {
      "annual": {
        "total_quota": 600,
        "total_used": 250,
        "total_remaining": 350
      },
      "sick": {
        "total_quota": 600,
        "total_used": 100,
        "total_remaining": 500
      }
      // ... other leave types
    }
  }
}
```

## Integration dengan Leave Requests

### Automatic Quota Deduction
Ketika leave request disetujui, sistem otomatis:
1. Mengurangi jatah cuti yang tersedia
2. Menambah jumlah cuti yang sudah digunakan
3. Berlaku untuk semua jenis cuti kecuali sick leave (unlimited)

### Validation Process
Saat pengajuan cuti:
1. Sistem mengecek jatah cuti yang tersedia
2. Memvalidasi apakah cuti yang diminta tidak melebihi jatah
3. Menolak pengajuan jika jatah tidak mencukupi

## Workflow untuk HR

### 1. Setup Awal Tahun
```bash
# Reset semua jatah cuti untuk tahun baru
POST /api/leave-quotas/reset-annual
{
  "year": 2025,
  "default_quotas": {
    "annual_leave_quota": 12,
    "sick_leave_quota": 12,
    "emergency_leave_quota": 3,
    "maternity_leave_quota": 90,
    "paternity_leave_quota": 7,
    "marriage_leave_quota": 3,
    "bereavement_leave_quota": 3
  }
}
```

### 2. Adjustment Individual
```bash
# Update jatah cuti karyawan tertentu
PUT /api/leave-quotas/{id}
{
  "annual_leave_quota": 15  # Karyawan senior dapat jatah lebih
}
```

### 3. Bulk Adjustment
```bash
# Update multiple karyawan sekaligus
POST /api/leave-quotas/bulk-update
{
  "updates": [
    {"employee_id": 1, "year": 2024, "annual_leave_quota": 15},
    {"employee_id": 2, "year": 2024, "annual_leave_quota": 18}
  ]
}
```

### 4. Monitoring
```bash
# Lihat ringkasan penggunaan cuti
GET /api/leave-quotas/usage-summary?year=2024
```

## Leave Types Supported

1. **Annual Leave** (`annual`) - Cuti tahunan
2. **Sick Leave** (`sick`) - Cuti sakit (unlimited)
3. **Emergency Leave** (`emergency`) - Cuti darurat
4. **Maternity Leave** (`maternity`) - Cuti melahirkan
5. **Paternity Leave** (`paternity`) - Cuti ayah
6. **Marriage Leave** (`marriage`) - Cuti menikah
7. **Bereavement Leave** (`bereavement`) - Cuti duka

## Security & Access Control

- Semua endpoint leave-quotas dapat diakses tanpa authentication (untuk kemudahan HR)
- Leave requests memerlukan authentication dengan Sanctum
- Role-based access control diterapkan di level controller
- Validation ketat untuk semua input data

## Best Practices untuk HR

1. **Setup Tahunan**: Gunakan `reset-annual` di awal tahun
2. **Monitoring Berkala**: Cek `usage-summary` secara rutin
3. **Adjustment Fleksibel**: Gunakan `bulk-update` untuk perubahan massal
4. **Individual Care**: Update individual quota sesuai kebutuhan khusus
5. **Data Backup**: Selalu backup data sebelum reset tahunan

## Error Handling

Semua endpoint mengembalikan response dengan format:
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## Support

Untuk pertanyaan teknis atau bug report, silakan hubungi tim development.