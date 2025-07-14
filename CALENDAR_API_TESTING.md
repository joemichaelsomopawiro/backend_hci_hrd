# 🧪 Testing API Kalender Nasional

## 📋 **API Endpoints yang Tersedia**

### 1. Get Calendar Data
```bash
GET /api/calendar/data?year=2024&month=8
```

**Response:**
```json
{
  "success": true,
  "data": {
    "calendar": [
      {
        "date": "2024-08-01",
        "day": 1,
        "is_holiday": false,
        "holiday_name": null,
        "is_weekend": false,
        "is_today": false
      }
    ],
    "holidays": [
      {
        "id": 1,
        "date": "2024-08-17",
        "name": "Hari Kemerdekaan RI",
        "description": null,
        "type": "national",
        "is_active": true
      }
    ]
  }
}
```

### 2. Check Holiday
```bash
GET /api/calendar/check?date=2024-08-17
```

**Response:**
```json
{
  "success": true,
  "data": {
    "date": "2024-08-17",
    "is_holiday": true,
    "holiday_name": "Hari Kemerdekaan RI"
  }
}
```

### 3. Get Holidays List
```bash
GET /api/calendar?year=2024&month=8
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "date": "2024-08-17",
      "name": "Hari Kemerdekaan RI",
      "description": null,
      "type": "national",
      "is_active": true
    }
  ]
}
```

### 4. Add Holiday (HR Only)
```bash
POST /api/calendar
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "date": "2024-07-16",
  "name": "Libur Perusahaan",
  "description": "Libur khusus perusahaan",
  "type": "custom"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Hari libur berhasil ditambahkan",
  "data": {
    "id": 2,
    "date": "2024-07-16",
    "name": "Libur Perusahaan",
    "description": "Libur khusus perusahaan",
    "type": "custom",
    "is_active": true
  }
}
```

### 5. Update Holiday (HR Only)
```bash
PUT /api/calendar/2
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "name": "Libur Perusahaan Update",
  "description": "Libur khusus perusahaan yang diupdate",
  "is_active": true
}
```

### 6. Delete Holiday (HR Only)
```bash
DELETE /api/calendar/2
Authorization: Bearer YOUR_TOKEN
```

## 🧪 **Testing dengan Postman**

### 1. Setup Collection
1. Buat collection baru di Postman
2. Set base URL: `http://localhost:8000`
3. Set Authorization: Bearer Token

### 2. Test Cases

#### Test Case 1: Get Calendar Data
```
Method: GET
URL: {{base_url}}/api/calendar/data?year=2024&month=8
Headers: 
  Authorization: Bearer {{token}}
```

#### Test Case 2: Check Holiday
```
Method: GET
URL: {{base_url}}/api/calendar/check?date=2024-08-17
Headers: 
  Authorization: Bearer {{token}}
```

#### Test Case 3: Add Holiday (HR)
```
Method: POST
URL: {{base_url}}/api/calendar
Headers: 
  Authorization: Bearer {{hr_token}}
  Content-Type: application/json
Body:
{
  "date": "2024-07-16",
  "name": "Libur Perusahaan",
  "description": "Libur khusus perusahaan",
  "type": "custom"
}
```

#### Test Case 4: Update Holiday (HR)
```
Method: PUT
URL: {{base_url}}/api/calendar/{{holiday_id}}
Headers: 
  Authorization: Bearer {{hr_token}}
  Content-Type: application/json
Body:
{
  "name": "Libur Perusahaan Update",
  "description": "Libur khusus perusahaan yang diupdate"
}
```

#### Test Case 5: Delete Holiday (HR)
```
Method: DELETE
URL: {{base_url}}/api/calendar/{{holiday_id}}
Headers: 
  Authorization: Bearer {{hr_token}}
```

## 🧪 **Testing dengan cURL**

### 1. Get Calendar Data
```bash
curl -X GET "http://localhost:8000/api/calendar/data?year=2024&month=8" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Check Holiday
```bash
curl -X GET "http://localhost:8000/api/calendar/check?date=2024-08-17" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Add Holiday (HR Only)
```bash
curl -X POST http://localhost:8000/api/calendar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer HR_TOKEN" \
  -d '{
    "date": "2024-07-16",
    "name": "Libur Perusahaan",
    "description": "Libur khusus perusahaan",
    "type": "custom"
  }'
```

### 4. Update Holiday (HR Only)
```bash
curl -X PUT http://localhost:8000/api/calendar/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer HR_TOKEN" \
  -d '{
    "name": "Libur Perusahaan Update",
    "description": "Libur khusus perusahaan yang diupdate"
  }'
```

### 5. Delete Holiday (HR Only)
```bash
curl -X DELETE http://localhost:8000/api/calendar/1 \
  -H "Authorization: Bearer HR_TOKEN"
```

## 🧪 **Testing dengan PHP Script**

