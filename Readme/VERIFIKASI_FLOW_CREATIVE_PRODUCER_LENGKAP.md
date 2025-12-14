# ✅ Verifikasi Flow Lengkap: Creative → Producer

**Tanggal:** 12 Desember 2025  
**Status:** ✅ **SEMUA FLOW SUDAH DIIMPLEMENTASIKAN & AMAN**

---

## 📋 Ringkasan Eksekutif

Semua flow Creative dan Producer setelah Creative submit sudah **LENGKAP** dan **AMAN**. Semua fitur yang diminta sudah diimplementasikan dengan benar.

---

## 🔄 FLOW LENGKAP

### **FLOW 1: Creative - Submit Creative Work**

**Status:** ✅ **LENGKAP & AMAN**

#### **1.1. Creative: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `creative_work_created` - Creative work task dibuat setelah arrangement approved

**Endpoint:** `GET /api/notifications`

---

#### **1.2. Creative: Terima Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/creative/works/{id}/accept-work`

**Flow:**
- ✅ Status: `draft` → `in_progress`
- ✅ Creative siap untuk mulai bekerja

**Controller:** `CreativeController::acceptWork()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 375-416)

---

#### **1.3. Creative: Tulis Script Cerita Video Klip Lagu**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "script_content": "Script cerita video klip lagu..."
}
```

**Field:** `script_content` (text)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **1.4. Creative: Buat Storyboard**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "storyboard_data": {
    "scenes": [...]
  }
}
```

**Field:** `storyboard_data` (JSON)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **1.5. Creative: Input Jadwal Rekaman Suara**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "recording_schedule": "2025-12-20 10:00:00"
}
```

**Field:** `recording_schedule` (datetime)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **1.6. Creative: Input Jadwal Syuting**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "shooting_schedule": "2025-12-25 08:00:00"
}
```

**Field:** `shooting_schedule` (datetime)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **1.7. Creative: Lokasi Syuting**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "shooting_location": "Studio HCI, Jakarta"
}
```

**Field:** `shooting_location` (string)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **1.8. Creative: Buat Budget Bayar Talent**

**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}`

**Request Body:**
```json
{
  "budget_data": {
    "talent_budget": 5000000,
    "production_budget": 3000000,
    "other_budget": 1000000
  }
}
```

**Field:** `budget_data` (JSON)

**Controller:** `CreativeController::update()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 139-175)

---

#### **1.9. Creative: Selesaikan Pekerjaan**

**Endpoint:** `POST /api/live-tv/roles/creative/works/{id}/complete-work`

**Request Body:**
```json
{
  "script_content": "Script cerita video klip lagu...",
  "storyboard_data": {...},
  "recording_schedule": "2025-12-20 10:00:00",
  "shooting_schedule": "2025-12-25 08:00:00",
  "shooting_location": "Studio HCI, Jakarta",
  "budget_data": {
    "talent_budget": 5000000,
    "production_budget": 3000000,
    "other_budget": 1000000
  },
  "completion_notes": "Semua pekerjaan selesai"
}
```

**Flow:**
- ✅ Validasi: Semua field required harus ada
- ✅ Status: `in_progress` → `submitted`
- ✅ Notifikasi ke Producer: `creative_work_submitted`
- ✅ Producer bisa review creative work

**Controller:** `CreativeController::completeWork()`

**File:** `app/Http/Controllers/Api/CreativeController.php` (line 423-487)

---

#### **1.10. Creative: Ajukan ke Producer**

**Status:** ✅ **AUTO-SUBMIT**

Setelah `completeWork()`, creative work otomatis di-submit ke Producer dengan status `submitted`.

---

### **FLOW 2: Producer - Review Creative Work**

**Status:** ✅ **LENGKAP & AMAN**

#### **2.1. Producer: Terima Notifikasi**

**Notifikasi yang Diterima:**
- ✅ `creative_work_submitted` - Creative work sudah di-submit

**Endpoint:** `GET /api/live-tv/producer/approvals`

---

#### **2.2. Producer: Cek Script**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/review`

**Request Body:**
```json
{
  "script_approved": true,
  "script_review_notes": "Script sudah bagus, approved!"
}
```

**Flow:**
- ✅ Producer review script
- ✅ Field: `script_approved` (boolean)
- ✅ Field: `script_review_notes` (string)

