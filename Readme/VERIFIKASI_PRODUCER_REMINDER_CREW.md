# ✅ VERIFIKASI: PRODUCER DAPAT MENGINGATKAN MELALUI SISTEM SETIAP CREW YANG MENJADI TIMNYA

**Tanggal Verifikasi:** 2026-01-14  
**Status:** ✅ **SUDAH ADA - LENGKAP DENGAN 3 MODE PENGIRIMAN**

---

## 📋 FITUR YANG DIPERLUKAN

**Producer di program musik dapat mengingatkan melalui sistem setiap crew yang menjadi timnya**

---

## ✅ VERIFIKASI DETAIL

### **✅ 1. Send Reminder to Crew**

**Status:** ✅ **SUDAH ADA**

**Endpoint:**
```
POST /api/live-tv/producer/send-reminder-to-crew
```

**Controller:** `ProducerController@sendReminderToCrew`  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 2821-2934

**Route:**
- File: `routes/live_tv_api.php` line 672
- Route: `Route::post('/send-reminder-to-crew', [ProducerController::class, 'sendReminderToCrew])`

**Fitur:**
- ✅ Producer bisa kirim reminder ke crew members
- ✅ 3 mode pengiriman:
  1. **Specific Crew Members** - Kirim ke crew member IDs tertentu
  2. **By Role** - Kirim ke semua crew dengan role tertentu
  3. **All Crew Members** - Kirim ke semua active crew members
- ✅ Validasi akses: hanya episode dari ProductionTeam Producer
- ✅ Create Notification untuk setiap crew member
- ✅ Priority level: low, normal, high, urgent
- ✅ Include episode context dalam reminder
- ✅ Return count crew yang dikirimi reminder

**Kode Verifikasi:**
```php
// Line 2821-2834: Authorization & Validation
public function sendReminderToCrew(Request $request): JsonResponse
{
    try {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Producer') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'crew_member_ids' => 'nullable|array',
            'crew_member_ids.*' => 'exists:users,id',
            'role' => 'nullable|string|in:creative,musik_arr,sound_eng,production,editor,art_set_design,graphic_design,promotion,broadcasting,quality_control',
            'message' => 'required|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ]);

        // Line 2836-2850: Validate Producer access
        $episode = Episode::with(['program.productionTeam'])->findOrFail($request->episode_id);

        if ($episode->program->productionTeam->producer_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: This episode is not from your production team.'
            ], 403);
        }

        // Line 2862-2889: 3 Mode Pengiriman
        $crewMembers = [];

        // Mode 1: Specific Crew Member IDs
        if ($request->has('crew_member_ids') && count($request->crew_member_ids) > 0) {
            $crewMembers = \App\Models\User::whereIn('id', $request->crew_member_ids)
                ->whereHas('productionTeamMembers', function($q) use ($episode) {
                    $q->where('production_team_id', $episode->program->productionTeam->id)
                      ->where('is_active', true);
                })
                ->get();
        }
        // Mode 2: By Role
        elseif ($request->has('role')) {
            $crewMembers = $episode->program->productionTeam->members()
                ->where('role', $request->role)
                ->where('is_active', true)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();
        }
        // Mode 3: All Active Crew Members
        else {
            $crewMembers = $episode->program->productionTeam->members()
                ->where('is_active', true)
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();
        }

        // Line 2898-2916: Create Notification untuk setiap crew member
        $sentCount = 0;
        foreach ($crewMembers as $crewMember) {
            Notification::create([
                'user_id' => $crewMember->id,
                'type' => 'producer_reminder',
                'title' => 'Reminder dari Producer',
                'message' => $request->message,
                'episode_id' => $episode->id,
                'program_id' => $episode->program_id,
                'priority' => $request->priority ?? 'normal',
                'data' => [
                    'episode_number' => $episode->episode_number,
                    'episode_title' => $episode->title,
                    'reminder_from' => $user->name,
                    'reminder_from_role' => 'Producer'
                ]
            ]);
            $sentCount++;
        }
```

