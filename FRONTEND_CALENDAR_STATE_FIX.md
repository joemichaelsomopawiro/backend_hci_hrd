# 🔄 Perbaikan State Management Frontend Calendar

## 🚨 **Masalah yang Ditemukan**

1. **Edit calendar hilang setelah git pull/push**
2. **Hari libur yang ditambah HR tidak muncul otomatis**
3. **Harus refresh manual untuk melihat perubahan**
4. **State tidak ter-update setelah operasi CRUD**

## ✅ **Solusi Frontend**

### **1. Perbaiki Calendar.vue - State Management**

```vue
<template>
  <div class="calendar-container">
    <!-- Calendar content -->
    <div class="calendar-grid">
      <div 
        v-for="day in calendarDays" 
        :key="day.date"
        class="calendar-day"
        :class="getDayClasses(day)"
        @click="handleDayClick(day)"
      >
        <span class="day-number">{{ day.day }}</span>
        <div v-if="day.isHoliday" class="holiday-indicator">★</div>
      </div>
    </div>

    <!-- Modal untuk add/edit holiday -->
    <div v-if="showModal" class="modal">
      <div class="modal-content">
        <h3>{{ isEditing ? 'Edit Hari Libur' : 'Tambah Hari Libur' }}</h3>
        <form @submit.prevent="saveHoliday">
          <input v-model="holidayForm.name" placeholder="Nama Hari Libur" required />
          <textarea v-model="holidayForm.description" placeholder="Deskripsi (opsional)" />
          <div class="modal-actions">
            <button type="submit" class="btn-primary">
              {{ isEditing ? 'Update' : 'Simpan' }}
            </button>
            <button type="button" @click="closeModal" class="btn-secondary">
              Batal
            </button>
            <button 
              v-if="isEditing" 
              type="button" 
              @click="deleteHoliday" 
              class="btn-danger"
            >
              Hapus
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, onMounted, watch } from 'vue';
import calendarService from '../services/calendarService';

export default {
  name: 'Calendar',
  
  setup() {
    // Reactive state
    const currentYear = ref(new Date().getFullYear());
    const currentMonth = ref(new Date().getMonth() + 1);
    const calendarDays = ref([]);
    const holidays = ref({});
    const showModal = ref(false);
    const isEditing = ref(false);
    const selectedDay = ref(null);
    
    const holidayForm = reactive({
      name: '',
      description: '',
      type: 'custom'
    });

    // Load calendar data
    const loadCalendarData = async () => {
      try {
        console.log('Loading calendar data...');
        const response = await calendarService.getCalendarData(currentYear.value, currentMonth.value);
        
        if (response.success) {
          // Update holidays state
          holidays.value = response.data || {};
          console.log('Holidays loaded:', holidays.value);
          
          // Generate calendar days
          generateCalendarDays();
        }
      } catch (error) {
        console.error('Error loading calendar data:', error);
      }
    };

    // Generate calendar days
    const generateCalendarDays = () => {
      const year = currentYear.value;
      const month = currentMonth.value;
      const firstDay = new Date(year, month - 1, 1);
      const lastDay = new Date(year, month, 0);
      const daysInMonth = lastDay.getDate();
      const startDayOfWeek = firstDay.getDay();
      
      const days = [];
      
      // Add empty days for padding
      for (let i = 0; i < startDayOfWeek; i++) {
        days.push({ day: '', date: '', isEmpty: true });
      }
      
      // Add days of month
      for (let day = 1; day <= daysInMonth; day++) {
        const date = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const holiday = holidays.value[date];
        const isWeekend = new Date(year, month - 1, day).getDay() === 0 || 
                         new Date(year, month - 1, day).getDay() === 6;
        
        days.push({
          day,
          date,
          isHoliday: !!holiday || isWeekend,
          holidayName: holiday?.name || (isWeekend ? (new Date(year, month - 1, day).getDay() === 0 ? 'Minggu' : 'Sabtu') : null),
          holidayType: holiday?.type || (isWeekend ? 'weekend' : null),
          holidayId: holiday?.id,
          isWeekend,
          isToday: date === new Date().toISOString().split('T')[0]
        });
      }
      
      calendarDays.value = days;
      console.log('Calendar days generated:', calendarDays.value);
    };

    // Handle day click
    const handleDayClick = (day) => {
      if (day.isEmpty) return;
      
      selectedDay.value = day;
      
      if (day.isHoliday && day.holidayId && day.holidayType !== 'national') {
        // Edit existing holiday (HR only)
        if (isHRUser()) {
          isEditing.value = true;
          holidayForm.name = day.holidayName;
          holidayForm.description = holidays.value[day.date]?.description || '';
          showModal.value = true;
        } else {
          // Show holiday info (non-HR)
          showHolidayInfo(day);
        }
      } else if (!day.isHoliday) {
        // Add new holiday (HR only)
        if (isHRUser()) {
          isEditing.value = false;
          holidayForm.name = '';
          holidayForm.description = '';
          showModal.value = true;
        }
      }
    };

    // Save holiday
    const saveHoliday = async () => {
      try {
        const holidayData = {
          date: selectedDay.value.date,
          name: holidayForm.name,
          description: holidayForm.description,
          type: 'custom'
        };

        let response;
        if (isEditing.value) {
          response = await calendarService.updateHoliday(selectedDay.value.holidayId, holidayData);
        } else {
          response = await calendarService.addHoliday(holidayData);
        }

        if (response.success) {
          console.log('Holiday saved successfully');
          
          // Update local state immediately
          await loadCalendarData();
          
          // Close modal
          closeModal();
          
          // Show success message
          showSuccessMessage(isEditing.value ? 'Hari libur berhasil diupdate' : 'Hari libur berhasil ditambahkan');
          
          // Emit event for parent component
          emit('holiday-updated');
        }
      } catch (error) {
        console.error('Error saving holiday:', error);
        showErrorMessage('Gagal menyimpan hari libur');
      }
    };

    // Delete holiday
    const deleteHoliday = async () => {
      if (!confirm('Yakin ingin menghapus hari libur ini?')) return;
      
      try {
        const response = await calendarService.deleteHoliday(selectedDay.value.holidayId);
        
        if (response.success) {
          console.log('Holiday deleted successfully');
          
          // Update local state immediately
          await loadCalendarData();
          
          // Close modal
          closeModal();
          
          // Show success message
          showSuccessMessage('Hari libur berhasil dihapus');
          
          // Emit event for parent component
          emit('holiday-updated');
        }
      } catch (error) {
        console.error('Error deleting holiday:', error);
        showErrorMessage('Gagal menghapus hari libur');
      }
    };

    // Close modal
    const closeModal = () => {
      showModal.value = false;
      isEditing.value = false;
      selectedDay.value = null;
      holidayForm.name = '';
      holidayForm.description = '';
    };

    // Check if user is HR
    const isHRUser = () => {
      const user = JSON.parse(localStorage.getItem('user') || '{}');
      return user.role === 'HR';
    };

    // Get day classes
    const getDayClasses = (day) => {
      const classes = ['calendar-day'];
      
      if (day.isEmpty) {
        classes.push('empty');
      } else {
        if (day.isToday) classes.push('today');
        if (day.isWeekend) classes.push('weekend');
        if (day.isHoliday) classes.push('holiday');
        if (day.holidayType === 'national') classes.push('national-holiday');
        if (day.holidayType === 'custom') classes.push('custom-holiday');
      }
      
      return classes;
    };

    // Show success message
    const showSuccessMessage = (message) => {
      // Implement your toast/notification system
      console.log('Success:', message);
    };

    // Show error message
    const showErrorMessage = (message) => {
      // Implement your toast/notification system
      console.error('Error:', message);
    };

    // Show holiday info
    const showHolidayInfo = (day) => {
      alert(`${day.holidayName}\nTanggal: ${day.date}`);
    };

    // Watch for month/year changes
    watch([currentYear, currentMonth], () => {
      loadCalendarData();
    });

    // Load data on mount
    onMounted(() => {
      loadCalendarData();
    });

    // Expose methods for parent component
    const refreshCalendar = () => {
      loadCalendarData();
    };

    return {
      currentYear,
      currentMonth,
      calendarDays,
      holidays,
      showModal,
      isEditing,
      selectedDay,
      holidayForm,
      handleDayClick,
      saveHoliday,
      deleteHoliday,
      closeModal,
      getDayClasses,
      refreshCalendar
    };
  }
};
</script>

<style scoped>
.calendar-container {
  position: relative;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: #e2e8f0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.calendar-day {
  background: white;
  padding: 8px;
  text-align: center;
  cursor: pointer;
  position: relative;
  min-height: 60px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.calendar-day:hover {
  background: #f8fafc;
}

.calendar-day.empty {
  background: #f8fafc;
  cursor: default;
}

.calendar-day.today {
  background: #3b82f6;
  color: white;
}

.calendar-day.weekend {
  background: #fef3c7;
}

.calendar-day.holiday {
  background: #fecaca;
  color: #dc2626;
}

.calendar-day.national-holiday {
  background: #fecaca;
  border: 2px solid #dc2626;
}

.calendar-day.custom-holiday {
  background: #fecaca;
  border: 2px solid #059669;
}

.holiday-indicator {
  font-size: 12px;
  color: #dc2626;
  margin-top: 2px;
}

.modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 24px;
  border-radius: 8px;
  min-width: 400px;
}

.modal-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.btn-primary {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-secondary {
  background: #6b7280;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-danger {
  background: #dc2626;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
}
</style>
```

