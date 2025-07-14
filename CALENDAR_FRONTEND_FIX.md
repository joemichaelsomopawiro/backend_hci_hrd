# 🔧 Perbaikan Masalah Calendar Frontend

## 🚨 Masalah yang Ditemukan

1. **Button "Tambah Hari Libur"** ✅ Berhasil
2. **Icon tambah di kalender** ❌ Error: `SyntaxError: Unexpected token '<', "<!DOCTYPE "... is not valid JSON`

## 🔍 Analisis Masalah

Error `"<!DOCTYPE "... is not valid JSON` menunjukkan bahwa server mengembalikan halaman HTML alih-alih response JSON. Ini terjadi karena:

1. **URL endpoint salah**
2. **Method HTTP salah**
3. **Route tidak ditemukan**
4. **Server error (500)**
5. **CORS issue**
6. **Authentication/Authorization issue**

## ✅ Solusi yang Diterapkan

### 1. **Perbaikan Backend (SELESAI)**
- ✅ Role HR sudah diperbaiki dari `'hr'` ke `'HR'`
- ✅ Controller sudah menggunakan `['HR']` untuk pengecekan role
- ✅ Routes sudah menggunakan `['role:HR']` untuk middleware
- ✅ Database sudah bisa menerima insert manual

### 2. **Perbaikan Frontend (PERLU DILAKUKAN)**

#### A. **Periksa calendarService.js**
Pastikan method `addHoliday` menggunakan URL yang benar:

```javascript
// calendarService.js
async addHoliday(holidayData) {
  try {
    const response = await fetch(`${this.baseURL}`, {  // ← Pastikan ini '/api/calendar'
      method: 'POST',
      headers: this.getHeaders(),
      body: JSON.stringify(holidayData)
    });
    
    // Tambahkan error handling yang lebih baik
    if (!response.ok) {
      const errorText = await response.text();
      console.error('HTTP Error:', response.status, errorText);
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }
    
    return await response.json();
  } catch (error) {
    console.error('Error adding holiday:', error);
    return { success: false, message: 'Gagal menambah hari libur: ' + error.message };
  }
}
```

#### B. **Periksa Component Calendar.vue**
Pastikan method `saveHoliday` menggunakan service yang benar:

```javascript
// Calendar.vue
async saveHoliday() {
  if (!this.holidayForm.date || !this.holidayForm.name) {
    alert('Tanggal dan nama hari libur wajib diisi');
    return;
  }
  
  try {
    let response;
    if (this.editingHoliday) {
      response = await calendarService.updateHoliday(this.editingHoliday.id, this.holidayForm);
    } else {
      response = await calendarService.addHoliday(this.holidayForm);
    }
    
    if (response.success) {
      this.closeModal();
      await this.loadCalendarData();
      this.$emit('holiday-saved');
    } else {
      alert(response.message || 'Gagal menyimpan hari libur');
    }
  } catch (error) {
    console.error('Error saving holiday:', error);
    alert('Terjadi kesalahan: ' + error.message);
  }
}
```

#### C. **Periksa Icon Tambah di Kalender**
Pastikan icon tambah menggunakan method yang sama dengan button:

```javascript
// Calendar.vue - untuk icon tambah di tanggal
addHolidayForDate(date) {
  this.holidayForm.date = date;
  this.holidayForm.name = '';
  this.holidayForm.description = '';
  this.holidayForm.type = 'custom';
  this.editingHoliday = null;
  this.showAddHolidayModal = true;
}
```

### 3. **Debug Steps**

#### Step 1: Cek Network Tab
1. Buka browser developer tools
2. Buka tab Network
3. Coba tambah hari libur dengan icon
4. Lihat request yang dikirim:
   - **URL**: Harus `POST /api/calendar`
   - **Headers**: Harus ada `Authorization: Bearer <token>`
   - **Body**: Harus JSON valid