**Request Body:**
```json
{
  "episode_id": 1,
  "message": "Jangan lupa deadline editing episode ini besok!",
  "priority": "high",
  "crew_member_ids": [10, 11, 12]  // Optional: specific crew members
}
```

**Atau dengan role:**
```json
{
  "episode_id": 1,
  "message": "Deadline creative work sudah dekat, mohon segera selesaikan!",
  "priority": "urgent",
  "role": "creative"  // Optional: kirim ke semua crew dengan role ini
}
```

**Atau ke semua crew:**
```json
{
  "episode_id": 1,
  "message": "Reminder untuk semua tim: deadline episode ini sudah dekat!",
  "priority": "normal"
  // Tidak perlu crew_member_ids atau role, akan kirim ke semua active crew
}
```

**Request Parameters:**
- `episode_id` (required, integer): ID episode yang terkait
- `message` (required, string, max: 1000): Pesan reminder
- `priority` (optional, in:low,normal,high,urgent): Priority level (default: normal)
- `crew_member_ids` (optional, array): Specific crew member IDs untuk kirim reminder
- `role` (optional, string): Role untuk kirim ke semua crew dengan role tersebut
  - Valid roles: `creative`, `musik_arr`, `sound_eng`, `production`, `editor`, `art_set_design`, `graphic_design`, `promotion`, `broadcasting`, `quality_control`

**Response Example:**
```json
{
  "success": true,
  "data": {
    "episode_id": 1,
    "reminder_sent_to": 3,
    "crew_members": {
      "10": "Creative 1",
      "11": "Creative 2",
      "12": "Music Arranger"
    }
  },
  "message": "Reminder sent successfully to 3 crew member(s)."
}
```

**Authorization:**
- ✅ Hanya user dengan role `Producer` yang bisa akses
- ✅ Hanya episode dari ProductionTeam Producer yang bisa digunakan
- ✅ Hanya crew members dari ProductionTeam yang sama yang bisa dikirimi reminder
- ✅ Hanya active crew members yang bisa dikirimi reminder

**3 Mode Pengiriman:**

1. **Mode 1: Specific Crew Members**
   - Kirim ke crew member IDs tertentu
   - Validasi: crew members harus dari ProductionTeam yang sama
   - Validasi: crew members harus active

2. **Mode 2: By Role**
   - Kirim ke semua crew dengan role tertentu
   - Contoh: kirim ke semua `creative`, semua `editor`, dll
   - Validasi: hanya active crew members

3. **Mode 3: All Crew Members**
   - Kirim ke semua active crew members
   - Tidak perlu specify crew_member_ids atau role
   - Default jika tidak ada filter

---

### **✅ 2. Get Crew Members**

**Status:** ✅ **SUDAH ADA**

**Endpoint:**
```
GET /api/live-tv/producer/crew-members
```

**Controller:** `ProducerController@getCrewMembers`  
**File:** `app/Http/Controllers/Api/ProducerController.php` line 2775-2815

**Route:**
- File: `routes/live_tv_api.php` line 673
- Route: `Route::get('/crew-members', [ProducerController::class, 'getCrewMembers])`

**Fitur:**
- ✅ Get semua crew members dari ProductionTeam Producer
- ✅ Include user info: id, name, role, role_label, is_active
- ✅ Filter exclude manager (optional)
- ✅ Unique by user ID (jika user ada di multiple teams)

**Kode Verifikasi:**
```php
// Line 2783-2804: Get crew members
$productionTeamIds = \App\Models\ProductionTeam::where('producer_id', $user->id)
    ->pluck('id');

$query = \App\Models\ProductionTeamMember::whereIn('production_team_id', $productionTeamIds)
    ->with('user');

if ($request->boolean('exclude_manager')) {
    $query->where('role', '!=', 'manager_program');
}

$members = $query->get()->map(function ($member) {
    return [
        'id' => $member->user->id,
        'name' => $member->user->name,
        'role' => $member->role,
        'role_label' => $member->role_label ?? $member->role,
        'is_active' => $member->is_active
    ];
})->unique('id')->values();
```

