# 🚀 Sistem Role Kustom Scalable untuk Semua Department

## 📋 Overview

Sistem role kustom yang sudah diperbaiki bersifat **scalable** dan otomatis mendukung role kustom baru untuk semua department (Production, HR, Distribution). Setiap role kustom akan otomatis masuk ke hierarchy yang benar berdasarkan department-nya.

## 🏗️ Arsitektur Department-Based System

### **1. Production Department**
```
Program Manager (Manager)
├── Producer (Employee)
├── Creative (Employee)
├── Production (Employee)
├── Editor (Employee)
├── backend (Employee) ← Role Kustom ✅
├── frontend (Employee) ← Role Kustom ✅
├── designer (Employee) ← Role Kustom ✅
└── developer (Employee) ← Role Kustom ✅
```

### **2. HR Department**
```
HR Manager (Manager)
├── Finance (Employee)
├── General Affairs (Employee)
├── Office Assistant (Employee)
├── hr_assistant (Employee) ← Role Kustom ✅
├── payroll_specialist (Employee) ← Role Kustom ✅
└── admin_staff (Employee) ← Role Kustom ✅
```

### **3. Distribution Department**
```
Distribution Manager (Manager)
├── Social Media (Employee)
├── Promotion (Employee)
├── Graphic Design (Employee)
├── Hopeline Care (Employee)
├── marketing_specialist (Employee) ← Role Kustom ✅
├── content_creator (Employee) ← Role Kustom ✅
└── digital_analyst (Employee) ← Role Kustom ✅
```

## 🔧 Cara Menambah Role Kustom Baru

### **Step 1: Buat Custom Role**
```php
// Contoh untuk Production Department
CustomRole::create([
    'role_name' => 'frontend',
    'department' => 'production',
    'access_level' => 'employee',
    'description' => 'Frontend Developer',
    'is_active' => true
]);

// Contoh untuk HR Department
CustomRole::create([
    'role_name' => 'hr_assistant',
    'department' => 'hr',
    'access_level' => 'employee',
    'description' => 'HR Assistant',
    'is_active' => true
]);

// Contoh untuk Distribution Department
CustomRole::create([
    'role_name' => 'marketing_specialist',
    'department' => 'distribution',
    'access_level' => 'employee',
    'description' => 'Marketing Specialist',
    'is_active' => true
]);
```

### **Step 2: Update User Data**
```php
// Update user dengan role kustom baru
$user = User::where('name', 'nama_user')->first();
$user->update([
    'role' => 'frontend', // atau role kustom lainnya
    'access_level' => 'employee'
]);

// Update employee department
$user->employee->update([
    'department' => 'production' // sesuai dengan role kustom
]);
```

### **Step 3: Sistem Otomatis Berfungsi**
Setelah role kustom dibuat, sistem otomatis:
- ✅ Role kustom masuk ke hierarchy department yang sesuai
- ✅ Manager department bisa melihat cuti dari role kustom
- ✅ Manager department bisa approve/reject cuti dari role kustom
- ✅ User dengan role kustom bisa mengajukan cuti

## 🎯 Contoh Role Kustom untuk Setiap Department

### **Production Department Roles:**
```php
[
    'role_name' => 'frontend',
    'department' => 'production',
    'access_level' => 'employee'
],
[
    'role_name' => 'designer',
    'department' => 'production',
    'access_level' => 'employee'
],
[
    'role_name' => 'developer',
    'department' => 'production',
    'access_level' => 'employee'
],
[
    'role_name' => 'qa_tester',
    'department' => 'production',
    'access_level' => 'employee'
],
[
    'role_name' => 'devops',
    'department' => 'production',
    'access_level' => 'employee'
]
```

### **HR Department Roles:**
```php
[
    'role_name' => 'hr_assistant',
    'department' => 'hr',
    'access_level' => 'employee'
],
[
    'role_name' => 'payroll_specialist',
    'department' => 'hr',
    'access_level' => 'employee'
],
[
    'role_name' => 'admin_staff',
    'department' => 'hr',
    'access_level' => 'employee'
],
[
    'role_name' => 'recruitment_specialist',
    'department' => 'hr',
    'access_level' => 'employee'
],
[
    'role_name' => 'training_coordinator',
    'department' => 'hr',
    'access_level' => 'employee'
]
```

### **Distribution Department Roles:**
```php
[
    'role_name' => 'marketing_specialist',
    'department' => 'distribution',
    'access_level' => 'employee'
],
[
    'role_name' => 'content_creator',
    'department' => 'distribution',
    'access_level' => 'employee'
],
[
    'role_name' => 'digital_analyst',
    'department' => 'distribution',
    'access_level' => 'employee'
],
[
    'role_name' => 'social_media_manager',
    'department' => 'distribution',
    'access_level' => 'employee'
],
[
    'role_name' => 'brand_specialist',
    'department' => 'distribution',
    'access_level' => 'employee'
]
```

## 🔄 Flow Otomatis untuk Role Kustom Baru

### **1. User dengan Role Kustom Mengajukan Cuti:**
```
1. User login dengan role kustom (misal: 'frontend')
2. Akses EmployeeDashboard
3. Submit leave request
4. Backend validasi: role 'frontend' dengan access_level 'employee' ✅
5. Leave request disimpan dengan status 'pending'
```

