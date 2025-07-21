# 🏢 Enhanced Role Management System

## 📋 Overview

Sistem role management yang ditingkatkan dengan fitur hierarki atasan-bawahan dan pengelompokan berdasarkan department. Sistem ini memungkinkan HR untuk membuat role baru dengan struktur organisasi yang jelas.

## 🎯 Fitur Utama

### 1. **Hierarki Atasan-Bawahan**
- Setiap role employee **harus** memiliki atasan
- Role manager dapat memiliki bawahan
- Mencegah circular reference dalam hierarki
- Otomatis mengatur approval cuti berdasarkan hierarki

### 2. **Pengelompokan Department**
- **HR & Finance**: HR, Finance, General Affairs, Office Assistant
- **Production**: Program Manager, Producer, Creative, Production, Editor
- **Distribution & Marketing**: Distribution Manager, Social Media, Promotion, Graphic Design, Hopeline Care
- **Executive**: VP President, President Director

### 3. **Level Akses**
- **Employee**: Karyawan biasa (harus punya atasan)
- **Manager**: Manajer (bisa punya bawahan)
- **HR Read-Only**: Akses lihat seperti HR
- **HR Full Access**: Akses penuh seperti HR
- **Director**: Direktur (hanya lihat, tidak mengatur)

## 🗂️ Database Schema

### Tabel `custom_roles` (Updated)
```sql
CREATE TABLE custom_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    access_level ENUM('employee', 'manager', 'hr_readonly', 'hr_full', 'director') NOT NULL,
    department ENUM('hr', 'production', 'distribution', 'executive') NOT NULL,
    supervisor_id BIGINT UNSIGNED NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (supervisor_id) REFERENCES custom_roles(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_active_access (is_active, access_level),
    INDEX idx_department_active (department, is_active),
    INDEX idx_supervisor (supervisor_id)
);
```

## 🔧 API Endpoints

### Base URL
```
/api/custom-roles
```

### Authentication
Semua endpoint memerlukan:
- `Authorization: Bearer {token}`
- Role: `HR` (hanya HR yang dapat mengelola custom roles)

---

## 📡 Endpoints Detail

### 1. **Get All Custom Roles**
**GET** `/api/custom-roles`

**Response Success (200)**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "role_name": "Developer",
            "description": "Software developer",
            "access_level": "employee",
            "department": "production",
            "supervisor_id": 2,
            "is_active": true,
            "created_by": 1,
            "created_at": "2025-07-18T10:00:00.000000Z",
            "updated_at": "2025-07-18T10:00:00.000000Z",
            "creator": {
                "id": 1,
                "name": "HR Manager"
            },
            "supervisor": {
                "id": 2,
                "role_name": "Tech Lead"
            }
        }
    ],
    "message": "Custom roles retrieved successfully"
}
```

---

### 2. **Create Custom Role**
**POST** `/api/custom-roles`

**Request Body**:
```json
{
    "role_name": "Developer",
    "description": "Software developer responsible for coding",
    "access_level": "employee",
    "department": "production",
    "supervisor_id": 2
}
```

**Parameters**:
- `role_name` (required, string): Nama role yang unik
- `description` (optional, string): Deskripsi role
- `access_level` (required, enum): Level akses role
  - `employee`: Karyawan (harus punya atasan)
  - `manager`: Manajer (bisa punya bawahan)
  - `hr_readonly`: HR Hanya Lihat
  - `hr_full`: HR Akses Penuh
  - `director`: Direktur
- `department` (required, enum): Department
  - `hr`: HR & Finance
  - `production`: Production
  - `distribution`: Distribution & Marketing
  - `executive`: Executive
- `supervisor_id` (optional, integer): ID role atasan (wajib untuk employee)

**Validation Rules**:
- Employee role **harus** memiliki supervisor
- Manager role **boleh** tidak punya supervisor
- Mencegah circular reference dalam hierarki
- Role name tidak boleh bentrok dengan standard roles

---

### 3. **Get Form Options**
**GET** `/api/custom-roles/form-options`

**Response Success (200)**:
```json
{
    "success": true,
    "data": {
        "departments": {
            "hr": "HR & Finance",
            "production": "Production",
            "distribution": "Distribution & Marketing",
            "executive": "Executive"
        },
        "access_levels": {
            "employee": "Employee (Karyawan)",
            "manager": "Manager (Manajer)",
            "hr_readonly": "HR Read-Only (HR Hanya Lihat)",
            "hr_full": "HR Full Access (HR Akses Penuh)",
            "director": "Director (Direktur)"
        },
        "supervisors": [
            "HR",
            "Program Manager",
            "Distribution Manager",
            "Tech Lead"
        ],
        "hierarchy": {
            "HR": ["Finance", "General Affairs", "Office Assistant"],
            "Program Manager": ["Producer", "Creative", "Production", "Editor"],
            "Distribution Manager": ["Social Media", "Promotion", "Graphic Design", "Hopeline Care"],
            "Tech Lead": ["Developer", "QA Engineer"]
        }
    },
    "message": "Form options retrieved successfully"
}
```

---

### 4. **Get Roles by Department**
**GET** `/api/custom-roles/by-department/{department}`

**Response Success (200)**:
```json
{
    "success": true,
    "data": [
        "Program Manager",
        "Producer",
        "Creative",
        "Production",
        "Editor",
        "Developer",
        "QA Engineer"
    ],
    "message": "Roles by department retrieved successfully"
}
```

---

### 5. **Get Role Hierarchy**
**GET** `/api/custom-roles/hierarchy/{roleName}`

**Response Success (200)**:
```json
{
    "success": true,
    "data": {
        "role_name": "Developer",
        "supervisor": "Tech Lead",
        "department": "production",
        "subordinates": []
    },
    "message": "Role hierarchy retrieved successfully"
}
```

---

### 6. **Update Custom Role**
**PUT** `/api/custom-roles/{id}`

**Request Body**:
```json
{
    "role_name": "Senior Developer",
    "description": "Senior software developer",
    "access_level": "manager",
    "department": "production",
    "supervisor_id": null,
    "is_active": true
}
```

---

### 7. **Deactivate Custom Role**
**DELETE** `/api/custom-roles/{id}`

---

## 🔄 Leave Approval System

### Hierarki Approval Cuti
Sistem otomatis mengatur approval cuti berdasarkan hierarki:

1. **Employee** → **Manager** → **HR**
2. **Manager** → **HR**
3. **HR** → **HR** (self-approval)

### Contoh Flow:
```
Developer (Employee) → Tech Lead (Manager) → Program Manager → HR
```

### Method: `RoleHierarchyService::canApproveLeave()`
```php
// Cek apakah Program Manager bisa approve cuti Developer
$canApprove = RoleHierarchyService::canApproveLeave('Program Manager', 'Developer');
// Result: true (karena Program Manager adalah atasan Tech Lead)
```

## 🏗️ Service Methods

### RoleHierarchyService

#### **getFullHierarchy()**
Mendapatkan hierarki lengkap (standard + custom roles)

#### **getSupervisorForRole($roleName)**
Mendapatkan atasan untuk role tertentu

#### **getDepartmentForRole($roleName)**
Mendapatkan department untuk role

#### **getAllSubordinates($managerRole)**
Mendapatkan semua bawahan untuk manager

#### **getRolesByDepartment($department)**
Mendapatkan semua roles berdasarkan department

#### **getAvailableManagers()**
Mendapatkan semua managers yang tersedia

#### **validateHierarchy($roleId, $supervisorId)**
Validasi hierarki untuk mencegah circular reference

## 📝 Contoh Penggunaan

### 1. **Membuat Role Developer**
```bash
curl -X POST http://localhost:8000/api/custom-roles \
  -H "Authorization: Bearer {hr_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "role_name": "Developer",
    "description": "Software developer",
    "access_level": "employee",
    "department": "production",
    "supervisor_id": 2
  }'
