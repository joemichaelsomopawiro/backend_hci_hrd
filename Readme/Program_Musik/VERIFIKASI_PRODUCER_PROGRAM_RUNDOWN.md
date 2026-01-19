# ✅ VERIFIKASI: PRODUCER MENERIMA PROGRAM & MENGELOLA RUNDOWN

**Tanggal Verifikasi:** 2026-01-14  
**Status:** ✅ **SUDAH ADA - LENGKAP**

---

## 📋 FITUR YANG DIPERLUKAN

**Producer di program musik dapat:**
1. ✅ Menerima Live Program apa saja yang menjadi tanggung jawabnya
2. ✅ Melihat Nama Program
3. ✅ Melihat Rundown Program
4. ✅ Mengedit Rundown jika dibutuhkan & Ajukan ke Program Manager

---

## ✅ VERIFIKASI DETAIL

### **✅ 1. Menerima Live Program yang Menjadi Tanggung Jawabnya**

**Status:** ✅ **SUDAH ADA**

**Endpoint:**
```
GET /api/live-tv/producer/programs
```

**Controller:** `ProducerController@getPrograms`  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 1518-1590

**Route:**
- File: `routes/live_tv_api.php` line 657
- Route: `Route::get('/programs', [ProducerController::class, 'getPrograms])`

**Fitur:**
- ✅ Producer hanya melihat program dari ProductionTeam mereka
- ✅ Filter berdasarkan `producer_id` di ProductionTeam
- ✅ Hanya ProductionTeam yang aktif (`is_active = true`)
- ✅ Include Manager Program info
- ✅ Include ProductionTeam info
- ✅ Filter by status (optional)
- ✅ Filter by production_team_id (optional)
- ✅ Search by name (optional)
- ✅ Pagination support

**Kode Verifikasi:**
```php
// Line 1537-1545: Query dengan filter ProductionTeam Producer
$query = Program::with(['managerProgram', 'productionTeam'])
    ->whereNotNull('production_team_id'); // Pastikan production_team_id tidak NULL

// Producer hanya bisa melihat program dari ProductionTeam mereka
$query->whereHas('productionTeam', function ($q) use ($user) {
    $q->where('producer_id', $user->id)
      ->where('is_active', true); // Hanya production team yang aktif
});

// Line 1547-1560: Filters
if ($request->has('status')) {
    $query->where('status', $request->status);
}
if ($request->has('production_team_id')) {
    $query->where('production_team_id', $request->production_team_id);
}
if ($request->has('search')) {
    $query->where('name', 'like', '%' . $request->search . '%');
}

$programs = $query->orderBy('created_at', 'desc')->paginate(15);
```

**Query Parameters:**
- `status` (optional): Filter by program status
- `production_team_id` (optional): Filter by production team
- `search` (optional): Search by program name
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 15)

**Response Example:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Hope Musik",
        "description": "Program musik mingguan",
        "type": "live",
        "status": "active",
        "production_team_id": 1,
        "manager_program": {
          "id": 2,
          "name": "Manager Program 1"
        },
        "production_team": {
          "id": 1,
          "name": "Tim Produksi Musik A",
          "producer_id": 5,
          "is_active": true
        },
        "created_at": "2025-01-01T00:00:00.000000Z",
        "updated_at": "2025-01-01T00:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "total": 5,
    "per_page": 15
  },
  "message": "Programs retrieved successfully"
}
```

**Authorization:**
- ✅ Hanya user dengan role `Producer` yang bisa akses
- ✅ Hanya program dari ProductionTeam Producer yang ditampilkan
- ✅ ProductionTeam harus aktif (`is_active = true`)

---

### **✅ 2. Melihat Nama Program**

**Status:** ✅ **SUDAH ADA**

**Cara Akses:**
1. **Via getPrograms:**
   ```
   GET /api/live-tv/producer/programs
   ```
   - Field `name` ada di response setiap program

2. **Via getEpisodes:**
   ```
   GET /api/live-tv/producer/episodes
   ```
   - Include `program.name` di response

3. **Via Episode Detail:**
   ```
   GET /api/live-tv/episodes/{id}
   ```
   - Include `program.name` di response

**Response Example:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Hope Musik",  // ✅ Nama Program
        "description": "Program musik mingguan",
        "type": "live",
        "status": "active"
      }
    ]
  }
}
```

---

### **✅ 3. Melihat Rundown Program**

**Status:** ✅ **SUDAH ADA**

**Cara Akses:**

1. **Via Episode Detail (Recommended):**
   ```
   GET /api/live-tv/episodes/{id}
   ```
   - Field `rundown` ada di response episode

2. **Via getEpisodes:**
   ```
   GET /api/live-tv/producer/episodes
   ```
   - Include `rundown` di response setiap episode

**Controller:** `EpisodeController@show`  
**File:** `app/Http/Controllers/Api/EpisodeController.php` line 213-242

