# Flow Producer - Review Creative Work dari Creative

## ✅ STATUS: **SUDAH LENGKAP & READY**

Producer dapat memproses creative work yang diajukan Creative dengan semua fitur yang diperlukan.

---

## 📋 WORKFLOW LENGKAP

```
Creative Submit Creative Work
    ↓
Producer
    ↓ Terima Notifikasi
    ↓ Cek Script
    ↓ Cek Storyboard
    ↓ Cek Budget
    ↓ Tambahkan Tim Syuting (Crew Program, selain manager)
    ↓ Tambahkan Tim Setting (Crew Program, selain manager, boleh sama)
    ↓ Tambahkan Tim Rekam Vokal
    ↓ [OPSIONAL] Edit Creative Work jika diperlukan
    ↓ [OPSIONAL] Edit Team jika diperlukan
    ↓ [OPSIONAL] Cancel Jadwal Syuting (jika ada kendala)
    ↓ [OPSIONAL] Ganti Tim Syuting secara dadakan
    ↓ [OPSIONAL] Ajukan Budget Khusus ke Manager Program
    ↓ Terima / Tolak
```

---

## 📋 ENDPOINT YANG TERSEDIA

### 1. **Terima Notifikasi**
**Endpoint:** `GET /api/live-tv/notifications?type=creative_work_submitted`

**Fungsi:** Get notifikasi bahwa Creative telah submit creative work

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "type": "creative_work_submitted",
        "title": "Creative Work Submitted",
        "message": "Creative John telah mengirim creative work untuk Episode 001",
        "data": {
          "creative_work_id": 1,
          "episode_id": 1
        }
      }
    ]
  }
}
```

**Tersedia di Postman:** ✅ Yes

---

### 2. **Get Pending Creative Works**
**Endpoint:** `GET /api/live-tv/producer/approvals`

**Fungsi:** Get semua pending approvals termasuk creative works

**Response:**
```json
{
  "success": true,
  "data": {
    "creative_works": [
      {
        "id": 1,
        "episode_id": 1,
        "script_content": "Script...",
        "storyboard_data": {...},
        "budget_data": {...},
        "status": "submitted"
      }
    ]
  }
}
```

**Tersedia di Postman:** ✅ Yes

---

### 3. **Cek Script, Storyboard, Budget** ⭐
**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/review`

**Fungsi:** Producer review script, storyboard, dan budget secara terpisah

**Request Body:**
```json
{
  "script_approved": true,
  "storyboard_approved": true,
  "budget_approved": true,
  "script_review_notes": "Script OK, sesuai dengan konsep",
  "storyboard_review_notes": "Storyboard jelas, bisa lanjut",
  "budget_review_notes": "Budget sesuai, talent fee reasonable"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "script_approved": true,
    "storyboard_approved": true,
    "budget_approved": true,
    "script_review_notes": "Script OK, sesuai dengan konsep",
    "storyboard_review_notes": "Storyboard jelas, bisa lanjut",
    "budget_review_notes": "Budget sesuai, talent fee reasonable",
    "reviewed_by": 5,
    "reviewed_at": "2026-01-27T12:00:00.000000Z"
  },
  "message": "Creative work reviewed successfully"
}
```

**Fitur:**
- ✅ Review Script (approve/reject dengan notes)
- ✅ Review Storyboard (approve/reject dengan notes)
- ✅ Review Budget (approve/reject dengan notes)
- ✅ Producer bisa edit budget langsung jika diperlukan

**Tersedia di Postman:** ✅ Yes

---

### 4. **Get Crew Members (Untuk Assign Team)**
**Endpoint:** `GET /api/live-tv/producer/crew-members?program_id={id}`

