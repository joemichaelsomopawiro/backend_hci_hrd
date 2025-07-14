# 🗓️ Panduan Implementasi Frontend - Sistem Kalender Nasional

## 📋 **File yang Perlu Dibuat di Frontend**

### 1. Service File
**Lokasi:** `src/services/calendarService.js`

```javascript
class CalendarService {
  constructor() {
    this.baseURL = '/api/calendar';
  }

  getHeaders() {
    const token = localStorage.getItem('token');
    return {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    };
  }

  async getCalendarData(year = null, month = null) {
    try {
      const params = new URLSearchParams();
      if (year) params.append('year', year);
      if (month) params.append('month', month);
      
      const response = await fetch(`${this.baseURL}/data?${params}`, {
        method: 'GET',
        headers: this.getHeaders()
      });
      
      return await response.json();
    } catch (error) {
      console.error('Error fetching calendar data:', error);
      return { success: false, data: { calendar: [], holidays: [] } };
    }
  }

  async getHolidays(year = null, month = null) {
    try {
      const params = new URLSearchParams();
      if (year) params.append('year', year);
      if (month) params.append('month', month);
      
      const response = await fetch(`${this.baseURL}?${params}`, {
        method: 'GET',
        headers: this.getHeaders()
      });
      
      return await response.json();
    } catch (error) {
      console.error('Error fetching holidays:', error);
      return { success: false, data: [] };
    }
  }

  async addHoliday(holidayData) {
    try {
      const response = await fetch(`${this.baseURL}`, {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify(holidayData)
      });
      
      return await response.json();
    } catch (error) {
      console.error('Error adding holiday:', error);
      return { success: false, message: 'Gagal menambah hari libur' };
    }
  }

  async updateHoliday(id, holidayData) {
    try {
      const response = await fetch(`${this.baseURL}/${id}`, {
        method: 'PUT',
        headers: this.getHeaders(),
        body: JSON.stringify(holidayData)
      });
      
      return await response.json();
    } catch (error) {
      console.error('Error updating holiday:', error);
      return { success: false, message: 'Gagal memperbarui hari libur' };
    }
  }

  async deleteHoliday(id) {
    try {
      const response = await fetch(`${this.baseURL}/${id}`, {
        method: 'DELETE',
        headers: this.getHeaders()
      });
      
      return await response.json();
    } catch (error) {
      console.error('Error deleting holiday:', error);
      return { success: false, message: 'Gagal menghapus hari libur' };
    }
  }

  async checkHoliday(date) {
    try {
      const response = await fetch(`${this.baseURL}/check?date=${date}`, {
        method: 'GET',
        headers: this.getHeaders()
      });
      
      return await response.json();
    } catch (error) {
      console.error('Error checking holiday:', error);
      return { success: false, data: { is_holiday: false, holiday_name: null } };
    }
  }

  // Helper method untuk generate calendar days
  generateCalendarDays(year, month) {
    const firstDay = new Date(year, month - 1, 1);
    const lastDay = new Date(year, month, 0);
    const startDate = new Date(firstDay);
    const endDate = new Date(lastDay);
    
    // Adjust start date to include previous month's days
    const dayOfWeek = firstDay.getDay();
    startDate.setDate(startDate.getDate() - dayOfWeek);
    
    const calendarDays = [];
    
    while (startDate <= endDate) {
      calendarDays.push({
        date: startDate.toISOString().split('T')[0],
        day: startDate.getDate(),
        isCurrentMonth: startDate.getMonth() === month - 1,
        isWeekend: startDate.getDay() === 0 || startDate.getDay() === 6,
        isToday: startDate.toDateString() === new Date().toDateString()
      });
      
      startDate.setDate(startDate.getDate() + 1);
    }
    
    return calendarDays;
  }

  // Helper method untuk format date
  formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  }

  // Helper method untuk get month name
  getMonthName(month) {
    const months = [
      'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return months[month - 1];
  }

  // Helper method untuk get day name
  getDayName(day) {
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return days[day];
  }
}

export default new CalendarService();
```

