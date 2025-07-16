# Panduan Auto-Download File Excel di Frontend

## Masalah yang Ditemukan
Frontend berhasil mendapatkan URL download file Excel dari API, tetapi file tidak terdownload secara otomatis. Endpoint download sudah berfungsi dengan baik, masalahnya adalah implementasi frontend yang tidak melakukan auto-download.

## Solusi Implementasi

### 1. JavaScript Vanilla (Pure JS)

```javascript
// Fungsi untuk auto-download file Excel
function autoDownloadExcel(downloadUrl, filename) {
    console.log('Memulai auto-download:', downloadUrl);
    
    // Buat elemen <a> untuk download
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = filename || 'attendance_report.xls';
    link.target = '_blank';
    
    // Tambahkan ke DOM
    document.body.appendChild(link);
    
    // Trigger click
    link.click();
    
    // Bersihkan DOM
    document.body.removeChild(link);
    
    console.log('Auto-download selesai');
}

// Contoh penggunaan dengan response dari sync bulanan cepat
async function handleSyncMonthlyFast() {
    try {
        const response = await fetch('/api/attendance/sync-current-month-fast', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Sync berhasil:', result.message);
            
            // Cek apakah ada URL download
            if (result.data && result.data.download_url) {
                const downloadUrl = result.data.download_url;
                const filename = result.data.filename || 'attendance_report.xls';
                
                // Auto-download file
                autoDownloadExcel(downloadUrl, filename);
                
                // Tampilkan notifikasi
                showNotification('File Excel berhasil didownload!', 'success');
            } else {
                console.warn('Tidak ada URL download dalam response');
            }
        } else {
            console.error('Sync gagal:', result.message);
            showNotification('Sync gagal: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error saat sync:', error);
        showNotification('Terjadi kesalahan saat sync', 'error');
    }
}

// Fungsi helper untuk notifikasi
function showNotification(message, type = 'info') {
    // Implementasi notifikasi sesuai framework yang digunakan
    console.log(`${type.toUpperCase()}: ${message}`);
}
```

### 2. Vue.js Implementation

```vue
<template>
  <div>
    <button @click="syncMonthlyFast" :disabled="isLoading">
      {{ isLoading ? 'Sync & Download...' : 'Sync Bulanan & Download Excel' }}
    </button>
    
    <div v-if="downloadStatus" :class="downloadStatus.type">
      {{ downloadStatus.message }}
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      isLoading: false,
      downloadStatus: null
    }
  },
  
  methods: {
    async syncMonthlyFast() {
      this.isLoading = true;
      this.downloadStatus = null;
      
      try {
        const response = await this.$http.post('/api/attendance/sync-current-month-fast');
        
        if (response.data.success) {
          this.downloadStatus = {
            type: 'success',
            message: response.data.message
          };
          
          // Auto-download jika ada URL
          if (response.data.data && response.data.data.download_url) {
            await this.autoDownloadFile(
              response.data.data.download_url,
              response.data.data.filename
            );
          }
        } else {
          this.downloadStatus = {
            type: 'error',
            message: response.data.message
          };
        }
      } catch (error) {
        console.error('Error:', error);
        this.downloadStatus = {
          type: 'error',
          message: 'Terjadi kesalahan saat sync'
        };
      } finally {
        this.isLoading = false;
      }
    },
    
    async autoDownloadFile(downloadUrl, filename) {
      try {
        console.log('Memulai auto-download:', downloadUrl);
        
        // Method 1: Menggunakan fetch untuk download
        const response = await fetch(downloadUrl);
        const blob = await response.blob();
        
        // Buat URL untuk blob
        const blobUrl = window.URL.createObjectURL(blob);
        
        // Buat link dan trigger download
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename || 'attendance_report.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Bersihkan blob URL
        window.URL.revokeObjectURL(blobUrl);
        
        console.log('Auto-download selesai');
        
        // Update status
        this.downloadStatus = {
          type: 'success',
          message: 'File Excel berhasil didownload!'
        };
        
      } catch (error) {
        console.error('Error saat download:', error);
        
        // Fallback: Buka di tab baru
        window.open(downloadUrl, '_blank');
        
        this.downloadStatus = {
          type: 'warning',
          message: 'File dibuka di tab baru (auto-download gagal)'
        };
      }
    }
  }
}
</script>

<style scoped>
.success {
  color: green;
  background: #e8f5e8;
  padding: 10px;
  border-radius: 4px;
  margin: 10px 0;
}

.error {
  color: red;
  background: #ffe8e8;
  padding: 10px;
  border-radius: 4px;
  margin: 10px 0;
}

.warning {
  color: orange;
  background: #fff8e8;
  padding: 10px;
  border-radius: 4px;
  margin: 10px 0;
}
</style>
```

### 3. React Implementation

