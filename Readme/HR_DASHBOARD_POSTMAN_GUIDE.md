# 📋 Panduan Postman untuk Dashboard HR

## 🎯 Overview

Dashboard HR menampilkan:
1. **Profil Pegawai** - Data personal pegawai
2. **Rekap Absensi** - Data absensi dengan filter tahun
3. **Informasi Gaji** - Data gaji pegawai (jika tersedia)
4. **Kinerja & Pelatihan** - Data kinerja dan pelatihan
5. **Kalender** - Hari libur nasional

---

## 🔐 Authentication

Semua endpoint memerlukan **Bearer Token** (kecuali kalender hari libur).

**Headers:**
```
Authorization: Bearer {your_token}
Content-Type: application/json
Accept: application/json
```

---

## 📡 Endpoint untuk Dashboard HR

### **1. Profil Pegawai**

**Endpoint:** `GET /api/personal/profile`

**Method:** `GET`

**Query Parameters:**
```
employee_id=1  (required)
```

**Contoh Request:**
```
GET http://localhost:8000/api/personal/profile?employee_id=1
```

**Response:**
```json
{
  "success": true,
  "message": "Profile data retrieved successfully",
  "data": {
    "id": 1,
    "nama_lengkap": "Developer",
    "jabatan_saat_ini": "Developer",
    "role": "HR",
    "nip": "67825468",
    "no_telepon": "0895616000972",
    "tanggal_mulai_kerja": "2025-07-24",
    "user": {
      "id": 1,
      "name": "Developer",
      "email": "developer@example.com"
    },
    "documents": [...],
    "employment_histories": [...],
    "promotion_histories": [...],
    "trainings": [...],
    "benefits": [...],
    "leave_quotas": [...],
    "statistics": {
      "total_documents": 0,
      "total_employment_histories": 0,
      "total_promotions": 0,
      "total_trainings": 0,
      "total_benefits": 0,
      "total_leave_requests": 0,
      "years_of_service": 0
    }
  }
}
```

---

### **2. Rekap Absensi**

**Endpoint:** `GET /api/personal/office-attendance`

**Method:** `GET`

**Query Parameters:**
```
employee_id=1              (required)
start_date=2025-01-01      (optional, default: start of month)
end_date=2025-12-31        (optional, default: today)
year=2025                  (optional, untuk filter tahun)
```

**Contoh Request:**
```
GET http://localhost:8000/api/personal/office-attendance?employee_id=1&year=2025
```

**Response:**
```json
{
  "success": true,
  "message": "Data absensi kantor berhasil diambil",
  "data": {
    "employee": {
      "id": 1,
      "nama_lengkap": "Developer",
      "jabatan_saat_ini": "Developer"
    },
    "attendance_records": [
      {
        "id": 1,
        "date": "2025-07-28",
        "day_name": "Monday",
        "check_in": null,
        "check_out": null,
        "status": "sick",
        "status_label": "Sakit",
        "work_hours": 0,
        "late_minutes": 0,
        "early_leave_minutes": 0,
        "overtime_hours": 0
      },
      {
        "id": 2,
        "date": "2025-07-29",
        "day_name": "Tuesday",
        "check_in": null,
        "check_out": null,
        "status": "sick",
        "status_label": "Sakit",
        "work_hours": 0,
        "late_minutes": 0,
        "early_leave_minutes": 0,
        "overtime_hours": 0
      }
    ],
    "statistics": {
      "hadir": 0,
      "izin": 0,
      "sakit": 11,
      "cuti": 19,
      "total_work_hours": 0,
      "total_days": 30
    },
    "summary": {
      "hadir": 0,
      "izin": 0,
      "sakit": 11,
      "total_work_hours": 0
    },
    "leave_records": [
      {
        "id": 1,
        "employee_id": 1,
        "leave_type": "annual",
        "type": "annual",
        "start_date": "2025-07-30",
        "end_date": "2025-07-30",
        "total_days": 1,
        "overall_status": "approved",
        "leave_dates": ["2025-07-30"]
      }
    ],
    "date_range": {
      "start_date": "2025-01-01",
      "end_date": "2025-12-31"
    },
    "total_records": 30
  }
}
```

**Catatan:**
- `statistics.hadir` = Total Hadir
- `statistics.sakit` = Total Sakit
- `statistics.cuti` = Total Cuti
- `attendance_records` = Daftar absensi per tanggal

---

### **3. Informasi Gaji**

**⚠️ CATATAN:** Endpoint untuk informasi gaji belum tersedia di backend saat ini.

**Alternatif:**
- Data gaji mungkin ada di tabel `employees` atau tabel terpisah
- Perlu dibuat endpoint baru jika diperlukan