### **2. Manager Melihat Cuti:**
```
1. Program Manager login
2. Akses dashboard leave requests
3. Backend query: getSubordinateRoles("Program Manager")
4. Hasil: ["Producer", "Creative", "Production", "Editor", "backend", "frontend", "designer", "developer"]
5. Filter leave requests berdasarkan subordinate roles
6. Tampilkan cuti dari semua role kustom production ✅
```

### **3. Manager Approve/Reject:**
```
1. Program Manager klik approve/reject
2. Backend validasi: canApproveLeave("Program Manager", "frontend")
3. Hasil: TRUE ✅ (karena department sama: "production")
4. Update status leave request
5. Update leave quota otomatis
```

## 🧪 Testing untuk Role Kustom Baru

### **Test 1: Hierarchy Detection**
```php
// Test untuk Production Department
$subordinates = RoleHierarchyService::getSubordinateRoles('Program Manager');
// Hasil: ["Producer", "Creative", "Production", "Editor", "backend", "frontend", "designer", "developer"]

// Test untuk HR Department
$subordinates = RoleHierarchyService::getSubordinateRoles('HR Manager');
// Hasil: ["Finance", "General Affairs", "Office Assistant", "hr_assistant", "payroll_specialist", "admin_staff"]

// Test untuk Distribution Department
$subordinates = RoleHierarchyService::getSubordinateRoles('Distribution Manager');
// Hasil: ["Social Media", "Promotion", "Graphic Design", "Hopeline Care", "marketing_specialist", "content_creator", "digital_analyst"]
```

### **Test 2: Approval Permission**
```php
// Production Department
canApproveLeave('Program Manager', 'frontend') = TRUE ✅
canApproveLeave('Program Manager', 'designer') = TRUE ✅
canApproveLeave('Program Manager', 'developer') = TRUE ✅

// HR Department
canApproveLeave('HR Manager', 'hr_assistant') = TRUE ✅
canApproveLeave('HR Manager', 'payroll_specialist') = TRUE ✅
canApproveLeave('HR Manager', 'admin_staff') = TRUE ✅

// Distribution Department
canApproveLeave('Distribution Manager', 'marketing_specialist') = TRUE ✅
canApproveLeave('Distribution Manager', 'content_creator') = TRUE ✅
canApproveLeave('Distribution Manager', 'digital_analyst') = TRUE ✅
```

## 🔐 Security Features

### **Department-Based Security:**
- ✅ **Production Department:** Program Manager hanya bisa approve role dengan department "production"
- ✅ **HR Department:** HR Manager hanya bisa approve role dengan department "hr"
- ✅ **Distribution Department:** Distribution Manager hanya bisa approve role dengan department "distribution"

### **Cross-Department Protection:**
```php
// Program Manager TIDAK BISA approve role HR
canApproveLeave('Program Manager', 'hr_assistant') = FALSE ✅

// HR Manager TIDAK BISA approve role Production
canApproveLeave('HR Manager', 'frontend') = FALSE ✅

// Distribution Manager TIDAK BISA approve role HR
canApproveLeave('Distribution Manager', 'payroll_specialist') = FALSE ✅
```

## 📊 Database Schema

### **Custom Roles Table:**
```sql
CREATE TABLE custom_roles (
    id BIGINT PRIMARY KEY,
    role_name VARCHAR(255) UNIQUE,
    department ENUM('hr', 'production', 'distribution', 'executive'),
    access_level ENUM('employee', 'manager', 'hr_readonly', 'hr_full', 'director'),
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_by BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **Users Table (Updated):**
```sql
ALTER TABLE users ADD COLUMN access_level ENUM('employee', 'manager', 'hr_readonly', 'hr_full', 'director') DEFAULT 'employee';
```

### **Employees Table (Updated):**
```sql
ALTER TABLE employees ADD COLUMN department ENUM('hr', 'production', 'distribution', 'executive');
```

## 🚀 Cara Penggunaan

### **Untuk Admin/HR:**
1. Buat custom role dengan department yang sesuai
2. Set access_level = "employee" untuk karyawan
3. Update user data dengan role kustom baru
4. Sistem otomatis berfungsi tanpa konfigurasi tambahan

### **Untuk Manager:**
1. Login sebagai manager department
2. Akses dashboard leave requests
3. Lihat cuti dari semua subordinates (termasuk role kustom)
4. Approve/reject cuti sesuai wewenang

### **Untuk Employee:**
1. Login dengan role kustom
2. Akses EmployeeDashboard
3. Ajukan cuti seperti biasa
4. Cuti otomatis masuk ke atasan department yang sesuai

## 🎉 Kesimpulan

Sistem role kustom sekarang **100% scalable** dan mendukung:

- ✅ **Semua department** (Production, HR, Distribution)
- ✅ **Role kustom unlimited** untuk setiap department
- ✅ **Automatic hierarchy mapping** berdasarkan department
- ✅ **Department-based security** yang ketat
- ✅ **No additional configuration** setelah role dibuat
- ✅ **Backward compatibility** dengan role standar

**Jadi ya, jika Anda tambah role baru untuk department HR atau Distribution, sistem akan otomatis berfungsi seperti Production!** 🎯 