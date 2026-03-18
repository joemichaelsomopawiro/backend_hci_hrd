# Dokumentasi Sistem Program Musik

Dokumen ini mendeskripsikan alur dan fitur sistem Program Musik agar dapat diperiksa: program yang sudah ada apakah sudah sesuai dengan sistem yang didesain atau belum.

---

## 1. Program Manager

### 1.1 Membagi tim (kelompok kerja)

- Program Manager **membuat / membagi tim** kelompok kerja berdasarkan jabatan (role).
- **Memilih user dengan role Producer** (bisa lebih dari satu) untuk dimasukkan ke tim yang dibuat.
- Di dalam tim tersebut Producer dapat **mengganti semua kelompok kerja** dalam setiap proses.
- **Role/jabatan dalam tim:**
  1. Music Arranger  
  2. Creative  
  3. Sound Engineer  
  4. Produksi  
  5. Editor  

Setelah tim dibuat, **Producer** juga dapat melakukan **CRUD pada tim** (mengganti anggota) karena tiap role (Music Arranger, Creative, Sound Engineer, Produksi, Editor) bisa lebih dari satu user.

### 1.2 Membagi program live ke kelompok kerja

- **Membuat program** apa saja yang menjadi Program Live (database Program, Proposal Program — ada di lampiran).
- **Membuat opsi jadwal tayang** dan mengajukan ke Manager Broadcasting / Distribution Manager.
- Sistem **otomatis membuat urutan episode** (1, 2, 3, …).
- **Deadline otomatis** setiap episode baru:
  - **7 hari sebelum tayang** → dari Editor.
  - **9 hari sebelum tayang** → untuk Creative dan Produksi.
- Manager Program **dapat mengedit deadline** jika ada perbaikan di QC atau kebutuhan khusus.

### 1.3 Episode (secara terus-menerus)

- Setiap tahun = **52 episode** (52 minggu).
- **Episode 1** terhitung per 1 Januari (contoh: Saptu minggu pertama Januari = tanggal 3 = Episode 1).
- Sistem **meng-inherit dari awal** semua episode 1–52 beserta deadline.
- Contoh deadline:
  - Tayang 3 Januari → deadline Editor **27 Des** (7 hari sebelum tayang).
  - Tayang 10 Januari → deadline Editor **3 Januari**.
  - Untuk Creative/Produksi: **9 hari** sebelum tayang (mis. tayang 3 Jan → deadline 25 Des).
- **Saat ganti tahun**: episode dimulai lagi dari Episode 1; **data episode lama disimpan** dan bisa dilihat per tahun (dropdown tahun di frontend).

### 1.4 Lain-lain (Program Manager)

- Membuat **target pencapaian views** per program (tarik data mingguan).
- **Menutup program reguler** yang tidak berkembang.
- **Mengintervensi semua jadwal dan approval** di semua bidang, termasuk cancel jadwal syuting, mengubah jadwal.

---

## 2. Manager Broadcasting / Distribution Manager

- **Menerima notifikasi** program dan **opsi jadwal** dari Program Manager.
- **Merevisi jadwal** (jika perlu) dan memberitahu kembali ke Program Manager; jadwal yang di-ACC dipakai.
- **Membagi pekerjaan** (berdasarkan jabatan).
- Membuat **target pencapaian views** per program (tarik data mingguan).
- **Memantau semua pekerjaan** hingga penayangan.
- Dapat **menutup program reguler** yang tidak berkembang.

---

## 3. Producer

Producer yang dipilih Program Manager di program musik tersebut:

- **Menerima program live** yang menjadi tanggung jawabnya (nama program, rundown).
- **Mengedit rundown** jika perlu & mengajukan ke Program Manager.
- **Mengontrol program live** (tayang 1 episode per minggu).
- **Mengingatkan** (melalui sistem) setiap crew di timnya.
- **Memantau semua pekerjaan** di setiap proses dalam tim (di frontend: tampil sudah di step mana, episode berapa, tugas sampai mana).
- **Mengalihkan tugas** (reassign): contoh jika Editor sakit, Producer bisa memindahkan tugas ke user lain; Program Manager juga bisa.
- **Mengintervensi jadwal syuting & jadwal rekaman vokal.**

---

## 4. Workflow: Music Arranger

