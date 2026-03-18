# Tim Setting & Tim Syuting – API & UX

## 1. Data yang harus tampil di card (Tim Setting / Tim Syuting)

- **Program** – `work.episode.program.name`
- **Episode** – `work.episode.episode_number`
- **Jadwal syuting** – `work.creative_work.shooting_schedule` (atau dari creative work)
- **Lokasi** – `work.creative_work.shooting_location`
- **Tim Setting (siapa saja)** – dari `work.episode.team_assignments`:
  - Filter `team_type === 'setting'`
  - Untuk setiap assignment, ambil `members[].user.name`
  - Tampilkan misalnya: "Tim Setting: Budi, Ani, Citra"
- **Tim Syuting (siapa saja)** – sama, filter `team_type === 'shooting'`

Backend sekarang mengembalikan di **GET produksi works (index & show)**:
- `episode.team_assignments` (hanya `setting` & `shooting`, status bukan cancelled)
- `episode.team_assignments[].members[].user` (id, name, email)

## 2. Satu aksi untuk “list alat” (Tim Setting)

- **Input list alat (Ajukan ke Art & Set Properti)** = satu tombol (request equipment).
- **Ajukan kebutuhan** digabung ke sini: pakai flow yang sama (input list alat → ajukan ke Art & Set). Tidak perlu tombol terpisah “Ajukan kebutuhan”.
- Alat tidak bisa di-request jika sedang dipakai (backend/inventory sudah handle).

## 3. Alur & notifikasi

- Tim Setting: input list alat → ajukan ke Art & Set Properti.
- Setelah Art & Set **approve** → masuk ke Tim Syuting (notifikasi ke Tim Syuting).
- Ada notifikasi ke **Tim Setting**: “Barang sudah disetujui dan diterima tim syuting” (siapa yang menerima).
- Setelah itu Tim Setting bisa **Selesai Pekerjaan**.

(Notifikasi dan aturan “barang sudah diterima” bisa diimplementasi di backend/notifikasi terpisah.)

## 4. History / aktivitas

- **GET produksi work detail** (`GET .../produksi/works/{id}`) mengembalikan **`activity`** (array).
- Setiap item: `at` (ISO datetime), `type`, `label`, `description`.
- Contoh `type`: `work_created`, `equipment_requested`, `equipment_approved`, `equipment_received`, `equipment_returned`, `run_sheet_created`, `shooting_uploaded`, `work_completed`.
- Frontend: tampilkan sebagai timeline/history (untuk Tim Setting & Tim Syuting).

## 5. Equipment & Producer Requests (untuk Tim Setting)

- **Equipment** – dipakai Tim Setting untuk **input list alat** dan **ajukan ke Art & Set Properti** (satu flow).
- **Producer Requests** – permintaan dari Producer ke tim (bisa untuk Tim Setting/Syuting); tetap tampilkan jika relevan.

## 6. Desain card

- Desain card Production Works: rapi, bersih, profesional, sederhana (referensi: **song proposal card**, **budget request**).
- Pastikan: program, episode, jadwal, lokasi, dan **Tim Setting / Tim Syuting (siapa saja)** jelas terbaca di card.
- **Episode**: Tampilkan nomor episode (mis. Ep. 3), bukan ID work atau episode_id. API mengembalikan `episode_display` (mis. "Ep. 3") pada setiap work.

## 7. Equipment & tab Equipment

- **Equipment** = satu flow: **Input list alat (Ajukan ke Art & Set Properti)** dari kartu Production Work.
- Tab **Equipment** di dashboard untuk **riwayat/list** permintaan alat saja. Ajukan alat baru selalu dari tombol di kartu Production Work.