### **2. Perbaiki calendarService.js - Real-time Updates**

```javascript
// src/services/calendarService.js
class CalendarService {
  constructor() {
    this.baseURL = '/api/calendar';
    this.cache = new Map();
    this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
  }

  getHeaders() {
    const token = localStorage.getItem('token');
    return {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    };
  }

  // Clear cache for specific month/year
  clearCache(year, month) {
    const key = `${year}-${month}`;
    this.cache.delete(key);
  }

  // Clear all cache
  clearAllCache() {
    this.cache.clear();
  }

  async getCalendarData(year = null, month = null) {
    try {
      const params = new URLSearchParams();
      if (year) params.append('year', year);
      if (month) params.append('month', month);
      
      const cacheKey = `${year}-${month}`;
      const cached = this.cache.get(cacheKey);
      
      if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
        console.log('Using cached calendar data');
        return cached.data;
      }
      
      const response = await fetch(`${this.baseURL}/data-frontend?${params}`, {
        method: 'GET',
        headers: this.getHeaders()
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      
      // Cache the result
      this.cache.set(cacheKey, {
        data: data,
        timestamp: Date.now()
      });
      
      return data;
    } catch (error) {
      console.error('Error fetching calendar data:', error);
      return { success: false, data: {} };
    }
  }

  async addHoliday(holidayData) {
    try {
      console.log('Adding holiday:', holidayData);
      
      const response = await fetch(`${this.baseURL}`, {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify(holidayData)
      });
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${errorText}`);
      }
      
      const result = await response.json();
      
      // Clear cache after successful operation
      this.clearCache(new Date(holidayData.date).getFullYear(), new Date(holidayData.date).getMonth() + 1);
      
      return result;
    } catch (error) {
      console.error('Error adding holiday:', error);
      return { success: false, message: 'Gagal menambah hari libur: ' + error.message };
    }
  }

  async updateHoliday(id, holidayData) {
    try {
      console.log('Updating holiday:', id, holidayData);
      
      const response = await fetch(`${this.baseURL}/${id}`, {
        method: 'PUT',
        headers: this.getHeaders(),
        body: JSON.stringify(holidayData)
      });
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${errorText}`);
      }
      
      const result = await response.json();
      
      // Clear cache after successful operation
      this.clearCache(new Date(holidayData.date).getFullYear(), new Date(holidayData.date).getMonth() + 1);
      
      return result;
    } catch (error) {
      console.error('Error updating holiday:', error);
      return { success: false, message: 'Gagal mengupdate hari libur: ' + error.message };
    }
  }

  async deleteHoliday(id) {
    try {
      console.log('Deleting holiday:', id);
      
      const response = await fetch(`${this.baseURL}/${id}`, {
        method: 'DELETE',
        headers: this.getHeaders()
      });
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${errorText}`);
      }
      
      const result = await response.json();
      
      // Clear all cache after delete operation
      this.clearAllCache();
      
      return result;
    } catch (error) {
      console.error('Error deleting holiday:', error);
      return { success: false, message: 'Gagal menghapus hari libur: ' + error.message };
    }
  }

  // Check if date is holiday
  async checkHoliday(date) {
    try {
      const response = await fetch(`${this.baseURL}/check?date=${date}`, {
        method: 'GET',
        headers: this.getHeaders()
      });
      
      return await response.json();
    } catch (error) {
      console.error('Error checking holiday:', error);
      return { success: false, data: { is_holiday: false } };
    }
  }
}

