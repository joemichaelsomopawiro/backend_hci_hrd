# 📋 Flow Producer Review Creative Work

## 🎯 Overview

Flow lengkap untuk Producer saat review dan approve creative work yang diajukan oleh Kreatif.

---

## 📍 Alur Kerja Producer

```
1. Terima Notifikasi (Creative Work Submitted)
    ↓
2. Cek Script
    ↓
3. Cek Storyboard
    ↓
4. Cek Budget
    ↓
5. Tambahkan Tim Syuting (Crew Program, selain manager)
    ↓
6. Tambahkan Tim Setting (Crew Program, selain manager, boleh sama dengan syuting)
    ↓
7. Tambahkan Tim Rekam Vokal
    ↓
8. [OPSIONAL] Edit Team jika diperlukan
    ↓
9. [OPSIONAL] Cancel Jadwal Syuting (jika ada kendala)
    ↓
10. [OPSIONAL] Ganti Tim Syuting secara dadakan (emergency)
    ↓
11. [OPSIONAL] Ajukan Budget Khusus ke Manager Program (jika diperlukan)
    ↓
12. Terima / Tolak Creative Work
```

---

## 🔔 1. Terima Notifikasi

**Notification Type**: `creative_work_submitted`

**Detail**:
- Producer menerima notifikasi saat Kreatif submit creative work
- Notifikasi berisi: Episode, Creative Work ID, dan link untuk review

---

## 📝 2-4. Review Script, Storyboard, dan Budget

### **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/review`

**Request**:
```json
{
  "script_approved": true,              // Approve/reject script
  "storyboard_approved": true,          // Approve/reject storyboard
  "budget_approved": true,              // Approve/reject budget
  "script_review_notes": "Script OK",   // Optional: notes untuk script
  "storyboard_review_notes": "OK",      // Optional: notes untuk storyboard
  "budget_review_notes": "Budget OK"    // Optional: notes untuk budget
}
```

**Fitur**:
- ✅ Cek Script (approve/reject dengan notes)
- ✅ Cek Storyboard (approve/reject dengan notes)
- ✅ Cek Budget (approve/reject dengan notes)
- ✅ Producer bisa edit budget langsung jika diperlukan

**Response**:
```json
{
  "success": true,
  "data": {
    "creative_work": {
      "id": 20,
      "script_approved": true,
      "storyboard_approved": true,
      "budget_approved": true,
      "script_review_notes": "Script OK",
      "storyboard_review_notes": "OK",
      "budget_review_notes": "Budget OK",
      "reviewed_by": 2,
      "reviewed_at": "2025-12-20 17:00:00"
    }
  },
  "message": "Creative work reviewed successfully"
}
```

---

## 👥 5. Tambahkan Tim Syuting

### **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Request**:
```json
{
  "team_type": "shooting",
  "team_member_ids": [1, 2, 3],        // Crew Program (selain manager)
  "team_name": "Tim Syuting - Episode 1",
  "team_notes": "Catatan untuk tim",
  "schedule_id": 123                    // Optional: jadwal syuting
}
```

**Fitur**:
- ✅ Ambil semua crew Program (selain manager)
- ✅ Validasi anggota harus dari production team
- ✅ Bisa set schedule_id (jadwal syuting)
- ✅ Auto-notify semua anggota team

---

## 🎨 6. Tambahkan Tim Setting

### **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Request**:
```json
{
  "team_type": "setting",
  "team_member_ids": [2, 4, 5],        // Boleh sama dengan tim syuting
  "team_name": "Tim Setting - Episode 1",
  "team_notes": "Catatan untuk tim setting",
  "schedule_id": 123                    // Optional: jadwal (biasanya sama dengan shooting)
}
```

**Fitur**:
- ✅ Ambil semua crew Program (selain manager)
- ✅ **Boleh sama dengan tim syuting** (anggota bisa double job)
- ✅ Validasi anggota harus dari production team
- ✅ Auto-notify semua anggota team

---

## 🎤 7. Tambahkan Tim Rekam Vokal

### **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/assign-team`

**Request**:
```json
{
  "team_type": "recording",
  "team_member_ids": [6, 7],           // Crew Program (selain manager)
  "team_name": "Tim Rekam Vokal - Episode 1",
  "team_notes": "Catatan untuk tim recording",
  "schedule_id": 124                    // Optional: jadwal rekam vokal
}
```

**Fitur**:
- ✅ Ambil semua crew Program (selain manager)
- ✅ Validasi anggota harus dari production team
- ✅ Auto-notify semua anggota team

