# Panduan Skip Fetch Endpoint untuk Role Tertentu

## 📋 Daftar Role yang Tidak Perlu Fetch Endpoint Tertentu

### 1. **Leave Quotas (`/api/leave-quotas/my-current`)**

**Role yang TIDAK punya leave quota** (tidak perlu fetch):
- `HR` / `HR Manager`
- `Program Manager`
- `Distribution Manager`
- `VP President`
- `President Director`

**Alasan**: Role ini tidak bisa mengajukan cuti di sistem, jadi tidak punya leave quota.

### 2. **Today Status (`/api/personal/today-status`)**

**Endpoint ini TIDAK ADA di backend** - harus dihapus dari semua role.

**Alasan**: Endpoint ini tidak pernah dibuat di backend.

---

## 🔧 Implementasi di Frontend

### 1. Profile.vue - Skip Fetch Leave Quotas

**File**: `Profile.vue` (sekitar line 414 dan 1006)

```javascript
// Tambahkan di bagian script
const rolesWithoutLeaveQuota = [
  'HR',
  'HR Manager',
  'Program Manager',
  'Distribution Manager',
  'VP President',
  'President Director'
];

// Fungsi untuk fetch leave quotas
async function fetchLeaveQuotas() {
  const user = authStore.user; // atau cara lain untuk mendapatkan user
  
  // Skip fetch jika role tidak punya leave quota
  if (rolesWithoutLeaveQuota.includes(user?.role)) {
    console.log('Skip fetch leave-quotas untuk role:', user?.role);
    // Set empty quotas langsung tanpa fetch
    return {
      success: true,
      data: {
        annual_leave_quota: 0,
        annual_leave_used: 0,
        annual_leave_remaining: 0,
        sick_leave_quota: 0,
        sick_leave_used: 0,
        sick_leave_remaining: 0,
        emergency_leave_quota: 0,
        emergency_leave_used: 0,
        emergency_leave_remaining: 0,
        // ... quota lainnya = 0
      }
    };
  }
  
  // Lanjutkan fetch untuk role lain
  try {
    const response = await api.get('/leave-quotas/my-current');
    return response.data;
  } catch (error) {
    if (error.response?.status === 404) {
      console.warn('Leave quotas endpoint not found (404) - Setting empty quotas');
      return {
        success: true,
        data: {
          annual_leave_quota: 0,
          annual_leave_used: 0,
          annual_leave_remaining: 0,
          // ... quota lainnya = 0
        }
      };
    }
    console.error('Error fetching leave quotas:', error);
    return { success: false, data: null };
  }
}
```

**Update di mounted/created**:
```javascript
async mounted() {
  // ... kode lainnya
  
  // Fetch leave quotas hanya jika role memerlukan
  if (!rolesWithoutLeaveQuota.includes(this.user?.role)) {
    this.leaveQuotas = await this.fetchLeaveQuotas();
  } else {
    // Set empty untuk role yang tidak punya quota
    this.leaveQuotas = {
      annual_leave_quota: 0,
      annual_leave_used: 0,
      annual_leave_remaining: 0,
      // ... quota lainnya = 0
    };
  }
}
```

### 2. employeeService.js - Hapus Fetch Today Status

**File**: `employeeService.js` (sekitar line 131)

**HAPUS atau COMMENT** kode berikut:
```javascript
// ❌ HAPUS KODE INI - Endpoint tidak ada
async function getTodayStatus(employeeId) {
  try {
    const response = await api.get(`/personal/today-status?employee_id=${employeeId}`);
    return response.data;
  } catch (error) {
    if (error.response?.status === 404) {
      console.warn('⚠️ Today status endpoint not found (404) - Endpoint mungkin tidak tersedia untuk role ini');
      return null;
    }
    console.error('Error fetching today status:', error);
    return null;
  }
}
```