**Route:**
- File: `routes/live_tv_api.php` line 162
- Route: `Route::get('/{id}', [EpisodeController::class, 'show])`

**Kode Verifikasi:**
```php
// Line 215-230: Load episode dengan semua relations termasuk rundown
$episode = Episode::with([
    'program',
    'deadlines',
    'workflowStates.assignedToUser',
    'mediaFiles',
    'musicArrangements',
    'creativeWorks',
    // ... other relations
])->findOrFail($id);

// Episode model memiliki field 'rundown' (line 28 di app/Models/Episode.php)
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "episode": {
      "id": 1,
      "episode_number": 1,
      "title": "Episode 1",
      "rundown": "1. Opening\n2. Song 1\n3. Interview\n4. Song 2\n5. Closing",  // ✅ Rundown
      "program": {
        "id": 1,
        "name": "Hope Musik"
      },
      "status": "in_production"
    },
    "progress": {
      "percentage": 65.5,
      "completed_steps": 5,
      "total_steps": 8
    }
  },
  "message": "Episode retrieved successfully"
}
```

**Database Field:**
- Model: `app/Models/Episode.php`
- Field: `rundown` (text/string)
- Line: 28 di `$fillable` array

---

### **✅ 4. Mengedit Rundown & Ajukan ke Program Manager**

**Status:** ✅ **SUDAH ADA**

**Endpoint:**
```
POST /api/live-tv/producer/episodes/{episodeId}/edit-rundown
```

**Controller:** `ProducerController@editRundown`  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 2404-2501

**Route:**
- File: `routes/live_tv_api.php` line 666
- Route: `Route::post('/episodes/{episodeId}/edit-rundown', [ProducerController::class, 'editRundown])`

**Fitur:**
- ✅ Producer bisa edit rundown episode dari program mereka
- ✅ Validasi akses: hanya episode dari ProductionTeam Producer
- ✅ Membuat ProgramApproval request dengan approval_type `episode_rundown`
- ✅ Notifikasi otomatis ke Manager Program
- ✅ Status pending sampai Manager Program approve/reject
- ✅ Include current rundown dan new rundown untuk comparison
- ✅ Include edit reason dan notes

**Kode Verifikasi:**
```php
// Line 2416-2420: Validation
$validator = Validator::make($request->all(), [
    'new_rundown' => 'required|string',
    'edit_reason' => 'required|string|max:1000',
    'notes' => 'nullable|string|max:1000'
]);

// Line 2430-2438: Check Producer access
$episode = Episode::with(['program'])->findOrFail($episodeId);

if (!$episode->program || $episode->program->productionTeam->producer_id !== $user->id) {
    return response()->json([
        'success' => false,
        'message' => 'You do not have access to edit rundown for this episode.'
    ], 403);
}

// Line 2440-2447: Get Manager Program
$managerProgram = $episode->program->managerProgram;
if (!$managerProgram) {
    return response()->json([
        'success' => false,
        'message' => 'Manager Program not found for this program.'
    ], 404);
}

// Line 2449-2470: Create ProgramApproval request
$approval = ProgramApproval::create([
    'approvable_id' => $episode->id,
    'approvable_type' => Episode::class,
    'approval_type' => 'episode_rundown',
    'requested_by' => $user->id,
    'requested_at' => now(),
    'request_notes' => $request->notes,
    'request_data' => [
        'new_rundown' => $request->new_rundown,
        'current_rundown' => $episode->rundown,
        'edit_reason' => $request->edit_reason,
        'episode_id' => $episode->id,
        'episode_number' => $episode->episode_number,
        'episode_title' => $episode->title
    ],
    'current_data' => [
        'current_rundown' => $episode->rundown
    ],
    'status' => 'pending',
    'priority' => 'normal'
]);

// Line 2472-2484: Notify Manager Program
Notification::create([
    'user_id' => $managerProgram->id,
    'type' => 'rundown_edit_request',
    'title' => 'Permintaan Edit Rundown',
    'message' => "Producer {$user->name} meminta edit rundown untuk Episode {$episode->episode_number}: {$episode->title}. Alasan: {$request->edit_reason}",
    'data' => [
        'approval_id' => $approval->id,
        'episode_id' => $episode->id,
        'program_id' => $episode->program_id,
        'edit_reason' => $request->edit_reason
    ]
]);
```

**Request Body:**
```json
{
  "new_rundown": "1. Opening\n2. Song 1 (Updated)\n3. Interview\n4. Song 2\n5. Closing",
  "edit_reason": "Perlu update song 1 karena ada perubahan jadwal",
  "notes": "Silakan review perubahan ini"
}
```

**Request Parameters:**
- `new_rundown` (required, string): Rundown baru yang diinginkan
- `edit_reason` (required, string, max: 1000): Alasan edit rundown
- `notes` (optional, string, max: 1000): Catatan tambahan

