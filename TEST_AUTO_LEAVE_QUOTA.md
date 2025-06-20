# Testing Auto Leave Quota Feature

## Test Scenario: Create Employee and Verify Auto Leave Quota

### Step 1: Create New Employee

**Endpoint**: `POST /api/employees`

**Request**:
```bash
curl -X POST http://localhost:8000/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "nama_lengkap": "Test Employee Auto Quota",
    "nik": "9876543210123456",
    "tanggal_lahir": "1995-05-15",
    "jenis_kelamin": "Laki-laki",
    "alamat": "Jakarta Selatan",
    "status_pernikahan": "Belum Menikah",
    "jabatan_saat_ini": "Software Developer",
    "tanggal_mulai_kerja": "2024-12-01",
    "tingkat_pendidikan": "S1",
    "gaji_pokok": 8000000,
    "tunjangan": 1000000,
    "bonus": 500000
  }'
```

**Expected Response**:
```json
{
  "message": "Data pegawai berhasil disimpan",
  "employee": {
    "id": 1,
    "nama_lengkap": "Test Employee Auto Quota",
    "nik": "9876543210123456",
    "jabatan_saat_ini": "Software Developer",
    "tanggal_mulai_kerja": "2024-12-01",
    "gaji_pokok": 8000000,
    "created_at": "2024-12-XX",
    "updated_at": "2024-12-XX"
  },
  "user_linked": false,
  "leave_quota_created": true,
  "default_leave_quota_year": "2024",
  "message_detail": "Data karyawan berhasil dibuat dan default jatah cuti tahun 2024 telah dibuat. Belum ada akun user yang cocok"
}
```

### Step 2: Verify Auto-Created Leave Quota

**Endpoint**: `GET /api/leave-quotas`

**Request**:
```bash
curl "http://localhost:8000/api/leave-quotas?employee_id=1&year=2024"
```

**Expected Response**:
```json
[
  {
    "id": 1,
    "employee_id": 1,
    "year": 2024,
    "annual_leave_quota": 12,
    "annual_leave_used": 0,
    "sick_leave_quota": 12,
    "sick_leave_used": 0,
    "emergency_leave_quota": 2,
    "emergency_leave_used": 0,
    "maternity_leave_quota": 90,
    "maternity_leave_used": 0,
    "paternity_leave_quota": 2,
    "paternity_leave_used": 0,
    "marriage_leave_quota": 3,
    "marriage_leave_used": 0,
    "bereavement_leave_quota": 3,
    "bereavement_leave_used": 0,
    "created_at": "2024-12-XX",
    "updated_at": "2024-12-XX",
    "employee": {
      "id": 1,
      "nama_lengkap": "Test Employee Auto Quota",
      "jabatan_saat_ini": "Software Developer"
    }
  }
]
```

### Step 3: Test HR Can Edit Default Quota

**Endpoint**: `PUT /api/leave-quotas/{id}`

**Request**:
```bash
curl -X PUT http://localhost:8000/api/leave-quotas/1 \
  -H "Content-Type: application/json" \
  -d '{
    "annual_leave_quota": 15,
    "sick_leave_quota": 10,
    "emergency_leave_quota": 3
  }'
```

**Expected Response**:
```json
{
  "message": "Leave quota updated successfully",
  "leave_quota": {
    "id": 1,
    "employee_id": 1,
    "year": 2024,
    "annual_leave_quota": 15,
    "annual_leave_used": 0,
    "sick_leave_quota": 10,
    "sick_leave_used": 0,
    "emergency_leave_quota": 3,
    "emergency_leave_used": 0,
    "updated_at": "2024-12-XX"
  }
}
```

### Step 4: Test Employee Can Request Leave Immediately

**Endpoint**: `POST /api/leave-requests`

**Request**:
```bash
curl -X POST http://localhost:8000/api/leave-requests \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 1,
    "leave_type": "annual",
    "start_date": "2024-12-20",
    "end_date": "2024-12-22",
    "reason": "Liburan akhir tahun"
  }'
```

**Expected Response**:
```json
{
  "message": "Leave request submitted successfully",
  "leave_request": {
    "id": 1,
    "employee_id": 1,
    "leave_type": "annual",
    "start_date": "2024-12-20",
    "end_date": "2024-12-22",
    "total_days": 3,
    "reason": "Liburan akhir tahun",
    "status": "pending",
    "created_at": "2024-12-XX"
  }
}
```

