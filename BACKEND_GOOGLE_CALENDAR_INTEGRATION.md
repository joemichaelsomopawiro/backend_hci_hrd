# 🗓️ Backend Google Calendar Integration - Dokumentasi Lengkap

## 📋 **Overview**

Backend Laravel untuk sistem kalender nasional yang sudah diperbaiki dan disesuaikan dengan frontend. Sistem ini mendukung:

- ✅ Integrasi Google Calendar API untuk hari libur nasional
- ✅ CRUD operasi untuk hari libur custom (HR only)
- ✅ Role-based access control
- ✅ Cache untuk performa optimal
- ✅ Fallback data statis jika API gagal
- ✅ Endpoint yang sesuai dengan frontend

## 🏗️ **Struktur Database**

### **Tabel: `national_holidays`**
```sql
CREATE TABLE national_holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('national', 'custom', 'weekend') DEFAULT 'national',
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);
```

## 📁 **File yang Telah Dibuat/Diperbaiki**

### **1. Service: `app/Services/GoogleCalendarService.php`**
- ✅ Fetch hari libur dari Google Calendar API
- ✅ Cache management (24 jam)
- ✅ Fallback data statis
- ✅ Sync ke database
- ✅ Test koneksi API
- ✅ Clear cache

### **2. Model: `app/Models/NationalHoliday.php`**
- ✅ Relationships dengan User
- ✅ Scopes untuk filtering
- ✅ Helper methods lengkap
- ✅ Seed method untuk hari libur nasional
- ✅ Methods untuk calendar data

### **3. Controller: `app/Http/Controllers/NationalHolidayController.php`**
- ✅ CRUD operations lengkap
- ✅ Role-based access control
- ✅ Google Calendar integration
- ✅ Endpoint untuk frontend
- ✅ Validation dan error handling

### **4. Routes: `routes/api.php`**
- ✅ Calendar routes dengan auth
- ✅ HR-only routes untuk manage
- ✅ Google Calendar integration routes
- ✅ Frontend-specific endpoints

### **5. Config: `config/services.php`**
- ✅ Google Calendar API configuration
- ✅ Environment variables setup

## 🔧 **Konfigurasi Environment**

### **File: `.env`**
```env
# Google Calendar API Configuration
GOOGLE_CALENDAR_API_KEY=your_google_calendar_api_key_here
GOOGLE_CALENDAR_ID=en.indonesian%23holiday%40group.v.calendar.google.com
```

### **Cara Dapat Google Calendar API Key:**
1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat project baru atau pilih yang sudah ada
3. Enable Google Calendar API
4. Buat API Key di Credentials
5. Copy API Key ke file `.env`

## 🚀 **API Endpoints**

### **Public Endpoints (Semua User)**
```bash
# Get calendar data lengkap
GET /api/calendar/data?year=2024&month=8

# Get calendar data untuk frontend
GET /api/calendar/data-frontend?year=2024&month=8

# Check hari libur spesifik
GET /api/calendar/check?date=2024-08-17

# Get daftar hari libur
GET /api/calendar?year=2024&month=8

# Get tahun yang tersedia
GET /api/calendar/years

# Get ringkasan tahunan
GET /api/calendar/yearly-summary?year=2024

# Get hari libur tahunan
GET /api/calendar/yearly-holidays?year=2024
```

### **HR Only Endpoints**
```bash
# Tambah hari libur
POST /api/calendar
{
    "date": "2024-07-16",
    "name": "Libur Perusahaan",
    "description": "Libur khusus perusahaan",
    "type": "custom"
}

# Edit hari libur
PUT /api/calendar/{id}
{
    "name": "Libur Perusahaan Update",
    "description": "Libur khusus perusahaan yang diupdate"
}

# Hapus hari libur
DELETE /api/calendar/{id}

# Seed hari libur nasional
POST /api/calendar/seed?year=2024

# Bulk seed multiple tahun
POST /api/calendar/bulk-seed
{
    "years": [2024, 2025, 2026]
}

# Google Calendar Integration
POST /api/calendar/sync-google?year=2024
GET /api/calendar/test-google-connection
POST /api/calendar/clear-google-cache?year=2024

# Hari libur berulang
POST /api/calendar/recurring
{
    "day_of_week": 0,
    "name": "Libur Minggu",
    "description": "Libur setiap hari Minggu",
    "start_year": 2024,
    "end_year": 2025
}

# Hari libur bulanan
POST /api/calendar/monthly
{
    "day_of_month": 15,
    "name": "Libur Tengah Bulan",
    "description": "Libur tanggal 15 setiap bulan",
    "start_year": 2024,
    "end_year": 2025
}

# Hari libur rentang tanggal
POST /api/calendar/date-range
{
    "start_date": "2024-12-24",
    "end_date": "2024-12-26",
    "name": "Libur Natal",
    "description": "Libur Natal dan sekitarnya"
}

# Get hari libur custom
GET /api/calendar/custom?year=2024&type=custom

# Get tipe hari libur
GET /api/calendar/types
```

## 📊 **Response Format**