### 1. Test Script
```php
<?php
// test_calendar_api.php

$baseUrl = 'http://localhost:8000/api';
$token = 'YOUR_TOKEN'; // Ganti dengan token yang valid

function makeRequest($method, $endpoint, $data = null) {
    global $baseUrl, $token;
    
    $url = $baseUrl . $endpoint;
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

// Test 1: Get Calendar Data
echo "=== Test 1: Get Calendar Data ===\n";
$response = makeRequest('GET', '/calendar/data?year=2024&month=8');
echo "Status: " . $response['status'] . "\n";
echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Check Holiday
echo "=== Test 2: Check Holiday ===\n";
$response = makeRequest('GET', '/calendar/check?date=2024-08-17');
echo "Status: " . $response['status'] . "\n";
echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Add Holiday (HR Only)
echo "=== Test 3: Add Holiday ===\n";
$holidayData = [
    'date' => '2024-07-16',
    'name' => 'Libur Perusahaan',
    'description' => 'Libur khusus perusahaan',
    'type' => 'custom'
];
$response = makeRequest('POST', '/calendar', $holidayData);
echo "Status: " . $response['status'] . "\n";
echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n\n";
?>
```

## 🧪 **Testing dengan JavaScript**

### 1. Test Script
```javascript
// test_calendar_api.js

const baseUrl = 'http://localhost:8000/api';
const token = 'YOUR_TOKEN'; // Ganti dengan token yang valid

async function makeRequest(method, endpoint, data = null) {
    const url = baseUrl + endpoint;
    const headers = {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    };
    
    const options = {
        method,
        headers
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const responseData = await response.json();
        return {
            status: response.status,
            data: responseData
        };
    } catch (error) {
        console.error('Error:', error);
        return {
            status: 500,
            data: { error: error.message }
        };
    }
}

async function testCalendarAPI() {
    console.log('=== Testing Calendar API ===\n');
    
    // Test 1: Get Calendar Data
    console.log('1. Testing Get Calendar Data...');
    const calendarResponse = await makeRequest('GET', '/calendar/data?year=2024&month=8');
    console.log('Status:', calendarResponse.status);
    console.log('Response:', JSON.stringify(calendarResponse.data, null, 2));
    console.log('\n');
    
    // Test 2: Check Holiday
    console.log('2. Testing Check Holiday...');
    const holidayResponse = await makeRequest('GET', '/calendar/check?date=2024-08-17');
    console.log('Status:', holidayResponse.status);
    console.log('Response:', JSON.stringify(holidayResponse.data, null, 2));
    console.log('\n');
    
    // Test 3: Add Holiday (HR Only)
    console.log('3. Testing Add Holiday...');
    const addHolidayData = {
        date: '2024-07-16',
        name: 'Libur Perusahaan',
        description: 'Libur khusus perusahaan',
        type: 'custom'
    };
    const addResponse = await makeRequest('POST', '/calendar', addHolidayData);
    console.log('Status:', addResponse.status);
    console.log('Response:', JSON.stringify(addResponse.data, null, 2));
    console.log('\n');
}

// Run tests
testCalendarAPI();
```

## 🧪 **Testing dengan Browser Console**

### 1. Test di Browser
```javascript
// Buka browser console di aplikasi frontend Anda

// Test get calendar data
fetch('/api/calendar/data?year=2024&month=8', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`
  }
})
.then(response => response.json())
.then(data => console.log('Calendar data:', data));

// Test check holiday
fetch('/api/calendar/check?date=2024-08-17', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`
  }
})
.then(response => response.json())
.then(data => console.log('Holiday check:', data));
```

## ✅ **Expected Results**

### Success Responses:
- **Status Code:** 200
- **Success:** true
- **Data:** Sesuai dengan endpoint

### Error Responses:
- **Status Code:** 403 (Forbidden) - HR only
- **Status Code:** 422 (Validation Error) - Invalid data
- **Status Code:** 404 (Not Found) - Resource not found

### Common Error Messages:
```json
{
  "success": false,
  "message": "Anda tidak memiliki akses untuk menambah hari libur"
}
```

## 🎯 **Testing Checklist**

- [ ] Get calendar data berhasil
- [ ] Check holiday berhasil
- [ ] Get holidays list berhasil
- [ ] Add holiday (HR only) berhasil
- [ ] Update holiday (HR only) berhasil
- [ ] Delete holiday (HR only) berhasil
- [ ] Non-HR tidak bisa add/update/delete
- [ ] Validation error untuk data invalid
- [ ] Weekend detection berfungsi
- [ ] National holiday detection berfungsi

## 🚀 **Next Steps**

Setelah testing berhasil:
1. Integrasikan dengan frontend
2. Test UI/UX
3. Test responsive design
4. Deploy ke production

Sistem kalender nasional siap digunakan! 🎉 