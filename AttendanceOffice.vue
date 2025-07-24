<template>
  <div class="attendance-dashboard">
    <div class="page-container">
      <div class="controls-section">
        <div class="upload-section">
          <div class="upload-header">
            <h3><i class="fas fa-upload"></i> Upload Data Absensi TXT</h3>
            <p>Upload file TXT absensi dari mesin, backend akan otomatis konversi dan mapping data.</p>
          </div>
          <div class="upload-controls">
            <div class="file-input-wrapper">
              <input 
                type="file" 
                ref="fileInput" 
                @change="handleFileSelect" 
                accept=".txt"
                class="file-input"
                :disabled="loading.upload"
              />
              <label for="fileInput" class="file-input-label">
                <i class="fas fa-file-alt"></i>
                {{ selectedFile ? selectedFile.name : 'Pilih File TXT' }}
              </label>
            </div>
            <div class="upload-buttons">
              <button 
                class="btn btn-success btn-compact" 
                @click="uploadExcelData"
                :disabled="!selectedFile || loading.upload"
                title="Upload dan proses data TXT"
              >
                <i class="fas fa-upload"></i>
                {{ loading.upload ? 'Uploading...' : 'Upload' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <CustomPopup
      :show="popup.show"
      :title="popup.title"
      :message="popup.message"
      :icon="popup.icon"
      :buttons="popup.buttons"
      @close="closePopup"
    />
  </div>
</template>

<script>
import CustomPopup from './CustomPopup.vue'
import { getApiBaseUrl } from '@/utils/apiConfig'
import { smartFetch } from '@/utils/fetchHelper'

export default {
  name: 'AttendanceOffice',
  components: {
    CustomPopup
  },
  data() {
    return {
      loading: {
        upload: false
      },
      selectedFile: null,
      popup: {
        show: false,
        title: '',
        message: '',
        icon: 'ℹ️',
        buttons: []
      }
    }
  },
  
  mounted() {
    // Check role access
    if (!this.hasAccess()) {
      this.showAccessDenied()
      return
    }
    
    this.initializeComponent()
  },
  
  beforeUnmount() {
    // Cleanup intervals
    if (this.timeInterval) clearInterval(this.timeInterval)
  },
  
  methods: {
    // Role checking
    hasAccess() {
      const user = JSON.parse(localStorage.getItem('user') || '{}')
      const userRole = user.role || user.jabatan || ''
      
      // Convert role to lowercase for comparison
      const normalizedUserRole = userRole.toLowerCase().replace(/\s+/g, '_')
      
      // Allow General Affairs, HR, Program Manager, VP President, and Director President
      const allowedRoles = ['general_affairs', 'hr', 'program_manager', 'vp_president', 'president_director']
      
      return allowedRoles.includes(normalizedUserRole)
    },
    
    showAccessDenied() {
      this.popup = {
        show: true,
        title: '❌ Akses Ditolak',
        message: 'Anda tidak memiliki akses ke fitur Absensi Kantor.<br><br>' +
                 '<strong>Hanya untuk:</strong><br>' +
                 '• General Affairs<br>' +
                 '• HR<br>' +
                 '• Program Manager<br>' +
                 '• VP President<br>' +
                 '• Director President<br><br>' +
                 'Hubungi administrator untuk akses.',
        icon: '🚫',
        buttons: [
          {
            text: 'Kembali ke Dashboard',
            class: 'btn-primary',
            action: () => {
              this.$router.push('/')
            }
          }
        ]
      }
    },
    
    // Initialization
    initializeComponent() {
      this.updateTime()
      this.timeInterval = setInterval(this.updateTime, 1000)
    },
    
    // Time management
    updateTime() {
      const now = new Date()
      this.currentTime = now.toLocaleString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      })
    },
    
    handleFileSelect(event) {
      const file = event.target.files[0];
      if (file) {
        const allowedExtensions = ['txt'];
        const fileExt = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(fileExt)) {
          this.showErrorPopup('Format File Tidak Valid', 'Hanya file TXT yang diperbolehkan');
          this.$refs.fileInput.value = '';
          return;
        }
        if (file.size > 10 * 1024 * 1024) {
          this.showErrorPopup('File Terlalu Besar', 'Ukuran file maksimal 10MB');
          this.$refs.fileInput.value = '';
          return;
        }
        this.selectedFile = file;
      }
    },
    async uploadExcelData() {
      if (!this.selectedFile) {
        this.showErrorPopup('File Belum Dipilih', 'Silakan pilih file TXT terlebih dahulu');
        return;
      }
      this.loading.upload = true;
      try {
        const formData = new FormData();
        formData.append('txt_file', this.selectedFile);
        const response = await smartFetch(`${this.apiBaseUrl}/attendance/upload-txt`, {
          method: 'POST',
          body: formData
        });
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          throw new Error('NETWORK_ERROR');
        }
        const result = await response.json();
        if (result.success) {
          this.showSuccessPopup('Upload Berhasil!', result.message);
          this.$refs.fileInput.value = '';
          this.selectedFile = null;
        } else {
          this.showErrorPopup('Upload Gagal', result.message || 'Gagal upload file TXT');
        }
      } catch (error) {
        this.showErrorPopup('Upload Gagal', error.message || 'Gagal upload file TXT');
      } finally {
        this.loading.upload = false;
      }
    },
    showSuccessPopup(title, message) {
      this.showPopup(title, message, '✅')
    },
    showErrorPopup(title, message) {
      this.showPopup(title, message, '❌')
    },
    showPopup(title, message, icon = 'ℹ️', buttons = null) {
      this.popup = {
        show: true,
        title,
        message,
        icon,
        buttons: buttons || [{ text: 'OK', class: 'success', action: 'close' }]
      }
    },
    closePopup() {
      this.popup.show = false
    },
    showNetworkErrorPopup() {
      this.popup = {
        show: true,
        title: '⚠️ Koneksi Jaringan Diperlukan',
        message: 'Sambung ke koneksi kantor yang terhubung dengan mesin absensi.<br><br>Minta bantuan ke pihak IT.',
        icon: '🌐',
        buttons: [
          {
            text: '✖️ Tutup',
            class: 'btn-secondary',
            action: 'close'
          }
        ]
      }
    },

    headerKey(label) {
      // Mapping label header ke key backend
      const map = {
        'No. ID': 'card_number',
        'Nama': 'user_name',
        'Tanggal': 'date',
        'Scan Masuk': 'check_in',
        'Scan Pulang': 'check_out',
        'Absent': 'absent',
        'Jml Jam Kerja': 'work_hours',
        'Jml Kehadiran': 'jml_kehadiran',
      }
      return map[label] || label
    }
  }
}
</script>