### 2. Komponen Calendar
**Lokasi:** `src/components/Calendar.vue`

```vue
<template>
  <div class="calendar-container">
    <!-- Header Kalender -->
    <div class="calendar-header">
      <div class="calendar-nav">
        <button @click="previousMonth" class="nav-btn">
          <i class="fas fa-chevron-left"></i>
        </button>
        <h2 class="calendar-title">{{ currentMonthYear }}</h2>
        <button @click="nextMonth" class="nav-btn">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
      
      <!-- Tombol Tambah Hari Libur (HR Only) -->
      <div v-if="isHR" class="calendar-actions">
        <button @click="showAddHolidayModal = true" class="btn btn-primary">
          <i class="fas fa-plus"></i>
          Tambah Hari Libur
        </button>
      </div>
    </div>

    <!-- Kalender Grid -->
    <div class="calendar-grid">
      <!-- Header Hari -->
      <div class="calendar-weekdays">
        <div v-for="day in weekDays" :key="day" class="weekday">
          {{ day }}
        </div>
      </div>

      <!-- Grid Hari -->
      <div class="calendar-days">
        <div 
          v-for="day in calendarDays" 
          :key="day.date"
          class="calendar-day"
          :class="getDayClasses(day)"
          @click="selectDay(day)"
        >
          <span class="day-number">{{ day.day }}</span>
          <div v-if="day.holiday_name" class="holiday-indicator">
            <i class="fas fa-star"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Daftar Hari Libur Bulan Ini -->
    <div class="holidays-list">
      <h3>Hari Libur {{ currentMonthYear }}</h3>
      <div v-if="monthHolidays.length === 0" class="no-holidays">
        <i class="fas fa-calendar-times"></i>
        <p>Tidak ada hari libur di bulan ini</p>
      </div>
      <div v-else class="holiday-items">
        <div 
          v-for="holiday in monthHolidays" 
          :key="holiday.id"
          class="holiday-item"
          :class="holiday.type"
        >
          <div class="holiday-info">
            <span class="holiday-date">{{ formatDate(holiday.date) }}</span>
            <span class="holiday-name">{{ holiday.name }}</span>
            <span v-if="holiday.description" class="holiday-desc">{{ holiday.description }}</span>
          </div>
          
          <!-- Actions untuk HR -->
          <div v-if="isHR && holiday.type !== 'national'" class="holiday-actions">
            <button @click="editHoliday(holiday)" class="btn-icon edit">
              <i class="fas fa-edit"></i>
            </button>
            <button @click="deleteHoliday(holiday.id)" class="btn-icon delete">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Tambah/Edit Hari Libur -->
    <div v-if="showAddHolidayModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>{{ editingHoliday ? 'Edit' : 'Tambah' }} Hari Libur</h3>
          <button @click="closeModal" class="close-btn">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="modal-body">
          <div class="form-group">
            <label>Tanggal</label>
            <input 
              v-model="holidayForm.date" 
              type="date" 
              class="form-input"
              :disabled="editingHoliday"
            />
          </div>
          
          <div class="form-group">
            <label>Nama Hari Libur</label>
            <input 
              v-model="holidayForm.name" 
              type="text" 
              class="form-input"
              placeholder="Contoh: Libur Perusahaan"
            />
          </div>
          
          <div class="form-group">
            <label>Deskripsi (Opsional)</label>
            <textarea 
              v-model="holidayForm.description" 
              class="form-textarea"
              placeholder="Deskripsi hari libur..."
              rows="3"
            ></textarea>
          </div>
          
          <div class="form-group">
            <label>Jenis</label>
            <select v-model="holidayForm.type" class="form-select">
              <option value="custom">Libur Khusus</option>
              <option value="national">Libur Nasional</option>
            </select>
          </div>
        </div>
        
        <div class="modal-footer">
          <button @click="closeModal" class="btn btn-secondary">Batal</button>
          <button @click="saveHoliday" class="btn btn-primary">
            {{ editingHoliday ? 'Update' : 'Simpan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import calendarService from '../services/calendarService.js';

export default {
  name: 'Calendar',
  data() {
    return {
      currentYear: new Date().getFullYear(),
      currentMonth: new Date().getMonth() + 1,
      calendarDays: [],
      monthHolidays: [],
      showAddHolidayModal: false,
      editingHoliday: null,
      holidayForm: {
        date: '',
        name: '',
        description: '',
        type: 'custom'
      },
      weekDays: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
      isHR: false
    };
  },
  
  computed: {
    currentMonthYear() {
      const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
      ];
      return `${months[this.currentMonth - 1]} ${this.currentYear}`;
    }
  },
  
  async mounted() {
    this.checkUserRole();
    await this.loadCalendarData();
  },
  
  methods: {
    checkUserRole() {
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      this.isHR = ['hr', 'hr_manager'].includes(user.role);
    },
    
    async loadCalendarData() {
      const response = await calendarService.getCalendarData(this.currentYear, this.currentMonth);
      if (response.success) {
        this.calendarDays = response.data.calendar;
        this.monthHolidays = response.data.holidays;
      } else {
        // Fallback: generate calendar days locally
        this.calendarDays = calendarService.generateCalendarDays(this.currentYear, this.currentMonth);
        this.monthHolidays = [];
      }
    },
    
    previousMonth() {
      if (this.currentMonth === 1) {
        this.currentMonth = 12;
        this.currentYear--;
      } else {
        this.currentMonth--;
      }
      this.loadCalendarData();
    },
    
    nextMonth() {
      if (this.currentMonth === 12) {
        this.currentMonth = 1;
        this.currentYear++;
      } else {
        this.currentMonth++;
      }
      this.loadCalendarData();
    },
    
    getDayClasses(day) {
      const classes = [];
      
      if (day.is_holiday) classes.push('holiday');
      if (day.is_weekend) classes.push('weekend');
      if (day.is_today) classes.push('today');
      if (day.holiday_name) classes.push('has-holiday');
      if (!day.isCurrentMonth) classes.push('other-month');
      
      return classes;
    },
    
    selectDay(day) {
      if (day.holiday_name) {
        this.$emit('day-selected', day);
      }
    },
    
    formatDate(dateString) {
      return calendarService.formatDate(dateString);
    },
    
    editHoliday(holiday) {
      this.editingHoliday = holiday;
      this.holidayForm = {
        date: holiday.date,
        name: holiday.name,
        description: holiday.description || '',
        type: holiday.type
      };
      this.showAddHolidayModal = true;
    },
    
    async deleteHoliday(id) {
      if (confirm('Apakah Anda yakin ingin menghapus hari libur ini?')) {
        const response = await calendarService.deleteHoliday(id);
        if (response.success) {
          this.$emit('holiday-deleted');
          await this.loadCalendarData();
        } else {
          alert(response.message);
        }
      }
    },
    
    async saveHoliday() {
      if (!this.holidayForm.date || !this.holidayForm.name) {
        alert('Tanggal dan nama hari libur wajib diisi');
        return;
      }
      
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
        alert(response.message);
      }
    },
    
    closeModal() {
      this.showAddHolidayModal = false;
      this.editingHoliday = null;
      this.holidayForm = {
        date: '',
        name: '',
        description: '',
        type: 'custom'
      };
    }
  }
};
</script>

<style scoped>
.calendar-container {
  background: var(--bg-card);
  border-radius: var(--radius-lg);
  padding: 1.5rem;
  box-shadow: var(--shadow-sm);
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.calendar-nav {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.nav-btn {
  background: var(--bg-hover);
  border: none;
  border-radius: var(--radius);
  padding: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.nav-btn:hover {
  background: var(--primary-color);
  color: white;
}

.calendar-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.calendar-grid {
  margin-bottom: 2rem;
}

.calendar-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  margin-bottom: 1px;
}

.weekday {
  background: var(--bg-hover);
  padding: 0.75rem;
  text-align: center;
  font-weight: 600;
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.calendar-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
}

.calendar-day {
  background: var(--bg-primary);
  padding: 0.75rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  min-height: 60px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.calendar-day:hover {
  background: var(--bg-hover);
}

.calendar-day.holiday {
  background: #fee2e2;
  color: #dc2626;
}

.calendar-day.weekend {
  background: #fef3c7;
  color: #d97706;
}

.calendar-day.today {
  background: var(--primary-color);
  color: white;
  font-weight: 600;
}

.calendar-day.has-holiday {
  font-weight: 600;
}

.calendar-day.other-month {
  opacity: 0.5;
  color: var(--text-muted);
}

.holiday-indicator {
  position: absolute;
  top: 2px;
  right: 2px;
  color: #dc2626;
  font-size: 0.75rem;
}

.day-number {
  font-size: 0.875rem;
}

.holidays-list {
  border-top: 1px solid var(--border-color);
  padding-top: 1.5rem;
}

.holidays-list h3 {
  margin-bottom: 1rem;
  color: var(--text-primary);
}

.no-holidays {
  text-align: center;
  color: var(--text-muted);
  padding: 2rem;
}

.no-holidays i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.holiday-items {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.holiday-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--bg-hover);
  border-radius: var(--radius);
  border-left: 4px solid var(--primary-color);
}

.holiday-item.national {
  border-left-color: #dc2626;
}

.holiday-item.custom {
  border-left-color: #059669;
}

.holiday-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.holiday-date {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.holiday-name {
  font-weight: 600;
  color: var(--text-primary);
}

.holiday-desc {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.holiday-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  background: none;
  border: none;
  padding: 0.25rem;
  cursor: pointer;
  border-radius: var(--radius);
  transition: all 0.2s;
}

.btn-icon.edit {
  color: var(--primary-color);
}

.btn-icon.edit:hover {
  background: var(--primary-color);
  color: white;
}

.btn-icon.delete {
  color: var(--error-color);
}

.btn-icon.delete:hover {
  background: var(--error-color);
  color: white;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: var(--bg-card);
  border-radius: var(--radius-lg);
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: var(--text-primary);
}

.form-input,
.form-textarea,
.form-select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  background: var(--bg-primary);
  color: var(--text-primary);
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: var(--radius);
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-dark);
}

.btn-secondary {
  background: var(--bg-hover);
  color: var(--text-primary);
}

.btn-secondary:hover {
  background: var(--border-color);
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: var(--text-muted);
}

.close-btn:hover {
  color: var(--text-primary);
}

/* Responsive Design */
@media (max-width: 768px) {
  .calendar-header {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .calendar-actions {
    display: flex;
    justify-content: center;
  }
  
  .calendar-day {
    min-height: 50px;
    padding: 0.5rem;
  }
  
  .day-number {
    font-size: 0.75rem;
  }
  
  .holiday-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .holiday-actions {
    align-self: flex-end;
  }
}
</style>
```