```jsx
import React, { useState } from 'react';

const AttendanceSync = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [downloadStatus, setDownloadStatus] = useState(null);

  const syncMonthlyFast = async () => {
    setIsLoading(true);
    setDownloadStatus(null);

    try {
      const response = await fetch('/api/attendance/sync-current-month-fast', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      const result = await response.json();

      if (result.success) {
        setDownloadStatus({
          type: 'success',
          message: result.message
        });

        // Auto-download jika ada URL
        if (result.data && result.data.download_url) {
          await autoDownloadFile(
            result.data.download_url,
            result.data.filename
          );
        }
      } else {
        setDownloadStatus({
          type: 'error',
          message: result.message
        });
      }
    } catch (error) {
      console.error('Error:', error);
      setDownloadStatus({
        type: 'error',
        message: 'Terjadi kesalahan saat sync'
      });
    } finally {
      setIsLoading(false);
    }
  };

  const autoDownloadFile = async (downloadUrl, filename) => {
    try {
      console.log('Memulai auto-download:', downloadUrl);

      // Method 1: Menggunakan fetch untuk download
      const response = await fetch(downloadUrl);
      const blob = await response.blob();

      // Buat URL untuk blob
      const blobUrl = window.URL.createObjectURL(blob);

      // Buat link dan trigger download
      const link = document.createElement('a');
      link.href = blobUrl;
      link.download = filename || 'attendance_report.xls';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      // Bersihkan blob URL
      window.URL.revokeObjectURL(blobUrl);

      console.log('Auto-download selesai');

      // Update status
      setDownloadStatus({
        type: 'success',
        message: 'File Excel berhasil didownload!'
      });

    } catch (error) {
      console.error('Error saat download:', error);

      // Fallback: Buka di tab baru
      window.open(downloadUrl, '_blank');

      setDownloadStatus({
        type: 'warning',
        message: 'File dibuka di tab baru (auto-download gagal)'
      });
    }
  };

  return (
    <div>
      <button 
        onClick={syncMonthlyFast} 
        disabled={isLoading}
        style={{
          padding: '10px 20px',
          backgroundColor: isLoading ? '#ccc' : '#007bff',
          color: 'white',
          border: 'none',
          borderRadius: '4px',
          cursor: isLoading ? 'not-allowed' : 'pointer'
        }}
      >
        {isLoading ? 'Sync & Download...' : 'Sync Bulanan & Download Excel'}
      </button>

      {downloadStatus && (
        <div
          style={{
            marginTop: '10px',
            padding: '10px',
            borderRadius: '4px',
            backgroundColor: 
              downloadStatus.type === 'success' ? '#e8f5e8' :
              downloadStatus.type === 'error' ? '#ffe8e8' : '#fff8e8',
            color: 
              downloadStatus.type === 'success' ? 'green' :
              downloadStatus.type === 'error' ? 'red' : 'orange'
          }}
        >
          {downloadStatus.message}
        </div>
      )}
    </div>
  );
};

export default AttendanceSync;
```

### 4. Axios Implementation

```javascript
// Konfigurasi Axios
import axios from 'axios';

// Set base URL
axios.defaults.baseURL = 'http://127.0.0.1:8000/api';

// Interceptor untuk menambahkan token
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Fungsi sync dengan auto-download
async function syncMonthlyFastWithDownload() {
  try {
    console.log('Memulai sync bulanan cepat...');
    
    const response = await axios.post('/attendance/sync-current-month-fast');
    
    if (response.data.success) {
      console.log('Sync berhasil:', response.data.message);
      
      // Auto-download file
      if (response.data.data && response.data.data.download_url) {
        await downloadFile(
          response.data.data.download_url,
          response.data.data.filename
        );
      }
      
      return {
        success: true,
        message: response.data.message
      };
    } else {
      throw new Error(response.data.message);
    }
  } catch (error) {
    console.error('Error saat sync:', error);
    throw error;
  }
}

// Fungsi download file
async function downloadFile(downloadUrl, filename) {
  try {
    console.log('Memulai download file:', downloadUrl);
    
    // Download file menggunakan axios
    const response = await axios.get(downloadUrl, {
      responseType: 'blob'
    });
    
    // Buat blob URL
    const blob = new Blob([response.data], {
      type: 'application/vnd.ms-excel'
    });
    
    const blobUrl = window.URL.createObjectURL(blob);
    
    // Buat link dan trigger download
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = filename || 'attendance_report.xls';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Bersihkan blob URL
    window.URL.revokeObjectURL(blobUrl);
    
    console.log('Download selesai');
    
  } catch (error) {
    console.error('Error saat download:', error);
    
    // Fallback: Buka di tab baru
    window.open(downloadUrl, '_blank');
    throw new Error('Auto-download gagal, file dibuka di tab baru');
  }
}

// Contoh penggunaan
document.getElementById('syncButton').addEventListener('click', async () => {
  try {
    const result = await syncMonthlyFastWithDownload();
    alert('Berhasil: ' + result.message);
  } catch (error) {
    alert('Error: ' + error.message);
  }
});
```

## Troubleshooting

### 1. File Tidak Terdownload
- **Penyebab**: Browser memblokir popup atau auto-download
- **Solusi**: 
  - Pastikan popup blocker dinonaktifkan
  - Gunakan fallback dengan `window.open(url, '_blank')`
  - Tambahkan user interaction (click) sebelum download

### 2. CORS Error
- **Penyebab**: Cross-origin request diblokir
- **Solusi**:
  - Pastikan server mengirim header CORS yang benar
  - Gunakan proxy atau backend untuk download

### 3. File Corrupt
- **Penyebab**: Response tidak dikenali sebagai file Excel
- **Solusi**:
  - Pastikan Content-Type header benar (`application/vnd.ms-excel`)
  - Cek apakah file berisi HTML yang valid

### 4. Download Lambat
- **Penyebab**: File besar atau koneksi lambat
- **Solusi**:
  - Tambahkan loading indicator
  - Implementasi progress bar
  - Gunakan streaming download untuk file besar

## Best Practices

1. **Selalu gunakan try-catch** untuk menangani error
2. **Implementasi fallback** jika auto-download gagal
3. **Beri feedback visual** kepada user (loading, success, error)
4. **Log semua aktivitas** untuk debugging
5. **Test di berbagai browser** untuk kompatibilitas
6. **Gunakan blob URL** untuk download yang lebih reliable
7. **Bersihkan blob URL** setelah download selesai

## Testing

Gunakan script test yang sudah dibuat:
```bash
php test_download_file.php
```

Script ini akan menguji:
- Keberadaan file di storage
- Endpoint download
- Download file lengkap
- Storage link
- URL langsung ke file 