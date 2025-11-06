# API Usage Examples - Leave Management System

## Quick Start Examples

### 1. Cek Jatah Cuti Karyawan Tertentu
```bash
# GET request
curl -X GET "http://localhost:8000/api/leave-quotas?employee_id=1&year=2024"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 1,
      "year": 2024,
      "annual_leave_quota": 12,
      "annual_leave_used": 5,
      "annual_leave_remaining": 7,
      "sick_leave_quota": 12,
      "sick_leave_used": 2,
      "employee": {
        "id": 1,
        "name": "John Doe",
        "employee_id": "EMP001"
      }
    }
  ]
}
```

### 2. Update Jatah Cuti Individual
```bash
# PUT request
curl -X PUT "http://localhost:8000/api/leave-quotas/1" \
  -H "Content-Type: application/json" \
  -d '{
    "annual_leave_quota": 15,
    "emergency_leave_quota": 5
  }'
```

### 3. Bulk Update Multiple Karyawan
```bash
# POST request
curl -X POST "http://localhost:8000/api/leave-quotas/bulk-update" \
  -H "Content-Type: application/json" \
  -d '{
    "updates": [
      {
        "employee_id": 1,
        "year": 2024,
        "annual_leave_quota": 15
      },
      {
        "employee_id": 2,
        "year": 2024,
        "annual_leave_quota": 12,
        "maternity_leave_quota": 90
      }
    ]
  }'
```

### 4. Reset Jatah Cuti Tahunan
```bash
# POST request
curl -X POST "http://localhost:8000/api/leave-quotas/reset-annual" \
  -H "Content-Type: application/json" \
  -d '{
    "year": 2025,
    "default_quotas": {
      "annual_leave_quota": 12,
      "sick_leave_quota": 12,
      "emergency_leave_quota": 3,
      "maternity_leave_quota": 90,
      "paternity_leave_quota": 7,
      "marriage_leave_quota": 3,
      "bereavement_leave_quota": 3
    }
  }'
```

### 5. Lihat Ringkasan Penggunaan Cuti
```bash
# GET request
curl -X GET "http://localhost:8000/api/leave-quotas/usage-summary?year=2024"
```

## JavaScript/Frontend Examples

### React/Vue.js API Calls

```javascript
// 1. Fetch employee leave quota
const getEmployeeQuota = async (employeeId, year) => {
  try {
    const response = await fetch(`/api/leave-quotas?employee_id=${employeeId}&year=${year}`);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error fetching quota:', error);
  }
};

// 2. Update single employee quota
const updateQuota = async (quotaId, updates) => {
  try {
    const response = await fetch(`/api/leave-quotas/${quotaId}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(updates)
    });
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error updating quota:', error);
  }
};

// 3. Bulk update quotas
const bulkUpdateQuotas = async (updates) => {
  try {
    const response = await fetch('/api/leave-quotas/bulk-update', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ updates })
    });
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error bulk updating:', error);
  }
};

// 4. Get usage summary
const getUsageSummary = async (year) => {
  try {
    const response = await fetch(`/api/leave-quotas/usage-summary?year=${year}`);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error fetching summary:', error);
  }
};

// 5. Reset annual quotas
const resetAnnualQuotas = async (year, defaultQuotas) => {
  try {
    const response = await fetch('/api/leave-quotas/reset-annual', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        year,
        default_quotas: defaultQuotas
      })
    });
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error resetting quotas:', error);
  }
};
```

### Axios Examples

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

// Get all quotas with filters
const getAllQuotas = (filters = {}) => {
  return api.get('/leave-quotas', { params: filters });
};

// Create new quota
const createQuota = (quotaData) => {
  return api.post('/leave-quotas', quotaData);
};

// Update quota
const updateQuota = (id, updates) => {
  return api.put(`/leave-quotas/${id}`, updates);
};

// Bulk update
const bulkUpdate = (updates) => {
  return api.post('/leave-quotas/bulk-update', { updates });
};

// Usage summary
const getUsageSummary = (params = {}) => {
  return api.get('/leave-quotas/usage-summary', { params });
};
```

## Common Use Cases

