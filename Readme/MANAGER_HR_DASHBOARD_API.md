# Manager & HR Dashboard API Documentation

## Overview
Dokumentasi lengkap untuk endpoint dashboard Manager dan HR yang memungkinkan melihat summary dan detail history cuti dengan fitur total disetujui, ditolak, dan total permohonan.

## 🎯 Endpoints untuk Manager Dashboard

### 1. **Manager Dashboard - Overview Semua Data Cuti Subordinates** ⭐ **RECOMMENDED**

**Endpoint**: `GET /api/leave-requests/manager-dashboard`

**Deskripsi**: Dashboard utama untuk Manager melihat semua data cuti dari subordinates dengan summary lengkap

**Authorization**: `Bearer Token` (Role: Manager - General Affairs, Producer, Creative)

**Query Parameters**:
```
?year=2024              // Filter berdasarkan tahun
?month=12               // Filter berdasarkan bulan
?status=pending         // Filter berdasarkan status (pending/approved/rejected)
?leave_type=annual      // Filter berdasarkan jenis cuti
?employee_id=1          // Filter berdasarkan employee tertentu
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
          "role": "General Affairs"
        }
      }
    }
  ],
  "summary": {
    "total_requests": 25,
    "pending_requests": 5,
    "approved_requests": 18,
    "rejected_requests": 2,
    "total_days_requested": 85,
    "total_days_approved": 72,
    "by_leave_type": {
      "annual": 15,
      "sick": 6,
      "emergency": 3,
      "maternity": 1,
      "paternity": 0,
      "marriage": 0,
      "bereavement": 0
    },
    "by_employee": {
      "John Doe": {
        "count": 5,
        "total_days": 15,
        "approved": 4,
        "pending": 1,
        "rejected": 0
      },
      "Jane Smith": {
        "count": 3,
        "total_days": 9,
        "approved": 2,
        "pending": 0,
        "rejected": 1
      }
    },
    "recent_requests": [
      // 10 request terbaru dari subordinates
    ],
    "subordinate_roles": ["Finance"]
  },
  "message": "Data cuti subordinates berhasil diambil untuk Manager dashboard"
}
```

### 2. **Manager - Cuti yang Disetujui** 🟢

**Endpoint**: `GET /api/leave-requests/manager/approved`

**Deskripsi**: Melihat semua cuti subordinates yang sudah disetujui

**Authorization**: `Bearer Token` (Role: Manager)

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
    // Semua cuti subordinates yang status approved
  ],
  "summary": {
    "total_approved": 18,
    "total_days": 72,
    "by_leave_type": {
      "annual": 12,
      "sick": 4,
      "emergency": 2,
      "maternity": 0,
      "paternity": 0,
      "marriage": 0,
      "bereavement": 0
    }
  },
  "message": "Data cuti yang disetujui berhasil diambil"
}
```

### 3. **Manager - Cuti yang Ditolak** 🔴

**Endpoint**: `GET /api/leave-requests/manager/rejected`

**Deskripsi**: Melihat semua cuti subordinates yang ditolak

**Authorization**: `Bearer Token` (Role: Manager)

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
    // Semua cuti subordinates yang status rejected
  ],
  "summary": {
    "total_rejected": 2,
    "total_days": 6,
    "by_leave_type": {
      "annual": 1,
      "sick": 0,
      "emergency": 1,
      "maternity": 0,
      "paternity": 0,
      "marriage": 0,
      "bereavement": 0
    }
  },
  "message": "Data cuti yang ditolak berhasil diambil"
}
```

## 🎯 Endpoints untuk HR Dashboard

### 1. **HR - Cuti yang Disetujui** 🟢

**Endpoint**: `GET /api/leave-requests/hr/approved`

**Deskripsi**: Melihat semua cuti dari semua employee yang sudah disetujui

**Authorization**: `Bearer Token` (Role: HR)

**Query Parameters**:
```
?year=2024              // Filter berdasarkan tahun
?month=12               // Filter berdasarkan bulan
?department=Finance     // Filter berdasarkan department/role
```

