# Custom Role Management API Documentation

## Overview
API ini memungkinkan HR untuk mengelola role kustom dalam sistem. Fitur ini juga menambahkan dua role baru dengan akses read-only: **VP President** dan **President Director** yang memiliki akses seperti HR tetapi hanya bisa melihat data (tidak bisa edit, create, atau delete).

## New Roles Added

### 1. VP President
- **Access Level**: Read-only (seperti HR)
- **Permissions**: Dapat melihat semua data yang bisa dilihat HR
- **Restrictions**: Tidak dapat melakukan create, update, atau delete

### 2. President Director
- **Access Level**: Read-only (seperti HR)
- **Permissions**: Dapat melihat semua data yang bisa dilihat HR
- **Restrictions**: Tidak dapat melakukan create, update, atau delete

## Custom Role Management

### Base URL
```
/api/custom-roles
```

### Authentication
Semua endpoint memerlukan:
- `Authorization: Bearer {token}`
- Role: `HR` (hanya HR yang dapat mengelola custom roles)

---

## Endpoints

### 1. Get All Custom Roles
**GET** `/api/custom-roles`

**Description**: Mendapatkan semua custom roles yang telah dibuat

**Headers**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Response Success (200)**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "role_name": "Marketing Specialist",
            "description": "Specialist in marketing activities",
            "access_level": "employee",
            "is_active": true,
            "created_by": 1,
            "created_at": "2025-01-30T10:00:00.000000Z",
            "updated_at": "2025-01-30T10:00:00.000000Z",
            "creator": {
                "id": 1,
                "name": "HR Manager"
            }
        }
    ],
    "message": "Custom roles retrieved successfully"
}
```

---

### 2. Create Custom Role
**POST** `/api/custom-roles`

**Description**: Membuat custom role baru

**Headers**:
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body**:
```json
{
    "role_name": "Marketing Specialist",
    "description": "Specialist in marketing activities",
    "access_level": "employee"
}
```

**Parameters**:
- `role_name` (required, string): Nama role yang unik
- `description` (optional, string): Deskripsi role
- `access_level` (required, enum): Level akses role
  - `employee`: Akses seperti karyawan biasa
  - `manager`: Akses seperti manager
  - `hr_readonly`: Akses read-only seperti HR
  - `hr_full`: Akses penuh seperti HR

**Response Success (201)**:
```json
{
    "success": true,
    "data": {
        "id": 1,
        "role_name": "Marketing Specialist",
        "description": "Specialist in marketing activities",
        "access_level": "employee",
        "is_active": true,
        "created_by": 1,
        "created_at": "2025-01-30T10:00:00.000000Z",
        "updated_at": "2025-01-30T10:00:00.000000Z",
        "creator": {
            "id": 1,
            "name": "HR Manager"
        }
    },
    "message": "Custom role created successfully"
}
```

**Response Error (422)**:
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "role_name": ["Role name already exists in standard roles."]
    }
}
```

---

### 3. Get Custom Role Detail
**GET** `/api/custom-roles/{id}`

**Description**: Mendapatkan detail custom role berdasarkan ID

**Response Success (200)**:
```json
{
    "success": true,
    "data": {
        "id": 1,
        "role_name": "Marketing Specialist",
        "description": "Specialist in marketing activities",
        "access_level": "employee",
        "is_active": true,
        "created_by": 1,
        "created_at": "2025-01-30T10:00:00.000000Z",
        "updated_at": "2025-01-30T10:00:00.000000Z",
        "creator": {
            "id": 1,
            "name": "HR Manager"
        }
    },
    "message": "Custom role retrieved successfully"
}
```

---

### 4. Update Custom Role
**PUT** `/api/custom-roles/{id}`

**Description**: Mengupdate custom role

**Request Body**:
```json
{
    "role_name": "Senior Marketing Specialist",
    "description": "Senior specialist in marketing activities",
    "access_level": "manager",
    "is_active": true
}
```

**Response Success (200)**:
```json
{
    "success": true,
    "data": {
        "id": 1,
        "role_name": "Senior Marketing Specialist",
        "description": "Senior specialist in marketing activities",
        "access_level": "manager",
        "is_active": true,
        "created_by": 1,
        "created_at": "2025-01-30T10:00:00.000000Z",
        "updated_at": "2025-01-30T10:30:00.000000Z",
        "creator": {
            "id": 1,
            "name": "HR Manager"
        }
    },
    "message": "Custom role updated successfully"
}
```

---

### 5. Deactivate Custom Role
**DELETE** `/api/custom-roles/{id}`

**Description**: Menonaktifkan custom role (soft delete)

**Response Success (200)**:
```json
{
    "success": true,
    "message": "Custom role deactivated successfully"
}
```

---