<style scoped>
/* CSS Variables */
:root {
  --primary-color: #3b82f6;
  --primary-dark: #1e40af;
  --primary-light: #dbeafe;
  --secondary-color: #64748b;
  --success-color: #10b981;
  --error-color: #ef4444;
  --warning-color: #f59e0b;
  --info-color: #06b6d4;
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --text-muted: #94a3b8;
  --border-color: #e2e8f0;
  --bg-primary: #f8fafc;
  --bg-secondary: #f1f5f9;
  --bg-card: #ffffff;
  --bg-tertiary: #f8fafc;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  --radius: 6px;
  --radius-lg: 12px;
  --spacing-xs: 4px;
  --spacing-sm: 8px;
  --spacing-md: 16px;
  --spacing-lg: 24px;
  --spacing-xl: 32px;
  --spacing-2xl: 48px;
  --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  --font-size-xs: 12px;
  --font-size-sm: 14px;
  --font-size-base: 16px;
  --font-size-lg: 18px;
  --font-size-xl: 20px;
  --font-size-2xl: 24px;
  --font-size-3xl: 30px;
}

.attendance-dashboard {
  min-height: 100vh;
  background-color: var(--bg-secondary);
  font-family: var(--font-family);
  margin: 0;
  padding: 0;
}

.header {
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
  color: white;
  padding: var(--spacing-lg) var(--spacing-xl);
  box-shadow: var(--shadow-lg);
  position: sticky;
  top: 0;
  z-index: 100;
  width: 100%;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1400px;
  margin: 0 auto;
}

.logo-section {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.logo {
  width: 48px;
  height: 48px;
  background-color: rgba(255, 255, 255, 0.2);
  border-radius: var(--radius);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-xl);
  color: white;
  backdrop-filter: blur(10px);
}

.title-section h1 {
  font-size: var(--font-size-2xl);
  font-weight: 700;
  margin-bottom: var(--spacing-xs);
  color: white;
}

.title-section p {
  font-size: var(--font-size-sm);
  opacity: 0.9;
  color: rgba(255, 255, 255, 0.9);
  margin-bottom: 0;
}

