# 📋 CARA KERJA SISTEM ABSENSI KANTOR

Dokumen ini menjelaskan secara detail cara kerja sistem absensi kantor yang terintegrasi dengan mesin fingerprint **Solution X304** di Hope Channel Indonesia.

---

## 🎯 OVERVIEW SISTEM

Sistem absensi kantor menggunakan **2 metode utama**:

1. **Upload File TXT** (Manual) - GA/HR mengekspor file dari aplikasi Solution dan upload ke sistem
2. **Sync Otomatis via SOAP** (Otomatis) - Sistem langsung mengambil data dari mesin via SOAP Web Service

---

## 📊 ALUR KERJA ABSENSI KANTOR

### **METODE 1: Upload File TXT (Manual)**

#### **Step 1: Karyawan Tap di Mesin**
```
👤 Karyawan → 📱 Tap Fingerprint/Kartu → 💾 Mesin Solution X304
```
- Karyawan melakukan tap di mesin fingerprint menggunakan:
  - **Kartu RFID** (nomor kartu 10 digit)
  - **Sidik Jari (Fingerprint)**
- Mesin menyimpan data tap ke memori internal

#### **Step 2: GA/HR Ekspor File TXT**
```
💾 Mesin Solution X304 → 📂 Aplikasi Solution → 📄 File TXT
```
- GA/HR membuka aplikasi **Solution** (software untuk mesin X304)
- Mengekspor data absensi dalam format **TXT (Fixed Width)**
- File TXT berisi data absensi harian/bulanan

#### **Step 3: Format File TXT**
```
No. ID        Nama                   Tanggal    Scan Masuk Scan Pulang Absent Jml Jam Kerja Jml Kehadiran
1             E.H Michael Palar      07-Jul-25                                      
20111201      Steven Albert Reynold M07-Jul-25   14:22      19:29             05:06         05:06
```

**Struktur Fixed Width:**
- `No. ID` (0-12): Nomor kartu/NIK
- `Nama` (13-36): Nama karyawan
- `Tanggal` (37-47): Tanggal absensi (DD-MMM-YY)
- `Scan Masuk` (48-57): Waktu check-in (HH:MM)
- `Scan Pulang` (58-68): Waktu check-out (HH:MM)
- `Absent` (69-76): Status absent (Y/N)
- `Jml Jam Kerja` (77-89): Total jam kerja (HH:MM)
- `Jml Kehadiran` (90-102): Jumlah kehadiran

#### **Step 4: Upload File TXT ke Sistem**
```
📄 File TXT → 🌐 Web Application → 🔄 Backend Processing
```
- GA/HR login ke sistem web
- Masuk ke menu **"Absensi Kantor"**
- Klik **"Upload File TXT"**
- Pilih file TXT yang sudah diekspor
- Klik **"Preview"** untuk melihat 10 data pertama
- Jika data benar, klik **"Upload"**

#### **Step 5: Backend Processing**

**A. Parse File TXT**
```php
// app/Services/TxtAttendanceUploadService.php
1. Baca file TXT baris per baris
2. Parse header untuk mendapatkan posisi kolom
3. Deteksi format (raw atau fixed width)
4. Jika raw, konversi ke fixed width
```

**B. Parse Setiap Baris Data**
```php
// Parse setiap baris:
- card_number (No. ID)
- user_name (Nama)
- date (Tanggal) → format ke Y-m-d
- check_in (Scan Masuk) → format ke HH:MM:SS
- check_out (Scan Pulang) → format ke HH:MM:SS
- work_hours (Jml Jam Kerja) → convert ke decimal
- status (determine dari check_in dan absent)
```

**C. Mapping Employee ID**
```php
// Prioritas mapping:
1. Exact Name Match: user_name = nama_lengkap (exact)
2. Case-Insensitive Match: nama sama tapi beda huruf besar/kecil
3. Partial Match: nama mengandung atau terkandung
4. Card Number Fallback: card_number = NumCard di employees
```

