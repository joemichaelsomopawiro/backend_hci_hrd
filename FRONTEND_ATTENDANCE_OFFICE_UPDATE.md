# Frontend AttendanceOffice.vue - Sync Status Integration

## Overview
Frontend `AttendanceOffice.vue` telah diupdate untuk mengintegrasikan dengan sistem auto-sync employee_id yang baru diimplementasikan di backend.

## Fitur Baru yang Ditambahkan

### 1. Sync Status Section
**Lokasi:** Setelah Upload Section, hanya untuk Manager

**Fitur:**
- **Status Cards**: Menampilkan statistik sync dalam 4 kartu:
  - Total Absensi
  - Data Ter-sync  
  - Data Belum Sync
  - Persentase Sync (dengan color coding)

- **Progress Bar**: Visual indicator persentase sync dengan warna dinamis:
  - 🟢 Hijau (≥90%): Excellent sync
  - 🟡 Kuning (70-89%): Good sync  
  - 🔴 Merah (50-69%): Low sync
  - ⚫ Abu-abu (<50%): Poor sync

- **Sample Data**: Menampilkan contoh data yang sudah dan belum ter-sync

### 2. Action Buttons
- **Refresh Button**: Memuat ulang status sync
- **Manual Sync Button**: Trigger bulk sync manual untuk semua data

### 3. Auto-Refresh
- Status sync otomatis di-refresh setelah upload TXT berhasil
- Memastikan informasi selalu up-to-date

## Technical Implementation

### New Data Properties
```javascript
data() {
  return {
    // ... existing properties ...
    loading: {
      // ... existing loading states ...
      syncStatus: false,
      manualSync: false,
    },
    syncStatus: {
      loaded: false,
      data: {
        total_attendance: 0,
        synced_attendance: 0,
        unsynced_attendance: 0,
        sync_percentage: 0,
        unsynced_samples: [],
        synced_samples: []
      }
    }
  }
}
```

### New Methods
```javascript
methods: {
  // Fetch sync status from backend
  async fetchSyncStatus() {
    // GET /api/attendance/upload-txt/sync-status
  },

  // Trigger manual bulk sync
  async manualBulkSync() {
    // POST /api/attendance/upload-txt/manual-sync
  },

  // Helper for percentage color coding
  getSyncPercentageClass() {
    // Returns: high-sync, medium-sync, low-sync, no-sync
  }
}
```

### API Integration
**Endpoints Used:**
- `GET /api/attendance/upload-txt/sync-status` - Get sync statistics
- `POST /api/attendance/upload-txt/manual-sync` - Manual bulk sync

### Responsive Design
**Desktop:**
- 4-column grid layout untuk stat cards
- Horizontal action buttons
- Full sample data display

**Tablet (≤768px):**
- Single column stat cards
- Centered action buttons
- Compact sample data

**Mobile (≤480px):**
- 2x2 grid untuk stat cards
- Vertical action buttons
- Minimal sample data display

## UI Components

### Stat Cards
```html
<div class="stat-card [type]">
  <div class="stat-icon">
    <i class="fas fa-[icon]"></i>
  </div>
  <div class="stat-content">
    <div class="stat-number">[number]</div>
    <div class="stat-label">[label]</div>
  </div>
</div>
```

**Types:** `total`, `synced`, `unsynced`, `percentage`

### Progress Bar
```html
<div class="progress-bar">
  <div class="progress-fill [class]" :style="{ width: percentage + '%' }"></div>
</div>
```

**Classes:** `high-sync`, `medium-sync`, `low-sync`, `no-sync`

### Sample Data
```html
<div class="sample-item">
  <div class="sample-info">
    <span class="sample-name">[name]</span>
    <span class="sample-card">Card: [card]</span>
    <span class="sample-date">[date]</span>
  </div>
  <div class="sample-status [status]">
    <i class="fas fa-[icon]"></i>
    [status text]
  </div>
</div>
```

## User Experience Flow

### Manager Access
1. **Load Page**: Auto-fetch sync status on mount
2. **Upload TXT**: Status otomatis di-refresh setelah upload berhasil
3. **Monitor Status**: Real-time visibility persentase sync
4. **Manual Action**: Button untuk trigger bulk sync jika diperlukan

### Employee Access
- Sync status section disembunyikan (hanya untuk manager)
- Upload section tidak tersedia untuk employee
- Focus pada personal attendance data saja

## Color Coding System

### Sync Percentage
- **90-100%**: 🟢 High Sync (Green) - Excellent
- **70-89%**: 🟡 Medium Sync (Orange) - Good  
- **50-69%**: 🟴 Low Sync (Red) - Needs Attention
- **0-49%**: ⚫ No Sync (Gray) - Critical

### Sample Status
- **Synced**: 🟢 Green background, check icon
- **Unsynced**: 🔴 Red background, X icon

## Error Handling
- Loading states untuk semua API calls
- Error popups untuk network issues
- Graceful fallback untuk missing data
- Retry mechanism melalui refresh button

## Performance Considerations
- Lazy loading: Status hanya dimuat saat dibutuhkan
- Efficient re-renders dengan conditional v-if
- Minimal API calls dengan smart refresh strategy
- Sample data limited (5 unsynced, 3 synced) untuk performa

## Usage Instructions

### For Developers
1. Import `smartFetch` dan `getApiBaseUrl` utilities
2. Ensure proper error handling untuk API calls
3. Test responsive design di berbagai screen sizes
4. Verify access control (manager vs employee)

### For Users (Managers)
1. **Monitor Sync**: Lihat persentase sync secara regular
2. **Upload TXT**: Status akan otomatis update setelah upload
3. **Manual Sync**: Gunakan jika persentase rendah atau ada masalah
4. **Review Samples**: Cek data mana yang belum ter-sync

## Future Enhancements
- Real-time updates dengan WebSocket
- Historical sync percentage tracking
- Advanced filtering untuk sample data
- Export sync report functionality
- Automated sync scheduling options 