# 🚀 Program Regular Management - Quick Start Guide

## 📋 Ringkasan Sistem

Sistem baru untuk mengelola program televisi/broadcast mingguan dengan:
- ✅ **53 Episode** otomatis per program
- ✅ **6 Role Wajib** + Producer sebagai leader
- ✅ **Auto Deadline** untuk setiap role
- ✅ **Google Spreadsheet** integration untuk proposal
- ✅ **Approval System** terpusat

---

## 🎯 Quick Setup (5 Langkah)

### 1️⃣ Jalankan Migration

```bash
php artisan migrate
```

### 2️⃣ Buat Production Team

```bash
POST /api/production-teams
{
  "name": "Tim Producer 1",
  "producer_id": 5,
  "created_by": 1,
  "members": [
    {"user_id": 10, "role": "kreatif"},
    {"user_id": 11, "role": "musik_arr"},
    {"user_id": 12, "role": "sound_eng"},
    {"user_id": 13, "role": "produksi"},
    {"user_id": 14, "role": "editor"},
    {"user_id": 15, "role": "art_set_design"}
  ]
}
```

### 3️⃣ Buat Proposal (Google Spreadsheet)

```bash
POST /api/program-proposals
{
  "spreadsheet_id": "YOUR_SPREADSHEET_ID",
  "proposal_title": "Proposal Program 2025",
  "format_type": "mingguan",
  "auto_sync": true,
  "created_by": 2
}
```

### 4️⃣ Buat Program Regular

```bash
POST /api/program-regular
{
  "name": "Program Kebaktian Mingguan",
  "production_team_id": 1,
  "manager_program_id": 2,
  "start_date": "2025-01-10",
  "air_time": "19:00",
  "duration_minutes": 60
}
```

**✨ Sistem otomatis generate:**
- 53 episodes (setiap minggu)
- 318 deadlines (6 role × 53 episode)

### 5️⃣ Submit & Approve

```bash
# Submit untuk approval
POST /api/program-regular/1/submit-approval
{"user_id": 2, "notes": "Siap direview"}

# Approve program
POST /api/program-regular/1/approve
{"user_id": 3, "notes": "Disetujui"}
```

---

## 📊 6 Role Wajib

Setiap tim **HARUS** punya minimal 1 orang untuk setiap role:

| Role | Label | Deadline |
|------|-------|----------|
| `kreatif` | Kreatif | 9 hari sebelum tayang |
| `musik_arr` | Musik Arranger | 9 hari sebelum tayang |
| `sound_eng` | Sound Engineer | 9 hari sebelum tayang |
| `produksi` | Produksi | 9 hari sebelum tayang |
| `editor` | Editor | **7 hari** sebelum tayang |
| `art_set_design` | Art & Set Design | 9 hari sebelum tayang |

---

## 🔗 API Endpoints Penting

### Production Teams
```
GET    /api/production-teams           # List teams
POST   /api/production-teams           # Buat team
GET    /api/production-teams/{id}      # Detail team
POST   /api/production-teams/{id}/members  # Tambah member
```

### Program Regular
```
GET    /api/program-regular            # List programs
POST   /api/program-regular            # Buat program (auto 53 episodes)
GET    /api/program-regular/{id}       # Detail program
GET    /api/program-regular/{id}/dashboard  # Dashboard statistik
```

### Episodes
```
GET    /api/program-episodes           # List episodes
GET    /api/program-episodes/{id}      # Detail episode
PUT    /api/program-episodes/{id}      # Update episode
GET    /api/program-episodes/{id}/deadlines  # Deadlines
POST   /api/program-episodes/{id}/submit-rundown  # Submit rundown
```

### Proposals
```
GET    /api/program-proposals          # List proposals
POST   /api/program-proposals          # Buat proposal
POST   /api/program-proposals/{id}/sync  # Sync dari spreadsheet
GET    /api/program-proposals/{id}/embedded-view  # Embedded view
```

### Approvals
```
GET    /api/program-approvals/pending  # Pending approvals
GET    /api/program-approvals/urgent   # Urgent approvals
POST   /api/program-approvals/{id}/approve  # Approve
POST   /api/program-approvals/{id}/reject   # Reject
```

