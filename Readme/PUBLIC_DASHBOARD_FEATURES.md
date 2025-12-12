# ✅ Fitur Umum Dashboard Utama - Semua Pegawai HCI

Dokumentasi ini menjelaskan fitur umum yang dapat diakses oleh **SEMUA pegawai HCI** di dashboard utama.

---

## 📋 Requirement & Status

### 1. ✅ Semua Pegawai HCI Dapat Melihat Jadwal Syuting yang Sudah Di-acc Producer di Dashboard Utama

**Status:** ✅ **IMPLEMENTED & UPDATED**

**Implementasi:**
- **Endpoint:** `GET /api/live-tv/dashboard/shooting-schedules`
- **Controller:** `PublicDashboardController::getApprovedShootingSchedules()`
- **Akses:** Semua authenticated users (semua pegawai HCI)
- **Fitur:**
  - Menampilkan jadwal syuting dari **Creative Work** yang sudah di-approve Producer
  - Menampilkan jadwal syuting dari **Music Schedule** (program musik) yang sudah di-confirm/scheduled
  - Filter berdasarkan tanggal (start_date, end_date)
  - Filter berdasarkan bulan (month, year)
  - Format calendar events untuk frontend

**File:**
- `app/Http/Controllers/Api/PublicDashboardController.php` (line 23-120)

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": "creative_1",
      "title": "Program Musik Live - Episode 1",
      "start": "2025-01-15 10:00:00",
      "location": "Studio A",
      "episode_title": "Episode 1",
      "program_name": "Program Musik Live",
      "type": "shooting",
      "source": "creative_work",
      "status": "approved"
    },
    {
      "id": "music_5",
      "title": "Program Musik Live - Episode 2 (Syuting Video Klip)",
      "start": "2025-01-16 14:00:00",
      "location": "Outdoor Location",
      "episode_title": "Episode 2",
      "program_name": "Program Musik Live",
      "type": "shooting",
      "source": "music_schedule",
      "status": "confirmed"
    }
  ],
  "message": "Approved shooting schedules retrieved successfully"
}
```

**Query Parameters:**
- `start_date` - Filter mulai tanggal
- `end_date` - Filter sampai tanggal
- `month` - Filter bulan (1-12)
- `year` - Filter tahun

---

### 2. ✅ Semua Pegawai HCI Dapat Melihat Jadwal Tayang

**Status:** ✅ **IMPLEMENTED**

**Implementasi:**
- **Endpoint:** `GET /api/live-tv/dashboard/air-schedules`
- **Controller:** `PublicDashboardController::getApprovedAirSchedules()`
- **Akses:** Semua authenticated users (semua pegawai HCI)
- **Fitur:**
  - Menampilkan jadwal tayang dari **BroadcastingSchedule** yang sudah scheduled/uploaded/published
  - Filter berdasarkan tanggal (start_date, end_date)
  - Filter berdasarkan bulan (month, year)
  - Format calendar events untuk frontend

**File:**
- `app/Http/Controllers/Api/PublicDashboardController.php` (line 89-148)

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Program Musik Live - Episode 1",
      "start": "2025-01-20 19:00:00",
      "platform": "youtube",
      "episode_title": "Episode 1",
      "program_name": "Program Musik Live",
      "type": "airing",
      "status": "scheduled",
      "url": "https://youtube.com/watch?v=..."
    }
  ],
  "message": "Approved air schedules retrieved successfully"
}
```

**Query Parameters:**
- `start_date` - Filter mulai tanggal
- `end_date` - Filter sampai tanggal
- `month` - Filter bulan (1-12)
- `year` - Filter tahun

---

### 3. ✅ KPI Berdasarkan Menyelesaikan Pekerjaan Tepat Waktu

**Status:** ✅ **IMPLEMENTED & INTEGRATED**

**Implementasi:**
- **Endpoint Dashboard:** `GET /api/live-tv/dashboard/overview` (include KPI)
- **Endpoint KPI Dedicated:** `GET /api/live-tv/kpi/dashboard`
- **Controller:** 
  - `PublicDashboardController::getDashboardOverview()` - Include KPI di overview
  - `KPIController::dashboard()` - KPI dedicated endpoint
- **Akses:** Semua authenticated users (semua pegawai HCI)
- **Fitur KPI:**
  - **On-Time Completion Rate:** Persentase pekerjaan yang selesai tepat waktu
  - **Deadline Compliance:** Compliance rate berdasarkan deadline vs completion time
  - **Work Completion:** Completion rate per role (kreatif, musik_arr, sound_eng, produksi, editor)
  - **On-Time Rate per Role:** Persentase pekerjaan tepat waktu per role

**File:**
- `app/Http/Controllers/Api/PublicDashboardController.php` (line 266-342, methods: getOnTimeCompletionRate, getDeadlineCompliance, getWorkCompletion)
- `app/Http/Controllers/Api/KPIController.php` (line 20-40, 444-460)

**KPI Metrics:**

#### A. On-Time Completion Rate
```json
{
  "on_time_completion_rate": 85.5
}
```
- Menghitung persentase deadline yang selesai sebelum atau tepat pada deadline date
- Formula: `(On-Time Deadlines / Total Completed Deadlines) * 100`

#### B. Deadline Compliance
```json
{
  "deadline_compliance": {
    "total_deadlines": 150,
    "completed_deadlines": 120,
    "on_time_deadlines": 102,
    "compliance_rate": 80.0,
    "on_time_rate": 85.0
  }
}
```

