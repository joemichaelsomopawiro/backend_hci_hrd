# 🎯 TABEL DATABASE YANG SANGAT PERLU (SIMPLIFIED) 
---

## ✅ TABEL YANG HARUS ADA (CORE - 7 TABEL)

### 1. **`employees`** ⭐⭐⭐
**PENTING:** Tabel utama sistem
- Data karyawan lengkap
- **TIDAK BISA DIHILANGKAN**

### 2. **`users`** ⭐⭐⭐
**PENTING:** Autentikasi dan akses sistem
- Login, role, akses
- **TIDAK BISA DIHILANGKAN**

### 3. **`attendances`** ⭐⭐⭐
**PENTING:** Data absensi final
- Check-in/out, status, jam kerja
- **TIDAK BISA DIHILANGKAN**

### 4. **`leave_requests`** ⭐⭐⭐
**PENTING:** Pengajuan cuti
- Data cuti, approval, status
- **TIDAK BISA DIHILANGKAN**

### 5. **`leave_quotas`** ⭐⭐⭐
**PENTING:** Kuota cuti per tahun
- Jatah cuti tahunan, sakit, darurat, dll
- **TIDAK BISA DIHILANGKAN**

### 6. **`morning_reflection_attendance`** ⭐⭐
**PENTING:** Absensi renungan pagi
- Bisa digabung dengan `attendances` (tambah kolom `attendance_type`)
- **OPSI:** Gabung ke `attendances` dengan kolom `type` (office, morning_reflection)

### 7. **`national_holidays`** ⭐⭐
**PENTING:** Perhitungan cuti dan absensi
- Hari libur nasional untuk kalkulasi
- **TIDAK BISA DIHILANGKAN** (untuk perhitungan cuti)

---

## ⚠️ TABEL YANG TIDAK DIPERLUKAN (METODE TXT UPLOAD)

### A. **TABEL ABSENSI MESIN (TIDAK DIPERLUKAN)**

#### ❌ **HILANGKAN (Tidak Dipakai untuk TXT Upload):**
1. **`attendance_logs`** 
   - **Alasan:** Metode TXT upload langsung save ke `attendances`, tidak perlu log mentah
   - **Status:** ❌ **TIDAK DIPERLUKAN**

2. **`employee_attendance`**
   - **Alasan:** Mapping karyawan pakai kolom `NumCard` di `employees`
   - **Status:** ❌ **TIDAK DIPERLUKAN**

3. **`attendance_sync_logs`**
   - **Alasan:** Tidak ada sync otomatis, hanya upload manual TXT
   - **Status:** ❌ **TIDAK DIPERLUKAN**

4. **`attendance_machines`**
   - **Alasan:** Tidak perlu konfigurasi mesin di database, GA upload file langsung
   - **Status:** ❌ **TIDAK DIPERLUKAN**

---

### B. **TABEL RIWAYAT KARYAWAN (Bisa Digabung)**

#### ✅ **BISA DIGABUNG MENJADI 1 TABEL:**
1. **`trainings`** + **`promotion_histories`** + **`employment_histories`** + **`benefits`**
   - **Gabung jadi:** `employee_histories`
   - **Kolom:**
     - `id`, `employee_id`, `type` (training, promotion, employment, benefit)
     - `title` (nama training/jabatan/company/benefit_type)
     - `description` (institution/notes)
     - `date` (completion_date/promotion_date/start_date)
     - `end_date` (untuk employment)
     - `amount` (untuk benefit)
     - `certificate_number` (untuk training)
   - **Dampak:** Lebih sederhana, tapi query sedikit lebih kompleks
   - **REKOMENDASI:** **GABUNG** untuk menyederhanakan class diagram

---

### C. **TABEL YANG BISA DIHILANGKAN**

#### ❌ **BISA DIHILANGKAN:**
1. **`otps`**
   - **Alasan:** Bisa pakai cache (Redis/Memcached) atau session
   - **Alternatif:** `Cache::put('otp_' . $phone, $code, 300)`
   - **Dampak:** Tidak ada riwayat OTP di database (tapi tidak critical)

2. **`worship_attendance`**
   - **Alasan:** Bisa digabung dengan `morning_reflection_attendance` atau `attendances`
   - **Alternatif:** Tambah kolom `type` di `morning_reflection_attendance` (morning_reflection, worship)
   - **Dampak:** Tidak ada pemisahan data (tapi bisa dibedakan dengan type)

3. **`employee_documents`**
   - **Alasan:** Bisa jadi JSON di `employees` atau kolom `documents` (JSON)
   - **Alternatif:** `employees.documents` (JSON) = `[{"type": "ktp", "path": "..."}]`
   - **Dampak:** Tidak ada relasi terpisah (tapi lebih sederhana)

