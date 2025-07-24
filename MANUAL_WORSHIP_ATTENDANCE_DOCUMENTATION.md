# 📚 Manual Worship Attendance - Dokumentasi Lengkap

## 📋 **Ringkasan Fitur**

Fitur **Manual Worship Attendance** memungkinkan General Affairs (GA) untuk input manual absensi ibadah hari Selasa dan Kamis, melengkapi sistem absensi online yang sudah ada.

### **Fitur Utama:**
- ✅ Input manual absensi untuk hari Selasa & Kamis
- ✅ Tracking metode absensi (online/manual)
- ✅ Validasi role General Affairs
- ✅ Integrasi dengan sistem absensi existing
- ✅ API endpoints lengkap
- ✅ Frontend integration ready

---

## 🏗️ **Arsitektur Sistem**

### **Database Structure**
```sql
-- Tabel: morning_reflection_attendances
ALTER TABLE morning_reflection_attendances 
ADD COLUMN attendance_method ENUM('online', 'manual') DEFAULT 'online',
ADD COLUMN attendance_source VARCHAR(50) DEFAULT 'zoom',
ADD COLUMN created_by BIGINT UNSIGNED NULL,
ADD FOREIGN KEY (created_by) REFERENCES users(id);
```

### **File Structure**
```
app/
├── Http/
│   ├── Controllers/
│   │   └── ManualWorshipAttendanceController.php
│   ├── Requests/
│   │   └── ManualWorshipAttendanceRequest.php
│   ├── Resources/
│   │   └── WorshipAttendanceResource.php
│   └── Middleware/
│       └── ValidateGARole.php
├── Models/
│   └── MorningReflectionAttendance.php (updated)
├── Services/
│   └── ManualAttendanceService.php
└── Console/
    └── Commands/
        └── UpdateExistingWorshipAttendanceData.php
```

---

## 🔌 **API Endpoints**

### **1. Store Manual Attendance**
```http
POST /api/ga-dashboard/manual-worship-attendance
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "tanggal": "2025-07-29",
    "attendance_data": [
        {
            "employee_id": 8,
            "status": "present"
        },
        {
            "employee_id": 13,
            "status": "late"
        }
    ]
}
```

**Response Success:**
```json
{
    "success": true,
    "message": "Data absensi manual berhasil disimpan",
    "data": {
        "saved_count": 2,
        "total_data": 2,
        "date": "2025-07-29",
        "errors": []
    }
}
```

### **2. Get Employees for Manual Input**
```http
GET /api/ga-dashboard/employees-for-manual-input
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 8,
            "nama": "Jelly Jeclien Lukas",
            "jabatan": "HR"
        }
    ]
}
```

### **3. Get Worship Attendance with Method**
```http
GET /api/ga-dashboard/worship-attendance?date=2025-07-29&attendance_method=manual
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "employee_id": 8,
            "employee_name": "Jelly Jeclien Lukas",
            "date": "2025-07-29",
            "status": "present",
            "attendance_method": "manual",
            "attendance_source": "ga_input",
            "created_by": 14
        }
    ]
}
```

### **4. Update Existing Data**
```http
POST /api/ga-dashboard/update-existing-worship-data
```

**Response:**
```json
{
    "success": true,
    "message": "Data berhasil diupdate",
    "updated_count": 23,
    "total_records": 23
}
```

---

## 🔐 **Authentication & Authorization**

### **Middleware Requirements**
- `auth:sanctum` - User harus login
- `role:ga` - Hanya role General Affairs yang bisa akses

### **Role Validation**
```php
// Di dalam controller
$user = auth()->user();
if (!$user || !in_array($user->role, ['General Affairs', 'Admin'])) {
    return response()->json([
        'success' => false,
        'message' => 'Unauthorized. Hanya GA/Admin yang dapat mengakses endpoint ini.'
    ], 403);
}
```

---

## 📊 **Business Logic**

### **Validasi Hari**
- ✅ Hanya hari Selasa dan Kamis yang bisa input manual
- ✅ Hari lain tetap menggunakan sistem online

### **Duplicate Prevention**
- ✅ Satu employee hanya bisa satu record per tanggal
- ✅ Jika sudah ada data online, akan diupdate menjadi manual

### **Status Options**
- `present` - Hadir tepat waktu
- `late` - Terlambat
- `absent` - Tidak hadir
- `leave` - Cuti

---

## 🗄️ **Database Operations**

### **Migration**
```bash
# Jalankan migration
php artisan migrate

# Update data existing
php artisan worship:update-existing-data
```

### **Model Relationships**
```php
// MorningReflectionAttendance Model
protected $fillable = [
    'employee_id',
    'date',
    'status',
    'attendance_method', // 'online' | 'manual'
    'attendance_source', // 'zoom' | 'ga_input'
    'created_by'
];

// Relationships
public function employee()
{
    return $this->belongsTo(Employee::class);
}

public function createdBy()
{
    return $this->belongsTo(User::class, 'created_by');
}
```

---

## 🧪 **Testing**

### **Manual Testing Script**
```bash
# Test semua endpoint
php test_manual_worship_attendance.php
```

### **Expected Output:**
```
=== TEST MANUAL WORSHIP ATTENDANCE API ===

1. Testing Get Employees for Manual Input...
✅ Berhasil mengambil 5 employee

2. Testing Store Manual Attendance (Tuesday)...
✅ Data baru akan dibuat

3. Testing Store Manual Attendance (Thursday)...
✅ Test data preparation berhasil

6. Testing Update Existing Data...
✅ Update existing data test berhasil

=== TEST SELESAI ===
```