### 3. Update Dashboard
**Lokasi:** `src/views/Dashboard.vue` (tambahkan ke dashboard yang sudah ada)

```vue
<template>
  <div class="dashboard-container">
    <!-- Existing dashboard content -->
    
    <!-- Tambahkan komponen kalender -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2>Kalender Nasional</h2>
        <p>Lihat hari libur nasional dan khusus</p>
      </div>
      
      <Calendar 
        @day-selected="handleDaySelected"
        @holiday-saved="handleHolidaySaved"
        @holiday-deleted="handleHolidayDeleted"
      />
    </div>
  </div>
</template>

<script>
import Calendar from '../components/Calendar.vue';

export default {
  name: 'Dashboard',
  components: {
    Calendar
  },
  
  methods: {
    handleDaySelected(day) {
      console.log('Selected day:', day);
      // Handle day selection - bisa untuk show detail atau trigger action
    },
    
    handleHolidaySaved() {
      // Refresh data jika diperlukan
      console.log('Holiday saved');
      this.$toast.success('Hari libur berhasil disimpan');
    },
    
    handleHolidayDeleted() {
      // Refresh data jika diperlukan
      console.log('Holiday deleted');
      this.$toast.success('Hari libur berhasil dihapus');
    }
  }
};
</script>
```

## 🚀 **Langkah Implementasi Frontend**

