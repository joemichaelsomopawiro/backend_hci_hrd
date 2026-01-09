# Postman Testing Guide - HCI HRD Complete Flow

> **Note:** Collection ini menggunakan **Collection Variables**, jadi tidak perlu import environment terpisah. Semua variables sudah ada di dalam collection!

## 🚀 Quick Start (3 Langkah Mudah!)

### Langkah 1: Import Collection
1. Buka Postman
2. Klik tombol **Import** (kiri atas)
3. Pilih file `Postman_Collection_HCI_HRD_Complete_Flow.json`
4. Klik **Import**

### Langkah 2: Edit Base URL (PENTING!)

**Cara Edit Variables di Postman:**

1. **Klik kanan** pada collection `HCI HRD - Complete Flow Testing` (di sidebar kiri)
2. Pilih **Edit** (yang paling atas)
3. Akan muncul popup/modal
4. Di bagian atas popup, ada beberapa **tab**: `General`, `Authorization`, `Variables`, `Pre-request Script`, dll
5. **Klik tab "Variables"** (di bagian atas popup)
6. Cari baris dengan **Variable** = `base_url`
7. Edit kolom **Current Value** (bukan Initial Value)
   - Default: `http://localhost:8000`
   - Sesuaikan dengan URL backend Anda
8. Klik **Save** (di kanan bawah popup)

**Visual Guide:**
```
Collection → Klik Kanan → Edit → Tab "Variables" → Edit base_url → Save
```

### Langkah 3: Login Cepat!

1. Buka folder **`0. Quick Login (Pilih Role)`**
2. Pilih role yang ingin Anda test (contoh: `Login - Producer`)
3. Klik **Send**
4. Token akan otomatis tersimpan! ✅

**Semua password: `password`** 🔐

---

## 📋 Daftar Quick Login

Di folder `0. Quick Login`, ada login untuk semua role:

- ✅ **Login - Manager Program** → `manager@example.com`
- ✅ **Login - Producer** → `producer@example.com`
- ✅ **Login - Music Arranger** → `musicarranger@example.com`
- ✅ **Login - Sound Engineer** → `soundengineer@example.com`
- ✅ **Login - Creative** → `creative@example.com`
- ✅ **Login - General Affairs** → `generalaffairs@example.com`
- ✅ **Login - Distribution** → `dsitribution@example.com`
- ✅ **Login - Design Grafis** → `graphicdesign@example.com`
- ✅ **Login - Editor Promosi** → `editorpromotion@example.com`
- ✅ **Login - Produksi** → `production@example.com`
- ✅ **Login - Art & Set Properti** → `artsetdesign@example.com`
- ✅ **Login - Quality Control** → `qualitycontrol@example.com`
- ✅ **Login - Editor** → `editor@example.com`
- ✅ **Login - Broadcasting** → `broadcasting@example.com`

**Password semua: `password`** 🔐

---

## 🔧 Cara Edit Variables (Detail)

### Jika Tab Variables Tidak Terlihat:

1. Pastikan Anda klik **Edit** pada **Collection** (bukan folder atau request)
2. Collection adalah item paling atas di sidebar (yang punya icon folder)
3. Klik kanan → Edit
4. Tab Variables ada di bagian atas popup

### Cara Update Variables Setelah Request:

Setelah request yang menghasilkan ID baru (seperti `arrangement_id`, `creative_work_id`):

1. Klik kanan collection → **Edit**
2. Tab **Variables**
3. Cari variable yang ingin diupdate (contoh: `arrangement_id`)
4. Edit **Current Value** dengan ID baru dari response
5. Klik **Save**

---

## 📝 Testing Flow

### Step 1: Login (PENTING - BACA INI DULU!)

**Cara Login yang Benar:**

