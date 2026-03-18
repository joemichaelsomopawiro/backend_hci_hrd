# Frontend: Edit Permintaan Alat yang Masih Pending

**Aturan Program Musik:** Tim **Setting** = pinjam barang (request equipment). Tim **Syuting** = balikin barang (return equipment). Backend menolak jika role/tim tidak sesuai.

Agar **riwayat permintaan alat** yang statusnya **Menunggu Art & Set** bisa diubah (tambah/hapus/ganti barang) **sebelum** di-approve Art & Set Properti, frontend harus memakai flow **update** (bukan buat permintaan baru).

---

## 1. Saat form terisi dari permintaan pending

- Jika user memilih satu item di **Riwayat permintaan alat** yang statusnya **Menunggu Art & Set**, form di bawah terisi dari permintaan itu.
- Teks: *"Form di bawah terisi dari permintaan ini. Ubah jika perlu lalu Submit peminjaman."*

---

## 2. Saat Submit

- **Wajib**: kirim body berisi **`update_equipment_request_id`** = **id** permintaan yang sedang diedit (id dari item riwayat yang pending).
- Backend akan **meng-update** record itu (daftar alat, catatan, dll). Status tetap **pending** sampai Art & Set approve/reject.

Contoh body:

```json
{
  "equipment_list": [
    { "equipment_name": "Canon", "quantity": 1, "notes": "" },
    { "equipment_name": "Tripod", "quantity": 1, "notes": "" }
  ],
  "request_notes": "",
  "update_equipment_request_id": 19
}
```

- Jika **tidak** mengirim `update_equipment_request_id`, backend akan **membuat permintaan baru**. Riwayat akan punya 2 permintaan (yang lama tetap pending, yang baru juga pending).

---

## 3. Setelah response sukses

- Response selalu berisi **`data.equipment_requests`**: daftar **lengkap** semua permintaan untuk episode itu (urutan `requested_at` desc).
- **Wajib**: gunakan **`data.equipment_requests`** untuk **memperbarui tampilan Riwayat permintaan alat** (replace state riwayat dengan array ini).
- Jika tidak di-update, tampilan riwayat tetap menampilkan data lama sampai user refresh/keluar-masuk.

Response saat **update**:

```json
{
  "success": true,
  "data": {
    "work": { ... },
    "equipment_requests": [ ... ],
    "is_update": true,
    "updated_request_id": 19
  },
  "message": "Permintaan alat diperbarui. Masih Menunggu Art & Set Properti. Tampilan riwayat gunakan data.equipment_requests."
}
```

- **`is_update`**: `true` = permintaan yang ada di-update; `false` = permintaan baru dibuat.
- **`updated_request_id`**: id permintaan yang di-update (hanya ada kalau `is_update === true`).

---

## 4. Ringkasan

| Yang dilakukan user | Yang harus dikirim | Yang harus dilakukan frontend setelah sukses |
|--------------------|--------------------|---------------------------------------------|
| Ubah form yang terisi dari permintaan pending lalu Submit | `update_equipment_request_id` = id permintaan itu | Set riwayat = `response.data.equipment_requests` |
| Isi form baru (bukan dari riwayat) lalu Submit | Jangan kirim `update_equipment_request_id` | Set riwayat = `response.data.equipment_requests` |

Dengan begitu, data riwayat yang **pending** bisa diubah sebelum di-approve, dan tampilan riwayat langsung ikut berubah setelah Submit.

---

## 5. Rekomendasi UX: Satu tampilan untuk “yang mau dipinjam” (pending)

**Masalah saat ini:** Riwayat menampilkan data lama (mis. Camera × 1, Canon × 1) sementara user sudah mengubah Daftar Alat di form (mis. hanya Canon × 1). Tampilan riwayat dan form tidak sinkron sampai user Submit.

**Rekomendasi:** Untuk permintaan yang **Masih Menunggu**, jangan pisahkan “tampilan riwayat” dan “form Daftar Alat”. Buat **satu sumber tampilan** = **daftar alat yang mau dipinjam** yang bisa langsung diedit (tambah/ganti/hapus).

### Opsi A – Form = tampilan untuk pending (disarankan)

- Jika ada **satu** permintaan pending untuk episode ini:
  - **Riwayat** menampilkan kartu: tanggal, status “Menunggu Art & Set”, lalu **langsung menampilkan isi Daftar Alat** dari **state form** (bukan dari data riwayat terpisah).
  - Artinya: yang tampil di “riwayat” untuk item pending = persis isi form (Canon × 1, dll). User ubah di form → tampilan riwayat ikut berubah karena pakai data yang sama.
  - Tombol **Submit peminjaman** menyimpan dengan `update_equipment_request_id`. Setelah sukses, tetap pakai `data.equipment_requests` untuk refresh (untuk konsistensi dengan server).
- Jika ada beberapa permintaan (mis. 1 pending + 1 sudah approved), tampilkan yang approved/disetujui/ditolak sebagai kartu statis; yang pending pakai pola di atas (form = tampilan pending).

### Opsi B – Edit inline di kartu riwayat

- Kartu riwayat untuk permintaan **pending** tidak hanya teks (Camera × 1, Canon × 1), tapi berisi **Daftar Alat yang bisa diedit inline**: tambah baris, ubah qty, hapus baris. Simpan (Submit) dari dalam kartu itu. Setelah submit, refresh dari `data.equipment_requests`.

**Inti:** Untuk status **Menunggu**, “riwayat” = **daftar alat yang mau dipinjam** yang bisa langsung diubah (tambah/ganti/hapus), lalu tersimpan saat Submit. Tidak ada dua tampilan terpisah yang bisa beda isi.
