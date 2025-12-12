# 🔄 Analisis Workflow Music Arranger & Producer

Dokumentasi analisis workflow yang dijelaskan vs implementasi saat ini.

---

## 📋 Workflow yang Dijelaskan User

### **TAHAP 1: Music Arranger - Ajukan Lagu & Penyanyi**

```
Music Arranger:
1. Pilih Lagu
2. Pilih Penyanyi (opsional)
3. Ajukan ke Producer
```

### **TAHAP 2: Producer - Review Lagu & Penyanyi**

```
Producer:
1. Terima notifikasi
2. Terima Atau tidak Usulan lagu & penyanyi
3. Dapat Mengganti usulan dari Music Arr
4. Selesai Pekerjaan
```

### **TAHAP 3: Music Arranger - Arrange Lagu**

```
Music Arranger:
1. Terima Notifikasi
2. Terima Pekerjaan
3. Arr Lagu (arrange lagu)
4. Selesaikan Pekerjaan
```

---

## 🔍 Analisis Implementasi Saat Ini

### **Current Flow:**

```
1. Music Arranger:
   - Create arrangement (dengan song/singer, file optional)
   - Status: draft
   - Submit arrangement
   - Status: submitted

2. Producer:
   - Terima notifikasi
   - Approve/Reject/Edit song/singer
   - Status: approved/rejected

3. Workflow lanjut ke Sound Engineer
```

### **Gap Analysis:**

❌ **TIDAK SESUAI** - Saat ini Music Arranger langsung create arrangement dengan file (optional), bukan ajukan lagu & penyanyi dulu.

**Yang Perlu Diubah:**

1. **Tahap 1:** Music Arranger ajukan lagu & penyanyi (tanpa arrangement file)
   - Status: `song_proposal` atau `pending_song_approval`
   - Tidak perlu file arrangement

2. **Tahap 2:** Producer approve/reject/edit lagu & penyanyi
   - Setelah approve → Status: `song_approved` atau `ready_for_arrangement`
   - Music Arranger mendapat notifikasi

3. **Tahap 3:** Music Arranger arrange lagu setelah song approved
   - Upload arrangement file
   - Submit arrangement
   - Status: `submitted` → Producer review arrangement file

---

## 💡 Rekomendasi Perubahan

### **Opsi 1: Tambah Status Baru**

Tambahkan status baru untuk membedakan:
- `song_proposal` - Ajukan lagu & penyanyi (tanpa file)
- `song_approved` - Lagu & penyanyi sudah approved, siap untuk arrange
- `submitted` - Arrangement file sudah di-submit
- `approved` - Arrangement file approved

### **Opsi 2: Pisah Model**

Buat 2 model terpisah:
1. `SongProposal` - Untuk ajukan lagu & penyanyi
2. `MusicArrangement` - Untuk arrangement file (setelah song approved)

### **Opsi 3: Gunakan Field Flag**

Gunakan field `has_arrangement_file` atau `arrangement_status`:
- `song_proposed` - Lagu & penyanyi diusulkan
- `song_approved` - Lagu & penyanyi approved
- `arrangement_in_progress` - Sedang arrange
- `arrangement_submitted` - Arrangement file submitted
- `arrangement_approved` - Arrangement file approved

---

## ✅ Kesimpulan

**Status:** ⚠️ **PERLU PENYESUAIAN**

**Workflow saat ini:** Music Arranger langsung create arrangement dengan file (optional)

**Workflow yang diinginkan:** 
1. Ajukan lagu & penyanyi dulu (tanpa file)
2. Producer approve lagu & penyanyi
3. Music Arranger arrange lagu setelah song approved

**Rekomendasi:** Gunakan **Opsi 1** (tambah status baru) karena lebih sederhana dan tidak perlu ubah struktur database besar.

---

**Last Updated:** December 10, 2025

