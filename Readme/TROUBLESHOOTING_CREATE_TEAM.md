# 🔧 TROUBLESHOOTING: Error 422 Create Production Team

## ❌ Error yang Terjadi

```
POST http://localhost:8000/api/live-tv/production-teams 422 (Unprocessable Content)
Error: {success: false, message: 'Validation failed', errors: {name: Array(1)}}
```

---

## 🔍 PENYEBAB UMUM

### **1. Nama Team Sudah Ada (Unique Constraint)**

**Error Message:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name has already been taken."]
  }
}
```

**Penyebab:**
- Nama team yang dikirim sudah ada di database
- Validasi `unique:production_teams,name` gagal

**Solusi:**
1. **Cek nama team yang sudah ada:**
   ```sql
   SELECT id, name FROM production_teams WHERE name = 'Nama Team Anda';
   ```

2. **Gunakan nama yang berbeda:**
   ```javascript
   // Frontend: Tambahkan timestamp atau counter
   const teamName = `${formData.name} - ${new Date().getTime()}`;
   // atau
   const teamName = `${formData.name} (${Math.random().toString(36).substr(2, 5)})`;
   ```

3. **Cek sebelum submit (frontend):**
   ```javascript
   // Cek apakah nama sudah ada
   async function checkTeamNameExists(name) {
     const response = await api.get(`/live-tv/production-teams?search=${name}`);
     return response.data.data.some(team => team.name === name);
   }
   ```

---

### **2. Nama Kosong atau Null**

**Error Message:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

**Penyebab:**
- Field `name` tidak dikirim atau null
- Frontend tidak mengirim data dengan benar

**Solusi:**
1. **Pastikan frontend mengirim data:**
   ```javascript
   // ✅ BENAR
   const data = {
     name: formData.name.trim(), // Pastikan tidak kosong
     description: formData.description || null,
     producer_id: formData.producer_id
   };
   
   // ❌ SALAH
   const data = {
     name: formData.name || '', // Bisa kosong
     producer_id: formData.producer_id
   };
   ```

2. **Validasi di frontend sebelum submit:**
   ```javascript
   if (!formData.name || formData.name.trim() === '') {
     alert('Nama team harus diisi!');
     return;
   }
   ```

---

### **3. Nama Terlalu Panjang**

**Error Message:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name may not be greater than 255 characters."]
  }
}
```

**Solusi:**
- Batasi panjang nama maksimal 255 karakter
- Validasi di frontend:
  ```javascript
  if (formData.name.length > 255) {
    alert('Nama team maksimal 255 karakter!');
    return;
  }
  ```

---

### **4. Producer ID Tidak Valid**

**Error Message:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "producer_id": ["The selected producer id is invalid."]
  }
}
```

**Penyebab:**
- `producer_id` tidak ada di database
- `producer_id` null atau tidak dikirim
- `producer_id` bukan angka

**Solusi:**
1. **Cek producer_id di database:**
   ```sql
   SELECT id, name, email FROM users WHERE id = <producer_id>;
   ```

2. **Pastikan frontend mengirim producer_id yang valid:**
   ```javascript
   // ✅ BENAR
   const data = {
     name: formData.name,
     producer_id: parseInt(formData.producer_id), // Pastikan integer
   };
   
   // ❌ SALAH
   const data = {
     name: formData.name,
     producer_id: formData.producer_id || null, // Bisa null
   };
   ```

3. **Validasi di frontend:**
   ```javascript
   if (!formData.producer_id || isNaN(formData.producer_id)) {
     alert('Producer harus dipilih!');
     return;
   }
   ```

---

### **5. Created By Tidak Valid (Jika Dikirim)**

**Error Message:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "created_by": ["The selected created by is invalid."]
  }
}
```

**Solusi:**
- **Jangan kirim `created_by`** - akan auto dari auth
- Atau pastikan `created_by` adalah ID user yang valid

```javascript
// ✅ BENAR - Jangan kirim created_by
const data = {
  name: formData.name,
  description: formData.description,
  producer_id: formData.producer_id
  // created_by akan auto dari auth
};

// ❌ SALAH
const data = {
  name: formData.name,
  producer_id: formData.producer_id,
  created_by: null // atau ID yang tidak valid
};
```

---

## 🛠️ CARA DEBUG

### **1. Cek Request yang Dikirim**

**Di Browser Console:**
```javascript
// Tambahkan di frontend sebelum API call
console.log('📤 Request Data:', {
  name: formData.name,
  description: formData.description,
  producer_id: formData.producer_id,
  created_by: formData.created_by
});
```

**Di Network Tab:**
1. Buka Developer Tools (F12)
2. Tab Network
3. Cari request `POST /api/live-tv/production-teams`
4. Klik request → Tab Payload
5. Lihat data yang dikirim

### **2. Cek Response Error Detail**

**Di Browser Console:**
```javascript
// Tambahkan di error handler
catch (error) {
  console.error('❌ Error Response:', error.response?.data);
  console.error('❌ Error Details:', error.response?.data?.errors);
  console.error('❌ Error Details (formatted):', error.response?.data?.error_details);
}
```

### **3. Cek Log Backend**

**Di Laravel Log:**
```bash
# Windows (PowerShell)
Get-Content storage\logs\laravel.log -Tail 50 | Select-String "Production Team"

# Atau buka file langsung
# storage/logs/laravel.log
```

**Cari log dengan format:**
```
[2025-12-12 10:00:00] local.WARNING: Production Team creation validation failed
{
  "errors": {...},
  "request_data": {...}
}
```

---

## ✅ CONTOH REQUEST YANG BENAR

### **Request Body yang Valid:**