---

## 📈 Status Flow

### Program Status
```
draft → pending_approval → approved → in_production → completed
   ↓                          ↓
rejected                  cancelled
```

### Episode Status
```
planning → ready_to_produce → in_production → 
post_production → ready_to_air → aired
```

---

## 🎯 Contoh Use Case

### Scenario: Program Kebaktian Mingguan Hope Channel

**Input:**
- Start Date: 10 Januari 2025
- Air Time: 19:00 WIB
- Tayang setiap Jumat

**Output Otomatis:**
```
Episode 1  → 10 Jan 2025, 19:00 (Deadline Editor: 3 Jan, Kreatif: 1 Jan)
Episode 2  → 17 Jan 2025, 19:00 (Deadline Editor: 10 Jan, Kreatif: 8 Jan)
Episode 3  → 24 Jan 2025, 19:00 (Deadline Editor: 17 Jan, Kreatif: 15 Jan)
...
Episode 53 → 2 Jan 2026, 19:00 (1 tahun setelah episode 1)
```

**Total:**
- 53 episodes
- 318 deadlines (53 × 6 role)

---

## 💡 Tips Penting

### ✅ DO's
- ✅ Pastikan tim punya semua 6 role sebelum buat program
- ✅ Pilih start_date minimal 2-3 minggu dari sekarang
- ✅ Submit rundown untuk approval sebelum produksi
- ✅ Mark deadline completed saat selesai
- ✅ Monitor dashboard untuk track progress

### ❌ DON'Ts
- ❌ Jangan hapus tim yang punya program aktif
- ❌ Jangan update program yang sudah completed
- ❌ Jangan hapus member terakhir untuk suatu role
- ❌ Jangan skip status episode (ikuti flow)

---

## 🔔 Auto Notifications

Sistem otomatis kirim notifikasi untuk:
1. ⏰ Deadline Reminder (1 hari sebelum deadline)
2. 🚨 Overdue Deadline Alert
3. 📺 Episode Air Reminder (3 hari sebelum tayang)
4. 📝 Approval Request Notification
5. ✅ Approval Decision Notification

---

## 📞 Frontend Integration

### Embedded Google Spreadsheet

```javascript
// Get embedded view data
const response = await fetch('/api/program-proposals/1/embedded-view');
const data = await response.json();

// Display in iframe
<iframe 
  src={data.data.embedded_url}
  width="100%"
  height="600px"
/>
```

### Dashboard Statistics

```javascript
// Get program dashboard
const response = await fetch('/api/program-regular/1/dashboard');
const dashboard = await response.json();

console.log(dashboard.data.episodes_stats);
// {
//   total: 53,
//   aired: 13,
//   upcoming: 40,
//   overdue: 0,
//   progress_percentage: 24.53
// }
```

---

## 🐛 Troubleshooting

### Error: "Tim belum lengkap"
**Solusi:** Pastikan tim punya minimal 1 orang untuk semua 6 role

### Error: "Cannot delete team with active programs"
**Solusi:** Complete atau cancel program dulu sebelum delete tim

### Error: "Cannot update aired episode"
**Solusi:** Episode yang sudah tayang tidak bisa diupdate

### Error: "Cannot remove the last member for role"
**Solusi:** Tambah member baru untuk role tersebut dulu sebelum hapus yang lama

---

## 📚 Dokumentasi Lengkap

Untuk dokumentasi detail, lihat: **`PROGRAM_REGULAR_SYSTEM_DOCUMENTATION.md`**

---

## ✅ Checklist Migration dari Sistem Lama

- [ ] Backup database lama
- [ ] Run migrations baru
- [ ] Buat production teams untuk setiap producer
- [ ] Migrate program existing (jika perlu)
- [ ] Test workflow end-to-end
- [ ] Train user untuk sistem baru
- [ ] Monitor selama 1 minggu pertama

---

**🎉 Sistem Siap Digunakan!**

Untuk support, hubungi tim development.

