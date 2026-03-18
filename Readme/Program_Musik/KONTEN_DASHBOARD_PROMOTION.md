# Konten Dashboard Promotion – Program Musik

**Lokasi tampilan:** Sidebar Program Musik → sub menu **Dashboard** (di `layout.vue` atau setara).

Dokumen ini hanya merinci **apa yang perlu ditampilkan** (data, section, aksi, endpoint). Desain visual (card, layout, style) diserahkan ke Anda; referensi seperti fitur Song Proposal bisa dipakai untuk inspirasi tampilan.

---

## Header utama (untuk semua user – jangan dihapus)

**Header ini dipakai di seluruh aplikasi untuk semua user.** Jangan dihapus atau diganti di halaman Promotion.

- **Judul aplikasi:** Sistem Manajemen SDM  
- **Badge/angka:** mis. `11` (notifikasi atau indikator lain sesuai implementasi)  
- **Profil user:** ikon 👤 + nama user (contoh: **Promotion Test**)

Konten dashboard Promotion (kartu, daftar pekerjaan, jadwal, link BTS/foto talent, dll.) ditampilkan **di bawah** header utama ini.

---

## 1. Alur singkat role Promotion

1. **Notifikasi dari Producer** setelah Producer meng-approve kerja Creative → Promotion dapat tugas baru.
2. **Jadwal syuting** per program/episode dikirim Producer (dari jadwal yang di-approve Program Manager / dibuat Creative); Promotion **menerima** jadwal tersebut.
3. **Pekerjaan Promotion:**
   - **BTS (Behind The Scene):** sistem hanya **menerima link file** (bukan upload file). Promotion memasukkan link BTS ke sistem.
   - **Foto talent:** sama, sistem hanya **menerima link file**. Promotion memasukkan link foto talent.
   - Link BTS dan link foto talent **bisa dilihat lagi** dan **bisa di-upload ulang** (ganti link) oleh Promotion.

---

## 2. Section yang perlu ada di dashboard

### 2.1 Notifikasi

**Tujuan:** Promotion melihat notifikasi dari Producer (setelah approve Creative).

**Data yang ditampilkan (contoh):**
- Judul notifikasi
- Pesan (mis. "Creative Work Episode X telah disetujui. Anda mendapat tugas baru. Jadwal syuting: …")
- Tanggal/waktu
- Status baca (read/unread)
- Link/aksi ke tugas terkait (mis. `promotion_work_id`, `episode_id`)

**Sumber data (pilih salah satu atau gabung):**
- **Unified notifications:** `GET /api/live-tv/unified-notifications/...` (cek route yang dipakai frontend)
- **Filter untuk Promotion:** notifikasi dengan `type` mis. `creative_work_approved_task_assigned` (dan mungkin `promotion_work_assigned` jika dipakai)
- **Payload notifikasi** biasanya berisi: `episode_id`, `episode_number`, `creative_work_id`, `shooting_schedule`, `promotion_work_id`

**Aksi:** Klik notifikasi → navigasi ke detail pekerjaan / jadwal syuting untuk episode tersebut.

---

### 2.2 Jadwal syuting per program/episode

**Tujuan:** Promotion melihat dan menerima jadwal syuting per program/episode yang dikirim Producer.

**Data yang ditampilkan (contoh per item):**
- Nama program
- Nomor episode (dan judul episode jika ada)
- Tanggal syuting (`shooting_date`)
- Waktu syuting (`shooting_time`)
- Lokasi (`location_data` atau teks dari Creative)
- Catatan (`shooting_notes`)
- Status: sudah diterima / belum (bisa dari status work atau flag tersendiri)

**Sumber data:**
- **GET** `GET /api/live-tv/promosi/works`  
  Response tiap work berisi: `episode` (program, episode_number), `shooting_date`, `shooting_time`, `location_data`, `shooting_notes`, `status`.
- Jadwal awal bisa dari Creative Work yang di-approve Producer; backend sudah set `shooting_date` dari `creativeWork->shooting_schedule` saat membuat PromotionWork.