### 6. Get All Available Roles
**GET** `/api/custom-roles/all-roles`

**Description**: Mendapatkan semua role yang tersedia (standard + custom)

**Response Success (200)**:
```json
{
    "success": true,
    "data": {
        "standard_roles": {
            "managers": ["HR", "Program Manager", "Distribution Manager"],
            "employees": ["Finance", "General Affairs", "Office Assistant", "Producer", "Creative", "Production", "Editor", "Social Media", "Promotion", "Graphic Design", "Hopeline Care"],
            "readonly": ["VP President", "President Director"]
        },
        "custom_roles": {
            "employee": [
                {
                    "id": 1,
                    "role_name": "Marketing Specialist",
                    "description": "Specialist in marketing activities",
                    "access_level": "employee",
                    "is_active": true
                }
            ],
            "manager": [],
            "hr_readonly": [],
            "hr_full": []
        }
    },
    "message": "All roles retrieved successfully"
}
```

---

## Access Control Implementation

### Read-Only Middleware
Sistem menggunakan middleware `ReadOnlyRoleMiddleware` untuk mengontrol akses:

- **VP President** dan **President Director**: Hanya dapat melakukan HTTP GET requests
- **HR**: Memiliki akses penuh ke semua operasi
- **Custom Roles**: Akses ditentukan berdasarkan `access_level`

### Role Hierarchy Service Updates
Service `RoleHierarchyService` telah diupdate dengan method baru:

- `isReadOnlyRole($role)`: Mengecek apakah role memiliki akses read-only
- `isExecutiveRole($role)`: Mengecek apakah role adalah VP President atau President Director
- `getAllAvailableRoles()`: Mendapatkan semua role termasuk custom roles
- `isCustomRole($role)`: Mengecek apakah role adalah custom role
- `getCustomRoleAccessLevel($role)`: Mendapatkan access level dari custom role

---

## Database Schema

### Custom Roles Table
```sql
CREATE TABLE custom_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(255) UNIQUE NOT NULL,
    description TEXT NULL,
    access_level ENUM('employee', 'manager', 'hr_readonly', 'hr_full') DEFAULT 'employee',
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_active_access (is_active, access_level)
);
```

### Updated Role Enums
Enum `role` di tabel `users` dan `jabatan_saat_ini` di tabel `employees` telah diupdate untuk menambahkan:
- `VP President`
- `President Director`

---

## Error Handling

### Common Error Responses

**401 Unauthorized**:
```json
{
    "success": false,
    "message": "Unauthorized"
}
```

**403 Access Denied**:
```json
{
    "success": false,
    "message": "Access denied. Read-only access for executive roles."
}
```

**404 Not Found**:
```json
{
    "success": false,
    "message": "Custom role not found"
}
```

**422 Validation Error**:
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "role_name": ["The role name field is required."]
    }
}
```

**500 Server Error**:
```json
{
    "success": false,
    "message": "Failed to create custom role: [error details]"
}
```

---

## Usage Examples

### Creating a New Employee Role
```bash
curl -X POST http://localhost:8000/api/custom-roles \
  -H "Authorization: Bearer {hr_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "role_name": "Content Writer",
    "description": "Responsible for creating content",
    "access_level": "employee"
  }'
```

### Creating a Manager-Level Role
```bash
curl -X POST http://localhost:8000/api/custom-roles \
  -H "Authorization: Bearer {hr_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "role_name": "Regional Manager",
    "description": "Manages regional operations",
    "access_level": "manager"
  }'
```

### Getting All Roles for Employee Assignment
```bash
curl -X GET http://localhost:8000/api/custom-roles/all-roles \
  -H "Authorization: Bearer {hr_token}"
```

---

## Security Considerations

1. **Role Validation**: Sistem memvalidasi bahwa custom role name tidak bentrok dengan standard roles
2. **Access Control**: Middleware memastikan hanya HR yang dapat mengelola custom roles
3. **Read-Only Enforcement**: Executive roles (VP President, President Director) dibatasi hanya untuk operasi read
4. **Soft Delete**: Custom roles tidak dihapus permanen, hanya dinonaktifkan
5. **Audit Trail**: Semua custom roles mencatat siapa yang membuatnya

---

## Integration Notes

1. **Employee Management**: EmployeeController telah diupdate untuk mendukung custom roles dalam validasi
2. **Role Hierarchy**: RoleHierarchyService terintegrasi dengan custom roles
3. **Middleware**: ReadOnlyRoleMiddleware mengatur akses berdasarkan role type
4. **Database**: Migration otomatis menambahkan role baru ke enum existing

Sistem ini memberikan fleksibilitas kepada HR untuk membuat role sesuai kebutuhan organisasi sambil mempertahankan kontrol akses yang ketat.