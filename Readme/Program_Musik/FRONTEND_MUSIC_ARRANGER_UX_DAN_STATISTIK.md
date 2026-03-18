# Panduan Frontend: Music Arranger – Statistik & Teks UX

## 1. Statistik Dashboard (Total, Draft, Dikirim, Disetujui, Ditolak)

### Penyebab angka 0

Backend sebelumnya hanya mengembalikan `total_arrangements` dan `approved`. Angka **Draft**, **Dikirim**, dan **Ditolak** tidak disediakan, sehingga frontend menampilkan 0.

### Perbaikan backend (sudah diterapkan)

Endpoint **`GET /api/live-tv/music-arranger/statistics`** sekarang mengembalikan:

```json
{
  "success": true,
  "data": {
    "total": 4,
    "draft": 0,
    "submitted": 2,
    "approved": 1,
    "rejected": 1,
    "total_arrangements": 4
  }
}
```

### Mapping ke UI

| Key API   | Tampilkan di UI |
|-----------|------------------|
| `total`   | **Total**        |
| `draft`   | **Draft**        |
| `submitted` | **Dikirim**   |
| `approved`  | **Disetujui**  |
| `rejected`  | **Ditolak**   |

Pastikan halaman Music Arranger memanggil endpoint ini dan menampilkan `data.total`, `data.draft`, `data.submitted`, `data.approved`, `data.rejected` (bukan hardcode 0).

---

## 2. Saran Teks & UX agar User Lebih Mudah Paham

User utama sistem adalah Music Arranger. Teks di bawah ini bisa dipakai agar langkah dan pilihan lebih jelas.

### 2.1 Judul / Deskripsi Halaman

- **Sekarang:** "Usulan lagu (Song Proposals) dan aransemen (Music Arrangements). Gunakan tab di bawah."
- **Saran:**  
  "Kelola **usulan lagu** dan **aransemen** untuk tiap episode. Gunakan tab **Usulan Lagu** dan **Aransemen** di bawah."

### 2.2 Tombol "Buat Usulan Lagu"

- Tetap: **"Buat Usulan Lagu"** (sudah jelas).

### 2.3 Langkah di form (step text)

- **Sekarang:** "Langkah: Pilih 1. Program → 2. Episode → 3. Lagu & Penyanyi."
- **Saran:**  
  "**Urutan:** 1) Pilih Program → 2) Pilih Episode → 3) Pilih atau isi Lagu & Penyanyi (opsional). File aransemen diupload setelah usulan disetujui Producer. Episode yang sudah ada usulan/aransemen aktif ditandai ⛔."

### 2.4 Pilih Lagu – ganti "Pilih dari Database"

- **Sekarang:** "Pilih dari Database" / "Input Manual"
- **Saran (lebih jelas untuk user):**
  - **"Cari dari daftar lagu"** atau **"Pilih lagu yang sudah ada"** (untuk opsi yang memakai data dari API songs).
  - **"Ketik judul lagu baru"** atau **"Isi manual (lagu belum ada di daftar)"** (untuk input manual).

Bisa ditambah hint singkat di bawah:
- Untuk opsi daftar: *"Pilih dari lagu yang sudah terdaftar di sistem."*
- Untuk manual: *"Jika lagu belum ada di daftar, ketik judul lagu di sini."*

### 2.5 Pilih Penyanyi – ganti "Pilih dari Database"

- **Sekarang:** "Pilih dari Database" / "Input Manual" / "Tidak Pilih"
- **Saran:**
  - **"Cari dari daftar penyanyi"** atau **"Pilih penyanyi yang sudah ada"** (dari API singers).
  - **"Ketik nama penyanyi baru"** atau **"Isi manual (penyanyi belum di daftar)"** (input manual).
  - **"Tanpa penyanyi"** atau **"Langsung aransemen tanpa tentukan penyanyi"** (tetap opsional).

Hint opsional:
- *"Penyanyi opsional. Bisa dikosongkan dan diisi nanti setelah disetujui Producer."*

### 2.6 Placeholder / label search

- **Lagu:**  
  "Cari lagu (judul, artis, genre)..." → boleh tetap atau: **"Cari judul atau artis lagu..."**
- **Episode:**  
  "Cari episode (judul atau nomor)..." (sudah jelas).

### 2.7 Ringkasan alur singkat (opsional)

