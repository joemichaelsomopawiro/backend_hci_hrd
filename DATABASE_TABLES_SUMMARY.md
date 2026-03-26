# 📊 RINGKASAN TABEL DATABASE SISTEM INFORMASI HRD

Dokumen ini merangkum semua tabel database yang diperlukan untuk Sistem Informasi HRD Hope Channel Indonesia berdasarkan analisis proyek dan migration files yang ada.

---

## 📋 DAFTAR TABEL DATABASE

### 1. **TABEL UTAMA (CORE TABLES)**

#### 1.1. `employees`
**Deskripsi:** Tabel utama untuk menyimpan data karyawan
**Fitur Terkait:** F#03 - Manajemen Data Karyawan
**Kolom Utama:**
- `id` (Primary Key)
- `nama_lengkap`, `nik`, `nip`
- `tanggal_lahir`, `jenis_kelamin`, `alamat`
- `status_pernikahan`
- `jabatan_saat_ini`, `tanggal_mulai_kerja`
- `tingkat_pendidikan`
- `gaji_pokok`, `tunjangan`, `bonus`
- `nomor_bpjs_kesehatan`, `nomor_bpjs_ketenagakerjaan`
- `npwp`, `nomor_kontrak`, `tanggal_kontrak_berakhir`
- `department` (ditambahkan via migration)
- `manager_id` (untuk hierarki organisasi)
- `numcard` (untuk integrasi mesin fingerprint)
- `created_from` (sumber data)

#### 1.2. `users`
**Deskripsi:** Tabel untuk menyimpan data akun pengguna sistem
**Fitur Terkait:** F#01 - Autentikasi & Login JWT, F#02 - RBAC
**Kolom Utama:**
- `id` (Primary Key)
- `name`, `email`, `phone`
- `password`
- `email_verified_at`, `phone_verified_at`
- `employee_id` (Foreign Key ke employees)
- `role` (untuk Role-Based Access Control)
- `access_level` (level akses: employee, manager, hr_readonly, hr_full)
- `profile_picture` (foto profil)
- `remember_token`

---

### 2. **TABEL AUTENTIKASI & KEAMANAN**

#### 2.1. `otps`
**Deskripsi:** Tabel untuk menyimpan kode OTP untuk registrasi dan reset password
**Fitur Terkait:** F#01 - Autentikasi & Login JWT
**Kolom Utama:**
- `id` (Primary Key)
- `phone` (nomor telepon penerima OTP)
- `otp_code` (kode OTP)
- `type` (register, forgot_password)
- `expires_at` (waktu kadaluarsa)
- `is_used` (status penggunaan)

---

### 3. **TABEL ABSENSI**

#### 3.1. `attendances`
**Deskripsi:** Tabel untuk menyimpan data absensi karyawan (hasil final setelah processing)
**Fitur Terkait:** F#04 - Manajemen Absensi
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `date` (tanggal absensi)
- `check_in`, `check_out` (waktu masuk dan keluar)
- `status` (present, absent, sick, leave, permission, overtime)
- `work_hours` (jam kerja dalam desimal)
- `overtime_hours` (jam lembur)
- `notes` (catatan)
- Unique constraint: `employee_id + date`

#### 3.2. `attendance_machines`
**Deskripsi:** Tabel untuk menyimpan data mesin absensi (Solution X304)
**Fitur Terkait:** F#04 - Manajemen Absensi
**Kolom Utama:**
- `id` (Primary Key)
- `name` (nama mesin)
- `ip_address` (IP Address mesin)
- `port` (port untuk SOAP Web Service)
- `comm_key` (Communication Key)
- `device_id`, `serial_number`
- `status` (active, inactive, maintenance)
- `last_sync_at` (waktu sync terakhir)
- `settings` (JSON pengaturan tambahan)

