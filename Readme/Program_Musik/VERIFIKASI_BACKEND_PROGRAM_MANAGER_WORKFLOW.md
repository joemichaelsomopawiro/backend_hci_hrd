# Verifikasi Backend – Workflow Program Manager

Dokumen ini memetakan kebutuhan bisnis **Program Manager** ke endpoint dan logika backend. Setelah backend dinyatakan sesuai, langkah berikutnya: cek frontend → testing di website → panduan cara pakai untuk user.

---

## 1. Membagi Kelompok Team Kerja (Berdasarkan Jabatan)

**Kebutuhan:**  
- Kelompok kerja: **Producer**, **Kreatif**, **Musik Arr**, **Sound Eng**, **Produksi**, **Editor**.  
- **Producer** dapat mengganti semua kelompok kerja dalam setiap proses.

| Backend | Status | Keterangan |
|--------|--------|------------|
| **Production Team (tim)** | ✅ | `ProductionTeam` punya `producer_id`, nama, deskripsi. |
| **Anggota per jabatan** | ✅ | `ProductionTeamMember` dengan `role`: `creative`, `musik_arr`, `sound_eng`, `production`, `editor`, `art_set_design`. Mapping ke User/Employee ada di `ProductionTeamService::getAvailableUsersForRole()`. |
| **Buat tim + tambah anggota** | ✅ | `POST /api/live-tv/production-teams` (create), `POST /api/live-tv/production-teams/{id}/members` (add). Controller: `ProductionTeamController`. |
| **Producer mengganti anggota** | ✅ | Producer: `PUT /api/live-tv/producer/team-assignments/{id}/replace-team`, remove member, assign team ke creative work. Controller: `ProducerController`. |

**Catatan:** Siapa yang boleh create production team (Program Manager vs Producer) tergantung aturan bisnis; endpoint production-teams ada dan dipakai untuk “kelompok kerja”.

---

## 2. Membagi Program Live ke Kelompok Kerja

**Kebutuhan:** Program live di-assign ke kelompok kerja (production team).

| Backend | Status | Keterangan |
|--------|--------|------------|
| **Program punya production_team_id** | ✅ | `Program` punya `production_team_id` (optional saat create). |
| **Assign team ke episode** | ✅ | `POST /api/live-tv/manager-program/episodes/{episodeId}/assign-team` dengan `production_team_id`. `ManagerProgramController::assignTeamToEpisode()`. |

Jadi: Program bisa punya 1 production team; Manager Program assign team ke episode (per episode atau turunan dari program).

---

## 3. Membuat Program Live & Database Program

**Kebutuhan:**  
- Menentukan program apa saja yang jadi program live.  
- Database program.  
- **Proposal Program (lampiran).**  
- Opsi jadwal tayang diajukan ke Manager Broadcasting.

| Backend | Status | Keterangan |
|--------|--------|------------|
| **Buat program** | ✅ | `POST /api/live-tv/programs`. Body: `name`, `description`, `category`, `manager_program_id`, `production_team_id`, `start_date`, `air_time`, `duration_minutes`, `broadcast_channel`, `target_views_per_episode`, dll. Controller: `ProgramController::store()`. |
| **Proposal (lampiran)** | ✅ | Program: `proposal_file_path`, `proposal_file_name`, `proposal_file_link`. Upload file saat ini dinonaktifkan; dipakai `proposal_file_link` (URL). |
| **Program Proposal (flow terpisah)** | ✅ | `ProgramProposalController`: CRUD proposal, submit, approve, reject, request revision. Setelah approve bisa generate program + episode (sesuai implementasi). |
| **Opsi jadwal → Manager Broadcasting** | ✅ | Manager Program: `POST /api/live-tv/manager-program/programs/{programId}/submit-schedule-options`. Manager Broadcasting/Distribution: `GET /api/live-tv/distribution-manager/schedule-options`, `POST .../schedule-options/{id}/approve`, `revise`, `reject`. |

---

## 4. Urutan Episode & Deadline Otomatis

**Kebutuhan:**  
- Sistem buat otomatis urutan episode 1 sampai seterusnya.  
- Deadline setiap episode baru: **7 hari sebelum tayang** untuk **Editor**.  
- **9 hari sebelum tayang** untuk **Creatif** dan **Produksi**.  
- Jika ada perbaikan QC, Manager Program bisa edit deadline untuk kebutuhan khusus.

