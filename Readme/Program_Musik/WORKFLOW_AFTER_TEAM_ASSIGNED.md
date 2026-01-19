# 📋 Alur Kerja Setelah Team Di-Assign

## 🎯 Overview

Setelah Producer berhasil assign team (Tim Syuting, Tim Setting, atau Tim Rekam Vokal) ke Creative Work, berikut adalah alur kerja selanjutnya.

---

## 📍 Status Team Assignment

Setelah team di-assign, status assignment adalah:
- **`assigned`** - Team sudah ditugaskan (default)
- **`confirmed`** - Team sudah konfirmasi hadir
- **`in_progress`** - Team sedang bekerja
- **`completed`** - Team sudah selesai tugas
- **`cancelled`** - Assignment dibatalkan

---

## 🔔 Notifikasi yang Terjadi

### 1. **Notification ke Team Members**
Setelah team di-assign, semua anggota team menerima notification:
- **Type**: `team_assigned`
- **Title**: "Ditugaskan ke Tim [Shooting/Setting/Recording]"
- **Message**: "Anda telah ditugaskan ke tim [nama tim] untuk Episode [nomor episode]"
- **Data**: 
  ```json
  {
    "assignment_id": 1,
    "team_type": "shooting",
    "episode_id": 2063
  }
  ```

---

## 🎬 Alur Kerja Berdasarkan Tipe Team

### **1. TIM SYUTING (Shooting Team)**

```
Producer Assign Team
    ↓
Team Members Terima Notification
    ↓
Creative Work Approved (oleh Producer)
    ↓
Produksi Work Created (otomatis)
    ↓
Produksi Accept Work
    ↓
1. Request Equipment (ke Art & Set Properti)
    ↓
2. Input Run Sheet (shooting date, location, crew, equipment)
    ↓
3. Upload Shooting Results (file hasil syuting)
    ↓
4. Complete Work
    ↓
Producer Review & Approve
```

**Detail Langkah:**

#### **Step 1: Request Equipment**
- **Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/request-equipment`
- **User**: Produksi
- **Action**: Request equipment dari Art & Set Properti
- **Data**: List equipment yang dibutuhkan

#### **Step 2: Input Run Sheet**
- **Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet`
- **User**: Produksi
- **Action**: Buat run sheet untuk shooting
- **Data**: 
  - Shooting date
  - Location
  - Crew list (dari team assignment)
  - Equipment list
  - Shooting notes

#### **Step 3: Upload Shooting Results**
- **Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results`
- **User**: Produksi
- **Action**: Upload file hasil syuting
- **Data**: Shooting files (video/photo)

#### **Step 4: Complete Work**
- **Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/complete`
- **User**: Produksi
- **Action**: Mark work sebagai completed
- **Result**: Producer akan di-notify untuk review

---

### **2. TIM SETTING (Art & Set Team)**

```
Producer Assign Team
    ↓
Team Members Terima Notification
    ↓
Creative Work Approved (oleh Producer)
    ↓
Art & Set Work Created (otomatis)
    ↓
Art & Set Accept Work
    ↓
1. Review Equipment Request (dari Produksi)
    ↓
2. Provide Equipment (setujui & siapkan equipment)
    ↓
3. Setup Location/Set (jika diperlukan)
    ↓
4. Complete Work
    ↓
Producer Review & Approve
```

**Detail Langkah:**

#### **Step 1: Review Equipment Request**
- **Endpoint**: `GET /api/live-tv/roles/art-set-properti/equipment-requests`
- **User**: Art & Set Properti
- **Action**: Lihat equipment requests dari Produksi

#### **Step 2: Provide Equipment**
- **Endpoint**: `POST /api/live-tv/roles/art-set-properti/equipment-requests/{id}/approve`
- **User**: Art & Set Properti
- **Action**: Setujui dan siapkan equipment
- **Data**: Equipment status, notes

#### **Step 3: Complete Work**
- **Endpoint**: `POST /api/live-tv/roles/art-set-properti/works/{id}/complete`
- **User**: Art & Set Properti
- **Action**: Mark work sebagai completed

---

### **3. TIM REKAM VOKAL (Recording Team)**