Bisa ditambah satu kalimat di atas form atau di help tooltip:

- *"Buat usulan lagu + penyanyi (opsional) untuk satu episode. Setelah Producer menyetujui, Anda bisa upload link file aransemen."*

---

## 4. Data Muncul Tanpa Refresh Setelah "Buat Usulan Lagu"

### Masalah

Setelah Music Arranger berhasil create usulan lagu, data baru tidak muncul di list sampai halaman di-refresh.

### Penyebab

Backend sudah mengembalikan data arrangement yang baru dibuat di response `POST .../arrangements` (key `data`). Yang kurang di frontend: **setelah create sukses, list tidak di-update**.

### Perbaikan di frontend (wajib)

Setelah **POST create arrangement sukses** (status 201):

1. **Opsi A (disarankan):** Panggil lagi **GET /api/live-tv/music-arranger/arrangements** (refetch list), lalu tampilkan hasilnya. Data terbaru akan muncul tanpa refresh.
2. **Opsi B:** Tambahkan **`response.data`** (objek arrangement yang dikembalikan backend) ke state/list yang dipakai untuk menampilkan daftar (prepend atau append sesuai urutan).

Jangan hanya menutup modal tanpa meng-update list.

---

## 5. Tampilan Episode: Jangan Pakai ID, Pakai Nomor & Judul

### Masalah

Episode ditampilkan sebagai **"Episode #3478"** — angka 3478 adalah **ID database** (bukan nomor episode). Per program hanya ada puluhan episode (mis. 52), jadi yang benar adalah menampilkan **nomor episode (1–52)** dan **judul episode**.

### Perbaikan backend (sudah diterapkan)

Setiap arrangement yang dikembalikan API (list dan create) sekarang menyertakan field **`episode_display`**:

- Contoh: `"Ep. 1 – Damai di Hatiku"` atau `"Episode 5"` jika judul kosong.
- Isi: nomor episode + judul episode, **bukan** ID.

### Perbaikan di frontend (wajib)

- **Tampilkan episode dengan:** `arrangement.episode_display` (prioritas).
- **Fallback** jika `episode_display` null: gunakan `arrangement.episode.episode_number` dan `arrangement.episode.title` (mis. `Episode #{episode_number} – {title}`).
- **Jangan tampilkan:** `episode_id` atau `episode.id` sebagai "Episode #...".

Contoh:

```text
✅ "Ep. 1 – Damai di Hatiku"
✅ "Episode 12"
❌ "Episode #3478"
```

---

## 7. Blok "Action Required" & Notifikasi

### Masalah

- Notifikasi yang sama sudah ada di **Semua Notifikasi**, jadi blok "Action Required" terasa duplikat.
- **View All** dan **Dismiss** tidak jelas fungsinya dan tampilannya **tidak bisa diklik**.

### Tujuan View All & Dismiss

| Tombol   | Fungsi yang diharapkan |
|----------|-------------------------|
| **View All** | Mengarahkan user ke daftar item yang butuh perhatian (mis. tab "Dikirim" / "Ditolak") atau ke halaman **Semua Notifikasi**, agar user bisa langsung tindak lanjuti. Harus **bisa diklik** (link/button). |
| **Dismiss**  | Menutup/sembunyikan **blok Action Required** saja (bukan menghapus notifikasi). Setelah dismiss, blok tidak tampil lagi sampai ada arrangement baru yang butuh perhatian. Juga harus **bisa diklik**. |

### Rekomendasi (sudah diterapkan: Opsi A)

1. **Opsi A (diterapkan):** Blok **"Action Required"** telah **dihapus** dari dashboard Music Arranger. Notifikasi tetap tersedia di ikon bell + halaman Semua Notifikasi (tidak duplikat). **View All** dan **Dismiss** ikut dihapus karena bagian dari blok yang sama.
2. **Opsi B** (jika nanti ingin tampil lagi): Pertahankan blok; **View All** dan **Dismiss** harus klikable (link ke Notifikasi atau list), dengan aksesibilitas yang jelas.

---

## 8. Desain Dashboard: Profesional, Bersih, Rapi, Responsif

### Bagian yang perlu di-update

- **Statistik:** Total, Draft, Dikirim, Disetujui, Ditolak (angka + label).
- **My Work:** Semua, Aransemen, Song Proposals (Usulan Lagu), History (Riwayat Disetujui).

