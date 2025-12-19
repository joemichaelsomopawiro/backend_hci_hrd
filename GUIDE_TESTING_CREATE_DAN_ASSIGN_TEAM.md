# 🧪 GUIDE TESTING: CREATE TEAM & ASSIGN TEAM

**Tanggal:** 13 Desember 2025  
**Status:** ✅ **READY FOR TESTING**

---

## 📋 OVERVIEW

Panduan lengkap untuk testing flow:
1. **Create Production Team** (kosong)
2. **Add Members** ke team
3. **Assign Team** ke episode

---

## 🚀 QUICK START

### **Prerequisites:**
1. ✅ Login sebagai **Manager Program**
2. ✅ Sudah ada **Program** yang dibuat
   - **PENTING:** Saat create program, **53 episodes otomatis ter-generate**
   - **TIDAK PERLU** create episode manual!
3. ✅ Sudah ada **Users** dengan role:
   - `Creative`
   - `Music Arranger`
   - `Sound Engineer`
   - `Production`
   - `Editor`
   - `Art & Set Properti`

---

## 📝 STEP-BY-STEP TESTING

### **⚠️ PENTING: Episode Auto-Generated**

**Episode otomatis ter-generate saat create program!**

Saat Manager Program create program baru:
- ✅ **53 episodes otomatis ter-generate**
- ✅ Episodes sudah punya `episode_number` (1-53)
- ✅ Episodes sudah punya `air_date` (auto-calculated)
- ✅ Episodes sudah punya `deadlines` (auto-generated)
- ✅ **TIDAK PERLU create episode manual!**

**Jadi flow-nya:**
1. ✅ Create Program → 53 Episodes auto-generated
2. ✅ Create Production Team
3. ✅ Add Members ke Team
4. ✅ Assign Team ke Episode (langsung pakai episode yang sudah ada)

**Cara Dapatkan Episode ID:**
- Dari program detail: `GET /api/live-tv/programs/{program_id}` → lihat `episodes[0].id`
- Dari list episodes: `GET /api/live-tv/episodes?program_id={program_id}` → ambil `id` dari episode yang mau di-assign

---

### **STEP 1: Create Production Team**

**Endpoint:**
```http
POST /api/live-tv/production-teams
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Tim Produksi Musik A",
  "description": "Tim untuk program musik episode 1-10",
  "producer_id": 5
}
```

**Response Success (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Tim Produksi Musik A",
    "description": "Tim untuk program musik episode 1-10",
    "producer_id": 5,
    "is_active": true,
    "member_count": 0,
    "is_ready_for_production": false,
    "producer": {
      "id": 5,
      "name": "Producer Name",
      "email": "producer@example.com",
      "role": "Producer"
    },
    "members": []
  },
  "message": "Production team created successfully"
}
```

**✅ Checklist:**
- [ ] Team berhasil dibuat
- [ ] Team ID tersimpan (contoh: `id: 1`)
- [ ] `member_count: 0` (masih kosong)
- [ ] `is_ready_for_production: false` (belum ready karena belum ada members)

---

### **STEP 2: Add Members ke Team**

**Endpoint:**
```http
POST /api/live-tv/production-teams/{team_id}/members
Authorization: Bearer {token}
Content-Type: application/json
```

**Contoh: Add Creative Member**

**Request Body:**
```json
{
  "user_id": 10,
  "role": "kreatif",
  "notes": "Creative utama"
}
```

**Response Success (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 10,
    "user": {
      "id": 10,
      "name": "Creative User",
      "email": "creative@example.com",
      "role": "Creative"
    },
    "role": "kreatif",
    "role_label": "Kreatif",
    "is_active": true,
    "joined_at": "2025-12-13T10:00:00.000000Z",
    "notes": "Creative utama"
  },
  "message": "Member added to team successfully"
}
```

**✅ Checklist:**
- [ ] Member berhasil ditambahkan
- [ ] User data lengkap (name, email, role)
- [ ] Role sesuai (`kreatif` → `Creative`)