```json
{
  "name": "Tim Produksi Musik A",
  "description": "Tim untuk program musik",
  "producer_id": 5
}
```

**Atau dengan created_by (optional):**
```json
{
  "name": "Tim Produksi Musik A",
  "description": "Tim untuk program musik",
  "producer_id": 5,
  "created_by": 1
}
```

### **JavaScript/Axios Example:**

```javascript
// ✅ BENAR
async function createTeam(formData) {
  try {
    // Validasi di frontend dulu
    if (!formData.name || formData.name.trim() === '') {
      throw new Error('Nama team harus diisi!');
    }
    
    if (!formData.producer_id) {
      throw new Error('Producer harus dipilih!');
    }
    
    // Prepare data
    const data = {
      name: formData.name.trim(), // Trim whitespace
      description: formData.description || null,
      producer_id: parseInt(formData.producer_id) // Pastikan integer
      // Jangan kirim created_by - akan auto dari auth
    };
    
    console.log('📤 Sending data:', data);
    
    const response = await api.post('/live-tv/production-teams', data);
    
    if (response.data.success) {
      console.log('✅ Team created:', response.data.data);
      return response.data;
    }
  } catch (error) {
    // Handle error
    if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      console.error('❌ Validation errors:', errors);
      
      // Tampilkan error per field
      Object.keys(errors).forEach(field => {
        console.error(`${field}:`, errors[field]);
        // Tampilkan ke user
        alert(`${field}: ${errors[field][0]}`);
      });
    } else {
      console.error('❌ Error:', error);
      alert('Gagal membuat team: ' + (error.response?.data?.message || error.message));
    }
    throw error;
  }
}
```

---

## 🔍 CHECKLIST SEBELUM SUBMIT

- [ ] Nama team sudah diisi dan tidak kosong
- [ ] Nama team tidak lebih dari 255 karakter
- [ ] Nama team belum ada di database (cek dulu)
- [ ] Producer ID sudah dipilih dan valid
- [ ] Producer ID adalah angka (integer)
- [ ] Tidak mengirim `created_by` (atau jika dikirim, pastikan valid)
- [ ] Data dikirim dalam format JSON yang benar
- [ ] Authorization header sudah ada (Bearer token)

---

## 📝 CONTOH IMPLEMENTASI FRONTEND YANG BENAR

```vue
<template>
  <div>
    <form @submit.prevent="handleSubmit">
      <!-- Name Input -->
      <div class="form-group">
        <label>Nama Team *</label>
        <input
          v-model="form.name"
          type="text"
          required
          maxlength="255"
          :class="{ 'error': errors.name }"
          @input="errors.name = null"
        />
        <span v-if="errors.name" class="error-text">
          {{ errors.name[0] }}
        </span>
      </div>

      <!-- Producer Selection -->
      <div class="form-group">
        <label>Producer *</label>
        <select
          v-model="form.producer_id"
          required
          :class="{ 'error': errors.producer_id }"
          @change="errors.producer_id = null"
        >
          <option value="">Pilih Producer</option>
          <option
            v-for="producer in producers"
            :key="producer.id"
            :value="producer.id"
          >
            {{ producer.name }}
          </option>
        </select>
        <span v-if="errors.producer_id" class="error-text">
          {{ errors.producer_id[0] }}
        </span>
      </div>

      <button type="submit" :disabled="loading">
        {{ loading ? 'Membuat...' : 'Buat Team' }}
      </button>
    </form>
  </div>
</template>

<script>
import { productionTeamService } from '@/services/productionTeamService';

export default {
  data() {
    return {
      form: {
        name: '',
        description: '',
        producer_id: '',
      },
      producers: [],
      errors: {},
      loading: false,
    };
  },
  methods: {
    async handleSubmit() {
      // Reset errors
      this.errors = {};
      this.loading = true;

      try {
        // Validasi frontend
        if (!this.form.name || this.form.name.trim() === '') {
          this.errors.name = ['Nama team harus diisi'];
          this.loading = false;
          return;
        }

        if (!this.form.producer_id) {
          this.errors.producer_id = ['Producer harus dipilih'];
          this.loading = false;
          return;
        }

        // Prepare data
        const data = {
          name: this.form.name.trim(),
          description: this.form.description || null,
          producer_id: parseInt(this.form.producer_id),
          // Jangan kirim created_by
        };

        console.log('📤 Creating team with data:', data);

        // API call
        const response = await productionTeamService.createTeam(data);

        if (response.success) {
          this.$toast.success('Team berhasil dibuat!');
          // Reset form atau redirect
          this.$router.push(`/teams/${response.data.id}/members`);
        }
      } catch (error) {
        console.error('❌ Error creating team:', error);

        if (error.response?.status === 422) {
          // Set errors untuk ditampilkan
          this.errors = error.response.data.errors || {};
          
          // Tampilkan error message
          const errorMessages = Object.values(this.errors)
            .flat()
            .join(', ');
          this.$toast.error('Validasi gagal: ' + errorMessages);
        } else {
          this.$toast.error(
            'Gagal membuat team: ' +
              (error.response?.data?.message || error.message)
          );
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
```

---

## 🎯 QUICK FIX

Jika error `{name: Array(1)}` muncul:

1. **Cek di browser console** - expand `errors.name` untuk lihat pesan error lengkap
2. **Cek Network tab** - lihat request payload yang dikirim
3. **Cek log backend** - lihat detail error di `storage/logs/laravel.log`
4. **Pastikan nama unik** - cek database apakah nama sudah ada
5. **Pastikan producer_id valid** - cek apakah ID ada di database

---

**Last Updated:** 2025-12-12  
**Created By:** AI Assistant

