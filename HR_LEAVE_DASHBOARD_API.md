# HR Leave Dashboard API Documentation

## Overview
Dokumentasi lengkap untuk semua endpoint yang memungkinkan HR melihat dan mengelola data cuti dari semua employee.

## 🎯 Endpoints untuk HR Melihat Data Cuti

### 1. **HR Dashboard - Semua Data Cuti** ⭐ **RECOMMENDED**

**Endpoint**: `GET /api/leave-requests/hr-dashboard`

**Deskripsi**: Endpoint utama untuk HR melihat SEMUA data cuti dengan summary lengkap

**Authorization**: `Bearer Token` (Role: HR)

**Query Parameters**:
```
?year=2024              // Filter berdasarkan tahun
?month=12               // Filter berdasarkan bulan
?status=pending         // Filter berdasarkan status (pending/approved/rejected)
?leave_type=annual      // Filter berdasarkan jenis cuti
?employee_id=1          // Filter berdasarkan employee tertentu
?department=Finance     // Filter berdasarkan department/role
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 1,
      "leave_type": "annual",
      "start_date": "2024-12-20",
      "end_date": "2024-12-22",
      "total_days": 3,
      "reason": "Liburan akhir tahun",
      "status": "approved",
      "approved_by": 2,
      "approved_at": "2024-12-15T10:30:00Z",
      "created_at": "2024-12-10T09:00:00Z",
      "employee": {
        "id": 1,
        "nama_lengkap": "John Doe",
        "jabatan_saat_ini": "Software Developer",
        "user": {
          "id": 1,
          "name": "John Doe",
          "email": "john@company.com",
          "role": "Finance"
        }
      },
      "approver": {
        "id": 2,
        "nama_lengkap": "Jane Manager",
        "user": {
          "name": "Jane Manager",
          "role": "HR"
        }
      }
    }
  ],
  "summary": {
    "total_requests": 45,
    "pending_requests": 8,
    "approved_requests": 32,
    "rejected_requests": 5,
    "total_days_requested": 156,
    "total_days_approved": 128,
    "by_leave_type": {
      "annual": 25,
      "sick": 12,
      "emergency": 5,
      "maternity": 2,
      "paternity": 1,
      "marriage": 0,
      "bereavement": 0
    },
    "by_department": {
      "Finance": 15,
      "General Affairs": 8,
      "Producer": 12,
      "Creative": 10
    },
    "recent_requests": [
      // 10 request terbaru
    ]
  },
  "message": "Data semua cuti berhasil diambil untuk HR dashboard"
}
```

### 2. **Semua Data Cuti (Index dengan HR Access)**

**Endpoint**: `GET /api/leave-requests`

**Deskripsi**: Endpoint standar yang sudah dimodifikasi untuk memberikan akses penuh ke HR

**Authorization**: `Bearer Token` (Role: HR)

**Query Parameters**:
```
?employee_id=1          // Filter berdasarkan employee
?status=pending         // Filter berdasarkan status
?leave_type=annual      // Filter berdasarkan jenis cuti
```

**Response**:
```json
{
  "success": true,
  "data": [
    // Semua data leave requests dari semua employee
  ]
}
```

### 3. **Data Cuti yang Sudah Disetujui**

**Endpoint**: `GET /api/leave-requests/approved`

**Deskripsi**: Melihat semua cuti yang sudah disetujui

**Authorization**: `Bearer Token` (Role: HR)

**Query Parameters**:
```
?year=2024              // Filter berdasarkan tahun
?month=12               // Filter berdasarkan bulan
```

**Response**:
```json
{
  "success": true,
  "data": [
    // Semua cuti yang status approved
  ],
  "summary": {
    "total_approved": 32,
    "total_days": 128
  }
}
```

## 🔍 Use Cases untuk HR

### 1. **Dashboard Overview**
```bash
# Melihat semua data cuti tahun 2024
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://localhost:8000/api/leave-requests/hr-dashboard?year=2024"
```

### 2. **Monitor Cuti Pending**
```bash
# Melihat semua cuti yang masih pending
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://localhost:8000/api/leave-requests/hr-dashboard?status=pending"
```

### 3. **Analisis per Department**
```bash
# Melihat cuti dari department Finance
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://localhost:8000/api/leave-requests/hr-dashboard?department=Finance"
```

### 4. **Report Bulanan**
```bash
# Melihat cuti bulan Desember 2024
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://localhost:8000/api/leave-requests/hr-dashboard?year=2024&month=12"
```

### 5. **Monitor Employee Tertentu**
```bash
# Melihat semua cuti dari employee ID 1
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://localhost:8000/api/leave-requests/hr-dashboard?employee_id=1"
```