---

## 🎨 **Frontend Integration**

### **Vue.js Component Example**
```vue
<template>
  <div class="manual-attendance-form">
    <h3>Input Manual Absensi Ibadah</h3>
    
    <!-- Date Picker -->
    <div class="form-group">
      <label>Tanggal:</label>
      <input 
        type="date" 
        v-model="selectedDate"
        :min="getMinDate()"
        :max="getMaxDate()"
      />
    </div>
    
    <!-- Employee List -->
    <div class="employee-list">
      <div 
        v-for="employee in employees" 
        :key="employee.id"
        class="employee-item"
      >
        <span>{{ employee.nama }}</span>
        <select v-model="attendanceData[employee.id]">
          <option value="">Pilih Status</option>
          <option value="present">Hadir</option>
          <option value="late">Terlambat</option>
          <option value="absent">Tidak Hadir</option>
          <option value="leave">Cuti</option>
        </select>
      </div>
    </div>
    
    <!-- Submit Button -->
    <button @click="submitAttendance" :disabled="!canSubmit">
      Simpan Absensi
    </button>
  </div>
</template>

<script>
export default {
  data() {
    return {
      selectedDate: '',
      employees: [],
      attendanceData: {},
      loading: false
    }
  },
  
  computed: {
    canSubmit() {
      return this.selectedDate && 
             Object.keys(this.attendanceData).length > 0 &&
             !this.loading;
    }
  },
  
  methods: {
    async submitAttendance() {
      this.loading = true;
      
      try {
        const response = await this.$http.post('/api/ga-dashboard/manual-worship-attendance', {
          tanggal: this.selectedDate,
          attendance_data: this.formatAttendanceData()
        });
        
        if (response.data.success) {
          this.$toast.success('Absensi berhasil disimpan!');
          this.resetForm();
        }
      } catch (error) {
        this.$toast.error('Gagal menyimpan absensi');
      } finally {
        this.loading = false;
      }
    },
    
    formatAttendanceData() {
      return Object.entries(this.attendanceData)
        .filter(([_, status]) => status)
        .map(([employeeId, status]) => ({
          employee_id: parseInt(employeeId),
          status
        }));
    }
  }
}
</script>
```

---

## 🚀 **Deployment Guide**

### **1. Database Setup**
```bash
# Jalankan migration
php artisan migrate

# Update data existing
php artisan worship:update-existing-data
```

### **2. Cache Clear**
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### **3. Testing**
```bash
# Test API endpoints
php test_manual_worship_attendance.php

# Test frontend integration
# Buka browser dan test fitur manual input
```

### **4. Verification**
- ✅ API endpoints berfungsi
- ✅ Role validation bekerja
- ✅ Data tersimpan dengan benar
- ✅ Frontend bisa mengakses API

---

## 🔧 **Troubleshooting**

### **Error: "Target class [ManualWorshipAttendanceController] does not exist"**
**Solution:**
```bash
# 1. Pastikan import statement ada di routes/api.php
use App\Http\Controllers\ManualWorshipAttendanceController;

# 2. Clear cache
php artisan config:clear
php artisan route:clear
```

### **Error: "Column 'attendance_method' does not exist"**
**Solution:**
```bash
# 1. Jalankan migration
php artisan migrate

# 2. Jika migration gagal, jalankan manual via Tinker
php artisan tinker
>>> Schema::table('morning_reflection_attendances', function($table) {
    $table->enum('attendance_method', ['online', 'manual'])->default('online');
    $table->string('attendance_source')->default('zoom');
    $table->foreignId('created_by')->nullable()->constrained('users');
});
```

### **Error: "Unauthorized"**
**Solution:**
- Pastikan user memiliki role `General Affairs` atau `Admin`
- Pastikan token authentication valid
- Cek middleware configuration

---

## 📈 **Monitoring & Analytics**

### **Log Tracking**
```php
// Semua operasi manual attendance di-log
Log::info('Manual worship attendance request', [
    'user_id' => $user->id,
    'date' => $date,
    'data_count' => count($attendanceData)
]);
```

### **Data Statistics**
```sql
-- Query untuk statistik
SELECT 
    attendance_method,
    COUNT(*) as total,
    DATE(date) as attendance_date
FROM morning_reflection_attendances 
WHERE date >= '2025-07-01'
GROUP BY attendance_method, DATE(date)
ORDER BY attendance_date DESC;
```

---

## 📞 **Support & Contact**

### **Backend Issues**
- Cek Laravel logs: `storage/logs/laravel.log`
- Test API endpoints dengan Postman
- Verifikasi database schema

### **Frontend Issues**
- Cek browser console untuk error
- Verifikasi API response format
- Test dengan different browsers

### **Documentation Updates**
- Update dokumentasi jika ada perubahan API
- Maintain changelog untuk versioning
- Update testing scripts jika diperlukan

---

## ✅ **Checklist Implementation**

- [x] Database migration created
- [x] Model updated with new fields
- [x] Controller implemented
- [x] Service layer created
- [x] Request validation added
- [x] API routes configured
- [x] Middleware implemented
- [x] Command for data update created
- [x] Testing scripts ready
- [x] Frontend integration documented
- [x] Error handling implemented
- [x] Logging configured
- [x] Documentation completed

---

**🎉 Fitur Manual Worship Attendance siap untuk production!** 