**D. Simpan ke Database**
```php
// Save ke tabel attendances
Attendance::updateOrCreate(
    [
        'card_number' => $data['card_number'],
        'date' => $data['date'],
    ],
    [
        'employee_id' => $employee_id, // dari mapping
        'user_name' => $data['user_name'],
        'check_in' => $data['check_in'],
        'check_out' => $data['check_out'],
        'work_hours' => $data['work_hours'],
        'status' => $data['status'], // present_ontime, present_late, absent
        'notes' => json_encode([...]) // info mapping
    ]
);
```

**E. Bulk Auto-Sync**
```php
// Setelah upload, sync semua employee yang baru
// Update employee_id yang masih NULL
```

#### **Step 6: Hasil Upload**
```
✅ Success: Data tersimpan di tabel attendances
❌ Failed: Error ditampilkan per baris
📊 Summary: Jumlah success dan failed
```

---

### **METODE 2: Sync Otomatis via SOAP (Otomatis)**

#### **Step 1: Karyawan Tap di Mesin**
```
👤 Karyawan → 📱 Tap Fingerprint/Kartu → 💾 Mesin Solution X304
```
- Sama seperti metode 1

#### **Step 2: Sistem Sync Otomatis**
```
⏰ Cron Job (setiap 15 menit) → 🔌 SOAP Web Service → 💾 Mesin X304
```
- Sistem menjalankan cron job setiap 15 menit
- Mengambil data absensi dari mesin via **SOAP Web Service**
- IP Mesin: `10.10.10.85`
- Port: `80`

#### **Step 3: Pull Data dari Mesin**
```php
// app/Services/AttendanceMachineService.php
1. Koneksi ke mesin via SOAP
2. Pull attendance logs dari mesin
3. Simpan ke tabel attendance_logs
```

**Struktur Data dari Mesin:**
```php
[
    'user_pin' => '20111201',        // PIN/NIK dari mesin
    'datetime' => '2025-01-26 07:25:00', // Waktu tap
    'verified_method' => 'fingerprint',  // card/fingerprint/face
    'status_code' => 'check_in',         // check_in/check_out
    'raw_data' => '...'                  // Data mentah
]
```

#### **Step 4: Simpan ke attendance_logs**
```php
AttendanceLog::create([
    'attendance_machine_id' => 1,
    'user_pin' => '20111201',
    'datetime' => '2025-01-26 07:25:00',
    'verified_method' => 'fingerprint',
    'status_code' => 'check_in',
    'is_processed' => false, // Belum diproses
    'raw_data' => '...'
]);
```

#### **Step 5: Processing Logs ke Attendances**
```php
// app/Services/AttendanceProcessingService.php
1. Ambil logs yang belum diproses (is_processed = false)
2. Group by user_pin dan date
3. Untuk setiap user per hari:
   - Ambil tap pertama = check_in
   - Ambil tap terakhir = check_out
   - Hitung work_hours, overtime, late_minutes
   - Tentukan status (present_ontime, present_late, absent)
4. Simpan ke tabel attendances
5. Mark logs as processed (is_processed = true)
```

**Logika Check-in/Check-out:**
```php
// Tap pertama hari itu = Check-in
// Tap terakhir hari itu = Check-out
// Multiple tap = Semua direcord, tapi hanya pertama & terakhir yang dipakai
// Minimum gap = 1 menit (untuk bedakan check-in dan check-out)
```

---

## 🔄 LOGIKA PENENTUAN STATUS

### **Status Kehadiran:**

1. **`present_ontime`** - Hadir tepat waktu
   - Ada check_in
   - check_in <= 07:30:00

2. **`present_late`** - Hadir terlambat
   - Ada check_in
   - check_in > 07:30:00

3. **`absent`** - Tidak hadir
   - Tidak ada check_in sama sekali
   - ATAU kolom absent = 'Y'