#### 3.3. `attendance_logs`
**Deskripsi:** Tabel untuk menyimpan data log mentah dari mesin fingerprint
**Fitur Terkait:** F#04 - Manajemen Absensi
**Kolom Utama:**
- `id` (Primary Key)
- `attendance_machine_id` (Foreign Key ke attendance_machines)
- `employee_id` (Foreign Key ke employees, nullable)
- `user_pin` (PIN/UserID dari mesin)
- `datetime` (tanggal dan waktu tap)
- `verified_method` (card, fingerprint, face, password)
- `verified_code` (kode verifikasi dari mesin)
- `status_code` (check_in, check_out, break_out, break_in, overtime_in, overtime_out)
- `is_processed` (apakah sudah diproses menjadi attendance)
- `raw_data` (data mentah dari mesin)

#### 3.4. `employee_attendance`
**Deskripsi:** Tabel untuk menyimpan data karyawan yang terdaftar di mesin absensi
**Fitur Terkait:** F#04 - Manajemen Absensi
**Kolom Utama:**
- `id` (Primary Key)
- `attendance_machine_id` (Foreign Key ke attendance_machines)
- `machine_user_id` (ID Number dari mesin)
- `name`, `card_number`, `department`
- `privilege`, `group_name`
- `is_active`
- `raw_data` (JSON data mentah)
- `last_seen_at` (terakhir terlihat di mesin)

#### 3.5. `attendance_sync_logs`
**Deskripsi:** Tabel untuk menyimpan log sinkronisasi dengan mesin absensi
**Fitur Terkait:** F#04 - Manajemen Absensi
**Kolom Utama:**
- `id` (Primary Key)
- `attendance_machine_id` (Foreign Key ke attendance_machines)
- `operation` (pull_data, pull_user_data, push_user, delete_user, clear_data, sync_time, restart_machine, test_connection)
- `status` (success, failed, partial)
- `message`, `details` (JSON)
- `records_processed`
- `started_at`, `completed_at`
- `duration` (durasi operasi dalam detik)

#### 3.6. `morning_reflection_attendance`
**Deskripsi:** Tabel untuk menyimpan data absensi renungan pagi via Zoom
**Fitur Terkait:** F#04 - Manajemen Absensi
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `date` (tanggal absensi)
- `status` (Hadir, Terlambat, Absen, Izin, Cuti)
- `join_time` (waktu join Zoom)
- `attendance_method` (zoom, manual)
- `testing_mode` (mode testing)
- Unique constraint: `employee_id + date`

#### 3.7. `worship_attendance`
**Deskripsi:** Tabel untuk menyimpan data kehadiran ibadah
**Fitur Terkait:** F#04 - Manajemen Absensi (Data Worship)
**Kolom Utama:**
- `id` (Primary Key)
- (Struktur lengkap perlu dicek di migration file)

---

### 4. **TABEL CUTI**

#### 4.1. `leave_requests`
**Deskripsi:** Tabel untuk menyimpan pengajuan cuti karyawan
**Fitur Terkait:** F#05 - Manajemen Cuti
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees - pengaju)
- `approved_by` (Foreign Key ke employees - yang menyetujui)
- `leave_type` (annual, sick, emergency, maternity, paternity, marriage, bereavement)
- `start_date`, `end_date`
- `total_days` (total hari cuti)
- `reason` (alasan cuti)
- `notes` (catatan)
- `status` (pending, approved, rejected, expired)
- `overall_status` (status persetujuan bertingkat)
- `approved_at` (waktu persetujuan)
- `rejection_reason` (alasan penolakan)
- `employee_signature`, `manager_signature` (path file tanda tangan)
- `expired_at` (untuk auto-expire)

