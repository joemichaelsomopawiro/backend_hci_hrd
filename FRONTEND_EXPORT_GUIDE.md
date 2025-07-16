# 📱 FRONTEND EXPORT GUIDE - Panduan untuk Frontend Developer

## 🎯 Overview

Setelah sync bulanan berhasil, sistem akan otomatis membuat file Excel dan memberikan URL download. Frontend perlu menangani response dengan benar untuk menampilkan link download atau auto-download file.

## 🔄 Response Structure

### Sync Monthly Response
```json
{
  "success": true,
  "message": "FAST Monthly sync berhasil untuk July 2025",
  "data": {
    "month": "July",
    "year": 2025,
    "month_number": 7,
    "export_result": {
      "success": true,
      "filename": "Absensi_July_2025_Hope_Channel_Indonesia.xls",
      "download_url": "http://localhost:8000/storage/exports/Absensi_July_2025_Hope_Channel_Indonesia.xls",
      "direct_download_url": "http://localhost:8000/api/attendance/export/download/Absensi_July_2025_Hope_Channel_Indonesia.xls",
      "total_employees": 42,
      "working_days": 23,
      "month": "July 2025"
    },
    "monthly_stats": {
      "total_from_machine": 822,
      "month_filtered": 822,
      "processed_to_logs": 822,
      "processed_to_attendances": 180,
      "sync_type": "fast_monthly_sync"
    }
  }
}
```

## 💻 Frontend Implementation

### 1. JavaScript/AJAX Example
```javascript
// Sync monthly dengan auto-export
async function syncMonthlyWithExport() {
    try {
        const response = await fetch('/api/attendance/sync-current-month-fast', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (result.success) {
            // Tampilkan pesan sukses
            showSuccessMessage(result.message);

            // Cek apakah export berhasil
            if (result.data.export_result && result.data.export_result.success) {
                const exportData = result.data.export_result;
                
                // Tampilkan info export
                showExportInfo(exportData);
                
                // Auto-download file (opsional)
                autoDownloadFile(exportData.direct_download_url, exportData.filename);
            }

            // Tampilkan statistik sync
            showSyncStats(result.data.monthly_stats);
        } else {
            showErrorMessage(result.message);
        }
    } catch (error) {
        console.error('Sync error:', error);
        showErrorMessage('Terjadi kesalahan saat sync');
    }
}

// Tampilkan info export
function showExportInfo(exportData) {
    const exportInfo = `
        <div class="export-success">
            <h3>✅ Export Excel Berhasil!</h3>
            <p><strong>File:</strong> ${exportData.filename}</p>
            <p><strong>Total Karyawan:</strong> ${exportData.total_employees}</p>
            <p><strong>Hari Kerja:</strong> ${exportData.working_days}</p>
            <p><strong>Bulan:</strong> ${exportData.month}</p>
            
            <div class="download-buttons">
                <a href="${exportData.download_url}" 
                   class="btn btn-primary" 
                   target="_blank">
                    📥 Download Excel
                </a>
                
                <button onclick="downloadFile('${exportData.direct_download_url}', '${exportData.filename}')" 
                        class="btn btn-success">
                    ⬇️ Direct Download
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('export-result').innerHTML = exportInfo;
}

