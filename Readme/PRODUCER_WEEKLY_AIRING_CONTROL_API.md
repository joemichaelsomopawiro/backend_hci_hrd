# 📺 Producer Weekly Airing Control API

Dokumentasi ini menjelaskan endpoint-endpoint baru untuk Producer mengontrol program live agar tayang 1 episode setiap minggu.

---

## 🎯 Overview

Endpoint-endpoint ini membantu Producer untuk:
- Melihat episode yang akan tayang minggu ini
- Memastikan episode siap untuk tayang
- Monitoring readiness checklist untuk setiap episode
- Mendapatkan warning jika episode belum ready

---

## 📋 Endpoints

### 1. GET `/api/live-tv/producer/weekly-airing-control`

**Deskripsi:** Dashboard khusus untuk kontrol tayang mingguan. Menampilkan semua episode yang akan tayang minggu ini dengan status readiness.

**Authorization:** Producer only

**Query Parameters:**
- Tidak ada

**Response:**
```json
{
  "success": true,
  "data": {
    "week_period": {
      "start": "2025-12-08",
      "end": "2025-12-14",
      "current_date": "2025-12-10"
    },
    "statistics": {
      "total_episodes_this_week": 3,
      "ready_episodes": 2,
      "not_ready_episodes": 1,
      "aired_episodes": 0,
      "readiness_rate": 66.67
    },
    "episodes": {
      "ready": [
        {
          "id": 1,
          "episode_number": 5,
          "title": "Episode 5",
          "program_name": "Program Musik Live",
          "air_date": "2025-12-12 19:00:00",
          "status": "ready_to_air",
          "current_workflow_state": "broadcasting",
          "readiness": {
            "is_ready": true,
            "checklist": {
              "status": {
                "label": "Episode Status",
                "status": "ready",
                "value": "ready_to_air",
                "required": "ready_to_air"
              },
              "rundown": {
                "label": "Rundown",
                "status": "ready",
                "value": "Available"
              },
              "deadlines": {
                "label": "Deadlines",
                "status": "ready",
                "value": "5/5 completed",
                "overdue": 0
              },
              "music_arrangement": {
                "label": "Music Arrangement",
                "status": "ready",
                "value": "Approved"
              },
              "creative_work": {
                "label": "Creative Work",
                "status": "ready",
                "value": "Approved"
              },
              "sound_engineering": {
                "label": "Sound Engineering",
                "status": "ready",
                "value": "Approved"
              },
              "editor_work": {
                "label": "Editor Work",
                "status": "ready",
                "value": "Approved"
              },
              "quality_control": {
                "label": "Quality Control",
                "status": "ready",
                "value": "Approved"
              }
            },
            "missing_items": [],
            "warnings": []
          },
          "days_until_air": 2
        }
      ],
      "not_ready": [
        {
          "id": 2,
          "episode_number": 6,
          "title": "Episode 6",
          "program_name": "Program Musik Live",
          "air_date": "2025-12-13 19:00:00",
          "status": "post_production",
          "current_workflow_state": "editing",
          "readiness": {
            "is_ready": false,
            "checklist": {
              "status": {
                "label": "Episode Status",
                "status": "not_ready",
                "value": "post_production",
                "required": "ready_to_air"
              },
              "quality_control": {
                "label": "Quality Control",
                "status": "not_ready",
                "value": "Not approved or missing"
              }
            },
            "missing_items": [
              "Episode status harus ready_to_air",
              "QC belum approved"
            ],
            "warnings": [
              "Episode akan tayang dalam 3 hari"
            ]
          },
          "days_until_air": 3
        }
      ],
      "aired": []
    }
  },
  "message": "Weekly airing control data retrieved successfully"
}
```

---

### 2. GET `/api/live-tv/producer/episodes/upcoming-this-week`

**Deskripsi:** Mendapatkan episode yang akan tayang minggu ini (belum aired).

**Authorization:** Producer only

**Query Parameters:**
- `ready_only` (boolean, optional): Jika `true`, hanya return episode yang ready untuk tayang

**Response:**
```json
{
  "success": true,
  "data": {
    "week_period": {
      "start": "2025-12-08",
      "end": "2025-12-14"
    },
    "episodes": [
      {
        "id": 1,
        "episode_number": 5,
        "title": "Episode 5",
        "program_name": "Program Musik Live",
        "program_id": 1,
        "air_date": "2025-12-12 19:00:00",
        "status": "ready_to_air",
        "current_workflow_state": "broadcasting",
        "readiness": {
          "is_ready": true,
          "checklist": {...},
          "missing_items": [],
          "warnings": []
        },
        "days_until_air": 2,
        "deadlines": {
          "total": 5,
          "completed": 5,
          "overdue": 0
        }
      }
    ],
    "count": 1
  },
  "message": "Upcoming episodes this week retrieved successfully"
}
```