#### 4.2. `leave_quotas`
**Deskripsi:** Tabel untuk menyimpan kuota cuti karyawan per tahun
**Fitur Terkait:** F#05 - Manajemen Cuti
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `year` (tahun)
- `annual_leave_quota`, `annual_leave_used` (cuti tahunan)
- `sick_leave_quota`, `sick_leave_used` (cuti sakit)
- `emergency_leave_quota`, `emergency_leave_used` (cuti darurat)
- `maternity_leave_quota`, `maternity_leave_used` (cuti melahirkan)
- `paternity_leave_quota`, `paternity_leave_used` (cuti ayah)
- `marriage_leave_quota`, `marriage_leave_used` (cuti menikah)
- `bereavement_leave_quota`, `bereavement_leave_used` (cuti duka)
- Unique constraint: `employee_id + year`

---

### 5. **TABEL PELATIHAN & PROMOSI**

#### 5.1. `trainings`
**Deskripsi:** Tabel untuk menyimpan data pelatihan yang diikuti karyawan
**Fitur Terkait:** F#06 - Manajemen Pelatihan dan Promosi
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `training_name` (nama pelatihan)
- `institution` (lembaga penyelenggara)
- `completion_date` (tanggal selesai)
- `certificate_number` (nomor sertifikat)

#### 5.2. `promotion_histories`
**Deskripsi:** Tabel untuk menyimpan riwayat promosi jabatan karyawan
**Fitur Terkait:** F#06 - Manajemen Pelatihan dan Promosi
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `position` (jabatan baru)
- `promotion_date` (tanggal promosi)

#### 5.3. `employment_histories`
**Deskripsi:** Tabel untuk menyimpan riwayat pekerjaan karyawan
**Fitur Terkait:** F#03 - Manajemen Data Karyawan
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `company_name` (nama perusahaan sebelumnya)
- `position` (jabatan)
- `start_date`, `end_date`

#### 5.4. `benefits`
**Deskripsi:** Tabel untuk menyimpan data tunjangan karyawan
**Fitur Terkait:** F#03 - Manajemen Data Karyawan
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `benefit_type` (jenis tunjangan)
- `amount` (jumlah tunjangan)
- `start_date` (tanggal mulai)

---

### 6. **TABEL DOKUMEN**

#### 6.1. `employee_documents`
**Deskripsi:** Tabel untuk menyimpan dokumen pendukung karyawan
**Fitur Terkait:** F#03 - Manajemen Data Karyawan
**Kolom Utama:**
- `id` (Primary Key)
- `employee_id` (Foreign Key ke employees)
- `document_type` (jenis dokumen)
- `file_path` (path file dokumen)

---

### 7. **TABEL KALENDER & HARI LIBUR**

#### 7.1. `national_holidays`
**Deskripsi:** Tabel untuk menyimpan daftar hari libur nasional dan custom
**Fitur Terkait:** F#08 - Integrasi Kalender dan Hari Libur Nasional
**Kolom Utama:**
- `id` (Primary Key)
- `date` (tanggal hari libur)
- `name` (nama hari libur)
- `description` (deskripsi)
- `type` (national, custom, weekend)
- `is_active` (status aktif)
- `created_by`, `updated_by` (Foreign Key ke users)
- Unique constraint: `date`

---

### 8. **TABEL PENGATURAN & KONFIGURASI**

#### 8.1. `settings`
**Deskripsi:** Tabel untuk menyimpan pengaturan aplikasi
**Fitur Terkait:** F#09 - Pengaturan Aplikasi dan Notifikasi
**Kolom Utama:**
- `id` (Primary Key)
- `key` (kunci pengaturan, unique)
- `value` (nilai pengaturan)
- Contoh: zoom_link, zoom_meeting_id, zoom_passcode

#### 8.2. `zoom_links`
**Deskripsi:** Tabel untuk menyimpan link Zoom untuk renungan pagi
**Fitur Terkait:** F#04 - Manajemen Absensi (Renungan Pagi)
**Kolom Utama:**
- `id` (Primary Key)
- `zoom_link` (link Zoom meeting)
- `meeting_id` (ID meeting Zoom)
- `passcode` (kode akses)
- `is_active` (status aktif)
- `created_by`, `updated_by` (Foreign Key ke users)