**Jika ada endpoint gaji, formatnya mungkin:**
```
GET /api/employees/{id}/salary
GET /api/personal/salary
```

---

### **4. Kinerja & Pelatihan**

**Endpoint untuk Pelatihan:**
Data pelatihan sudah termasuk di endpoint **Profil Pegawai** (`/api/personal/profile`):
- `data.trainings` - Daftar pelatihan
- `data.statistics.total_trainings` - Total pelatihan

**Endpoint untuk Kinerja:**
**⚠️ CATATAN:** Endpoint khusus untuk kinerja (KPI) mungkin ada di:
```
GET /api/kpi/user/{userId}
GET /api/analytics/dashboard
```

**Contoh Request (jika ada):**
```
GET http://localhost:8000/api/kpi/user/1
```

---

### **5. Kalender Hari Libur**

**Endpoint:** `GET /api/calendar/national-holidays`

**Method:** `GET`

**Query Parameters:**
```
year=2025  (optional, default: current year)
```

**Contoh Request:**
```
GET http://localhost:8000/api/calendar/national-holidays?year=2025
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "date": "2025-01-01",
      "name": "Hari Tahun Baru",
      "description": "Hari libur nasional",
      "type": "national",
      "is_active": true,
      "google_event_id": "20250101_ks7guic13a7hki9vop7h626m3k"
    },
    {
      "date": "2025-12-24",
      "name": "Malam Natal",
      "description": "Perayaan",
      "type": "perayaan",
      "is_active": true,
      "google_event_id": "20251224_l0k3u6ntli9048lt9is90c1ua4"
    },
    {
      "date": "2025-12-25",
      "name": "Hari Raya Natal",
      "description": "Hari libur nasional",
      "type": "national",
      "is_active": true,
      "google_event_id": "20251225_b7v0qnek2a5qf7nf96tfeg0mkk"
    },
    {
      "date": "2025-12-26",
      "name": "Cuti Bersama Natal (Hari Tinju)",
      "description": "Hari libur nasional",
      "type": "cuti_bersama",
      "is_active": true,
      "google_event_id": "20251226_crklv6e1ketbb7sh7c9rpv8iho"
    },
    {
      "date": "2025-12-31",
      "name": "Malam Tahun Baru",
      "description": "Perayaan",
      "type": "perayaan",
      "is_active": true,
      "google_event_id": "20251231_qfuh1mjr99kp2pudrpthrsrdao"
    }
  ],
  "year": 2025,
  "count": 32
}
```

**Catatan:**
- Endpoint ini **PUBLIC** (tidak perlu authentication)
- Type: `national`, `cuti_bersama`, atau `perayaan`

---

## 📋 Collection Postman

### **Setup Environment Variables:**

Di Postman, buat environment dengan variables:
```
base_url = http://localhost:8000
token = {your_bearer_token}
employee_id = 1
year = 2025
```

### **Request 1: Get Profile**
```
GET {{base_url}}/api/personal/profile?employee_id={{employee_id}}
Headers:
  Authorization: Bearer {{token}}
  Accept: application/json
```

### **Request 2: Get Attendance Recap**
```
GET {{base_url}}/api/personal/office-attendance?employee_id={{employee_id}}&year={{year}}
Headers:
  Authorization: Bearer {{token}}
  Accept: application/json
```

### **Request 3: Get Calendar Holidays**
```
GET {{base_url}}/api/calendar/national-holidays?year={{year}}
Headers:
  Accept: application/json
```

---

## 🔄 Flow Dashboard HR

1. **Load Profile** → `GET /api/personal/profile?employee_id={id}`
2. **Load Attendance** → `GET /api/personal/office-attendance?employee_id={id}&year={year}`
3. **Load Calendar** → `GET /api/calendar/national-holidays?year={year}`
4. **Load Salary** → (Belum tersedia, perlu dibuat)
5. **Load Performance** → (Mungkin ada di `/api/kpi/user/{id}` atau endpoint lain)

---

## ✅ Checklist Endpoint

- [x] Profil Pegawai - `/api/personal/profile`
- [x] Rekap Absensi - `/api/personal/office-attendance`
- [x] Kalender Hari Libur - `/api/calendar/national-holidays`
- [ ] Informasi Gaji - **Belum tersedia**
- [ ] Kinerja Detail - **Perlu dicek endpoint KPI**

---

## 📝 Catatan Penting

1. **Gaji**: Endpoint untuk gaji belum tersedia, perlu dibuat jika diperlukan
2. **Kinerja**: Data kinerja mungkin ada di endpoint KPI atau analytics
3. **Pelatihan**: Data pelatihan sudah ada di endpoint profil
4. **Authentication**: Semua endpoint kecuali kalender memerlukan Bearer Token

---

**Last Updated**: 2025-01-23


