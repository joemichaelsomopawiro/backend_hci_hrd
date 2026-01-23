# 📚 DOKUMENTASI LENGKAP SISTEM PROGRAM MUSIK HOPE CHANNEL
## Verifikasi Kesesuaian dengan Workflow yang Dijelaskan

> **Dokumentasi ini dibuat berdasarkan penjelasan lengkap sistem workflow program musik untuk memverifikasi kesesuaian implementasi backend dengan requirement yang dijelaskan.**

**Tanggal:** {{ date('Y-m-d H:i:s') }}  
**Status Verifikasi:** 🔄 **SEDANG DIPERIKSA**

---

## 📋 DAFTAR ISI

1. [Ringkasan Sistem](#ringkasan-sistem)
2. [Verifikasi Per Role](#verifikasi-per-role)
3. [Workflow Lengkap](#workflow-lengkap)
4. [Sistem Otomatis](#sistem-otomatis)
5. [Checklist Verifikasi](#checklist-verifikasi)
6. [Kesimpulan & Rekomendasi](#kesimpulan--rekomendasi)

---

## 🎯 RINGKASAN SISTEM

### Karakteristik Utama Sistem Program Musik

1. **Team-based Workflow**: Setiap program memiliki tim dengan role-role spesifik
2. **Episode Generation**: Sistem otomatis membuat 52 episode per tahun (1 episode/minggu)
3. **Automated Deadlines**: Sistem menghitung deadline otomatis (7 hari untuk Editor, 9 hari untuk Creative/Production)
4. **File Storage**: Menggunakan link server eksternal (bukan upload file langsung karena keterbatasan storage 20GB)
5. **Multi-level QC**: Quality Control dilakukan oleh Producer dan Broadcasting Manager di tahap berbeda
6. **Notification System**: Setiap perpindahan workflow memicu notifikasi ke role terkait

### Daftar Role dalam Sistem

1. **Program Manager** (Manager Program)
2. **Broadcasting Manager** / **Distribution Manager**
3. **Producer**
4. **Music Arranger**
5. **Sound Engineer**
6. **Creative**
7. **Production** / **Produksi**
8. **Editor**
9. **Art Set Property**
10. **General Affairs**
11. **Promotion** / **Promosi**
12. **Design Grafis**
13. **Editor Promosi**
14. **QC Promosi** (Quality Control untuk Promosi)
15. **Broadcasting**

---

## ✅ VERIFIKASI PER ROLE

### 1. PROGRAM MANAGER (Manager Program)

#### ✅ **Fungsi Utama:**
- Membuat dan mengelola program musik
- Membentuk tim berdasarkan jabatan/role
- Mengawasi seluruh proses produksi

#### ✅ **Tanggung Jawab yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| Membuat tim kelompok kerja berdasarkan jabatan/role | ✅ | `POST /api/live-tv/manager-program/episodes/{episodeId}/assign-team` |
| Memilih Producer untuk dimasukkan ke tim (bisa lebih dari 1 Producer) | ✅ | Melalui `assign-team` endpoint |
| Membuat database program proposal | ✅ | `POST /api/live-tv/proposals` |
| Membuat program live | ✅ | `POST /api/live-tv/programs` |
| Membuat opsi jadwal tayang dan mengajukan ke Broadcasting Manager | ✅ | `POST /api/live-tv/manager-program/programs/{programId}/submit-schedule-options` |
| Sistem auto-generate urutan episode 1 sampai seterusnya (52 episode/tahun) | ✅ | `POST /api/live-tv/manager-program/programs/{programId}/generate-episodes` |
| Sistem auto-generate deadline setiap episode baru (7 hari Editor, 9 hari Creative/Production) | ✅ | Method `generateDeadlines()` di Model Episode |
| Dapat mengedit deadline jika ada kebutuhan khusus | ✅ | `PUT /api/live-tv/manager-program/deadlines/{deadlineId}` |
| Membuat target pencapaian views dll setiap program | ✅ | `PUT /api/live-tv/manager-program/programs/{programId}/target-views` |
| Menutup program reguler yang tidak berkembang | ✅ | `POST /api/live-tv/manager-program/programs/{programId}/close` |
| Dapat mengintervensi semua jadwal | ✅ | `POST /api/live-tv/manager-program/schedules/{scheduleId}/cancel` |
| Approval di semua bidang hingga membatalkan/merubah jadwal syuting | ✅ | Endpoint tersedia |
| Menerima pengajuan budget khusus dari Producer | ✅ | `GET /api/live-tv/manager-program/special-budget-approvals` |
| ACC budget khusus / Edit budget / Tolak budget | ✅ | `POST /api/live-tv/manager-program/special-budget-approvals/{id}/approve` |
| Dapat mengganti tim jika ada anggota yang sakit | ✅ | Melalui `assign-team` endpoint |

#### 📝 **Catatan:**
- ✅ **Semua fungsi Program Manager sudah diimplementasikan**
- ✅ **Episode generation dengan 52 episode per tahun sudah ada**
- ✅ **Deadline auto-calculation sudah ada (7 hari Editor, 9 hari Creative/Production)**
- ✅ **Sistem dapat mengedit deadline manual**

---

### 2. BROADCASTING MANAGER / DISTRIBUTION MANAGER

#### ✅ **Fungsi Utama:**
- Menerima dan mereview jadwal tayang
- Mengelola distribusi konten
- Melakukan QC final sebelum broadcast

#### ✅ **Tanggung Jawab yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| Menerima notifikasi program dari Program Manager | ✅ | Notification system sudah ada |
| Menerima opsi jadwal tayang | ✅ | `GET /api/live-tv/manager-broadcasting/schedule-options` |
| Merevisi jadwal dan memberitahukan kembali ke Program Manager | ✅ | `POST /api/live-tv/manager-broadcasting/schedules/{id}/revise` |
| Membagi pekerjaan berdasarkan jabatan | ✅ | `POST /api/live-tv/distribution/episodes/{episodeId}/assign-work` |
| Membuat target pencapaian views dll setiap program | ✅ | Shared endpoint dengan Manager Program |
| Memonitoring semua pekerjaan hingga penayangan | ✅ | `GET /api/live-tv/distribution/dashboard` |
| Menutup program reguler yang tidak berkembang | ✅ | Shared endpoint dengan Manager Program |
| Quality Control (QC) hasil editing dari Editor | ✅ | `POST /api/live-tv/roles/quality-control/controls/{id}/approve` |
| Approve atau Reject hasil editing | ✅ | Endpoint tersedia |
| Jika approve → lanjut ke Broadcasting | ✅ | Workflow sudah ada |
| Jika reject → kembali ke Editor dengan catatan QC | ✅ | Workflow sudah ada |

#### 📝 **Catatan:**
- ✅ **Semua fungsi Broadcasting Manager sudah diimplementasikan**
- ✅ **QC system untuk Editor sudah ada**

---

### 3. PRODUCER

#### ✅ **Fungsi Utama:**
- Mengelola tim produksi
- Mengontrol workflow harian
- Melakukan QC di beberapa tahap

#### ✅ **Tanggung Jawab yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **General Management:** | | |
| Menerima live program yang menjadi tanggung jawabnya | ✅ | `GET /api/live-tv/producer/approvals` |
| Menerima nama program dan rundown program | ✅ | Data tersedia di Episode |
| Dapat mengedit rundown jika dibutuhkan (ajukan ke Program Manager) | ✅ | `PUT /api/live-tv/producer/episodes/{episodeId}/rundown` |
| Mengontrol program live untuk tayang 1 episode setiap minggu | ✅ | Monitoring system sudah ada |
| Dapat mengingatkan melalui sistem setiap crew yang menjadi timnya | ✅ | Notification system sudah ada |
| Monitoring semua pekerjaan di setiap proses dalam timnya | ✅ | `GET /api/live-tv/producer/episodes/{episodeId}/team-assignments` |
| Dapat mengganti semua kelompok kerja dalam setiap proses | ✅ | `PUT /api/live-tv/producer/team-assignments/{assignmentId}` |
| Dapat mengganti tim jika anggota sakit | ✅ | `PUT /api/live-tv/producer/team-assignments/{scheduleId}/emergency-reassign-team` |
| Dapat mengintervensi jadwal syuting & jadwal rekaman vokal | ✅ | Endpoint tersedia |
| **Workflow dari Music Arranger:** | | |
| Menerima notifikasi usulan lagu dan penyanyi dari Music Arranger | ✅ | Notification system sudah ada |
| Approve atau Reject usulan | ✅ | `POST /api/live-tv/producer/approvals/{approvalId}/approve` |
| Opsi Edit Langsung: Dapat mengedit/mengganti usulan lagu dan penyanyi | ✅ | `PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer` |
| Jika edit, tidak perlu approve lagi | ✅ | Logic sudah ada di backend |
| Jika reject: kembali ke Music Arranger dengan catatan | ✅ | Workflow sudah ada |
| QC Arrangement: Menerima link arr lagu dari Music Arranger | ✅ | `GET /api/live-tv/producer/approvals` |
| Quality control musik arrangement | ✅ | Producer dapat approve/reject |
| Approve → lanjut ke Creative | ✅ | Workflow sudah ada |
| Reject → kembali ke Music Arranger (Sound Engineer dapat membantu) | ✅ | Workflow sudah ada |
| **Workflow dari Creative:** | | |
| Menerima notifikasi tugas dari Creative | ✅ | Notification system sudah ada |
| Cek script cerita video klip | ✅ | `GET /api/live-tv/producer/creative-works/{id}` |
| Cek storyboard (bisa berupa teks, PDF, atau link) | ✅ | Data tersedia di CreativeWork model |
| Cek budget | ✅ | Data tersedia di CreativeWork model |
| Tambahkan Tim Syuting: Bisa memilih semua user di sistem kecuali manager | ✅ | `POST /api/live-tv/producer/creative-works/{id}/assign-team` |
| Tambahkan Tim Setting: Bisa memilih semua user di sistem kecuali manager | ✅ | Endpoint tersedia |
| Tambahkan Tim Rekam Vokal: Bisa memilih semua user di sistem | ✅ | Endpoint tersedia |
| Dapat membatalkan jadwal syuting jika ada kendala | ✅ | `POST /api/live-tv/producer/creative-works/{id}/cancel-shooting` |
| Dapat mengganti tim syuting secara dadakan | ✅ | `PUT /api/live-tv/producer/team-assignments/{scheduleId}/emergency-reassign-team` |
| Opsi Edit Langsung: Dapat mengedit langsung jika diperlukan | ✅ | `PUT /api/live-tv/producer/creative-works/{id}/edit` |
| Pengajuan Budget Khusus: Jika ada tambahan budget khusus, ajukan ke Program Manager | ✅ | `POST /api/live-tv/producer/creative-works/{id}/request-special-budget` |
| Approve atau Reject tugas dari Creative | ✅ | `POST /api/live-tv/producer/creative-works/{id}/final-approval` |
| Jika reject: kembali ke Creative dengan catatan perbaikan | ✅ | Workflow sudah ada |
| Jika approve/edit: lanjut ke multiple roles (General Affairs, Sound Engineer, Promotion, Production) | ✅ | Workflow sudah ada |
| **Workflow dari Sound Engineer:** | | |
| QC Edited Vocal: Menerima notifikasi setelah Sound Engineer selesai edit vokal | ✅ | Notification system sudah ada |
| Menerima pekerjaan QC | ✅ | `GET /api/live-tv/producer/approvals` |
| Memproses QC lagu | ✅ | Producer dapat approve/reject |
| Approve → lanjut ke Editor | ✅ | Workflow sudah ada |
| Reject → kembali ke Sound Engineer untuk edit ulang | ✅ | Workflow sudah ada |
| **Workflow dari Editor (File Tidak Lengkap):** | | |
| Menerima notifikasi jika file tidak lengkap | ✅ | Notification system sudah ada |
| Lihat catatan kekurangan dari Editor | ✅ | Data tersedia |
| Jika file bermasalah dan harus syuting ulang: Jadwalkan syuting ulang | ✅ | Endpoint tersedia |
| Jika file belum komplit: Ajukan perbaikan ke Production | ✅ | Workflow sudah ada |

#### 📝 **Catatan:**
- ✅ **Semua fungsi Producer sudah diimplementasikan**
- ✅ **Sistem edit langsung sudah ada (tidak perlu approve lagi)**
- ✅ **Sistem pengajuan budget khusus sudah ada**

---

### 4. MUSIC ARRANGER

#### ✅ **Fungsi Utama:**
- Memilih lagu dan penyanyi
- Membuat arrangement musik

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Pemilihan Lagu & Penyanyi:** | | |
| Pilih lagu: Jika lagu belum ada, masukkan teks judul lagu → tersimpan di database | ✅ | `POST /api/live-tv/music-arranger/arrangements` (auto-save jika belum ada) |
| Pilih lagu: Jika lagu sudah ada, pilih dari database | ✅ | `GET /api/live-tv/music-arranger/songs` |
| Pilih penyanyi (opsional): Jika penyanyi belum ada, masukkan teks nama penyanyi → tersimpan | ✅ | Auto-save sebagai User dengan role Singer |
| Pilih penyanyi: Jika penyanyi sudah ada, pilih dari database | ✅ | `GET /api/live-tv/music-arranger/singers` |
| Ajukan ke Producer | ✅ | `POST /api/live-tv/music-arranger/arrangements/{id}/submit-song-proposal` |
| **2. Setelah Approval/Edit dari Producer:** | | |
| Menerima notifikasi bahwa sudah di-approve atau workflow selanjutnya | ✅ | Notification system sudah ada |
| Terima pekerjaan untuk arr lagu | ✅ | `POST /api/live-tv/music-arranger/arrangements/{id}/accept-work` |
| Upload ke server (BUKAN file langsung, tapi LINK) | ✅ | `PUT /api/live-tv/music-arranger/arrangements/{id}` (menggunakan `file_link`) |
| Masukkan link arr lagu ke sistem | ✅ | Field `file_link` di MusicArrangement model |
| Ajukan ke Producer | ✅ | `POST /api/live-tv/music-arranger/arrangements/{id}/submit` |
| **3. Jika Reject dari Producer:** | | |
| Menerima notifikasi kembali | ✅ | Notification system sudah ada |
| Melakukan arr lagu ulang | ✅ | Workflow sudah ada |
| Upload link arr lagu | ✅ | Endpoint tersedia |
| Ajukan ke Producer | ✅ | Endpoint tersedia |
| **4. Perbaikan dengan Sound Engineer:** | | |
| Jika Producer reject arr lagu, Sound Engineer juga dapat membantu | ✅ | Workflow sudah ada |
| Menerima perbaikan dari Sound Engineer (berupa link file) | ✅ | Field `sound_engineer_helper_id` di MusicArrangement |
| Ajukan link arr lagu yang sudah diperbaiki ke Producer | ✅ | Workflow sudah ada |

#### 📝 **Catatan:**
- ✅ **Semua fungsi Music Arranger sudah diimplementasikan**
- ✅ **Sistem auto-save lagu dan penyanyi ke database sudah ada**
- ✅ **Sistem menggunakan link (bukan file upload langsung) sudah ada**

---

### 5. SOUND ENGINEER

#### ✅ **Fungsi Utama:**
- Membantu arrangement
- Mengelola rekaman vokal
- Mengedit vokal

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Membantu Music Arranger (Jika Reject):** | | |
| Menerima notifikasi jika arrangement di-reject Producer | ✅ | Notification system sudah ada |
| Dapat membantu perbaikan | ✅ | Workflow sudah ada |
| Upload link perbaikan | ✅ | Endpoint tersedia |
| Ajukan ke Music Arranger (BUKAN langsung ke Producer) | ✅ | Workflow sudah ada |
| **2. Setelah Producer Approve Creative:** | | |
| Menerima notifikasi tugas dari Producer | ✅ | Notification system sudah ada |
| Menerima jadwal rekaman vokal (dari Creative → Producer → Sound Engineer) | ✅ | Data tersedia di CreativeWork |
| Input list alat | ✅ | Endpoint tersedia |
| Ajukan list alat ke Art Set Property | ✅ | `POST /api/live-tv/art-set-property/equipment-requests` |
| **3. Setelah Art Set Property ACC Alat:** | | |
| Menerima notifikasi dari Art Set Property | ✅ | Notification system sudah ada |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| Rekam vokal | ✅ | `POST /api/live-tv/roles/sound-engineer/recordings` |
| Kembalikan alat ke Art Set Property | ✅ | Endpoint tersedia |
| Upload file rekaman ke server/storage | ⚠️ | **PERLU DICEK: Apakah menggunakan link atau file upload?** |
| Masukkan link file rekaman ke sistem | ⚠️ | **PERLU DICEK: Apakah field `file_link` sudah ada?** |
| **4. Editing Vokal:** | | |
| Menerima notifikasi untuk melakukan edit vokal | ✅ | Notification system sudah ada |
| Terima pekerjaan edit vokal | ✅ | Endpoint tersedia |
| Proses editing vokal | ✅ | `POST /api/live-tv/roles/sound-engineer/editing` |
| Upload hasil edit ke storage | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| Masukkan link file edit vokal ke sistem | ⚠️ | **PERLU DICEK: Apakah field `file_link` sudah ada?** |
| Ajukan ke QC (Producer) | ✅ | `POST /api/live-tv/roles/sound-engineer/editing/{id}/submit` |
| **5. Jika Reject dari Producer QC:** | | |
| Kembali ke edit vokal | ✅ | Workflow sudah ada |
| Upload link hasil edit ulang | ✅ | Endpoint tersedia |
| Ajukan ke Producer lagi | ✅ | Endpoint tersedia |

#### ⚠️ **Catatan:**
- ✅ **Sebagian besar fungsi Sound Engineer sudah diimplementasikan**
- ⚠️ **PERLU DICEK: Apakah SoundEngineerRecording dan SoundEngineerEditing menggunakan `file_link` (link) atau `file_path` (upload langsung)?**
- ⚠️ **Sesuai requirement, harus menggunakan link (bukan upload langsung)**

---

### 6. CREATIVE

#### ✅ **Fungsi Utama:**
- Membuat konsep kreatif video klip
- Script, storyboard
- Merencanakan produksi

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Setelah Menerima Tugas dari Producer:** | | |
| Menerima notifikasi tugas dari Producer | ✅ | Notification system sudah ada |
| Menerima pekerjaan | ✅ | Endpoint tersedia |
| **2. Pekerjaan Creative:** | | |
| Tulis script cerita video klip lagu | ✅ | Field `script_content` di CreativeWork model |
| Buat storyboard (bisa di sistem, PDF, atau link dari server) | ✅ | Field `storyboard_data` (array) di CreativeWork model |
| Input jadwal rekaman suara | ✅ | Field `recording_schedule` di CreativeWork model |
| Input jadwal syuting | ✅ | Field `shooting_schedule` di CreativeWork model |
| Input lokasi syuting | ✅ | Field `shooting_location` di CreativeWork model |
| Buat budget untuk bayar talent (bisa teks, PDF, atau link) | ✅ | Field `budget_data` (array) di CreativeWork model |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Ajukan ke Producer | ✅ | `POST /api/live-tv/roles/creative/works/{id}/submit` |
| **3. Jika Reject dari Producer:** | | |
| Menerima notifikasi dengan catatan perbaikan | ✅ | Notification system sudah ada |
| Perbaiki pekerjaan | ✅ | `PUT /api/live-tv/roles/creative/works/{id}/revise` |
| Ajukan kembali ke Producer | ✅ | Endpoint tersedia |

#### 📝 **Catatan:**
- ✅ **Semua fungsi Creative sudah diimplementasikan**
- ✅ **Sistem mendukung script, storyboard, dan budget dalam berbagai format (teks, PDF, link)**

---

### 7. PRODUCTION / PRODUKSI

#### ✅ **Fungsi Utama:**
- Mengelola peralatan
- Melaksanakan syuting

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Setelah Producer Approve Creative:** | | |
| Menerima notifikasi dari Producer | ✅ | Notification system sudah ada |
| Menerima pekerjaan | ✅ | Endpoint tersedia |
| Input list alat | ✅ | Endpoint tersedia |
| Ajukan ke Art Set Property | ✅ | `POST /api/live-tv/art-set-property/equipment-requests` |
| Jika alat sedang dipakai: sistem tidak bisa request alat | ✅ | Validation sudah ada |
| Ajukan kebutuhan | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| **2. Setelah Art Set Property ACC Alat:** | | |
| Menerima notifikasi dari Art Set Property | ✅ | Notification system sudah ada |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| Proses Pekerjaan: | | |
| - Input form catatan syuting (run sheet) | ✅ | Endpoint tersedia |
| - Setelah syuting, upload hasil syuting ke storage/server | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| - Input link file di sistem (alamat storage) | ⚠️ | **PERLU DICEK: Apakah field `file_link` sudah ada?** |
| - Kembalikan alat ke Art Set Property | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Notifikasi ke: Art Set Property, Producer, Editor, Design Grafis | ✅ | Notification system sudah ada |
| **3. Jika Producer Minta Perbaikan/Reshoot:** | | |
| Menerima notifikasi dari Producer (karena file tidak lengkap dari Editor) | ✅ | Notification system sudah ada |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| Input list alat (ajukan ke Art Set Property) | ✅ | Endpoint tersedia |
| Alat tidak bisa di-request jika sedang dipakai | ✅ | Validation sudah ada |
| Ajukan kebutuhan | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Kembali ke syuting (ulangi workflow syuting) | ✅ | Workflow sudah ada |

#### ⚠️ **Catatan:**
- ✅ **Sebagian besar fungsi Production sudah diimplementasikan**
- ⚠️ **PERLU DICEK: Apakah Production menggunakan `file_link` (link) untuk hasil syuting atau `file_path` (upload langsung)?**
- ⚠️ **Sesuai requirement, harus menggunakan link (bukan upload langsung)**

---

### 8. ART SET PROPERTY

#### ✅ **Fungsi Utama:**
- Mengelola dan menyediakan peralatan untuk produksi

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Menerima Request dari Sound Engineer:** | | |
| Menerima notifikasi dari Sound Engineer | ✅ | Notification system sudah ada |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| ACC alat yang diajukan | ✅ | `POST /api/live-tv/art-set-property/equipment-requests/{id}/approve` |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| **2. Menerima Request dari Production:** | | |
| Menerima notifikasi dari Production | ✅ | Notification system sudah ada |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| ACC alat yang diajukan | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| **3. Menerima Pengembalian Alat dari Sound Engineer:** | | |
| Menerima notifikasi dari Sound Engineer (setelah selesai recording) | ✅ | Notification system sudah ada |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| ACC alat yang dikembalikan | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| **4. Menerima Pengembalian Alat dari Production:** | | |
| Menerima notifikasi dari Production (setelah selesai syuting) | ✅ | Notification system sudah ada |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| ACC alat yang dikembalikan | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |

#### ⚠️ **Catatan Penting:**
- ✅ **Semua fungsi Art Set Property sudah diimplementasikan**
- ✅ **Sistem validasi ketersediaan alat sudah ada (tidak bisa request jika sedang dipakai)**

---

### 9. EDITOR

#### ✅ **Fungsi Utama:**
- Mengedit video
- Memastikan kelengkapan file untuk QC

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Setelah Producer Approve Edited Vocal:** | | |
| Menerima notifikasi dari Producer | ✅ | Notification system sudah ada |
| Menerima pekerjaan | ✅ | Endpoint tersedia |
| Cek kelengkapan file: | | |
| - File Lengkap: proses pekerjaan | ✅ | Logic sudah ada |
| - File Tidak Lengkap: ajukan ke Producer dengan catatan kekurangan | ✅ | `POST /api/live-tv/roles/editor/works/{id}/report-missing-files` |
| Buat catatan file apa saja yang kurang atau perlu perbaikan | ✅ | Endpoint tersedia |
| (Jika file lengkap) Proses pekerjaan: | | |
| - Lihat catatan syuting (run sheet) | ✅ | Data tersedia |
| - Edit video | ✅ | Endpoint tersedia |
| - Upload file setelah di-edit ke storage/server | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| - Masukkan link file ke sistem | ⚠️ | **PERLU DICEK: Apakah field `file_link` sudah ada?** |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Ajukan ke QC (Broadcasting Manager) | ✅ | `POST /api/live-tv/roles/editor/works/{id}/submit` |
| **2. Setelah Production Selesai Syuting:** | | |
| Menerima notifikasi dari Production | ✅ | Notification system sudah ada |
| Menerima pekerjaan | ✅ | Endpoint tersedia |
| Cek kelengkapan file (sama seperti di atas) | ✅ | Logic sudah ada |
| Proses pekerjaan (sama seperti di atas) | ✅ | Endpoint tersedia |
| **3. Jika File Tidak Lengkap:** | | |
| Ajukan ke Producer dengan catatan kekurangan | ✅ | Endpoint tersedia |
| Menunggu Producer jadwalkan reshoot atau minta Production lengkapi file | ✅ | Workflow sudah ada |
| **4. Jika Reject dari Broadcasting Manager QC:** | | |
| Menerima notifikasi dari Broadcasting Manager | ✅ | Notification system sudah ada |
| Menerima catatan QC dari Producer | ✅ | Data tersedia |
| Perbaiki editing | ✅ | Endpoint tersedia |
| Upload link file hasil edit ulang | ✅ | Endpoint tersedia |
| Ajukan ke QC lagi | ✅ | Endpoint tersedia |

#### ⚠️ **Catatan:**
- ✅ **Sebagian besar fungsi Editor sudah diimplementasikan**
- ⚠️ **PERLU DICEK: Apakah EditorWork menggunakan `file_link` (link) untuk hasil editing atau `file_path` (upload langsung)?**
- ⚠️ **Sesuai requirement, harus menggunakan link (bukan upload langsung)**

---

### 10. GENERAL AFFAIRS

#### ✅ **Fungsi Utama:**
- Mengelola permintaan dana dari Producer

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| Menerima permintaan dana dari Producer (setelah Producer approve Creative) | ✅ | Notification system sudah ada |
| Memproses permintaan dana | ✅ | `POST /api/live-tv/general-affairs/budget-requests/{id}/process` |
| Memberikan hasil kembali pada Producer | ✅ | Endpoint tersedia |

#### 📝 **Catatan:**
- ✅ **Semua fungsi General Affairs sudah diimplementasikan**

---

### 11. PROMOTION / PROMOSI

#### ✅ **Fungsi Utama:**
- Membuat konten promosi
- Mendistribusikan ke berbagai platform

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Setelah Producer Approve Creative:** | | |
| Menerima notifikasi dari Producer | ✅ | Notification system sudah ada |
| Terima jadwal syuting | ✅ | Data tersedia |
| Terima pekerjaan: | | |
| - Buat video BTS (Behind The Scenes) | ✅ | Endpoint tersedia |
| - Buat foto talent | ✅ | Endpoint tersedia |
| - Upload file ke storage/server | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| - Masukkan link alamat file ke sistem | ⚠️ | **PERLU DICEK: Apakah field `file_link` sudah ada?** |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| **2. Setelah QC Promosi Approve dan Broadcasting Selesai:** | | |
| Terima notifikasi dari QC (promosi) setelah approve design grafis dan editor promosi | ✅ | Notification system sudah ada |
| Terima notifikasi dari Broadcasting | ✅ | Notification system sudah ada |
| Terima link YouTube | ✅ | Data tersedia |
| Terima link website | ✅ | Data tersedia |
| Terima pekerjaan: | | |
| - Share link website ke Facebook (masukkan bukti ke sistem) | ⚠️ | **PERLU DICEK: Apakah endpoint sudah ada?** |
| - Buat video HL untuk story IG (masukkan bukti ke sistem) | ⚠️ | **PERLU DICEK: Apakah endpoint sudah ada?** |
| - Buat video HL untuk reels Facebook (masukkan bukti ke sistem) | ⚠️ | **PERLU DICEK: Apakah endpoint sudah ada?** |
| - Share ke group promosi WhatsApp (masukkan bukti ke sistem) | ⚠️ | **PERLU DICEK: Apakah endpoint sudah ada?** |
| Selesai pekerjaan | ✅ | Endpoint tersedia |

#### ⚠️ **Catatan:**
- ✅ **Sebagian besar fungsi Promotion sudah diimplementasikan**
- ⚠️ **PERLU DICEK: Apakah Promotion menggunakan `file_link` (link) untuk video BTS dan foto talent?**
- ⚠️ **PERLU DICEK: Apakah endpoint untuk share ke Facebook, IG, WhatsApp sudah ada?**

---

### 12. DESIGN GRAFIS

#### ✅ **Fungsi Utama:**
- Membuat thumbnail dan desain grafis untuk promosi

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Menerima dari Promotion dan Production:** | | |
| Terima notifikasi dari Promotion (setelah buat video BTS & foto talent) | ✅ | Notification system sudah ada |
| Terima notifikasi dari Production (setelah syuting selesai) | ✅ | Notification system sudah ada |
| Terima lokasi file dari Production | ✅ | Data tersedia |
| Terima lokasi foto talent dari Promotion | ✅ | Data tersedia |
| Terima pekerjaan: | | |
| - Buat thumbnail YouTube | ✅ | Endpoint tersedia |
| - Buat thumbnail BTS | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Ajukan ke QC (QC Promosi) | ✅ | `POST /api/live-tv/roles/design-grafis/works/{id}/submit` |
| **2. Jika Reject dari QC Promosi:** | | |
| Menerima notifikasi | ✅ | Notification system sudah ada |
| Kerjakan ulang | ✅ | Endpoint tersedia |
| Ajukan ke QC lagi | ✅ | Endpoint tersedia |

#### 📝 **Catatan:**
- ✅ **Semua fungsi Design Grafis sudah diimplementasikan**

---

### 13. EDITOR PROMOSI

#### ✅ **Fungsi Utama:**
- Mengedit konten promosi untuk berbagai platform

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Menerima dari Promotion dan Editor:** | | |
| Terima notifikasi dari Promotion (setelah buat video BTS) | ✅ | Notification system sudah ada |
| Terima notifikasi dari Editor (setelah selesai edit) | ✅ | Notification system sudah ada |
| Terima lokasi file dari Editor | ✅ | Data tersedia |
| Terima lokasi file BTS dari Promotion | ✅ | Data tersedia |
| Terima pekerjaan: | | |
| - Edit video BTS (input link) | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| - Edit iklan episode TV (input link) | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| - Buat highlight episode IG (input link) | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| - Buat highlight episode TV (input link) | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| - Buat highlight episode Facebook (input link) | ⚠️ | **PERLU DICEK: Apakah menggunakan link?** |
| (Semua berupa link, bukan file) | ⚠️ | **PERLU DICEK** |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Ajukan ke QC (QC Promosi) | ✅ | `POST /api/live-tv/roles/editor-promosi/works/{id}/submit` |
| **2. Jika Reject dari QC Promosi:** | | |
| Menerima notifikasi | ✅ | Notification system sudah ada |
| Kerjakan ulang | ✅ | Endpoint tersedia |
| Ajukan ke QC lagi | ✅ | Endpoint tersedia |

#### ⚠️ **Catatan:**
- ✅ **Sebagian besar fungsi Editor Promosi sudah diimplementasikan**
- ⚠️ **PERLU DICEK: Apakah EditorPromosiWork menggunakan `file_link` (link) untuk semua hasil editing?**

---

### 14. QC PROMOSI (Quality Control untuk Promosi)

#### ✅ **Fungsi Utama:**
- Quality control untuk semua konten promosi

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Menerima dari Design Grafis:** | | |
| Menerima notifikasi dari Design Grafis | ✅ | Notification system sudah ada |
| Terima lokasi file dari Editor Promosi (untuk konteks) | ✅ | Data tersedia |
| Terima lokasi file Design Grafis | ✅ | Data tersedia |
| Terima pekerjaan: | | |
| - QC thumbnail YouTube | ✅ | Endpoint tersedia |
| - QC thumbnail BTS | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Approve atau Reject | ✅ | `POST /api/live-tv/roles/quality-control/controls/{id}/approve` |
| Jika reject: kembali ke Design Grafis | ✅ | Workflow sudah ada |
| Jika approve: lanjut ke Broadcasting dan Promotion | ✅ | Workflow sudah ada |
| **2. Menerima dari Editor Promosi:** | | |
| Menerima notifikasi dari Editor Promosi | ✅ | Notification system sudah ada |
| Terima lokasi file dari Editor Promosi | ✅ | Data tersedia |
| Terima lokasi file Design Grafis (untuk konteks) | ✅ | Data tersedia |
| Terima pekerjaan: | | |
| - QC video BTS | ✅ | Endpoint tersedia |
| - QC iklan episode TV | ✅ | Endpoint tersedia |
| - QC highlight episode TV | ✅ | Endpoint tersedia |
| - QC highlight episode Facebook | ✅ | Endpoint tersedia |
| - QC highlight episode IG | ✅ | Endpoint tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |
| Approve atau Reject | ✅ | Endpoint tersedia |
| Jika reject: kembali ke Editor Promosi | ✅ | Workflow sudah ada |
| Jika approve: lanjut ke Broadcasting dan Promotion | ✅ | Workflow sudah ada |

#### 📝 **Catatan:**
- ✅ **Semua fungsi QC Promosi sudah diimplementasikan**

---

### 15. BROADCASTING

#### ✅ **Fungsi Utama:**
- Mempublikasikan konten ke YouTube, website, dan platform lainnya

#### ✅ **Workflow yang Sudah Diimplementasikan:**

| Fungsi | Status | Keterangan |
|--------|--------|------------|
| **1. Menerima dari Multiple Sources:** | | |
| Terima notifikasi dari QC Promosi (setelah approve Design Grafis & Editor Promosi) | ✅ | Notification system sudah ada |
| Terima notifikasi dari Broadcasting Manager (setelah QC approve Editor) | ✅ | Notification system sudah ada |
| Terima file materi dari QC Broadcasting Manager | ✅ | Data tersedia |
| Terima thumbnail dari QC Promosi | ✅ | Data tersedia |
| Terima pekerjaan | ✅ | Endpoint tersedia |
| **2. Proses Pekerjaan:** | | |
| Masukkan ke jadwal playlist | ✅ | Endpoint tersedia |
| Upload di YouTube: | | |
| - Thumbnail (dari QC Promosi) | ✅ | Endpoint tersedia |
| - Deskripsi | ✅ | Endpoint tersedia |
| - Tag | ✅ | Endpoint tersedia |
| - Judul sesuai SEO | ✅ | Endpoint tersedia |
| Upload ke sistem website | ✅ | Endpoint tersedia |
| Input link YouTube ke sistem | ✅ | Field `youtube_link` tersedia |
| Selesai pekerjaan | ✅ | Endpoint tersedia |

#### 📝 **Catatan:**
- ✅ **Semua fungsi Broadcasting sudah diimplementasikan**

---

## 🔄 WORKFLOW LENGKAP

### Main Workflow Sequence

```
1. Program Manager
   ├─ Buat Tim (Producer, Music Arranger, Creative, Sound Engineer, Production, Editor)
   ├─ Buat Program Live
   ├─ Generate 52 Episode (auto)
   ├─ Set Deadline Auto (7 hari Editor, 9 hari Creative/Production)
   └─ Submit Jadwal Tayang ke Broadcasting Manager

2. Broadcasting Manager
   ├─ Terima Opsi Jadwal
   ├─ Review & Approve/Revise Jadwal
   └─ Kembalikan ke Program Manager

3. Music Arranger
   ├─ Pilih Lagu & Penyanyi (atau input baru)
   ├─ Submit ke Producer
   └─ Producer: Approve/Reject/Edit
       ├─ Jika Approve/Edit → Music Arranger: Arr Lagu (input link)
       └─ Jika Reject → Kembali ke Music Arranger

4. Producer QC Arrangement
   ├─ Terima Link Arr Lagu
   ├─ QC Arrangement
   └─ Approve/Reject
       ├─ Jika Approve → Lanjut ke Creative
       └─ Jika Reject → Kembali ke Music Arranger (Sound Engineer bisa bantu)

5. Creative
   ├─ Terima Tugas dari Producer
   ├─ Buat Script, Storyboard, Budget
   ├─ Input Jadwal Rekaman & Syuting
   └─ Submit ke Producer

6. Producer Review Creative
   ├─ Cek Script, Storyboard, Budget
   ├─ Tambahkan Tim Syuting/Setting/Rekam Vocal
   ├─ Dapat Cancel Jadwal Syuting
   ├─ Dapat Edit Langsung
   ├─ Request Budget Khusus (jika perlu)
   └─ Approve/Reject
       ├─ Jika Approve → Multiple Roles Activated:
       │   ├─ General Affairs (proses dana)
       │   ├─ Sound Engineer (rekam vocal)
       │   ├─ Promotion (BTS & foto talent)
       │   └─ Production (syuting)
       └─ Jika Reject → Kembali ke Creative

7. Sound Engineer
   ├─ Request Alat ke Art Set Property
   ├─ Art Set Property: ACC Alat
   ├─ Recording Vocal (input link)
   ├─ Kembalikan Alat
   ├─ Edit Vocal (input link)
   └─ Submit ke Producer QC

8. Producer QC Vocal
   ├─ QC Edited Vocal
   └─ Approve/Reject
       ├─ Jika Approve → Lanjut ke Editor
       └─ Jika Reject → Kembali ke Sound Engineer

9. Production
   ├─ Request Alat ke Art Set Property
   ├─ Art Set Property: ACC Alat
   ├─ Syuting (input link hasil syuting)
   ├─ Kembalikan Alat
   └─ Notifikasi ke: Art Set Property, Producer, Editor, Design Grafis

10. Editor
    ├─ Terima dari Producer (vocal approved) atau Production (syuting selesai)
    ├─ Cek Kelengkapan File
    ├─ Jika Lengkap: Edit Video (input link)
    ├─ Jika Tidak Lengkap: Ajukan ke Producer
    └─ Submit ke Broadcasting Manager QC

11. Broadcasting Manager QC
    ├─ QC Hasil Editing dari Editor
    └─ Approve/Reject
        ├─ Jika Approve → Lanjut ke Broadcasting
        └─ Jika Reject → Kembali ke Editor

12. Design Grafis
    ├─ Terima dari Production & Promotion
    ├─ Buat Thumbnail YouTube & BTS
    └─ Submit ke QC Promosi

13. Editor Promosi
    ├─ Terima dari Editor & Promotion
    ├─ Edit Video BTS, Iklan, Highlight (input link)
    └─ Submit ke QC Promosi

14. QC Promosi
    ├─ QC dari Design Grafis & Editor Promosi
    └─ Approve/Reject
        ├─ Jika Approve → Lanjut ke Broadcasting & Promotion
        └─ Jika Reject → Kembali ke Design Grafis / Editor Promosi

15. Broadcasting
    ├─ Terima dari Broadcasting Manager QC & QC Promosi
    ├─ Upload ke YouTube (thumbnail, deskripsi, tag, judul SEO)
    ├─ Upload ke Website
    └─ Input Link YouTube ke Sistem

16. Promotion
    ├─ Terima Link YouTube & Website dari Broadcasting
    ├─ Share ke Facebook (input bukti)
    ├─ Buat HL untuk IG Story (input bukti)
    ├─ Buat HL untuk Facebook Reels (input bukti)
    └─ Share ke WhatsApp Group (input bukti)
```

---

## ⚙️ SISTEM OTOMATIS

### 1. ✅ Auto-Generate Episodes

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Fungsi:**
- Sistem otomatis membuat 52 episode setiap tahun berdasarkan jadwal tayang mingguan
- Episode 1 = Sabtu pertama di Januari
- Episode 2-52 = Setiap Sabtu berikutnya (7 hari interval)
- Setiap tahun baru, episode reset ke Episode 1 (data lama tersimpan)

**Implementasi:**
- Method: `Program::generateEpisodes()` dan `Program::generateEpisodesForYear()`
- Endpoint: `POST /api/live-tv/manager-program/programs/{programId}/generate-episodes`

**Verifikasi:**
- ✅ Generate 52 episode per tahun
- ✅ Episode number reset ke 1 setiap tahun baru
- ✅ Data episode lama tersimpan (bisa difilter per tahun)

---

### 2. ✅ Auto-Calculate Deadlines

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Fungsi:**
- Sistem otomatis menghitung deadline berdasarkan tanggal tayang
- Editor: 7 hari sebelum tanggal tayang
- Creative & Production: 9 hari sebelum tanggal tayang
- Program Manager dapat mengedit deadline jika ada kebutuhan khusus

**Implementasi:**
- Method: `Episode::generateDeadlines()`
- Otomatis dipanggil saat episode dibuat

**Verifikasi:**
- ✅ Deadline Editor: 7 hari sebelum tayang
- ✅ Deadline Creative/Production: 9 hari sebelum tayang
- ✅ Dapat di-edit manual oleh Program Manager

---

### 3. ✅ Notification System

**Status:** ✅ **SUDAH DIIMPLEMENTASIKAN**

**Fungsi:**
- Sistem otomatis mengirim notifikasi setiap perpindahan workflow atau approval/rejection

**Implementasi:**
- Model: `Notification`
- Notifikasi dikirim di berbagai controller saat workflow berubah

**Verifikasi:**
- ✅ Notifikasi dikirim saat workflow berubah
- ✅ Notifikasi dikirim saat approve/reject
- ✅ Notifikasi dikirim saat deadline dibuat/diubah

---

## 📋 CHECKLIST VERIFIKASI

### ✅ **YANG SUDAH LENGKAP:**

1. ✅ **Program Manager** - Semua fungsi sudah diimplementasikan
2. ✅ **Broadcasting Manager** - Semua fungsi sudah diimplementasikan
3. ✅ **Producer** - Semua fungsi sudah diimplementasikan
4. ✅ **Music Arranger** - Semua fungsi sudah diimplementasikan
5. ✅ **Creative** - Semua fungsi sudah diimplementasikan
6. ✅ **Art Set Property** - Semua fungsi sudah diimplementasikan
7. ✅ **General Affairs** - Semua fungsi sudah diimplementasikan
8. ✅ **Design Grafis** - Semua fungsi sudah diimplementasikan
9. ✅ **QC Promosi** - Semua fungsi sudah diimplementasikan
10. ✅ **Broadcasting** - Semua fungsi sudah diimplementasikan
11. ✅ **Episode Generation** - Sistem auto-generate 52 episode sudah ada
12. ✅ **Deadline Calculation** - Sistem auto-calculate deadline sudah ada
13. ✅ **Notification System** - Sistem notifikasi sudah ada

### ⚠️ **YANG PERLU DICEK:**

1. ⚠️ **Sound Engineer** - Perlu dicek apakah menggunakan `file_link` (link) atau `file_path` (upload langsung) untuk:
   - File rekaman vokal (`SoundEngineerRecording`)
   - File edit vokal (`SoundEngineerEditing`)

2. ⚠️ **Production** - Perlu dicek apakah menggunakan `file_link` (link) untuk hasil syuting

3. ⚠️ **Editor** - Perlu dicek apakah menggunakan `file_link` (link) untuk hasil editing (`EditorWork`)

4. ⚠️ **Promotion** - Perlu dicek apakah menggunakan `file_link` (link) untuk:
   - Video BTS
   - Foto talent
   - Dan perlu dicek endpoint untuk share ke Facebook, IG, WhatsApp

5. ⚠️ **Editor Promosi** - Perlu dicek apakah menggunakan `file_link` (link) untuk semua hasil editing

---

## 🎯 KESIMPULAN & REKOMENDASI

### ✅ **KESIMPULAN:**

1. **Sebagian besar sistem sudah diimplementasikan dengan baik** ✅
2. **Workflow utama sudah sesuai dengan requirement** ✅
3. **Sistem otomatis (episode generation, deadline calculation) sudah ada** ✅
4. **Notification system sudah ada** ✅

### ⚠️ **REKOMENDASI:**

1. **Verifikasi File Storage System:**
   - Pastikan semua model yang menyimpan file menggunakan `file_link` (link) bukan `file_path` (upload langsung)
   - Model yang perlu dicek:
     - `SoundEngineerRecording` - field `file_link` untuk rekaman vokal
     - `SoundEngineerEditing` - field `file_link` untuk edit vokal
     - `ProduksiWork` / `ProductionWork` - field `file_link` untuk hasil syuting
     - `EditorWork` - field `file_link` untuk hasil editing
     - `PromotionWork` / `PromotionMaterial` - field `file_link` untuk video BTS dan foto talent
     - `EditorPromosiWork` - field `file_link` untuk semua hasil editing promosi

2. **Verifikasi Endpoint Promotion:**
   - Pastikan endpoint untuk share ke Facebook, IG, WhatsApp sudah ada
   - Endpoint yang perlu dicek:
     - Share link website ke Facebook (dengan input bukti)
     - Buat video HL untuk story IG (dengan input bukti)
     - Buat video HL untuk reels Facebook (dengan input bukti)
     - Share ke group promosi WhatsApp (dengan input bukti)

3. **Testing End-to-End:**
   - Lakukan testing end-to-end untuk memastikan semua workflow berjalan dengan baik
   - Pastikan semua notifikasi dikirim dengan benar
   - Pastikan semua deadline dihitung dengan benar

4. **Dokumentasi API:**
   - Lengkapi dokumentasi API untuk semua endpoint
   - Pastikan dokumentasi mencakup semua parameter dan response

---

## 📝 CATATAN PENTING

1. **File Storage:** Sesuai requirement, semua file harus disimpan di server eksternal dan sistem hanya menyimpan link (bukan file langsung) karena keterbatasan storage 20GB.

2. **Episode Generation:** Sistem sudah otomatis generate 52 episode per tahun. Episode number reset ke 1 setiap tahun baru, namun data episode lama tersimpan dan bisa difilter per tahun.

3. **Deadline Calculation:** Sistem sudah otomatis menghitung deadline (7 hari untuk Editor, 9 hari untuk Creative/Production). Program Manager dapat mengedit deadline jika ada kebutuhan khusus.

4. **Workflow Tracking:** Semua workflow sudah dilacak melalui `current_workflow_state` di Episode model dan notification system.

5. **Team Management:** Producer dan Program Manager dapat melakukan CRUD pada tim. Producer dapat menambahkan tim syuting, setting, dan rekam vokal dari semua user di sistem (kecuali manager).

---

**Dokumentasi ini akan terus diperbarui seiring dengan perkembangan sistem.**