**ATAU** jika masih diperlukan, skip untuk semua role:
```javascript
// ✅ SKIP SEMUA - Endpoint tidak ada di backend
async function getTodayStatus(employeeId) {
  // Endpoint /api/personal/today-status tidak ada di backend
  // Skip fetch untuk semua role
  console.log('Skip fetch today-status - Endpoint tidak tersedia');
  return null;
}
```

### 3. Update Semua Tempat yang Fetch Today Status

**Cari dan HAPUS/COMMENT** semua pemanggilan:
```javascript
// ❌ HAPUS/COMMENT
const todayStatus = await employeeService.getTodayStatus(employeeId);

// ✅ GANTI DENGAN
// const todayStatus = null; // Endpoint tidak tersedia
```

---

## 📝 Template Helper Function

Buat helper function untuk cek role:

```javascript
// utils/roleHelper.js
export const rolesWithoutLeaveQuota = [
  'HR',
  'HR Manager',
  'Program Manager',
  'Distribution Manager',
  'VP President',
  'President Director'
];

export const rolesWithoutLeaveRequests = [
  'HR',
  'HR Manager',
  'Program Manager',
  'Distribution Manager',
  'VP President',
  'President Director'
];

export function shouldFetchLeaveQuota(userRole) {
  return !rolesWithoutLeaveQuota.includes(userRole);
}

export function shouldFetchLeaveRequests(userRole) {
  return !rolesWithoutLeaveRequests.includes(userRole);
}

export function isManagerRole(userRole) {
  return [
    'HR',
    'HR Manager',
    'Program Manager',
    'Distribution Manager',
    'VP President',
    'President Director'
  ].includes(userRole);
}
```

**Penggunaan**:
```javascript
import { shouldFetchLeaveQuota, shouldFetchLeaveRequests } from '@/utils/roleHelper';

// Di component
if (shouldFetchLeaveQuota(this.user?.role)) {
  this.leaveQuotas = await this.fetchLeaveQuotas();
} else {
  this.leaveQuotas = { /* empty quotas */ };
}
```

---

## ✅ Checklist Perbaikan

- [ ] **Profile.vue line 414**: Skip fetch leave-quotas untuk role manager
- [ ] **Profile.vue line 1006**: Skip fetch leave-quotas untuk role manager
- [ ] **employeeService.js line 131**: Hapus/comment fetch today-status
- [ ] **Semua tempat yang fetch today-status**: Hapus/comment
- [ ] **Test dengan login Program Manager**: Tidak ada error 404 di console
- [ ] **Test dengan login HR**: Tidak ada error 404 di console
- [ ] **Test dengan login Employee**: Fetch berjalan normal

---

## 🧪 Testing

### Test Case 1: Login sebagai Program Manager
- ✅ Tidak ada error 404 untuk `/api/leave-quotas/my-current`
- ✅ Tidak ada error 404 untuk `/api/personal/today-status`
- ✅ Leave quotas ditampilkan sebagai 0 atau tidak ditampilkan

### Test Case 2: Login sebagai HR
- ✅ Tidak ada error 404 untuk `/api/leave-quotas/my-current`
- ✅ Tidak ada error 404 untuk `/api/personal/today-status`
- ✅ Leave quotas ditampilkan sebagai 0 atau tidak ditampilkan

### Test Case 3: Login sebagai Employee (Producer, Creative, dll)
- ✅ Fetch leave-quotas berjalan normal
- ✅ Tidak ada error 404
- ✅ Data ditampilkan dengan benar

---

## 📌 Catatan Penting

1. **Endpoint `/api/personal/today-status` TIDAK ADA** - harus dihapus dari semua role
2. **Role Manager tidak punya leave quota** - skip fetch untuk menghindari 404
3. **Gunakan helper function** untuk konsistensi di seluruh aplikasi
4. **Set empty data** untuk role yang tidak perlu fetch, jangan biarkan error 404

---

## 🔗 Referensi

- Role yang tidak bisa submit cuti: `app/Http/Controllers/LeaveRequestController.php` line 305-312
- Leave quota endpoint: `routes/api.php` line 116
- Personal profile endpoint: `routes/api.php` line 388