```
Producer Assign Team
    ↓
Team Members Terima Notification
    ↓
Creative Work Approved (oleh Producer)
    ↓
Sound Engineer Recording Task Created (otomatis)
    ↓
Sound Engineer Accept Work
    ↓
1. Start Recording
    ↓
2. Upload Recording File
    ↓
3. Complete Recording
    ↓
Producer Review & Approve
```

**Detail Langkah:**

#### **Step 1: Start Recording**
- **Endpoint**: `POST /api/live-tv/roles/sound-engineer/recordings/{id}/start`
- **User**: Sound Engineer
- **Action**: Mulai proses recording
- **Status**: `in_progress`

#### **Step 2: Upload Recording File**
- **Endpoint**: `POST /api/live-tv/roles/sound-engineer/recordings/{id}/upload`
- **User**: Sound Engineer
- **Action**: Upload file hasil recording
- **Data**: Recording file (audio)

#### **Step 3: Complete Recording**
- **Endpoint**: `POST /api/live-tv/roles/sound-engineer/recordings/{id}/complete`
- **User**: Sound Engineer
- **Action**: Mark recording sebagai completed
- **Result**: Producer akan di-notify untuk review

---

## 🔍 Cara Melihat Team Assignments

### **1. Lihat Team Assignments per Episode**
- **Endpoint**: `GET /api/live-tv/producer/episodes/{episodeId}/team-assignments`
- **User**: Producer
- **Response**: 
  ```json
  {
    "success": true,
    "data": {
      "team_assignments": [
        {
          "id": 1,
          "team_type": "shooting",
          "team_name": "Tim Syuting - Episode 1",
          "status": "assigned",
          "members": [
            {
              "id": 1,
              "user_id": 1,
              "user": {
                "id": 1,
                "name": "John Doe"
              },
              "role": "leader",
              "status": "assigned"
            }
          ],
          "episode": {
            "id": 2063,
            "episode_number": 1
          }
        }
      ],
      "grouped_by_type": {
        "shooting": [...],
        "setting": [...],
        "recording": [...]
      }
    }
  }
  ```

### **2. Lihat Team Assignments per Program**
- **Endpoint**: `GET /api/live-tv/producer/programs/{programId}/team-assignments`
- **User**: Producer
- **Purpose**: Untuk reuse team dari episode lain dalam program yang sama

---

## ✏️ Edit Team Assignment

### **Update Team Assignment Details**
- **Endpoint**: `PUT /api/live-tv/producer/team-assignments/{assignmentId}`
- **User**: Producer
- **Action**: Edit detail team assignment
- **Data yang bisa di-edit**:
  ```json
  {
    "team_name": "Tim Syuting - Episode 1 (Updated)",  // Optional
    "team_notes": "Updated notes",                       // Optional
    "schedule_id": 123,                                  // Optional
    "team_member_ids": [1, 2, 3]                        // Optional: tambah/kurang anggota
  }
  ```
- **Fitur**:
  - Edit `team_name` dan `team_notes`
  - Update `schedule_id`
  - Tambah/kurang anggota team (otomatis notify anggota baru/dihapus)
  - Validasi anggota harus dari production team
- **Response**:
  ```json
  {
    "success": true,
    "data": {
      "id": 1,
      "team_type": "shooting",
      "team_name": "Tim Syuting - Episode 1 (Updated)",
      "team_notes": "Updated notes",
      "members": [...],
      "episode": {...}
    },
    "message": "Team assignment updated successfully"
  }
  ```

## 🔄 Update Status Team Assignment

### **1. Confirm Assignment (Team Leader)**
- **Endpoint**: `PUT /api/live-tv/producer/team-assignments/{assignmentId}/confirm`
- **User**: Team Leader
- **Action**: Konfirmasi bahwa team siap bekerja
- **Status Change**: `assigned` → `confirmed`

### **2. Start Work (Team Leader)**
- **Endpoint**: `PUT /api/live-tv/producer/team-assignments/{assignmentId}/start`
- **User**: Team Leader
- **Action**: Mulai bekerja
- **Status Change**: `confirmed` → `in_progress`

### **3. Complete Work (Team Leader)**
- **Endpoint**: `PUT /api/live-tv/producer/team-assignments/{assignmentId}/complete`
- **User**: Team Leader
- **Action**: Selesai bekerja
- **Status Change**: `in_progress` → `completed`