// Auto-download file
function autoDownloadFile(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Manual download dengan progress
function downloadFile(url, filename) {
    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Download failed');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(error => {
            console.error('Download error:', error);
            alert('Gagal download file');
        });
}
```

### 2. Vue.js Example
```vue
<template>
  <div class="sync-monthly">
    <button @click="syncMonthly" :disabled="loading" class="btn btn-primary">
      {{ loading ? '⏳ Syncing...' : '🔄 Sync Bulanan Cepat' }}
    </button>

    <!-- Export Result -->
    <div v-if="exportResult" class="export-result mt-3">
      <div class="alert alert-success">
        <h4>✅ Export Excel Berhasil!</h4>
        <p><strong>File:</strong> {{ exportResult.filename }}</p>
        <p><strong>Total Karyawan:</strong> {{ exportResult.total_employees }}</p>
        <p><strong>Hari Kerja:</strong> {{ exportResult.working_days }}</p>
        <p><strong>Bulan:</strong> {{ exportResult.month }}</p>
        
        <div class="mt-3">
          <a :href="exportResult.download_url" 
             class="btn btn-primary me-2" 
             target="_blank">
            📥 Download Excel
          </a>
          
          <button @click="downloadFile" class="btn btn-success">
            ⬇️ Direct Download
          </button>
        </div>
      </div>
    </div>

    <!-- Sync Stats -->
    <div v-if="syncStats" class="sync-stats mt-3">
      <div class="card">
        <div class="card-header">
          <h5>📊 Statistik Sync</h5>
        </div>
        <div class="card-body">
          <p><strong>Total dari Mesin:</strong> {{ syncStats.total_from_machine }}</p>
          <p><strong>Filtered Bulan Ini:</strong> {{ syncStats.month_filtered }}</p>
          <p><strong>Processed to Logs:</strong> {{ syncStats.processed_to_logs }}</p>
          <p><strong>Processed to Attendances:</strong> {{ syncStats.processed_to_attendances }}</p>
          <p><strong>Sync Type:</strong> {{ syncStats.sync_type }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
      exportResult: null,
      syncStats: null
    }
  },
  methods: {
    async syncMonthly() {
      this.loading = true;
      this.exportResult = null;
      this.syncStats = null;

      try {
        const response = await fetch('/api/attendance/sync-current-month-fast', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        });

        const result = await response.json();

        if (result.success) {
          // Set export result
          if (result.data.export_result && result.data.export_result.success) {
            this.exportResult = result.data.export_result;
          }

          // Set sync stats
          if (result.data.monthly_stats) {
            this.syncStats = result.data.monthly_stats;
          }

          this.$toast.success(result.message);
        } else {
          this.$toast.error(result.message);
        }
      } catch (error) {
        console.error('Sync error:', error);
        this.$toast.error('Terjadi kesalahan saat sync');
      } finally {
        this.loading = false;
      }
    },

    downloadFile() {
      if (!this.exportResult) return;

      fetch(this.exportResult.direct_download_url)
        .then(response => {
          if (!response.ok) throw new Error('Download failed');
          return response.blob();
        })
        .then(blob => {
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = this.exportResult.filename;
          link.click();
          window.URL.revokeObjectURL(url);
          this.$toast.success('File berhasil didownload');
        })
        .catch(error => {
          console.error('Download error:', error);
          this.$toast.error('Gagal download file');
        });
    }
  }
}
</script>
```

### 3. React Example
```jsx
import React, { useState } from 'react';