### 6. **Analisis Jenis Cuti**
```bash
# Melihat semua cuti tahunan
curl -H "Authorization: Bearer YOUR_TOKEN" \
     "http://localhost:8000/api/leave-requests/hr-dashboard?leave_type=annual"
```

## 📊 Data yang Bisa Diakses HR

### ✅ **Full Access Data**
- **Semua leave requests** dari semua employee
- **Semua status** (pending, approved, rejected)
- **Semua jenis cuti** (annual, sick, emergency, maternity, paternity, marriage, bereavement)
- **Data employee** yang mengajukan
- **Data approver** yang menyetujui/menolak
- **Tanggal dan durasi** cuti
- **Alasan** pengajuan cuti
- **Timestamp** pengajuan dan approval

### 📈 **Summary Analytics**
- **Total requests** per periode
- **Breakdown by status** (pending/approved/rejected)
- **Total hari** yang diminta vs disetujui
- **Analisis per jenis cuti**
- **Analisis per department**
- **Recent requests** (10 terbaru)

## 🔐 Security & Authorization

### **Role-Based Access**
```php
// Hanya HR yang bisa akses
if ($user->role !== 'HR') {
    return response()->json([
        'success' => false,
        'message' => 'Akses ditolak. Hanya HR yang dapat melihat data ini'
    ], 403);
}
```

### **Authentication Required**
- Semua endpoint memerlukan `Bearer Token`
- Token harus valid dan user harus memiliki role `HR`
- Middleware `auth:sanctum` diterapkan

## 🚀 Frontend Integration Examples

### **JavaScript/Fetch**
```javascript
// HR Dashboard Data
const getHRDashboard = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/leave-requests/hr-dashboard?${params}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};

// Usage examples
const allData = await getHRDashboard();
const pendingOnly = await getHRDashboard({ status: 'pending' });
const financeTeam = await getHRDashboard({ department: 'Finance' });
const december2024 = await getHRDashboard({ year: 2024, month: 12 });
```

### **React Component Example**
```jsx
const HRDashboard = () => {
  const [leaveData, setLeaveData] = useState(null);
  const [filters, setFilters] = useState({});
  
  useEffect(() => {
    const fetchData = async () => {
      const data = await getHRDashboard(filters);
      setLeaveData(data);
    };
    fetchData();
  }, [filters]);
  
  return (
    <div>
      <h1>HR Leave Dashboard</h1>
      
      {/* Summary Cards */}
      <div className="summary-cards">
        <div>Total Requests: {leaveData?.summary.total_requests}</div>
        <div>Pending: {leaveData?.summary.pending_requests}</div>
        <div>Approved: {leaveData?.summary.approved_requests}</div>
      </div>
      
      {/* Filters */}
      <div className="filters">
        <select onChange={(e) => setFilters({...filters, status: e.target.value})}>
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      
      {/* Data Table */}
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Leave Type</th>
            <th>Dates</th>
            <th>Days</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          {leaveData?.data.map(leave => (
            <tr key={leave.id}>
              <td>{leave.employee.nama_lengkap}</td>
              <td>{leave.leave_type}</td>
              <td>{leave.start_date} - {leave.end_date}</td>
              <td>{leave.total_days}</td>
              <td>{leave.status}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};
```

## 📋 Quick Reference

### **Main Endpoints**
| Endpoint | Method | Purpose | Best For |
|----------|--------|---------|----------|
| `/leave-requests/hr-dashboard` | GET | Complete overview with analytics | **Main HR Dashboard** |
| `/leave-requests` | GET | All leave requests | Simple listing |
| `/leave-requests/approved` | GET | Approved leaves only | Reports & analytics |

### **Common Filters**
| Parameter | Example | Description |
|-----------|---------|-------------|
| `year` | `2024` | Filter by year |
| `month` | `12` | Filter by month |
| `status` | `pending` | Filter by status |
| `leave_type` | `annual` | Filter by leave type |
| `employee_id` | `1` | Filter by employee |
| `department` | `Finance` | Filter by department |

### **Response Fields**
- `data`: Array of leave requests
- `summary`: Analytics and statistics
- `success`: Boolean status
- `message`: Response message

---

## 🎉 Summary

**HR sekarang memiliki akses penuh untuk melihat:**

✅ **SEMUA data cuti** dari semua employee  
✅ **Dashboard analytics** dengan summary lengkap  
✅ **Flexible filtering** berdasarkan berbagai parameter  
✅ **Real-time data** yang selalu update  
✅ **Security** dengan role-based access control  

**Endpoint utama**: `GET /api/leave-requests/hr-dashboard`

**Status**: 🚀 **READY TO USE**

**Last Updated**: December 2024