## Test Results Validation

### ✅ Success Indicators
1. **Employee Creation**: Returns `leave_quota_created: true`
2. **Auto Quota**: Leave quota exists with default values
3. **HR Editing**: Can modify quotas immediately
4. **Leave Request**: Employee can request leave right away
5. **Data Integrity**: All relationships work correctly

### ❌ Failure Indicators
1. Employee creation fails
2. No leave quota created automatically
3. Leave quota has wrong default values
4. Cannot edit quota after creation
5. Employee cannot request leave

## Performance Test

### Bulk Employee Creation
```bash
# Test creating multiple employees
for i in {1..5}; do
  curl -X POST http://localhost:8000/api/employees \
    -H "Content-Type: application/json" \
    -d "{
      \"nama_lengkap\": \"Test Employee $i\",
      \"nik\": \"123456789012345$i\",
      \"tanggal_lahir\": \"1990-01-01\",
      \"jenis_kelamin\": \"Laki-laki\",
      \"alamat\": \"Jakarta\",
      \"status_pernikahan\": \"Belum Menikah\",
      \"jabatan_saat_ini\": \"Staff\",
      \"tanggal_mulai_kerja\": \"2024-12-01\",
      \"tingkat_pendidikan\": \"S1\",
      \"gaji_pokok\": 5000000
    }"
done
```

### Verify All Quotas Created
```bash
curl "http://localhost:8000/api/leave-quotas?year=2024"
```

## Error Scenarios

### Test 1: Invalid Employee Data
```bash
curl -X POST http://localhost:8000/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "nama_lengkap": "",
    "nik": "invalid"
  }'
```
**Expected**: Validation error, no employee or quota created

### Test 2: Duplicate NIK
```bash
curl -X POST http://localhost:8000/api/employees \
  -H "Content-Type: application/json" \
  -d '{
    "nama_lengkap": "Duplicate Test",
    "nik": "9876543210123456"
  }'
```
**Expected**: Duplicate error, no employee or quota created

## Database Verification

### Check Employee Table
```sql
SELECT id, nama_lengkap, nik, created_at FROM employees ORDER BY id DESC LIMIT 5;
```

### Check Leave Quota Table
```sql
SELECT 
  lq.id, 
  lq.employee_id, 
  e.nama_lengkap, 
  lq.year,
  lq.annual_leave_quota,
  lq.sick_leave_quota,
  lq.created_at
FROM leave_quotas lq
JOIN employees e ON lq.employee_id = e.id
ORDER BY lq.id DESC LIMIT 5;
```

### Check Transaction Integrity
```sql
-- Verify every employee has leave quota
SELECT 
  e.id as employee_id,
  e.nama_lengkap,
  lq.id as quota_id,
  lq.year
FROM employees e
LEFT JOIN leave_quotas lq ON e.id = lq.employee_id AND lq.year = YEAR(CURDATE())
WHERE lq.id IS NULL;
```
**Expected**: No results (all employees should have quota)

---

## Quick Test Commands

### 1. Create Test Employee
```bash
curl -X POST http://localhost:8000/api/employees -H "Content-Type: application/json" -d '{"nama_lengkap":"Quick Test","nik":"1111111111111111","tanggal_lahir":"1990-01-01","jenis_kelamin":"Laki-laki","alamat":"Jakarta","status_pernikahan":"Belum Menikah","jabatan_saat_ini":"Staff","tanggal_mulai_kerja":"2024-12-01","tingkat_pendidikan":"S1","gaji_pokok":5000000}'
```

### 2. Check Auto Quota
```bash
curl "http://localhost:8000/api/leave-quotas?year=2024" | grep -A 20 "Quick Test"
```

### 3. Test Leave Request
```bash
curl -X POST http://localhost:8000/api/leave-requests -H "Content-Type: application/json" -d '{"employee_id":1,"leave_type":"annual","start_date":"2024-12-25","end_date":"2024-12-25","reason":"Test"}'
```

---

**Status**: 🧪 **READY FOR TESTING**

**Test Environment**: Local Development
**Last Updated**: December 2024