.current-time {
  font-size: var(--font-size-lg);
  font-weight: 500;
  color: rgba(255, 255, 255, 0.9);
}

.page-container {
  max-width: none;
  margin: 0;
  padding: var(--spacing-xl);
  width: 100%;
}

.controls-section {
  margin-bottom: var(--spacing-xl);
}

.controls {
  display: flex;
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-md);
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-xs);
  padding: 6px 16px;
  border-radius: var(--radius);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  min-width: 80px;
  justify-content: center;
  height: 32px;
  line-height: 1.2;
}

.btn-primary {
  background: var(--primary-color);
  color: white;
  box-shadow: var(--shadow-sm);
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-dark);
  box-shadow: var(--shadow-md);
}

.btn-success {
  background: linear-gradient(135deg, #28a745, #20c997);
  color: white;
  border: none;
  transition: all 0.3s ease;
}

.btn-success:hover {
  background: linear-gradient(135deg, #218838, #1ea085);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.btn-success:disabled {
  background: #6c757d;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.btn-warning {
  background: var(--warning-color);
  color: white;
  box-shadow: var(--shadow-sm);
}

.btn-warning:hover:not(:disabled) {
  background: #d97706;
  box-shadow: var(--shadow-md);
}

.btn-info {
  background: var(--info-color);
  color: white;
  box-shadow: var(--shadow-sm);
}

.btn-info:hover:not(:disabled) {
  background: #0891b2;
  box-shadow: var(--shadow-md);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* Excel Upload Styles */
.upload-section {
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border: 1px solid #0ea5e9;
  border-radius: var(--radius-lg);
  padding: var(--spacing-xl);
  margin-bottom: var(--spacing-lg);
  box-shadow: 0 4px 12px rgba(14, 165, 233, 0.1);
}

.upload-header {
  margin-bottom: var(--spacing-lg);
  text-align: center;
}

.upload-header h3 {
  color: var(--primary-color);
  margin-bottom: var(--spacing-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-sm);
}

.upload-header p {
  color: var(--text-secondary);
  margin: 0;
  font-size: var(--font-size-sm);
}

.upload-controls {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
  align-items: center;
}

.file-input-wrapper {
  position: relative;
  width: 100%;
  max-width: 400px;
}

.file-input {
  position: absolute;
  opacity: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
}

.file-input-label {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-lg);
  background: white;
  border: 2px dashed var(--primary-color);
  border-radius: var(--radius);
  cursor: pointer;
  transition: all 0.3s ease;
  font-weight: 500;
  color: var(--primary-color);
}

.file-input-label:hover {
  background: var(--primary-light);
  border-color: var(--primary-dark);
}

.file-input-label i {
  font-size: var(--font-size-lg);
}

.upload-buttons {
  display: flex;
  gap: var(--spacing-md);
  flex-wrap: wrap;
  justify-content: center;
}

.template-section {
  display: flex;
  gap: var(--spacing-sm);
  justify-content: center;
  margin-top: var(--spacing-md);
}

.btn-sm {
  padding: var(--spacing-sm) var(--spacing-md);
  font-size: var(--font-size-sm);
}

.upload-info {
  margin-top: var(--spacing-md);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.info-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  font-size: var(--font-size-sm);
  color: var(--text-muted);
}

.info-item i {
  color: var(--primary-color);
}

.upload-note {
  margin-top: var(--spacing-md);
  padding: var(--spacing-md);
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border: 1px solid #f59e0b;
  border-radius: var(--radius);
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-sm);
  font-size: var(--font-size-sm);
  color: #92400e;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
}

.upload-note i {
  color: #f59e0b;
  font-size: var(--font-size-base);
  margin-top: 2px;
}

.upload-note strong {
  color: #92400e;
  font-weight: 600;
}

/* Excel Preview Modal Styles */
.excel-preview-modal {
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
}

.preview-content {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.preview-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--spacing-md);
  padding: var(--spacing-md);
  background: var(--bg-secondary);
  border-radius: var(--radius);
}

.summary-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  font-size: var(--font-size-sm);
}

.summary-item i {
  color: var(--primary-color);
  font-size: var(--font-size-base);
}

.sample-data-section h4 {
  margin-bottom: var(--spacing-md);
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.preview-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--font-size-sm);
  background: white;
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.preview-table th {
  background: var(--primary-color);
  color: white;
  font-weight: 600;
  padding: var(--spacing-sm) var(--spacing-md);
  text-align: left;
  font-size: var(--font-size-xs);
}

