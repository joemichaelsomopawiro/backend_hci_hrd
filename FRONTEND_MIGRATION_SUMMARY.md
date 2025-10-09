### Migrasi Frontend: Ringkasan Perubahan Backend (Sebelum vs Sesudah)

Dokumen ringkas untuk membantu perpindahan frontend dari sistem lama ke sistem baru Program Regular.

---

## 1) Perubahan Konsep Utama
- Sebelum: Tim melekat ke program; struktur role tidak wajib lengkap.
- Sesudah: `production_teams` berdiri sendiri dipimpin Producer, dan WAJIB punya minimal 1 orang untuk setiap 6 role:
  - kreatif, musik_arr, sound_eng, produksi, editor, art_set_design.
- Sesudah: Saat program dibuat → otomatis terbentuk 53 episode mingguan + 6 deadline per episode (318 deadline/tahun).
- Proposal program kini terintegrasi Google Spreadsheet (embed + sync data).
- Approval disatukan dalam sistem polymorphic (program, episode/rundown, jadwal, perubahan/cancel).

---

## 2) Mapping Endpoint Lama → Baru (yang dipakai Frontend)

### Program
- Lama:
  - `GET/POST/PUT/DELETE /api/programs`
  - `POST /api/programs/{program}/assign-teams`
  - `GET /api/programs/{program}/dashboard`
  - `GET /api/programs/{program}/statistics`
- Baru:
  - `GET /api/program-regular`
  - `POST /api/program-regular` (auto generate 53 episode + deadlines)
  - `GET /api/program-regular/{id}`
  - `PUT /api/program-regular/{id}`
  - `DELETE /api/program-regular/{id}`
  - `GET /api/program-regular/{id}/dashboard`
  - Workflow: `POST /api/program-regular/{id}/submit-approval | /approve | /reject`

### Tim
- Lama: `GET/POST/PUT/DELETE /api/teams` (+ add/remove/update-member)
- Baru:
  - `GET/POST/PUT/DELETE /api/production-teams`
  - Members: `POST /api/production-teams/{id}/members`, `DELETE /api/production-teams/{id}/members`
  - Helper: `GET /api/production-teams/{id}/available-users`, `GET /api/production-teams/producers`

### Episode
- Lama: `GET/POST/PUT/DELETE /api/episodes`, `GET /api/episodes/by-program/{programId}`
- Baru:
  - `GET /api/program-episodes?program_regular_id={id}`
  - `GET /api/program-episodes/{id}`
  - `PUT /api/program-episodes/{id}`
  - `PATCH /api/program-episodes/{id}/status`
  - Deadlines: `GET /api/program-episodes/{id}/deadlines`
  - Complete deadline: `POST /api/program-episodes/{episodeId}/deadlines/{deadlineId}/complete`
  - Rundown approval: `POST /api/program-episodes/{id}/submit-rundown`
  - Upcoming: `GET /api/program-episodes/upcoming?days=7`

### Proposal (BARU)
- `GET/POST/PUT/DELETE /api/program-proposals`
- Embedded view: `GET /api/program-proposals/{id}/embedded-view`
- Sync Spreadsheet: `POST /api/program-proposals/{id}/sync`
- Workflow: `POST /api/program-proposals/{id}/submit | /review | /approve | /reject | /request-revision`

### Approval (Unified, BARU)
- `GET/POST /api/program-approvals`
- Aksi: `POST /api/program-approvals/{id}/review | /approve | /reject | /cancel`
- Koleksi: `GET /api/program-approvals/pending | /overdue | /urgent | /history`

---

## 3) Perubahan Payload Penting

### Create Program (baru)
```json
{
  "name": "Program Kebaktian Mingguan",
  "description": "Ibadah mingguan",
  "production_team_id": 1,
  "manager_program_id": 2,
  "start_date": "2025-01-10",
  "air_time": "19:00",
  "duration_minutes": 60,
  "broadcast_channel": "Hope Channel Indonesia",
  "target_views_per_episode": 10000
}
```
Hasil: backend otomatis membuat 53 episode + 318 deadlines.