**Lanjutkan untuk role lainnya:**

1. **Add Music Arranger**
   ```json
   {
     "user_id": 11,
     "role": "musik_arr",
     "notes": "Music Arranger utama"
   }
   ```

2. **Add Sound Engineer**
   ```json
   {
     "user_id": 12,
     "role": "sound_eng",
     "notes": "Sound Engineer utama"
   }
   ```

3. **Add Production**
   ```json
   {
     "user_id": 13,
     "role": "produksi",
     "notes": "Production utama"
   }
   ```

4. **Add Editor**
   ```json
   {
     "user_id": 14,
     "role": "editor",
     "notes": "Editor utama"
   }
   ```

5. **Add Art & Set Design**
   ```json
   {
     "user_id": 15,
     "role": "art_set_design",
     "notes": "Art & Set Design utama"
   }
   ```

**✅ Checklist Setelah Add Semua Members:**
- [ ] Semua 6 role sudah ada (kreatif, musik_arr, sound_eng, produksi, editor, art_set_design)
- [ ] Team `is_ready_for_production: true`
- [ ] `member_count: 6` (atau lebih jika ada multiple members per role)

**Optional: Add Multiple Members dengan Role Sama**

Bisa tambah multiple members dengan role yang sama:
```json
{
  "user_id": 16,
  "role": "editor",
  "notes": "Editor kedua"
}
```

---

### **STEP 3: Get Episode ID (Dari Program yang Sudah Ada)**

**Endpoint:**
```http
GET /api/live-tv/programs/{program_id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "program": {
      "id": 1,
      "name": "Program Musik Test",
      "episodes": [
        {
          "id": 1,
          "episode_number": 1,
          "title": "Episode 1",
          "status": "draft"
        },
        {
          "id": 2,
          "episode_number": 2,
          "title": "Episode 2",
          "status": "draft"
        }
        // ... sampai episode 53
      ]
    }
  }
}
```

**✅ Checklist:**
- [ ] Program punya 53 episodes
- [ ] Episode ID tersedia (contoh: `id: 1` untuk episode 1)
- [ ] Episodes sudah punya `episode_number` (1-53)

**Atau langsung dari list episodes:**
```http
GET /api/live-tv/episodes?program_id={program_id}
Authorization: Bearer {token}
```

**⚠️ PENTING: Pagination Default**
- Default: `per_page=100` (cukup untuk 53 episodes)
- Untuk get semua episodes tanpa pagination: `?per_page=0` atau `?per_page=all`
- Contoh: `GET /api/live-tv/episodes?program_id=1&per_page=0`

---

### **STEP 4: Assign Team ke Episode**

**Endpoint:**
```http
POST /api/live-tv/manager-program/episodes/{episode_id}/assign-team
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "production_team_id": 1,
  "notes": "Assign team untuk episode 1"
}
```

