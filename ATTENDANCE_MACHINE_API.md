# Attendance Machine Management API

## 📋 Overview

API untuk mengelola mesin absensi Solution X304, termasuk operasi CRUD, sinkronisasi data, dan monitoring.

## 🔐 Authentication

Semua endpoint memerlukan autentikasi dengan middleware `auth:sanctum`.

## 📡 Base URL

```
POST /api/attendance-machines
```

## 🚀 API Endpoints

### 1. CRUD Operations

#### GET /api/attendance-machines
**Mendapatkan semua mesin absensi**

```bash
curl -X GET "http://localhost:8000/api/attendance-machines" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "X304 Main Office",
      "ip_address": "10.10.10.85",
      "port": 80,
      "comm_key": 12345,
      "serial_number": "X304-001",
      "model": "Solution X304",
      "location": "Lobby Utama",
      "description": "Mesin absensi utama",
      "is_active": true,
      "sync_logs": [...]
    }
  ],
  "message": "Attendance machines retrieved successfully"
}
```

#### POST /api/attendance-machines
**Membuat mesin absensi baru**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "X304 Branch Office",
    "ip_address": "192.168.1.100",
    "port": 80,
    "comm_key": 54321,
    "serial_number": "X304-002",
    "model": "Solution X304",
    "location": "Cabang Jakarta",
    "description": "Mesin absensi cabang",
    "is_active": true
  }'
```

**Request Body:**
```json
{
  "name": "string (required)",
  "ip_address": "string (required, valid IP)",
  "port": "integer (required, 1-65535)",
  "comm_key": "integer (required, min:1)",
  "serial_number": "string (required, unique)",
  "model": "string (optional)",
  "location": "string (optional)",
  "description": "string (optional)",
  "is_active": "boolean (optional)"
}
```

#### GET /api/attendance-machines/{id}
**Mendapatkan detail mesin absensi**

```bash
curl -X GET "http://localhost:8000/api/attendance-machines/1" \
  -H "Authorization: Bearer {token}"
```

#### PUT /api/attendance-machines/{id}
**Update mesin absensi**

```bash
curl -X PUT "http://localhost:8000/api/attendance-machines/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "X304 Updated Name",
    "is_active": false
  }'
```

#### DELETE /api/attendance-machines/{id}
**Hapus mesin absensi**

```bash
curl -X DELETE "http://localhost:8000/api/attendance-machines/1" \
  -H "Authorization: Bearer {token}"
```

### 2. Machine Operations

#### POST /api/attendance-machines/{id}/test-connection
**Test koneksi ke mesin**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/test-connection" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Koneksi berhasil"
}
```

#### POST /api/attendance-machines/{id}/pull-attendance
**Tarik data absensi dari mesin (semua data)**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/pull-attendance" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Berhasil memproses 150 data absensi",
  "data": [...]
}
```

#### POST /api/attendance-machines/{id}/pull-attendance-process
**Tarik dan proses data absensi**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/pull-attendance-process" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil ditarik dan diproses",
  "data": {
    "pull_result": {...},
    "process_result": {...}
  }
}
```

#### POST /api/attendance-machines/{id}/pull-today
**Tarik data absensi hari ini (optimized)**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/pull-today" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2025-01-27"
  }'
```

**Request Body (optional):**
```json
{
  "date": "string (optional, format: Y-m-d, default: today)"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Data hari ini berhasil ditarik dan diproses",
  "data": {
    "pull_result": {
      "stats": {
        "total_from_machine": 500,
        "today_filtered": 25,
        "processed": 25
      }
    },
    "process_result": {...},
    "target_date": "2025-01-27"
  }
}
```

### 3. User Synchronization

#### POST /api/attendance-machines/{id}/sync-user/{employeeId}
**Sync user tertentu ke mesin**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/sync-user/123" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "User John Doe berhasil disync ke mesin",
  "data": {
    "employee_id": 123,
    "machine_user_id": "EMP001",
    "name": "John Doe"
  }
}
```