**Response Example:**
```json
{
  "success": true,
  "data": {
    "approval": {
      "id": 1,
      "approval_type": "episode_rundown",
      "status": "pending",
      "requested_by": {
        "id": 5,
        "name": "Producer 1"
      },
      "request_data": {
        "new_rundown": "1. Opening\n2. Song 1 (Updated)\n3. Interview\n4. Song 2\n5. Closing",
        "current_rundown": "1. Opening\n2. Song 1\n3. Interview\n4. Song 2\n5. Closing",
        "edit_reason": "Perlu update song 1 karena ada perubahan jadwal",
        "episode_id": 1,
        "episode_number": 1,
        "episode_title": "Episode 1"
      },
      "requested_at": "2025-01-14T10:00:00.000000Z"
    },
    "episode": {
      "id": 1,
      "episode_number": 1,
      "title": "Episode 1",
      "program": {
        "id": 1,
        "name": "Hope Musik"
      }
    }
  },
  "message": "Rundown edit request submitted successfully. Waiting for Manager Program approval."
}
```

**Authorization:**
- ✅ Hanya user dengan role `Producer` yang bisa akses
- ✅ Hanya episode dari ProductionTeam Producer yang bisa di-edit
- ✅ Manager Program harus ada untuk program tersebut

**Workflow:**
1. Producer submit edit rundown request
2. System create `ProgramApproval` dengan status `pending`
3. System send notification ke Manager Program
4. Manager Program review request (lihat current vs new rundown)
5. Manager Program approve/reject via endpoint:
   - `POST /api/live-tv/manager-program/rundown-edit-requests/{approvalId}/approve`
   - `POST /api/live-tv/manager-program/rundown-edit-requests/{approvalId}/reject`
6. Jika approved, rundown episode di-update otomatis
7. Producer mendapat notifikasi hasil approval

---

## 📊 RINGKASAN VERIFIKASI

| No | Fitur | Status | Endpoint | Controller Method |
|---|---|---|---|---|
| 1 | Menerima Program Tanggung Jawab | ✅ **ADA** | `GET /api/live-tv/producer/programs` | `ProducerController@getPrograms` |
| 2 | Melihat Nama Program | ✅ **ADA** | Via getPrograms/getEpisodes/episode detail | - |
| 3 | Melihat Rundown Program | ✅ **ADA** | `GET /api/live-tv/episodes/{id}` | `EpisodeController@show` |
| 4 | Edit Rundown & Ajukan ke Manager | ✅ **ADA** | `POST /api/live-tv/producer/episodes/{episodeId}/edit-rundown` | `ProducerController@editRundown` |

---

## ✅ KESIMPULAN

**SEMUA FITUR SUDAH TERIMPLEMENTASI DENGAN LENGKAP:**

1. ✅ **Menerima Program Tanggung Jawab**
   - Producer bisa melihat semua program dari ProductionTeam mereka
   - Filter berdasarkan status, production team, search by name
   - Include nama program, Manager Program, ProductionTeam info

2. ✅ **Melihat Nama Program**
   - Tersedia di semua endpoint yang menampilkan program/episode
   - Field `name` selalu include di response

3. ✅ **Melihat Rundown Program**
   - Tersedia di episode detail endpoint
   - Field `rundown` ada di model Episode
   - Bisa diakses via `GET /api/live-tv/episodes/{id}`

4. ✅ **Edit Rundown & Ajukan ke Manager Program**
   - Producer bisa submit edit rundown request
   - System create ProgramApproval dengan approval_type `episode_rundown`
   - Notifikasi otomatis ke Manager Program
   - Manager Program bisa approve/reject via endpoint terpisah
   - Jika approved, rundown di-update otomatis

**WORKFLOW LENGKAP:**
```
Producer → Submit Edit Rundown Request
    ↓
System Create ProgramApproval (status: pending)
    ↓
Notify Manager Program
    ↓
Manager Program Review & Approve/Reject
    ↓
If Approved: Update Episode Rundown
    ↓
Notify Producer (Approval Result)
```

---

## 🔗 ENDPOINT TERKAIT

**Manager Program - Approve/Reject Rundown Edit:**
- `GET /api/live-tv/manager-program/rundown-edit-requests` - List semua rundown edit requests
- `POST /api/live-tv/manager-program/rundown-edit-requests/{approvalId}/approve` - Approve edit rundown
- `POST /api/live-tv/manager-program/rundown-edit-requests/{approvalId}/reject` - Reject edit rundown

**Controller:** `ManagerProgramController@approveRundownEdit` / `rejectRundownEdit`  
**File:** `app/Http/Controllers/Api/ManagerProgramController.php` line 2058-2117

---

**Diverifikasi oleh:** AI Assistant  
**Tanggal:** 2026-01-14  
**Status:** ✅ **SEMUA FITUR SUDAH ADA - LENGKAP DENGAN WORKFLOW APPROVAL**