- Music Arranger **memilih lagu** (jika belum ada: input teks judul lagu → tersimpan, bisa dipilih di episode/program berikutnya).
- **Pilih penyanyi** (opsional): jika belum ada di database, input nama → tersimpan, bisa dipilih di episode berikutnya.
- Setelah pilih lagu & penyanyi → **ajukan ke Producer**.

**Producer:**

- Menerima notifikasi dari Music Arranger.
- **Approve / Reject** usulan lagu & penyanyi.
- Producer bisa **mengedit/mengganti** usulan; kalau Producer yang edit, tidak perlu approve lagi.
- Jika **Reject** → kembali ke Music Arranger (ada teks apa yang perlu diganti).

**Setelah Approve / Edit Producer:**

- Kembali ke **Music Arranger**.
- Music Arranger menerima notifikasi & tugas: **arrangement lagu** (bukan file, **link** ke hosting/server).
- Music Arranger **masukkan link arr lagu** → ajukan ke Producer.

**Producer:**

- Menerima notifikasi, **QC arr lagu** → Approve / Reject.
- **Reject** → kembali ke Music Arranger (perbaiki, kirim lagi link); **Sound Engineer** juga bisa **membantu** perbaikan arr lagu (link dikirim ke Music Arranger, lalu Music Arranger ajukan lagi ke Producer).
- **Approve** → lanjut ke **Creative**.

---

## 5. Workflow: Creative

- Creative menerima notifikasi tugas dari Producer.
- **Pekerjaan:** tulis script cerita video klip, storyboard (bisa di sistem / PDF / link), input jadwal rekaman vokal, input jadwal syuting, lokasi syuting, budget talent.
- **Ajukan ke Producer.**

**Producer:**

- Cek script, storyboard, budget (teks/PDF/link).
- Bisa **tambah Tim Syuting** (semua user di sistem kecuali manager).
- Bisa **tambah Tim Setting** (sama: semua user kecuali manager).
- Bisa **cancel** jadwal syuting.
- Bisa **ganti tim syuting** kapan saja.
- Bisa **tambah Tim Rekam Vokal** (semua user).
- Bisa **edit langsung** yang diajukan Creative.
- Jika ada **budget khusus** → ajukan ke Program Manager.
- **Approve / Reject** tugas Creative.
  - Reject → kembali ke Creative (dengan catatan perbaikan).
  - Approve / setelah Producer edit → lanjut; **banyak role** menerima tugas secara **paralel**.

---

## 6. Setelah Producer Approve Creative — Paralel

**1. General Affairs**  
Menerima permohonan dana dari Producer, memproses, mengembalikan hasil ke Producer.

**2. Sound Engineer**  
- Notifikasi dari Producer; terima jadwal rekaman vokal (dari Creative).  
- Input **list alat** → ajukan ke **Art Set Properti**.

**3. Promosi**  
- Notifikasi dari Producer; terima jadwal syuting.  
- Tugas: buat video BTS, foto talent, upload ke storage mereka, **link** dimasukkan ke sistem.

**4. Produksi**  
- Notifikasi dari Producer.  
- Input list alat → ajukan ke **Art Set Properti** (jika alat sedang dipakai, sistem tidak bisa request).

---

## 7. Art Set Properti

- Menerima notifikasi dari **Sound Engineer** (list alat) dan dari **Produksi** (list alat).
- **Pekerjaan:** acc alat yang diajukan → selesai.
- Setelah acc → Sound Engineer lanjut rekaman; Produksi/Tim Syuting lanjut syuting.

**Setelah Sound Engineer selesai rekaman:**

- Kembalikan alat ke Art Set Properti → Art Set Properti acc alat dikembalikan.
- Sound Engineer upload file rekaman ke server → **link** ke sistem.
- Sound Engineer **editing vokal** → ajukan ke **QC (Producer)**.
- Producer: QC lagu → Approve / Reject. Reject → kembali ke Sound Engineer. Approve → masuk ke **Editor**.

**Setelah Produksi / Tim Syuting selesai syuting:**

- Input run sheet, upload hasil syuting ke storage, **link** ke sistem, kembalikan alat ke Art Set Properti.
- Art Set Properti terima notifikasi, acc alat dikembalikan.
- Notifikasi ke **Producer**, **Editor**, dan **Design Grafis**.

---

## 8. Editor (video utama)

- Editor menerima notifikasi dari Producer (setelah Sound Engineer QC approve) dan dari Produksi/Tim Syuting.
- **Cek kelengkapan file:** lengkap → proses; tidak lengkap → ajukan ke Producer.
- Catatan file yang kurang/perlu perbaikan.
- Proses: lihat catatan syuting (run sheet), upload file hasil edit ke storage, **link** ke sistem.

