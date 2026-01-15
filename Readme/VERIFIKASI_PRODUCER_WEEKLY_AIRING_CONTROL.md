# ✅ VERIFIKASI: PRODUCER MENGONTROL PROGRAM LIVE UNTUK TAYANG 1 EPISODE SETIAP MINGGU

**Tanggal Verifikasi:** 2026-01-14  
**Status:** ✅ **SUDAH ADA - LENGKAP DENGAN DASHBOARD & READINESS CHECK**

---

## 📋 FITUR YANG DIPERLUKAN

**Producer di program musik dapat mengontrol Program Live untuk tayang 1 Episode setiap minggu**

---

## ✅ VERIFIKASI DETAIL

### **✅ 1. Weekly Airing Control Dashboard**

**Status:** ✅ **SUDAH ADA**

**Endpoint:**
```
GET /api/live-tv/producer/weekly-airing-control
```

**Controller:** `ProducerController@getWeeklyAiringControl`  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 2940-3030

**Route:**
- File: `routes/live_tv_api.php` line 675
- Route: `Route::get('/weekly-airing-control', [ProducerController::class, 'getWeeklyAiringControl])`

**Fitur:**
- ✅ Dashboard kontrol tayang mingguan
- ✅ Get episodes yang akan tayang minggu ini (startOfWeek sampai endOfWeek)
- ✅ Kategorisasi episodes: ready, not ready, aired
- ✅ Statistics: total episodes, ready count, not ready count, aired count, readiness rate
- ✅ Episode readiness check untuk setiap episode
- ✅ Days until air untuk setiap episode
- ✅ Filter hanya dari program ProductionTeam Producer

**Kode Verifikasi:**
```php
// Line 2952-2962: Get episodes minggu ini
$weekStart = now()->startOfWeek();
$weekEnd = now()->endOfWeek();

$episodesThisWeek = Episode::with(['program', 'deadlines', 'workflowStates'])
    ->whereHas('program.productionTeam', function ($q) use ($user) {
        $q->where('producer_id', $user->id);
    })
    ->whereBetween('air_date', [$weekStart, $weekEnd])
    ->orderBy('air_date', 'asc')
    ->get();

// Line 2964-2991: Kategorisasi episodes
$readyEpisodes = [];
$notReadyEpisodes = [];
$airedEpisodes = [];

foreach ($episodesThisWeek as $episode) {
    $readiness = $this->checkEpisodeReadiness($episode);
    
    $episodeData = [
        'id' => $episode->id,
        'episode_number' => $episode->episode_number,
        'title' => $episode->title,
        'program_name' => $episode->program->name,
        'air_date' => $episode->air_date,
        'status' => $episode->status,
        'current_workflow_state' => $episode->current_workflow_state,
        'readiness' => $readiness,
        'days_until_air' => now()->diffInDays($episode->air_date, false)
    ];

    if ($episode->status === 'aired') {
        $airedEpisodes[] = $episodeData;
    } elseif ($readiness['is_ready']) {
        $readyEpisodes[] = $episodeData;
    } else {
        $notReadyEpisodes[] = $episodeData;
    }
}

// Line 2993-2998: Statistics
$totalEpisodes = $episodesThisWeek->count();
$readyCount = count($readyEpisodes);
$notReadyCount = count($notReadyEpisodes);
$airedCount = count($airedEpisodes);
$readinessRate = $totalEpisodes > 0 ? round(($readyCount / ($totalEpisodes - $airedCount)) * 100, 2) : 0;
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "week_period": {
      "start": "2025-01-13",
      "end": "2025-01-19",
      "current_date": "2025-01-14"
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
          "episode_number": 1,
          "title": "Episode 1",
          "program_name": "Hope Musik",
          "air_date": "2025-01-18",
          "status": "ready_to_air",
          "current_workflow_state": "broadcasting",
          "readiness": {
            "is_ready": true,
            "checklist": {
              "status": {
                "label": "Episode Status",
                "status": "ready",
                "value": "ready_to_air"
              },
              "rundown": {
                "label": "Rundown",
                "status": "ready",
                "value": "Available"
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
              "sound_recording": {
                "label": "Sound Recording",
                "status": "ready",
                "value": "Completed"
              },
              "editor_work": {
                "label": "Editor Work",
                "status": "ready",
                "value": "Completed"
              },
              "quality_control": {
                "label": "Quality Control",
                "status": "ready",
                "value": "Approved"
              },
              "broadcasting_schedule": {
                "label": "Broadcasting Schedule",
                "status": "ready",
                "value": "Confirmed"
              }
            },
            "missing_items": [],
            "warnings": []
          },
          "days_until_air": 4
        }
      ],
      "not_ready": [
        {
          "id": 2,
          "episode_number": 2,
          "title": "Episode 2",
          "program_name": "Hope Musik",
          "air_date": "2025-01-19",
          "status": "in_production",
          "current_workflow_state": "editing",
          "readiness": {
            "is_ready": false,
            "checklist": {
              "status": {
                "label": "Episode Status",
                "status": "not_ready",
                "value": "in_production",
                "required": "ready_to_air"
              },
              "editor_work": {
                "label": "Editor Work",
                "status": "not_ready",
                "value": "In Progress"
              }
            },
            "missing_items": [
              "Episode status harus ready_to_air",
              "Editor work belum completed"
            ],
            "warnings": []
          },
          "days_until_air": 5
        }
      ],
      "aired": []
    }
  },
  "message": "Weekly airing control data retrieved successfully"
}
```