```

### 2. **Membuat Role Tech Lead (Manager)**
```bash
curl -X POST http://localhost:8000/api/custom-roles \
  -H "Authorization: Bearer {hr_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "role_name": "Tech Lead",
    "description": "Technical team leader",
    "access_level": "manager",
    "department": "production",
    "supervisor_id": null
  }'
```

### 3. **Mendapatkan Form Options**
```bash
curl -X GET http://localhost:8000/api/custom-roles/form-options \
  -H "Authorization: Bearer {hr_token}"
```

## ⚠️ Validasi dan Error Handling

### Common Validation Errors

**Employee tanpa Supervisor**:
```json
{
    "success": false,
    "message": "Employee roles must have a supervisor"
}
```

**Circular Reference**:
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "supervisor_id": ["Invalid supervisor selection. Cannot create circular reference."]
    }
}
```

**Role Name Conflict**:
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "role_name": ["Role name already exists in standard roles."]
    }
}
```

## 🔒 Security Features

1. **Role Validation**: Mencegah bentrok dengan standard roles
2. **Hierarchy Validation**: Mencegah circular reference
3. **Access Control**: Hanya HR yang bisa mengelola custom roles
4. **Soft Delete**: Role tidak dihapus permanen
5. **Audit Trail**: Mencatat siapa yang membuat role

## 🚀 Integration dengan Sistem Lain

### 1. **Leave Request System**
- Otomatis menggunakan hierarki untuk approval
- Manager hanya bisa approve bawahannya
- HR bisa approve semua

### 2. **Employee Management**
- Employee baru bisa dipilih role dari custom roles
- Validasi supervisor saat assign role

### 3. **Database Enum Sync**
- Otomatis update enum values di database
- Konsistensi antara custom roles dan standard roles

## 📊 Department Mapping

### Standard Roles by Department

| Department | Roles |
|------------|-------|
| **HR & Finance** | HR, Finance, General Affairs, Office Assistant |
| **Production** | Program Manager, Producer, Creative, Production, Editor |
| **Distribution** | Distribution Manager, Social Media, Promotion, Graphic Design, Hopeline Care |
| **Executive** | VP President, President Director |

### Custom Roles
Custom roles akan ditambahkan ke department yang sesuai berdasarkan pilihan HR saat membuat role.

## 🎯 Best Practices

1. **Naming Convention**: Gunakan nama role yang jelas dan konsisten
2. **Hierarchy Planning**: Rencanakan hierarki sebelum membuat role
3. **Department Alignment**: Pastikan role masuk ke department yang tepat
4. **Documentation**: Selalu berikan deskripsi yang jelas
5. **Testing**: Test approval flow setelah membuat role baru

---

Sistem ini memberikan fleksibilitas maksimal kepada HR untuk mengelola struktur organisasi sambil mempertahankan kontrol akses yang ketat dan hierarki yang jelas. 🎉 