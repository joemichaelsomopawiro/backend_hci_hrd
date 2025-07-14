# 🔧 Perbaikan Error Calendar Frontend: "Unexpected token '<', "<!DOCTYPE "... is not valid JSON"

## 🚨 Masalah yang Ditemukan

**Error**: `SyntaxError: Unexpected token '<', "<!DOCTYPE "... is not valid JSON`

**Penyebab**: Server mengembalikan halaman HTML alih-alih response JSON. Ini terjadi karena:
1. URL endpoint salah
2. Route tidak ditemukan (404)
3. Server error (500)
4. Authentication/Authorization issue
5. CORS issue

## ✅ **Status Backend - SELESAI**

Setelah testing, backend sudah berfungsi dengan baik:
- ✅ Role HR sudah diperbaiki (`'HR'` bukan `'hr'`)
- ✅ Controller sudah menggunakan autentikasi yang benar
- ✅ Route `POST /api/calendar` sudah terdaftar
- ✅ Database bisa menerima insert data

## 🔧 **Langkah Perbaikan Frontend**

### **Step 1: Debug dengan Browser Console**

1. **Buka browser developer tools** (F12)
2. **Buka tab Console**
3. **Copy dan paste script debug** berikut:

```javascript
// Copy seluruh isi dari file fix_calendar_frontend.js
// atau jalankan perintah ini di console:

// Cek token dan user
const token = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');
console.log('Token:', token ? 'Present' : 'Missing');
console.log('User Role:', user.role);

// Test POST endpoint
fetch('/api/calendar', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    date: '2025-01-05',
    name: 'Test Debug',
    description: 'Test dari console',
    type: 'custom'
  })
})
.then(response => {
  console.log('Status:', response.status);
  return response.text();
})
.then(text => {
  console.log('Response:', text);
  if (text.includes('<!DOCTYPE')) {
    console.error('❌ Server returned HTML instead of JSON!');
  } else {
    try {
      const json = JSON.parse(text);
      console.log('✅ JSON response:', json);
    } catch (e) {
      console.error('❌ Invalid JSON:', e);
    }
  }
})
.catch(error => {
  console.error('❌ Fetch error:', error);
});
```

### **Step 2: Periksa Network Tab**

1. **Buka tab Network** di developer tools
2. **Coba tambah hari libur dengan icon**
3. **Lihat request yang dikirim**:
   - **URL**: Harus `POST /api/calendar`
   - **Headers**: Harus ada `Authorization: Bearer <token>`
   - **Body**: Harus JSON valid

### **Step 3: Perbaiki calendarService.js**

Pastikan file `calendarService.js` menggunakan kode yang benar:

```javascript
class CalendarService {
  constructor() {
    this.baseURL = '/api/calendar'; // ← Pastikan tidak ada trailing slash
  }

  getHeaders() {
    const token = localStorage.getItem('token');
    return {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json' // ← Tambahkan ini
    };
  }

  async addHoliday(holidayData) {
    try {
      console.log('Sending request to:', this.baseURL);
      console.log('Data:', holidayData);
      
      const response = await fetch(this.baseURL, {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify(holidayData)
      });
      
      console.log('Response status:', response.status);
      
      // Tambahkan error handling yang lebih baik
      if (!response.ok) {
        const errorText = await response.text();
        console.error('HTTP Error:', response.status, errorText);
        
        if (errorText.includes('<!DOCTYPE')) {
          throw new Error(`Server returned HTML (${response.status}). Check URL endpoint.`);
        }
        
        throw new Error(`HTTP ${response.status}: ${errorText}`);
      }
      
      const data = await response.json();
      console.log('Success response:', data);
      return data;
    } catch (error) {
      console.error('Error adding holiday:', error);
      return { 
        success: false, 
        message: 'Gagal menambah hari libur: ' + error.message 
      };
    }
  }
}
```

### **Step 4: Perbaiki Component Calendar.vue**

Pastikan method `saveHoliday` menggunakan error handling yang baik:

```javascript
async saveHoliday() {
  if (!this.holidayForm.date || !this.holidayForm.name) {
    alert('Tanggal dan nama hari libur wajib diisi');
    return;
  }
  
  try {
    console.log('Saving holiday:', this.holidayForm);
    
    let response;
    if (this.editingHoliday) {
      response = await calendarService.updateHoliday(this.editingHoliday.id, this.holidayForm);
    } else {
      response = await calendarService.addHoliday(this.holidayForm);
    }
    
    console.log('Service response:', response);
    
    if (response.success) {
      this.closeModal();
      await this.loadCalendarData();
      this.$emit('holiday-saved');
      alert('Hari libur berhasil disimpan!');
    } else {
      alert(response.message || 'Gagal menyimpan hari libur');
    }
  } catch (error) {
    console.error('Error saving holiday:', error);
    alert('Terjadi kesalahan: ' + error.message);
  }
}
```

### **Step 5: Periksa Icon Tambah di Kalender**

Pastikan icon tambah menggunakan method yang sama dengan button:

```javascript
// Calendar.vue - untuk icon tambah di tanggal
addHolidayForDate(date) {
  console.log('Adding holiday for date:', date);
  this.holidayForm.date = date;
  this.holidayForm.name = '';
  this.holidayForm.description = '';
  this.holidayForm.type = 'custom';
  this.editingHoliday = null;
  this.showAddHolidayModal = true;
}
```

## 🧪 **Testing Steps**

### **Test 1: Cek Token**
```javascript
// Di browser console
console.log('Token:', localStorage.getItem('token'));
console.log('User:', localStorage.getItem('user'));
```

### **Test 2: Cek Endpoint**
```javascript
// Test GET endpoint
fetch('/api/calendar/data?year=2024&month=12', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
    'Accept': 'application/json'
  }
})
.then(r => r.json())
.then(data => console.log('GET success:', data))
.catch(e => console.error('GET error:', e));
```

### **Test 3: Test POST Endpoint**
```javascript
// Test POST endpoint
fetch('/api/calendar', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    date: '2025-01-06',
    name: 'Test Holiday',
    description: 'Test description',
    type: 'custom'
  })
})
.then(r => r.text())
.then(text => {
  console.log('Response:', text);
  if (text.includes('<!DOCTYPE')) {
    console.error('❌ HTML response - check URL!');
  } else {
    try {
      const json = JSON.parse(text);
      console.log('✅ JSON response:', json);
    } catch (e) {
      console.error('❌ Invalid JSON:', e);
    }
  }
})
.catch(e => console.error('POST error:', e));
```

## 🔍 **Kemungkinan Penyebab Error**

### **1. URL Endpoint Salah**
```javascript
// SALAH
this.baseURL = '/api/calendar/';  // trailing slash
this.baseURL = 'api/calendar';    // tanpa leading slash
this.baseURL = '/calendar';       // tanpa api prefix

// BENAR
this.baseURL = '/api/calendar';
```

### **2. Method HTTP Salah**
```javascript
// SALAH
method: 'GET'  // untuk tambah data

// BENAR
method: 'POST'  // untuk tambah data
```

### **3. Headers Salah**
```javascript
// SALAH
headers: {
  'Content-Type': 'application/json'
  // tanpa Authorization
}

// BENAR
headers: {
  'Content-Type': 'application/json',
  'Authorization': `Bearer ${token}`,
  'Accept': 'application/json'
}
```

### **4. Token Expired/Invalid**
```javascript
// Cek token
const token = localStorage.getItem('token');
if (!token) {
  console.error('❌ No token found - need to login');
}
```

## 🚀 **Quick Fix Checklist**

- [ ] **Cek token** di localStorage
- [ ] **Cek user role** adalah 'HR'
- [ ] **Cek URL endpoint** adalah '/api/calendar'
- [ ] **Cek method** adalah 'POST'
- [ ] **Cek headers** ada Authorization dan Content-Type
- [ ] **Cek body** adalah JSON valid
- [ ] **Test dengan browser console**
- [ ] **Cek network tab** untuk request yang dikirim

## 📝 **Jika Masih Error**

1. **Clear browser cache**
2. **Logout dan login ulang**
3. **Restart Laravel server**: `php artisan serve`
4. **Clear Laravel cache**: `php artisan cache:clear`
5. **Periksa Laravel logs**: `storage/logs/laravel.log`

## 🎯 **Expected Result**

Setelah perbaikan:
- ✅ Icon tambah di kalender berfungsi
- ✅ Data hari libur masuk ke database
- ✅ Tidak ada error `"<!DOCTYPE "... is not valid JSON`
- ✅ Response dari server adalah JSON, bukan HTML

**Silakan lakukan langkah-langkah debug di atas dan beri tahu hasilnya!** 🚀 