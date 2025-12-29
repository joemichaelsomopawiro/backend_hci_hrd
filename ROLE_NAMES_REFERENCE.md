# 📋 Referensi Nama Role yang Benar

**PENTING**: Gunakan constants dari `App\Constants\Role` untuk menghindari typo!

---

## ✅ Role Names yang Valid (Sesuai Hope Channel Indonesia)

**Total: 22 Role Standar + 1 Default Role**

### 📋 Daftar Role (Urutan sesuai sistem)

| No | Constant | Value | Keterangan |
|---|----------|-------|------------|
| 1 | `Role::HOPELINE_CARE` | `'Hopeline Care'` | Hope Line Care |
| 2 | `Role::PRODUCTION` | `'Production'` | Production (BUKAN 'production' atau 'produksi') |
| 3 | `Role::GRAPHIC_DESIGN` | `'Graphic Design'` | Graphic Design |
| 4 | `Role::EDITOR` | `'Editor'` | Editor (BUKAN 'editor') |
| 5 | `Role::HR` | `'HR'` | Human Resources |
| 6 | `Role::GENERAL_AFFAIRS` | `'General Affairs'` | General Affairs |
| 7 | `Role::PROMOTION` | `'Promotion'` | Promotion (BUKAN 'promosi' atau 'prmotion') |
| 8 | `Role::SOUND_ENGINEER` | `'Sound Engineer'` | Sound Engineer |
| 9 | `Role::ART_SET_PROPERTI` | `'Art & Set Properti'` | Art & Set Properti |
| 10 | `Role::PROGRAM_MANAGER` | `'Program Manager'` | Program Manager (BUKAN 'Manager Program') |
| 11 | `Role::OFFICE_ASSISTANT` | `'Office Assistant'` | Office Assistant |
| 12 | `Role::CREATIVE` | `'Creative'` | Creative |
| 13 | `Role::QUALITY_CONTROL` | `'Quality Control'` | Quality Control (BUKAN 'QC') |
| 14 | `Role::PRESIDENT_DIRECTOR` | `'President Director'` | President Director |
| 15 | `Role::EDITOR_PROMOTION` | `'Editor Promotion'` | Editor Promotion |
| 16 | `Role::DISTRIBUTION_MANAGER` | `'Distribution Manager'` | Distribution Manager |
| 17 | `Role::MUSIC_ARRANGER` | `'Music Arranger'` | Music Arranger |
| 18 | `Role::BROADCASTING` | `'Broadcasting'` | Broadcasting |
| 19 | `Role::PRODUCER` | `'Producer'` | Producer (BUKAN 'prodcer') |
| 20 | `Role::VP_PRESIDENT` | `'VP President'` | VP President (BUKAN 'vice presdent') |
| 21 | `Role::FINANCE` | `'Finance'` | Finance |
| 22 | `Role::SOCIAL_MEDIA` | `'Social Media'` | Social Media |

### 👤 Default Role
| Constant | Value | Keterangan |
|----------|-------|------------|
| `Role::EMPLOYEE` | `'Employee'` | Employee (default role untuk fallback) |

### 📝 Catatan Penting

1. **Custom Roles**: Sistem mendukung custom roles yang ditambahkan melalui sistem. Gunakan `DatabaseEnumService::getAllAvailableRoles()` untuk mendapatkan semua roles termasuk custom roles.

2. **Typo Handling**: Method `Role::normalize()` akan otomatis handle typo:
   - `'prmotion'` → `'Promotion'`
   - `'prodcer'` → `'Producer'`
   - `'vice presdent'` → `'VP President'`

---

## ❌ Variasi Penulisan yang SALAH (Jangan Gunakan!)

### Manager Program
- ❌ `'Manager Program'` → ✅ Gunakan `Role::PROGRAM_MANAGER`
- ❌ `'ManagerProgram'` → ✅ Gunakan `Role::PROGRAM_MANAGER`
- ❌ `'managerprogram'` → ✅ Gunakan `Role::PROGRAM_MANAGER`
- ❌ `'program manager'` → ✅ Gunakan `Role::PROGRAM_MANAGER`

### Production
- ❌ `'production'` (lowercase) → ✅ Gunakan `Role::PRODUCTION`
- ❌ `'produksi'` → ✅ Gunakan `Role::PRODUCTION`

### Editor
- ❌ `'editor'` (lowercase) → ✅ Gunakan `Role::EDITOR`

### Quality Control
- ❌ `'QC'` → ✅ Gunakan `Role::QUALITY_CONTROL`
- ❌ `'quality_control'` → ✅ Gunakan `Role::QUALITY_CONTROL`
- ❌ `'quality control'` (lowercase) → ✅ Gunakan `Role::QUALITY_CONTROL`

### Promotion (Handle Typo)
- ❌ `'prmotion'` → ✅ Gunakan `Role::PROMOTION` (akan di-normalize otomatis)
- ❌ `'promosi'` → ✅ Gunakan `Role::PROMOTION`

### Producer (Handle Typo)
- ❌ `'prodcer'` → ✅ Gunakan `Role::PRODUCER` (akan di-normalize otomatis)

### VP President (Handle Typo)
- ❌ `'vice presdent'` → ✅ Gunakan `Role::VP_PRESIDENT` (akan di-normalize otomatis)
- ❌ `'vice president'` → ✅ Gunakan `Role::VP_PRESIDENT`