.preview-table td {
  padding: var(--spacing-sm) var(--spacing-md);
  border-bottom: 1px solid var(--border-color);
  font-size: var(--font-size-xs);
}

.preview-table tr:hover {
  background: var(--bg-tertiary);
}

.error-row {
  background: #fef2f2 !important;
}

.error-row:hover {
  background: #fee2e2 !important;
}

.error-badge {
  background: #dc2626;
  color: white;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: var(--font-size-xs);
  display: flex;
  align-items: center;
  gap: 4px;
}

.success-badge {
  background: #10b981;
  color: white;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: var(--font-size-xs);
  display: flex;
  align-items: center;
  gap: 4px;
}

.error-details {
  margin-top: var(--spacing-lg);
  padding: var(--spacing-md);
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: var(--radius);
}

.error-details h4 {
  color: #dc2626;
  margin-bottom: var(--spacing-md);
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.error-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-sm);
}

.error-item {
  padding: var(--spacing-sm);
  background: white;
  border-radius: var(--radius-sm);
  font-size: var(--font-size-sm);
  border-left: 3px solid #dc2626;
}

.error-item strong {
  color: #dc2626;
}

.error-state {
  text-align: center;
  padding: var(--spacing-xl);
  color: #dc2626;
}

.error-state i {
  font-size: 3rem;
  margin-bottom: var(--spacing-md);
}

.error-state h4 {
  margin-bottom: var(--spacing-md);
  color: #dc2626;
}

/* Validation Rules Modal Styles */
.validation-rules {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.header-format {
  background: var(--bg-secondary);
  padding: var(--spacing-md);
  border-radius: var(--radius);
  border-left: 4px solid var(--primary-color);
}

.header-format code {
  font-family: 'Courier New', monospace;
  font-size: var(--font-size-sm);
  color: var(--primary-color);
  font-weight: 600;
}

.validation-rules h4 {
  color: var(--text-primary);
  margin-bottom: var(--spacing-sm);
  font-size: var(--font-size-base);
}

.validation-rules ul {
  margin: 0;
  padding-left: var(--spacing-lg);
}

.validation-rules li {
  margin-bottom: var(--spacing-sm);
  font-size: var(--font-size-sm);
  line-height: 1.5;
}

.validation-rules strong {
  color: var(--text-primary);
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
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  color: #333;
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
}

.modal-body {
  padding: 20px;
}

.modal-loading-message {
  margin-bottom: 20px;
  padding: 16px;
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  border: 1px solid #f59e0b;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  color: #92400e;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
  text-align: center;
  justify-content: center;
}

.modal-loading-message i {
  color: #f59e0b;
  font-size: 16px;
}

.modal-loading-message strong {
  color: #92400e;
  font-weight: 600;
}

.modal-footer {
  padding: 20px;
  border-top: 1px solid #eee;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn-secondary {
  background: #6c757d;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-secondary:hover {
  background: #5a6268;
}

/* Responsive Design */
@media (max-width: 768px) {
  .page-container {
    padding: var(--spacing-md);
  }
  
  .header {
    padding: var(--spacing-md);
  }
  
  .header-content {
    flex-direction: column;
    gap: var(--spacing-md);
    text-align: center;
  }
  
  .controls {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
    min-width: auto;
  }
  
  .upload-controls {
    gap: var(--spacing-md);
  }
  
  .upload-buttons {
    flex-direction: column;
    width: 100%;
  }
  
  .template-section {
    flex-direction: column;
    width: 100%;
  }
  
  .preview-summary {
    grid-template-columns: 1fr;
  }
  
  .preview-table {
    font-size: var(--font-size-xs);
  }
  
  .preview-table th,
  .preview-table td {
    padding: var(--spacing-xs) var(--spacing-sm);
  }
}

@media (max-width: 480px) {
  .page-container {
    padding: var(--spacing-sm);
  }
  
  .header {
    padding: var(--spacing-sm);
  }
  
  .logo {
    width: 40px;
    height: 40px;
    font-size: var(--font-size-lg);
  }
  
  .title-section h1 {
    font-size: var(--font-size-xl);
  }
  
  .current-time {
    font-size: var(--font-size-base);
  }
  
  .btn {
    padding: var(--spacing-sm) var(--spacing-lg);
    font-size: var(--font-size-xs);
  }
}
</style>