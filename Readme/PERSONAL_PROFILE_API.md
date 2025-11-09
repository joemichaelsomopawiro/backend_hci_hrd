# PERSONAL PROFILE API DOCUMENTATION

## Overview
API untuk melihat dan mengupdate data profil pribadi employee. Semua role (HR, GA, Manager, Employee) dapat menggunakan endpoint ini untuk melihat data lengkap mereka sendiri.

## Base URL
```
/api/personal
```

## Authentication
**Tidak memerlukan autentikasi** - endpoint ini dapat diakses langsung dengan parameter `employee_id`.

## Endpoints

### 1. Get Personal Profile

**GET** `/api/personal/profile`

Mengambil data profil lengkap employee berdasarkan `employee_id`.

#### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `employee_id` | integer | ✅ | ID employee dari tabel employees |

#### Response Success (200)
```json
{
    "success": true,
    "message": "Profile data retrieved successfully",
    "data": {
        "basic_info": {
            "id": 1,
            "nama_lengkap": "John Doe",
            "nik": "123456789",
            "nip": "NIP123456",
            "tanggal_lahir": "1990-01-01",
            "jenis_kelamin": "Laki-laki",
            "alamat": "Jl. Contoh No. 123",
            "status_pernikahan": "Menikah",
            "jabatan_saat_ini": "Employee",
            "tanggal_mulai_kerja": "2020-01-01",
            "tingkat_pendidikan": "S1",
            "gaji_pokok": 5000000,
            "tunjangan": 500000,
            "bonus": 1000000,
            "nomor_bpjs_kesehatan": "BPJS123456",
            "nomor_bpjs_ketenagakerjaan": "BPJSTK123456",
            "npwp": "12.345.678.9-123.456",
            "nomor_kontrak": "KONTRAK001",
            "tanggal_kontrak_berakhir": "2025-12-31",
            "created_from": "manual",
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        "user_info": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@company.com",
            "phone": "081234567890",
            "role": "Employee",
            "profile_picture": "/storage/profile/john-doe.jpg",
            "phone_verified_at": "2024-01-01T00:00:00.000000Z",
            "created_at": "2024-01-01T00:00:00.000000Z"
        },
        "documents": [
            {
                "id": 1,
                "document_type": "KTP",
                "file_path": "/storage/documents/ktp-john-doe.pdf",
                "created_at": "2024-01-01T00:00:00.000000Z"
            }
        ],
        "employment_histories": [
            {
                "id": 1,
                "company_name": "PT Sebelumnya",
                "position": "Staff",
                "start_date": "2018-01-01",
                "end_date": "2019-12-31",
                "created_at": "2024-01-01T00:00:00.000000Z"
            }
        ],
        "promotion_histories": [
            {
                "id": 1,
                "position": "Senior Staff",
                "promotion_date": "2022-01-01",
                "created_at": "2024-01-01T00:00:00.000000Z"
            }
        ],
        "trainings": [
            {
                "id": 1,
                "training_name": "Leadership Training",
                "institution": "Training Center",
                "completion_date": "2023-06-01",
                "certificate_number": "CERT001",
                "created_at": "2024-01-01T00:00:00.000000Z"
            }
        ],
        "benefits": [
            {
                "id": 1,
                "benefit_type": "Asuransi Kesehatan",
                "amount": 500000,
                "start_date": "2020-01-01",
                "created_at": "2024-01-01T00:00:00.000000Z"
            }
        ],
        "current_leave_quota": {
            "id": 1,
            "year": 2025,
            "annual_leave_quota": 12,
            "annual_leave_used": 3,
            "annual_leave_remaining": 9,
            "sick_leave_quota": 12,
            "sick_leave_used": 1,
            "sick_leave_remaining": 11,
            "emergency_leave_quota": 2,
            "emergency_leave_used": 0,
            "emergency_leave_remaining": 2,
            "maternity_leave_quota": 0,
            "maternity_leave_used": 0,
            "maternity_leave_remaining": 0,
            "paternity_leave_quota": 2,
            "paternity_leave_used": 0,
            "paternity_leave_remaining": 2,
            "marriage_leave_quota": 2,
            "marriage_leave_used": 0,
            "marriage_leave_remaining": 2,
            "bereavement_leave_quota": 2,
            "bereavement_leave_used": 0,
            "bereavement_leave_remaining": 2
        },
        "recent_leave_requests": [
            {
                "id": 1,
                "leave_type": "annual",
                "start_date": "2025-07-01",
                "end_date": "2025-07-03",
                "reason": "Liburan keluarga",
                "overall_status": "approved",
                "created_at": "2025-06-15T00:00:00.000000Z"
            }
        ],
        "statistics": {
            "total_documents": 5,
            "total_employment_histories": 2,
            "total_promotions": 1,
            "total_trainings": 3,
            "total_benefits": 2,
            "total_leave_requests": 8,
            "years_of_service": 5
        }
    }
}
```

#### Response Error (404)
```json
{
    "success": false,
    "message": "Employee not found"
}
```