**Aksi:**
- **Terima jadwal:**  
  **POST** `POST /api/live-tv/promosi/works/{id}/accept-schedule`  
  Body opsional: `shooting_date`, `shooting_time`, `location_data`, `shooting_notes` (jika Promotion ingin mengoreksi/ melengkapi).

---

### 2.3 Daftar pekerjaan (Promotion Works)

**Tujuan:** List semua tugas Promotion (per episode) beserta status dan aksi input link BTS & foto talent.

**Data yang ditampilkan (contoh per card/row):**
- Judul work (`title`)
- Deskripsi (`description`)
- Program & episode (dari `episode.program.name`, `episode.episode_number`)
- Status: `planning` | `shooting` | `editing` | `published`
- Jadwal syuting (jika sudah ada)
- Untuk work type **BTS/Talent:**  
  - Ada/tidak link BTS; ada/tidak link foto talent (bisa dari `file_links`)

**Sumber data:**
- **GET** `GET /api/live-tv/promosi/works`  
  Bisa filter: `?status=planning` atau `?episode_id=...`  
  Response include `file_links` (array).

**Aksi umum:**
- **Terima pekerjaan:**  
  **POST** `POST /api/live-tv/promosi/works/{id}/accept-work`  
  (hanya bila status `planning`).
- **Selesaikan pekerjaan:**  
  **POST** `POST /api/live-tv/promosi/works/{id}/complete-work`  
  (setelah link BTS dan foto talent ada; body opsional: `completion_notes`).

---

### 2.4 Input & tampilan link BTS

**Tujuan:** Promotion memasukkan **satu link file BTS** ke sistem; bisa melihat link yang tersimpan dan menggantinya (upload ulang).

**Tampilkan:**
- Form/field: **satu URL** (link file BTS).
- Daftar link BTS yang **sudah tersimpan** (dari `work.file_links` dengan `type === 'bts_video'`).  
  Setiap item: link (klik bisa buka), tanggal upload, opsional “Ganti / Upload ulang”.

**API:**
- **Simpan / ganti link BTS:**  
  **POST** `POST /api/live-tv/promosi/works/{id}/upload-bts-video`  
  Body: `{ "file_link": "https://..." }`  
  Backend menggantikan entry `bts_video` di `file_links` (satu link BTS per work).

**Validasi:** `file_link` harus URL valid (backend: `required|url`).

---

### 2.5 Input & tampilan link foto talent

**Tujuan:** Promotion memasukkan **satu atau banyak link file foto talent**; bisa melihat link yang tersimpan dan mengubah (upload ulang / tambah).

**Tampilkan:**
- Form/field: **satu atau banyak URL** (link file foto talent).
- Daftar link foto talent yang **sudah tersimpan** (dari `work.file_links` dengan `type === 'talent_photo'`).  
  Setiap item: link, tanggal upload, opsional “Hapus” atau “Upload ulang”.

**API:**
- **Simpan / ganti link foto talent:**  
  **POST** `POST /api/live-tv/promosi/works/{id}/upload-talent-photos`  
  Body: `{ "file_links": ["https://...", "https://..."] }`  
  Backend saat ini menggantikan semua entry `talent_photo` dengan array baru (lihat controller); jadi “upload ulang” = kirim lagi array lengkap yang diinginkan.

**Validasi:** `file_links` array of URL (backend: `required|array|min:1`, `file_links.*` = `required|url`).

---

### 2.6 Ringkasan link yang sudah di-upload (lihat kembali & upload ulang)

**Tujuan:** Satu tempat di dashboard (atau di detail work) agar Promotion bisa **melihat kembali** semua link BTS dan foto talent, dan memutuskan mau ganti yang mana.

**Sumber data:**  
Sama dengan response **GET** `GET /api/live-tv/promosi/works` atau detail work; field `file_links`:

- `{ "type": "bts_video", "file_link": "https://...", "uploaded_at": "...", "uploaded_by": id }`
- `{ "type": "talent_photo", "file_link": "https://...", "uploaded_at": "...", "uploaded_by": id }`