4. **`on_leave`** - Cuti
   - Ada cuti yang disetujui di leave_requests
   - Status cuti = approved
   - Tanggal cuti overlap dengan tanggal absensi

5. **`sick_leave`** - Cuti sakit
   - Sama seperti on_leave, tapi leave_type = 'sick'

6. **`permission`** - Izin
   - Izin khusus (bukan cuti)

### **Perhitungan Otomatis:**

```php
// 1. Work Hours (Jam Kerja)
work_hours = (check_out - check_in) - 1 jam (lunch break jika > 4 jam)

// 2. Overtime Hours (Jam Lembur)
if (check_out > 16:30:00) {
    overtime_hours = (check_out - 16:30:00)
}

// 3. Late Minutes (Menit Terlambat)
if (check_in > 07:30:00) {
    late_minutes = (check_in - 07:30:00) dalam menit
}

// 4. Early Leave Minutes (Pulang Cepat)
if (check_out < 16:30:00) {
    early_leave_minutes = (16:30:00 - check_out) dalam menit
}
```

---

## 📊 STRUKTUR DATABASE

### **Tabel `attendances` (Data Final)**
```sql
id                  BIGINT PRIMARY KEY
employee_id         BIGINT FK (nullable, bisa NULL jika belum di-mapping)
card_number         VARCHAR(20) -- No. ID dari mesin
user_name           VARCHAR(255) -- Nama dari mesin
date                DATE -- Tanggal absensi
check_in            TIME -- Waktu tap pertama
check_out           TIME -- Waktu tap terakhir
status              ENUM('present_ontime','present_late','absent','on_leave','sick_leave','permission')
work_hours          DECIMAL(5,2) -- Total jam kerja
overtime_hours      DECIMAL(5,2) DEFAULT 0 -- Jam lembur
late_minutes        INT DEFAULT 0 -- Menit terlambat
early_leave_minutes INT DEFAULT 0 -- Menit pulang cepat
total_taps          INT DEFAULT 0 -- Total tap dalam sehari
notes               TEXT -- JSON info mapping
UNIQUE(employee_id, date) atau UNIQUE(card_number, date)
```

### **Tabel `attendance_logs` (Data Mentah dari Mesin)**
```sql
id                      BIGINT PRIMARY KEY
attendance_machine_id   BIGINT FK
employee_id             BIGINT FK (nullable)
user_pin                VARCHAR(20) -- PIN dari mesin
datetime                TIMESTAMP -- Waktu tap
verified_method         ENUM('card','fingerprint','face','password')
status_code             ENUM('check_in','check_out','break_out','break_in','overtime_in','overtime_out')
is_processed            BOOLEAN DEFAULT false -- Sudah diproses?
raw_data                TEXT -- Data mentah dari mesin
```

### **Tabel `attendance_machines` (Konfigurasi Mesin)**
```sql
id              BIGINT PRIMARY KEY
name            VARCHAR(100) -- Nama mesin
ip_address      VARCHAR(15) UNIQUE -- IP mesin (10.10.10.85)
port            INT DEFAULT 80 -- Port SOAP
status          ENUM('active','inactive','maintenance')
last_sync_at    TIMESTAMP -- Waktu sync terakhir
```

---

## 🔧 FITUR-FITUR SISTEM

### **1. Auto-Mapping Employee**
- Sistem otomatis mencocokkan nama dari file TXT dengan nama di database
- Jika tidak ditemukan, coba dengan card_number
- Log mapping disimpan di notes (JSON)

### **2. Bulk Auto-Sync**
- Setelah upload TXT, sistem otomatis sync employee_id yang masih NULL
- Mencari berdasarkan nama atau card_number

### **3. Integrasi dengan Cuti**
- Sistem otomatis cek leave_requests yang approved
- Jika ada cuti di tanggal tersebut, status = on_leave atau sick_leave

### **4. Recalculate Attendance**
- Bisa recalculate ulang attendance untuk tanggal tertentu
- Berguna jika ada perubahan jam kerja atau status cuti