**Authorization:**
- ✅ Hanya user dengan role `Producer` yang bisa akses
- ✅ Hanya episodes dari program ProductionTeam Producer yang ditampilkan

---

### **✅ 2. Get Upcoming Episodes This Week**

**Status:** ✅ **SUDAH ADA**

**Endpoint:**
```
GET /api/live-tv/producer/episodes/upcoming-this-week
```

**Controller:** `ProducerController@getUpcomingEpisodesThisWeek`  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 3035-3107

**Route:**
- File: `routes/live_tv_api.php` line 676
- Route: `Route::get('/episodes/upcoming-this-week', [ProducerController::class, 'getUpcomingEpisodesThisWeek])`

**Fitur:**
- ✅ Get episodes yang akan tayang minggu ini
- ✅ Exclude episodes yang sudah aired
- ✅ Filter by readiness (optional: `ready_only=true`)
- ✅ Include readiness check untuk setiap episode
- ✅ Include deadlines summary
- ✅ Days until air untuk setiap episode

**Kode Verifikasi:**
```php
// Line 3047-3056: Get upcoming episodes
$weekStart = now()->startOfWeek();
$weekEnd = now()->endOfWeek();

$query = Episode::with(['program', 'deadlines', 'workflowStates'])
    ->whereHas('program.productionTeam', function ($q) use ($user) {
        $q->where('producer_id', $user->id);
    })
    ->whereBetween('air_date', [$weekStart, $weekEnd])
    ->where('status', '!=', 'aired')
    ->orderBy('air_date', 'asc');

// Line 3058-3065: Filter by readiness
if ($request->has('ready_only') && $request->boolean('ready_only')) {
    $episodes = $query->get()->filter(function ($episode) {
        return $this->checkEpisodeReadiness($episode)['is_ready'];
    })->values();
} else {
    $episodes = $query->get();
}
```

**Query Parameters:**
- `ready_only` (optional, boolean): Filter hanya episodes yang ready

**Response Example:**
```json
{
  "success": true,
  "data": {
    "week_period": {
      "start": "2025-01-13",
      "end": "2025-01-19"
    },
    "episodes": [
      {
        "id": 1,
        "episode_number": 1,
        "title": "Episode 1",
        "program_name": "Hope Musik",
        "program_id": 1,
        "air_date": "2025-01-18",
        "status": "ready_to_air",
        "current_workflow_state": "broadcasting",
        "readiness": {
          "is_ready": true,
          "checklist": {...},
          "missing_items": [],
          "warnings": []
        },
        "days_until_air": 4,
        "deadlines": {
          "total": 3,
          "completed": 3,
          "overdue": 0
        }
      }
    ],
    "count": 1
  },
  "message": "Upcoming episodes this week retrieved successfully"
}
```

---

### **✅ 3. Get Ready Episodes This Week**

**Status:** ✅ **SUDAH ADA**

**Endpoint:**
```
GET /api/live-tv/producer/episodes/ready-this-week
```

**Controller:** `ProducerController@getReadyEpisodesThisWeek`  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 3112-3174

**Route:**
- File: `routes/live_tv_api.php` line 677
- Route: `Route::get('/episodes/ready-this-week', [ProducerController::class, 'getReadyEpisodesThisWeek])`