**Controller:** `ProducerController::reviewCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2268-2400)

---

#### **2.3. Producer: Cek Storyboard**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/review`

**Request Body:**
```json
{
  "storyboard_approved": true,
  "storyboard_review_notes": "Storyboard sudah sesuai, approved!"
}
```

**Flow:**
- ✅ Producer review storyboard
- ✅ Field: `storyboard_approved` (boolean)
- ✅ Field: `storyboard_review_notes` (string)

**Controller:** `ProducerController::reviewCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2268-2400)

---

#### **2.4. Producer: Cek Budget**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/review`

**Request Body:**
```json
{
  "budget_approved": true,
  "budget_review_notes": "Budget sudah sesuai, approved!"
}
```

**Flow:**
- ✅ Producer review budget
- ✅ Field: `budget_approved` (boolean)
- ✅ Field: `budget_review_notes` (string)
- ✅ Producer dapat edit budget langsung jika diperlukan

**Controller:** `ProducerController::reviewCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2268-2400)

---

#### **2.5. Producer: Tambahkan Tim Syuting**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Request Body:**
```json
{
  "team_type": "shooting",
  "team_member_ids": [8, 9, 10, 11],
  "team_notes": "Tim syuting untuk episode ini"
}
```

**Flow:**
- ✅ Producer assign shooting team
- ✅ Ambil semua crew program selain manager
- ✅ Create `ProductionTeamAssignment` dengan `team_type: 'shooting'`
- ✅ Notifikasi ke team members

**Controller:** `ProducerController::assignTeamToCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2343-2453)

**Alternatif Endpoint:**
- ✅ `POST /api/live-tv/producer/creative-works/{id}/assign-production-teams` - Assign multiple teams sekaligus

**Request Body:**
```json
{
  "shooting_team_ids": [8, 9, 10],
  "setting_team_ids": [11, 12, 13],
  "recording_team_ids": [14, 15],
  "shooting_team_notes": "Tim syuting",
  "setting_team_notes": "Tim setting",
  "recording_team_notes": "Tim rekam vokal"
}
```

**Controller:** `ProducerController::assignProductionTeams()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 1272-1418)

---

#### **2.6. Producer: Tambahkan Tim Setting**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Request Body:**
```json
{
  "team_type": "setting",
  "team_member_ids": [11, 12, 13],
  "team_notes": "Tim setting untuk episode ini (boleh sama dengan tim syuting)"
}
```

**Flow:**
- ✅ Producer assign setting team
- ✅ Ambil semua crew program selain manager
- ✅ Boleh sama dengan tim syuting
- ✅ Create `ProductionTeamAssignment` dengan `team_type: 'setting'`
- ✅ Notifikasi ke team members

**Controller:** `ProducerController::assignTeamToCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2343-2453)

---

#### **2.7. Producer: Dapat Cancel Jadwal Syuting**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/cancel-shooting`

**Request Body:**
```json
{
  "cancellation_reason": "Cancel karena cuaca buruk, akan di-reschedule",
  "new_shooting_schedule": "2025-12-26 08:00:00"
}
```

**Flow:**
- ✅ Producer dapat cancel jadwal syuting jika terjadi kendala
- ✅ Status shooting schedule: `cancelled`
- ✅ Include cancellation reason
- ✅ Optional: new shooting schedule
- ✅ Cancel shooting team assignments
- ✅ Notifikasi ke team members

