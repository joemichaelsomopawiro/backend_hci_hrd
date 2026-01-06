# 📋 AUTO CREATION SAAT CREATE PROGRAM

**Dokumen ini menjelaskan apa saja yang otomatis dibuat saat Manager Program membuat program baru.**

---

## 🎯 OVERVIEW

Saat Manager Program membuat program melalui endpoint `POST /api/live-tv/programs`, sistem akan **otomatis** membuat beberapa data terkait:

---

## ✅ 1. PROGRAM RECORD

**Table:** `programs`

**Data yang dibuat:**
```php
[
    'name' => $request->name,
    'description' => $request->description,
    'category' => $request->category ?? 'regular',
    'manager_program_id' => $request->manager_program_id,
    'production_team_id' => $request->production_team_id,
    'start_date' => $request->start_date,
    'air_time' => $request->air_time,
    'duration_minutes' => $request->duration_minutes,
    'broadcast_channel' => $request->broadcast_channel,
    'target_views_per_episode' => $request->target_views_per_episode,
    'proposal_file_path' => $proposalFilePath, // Jika ada file upload
    'proposal_file_name' => $proposalFileName, // Jika ada file upload
    'status' => 'draft'
]
```

**Lokasi:** `app/Http/Controllers/Api/ProgramController.php` → `store()`

---

## ✅ 2. 53 EPISODES (AUTO-GENERATE)

**Table:** `episodes`

**Jumlah:** 53 episodes otomatis dibuat

**Per Episode (untuk setiap episode 1-53):**
```php
[
    'program_id' => $program->id,
    'episode_number' => $i, // 1 sampai 53
    'title' => "Episode {$i}",
    'description' => "Episode {$i} dari program {$program->name}",
    'air_date' => $airDate, // start_date + (i-1) minggu
    'production_date' => $airDate->copy()->subDays(7), // 7 hari sebelum tayang
    'status' => 'draft',
    'current_workflow_state' => 'program_created'
]
```

**Logika:**
- Episode 1: `air_date` = `start_date`
- Episode 2: `air_date` = `start_date` + 1 minggu
- Episode 3: `air_date` = `start_date` + 2 minggu
- ... dan seterusnya sampai Episode 53

**Lokasi:** 
- Controller: `app/Http/Controllers/Api/ProgramController.php` → `store()`
- Service: `app/Services/ProgramWorkflowService.php` → `createProgram()`
- Model: `app/Models/Program.php` → `generateEpisodes()`

---

## ✅ 3. DEADLINES (AUTO-GENERATE)

**Table:** `deadlines`

**Jumlah:** 5 deadlines per episode = **265 deadlines total** (53 episodes × 5 deadlines)

**Per Episode, sistem membuat 5 deadlines:**

### **3.1. Deadline Editor**
```php
[
    'episode_id' => $episode->id,
    'role' => 'editor',
    'deadline_date' => $airDate->copy()->subDays(7), // 7 hari sebelum tayang
    'description' => 'Deadline editing episode',
    'auto_generated' => true,
    'created_by' => auth()->id()
]
```

### **3.2. Deadline Creative**
```php
[
    'episode_id' => $episode->id,
    'role' => 'creative',
    'deadline_date' => $airDate->copy()->subDays(9), // 9 hari sebelum tayang
    'description' => 'Deadline creative work episode',
    'auto_generated' => true,
    'created_by' => auth()->id()
]
```

### **3.3. Deadline Production**
```php
[
    'episode_id' => $episode->id,
    'role' => 'production',
    'deadline_date' => $airDate->copy()->subDays(9), // 9 hari sebelum tayang
    'description' => 'Deadline production episode',
    'auto_generated' => true,
    'created_by' => auth()->id()
]
```

### **3.4. Deadline Music Arranger**
```php
[
    'episode_id' => $episode->id,
    'role' => 'musik_arr',
    'deadline_date' => $airDate->copy()->subDays(9), // 9 hari sebelum tayang
    'description' => 'Deadline music arrangement episode',
    'auto_generated' => true,
    'created_by' => auth()->id()
]
```

### **3.5. Deadline Sound Engineer**
```php
[
    'episode_id' => $episode->id,
    'role' => 'sound_eng',
    'deadline_date' => $airDate->copy()->subDays(9), // 9 hari sebelum tayang
    'description' => 'Deadline sound engineering episode',
    'auto_generated' => true,
    'created_by' => auth()->id()
]
```