**Fitur:**
- ✅ Get hanya episodes yang ready untuk tayang minggu ini
- ✅ Filter otomatis berdasarkan readiness check
- ✅ Exclude episodes yang sudah aired
- ✅ Include readiness details untuk setiap episode

**Kode Verifikasi:**
```php
// Line 3124-3134: Get episodes minggu ini
$weekStart = now()->startOfWeek();
$weekEnd = now()->endOfWeek();

$episodes = Episode::with(['program', 'deadlines', 'workflowStates'])
    ->whereHas('program.productionTeam', function ($q) use ($user) {
        $q->where('producer_id', $user->id);
    })
    ->whereBetween('air_date', [$weekStart, $weekEnd])
    ->where('status', '!=', 'aired')
    ->orderBy('air_date', 'asc')
    ->get();

// Line 3136-3153: Filter only ready episodes
$readyEpisodes = $episodes->filter(function ($episode) {
    return $this->checkEpisodeReadiness($episode)['is_ready'];
})->map(function ($episode) {
    $readiness = $this->checkEpisodeReadiness($episode);
    return [
        'id' => $episode->id,
        'episode_number' => $episode->episode_number,
        'title' => $episode->title,
        'program_name' => $episode->program->name,
        'program_id' => $episode->program_id,
        'air_date' => $episode->air_date,
        'status' => $episode->status,
        'current_workflow_state' => $episode->current_workflow_state,
        'readiness' => $readiness,
        'days_until_air' => now()->diffInDays($episode->air_date, false)
    ];
})->values();
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "week_period": {
      "start": "2025-01-13",
      "end": "2025-01-19"
    },
    "episodes": [
      {
        "id": 1,
        "episode_number": 1,
        "title": "Episode 1",
        "program_name": "Hope Musik",
        "program_id": 1,
        "air_date": "2025-01-18",
        "status": "ready_to_air",
        "current_workflow_state": "broadcasting",
        "readiness": {
          "is_ready": true,
          "checklist": {...},
          "missing_items": [],
          "warnings": []
        },
        "days_until_air": 4
      }
    ],
    "count": 1
  },
  "message": "Ready episodes this week retrieved successfully"
}
```

---

### **✅ 4. Episode Readiness Check**

**Status:** ✅ **SUDAH ADA**

**Method:** `ProducerController@checkEpisodeReadiness` (private method)  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 3180-3300+

**Fitur:**
- ✅ Comprehensive checklist untuk menentukan episode ready untuk tayang
- ✅ Check semua workflow steps:
  - Episode Status (harus `ready_to_air` atau `post_production`)
  - Rundown (harus ada)
  - Music Arrangement (harus approved)
  - Creative Work (harus approved)
  - Sound Recording (harus completed)
  - Editor Work (harus completed)
  - Quality Control (harus approved)
  - Broadcasting Schedule (harus confirmed)
- ✅ Missing items list
- ✅ Warnings list

**Kode Verifikasi:**
```php
// Line 3180-3187: Readiness structure
private function checkEpisodeReadiness(Episode $episode): array
{
    $readiness = [
        'is_ready' => false,
        'checklist' => [],
        'missing_items' => [],
        'warnings' => []
    ];

    // Line 3189-3199: Check 1 - Episode Status
    $statusReady = in_array($episode->status, ['ready_to_air', 'post_production']);
    $readiness['checklist']['status'] = [
        'label' => 'Episode Status',
        'status' => $statusReady ? 'ready' : 'not_ready',
        'value' => $episode->status,
        'required' => 'ready_to_air'
    ];
    if (!$statusReady) {
        $readiness['missing_items'][] = 'Episode status harus ready_to_air';
    }

    // Line 3201-3209: Check 2 - Rundown
    $hasRundown = !empty($episode->rundown);
    $readiness['checklist']['rundown'] = [
        'label' => 'Rundown',
        'status' => $hasRundown ? 'ready' : 'not_ready',
        'value' => $hasRundown ? 'Available' : 'Missing'
    ];
    if (!$hasRundown) {
        $readiness['missing_items'][] = 'Rundown belum tersedia';
    }

    // Check 3 - Music Arrangement (harus approved)
    // Check 4 - Creative Work (harus approved)
    // Check 5 - Sound Recording (harus completed)
    // Check 6 - Editor Work (harus completed)
    // Check 7 - Quality Control (harus approved)
    // Check 8 - Broadcasting Schedule (harus confirmed)

    // Line 3300+: Final check
    $readiness['is_ready'] = count($readiness['missing_items']) === 0;
    
    return $readiness;
}
```