### Scenario 1: HR Setup Jatah Cuti Karyawan Baru
```javascript
// Step 1: Create quota for new employee
const newEmployeeQuota = {
  employee_id: 15,
  year: 2024,
  annual_leave_quota: 12,
  sick_leave_quota: 12,
  emergency_leave_quota: 3,
  maternity_leave_quota: 0,  // Male employee
  paternity_leave_quota: 7,
  marriage_leave_quota: 3,
  bereavement_leave_quota: 3
};

fetch('/api/leave-quotas', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(newEmployeeQuota)
});
```

### Scenario 2: HR Adjustment untuk Karyawan Senior
```javascript
// Step 1: Get current quota
const currentQuota = await fetch('/api/leave-quotas?employee_id=5&year=2024');

// Step 2: Update with senior benefits
const updates = {
  annual_leave_quota: 18,  // Senior gets more annual leave
  emergency_leave_quota: 5
};

fetch(`/api/leave-quotas/${currentQuota.data[0].id}`, {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(updates)
});
```

### Scenario 3: End of Year Reset
```javascript
// Reset all employees for new year
const resetData = {
  year: 2025,
  default_quotas: {
    annual_leave_quota: 12,
    sick_leave_quota: 12,
    emergency_leave_quota: 3,
    maternity_leave_quota: 90,
    paternity_leave_quota: 7,
    marriage_leave_quota: 3,
    bereavement_leave_quota: 3
  }
};

fetch('/api/leave-quotas/reset-annual', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(resetData)
});
```

### Scenario 4: Monthly Monitoring
```javascript
// Get usage summary for monitoring
const summary = await fetch('/api/leave-quotas/usage-summary?year=2024');

// Display summary data
console.log('Total Employees:', summary.data.summary.total_employees);
console.log('Annual Leave Usage:', summary.data.summary.leave_types_summary.annual);
```

## Error Handling Examples

```javascript
const handleApiCall = async (apiFunction) => {
  try {
    const response = await apiFunction();
    
    if (response.success) {
      console.log('Success:', response.message);
      return response.data;
    } else {
      console.error('API Error:', response.message);
      if (response.errors) {
        Object.keys(response.errors).forEach(field => {
          console.error(`${field}: ${response.errors[field].join(', ')}`);
        });
      }
    }
  } catch (error) {
    console.error('Network Error:', error);
  }
};

// Usage
handleApiCall(() => updateQuota(1, { annual_leave_quota: 15 }));
```

## Integration dengan Leave Requests

### Check Available Quota Before Request
```javascript
const checkQuotaBeforeRequest = async (employeeId, leaveType, requestedDays) => {
  const quotaResponse = await fetch(`/api/leave-quotas?employee_id=${employeeId}&year=2024`);
  const quota = quotaResponse.data[0];
  
  const quotaField = `${leaveType}_leave_quota`;
  const usedField = `${leaveType}_leave_used`;
  
  const available = quota[quotaField] - quota[usedField];
  
  if (available >= requestedDays) {
    console.log('Quota sufficient, can proceed with request');
    return true;
  } else {
    console.log(`Insufficient quota. Available: ${available}, Requested: ${requestedDays}`);
    return false;
  }
};
```

## Postman Collection

Untuk testing yang lebih mudah, import collection berikut ke Postman:

```json
{
  "info": {
    "name": "Leave Management API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Get All Quotas",
      "request": {
        "method": "GET",
        "header": [],
        "url": {
          "raw": "{{base_url}}/api/leave-quotas",
          "host": ["{{base_url}}"],
          "path": ["api", "leave-quotas"]
        }
      }
    },
    {
      "name": "Bulk Update Quotas",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"updates\": [\n    {\n      \"employee_id\": 1,\n      \"year\": 2024,\n      \"annual_leave_quota\": 15\n    }\n  ]\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/leave-quotas/bulk-update",
          "host": ["{{base_url}}"],
          "path": ["api", "leave-quotas", "bulk-update"]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000"
    }
  ]
}
```

## Tips untuk Frontend Developer

1. **Base URL**: Gunakan environment variable untuk base URL
2. **Error Handling**: Selalu handle error response dengan proper UI feedback
3. **Loading States**: Tampilkan loading indicator saat API call
4. **Validation**: Validate input di frontend sebelum kirim ke API
5. **Caching**: Consider caching quota data untuk performa yang lebih baik
6. **Real-time Updates**: Refresh quota data setelah leave request disetujui