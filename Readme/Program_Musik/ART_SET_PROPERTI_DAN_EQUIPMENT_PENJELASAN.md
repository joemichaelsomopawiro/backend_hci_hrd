# Art & Set Properti, Tim Rekam Vocal, dan Sistem Peminjaman Equipment

Dokumen ini berisi: (1) penjelasan **Tim Rekam Vocal** vs Sound Engineer, (2) ringkasan kebutuhan **peminjaman alat** (crew leader, multi program/episode same day, riwayat), (3) **status sistem saat ini** (backend + frontend), dan (4) **gap** yang perlu dikonfirmasi/dikembangkan.

---

## 1. Tim Rekam Vocal — Siapa yang Bisa Dimasukkan?

### Yang client jelaskan
- **Producer** bisa: Tambah Tim Syuting, Tambah Tim Setting, **Tambahkan Tim Rekam Vocal** (dan edit jika perlu).
- **Tim Syuting & Tim Setting**: crew = semua user di program **kecuali Manager** (jelas).
- **Tim Rekam Vocal**: client **tidak** menjelaskan siapa saja yang boleh dimasukkan.

### Flow yang client berikan (Sound Engineer)
Setelah Producer approve creative:
1. **Sound Engineer** → Terima notifikasi → Terima jadwal rekaman → Terima pekerjaan → **Input list alat (ajukan ke Art & Set Properti)** → Selesai (fase persiapan).
2. Setelah dapat barang dari Art & Set Properti → **Recording**: terima pekerjaan → rekam vokal → kembalikan alat ke Art & Set Properti → kirim file ke storage → selesai.
3. **Sound Engineer Editing** → terima pekerjaan → edit vokal → selesai.

Jadi yang **mengerjakan** tugas rekaman di sistem (request alat, rekam, kembalikan, input link, edit) adalah **Sound Engineer**.

### Di backend saat ini
- **Tim Rekam Vocal** = **Recording Team** (`team_type = 'recording'`).
- Producer mengisi `recording_team_ids` (atau frontend: `vocal_team_ids`).
- **Aturan anggota tim**: sama dengan Tim Syuting/Setting — **semua user aktif kecuali** Program Manager & Distribution Manager (boleh lebih dari satu, ada leader/crew).
- Sound Engineer **melihat** recording work jika: (a) dia yang membuat recording, **atau** (b) dia ada di **recording_team** episode tersebut.

### Kesimpulan dan rekomendasi

- **Secara logika**:  
  - **Tim Rekam Vocal** = orang yang “terlibat” di sesi rekaman (bisa untuk notifikasi, laporan, atau kehadiran).  
  - **Yang menjalankan flow di aplikasi** (request alat, rekam, kembalikan, edit) = **Sound Engineer**.

- **Siapa yang “boleh” di Tim Rekam Vocal** client tidak definisikan. Di kode saat ini: **siapa saja (kecuali manager)** seperti Tim Syuting/Setting.

**Rekomendasi ke client (konfirmasi):**

1. **Opsi A** — Tim Rekam Vocal **hanya Sound Engineer**:  
   - Siapa yang boleh ditambah = user dengan role Sound Engineer saja.  
   - Kalau di episode itu ada beberapa Sound Engineer, Producer pilih salah satu (atau beberapa) yang jadi “tim rekam vokal” untuk episode itu.

2. **Opsi B** — Tim Rekam Vocal = **crew rekaman** (boleh beda-beda role):  
   - Boleh Sound Engineer + orang lain (asisten, talent, dll).  
   - Yang **login dan kerjakan** task di aplikasi tetap **Sound Engineer**; anggota lain hanya tercatat (dan bisa dapat notifikasi).

Saat ini sistem mengikuti **Opsi B** (sama seperti Tim Syuting/Setting). Jika client mau **Opsi A**, perlu penyesuaian validasi di backend (mis. hanya user dengan role Sound Engineer yang boleh ada di `recording_team_ids`).

---

## 2. Kebutuhan Peminjaman Alat (dari penjelasan Anda)

### Data yang harus ada
- **Program / Acara** (wajib)
- **Episode** (implisit atau eksplisit)
- **Crew Leader** (1 orang)
- **Anggota crew** (bisa banyak)
- **Daftar alat** (nama + qty, dengan batas max stok)
- **Catatan tambahan**