### Create Production Team (wajib 6 role)
```json
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

### Episode (baru, read)
- Tambahan properti untuk UI:
  - `deadlines[]` (6 item/role), `progress_percentage`, `days_until_air`, `is_overdue`.

### Proposal (baru)
```json
{
  "program_regular_id": 1,
  "spreadsheet_id": "19eF1kyIALqQtDxvUvA_Uz0LD1oktMKx8DOTLcH8c2tc",
  "sheet_name": "Sheet1",
  "proposal_title": "Proposal 2025",
  "format_type": "mingguan",
  "auto_sync": true,
  "created_by": 2
}
```

---

## 4) Status/Enums (Frontend perlu mapping ulang label/warna)
- Program: `draft`, `pending_approval`, `approved`, `in_production`, `completed`, `cancelled`, `rejected`
- Episode: `planning`, `ready_to_produce`, `in_production`, `post_production`, `ready_to_air`, `aired`, `cancelled`
- Approval (status): `pending`, `reviewed`, `approved`, `rejected`, `cancelled`, `auto_approved`
- Approval (types): `program_proposal`, `program_schedule`, `episode_rundown`, `production_schedule`, `schedule_change`, `schedule_cancellation`, `deadline_extension`

---

## 5) Checklist Migrasi UI
1. Ganti modul "Teams" → "Production Teams"; validasi tampilkan 6 role wajib + indikator "ready_for_production".
2. Form Create Program: ganti pemilihan Producer → `production_team_id` (ambil dari `/api/production-teams`).
3. Halaman Episodes: konsumsi `/api/program-episodes?program_regular_id={id}`; tampilkan `deadlines`, action "Complete Deadline", dan tombol "Submit Rundown".
4. Tambah modul "Proposals": embed iframe dari `GET /api/program-proposals/{id}/embedded-view`, tombol Sync dan workflow (submit/review/approve/reject/revision).
5. Tambah modul "Approvals": halaman daftar (Pending/Urgent/Overdue/History) + detail & actions.
6. Dashboard Program: pindah ke `GET /api/program-regular/{id}/dashboard` dan render statistik baru (`episodes_stats`, `deadlines_stats`, `next_episodes`, `recent_aired`).
7. Perbarui mapping enum status untuk badge/warna di seluruh UI.

---

## 6) Endpoint Referensi Cepat

### Production Teams
```
GET    /api/production-teams
POST   /api/production-teams
GET    /api/production-teams/{id}
PUT    /api/production-teams/{id}
DELETE /api/production-teams/{id}
POST   /api/production-teams/{id}/members
DELETE /api/production-teams/{id}/members
GET    /api/production-teams/{id}/available-users
GET    /api/production-teams/producers
```

### Program Regular
```
GET    /api/program-regular
POST   /api/program-regular
GET    /api/program-regular/{id}
PUT    /api/program-regular/{id}
DELETE /api/program-regular/{id}
GET    /api/program-regular/{id}/dashboard
POST   /api/program-regular/{id}/submit-approval
POST   /api/program-regular/{id}/approve
POST   /api/program-regular/{id}/reject
```

### Program Episodes
```
GET    /api/program-episodes?program_regular_id={id}
GET    /api/program-episodes/{id}
PUT    /api/program-episodes/{id}
PATCH  /api/program-episodes/{id}/status
GET    /api/program-episodes/{id}/deadlines
POST   /api/program-episodes/{episodeId}/deadlines/{deadlineId}/complete
POST   /api/program-episodes/{id}/submit-rundown
GET    /api/program-episodes/upcoming?days=7
```

### Program Proposals
```
GET    /api/program-proposals
POST   /api/program-proposals
GET    /api/program-proposals/{id}
PUT    /api/program-proposals/{id}
DELETE /api/program-proposals/{id}
POST   /api/program-proposals/{id}/sync
GET    /api/program-proposals/{id}/embedded-view
POST   /api/program-proposals/{id}/submit
POST   /api/program-proposals/{id}/review
POST   /api/program-proposals/{id}/approve
POST   /api/program-proposals/{id}/reject
POST   /api/program-proposals/{id}/request-revision
```

### Program Approvals
```
GET    /api/program-approvals
POST   /api/program-approvals
GET    /api/program-approvals/pending
GET    /api/program-approvals/overdue
GET    /api/program-approvals/urgent
GET    /api/program-approvals/history
GET    /api/program-approvals/{id}
PUT    /api/program-approvals/{id}
POST   /api/program-approvals/{id}/review
POST   /api/program-approvals/{id}/approve
POST   /api/program-approvals/{id}/reject
POST   /api/program-approvals/{id}/cancel
```

---

## 7) Catatan Deadline Otomatis (untuk UI)
- Editor: **7 hari** sebelum `air_date` episode.
- Kreatif, Musik Arr, Sound Eng, Produksi, Art & Set Design: **9 hari** sebelum `air_date`.

---

## 8) Link Spreadsheet Proposal (Sumber Data)
`https://docs.google.com/spreadsheets/d/19eF1kyIALqQtDxvUvA_Uz0LD1oktMKx8DOTLcH8c2tc/edit?gid=1723355363#gid=1723355363`

---

Selesai. Dokumen ini dapat langsung Anda copy ke wiki/Notion/Docs tim FE.