**Query Parameters:**
- `exclude_manager` (optional, boolean): Exclude manager program dari list

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "Creative 1",
      "role": "creative",
      "role_label": "Creative",
      "is_active": true
    },
    {
      "id": 11,
      "name": "Music Arranger",
      "role": "musik_arr",
      "role_label": "Music Arranger",
      "is_active": true
    },
    {
      "id": 12,
      "name": "Editor 1",
      "role": "editor",
      "role_label": "Editor",
      "is_active": true
    }
  ],
  "message": "Crew members retrieved successfully"
}
```

---

## 📊 RINGKASAN VERIFIKASI

| No | Fitur | Status | Endpoint | Controller Method |
|---|---|---|---|---|
| 1 | Send Reminder to Crew | ✅ **ADA** | `POST /api/live-tv/producer/send-reminder-to-crew` | `ProducerController@sendReminderToCrew` |
| 2 | Get Crew Members | ✅ **ADA** | `GET /api/live-tv/producer/crew-members` | `ProducerController@getCrewMembers` |

---

## ✅ KESIMPULAN

**FITUR REMINDER CREW SUDAH TERIMPLEMENTASI DENGAN LENGKAP:**

1. ✅ **Send Reminder to Crew**
   - Producer bisa kirim reminder ke crew members
   - 3 mode pengiriman: specific crew, by role, all crew
   - Create Notification untuk setiap crew member
   - Priority level: low, normal, high, urgent
   - Include episode context dalam reminder
   - Validasi akses: hanya episode dari ProductionTeam Producer

2. ✅ **Get Crew Members**
   - Get semua crew members dari ProductionTeam Producer
   - Include user info: id, name, role, is_active
   - Filter exclude manager (optional)
   - Unique by user ID

**CARA PRODUCER MENGINGATKAN CREW:**

1. **Get Crew Members (Optional):**
   ```
   GET /api/live-tv/producer/crew-members
   ```
   - Lihat list semua crew members
   - Pilih crew members yang akan dikirimi reminder

2. **Send Reminder:**
   ```
   POST /api/live-tv/producer/send-reminder-to-crew
   ```
   - **Mode 1 - Specific Crew:**
     ```json
     {
       "episode_id": 1,
       "message": "Reminder deadline editing!",
       "crew_member_ids": [10, 11, 12]
     }
     ```
   
   - **Mode 2 - By Role:**
     ```json
     {
       "episode_id": 1,
       "message": "Reminder deadline creative work!",
       "role": "creative"
     }
     ```
   
   - **Mode 3 - All Crew:**
     ```json
     {
       "episode_id": 1,
       "message": "Reminder untuk semua tim!",
       "priority": "high"
     }
     ```

**FITUR REMINDER:**
- ✅ Producer bisa kirim reminder ke crew members
- ✅ 3 mode pengiriman: specific, by role, all crew
- ✅ Priority level: low, normal, high, urgent
- ✅ Create Notification untuk setiap crew member
- ✅ Include episode context: episode_number, episode_title
- ✅ Include reminder from: Producer name, role
- ✅ Validasi akses: hanya episode dari ProductionTeam Producer
- ✅ Validasi crew: hanya crew dari ProductionTeam yang sama
- ✅ Validasi active: hanya active crew members

**NOTIFICATION DETAILS:**
- Type: `producer_reminder`
- Title: `Reminder dari Producer`
- Message: Custom message dari Producer
- Priority: low, normal, high, urgent
- Episode ID & Program ID: Context episode
- Data:
  - `episode_number`: Episode number
  - `episode_title`: Episode title
  - `reminder_from`: Producer name
  - `reminder_from_role`: "Producer"

---

## 🔗 ENDPOINT TERKAIT

**Notification (untuk crew melihat reminder):**
- `GET /api/notifications` - Get notifications untuk user
- `GET /api/notifications/unread` - Get unread notifications

**Episode Detail (untuk context reminder):**
- `GET /api/live-tv/episodes/{id}` - Episode detail

---

**Diverifikasi oleh:** AI Assistant  
**Tanggal:** 2026-01-14  
**Status:** ✅ **FITUR REMINDER CREW SUDAH ADA - LENGKAP DENGAN 3 MODE PENGIRIMAN**