**Fungsi:** Get semua crew Program (selain Manager Program) untuk assign ke team

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 6,
      "name": "Crew 1",
      "role": "Camera Operator",
      "production_team_id": 1
    },
    {
      "id": 7,
      "name": "Crew 2",
      "role": "Sound Engineer",
      "production_team_id": 1
    }
  ]
}
```

**Catatan:**
- ✅ Hanya menampilkan crew dari Production Team yang di-assign ke Program
- ✅ **Manager Program TIDAK ditampilkan** ✅
- ✅ Bisa dipilih untuk multiple teams (shooting + setting)

**Tersedia di Postman:** ✅ Yes

---

### 5. **Tambahkan Tim Syuting** ⭐
**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Fungsi:** Assign tim syuting dengan crew Program (selain manager)

**Request Body:**
```json
{
  "team_type": "shooting",
  "team_member_ids": [6, 7, 8],
  "team_name": "Tim Syuting Episode 001",
  "team_notes": "Tim untuk syuting video klip",
  "schedule_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "assignment": {
      "id": 1,
      "team_type": "shooting",
      "team_name": "Tim Syuting Episode 001",
      "members": [
        {"user_id": 6, "name": "Crew 1"},
        {"user_id": 7, "name": "Crew 2"},
        {"user_id": 8, "name": "Crew 3"}
      ]
    }
  },
  "message": "Team assigned successfully"
}
```

**Fitur:**
- ✅ Ambil crew dari Production Team Program
- ✅ **Manager Program TIDAK bisa dipilih** ✅
- ✅ Auto-notify semua anggota team
- ✅ Bisa set schedule_id (jadwal syuting)

**Tersedia di Postman:** ✅ Yes

---

### 6. **Tambahkan Tim Setting** ⭐
**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Fungsi:** Assign tim setting dengan crew Program (selain manager, boleh sama dengan shooting)

**Request Body:**
```json
{
  "team_type": "setting",
  "team_member_ids": [7, 8, 9],
  "team_name": "Tim Setting Episode 001",
  "team_notes": "Tim untuk setting lokasi",
  "schedule_id": 123
}
```

**Fitur:**
- ✅ Ambil crew dari Production Team Program
- ✅ **Manager Program TIDAK bisa dipilih** ✅
- ✅ **Boleh sama dengan tim syuting** (anggota bisa double job) ✅
- ✅ Auto-notify semua anggota team

**Tersedia di Postman:** ✅ Yes

---

### 7. **Tambahkan Tim Rekam Vokal** ⭐
**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Fungsi:** Assign tim rekam vokal dengan crew Program (selain manager)

**Request Body:**
```json
{
  "team_type": "recording",
  "team_member_ids": [10, 11],
  "team_name": "Tim Rekam Vokal Episode 001",
  "team_notes": "Tim untuk rekaman suara",
  "schedule_id": 124
}
```

**Fitur:**
- ✅ Ambil crew dari Production Team Program
- ✅ **Manager Program TIDAK bisa dipilih** ✅
- ✅ Auto-notify semua anggota team
- ✅ Bisa set schedule_id (jadwal rekaman)

**Tersedia di Postman:** ✅ Yes

---

### 8. **Edit Creative Work (Jika Diperlukan)** ⭐
**Endpoint:** `PUT /api/live-tv/producer/creative-works/{id}/edit`

**Fungsi:** Producer dapat mengedit creative work langsung jika diperlukan

**Request Body:**
```json
{
  "script_content": "Script yang diubah oleh Producer...",
  "storyboard_data": {...},
  "budget_data": {...},
  "recording_schedule": "2026-01-30 10:00:00",
  "shooting_schedule": "2026-02-01 08:00:00",
  "shooting_location": "Studio B",
  "edit_notes": "Diubah oleh Producer karena perlu penyesuaian"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "script_content": "Script yang diubah oleh Producer...",
    "edit_notes": "Diubah oleh Producer karena perlu penyesuaian"
  },
  "message": "Creative work edited successfully. Creative has been notified."
}
```

**Fitur:**
- ✅ Producer bisa edit semua field creative work
- ✅ **Creative di-notify** tentang perubahan ✅
- ✅ Bisa edit script, storyboard, budget, jadwal, lokasi
- ✅ Reset review fields jika status rejected

**Validasi:**
- ✅ Hanya bisa edit jika status: `submitted`, `rejected`, atau `revised`

**Tersedia di Postman:** ✅ Yes

---

### 9. **Edit Team Assignment (Jika Diperlukan)**
**Endpoint:** `PUT /api/live-tv/producer/team-assignments/{assignmentId}`

**Fungsi:** Edit team assignment (nama, notes, jadwal, anggota)

**Request Body:**
```json
{
  "team_name": "Tim Syuting Episode 001 (Updated)",
  "team_notes": "Update notes",
  "schedule_id": 125,
  "team_member_ids": [6, 7, 9]
}
```

**Fitur:**
- ✅ Edit nama tim
- ✅ Edit catatan tim
- ✅ Update jadwal (schedule_id)
- ✅ Tambah/kurang anggota team
- ✅ Auto-notify anggota baru/dihapus

**Tersedia di Postman:** ✅ Yes

---

### 10. **Cancel Jadwal Syuting (Jika Ada Kendala)** ⭐
**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/cancel-shooting`

**Fungsi:** Cancel jadwal syuting jika terjadi kendala

**Request Body:**
```json
{
  "cancellation_reason": "Hujan deras, lokasi tidak bisa digunakan",
  "new_shooting_schedule": "2026-02-05 08:00:00"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "creative_work": {
      "id": 1,
      "shooting_schedule_cancelled": true,
      "shooting_cancellation_reason": "Hujan deras, lokasi tidak bisa digunakan",
      "shooting_schedule_new": "2026-02-05 08:00:00"
    },
    "cancelled_assignments": [...]
  },
  "message": "Shooting schedule cancelled successfully"
}
```