### **5. Export Data**
- Export data absensi ke Excel/PDF
- Filter berdasarkan tanggal, employee, status

---

## 📱 API ENDPOINTS

### **Upload File TXT**
```http
POST /api/attendance/upload-txt
Content-Type: multipart/form-data

txt_file: [file]
```

**Response:**
```json
{
    "success": true,
    "message": "File berhasil diupload",
    "data": {
        "success": 25,
        "failed": 2,
        "errors": [...]
    }
}
```

### **Preview File TXT**
```http
POST /api/attendance/preview-txt
Content-Type: multipart/form-data

txt_file: [file]
```

**Response:**
```json
{
    "success": true,
    "preview": [
        {
            "card_number": "20111201",
            "user_name": "Steven Albert Reynold M",
            "date": "2025-07-07",
            "check_in": "14:22:00",
            "check_out": "19:29:00",
            "work_hours": 5.1,
            "status": "present_late"
        },
        ...
    ]
}
```

### **Sync dari Mesin**
```http
POST /api/attendance/sync
```

**Response:**
```json
{
    "success": true,
    "message": "Sync berhasil",
    "data": {
        "pull_result": {
            "success": true,
            "message": "Berhasil memproses 25 data absensi"
        },
        "process_result": {
            "success": true,
            "processed": 15
        }
    }
}
```

### **List Attendance**
```http
GET /api/attendance/list?date=2025-01-26&employee_id=1&status=present_late
```

---

## ⚙️ KONFIGURASI

### **Jam Kerja**
- **Jam Masuk**: 07:30 WIB
- **Jam Pulang**: 16:30 WIB
- **Lunch Break**: 1 jam (jika work_hours > 4 jam)

### **Mesin Solution X304**
- **IP Address**: 10.10.10.85
- **Port**: 80
- **Protocol**: SOAP Web Service
- **Kapasitas**: 6.000 user, 100.000 transaksi

### **Format File TXT**
- **Encoding**: UTF-8 atau ANSI
- **Format**: Fixed Width
- **Header**: Baris pertama
- **Data**: Baris kedua dan seterusnya

---

## 🐛 TROUBLESHOOTING

### **1. Employee ID NULL setelah upload**
**Penyebab:** Nama di file TXT tidak cocok dengan nama di database
**Solusi:**
- Cek mapping di notes (JSON)
- Update nama di database agar sesuai
- Atau update card_number di employees

### **2. Status salah (misal: absent padahal ada check_in)**
**Penyebab:** Logika penentuan status atau integrasi cuti
**Solusi:**
- Recalculate attendance untuk tanggal tersebut
- Cek leave_requests yang approved

### **3. File TXT tidak bisa di-parse**
**Penyebab:** Format file tidak sesuai fixed width
**Solusi:**
- Pastikan header sesuai format
- Pastikan setiap baris minimal 103 karakter
- Cek encoding file (harus UTF-8 atau ANSI)

### **4. Sync dari mesin gagal**
**Penyebab:** Koneksi ke mesin terputus atau mesin mati
**Solusi:**
- Cek IP address dan port mesin
- Cek firewall tidak memblokir port 80
- Test ping ke IP mesin
- Cek log di attendance_sync_logs

---

## 📝 CATATAN PENTING

1. **Data di `attendance_logs` adalah data mentah** dari mesin, belum diproses
2. **Data di `attendances` adalah data final** yang sudah diproses dan dihitung
3. **Mapping employee_id bisa NULL** jika nama tidak ditemukan (tapi data tetap tersimpan)
4. **Bulk auto-sync** berjalan otomatis setelah upload TXT
5. **Status cuti** otomatis terintegrasi dengan leave_requests
6. **Multiple tap** dalam sehari = semua direcord, tapi hanya pertama & terakhir yang dipakai

---

**Dokumen ini dibuat berdasarkan analisis kode dan dokumentasi sistem absensi Hope Channel Indonesia.**