#### Step 2: Cek Console Errors
1. Buka browser console
2. Coba tambah hari libur
3. Lihat error yang muncul

#### Step 3: Test dengan cURL
```bash
# Login sebagai HR dan dapatkan token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"hr@company.com","password":"password"}'

# Test tambah hari libur
curl -X POST http://localhost:8000/api/calendar \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "date": "2024-12-28",
    "name": "Libur Test cURL",
    "description": "Test via cURL",
    "type": "custom"
  }'
```

### 4. **Kemungkinan Penyebab Error**

#### A. **URL Salah**
```javascript
// SALAH
this.baseURL = '/api/calendar/';  // trailing slash
this.baseURL = 'api/calendar';    // tanpa leading slash

// BENAR
this.baseURL = '/api/calendar';
```

#### B. **Method HTTP Salah**
```javascript
// SALAH
method: 'GET'  // untuk tambah data

// BENAR
method: 'POST'  // untuk tambah data
```

#### C. **Headers Salah**
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

#### D. **Token Expired/Invalid**
```javascript
// Cek token di localStorage
console.log('Token:', localStorage.getItem('token'));
console.log('User:', localStorage.getItem('user'));
```

### 5. **Quick Fix Script**

Buat file `fix_calendar_frontend.js`:

```javascript
// fix_calendar_frontend.js
// Script untuk debug dan fix calendar frontend

// 1. Cek token
const token = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');

console.log('Token:', token ? 'Present' : 'Missing');
console.log('User Role:', user.role);

// 2. Test API endpoint
async function testCalendarAPI() {
  try {
    const response = await fetch('/api/calendar/data?year=2024&month=12', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      console.log('✅ GET /api/calendar/data successful:', data);
    } else {
      console.error('❌ GET /api/calendar/data failed:', response.status);
    }
  } catch (error) {
    console.error('❌ Error testing API:', error);
  }
}

// 3. Test POST endpoint
async function testPostCalendar() {
  try {
    const holidayData = {
      date: '2024-12-29',
      name: 'Libur Test Frontend',
      description: 'Test dari frontend',
      type: 'custom'
    };
    
    const response = await fetch('/api/calendar', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(holidayData)
    });
    
    if (response.ok) {
      const data = await response.json();
      console.log('✅ POST /api/calendar successful:', data);
    } else {
      const errorText = await response.text();
      console.error('❌ POST /api/calendar failed:', response.status, errorText);
    }
  } catch (error) {
    console.error('❌ Error testing POST:', error);
  }
}

// Run tests
testCalendarAPI();
testPostCalendar();
```

### 6. **Langkah Verifikasi**

1. **Jalankan script debug di browser console**
2. **Periksa apakah token valid**
3. **Periksa apakah role user adalah 'HR'**
4. **Periksa apakah endpoint bisa diakses**
5. **Periksa apakah POST request berhasil**

### 7. **Jika Masih Error**

1. **Clear browser cache**
2. **Logout dan login ulang**
3. **Periksa Laravel logs**: `storage/logs/laravel.log`
4. **Restart Laravel server**: `php artisan serve`
5. **Clear Laravel cache**: `php artisan cache:clear`

## 🎯 Expected Result

Setelah perbaikan:
- ✅ Button "Tambah Hari Libur" tetap berfungsi
- ✅ Icon tambah di kalender berfungsi
- ✅ Data hari libur masuk ke database
- ✅ Tidak ada error `"<!DOCTYPE "... is not valid JSON`

## 📝 Catatan Penting

- **Konsistensi URL**: Pastikan semua request menggunakan URL yang sama
- **Error Handling**: Tambahkan error handling yang lebih baik di frontend
- **Debug Info**: Gunakan console.log untuk debug
- **Network Tab**: Selalu cek network tab untuk melihat request yang dikirim

**Silakan lakukan langkah-langkah di atas dan beri tahu hasilnya!** 🚀 