**Aksi:**
- Ubah BTS → pakai **upload-bts-video** (kirim link baru).
- Ubah foto talent → pakai **upload-talent-photos** (kirim array link baru).

Tidak perlu endpoint khusus “lihat”; cukup tampilkan `file_links` dan tombol “Ganti link BTS” / “Ganti link foto talent” yang mengarah ke form di atas.

---

### 2.7 Statistik (opsional)

**Tujuan:** Ringkasan jumlah pekerjaan per status (untuk card ringkasan atau filter).

**API:**  
**GET** `GET /api/live-tv/promosi/statistics`

**Response contoh:**  
`total_works`, `planning_works`, `shooting_works`, `editing_works`, `completed_works`, `my_works`, `my_completed`.

---

### 2.8 Riwayat aktivitas per work (opsional)

**Tujuan:** Promotion (atau admin) melihat riwayat per pekerjaan (siapa terima jadwal, upload BTS, upload foto, selesai, dll).

**API:**  
**GET** `GET /api/live-tv/promosi/works/{id}/history`

---

### 2.9 Sharing Works (setelah tayang)

**Alur:**
1. **Terima notifikasi** dari QC (QC telah approve) dan dari Broadcasting.
2. **Terima link YouTube** dari Broadcasting.
3. **Terima link Website** dari Broadcasting.
4. **Terima Pekerjaan** (status planning → shooting).
5. Empat tugas (masukkan bukti ke sistem):
   - Share link Website ke Facebook (masukkan bukti)
   - Buat Video HL untuk Story IG (masukkan bukti)
   - Buat Video HL untuk Reels Facebook (masukkan bukti)
   - Share ke grup Promosi WA (masukkan bukti)

**Tampilkan:**
- Link YouTube & Link Website (dari Broadcasting, field `social_media_links`).
- Per tugas: label tugas, status (Menunggu / Siap / Selesai), bukti yang sudah di-upload (link bukti dapat diklik dan dilihat lagi), tombol "Masukkan bukti" atau "Ganti bukti".

**Penyimpanan bukti:** Backend menyimpan di `PromotionWork.social_media_proof` (facebook_share, story_ig, reels_facebook, wa_group_share). Saat ini bukti berupa **link** (proof_link). Jika nanti mendukung file, bisa ditambah endpoint upload file bukti.

**API:**  
- **GET** `GET /api/live-tv/promosi/works` → filter `work_type`: share_facebook, story_ig, reels_facebook, share_wa_group.  
- **POST** `POST /api/live-tv/promosi/works/{id}/accept-work` (terima pekerjaan).  
- **POST** `POST /api/live-tv/promosi/works/{id}/share-facebook` (body: proof_link, facebook_post_url, notes).  
- **POST** `POST /api/live-tv/promosi/works/{id}/upload-story-ig` (body: video_link, proof_link, story_url, notes).  
- **POST** `POST /api/live-tv/promosi/works/{id}/upload-reels-facebook` (body: video_link, proof_link, reels_url, notes).  
- **POST** `POST /api/live-tv/promosi/works/{id}/share-wa-group` (body: proof_link, group_name, notes).

---

## 3. Endpoint ringkas

| Kegunaan                         | Method | Endpoint |
|----------------------------------|--------|----------|
| Daftar pekerjaan Promotion       | GET    | `/api/live-tv/promosi/works` |
| Terima jadwal syuting            | POST   | `/api/live-tv/promosi/works/{id}/accept-schedule` |
| Terima pekerjaan                 | POST   | `/api/live-tv/promosi/works/{id}/accept-work` |
| Simpan/ganti link BTS            | POST   | `/api/live-tv/promosi/works/{id}/upload-bts-video` |
| Simpan/ganti link foto talent    | POST   | `/api/live-tv/promosi/works/{id}/upload-talent-photos` |
| Selesaikan pekerjaan             | POST   | `/api/live-tv/promosi/works/{id}/complete-work` |
| Statistik                        | GET    | `/api/live-tv/promosi/statistics` |
| Riwayat per work                 | GET    | `/api/live-tv/promosi/works/{id}/history` |
| Notifikasi (unified / list)      | GET    | Sesuai route unified-notifications yang dipakai frontend |
| Share Facebook (bukti)           | POST   | `/api/live-tv/promosi/works/{id}/share-facebook` |
| Upload Story IG (bukti)          | POST   | `/api/live-tv/promosi/works/{id}/upload-story-ig` |
| Upload Reels Facebook (bukti)    | POST   | `/api/live-tv/promosi/works/{id}/upload-reels-facebook` |
| Share WA Group (bukti)           | POST   | `/api/live-tv/promosi/works/{id}/share-wa-group` |