### **Success Response**
```json
{
    "success": true,
    "message": "Hari libur berhasil ditambahkan",
    "data": {
        "id": 1,
        "date": "2024-07-16",
        "name": "Libur Perusahaan",
        "description": "Libur khusus perusahaan",
        "type": "custom",
        "is_active": true,
        "created_by": 1,
        "updated_by": null,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

### **Error Response**
```json
{
    "success": false,
    "message": "Tanggal sudah ada hari libur",
    "errors": {
        "date": ["Tanggal sudah ada hari libur"]
    }
}
```

## 🔐 **Role-Based Access Control**

### **HR Role (Full Access)**
- ✅ Tambah hari libur custom
- ✅ Edit hari libur custom
- ✅ Hapus hari libur custom
- ✅ Sync dari Google Calendar
- ✅ Seed hari libur nasional
- ✅ Manage cache

### **User Lain (Read Only)**
- ✅ Lihat kalender
- ✅ Check hari libur
- ✅ Lihat daftar hari libur

### **Hari Libur Nasional**
- ❌ Tidak bisa diedit/dihapus oleh siapapun
- ✅ Hanya bisa diupdate via Google Calendar sync

## 🧪 **Testing**

### **1. Test dengan Postman**
```bash
# Setup Collection
Base URL: http://localhost:8000
Authorization: Bearer Token

# Test Cases
1. GET /api/calendar/data?year=2024&month=8
2. POST /api/calendar (HR only)
3. PUT /api/calendar/{id} (HR only)
4. DELETE /api/calendar/{id} (HR only)
5. POST /api/calendar/sync-google (HR only)
```

### **2. Test dengan cURL**
```bash
# Get calendar data
curl -X GET "http://localhost:8000/api/calendar/data?year=2024&month=8" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Add holiday (HR only)
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

### **3. Test dengan PHP Script**
```php
<?php
// test_calendar_api.php
$baseUrl = 'http://localhost:8000/api';
$token = 'YOUR_TOKEN';

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

// Test cases
$response = makeRequest('GET', '/calendar/data?year=2024&month=8');
echo "Status: " . $response['status'] . "\n";
echo "Response: " . json_encode($response['data'], JSON_PRETTY_PRINT) . "\n";
?>
```

## 🔄 **Google Calendar Integration**

### **Features**
- ✅ Fetch hari libur nasional otomatis
- ✅ Cache untuk performa (24 jam)
- ✅ Fallback data statis jika API gagal
- ✅ Sync ke database
- ✅ Test koneksi API
- ✅ Clear cache

### **Setup Google Calendar API**
1. **Enable Google Calendar API**
2. **Buat API Key**
3. **Set environment variables**
4. **Test koneksi**

### **Usage**
```bash
# Sync hari libur nasional
POST /api/calendar/sync-google?year=2024

# Test koneksi
GET /api/calendar/test-google-connection

# Clear cache
POST /api/calendar/clear-google-cache?year=2024
```

## 📈 **Performance Optimization**

### **Caching Strategy**
- ✅ Cache Google Calendar API response (24 jam)
- ✅ Cache calendar data per bulan
- ✅ Cache holiday checks

### **Database Optimization**
- ✅ Index pada kolom `date`
- ✅ Index pada kolom `type`
- ✅ Index pada kolom `created_by`

### **API Optimization**
- ✅ Lazy loading untuk data kalender
- ✅ Pagination untuk data besar
- ✅ Rate limiting untuk API calls

## 🚨 **Error Handling**

### **Common Errors**
```json
// 401 Unauthorized
{
    "success": false,
    "message": "Anda harus login terlebih dahulu"
}

// 403 Forbidden
{
    "success": false,
    "message": "Anda tidak memiliki akses untuk menambah hari libur"
}

// 422 Validation Error
{
    "success": false,
    "message": "Data tidak valid",
    "errors": {
        "date": ["Tanggal sudah ada hari libur"],
        "name": ["Nama hari libur wajib diisi"]
    }
}

// 500 Server Error
{
    "success": false,
    "message": "Terjadi kesalahan server"
}
```

## 🔧 **Maintenance**

### **Regular Tasks**
1. **Update hari libur variabel** setiap tahun
2. **Test Google Calendar API** secara berkala
3. **Clear cache** jika ada masalah
4. **Backup database** secara rutin

### **Monitoring**
- ✅ Log API calls
- ✅ Monitor cache hit rate
- ✅ Track error rates
- ✅ Monitor performance

## ✅ **Checklist Implementasi**

### **Backend Setup**
- [x] Migration database
- [x] Model NationalHoliday
- [x] Controller NationalHolidayController
- [x] Routes API
- [x] Google Calendar Service
- [x] Environment configuration
- [x] Role-based access control
- [x] Error handling
- [x] Validation
- [x] Testing endpoints

### **Google Calendar Integration**
- [x] API key setup
- [x] Service implementation
- [x] Cache management
- [x] Fallback data
- [x] Sync functionality
- [x] Test connection
- [x] Clear cache

### **Frontend Compatibility**
- [x] Endpoint `/api/calendar/data-frontend`
- [x] Response format sesuai frontend
- [x] Error handling yang konsisten
- [x] Role-based UI support

## 🎯 **Kesimpulan**

Backend sistem kalender nasional sudah:
1. **✅ Terintegrasi dengan Google Calendar API**
2. **✅ Mendukung CRUD operasi lengkap**
3. **✅ Role-based access control**
4. **✅ Sesuai dengan kebutuhan frontend**
5. **✅ Optimized untuk performa**
6. **✅ Error handling yang robust**
7. **✅ Dokumentasi lengkap**

Sistem siap untuk digunakan dengan frontend yang sudah ada! 🚀 