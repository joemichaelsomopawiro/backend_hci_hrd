# 🗓️ PANDUAN QUICK START SISTEM KALENDER NASIONAL

## 🚀 Setup Cepat (5 Menit)

### 1. **Verifikasi Database**
```bash
# Cek struktur tabel
php artisan migrate:status

# Jika perlu, jalankan migration
php artisan migrate
```

### 2. **Seed Data Hari Libur**
```bash
# Masuk ke tinker
php artisan tinker

# Seed data untuk tahun 2024
>>> App\Models\NationalHoliday::seedNationalHolidays(2024);

# Seed data untuk tahun 2025
>>> App\Models\NationalHoliday::seedNationalHolidays(2025);

# Keluar dari tinker
>>> exit
```

### 3. **Test API Endpoints**
```bash
# Test endpoint data frontend
curl http://localhost:8000/api/calendar/data-frontend?year=2024&month=1

# Test endpoint check holiday
curl http://localhost:8000/api/calendar/check?date=2024-01-01
```

## 📱 Cara Penggunaan Frontend

### 1. **Akses Kalender**
- Buka dashboard HR
- Klik menu "Kalender Nasional"
- Kalender akan menampilkan hari libur bulan ini

### 2. **Tambah Hari Libur (HR Only)**
- Klik tombol "Tambah Hari Libur"
- Isi form:
  - **Tanggal**: Pilih tanggal (format Y-m-d)
  - **Nama**: Nama hari libur
  - **Deskripsi**: Keterangan (opsional)
  - **Tipe**: national/custom/weekend
- Klik "Simpan"

### 3. **Edit Hari Libur (HR Only)**
- Klik tombol edit (✏️) pada hari libur
- Ubah data yang diperlukan
- Klik "Update"

### 4. **Hapus Hari Libur (HR Only)**
- Klik tombol delete (🗑️) pada hari libur
- Konfirmasi penghapusan
- Hari libur akan dihapus

## 🔧 Konfigurasi Google Calendar

### 1. **Setup Google Calendar API**
```bash
# Tambahkan ke .env
GOOGLE_CALENDAR_ID=your_calendar_id@group.calendar.google.com
GOOGLE_API_KEY=your_api_key
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
```

### 2. **Test Koneksi Google Calendar**
```bash
# Test koneksi
curl -X GET http://localhost:8000/api/calendar/test-google-connection \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. **Sync dari Google Calendar**
```bash
# Sync data dari Google Calendar
curl -X POST http://localhost:8000/api/calendar/sync-google \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"year": 2024}'
```

## 🛠️ Troubleshooting Cepat

### ❌ **Kalender tidak muncul**
```bash
# Cek API endpoint
curl http://localhost:8000/api/calendar/data-frontend?year=2024&month=1

# Cek database
php artisan tinker
>>> App\Models\NationalHoliday::count();
```

### ❌ **Tombol edit/delete hilang**
- Refresh halaman (Ctrl+F5)
- Cek role user (harus HR)
- Cek console browser untuk error

### ❌ **Format tanggal salah**
```bash
# Cek struktur database
php artisan tinker
>>> $holiday = App\Models\NationalHoliday::first();
>>> echo $holiday->date->format('Y-m-d');
```

### ❌ **Google Calendar tidak sync**
```bash
# Clear cache
curl -X POST http://localhost:8000/api/calendar/clear-google-cache \
  -H "Authorization: Bearer YOUR_TOKEN"

# Cek credentials
php artisan tinker
>>> config('services.google.calendar_id');
```

## 📊 Monitoring

### 1. **Cek Data Hari Libur**
```bash
# Total hari libur
php artisan tinker
>>> App\Models\NationalHoliday::count();

# Hari libur per tahun
>>> App\Models\NationalHoliday::whereYear('date', 2024)->count();

# Hari libur per tipe
>>> App\Models\NationalHoliday::groupBy('type')->selectRaw('type, count(*) as total')->get();
```

### 2. **Cek API Performance**
```bash
# Test response time
time curl http://localhost:8000/api/calendar/data-frontend?year=2024&month=1
```

### 3. **Cek Cache Status**
```bash
# Clear semua cache
php artisan cache:clear

# Cek cache Google Calendar
php artisan tinker
>>> Cache::get('google_calendar_holidays_2024');
```

## 🔄 Maintenance Rutin

### 1. **Update Tahunan (Setiap Desember)**
```bash
# Seed data untuk tahun baru
php artisan tinker
>>> App\Models\NationalHoliday::seedNationalHolidays(2025);
```

### 2. **Backup Data**
```bash
# Backup tabel hari libur
mysqldump -u root -p hci_hrd national_holidays > holidays_backup_$(date +%Y%m%d).sql
```

### 3. **Clear Cache Berkala**
```bash
# Clear cache setiap minggu
php artisan cache:clear
```

## 🎯 Tips Penggunaan

### ✅ **Best Practices**
1. **Gunakan role HR** untuk manage hari libur
2. **Backup data** sebelum update besar
3. **Test API** sebelum deploy
4. **Monitor cache** untuk performance
5. **Update tahunan** tepat waktu

### ⚠️ **Yang Perlu Diperhatikan**
1. **Hari libur nasional** tidak bisa diedit/dihapus
2. **Format tanggal** harus Y-m-d
3. **Role access** ketat (HR only)
4. **Cache headers** untuk update realtime
5. **Google Calendar** perlu credentials valid

## 📞 Support

Jika mengalami masalah:

1. **Cek log Laravel**: `storage/logs/laravel.log`
2. **Cek console browser**: F12 → Console
3. **Test API manual**: Gunakan Postman/curl
4. **Jalankan testing**: `php test_calendar_comprehensive.php`

---

**🎉 Sistem kalender nasional siap digunakan!**

*Panduan ini dibuat untuk memudahkan penggunaan sistem kalender nasional yang telah diperbaiki dan dioptimalkan.* 