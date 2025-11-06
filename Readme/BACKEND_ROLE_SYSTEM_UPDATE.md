# 🔧 Backend Role System Update

## 📋 Overview

Backend telah diupdate dengan sistem role management yang lebih terstruktur dengan fitur hierarki atasan-bawahan dan pengelompokan berdasarkan department.

## 🆕 Fitur Baru yang Ditambahkan

### 1. **Database Schema Updates**

#### **Migration: `2025_07_18_103434_add_department_and_supervisor_to_custom_roles_table.php`**
```php
Schema::table('custom_roles', function (Blueprint $table) {
    $table->enum('department', ['hr', 'production', 'distribution', 'executive'])->nullable()->after('access_level');
    $table->unsignedBigInteger('supervisor_id')->nullable()->after('department');
    
    $table->foreign('supervisor_id')->references('id')->on('custom_roles')->onDelete('set null');
    $table->index(['department', 'is_active']);
    $table->index('supervisor_id');
});
```

**Kolom Baru:**
- `department`: ENUM untuk mengelompokkan role berdasarkan department
- `supervisor_id`: Foreign key ke role atasan (self-referencing)

### 2. **Model Updates**

#### **`app/Models/CustomRole.php`**
```php
// Relationships baru
public function supervisor()
{
    return $this->belongsTo(CustomRole::class, 'supervisor_id');
}

public function subordinates()
{
    return $this->hasMany(CustomRole::class, 'supervisor_id');
}

// Scopes baru
public function scopeByDepartment($query, $department)
{
    return $query->where('department', $department);
}

// Helper methods
public static function getDepartmentOptions()
{
    return [
        'hr' => 'HR & Finance',
        'production' => 'Production',
        'distribution' => 'Distribution & Marketing',
        'executive' => 'Executive'
    ];
}

public static function getAccessLevelOptions()
{
    return [
        'employee' => 'Employee (Karyawan)',
        'manager' => 'Manager (Manajer)',
        'hr_readonly' => 'HR Read-Only (HR Hanya Lihat)',
        'hr_full' => 'HR Full Access (HR Akses Penuh)',
        'director' => 'Director (Direktur)'
    ];
}
```

### 3. **Service Updates**

#### **`app/Services/RoleHierarchyService.php`**
```php
// Method baru untuk hierarki
public static function getFullHierarchy(): array
{
    $hierarchy = self::$standardHierarchy;
    
    // Tambahkan custom roles ke hierarchy
    $customRoles = CustomRole::with('supervisor')
        ->where('is_active', true)
        ->where('access_level', 'manager')
        ->get();
        
    foreach ($customRoles as $customRole) {
        $subordinates = CustomRole::where('supervisor_id', $customRole->id)
            ->where('is_active', true)
            ->pluck('role_name')
            ->toArray();
            
        if (!empty($subordinates)) {
            $hierarchy[$customRole->role_name] = $subordinates;
        }
    }
    
    return $hierarchy;
}

public static function getSupervisorForRole($roleName): ?string
{
    // Cek di standard hierarchy
    foreach (self::$standardHierarchy as $manager => $subordinates) {
        if (in_array($roleName, $subordinates)) {
            return $manager;
        }
    }
    
    // Cek di custom roles
    $customRole = CustomRole::where('role_name', $roleName)
        ->where('is_active', true)
        ->with('supervisor')
        ->first();
        
    if ($customRole && $customRole->supervisor) {
        return $customRole->supervisor->role_name;
    }
    
    return null;
}

public static function validateHierarchy($roleId, $supervisorId): bool
{
    if (!$supervisorId) return true;
    
    if ($roleId == $supervisorId) return false;
    
    $supervisor = CustomRole::find($supervisorId);
    if (!$supervisor) return true;
    
    return $supervisor->supervisor_id != $roleId;
}
```

### 4. **Controller Updates**

#### **`app/Http/Controllers/CustomRoleController.php`**

**Validation Rules Baru:**
```php
$validated = $request->validate([
    'role_name' => [
        'required',
        'string',
        'max:255',
        'unique:custom_roles,role_name',
        function ($attribute, $value, $fail) {
            $standardRoles = RoleHierarchyService::getAllAvailableRoles();
            if (in_array($value, $standardRoles)) {
                $fail('Role name already exists in standard roles.');
            }
        },
    ],
    'description' => 'nullable|string|max:1000',
    'access_level' => [
        'required',
        Rule::in(['employee', 'manager', 'hr_readonly', 'hr_full', 'director'])
    ],
    'department' => [
        'required',
        Rule::in(['hr', 'production', 'distribution', 'executive'])
    ],
    'supervisor_id' => [
        'nullable',
        'exists:custom_roles,id',
        function ($attribute, $value, $fail) {
            if ($value && !RoleHierarchyService::validateHierarchy(null, $value)) {
                $fail('Invalid supervisor selection. Cannot create circular reference.');
            }
        }
    ]
]);

// Validasi tambahan untuk supervisor
if ($validated['access_level'] === 'employee' && !$validated['supervisor_id']) {
    return response()->json([
        'success' => false,
        'message' => 'Employee roles must have a supervisor'
    ], 422);
}
```