**Response**:
```json
{
  "success": true,
  "data": [
    // Semua cuti dari semua employee yang status approved
  ],
  "summary": {
    "total_approved": 45,
    "total_days": 180,
    "by_leave_type": {
      "annual": 30,
      "sick": 10,
      "emergency": 4,
      "maternity": 1,
      "paternity": 0,
      "marriage": 0,
      "bereavement": 0
    },
    "by_department": {
      "Finance": 15,
      "General Affairs": 12,
      "Producer": 10,
      "Creative": 8
    }
  },
  "message": "Data cuti yang disetujui berhasil diambil"
}
```

### 2. **HR - Cuti yang Ditolak** 🔴

**Endpoint**: `GET /api/leave-requests/hr/rejected`

**Deskripsi**: Melihat semua cuti dari semua employee yang ditolak

**Authorization**: `Bearer Token` (Role: HR)

**Query Parameters**:
```
?year=2024              // Filter berdasarkan tahun
?month=12               // Filter berdasarkan bulan
?department=Finance     // Filter berdasarkan department/role
```

**Response**:
```json
{
  "success": true,
  "data": [
    // Semua cuti dari semua employee yang status rejected
  ],
  "summary": {
    "total_rejected": 8,
    "total_days": 24,
    "by_leave_type": {
      "annual": 3,
      "sick": 2,
      "emergency": 3,
      "maternity": 0,
      "paternity": 0,
      "marriage": 0,
      "bereavement": 0
    },
    "by_department": {
      "Finance": 3,
      "General Affairs": 2,
      "Producer": 2,
      "Creative": 1
    }
  },
  "message": "Data cuti yang ditolak berhasil diambil"
}
```

## 🔍 Use Cases untuk Frontend Integration

### **Manager Dashboard Page**
```javascript
// Get total summary untuk Manager
const getManagerSummary = async () => {
  const response = await fetch('/api/leave-requests/manager-dashboard', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};

// Get approved leaves untuk Manager
const getManagerApproved = async () => {
  const response = await fetch('/api/leave-requests/manager/approved', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};

// Get rejected leaves untuk Manager
const getManagerRejected = async () => {
  const response = await fetch('/api/leave-requests/manager/rejected', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};
```

