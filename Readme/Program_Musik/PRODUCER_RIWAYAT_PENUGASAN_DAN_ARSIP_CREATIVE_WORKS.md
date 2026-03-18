# Producer: Riwayat Penugasan Tim & Arsip Creative Works

## Di mana melihat history "producer create team setting syuting vocal"?

### 1. Riwayat Penugasan Tim (Team Assignment History)

**Lokasi di UI:** Dashboard Producer → menu **Program Musik** → **Riwayat Penugasan**.

- Menampilkan **semua penugasan tim** (Syuting, Setting, Rekam Vokal) untuk episode-episode dari **program yang Anda kelola** sebagai Producer.
- Filter: **Semua** / **Syuting** / **Setting** / **Rekam Vokal**, plus pencarian episode/program.
- Data diambil dari API: `GET /api/live-tv/producer/all-team-assignments`.

**Jika tidak ada data:**

1. **Program harus punya Producer:** Pastikan di setiap Program, **Production Team**-nya memiliki **Producer** yang di-set ke user Anda (kolom `producer_id` di `production_teams`).
2. **Penugasan tim harus pernah dibuat:** Riwayat hanya berisi record setelah Producer **assign tim** (Syuting / Setting / Rekam Vokal) untuk suatu episode, misalnya lewat:
   - **Creative Works** → Approve creative work → **Assign Teams** (shooting_team_ids, setting_team_ids, recording_team_ids), atau
   - **Final approval** creative work dengan data tim.
3. Cek di database: apakah ada baris di `production_teams_assignment` untuk `episode_id` yang termasuk program Anda, dan `production_teams.producer_id` = user ID Anda.

---

### 2. Arsip Creative Works yang Di-approve Producer

**Lokasi di UI:** Dashboard Producer → tab **Creative Works** → sub-tab **Riwayat Disetujui** (Approved).

- Menampilkan **creative works dengan status `approved`** untuk episode-episode dari **program yang Anda kelola** sebagai Producer.
- Bisa filter: Episode ID, tanggal dari/sampai.
- Data diambil dari: `GET /api/live-tv/creative/works?status=approved` (backend sekarang memfilter hanya program yang producer_id = Anda).

**Jika tidak ada data:**

1. **Program punya Producer:** Sama seperti di atas, Program harus punya Production Team dengan `producer_id` = user Anda.
2. **Ada creative work yang sudah di-approve:** Pastikan setidaknya satu Creative Work untuk episode di program Anda sudah di-**final approve** oleh Producer (status = `approved`).
3. Setelah backend di-update: response hanya berisi creative works dari program yang Anda kelola; kalau belum ada yang approve, daftar memang kosong.

---

## Ringkasan

| Yang ingin dilihat | Tab / Menu | Sumber data |
|--------------------|------------|-------------|
| History tim Syuting / Setting / Rekam Vokal yang Anda buat | **Riwayat Penugasan** | `production_teams_assignment` untuk episode program Anda |
| Daftar creative works yang sudah Anda approve | **Creative Works** → **Riwayat Disetujui** | `creative_works` status `approved`, episode dari program Anda |

Keduanya memfilter berdasarkan **program yang Anda kelola sebagai Producer** (via `production_teams.producer_id`).
