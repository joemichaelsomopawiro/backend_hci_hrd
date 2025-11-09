# Employee Role Synchronization System

## Overview
Sistem sinkronisasi otomatis antara kolom `jabatan_saat_ini` di tabel `employees` dan kolom `role` di tabel `users`. Setiap kali HR menambah atau mengedit data pegawai, role di tabel users akan otomatis mengikuti jabatan yang dipilih.

## Features

### 1. Enum Validation for Jabatan
- Kolom `jabatan_saat_ini` di tabel `employees` sekarang menggunakan enum dengan pilihan yang sama seperti `role` di tabel `users`
- Pilihan yang tersedia: `HR`, `Manager`, `Employee`, `GA`
- Default value: `Employee`

### 2. Automatic Role Synchronization

#### Saat Menambah Employee Baru (Store)
- Sistem akan mencari user yang namanya sama dengan `nama_lengkap` employee
- Jika ditemukan user yang belum terhubung dengan employee lain:
  - User akan dihubungkan dengan employee baru
  - Role user akan diupdate sesuai dengan `jabatan_saat_ini` yang dipilih

#### Saat Mengedit Employee (Update)
- **Jika nama berubah:**
  - User yang sudah terhubung akan diupdate nama dan role-nya
  - Atau mencari user baru dengan nama yang sesuai
  - User lama akan diputus hubungannya jika nama tidak cocok lagi

- **Jika nama tidak berubah:**
  - Role user yang terhubung akan tetap disinkronkan dengan `jabatan_saat_ini`
  - Memastikan role selalu up-to-date meskipun hanya jabatan yang berubah

## Database Changes

### Migration: `2025_01_23_000001_modify_jabatan_saat_ini_to_enum.php`
```php
Schema::table('employees', function (Blueprint $table) {
    $table->enum('jabatan_saat_ini', ['HR', 'Manager', 'Employee', 'GA'])
          ->default('Employee')
          ->change();
});
```

### Model Updates
- **Employee Model**: Added cast for `jabatan_saat_ini`
- **Validation**: Updated to use enum validation in EmployeeController

## Controller Changes

### EmployeeController Validation
```php
'jabatan_saat_ini' => 'required|in:HR,Manager,Employee,GA',
```

### Synchronization Logic

#### Store Method
```php
if ($matchingUser) {
    $matchingUser->update([
        'employee_id' => $employee->id,
        'role' => $validated['jabatan_saat_ini'] // Sinkronisasi role
    ]);
}
```

#### Update Method
```php
// Sinkronisasi nama dan role
$employee->user->update([
    'name' => $validated['nama_lengkap'],
    'role' => $validated['jabatan_saat_ini']
]);
```

## API Usage

### Creating Employee with Role Sync
```json
POST /api/employees
{
    "nama_lengkap": "John Doe",
    "jabatan_saat_ini": "Manager",
    // ... other fields
}
```

### Updating Employee with Role Sync
```json
PUT /api/employees/{id}
{
    "nama_lengkap": "John Doe",
    "jabatan_saat_ini": "HR",
    // ... other fields
}
```

## Response Format

### Successful Creation/Update
```json
{
    "message": "Data pegawai berhasil disimpan",
    "employee": {
        "id": 1,
        "nama_lengkap": "John Doe",
        "jabatan_saat_ini": "Manager",
        // ... other fields
    },
    "user_linked": true,
    "linked_user": {
        "id": 1,
        "name": "John Doe",
        "role": "Manager",
        "employee_id": 1
    }
}
```

## Benefits

1. **Data Consistency**: Role di users table selalu sinkron dengan jabatan di employees table
2. **Automatic Linking**: User dan employee otomatis terhubung berdasarkan nama
3. **Role-based Access Control**: Sistem permission dapat mengandalkan role yang akurat
4. **Audit Trail**: Log lengkap untuk setiap perubahan dan sinkronisasi
5. **Error Prevention**: Validasi enum mencegah input jabatan yang tidak valid

## Security Considerations

- Hanya HR yang dapat mengubah jabatan employee
- Validasi enum memastikan hanya role yang valid yang dapat dipilih
- Logging untuk audit trail setiap perubahan role
- Automatic disconnection dari user lama saat nama berubah

## Migration Instructions

1. Jalankan migration untuk mengubah kolom jabatan_saat_ini:
   ```bash
   php artisan migrate
   ```

2. Update existing data jika diperlukan:
   ```sql
   UPDATE employees SET jabatan_saat_ini = 'Employee' 
   WHERE jabatan_saat_ini NOT IN ('HR', 'Manager', 'Employee', 'GA');
   ```

3. Sinkronisasi manual untuk data existing (opsional):
   ```php
   // Script untuk sinkronisasi data existing
   $employees = Employee::with('user')->get();
   foreach ($employees as $employee) {
       if ($employee->user) {
           $employee->user->update(['role' => $employee->jabatan_saat_ini]);
       }
   }
   ```

## Troubleshooting

### Common Issues
1. **User tidak terhubung**: Pastikan nama di user dan employee sama persis
2. **Role tidak tersinkron**: Cek log untuk error sinkronisasi
3. **Validation error**: Pastikan jabatan_saat_ini menggunakan nilai enum yang valid

### Debugging
- Check logs di `storage/logs/laravel.log` untuk detail sinkronisasi
- Verify user-employee relationship di database
- Ensure enum values match between employees and users tables