### **HR Dashboard Page**
```javascript
// Get approved leaves untuk HR
const getHRApproved = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/leave-requests/hr/approved?${params}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};

// Get rejected leaves untuk HR
const getHRRejected = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/leave-requests/hr/rejected?${params}`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  return response.json();
};
```

### **React Component Example untuk Manager**
```jsx
const ManagerDashboard = () => {
  const [summary, setSummary] = useState(null);
  const [approvedLeaves, setApprovedLeaves] = useState(null);
  const [rejectedLeaves, setRejectedLeaves] = useState(null);
  const [activeTab, setActiveTab] = useState('overview');
  
  useEffect(() => {
    const fetchData = async () => {
      const summaryData = await getManagerSummary();
      setSummary(summaryData);
    };
    fetchData();
  }, []);
  
  const handleApprovedClick = async () => {
    const data = await getManagerApproved();
    setApprovedLeaves(data);
    setActiveTab('approved');
  };
  
  const handleRejectedClick = async () => {
    const data = await getManagerRejected();
    setRejectedLeaves(data);
    setActiveTab('rejected');
  };
  
  return (
    <div className="manager-dashboard">
      <h1>Manager Dashboard</h1>
      
      {/* Summary Cards */}
      <div className="summary-cards">
        <div className="card total" onClick={() => setActiveTab('overview')}>
          <h3>Total Permohonan</h3>
          <p>{summary?.summary.total_requests}</p>
        </div>
        
        <div className="card approved" onClick={handleApprovedClick}>
          <h3>Disetujui</h3>
          <p>{summary?.summary.approved_requests}</p>
        </div>
        
        <div className="card rejected" onClick={handleRejectedClick}>
          <h3>Ditolak</h3>
          <p>{summary?.summary.rejected_requests}</p>
        </div>
        
        <div className="card pending">
          <h3>Pending</h3>
          <p>{summary?.summary.pending_requests}</p>
        </div>
      </div>
      
      {/* Content based on active tab */}
      {activeTab === 'overview' && (
        <div className="overview-content">
          <h2>Overview Semua Cuti Subordinates</h2>
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
              {summary?.data.map(leave => (
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
      )}
      
      {activeTab === 'approved' && approvedLeaves && (
        <div className="approved-content">
          <h2>Cuti yang Disetujui</h2>
          <p>Total: {approvedLeaves.summary.total_approved} cuti ({approvedLeaves.summary.total_days} hari)</p>
          <table>
            {/* Table untuk approved leaves */}
          </table>
        </div>
      )}
      
      {activeTab === 'rejected' && rejectedLeaves && (
        <div className="rejected-content">
          <h2>Cuti yang Ditolak</h2>
          <p>Total: {rejectedLeaves.summary.total_rejected} cuti ({rejectedLeaves.summary.total_days} hari)</p>
          <table>
            {/* Table untuk rejected leaves */}
          </table>
        </div>
      )}
    </div>
  );
};
```

## 📊 Role-Based Access Control

### **Manager Access**
- ✅ Dapat melihat cuti dari **subordinates saja**
- ✅ Summary berdasarkan **hierarchy role**
- ✅ Filter berdasarkan **tahun, bulan, status, jenis cuti**
- ✅ Detail history **approved dan rejected**

### **HR Access**
- ✅ Dapat melihat cuti dari **SEMUA employee**
- ✅ Summary berdasarkan **department/role**
- ✅ Filter berdasarkan **tahun, bulan, department**
- ✅ Detail history **approved dan rejected**
- ✅ **Full access** ke semua data

## 🔐 Security Features

### **Authentication & Authorization**
```php
// Manager validation
if (!$user || !RoleHierarchyService::isManager($user->role)) {
    return response()->json([
        'success' => false,
        'message' => 'Akses ditolak. Hanya manager yang dapat melihat data ini'
    ], 403);
}

// HR validation
if (!$user || $user->role !== 'HR') {
    return response()->json([
        'success' => false,
        'message' => 'Akses ditolak. Hanya HR yang dapat melihat data ini'
    ], 403);
}
```

### **Data Filtering**
- Manager: Otomatis filter berdasarkan **subordinate roles**
- HR: Akses **semua data** tanpa filter role
- Semua endpoint: **Bearer Token** required

## 📋 Quick Reference

### **Manager Endpoints**
| Endpoint | Method | Purpose | Response |
|----------|--------|---------|----------|
| `/leave-requests/manager-dashboard` | GET | Overview semua cuti subordinates | Summary + Detail |
| `/leave-requests/manager/approved` | GET | Cuti subordinates yang disetujui | Approved only |
| `/leave-requests/manager/rejected` | GET | Cuti subordinates yang ditolak | Rejected only |

### **HR Endpoints**
| Endpoint | Method | Purpose | Response |
|----------|--------|---------|----------|
| `/leave-requests/hr-dashboard` | GET | Overview semua cuti (existing) | Summary + Detail |
| `/leave-requests/hr/approved` | GET | Semua cuti yang disetujui | Approved only |
| `/leave-requests/hr/rejected` | GET | Semua cuti yang ditolak | Rejected only |

### **Common Filters**
| Parameter | Example | Available For |
|-----------|---------|---------------|
| `year` | `2024` | All endpoints |
| `month` | `12` | All endpoints |
| `department` | `Finance` | HR endpoints only |
| `status` | `pending` | Dashboard endpoints |
| `leave_type` | `annual` | Dashboard endpoints |
| `employee_id` | `1` | Dashboard endpoints |

---

## 🎉 Summary

**Fitur yang telah diimplementasi:**

✅ **Manager Dashboard** dengan summary lengkap subordinates  
✅ **Manager Approved Leaves** - detail cuti yang disetujui  
✅ **Manager Rejected Leaves** - detail cuti yang ditolak  
✅ **HR Approved Leaves** - semua cuti yang disetujui  
✅ **HR Rejected Leaves** - semua cuti yang ditolak  
✅ **Role-based access control** yang ketat  
✅ **Flexible filtering** untuk semua endpoint  
✅ **Summary statistics** untuk frontend integration  

**Frontend dapat menggunakan:**
- **Total Permohonan** → `/manager-dashboard` atau `/hr-dashboard`
- **Total Disetujui** → `/manager/approved` atau `/hr/approved`
- **Total Ditolak** → `/manager/rejected` atau `/hr/rejected`
- **Detail History** → Semua endpoint menyediakan data lengkap

**Status**: 🚀 **READY TO USE**

**Last Updated**: December 2024