# Verifikasi Frontend – Workflow Program Manager

Dokumen ini memetakan fitur **Program Manager** di frontend ke kebutuhan bisnis dan endpoint backend. Setelah backend dinyatakan sesuai (lihat `VERIFIKASI_BACKEND_PROGRAM_MANAGER_WORKFLOW.md`), frontend dicek apakah UI dan pemanggilan API sudah benar.

---

## Lokasi Utama

- **Dashboard:** `frontend_hci_hrd/src/components/music/program-manager-dashboard/ProgramManagerDashboard.vue`
- **Service:** `programManagerService.js`, `musicWorkflowService.js` (managerProgramService)
- **Actions:** `composables/useProgramManagerActions.js`
- **Tabs:** `tabs/*.vue` (ProgramsTab, EpisodesTab, TeamsTab, ScheduleOptionsTab, ScheduleInterventionTab, ApprovalOverrideTab, UnderperformingProgramsTab, dll.)
- **Modals:** `modals/NewProgramModal.vue`, `CreateTeamModal.vue`, `AssignTeamModal.vue`, `EditDeadlineModal.vue`, `SubmitScheduleOptionsModal.vue`, `SetTargetViewsModal.vue`, `CloseProgramModal.vue`, `RescheduleModal.vue`, dll.

---

## 1. Membagi Kelompok Team Kerja (Berdasarkan Jabatan)

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Buat tim baru | TeamsTab → "Create Team" → CreateTeamModal | OK | Form: nama, deskripsi, Producer, anggota per role (Music Arranger, Creative, Sound Engineer, Production, Editor). Submit → productionTeamService create + add members. |
| Daftar tim | TeamsTab, DashboardTab | OK | Data dari production teams API. |
| Kelola anggota (tambah/hapus) | ManageTeamModal (dari TeamsTab → Manage) | OK | Load available users per role, add/remove members. |
| Role yang ditampilkan | CreateTeamModal | OK | Music Arranger, Creative, Sound Engineer, Production, Editor, Producer (sebagai pemilik tim). |

**Catatan:** Producer mengganti kelompok kerja (replace team, remove member) ada di **Producer** dashboard (bukan Program Manager), sesuai backend.

---

## 2. Membagi Program Live ke Kelompok Kerja

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Assign team ke **program** | ProgramsTab → Program card → "Assign Team" → AssignTeamModal | OK | Pilih production team → updateProgram(programId, { production_team_id }) → PUT /live-tv/programs/{id}. |
| Assign team ke **episode** | EpisodesTab → Episode → "Assign Team" → AssignTeamModal | OK | Pilih production team → assignTeamToEpisode(episodeId, { production_team_id, notes }) → POST .../manager-program/episodes/{id}/assign-team. |
| Tampilan tim saat ini | AssignTeamModal, ProgramCard, EpisodeItem | OK | Menampilkan current team atau "No team assigned". |

---

## 3. Membuat Program Live & Proposal

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Buat program baru | Dashboard / ProgramsTab → "New Program" → NewProgramModal | OK | Form: name, description, category (musik/live_tv), Proposal Link (wajib), Broadcast info, target_views_per_episode. Submit → FormData → createProgram → POST /live-tv/programs. |
| Proposal (lampiran) | NewProgramModal | OK | Hanya Proposal Link (URL). Field: proposal_file_link, proposal_description. |
| Setelah create | useProgramManagerActions.submitProgram | OK | Backend sudah auto-generate 52 episode saat create. Tidak memanggil generateEpisodes lagi (perbaikan). Opsional: submit schedule options jika programData.schedule_option. |

---

## 4. Opsi Jadwal Tayang ke Manager Broadcasting

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Submit opsi jadwal | ProgramsTab → "Schedule Options" / dari modal create | OK | SubmitScheduleOptionsModal → submitScheduleOptions(programId, data) → POST .../submit-schedule-options. |
| Lihat status opsi jadwal | getScheduleOptions | OK | GET .../programs/{id}/schedule-options. |

---

## 5. Episode & Deadline

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Episode 1–52 otomatis | Setelah create program | OK | Dari response create program (backend sudah generate). Tidak ada panggilan generateEpisodes tambahan. |
| Generate episode tahun berikutnya | ProgramsTab → "Generate Next Year" | OK | generateNextYearEpisodes(programId) → POST .../generate-next-year-episodes. |
| Edit deadline | EpisodesTab → "Edit Deadline" → EditDeadlineModal | OK | deadline_date, reason, description → PUT .../manager-program/deadlines/{id}. |

---

## 6. Target Pencapaian Views & Data Mingguan

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Set/update target views | SetTargetViewsModal | OK | target_views_per_episode → PUT .../programs/{id}/target-views. |
| Laporan mingguan | ViewsAchievementTab, PerformanceModal, WeeklyChart | OK | getWeeklyPerformance(programId) → GET .../weekly-performance. |
| programManagerService.setTargetViews | programManagerService.js | OK | Diperbaiki: body memakai target_views_per_episode. |

---

## 7. Menutup Program Reguler

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Daftar underperforming | UnderperformingProgramsTab | OK | GET .../programs/underperforming. |
| Tutup program | CloseProgramModal | OK | reason → POST .../programs/{id}/close. |

---

## 8. Intervensi Jadwal & Approval

| Fitur | Frontend | Status | Keterangan |
|-------|----------|--------|------------|
| Daftar jadwal | ScheduleInterventionTab | OK | GET .../schedules. |
| Cancel jadwal | Modal cancel | OK | POST .../schedules/{id}/cancel. |
| Reschedule | RescheduleModal | OK | new_datetime, reason → POST .../schedules/{id}/reschedule. |
| Override approval | ApprovalOverrideTab | OK | GET .../approvals, POST .../approvals/{id}/override. |

---

## Perbaikan yang Dilakukan

1. **Create program – tidak panggil generateEpisodes lagi**  
   Backend POST /programs sudah meng-generate 52 episode. Di `useProgramManagerActions.js` panggilan `autoGenerateEpisodesForLiveProgram` setelah create dihapus agar tidak memicu 400 "Episodes already generated".

2. **programManagerService.setTargetViews**  
   Body request diseragamkan ke `{ target_views_per_episode }` sesuai backend; JSDoc diperbarui.

---

## Ringkasan

Frontend Program Manager sudah mendukung: kelompok team kerja (create/manage tim), assign program/episode ke tim, buat program dengan proposal link, submit opsi jadwal, episode & edit deadline, target views & weekly performance, tutup program, serta intervensi (cancel/reschedule, override approval). Perbaikan: hapus generateEpisodes setelah create program dan sesuaikan body setTargetViews.

---

*Dibuat dari pengecekan ProgramManagerDashboard, tabs, modals, useProgramManagerActions, programManagerService, musicWorkflowService.*