---

## 🚨 Emergency Actions

### **1. Replace Team Members**
- **Endpoint**: `PUT /api/live-tv/producer/team-assignments/{assignmentId}/replace-team`
- **User**: Producer
- **Action**: Ganti anggota team secara dadakan
- **Data**: 
  ```json
  {
    "new_team_member_ids": [5, 6],
    "replacement_reason": "Anggota tim sakit"
  }
  ```

### **2. Cancel Shooting Schedule**
- **Endpoint**: `POST /api/live-tv/producer/creative-works/{id}/cancel-shooting`
- **User**: Producer
- **Action**: Batalkan jadwal syuting
- **Result**: Team assignments akan di-cancel

---

## 📊 Tracking Progress

### **1. Lihat Status Team Assignment**
```sql
SELECT 
    ta.id,
    ta.team_type,
    ta.team_name,
    ta.status,
    ta.assigned_at,
    ta.completed_at,
    COUNT(tm.id) as member_count
FROM production_teams_assignment ta
LEFT JOIN production_team_members tm ON tm.assignment_id = ta.id
WHERE ta.episode_id = ?
GROUP BY ta.id
```

### **2. Lihat Member Status**
```sql
SELECT 
    tm.id,
    u.name as member_name,
    tm.role,
    tm.status,
    ta.team_type,
    ta.team_name
FROM production_team_members tm
JOIN users u ON u.id = tm.user_id
JOIN production_teams_assignment ta ON ta.id = tm.assignment_id
WHERE ta.episode_id = ?
```

---

## ✅ Checklist Setelah Team Di-Assign

### **Untuk Producer:**
- [ ] Team sudah di-assign dengan benar
- [ ] Semua team members menerima notification
- [ ] Schedule sudah di-set (jika ada)
- [ ] Team notes sudah diisi (jika perlu)

### **Untuk Team Members:**
- [ ] Terima notification assignment
- [ ] Cek detail assignment (episode, schedule, notes)
- [ ] Konfirmasi kehadiran (jika diperlukan)
- [ ] Siapkan equipment/peralatan (jika diperlukan)

### **Untuk Team Leader:**
- [ ] Konfirmasi assignment (`confirmed`)
- [ ] Koordinasi dengan team members
- [ ] Start work saat waktunya (`in_progress`)
- [ ] Complete work setelah selesai (`completed`)

---

## 🔗 Related Endpoints

### **Team Assignment Management:**
- `POST /api/live-tv/producer/creative-works/{id}/assign-team` - Assign team (create new)
- `PUT /api/live-tv/producer/team-assignments/{assignmentId}` - **Edit team assignment** (update details)
- `GET /api/live-tv/producer/episodes/{episodeId}/team-assignments` - Lihat assignments
- `PUT /api/live-tv/producer/team-assignments/{assignmentId}/replace-team` - Ganti team (emergency replacement)
- `POST /api/live-tv/producer/episodes/{episodeId}/copy-team-assignment` - Copy dari episode lain

### **Work Management:**
- `GET /api/live-tv/roles/produksi/works` - Lihat produksi works
- `POST /api/live-tv/roles/produksi/works/{id}/accept` - Accept work
- `POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet` - Buat run sheet
- `POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results` - Upload hasil

---

## 📝 Notes

1. **Team Assignment** berbeda dengan **Production Team**:
   - **Production Team**: Tim tetap yang di-assign ke program (oleh Manager Program)
   - **Team Assignment**: Tim yang di-assign untuk tugas spesifik (oleh Producer)

2. **Status Flow**:
   ```
   assigned → confirmed → in_progress → completed
                      ↓
                  cancelled (jika dibatalkan)
   ```

3. **Team bisa di-reuse** dari episode lain dalam program yang sama menggunakan `copy-team-assignment`

4. **Emergency replacement** bisa dilakukan kapan saja oleh Producer dengan alasan yang jelas

---

## 🎯 Next Steps

Setelah team di-assign dan creative work di-approve:
1. Produksi akan otomatis mendapat Produksi Work
2. Art & Set akan otomatis mendapat Art & Set Work (jika ada equipment request)
3. Sound Engineer akan otomatis mendapat Recording Task (jika ada recording team)
4. Masing-masing role akan menerima notification untuk accept work