---

## ✏️ 8. Edit Team (Jika Diperlukan)

### **Endpoint**: `PUT /api/live-tv/producer/team-assignments/{assignmentId}`

**Request**:
```json
{
  "team_name": "Tim Syuting - Episode 1 (Updated)",  // Optional
  "team_notes": "Update notes",                      // Optional
  "schedule_id": 125,                                 // Optional
  "team_member_ids": [1, 2, 5, 6]                    // Optional: tambah/kurang anggota
}
```

**Fitur**:
- ✅ Edit nama tim
- ✅ Edit catatan tim
- ✅ Update jadwal
- ✅ Tambah/kurang anggota team
- ✅ Auto-notify anggota baru/dihapus

**Use Case**:
- Perlu update nama tim
- Perlu update catatan
- Perlu tambah anggota
- Perlu kurangi anggota
- Perlu update jadwal

---

## 🚫 9. Cancel Jadwal Syuting (Jika Ada Kendala)

### **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/cancel-shooting`

**Request**:
```json
{
  "reason": "Hujan deras, lokasi tidak bisa digunakan",
  "new_schedule_date": "2025-12-25 10:00:00"  // Optional: jadwal baru
}
```

**Fitur**:
- ✅ Cancel jadwal syuting dengan alasan
- ✅ Set jadwal baru (opsional)
- ✅ Auto-cancel team assignments terkait
- ✅ Notify semua anggota team yang di-cancel

**Response**:
```json
{
  "success": true,
  "message": "Shooting schedule cancelled successfully",
  "data": {
    "creative_work": {...},
    "cancelled_assignments": [...],
    "new_schedule_date": "2025-12-25 10:00:00"
  }
}
```

---

## 🔄 10. Ganti Tim Syuting Secara Dadakan (Emergency)

### **Endpoint**: `PUT /api/live-tv/producer/team-assignments/{assignmentId}/replace-team`

**Request**:
```json
{
  "new_team_member_ids": [8, 9, 10],
  "replacement_reason": "Anggota tim sakit, perlu ganti segera"
}
```

**Fitur**:
- ✅ Ganti semua anggota team sekaligus
- ✅ Wajib isi alasan replacement
- ✅ Auto-notify anggota lama (dihapus)
- ✅ Auto-notify anggota baru (ditugaskan)
- ✅ Status assignment tetap sama

**Response**:
```json
{
  "success": true,
  "data": {
    "assignment": {
      "id": 1,
      "team_type": "shooting",
      "members": [
        {"user_id": 8, "role": "leader"},
        {"user_id": 9, "role": "crew"},
        {"user_id": 10, "role": "crew"}
      ]
    }
  },
  "message": "Team members replaced successfully"
}
```

---

## 💰 11. Ajukan Budget Khusus ke Manager Program

### **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/request-special-budget`

**Request**:
```json
{
  "special_budget_amount": 5000000,
  "special_budget_reason": "Perlu tambahan budget untuk talent khusus",
  "priority": "high"  // Optional: low, normal, high, urgent
}
```

**Fitur**:
- ✅ Ajukan budget tambahan ke Manager Program
- ✅ Wajib isi alasan
- ✅ Set priority (low, normal, high, urgent)
- ✅ Auto-notify Manager Program
- ✅ Status creative work: `pending_special_budget_approval`

**Response**:
```json
{
  "success": true,
  "data": {
    "special_budget_approval": {
      "id": 1,
      "amount": 5000000,
      "reason": "Perlu tambahan budget untuk talent khusus",
      "priority": "high",
      "status": "pending"
    }
  },
  "message": "Special budget request submitted successfully"
}
```

**Note**: Producer harus menunggu approval dari Manager Program sebelum bisa final approve.

---

## ✅ 12. Terima / Tolak Creative Work

### **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/final-approval`

**Request**:
```json
{
  "action": "approve",  // atau "reject"
  "approval_notes": "Semua sudah OK, bisa lanjut ke produksi",
  "review_details": {
    "script": {
      "approved": true,
      "notes": "Script bagus"
    },
    "storyboard": {
      "approved": true,
      "notes": "Storyboard jelas"
    },
    "budget": {
      "approved": true,
      "notes": "Budget sesuai"
    }
  }
}
```

**Fitur**:
- ✅ Final approve/reject dengan review detail
- ✅ Review script, storyboard, budget secara detail
- ✅ Set notes untuk setiap komponen
- ✅ Jika approve: lanjut ke fase produksi
- ✅ Jika reject: kembali ke Kreatif untuk revisi

