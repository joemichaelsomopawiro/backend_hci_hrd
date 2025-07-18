# 🔧 Perbaikan Sistem Role Kustom untuk Leave Request

## 📋 Ringkasan Masalah

Sebelumnya, user dengan role kustom "backend" yang sudah dibuat dengan department "production" tidak bisa mengajukan cuti dan permohonan cutinya tidak muncul di dashboard Program Manager.

## ✅ Solusi yang Telah Diimplementasikan

### 1. **Update Database Schema**
- ✅ Menambahkan kolom `department` ke tabel `employees`
- ✅ Menambahkan kolom `access_level` ke tabel `users`
- ✅ Migration berhasil dijalankan

### 2. **Update RoleHierarchyService**
- ✅ Method `getSubordinateRoles()` diperbarui untuk mendukung role kustom
- ✅ Role kustom dengan department yang sama otomatis menjadi subordinate manager
- ✅ Support untuk custom roles dengan access_level "employee"

### 3. **Update LeaveRequestController**
- ✅ Method `store()` mendukung role kustom dengan access_level "employee"
- ✅ Method `index()` mendukung filtering berdasarkan role kustom
- ✅ Validasi role yang lebih fleksibel

### 4. **Update User Data**
- ✅ User dengan role "backend" diupdate department menjadi "production"
- ✅ User dengan role "backend" diupdate access_level menjadi "employee"
- ✅ Role standar juga diupdate dengan department dan access_level yang sesuai

## 🏗️ Arsitektur Sistem

### **Hierarchy Department:**
```
Production Department:
├── Program Manager (Manager)
│   ├── Producer (Employee)
│   ├── Creative (Employee)
│   ├── Production (Employee)
│   ├── Editor (Employee)
│   └── backend (Employee) ← Role Kustom
│
HR Department:
├── HR Manager (Manager)
│   ├── Finance (Employee)
│   ├── General Affairs (Employee)
│   └── Office Assistant (Employee)
│
Distribution Department:
├── Distribution Manager (Manager)
│   ├── Social Media (Employee)
│   ├── Promotion (Employee)
│   ├── Graphic Design (Employee)
│   └── Hopeline Care (Employee)
```

### **Access Level Mapping:**
- `employee`: Bisa mengajukan cuti, tidak bisa approve
- `manager`: Bisa approve cuti dari subordinates
- `hr_full`: Akses penuh untuk semua data
- `hr_readonly`: Hanya lihat data
- `director`: Akses executive

## 🔄 Flow Leave Request untuk Role Kustom

### **1. User Backend Mengajukan Cuti:**
```
1. User login dengan role "backend"
2. Akses EmployeeDashboard
3. Submit leave request
4. Backend validasi: role "backend" dengan access_level "employee" ✅
5. Leave request disimpan dengan status "pending"
```

### **2. Program Manager Melihat Cuti:**
```
1. Program Manager login
2. Akses dashboard leave requests
3. Backend query: getSubordinateRoles("Program Manager")
4. Hasil: ["Producer", "Creative", "Production", "Editor", "backend"]
5. Filter leave requests berdasarkan subordinate roles
6. Tampilkan cuti dari backend ✅
```

### **3. Program Manager Approve/Reject:**
```
1. Program Manager klik approve/reject
2. Backend validasi: canApproveLeave("Program Manager", "backend")
3. Hasil: TRUE ✅ (karena backend adalah subordinate)
4. Update status leave request
5. Update leave quota otomatis
```

## 📊 Data yang Sudah Diupdate

### **Custom Role:**
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

### **Leave Request:**
```sql
employee_id: [ID dari user backend]
leave_type: "annual"
start_date: "2025-07-21"
end_date: "2025-07-23"
overall_status: "pending"
```

## 🧪 Testing Results

### **Test 1: Role Hierarchy**
```
✅ Role 'backend' adalah subordinate dari Program Manager
✅ Subordinate roles untuk Program Manager: Producer, Creative, Production, Editor, backend
```

### **Test 2: Leave Request Access**
```
✅ Program Manager bisa melihat leave request dari backend
✅ Total leave requests yang bisa dilihat: 1 (dari backend)
✅ Status: pending, Type: annual
```

### **Test 3: API Endpoint**
```
✅ GET /api/leave-requests (Program Manager)
✅ GET /api/leave-requests?status=pending
✅ Response success dengan data leave request backend
```

## 🎯 Hasil Akhir

### **✅ Yang Sudah Berfungsi:**
1. **User backend bisa mengajukan cuti** - Role kustom dengan access_level "employee" diterima
2. **Program Manager bisa melihat cuti backend** - Role kustom dengan department "production" terdeteksi sebagai subordinate
3. **Program Manager bisa approve/reject cuti backend** - Validasi hierarchy berfungsi dengan benar
4. **Sistem department mapping** - Role kustom otomatis masuk ke department yang sesuai

### **🔧 File yang Diperbarui:**
1. `app/Services/RoleHierarchyService.php` - Method getSubordinateRoles()
2. `app/Http/Controllers/LeaveRequestController.php` - Method store() dan index()
3. `app/Models/Employee.php` - Tambah field department
4. `app/Models/User.php` - Tambah field access_level
5. `database/migrations/` - Tambah kolom department dan access_level

### **📝 Script yang Dibuat:**
1. `update_user_data_for_custom_roles.php` - Update data user
2. `check_custom_roles.php` - Verifikasi data
3. `test_program_manager_access.php` - Test hierarchy
4. `test_api_program_manager.php` - Test API endpoint

## 🚀 Cara Penggunaan

### **Untuk Role Kustom Baru:**
1. Buat custom role dengan department yang sesuai
2. Set access_level = "employee" untuk karyawan
3. Set access_level = "manager" untuk atasan
4. User dengan role tersebut otomatis masuk ke hierarchy yang benar

### **Untuk Manager:**
1. Login sebagai Program Manager/Distribution Manager/HR Manager
2. Akses dashboard leave requests
3. Lihat cuti dari subordinates (termasuk role kustom)
4. Approve/reject cuti sesuai wewenang

### **Untuk Employee:**
1. Login dengan role kustom
2. Akses EmployeeDashboard
3. Ajukan cuti seperti biasa
4. Cuti otomatis masuk ke atasan yang sesuai

## 🎉 Kesimpulan

Sistem role kustom sekarang sudah berfungsi dengan sempurna:
- ✅ **Role "backend" bisa mengajukan cuti**
- ✅ **Program Manager bisa melihat dan approve cuti backend**
- ✅ **Hierarchy department berfungsi dengan benar**
- ✅ **Sistem scalable untuk role kustom lainnya**

Sekarang user dengan role "backend" dan department "production" bisa mengajukan cuti dan request akan otomatis masuk ke Program Manager untuk approval! 