export default new CalendarService();
```

### **3. Perbaiki Dashboard.vue - Event Handling**

```vue
<template>
  <div class="dashboard-container">
    <!-- Existing dashboard content -->
    
    <!-- Calendar section -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2>Kalender Nasional</h2>
        <p>Lihat hari libur nasional dan khusus</p>
      </div>
      
      <Calendar 
        ref="calendarRef"
        @holiday-updated="handleHolidayUpdated"
      />
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import Calendar from '../components/Calendar.vue';

export default {
  name: 'Dashboard',
  components: {
    Calendar
  },
  
  setup() {
    const calendarRef = ref(null);
    
    const handleHolidayUpdated = () => {
      console.log('Holiday updated, refreshing calendar...');
      
      // Refresh calendar data
      if (calendarRef.value && calendarRef.value.refreshCalendar) {
        calendarRef.value.refreshCalendar();
      }
      
      // Show success notification
      showNotification('Kalender berhasil diperbarui', 'success');
    };
    
    const showNotification = (message, type = 'info') => {
      // Implement your notification system
      console.log(`${type.toUpperCase()}: ${message}`);
    };
    
    onMounted(() => {
      console.log('Dashboard mounted, calendar ready');
    });
    
    return {
      calendarRef,
      handleHolidayUpdated
    };
  }
};
</script>
```

## 🔧 **Perbaikan Backend - Cache Busting**

### **1. Tambahkan Cache Headers di Controller**

```php
// app/Http/Controllers/NationalHolidayController.php