4. **`settings`**
   - **Alasan:** Bisa pakai config file atau `.env`
   - **Alternatif:** `config/app.php` atau `.env`
   - **Dampak:** Tidak bisa diubah via UI (tapi bisa pakai config file)

5. **`zoom_links`**
   - **Alasan:** Bisa masuk ke `settings` atau config file
   - **Alternatif:** `config/zoom.php` atau `.env`
   - **Dampak:** Tidak fleksibel untuk multiple link (tapi bisa 1 link saja)

6. **`custom_roles`**
   - **Alasan:** Bisa pakai enum di `users.role`
   - **Alternatif:** Enum di migration: `enum('role', ['HR', 'Manager', 'Employee', ...])`
   - **Dampak:** Tidak bisa tambah role dinamis (tapi bisa hardcode di enum)

---

## 📊 REKOMENDASI TABEL MINIMAL (METODE TXT UPLOAD)

### **VERSI MINIMAL (7-8 TABEL):**

1. ✅ **`employees`** - Data karyawan (dengan kolom `NumCard` untuk mapping)
2. ✅ **`users`** - Autentikasi & akses
3. ✅ **`attendances`** - Absensi kantor (hasil upload TXT)
   - Kolom: `employee_id`, `card_number`, `user_name`, `date`, `check_in`, `check_out`, `status`, `work_hours`
4. ✅ **`morning_reflection_attendance`** - Absensi renungan pagi (terpisah)
   - **ATAU** gabung ke `attendances` dengan kolom `attendance_type`
5. ✅ **`leave_requests`** - Pengajuan cuti
6. ✅ **`leave_quotas`** - Kuota cuti
7. ✅ **`employee_histories`** - Gabungan trainings + promotions + employment + benefits
   - Kolom: `type` (training, promotion, employment, benefit)
8. ✅ **`national_holidays`** - Hari libur (untuk perhitungan cuti)

---

## 🎯 VERSI SANGAT MINIMAL (7 TABEL) - UNTUK CLASS DIAGRAM

**✅ REKOMENDASI UNTUK CLASS DIAGRAM (Metode TXT Upload):**

1. ✅ **`employees`** - Data karyawan
   - Kolom: `id`, `nama_lengkap`, `nik`, `NumCard` (untuk mapping dengan file TXT)
   - Kolom: `documents` (JSON) untuk dokumen (opsional)
2. ✅ **`users`** - Autentikasi & akses
   - Kolom: `id`, `email`, `password`, `role`, `employee_id`
3. ✅ **`attendances`** - Absensi kantor (hasil upload TXT)
   - Kolom: `id`, `employee_id`, `card_number`, `user_name`, `date`, `check_in`, `check_out`, `status`, `work_hours`
   - **OPSI:** Tambah kolom `attendance_type` jika mau gabung dengan morning_reflection
4. ✅ **`morning_reflection_attendance`** - Absensi renungan pagi
   - Kolom: `id`, `employee_id`, `date`, `status`, `join_time`
   - **ATAU** gabung ke `attendances` dengan `attendance_type = 'morning_reflection'`
