# Producer vs Program Manager – Tugas & API Dashboard

**Tanggal:** 2026-03-17  
**Tujuan:** Memisahkan tugas Producer vs Manager Program dan memastikan API Producer menampilkan 52 episode + progress dengan benar.

---

## 1. Pembagian Tugas (Jangan Tertukar di Frontend)

### Program Manager (Manager Program)
- **Membuat / mengedit program** (nama, deskripsi, budget, dll.)
- **Generate episode** (52 episode per tahun) — saat program dibuat atau manual trigger
- **Assign Production Team** ke program (termasuk menunjuk Producer)
- **Approve/reject** program proposal, jadwal, dll.
- **Tidak** melakukan: accept program sebagai Producer, edit rundown per episode, kontrol tayang mingguan, reminder crew

### Producer
- **Menerima program** yang menjadi tanggung jawabnya (accept program agar workflow jalan)
- **Melihat nama program & rundown**
- **Mengedit rundown** jika perlu & **ajukan ke Program Manager**
- **Mengontrol program live** — 1 episode tayang per minggu
- **Mengingatkan crew** lewat sistem (reminder)
- **Monitoring** semua pekerjaan di setiap proses dalam timnya
- **Mengintervensi jadwal** syuting & jadwal rekaman vokal
- **Tidak** melakukan: generate episode, edit program (nama/deskripsi/budget), create program

**Implikasi frontend:** Di dashboard **Producer**, jangan tampilkan tombol/aksi: "Generate Episode", "Edit Program", "Create Program". Itu tugas Program Manager. Producer hanya: Accept Program, Lihat Program & Episodes, Edit Rundown (submit ke Manager), Weekly Airing Control, Remind Crew, Monitoring, Intervensi Jadwal.

---

## 2. API untuk Dashboard Producer (52 Episode & Progress)

### 2.1 Daftar Program Producer (dengan jumlah episode & selesai)

**Endpoint:** `GET /api/live-tv/producer/programs`

Response setiap program sekarang menyertakan:
- `episodes_count` — total episode program (harus 52 jika Program Manager sudah generate)
- `episodes_finished_count` — episode dengan status `aired` (untuk tampilan "X of Y episodes finished")

Gunakan untuk tampilan: **Production Progress: X% — N of M episodes finished** (N = `episodes_finished_count`, M = `episodes_count`).

### 2.2 Detail Satu Program + Daftar Episode (untuk Producer)

**Endpoint:** `GET /api/live-tv/producer/programs/{id}`

- Hanya program yang Production Team-nya punya `producer_id` = user yang login.
- Response berisi:
  - Data program (nama, manager, production team, dll.)
  - `episodes` — daftar episode (urutan `episode_number`), biasanya 52
  - `episodes_count`, `episodes_finished_count`

Gunakan untuk: halaman detail program Producer (nama program, rundown, daftar 52 episode, progress).

### 2.3 Daftar Episode (filter by program)

**Endpoint:** `GET /api/live-tv/producer/episodes?program_id={id}`

- Mengembalikan episode dari program yang menjadi tanggung jawab Producer.
- Filter opsional: `program_id`, `status`, `workflow_state`.

Pastikan frontend mengirim `program_id` saat ingin menampilkan episode satu program (supaya tidak dapat `filtered-episodes = []` karena tanpa filter).

### 2.4 Accept / Reject Program (Producer)

- **Accept:** `POST /api/live-tv/producer/programs/{id}/accept`
- **Reject:** `POST /api/live-tv/producer/programs/{id}/reject` (body: `rejection_notes`)

**Catatan frontend:** Panggil endpoint ini dari **Producer flow**, jangan dari composable/action Program Manager. Error `emit is not a function` di `useProgramManagerActions.js` saat accept muncul karena Producer memakai action yang ditujukan untuk Manager (yang pakai `emit`). Buat action/komponen khusus Producer untuk accept/reject program (tanpa reuse `useProgramManagerActions` untuk accept program).

---

## 3. Alur Singkat

1. **Program Manager** buat program → sistem auto-generate 52 episode (atau manual generate).
2. **Program Manager** assign Production Team (termasuk Producer) ke program.
3. **Producer** lihat daftar program (`GET /producer/programs`) → tampil `episodes_count` (52) dan `episodes_finished_count` (0).
4. **Producer** accept program (`POST /producer/programs/{id}/accept`) → workflow bisa jalan (Music Arranger, dll.).
5. **Producer** buka detail program (`GET /producer/programs/{id}`) → dapat nama program, rundown, daftar 52 episode, progress.
6. Producer mengelola rundown, kontrol tayang mingguan, reminder crew, monitoring, intervensi jadwal lewat endpoint yang sudah ada (lihat dokumentasi Producer lain di folder ini).

---

## 4. Ringkasan Endpoint Producer (Program & Episode)

| Endpoint | Method | Keterangan |
|----------|--------|------------|
| `/api/live-tv/producer/programs` | GET | Daftar program + `episodes_count`, `episodes_finished_count` |
| `/api/live-tv/producer/programs/{id}` | GET | Detail program + daftar episode (52) + progress |
| `/api/live-tv/producer/programs/{id}/accept` | POST | Producer accept program |
| `/api/live-tv/producer/programs/{id}/reject` | POST | Producer reject program (body: `rejection_notes`) |
| `/api/live-tv/producer/pending-programs` | GET | Program yang belum di-accept Producer |
| `/api/live-tv/producer/episodes` | GET | Daftar episode (opsional: `?program_id={id}`) |
