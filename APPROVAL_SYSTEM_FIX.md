# 🔧 Perbaikan Sistem Approval untuk Role Kustom

## 📋 Ringkasan Masalah

Sebelumnya, Program Manager bisa melihat leave request dari role kustom "backend", tapi tidak bisa approve/reject karena error 403 (Forbidden). Masalahnya ada di method `canApproveLeave` di `RoleHierarchyService` yang belum mendukung role kustom berdasarkan department.

## ✅ Solusi yang Telah Diimplementasikan

### **1. Update RoleHierarchyService::canApproveLeave()**

**File:** `app/Services/RoleHierarchyService.php`

**Perubahan:**
```php
// DIPERBARUI: Cek di custom hierarchy berdasarkan department
$approverDepartment = self::getDepartmentForRole($approverRole);
$employeeDepartment = self::getDepartmentForRole($employeeRole);

// Jika department sama dan employee adalah custom role dengan access_level employee
if ($approverDepartment && $employeeDepartment && $approverDepartment === $employeeDepartment) {
    $customEmployee = CustomRole::where('role_name', $employeeRole)
        ->where('access_level', 'employee')
        ->where('is_active', true)
        ->first();
        
    if ($customEmployee) {
        return true;
    }
}
```

**Logika Baru:**
1. Cek apakah approver adalah manager
2. Cek di standard hierarchy (role standar)
3. **BARU:** Cek di custom hierarchy berdasarkan department mapping
4. Cek di custom hierarchy berdasarkan supervisor_id

### **2. Testing Results**

#### **Test 1: canApproveLeave Function**
```
✅ canApproveLeave('Program Manager', 'backend') = TRUE
✅ Program Manager BISA approve leave request dari backend
```

#### **Test 2: Department Mapping**
```
✅ Program Manager Department: production
✅ Backend Department: production
✅ Department sama: production
```

#### **Test 3: API Endpoint Approve**
```
✅ PUT /api/leave-requests/47/approve
✅ Status Code: 200
✅ Success: Permohonan cuti berhasil disetujui
✅ Leave Request Status: approved
```

#### **Test 4: API Endpoint Reject**
```
✅ PUT /api/leave-requests/48/reject
✅ Status Code: 200
✅ Success: Permohonan cuti berhasil ditolak
✅ Leave Request Status: rejected
```

## 🏗️ Arsitektur Approval System

### **Flow Approval untuk Role Kustom:**

```
1. User Backend mengajukan cuti
   ↓
2. Leave request disimpan dengan status "pending"
   ↓
3. Program Manager login dan akses dashboard
   ↓
4. Backend query: getSubordinateRoles("Program Manager")
   ↓
5. Hasil: ["Producer", "Creative", "Production", "Editor", "backend"]
   ↓
6. Dashboard menampilkan cuti dari backend
   ↓
7. Program Manager klik approve/reject
   ↓
8. Backend validasi: canApproveLeave("Program Manager", "backend")
   ↓
9. Hasil: TRUE (karena department sama: "production")
   ↓
10. Update status leave request
    ↓
11. Update leave quota otomatis
```

### **Validasi Hierarchy:**

#### **Standard Roles:**
```php
// Program Manager bisa approve:
- Producer, Creative, Production, Editor
```

#### **Custom Roles:**
```php
// Program Manager bisa approve:
- backend (department: production, access_level: employee)
- frontend (department: production, access_level: employee)
- designer (department: production, access_level: employee)
- developer (department: production, access_level: employee)
```

## 🔐 Security Features

### **Authorization Logic:**
```php
// 1. Cek apakah user adalah manager
if (!RoleHierarchyService::isManager($user->role)) {
    return false;
}

// 2. Cek standard hierarchy
if (in_array($employeeRole, $standardSubordinates)) {
    return true;
}

// 3. Cek custom hierarchy berdasarkan department
if ($approverDepartment === $employeeDepartment) {
    if ($customEmployee && $customEmployee->access_level === 'employee') {
        return true;
    }
}

// 4. Cek custom hierarchy berdasarkan supervisor_id
if ($customManager && in_array($employeeRole, $customSubordinates)) {
    return true;
}
```

### **Department-Based Security:**
- ✅ **Production Department:** Program Manager bisa approve semua role dengan department "production"
- ✅ **HR Department:** HR Manager bisa approve semua role dengan department "hr"
- ✅ **Distribution Department:** Distribution Manager bisa approve semua role dengan department "distribution"

## 📊 Data yang Sudah Diupdate

### **Custom Role Data:**
```sql
role_name: "backend"
department: "production"
access_level: "employee"
is_active: true
```

### **User Data:**
```sql
name: "backend"
role: "backend"
access_level: "employee"
employee.department: "production"
```

### **Leave Request Data:**
```sql
-- Approved Request
id: 47
employee_id: [backend employee id]
leave_type: "annual"
overall_status: "approved"
approved_by: [program manager employee id]

-- Rejected Request
id: 48
employee_id: [backend employee id]
leave_type: "sick"
overall_status: "rejected"
rejection_reason: "Test rejection dari Program Manager"
```

## 🧪 Testing Scripts

### **1. test_approve_permission.php**
- Test function `canApproveLeave`
- Test department mapping
- Test custom role data

### **2. test_approve_api.php**
- Test API endpoint approve
- Generate token untuk Program Manager
- Test dengan leave request real

### **3. test_reject_api.php**
- Test API endpoint reject
- Test dengan rejection reason
- Verify response format

### **4. create_test_leave_request.php**
- Buat leave request baru untuk testing
- Hitung hari kerja otomatis
- Set status pending

## 🎯 Hasil Akhir

### **✅ Yang Sudah Berfungsi:**
1. **Program Manager bisa melihat cuti backend** - Role kustom terdeteksi sebagai subordinate
2. **Program Manager bisa approve cuti backend** - Department mapping berfungsi
3. **Program Manager bisa reject cuti backend** - Validasi hierarchy berfungsi
4. **Sistem scalable** - Bisa digunakan untuk role kustom lainnya

### **🔧 File yang Diperbarui:**
1. `app/Services/RoleHierarchyService.php` - Method canApproveLeave()
2. `app/Http/Controllers/LeaveRequestController.php` - Method approve() dan reject()

### **📝 Script yang Dibuat:**
1. `test_approve_permission.php` - Test function canApproveLeave
2. `test_approve_api.php` - Test API endpoint approve
3. `test_reject_api.php` - Test API endpoint reject
4. `create_test_leave_request.php` - Buat leave request untuk testing

## 🚀 Cara Penggunaan

### **Untuk Program Manager:**
1. Login sebagai Program Manager
2. Akses dashboard leave requests
3. Lihat cuti dari subordinates (termasuk role kustom)
4. Klik approve/reject untuk cuti dari backend
5. Sistem otomatis validasi dan update status

### **Untuk Role Kustom Baru:**
1. Buat custom role dengan department yang sesuai
2. Set access_level = "employee" untuk karyawan
3. User dengan role tersebut otomatis bisa diapprove oleh manager department yang sama

## 🎉 Kesimpulan

Sistem approval untuk role kustom sekarang sudah berfungsi dengan sempurna:
- ✅ **Program Manager bisa approve/reject cuti backend**
- ✅ **Department-based security berfungsi**
- ✅ **API endpoints approve/reject berfungsi**
- ✅ **Sistem scalable untuk role kustom lainnya**

Sekarang Program Manager bisa dengan mudah approve atau reject leave request dari user dengan role kustom "backend" tanpa error 403! 