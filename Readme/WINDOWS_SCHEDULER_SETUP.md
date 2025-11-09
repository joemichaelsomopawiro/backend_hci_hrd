# 🪟 Windows Task Scheduler Setup - Sistem Absensi Solution X304

Panduan setup scheduled task di Windows untuk sistem absensi Solution X304.

## 🚀 **Setup Windows Task Scheduler**

### **1. Buka Task Scheduler**
- Press `Win + R`
- Ketik `taskschd.msc`
- Press Enter

### **2. Create Basic Task**
1. Klik **"Create Basic Task..."** di panel kanan
2. **Name**: `Laravel Attendance Scheduler`
3. **Description**: `Automated attendance sync and processing`

### **3. Trigger Setup**
1. **When do you want the task to start?**: Pilih **"Daily"**
2. **Start**: Pilih tanggal hari ini
3. **Recur every**: `1 days`

### **4. Action Setup**
1. **What action do you want to task to perform?**: Pilih **"Start a program"**
2. **Program/script**: 
   ```
   C:\xampp\php\php.exe
   ```
3. **Add arguments**: 
   ```
   artisan schedule:run
   ```
4. **Start in**: 
   ```
   C:\xampp\htdocs\backend_hci
   ```

### **5. Advanced Settings**
1. Centang **"Run whether user is logged on or not"**
2. Centang **"Run with highest privileges"**
3. **Configure for**: Windows 10

### **6. Repeat Settings**
1. Klik **"Properties"** pada task yang baru dibuat
2. Tab **"Triggers"** → **"Edit"**
3. Centang **"Repeat task every"**: `1 minutes`
4. **for a duration of**: `Indefinitely`

## ⚡ **Option 2: Batch Script + Task Scheduler**

### **1. Buat Batch File**
Buat file `attendance_scheduler.bat`:

```batch
@echo off
cd /d C:\xampp\htdocs\backend_hci
C:\xampp\php\php.exe artisan schedule:run
```

### **2. Setup di Task Scheduler**
- **Program/script**: `C:\xampp\htdocs\backend_hci\attendance_scheduler.bat`
- **Start in**: `C:\xampp\htdocs\backend_hci`

## 🔧 **Option 3: PowerShell Script**

### **1. Buat PowerShell Script**
Buat file `attendance_scheduler.ps1`:

```powershell
Set-Location "C:\xampp\htdocs\backend_hci"
& "C:\xampp\php\php.exe" artisan schedule:run
```

### **2. Setup di Task Scheduler**
- **Program/script**: `powershell.exe`
- **Add arguments**: `-File "C:\xampp\htdocs\backend_hci\attendance_scheduler.ps1"`

## 🐛 **Troubleshooting**

### **Problem: Task tidak jalan**
1. Check **"Task Scheduler Library"** → **"Laravel Attendance Scheduler"**
2. Lihat **"Last Run Result"** dan **"Last Run Time"**
3. Check **"History"** tab untuk error details

### **Problem: PHP tidak ditemukan**
```batch
# Ganti path PHP sesuai instalasi Anda
C:\xampp\php\php.exe
# atau
C:\php\php.exe
# atau cek dengan
where php
```

### **Problem: Permission denied**
1. Run Task Scheduler **as Administrator**
2. Set task untuk **"Run with highest privileges"**

## ✅ **Verifikasi Task Berjalan**

### **1. Check Task History**
- Buka Task Scheduler
- Pilih task → Tab **"History"**
- Lihat log execution

### **2. Check Laravel Log**
```bash
# Check di file log Laravel
tail -f storage/logs/laravel.log
```

### **3. Manual Test**
```bash
cd C:\xampp\htdocs\backend_hci
C:\xampp\php\php.exe artisan schedule:run
```

---

**✅ Scheduled task untuk sistem absensi berhasil disetup di Windows!** 