### Prinsip desain

1. **Konsisten & bersih**
   - Satu style kartu untuk statistik (mis. card dengan border halus atau shadow ringan).
   - Satu style untuk menu My Work (kartu atau list yang jelas klikable).
   - Jarak (padding/margin) seragam, typography jelas (label kecil, angka menonjol).

2. **Statistik (Total, Draft, Dikirim, Disetujui, Ditolak)**
   - Tampilkan dalam **grid responsif**: desktop 5 kolom, tablet 2–3 kolom, mobile 2 kolom atau 1 kolom (stack).
   - Setiap item: **label** di atas (Draft, Dikirim, …), **angka** di bawah atau di samping (font lebih besar).
   - Warna netral untuk kartu; opsional: warna subtle per status (mis. hijau untuk Disetujui, abu untuk Draft, dll.).
   - Hindari tampilan penuh teks horizontal "Total 0 Draft 0 …" tanpa pemisah visual.

3. **My Work (Semua, Aransemen, Song Proposals, History)**
   - Setiap opsi sebagai **kartu klikable** atau **list item dengan ikon + teks**.
   - Deskripsi singkat di bawah judul (mis. "Manage all your music arrangements and tasks") dengan font lebih kecil, warna secondary.
   - Hover/focus state jelas (perubahan ringan background atau border).
   - Di mobile: kartu full-width, stack vertikal; jarak cukup agar mudah tap.

4. **Responsif**
   - Breakpoint jelas: mis. &lt; 640px (mobile), 640–1024px (tablet), &gt; 1024px (desktop).
   - Statistik dan My Work tidak overflow; teks tidak menumpuk (bisa truncate atau wrap dengan max-width).

5. **Aksesibilitas**
   - Semua area yang mengarah ke halaman lain harus **focusable dan klikable** (bukan `div` saja).
   - Kontras warna teks/label memadai (WCAG AA).

### Contoh struktur layout (konsep)

```text
[ Statistik ]
[ Total ] [ Draft ] [ Dikirim ] [ Disetujui ] [ Ditolak ]
   (grid 5 kolom → 2 kolom di mobile)

[ My Work ]
[ Semua ]          [ Aransemen ]        [ Song Proposals ]   [ History ]
Manage all…        Work on assigned…    Create and track…   View history…
   (grid 4 kolom → 2 kolom tablet → 1 kolom mobile)
```

### Checklist desain

- [ ] Statistik dalam grid; angka dan label terbaca; spacing rapi.
- [ ] My Work: setiap opsi jelas klikable, ada deskripsi singkat.
- [ ] View All & Dismiss (jika blok Action Required tetap ada): bisa diklik, ada hover/focus.
- [ ] Layout responsif (mobile / tablet / desktop) tanpa overflow.
- [ ] Warna dan typography konsisten; tampilan profesional dan bersih.

---

## 9. Checklist Frontend

- [ ] Panggil `GET /api/live-tv/music-arranger/statistics` dan tampilkan **Total**, **Draft**, **Dikirim**, **Disetujui**, **Ditolak** dari `data`.
- [ ] Setelah **create usulan lagu sukses**: refetch list arrangements **atau** tambahkan `response.data` ke list agar data muncul tanpa refresh.
- [ ] Tampilkan episode dengan **`episode_display`** (atau `episode.episode_number` + `episode.title`), **bukan** `episode.id`.
- [ ] **Action Required:** Hapus blok **atau** buat View All & Dismiss **klikable** (link ke Notifikasi / list), hindari duplikasi dengan Semua Notifikasi.
- [ ] **Dashboard:** Update layout statistik & My Work – grid responsif, kartu klikable, desain bersih dan profesional.
- [ ] Ganti label "Pilih dari Database" menjadi teks yang lebih jelas untuk Lagu dan Penyanyi.
- [ ] Tambah hint singkat di form agar urutan langkah jelas.
- [ ] Pastikan tab "Usulan Lagu" dan "Aransemen" konsisten.

---

**Backend:** `backend_hci_hrd` – statistics, `episode_display`, dan response create sudah siap.  
**Frontend:** Refetch list setelah create, pakai `episode_display`, perbaiki Action Required (klikable/hapus duplikat), dan terapkan desain dashboard yang rapi dan responsif.