**Response (Approve)**:
```json
{
  "success": true,
  "data": {
    "creative_work": {
      "id": 20,
      "status": "approved",
      "approved_by": 2,
      "approved_at": "2025-12-20 17:00:00"
    },
    "next_phase": "production_preparation"
  },
  "message": "Creative work approved successfully"
}
```

**Response (Reject)**:
```json
{
  "success": true,
  "data": {
    "creative_work": {
      "id": 20,
      "status": "rejected",
      "rejected_by": 2,
      "rejected_at": "2025-12-20 17:00:00",
      "rejection_notes": "Perlu revisi script dan budget"
    },
    "next_phase": "creative_revision"
  },
  "message": "Creative work rejected, sent back for revision"
}
```

---

## 📊 Checklist Producer Review

### **Sebelum Final Approval:**
- [ ] Script sudah dicek dan approve/reject
- [ ] Storyboard sudah dicek dan approve/reject
- [ ] Budget sudah dicek dan approve/reject
- [ ] Tim Syuting sudah di-assign
- [ ] Tim Setting sudah di-assign (jika diperlukan)
- [ ] Tim Rekam Vokal sudah di-assign (jika diperlukan)
- [ ] Team sudah di-edit jika diperlukan
- [ ] Jadwal syuting sudah di-set atau di-cancel jika ada kendala
- [ ] Budget khusus sudah diajukan (jika diperlukan)
- [ ] Semua sudah OK untuk final approval

### **Setelah Final Approval:**
- [ ] Creative Work status: `approved`
- [ ] Workflow lanjut ke fase produksi
- [ ] Produksi Work otomatis dibuat
- [ ] Team assignments siap digunakan

---

## 🔗 Endpoints Summary

| Action | Endpoint | Method |
|--------|----------|--------|
| Review Script/Storyboard/Budget | `/api/live-tv/producer/creative-works/{id}/review` | POST |
| Assign Team (Shooting/Setting/Recording) | `/api/live-tv/producer/creative-works/{id}/assign-team` | POST |
| Edit Team Assignment | `/api/live-tv/producer/team-assignments/{assignmentId}` | PUT |
| Cancel Shooting Schedule | `/api/live-tv/producer/creative-works/{id}/cancel-shooting` | POST |
| Replace Team (Emergency) | `/api/live-tv/producer/team-assignments/{assignmentId}/replace-team` | PUT |
| Request Special Budget | `/api/live-tv/producer/creative-works/{id}/request-special-budget` | POST |
| Final Approve/Reject | `/api/live-tv/producer/creative-works/{id}/final-approval` | POST |
| Edit Creative Work | `/api/live-tv/producer/creative-works/{id}/edit` | PUT |
| View Team Assignments | `/api/live-tv/producer/episodes/{episodeId}/team-assignments` | GET |

---

## 📝 Notes Penting

1. **Crew Selection**:
   - Semua crew diambil dari Production Team yang di-assign ke Program
   - Manager Program **TIDAK** bisa dipilih sebagai crew
   - Anggota yang sama bisa di-assign ke multiple teams (shooting + setting)

2. **Team Assignment**:
   - Bisa assign multiple teams sekaligus (shooting, setting, recording)
   - Setiap team bisa punya schedule berbeda
   - Team bisa di-edit kapan saja sebelum final approval

3. **Emergency Actions**:
   - Cancel shooting: untuk kendala yang tidak terduga
   - Replace team: untuk ganti anggota secara dadakan
   - Keduanya auto-notify anggota yang terpengaruh

4. **Budget Khusus**:
   - Hanya bisa diajukan jika budget normal tidak cukup
   - Harus menunggu approval Manager Program
   - Tidak bisa final approve sebelum budget khusus di-approve (jika diajukan)

5. **Final Approval**:
   - Hanya bisa dilakukan setelah semua review selesai
   - Jika ada budget khusus, harus menunggu approval dulu
   - Setelah approve, workflow lanjut ke fase produksi

---

## ✅ Flow Validation

**Sebelum Final Approval, pastikan:**
1. ✅ Script approved
2. ✅ Storyboard approved
3. ✅ Budget approved (atau special budget approved)
4. ✅ Tim Syuting assigned (minimal)
5. ✅ Tim Setting assigned (jika diperlukan)
6. ✅ Tim Rekam Vokal assigned (jika diperlukan)

**Jika semua sudah OK → Final Approve!**