**Fitur:**
- ✅ Cancel jadwal syuting dengan alasan
- ✅ Set jadwal baru (opsional)
- ✅ Auto-cancel team assignments terkait (shooting team)
- ✅ Notify semua anggota team yang di-cancel
- ✅ **Creative di-notify** tentang cancellation ✅

**Tersedia di Postman:** ✅ Yes

---

### 11. **Ganti Tim Syuting Secara Dadakan (Emergency)** ⭐
**Endpoint:** `PUT /api/live-tv/producer/team-assignments/{assignmentId}/replace-team`

**Fungsi:** Ganti tim syuting secara dadakan untuk keperluan emergency

**Request Body:**
```json
{
  "new_team_member_ids": [12, 13, 14],
  "replacement_reason": "Anggota tim sakit, perlu ganti segera"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "assignment": {
      "id": 1,
      "team_type": "shooting",
      "members": [
        {"user_id": 12, "name": "Crew Baru 1"},
        {"user_id": 13, "name": "Crew Baru 2"},
        {"user_id": 14, "name": "Crew Baru 3"}
      ]
    }
  },
  "message": "Team members replaced successfully"
}
```

**Fitur:**
- ✅ Ganti semua anggota team sekaligus
- ✅ Wajib isi alasan replacement
- ✅ Auto-notify anggota lama (dihapus)
- ✅ Auto-notify anggota baru (ditugaskan)
- ✅ Bisa untuk shooting, setting, atau recording team

**Tersedia di Postman:** ✅ Yes

---

