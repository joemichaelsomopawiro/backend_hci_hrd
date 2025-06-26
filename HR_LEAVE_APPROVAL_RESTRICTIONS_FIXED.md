# Pembatasan Wewenang HR dalam Sistem Persetujuan Cuti

## Ringkasan Masalah
Sebelumnya, HR Manager dapat melihat dan mencoba menyetujui permohonan cuti dari semua departemen, termasuk bawahan Program Manager dan Distribution Manager. Meskipun HR tidak memiliki wewenang untuk menyetujui cuti tersebut, mereka masih dapat melihatnya dalam daftar permohonan cuti.

## Solusi yang Diimplementasikan
Membatasi HR Manager agar:
1. **Hanya dapat melihat** permohonan cuti dari bawahan langsung mereka (Finance, General Affairs, Office Assistant)
2. **Tidak dapat melihat** permohonan cuti dari Program Manager, Distribution Manager, atau bawahan mereka
3. **Hanya dapat menyetujui/menolak** cuti dari bawahan langsung mereka

## File yang Dimodifikasi

### 1. `app/Services/RoleHierarchyService.php`
**Perubahan:** Modifikasi fungsi `canApproveLeave()`
- Menambahkan pembatasan khusus untuk HR Manager
- HR hanya dapat menyetujui cuti dari Finance, General Affairs, dan Office Assistant
- HR tidak dapat menyetujui cuti dari Program Manager atau Distribution Manager

### 2. `app/Http/Controllers/LeaveRequestController.php`
**Perubahan:** Modifikasi metode `index()`
- **PERBAIKAN UTAMA:** Mengganti logika lama yang memungkinkan HR melihat semua permohonan
- Menambahkan filter untuk HR Manager agar hanya dapat melihat permohonan cuti dari bawahan langsung
- Menggunakan `RoleHierarchyService::getSubordinateRoles()` untuk mendapatkan daftar peran bawahan
- Menghapus komentar "HR Manager dapat melihat SEMUA permohonan dari SEMUA departemen"

### 3. `app/Http/Controllers/GeneralAffairController.php`
**Perubahan:** 
- Memperbaiki namespace dari `App\Http\Controllers\Api` menjadi `App\Http\Controllers`
- Menambahkan import `RoleHierarchyService`
- Modifikasi metode `getAllLeaveRequests()` dengan menambahkan otorisasi berdasarkan peran
- **PERBAIKAN BARU:** Modifikasi metode `getLeaves()` dengan menambahkan otorisasi yang sama
- Menambahkan autentikasi user untuk metode `getLeaves()`

### 4. `routes/api.php`
**Perubahan:**
- Memperbaiki import `GeneralAffairController` untuk mencocokkan namespace yang benar
- Menambahkan middleware `auth:sanctum` untuk rute-rute yang memerlukan autentikasi
- **PERBAIKAN BARU:** Memindahkan route `/leaves` ke dalam grup middleware `auth:sanctum`

## Hierarki Persetujuan Cuti Setelah Perbaikan

### HR Manager
- **Dapat melihat dan menyetujui:** Finance, General Affairs, Office Assistant
- **Tidak dapat melihat:** Program Manager, Distribution Manager, dan bawahan mereka

### Program Manager
- **Dapat melihat dan menyetujui:** Bawahan langsung mereka saja
- **Tidak dapat melihat:** Departemen lain

### Distribution Manager
- **Dapat melihat dan menyetujui:** Bawahan langsung mereka saja
- **Tidak dapat melihat:** Departemen lain

### Karyawan Biasa
- **Dapat melihat:** Hanya permohonan cuti mereka sendiri

## Endpoint yang Terpengaruh

1. **GET `/api/leave-requests`** (LeaveRequestController@index)
   - Sekarang memfilter berdasarkan hierarki yang benar

2. **GET `/api/dashboard/leave-requests`** (GeneralAffairController@getAllLeaveRequests)
   - Menambahkan otorisasi yang sama

3. **GET `/api/leaves`** (GeneralAffairController@getLeaves)
   - Menambahkan otorisasi dan autentikasi
   - Dipindahkan ke grup middleware `auth:sanctum`

## Panduan Pengujian

### Test Case 1: HR Manager Login
1. Login sebagai HR Manager
2. Akses halaman permohonan cuti
3. **Hasil yang diharapkan:** Hanya melihat permohonan dari Finance, General Affairs, Office Assistant
4. **Tidak boleh melihat:** Permohonan dari Program Manager atau Distribution Manager

### Test Case 2: Program Manager Login
1. Login sebagai Program Manager
2. Akses halaman permohonan cuti
3. **Hasil yang diharapkan:** Hanya melihat permohonan dari bawahan langsung mereka

### Test Case 3: Distribution Manager Login
1. Login sebagai Distribution Manager
2. Akses halaman permohonan cuti
3. **Hasil yang diharapkan:** Hanya melihat permohonan dari bawahan langsung mereka

## Dampak Perubahan

### Positif
- ✅ HR tidak lagi melihat permohonan yang tidak bisa mereka setujui
- ✅ Hierarki persetujuan menjadi lebih jelas dan konsisten
- ✅ Mengurangi kebingungan dalam sistem
- ✅ Keamanan data yang lebih baik

### Yang Perlu Diperhatikan
- 🔍 Pastikan semua endpoint yang menampilkan data cuti sudah konsisten
- 🔍 Test semua role untuk memastikan tidak ada yang terlewat
- 🔍 Dokumentasikan perubahan ini kepada pengguna sistem

## Catatan Teknis

- Semua perubahan menggunakan `RoleHierarchyService` untuk konsistensi
- Middleware `auth:sanctum` ditambahkan untuk endpoint yang memerlukan autentikasi
- Namespace controller sudah diperbaiki untuk konsistensi
- Error handling ditambahkan untuk kasus user tidak terautentikasi

---
**Tanggal Implementasi:** $(Get-Date -Format "yyyy-MM-dd")
**Status:** Selesai - Siap untuk testing