#### POST /api/attendance-machines/{id}/sync-all-users
**Sync semua user ke mesin**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/sync-all-users" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Sync selesai. Berhasil: 45, Gagal: 2",
  "data": {
    "total_employees": 47,
    "success_count": 45,
    "failed_count": 2,
    "sync_results": {...}
  }
}
```

#### DELETE /api/attendance-machines/{id}/remove-user/{employeeId}
**Hapus user dari mesin**

```bash
curl -X DELETE "http://localhost:8000/api/attendance-machines/1/remove-user/123" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "User berhasil dihapus dari mesin"
}
```

#### GET /api/attendance-machines/{id}/users
**Dapatkan daftar user yang terdaftar di mesin**

```bash
curl -X GET "http://localhost:8000/api/attendance-machines/1/users" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "attendance_machine_id": 1,
        "machine_user_id": "EMP001",
        "name": "John Doe",
        "card_number": "123456",
        "privilege": "User",
        "group_name": "Employee",
        "is_active": true,
        "last_seen_at": "2025-01-27T08:30:00.000000Z"
      }
    ],
    "current_page": 1,
    "per_page": 50,
    "total": 45
  },
  "message": "Machine users retrieved successfully"
}
```

### 4. Machine Management

#### POST /api/attendance-machines/{id}/restart
**Restart mesin absensi**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/restart" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Mesin berhasil di-restart"
}
```

#### POST /api/attendance-machines/{id}/clear-data
**Hapus data absensi dari mesin**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/clear-data" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Data absensi berhasil dihapus dari mesin"
}
```

#### POST /api/attendance-machines/{id}/sync-time
**Sync waktu dengan mesin**

```bash
curl -X POST "http://localhost:8000/api/attendance-machines/1/sync-time" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "message": "Waktu berhasil disync: 2025-01-27 14:30:00"
}
```

### 5. Monitoring & Logs

#### GET /api/attendance-machines/{id}/sync-logs
**Dapatkan log sinkronisasi mesin**

```bash
curl -X GET "http://localhost:8000/api/attendance-machines/1/sync-logs" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "machine_id": 1,
        "operation": "pull_today_data",
        "status": "success",
        "message": "Berhasil memproses 25 data absensi untuk 2025-01-27",
        "records_processed": 25,
        "metadata": {...},
        "created_at": "2025-01-27T14:30:00.000000Z"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 150
  },
  "message": "Sync logs retrieved successfully"
}
```

#### GET /api/attendance-machines/dashboard
**Dashboard untuk semua mesin**

```bash
curl -X GET "http://localhost:8000/api/attendance-machines/dashboard" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_machines": 3,
    "active_machines": 2,
    "inactive_machines": 1,
    "machines": [
      {
        "id": 1,
        "name": "X304 Main Office",
        "ip_address": "10.10.10.85",
        "is_active": true,
        "last_sync": "2025-01-27T14:30:00.000000Z",
        "last_sync_status": "success",
        "total_sync_logs": 150
      }
    ]
  },
  "message": "Dashboard data retrieved successfully"
}
```

## 🔧 Error Handling

### Common Error Responses

#### 404 - Machine Not Found
```json
{
  "success": false,
  "message": "Attendance machine not found"
}
```

#### 422 - Validation Error
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "ip_address": ["The ip address field is required."],
    "comm_key": ["The comm key must be at least 1."]
  }
}
```

#### 500 - Server Error
```json
{
  "success": false,
  "message": "Terjadi kesalahan saat mengambil data mesin absensi"
}
```

## 📊 Usage Examples

### Frontend Integration (JavaScript)

```javascript
// Test connection
async function testConnection(machineId) {
  try {
    const response = await fetch(`/api/attendance-machines/${machineId}/test-connection`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    const data = await response.json();
    if (data.success) {
      console.log('Connection successful');
    } else {
      console.error('Connection failed:', data.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

// Pull today's data
async function pullTodayData(machineId, date = null) {
  try {
    const body = date ? { date } : {};
    const response = await fetch(`/api/attendance-machines/${machineId}/pull-today`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(body)
    });
    
    const data = await response.json();
    if (data.success) {
      console.log('Today data pulled successfully:', data.data);
    } else {
      console.error('Pull failed:', data.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

// Get machine users
async function getMachineUsers(machineId) {
  try {
    const response = await fetch(`/api/attendance-machines/${machineId}/users`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    const data = await response.json();
    if (data.success) {
      console.log('Machine users:', data.data);
    } else {
      console.error('Failed to get users:', data.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}
```