**Catatan:** `episode_id` adalah ID dari episode yang sudah auto-generated (bukan episode_number!)

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "episode": {
      "id": 1,
      "episode_number": 1,
      "title": "Episode 1",
      "production_team_id": 1,
      "team_assigned_at": "2025-12-13T10:30:00.000000Z",
      "team_assigned_by": 1,
      "team_assignment_notes": "Assign team untuk episode 1"
    },
    "team": {
      "id": 1,
      "name": "Tim Produksi Musik A",
      "members": [
        {
          "id": 1,
          "user": {
            "id": 10,
            "name": "Creative User",
            "role": "Creative"
          },
          "role": "kreatif"
        },
        // ... other members
      ]
    }
  },
  "message": "Team assigned to episode successfully"
  }
}
```

**✅ Checklist:**
- [ ] Episode berhasil di-assign team
- [ ] `production_team_id` di episode sudah ter-update
- [ ] `team_assigned_at` sudah terisi
- [ ] `team_assigned_by` adalah user yang assign (Manager Program)
- [ ] Notifikasi terkirim ke semua team members

---

### **STEP 5: Verifikasi Notifikasi**

**Endpoint:**
```http
GET /api/live-tv/notifications
Authorization: Bearer {token}
```

**Cek untuk setiap team member:**
- [ ] Creative user dapat notifikasi
- [ ] Music Arranger user dapat notifikasi
- [ ] Sound Engineer user dapat notifikasi
- [ ] Production user dapat notifikasi
- [ ] Editor user dapat notifikasi
- [ ] Art & Set Design user dapat notifikasi

**Notifikasi yang Diharapkan:**
```json
{
  "type": "team_assigned",
  "title": "Team Assigned to Episode",
  "message": "Anda telah di-assign ke Tim Produksi Musik A untuk Episode 1",
  "data": {
    "episode_id": 1,
    "team_id": 1
  }
}
```

---

### **STEP 6: Verifikasi Episode Data**

**Endpoint:**
```http
GET /api/live-tv/episodes/{episode_id}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "episode_number": 1,
    "title": "Episode 1",
    "production_team_id": 1,
    "production_team": {
      "id": 1,
      "name": "Tim Produksi Musik A",
      "members": [
        // ... all members
      ]
    },
    "team_assigned_at": "2025-12-13T10:30:00.000000Z",
    "team_assigned_by": 1,
    "team_assignment_notes": "Assign team untuk episode 1"
  }
}
```

**✅ Checklist:**
- [ ] Episode punya `production_team_id`
- [ ] Production team data lengkap
- [ ] Semua members ter-load
- [ ] Assignment info lengkap

---

## 🔄 FLOW LENGKAP SETELAH ASSIGN TEAM

Setelah team di-assign ke episode, workflow bisa lanjut:

### **1. Music Arranger Bisa Mulai Kerja**
- Music Arranger (yang di-assign di team) bisa:
  - Buat song proposal
  - Buat music arrangement
  - Submit arrangement

### **2. Creative Bisa Mulai Kerja**
- Creative (yang di-assign di team) bisa:
  - Terima pekerjaan setelah arrangement approved
  - Buat script, storyboard, budget
  - Submit creative work

### **3. Sound Engineer Bisa Mulai Kerja**
- Sound Engineer (yang di-assign di team) bisa:
  - Terima arrangement yang approved
  - Buat recording task
  - Record vokal

### **4. Production Bisa Mulai Kerja**
- Production (yang di-assign di team) bisa:
  - Terima pekerjaan setelah creative work approved
  - Buat production planning
  - Request equipment

### **5. Editor Bisa Mulai Kerja**
- Editor (yang di-assign di team) bisa:
  - Terima pekerjaan setelah recording selesai
  - Edit video/audio
  - Submit editing work

### **6. Art & Set Design Bisa Mulai Kerja**
- Art & Set Design (yang di-assign di team) bisa:
  - Terima pekerjaan setelah creative work approved
  - Buat art & set design
  - Submit design work

---

## 🧪 TESTING SCENARIOS

### **Scenario 1: Assign Team ke Single Episode**

1. Create team
2. Add all members
3. Assign team ke episode 1
4. Verifikasi episode 1 punya team
5. Verifikasi semua members dapat notifikasi

### **Scenario 2: Assign Team ke Multiple Episodes**

1. Create team
2. Add all members
3. Assign team ke episode 1
4. Assign team ke episode 2
5. Assign team ke episode 3
6. Verifikasi semua episode punya team yang sama
7. Verifikasi members dapat notifikasi untuk setiap episode

### **Scenario 3: Change Team Assignment**

1. Episode sudah punya team A
2. Assign team B ke episode yang sama
3. Verifikasi episode sekarang punya team B
4. Verifikasi team A members dapat notifikasi (team diubah)
5. Verifikasi team B members dapat notifikasi (team baru)

### **Scenario 4: Team dengan Multiple Members per Role**

1. Create team
2. Add Creative member 1
3. Add Creative member 2 (multiple dengan role sama)
4. Add semua role lainnya
5. Assign team ke episode
6. Verifikasi semua members (termasuk multiple Creative) dapat notifikasi

---

## 🐛 TROUBLESHOOTING

### **Error: "No users found for role"**

**Penyebab:**
- User dengan role yang sesuai tidak ada di database
- Role mapping salah

**Solusi:**
1. Cek apakah user dengan role yang benar ada di database:
   - `Creative` (bukan `kreatif`)
   - `Music Arranger` (bukan `musik_arr`)
   - `Sound Engineer` (bukan `sound_eng`)
   - `Production` (bukan `produksi`)
   - `Editor` (bukan `editor`)
   - `Art & Set Properti` (bukan `art_set_design`)

2. Pastikan user `is_active = true`

### **Error: "Team is not ready for production"**

**Penyebab:**
- Team belum punya semua required roles
- Minimal harus ada 1 member untuk setiap role:
  - kreatif
  - musik_arr
  - sound_eng
  - produksi
  - editor
  - art_set_design

**Solusi:**
- Tambahkan member untuk role yang masih kosong

### **Error: "Unauthorized: Only Manager Program can assign teams"**

**Penyebab:**
- User yang login bukan Manager Program

**Solusi:**
- Login sebagai user dengan role `Manager Program` atau `Program Manager`

### **Error: "Production team not found"**

**Penyebab:**
- `production_team_id` tidak ada di database
- Team sudah di-delete

**Solusi:**
- Pastikan team ID benar
- Cek apakah team masih aktif (`is_active = true`)

---

## 📊 CHECKLIST TESTING LENGKAP

### **Create Team**
- [ ] Team berhasil dibuat
- [ ] Team muncul di list teams
- [ ] Cache auto-clear (data langsung muncul tanpa refresh)

### **Add Members**
- [ ] Member berhasil ditambahkan
- [ ] Member muncul di team members list
- [ ] Multiple members per role bisa ditambahkan
- [ ] Team `is_ready_for_production` menjadi `true` setelah semua role ada
- [ ] Cache auto-clear (data langsung update)

### **Assign Team**
- [ ] Team berhasil di-assign ke episode
- [ ] Episode `production_team_id` ter-update
- [ ] Assignment info lengkap (assigned_at, assigned_by, notes)
- [ ] Semua team members dapat notifikasi
- [ ] Episode data menampilkan team lengkap dengan members
- [ ] Cache auto-clear (data langsung update)

### **Workflow Lanjutan**
- [ ] Music Arranger bisa mulai kerja (setelah team di-assign)
- [ ] Creative bisa mulai kerja
- [ ] Sound Engineer bisa mulai kerja
- [ ] Production bisa mulai kerja
- [ ] Editor bisa mulai kerja
- [ ] Art & Set Design bisa mulai kerja

---

## 🎯 EXPECTED RESULTS

### **Setelah Create Team:**
- Team muncul di list
- Team kosong (0 members)
- Team `is_ready_for_production: false`

### **Setelah Add All Members:**
- Team punya 6+ members (minimal 1 per role)
- Team `is_ready_for_production: true`
- Semua members terlihat di team detail

### **Setelah Assign Team:**
- Episode punya `production_team_id`
- Episode menampilkan team lengkap dengan members
- Semua members dapat notifikasi
- Workflow bisa lanjut ke tahap berikutnya

---

## 📝 NOTES

1. **Cache Auto-Clear:** Semua operasi (create, update, delete) otomatis clear cache, jadi data langsung update tanpa perlu refresh manual.

2. **Role Mapping:** Backend otomatis map production team role ke user role:
   - `kreatif` → `Creative`
   - `musik_arr` → `Music Arranger`
   - `sound_eng` → `Sound Engineer`
   - `produksi` → `Production`
   - `editor` → `Editor`
   - `art_set_design` → `Art & Set Properti`

3. **Multiple Members:** Bisa add multiple members dengan role yang sama (contoh: 2 Editor, 3 Creative).

4. **Team Reuse:** Satu team bisa di-assign ke multiple episodes.

---

**Last Updated:** 13 Desember 2025  
**Created By:** AI Assistant

