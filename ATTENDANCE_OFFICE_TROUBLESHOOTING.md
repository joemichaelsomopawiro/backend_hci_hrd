# AttendanceOffice.vue Troubleshooting Guide

## Masalah: Tabel Absensi Tidak Muncul untuk Program Manager

### 🔍 Quick Diagnosis

#### Step 1: Buka Browser Console
1. Tekan `F12` atau `Ctrl+Shift+I`
2. Pergi ke tab `Console`
3. Refresh page AttendanceOffice
4. Perhatikan log messages yang muncul

#### Step 2: Jalankan Debug Script
1. Copy paste kode dari `debug_attendance_office.js` ke console
2. Atau jalankan: `debugAttendanceOffice.runAll()`
3. Perhatikan hasil debug output

### 🚨 Common Issues & Solutions

#### Issue 1: Role Detection Problem
**Gejala:** Console log menunjukkan role tidak terdeteksi sebagai `program_manager`

**Debug:** Lihat console log:
```
👤 Original role: Program Manager
👤 Normalized role: program_manager  ← Should be this
👤 Is employee? false               ← Should be false for managers
```

**Solusi:**
```javascript
// Option A: Fix role in localStorage
debugAttendanceOffice.fixRole("Program Manager")

// Option B: Check database role format
// Pastikan role di database adalah "Program Manager" bukan "program manager"
```

#### Issue 2: API Endpoint Error
**Gejala:** Console log menunjukkan API call failed

**Debug:** Lihat console log:
```
🌐 API URL: /api/attendance/monthly-table?month=1&year=2025
📡 Response status: 404  ← Error status
❌ API Failed: Endpoint not found
```

**Solusi:**
1. Check apakah API endpoint `/api/attendance/monthly-table` accessible
2. Test manual di browser: `your-domain.com/api/attendance/monthly-table?month=1&year=2025`
3. Check routes/api.php untuk memastikan route ada

#### Issue 3: No Data in Database
**Gejala:** API berhasil tapi return empty data

**Debug:** Lihat console log:
```
✅ API Success - Records: 0        ← No records
✅ API Success - Working days: 5
🎯 Final monthlyTable.data length: 0
```

**Solusi:**
1. Check database table `attendances` ada data atau tidak
2. Run test sync: `php test_employee_sync_fixed.php`
3. Upload TXT file untuk generate data

#### Issue 4: Vue Component State Issue
**Gejala:** API berhasil, data ada, tapi table tidak render

**Debug:** 
```javascript
debugAttendanceOffice.testVue()
// Check:
// - monthlyTable.data length > 0
// - monthlyTable.loading = false
// - isEmployee = false
```

**Solusi:**
1. Hard refresh browser (Ctrl+F5)
2. Clear browser cache
3. Check conditional rendering di template

### 🔧 Manual Quick Fixes

#### Fix 1: Force Role Update
```javascript
// Run di console:
const user = JSON.parse(localStorage.getItem('user') || '{}');
user.role = 'Program Manager';
localStorage.setItem('user', JSON.stringify(user));
location.reload();
```

#### Fix 2: Manual API Test
```javascript
// Test API directly:
fetch('/api/attendance/monthly-table?month=1&year=2025')
  .then(r => r.json())
  .then(data => console.log('API Result:', data));
```

#### Fix 3: Force Data Load
```javascript
// If you have access to Vue instance:
const app = document.querySelector('#app').__vue__;
app.isEmployee = false;  // Force manager mode
app.fetchMonthlyTable(); // Force data reload
```

### 📋 Checklist Troubleshooting

**✅ Role Check:**
- [ ] User role adalah "Program Manager" 
- [ ] Normalized role adalah "program_manager"
- [ ] isEmployee = false
- [ ] hasManagerAccess() = true

**✅ API Check:**
- [ ] API URL accessible: `/api/attendance/monthly-table`
- [ ] Response status: 200
- [ ] Response success: true
- [ ] Records count > 0

**✅ Data Check:**
- [ ] monthlyTable.data.length > 0
- [ ] monthlyTable.loading = false
- [ ] workingDays.length > 0

**✅ DOM Check:**
- [ ] Table container visible
- [ ] No loading spinner stuck
- [ ] No empty state showing

### 🎯 Expected Console Output (Success)

```
🚀 AttendanceOffice mounted() started
👤 Raw user data: {role: "Program Manager", ...}
👤 Original role: Program Manager
👤 Normalized role: program_manager
👤 Is employee? false
👤 Is program_manager? true
🔑 hasManagerAccess() - has access? true
📊 Loading monthly table for manager...
📊 fetchMonthlyTable() started
🌐 API URL: /api/attendance/monthly-table?month=1&year=2025
📡 Response status: 200
✅ API Success - Records: 15
✅ API Success - Working days: 22
🎯 Final monthlyTable.data length: 15
📊 fetchMonthlyTable() completed, loading: false
🔄 Loading sync status for manager...
✅ AttendanceOffice mounted() completed
```

### 🆘 Emergency Fallback

Jika semua cara di atas tidak berhasil:

1. **Check Network Tab:**
   - Buka F12 → Network
   - Refresh page
   - Lihat ada request failed atau tidak

2. **Check Database Direct:**
   ```sql
   SELECT COUNT(*) FROM attendances;
   SELECT COUNT(*) FROM employees;
   ```

3. **Check Laravel Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Restore Previous Version:**
   - Rollback AttendanceOffice.vue ke versi sebelumnya
   - Test apakah table muncul

### 📞 Get Help

Jika masih bermasalah, provide info berikut:

1. **Console Log Output** (copy semua output debug)
2. **Network Requests** (screenshot network tab)
3. **User Role Data** (hasil `localStorage.getItem('user')`)
4. **API Response** (hasil manual API test)
5. **Database Data Count** (jumlah records di tabel attendances)

Dengan informasi ini, masalah bisa diidentifikasi dan diperbaiki dengan cepat! 🚀 