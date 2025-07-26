<template>
  <div class="attendance-office">
    <!-- Header Section -->
    <div class="dashboard-header">
      <div class="header-content">
        <div class="welcome-section">
          <h1>Attendance Office</h1>
          <p>Manajemen Data Absensi - Hope Channel Indonesia</p>
        </div>
        <div class="current-time">
          <div class="time-display">
            <i class="fas fa-clock"></i>
            {{ currentTime.toLocaleTimeString('id-ID') }}
          </div>
          <div class="date-display">
            {{ currentTime.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Section - Only for Managers -->
    <div v-if="!isEmployee" class="upload-section">
      <div class="dashboard-card upload-card">
        <div class="card-header">
          <div class="header-icon">
            <i class="fas fa-upload"></i>
          </div>
          <div class="header-text">
            <h3>Upload Data Absensi TXT</h3>
            <p>Upload file TXT absensi dari mesin, backend akan otomatis konversi dan mapping data.</p>
          </div>
        </div>
        <div class="card-content">
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
                <div class="file-icon">
                  <i class="fas fa-file-alt"></i>
                </div>
                <div class="file-text">
                  <span class="file-name">{{ selectedFile ? selectedFile.name : 'Pilih File TXT' }}</span>
                  <span v-if="!selectedFile" class="file-hint">Drag & drop atau klik untuk memilih file</span>
                  <span v-else class="file-hint">File siap untuk diupload</span>
                </div>
              </label>
            </div>
            
            <button 
              class="btn btn-upload" 
              @click="uploadExcelData"
              :disabled="!selectedFile || loading.upload"
              title="Upload dan proses data TXT"
            >
              <i class="fas fa-upload"></i>
              {{ loading.upload ? 'Uploading...' : 'Upload File' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Sync Status Section - Only for Managers -->
    <div v-if="!isEmployee" class="sync-status-section">
      <div class="dashboard-card sync-card">
        <div class="card-header">
          <div class="header-icon">
            <i class="fas fa-sync-alt"></i>
          </div>
          <div class="header-text">
            <h3>Status Sinkronisasi Employee</h3>
            <p>Status sinkronisasi employee_id di data absensi</p>
          </div>
          <div class="sync-actions">
            <button 
              class="btn btn-refresh" 
              @click="fetchSyncStatus"
              :disabled="loading.syncStatus"
              title="Refresh status sync"
            >
              <i class="fas fa-refresh"></i>
              {{ loading.syncStatus ? 'Loading...' : 'Refresh' }}
            </button>
            <button 
              class="btn btn-sync" 
              @click="manualBulkSync"
              :disabled="loading.manualSync"
              title="Manual bulk sync semua data"
            >
              <i class="fas fa-cogs"></i>
              {{ loading.manualSync ? 'Syncing...' : 'Manual Sync' }}
            </button>
          </div>
        </div>
        <div class="card-content">
          <div v-if="loading.syncStatus" class="loading-state">
            <div class="loading-spinner"></div>
            <span>Memuat status sync...</span>
          </div>
          
          <div v-else-if="syncStatus.loaded" class="sync-info">
            <div class="sync-stats">
              <div class="stat-card total">
                <div class="stat-icon">
                  <i class="fas fa-database"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-number">{{ syncStatus.data.total_attendance || 0 }}</div>
                  <div class="stat-label">Total Absensi</div>
                </div>
              </div>
              
              <div class="stat-card synced">
                <div class="stat-icon">
                  <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-number">{{ syncStatus.data.synced_attendance || 0 }}</div>
                  <div class="stat-label">Ter-sync</div>
                </div>
              </div>
              
              <div class="stat-card unsynced">
                <div class="stat-icon">
                  <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-number">{{ syncStatus.data.unsynced_attendance || 0 }}</div>
                  <div class="stat-label">Belum Sync</div>
                </div>
              </div>
              
              <div class="stat-card percentage" :class="getSyncPercentageClass()">
                <div class="stat-icon">
                  <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-content">
                  <div class="stat-number">{{ syncStatus.data.sync_percentage || 0 }}%</div>
                  <div class="stat-label">Persentase Sync</div>
                </div>
              </div>
            </div>
            
            <div class="sync-progress">
              <div class="progress-bar">
                <div 
                  class="progress-fill" 
                  :style="{ width: (syncStatus.data.sync_percentage || 0) + '%' }"
                  :class="getSyncPercentageClass()"
                ></div>
              </div>
              <div class="progress-label">
                {{ syncStatus.data.synced_attendance || 0 }} dari {{ syncStatus.data.total_attendance || 0 }} data ter-sync ({{ syncStatus.data.sync_percentage || 0 }}%)
              </div>
            </div>
            
            <!-- Sample Data -->
            <div v-if="syncStatus.data.unsynced_samples && syncStatus.data.unsynced_samples.length > 0" class="sync-samples">
              <h4>Contoh Data Belum Sync:</h4>
              <div class="sample-list unsynced">
                <div v-for="sample in syncStatus.data.unsynced_samples.slice(0, 5)" :key="`unsynced-${sample.user_name}-${sample.date}`" class="sample-item">
                  <div class="sample-info">
                    <span class="sample-name">{{ sample.user_name }}</span>
                    <span class="sample-card">Card: {{ sample.card_number }}</span>
                    <span class="sample-date">{{ sample.date }}</span>
                  </div>
                  <div class="sample-status unsynced">
                    <i class="fas fa-times"></i>
                    Belum Sync
                  </div>
                </div>
              </div>
            </div>
            
            <div v-if="syncStatus.data.synced_samples && syncStatus.data.synced_samples.length > 0" class="sync-samples">
              <h4>Contoh Data Sudah Sync:</h4>
              <div class="sample-list synced">
                <div v-for="sample in syncStatus.data.synced_samples.slice(0, 3)" :key="`synced-${sample.user_name}-${sample.date}`" class="sample-item">
                  <div class="sample-info">
                    <span class="sample-name">{{ sample.user_name }}</span>
                    <span class="sample-card">Card: {{ sample.card_number }}</span>
                    <span class="sample-date">{{ sample.date }}</span>
                  </div>
                  <div class="sample-status synced">
                    <i class="fas fa-check"></i>
                    ID: {{ sample.employee_id }}
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div v-else class="sync-placeholder">
            <div class="placeholder-icon">
              <i class="fas fa-sync-alt"></i>
            </div>
            <h4>Status Sinkronisasi</h4>
            <p>Klik "Refresh" untuk melihat status sinkronisasi employee_id</p>
            <button class="btn btn-primary" @click="fetchSyncStatus">
              <i class="fas fa-refresh"></i>
              Lihat Status
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Employee View Mode Toggle -->
    <div v-if="isEmployee" class="view-mode-section">
      <div class="dashboard-card">
        <div class="card-header">
          <div class="header-icon">
            <i class="fas fa-user-clock"></i>
          </div>
          <div class="header-text">
            <h3>Absensi Pribadi</h3>
            <p>Lihat data absensi Anda</p>
          </div>
          <div class="view-toggle">
            <button 
              @click="toggleViewMode" 
              class="btn-toggle"
              :class="{ active: viewMode === 'table' }"
            >
              <i class="fas fa-table"></i>
              Tabel
            </button>
            <button 
              @click="toggleViewMode" 
              class="btn-toggle"
              :class="{ active: viewMode === 'list' }"
            >
              <i class="fas fa-list"></i>
              List
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Monthly Table Section -->
    <div class="table-section">
      <div class="dashboard-card table-card">
        <div class="card-header">
          <div class="header-icon">
            <i class="fas fa-table"></i>
          </div>
          <div class="header-text">
            <h3 v-if="!isEmployee">Tabel Absensi Bulanan</h3>
            <h3 v-else>{{ viewMode === 'table' ? 'Tabel Absensi' : 'List Absensi' }}</h3>
            <p v-if="!isEmployee">Data absensi karyawan per bulan</p>
            <p v-else>Data absensi pribadi Anda</p>
          </div>
        </div>
        <div class="card-content">
          <div class="filter-section">
            <div class="date-picker-group">
              <div class="date-picker-item">
                <label for="exportMonth">
                  <i class="fas fa-calendar-alt"></i>
                  Bulan
                </label>
                <select 
                  id="exportMonth" 
                  v-model="exportDate.month" 
                  class="date-picker-select"
                  :disabled="monthlyTable.loading"
                  @change="onMonthYearChange"
                >
                  <option value="01">Januari</option>
                  <option value="02">Februari</option>
                  <option value="03">Maret</option>
                  <option value="04">April</option>
                  <option value="05">Mei</option>
                  <option value="06">Juni</option>
                  <option value="07">Juli</option>
                  <option value="08">Agustus</option>
                  <option value="09">September</option>
                  <option value="10">Oktober</option>
                  <option value="11">November</option>
                  <option value="12">Desember</option>
                </select>
              </div>
              <div class="date-picker-item">
                <label for="exportYear">
                  <i class="fas fa-calendar"></i>
                  Tahun
                </label>
                <select 
                  id="exportYear" 
                  v-model="exportDate.year" 
                  class="date-picker-select"
                  :disabled="monthlyTable.loading"
                  @change="onMonthYearChange"
                >
                  <option v-for="year in availableYears" :key="year" :value="year">
                    {{ year }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div v-if="isEmployee ? personalAttendance.loading : monthlyTable.loading" class="loading-state">
            <div class="loading-spinner"></div>
            <span>Memuat data...</span>
          </div>
          
          <div v-else class="table-container">
            <transition name="fade-slide">
              <div v-if="isEmployee ? personalAttendance.data.length > 0 : monthlyTable.data.length > 0">
                <div class="table-info">
                  <div class="info-badge">
                    <i class="fas fa-calendar-check"></i>
                    <span>{{ isEmployee ? personalAttendance.month : monthlyTable.month }} {{ isEmployee ? personalAttendance.year : monthlyTable.year }}</span>
                  </div>
                  <div class="info-stats">
                    <span v-if="!isEmployee" class="stat-item">
                      <i class="fas fa-users"></i>
                      {{ monthlyTable.data.length }} Karyawan
                    </span>
                    <span v-else class="stat-item">
                      <i class="fas fa-user"></i>
                      Absensi Pribadi
                    </span>
                    <span class="stat-item">
                      <i class="fas fa-calendar-day"></i>
                      {{ isEmployee ? personalAttendance.workingDays.length : monthlyTable.workingDays.length }} Hari Kerja
                    </span>
                    <span class="stat-item">
                      <i class="fas fa-calendar-alt"></i>
                      {{ isEmployee ? personalAttendance.month : monthlyTable.month }} {{ isEmployee ? personalAttendance.year : monthlyTable.year }}
                    </span>
                  </div>
                </div>
                
                <!-- Table View -->
                <div v-if="!isEmployee || viewMode === 'table'" class="table-wrapper">
                  <table class="monthly-table">
                    <thead>
                      <tr>
                        <th class="th-number">No</th>
                        <th class="th-name">Nama</th>
                        <th class="th-card">Card Number</th>
                        <th v-for="day in (isEmployee ? personalAttendance.workingDays : monthlyTable.workingDays)" :key="day.day" class="th-day">
                          <div class="day-header">
                            <div class="day-number">{{ day.day }}</div>
                            <div class="day-name">{{ day.day_name }}</div>
                          </div>
                        </th>
                        <th class="th-summary">Total Jam</th>
                        <th class="th-summary">Total Absen</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(row, idx) in (isEmployee ? personalAttendance.data : monthlyTable.data)" :key="row.nama" class="table-row">
                        <td class="td-number">{{ idx + 1 }}</td>
                        <td class="td-name">{{ row.nama }}</td>
                        <td class="td-card">{{ row.card_number }}</td>
                        <td v-for="day in (isEmployee ? personalAttendance.workingDays : monthlyTable.workingDays)" :key="day.day" class="td-day">
                          <div v-if="row.daily_data[String(day.day)]"
                               :class="{
                                 'status-absent': row.daily_data[String(day.day)].status === 'absent' || row.daily_data[String(day.day)].status === 'no_data',
                                 'status-present': row.daily_data[String(day.day)].status === 'present_ontime' || row.daily_data[String(day.day)].status === 'present_late',
                                 'status-leave': row.daily_data[String(day.day)].status === 'cuti',
                                 'status-late': row.daily_data[String(day.day)].status === 'present_late'
                               }"
                               class="status-cell">
                            <template v-if="row.daily_data[String(day.day)].status === 'cuti'">
                              <div class="status-content">
                                CUTI
                              </div>
                            </template>
                            <template v-else-if="row.daily_data[String(day.day)].status === 'present_ontime' || row.daily_data[String(day.day)].status === 'present_late'">
                              <div class="status-content">
                                <span class="time-in">{{ row.daily_data[String(day.day)].check_in }}</span>
                                <span class="time-out">{{ row.daily_data[String(day.day)].check_out }}</span>
                              </div>
                            </template>
                            <template v-else>
                              -
                            </template>
                          </div>
                          <div v-else class="status-cell status-absent">
                            -
                          </div>
                        </td>
                        <td class="td-summary td-hours">
                          <div class="hours-cell">
                            <i class="fas fa-check"></i>
                            <span>{{ Math.round(row.total_jam_kerja) }}h</span>
                          </div>
                        </td>
                        <td class="td-summary td-absent">{{ row.total_absen }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- List View for Employees -->
                <div v-else-if="isEmployee && viewMode === 'list'" class="list-view">
                  <div v-for="(row, idx) in personalAttendance.data" :key="row.nama" class="employee-card">
                    <div class="employee-header">
                      <div class="employee-info">
                        <h4>{{ row.nama }}</h4>
                        <span class="card-number">Card: {{ row.card_number }}</span>
                      </div>
                      <div class="employee-summary">
                        <div class="hours-cell">
                          <i class="fas fa-check"></i>
                          <span>{{ Math.round(row.total_jam_kerja) }}h</span>
                        </div>
                        <span class="absent-count">{{ row.total_absen }} Absen</span>
                      </div>
                    </div>
                    <div class="attendance-list">
                      <div v-for="day in personalAttendance.workingDays" :key="day.day" class="attendance-item">
                        <div class="date-info">
                          <span class="day-number">{{ day.day }}</span>
                          <span class="day-name">{{ day.day_name }}</span>
                        </div>
                        <div v-if="row.daily_data[String(day.day)]" class="status-info">
                          <div v-if="row.daily_data[String(day.day)].status === 'cuti'" class="status-badge status-leave">
                            <i class="fas fa-calendar-times"></i>
                            CUTI
                          </div>
                          <div v-else-if="row.daily_data[String(day.day)].status === 'present_ontime' || row.daily_data[String(day.day)].status === 'present_late'" 
                               class="status-badge" 
                               :class="row.daily_data[String(day.day)].status === 'present_late' ? 'status-late' : 'status-present'">
                            <i class="fas fa-clock"></i>
                            <div class="time-details">
                              <span>{{ row.daily_data[String(day.day)].check_in }}</span>
                              <span>{{ row.daily_data[String(day.day)].check_out }}</span>
                            </div>
                          </div>
                          <div v-else class="status-badge status-absent">
                            <i class="fas fa-times"></i>
                            TIDAK HADIR
                          </div>
                        </div>
                        <div v-else class="status-info">
                          <div class="status-badge status-absent">
                            <i class="fas fa-times"></i>
                            TIDAK HADIR
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="empty-state">
                <div class="empty-icon">
                  <i class="fas fa-calendar-times"></i>
                </div>
                <h3>Tidak Ada Data</h3>
                <p>Tidak ada data absensi untuk bulan ini.</p>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </div>

    <!-- Custom Popup -->
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
          upload: false,
          exportToday: false,
          exportMonthly: false,
          syncStatus: false,
          manualSync: false,
        },
      selectedFile: null,
      popup: {
        show: false,
        title: '',
        message: '',
        icon: 'ℹ️',
        buttons: []
      },
      apiBaseUrl: `${getApiBaseUrl()}/api`,
      exportDate: {
        month: String(new Date().getMonth() + 1).padStart(2, '0'),
        year: new Date().getFullYear()
      },
      availableYears: [],
      currentTime: new Date(),
      monthlyTable: {
        loading: false,
        data: [],
        workingDays: [],
        month: '',
        year: ''
      },
      isEmployee: false,
      viewMode: 'table', // 'table' or 'list'
      personalAttendance: {
        loading: false,
        data: [],
        workingDays: [],
        month: '',
        year: ''
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
  },
  
  async mounted() {
    this.checkUserRole()
    this.loadAvailableYears()
    this.startTimeUpdate()
    
    if (this.isEmployee) {
      await this.fetchPersonalAttendance()
    } else if (this.hasManagerAccess()) {
      await this.fetchMonthlyTable()
      await this.fetchSyncStatus() // Fetch sync status for managers
    } else {
      this.showAccessDenied()
    }
  },
  
  beforeUnmount() {
    // Clear interval when component is destroyed
    if (this.timeInterval) {
      clearInterval(this.timeInterval)
    }
  },
  
  methods: {
    // Role checking
    checkUserRole() {
      const user = JSON.parse(localStorage.getItem('user') || '{}')
      const userRole = user.role || user.jabatan || ''
      
      // Convert role to lowercase for comparison
      const normalizedUserRole = userRole.toLowerCase().replace(/\s+/g, '_')
      
      // Manager roles that can see all employees
      const managerRoles = ['general_affairs', 'hr', 'program_manager', 'distribution_manager', 'vp_president', 'president_director']
      
      this.isEmployee = !managerRoles.includes(normalizedUserRole)
    },

    hasManagerAccess() {
      const user = JSON.parse(localStorage.getItem('user') || '{}')
      const userRole = user.role || user.jabatan || ''
      
      // Convert role to lowercase for comparison
      const normalizedUserRole = userRole.toLowerCase().replace(/\s+/g, '_')
      
      // Allow General Affairs, HR, Program Manager, Distribution Manager, VP President, and Director President
      const allowedRoles = ['general_affairs', 'hr', 'program_manager', 'distribution_manager', 'vp_president', 'president_director']
      
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
          // After successful upload, refresh sync status for managers
          if (!this.isEmployee) {
            await this.fetchSyncStatus();
          }
        } else {
          this.showErrorPopup('Upload Gagal', result.message || 'Gagal upload file TXT');
        }
      } catch (error) {
        this.showErrorPopup('Upload Gagal', error.message || 'Gagal upload file TXT');
      } finally {
        this.loading.upload = false;
      }
    },
    async fetchMonthlyTable() {
      this.monthlyTable.loading = true
      try {
        const params = new URLSearchParams({
          month: parseInt(this.exportDate.month).toString(),
          year: this.exportDate.year
        })
        const response = await smartFetch(`${this.apiBaseUrl}/attendance/monthly-table?${params}`)
        const result = await response.json()
        if (result.success) {
          this.monthlyTable.data = result.data.records
          this.monthlyTable.workingDays = this.addDayNames(result.data.working_days, this.exportDate.month, this.exportDate.year)
          this.monthlyTable.month = result.data.month
          this.monthlyTable.year = result.data.year
        } else {
          this.monthlyTable.data = []
          this.monthlyTable.workingDays = []
        }
      } catch (e) {
        this.monthlyTable.data = []
        this.monthlyTable.workingDays = []
      } finally {
        this.monthlyTable.loading = false
      }
    },
    async onMonthYearChange() {
      if (this.isEmployee) {
        await this.fetchPersonalAttendance()
      } else {
        await this.fetchMonthlyTable()
      }
    },

    async fetchPersonalAttendance() {
      this.personalAttendance.loading = true
      try {
        const user = JSON.parse(localStorage.getItem('user') || '{}')
        const params = new URLSearchParams({
          month: parseInt(this.exportDate.month).toString(),
          year: this.exportDate.year,
          employee_id: user.employee_id || user.id
        })
        const response = await smartFetch(`${this.apiBaseUrl}/attendance/personal-monthly?${params}`)
        const result = await response.json()
        if (result.success) {
          this.personalAttendance.data = [result.data.record]
          this.personalAttendance.workingDays = this.addDayNames(result.data.working_days, this.exportDate.month, this.exportDate.year)
          this.personalAttendance.month = result.data.month
          this.personalAttendance.year = result.data.year
        } else {
          this.personalAttendance.data = []
          this.personalAttendance.workingDays = []
        }
      } catch (e) {
        this.personalAttendance.data = []
        this.personalAttendance.workingDays = []
      } finally {
        this.personalAttendance.loading = false
      }
    },

    toggleViewMode() {
      this.viewMode = this.viewMode === 'table' ? 'list' : 'table'
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
    },
    async loadAvailableYears() {
      // Generate tahun dari 2020 sampai 5 tahun ke depan
      const currentYear = new Date().getFullYear();
      this.availableYears = [];
      for (let year = 2020; year <= currentYear + 5; year++) {
        this.availableYears.push(year);
      }
    },
    
    startTimeUpdate() {
      // Update time every second
      this.timeInterval = setInterval(() => {
        this.currentTime = new Date();
      }, 1000);
    },
    
    addDayNames(workingDays, month, year) {
      const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
      
      return workingDays.map(day => {
        const date = new Date(year, month - 1, day.day);
        const dayName = dayNames[date.getDay()];
        
        return {
          ...day,
          day_name: dayName
        };
      });
    },

    async fetchSyncStatus() {
      this.loading.syncStatus = true;
      try {
                 const response = await smartFetch(`${this.apiBaseUrl}/attendance/upload-txt/sync-status`);
        const result = await response.json();
        if (result.success) {
          this.syncStatus.data = result.data;
          this.syncStatus.loaded = true;
        } else {
          this.showErrorPopup('Gagal Memuat Status Sync', result.message || 'Gagal mengambil status sinkronisasi');
          this.syncStatus.loaded = true; // Ensure loaded is true even on error
        }
      } catch (error) {
        this.showErrorPopup('Gagal Memuat Status Sync', error.message || 'Gagal mengambil status sinkronisasi');
        this.syncStatus.loaded = true;
      } finally {
        this.loading.syncStatus = false;
      }
    },

    async manualBulkSync() {
      this.loading.manualSync = true;
      try {
                 const response = await smartFetch(`${this.apiBaseUrl}/attendance/upload-txt/manual-sync`, {
           method: 'POST'
         });
        const result = await response.json();
        if (result.success) {
          this.showSuccessPopup('Bulk Sync Berhasil!', result.message || 'Data absensi berhasil di-sync secara bulk.');
          await this.fetchSyncStatus(); // Refresh status after bulk sync
        } else {
          this.showErrorPopup('Bulk Sync Gagal', result.message || 'Gagal melakukan bulk sync data absensi.');
        }
      } catch (error) {
        this.showErrorPopup('Bulk Sync Gagal', error.message || 'Gagal melakukan bulk sync data absensi.');
      } finally {
        this.loading.manualSync = false;
      }
    },

    getSyncPercentageClass() {
      const percentage = this.syncStatus.data.sync_percentage || 0;
      if (percentage >= 90) return 'high-sync';
      if (percentage >= 70) return 'medium-sync';
      if (percentage >= 50) return 'low-sync';
      return 'no-sync';
    }
  }
}
</script>

<style scoped>
/* Attendance Office Styles - Simple Design */
.attendance-office {
  padding: 24px;
  background: #f8fafc;
  min-height: 100vh;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header Section */
.dashboard-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  padding: 20px 0;
}

.welcome-section h1 {
  font-size: 28px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 4px 0;
}

.welcome-section p {
  font-size: 16px;
  color: #64748b;
  margin: 0;
}

.current-time {
  text-align: right;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.time-display {
  font-size: 16px;
  color: #1e293b;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.date-display {
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
}

/* Layout Sections */
.upload-section, .table-section {
  margin-bottom: 24px;
}

.sync-status-section {
  margin-bottom: 24px;
}

/* Dashboard Cards */
.dashboard-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
}

.card-header {
  padding: 20px 20px 0 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-icon i {
  font-size: 20px;
  color: #3b82f6;
}

.header-text h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.header-text p {
  font-size: 14px;
  color: #64748b;
  margin: 8px 0 0 0;
  line-height: 1.5;
}

.card-content {
  padding: 20px;
}

/* Upload Controls */
.upload-controls {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.file-input-wrapper {
  position: relative;
  width: 100%;
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
  gap: 12px;
  padding: 16px;
  background: #f8fafc;
  border: 2px dashed #3b82f6;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  color: #3b82f6;
  min-height: 60px;
  transition: all 0.3s ease;
}

.file-input-label:hover {
  background: #e0f2fe;
  border-color: #1d4ed8;
  transform: translateY(-1px);
}

.file-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-icon i {
  font-size: 18px;
}

.file-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  text-align: center;
}

.file-name {
  font-size: 14px;
  font-weight: 500;
  color: #1e293b;
}

.file-hint {
  font-size: 12px;
  color: #64748b;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  justify-content: center;
  min-width: 120px;
  transition: all 0.2s ease;
}

.btn-upload {
  background: #10b981;
  color: white;
}

.btn-upload:hover:not(:disabled) {
  background: #059669;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-refresh {
  background: #4f46e5;
  color: white;
}

.btn-refresh:hover:not(:disabled) {
  background: #4338ca;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(69, 79, 192, 0.3);
}

.btn-sync {
  background: #f59e0b;
  color: white;
}

.btn-sync:hover:not(:disabled) {
  background: #d97706;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}


.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* Filter Section */
.filter-section {
  background: #f8fafc;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  border: 1px solid #e2e8f0;
}

.date-picker-group {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.date-picker-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  min-width: 120px;
}

.date-picker-item label {
  font-size: 14px;
  font-weight: 500;
  color: #000000;
  display: flex;
  align-items: center;
  gap: 6px;
}

.date-picker-item label i {
  color: #000000;
}

.date-picker-select {
  padding: 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  color: #000000;
}

.date-picker-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}



/* Table Container */
.table-container {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

.table-info {
  background: #f8fafc;
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  border-bottom: 1px solid #e2e8f0;
}

.info-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #3b82f6;
  color: white;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
}

.info-badge i {
  font-size: 16px;
}

.info-stats {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}

.stat-item i {
  font-size: 14px;
}

/* Table Wrapper */
.table-wrapper {
  overflow-y: auto;
  max-height: 500px;
}

.monthly-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
  background: white;
  table-layout: fixed;
}

/* Column width distribution */
.th-number, .td-number {
  width: 4%;
}

.th-name, .td-name {
  width: 20%;
}

.th-card, .td-card {
  width: 12%;
}

.th-day, .td-day {
  width: 2.5%;
}

.th-summary, .td-summary {
  width: 6%;
}

.monthly-table th {
  background: #1e3a8a;
  color: white;
  font-weight: 600;
  padding: 8px 4px;
  text-align: center;
  border: none;
  border-right: 1px solid #1e40af;
  position: sticky;
  top: 0;
  z-index: 10;
  font-size: 10px;
}

.monthly-table th:last-child {
  border-right: none;
}

.day-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.day-number {
  font-size: 11px;
  font-weight: 600;
}

.day-name {
  font-size: 8px;
  font-weight: 400;
  opacity: 0.9;
}

.monthly-table td {
  padding: 6px 3px;
  text-align: center;
  border: none;
  border-right: 1px solid #e5e7eb;
  border-bottom: 1px solid #e5e7eb;
  background: white;
  font-size: 10px;
}

.monthly-table td:last-child {
  border-right: none;
}

.monthly-table tbody tr:first-child td {
  border-top: 1px solid #1e40af;
}



/* Table Cell Styles */
.td-number, .td-name, .td-pin, .td-card {
  font-weight: 500;
  color: #1e293b;
}

.td-name {
  text-align: left;
  font-weight: 600;
  white-space: normal;
  word-wrap: break-word;
  line-height: 1.2;
  padding: 6px 4px;
}

.td-day {
  width: auto;
  padding: 4px 2px;
}

.status-cell {
  padding: 2px;
  border-radius: 3px;
  font-size: 9px;
  font-weight: 500;
}

.status-present {
  background: #22c55e;
  color: white;
}

.status-late {
  background: #22c55e;
  color: white;
}

.status-absent {
  color: #6b7280;
}

.status-leave {
  background: #f59e0b;
  color: white;
}

.status-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  font-size: 8px;
}