#### Response Error (422)
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "employee_id": ["The employee id field is required."]
    }
}
```

### 2. Update Personal Profile

**PUT** `/api/personal/profile`

Mengupdate data profil employee (hanya field yang diizinkan).

#### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `employee_id` | integer | ✅ | ID employee dari tabel employees |
| `alamat` | string | ❌ | Alamat baru (max 500 karakter) |
| `nomor_bpjs_kesehatan` | string | ❌ | Nomor BPJS Kesehatan (max 20 karakter) |
| `nomor_bpjs_ketenagakerjaan` | string | ❌ | Nomor BPJS Ketenagakerjaan (max 20 karakter) |
| `npwp` | string | ❌ | Nomor NPWP (max 20 karakter) |

#### Request Body
```json
{
    "employee_id": 1,
    "alamat": "Jl. Baru No. 456",
    "nomor_bpjs_kesehatan": "BPJS654321",
    "npwp": "12.345.678.9-654.321"
}
```

#### Response Success (200)
```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "id": 1,
        "nama_lengkap": "John Doe",
        "alamat": "Jl. Baru No. 456",
        "nomor_bpjs_kesehatan": "BPJS654321",
        "npwp": "12.345.678.9-654.321",
        "updated_at": "2025-07-08T09:30:00.000000Z"
    }
}
```

## Field yang Dapat Diupdate

Untuk keamanan, hanya field berikut yang dapat diupdate oleh employee:

1. **alamat** - Alamat tempat tinggal
2. **nomor_bpjs_kesehatan** - Nomor BPJS Kesehatan
3. **nomor_bpjs_ketenagakerjaan** - Nomor BPJS Ketenagakerjaan
4. **npwp** - Nomor Pokok Wajib Pajak

Field lain seperti nama, NIK, jabatan, gaji, dll hanya dapat diupdate oleh HR/Admin.

## Data yang Ditampilkan

### 1. Basic Info
- Semua field dari tabel employees
- Informasi personal dan pekerjaan

### 2. User Info
- Data user account (email, phone, role)
- Profile picture
- Status verifikasi

### 3. Documents
- Dokumen pribadi (KTP, KK, dll)
- File path untuk download

### 4. Employment Histories
- Riwayat pekerjaan sebelumnya
- Posisi dan periode kerja

### 5. Promotion Histories
- Riwayat promosi
- Tanggal promosi

### 6. Trainings
- Training yang pernah diikuti
- Sertifikat dan institusi

### 7. Benefits
- Benefit yang diterima
- Jumlah dan periode

### 8. Current Leave Quota
- Jatah cuti tahun berjalan
- Cuti yang sudah digunakan
- Sisa cuti yang tersedia

### 9. Recent Leave Requests
- 5 pengajuan cuti terbaru
- Status approval

### 10. Statistics
- Total dokumen, riwayat, training
- Lama bekerja dalam tahun

## Contoh Penggunaan Frontend

### JavaScript/Fetch
```javascript
// Get profile data
async function getProfile(employeeId) {
    try {
        const response = await fetch(`/api/personal/profile?employee_id=${employeeId}`);
        const data = await response.json();
        
        if (data.success) {
            console.log('Profile data:', data.data);
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('Network error:', error);
        return null;
    }
}

// Update profile
async function updateProfile(employeeId, updateData) {
    try {
        const response = await fetch('/api/personal/profile', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                employee_id: employeeId,
                ...updateData
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Profile updated:', data.data);
            return data.data;
        } else {
            console.error('Error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('Network error:', error);
        return null;
    }
}

// Usage
const profile = await getProfile(1);
if (profile) {
    console.log('Name:', profile.basic_info.nama_lengkap);
    console.log('Position:', profile.basic_info.jabatan_saat_ini);
    console.log('Leave remaining:', profile.current_leave_quota.annual_leave_remaining);
}

// Update address
const updated = await updateProfile(1, {
    alamat: 'Jl. Baru No. 789'
});
```

### Vue.js Example
```vue
<template>
  <div class="profile-page">
    <div v-if="loading">Loading...</div>
    <div v-else-if="profile">
      <h1>{{ profile.basic_info.nama_lengkap }}</h1>
      <p>Position: {{ profile.basic_info.jabatan_saat_ini }}</p>
      <p>Address: {{ profile.basic_info.alamat }}</p>
      
      <!-- Leave Quota -->
      <div class="leave-quota">
        <h3>Leave Quota ({{ profile.current_leave_quota.year }})</h3>
        <p>Annual Leave: {{ profile.current_leave_quota.annual_leave_remaining }}/{{ profile.current_leave_quota.annual_leave_quota }}</p>
        <p>Sick Leave: {{ profile.current_leave_quota.sick_leave_remaining }}/{{ profile.current_leave_quota.sick_leave_quota }}</p>
      </div>
      
      <!-- Recent Leave Requests -->
      <div class="recent-leaves">
        <h3>Recent Leave Requests</h3>
        <div v-for="leave in profile.recent_leave_requests" :key="leave.id">
          <p>{{ leave.leave_type }}: {{ leave.start_date }} - {{ leave.end_date }} ({{ leave.overall_status }})</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      profile: null,
      loading: true
    }
  },
  async mounted() {
    const employeeId = this.$store.state.user.employee_id; // Get from store
    this.profile = await this.getProfile(employeeId);
    this.loading = false;
  },
  methods: {
    async getProfile(employeeId) {
      // Implementation as above
    }
  }
}
</script>
```

## Error Handling

### Common Errors
1. **Employee not found** - employee_id tidak valid
2. **Validation error** - parameter tidak sesuai format
3. **Server error** - masalah internal server

### Best Practices
1. **Always check success flag** sebelum menggunakan data
2. **Handle network errors** dengan try-catch
3. **Show loading state** saat request
4. **Validate input** sebelum update
5. **Show user-friendly messages** untuk error

## Security Notes

1. **Field restrictions** - Hanya field tertentu yang bisa diupdate
2. **No authentication required** - Gunakan employee_id untuk identifikasi
3. **Input validation** - Semua input divalidasi
4. **Logging** - Semua error dicatat untuk monitoring 