**Controller:** `ProducerController::cancelShootingSchedule()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2559-2652)

---

#### **2.8. Producer: Dapat Mengganti Tim Syuting Secara Dadakan**

**Endpoints:**
- ✅ `PUT /api/live-tv/producer/team-assignments/{assignmentId}/replace-team` - Replace team members
- ✅ `PUT /api/live-tv/producer/schedules/{scheduleId}/emergency-reassign-team` - Emergency reassign team

**Request Body (Replace Team):**
```json
{
  "new_team_member_ids": [16, 17, 18],
  "replacement_reason": "Team lama tidak bisa hadir, perlu ganti team"
}
```

**Request Body (Emergency Reassign):**
```json
{
  "team_type": "shooting",
  "new_team_member_ids": [16, 17, 18],
  "reason": "Team lama tidak bisa hadir, perlu ganti team",
  "notes": "Team baru sudah dikonfirmasi"
}
```

**Flow:**
- ✅ Producer dapat mengganti tim syuting secara dadakan
- ✅ Cancel old team assignment
- ✅ Create new team assignment
- ✅ Notifikasi ke old team (removed) dan new team (assigned)
- ✅ Include reason untuk replacement

**Controller:** 
- `ProducerController::replaceTeamMembers()`
- `ProducerController::emergencyReassignTeam()`

**File:** 
- `app/Http/Controllers/Api/ProducerController.php` (line 2455-2553)
- `app/Http/Controllers/Api/ProducerController.php` (line 1423-1513)

---

#### **2.9. Producer: Tambahkan Tim Rekam Vokal**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Request Body:**
```json
{
  "team_type": "recording",
  "team_member_ids": [14, 15],
  "team_notes": "Tim rekam vokal untuk episode ini"
}
```

**Flow:**
- ✅ Producer assign recording team
- ✅ Ambil semua crew program selain manager
- ✅ Create `ProductionTeamAssignment` dengan `team_type: 'recording'`
- ✅ Notifikasi ke team members

**Controller:** `ProducerController::assignTeamToCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2343-2453)

---

#### **2.10. Producer: Dapat Mengedit Langsung Jika Diperlukan**

**Endpoint:** `PUT /api/live-tv/producer/creative-works/{id}/edit`

**Request Body:**
```json
{
  "script_content": "Script yang diedit oleh Producer...",
  "storyboard_data": {...},
  "budget_data": {...},
  "recording_schedule": "2025-12-21 10:00:00",
  "shooting_schedule": "2025-12-26 08:00:00",
  "shooting_location": "Lokasi baru",
  "edit_notes": "Producer mengedit script dan budget"
}
```

**Flow:**
- ✅ Producer dapat mengedit creative work langsung
- ✅ Edit script, storyboard, budget, schedules, location
- ✅ Status: `submitted` / `rejected` / `revised` → tetap atau `revised`
- ✅ Notifikasi ke Creative: `creative_work_edited_by_producer`
- ✅ Include edit notes

**Controller:** `ProducerController::editCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2658-2761)

---

#### **2.11. Producer: Jika Ada Tambahan Budget Khusus Ajukan ke Manager Program**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/request-special-budget`

**Request Body:**
```json
{
  "special_budget_amount": 2000000,
  "special_budget_reason": "Perlu tambahan budget untuk talent khusus",
  "priority": "high"
}
```

**Flow:**
- ✅ Producer dapat ajukan budget khusus ke Manager Program
- ✅ Create `ProgramApproval` dengan `approval_type: 'special_budget'`
- ✅ Status: `pending`
- ✅ Notifikasi ke Manager Program: `special_budget_request`
- ✅ Creative Work: `requires_special_budget_approval = true`
- ✅ Producer tidak bisa approve/reject creative work jika special budget masih pending

**Controller:** `ProducerController::requestSpecialBudget()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2767-2850)

---

#### **2.12. Producer: Terima atau Tolak yang Diajukan Creative**

**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/final-approval`

**Request Body (Approve):**
```json
{
  "action": "approve",
  "notes": "Semua sudah sesuai, approved!"
}
```

**Request Body (Reject):**
```json
{
  "action": "reject",
  "reason": "Script perlu diperbaiki, budget terlalu besar"
}
```

**Flow:**
- ✅ Producer dapat approve/reject creative work secara final
- ✅ Validasi: Script, storyboard, dan budget harus sudah di-review dulu
- ✅ Validasi: Jika special budget pending, tidak bisa approve/reject
- ✅ Approve: Status `submitted` → `approved`
- ✅ Reject: Status `submitted` → `rejected`
- ✅ Notifikasi ke Creative: `creative_work_approved` / `creative_work_rejected`
- ✅ Jika approve: Auto-create BudgetRequest ke General Affairs

**Controller:** `ProducerController::finalApprovalCreativeWork()`

**File:** `app/Http/Controllers/Api/ProducerController.php` (line 2880-3000)

---

## 📊 STATUS FLOW DIAGRAM