.time-in, .time-out {
  font-size: 8px;
  line-height: 1.1;
}

.td-summary {
  font-weight: 600;
  font-size: 10px;
}

.hours-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  background: #22c55e;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 9px;
  font-weight: 600;
}

.hours-cell i {
  font-size: 8px;
}

/* View Toggle Styles */
.view-toggle {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.btn-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 2px solid #e5e7eb;
  background: white;
  color: #6b7280;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-toggle:hover {
  border-color: #1e3a8a;
  color: #1e3a8a;
}

.btn-toggle.active {
  border-color: #1e3a8a;
  background: #1e3a8a;
  color: white;
}

.btn-toggle i {
  font-size: 11px;
}

/* List View Styles */
.list-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.employee-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.employee-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f3f4f6;
}

.employee-info h4 {
  margin: 0 0 4px 0;
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.employee-info .card-number {
  font-size: 12px;
  color: #6b7280;
}

.employee-summary {
  display: flex;
  align-items: center;
  gap: 12px;
}

.absent-count {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.attendance-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.attendance-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #f9fafb;
  border-radius: 6px;
  border: 1px solid #f3f4f6;
}

.date-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 40px;
}

.date-info .day-number {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.date-info .day-name {
  font-size: 10px;
  color: #6b7280;
}

.status-info {
  flex: 1;
  display: flex;
  justify-content: flex-end;
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 500;
  color: white;
}

.status-badge.status-present {
  background: #22c55e;
}

.status-badge.status-late {
  background: #22c55e;
}

.status-badge.status-leave {
  background: #f59e0b;
}

.status-badge.status-absent {
  background: #ef4444;
}

.time-details {
  display: flex;
  flex-direction: column;
  font-size: 9px;
  line-height: 1.2;
}

.td-present {
  color: #059669;
}

.td-hours {
  color: #0891b2;
}

.td-absent {
  color: #dc2626;
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 40px 20px;
  color: #6b7280;
}

.loading-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid #e5e7eb;
  border-top: 2px solid #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 12px;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #6b7280;
}