5. ✅ **`leave_requests`** - Pengajuan cuti
   - Kolom: `id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `status`, `approved_by`
6. ✅ **`leave_quotas`** - Kuota cuti per tahun
   - Kolom: `id`, `employee_id`, `year`, `annual_leave_quota`, `annual_leave_used`, dll
7. ✅ **`national_holidays`** - Hari libur nasional
   - Kolom: `id`, `date`, `name`, `type`

**TOTAL: 7 TABEL** (dari 21 tabel menjadi 7 tabel)

**📝 CATATAN:**
- Tabel `attendance_logs`, `attendance_machines`, `employee_attendance`, `attendance_sync_logs` **TIDAK DIPERLUKAN** karena metode TXT upload langsung save ke `attendances`
- Tabel `employee_histories` bisa ditambahkan jika butuh riwayat training/promotion (opsional)

---

## 📋 PERBANDINGAN: SEBELUM vs SESUDAH

### **SEBELUM (21 Tabel - Semua Fitur):**
1. employees
2. users
3. otps
4. attendances
5. attendance_machines ❌ (tidak dipakai untuk TXT upload)
6. attendance_logs ❌ (tidak dipakai untuk TXT upload)
7. employee_attendance ❌ (tidak dipakai untuk TXT upload)
8. attendance_sync_logs ❌ (tidak dipakai untuk TXT upload)
9. morning_reflection_attendance
10. worship_attendance
11. leave_requests
12. leave_quotas
13. trainings
14. promotion_histories
15. employment_histories
16. benefits
17. employee_documents
18. national_holidays
19. settings
20. zoom_links
21. custom_roles

### **SESUDAH SIMPLIFIED (7 Tabel - Metode TXT Upload):**
1. ✅ **employees** - Data karyawan (dengan `NumCard` untuk mapping)
2. ✅ **users** - Autentikasi & akses
3. ✅ **attendances** - Absensi kantor (hasil upload TXT)
4. ✅ **morning_reflection_attendance** - Absensi renungan pagi
5. ✅ **leave_requests** - Pengajuan cuti
6. ✅ **leave_quotas** - Kuota cuti
7. ✅ **national_holidays** - Hari libur

**PENGURANGAN: 21 → 7 tabel (67% lebih sederhana!)**

**✅ KONFIRMASI:**
- Metode yang dipakai: **Upload File TXT** oleh General Affairs ✅
- Tabel yang diperlukan: **7 tabel di atas** ✅
- Tabel yang tidak diperlukan: `attendance_logs`, `attendance_machines`, `employee_attendance`, `attendance_sync_logs` ❌

---

## 🔄 CARA IMPLEMENTASI PENYEDERHANAAN

### 1. **Gabung Absensi**
```php
// Migration: Update attendances table
Schema::table('attendances', function (Blueprint $table) {
    $table->enum('attendance_type', ['office', 'morning_reflection', 'worship'])
          ->default('office')
          ->after('employee_id');
});
```

### 2. **Gabung Riwayat**
```php
// Migration: Create employee_histories table
Schema::create('employee_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained();
    $table->enum('type', ['training', 'promotion', 'employment', 'benefit']);
    $table->string('title');
    $table->text('description')->nullable();
    $table->date('date');
    $table->date('end_date')->nullable();
    $table->decimal('amount', 15, 2)->nullable();
    $table->string('certificate_number')->nullable();
    $table->timestamps();
});
```

### 3. **Dokumen jadi JSON**
```php
// Migration: Update employees table
Schema::table('employees', function (Blueprint $table) {
    $table->json('documents')->nullable()->after('tanggal_kontrak_berakhir');
});
```

### 4. **OTP pakai Cache**
```php
// Ganti dari database ke cache
Cache::put('otp_' . $phone, $code, 300); // 5 menit
$otp = Cache::get('otp_' . $phone);
```

### 5. **Settings pakai Config**
```php
// config/attendance.php
return [
    'machine_ip' => env('ATTENDANCE_MACHINE_IP'),
    'machine_port' => env('ATTENDANCE_MACHINE_PORT', 80),
];

// config/zoom.php
return [
    'meeting_link' => env('ZOOM_MEETING_LINK'),
    'meeting_id' => env('ZOOM_MEETING_ID'),
    'passcode' => env('ZOOM_PASSCODE'),
];
```

---

## ✅ KESIMPULAN - METODE TXT UPLOAD

### **✅ KONFIRMASI METODE:**
- **Metode yang dipakai:** Upload File TXT oleh General Affairs ✅
- **Alur kerja:** GA ekspor file TXT dari mesin → Upload ke sistem → Parse & save ke `attendances`
- **Tidak menggunakan:** Sync otomatis via SOAP, attendance_logs, attendance_machines

### **✅ TABEL UNTUK CLASS DIAGRAM (7 TABEL):**
1. ✅ **`employees`** - Data karyawan (dengan `NumCard` untuk mapping dengan file TXT)
2. ✅ **`users`** - Autentikasi & akses
3. ✅ **`attendances`** - Absensi kantor (hasil upload TXT)
4. ✅ **`morning_reflection_attendance`** - Absensi renungan pagi
5. ✅ **`leave_requests`** - Pengajuan cuti
6. ✅ **`leave_quotas`** - Kuota cuti
7. ✅ **`national_holidays`** - Hari libur

### **❌ TABEL YANG TIDAK DIPERLUKAN:**
- `attendance_logs` - Tidak dipakai (langsung save ke attendances)
- `attendance_machines` - Tidak dipakai (tidak perlu konfigurasi mesin)
- `employee_attendance` - Tidak dipakai (mapping pakai NumCard di employees)
- `attendance_sync_logs` - Tidak dipakai (tidak ada sync otomatis)
- `otps` - Bisa pakai cache
- `settings` - Bisa pakai config file
- `zoom_links` - Bisa pakai config file
- `custom_roles` - Bisa pakai enum di users
- `employee_documents` - Bisa jadi JSON di employees
- `trainings`, `promotion_histories`, `employment_histories`, `benefits` - Bisa digabung jadi `employee_histories` (opsional)

### **Keuntungan:**
- ✅ Class diagram lebih sederhana dan mudah dipahami
- ✅ Relasi antar tabel lebih jelas
- ✅ Fokus pada metode yang benar-benar dipakai (TXT upload)
- ✅ Maintenance lebih mudah

---

**✅ REKOMENDASI: Gunakan 7 tabel di atas untuk class diagram. Sudah benar dan sesuai dengan metode TXT upload yang dipakai sekarang.**