```
1. Creative:
   draft → in_progress → submitted
   (script, storyboard, recording schedule, shooting schedule, location, budget)
   ↓
2. Producer:
   ├─ Review Script (script_approved)
   ├─ Review Storyboard (storyboard_approved)
   ├─ Review Budget (budget_approved)
   ├─ Assign Shooting Team
   ├─ Assign Setting Team
   ├─ Assign Recording Team
   ├─ Cancel Shooting Schedule (jika kendala)
   ├─ Replace Team (jika perlu)
   ├─ Edit Creative Work (jika perlu)
   ├─ Request Special Budget (jika perlu)
   └─ Final Approval/Reject
       ├─ Approve → approved (lanjut ke production)
       └─ Reject → rejected (kembali ke Creative untuk revisi)
```

---

## 🔒 KEAMANAN

### ✅ Role Validation
- ✅ Creative: `if ($user->role !== 'Creative')`
- ✅ Producer: `if ($user->role !== 'Producer')`

### ✅ Authorization
- ✅ Producer hanya bisa review/edit creative work dari ProductionTeam mereka
- ✅ Creative hanya bisa update creative work yang mereka buat sendiri
- ✅ Producer hanya bisa assign team untuk episode dari ProductionTeam mereka

### ✅ Input Validation
- ✅ Laravel Validator untuk semua input
- ✅ Required fields validation
- ✅ Type validation
- ✅ Size/limit validation

### ✅ Status Validation
- ✅ Status checks sebelum setiap action
- ✅ Workflow state management
- ✅ Special budget approval check sebelum final approval

---

## 📋 DAFTAR ENDPOINT

### **Creative Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| List Creative Works | `/api/live-tv/roles/creative/works` | GET | ✅ |
| Show Creative Work | `/api/live-tv/roles/creative/works/{id}` | GET | ✅ |
| Create Creative Work | `/api/live-tv/roles/creative/works` | POST | ✅ |
| Update Creative Work | `/api/live-tv/roles/creative/works/{id}` | PUT | ✅ |
| Accept Work | `/api/live-tv/roles/creative/works/{id}/accept-work` | POST | ✅ |
| Complete Work | `/api/live-tv/roles/creative/works/{id}/complete-work` | POST | ✅ |

### **Producer Endpoints:**
| Fitur | Endpoint | Method | Status |
|-------|----------|--------|--------|
| Review Creative Work | `/api/live-tv/producer/creative-works/{id}/review` | POST | ✅ |
| Final Approval/Reject | `/api/live-tv/producer/creative-works/{id}/final-approval` | POST | ✅ |
| Assign Team | `/api/live-tv/producer/creative-works/{id}/assign-team` | POST | ✅ |
| Assign Production Teams | `/api/live-tv/producer/creative-works/{id}/assign-production-teams` | POST | ✅ |
| Cancel Shooting Schedule | `/api/live-tv/producer/creative-works/{id}/cancel-shooting` | POST | ✅ |
| Replace Team | `/api/live-tv/producer/team-assignments/{id}/replace-team` | PUT | ✅ |
| Emergency Reassign Team | `/api/live-tv/producer/schedules/{id}/emergency-reassign-team` | PUT | ✅ |
| Edit Creative Work | `/api/live-tv/producer/creative-works/{id}/edit` | PUT | ✅ |
| Request Special Budget | `/api/live-tv/producer/creative-works/{id}/request-special-budget` | POST | ✅ |

**Total Endpoint:** 15+ endpoint

---

## ✅ KESIMPULAN

### Status: **LENGKAP & AMAN**

Semua flow yang diminta sudah diimplementasikan:

1. ✅ **Creative - Submit Creative Work** - Terima notifikasi, terima pekerjaan, script, storyboard, jadwal rekaman, jadwal syuting, lokasi, budget, selesaikan pekerjaan, ajukan ke producer
2. ✅ **Producer - Review Creative Work** - Terima notifikasi, cek script, cek storyboard, cek budget, assign shooting team, assign setting team, cancel shooting schedule, replace team, assign recording team, edit creative work, request special budget, approve/reject

### Keamanan: **AMAN**
- ✅ Role validation di semua endpoint
- ✅ Authorization checks (ProductionTeam membership)
- ✅ Input validation & sanitization
- ✅ Status validation (workflow state management)
- ✅ Special budget approval check

### Total Endpoint: **15+ endpoint** untuk Creative & Producer

---

**Last Updated:** 12 Desember 2025  
**Status:** ✅ **VERIFIED & COMPLETE - READY FOR PRODUCTION**