#### 8.3. `custom_roles`
**Deskripsi:** Tabel untuk menyimpan role custom yang dapat dikonfigurasi
**Fitur Terkait:** F#02 - Role Based Access Control
**Kolom Utama:**
- `id` (Primary Key)
- `role_name` (nama role, unique)
- `description` (deskripsi role)
- `access_level` (employee, manager, hr_readonly, hr_full)
- `is_active` (status aktif)
- `created_by` (Foreign Key ke users)

---

## 📊 RINGKASAN TABEL PER FITUR

### F#01 - Autentikasi & Login JWT
- `users`
- `otps`

### F#02 - Role Based Access Control
- `users` (kolom role, access_level)
- `custom_roles`

### F#03 - Manajemen Data Karyawan
- `employees`
- `employee_documents`
- `employment_histories`
- `benefits`

### F#04 - Manajemen Absensi
- `attendances`
- `attendance_machines`
- `attendance_logs`
- `employee_attendance`
- `attendance_sync_logs`
- `morning_reflection_attendance`
- `worship_attendance`
- `zoom_links`

### F#05 - Manajemen Cuti
- `leave_requests`
- `leave_quotas`

### F#06 - Manajemen Pelatihan dan Promosi
- `trainings`
- `promotion_histories`

### F#07 - Dashboard dan Laporan
- Menggunakan data dari semua tabel di atas (tidak memerlukan tabel khusus)

### F#08 - Integrasi Kalender dan Hari Libur Nasional
- `national_holidays`

### F#09 - Pengaturan Aplikasi dan Notifikasi
- `settings`

---

## 🔗 HUBUNGAN ANTAR TABEL (RELATIONSHIPS)

### Hubungan Utama:
1. **users** → **employees** (one-to-one via employee_id)
2. **employees** → **attendances** (one-to-many)
3. **employees** → **leave_requests** (one-to-many)
4. **employees** → **leave_quotas** (one-to-many)
5. **employees** → **trainings** (one-to-many)
6. **employees** → **promotion_histories** (one-to-many)
7. **employees** → **employment_histories** (one-to-many)
8. **employees** → **benefits** (one-to-many)
9. **employees** → **employee_documents** (one-to-many)
10. **employees** → **morning_reflection_attendance** (one-to-many)
11. **attendance_machines** → **attendance_logs** (one-to-many)
12. **attendance_machines** → **employee_attendance** (one-to-many)
13. **attendance_machines** → **attendance_sync_logs** (one-to-many)
14. **leave_requests** → **employees** (approved_by, many-to-one)

---

## 📝 CATATAN PENTING

1. **Tabel `employees` adalah tabel utama** yang menjadi pusat relasi dengan tabel-tabel lain
2. **Tabel `users` terhubung ke `employees`** untuk autentikasi dan akses sistem
3. **Sistem absensi menggunakan 3 level:**
   - `attendance_logs` (data mentah dari mesin)
   - `employee_attendance` (data karyawan di mesin)
   - `attendances` (data final yang sudah diproses)
4. **Sistem cuti menggunakan 2 tabel:**
   - `leave_requests` (pengajuan cuti)
   - `leave_quotas` (kuota cuti per tahun)
5. **Sistem RBAC menggunakan:**
   - Kolom `role` di tabel `users`
   - Tabel `custom_roles` untuk role yang dapat dikonfigurasi
6. **Integrasi mesin fingerprint menggunakan:**
   - `attendance_machines` (data mesin)
   - `attendance_logs` (log dari mesin)
   - `attendance_sync_logs` (log sinkronisasi)

---

## ✅ TOTAL TABEL YANG DIPERLUKAN

**Total: 20 Tabel**

1. employees
2. users
3. otps
4. attendances
5. attendance_machines
6. attendance_logs
7. employee_attendance
8. attendance_sync_logs
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

---

**Dokumen ini dibuat berdasarkan analisis migration files dan dokumentasi proyek Sistem Informasi HRD Hope Channel Indonesia.**