### 1. Buat Service File
```bash
# Di folder frontend Anda
mkdir -p src/services
# Copy paste kode calendarService.js ke file tersebut
```

### 2. Buat Komponen Calendar
```bash
# Di folder frontend Anda
mkdir -p src/components
# Copy paste kode Calendar.vue ke file tersebut
```

### 3. Update Dashboard
```bash
# Edit file Dashboard.vue yang sudah ada
# Tambahkan import dan komponen Calendar
```

### 4. Install Dependencies (jika belum ada)
```bash
npm install
# Pastikan FontAwesome sudah terinstall untuk icons
```

## 🧪 **Testing Frontend**

### 1. Test API Connection
```javascript
// Di browser console
import calendarService from './services/calendarService.js';

// Test get calendar data
calendarService.getCalendarData(2024, 8).then(response => {
  console.log('Calendar data:', response);
});

// Test check holiday
calendarService.checkHoliday('2024-08-17').then(response => {
  console.log('Holiday check:', response);
});
```

### 2. Test Component
```bash
# Buka aplikasi frontend
# Login sebagai HR
# Coba tambah hari libur
# Login sebagai user biasa
# Cek kalender
```

## 📱 **Responsive Design**

Komponen sudah responsive dengan:
- ✅ Desktop: Kalender full size
- ✅ Tablet: Kalender responsive
- ✅ Mobile: Kalender compact dengan scroll