1. **Buka folder role yang ingin ditest** (contoh: `2. Manager Program`)
2. **Klik request `🔐 Login - Manager Program`** (yang ada di paling atas folder)
3. **Klik Send**
4. **Buka Console** (View → Show Postman Console) untuk melihat log:
   - ✅ Harus ada: `✅ Login berhasil sebagai: Manager`
   - ✅ Harus ada: `✅ Token berhasil disimpan: ...`
   - ✅ Harus ada: `✅ Token length: [angka lebih dari 0]`
5. **Verifikasi token tersimpan:**
   - Klik kanan collection → Edit → Tab Variables
   - Cek apakah `auth_token` ada isinya (bukan kosong)
   - Jika kosong, login lagi dan cek Console untuk error

**⚠️ PENTING:** 
- Setelah login, tunggu 1-2 detik sebelum test request lainnya!
- Jika token masih kosong, coba login lagi dan cek Console untuk error message
- Pastikan response login berhasil (status 200) dan ada field `data.token`

### Step 2: Test Flow Berdasarkan Role

**Flow Music Arranger:**
1. `3. Music Arranger` → `Get Available Songs`
2. `3. Music Arranger` → `Create Arrangement`
3. `3. Music Arranger` → `Submit Song Proposal`
4. `3. Music Arranger` → `Complete Work`

**Flow Producer:**
1. `4. Producer` → `Get Approvals`
2. `4. Producer` → `Approve Arrangement`
3. `4. Producer` → `Review Creative Work`
4. `4. Producer` → `Final Approve Creative Work`

**Flow Creative:**
1. `7. Creative` → `Get Works`
2. `7. Creative` → `Accept Work`
3. `7. Creative` → `Update Creative Work`
4. `7. Creative` → `Complete Work`

Dan seterusnya...

---

## 💡 Tips & Tricks

1. **Auto-Save Token:** Token otomatis tersimpan setelah login berhasil
2. **Check Console:** View → Show Postman Console untuk melihat log
3. **Update Variables:** Setelah dapat ID baru, update di collection variables
4. **Check Notifications:** Gunakan `17. Notifications` → `Get Notifications`
5. **Check Dashboard:** Gunakan `18. Dashboard & Schedules`

---

## ❓ Troubleshooting

### "base_url tidak ditemukan"
- Pastikan sudah edit collection variables (bukan environment)
- Klik kanan collection → Edit → Tab Variables

### "Unauthorized" atau "401" atau "Authentication required"
**Ini masalah paling umum!** Ikuti langkah berikut:

1. **Pastikan sudah login dulu:**
   - Buka folder `0. Quick Login (Pilih Role)`
   - Pilih login sesuai role yang ingin ditest
   - Klik **Send**
   - Cek di **Console** (View → Show Postman Console) apakah ada pesan "✅ Login berhasil"

2. **Verifikasi token tersimpan:**
   - Setelah login, buka collection → Edit → Tab Variables
   - Cek apakah `auth_token` sudah ada isinya (bukan kosong)
   - Jika kosong, login lagi

3. **Cek Authorization header:**
   - Buka request yang error
   - Tab **Headers**
   - Pastikan ada header: `Authorization: Bearer {{auth_token}}`
   - Jika tidak ada, tambahkan manual

4. **Test token:**
   - Setelah login, coba request `1. Authentication` → `Get Me`
   - Jika berhasil, berarti token OK
   - Jika gagal, login lagi

5. **Refresh collection:**
   - Kadang Postman perlu refresh
   - Tutup dan buka lagi collection
   - Atau restart Postman

### "Connection refused" atau "Network error"
- Cek apakah backend server sudah running
- Cek base_url sudah benar
- Cek firewall/antivirus

### Token tidak tersimpan setelah login
- Cek Console untuk error messages
- Pastikan response login berhasil (status 200)
- Pastikan response punya field `data.token`
- Coba login lagi dan cek Console

---

## 📞 Need Help?

Jika masih bingung:
1. Pastikan collection sudah di-import
2. Pastikan sudah edit base_url di collection variables
3. Pastikan sudah login dulu sebelum test endpoint lain
4. Cek console Postman untuk error messages

**Happy Testing! 🎉**
