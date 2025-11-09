# Custom Role Enum Synchronization Solution

## Problem
Ketika membuat custom role baru, enum values di tabel `users` (kolom `role`) dan `employees` (kolom `jabatan_saat_ini`) tidak otomatis terupdate. Hal ini menyebabkan error 500 saat mencoba mengupdate employee dengan role yang baru dibuat karena role tersebut tidak ada dalam enum constraint database.

## Solution
Dibuat `DatabaseEnumService` yang secara otomatis mengupdate enum values di kedua tabel setiap kali ada perubahan pada custom roles.

### Files Created/Modified

#### 1. `app/Services/DatabaseEnumService.php` (NEW)
- Service untuk mengelola enum values di database
- Method `updateRoleEnums()` untuk mengupdate enum values
- Method `getAllAvailableRoles()` untuk mendapatkan semua role yang tersedia
- Method `roleExistsInEnum()` untuk mengecek apakah role ada dalam enum

#### 2. `app/Http/Controllers/CustomRoleController.php` (MODIFIED)
- Ditambahkan import `DatabaseEnumService`
- Ditambahkan call `DatabaseEnumService::updateRoleEnums()` di:
  - `store()` method - setelah membuat custom role baru
  - `update()` method - setelah mengupdate custom role
  - `destroy()` method - setelah menonaktifkan custom role

#### 3. `app/Services/RoleHierarchyService.php` (MODIFIED)
- Method `getAllAvailableRoles()` sekarang menggunakan `DatabaseEnumService::getAllAvailableRoles()` untuk konsistensi

### How It Works

1. **Standard Roles**: Role-role standar yang selalu ada didefinisikan dalam `DatabaseEnumService::$standardRoles`

2. **Custom Roles**: Diambil dari tabel `custom_roles` dengan kondisi `is_active = true`

3. **Automatic Sync**: Setiap kali ada perubahan pada custom roles (create, update, deactivate), enum values di database otomatis terupdate

4. **Database Update**: Menggunakan raw SQL `ALTER TABLE` statement untuk mengupdate enum values:
   ```sql
   ALTER TABLE users MODIFY COLUMN role ENUM('role1', 'role2', ...) DEFAULT 'Employee'
   ALTER TABLE employees MODIFY COLUMN jabatan_saat_ini ENUM('role1', 'role2', ...) DEFAULT 'Employee'
   ```

### Benefits

✅ **No More 500 Errors**: Employee dapat diupdate dengan custom role yang baru dibuat

✅ **Automatic Sync**: Tidak perlu manual intervention untuk mengupdate enum values

✅ **Consistent Validation**: Semua bagian aplikasi menggunakan source yang sama untuk role validation

✅ **Real-time Updates**: Enum values langsung terupdate setelah custom role dibuat/diupdate

✅ **Backward Compatible**: Tidak mengubah existing functionality

### Testing

Telah ditest dengan:
- Membuat custom role baru
- Mengupdate employee dengan custom role
- Memverifikasi enum values di database
- Memastikan validation berfungsi dengan benar

### Usage Example

```php
// Setelah membuat custom role baru
$customRole = CustomRole::create([...]);
DatabaseEnumService::updateRoleEnums(); // Otomatis dipanggil di controller

// Sekarang employee bisa diupdate dengan custom role tersebut
$employee->jabatan_saat_ini = $customRole->role_name;
$employee->save(); // ✅ Berhasil, tidak ada error 500
```

### Notes

- Service ini menggunakan Laravel's DB facade untuk raw SQL queries
- Logging ditambahkan untuk monitoring
- Error handling untuk memastikan aplikasi tidak crash jika ada masalah
- Enum values di-sort alphabetically untuk konsistensi