**Lokasi:** `app/Models/Episode.php` → `generateDeadlines()`

---

## ✅ 4. WORKFLOW STATES (AUTO-GENERATE)

**Table:** `workflow_states`

**Jumlah:** 10 workflow states untuk 10 episode pertama (optimasi)

**Per Episode (untuk 10 episode pertama):**
```php
[
    'episode_id' => $episode->id,
    'current_state' => 'program_created',
    'assigned_to_role' => 'manager_program',
    'assigned_to_user_id' => $program->manager_program_id,
    'notes' => 'Program created, ready for production'
]
```

**Catatan:**
- Hanya 10 episode pertama yang dibuat workflow state saat create program
- Workflow state untuk episode lainnya akan dibuat on-demand saat diperlukan

**Lokasi:** `app/Services/ProgramWorkflowService.php` → `createInitialWorkflowState()`

---

## ✅ 5. NOTIFICATIONS (AUTO-GENERATE)

**Table:** `notifications`

**Jumlah:** 1 + N notifications (1 untuk Manager Program + N untuk Production Team Members)

### **5.1. Notification untuk Manager Program**
```php
[
    'user_id' => $program->manager_program_id,
    'type' => 'program_created',
    'title' => 'Program Created',
    'message' => "Program '{$program->name}' has been created successfully.",
    'program_id' => $program->id,
    'priority' => 'normal'
]
```

### **5.2. Notification untuk Production Team Members**
```php
// Untuk setiap member di production team (jika ada)
[
    'user_id' => $member->user_id,
    'type' => 'program_created',
    'title' => 'New Program Assigned',
    'message' => "You have been assigned to program '{$program->name}'.",
    'program_id' => $program->id,
    'priority' => 'normal'
]
```

**Lokasi:** `app/Services/ProgramWorkflowService.php` → `sendProgramCreatedNotifications()`

---

## ✅ 6. DEADLINE NOTIFICATIONS (AUTO-GENERATE)

**Table:** `notifications`

**Jumlah:** 5 notifications per episode × 53 episodes = **265 notifications**

**Per Deadline, sistem membuat notification untuk semua user dengan role yang sesuai:**

### **6.1. Deadline Editor Notification**
- Dikirim ke semua user dengan role `Editor`
- Isi: "Deadline baru untuk episode '{episode_title}' telah dibuat"

### **6.2. Deadline Creative Notification**
- Dikirim ke semua user dengan role `Creative`
- Isi: "Deadline baru untuk episode '{episode_title}' telah dibuat"

### **6.3. Deadline Production Notification**
- Dikirim ke semua user dengan role `Production`
- Isi: "Deadline baru untuk episode '{episode_title}' telah dibuat"

### **6.4. Deadline Music Arranger Notification**
- Dikirim ke semua user dengan role `Music Arranger`
- Isi: "Deadline baru untuk episode '{episode_title}' telah dibuat"

### **6.5. Deadline Sound Engineer Notification**
- Dikirim ke semua user dengan role `Sound Engineer`
- Isi: "Deadline baru untuk episode '{episode_title}' telah dibuat"

**Lokasi:** `app/Models/Episode.php` → `notifyDeadlineCreation()`

---

## 📊 RINGKASAN TOTAL DATA YANG DIBUAT

| Data Type | Jumlah | Keterangan |
|-----------|--------|------------|
| **Program** | 1 | Record program utama |
| **Episodes** | 53 | Semua episode auto-generate |
| **Deadlines** | 265 | 5 deadlines × 53 episodes |
| **Workflow States** | 10 | Hanya untuk 10 episode pertama |
| **Notifications (Program)** | 1 + N | 1 untuk Manager + N untuk Production Team |
| **Notifications (Deadlines)** | ~265 | ~5 per episode × 53 episodes |

**Total Records Created:** **~594+ records**

---

## 🔄 FLOW PROSES AUTO-CREATION