#### C. Work Completion per Role
```json
{
  "work_completion": [
    {
      "role": "kreatif",
      "total": 30,
      "completed": 25,
      "on_time": 22,
      "completion_rate": 83.33,
      "on_time_rate": 88.0
    },
    {
      "role": "musik_arr",
      "total": 25,
      "completed": 20,
      "on_time": 18,
      "completion_rate": 80.0,
      "on_time_rate": 90.0
    },
    {
      "role": "sound_eng",
      "total": 20,
      "completed": 18,
      "on_time": 16,
      "completion_rate": 90.0,
      "on_time_rate": 88.89
    },
    {
      "role": "produksi",
      "total": 35,
      "completed": 30,
      "on_time": 26,
      "completion_rate": 85.71,
      "on_time_rate": 86.67
    },
    {
      "role": "editor",
      "total": 40,
      "completed": 27,
      "on_time": 20,
      "completion_rate": 67.5,
      "on_time_rate": 74.07
    }
  ]
}
```

**Dashboard Overview Response (Include KPI):**
```json
{
  "success": true,
  "data": {
    "user": {
      "name": "John Doe",
      "role": "Editor",
      "email": "john@example.com"
    },
    "today": {
      "date": "2025-12-10",
      "shooting_schedules": [...],
      "recording_schedules": [...],
      "air_schedules": [...]
    },
    "upcoming": {
      "shooting_schedules": [...],
      "air_schedules": [...]
    },
    "statistics": {
      "active_programs": 5,
      "total_episodes_this_month": 20,
      "upcoming_air_this_week": 3
    },
    "kpi": {
      "on_time_completion_rate": 85.5,
      "deadline_compliance": {
        "total_deadlines": 150,
        "completed_deadlines": 120,
        "on_time_deadlines": 102,
        "compliance_rate": 80.0,
        "on_time_rate": 85.0
      },
      "work_completion": [...]
    }
  },
  "message": "Dashboard overview retrieved successfully"
}
```

---

## 📊 Endpoints Summary

| Fitur | Endpoint | Method | Akses |
|-------|----------|--------|-------|
| Jadwal Syuting (Approved) | `/api/live-tv/dashboard/shooting-schedules` | GET | Semua Pegawai |
| Jadwal Tayang | `/api/live-tv/dashboard/air-schedules` | GET | Semua Pegawai |
| Dashboard Overview (Include KPI) | `/api/live-tv/dashboard/overview` | GET | Semua Pegawai |
| Calendar (Combined) | `/api/live-tv/dashboard/calendar` | GET | Semua Pegawai |
| KPI Dashboard | `/api/live-tv/kpi/dashboard` | GET | Semua Pegawai |

---

## 🔑 Key Features

### 1. Jadwal Syuting
- ✅ Menampilkan jadwal syuting dari **Creative Work** (program reguler)
- ✅ Menampilkan jadwal syuting dari **Music Schedule** (program musik)
- ✅ Hanya menampilkan yang sudah di-approve/confirmed oleh Producer
- ✅ Filter berdasarkan tanggal dan bulan
- ✅ Format calendar events untuk frontend

### 2. Jadwal Tayang
- ✅ Menampilkan jadwal tayang dari **BroadcastingSchedule**
- ✅ Hanya menampilkan yang sudah scheduled/uploaded/published
- ✅ Filter berdasarkan tanggal dan bulan
- ✅ Include platform dan URL

### 3. KPI Tepat Waktu
- ✅ **On-Time Completion Rate:** Persentase pekerjaan tepat waktu
- ✅ **Deadline Compliance:** Compliance rate dan on-time rate
- ✅ **Work Completion per Role:** Completion rate dan on-time rate per role
- ✅ **Real Calculation:** Berdasarkan data aktual dari database (bukan mock)
- ✅ **Integrated:** Sudah terintegrasi di dashboard overview

---

## 📝 Catatan Penting

### Jadwal Syuting Program Musik
- Untuk program musik, jadwal syuting menggunakan model `MusicSchedule`
- Status yang ditampilkan: `scheduled`, `confirmed`
- Schedule type: `shooting` (syuting video klip)
- Sudah terintegrasi dengan `getApprovedShootingSchedules()`

### KPI Calculation
- **On-Time Completion Rate:** Dihitung dari `Deadline` table
  - `completed_at <= deadline_date` = On-Time
  - `completed_at > deadline_date` = Late
- **Deadline Compliance:** 
  - `compliance_rate` = (Completed / Total) * 100
  - `on_time_rate` = (On-Time / Completed) * 100
- **Work Completion:** Per role berdasarkan deadline role

### Akses
- **Semua endpoint di `/api/live-tv/dashboard/*` dapat diakses oleh SEMUA authenticated users**
- Tidak ada role restriction
- Hanya perlu authentication (login)

---

## ✅ Kesimpulan

**Semua 3 requirement sudah diimplementasikan dan terintegrasi:**

1. ✅ **Jadwal Syuting yang Sudah Di-acc Producer** - SUDAH ADA & UPDATED (include MusicSchedule)
2. ✅ **Jadwal Tayang** - SUDAH ADA
3. ✅ **KPI Berdasarkan Menyelesaikan Pekerjaan Tepat Waktu** - SUDAH ADA & INTEGRATED di Dashboard Overview

**Status:** ✅ **SEMUA FITUR SUDAH TERINTEGRASI**

---

**Last Updated:** December 10, 2025