| Backend | Status | Keterangan |
|--------|--------|------------|
| **Generate episode 1–52 (Sabtu)** | ✅ | Saat create program: `Program::generateYearlyEpisodes()`. Episode 1 = Sabtu pertama (dari `start_date`), lalu +7 hari per episode. Juga: `generate-next-year-episodes`, `generate-episodes-for-year`. |
| **Deadline Editor H-7** | ✅ | `Program::generateYearlyEpisodes()` dan `generateEpisodesForYear()`: role `editor` → `air_date - 7 days`. |
| **Deadline Creatif & Produksi H-9** | ✅ | Role `kreatif` dan `produksi` → `air_date - 9 days`. (Di DB/response bisa tetap pakai `kreatif`/`produksi` untuk kompatibilitas.) |
| **Deadline Musik Arr & Sound Eng** | ✅ | Di kode: H-8 untuk `musik_arr` dan `sound_eng`. |
| **Edit deadline (kebutuhan khusus/QC)** | ✅ | `PUT /api/live-tv/manager-program/deadlines/{deadlineId}`. Body: `deadline_date`, `reason`, `description`. `ManagerProgramController::editDeadlineById()`. |

---

## 5. Target Pencapaian Views & Data Mingguan

**Kebutuhan:** Membuat target pencapaian views per program; tarik data mingguan.

| Backend | Status | Keterangan |
|--------|--------|------------|
| **Set/update target views** | ✅ | `PUT /api/live-tv/manager-program/programs/{programId}/target-views`. Body: `target_views_per_episode`. |
| **Laporan performa mingguan** | ✅ | `GET /api/live-tv/manager-program/programs/{programId}/weekly-performance`. Response: target vs actual views, achievement per minggu/episode. |

---

## 6. Menutup Program Reguler yang Tidak Berkembang

**Kebutuhan:** Bisa menutup program reguler yang tidak berkembang.

| Backend | Status | Keterangan |
|--------|--------|------------|
| **Daftar program underperforming** | ✅ | `GET /api/live-tv/manager-program/programs/underperforming`. Query: `performance_status`, `min_episodes`, pagination. |
| **Tutup program** | ✅ | `POST /api/live-tv/manager-program/programs/{programId}/close`. Body: `reason`. Status program jadi `cancelled` dll. |

---

## 7. Intervensi Jadwal & Approval

**Kebutuhan:**  
- Mengintervensi semua jadwal.  
- Approval di semua bidang.  
- Bisa cancel jadwal syuting, ubah jadwal.

| Backend | Status | Keterangan |
|--------|--------|------------|
| **Cancel jadwal** | ✅ | `POST /api/live-tv/manager-program/schedules/{scheduleId}/cancel`. Body: `reason`, `notify_team`. |
| **Reschedule** | ✅ | `POST /api/live-tv/manager-program/schedules/{scheduleId}/reschedule`. Body: `new_datetime`, `reason`, `location`, dll. |
| **Override approval** | ✅ | `POST /api/live-tv/manager-program/approvals/{approvalId}/override`. Body: `action` (approve/reject), `reason`, `notes`. |
| **Cancel shooting (creative work)** | ✅ | `POST /api/live-tv/manager-program/creative-works/{creativeWorkId}/cancel-shooting`. |
| **List jadwal & approval** | ✅ | `GET /api/live-tv/manager-program/schedules`, `GET /api/live-tv/manager-program/approvals`. |

---

## Ringkasan Backend

- **Kelompok team kerja (jabatan)** → Production teams + members by role; Producer bisa ganti anggota lewat endpoint Producer.  
- **Program live ke kelompok kerja** → `production_team_id` di program + assign team ke episode.  
- **Program & proposal** → Create program (dengan proposal link), flow Program Proposal terpisah, opsi jadwal submit ke Manager Broadcasting.  
- **Episode & deadline** → Auto 52 episode (Sabtu), Editor H-7, Creatif/Produksi H-9, edit deadline oleh Manager Program.  
- **Target views & mingguan** → Target views per program, weekly performance.  
- **Tutup program** → Underperforming list + close dengan alasan.  
- **Intervensi** → Cancel/reschedule jadwal, override approval, cancel shooting.

**Kesimpulan:** Backend untuk workflow Program Manager **sudah mendukung** kebutuhan yang Anda sebut. Yang perlu dipastikan di frontend: semua fitur ini terpanggil dengan benar (role, validasi, dan alur sesuai aturan bisnis).

---

## Langkah Berikutnya

1. **Frontend** – Cek halaman/role Program Manager: apakah setiap fitur di atas punya UI dan memanggil endpoint yang benar.  
2. **Testing** – Test end-to-end di website (login Program Manager, buat tim, buat program, assign team, submit schedule options, edit deadline, target views, close program, cancel/reschedule, override).  
3. **Panduan user** – Buat dokumen “Cara menggunakan sistem Program Musik (Program Manager)” untuk memudahkan user memakai fitur di website.

---

*Dibuat dari pengecekan kode backend (ManagerProgramController, ProgramController, Program model, ProductionTeamService, ProductionTeamController, routes live_tv_api, API_DOCUMENTATION_MANAGER_PROGRAM).*