**Jika file tidak lengkap / bermasalah:**

- Producer terima notifikasi, lihat catatan dari Editor.
- Jika perlu syuting ulang → Producer jadwalkan syuting ulang.
- Jika perlu lengkapi file → ajukan ke Produksi; Produksi kembali input list alat (ke Art Set Properti), tim syuting input run sheet, link, kembalikan alat.

**Setelah Editor selesai:**

- Masuk juga ke **Editor Promosi** (notifikasi; terima lokasi file dari Editor & dari BTS).
- Editor **ajukan ke QC** (QC ini **Manager Broadcasting / Distribution Manager**).

---

## 9. QC (Manager Broadcasting / Distribution Manager)

- Menerima notifikasi dari Editor.
- Isi form catatan QC; **tidak ada revisi** → Yes, selesai.
- **Reject** → kembali ke Editor (notifikasi ke Producer + catatan QC).
- **Approve** → masuk ke **Broadcasting**.

---

## 10. Broadcasting

- Menerima notifikasi (file materi dari QC, thumbnail dari Design Grafis).
- Pekerjaan: jadwal playlist, upload ke YouTube (thumbnail, deskripsi, tag, judul SEO), upload ke website, **input link YT & website** ke sistem.

---

## 11. Kotak Promosi: Design Grafis, Editor Promosi, QC Promosi

**Design Grafis**  
- Notifikasi dari **Promosi** dan dari **Produksi/Tim Syuting**.  
- Terima lokasi file dari Produksi, lokasi foto talent dari Promosi.  
- Pekerjaan: buat thumbnail YouTube, thumbnail BTS.

**Editor Promosi**  
- Notifikasi dari **Promosi** dan dari **Editor** (setelah Editor selesai).  
- Terima lokasi file dari Editor & dari BTS.  
- Pekerjaan: edit video BTS, iklan episode TV, highlight IG/TV/Facebook (semua berupa **link**, bukan file).

**QC (role QC — untuk promosi):**  
- Menerima notifikasi dari Design Grafis dan Editor Promosi.  
- Terima lokasi file dari Editor Promosi & Design Grafis.  
- QC: video BTS, iklan episode TV, highlight, thumbnail YT, thumbnail BTS.  
- **Reject** → kembali ke Design Grafis / Editor Promosi.  
- **Approve** → masuk ke **Broadcasting** dan **Promosi**.

**Promosi (sharing works):**  
- Notifikasi dari QC (setelah approve Design Grafis/Editor Promosi) dan dari Broadcasting.  
- Terima link YouTube, link website.  
- Pekerjaan: share link website ke Facebook (bukti ke sistem), video HL story IG (bukti), video HL reels Facebook (bukti), share ke grup WA Promosi (bukti).

---

## 12. Active Productions (tampilan)

- **Flow ditampilkan mulai dari Music Arranger** (bukan dari “Program Aktif”).
- Satu per satu tahap: Music Arranger selesai ✓, Creative selesai ✓, … (semua tahap dengan centang jika selesai).
- Jika belum sampai selesai, **harus bisa tahu kenapa** (alasan belum selesai / ditolak — bisa diklik).
- Urutan step di API mengikuti dokumen **ACTIVE_PRODUCTION_WORKFLOW_API.md** (flow mulai Music Arranger, Program Aktif di akhir).

---

## 13. Ringkasan untuk pemeriksaan

Gunakan dokumen ini untuk:

1. **Program Manager:** tim, program, episode, deadline, opsi jadwal, target views, intervensi.
2. **Distribution Manager:** terima/revise jadwal, bagi pekerjaan, monitoring.
3. **Producer:** rundown, approve/reject Music Arranger & Creative, tim syuting/setting/rekam vokal, reassign, QC Sound Engineer, monitoring.
4. **Music Arranger → Producer → Creative → Producer** lalu paralel: **General Affairs, Sound Engineer, Promosi, Produksi** → **Art Set Properti** → **Tim Syuting / Rekaman** → **Editor** → **QC (Manager Broadcasting)** → **Broadcasting**; dan jalur **Promosi → Design Grafis & Editor Promosi → QC Promosi → Broadcasting & Promosi (sharing)**.

Periksa apakah fitur dan alur di sistem yang sudah ada sudah sesuai dengan deskripsi di atas.