### Sound Engineer
- ❌ `'sound_engineer'` → ✅ Gunakan `Role::SOUND_ENGINEER`
- ❌ `'sound engineer'` (lowercase) → ✅ Gunakan `Role::SOUND_ENGINEER`

### Music Arranger
- ❌ `'music_arranger'` → ✅ Gunakan `Role::MUSIC_ARRANGER`
- ❌ `'musik_arr'` → ✅ Gunakan `Role::MUSIC_ARRANGER`

### Promotion
- ❌ `'promosi'` → ✅ Gunakan `Role::PROMOTION`
- ❌ `'promotion'` (lowercase) → ✅ Gunakan `Role::PROMOTION`

---

## 📖 Cara Penggunaan

### ✅ BENAR - Menggunakan Constants

```php
use App\Constants\Role;

// Cek role
if ($user->role === Role::PRODUCER) {
    // ...
}

// Cek multiple roles
if (in_array($user->role, [Role::PRODUCER, Role::PROGRAM_MANAGER])) {
    // ...
}

// Menggunakan helper methods
if (Role::isManager($user->role)) {
    // ...
}

if (Role::canApproveProgram($user->role)) {
    // ...
}

// Query dengan role
User::where('role', Role::PRODUCER)->get();

// Normalize role (handle variasi)
$normalized = Role::normalize('Manager Program'); // Returns 'Program Manager'
```

### ❌ SALAH - Hard-coded Strings

```php
// JANGAN LAKUKAN INI!
if ($user->role === 'Producer') { // ❌
    // ...
}

if (in_array($user->role, ['Manager', 'Program Manager'])) { // ❌
    // ...
}

User::where('role', 'production')->get(); // ❌
```

---

## 🔧 Helper Methods

### Role Checking
```php
// Cek apakah role adalah manager
Role::isManager($user->role); // true/false

// Cek apakah role adalah producer
Role::isProducer($user->role); // true/false

// Cek apakah role adalah HR
Role::isHr($user->role); // true/false

// Cek apakah role adalah production team
Role::isProductionTeam($user->role); // true/false
```

### Permission Checking
```php
// Cek apakah role bisa approve program
Role::canApproveProgram($user->role); // true/false

// Cek apakah role bisa approve rundown
Role::canApproveRundown($user->role); // true/false

// Cek apakah role bisa approve schedule
Role::canApproveSchedule($user->role); // true/false
```

### Role Groups
```php
// Get semua manager roles
$managerRoles = Role::getManagerRoles();

// Get semua production team roles
$productionRoles = Role::getProductionTeamRoles();

// Get semua HR roles
$hrRoles = Role::getHrRoles();
```

### Normalization
```php
// Normalize role (handle variasi penulisan)
$normalized = Role::normalize('Manager Program'); // 'Program Manager'
$normalized = Role::normalize('production'); // 'Production'
$normalized = Role::normalize('QC'); // 'Quality Control'

// Compare roles (handle variasi)
Role::equals('Manager Program', 'Program Manager'); // true
Role::equals('production', 'Production'); // true

// Cek apakah role ada di array (handle variasi)
Role::inArray('Manager Program', ['Program Manager', 'Producer']); // true
```

---

## 🎯 Contoh Refactoring

### Sebelum (SALAH)
```php
// ApprovalWorkflowController.php
if (in_array($user->role, ['Manager', 'Program Manager'])) {
    // ...
}

if (in_array($user->role, ['Producer', 'Manager', 'Program Manager'])) {
    // ...
}

$notifyUsers = ['Manager', 'Program Manager'];
```

### Sesudah (BENAR)
```php
use App\Constants\Role;

// ApprovalWorkflowController.php
if (Role::canApproveProgram($user->role)) {
    // ...
}

if (Role::canApproveRundown($user->role)) {
    // ...
}

$notifyUsers = Role::getManagerRoles();
```

---

## 📝 Checklist untuk Developer

Sebelum commit code, pastikan:

- [ ] Tidak ada hard-coded role strings (gunakan `Role::CONSTANT`)
- [ ] Menggunakan helper methods jika tersedia (`Role::isManager()`, dll)
- [ ] Menggunakan `Role::normalize()` jika perlu handle variasi penulisan
- [ ] Menggunakan `Role::inArray()` untuk cek multiple roles
- [ ] Tidak menggunakan variasi penulisan yang salah (lihat daftar di atas)

---

## 🔍 Cara Cek Role Names di Codebase

### Cari Hard-coded Role Strings
```bash
# Cari semua hard-coded role strings
grep -r "role.*=.*['\"]" app/ --include="*.php"

# Cari in_array dengan role strings
grep -r "in_array.*role.*\[" app/ --include="*.php"
```

### Cek dengan PHPStan/Static Analysis
Jika menggunakan PHPStan, tambahkan rule untuk detect hard-coded role strings.

---

## ⚠️ Catatan Penting

1. **JANGAN** hard-code role names di controller/service
2. **SELALU** gunakan `Role::CONSTANT` untuk role names
3. **GUNAKAN** helper methods jika tersedia
4. **NORMALIZE** role jika perlu handle variasi penulisan
5. **UPDATE** dokumentasi ini jika ada role baru

---

**Terakhir Diupdate**: 2025-01-27  
**Maintainer**: Development Team

