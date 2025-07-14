# 🔧 Perbaikan Akses HR ke Calendar API

## 🚨 Masalah yang Ditemukan

User HR tidak bisa menambah hari libur karena error 403 Forbidden dengan pesan:
```
Access denied. Required roles: hr, hr_manager
```

## 🔍 Analisis Masalah

Setelah investigasi, ditemukan **inkonsistensi penamaan role** antara:

### 1. **Migration (Database Schema)**
```php
// database/migrations/2025_01_22_000001_update_roles_add_managers.php
$table->enum('role', [
    'HR', 'Program Manager', 'Distribution Manager', 'GA',  // ← HR dengan huruf besar
    'Finance', 'General Affairs', 'Office Assistant',
    'Producer', 'Creative', 'Production', 'Editor',
    'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
    'Employee'
]);
```

### 2. **Controller & Routes (Backend Logic)**
```php
// app/Http/Controllers/NationalHolidayController.php
if (!in_array(Auth::user()->role, ['hr', 'hr_manager'])) {  // ← hr dengan huruf kecil
    return response()->json([...], 403);
}

// routes/api.php
Route::middleware(['role:hr,hr_manager'])->group(function () {  // ← hr dengan huruf kecil
```

## ✅ Solusi yang Diterapkan

### 1. **Perbaikan Controller**
Mengubah semua pengecekan role di `NationalHolidayController.php`:

```php
// SEBELUM
if (!in_array(Auth::user()->role, ['hr', 'hr_manager'])) {

// SESUDAH  
if (!in_array(Auth::user()->role, ['HR'])) {
```

**Method yang diperbaiki:**
- `store()` - Tambah hari libur
- `update()` - Edit hari libur  
- `destroy()` - Hapus hari libur
- `seedHolidays()` - Seed hari libur nasional
- `bulkSeedYears()` - Bulk seed multiple tahun
- `createRecurringHoliday()` - Buat hari libur berulang
- `createMonthlyHoliday()` - Buat hari libur bulanan
- `createDateRangeHoliday()` - Buat hari libur rentang tanggal

### 2. **Perbaikan Routes**
Mengubah middleware role di `routes/api.php`:

```php
// SEBELUM
Route::middleware(['role:hr,hr_manager'])->group(function () {

// SESUDAH
Route::middleware(['role:HR'])->group(function () {
```

## 🧪 Testing

### Script Test
Buat file `test_calendar_hr_access.php` untuk memverifikasi perbaikan:

```bash
php test_calendar_hr_access.php
```

### Expected Results
- ✅ Status 200 untuk GET `/api/calendar/data`
- ✅ Status 201 untuk POST `/api/calendar` (HR only)
- ✅ Status 200 untuk GET `/api/calendar/check`

## 📋 Checklist Verifikasi

- [x] Role HR di database: `'HR'` (huruf besar)
- [x] Controller check: `['HR']` (huruf besar)
- [x] Routes middleware: `['role:HR']` (huruf besar)
- [x] User HR memiliki role: `'HR'` di database
- [x] Token valid dan tidak expired
- [x] Frontend mengirim Authorization header dengan benar

## 🚀 Cara Test Manual

### 1. **Cek Role User HR**
```sql
SELECT id, name, email, role FROM users WHERE role = 'HR';
```

### 2. **Test dengan cURL**
```bash
# Login sebagai HR dan dapatkan token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"hr@company.com","password":"password"}'

# Test tambah hari libur
curl -X POST http://localhost:8000/api/calendar \
  -H "Authorization: Bearer YOUR_HR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-25",
    "name": "Libur Natal Test",
    "description": "Libur Natal untuk testing",
    "type": "custom"
  }'
```

### 3. **Test di Frontend**
1. Login sebagai HR
2. Buka halaman calendar
3. Coba tambah hari libur
4. Seharusnya tidak ada error 403

## 🔐 Security Notes

- Hanya user dengan role `'HR'` yang bisa manage hari libur
- Role `'hr'` atau `'hr_manager'` (huruf kecil) tidak akan bekerja
- Middleware `auth:sanctum` tetap diperlukan untuk semua endpoint
- Validasi input tetap berjalan untuk mencegah data invalid

## 📝 Catatan Penting

1. **Konsistensi Penamaan**: Selalu gunakan `'HR'` (huruf besar) untuk role HR
2. **Database Check**: Pastikan user HR memiliki role `'HR'` di database
3. **Token Refresh**: Jika masih error, coba logout dan login ulang
4. **Cache Clear**: Jika perlu, clear cache Laravel: `php artisan cache:clear`

## 🎉 Hasil Akhir

Setelah perbaikan ini, user HR akan bisa:
- ✅ Menambah hari libur baru
- ✅ Edit hari libur yang ada
- ✅ Hapus hari libur custom
- ✅ Seed hari libur nasional
- ✅ Buat hari libur berulang/bulanan/rentang tanggal

**Error 403 Forbidden sudah teratasi!** 🚀 