const SyncMonthly = () => {
  const [loading, setLoading] = useState(false);
  const [exportResult, setExportResult] = useState(null);
  const [syncStats, setSyncStats] = useState(null);

  const syncMonthly = async () => {
    setLoading(true);
    setExportResult(null);
    setSyncStats(null);

    try {
      const response = await fetch('/api/attendance/sync-current-month-fast', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      });

      const result = await response.json();

      if (result.success) {
        if (result.data.export_result?.success) {
          setExportResult(result.data.export_result);
        }
        if (result.data.monthly_stats) {
          setSyncStats(result.data.monthly_stats);
        }
        alert(result.message);
      } else {
        alert(result.message);
      }
    } catch (error) {
      console.error('Sync error:', error);
      alert('Terjadi kesalahan saat sync');
    } finally {
      setLoading(false);
    }
  };

  const downloadFile = async () => {
    if (!exportResult) return;

    try {
      const response = await fetch(exportResult.direct_download_url);
      if (!response.ok) throw new Error('Download failed');
      
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = exportResult.filename;
      link.click();
      window.URL.revokeObjectURL(url);
      alert('File berhasil didownload');
    } catch (error) {
      console.error('Download error:', error);
      alert('Gagal download file');
    }
  };

  return (
    <div className="sync-monthly">
      <button 
        onClick={syncMonthly} 
        disabled={loading}
        className="btn btn-primary"
      >
        {loading ? '⏳ Syncing...' : '🔄 Sync Bulanan Cepat'}
      </button>

      {exportResult && (
        <div className="export-result mt-3">
          <div className="alert alert-success">
            <h4>✅ Export Excel Berhasil!</h4>
            <p><strong>File:</strong> {exportResult.filename}</p>
            <p><strong>Total Karyawan:</strong> {exportResult.total_employees}</p>
            <p><strong>Hari Kerja:</strong> {exportResult.working_days}</p>
            <p><strong>Bulan:</strong> {exportResult.month}</p>
            
            <div className="mt-3">
              <a 
                href={exportResult.download_url}
                className="btn btn-primary me-2"
                target="_blank"
                rel="noopener noreferrer"
              >
                📥 Download Excel
              </a>
              
              <button onClick={downloadFile} className="btn btn-success">
                ⬇️ Direct Download
              </button>
            </div>
          </div>
        </div>
      )}

      {syncStats && (
        <div className="sync-stats mt-3">
          <div className="card">
            <div className="card-header">
              <h5>📊 Statistik Sync</h5>
            </div>
            <div className="card-body">
              <p><strong>Total dari Mesin:</strong> {syncStats.total_from_machine}</p>
              <p><strong>Filtered Bulan Ini:</strong> {syncStats.month_filtered}</p>
              <p><strong>Processed to Logs:</strong> {syncStats.processed_to_logs}</p>
              <p><strong>Processed to Attendances:</strong> {syncStats.processed_to_attendances}</p>
              <p><strong>Sync Type:</strong> {syncStats.sync_type}</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default SyncMonthly;
```

## 🎨 CSS Styling

```css
.export-success {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px;
  border-radius: 10px;
  margin: 20px 0;
}

.export-success h3 {
  margin-top: 0;
  color: white;
}

.download-buttons {
  margin-top: 15px;
}

.download-buttons .btn {
  margin-right: 10px;
  margin-bottom: 10px;
}

.sync-stats .card {
  border: none;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.sync-stats .card-header {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
  border: none;
}

.sync-stats .card-body p {
  margin-bottom: 8px;
  font-size: 14px;
}
```

## 🔧 Error Handling

```javascript
// Handle berbagai jenis error
function handleSyncError(error, response) {
  if (response.status === 404) {
    return 'Mesin absensi tidak ditemukan';
  } else if (response.status === 400) {
    return 'Tidak dapat terhubung ke mesin';
  } else if (response.status === 500) {
    return 'Terjadi kesalahan server';
  } else if (error.name === 'TypeError') {
    return 'Koneksi jaringan bermasalah';
  } else {
    return 'Terjadi kesalahan tidak diketahui';
  }
}

// Retry mechanism
async function syncWithRetry(maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      const result = await syncMonthly();
      return result;
    } catch (error) {
      if (i === maxRetries - 1) throw error;
      await new Promise(resolve => setTimeout(resolve, 2000 * (i + 1)));
    }
  }
}
```

## 📱 Mobile Considerations

```javascript
// Check if mobile device
function isMobile() {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// Mobile-friendly download
function mobileDownload(url, filename) {
  if (isMobile()) {
    // For mobile, open in new tab
    window.open(url, '_blank');
  } else {
    // For desktop, trigger download
    autoDownloadFile(url, filename);
  }
}
```

## 🚀 Best Practices

1. **Loading States**: Selalu tampilkan loading indicator selama sync
2. **Error Handling**: Tangani semua kemungkinan error dengan graceful
3. **User Feedback**: Berikan feedback yang jelas untuk setiap aksi
4. **Progressive Enhancement**: Pastikan fitur tetap berfungsi tanpa JavaScript
5. **Accessibility**: Gunakan proper ARIA labels dan keyboard navigation
6. **Mobile First**: Desain untuk mobile terlebih dahulu
7. **Performance**: Optimasi untuk koneksi lambat

---

**📞 Support**: Jika ada masalah, hubungi backend developer atau cek log Laravel di `storage/logs/laravel.log` 