# Panduan Update Kalender Nasional Tahun-Tahun Berikutnya

## Overview

Sistem kalender nasional sudah dirancang untuk mendukung pergantian tahun secara otomatis. Sistem ini memisahkan antara:

1. **Hari Libur Tetap** - Tanggal yang sama setiap tahun (Tahun Baru, Hari Buruh, Hari Kemerdekaan, Natal)
2. **Hari Libur Variabel** - Tanggal yang berubah setiap tahun (Idul Fitri, Idul Adha, Imlek, dll)

## Fitur yang Tersedia

### 1. API Endpoints Baru

#### Mendapatkan Tahun yang Tersedia
```bash
GET /api/calendar/years
```
Response:
```json
{
    "success": true,
    "data": [2026, 2025, 2024, 2023]
}
```

#### Ringkasan Tahunan
```bash
GET /api/calendar/yearly-summary?year=2025
```
Response:
```json
{
    "success": true,
    "data": {
        "year": 2025,
        "summary": {
            "total_holidays": 15,
            "national_holidays": 13,
            "custom_holidays": 2,
            "weekend_holidays": 0,
            "holidays_by_month": {
                "1": {"count": 2, "holidays": [...]},
                "2": {"count": 1, "holidays": [...]},
                // ... dst
            }
        }
    }
}
```

#### Hari Libur Tahunan
```bash
GET /api/calendar/yearly-holidays?year=2025
```

#### Bulk Seed Multiple Tahun (HR Only)
```bash
POST /api/calendar/bulk-seed
Content-Type: application/json

{
    "years": [2025, 2026, 2027]
}
```

### 2. Command Artisan

#### Update Tahun Berikutnya
```bash
php artisan calendar:update-holidays
```

#### Update Tahun Tertentu
```bash
php artisan calendar:update-holidays --year=2025
```

#### Update Multiple Tahun
```bash
php artisan calendar:update-holidays --years=2025 --years=2026 --years=2027
```

#### Force Update (Tanpa Konfirmasi)
```bash
php artisan calendar:update-holidays --year=2025 --force
```

## Cara Update Kalender untuk Tahun Baru

### Langkah 1: Update Data Hari Libur Variabel

Edit file `app/Models/NationalHoliday.php` pada method `seedNationalHolidays()`:

```php
$variableHolidays = [
    // Data 2024 (sudah ada)
    '2024' => [
        '02-08' => 'Isra Mikraj Nabi Muhammad SAW',
        '02-10' => 'Tahun Baru Imlek 2575',
        // ... dst
    ],
    
    // Update data 2025 dengan tanggal resmi
    '2025' => [
        '01-28' => 'Tahun Baru Imlek 2576',
        '03-01' => 'Hari Suci Nyepi',
        '03-30' => 'Isra Mikraj Nabi Muhammad SAW',
        '03-31' => 'Hari Raya Idul Fitri',
        '04-01' => 'Hari Raya Idul Fitri',
        '05-03' => 'Hari Raya Waisak',
        '06-07' => 'Hari Raya Idul Adha',
        '07-09' => 'Tahun Baru Islam 1447 Hijriyah',
        '09-18' => 'Maulid Nabi Muhammad SAW',
    ],
    
    // Tambahkan data 2026
    '2026' => [
        '02-17' => 'Tahun Baru Imlek 2577',
        '03-21' => 'Hari Suci Nyepi',
        // ... dst dengan tanggal resmi
    ]
];
```

### Langkah 2: Jalankan Command Update

```bash
# Update tahun 2025
php artisan calendar:update-holidays --year=2025

# Update multiple tahun
php artisan calendar:update-holidays --years=2025 --years=2026

# Update tahun berikutnya secara otomatis
php artisan calendar:update-holidays
```

### Langkah 3: Verifikasi Hasil

```bash
# Cek tahun yang tersedia
php artisan tinker
>>> App\Models\NationalHoliday::getAvailableYears();

# Cek hari libur tahun 2025
>>> App\Models\NationalHoliday::byYear(2025)->get();
```

## Sumber Data Hari Libur Nasional

Untuk mendapatkan tanggal resmi hari libur nasional, gunakan sumber berikut:

1. **Keputusan Presiden (Keppres)** - Diterbitkan setiap tahun
2. **Kementerian Agama** - Untuk hari raya keagamaan
3. **Situs Resmi Pemerintah** - www.setneg.go.id

## Contoh Update untuk Tahun 2025

### Data Hari Libur 2025 (Perlu Update dengan Data Resmi)

```php
'2025' => [
    '01-28' => 'Tahun Baru Imlek 2576',
    '03-01' => 'Hari Suci Nyepi',
    '03-30' => 'Isra Mikraj Nabi Muhammad SAW',
    '03-31' => 'Hari Raya Idul Fitri',
    '04-01' => 'Hari Raya Idul Fitri',
    '05-03' => 'Hari Raya Waisak',
    '06-07' => 'Hari Raya Idul Adha',
    '07-09' => 'Tahun Baru Islam 1447 Hijriyah',
    '09-18' => 'Maulid Nabi Muhammad SAW',
]
```

### Jalankan Update

```bash
php artisan calendar:update-holidays --year=2025
```

## Monitoring dan Maintenance

### 1. Cek Status Kalender

```bash
# Cek tahun yang tersedia
curl -X GET "http://localhost:8000/api/calendar/years" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Cek ringkasan tahun 2025
curl -X GET "http://localhost:8000/api/calendar/yearly-summary?year=2025" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Backup Data

```bash
# Export data hari libur
php artisan tinker
>>> $holidays = App\Models\NationalHoliday::all();
>>> file_put_contents('holidays_backup.json', $holidays->toJson());
```

### 3. Restore Data

```bash
php artisan tinker
>>> $data = json_decode(file_get_contents('holidays_backup.json'), true);
>>> foreach($data as $holiday) { App\Models\NationalHoliday::updateOrCreate(['date' => $holiday['date']], $holiday); }
```

## Troubleshooting

### Error: Tahun Sudah Ada
```bash
# Gunakan --force untuk menimpa
php artisan calendar:update-holidays --year=2025 --force
```

### Error: Data Tidak Lengkap
1. Pastikan data hari libur variabel sudah diupdate di model
2. Jalankan ulang command update
3. Cek log error di `storage/logs/laravel.log`

### Error: API Tidak Berfungsi
1. Pastikan migration sudah dijalankan
2. Cek apakah tabel `national_holidays` sudah ada
3. Jalankan `php artisan migrate:status`

## Best Practices

1. **Update Data Sebelum Tahun Baru** - Update data hari libur variabel minimal 1 bulan sebelum tahun baru
2. **Backup Data** - Selalu backup data sebelum melakukan update besar
3. **Test di Environment Development** - Test update di development sebelum production
4. **Dokumentasi Perubahan** - Catat perubahan tanggal hari libur untuk audit trail
5. **Notifikasi Tim** - Beritahu tim HR tentang update kalender

## Otomatisasi (Opsional)

Untuk otomatisasi update tahunan, bisa dibuat:

1. **Cron Job** - Update otomatis setiap Desember
2. **Scheduled Command** - Laravel scheduler untuk update berkala
3. **Webhook** - Trigger update dari sistem eksternal

Contoh Scheduled Command:
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Update kalender tahun berikutnya setiap 1 Desember jam 00:00
    $schedule->command('calendar:update-holidays')
             ->monthlyOn(1, '00:00')
             ->when(function () {
                 return now()->month === 12;
             });
}
``` 