**Method Baru:**
```php
public function getFormOptions(): JsonResponse
{
    $options = [
        'departments' => CustomRole::getDepartmentOptions(),
        'access_levels' => CustomRole::getAccessLevelOptions(),
        'supervisors' => RoleHierarchyService::getAvailableManagers(),
        'hierarchy' => RoleHierarchyService::getFullHierarchy()
    ];

    return response()->json([
        'success' => true,
        'data' => $options,
        'message' => 'Form options retrieved successfully'
    ]);
}

public function getRolesByDepartment($department): JsonResponse
{
    $roles = RoleHierarchyService::getRolesByDepartment($department);

    return response()->json([
        'success' => true,
        'data' => $roles,
        'message' => 'Roles by department retrieved successfully'
    ]);
}

public function getRoleHierarchy($roleName): JsonResponse
{
    $supervisor = RoleHierarchyService::getSupervisorForRole($roleName);
    $department = RoleHierarchyService::getDepartmentForRole($roleName);
    $subordinates = RoleHierarchyService::getAllSubordinates($roleName);

    return response()->json([
        'success' => true,
        'data' => [
            'role_name' => $roleName,
            'supervisor' => $supervisor,
            'department' => $department,
            'subordinates' => $subordinates
        ],
        'message' => 'Role hierarchy retrieved successfully'
    ]);
}
```

### 5. **Routes Updates**

#### **`routes/api.php`**
```php
// Custom Role Management Routes
Route::prefix('custom-roles')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [CustomRoleController::class, 'index']);
    Route::post('/', [CustomRoleController::class, 'store']);
    Route::get('/all-roles', [CustomRoleController::class, 'getAllRoles']);
    Route::get('/form-options', [CustomRoleController::class, 'getFormOptions']); // BARU
    Route::get('/by-department/{department}', [CustomRoleController::class, 'getRolesByDepartment']); // BARU
    Route::get('/hierarchy/{roleName}', [CustomRoleController::class, 'getRoleHierarchy']); // BARU
    Route::get('/{id}', [CustomRoleController::class, 'show']);
    Route::put('/{id}', [CustomRoleController::class, 'update']);
    Route::delete('/{id}', [CustomRoleController::class, 'destroy']);
});
```

## 🔄 Leave Approval System Integration

### **Updated `canApproveLeave()` Method**
```php
public static function canApproveLeave($approverRole, $employeeRole): bool
{
    // Cek apakah approver adalah manager
    if (!self::isManager($approverRole)) {
        return false;
    }

    // Cek di standard hierarchy
    if (isset(self::$standardHierarchy[$approverRole])) {
        if (in_array($employeeRole, self::$standardHierarchy[$approverRole])) {
            return true;
        }
    }
    
    // Cek di custom hierarchy
    $customManager = CustomRole::where('role_name', $approverRole)
        ->where('is_active', true)
        ->first();
        
    if ($customManager) {
        $subordinates = CustomRole::where('supervisor_id', $customManager->id)
            ->where('is_active', true)
            ->pluck('role_name')
            ->toArray();
            
        return in_array($employeeRole, $subordinates);
    }
    
    return false;
}
```

## 📊 Department Mapping

### **Standard Roles by Department**
```php
protected static $standardDepartmentMapping = [
    'HR' => 'hr',
    'Finance' => 'hr',
    'General Affairs' => 'hr',
    'Office Assistant' => 'hr',
    'Program Manager' => 'production',
    'Producer' => 'production',
    'Creative' => 'production',
    'Production' => 'production',
    'Editor' => 'production',
    'Distribution Manager' => 'distribution',
    'Social Media' => 'distribution',
    'Promotion' => 'distribution',
    'Graphic Design' => 'distribution',
    'Hopeline Care' => 'distribution',
    'VP President' => 'executive',
    'President Director' => 'executive'
];
```

## 🧪 Testing

### **File Test: `test_enhanced_role_system.php`**
Test file yang komprehensif untuk menguji:
- Form options retrieval
- Role creation dengan hierarki
- Department filtering
- Hierarchy validation
- Error handling

### **Cara Menjalankan Test:**
```bash
php test_enhanced_role_system.php
```

## 🔒 Security & Validation

### **Validasi yang Ditambahkan:**
1. **Employee Role Validation**: Employee role harus punya supervisor
2. **Circular Reference Prevention**: Mencegah hierarki melingkar
3. **Role Name Conflict**: Mencegah bentrok dengan standard roles
4. **Department Validation**: Memastikan department valid
5. **Access Level Validation**: Memastikan access level valid

### **Error Messages:**
```php
// Employee tanpa supervisor
"Employee roles must have a supervisor"

// Circular reference
"Invalid supervisor selection. Cannot create circular reference."

// Role name conflict
"Role name already exists in standard roles."
```

## 🚀 Migration Commands

### **Jalankan Migration:**
```bash
php artisan migrate
```

### **Rollback Migration (jika perlu):**
```bash
php artisan migrate:rollback --step=1
```

## 📝 API Response Examples

### **Create Role Success:**
```json
{
    "success": true,
    "data": {
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
    },
    "message": "Custom role created successfully"
}
```

### **Form Options:**
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

## 🎯 Integration Points

### **1. Leave Request System**
- Otomatis menggunakan hierarki untuk approval
- Manager hanya bisa approve bawahannya
- HR bisa approve semua

### **2. Employee Management**
- Employee baru bisa dipilih role dari custom roles
- Validasi supervisor saat assign role

### **3. Database Enum Sync**
- Otomatis update enum values di database
- Konsistensi antara custom roles dan standard roles

## 🔧 Troubleshooting

### **Common Issues:**

1. **Migration Error**: Pastikan database connection stabil
2. **Foreign Key Error**: Pastikan custom_roles table sudah ada
3. **Enum Update Error**: Pastikan DatabaseEnumService berfungsi
4. **Validation Error**: Cek format data yang dikirim

### **Debug Commands:**
```bash
# Cek migration status
php artisan migrate:status

# Cek route list
php artisan route:list | grep custom-roles

# Clear cache
php artisan config:clear
php artisan route:clear
```

---

Sistem role management backend telah berhasil diupdate dengan fitur hierarki dan department yang lengkap! 🎉 