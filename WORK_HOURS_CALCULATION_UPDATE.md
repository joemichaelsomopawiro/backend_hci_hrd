# Update Perhitungan Jam Kerja - Mulai dari Jam 06:00

## Perubahan yang Dilakukan

Sistem absensi telah diupdate untuk menghitung jam kerja dimulai dari **jam 06:00 pagi**, bukan dari waktu check-in aktual jika check-in dilakukan sebelum jam 06:00.

## Logika Baru

### Sebelum Update:
- Jam kerja dihitung dari waktu check-in sampai check-out
- Contoh: Check-in jam 03:00, check-out jam 17:00 = 14 jam kerja

### Setelah Update:
- Jam kerja dimulai dihitung dari jam 06:00 pagi
- Jika check-in sebelum jam 06:00, maka waktu mulai kerja dianggap jam 06:00
- **TIDAK ADA pengurangan lunch break** - jam kerja murni dari jam 6 pagi sampai check-out
- Contoh: Check-in jam 03:00, check-out jam 17:00 = 11 jam kerja (06:00-17:00)

## Contoh Kasus

| Check-in | Check-out | Jam Kerja Lama | Jam Kerja Baru | Keterangan |
|----------|-----------|----------------|----------------|-----------|
| 03:00 | 17:00 | 13 jam | 11 jam | Mulai dihitung dari 06:00, tanpa pengurangan lunch break |
| 05:00 | 10:00 | 5 jam | 4 jam | Mulai dihitung dari 06:00, tanpa pengurangan lunch break |
| 07:00 | 16:00 | 8 jam | 9 jam | Tidak ada perubahan karena check-in setelah 06:00 |
| 06:00 | 15:00 | 8 jam | 9 jam | Tidak ada perubahan karena check-in tepat 06:00 |

## File yang Dimodifikasi

- `app/Models/Attendance.php` - Method `calculateWorkHours()`

## Testing

File test telah dibuat untuk memverifikasi perubahan:
- `test_work_hours_calculation.php`

Jalankan test dengan:
```bash
php test_work_hours_calculation.php
```

## Catatan Penting

1. Perubahan ini hanya mempengaruhi perhitungan jam kerja (`work_hours`)
2. Waktu check-in dan check-out tetap disimpan sesuai waktu aktual
3. **TIDAK ADA pengurangan lunch break** - jam kerja murni dari jam 6 pagi sampai check-out
4. Status kehadiran (terlambat/tepat waktu) tetap berdasarkan jam masuk kerja normal (07:30)

## Implementasi

Perubahan sudah aktif dan akan berlaku untuk semua perhitungan jam kerja baru maupun yang diproses ulang.