public function getCalendarDataForFrontend(Request $request)
{
    $year = $request->get('year', date('Y'));
    $month = $request->get('month', date('n'));
    
    $holidays = NationalHoliday::getHolidaysByMonth($year, $month);
    
    $holidaysMap = [];
    foreach ($holidays as $holiday) {
        $holidaysMap[$holiday->date->format('Y-m-d')] = [
            'id' => $holiday->id,
            'date' => $holiday->date->format('Y-m-d'),
            'name' => $holiday->name,
            'description' => $holiday->description,
            'type' => $holiday->type,
            'is_active' => $holiday->is_active,
            'created_by' => $holiday->created_by,
            'updated_by' => $holiday->updated_by
        ];
    }
    
    return response()->json([
        'success' => true,
        'data' => $holidaysMap
    ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
      ->header('Pragma', 'no-cache')
      ->header('Expires', '0');
}
```

## 🚀 **Langkah Implementasi**

1. **Update Calendar.vue** dengan kode di atas
2. **Update calendarService.js** dengan cache management
3. **Update Dashboard.vue** dengan event handling
4. **Update Controller** dengan cache headers
5. **Test real-time updates**

## ✅ **Hasil yang Diharapkan**

- ✅ Calendar update otomatis setelah add/edit/delete
- ✅ Tidak perlu refresh manual
- ✅ State management yang proper
- ✅ Cache management yang efisien
- ✅ Real-time feedback untuk user

**Setelah implementasi ini, calendar akan update otomatis tanpa perlu refresh! 🎯** 