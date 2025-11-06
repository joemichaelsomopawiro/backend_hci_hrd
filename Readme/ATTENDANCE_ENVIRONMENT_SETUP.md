# 🔧 Environment Setup - Sistem Absensi Solution X304

Panduan lengkap untuk mengkonfigurasi environment variables yang diperlukan untuk sistem absensi Solution X304.

## 📋 **Daftar Environment Variables**

### **1. Machine Configuration**

```bash
# IP Address dan Port Mesin Solution X304
ATTENDANCE_MACHINE_IP=10.10.10.85
ATTENDANCE_MACHINE_PORT=80
ATTENDANCE_MACHINE_COMM_KEY=0
ATTENDANCE_MACHINE_TIMEOUT=10
```

**Penjelasan:**
- `ATTENDANCE_MACHINE_IP`: IP address mesin absensi Solution X304
- `ATTENDANCE_MACHINE_PORT`: Port komunikasi (default: 80 untuk HTTP)
- `ATTENDANCE_MACHINE_COMM_KEY`: Communication key untuk SOAP request (biasanya 0)
- `ATTENDANCE_MACHINE_TIMEOUT`: Timeout koneksi dalam detik

### **2. Work Schedule Configuration**

```bash
# Jadwal Kerja Standard
ATTENDANCE_WORK_START_TIME=07:30:00
ATTENDANCE_WORK_END_TIME=16:30:00
ATTENDANCE_LUNCH_BREAK_DURATION=60
ATTENDANCE_LATE_TOLERANCE_MINUTES=0
ATTENDANCE_OVERTIME_START_TIME=16:30:00
ATTENDANCE_MIN_WORK_HOURS=8
```

**Penjelasan:**
- `ATTENDANCE_WORK_START_TIME`: Jam mulai kerja (format HH:MM:SS)
- `ATTENDANCE_WORK_END_TIME`: Jam selesai kerja (format HH:MM:SS)
- `ATTENDANCE_LUNCH_BREAK_DURATION`: Durasi istirahat lunch dalam menit
- `ATTENDANCE_LATE_TOLERANCE_MINUTES`: Toleransi keterlambatan (0 = tidak ada toleransi)
- `ATTENDANCE_OVERTIME_START_TIME`: Jam mulai perhitungan lembur
- `ATTENDANCE_MIN_WORK_HOURS`: Minimum jam kerja per hari

### **3. Sync Configuration**

```bash
# Pengaturan Sinkronisasi Otomatis
ATTENDANCE_AUTO_SYNC_ENABLED=true
ATTENDANCE_SYNC_INTERVAL_MINUTES=15
ATTENDANCE_PROCESS_INTERVAL_MINUTES=60
ATTENDANCE_DAILY_SUMMARY_TIME=06:00
ATTENDANCE_DUPLICATE_DETECTION_MINUTES=1
ATTENDANCE_MAX_DAILY_TAPS=20

# Employee Management
ATTENDANCE_AUTO_CREATE_EMPLOYEE=false
```

**Penjelasan:**
- `ATTENDANCE_AUTO_SYNC_ENABLED`: Enable/disable auto sync (true/false)
- `ATTENDANCE_SYNC_INTERVAL_MINUTES`: Interval sync data dari mesin (menit)
- `ATTENDANCE_PROCESS_INTERVAL_MINUTES`: Interval proses attendance logs (menit)
- `ATTENDANCE_DAILY_SUMMARY_TIME`: Waktu generate summary harian (HH:MM)
- `ATTENDANCE_DUPLICATE_DETECTION_MINUTES`: Gap minimum antar tap untuk deteksi duplikat
- `ATTENDANCE_MAX_DAILY_TAPS`: Maksimum tap per hari (untuk deteksi anomali)
- `ATTENDANCE_AUTO_CREATE_EMPLOYEE`: Auto-create employee jika tidak ditemukan di database

### **4. Logging Configuration**

```bash
# Pengaturan Logging
ATTENDANCE_DEBUG_MODE=false
ATTENDANCE_LOG_LEVEL=info
ATTENDANCE_KEEP_LOGS_DAYS=30
```

**Penjelasan:**
- `ATTENDANCE_DEBUG_MODE`: Mode debug untuk development (true/false)
- `ATTENDANCE_LOG_LEVEL`: Level logging (debug, info, warning, error)
- `ATTENDANCE_KEEP_LOGS_DAYS`: Berapa hari log attendance disimpan

### **5. Notification Configuration**

```bash
# Pengaturan Notifikasi (Optional)
ATTENDANCE_NOTIFICATIONS_ENABLED=false
ATTENDANCE_NOTIFICATION_EMAIL=admin@company.com
ATTENDANCE_SLACK_WEBHOOK_URL=
```

**Penjelasan:**
- `ATTENDANCE_NOTIFICATIONS_ENABLED`: Enable notifikasi (true/false)
- `ATTENDANCE_NOTIFICATION_EMAIL`: Email untuk notifikasi sistem
- `ATTENDANCE_SLACK_WEBHOOK_URL`: Webhook URL untuk notifikasi Slack

## 🚀 **Setup Awal**

### **1. Copy Environment Template**

```bash
cp .env.example .env
```

### **2. Tambahkan Konfigurasi Attendance**

Tambahkan semua variabel di atas ke file `.env` Anda. Contoh lengkap:

```bash
# ============================================
# ATTENDANCE MACHINE CONFIGURATION
# ============================================

# Solution X304 Machine Settings
ATTENDANCE_MACHINE_IP=10.10.10.85
ATTENDANCE_MACHINE_PORT=80
ATTENDANCE_MACHINE_COMM_KEY=0
ATTENDANCE_MACHINE_TIMEOUT=10

# Work Schedule
ATTENDANCE_WORK_START_TIME=07:30:00
ATTENDANCE_WORK_END_TIME=16:30:00
ATTENDANCE_LUNCH_BREAK_DURATION=60
ATTENDANCE_LATE_TOLERANCE_MINUTES=0
ATTENDANCE_OVERTIME_START_TIME=16:30:00
ATTENDANCE_MIN_WORK_HOURS=8

# Sync Settings
ATTENDANCE_AUTO_SYNC_ENABLED=true
ATTENDANCE_SYNC_INTERVAL_MINUTES=15
ATTENDANCE_PROCESS_INTERVAL_MINUTES=60
ATTENDANCE_DAILY_SUMMARY_TIME=06:00
ATTENDANCE_DUPLICATE_DETECTION_MINUTES=1
ATTENDANCE_MAX_DAILY_TAPS=20

# Employee Management
ATTENDANCE_AUTO_CREATE_EMPLOYEE=false

# Logging
ATTENDANCE_DEBUG_MODE=false
ATTENDANCE_LOG_LEVEL=info
ATTENDANCE_KEEP_LOGS_DAYS=30

# Notifications (Optional)
ATTENDANCE_NOTIFICATIONS_ENABLED=false
ATTENDANCE_NOTIFICATION_EMAIL=admin@company.com
ATTENDANCE_SLACK_WEBHOOK_URL=
```

### **3. Clear Config Cache**

```bash
php artisan config:clear
php artisan cache:clear
```

## 🔍 **Verifikasi Konfigurasi**

### **1. Test Koneksi Mesin**

```bash
php artisan attendance:sync --force
```

### **2. Check Config Values**

```bash
php artisan tinker
>>> config('attendance.machine.default_ip')
>>> config('attendance.schedule.work_start_time')
```

### **3. Check Environment Values**

```bash
php artisan tinker
>>> env('ATTENDANCE_MACHINE_IP')
>>> env('ATTENDANCE_WORK_START_TIME')
```

## 🏢 **Konfigurasi untuk Environment Berbeda**

### **Development Environment**

```bash
ATTENDANCE_DEBUG_MODE=true
ATTENDANCE_LOG_LEVEL=debug
ATTENDANCE_AUTO_SYNC_ENABLED=false
ATTENDANCE_MACHINE_IP=127.0.0.1  # Local testing
```

### **Production Environment**

```bash
ATTENDANCE_DEBUG_MODE=false
ATTENDANCE_LOG_LEVEL=warning
ATTENDANCE_AUTO_SYNC_ENABLED=true
ATTENDANCE_MACHINE_IP=10.10.10.85  # Real machine IP
ATTENDANCE_NOTIFICATIONS_ENABLED=true
```

### **Testing Environment**

```bash
ATTENDANCE_DEBUG_MODE=true
ATTENDANCE_LOG_LEVEL=debug
ATTENDANCE_AUTO_SYNC_ENABLED=false
ATTENDANCE_SYNC_INTERVAL_MINUTES=1  # Fast testing
```

## ⚙️ **Customization Guide**

### **1. Mengubah Jam Kerja**

Untuk perusahaan dengan jam kerja 08:00-17:00:

```bash
ATTENDANCE_WORK_START_TIME=08:00:00
ATTENDANCE_WORK_END_TIME=17:00:00
ATTENDANCE_OVERTIME_START_TIME=17:00:00
```

### **2. Multiple Mesin Absensi**

Jika ada multiple mesin, buat konfigurasi terpisah:

```bash
ATTENDANCE_MACHINE_IP_MAIN=10.10.10.85
ATTENDANCE_MACHINE_IP_GATE=10.10.10.86
ATTENDANCE_MACHINE_IP_CANTEEN=10.10.10.87
```

### **3. Shift Kerja**

Untuk shift malam (misal 22:00-06:00):

```bash
ATTENDANCE_WORK_START_TIME=22:00:00
ATTENDANCE_WORK_END_TIME=06:00:00
ATTENDANCE_OVERTIME_START_TIME=06:00:00
```

## 🔧 **Troubleshooting**

### **Problem: Koneksi ke mesin gagal**

```bash
# Check network connectivity
ping 10.10.10.85

# Test dengan timeout lebih lama
ATTENDANCE_MACHINE_TIMEOUT=30
```

### **Problem: Sync terlalu lambat**

```bash
# Kurangi interval sync
ATTENDANCE_SYNC_INTERVAL_MINUTES=5
```

### **Problem: Terlalu banyak log**

```bash
# Kurangi retention log
ATTENDANCE_KEEP_LOGS_DAYS=7
ATTENDANCE_LOG_LEVEL=warning
```

### **Problem: Employee tidak ditemukan di database**

```bash
# Enable auto-create employee dari mesin
ATTENDANCE_AUTO_CREATE_EMPLOYEE=true

# Check log untuk employee yang auto-created
tail -f storage/logs/laravel.log | grep "Auto-created"
```

## 📚 **Best Practices**

1. **Backup konfigurasi** sebelum mengubah setting production
2. **Test di development** environment terlebih dahulu
3. **Monitor log** setelah mengubah konfigurasi
4. **Dokumentasikan** perubahan konfigurasi
5. **Set notification** untuk environment production

## 🔐 **Security Notes**

1. **Jangan commit** file `.env` ke repository
2. **Gunakan encryption** untuk komunikasi dengan mesin jika memungkinkan
3. **Set firewall rules** untuk membatasi akses ke mesin absensi
4. **Regular update** IP address jika ada perubahan network

---

**✅ Sistem absensi Solution X304 sekarang siap digunakan dengan konfigurasi yang fleksibel!** 