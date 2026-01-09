# 📋 Daftar Role yang Ada di Hope Channel Indonesia

**Terakhir Diupdate**: 2025-01-27  
**Total Role**: 22 Role Standar + 1 Default Role

---

## ✅ Role Standar (22 Role)

Berikut adalah daftar lengkap role yang ada di sistem Hope Channel Indonesia:

1. **Hopeline Care** → `Role::HOPELINE_CARE`
2. **Production** → `Role::PRODUCTION`
3. **Graphic Design** → `Role::GRAPHIC_DESIGN`
4. **Editor** → `Role::EDITOR`
5. **HR** → `Role::HR`
6. **General Affairs** → `Role::GENERAL_AFFAIRS`
7. **Promotion** → `Role::PROMOTION`
8. **Sound Engineer** → `Role::SOUND_ENGINEER`
9. **Art & Set Properti** → `Role::ART_SET_PROPERTI`
10. **Program Manager** → `Role::PROGRAM_MANAGER`
11. **Office Assistant** → `Role::OFFICE_ASSISTANT`
12. **Creative** → `Role::CREATIVE`
13. **Quality Control** → `Role::QUALITY_CONTROL`
14. **President Director** → `Role::PRESIDENT_DIRECTOR`
15. **Editor Promotion** → `Role::EDITOR_PROMOTION`
16. **Distribution Manager** → `Role::DISTRIBUTION_MANAGER`
17. **Music Arranger** → `Role::MUSIC_ARRANGER`
18. **Broadcasting** → `Role::BROADCASTING`
19. **Producer** → `Role::PRODUCER`
20. **VP President** → `Role::VP_PRESIDENT`
21. **Finance** → `Role::FINANCE`
22. **Social Media** → `Role::SOCIAL_MEDIA`

---

## 👤 Default Role

- **Employee** → `Role::EMPLOYEE` (default role untuk fallback)

---

## ⚠️ Typo yang Dihandle Otomatis

Sistem akan otomatis normalize typo berikut:

| Typo | Normalized To |
|------|---------------|
| `'prmotion'` | `'Promotion'` |
| `'prodcer'` | `'Producer'` |
| `'vice presdent'` | `'VP President'` |

Gunakan `Role::normalize($role)` untuk normalize role name.

---

## 🔄 Custom Roles

Sistem mendukung **custom roles** yang ditambahkan melalui sistem. 

Untuk mendapatkan semua roles (standar + custom), gunakan:
```php
use App\Services\DatabaseEnumService;

$allRoles = DatabaseEnumService::getAllAvailableRoles();
```

---

## 📝 Cara Menambah Role Baru

Jika ada role baru yang perlu ditambahkan:

1. **Jika role standar** (selalu ada di sistem):
   - Tambahkan constant di `app/Constants/Role.php`
   - Update `getAllStandardRoles()`
   - Update dokumentasi ini

2. **Jika custom role** (ditambahkan melalui sistem):
   - Tambahkan melalui sistem custom role management
   - Tidak perlu update `Role.php` (akan otomatis terdeteksi)

---

## ✅ Checklist

Sebelum commit code dengan role baru:

- [ ] Role sudah ditambahkan ke `Role.php` (jika standar)
- [ ] Constant name mengikuti konvensi (UPPER_SNAKE_CASE)
- [ ] Value role name konsisten (Title Case)
- [ ] Sudah ditambahkan ke `getAllStandardRoles()`
- [ ] Sudah ditambahkan ke dokumentasi ini
- [ ] Sudah ditambahkan typo handling di `normalize()` jika perlu

---

**Maintainer**: Development Team