### Aturan bisnis yang diinginkan
1. **Satu hari, beda jam**: alat yang sama boleh dipakai untuk **beberapa program/episode** dalam **satu hari** dengan **jam berbeda** (tanpa harus “kembalikan dulu, pinjam lagi”).
2. **Lanjut ke program/episode lain**: kalau alat sudah dipinjam untuk Program A Episode 1, bisa **langsung dilanjutkan** ke Program B Episode 2 (status berganti “dipakai untuk Program B Ep 2”), tanpa wajib return dulu.
3. **Status & riwayat**:
   - Status: alat dipakai untuk program mana, episode berapa.
   - Riwayat: siapa pinjam, siapa kembalikan, alat apa saja per program/episode, leader + anggota crew.

### Tampilan yang diinginkan (contoh)
- **Riwayat & Pengembalian**: filter by program / crew leader, status; detail per peminjaman: program, crew leader, tanggal, daftar alat (nama + qty), anggota crew, catatan, tanggal dikembalikan.
- **Daftar program (template)**: per program bisa ada “default equipment” (mis. Hope Music Outdoor: 6 item, Kebaktian Vesper: 4 item) — untuk keperluan template/quick pick.

---

## 3. Status Sistem Saat Ini

### 3.1 Database & CRUD — Art & Set Properti (Equipment Inventory)

**Tabel `equipment_inventory` (Art & Set Properti):**
- **Kolom**: id, name, category, brand, model, serial_number, description, status (available / in_use / maintenance / broken / retired), location, purchase_price, purchase_date, last_maintenance, next_maintenance, maintenance_notes, image_path, is_active, notes, **assigned_to**, **assigned_by**, **assigned_at**, **episode_id**, return_date, returned_at, return_condition, return_notes, timestamps.
- **CRUD**: Ada di `ArtSetPropertiController`: index, store, update, destroy (soft delete = status retired + is_active false). **Create** bisa dengan `quantity` → membuat N baris dengan nama yang sama (stok per unit).
- **Keterbatasan**: 
  - Tidak ada kolom **kondisi** terpisah (seperti “Baik” / “Tak Tertolong”) — bisa pakai `description` atau `notes`, atau tambah kolom nanti.
  - Tidak ada **ID custom** (EQ-001, dll) di migration; kalau UI pakai EQ-001, itu bisa dari attribute/accessor atau kolom baru.
  - **Stok**: saat ini per **row** (satu baris = satu unit). Tampilan “2/2”, “5/5” bisa dihitung dari count by name + status.

**Kesimpulan**: Database alat Art & Set Properti **sudah mendukung CRUD**. Untuk tampilan seperti “ID, Nama Alat, Kategori, Kondisi & Lokasi, Stok, Aksi” bisa dipenuhi dengan query + frontend; jika butuh “Kondisi” resmi dan “Kode (EQ-xxx)” bisa ditambah kolom.

---

### 3.2 Request Peminjaman — Production Equipment

**Tabel `production_equipment`:**
- **Kolom**: id, **episode_id**, **equipment_list** (JSON array nama alat), request_notes, status (pending / approved / rejected / in_use / returned), **requested_by**, requested_at, approved_by, approved_at, rejected_by, rejected_at, approval_notes, rejection_reason, assigned_at, returned_at, return_condition, return_notes, **assigned_to**.
- **Saat ini**: Satu request = **satu episode**, **satu user** (requested_by), **daftar nama alat**. **Tidak ada**: program_id (didapat lewat episode), crew_leader, crew_members, jadwal (tanggal/jam), atau “transfer” ke episode lain tanpa return.

**Gap dengan kebutuhan Anda:**
- Belum ada **crew leader** dan **anggota crew** (hanya `requested_by`).
- Belum ada **multi program/episode dalam satu hari** (jam berbeda) atau **lanjut pakai ke program/episode lain** tanpa return.
- Belum ada **riwayat per program/episode** dengan struktur “siapa pinjam, siapa kembalikan, alat apa”.

---

### 3.3 Siapa yang Request Alat di Sistem Sekarang

- **Produksi (Setting)**: `ProduksiController::requestEquipment` — untuk **ProduksiWork** (episode), requested_by = user yang login, equipment_list dari input.
- **Sound Engineer**: `SoundEngineerController::requestEquipment` — untuk **rekaman vokal** (episode/arrangement), requested_by = user yang login, equipment_list dari input.
- **Art & Set Properti**: approve/reject request, update inventory (status in_use, assigned_to, episode_id), dan **return** (update status, return_condition, return_notes).

Alur: request → approve → pakai → return. **Tidak ada** alur “transfer ke episode lain” atau “same day multi episode”.

---

### 3.4 Apakah Bisa Tahu “Program X Episode Y Pakai Alat Apa”?

