# 🔄 Panduan Migrasi ke Role Constants

Panduan ini membantu Anda memigrasikan semua hard-coded role names ke menggunakan `Role` constants.

---

## 📋 Langkah-langkah Migrasi

### 1. Install Role Constants

Role constants sudah dibuat di `app/Constants/Role.php`. Pastikan file ini sudah ada.

### 2. Cari Hard-coded Role Names

Jalankan script untuk menemukan semua hard-coded role names:

```bash
php scripts/find-hardcoded-roles.php
```

Script ini akan menampilkan:
- File yang mengandung hard-coded role names
- Baris yang perlu diperbaiki
- Jenis masalah (hard-coded, variation, atau in_array)

### 3. Refactor File per File

#### Contoh 1: Mengganti Hard-coded String

**Sebelum:**
```php
if ($user->role === 'Producer') {
    // ...
}
```

**Sesudah:**
```php
use App\Constants\Role;

if ($user->role === Role::PRODUCER) {
    // ...
}
```

#### Contoh 2: Mengganti in_array dengan Role Strings

**Sebelum:**
```php
if (in_array($user->role, ['Manager', 'Program Manager'])) {
    // ...
}
```

**Sesudah:**
```php
use App\Constants\Role;

if (Role::canApproveProgram($user->role)) {
    // ...
}
```

#### Contoh 3: Mengganti Query dengan Role

**Sebelum:**
```php
User::where('role', 'Production')->get();
```

**Sesudah:**
```php
use App\Constants\Role;

User::where('role', Role::PRODUCTION)->get();
```

#### Contoh 4: Mengganti Array of Roles

**Sebelum:**
```php
$notifyUsers = ['Manager', 'Program Manager'];
```

**Sesudah:**
```php
use App\Constants\Role;

$notifyUsers = Role::getManagerRoles();
```

### 4. Handle Variasi Penulisan

Jika ada variasi penulisan (misalnya 'Manager Program' vs 'Program Manager'), gunakan `Role::normalize()`:

**Sebelum:**
```php
if ($user->role === 'Manager Program' || $user->role === 'Program Manager') {
    // ...
}
```

**Sesudah:**
```php
use App\Constants\Role;

if (Role::equals($user->role, Role::PROGRAM_MANAGER)) {
    // ...
}

// Atau lebih sederhana:
if (Role::isManager($user->role)) {
    // ...
}
```

### 5. Test Setelah Refactoring

Setelah refactoring, pastikan:
- [ ] Semua test masih pass
- [ ] Tidak ada error di log
- [ ] Fitur masih berfungsi dengan benar

---

## 🎯 Prioritas File untuk Direfactor

### Prioritas Tinggi (Sering Digunakan)

1. **ApprovalWorkflowController.php** ✅ (Sudah direfactor)
2. **ProducerController.php** - Banyak hard-coded 'Producer'
3. **ManagerProgramController.php** - Banyak variasi 'Manager Program'
4. **QualityControlController.php** - Hard-coded 'Quality Control'
5. **BroadcastingController.php** - Hard-coded 'Broadcasting'

### Prioritas Sedang

6. **EditorController.php**
7. **ProductionController.php**
8. **CreativeController.php**
9. **SoundEngineerController.php**
10. **MusicArrangerController.php**

### Prioritas Rendah

11. File-file lainnya yang menggunakan role checking

---

## 📝 Checklist Migrasi

Untuk setiap file yang direfactor:

- [ ] Import `use App\Constants\Role;`
- [ ] Ganti semua hard-coded role strings dengan `Role::CONSTANT`
- [ ] Ganti `in_array($user->role, [...])` dengan helper methods jika tersedia
- [ ] Ganti array of roles dengan `Role::get*Roles()` methods
- [ ] Test fitur yang menggunakan role checking
- [ ] Commit dengan message yang jelas

---

## 🔍 Pattern Matching

### Pattern yang Perlu Diganti

1. **String Comparison**
   ```php
   // Pattern: $user->role === 'RoleName'
   // Replace: $user->role === Role::ROLE_NAME
   ```

2. **in_array with Role Strings**
   ```php
   // Pattern: in_array($user->role, ['Role1', 'Role2'])
   // Replace: Role::helperMethod($user->role) atau Role::inArray($user->role, [Role::ROLE1, Role::ROLE2])
   ```

3. **Query with Role**
   ```php
   // Pattern: User::where('role', 'RoleName')
   // Replace: User::where('role', Role::ROLE_NAME)
   ```

4. **Array of Roles**
   ```php
   // Pattern: $roles = ['Role1', 'Role2']
   // Replace: $roles = Role::get*Roles() atau [Role::ROLE1, Role::ROLE2]
   ```

---

## ⚠️ Catatan Penting

1. **Jangan** menghapus variasi penulisan yang sudah ada di database
   - Gunakan `Role::normalize()` untuk handle variasi
   - Database mungkin masih menyimpan 'Manager Program' meskipun standard-nya 'Program Manager'

2. **Test** setelah setiap refactoring
   - Jangan refactor semua file sekaligus
   - Test satu file dulu sebelum lanjut ke file berikutnya

3. **Backup** sebelum refactoring
   - Commit perubahan sebelum refactoring
   - Buat branch baru untuk refactoring

4. **Custom Roles**
   - Jika ada custom roles yang tidak ada di constants, tambahkan ke `Role::getAllStandardRoles()`
   - Atau gunakan `Role::normalize()` untuk handle custom roles

---

## 🆘 Troubleshooting

### Error: "Class 'App\Constants\Role' not found"
- Pastikan file `app/Constants/Role.php` ada
- Run `composer dump-autoload`

### Error: "Undefined constant Role::PRODUCER"
- Pastikan constant sudah didefinisikan di `Role.php`
- Cek case sensitivity (Role::PRODUCER, bukan Role::producer)

### Role tidak terdeteksi setelah normalize
- Cek apakah role ada di mapping `Role::normalize()`
- Jika custom role, tambahkan ke mapping atau gunakan role as-is

---

## 📚 Referensi

- **ROLE_NAMES_REFERENCE.md** - Daftar lengkap role names yang valid
- **app/Constants/Role.php** - File constants dan helper methods
- **scripts/find-hardcoded-roles.php** - Script untuk menemukan hard-coded roles

---

**Terakhir Diupdate**: 2025-01-27