```
Manager Program Create Program
    ↓
1. Create Program Record (status: draft)
    ↓
2. Auto-generate 53 Episodes
    │
    ├─► Untuk setiap Episode (1-53):
    │   │
    │   ├─► Create Episode Record
    │   │   - episode_number: 1-53
    │   │   - air_date: start_date + (n-1) minggu
    │   │   - production_date: air_date - 7 hari
    │   │   - status: draft
    │   │
    │   ├─► Auto-generate 5 Deadlines
    │   │   - Editor (7 hari sebelum tayang)
    │   │   - Creative (9 hari sebelum tayang)
    │   │   - Production (9 hari sebelum tayang)
    │   │   - Music Arranger (9 hari sebelum tayang)
    │   │   - Sound Engineer (9 hari sebelum tayang)
    │   │
    │   ├─► Create Deadline Notifications
    │   │   - Untuk setiap role yang terkait
    │   │
    │   └─► Create Workflow State (hanya 10 pertama)
    │       - current_state: program_created
    │       - assigned_to_role: manager_program
    ↓
3. Create Initial Workflow States (10 episode pertama)
    ↓
4. Send Program Created Notifications
    - Ke Manager Program
    - Ke Production Team Members
    ↓
5. Clear Cache (programs & episodes)
```

---

## 📝 CONTOH REQUEST & RESPONSE

### **Request:**
```http
POST /api/live-tv/programs
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Program Musik Test",
  "description": "Program musik untuk testing",
  "category": "musik",
  "manager_program_id": 1,
  "production_team_id": 1,
  "start_date": "2025-01-15",
  "air_time": "20:00",
  "duration_minutes": 60,
  "broadcast_channel": "Hope Channel Indonesia",
  "target_views_per_episode": 10000
}
```

### **Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Test",
    "description": "Program musik untuk testing",
    "category": "musik",
    "status": "draft",
    "start_date": "2025-01-15",
    "air_time": "20:00:00",
    "duration_minutes": 60,
    "manager_program_id": 1,
    "production_team_id": 1,
    "episodes": [
      {
        "id": 1,
        "episode_number": 1,
        "title": "Episode 1",
        "air_date": "2025-01-15",
        "production_date": "2025-01-08",
        "status": "draft",
        "deadlines": [
          {
            "id": 1,
            "role": "editor",
            "deadline_date": "2025-01-08",
            "description": "Deadline editing episode"
          },
          {
            "id": 2,
            "role": "creative",
            "deadline_date": "2025-01-06",
            "description": "Deadline creative work episode"
          },
          // ... 3 deadlines lainnya
        ]
      },
      // ... 52 episodes lainnya
    ],
    "manager_program": {
      "id": 1,
      "name": "Manager Program Name"
    },
    "production_team": {
      "id": 1,
      "name": "Production Team Name"
    }
  },
  "message": "Program created successfully with 53 episodes generated"
}
```

---

## ⚠️ CATATAN PENTING

### **1. Database Transaction**
- Semua operasi di-wrap dalam `DB::transaction()`
- Jika ada error, semua perubahan akan di-rollback
- Memastikan data consistency

### **2. Performance**
- 53 episodes + 265 deadlines = banyak data yang dibuat
- Proses ini mungkin memakan waktu beberapa detik
- Disarankan menggunakan queue/job untuk production

### **3. Workflow States**
- Hanya 10 episode pertama yang dibuat workflow state
- Episode lainnya akan dibuat on-demand saat diperlukan
- Ini untuk optimasi performa

### **4. Notifications**
- Notifications dikirim ke semua user dengan role yang sesuai
- Jika ada banyak user dengan role yang sama, akan banyak notifications
- Pertimbangkan untuk batch notification atau queue

### **5. Cache Clearing**
- Cache untuk `programs` dan `episodes` di-clear setelah create
- Memastikan data terbaru terlihat oleh user

---

## 🔍 VERIFIKASI

Setelah create program, verifikasi:

1. ✅ **Program created** dengan status `draft`
2. ✅ **53 episodes** ter-generate
3. ✅ **265 deadlines** ter-generate (5 per episode)
4. ✅ **10 workflow states** ter-generate (untuk 10 episode pertama)
5. ✅ **Notifications** terkirim ke Manager Program
6. ✅ **Notifications** terkirim ke Production Team Members
7. ✅ **Deadline notifications** terkirim ke semua role yang terkait

---

## 📚 REFERENSI

- **Controller:** `app/Http/Controllers/Api/ProgramController.php`
- **Service:** `app/Services/ProgramWorkflowService.php`
- **Model Program:** `app/Models/Program.php`
- **Model Episode:** `app/Models/Episode.php`
- **Model Deadline:** `app/Models/Deadline.php`
- **Model WorkflowState:** `app/Models/WorkflowState.php`
- **Model Notification:** `app/Models/Notification.php`

---

**Last Updated:** 2025-01-15  
**Created By:** AI Assistant  
**Version:** 1.0