## 🎨 **Customization**

### CSS Variables yang Digunakan:
```css
:root {
  --bg-card: #ffffff;
  --bg-primary: #f8fafc;
  --bg-hover: #f1f5f9;
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --text-muted: #94a3b8;
  --primary-color: #3b82f6;
  --primary-dark: #2563eb;
  --border-color: #e2e8f0;
  --radius: 0.375rem;
  --radius-lg: 0.5rem;
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --error-color: #ef4444;
}
```

### Warna Hari Libur:
- **Hari Libur Nasional:** Merah (#dc2626)
- **Weekend:** Kuning (#d97706)
- **Hari Libur Khusus:** Merah dengan border hijau
- **Hari Ini:** Biru primary

## 🔒 **Security**

- ✅ Role-based access control
- ✅ HR only untuk tambah/edit/hapus
- ✅ Validasi input di frontend dan backend
- ✅ Token authentication

## 🎯 **Fitur yang Tersedia**

### Untuk Semua User:
- ✅ Lihat kalender dengan hari libur nasional
- ✅ Lihat weekend (Sabtu-Minggu) otomatis merah
- ✅ Lihat hari libur khusus yang ditambahkan HR
- ✅ Navigasi bulan (previous/next)
- ✅ Daftar hari libur bulan ini

### Khusus HR:
- ✅ Tambah hari libur khusus
- ✅ Edit hari libur khusus
- ✅ Hapus hari libur khusus
- ✅ Tidak bisa edit/hapus hari libur nasional

## 📊 **API Endpoints yang Digunakan**

```javascript
// GET /api/calendar/data?year=2024&month=8
// GET /api/calendar/check?date=2024-08-17
// GET /api/calendar?year=2024&month=8
// POST /api/calendar (HR only)
// PUT /api/calendar/{id} (HR only)
// DELETE /api/calendar/{id} (HR only)
```

## 🎉 **Selesai!**

Setelah mengikuti semua langkah di atas, sistem kalender nasional akan berfungsi penuh di frontend Anda dengan:
- ✅ Kalender visual yang menarik
- ✅ Manajemen hari libur oleh HR
- ✅ Responsive design
- ✅ Role-based access control
- ✅ Real-time updates

Sistem ini akan memberikan pengalaman yang lebih baik untuk manajemen cuti dan perencanaan kerja karyawan! 🚀 