**Example dengan filter ready_only:**
```
GET /api/live-tv/producer/episodes/upcoming-this-week?ready_only=true
```

---

### 3. GET `/api/live-tv/producer/episodes/ready-this-week`

**Deskripsi:** Mendapatkan episode yang sudah ready untuk tayang minggu ini.

**Authorization:** Producer only

**Query Parameters:**
- Tidak ada

**Response:**
```json
{
  "success": true,
  "data": {
    "week_period": {
      "start": "2025-12-08",
      "end": "2025-12-14"
    },
    "episodes": [
      {
        "id": 1,
        "episode_number": 5,
        "title": "Episode 5",
        "program_name": "Program Musik Live",
        "program_id": 1,
        "air_date": "2025-12-12 19:00:00",
        "status": "ready_to_air",
        "current_workflow_state": "broadcasting",
        "readiness": {
          "is_ready": true,
          "checklist": {...},
          "missing_items": [],
          "warnings": []
        },
        "days_until_air": 2
      }
    ],
    "count": 1
  },
  "message": "Ready episodes this week retrieved successfully"
}
```

---

## 🔍 Readiness Checklist

Setiap episode memiliki `readiness` object yang berisi checklist untuk memastikan episode siap tayang:

### Checklist Items:

1. **Status** - Episode status harus `ready_to_air`
2. **Rundown** - Rundown harus tersedia
3. **Deadlines** - Semua deadlines harus completed
4. **Music Arrangement** - Music arrangement harus approved
5. **Creative Work** - Creative work harus approved
6. **Sound Engineering** - Sound engineering harus approved
7. **Editor Work** - Editor work harus approved
8. **Quality Control** - QC harus approved

### Readiness Status:

- `is_ready`: `true` jika episode siap tayang (status `ready_to_air` dan QC approved)
- `checklist`: Array checklist items dengan status masing-masing
- `missing_items`: Array item yang masih missing
- `warnings`: Array warning messages (contoh: deadline overdue, kurang dari 3 hari lagi)

---

## 📊 Use Cases

### Use Case 1: Producer ingin melihat semua episode yang akan tayang minggu ini

```bash
GET /api/live-tv/producer/weekly-airing-control
```

**Response:** Dashboard lengkap dengan statistics dan categorized episodes (ready, not_ready, aired)

### Use Case 2: Producer ingin melihat hanya episode yang ready untuk tayang

```bash
GET /api/live-tv/producer/episodes/ready-this-week
```

**Response:** Hanya episode yang sudah ready untuk tayang

### Use Case 3: Producer ingin melihat episode yang belum ready untuk tayang

```bash
GET /api/live-tv/producer/episodes/upcoming-this-week
```

Kemudian filter di frontend untuk episode dengan `readiness.is_ready === false`

---

## ⚠️ Warnings & Missing Items

### Warnings:
- Episode akan tayang dalam X hari (jika kurang dari 3 hari)
- Episode sudah melewati jadwal tayang (jika air_date sudah lewat)
- Ada deadline yang overdue

### Missing Items:
- Episode status harus ready_to_air
- Rundown belum tersedia
- Masih ada X deadline yang belum selesai
- Music arrangement belum approved
- Creative work belum approved
- Sound engineering belum completed
- Editor work belum approved
- QC belum approved

---

## 🔑 Key Features

1. **Weekly Period Detection:**
   - Otomatis detect minggu ini (start of week sampai end of week)
   - Menggunakan `now()->startOfWeek()` dan `now()->endOfWeek()`

2. **Readiness Check:**
   - Comprehensive checklist untuk memastikan episode siap tayang
   - Real-time check dari database
   - Warning system untuk episode yang mendekati jadwal tayang

3. **Statistics:**
   - Total episodes minggu ini
   - Ready episodes count
   - Not ready episodes count
   - Aired episodes count
   - Readiness rate percentage

4. **Categorized Episodes:**
   - Ready episodes (siap tayang)
   - Not ready episodes (belum siap, dengan missing items)
   - Aired episodes (sudah tayang)

---

## 📝 Notes

- **Week Period:** Menggunakan start of week (Senin) sampai end of week (Minggu)
- **Readiness Logic:** Episode dianggap ready jika:
  - Status = `ready_to_air`
  - QC approved
- **Filter:** Producer hanya melihat episode dari program ProductionTeam mereka
- **Days Until Air:** Negative jika sudah lewat jadwal tayang

---

## ✅ Summary

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/weekly-airing-control` | GET | Dashboard kontrol tayang mingguan |
| `/episodes/upcoming-this-week` | GET | Episode yang akan tayang minggu ini |
| `/episodes/ready-this-week` | GET | Episode yang ready untuk tayang minggu ini |

**Status:** ✅ **IMPLEMENTED**

---

**Last Updated:** December 10, 2025