- **Ya, secara data**: `production_equipment` punya `episode_id` dan `equipment_list`; episode punya `program_id`. Jadi bisa: list request per episode → tampilkan program + episode + daftar alat.
- **Belum ada**: endpoint khusus “Daftar alat per program/episode” atau “Template default equipment per program” (seperti Hope Music Outdoor: 6 item, dll). Itu bisa ditambah (tabel template atau query aggregasi).

---

### 3.5 Tim di Backend (Producer)

- **Tim Syuting** (`team_type = 'shooting'`): assignment + members (leader + crew), aturan: semua user kecuali manager.
- **Tim Setting** (`team_type = 'setting'`): sama.
- **Tim Rekam Vocal** (`team_type = 'recording'`): sama; frontend bisa kirim `vocal_team_ids` → map ke `recording_team_ids`.
- Sound Engineer bisa lihat recording work jika dia di **recording_team** atau yang membuat recording.

---

## 4. Ringkasan Gap dan Rekomendasi

| Aspek | Status sekarang | Yang diinginkan |
|-------|------------------|------------------|
| **Tim Rekam Vocal — siapa boleh** | Siapa saja (kecuali manager) | Perlu konfirmasi client: hanya Sound Engineer atau boleh crew lain? |
| **Database alat (Art & Set)** | CRUD ada, kolom cukup | Bisa dipakai; opsional: kolom kondisi, kode (EQ-xxx). |
| **Crew leader + anggota** | Hanya `requested_by` (1 user) | Crew leader 1 + anggota banyak per request. |
| **Same day, multi program/episode** | Tidak ada | Alat sama boleh dipakai beberapa program/episode, jam berbeda, tanpa return dulu. |
| **Lanjut ke program/episode lain** | Tidak ada | Status “pindah” ke Program B Ep 2 tanpa return. |
| **Riwayat lengkap** | Ada data (episode, requester, equipment_list, return) | Perlu tampilan: program, episode, crew leader, anggota, alat, siapa kembalikan, tanggal. |
| **Template alat per program** | Tidak ada | Daftar default alat per program (untuk quick pick). |

---

## 5. Langkah yang Disarankan

1. **Konfirmasi ke client**: definisi **Tim Rekam Vocal** (hanya Sound Engineer vs crew campuran); kalau hanya Sound Engineer, kita batasi validasi di backend.
2. **Desain peminjaman baru** (atau perluas `production_equipment`):  
   - Crew leader + anggota crew;  
   - Opsi jadwal (tanggal + jam) dan “transfer” ke episode lain / same-day multi episode;  
   - Riwayat dan laporan per program/episode.
3. **Database alat**: tetap pakai `equipment_inventory`; tambah kolom jika butuh “kondisi” resmi dan “kode alat”.
4. **Fitur tambahan**:  
   - Endpoint/tampilan “alat per program/episode”;  
   - Template default equipment per program (untuk dropdown/quick add).

Setelah ada konfirmasi client untuk Tim Rekam Vocal dan prioritas fitur equipment (crew, same-day, transfer, riwayat, template), implementasi bisa dipecah per fase (backend dulu, lalu frontend).

---

## 6. Implementasi (Update 2026-03-05)

### Backend
- **production_equipment**: Kolom baru `program_id`, `crew_leader_id`, `crew_member_ids` (JSON), `scheduled_date`, `scheduled_time`; `returned_by` (sudah ada di migration lain) dipakai untuk mencatat tim syuting yang mengembalikan.
- **production_equipment_transfers**: Tabel riwayat transfer (dari episode A ke B tanpa return).
- **program_equipment_templates**: Template default alat per program (items JSON: name + qty).
- **API baru**: `GET /art-set-properti/history`, `POST /art-set-properti/requests/{id}/transfer`, `GET /art-set-properti/program-templates`, `GET /art-set-properti/programs/{id}/equipment-template`, `POST /art-set-properti/program-templates`, `GET /art-set-properti/episodes/{id}/equipment-summary`.
- **Produksi**: Satu peminjaman per request (satu ProductionEquipment dengan equipment_list flat + crew_leader_id, crew_member_ids, program_id). Return: body `returned_by` opsional.

### Frontend
- **Art Set Dashboard**: Tab "Riwayat" (HistoryTab) dengan filter status & search; getHistory.
- **Produksi EquipmentRequestModal**: Crew Leader (1), Anggota Crew (banyak), Jadwal (tanggal/jam); tombol "Isi default program" memanggil getEpisodeEquipmentSummary dan mengisi daftar alat dari template + stok.
- **Service**: getHistory, transferRequest, getProgramTemplates, getTemplateByProgram, storeProgramTemplate, getEpisodeEquipmentSummary; returnEquipment mengirim returned_by.
