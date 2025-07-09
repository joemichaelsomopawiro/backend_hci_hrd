# Employee Status Check API

## Endpoint: `GET /api/auth/check-employee-status`

Endpoint ini digunakan untuk mengecek apakah user yang sedang login masih terdaftar sebagai karyawan di sistem.

### 🔐 **Authentication**
- **Required**: Bearer Token (Sanctum)
- **Middleware**: `auth:sanctum`

### 📝 **Request**
```http
GET /api/auth/check-employee-status
Authorization: Bearer {token}
```

### ✅ **Success Response (200)**
```json
{
  "success": true,
  "data": {
    "employee_id": 123,
    "status": "active",
    "name": "John Doe",
    "jabatan": "HR",
    "nik": "1234567890123456",
    "nip": "198501012010012001",
    "tanggal_mulai_kerja": "2020-01-15",
    "manager_id": 1
  }
}
```

### ❌ **Error Responses**

#### **401 Unauthorized**
```json
{
  "success": false,
  "message": "User tidak ditemukan",
  "code": "USER_NOT_FOUND"
}
```

#### **403 Forbidden**
```json
{
  "success": false,
  "message": "Maaf, Anda sudah tidak terdaftar sebagai karyawan Hope Channel Indonesia",
  "code": "EMPLOYEE_NOT_FOUND"
}
```

#### **500 Internal Server Error**
```json
{
  "success": false,
  "message": "Gagal mengecek status employee",
  "code": "API_ERROR",
  "error": "Error detail message"
}
```

### 🔧 **Implementation Details**

#### **Controller Method**
```php
public function checkEmployeeStatus(Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan',
                'code' => 'USER_NOT_FOUND'
            ], 401);
        }
        
        // Cek apakah user masih ada di tabel employee
        $employee = Employee::where('id', $user->employee_id)->first();
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, Anda sudah tidak terdaftar sebagai karyawan Hope Channel Indonesia',
                'code' => 'EMPLOYEE_NOT_FOUND'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'employee_id' => $employee->id,
                'status' => 'active',
                'name' => $employee->nama_lengkap,
                'jabatan' => $employee->jabatan_saat_ini,
                'nik' => $employee->nik,
                'nip' => $employee->nip,
                'tanggal_mulai_kerja' => $employee->tanggal_mulai_kerja,
                'manager_id' => $employee->manager_id
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengecek status employee',
            'code' => 'API_ERROR',
            'error' => $e->getMessage()
        ], 500);
    }
}
```

#### **Route Definition**
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/check-employee-status', [AuthController::class, 'checkEmployeeStatus']);
});
```

### 🧪 **Testing**

#### **Test Cases**

1. **User yang masih aktif di tabel employee**
   - Expected: 200 OK dengan data employee

2. **User yang sudah dihapus dari tabel employee**
   - Expected: 403 Forbidden dengan pesan "Maaf, Anda sudah tidak terdaftar sebagai karyawan Hope Channel Indonesia"

3. **User yang tidak login**
   - Expected: 401 Unauthorized

#### **cURL Example**
```bash
curl -X GET "http://localhost:8000/api/auth/check-employee-status" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### 🔄 **Frontend Integration**

#### **JavaScript Example**
```javascript
async function checkEmployeeStatus() {
  try {
    const response = await fetch('/api/auth/check-employee-status', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });

    const data = await response.json();

    if (!response.ok) {
      if (response.status === 403) {
        // Employee tidak ditemukan - redirect ke logout
        localStorage.removeItem('token');
        window.location.href = '/login?message=employee_not_found';
        return;
      }
      throw new Error(data.message);
    }

    return data.data;
  } catch (error) {
    console.error('Error checking employee status:', error);
    throw error;
  }
}
```

### 📋 **Use Cases**

1. **Pre-flight Check**: Sebelum mengakses fitur tertentu, cek status employee
2. **Auto Logout**: Jika employee sudah tidak terdaftar, otomatis logout
3. **Dashboard Validation**: Validasi status employee saat membuka dashboard
4. **Permission Check**: Cek apakah user masih berhak mengakses sistem

### 🔒 **Security Considerations**

- Endpoint dilindungi dengan authentication middleware
- Hanya user yang sudah login yang bisa mengakses
- Response tidak mengekspos informasi sensitif
- Error handling yang aman tanpa mengekspos detail sistem

### 📊 **Monitoring**

- Log semua request ke endpoint ini
- Monitor response time
- Track error rates (403, 500)
- Alert jika ada peningkatan error rate 