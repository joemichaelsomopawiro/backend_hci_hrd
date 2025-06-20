# Auto Leave Quota Feature

## Overview
Fitur otomatis yang membuat default leave quota ketika HR membuat employee baru. HR hanya perlu mengedit quota yang sudah ada tanpa perlu membuat dari awal.

## How It Works

### 1. Automatic Creation
Ketika HR membuat employee baru melalui endpoint `POST /api/employees`, sistem akan otomatis:
- Membuat employee record
- Membuat default leave quota untuk tahun berjalan
- Menghubungkan dengan user account jika ada yang cocok

### 2. Default Leave Quota Values
```json
{
  "annual_leave_quota": 12,        // 12 hari cuti tahunan
  "sick_leave_quota": 12,          // 12 hari cuti sakit
  "emergency_leave_quota": 2,      // 2 hari cuti darurat
  "maternity_leave_quota": 90,     // 90 hari cuti melahirkan
  "paternity_leave_quota": 2,      // 2 hari cuti ayah
  "marriage_leave_quota": 3,       // 3 hari cuti nikah
  "bereavement_leave_quota": 3,    // 3 hari cuti duka
  "*_leave_used": 0                // Semua used = 0
}
```

## API Response

### Success Response
```json
{
  "message": "Data pegawai berhasil disimpan",
  "employee": {
    "id": 1,
    "nama_lengkap": "John Doe",
    // ... employee data
  },
  "user_linked": true,
  "leave_quota_created": true,
  "default_leave_quota_year": "2024",
  "linked_user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "message_detail": "Data karyawan berhasil dibuat, otomatis terhubung dengan akun user 'John Doe', dan default jatah cuti tahun 2024 telah dibuat"
}
```

## HR Workflow

### Before (Manual Process)
1. HR creates employee ✅
2. HR manually creates leave quota ❌ (Extra step)
3. HR sets all quota values ❌ (Time consuming)

### After (Automated Process)
1. HR creates employee ✅
2. ✨ **System auto-creates default leave quota** ✨
3. HR only edits quota if needed ✅ (Optional)

## Benefits

### For HR
- **Faster employee onboarding** - No need to manually create leave quotas
- **Consistent defaults** - All new employees get standard quota
- **Less errors** - No risk of forgetting to create leave quota
- **Easy editing** - Can modify quotas anytime via existing endpoints

### For System
- **Data integrity** - Every employee automatically has leave quota
- **Immediate functionality** - Employee can request leave right away
- **Audit trail** - All quota creation is logged

## Technical Implementation

### Modified Files
- `EmployeeController.php` - Added auto leave quota creation
- Added `LeaveQuota` model import
- Enhanced response with quota creation info

### Database Transaction
```php
DB::beginTransaction();
// Create employee
// Create related data (documents, histories, etc.)
// 🔥 NEW: Create default leave quota
DB::commit();
```

### Error Handling
- If leave quota creation fails, entire transaction rolls back
- Employee creation and quota creation are atomic
- Logging for successful quota creation

## Usage Examples

### 1. Create Employee (Auto-creates Leave Quota)
```bash
curl -X POST http://localhost:8000/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "nama_lengkap": "Jane Smith",
    "nik": "1234567890123456",
    "tanggal_lahir": "1990-01-01",
    "jenis_kelamin": "Perempuan",
    "alamat": "Jakarta",
    "status_pernikahan": "Belum Menikah",
    "jabatan_saat_ini": "Staff",
    "tanggal_mulai_kerja": "2024-01-01",
    "tingkat_pendidikan": "S1",
    "gaji_pokok": 5000000
  }'
```

### 2. Check Auto-Created Leave Quota
```bash
curl http://localhost:8000/api/leave-quotas?employee_id=1&year=2024
```

### 3. Edit Leave Quota (HR can modify defaults)
```bash
curl -X PUT http://localhost:8000/api/leave-quotas/1 \
  -H "Content-Type: application/json" \
  -d '{
    "annual_leave_quota": 15,
    "sick_leave_quota": 10
  }'
```

## Configuration

### Default Values Location
`EmployeeController.php` - line ~235

### Customizing Defaults
To change default quota values, modify the `LeaveQuota::create()` call:

```php
LeaveQuota::create([
    'employee_id' => $employee->id,
    'year' => $currentYear,
    'annual_leave_quota' => 15, // Change from 12 to 15
    'sick_leave_quota' => 10,   // Change from 12 to 10
    // ... other quotas
]);
```

## Integration with Existing Features

### Leave Request System
- ✅ Employee can immediately request leave
- ✅ System validates against auto-created quotas
- ✅ Quota reduction works automatically

### HR Management
- ✅ HR can view all quotas via existing endpoints
- ✅ HR can edit quotas via existing endpoints
- ✅ HR can bulk update quotas
- ✅ HR can reset annual quotas

## Monitoring & Logs

### Success Log
```
[INFO] Default leave quota berhasil dibuat untuk employee 'Jane Smith' (ID: 1) tahun 2024
```

### Error Handling
- Transaction rollback if quota creation fails
- Employee creation fails if quota creation fails
- Maintains data consistency

## Future Enhancements

### Possible Improvements
1. **Configurable defaults** - Admin can set company-wide defaults
2. **Role-based quotas** - Different defaults based on job position
3. **Seniority-based quotas** - More quota for senior employees
4. **Department-based quotas** - Different quotas per department
5. **Bulk employee import** - Auto-create quotas for multiple employees

---

**Status**: ✅ **IMPLEMENTED & READY**

**Last Updated**: December 2024
**Version**: 1.0
**Author**: AI Assistant