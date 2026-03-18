# Panduan Frontend: Sound Engineer Program Musik – UX & Desain Dashboard

Panduan ini mengacu pada **Music Arranger** dan **Producer** (song proposal & arrangement, terima kerja, approve/reject): desain **simple, bersih, rapi, responsif**.

---

## 1. Hapus Blok "Action Required"

### Yang harus dihapus

**Hapus seluruh blok notifikasi berikut dari dashboard Sound Engineer:**

- Teks: **"Action Required"**
- Teks: **"You have X rejected arrangement(s) that needs your help"**
- Tombol/link: **"View All"**
- Tombol/link: **"Dismiss"**

### Alasan

- Sama seperti di Music Arranger: notifikasi yang relevan sudah tersedia di **ikon bell** dan halaman **Semua Notifikasi**.
- Blok ini terasa duplikat dan memakan ruang tanpa menambah nilai.
- Setelah dihapus, pengguna tetap bisa melihat rejected arrangements yang butuh bantuan di bagian **Rejected Arrangements** (card list) dan di notifikasi.

### Tindakan di kode frontend

1. Hapus komponen/section yang me-render blok "Action Required" (termasuk View All dan Dismiss).
2. Jangan lagi menampilkan banner/alert berdasarkan count rejected arrangement di atas konten utama.
3. Tetap gunakan API `GET /api/live-tv/roles/sound-engineer/rejected-arrangements` hanya untuk **list card** Rejected Arrangements di bawah, bukan untuk banner.

---

## 2. Desain Dashboard: Simple, Bersih, Rapi, Responsif

Desain mengacu pada **Music Arranger** dan **Producer** (song proposal, terima kerja music arranger, approve/reject): **card simple**, tanpa elemen berlebihan.

### 2.1 Struktur halaman

- **Header:** Judul "Sound Engineer Dashboard" + deskripsi singkat (opsional, satu baris).
- **Tombol Refresh** (jika ada): satu tombol jelas di header atau di atas statistik.
- **Statistik (angka saja):** Recording Tasks, Editing Works, Needs Review, Rejected Arrangements – dalam **grid card kecil** (4 kolom desktop → 2 kolom tablet → 2 kolom mobile).
- **Section "Rejected Arrangements":** hanya jika ada data; tampilkan sebagai **list card simple** (satu card per item).
- **Section lain (Recording Tasks, Editing Works, Needs Review):** juga pakai **card list** dengan gaya yang sama.

### 2.2 Statistik (Recording Tasks, Editing Works, Needs Review, Rejected Arrangements)

- Satu **card kecil per metrik**: label di atas, angka di bawah (atau label kiri, angka kanan).
- Gaya: border halus atau shadow ringan; warna netral; font angka lebih besar dari label.
- **Responsif:** grid 4 kolom → 2 kolom (tablet) → 2 kolom (mobile), tanpa overflow.
- **Tidak perlu** teks panjang; cukup label + angka (mis. "Recording Tasks" + "12").

### 2.3 Rejected Arrangements – Card simple (seperti Music Arranger / Producer)

Setiap item rejected arrangement tampil sebagai **satu card** dengan:

- **Episode:** tampilkan **episode_display** (dari API) atau "Ep. {episode_number} – {episode.title}", **bukan** ID.
- **Status:** badge/kapsul kecil mis. "Needs Review" atau "Rejected".
- **Judul lagu / penyanyi:** satu baris (mis. "itali" atau song_title + singer_name).
- **Tanggal:** satu baris (mis. "2 Mar 2026") – dari `reviewed_at` atau `updated_at`.
- **Producer's Notes:** teks catatan producer (mis. "gabisa gini") – dari `review_notes` atau `rejection_reason`.
- **Satu tombol aksi:** **"Help Fix"** (primary), mengarah ke halaman/form bantu perbaikan untuk arrangement tersebut.

Prinsip:

- Satu card = satu arrangement; tidak gabung-gabung teks panjang.
- Spacing rapi (padding konsisten), typography jelas (label kecil, konten lebih besar).
- **Tidak** menambah elemen berlebihan (mis. banyak ikon, banyak tombol).
- Konsisten dengan card di Music Arranger (usulan lagu/aransemen) dan Producer (terima kerja, approve/reject).

### 2.4 Recording Tasks / Editing Works / Needs Review

- Jika ditampilkan sebagai list card: gunakan **gaya card yang sama** seperti Rejected Arrangements (episode_display, status, tanggal, satu aksi utama per card).
- Judul section singkat (mis. "Recording Tasks", "Editing Works", "Needs Review").
- Subteks opsional satu baris (mis. "Help improve arrangements that need revision") – boleh di bawah judul section, jangan di setiap card.

### 2.5 Responsif & aksesibilitas

- **Mobile:** card full-width, stack vertikal; tombol "Help Fix" full-width atau jelas tap target.
- **Tablet/Desktop:** grid card 2–3 kolom (sesuai lebar).
- Semua tombol/link **focusable dan klikable** (bukan div saja).
- Kontras teks memadai (WCAG AA).

---

## 3. Ringkasan checklist frontend

- [ ] **Hapus** blok "Action Required" (termasuk "You have X rejected…", "View All", "Dismiss").
- [ ] **Statistik:** tampil dalam grid card kecil (Recording Tasks, Editing Works, Needs Review, Rejected Arrangements); rapi dan responsif.
- [ ] **Rejected Arrangements:** tampil sebagai **list card simple**; setiap card berisi episode (episode_display), status, lagu/penyanyi, tanggal, Producer's Notes, satu tombol "Help Fix".
- [ ] **Recording Tasks / Editing Works / Needs Review:** gunakan **gaya card yang sama** (simple, bersih), konsisten dengan Music Arranger & Producer.
- [ ] **Desain:** simple, bersih, rapi, responsif; tanpa elemen berlebihan; konsisten dengan program musik (Music Arranger, Producer).
- [ ] **Refresh:** tombol Refresh (jika ada) tetap berfungsi untuk muat ulang data.

---

## 4. API yang relevan (backend sudah siap)

| Fungsi | Method | Endpoint |
|--------|--------|----------|
| Rejected arrangements (untuk list card) | GET | `/api/live-tv/roles/sound-engineer/rejected-arrangements` |
| Rejected song proposals | GET | `/api/live-tv/roles/sound-engineer/rejected-song-proposals` |
| Help fix song proposal | POST | `/api/live-tv/roles/sound-engineer/arrangements/{id}/help-fix-song-proposal` |
| Help fix arrangement | POST | `/api/live-tv/roles/sound-engineer/arrangements/{id}/help-fix-arrangement` |

Response rejected arrangements menyertakan relasi `episode`; gunakan **episode_display** (jika ada) atau `episode.episode_number` + `episode.title` untuk tampilan episode. Untuk Producer's Notes gunakan field **review_notes** atau **rejection_reason**.

---

**Backend:** `backend_hci_hrd` – endpoint Sound Engineer sudah tersedia.  
**Frontend:** Hapus Action Required; update desain dashboard dan card Rejected Arrangements/Recording/Editing/Needs Review menjadi simple, bersih, rapi, dan responsif seperti Music Arranger & Producer.