.empty-icon {
  width: 60px;
  height: 60px;
  background: #f3f4f6;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.empty-icon i {
  font-size: 24px;
  color: #9ca3af;
}

.empty-state h3 {
  font-size: 16px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 4px 0;
}

.empty-state p {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

/* Animations */
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.3s ease;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(20px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

/* Responsive Design */
@media (max-width: 1200px) {
  .info-stats {
    gap: 16px;
  }
  
  .monthly-table {
    font-size: 12px;
  }
  
  .monthly-table th, .monthly-table td {
    padding: 10px 6px;
  }
}

@media (max-width: 768px) {
  .attendance-office {
    padding: 16px;
  }
  
  .header-content {
    flex-direction: column;
    text-align: center;
    padding: 20px;
  }
  
  .welcome-section h1 {
    font-size: 26px;
  }
  
  .current-time {
    text-align: center;
  }
  
  .card-header {
    padding: 20px;
    flex-direction: column;
    text-align: center;
    gap: 16px;
  }

  .sync-actions {
    margin-left: 0;
    margin-top: 12px;
    justify-content: center;
    flex-wrap: wrap;
  }
  
  .card-content {
    padding: 20px;
  }
  
  .file-input-label {
    flex-direction: column;
    text-align: center;
    gap: 16px;
    min-height: 120px;
  }
  
  .date-picker-group {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  
  .table-info {
    flex-direction: column;
    gap: 12px;
    text-align: center;
  }
  
  .info-stats {
    justify-content: center;
    gap: 20px;
  }
  
  .monthly-table {
    font-size: 9px;
  }
  
  .monthly-table th, .monthly-table td {
    padding: 4px 2px;
    font-size: 8px;
  }
  
  .day-number {
    font-size: 9px;
  }
  
  .day-name {
    font-size: 6px;
  }
  
  .status-content {
    font-size: 7px;
  }
  
  .time-in, .time-out {
    font-size: 7px;
  }
  
  .td-summary {
    font-size: 8px;
  }

  /* View Toggle Mobile */
  .view-toggle {
    margin-left: 0;
    margin-top: 12px;
    justify-content: center;
  }

  .btn-toggle {
    padding: 6px 12px;
    font-size: 11px;
  }

  /* List View Mobile */
  .attendance-list {
    grid-template-columns: 1fr;
  }

  .employee-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .employee-summary {
    align-self: flex-end;
  }

  .attendance-item {
    padding: 6px 8px;
  }

  .status-badge {
    padding: 3px 6px;
    font-size: 9px;
  }

  .time-details {
    font-size: 8px;
  }

  /* Sync Status Mobile */

  .sync-stats {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .stat-card {
    padding: 12px;
  }

  .stat-number {
    font-size: 20px;
  }

  .stat-label {
    font-size: 13px;
  }

  .sample-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 10px;
  }

  .sample-status {
    align-self: flex-end;
  }
}

@media (max-width: 480px) {
  .attendance-office {
    padding: 12px;
  }
  
  .header-content {
    padding: 16px;
  }
  
  .welcome-section h1 {
    font-size: 22px;
  }
  
  .welcome-section p {
    font-size: 14px;
  }
  
  .time-display {
    font-size: 16px;
  }
  
  .date-display {
    font-size: 12px;
  }
  
  .card-header {
    padding: 16px;
  }
  
  .header-text h3 {
    font-size: 18px;
  }
  
  .header-text p {
    font-size: 13px;
  }
  
  .card-content {
    padding: 16px;
  }
  
  .file-input-label {
    min-height: 100px;
    padding: 16px;
  }
  
  .btn {
    padding: 14px 24px;
    font-size: 14px;
  }
  
  .filter-section {
    padding: 16px;
  }
  
  .table-info {
    padding: 16px;
  }
  
  .info-badge {
    padding: 8px 16px;
  }
  
  .info-badge span {
    font-size: 14px;
  }
  
  .stat-item {
    font-size: 12px;
  }
  
  .monthly-table {
    font-size: 10px;
  }
  
  .monthly-table th, .monthly-table td {
    padding: 6px 3px;
  }
  
  .td-name {
    max-width: 80px;
  }
  
  .status-cell {
    padding: 6px;
    margin: 1px;
  }
  
  .status-content {
    font-size: 9px;
    gap: 2px;
  }
  
  .status-content i {
    font-size: 10px;
  }
  
  .time-in, .time-out {
    font-size: 8px;
  }

  /* Sync Status Mobile Small */
  .sync-actions {
    flex-direction: column;
    gap: 8px;
  }

  .sync-stats {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }

  .stat-card {
    padding: 10px;
    flex-direction: column;
    text-align: center;
    gap: 8px;
  }

  .stat-icon {
    width: 32px;
    height: 32px;
  }

  .stat-number {
    font-size: 18px;
  }

  .stat-label {
    font-size: 12px;
  }

  .progress-bar {
    height: 8px;
  }

  .progress-label {
    font-size: 12px;
  }

  .sync-samples h4 {
    font-size: 14px;
  }

  .sample-item {
    padding: 8px;
  }

  .sample-name {
    font-size: 13px;
  }

  .sample-card, .sample-date {
    font-size: 11px;
  }

  .sample-status {
    font-size: 11px;
    padding: 3px 6px;
  }

  .sync-placeholder {
    padding: 30px 16px;
  }

  .placeholder-icon {
    width: 50px;
    height: 50px;
    margin-bottom: 12px;
  }

  .placeholder-icon i {
    font-size: 20px;
  }

  .sync-placeholder h4 {
    font-size: 15px;
  }
}

/* Scroll customization */
.table-wrapper::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.table-wrapper::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.table-wrapper::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
  border-radius: 4px;
}

.table-wrapper::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #64748b 0%, #475569 100%);
}

/* Sync Status Styles */
.sync-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.sync-info {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sync-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.stat-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.stat-card.total {
  border-left: 4px solid #3b82f6;
}

.stat-card.synced {
  border-left: 4px solid #10b981;
}

.stat-card.unsynced {
  border-left: 4px solid #ef4444;
}

.stat-card.percentage.high-sync {
  border-left: 4px solid #10b981;
  background: #ecfdf5;
}

.stat-card.percentage.medium-sync {
  border-left: 4px solid #f59e0b;
  background: #fffbeb;
}

.stat-card.percentage.low-sync {
  border-left: 4px solid #ef4444;
  background: #fef2f2;
}

.stat-card.percentage.no-sync {
  border-left: 4px solid #6b7280;
  background: #f9fafb;
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stat-card.total .stat-icon {
  color: #3b82f6;
}

.stat-card.synced .stat-icon {
  color: #10b981;
}

.stat-card.unsynced .stat-icon {
  color: #ef4444;
}

.stat-card.percentage .stat-icon {
  color: #8b5cf6;
}

.stat-content {
  flex: 1;
}

.stat-number {
  font-size: 24px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
}

.sync-progress {
  background: #f8fafc;
  border-radius: 8px;
  padding: 16px;
  border: 1px solid #e2e8f0;
}

.progress-bar {
  width: 100%;
  height: 12px;
  background: #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-fill {
  height: 100%;
  transition: width 0.5s ease;
  border-radius: 6px;
}

.progress-fill.high-sync {
  background: linear-gradient(90deg, #10b981 0%, #059669 100%);
}

.progress-fill.medium-sync {
  background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
}

.progress-fill.low-sync {
  background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
}

.progress-fill.no-sync {
  background: linear-gradient(90deg, #6b7280 0%, #4b5563 100%);
}

.progress-label {
  font-size: 14px;
  color: #64748b;
  text-align: center;
  font-weight: 500;
}

.sync-samples {
  margin-top: 16px;
}

.sync-samples h4 {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 12px;
}

.sample-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sample-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
}

.sample-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sample-name {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.sample-card, .sample-date {
  font-size: 12px;
  color: #64748b;
}

.sample-status {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}

.sample-status.synced {
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #d1fae5;
}

.sample-status.unsynced {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.sync-placeholder {
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
}

.placeholder-icon {
  width: 60px;
  height: 60px;
  background: #f3f4f6;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.placeholder-icon i {
  font-size: 24px;
  color: #9ca3af;
}

.sync-placeholder h4 {
  font-size: 16px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 8px 0;
}

.sync-placeholder p {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 16px 0;
}
</style>