**Readiness Checklist Items:**
1. ✅ **Episode Status** - Harus `ready_to_air` atau `post_production`
2. ✅ **Rundown** - Harus ada (tidak kosong)
3. ✅ **Music Arrangement** - Harus approved
4. ✅ **Creative Work** - Harus approved
5. ✅ **Sound Recording** - Harus completed
6. ✅ **Editor Work** - Harus completed
7. ✅ **Quality Control** - Harus approved
8. ✅ **Broadcasting Schedule** - Harus confirmed

---

## 📊 RINGKASAN VERIFIKASI

| No | Fitur | Status | Endpoint | Controller Method |
|---|---|---|---|---|
| 1 | Weekly Airing Control Dashboard | ✅ **ADA** | `GET /api/live-tv/producer/weekly-airing-control` | `ProducerController@getWeeklyAiringControl` |
| 2 | Upcoming Episodes This Week | ✅ **ADA** | `GET /api/live-tv/producer/episodes/upcoming-this-week` | `ProducerController@getUpcomingEpisodesThisWeek` |
| 3 | Ready Episodes This Week | ✅ **ADA** | `GET /api/live-tv/producer/episodes/ready-this-week` | `ProducerController@getReadyEpisodesThisWeek` |
| 4 | Episode Readiness Check | ✅ **ADA** | (Private method) | `ProducerController@checkEpisodeReadiness` |

---

## ✅ KESIMPULAN

**FITUR KONTROL TAYANG MINGGUAN SUDAH TERIMPLEMENTASI DENGAN LENGKAP:**

1. ✅ **Weekly Airing Control Dashboard**
   - Dashboard lengkap untuk kontrol tayang mingguan
   - Kategorisasi: ready, not ready, aired
   - Statistics: total, ready count, not ready count, readiness rate
   - Week period (start - end)

2. ✅ **Upcoming Episodes This Week**
   - List episodes yang akan tayang minggu ini
   - Filter by readiness (optional)
   - Include deadlines summary
   - Days until air

3. ✅ **Ready Episodes This Week**
   - List hanya episodes yang ready untuk tayang
   - Filter otomatis berdasarkan readiness check
   - Include readiness details

4. ✅ **Episode Readiness Check**
   - Comprehensive checklist (8 items)
   - Missing items list
   - Warnings list
   - Auto-determine if episode is ready

**CARA PRODUCER MENGONTROL TAYANG MINGGUAN:**

1. **Dashboard Overview:**
   ```
   GET /api/live-tv/producer/weekly-airing-control
   ```
   - Lihat semua episodes minggu ini
   - Lihat statistics: berapa ready, berapa not ready
   - Identifikasi episodes yang perlu perhatian

2. **Check Upcoming Episodes:**
   ```
   GET /api/live-tv/producer/episodes/upcoming-this-week
   ```
   - Lihat semua episodes yang akan tayang
   - Filter hanya ready: `?ready_only=true`
   - Check readiness details untuk setiap episode

3. **Check Ready Episodes:**
   ```
   GET /api/live-tv/producer/episodes/ready-this-week
   ```
   - Lihat hanya episodes yang sudah ready
   - Pastikan minimal 1 episode ready setiap minggu

4. **Monitor Readiness:**
   - Setiap episode memiliki readiness checklist
   - Producer bisa lihat apa yang masih missing
   - Producer bisa take action untuk complete missing items

**KONTROL MINGGUAN:**
- ✅ Producer bisa monitor episodes minggu ini
- ✅ Producer bisa lihat readiness status setiap episode
- ✅ Producer bisa identifikasi episodes yang belum ready
- ✅ Producer bisa pastikan minimal 1 episode ready setiap minggu
- ✅ Statistics membantu Producer track readiness rate

---

## 🔗 ENDPOINT TERKAIT

**Episode Detail (untuk melihat detail readiness):**
- `GET /api/live-tv/episodes/{id}` - Episode detail dengan readiness

**Monitoring (untuk track progress):**
- `GET /api/live-tv/producer/production-overview` - Production overview
- `GET /api/live-tv/producer/episodes` - All episodes dengan filter

---

**Diverifikasi oleh:** AI Assistant  
**Tanggal:** 2026-01-14  
**Status:** ✅ **FITUR KONTROL TAYANG MINGGUAN SUDAH ADA - LENGKAP DENGAN DASHBOARD & READINESS CHECK**