### 12. **Ajukan Budget Khusus ke Manager Program** ⭐
**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/request-special-budget`

**Fungsi:** Ajukan budget khusus ke Manager Program jika budget normal tidak cukup

**Request Body:**
```json
{
  "special_budget_amount": 5000000,
  "special_budget_reason": "Perlu tambahan budget untuk talent khusus yang lebih mahal"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "special_budget_approval": {
      "id": 1,
      "special_budget_amount": 5000000,
      "special_budget_reason": "Perlu tambahan budget untuk talent khusus yang lebih mahal",
      "status": "pending"
    },
    "creative_work": {
      "id": 1,
      "requires_special_budget_approval": true,
      "special_budget_approval_id": 1
    }
  },
  "message": "Special budget request submitted successfully. Manager Program has been notified."
}
```

**Fitur:**
- ✅ Ajukan budget tambahan ke Manager Program
- ✅ Wajib isi alasan
- ✅ Auto-notify Manager Program
- ✅ Status creative work: `requires_special_budget_approval = true`
- ✅ Tidak bisa final approve sebelum special budget di-approve

**Catatan:**
- Jika ada special budget request pending, Producer **TIDAK BISA** final approve creative work
- Harus tunggu Manager Program approve/reject dulu

**Tersedia di Postman:** ✅ Yes

---

### 13. **Terima / Tolak Creative Work** ⭐
**Endpoint:** `POST /api/live-tv/producer/creative-works/{id}/final-approval`

**Fungsi:** Final approve/reject creative work setelah semua review selesai

**Request Body (Approve):**
```json
{
  "action": "approve",
  "notes": "Semua sudah OK, bisa lanjut ke produksi"
}
```

**Request Body (Reject):**
```json
{
  "action": "reject",
  "notes": "Perlu revisi script dan budget"
}
```

**Response (Approve):**
```json
{
  "success": true,
  "data": {
    "creative_work": {
      "id": 1,
      "status": "approved",
      "reviewed_by": 5,
      "reviewed_at": "2026-01-27T13:00:00.000000Z"
    }
  },
  "message": "Creative work approved successfully"
}
```

**Response (Reject):**
```json
{
  "success": true,
  "data": {
    "creative_work": {
      "id": 1,
      "status": "rejected",
      "rejection_reason": "Perlu revisi script dan budget"
    }
  },
  "message": "Creative work rejected. Creative has been notified."
}
```

**Validasi:**
- ✅ Hanya bisa approve jika:
  - Script approved
  - Storyboard approved
  - Budget approved (atau special budget approved)
  - Special budget approval sudah di-approve (jika ada)
- ✅ **Creative di-notify** tentang approval/rejection ✅
- ✅ Jika approve: Auto-create Budget Request ke General Affairs
- ✅ Jika approve: Auto-create Produksi Work
- ✅ Jika approve: Auto-create Promotion Work

**Tersedia di Postman:** ✅ Yes

---

## 📋 ENDPOINT SUMMARY UNTUK FRONTEND

| Fitur | Endpoint | Method | Body |
|-------|----------|--------|------|
| Get Notifications | `/api/live-tv/notifications?type=creative_work_submitted` | GET | - |
| Get Pending Approvals | `/api/live-tv/producer/approvals` | GET | - |
| Get Crew Members | `/api/live-tv/producer/crew-members?program_id={id}` | GET | - |
| Review Script/Storyboard/Budget | `/api/live-tv/producer/creative-works/{id}/review` | POST | `{script_approved, storyboard_approved, budget_approved, ...}` |
| Assign Team (Shooting/Setting/Recording) | `/api/live-tv/producer/creative-works/{id}/assign-team` | POST | `{team_type, team_member_ids, ...}` |
| Edit Creative Work | `/api/live-tv/producer/creative-works/{id}/edit` | PUT | `{script_content, storyboard_data, ...}` |
| Edit Team Assignment | `/api/live-tv/producer/team-assignments/{assignmentId}` | PUT | `{team_name, team_member_ids, ...}` |
| Cancel Shooting Schedule | `/api/live-tv/producer/creative-works/{id}/cancel-shooting` | POST | `{cancellation_reason, new_shooting_schedule}` |
| Replace Team (Emergency) | `/api/live-tv/producer/team-assignments/{assignmentId}/replace-team` | PUT | `{new_team_member_ids, replacement_reason}` |
| Request Special Budget | `/api/live-tv/producer/creative-works/{id}/request-special-budget` | POST | `{special_budget_amount, special_budget_reason}` |
| Final Approve/Reject | `/api/live-tv/producer/creative-works/{id}/final-approval` | POST | `{action, notes}` |
| Get Team Assignments | `/api/live-tv/producer/episodes/{episodeId}/team-assignments` | GET | - |

---

## ✅ VERIFIKASI SEMUA FITUR

### **Producer Review Creative Work:**

1. ✅ **Terima Notifikasi** - Endpoint tersedia
2. ✅ **Cek Script** - Via review endpoint (script_approved)
3. ✅ **Cek Storyboard** - Via review endpoint (storyboard_approved)
4. ✅ **Cek Budget** - Via review endpoint (budget_approved)
5. ✅ **Tambahkan Tim Syuting** - Endpoint assign-team dengan team_type: shooting
6. ✅ **Tambahkan Tim Setting** - Endpoint assign-team dengan team_type: setting
7. ✅ **Tambahkan Tim Rekam Vokal** - Endpoint assign-team dengan team_type: recording
8. ✅ **Dapat Cancel Jadwal Syuting** - Endpoint cancel-shooting tersedia
9. ✅ **Dapat Ganti Tim Syuting Secara Dadakan** - Endpoint replace-team tersedia
10. ✅ **Producer Dapat Edit Langsung** - Endpoint edit tersedia
11. ✅ **Ajukan Budget Khusus ke Manager Program** - Endpoint request-special-budget tersedia
12. ✅ **Terima/Tolak** - Endpoint final-approval tersedia

### **Validation & Security:**
- ✅ Crew hanya diambil dari Production Team Program
- ✅ **Manager Program TIDAK bisa dipilih sebagai crew** ✅
- ✅ Anggota bisa di-assign ke multiple teams (shooting + setting)
- ✅ Tidak bisa final approve jika special budget pending
- ✅ Auto-notify semua pihak terkait

---

## 🎯 KESIMPULAN

### ✅ **Semua Fitur Sudah Ada:**

1. ✅ **Terima Notifikasi** - Endpoint tersedia
2. ✅ **Cek Script** - Via review endpoint
3. ✅ **Cek Storyboard** - Via review endpoint
4. ✅ **Cek Budget** - Via review endpoint
5. ✅ **Tambahkan Tim Syuting** - Endpoint tersedia (crew Program, selain manager)
6. ✅ **Tambahkan Tim Setting** - Endpoint tersedia (crew Program, selain manager, boleh sama)
7. ✅ **Tambahkan Tim Rekam Vokal** - Endpoint tersedia
8. ✅ **Dapat Cancel Jadwal Syuting** - Endpoint tersedia
9. ✅ **Dapat Ganti Tim Syuting Secara Dadakan** - Endpoint tersedia
10. ✅ **Producer Dapat Edit Langsung** - Endpoint tersedia
11. ✅ **Ajukan Budget Khusus ke Manager Program** - Endpoint tersedia
12. ✅ **Terima/Tolak** - Endpoint tersedia

**Status:** ✅ **READY FOR FRONTEND INTEGRATION**

---

**Last Updated:** 2026-01-27