---

## 4. Catatan backend

- **PromotionWork** dibuat otomatis saat Producer **approve Creative Work** (satu work type `bts_video` per episode).
- Notifikasi ke role Promotion: type `creative_work_approved_task_assigned` (dan bisa `promotion_work_assigned` di flow lain), dengan `promotion_work_id` dan `episode_id`, `shooting_schedule`.
- **Jadwal syuting** di PromotionWork di-set dari `CreativeWork.shooting_schedule` saat create; Promotion bisa update via `accept-schedule`.
- **Link hanya URL:** sistem tidak menyimpan file; hanya `file_link` (BTS) dan `file_links[]` (foto talent) di `PromotionWork.file_links`.

---

## 5. Referensi visual (mockup)

Referensi desain berikut bisa dipakai untuk tampilan dashboard Promotion: **simple, rapi, bersih, profesional, warna bersih, komposisi dan tata letak bagus, style card rapi dan simple.** Konten dashboard (semua section di bawah) berada **di bawah header utama** (Sistem Manajemen SDM + 👤 nama user).

| No | Gambar / layar | Yang bisa dipakai |
|----|----------------|-------------------|
| 1 | **Promotion Works (detail)** | Header halaman + deskripsi singkat; filter status (Semua Status, Pending, Sedang Dikerjakan, Selesai, Published); kartu per pekerjaan; blok **Jadwal Syuting** (tanggal, jam, lokasi, “Jadwal diterima”); blok **BTS Video** (label “Ada” + link); blok **Foto Talent** (jumlah + link dengan ikon external). |
| 2 & 3 | **Daftar + area link** | Baris tampilan link file (background pastel, mis. pink/abu) + ikon buka link; teks “Pekerjaan selesai” + centang hijau; kartu episode (E1, E2, …) dengan jadwal syuting & badge status (Pending / Sedang Dikerjakan); section **Ringkasan** di bawah. |
| 4 | **Kartu statistik + tab** | Empat kartu ringkas di atas: Pending, Sedang Dikerjakan, Selesai, Published (dengan angka); tab **Promotion Works** vs **Sharing Works**; bilah pencarian “Cari program atau episode…”. |
| 5 | **Sharing Works** | Halaman terpisah untuk tugas sharing (Facebook, WA, Story IG, Reels) dengan status Published dan link YouTube/Website. |
| 6 | **Ringkasan progres** | Section “Ringkasan” dengan tiga kartu horizontal: **BTS Uploaded** (x/y), **Foto Talent Uploaded** (x/y), **Total Sharing Selesai** (x/y). |
| 7 | **Riwayat Aktivitas** | Timeline: Jadwal diterima → Link BTS di-upload → Foto talent di-upload → Pekerjaan diselesaikan; tiap item dengan nama (mis. Promo Team) + tanggal/waktu; titik ungu untuk item terbaru, lingkaran abu-abu untuk lampau. |

**File referensi (jika disimpan di repo):**  
`assets/.../image-cd10203c-87da-42d0-967a-11ae755a181a.png` (1), `image-767298e8-...` (2), `image-7fe038d1-...` (3), `image-1a40b5d3-...` (4), `image-fddc58e7-...` (5), `image-391dd65f-...` (6), `image-a350d7a9-...` (7).

Dengan ini, konten (section 2) dan header utama tetap satu acuan; referensi visual di atas hanya untuk gaya tampilan card dan layout.