### Vue.js Integration

```vue
<template>
  <div>
    <h2>Attendance Machine Management</h2>
    
    <!-- Machine List -->
    <div v-for="machine in machines" :key="machine.id">
      <h3>{{ machine.name }}</h3>
      <p>IP: {{ machine.ip_address }}</p>
      <p>Status: {{ machine.is_active ? 'Active' : 'Inactive' }}</p>
      
      <!-- Action Buttons -->
      <button @click="testConnection(machine.id)">Test Connection</button>
      <button @click="pullTodayData(machine.id)">Pull Today Data</button>
      <button @click="getUsers(machine.id)">View Users</button>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      machines: [],
      token: localStorage.getItem('token')
    }
  },
  
  async mounted() {
    await this.loadMachines();
  },
  
  methods: {
    async loadMachines() {
      try {
        const response = await fetch('/api/attendance-machines', {
          headers: {
            'Authorization': `Bearer ${this.token}`
          }
        });
        
        const data = await response.json();
        if (data.success) {
          this.machines = data.data;
        }
      } catch (error) {
        console.error('Error loading machines:', error);
      }
    },
    
    async testConnection(machineId) {
      try {
        const response = await fetch(`/api/attendance-machines/${machineId}/test-connection`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${this.token}`
          }
        });
        
        const data = await response.json();
        if (data.success) {
          alert('Connection successful!');
        } else {
          alert(`Connection failed: ${data.message}`);
        }
      } catch (error) {
        console.error('Error:', error);
      }
    },
    
    async pullTodayData(machineId) {
      try {
        const response = await fetch(`/api/attendance-machines/${machineId}/pull-today`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
          }
        });
        
        const data = await response.json();
        if (data.success) {
          alert('Today data pulled successfully!');
        } else {
          alert(`Pull failed: ${data.message}`);
        }
      } catch (error) {
        console.error('Error:', error);
      }
    },
    
    async getUsers(machineId) {
      try {
        const response = await fetch(`/api/attendance-machines/${machineId}/users`, {
          headers: {
            'Authorization': `Bearer ${this.token}`
          }
        });
        
        const data = await response.json();
        if (data.success) {
          console.log('Machine users:', data.data);
        } else {
          alert(`Failed to get users: ${data.message}`);
        }
      } catch (error) {
        console.error('Error:', error);
      }
    }
  }
}
</script>
```

## 🎯 Key Features

### ✅ **Optimized Operations**
- **Pull Today Data**: Hanya menarik data hari ini (lebih cepat)
- **Connection Testing**: Test koneksi sebelum operasi
- **Error Handling**: Comprehensive error handling dan logging

### ✅ **User Management**
- **Sync Specific User**: Sync user tertentu ke mesin
- **Sync All Users**: Sync semua employee aktif
- **Remove User**: Hapus user dari mesin
- **View Users**: Lihat daftar user di mesin

### ✅ **Machine Control**
- **Restart Machine**: Restart mesin absensi
- **Clear Data**: Hapus data absensi dari mesin
- **Sync Time**: Sync waktu dengan mesin

### ✅ **Monitoring**
- **Sync Logs**: Log semua operasi sinkronisasi
- **Dashboard**: Overview semua mesin
- **Real-time Status**: Status koneksi dan operasi

## 🔄 Integration dengan Backend Existing

Controller ini terintegrasi dengan:

1. **AttendanceMachineService** - Untuk operasi mesin
2. **AttendanceProcessingService** - Untuk proses data absensi
3. **Employee Model** - Untuk sinkronisasi user
4. **AttendanceSyncLog** - Untuk logging operasi

Semua operasi menggunakan protokol SOAP/HTTP yang sesuai dengan mesin Solution X304. 