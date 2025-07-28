<template>
    <div class="ga-dashboard">
      <div class="dashboard-header">
        <h1 class="page-title">{{ isGeneralAffairs ? 'Manajemen Data General Affairs' : 'Data Worship & Cuti' }}</h1>
        <p v-if="!isGeneralAffairs">Data absensi renungan dan data cuti</p>
      </div>
  
      <div class="tab-navigation">
        <button 
          class="tab-button" 
          :class="{ active: activeTab === 'worship' }"
          @click="activeTab = 'worship'"
        >
          <i class="fas fa-pray"></i>
          Absensi Ibadah
        </button>
                <button 
            class="tab-button" 
            :class="{ active: activeTab === 'leave' }"
            @click="activeTab = 'leave'; loadLeaveData()"
          >
            <i class="fas fa-calendar-alt"></i>
            Data Cuti
          </button>
      </div>
  
      <div v-if="activeTab === 'worship'" class="tab-content">
  
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon present">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-content">
            <h3>{{ stats.present }}</h3>
            <p>{{ selectedWorshipPeriod === 'today' ? 'Hadir Hari Ini' : 'Hadir' }}</p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon late">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-content">
            <h3>{{ stats.late }}</h3>
            <p>Terlambat</p>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon leave">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <div class="stat-content">
            <h3>{{ stats.leave }}</h3>
            <p>Cuti</p>
          </div>
        </div>
      </div>
  
      <div class="filter-section">
        <div class="filter-row">
          <div class="filter-group">
            <label>Periode:</label>
            <select v-model="selectedWorshipPeriod" class="filter-select">
              <option value="today">Hari Ini</option>
              <option value="week">Minggu Ini</option>
              <option value="month">Bulan Ini</option>
              <option value="all">Semua</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label>Status:</label>
            <select v-model="statusFilter" class="filter-select">
              <option value="">Status</option>
              <option value="present">Hadir</option>
              <option value="late">Terlambat</option>
              <option value="leave">Cuti</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label>Cari:</label>
            <input type="text" v-model="searchQuery" @input="filterData" placeholder="Nama pegawai...">
          </div>
          
          <div class="filter-group">
            <button @click="loadData" class="btn-refresh" :disabled="loading">
              <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
              {{ loading ? 'Memuat...' : 'Refresh' }}
            </button>
          </div>
        </div>
      </div>
  
      <div class="table-container">
        <div v-if="loading" class="loading-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p v-if="selectedWorshipPeriod === 'today'">Memuat data absensi ibadah hari ini...</p>
          <p v-else-if="selectedWorshipPeriod === 'week'">Memuat data absensi ibadah minggu ini...</p>
          <p v-else-if="selectedWorshipPeriod === 'month'">Memuat data absensi ibadah bulan ini...</p>
          <p v-else>Memuat data absensi ibadah...</p>
        </div>
        
        <div v-else-if="error" class="error-state">
          <i class="fas fa-exclamation-triangle"></i>
          <p>{{ error }}</p>
          <button @click="loadData" class="btn-retry">Coba Lagi</button>
        </div>
        
        <div v-else-if="filteredData.length === 0" class="no-data">
          <i class="fas fa-inbox"></i>
          <p v-if="selectedWorshipPeriod === 'today'">
            {{ isOnlineDay ? 'Belum ada karyawan yang melakukan absensi ibadah online hari ini' : 'Belum ada data absensi untuk hari ini' }}
          </p>
          <p v-else-if="selectedWorshipPeriod === 'week'">Belum ada data absensi ibadah minggu ini</p>
          <p v-else-if="selectedWorshipPeriod === 'month'">Belum ada data absensi ibadah bulan ini</p>
          <p v-else>Belum ada data absensi ibadah</p>
          <small v-if="selectedWorshipPeriod === 'today' && isOnlineDay">Data hanya ditampilkan untuk karyawan yang sudah absen online</small>
          <small v-else-if="selectedWorshipPeriod === 'today' && isManualDay">Data hanya ditampilkan setelah GA menginput absensi manual</small>
        </div>
        
        <table v-else class="attendance-table">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Jabatan</th>
              <th>Tanggal</th>
              <th>Waktu</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(record, index) in filteredData" :key="record.id">
              <td>{{ (currentPage - 1) * itemsPerPage + index + 1 }}</td>
              <td>{{ record.name }}</td>
              <td>{{ record.position }}</td>
              <td>{{ formatDate(record.date) }}</td>
              <td>
                {{
                  (record.attendance_method === 'manual' || record.attendance_source === 'manual_input' || record.data_source === 'manual')
                    ? '-' 
                    : (record.attendance_time || '-')
                }}
              </td>
              <td>
                <span class="status-badge" :class="getStatusClass(record.status)">
                  {{ getStatusText(record.status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div v-if="totalPages > 1" class="pagination-container">
          <div class="pagination-info">
            Menampilkan {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, totalItems) }} dari {{ totalItems }} data
          </div>
          <div class="pagination-controls">
            <button 
              @click="goToPage(1)" 
              :disabled="currentPage === 1"
              class="btn-pagination"
            >
              <i class="fas fa-angle-double-left"></i>
            </button>
            <button 
              @click="goToPage(currentPage - 1)" 
              :disabled="currentPage === 1"
              class="btn-pagination"
            >
              <i class="fas fa-angle-left"></i>
            </button>
            
            <span class="page-numbers">
              <button 
                v-for="page in visiblePages" 
                :key="page"
                @click="goToPage(page)"
                :class="['btn-page', { active: page === currentPage }]"
              >
                {{ page }}
              </button>
            </span>
            
            <button 
              @click="goToPage(currentPage + 1)" 
              :disabled="currentPage === totalPages"
              class="btn-pagination"
            >
              <i class="fas fa-angle-right"></i>
            </button>
            <button 
              @click="goToPage(totalPages)" 
              :disabled="currentPage === totalPages"
              class="btn-pagination"
            >
              <i class="fas fa-angle-double-right"></i>
            </button>
          </div>
        </div>
      </div>
  
        <!-- NEW: Conditional rendering for manual attendance form -->
        <div v-if="isGeneralAffairs && isManualDay && selectedWorshipPeriod === 'today'" class="manual-attendance-section" style="margin-top: 40px;">
          <h2 style="margin-bottom: 8px;">Input Manual Absensi Renungan</h2>
          <div class="manual-attendance-filter" style="margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
            <label for="manualAttendanceDate" style="font-weight: 500;">Tanggal:</label>
            <input type="date" id="manualAttendanceDate" v-model="manualAttendanceDate" class="input-date-manual" />
            <!-- NEW: Refresh button for employees -->
            <button @click="fetchPegawaiForManualAttendance" style="background: #27ae60; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">
              <i class="fas fa-sync-alt"></i> Refresh Data Pegawai
            </button>
    </div>
  
          <!-- NEW: Debug info -->
          <div v-if="daftarPegawai.length === 0" style="margin-bottom: 12px; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Debug Info:</strong> Data pegawai belum dimuat. Total pegawai: {{ daftarPegawai.length }}
            <br>
            <small>Hari ini: {{ currentDayName }} ({{ isManualDay ? 'Manual Day' : 'Not Manual Day' }})</small>
  
          </div>
          
          <table class="manual-attendance-table" style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
            <thead>
              <tr>
                <th style="padding: 8px 4px; border-bottom: 1px solid #eee; text-align: left;">No</th>
                <th style="padding: 8px 4px; border-bottom: 1px solid #eee; text-align: left;">Nama Pegawai</th>
                <th style="padding: 8px 4px; border-bottom: 1px solid #eee; text-align: left;">Jabatan</th>
                <th style="padding: 8px 4px; border-bottom: 1px solid #eee; text-align: left;">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(pegawai, idx) in filteredPegawaiForManualInput" :key="pegawai.id">
                <td style="padding: 8px 4px;">{{ idx + 1 }}</td>
                <td style="padding: 8px 4px;">{{ pegawai.nama_lengkap || pegawai.name || '-' }}</td>
                <td style="padding: 8px 4px;">{{ pegawai.jabatan_saat_ini || pegawai.position || '-' }}</td>
                <td style="padding: 8px 4px;">
                  <select v-model="manualAttendanceStatus[pegawai.id]" class="dropdown-status-beauty">
                    <option value="" disabled>Status</option>
                    <option value="present">Hadir</option>
                    <option value="late">Terlambat</option>
                    <option value="absent">Absen</option>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
          <!-- Pesan untuk pegawai yang cuti -->
          <div v-if="filteredPegawaiForManualInput.length < daftarPegawai.length" style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">
            <i class="fas fa-info-circle"></i>
            <strong>Info:</strong> Beberapa pegawai tidak ditampilkan karena sedang cuti pada tanggal ini. Data cuti akan otomatis muncul di tabel absensi.
          </div>
          
          <div v-if="filteredPegawaiForManualInput.length > 0" style="margin-top: 18px; text-align: left;">
                        <div class="manual-attendance-actions">
                <button 
                  @click="submitManualAttendance" 
                  class="btn-submit-manual"
                  :disabled="!hasManualData || isSubmitting"
                >
                  <i class="fas fa-save"></i>
                  {{ isSubmitting ? 'Menyimpan...' : 'Simpan Absensi Manual' }}
                </button>
              </div>
          </div>
        </div>
  
      </div>
  
      <div v-if="activeTab === 'leave'" class="tab-content">
          <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon pending">
              <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
              <h3>{{ dynamicLeaveStats.pending }}</h3>
              <p>Menunggu Approval</p>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon approved">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
              <h3>{{ dynamicLeaveStats.approved }}</h3>
              <p>Disetujui</p>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon rejected">
              <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
              <h3>{{ dynamicLeaveStats.rejected }}</h3>
              <p>Ditolak</p>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon total">
              <i class="fas fa-list"></i>
            </div>
            <div class="stat-content">
              <h3>{{ dynamicLeaveStats.total }}</h3>
              <p>Total Permohonan</p>
            </div>
          </div>
        </div>
  
        <div class="filter-section">
          <div class="filter-row">
            <div class="filter-group">
              <label>Periode:</label>
              <select v-model="selectedLeavePeriod" class="filter-select">
                <option value="today">Hari Ini</option>
                <option value="week">Minggu Ini</option>
                <option value="month">Bulan Ini</option>
                <option value="all">Semua</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label>Status:</label>
              <select v-model="selectedLeaveStatus" class="filter-select">
                <option value="all">Semua</option>
                <option value="pending">Menunggu Approval</option>
                <option value="approved">Disetujui</option>
                <option value="rejected">Ditolak</option>
              </select>
            </div>
            
            <div class="filter-group">
              <label>Cari:</label>
              <input type="text" v-model="leaveSearchQuery" @input="resetLeavePagination" placeholder="Nama karyawan...">
            </div>
            
            <div class="filter-group">
              <button @click="loadLeaveData" class="btn-refresh" :disabled="leaveLoading">
                <i class="fas fa-sync-alt" :class="{ 'fa-spin': leaveLoading }"></i>
                {{ leaveLoading ? 'Memuat...' : 'Refresh' }}
              </button>
            </div>
          </div>
        </div>
  
        <div class="table-container">
          <div v-if="leaveLoading" class="loading-state">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Memuat data permohonan cuti...</p>
          </div>
          
          <div v-else-if="leaveError" class="error-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>{{ leaveError }}</p>
            <button @click="loadLeaveData" class="btn-retry">Coba Lagi</button>
          </div>
          
          <div v-else-if="filteredLeaveData.length === 0" class="no-data">
            <i class="fas fa-inbox"></i>
            <p v-if="selectedLeavePeriod === 'today'">Tidak ada permohonan cuti yang aktif hari ini</p>
            <p v-else-if="selectedLeavePeriod === 'week'">Tidak ada permohonan cuti minggu ini</p>
            <p v-else-if="selectedLeavePeriod === 'month'">Tidak ada permohonan cuti bulan ini</p>
            <p v-else>Belum ada data permohonan cuti</p>
            <small v-if="selectedLeavePeriod === 'today'">Cuti yang ditampilkan adalah cuti yang sedang berlangsung pada hari ini</small>
          </div>
          
          <table v-else class="attendance-table">
                        <thead>
                <tr>
                  <th>No</th>
                  <th>Nama Karyawan</th>
                  <th>Jenis Cuti</th>
                  <th>Tanggal Mulai</th>
                  <th>Tanggal Selesai</th>
                  <th>Durasi</th>
                  <th>Status</th>
                  <th>Disetujui Oleh</th>
                  <th>Alasan</th>
                </tr>
              </thead>
            <tbody>
              <tr v-for="(request, index) in filteredLeaveData" :key="request.id">
                <td class="number-cell">{{ (leaveCurrentPage - 1) * leaveItemsPerPage + index + 1 }}</td>
                <td class="employee-name-cell">{{ getEmployeeName(request) }}</td>
                <td class="leave-type-cell">{{ getLeaveTypeLabel(request.leave_type) }}</td>
                <td class="date-cell">{{ formatDate(request.start_date) }}</td>
                <td class="date-cell">{{ formatDate(request.end_date) }}</td>
                <td class="duration-cell">{{ getLeaveDuration(request) }} hari</td>
                <td class="status-cell">
                  <span class="status-badge" :class="getLeaveStatusClass(request.overall_status || request.status)">
                    {{ getLeaveStatusText(request.overall_status || request.status) }}
                  </span>
                </td>
                <td class="approver-cell">{{ getApproverName(request) }}</td>
                <td class="reason-cell">{{ request.reason || '-' }}</td>
              </tr>
            </tbody>
          </table>
          
          <div v-if="leaveTotalPages > 1" class="pagination-section">
                        <div class="pagination-info">
                Menampilkan {{ ((leaveCurrentPage - 1) * leaveItemsPerPage) + 1 }} - 
                {{ Math.min(leaveCurrentPage * leaveItemsPerPage, leaveTotalItems) }} 
                dari {{ leaveTotalItems }} data cuti
              </div>
            <div class="pagination-controls">
              <button 
                @click="goToLeavePage(1)" 
                :disabled="leaveCurrentPage === 1"
                class="btn-pagination"
              >
                <i class="fas fa-angle-double-left"></i>
              </button>
              <button 
                @click="goToLeavePage(leaveCurrentPage - 1)" 
                :disabled="leaveCurrentPage === 1"
                class="btn-pagination"
              >
                <i class="fas fa-angle-left"></i>
              </button>
              
              <span class="page-numbers">
                <button 
                  v-for="page in leaveVisiblePages" 
                  :key="page"
                  @click="goToLeavePage(page)"
                  :class="['btn-page', { active: page === leaveCurrentPage }]"
                >
                  {{ page }}
                </button>
              </span>
              
              <button 
                @click="goToLeavePage(leaveCurrentPage + 1)" 
                :disabled="leaveCurrentPage === leaveTotalPages"
                class="btn-pagination"
              >
                <i class="fas fa-angle-right"></i>
              </button>
              <button 
                @click="goToLeavePage(leaveTotalPages)" 
                :disabled="leaveCurrentPage === leaveTotalPages"
                class="btn-pagination"
              >
                <i class="fas fa-angle-double-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import { useGaStore } from '../stores/gaStore'
  import { apiFetch } from '@/utils/fetchHelper'
  
  
  export default {
    name: 'GaDashboard',
    components: {
    },
    data() {
      return {
        gaStore: useGaStore(),
        activeTab: 'worship', // Default tab
        
        // Worship tab data
        searchQuery: '',
        statusFilter: '',
        attendanceData: [],
        loading: false,
        error: null,
        stats: {
          present: 0,
          late: 0,
          leave: 0
        },
        
        // Leave tab data
        leaveData: [],
        leaveLoading: false,
        leaveError: null,
        selectedLeaveDate: '',
        selectedLeaveStatus: '',
        selectedLeaveType: '',
        leaveSearchQuery: '',
        leaveStats: {
          pending: 0,
          approved: 0,
          rejected: 0,
          expired: 0,
          total: 0
        },
        
        // Debug state management
        showDebugPanel: import.meta.env.DEV,
        debugMode: false,
        debugDay: '',
        debugHour: '',
        debugMinute: '',
        currentUserId: null,
        
        // Pagination for all data view
        allDataLoading: false,
        currentPage: 1,
        itemsPerPage: 20,
        totalItems: 0,
        
        // Pagination for leave data
        leaveCurrentPage: 1,
        leaveItemsPerPage: 20,
        leaveTotalItems: 0,
        
        // Manual attendance data
        manualAttendanceDate: '',
        daftarPegawai: [],
        manualAttendanceStatus: {},
        isSubmitting: false,
  
  
        
        // Period filters
        selectedLeavePeriod: 'today', // Default: hari ini ke depan
        selectedWorshipPeriod: 'today', // Default: hari ini
        selectedLeaveStatus: 'all', // Default: semua status
        
        // Error handling and retry
        retryCount: 0,
        maxRetries: 3,
        retryDelay: 1000,
        autoRefreshInterval: null
      }
    },
    computed: {
      // Helper untuk mendapatkan tanggal hari ini dalam format YYYY-MM-DD
      todayFormatted() {
        return this.getTodayString();
      },
      // Filter worship attendance data based on selectedWorshipPeriod
      filteredData() {
        // Gabungkan absensi + cuti berdasarkan periode yang dipilih
          const mappedLeaveData = this.getLeaveDataForWorshipDays().map(item => ({
            ...item,
            name: item.employee_name || item.employee?.nama_lengkap || item.employee?.name || '-',
            position: item.employee?.position || item.employee?.jabatan_saat_ini || '-',
            attendance_time: '-',
            status: 'leave',
          }));
        
        let baseData = [...this.attendanceData, ...mappedLeaveData];
        let filtered = baseData;
        
        // TIDAK PERLU FILTER LAGI - Backend sudah mengirim data sesuai periode
        // Data attendanceData sudah difilter di backend berdasarkan selectedWorshipPeriod
        // Hanya perlu filter data cuti berdasarkan periode untuk konsistensi
        
        console.log(`🔍 [DEBUG] Base data count: attendance=${this.attendanceData.length}, leave=${mappedLeaveData.length}, total=${baseData.length}`);
        
        // Filter untuk menampilkan present, late, dan leave - HILANGKAN absent
        filtered = filtered.filter(item => {
          const status = item.status?.toLowerCase();
          return status === 'present' || status === 'hadir' || 
                 status === 'late' || status === 'terlambat' || 
                 status === 'leave' || status === 'cuti';
        });
        
        // Filter berdasarkan pencarian nama
        if (this.searchQuery.trim()) {
          const query = this.searchQuery.toLowerCase();
          filtered = filtered.filter(item => {
            const name = (item.name || item.employee?.nama_lengkap || item.employee_name || '').toLowerCase();
            return name.includes(query);
          });
        }
        
        // Sort berdasarkan tanggal (terbaru di atas)
        filtered.sort((a, b) => {
          const dateA = new Date(a.date);
          const dateB = new Date(b.date);
          return dateB - dateA; // Descending order (terbaru di atas)
        });
        
        // Update totalItems untuk pagination
        this.totalItems = filtered.length;
        
        // Apply pagination
        const startIndex = (this.currentPage - 1) * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        
        return filtered.slice(startIndex, endIndex);
      },
      
      // Pagination computed properties
      totalPages() {
        return Math.ceil(this.totalItems / this.itemsPerPage);
      },
      
      visiblePages() {
        const pages = [];
        const maxVisible = 5;
        let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let end = Math.min(this.totalPages, start + maxVisible - 1);
        
        // Adjust start if we're near the end
        if (end - start + 1 < maxVisible) {
          start = Math.max(1, end - maxVisible + 1);
        }
        
        for (let i = start; i <= end; i++) {
          pages.push(i);
        }
        
        return pages;
      },
      
      debugTimeDisplay() {
        if (this.debugHour !== '' && this.debugMinute !== '') {
          return `${this.debugHour.toString().padStart(2, '0')}:${this.debugMinute.toString().padStart(2, '0')}`
        }
        return 'Jam Real'
      },
      
      debugGaStatus() {
        if (!this.debugMode) return 'Menggunakan waktu real'
        
        const currentDay = this.debugDay || this.getCurrentDay()
        const currentTime = this.debugTimeDisplay
        
        if (currentDay === 'Senin' || currentDay === 'Selasa' || currentDay === 'Rabu' || 
            currentDay === 'Kamis' || currentDay === 'Jumat') {
          if (currentTime === '07:00' || currentTime === '07:15' || currentTime === '07:30') {
            return 'Waktu Renungan (Debug)'
          } else {
            return 'Di Luar Waktu (Debug)'
          }
        } else {
          return 'Bukan Hari Kerja (Debug)'
        }
      },
      
  
      
      // Filter leave data based on selectedLeavePeriod
      filteredLeaveData() {
        let filtered = [...this.leaveData];
        
        // Filter berdasarkan periode yang dipilih
        const today = this.getTodayString();
        
        if (this.selectedLeavePeriod === 'today') {
          // Hanya cuti yang aktif hari ini (hari ini berada dalam rentang cuti)
          filtered = filtered.filter(item => {
            const startDate = new Date(item.start_date).toISOString().split('T')[0];
            const endDate = new Date(item.end_date).toISOString().split('T')[0];
            return today >= startDate && today <= endDate;
          });
        } else if (this.selectedLeavePeriod === 'week') {
          const startOfWeek = new Date();
          startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1); // Senin
          const endOfWeek = new Date(startOfWeek);
          endOfWeek.setDate(endOfWeek.getDate() + 6); // Minggu
          const startWeekStr = startOfWeek.toISOString().split('T')[0];
          const endWeekStr = endOfWeek.toISOString().split('T')[0];
          
          console.log(`🔍 [DEBUG] Week filter - Start: ${startWeekStr}, End: ${endWeekStr}`);
          
          filtered = filtered.filter(item => {
            const startDate = new Date(item.start_date).toISOString().split('T')[0];
            const endDate = new Date(item.end_date).toISOString().split('T')[0];
            
            // Cuti yang overlap dengan minggu ini (start atau end date dalam range minggu)
            const isOverlapping = (startDate <= endWeekStr && endDate >= startWeekStr);
            
            console.log(`🔍 [DEBUG] Leave: ${item.employee?.nama_lengkap} | ${startDate} - ${endDate} | Overlapping: ${isOverlapping}`);
            
            return isOverlapping;
          });
        } else if (this.selectedLeavePeriod === 'month') {
          const startOfMonth = new Date();
          startOfMonth.setDate(1);
          const endOfMonth = new Date(startOfMonth);
          endOfMonth.setMonth(endOfMonth.getMonth() + 1);
          endOfMonth.setDate(0); // Last day of current month
          const startMonthStr = startOfMonth.toISOString().split('T')[0];
          const endMonthStr = endOfMonth.toISOString().split('T')[0];
          
          console.log(`🔍 [DEBUG] Month filter - Start: ${startMonthStr}, End: ${endMonthStr}`);
          
          filtered = filtered.filter(item => {
            const startDate = new Date(item.start_date).toISOString().split('T')[0];
            const endDate = new Date(item.end_date).toISOString().split('T')[0];
            
            // Cuti yang overlap dengan bulan ini (start atau end date dalam range bulan)
            const isOverlapping = (startDate <= endMonthStr && endDate >= startMonthStr);
            
            console.log(`🔍 [DEBUG] Leave: ${item.employee?.nama_lengkap} | ${startDate} - ${endDate} | Overlapping: ${isOverlapping}`);
            
            return isOverlapping;
          });
        }
        // 'all' tidak perlu filter tanggal
        
        // Apply status filter
        if (this.selectedLeaveStatus !== 'all') {
          console.log(`🔍 [DEBUG] Applying status filter: "${this.selectedLeaveStatus}"`);
          console.log(`🔍 [DEBUG] Before status filter: ${filtered.length} items`);
          
          filtered = filtered.filter(item => {
            const status = item.overall_status || item.status;
            const matches = status === this.selectedLeaveStatus;
            
            if (!matches) {
              console.log(`🔍 [DEBUG] Filtered out: ${item.employee_name || item.employee?.nama_lengkap} | Status: "${status}" | Expected: "${this.selectedLeaveStatus}"`);
            }
            
            return matches;
          });
          
          console.log(`🔍 [DEBUG] After status filter: ${filtered.length} items`);
        }
        
        if (this.leaveSearchQuery) {
          const query = this.leaveSearchQuery.toLowerCase();
          filtered = filtered.filter(item => 
            item.employee?.nama_lengkap?.toLowerCase().includes(query) ||
            item.reason?.toLowerCase().includes(query)
          );
        }
        
        // Update total items for pagination
        this.leaveTotalItems = filtered.length;
        
        // Apply pagination
        const startIndex = (this.leaveCurrentPage - 1) * this.leaveItemsPerPage;
        const endIndex = startIndex + this.leaveItemsPerPage;
        return filtered.slice(startIndex, endIndex);
      },
      
      // Computed property untuk statistik yang dinamis berdasarkan filtered data
      dynamicLeaveStats() {
        try {
          console.log('🔍 [DEBUG] ===== DYNAMIC LEAVE STATS CALLED =====');
          console.log('🔍 [DEBUG] Raw leaveData length:', this.leaveData.length);
          console.log('🔍 [DEBUG] leaveData sample:', this.leaveData.slice(0, 2));
          console.log('🔍 [DEBUG] Dependencies:', {
            selectedLeavePeriod: this.selectedLeavePeriod,
            selectedLeaveStatus: this.selectedLeaveStatus,
            leaveSearchQuery: this.leaveSearchQuery
          });
          
          // Hitung statistik dari data yang sudah difilter (tanpa pagination)
          let filteredData = [...this.leaveData];
        
        // Apply same filtering logic as filteredLeaveData
        const today = this.getTodayString();
        
        if (this.selectedLeavePeriod === 'today') {
          filteredData = filteredData.filter(item => {
            const startDate = new Date(item.start_date).toISOString().split('T')[0];
            const endDate = new Date(item.end_date).toISOString().split('T')[0];
            return today >= startDate && today <= endDate;
          });
        } else if (this.selectedLeavePeriod === 'week') {
          const startOfWeek = new Date();
          startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1);
          const endOfWeek = new Date(startOfWeek);
          endOfWeek.setDate(endOfWeek.getDate() + 6);
          const startWeekStr = startOfWeek.toISOString().split('T')[0];
          const endWeekStr = endOfWeek.toISOString().split('T')[0];
          
          filteredData = filteredData.filter(item => {
            const startDate = new Date(item.start_date).toISOString().split('T')[0];
            const endDate = new Date(item.end_date).toISOString().split('T')[0];
            return (startDate <= endWeekStr && endDate >= startWeekStr);
          });
        } else if (this.selectedLeavePeriod === 'month') {
          const startOfMonth = new Date();
          startOfMonth.setDate(1);
          const endOfMonth = new Date(startOfMonth);
          endOfMonth.setMonth(endOfMonth.getMonth() + 1);
          endOfMonth.setDate(0);
          const startMonthStr = startOfMonth.toISOString().split('T')[0];
          const endMonthStr = endOfMonth.toISOString().split('T')[0];
          
          filteredData = filteredData.filter(item => {
            const startDate = new Date(item.start_date).toISOString().split('T')[0];
            const endDate = new Date(item.end_date).toISOString().split('T')[0];
            return (startDate <= endMonthStr && endDate >= startMonthStr);
          });
        }
        
        // Apply status filter
        if (this.selectedLeaveStatus !== 'all') {
          filteredData = filteredData.filter(item => {
            const status = item.overall_status || item.status;
            return status === this.selectedLeaveStatus;
          });
        }
        
        // Apply search filter
        if (this.leaveSearchQuery) {
          const query = this.leaveSearchQuery.toLowerCase();
          filteredData = filteredData.filter(item => 
            item.employee?.nama_lengkap?.toLowerCase().includes(query) ||
            item.reason?.toLowerCase().includes(query)
          );
        }
        
        // Calculate stats from filtered data
        const stats = {
          pending: 0,
          approved: 0,
          rejected: 0,
          expired: 0,
          total: filteredData.length
        };
        
        filteredData.forEach(request => {
          const status = request.overall_status || request.status;
          if (stats.hasOwnProperty(status)) {
            stats[status]++;
          }
        });
        
        // 🔍 DEBUG: Detailed stats calculation
        console.log(`🔍 [DEBUG] ===== DYNAMIC LEAVE STATS CALCULATION =====`);
        console.log(`🔍 [DEBUG] Selected period: "${this.selectedLeavePeriod}"`);
        console.log(`🔍 [DEBUG] Selected status: "${this.selectedLeaveStatus}"`);
        console.log(`🔍 [DEBUG] Search query: "${this.leaveSearchQuery}"`);
        console.log(`🔍 [DEBUG] Total raw data: ${this.leaveData.length}`);
        console.log(`🔍 [DEBUG] After all filters: ${filteredData.length}`);
        
        // Show detailed breakdown
        const statusBreakdown = {};
        filteredData.forEach(request => {
          const status = request.overall_status || request.status;
          if (!statusBreakdown[status]) {
            statusBreakdown[status] = [];
          }
          statusBreakdown[status].push({
            id: request.id,
            name: request.employee_name || request.employee?.nama_lengkap,
            dates: `${request.start_date} - ${request.end_date}`
          });
        });
        
        console.log(`🔍 [DEBUG] Filtered data breakdown by status:`, statusBreakdown);
        console.log(`🔍 [DEBUG] Final stats:`, stats);
        console.log(`🔍 [DEBUG] ===== END STATS CALCULATION =====`);
        
        // Ensure all required properties exist
        const finalStats = {
          pending: stats.pending || 0,
          approved: stats.approved || 0,
          rejected: stats.rejected || 0,
          expired: stats.expired || 0,
          total: stats.total || 0
        };
        
        console.log(`🔍 [DEBUG] Final stats with fallbacks:`, finalStats);
        
        return finalStats;
        
        } catch (error) {
          console.error('🔍 [DEBUG] ERROR in dynamicLeaveStats:', error);
          
          // Return safe fallback
          return {
            pending: 0,
            approved: 0,
            rejected: 0,
            expired: 0,
            total: 0
          };
        }
      },
      
      // Leave pagination computed properties
      leaveTotalPages() {
        return Math.ceil(this.leaveTotalItems / this.leaveItemsPerPage);
      },
      
      leaveVisiblePages() {
        const pages = [];
        const maxVisible = 5;
        let start = Math.max(1, this.leaveCurrentPage - Math.floor(maxVisible / 2));
        let end = Math.min(this.leaveTotalPages, start + maxVisible - 1);
        
        // Adjust start if we're near the end
        if (end - start + 1 < maxVisible) {
          start = Math.max(1, end - maxVisible + 1);
        }
        
        for (let i = start; i <= end; i++) {
          pages.push(i);
        }
        
        return pages;
      },
      
      // Check if user is General Affairs
      isGeneralAffairs() {
        try {
          const user = JSON.parse(localStorage.getItem('user') || '{}');
          const userRole = user.role || user.jabatan || '';
          const normalizedRole = userRole.toLowerCase().replace(/\s+/g, '_');
          return normalizedRole === 'general_affairs';
        } catch (error) {
          console.error('Error checking user role:', error);
          return false;
        }
      },
  
      // Check if current day is online worship day (Monday, Wednesday, Friday)
      isOnlineDay() {
        const today = new Date();
        const dayOfWeek = today.getDay();
        return [1, 3, 5].includes(dayOfWeek); // Senin, Rabu, Jumat
      },
  
      // Check if current day is manual worship day (Tuesday, Thursday)
      isManualDay() {
        const today = new Date();
        const dayOfWeek = today.getDay();
        return [2, 4].includes(dayOfWeek); // Selasa, Kamis
      },
  
      // NEW: Check if current day is weekend
      isWeekend() {
        const today = new Date();
        const dayOfWeek = today.getDay();
        return dayOfWeek === 0 || dayOfWeek === 6; // Minggu, Sabtu
      },
  
      // NEW: Get current day name in Indonesian
      currentDayName() {
        const today = new Date();
        const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return dayNames[today.getDay()];
      },
      
      // Check if there's any manual attendance data selected
      hasManualData() {
        if (!this.daftarPegawai.length) return false;
        
        return Object.values(this.manualAttendanceStatus).some(status => 
          status && status !== '' && status !== 'Status'
        );
      },
      // Filter pegawai yang tidak cuti untuk manual input
      filteredPegawaiForManualInput() {
        if (!this.daftarPegawai.length) return [];
        
        // Get current date
        const currentDate = this.manualAttendanceDate || new Date().toISOString().split('T')[0];
        
        // Filter out employees who are on leave
        return this.daftarPegawai.filter(emp => {
          // Check if employee is on leave for this date
          const isOnLeave = this.leaveData.some(leave => {
            if (leave.status !== 'approved') return false;
            
            const startDate = new Date(leave.start_date).toISOString().split('T')[0];
            const endDate = new Date(leave.end_date).toISOString().split('T')[0];
            
            return leave.nama_lengkap === emp.nama_lengkap && 
                   currentDate >= startDate && 
                   currentDate <= endDate;
          });
          
          return !isOnLeave;
        });
      },
    },
    watch: {
      activeTab(newTab, oldTab) {
        console.log(`🔍 [DEBUG] Tab changed from ${oldTab} to ${newTab}`);
        if (newTab === 'worship' && oldTab !== 'worship') {
          console.log('🔍 [DEBUG] Loading leave data for worship tab');
          // Load leave data untuk statistik cuti di tab absensi ibadah
          this.loadLeaveData();
        }
      },
      
      // Update statistik ketika filteredData berubah
      filteredData: {
        handler() {
          this.stats = this.calculateStats();
        },
        deep: true
      },
  
  
      
      // Watch status filter changes
      statusFilter() {
        this.resetPagination();
      },
      
      // Watch search query changes
      searchQuery() {
        this.resetPagination();
      },
      
      // Watch manual attendance status changes for debugging
      manualAttendanceStatus: {
        handler(newVal, oldVal) {
          console.log('🔍 [DEBUG] manualAttendanceStatus changed:', {
            new: newVal,
            old: oldVal,
            changedKeys: Object.keys(newVal).filter(key => newVal[key] !== oldVal?.[key])
          });
        },
        deep: true
      },
      
      // Watch manual attendance date changes
      manualAttendanceDate() {
        if (this.daftarPegawai.length > 0) {
          this.loadExistingManualAttendance();
        }
      },
      
      // Watch period filter changes
      selectedLeavePeriod() {
        this.resetLeavePagination();
      },
      
      selectedLeaveStatus(newStatus, oldStatus) {
        console.log(`🔍 [DEBUG] Status filter changed: "${oldStatus}" → "${newStatus}"`);
        this.resetLeavePagination();
      },
      
      selectedWorshipPeriod() {
        this.loadData();
      },
      
      // Watch daftarPegawai changes to load existing data when employees are loaded
      daftarPegawai: {
        handler() {
          if (this.daftarPegawai.length > 0 && this.manualAttendanceDate) {
            this.loadExistingManualAttendance();
          }
        },
        deep: true
      }
    },
    mounted() {
      // Set default date to today
      this.manualAttendanceDate = new Date().toISOString().split('T')[0];
      
      this.loadData();
      this.fetchPegawaiForManualAttendance();
    },
    methods: {
      // Helper untuk mendapatkan tanggal hari ini dalam format YYYY-MM-DD
      getTodayString() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      },
      
      // Load worship attendance data based on selectedWorshipPeriod
      async loadData() {
          this.loading = true;
        this.error = null;
        
        try {
          // Pastikan data cuti sudah di-load sebelum ambil data absensi
          await this.loadLeaveData();
          
          // Determine API endpoint based on selectedWorshipPeriod
          let apiUrl = '/api/ga-dashboard/worship-attendance';
          
          if (this.selectedWorshipPeriod === 'today') {
            const today = this.getTodayString();
            apiUrl += `?date=${today}`;
            console.log(`🔍 [DEBUG] Mode "Today", loading data for today: ${today}`);
          } else if (this.selectedWorshipPeriod === 'week') {
            const startOfWeek = new Date();
            startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1); // Senin
            const startWeekStr = startOfWeek.toISOString().split('T')[0];
            apiUrl += `?period=week&start_date=${startWeekStr}`;
            console.log(`🔍 [DEBUG] Mode "Week", loading data from: ${startWeekStr}`);
          } else if (this.selectedWorshipPeriod === 'month') {
            const startOfMonth = new Date();
            startOfMonth.setDate(1);
            const startMonthStr = startOfMonth.toISOString().split('T')[0];
            apiUrl += `?period=month&start_date=${startMonthStr}`;
            console.log(`🔍 [DEBUG] Mode "Month", loading data from: ${startMonthStr}`);
          } else if (this.selectedWorshipPeriod === 'all') {
            apiUrl += '?all=true';
            console.log(`🔍 [DEBUG] Mode "All", loading all data`);
          }
          
          console.log(`🔍 [DEBUG] Loading worship attendance data for period: ${this.selectedWorshipPeriod}`);
          console.log(`🔍 [DEBUG] API URL: ${apiUrl}`);
          
          // Use apiFetch with automatic URL conversion
          console.log(`🔍 [DEBUG] Using apiFetch with endpoint: ${apiUrl}`);
          
          const response = await apiFetch(apiUrl, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${localStorage.getItem('token')}`
            }
          });
          
          console.log(`🔍 [DEBUG] API URL: ${apiUrl}`);
          console.log(`🔍 [DEBUG] Response status: ${response.status}`);
          console.log(`🔍 [DEBUG] Response data:`, response.data);
          
          if (!response.ok) {
            console.error(`🔍 [DEBUG] Error response:`, response.data);
            throw new Error(`Failed to fetch data: ${response.status}`);
          }
          
          const result = response.data;
          console.log('GA Dashboard worship attendance API response:', result);
          console.log('🔍 [DEBUG] Sample raw data from backend:', result.data ? result.data.slice(0, 2) : 'No data');
          
          if (result.success) {
            this.attendanceData = result.data || [];
            
            // Map data untuk memastikan format yang konsisten
            this.attendanceData = this.attendanceData.map(item => ({
              ...item,
              // Ensure attendance_method exists for old data
              attendance_method: item.attendance_method || 'online',
              attendance_source: item.attendance_source || 'zoom',
              // Map nama dan jabatan dari berbagai kemungkinan field
              name: item.name || item.employee_name || item.employee?.nama_lengkap || item.employee?.name || '-',
              position: item.position || item.employee?.position || item.employee?.jabatan_saat_ini || item.employee?.jabatan || '-'
            }));
            
            console.log('🔍 [DEBUG] Sample mapped attendance data:', this.attendanceData.slice(0, 2));
            console.log(`🔍 [DEBUG] Total attendance data loaded: ${this.attendanceData.length}`);
          } else {
            throw new Error(result.message || 'Failed to load data');
          }
          
        } catch (error) {
          console.error('Error loading data:', error);
          
          // Retry logic
          if (this.retryCount < this.maxRetries && 
              (error.message.includes('500') || error.message.includes('ERR_ADDRESS_IN_USE'))) {
            this.retryCount++;
            const delay = this.retryDelay * Math.pow(2, this.retryCount - 1);
            console.log(`Retrying in ${delay}ms (attempt ${this.retryCount}/${this.maxRetries})`);
            
            setTimeout(() => {
              this.loadData();
            }, delay);
            return;
          }
          
          // Reset retry count on different error or max retries reached
          this.retryCount = 0;
          
          let errorMessage = 'Gagal memuat data absensi ibadah';
          if (error.message.includes('500')) {
            errorMessage += '. Server sedang bermasalah, silakan coba lagi.';
          } else if (error.message.includes('404')) {
            errorMessage += '. Endpoint tidak ditemukan.';
          } else if (error.message.includes('ERR_ADDRESS_IN_USE')) {
            errorMessage += '. Koneksi bermasalah, akan otomatis refresh.';
            this.setupAutoRefresh();
          } else {
            errorMessage += `: ${error.message}`;
          }
          
          this.error = errorMessage;
          
          // Fallback: set data kosong jika error
          this.attendanceData = [];
          this.stats = {
            present: 0,
            late: 0,
            leave: 0
          };
        } finally {
          this.loading = false;
        }
      },
      
      // Retry loading data manually
      retryLoadData() {
        this.retryCount = 0;
        this.loadData();
      },
      
      // Setup auto refresh for connection errors
      setupAutoRefresh() {
        if (this.autoRefreshInterval) {
          clearInterval(this.autoRefreshInterval);
        }
        
        this.autoRefreshInterval = setInterval(() => {
          console.log('Auto refreshing data...');
          this.retryLoadData();
        }, 30000); // Refresh every 30 seconds
      },
      
      calculateStats() {
        // Gunakan filteredData yang sama dengan yang ditampilkan di tabel
        const baseData = this.filteredData;
        const stats = { present: 0, late: 0, leave: 0 };
        baseData.forEach(item => {
          const status = (item.status || '').toLowerCase();
          if (status === 'present' || status === 'hadir') stats.present++;
          else if (status === 'late' || status === 'terlambat') stats.late++;
          else if (status === 'leave' || status === 'cuti') stats.leave++;
        });
        return stats;
      },
      
      filterData() {
        // Reset pagination when filters change
        this.resetPagination();
        // Reload data ketika tanggal berubah
        this.loadData();
      },
      
      // Reset pagination when filters change
      resetPagination() {
        this.currentPage = 1;
      },
      
  
      
      // Pagination methods
      goToPage(page) {
        if (page >= 1 && page <= this.totalPages) {
          this.currentPage = page;
        }
      },
      
      // Leave pagination methods
      goToLeavePage(page) {
        if (page >= 1 && page <= this.leaveTotalPages) {
          this.leaveCurrentPage = page;
        }
      },
      
      // Reset leave pagination when filters change
      resetLeavePagination() {
        this.leaveCurrentPage = 1;
      },
      
      // Back to today's view
  
      
      // Leave data methods - Load ALL leave requests data using new GA Dashboard API
      async loadLeaveData() {
        this.leaveLoading = true;
        this.leaveError = null;
        
        try {
          console.log('Loading ALL leave requests data using new GA Dashboard API...');
          
          // Import apiClient like EmployeeList.vue does
          const { apiClient } = await import('@/services/authService');
          
          // Use new GA Dashboard API endpoint for leave data
          console.log('Using new GA Dashboard endpoint: /ga-dashboard/leave-requests');
          
          // 🔍 DEBUG: Log exact API request
          console.log('🔍 [DEBUG] ===== API REQUEST DEBUG =====');
          console.log('🔍 [DEBUG] Full API URL:', `${apiClient.defaults.baseURL}/ga-dashboard/leave-requests`);
          console.log('🔍 [DEBUG] Request headers:', apiClient.defaults.headers);
          
          const response = await apiClient.get('/ga-dashboard/leave-requests');
          
          // 🔍 DEBUG: Log exact API response
          console.log('🔍 [DEBUG] ===== API RESPONSE DEBUG =====');
          console.log('🔍 [DEBUG] Response status:', response.status);
          console.log('🔍 [DEBUG] Response headers:', response.headers);
          console.log('🔍 [DEBUG] Full response data:', response.data);
          
          console.log('GA Dashboard leave requests API response:', response);
          
          if (response.data && response.data.success) {
            this.leaveData = response.data.data || [];
            
            console.log('🔍 [DEBUG] ===== AFTER SETTING leaveData =====');
            console.log('🔍 [DEBUG] this.leaveData length after assignment:', this.leaveData.length);
            
            // 🔍 DEBUG: Analyze status data from backend
            console.log('🔍 [DEBUG] Raw leave data from backend:', this.leaveData);
            console.log('🔍 [DEBUG] Total leave records:', this.leaveData.length);
            
            // Analyze status fields in each record
            this.leaveData.forEach((item, index) => {
              console.log(`🔍 [DEBUG] Record #${index + 1}:`, {
                id: item.id,
                employee_name: item.employee_name || item.employee?.nama_lengkap,
                overall_status: item.overall_status,
                status: item.status,
                final_status: item.overall_status || item.status,
                start_date: item.start_date,
                end_date: item.end_date
              });
            });
            
            // Count status distribution
            const statusCount = {};
            this.leaveData.forEach(item => {
              const status = item.overall_status || item.status;
              statusCount[status] = (statusCount[status] || 0) + 1;
            });
            console.log('🔍 [DEBUG] Status distribution:', statusCount);
            
            this.calculateLeaveStats();
          } else {
            throw new Error(response.data?.message || 'Failed to load leave data');
          }
          
        } catch (error) {
          console.error('Error loading leave data:', error);
          this.leaveError = `Gagal memuat data cuti: ${error.message}`;
          this.leaveData = [];
          this.leaveStats = {
            pending: 0,
            approved: 0,
            rejected: 0,
            expired: 0,
            total: 0
          };
        } finally {
          this.leaveLoading = false;
        }
      },
      
      calculateLeaveStats() {
        const stats = {
          pending: 0,
          approved: 0,
          rejected: 0,
          expired: 0,
          total: this.leaveData.length
        };
        
        this.leaveData.forEach(request => {
          const status = request.overall_status || request.status;
          if (stats.hasOwnProperty(status)) {
            stats[status]++;
          }
        });
        
        this.leaveStats = stats;
      },
      
      filterLeaveData() {
        // This method is called when filters change
        // The actual filtering is done in the computed property
        console.log('Leave filters changed:', {
          status: this.selectedLeaveStatus,
          type: this.selectedLeaveType,
          search: this.leaveSearchQuery
        });
      },
      
      getLeaveTypeLabel(type) {
        const labels = {
          'annual': 'Cuti Tahunan',
          'sick': 'Cuti Sakit',
          'emergency': 'Cuti Darurat',
          'maternity': 'Cuti Melahirkan',
          'paternity': 'Cuti Ayah',
          'marriage': 'Cuti Menikah',
          'bereavement': 'Cuti Duka'
        };
        return labels[type] || type;
      },
      
      getLeaveStatusText(status) {
        const labels = {
          'pending': 'Menunggu Persetujuan',
          'approved': 'Disetujui',
          'rejected': 'Ditolak',
          'expired': 'Kadaluarsa'
        };
        const result = labels[status] || status;
        
        // 🔍 DEBUG: Status text conversion
        console.log(`🔍 [DEBUG] Status text conversion: "${status}" → "${result}"`);
        
        return result;
      },
      
      getLeaveStatusClass(status) {
        const result = `status-${status}`;
        
        // 🔍 DEBUG: Status class conversion  
        console.log(`🔍 [DEBUG] Status class conversion: "${status}" → "${result}"`);
        
        return result;
      },
      
      getEmployeeName(request) {
        // Data sudah diformat dengan benar dari backend GA Dashboard API
        return request.employee_name || 
               request.employee?.nama_lengkap || 
               request.employee?.name || 
               request.employee?.full_name ||
               request.user?.nama_lengkap ||
               request.user?.name ||
               request.user?.full_name ||
               '-';
      },
      
      getApproverName(request) {
        const status = request.overall_status || request.status;
        
        // Jika status pending, tampilkan siapa yang akan approve
        if (status === 'pending') {
          const employeeRole = request.employee?.user?.role || request.employee?.jabatan_saat_ini;
          const managerRole = this.getManagerForRole(employeeRole);
          return `Menunggu ${managerRole}`;
        }
        
        // Jika status approved, tampilkan nama manager yang bertanggung jawab
        if (status === 'approved') {
          // Coba ambil nama approver dari data backend
          const approverName = request.approved_by?.nama_lengkap || 
                              request.approved_by?.name || 
                              request.approved_by?.full_name ||
                              request.approver?.nama_lengkap ||
                              request.approver?.name ||
                              request.approver?.full_name ||
                              request.approved_by_name ||
                              request.approver_name ||
                              request.manager?.nama_lengkap ||
                              request.manager?.name ||
                              request.manager?.full_name;
          
          if (approverName && approverName !== 'Disetujui' && approverName !== 'approved') {
            return approverName;
          }
          
          // Jika tidak ada nama approver, tampilkan manager berdasarkan hierarki
          const employeeRole = request.employee?.user?.role || request.employee?.jabatan_saat_ini;
          const managerRole = this.getManagerForRole(employeeRole);
          return managerRole;
        }
        
        // Jika status rejected, tampilkan nama manager yang bertanggung jawab
        if (status === 'rejected') {
          // Coba ambil nama rejector dari data backend
          const rejectorName = request.rejected_by?.nama_lengkap || 
                              request.rejected_by?.name || 
                              request.rejected_by?.full_name ||
                              request.rejector?.nama_lengkap ||
                              request.rejector?.name ||
                              request.rejector?.full_name ||
                              request.rejected_by_name ||
                              request.rejector_name ||
                              request.manager?.nama_lengkap ||
                              request.manager?.name ||
                              request.manager?.full_name;
          
          if (rejectorName && rejectorName !== 'Ditolak' && rejectorName !== 'rejected') {
            return rejectorName;
          }
          
          // Jika tidak ada nama rejector, tampilkan manager berdasarkan hierarki
          const employeeRole = request.employee?.user?.role || request.employee?.jabatan_saat_ini;
          const managerRole = this.getManagerForRole(employeeRole);
          return managerRole;
        }
        
        // Jika status expired
        if (status === 'expired') {
          return 'Kadaluarsa';
        }
        
        return '-';
      },
      
      getManagerForRole(employeeRole) {
        const hierarchy = {
          'Finance': 'HR Manager',
          'General Affairs': 'HR Manager',
          'Office Assistant': 'HR Manager',
          'Producer': 'Program Manager',
          'Creative': 'Program Manager',
          'Production': 'Program Manager',
          'Editor': 'Program Manager',
          'Social Media': 'Distribution Manager',
          'Promotion': 'Distribution Manager',
          'Graphic Design': 'Distribution Manager',
          'Hopeline Care': 'Distribution Manager'
        };
        return hierarchy[employeeRole] || 'Atasan';
      },
      
      getLeaveDuration(request) {
        // Coba berbagai kemungkinan field untuk durasi
        return request.total_days || 
               request.duration || 
               request.days || 
               request.leave_days ||
               this.calculateDuration(request.start_date, request.end_date);
      },
      
      calculateDuration(startDate, endDate) {
        if (!startDate || !endDate) return 0;
        
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 karena inclusive
        
        return diffDays;
      },
      
      formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('id-ID');
      },
      
      getStatusClass(status) {
        const statusLower = status?.toLowerCase();
        const classMap = {
          present: 'status-present',
          late: 'status-late',
          leave: 'status-leave',
          hadir: 'status-present',
          terlambat: 'status-late',
          cuti: 'status-leave'
        };
        return classMap[statusLower] || '';
      },
      
      getStatusText(status) {
        const statusMap = {
          'present': 'Hadir',
          'hadir': 'Hadir',
          'late': 'Terlambat',
          'terlambat': 'Terlambat',
          'absent': 'Absen',
          'absen': 'Absen',
          'leave': 'Cuti',
          'cuti': 'Cuti'
        };
        return statusMap[status?.toLowerCase()] || status || 'Tidak Diketahui';
      },
      
      // NEW: Get method class for styling
      getMethodClass(method) {
        const methodMap = {
          'online': 'method-online',
          'manual': 'method-manual'
        };
        return methodMap[method] || 'method-online'; // Default to online for old data
      },
  
      // NEW: Get method text for display
      getMethodText(method) {
        const methodMap = {
          'online': 'Online',
          'manual': 'Manual'
        };
        return methodMap[method] || 'Online'; // Default to online for old data
      },
      
      // Debug methods
      loadDebugSettings() {
        if (import.meta.env.DEV) {
          const savedDebugMode = localStorage.getItem('gaDashboardDebugMode')
          const savedDebugDay = localStorage.getItem('gaDashboardDebugDay')
          const savedDebugHour = localStorage.getItem('gaDashboardDebugHour')
          const savedDebugMinute = localStorage.getItem('gaDashboardDebugMinute')
          
          if (savedDebugMode) this.debugMode = JSON.parse(savedDebugMode)
          if (savedDebugDay) this.debugDay = savedDebugDay
          if (savedDebugHour) this.debugHour = savedDebugHour
          if (savedDebugMinute) this.debugMinute = savedDebugMinute
          
          // Set current user ID
          try {
            const userStr = localStorage.getItem('user')
            if (userStr) {
              const user = JSON.parse(userStr)
              this.currentUserId = user.id || user.employee_id || null
            }
          } catch (error) {
            console.error('Error loading current user:', error)
          }
        }
      },
      
      saveDebugSettings() {
        if (import.meta.env.DEV) {
          localStorage.setItem('gaDashboardDebugMode', JSON.stringify(this.debugMode))
          localStorage.setItem('gaDashboardDebugDay', this.debugDay)
          localStorage.setItem('gaDashboardDebugHour', this.debugHour)
          localStorage.setItem('gaDashboardDebugMinute', this.debugMinute)
        }
      },
      
      getCurrentDay() {
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
        return days[new Date().getDay()]
      },
      
      setDebugDay(day) {
        this.debugDay = day
        this.saveDebugSettings()
      },
      
      resetDebug() {
        this.debugMode = false
        this.debugDay = ''
        this.debugHour = ''
        this.debugMinute = ''
        this.saveDebugSettings()
      },
      
      toggleDebugMode() {
        this.debugMode = !this.debugMode
        this.saveDebugSettings()
      },
      
      // Helper method untuk mengecek apakah tanggal adalah hari ibadah
      isWorshipDay(date) {
        if (!date) return false;
        
        const dayOfWeek = new Date(date).getDay();
        // Senin (1), Rabu (3), Jumat (5)
        return dayOfWeek === 1 || dayOfWeek === 3 || dayOfWeek === 5;
      },
      
      // Helper method untuk mendapatkan nama hari dalam bahasa Indonesia
      getDayName(date) {
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return days[new Date(date).getDay()];
      },
  
      // Helper method untuk mendapatkan data cuti yang jatuh pada hari kerja berdasarkan periode
      getLeaveDataForWorshipDays() {
        const leaveDataForWorshipDays = [];
        const workDayNumbers = [1, 2, 3, 4, 5]; // Senin, Selasa, Rabu, Kamis, Jumat (semua hari kerja)
        
        // Tentukan rentang tanggal berdasarkan selectedWorshipPeriod
        // Harus sama dengan logic di loadData() untuk konsistensi
        const today = this.getTodayString();
        let filterStartDate = null;
        let filterEndDate = null;
        
        if (this.selectedWorshipPeriod === 'today') {
          filterStartDate = filterEndDate = today;
        } else if (this.selectedWorshipPeriod === 'week') {
          const startOfWeek = new Date();
          startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1); // Senin
          filterStartDate = startOfWeek.toISOString().split('T')[0];
          filterEndDate = today; // Sampai hari ini
        } else if (this.selectedWorshipPeriod === 'month') {
          const startOfMonth = new Date();
          startOfMonth.setDate(1);
          filterStartDate = startOfMonth.toISOString().split('T')[0];
          filterEndDate = today; // Sampai hari ini
        }
        // 'all' tidak ada filter tanggal
        
        console.log('===== [DEBUG] getLeaveDataForWorshipDays START =====');
        console.log(`[DEBUG] Total leaveData: ${this.leaveData.length}`);
        console.log(`[DEBUG] Periode: ${this.selectedWorshipPeriod}, filterStartDate: ${filterStartDate}, filterEndDate: ${filterEndDate}`);
        
        this.leaveData.forEach((leave, index) => {
          const leaveStatus = leave.overall_status || leave.status;
          if (leaveStatus !== 'approved') return;
          
          const nama = leave.employee?.nama_lengkap || leave.employee_name || leave.employee_id;
          const startDate = new Date(leave.start_date);
          const endDate = new Date(leave.end_date);
          
          console.log(`[DEBUG] Leave #${index + 1} | id: ${leave.id} | nama: ${nama} | status: ${leaveStatus} | start_date: ${leave.start_date} | end_date: ${leave.end_date}`);
          
          // Generate semua tanggal dalam periode cuti
          const leaveStartDate = new Date(leave.start_date);
          const leaveEndDate = new Date(leave.end_date);
          const currentDate = new Date(leaveStartDate);
          
          while (currentDate <= leaveEndDate) {
            const dateStr = currentDate.toISOString().split('T')[0];
            const dayOfWeek = currentDate.getDay();
            const isWorkDay = workDayNumbers.includes(dayOfWeek);
            
            // Filter berdasarkan periode yang dipilih
            let lolosPeriode = true;
            if (filterStartDate && dateStr < filterStartDate) lolosPeriode = false;
            if (filterEndDate && dateStr > filterEndDate) lolosPeriode = false;
            
            console.log(`[DEBUG]   - date: ${dateStr} | dayOfWeek: ${dayOfWeek} | isWorkDay: ${isWorkDay} | lolosPeriode: ${lolosPeriode}`);
            
            if (isWorkDay && lolosPeriode) {
              const leaveItem = {
                id: `leave_${leave.id}_${dateStr}`,
                employee_id: leave.employee_id || leave.employee?.id,
                employee: leave.employee,
                employee_name: nama,
                name: nama, // Untuk compatibility dengan filteredData
                date: dateStr,
                status: 'leave',
                attendance_time: '-',
                attendance_method: '-', // NEW: Set method ke "-" untuk data cuti
                position: leave.employee?.position || leave.employee?.jabatan_saat_ini || '-',
                leave_id: leave.id,
                leave_dates: [leave.start_date, leave.end_date],
                overall_status: leaveStatus,
                // Tambahkan flag untuk membedakan dengan data renungan
                is_leave: true,
                data_type: 'leave'
              };
              leaveDataForWorshipDays.push(leaveItem);
              console.log(`[DEBUG]     => DITAMBAHKAN KE TABEL: ${nama} | ${dateStr}`);
            }
            
            // Pindah ke tanggal berikutnya
            currentDate.setDate(currentDate.getDate() + 1);
          }
        });
        
        console.log(`[DEBUG] TOTAL CUTI MASUK TABEL: ${leaveDataForWorshipDays.length}`);
        console.log('===== [DEBUG] getLeaveDataForWorshipDays END =====');
        
        return leaveDataForWorshipDays;
      },
      async fetchPegawaiForManualAttendance() {
        try {
          console.log('🔍 [DEBUG] Fetching employees for manual attendance...');
          
          // Import apiClient like EmployeeList.vue does
          const { apiClient } = await import('@/services/authService');
          
          // Use apiClient instead of fetch to avoid CORS
          const response = await apiClient.get('/ga-dashboard/employees-for-manual-input');
          console.log('🔍 [DEBUG] Employees API response:', response);
  
          // Handle response like EmployeeList.vue does
          let pegawaiArr = [];
          
          if (response.data && response.data.success && Array.isArray(response.data.data)) {
            // Backend response format: { success: true, data: [...] }
            pegawaiArr = response.data.data;
          } else if (response.data && Array.isArray(response.data)) {
            // Direct array response
            pegawaiArr = response.data;
          } else {
            console.error('🔍 [DEBUG] Struktur response tidak dikenali:', response);
            pegawaiArr = [];
          }
  
          // Debug: Log raw employee data structure
          console.log('🔍 [DEBUG] Raw employee data structure:');
          if (pegawaiArr.length > 0) {
            console.log('🔍 [DEBUG] Sample employee object:', pegawaiArr[0]);
            console.log('🔍 [DEBUG] Available fields:', Object.keys(pegawaiArr[0]));
          }
  
          // Map to consistent format like EmployeeList.vue
          this.daftarPegawai = pegawaiArr.map(emp => ({
            id: emp.pegawai_id || emp.id, // Backend menggunakan pegawai_id
            nama_lengkap: emp.nama_lengkap || emp.name || emp.nama || 'Unknown',
            jabatan_saat_ini: emp.jabatan || emp.jabatan_saat_ini || emp.position || emp.role || '-'
          }));
  
          // Set initial status to empty string for placeholder
          this.manualAttendanceStatus = {};
          this.daftarPegawai.forEach(emp => {
            this.manualAttendanceStatus[emp.id] = '';
          });
  
          console.log('🔍 [DEBUG] HASIL daftarPegawai:', this.daftarPegawai);
          console.log('🔍 [DEBUG] Total employees loaded:', this.daftarPegawai.length);
          console.log('🔍 [DEBUG] Initial manualAttendanceStatus:', this.manualAttendanceStatus);
          
          // Debug: Show sample employee mapping
          if (this.daftarPegawai.length > 0) {
            console.log('🔍 [DEBUG] Sample employee mapping:', {
              original: pegawaiArr[0],
              mapped: this.daftarPegawai[0],
              statusKey: this.daftarPegawai[0].id
            });
          }
        } catch (error) {
          console.error('🔍 [DEBUG] Gagal mengambil data pegawai untuk absensi manual:', error);
          this.daftarPegawai = [];
          
          // Show error to user
          alert('Gagal memuat data pegawai. Silakan refresh halaman atau coba lagi.');
        }
      },
      async submitManualAttendance() {
        if (!this.hasManualData) {
          alert('Pilih status untuk setidaknya satu pegawai terlebih dahulu.');
          return;
        }
  
        this.isSubmitting = true; // Set loading state
        
        try {
          // Prepare data for submission
          const dataToSubmit = [];
          
          for (const [employeeId, status] of Object.entries(this.manualAttendanceStatus)) {
            if (status && status !== '' && status !== 'Status') {
              dataToSubmit.push({
                employee_id: parseInt(employeeId),
                pegawai_id: parseInt(employeeId), // Tambahan field untuk backend
                id: parseInt(employeeId), // Tambahan field untuk backend
                status: status
              });
            }
          }
  
          if (dataToSubmit.length === 0) {
            alert('Tidak ada data yang valid untuk disimpan.');
            return;
          }
  
          // Import apiClient like EmployeeList.vue does
          const { apiClient } = await import('@/services/authService');
          
          // Debug: Log data yang akan dikirim
          console.log('🔍 [DEBUG] Data yang akan dikirim ke backend:');
          console.log('🔍 [DEBUG] tanggal:', this.manualAttendanceDate);
          console.log('🔍 [DEBUG] attendance_data:', dataToSubmit);
          console.log('🔍 [DEBUG] Total data yang akan dikirim:', dataToSubmit.length);
          
          // Debug: Log detail setiap item
          dataToSubmit.forEach((item, index) => {
            console.log(`🔍 [DEBUG] Item ${index + 1}:`, {
              employee_id: item.employee_id,
              employee_id_type: typeof item.employee_id,
              status: item.status,
              status_type: typeof item.status
            });
          });
          
          // Debug: Log manualAttendanceStatus untuk comparison
          console.log('🔍 [DEBUG] manualAttendanceStatus:', this.manualAttendanceStatus);
          console.log('🔍 [DEBUG] daftarPegawai:', this.daftarPegawai);
          
          // Call backend API menggunakan apiClient
          const response = await apiClient.post('/ga-dashboard/manual-worship-attendance', {
            tanggal: this.manualAttendanceDate,
            attendance_data: dataToSubmit
          });
          
          console.log('🔍 [DEBUG] Response dari backend:', response);
  
          if (response.data.success) {
            alert(`Data absensi manual berhasil disimpan! (${response.data.data.saved_count} dari ${response.data.data.total_data} data)`);
            
            // Reload existing data instead of resetting
            await this.loadExistingManualAttendance();
            
            // Reload main data
            this.loadData();
          } else {
            // Handle validation errors
            if (response.data.errors) {
              const errorMessages = Object.values(response.data.errors).flat().join('\n');
              alert(`Error validasi:\n${errorMessages}`);
            } else {
              alert(`Gagal menyimpan data: ${response.data.message}`);
            }
          }
        } catch (error) {
          console.error('🔍 [DEBUG] Error submitting manual attendance:', error);
          
          // Debug: Log error details
          if (error.response) {
            console.error('🔍 [DEBUG] Error response status:', error.response.status);
            console.error('🔍 [DEBUG] Error response data:', error.response.data);
            console.error('🔍 [DEBUG] Error response headers:', error.response.headers);
          } else if (error.request) {
            console.error('🔍 [DEBUG] Error request:', error.request);
          } else {
            console.error('🔍 [DEBUG] Error message:', error.message);
          }
          
          // Show user-friendly error message
          let errorMessage = 'Gagal menyimpan data absensi manual.';
          
          if (error.response?.data?.message) {
            errorMessage += `\n\nError: ${error.response.data.message}`;
          }
          
          if (error.response?.data?.errors) {
            const validationErrors = Object.values(error.response.data.errors).flat().join('\n');
            errorMessage += `\n\nValidasi Error:\n${validationErrors}`;
          }
          
          alert(errorMessage);
        } finally {
          this.isSubmitting = false; // Reset loading state
        }
      },
      // Load existing manual attendance data for the selected date
      async loadExistingManualAttendance() {
        try {
          console.log('🔍 [DEBUG] Loading existing manual attendance for date:', this.manualAttendanceDate);
          console.log('🔍 [DEBUG] Current daftarPegawai length:', this.daftarPegawai.length);
          
          // Import apiClient
          const { apiClient } = await import('@/services/authService');
          
          // Get existing attendance data for the date using worship-attendance endpoint
          const response = await apiClient.get(`/ga-dashboard/worship-attendance?date=${this.manualAttendanceDate}`);
          
          console.log('🔍 [DEBUG] Existing attendance response:', response);
          
          if (response.data && response.data.success && response.data.data) {
            const existingData = response.data.data;
            console.log('🔍 [DEBUG] Existing data found:', existingData);
            
            // Debug: Show all Albert records
            const albertRecords = existingData.filter(record => 
              record.nama_lengkap && record.nama_lengkap.includes('Albert')
            );
            console.log('🔍 [DEBUG] All Albert records:', albertRecords);
            
            // Debug: Show sample records
            console.log('🔍 [DEBUG] Sample existing records:', existingData.slice(0, 3));
            
            // Reset status
            this.manualAttendanceStatus = {};
            
            // Set status for each employee based on existing data
            this.daftarPegawai.forEach(emp => {
              console.log(`🔍 [DEBUG] Processing employee: ${emp.nama_lengkap} (ID: ${emp.id})`);
              
              const existingRecord = existingData.find(record => {
                const matchById = record.employee_id === emp.id || 
                                 record.pegawai_id === emp.id || 
                                 record.id === emp.id;
                const matchByName = record.nama_lengkap === emp.nama_lengkap;
                
                console.log(`🔍 [DEBUG] Checking record:`, {
                  record_id: record.employee_id || record.pegawai_id || record.id,
                  record_name: record.nama_lengkap,
                  emp_id: emp.id,
                  emp_name: emp.nama_lengkap,
                  matchById,
                  matchByName
                });
                
                return matchById || matchByName;
              });
              
              if (existingRecord) {
                // Map status values
                let status = existingRecord.status;
                
                // Map status dari backend ke frontend dropdown
                if (status === 'present' || status === 'hadir') {
                  status = 'present';
                } else if (status === 'late' || status === 'terlambat') {
                  status = 'late';
                } else if (status === 'absent' || status === 'absen' || status === 'tidak_hadir') {
                  status = 'absent';
                } else if (status === 'leave' || status === 'cuti') {
                  status = 'leave';
                } else {
                  // Jika status tidak dikenali, set ke empty
                  status = '';
                  console.log(`🔍 [DEBUG] ⚠️ Unknown status for ${emp.nama_lengkap}: ${existingRecord.status}`);
                }
                
                this.manualAttendanceStatus[emp.id] = status;
                console.log(`🔍 [DEBUG] ✅ Found existing status for ${emp.nama_lengkap}: ${status} (original: ${existingRecord.status})`);
              } else {
                this.manualAttendanceStatus[emp.id] = '';
                console.log(`🔍 [DEBUG] ❌ No existing status for ${emp.nama_lengkap}`);
              }
            });
            
            console.log('🔍 [DEBUG] Updated manualAttendanceStatus:', this.manualAttendanceStatus);
          } else {
            console.log('🔍 [DEBUG] No existing data found or response format incorrect');
            // Reset to empty status
            this.manualAttendanceStatus = {};
            this.daftarPegawai.forEach(emp => {
              this.manualAttendanceStatus[emp.id] = '';
            });
          }
          
        } catch (error) {
          console.error('🔍 [DEBUG] Error loading existing manual attendance:', error);
          // If error, just reset to empty status
          this.manualAttendanceStatus = {};
          this.daftarPegawai.forEach(emp => {
            this.manualAttendanceStatus[emp.id] = '';
          });
        }
      },
    }
  }
  </script>
  
  <style scoped>
  .ga-dashboard {
    padding: var(--space-5);
    max-width: 1200px;
    margin: 0 auto;
    background-color: var(--bg-secondary);
    min-height: 100vh;
  }
  
  .dashboard-header {
    text-align: center;
    margin-bottom: 30px;
  }
  
  .dashboard-header h1 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 28px;
  }
  
  .page-title {
    font-weight: bold;
    font-size: 32px;
    color: #1a365d;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    letter-spacing: -0.5px;
  }
  
  .dashboard-header p {
    margin: 0;
    color: #7f8c8d;
    font-size: 16px;
  }
  
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
  }
  
  .stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: white;
    border: 1px solid #e1e8ed;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  
  .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
  }
  
  .stat-icon.present {
    background: rgba(46, 204, 113, 0.1);
    color: #27ae60;
  }
  
  
  
  .stat-icon.late {
    background: rgba(243, 156, 18, 0.1);
    color: #f39c12;
  }
  
  .stat-icon.leave {
    background: rgba(52, 152, 219, 0.1);
    color: #3498db;
  }
  
  .stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
  }
  
  .stat-content p {
    margin: 0;
    color: #7f8c8d;
    font-size: 14px;
  }
  
  .filter-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
  }
  
  .filter-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: end;
  }
  
  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
  }
  
  .filter-group label {
    font-weight: 500;
    color: #2c3e50;
    font-size: 14px;
  }
  
  .filter-group input,
  .filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    min-width: 150px;
  }
  
  .table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  
  .attendance-table {
    width: 100%;
    border-collapse: collapse;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  }
  
  .attendance-table th {
    background: #f8f9fa;
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 1px solid #e1e8ed;
    font-size: 14px;
    vertical-align: middle;
  }
  
  .attendance-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
    font-size: 14px;
    line-height: 1.4;
  }
  
  .attendance-table tr:hover {
    background: #f8f9fa;
  }
  
  .status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    text-align: center;
    display: inline-block;
    min-width: 80px;
  }
  
  .status-badge.status-present {
    background: rgba(46, 204, 113, 0.1);
    color: #27ae60;
  }
  
  
  
  .status-badge.status-late {
    background: rgba(243, 156, 18, 0.1);
    color: #f39c12;
  }
  
  .status-badge.status-leave {
    background: rgba(52, 152, 219, 0.1);
    color: #3498db;
  }
  
  .status-badge.status-absent {
    background-color: #e74c3c;
    color: white;
  }
  
  
  
  .btn-refresh {
    background: #3498db;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .btn-refresh:hover:not(:disabled) {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
  }
  
  .btn-refresh:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
  }
  
  .btn-debug {
    padding: 6px 10px;
    border: none;
    border-radius: 4px;
    background: #3498db;
    color: white;
    cursor: pointer;
    font-size: 12px;
  }
  
  .btn-debug:hover {
    background: #2980b9;
  }
  
  .btn-reset {
    padding: 6px 10px;
    border: none;
    border-radius: 4px;
    background: #3498db;
    color: white;
    cursor: pointer;
    font-size: 12px;
  }
  
  .btn-reset:hover {
    background: #2980b9;
  }
  
  .no-data {
    text-align: center;
    padding: 40px 20px;
    color: #666;
  }
  
  .no-data i {
    font-size: 48px;
    margin-bottom: 16px;
    color: #ddd;
  }
  
  .loading-state {
    text-align: center;
    padding: 40px 20px;
    color: #3498db;
  }
  
  .loading-state i {
    font-size: 48px;
    margin-bottom: 16px;
  }
  
  .error-state {
    text-align: center;
    padding: 40px 20px;
    color: #e74c3c;
  }
  
  .error-state i {
    font-size: 48px;
    margin-bottom: 16px;
  }
  
  .btn-retry {
    margin-top: 16px;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background: #e74c3c;
    color: white;
    cursor: pointer;
  }
  
  .btn-retry:hover {
    background: #c0392b;
  }
  
  .no-data p {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 500;
  }
  
  .no-data small {
    color: #999;
  }
  
  
  
  /* Tab Navigation Styles */
  .tab-navigation {
    display: flex;
    gap: 0;
    margin-bottom: 30px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  
  .tab-button {
    flex: 1;
    padding: 12px 20px;
    border: none;
    background: transparent;
    color: #6c757d;
    cursor: pointer;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }
  
  .tab-button:hover {
    background: #e9ecef;
    color: #495057;
  }
  
  .tab-button.active {
    background: #007bff;
    color: white;
    box-shadow: 0 2px 4px rgba(0,123,255,0.3);
  }
  
  .tab-button i {
    font-size: 16px;
  }
  
  .tab-content {
    animation: fadeIn 0.3s ease-in-out;
  }
  
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  /* Leave Status Styles */
  .status-badge.status-pending {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
  }
  
  .status-badge.status-approved {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
  }
  
  .status-badge.status-rejected {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
  }
  
  .status-badge.status-expired {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
  }
  
  /* Leave Stats Icons */
  .stat-icon.pending {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
  }
  
  .stat-icon.approved {
    background: linear-gradient(135deg, #28a745, #20c997);
  }
  
  .stat-icon.rejected {
    background: linear-gradient(135deg, #dc3545, #e83e8c);
  }
  
  .stat-icon.total {
    background: linear-gradient(135deg, #6f42c1, #6610f2);
  }
  
  /* Reason cell styling */
  .reason-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 14px;
    color: #495057;
  }
  
  .reason-cell:hover {
    white-space: normal;
    word-wrap: break-word;
  }
  
  /* Approver cell styling */
  .approver-cell {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 14px;
    color: #495057;
    vertical-align: middle;
    font-weight: 500;
  }
  
  /* Number cell styling */
  .number-cell {
    font-size: 14px;
    color: #6c757d;
    font-weight: 500;
    text-align: center;
    width: 50px;
  }
  
  /* Status cell styling */
  .status-cell {
    text-align: center;
    vertical-align: middle;
  }
  
  /* Employee name cell styling */
  .employee-name-cell {
    font-weight: 500;
    color: #2c3e50;
    font-size: 14px;
  }
  
  /* Date cell styling */
  .date-cell {
    font-size: 14px;
    color: #495057;
    font-weight: 400;
  }
  
  /* Duration cell styling */
  .duration-cell {
    font-size: 14px;
    color: #495057;
    font-weight: 500;
    text-align: center;
  }
  
  /* Leave type cell styling */
  .leave-type-cell {
    font-size: 14px;
    color: #495057;
    font-weight: 500;
  }
  
  /* Debug Panel Styles */
  .debug-panel {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  }
  
  .debug-header {
    text-align: center;
    margin-bottom: 1.5rem;
  }
  
  .debug-header h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    font-weight: 700;
  }
  
  .debug-header p {
    margin: 0;
    opacity: 0.9;
  }
  
  .debug-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
  }
  
  .debug-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    backdrop-filter: blur(10px);
  }
  
  .debug-section h4 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    font-weight: 600;
  }
  
  .debug-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  
  .debug-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .debug-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
  }
  
  .debug-btn.active {
    background: rgba(255, 255, 255, 0.4);
    border-color: rgba(255, 255, 255, 0.6);
  }
  
  .debug-btn.reset {
    background: rgba(255, 107, 107, 0.8);
  }
  
  .debug-btn.toggle {
    background: rgba(46, 204, 113, 0.8);
  }
  
  .debug-time-controls {
    display: flex;
    gap: 0.5rem;
  }
  
  .debug-select {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
  }
  
  .debug-select option {
    background: #2d3748;
    color: white;
  }
  
  .debug-status {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .debug-info {
    font-size: 0.875rem;
    opacity: 0.9;
  }
  
  .debug-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
  }
  
  /* All Data Button Styles */
  .btn-all-data {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .btn-all-data:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
  }
  
  .btn-all-data:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
  }
  
  .btn-back-date {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .btn-back-date:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
  }
  
  /* Pagination Styles */
  .pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 15px 0;
    border-top: 1px solid #e9ecef;
  }
  
  .pagination-info {
    color: #6c757d;
    font-size: 14px;
  }
  
  .pagination-controls {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .btn-pagination {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #6c757d;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
  }
  
  .btn-pagination:hover:not(:disabled) {
    background: #e9ecef;
    border-color: #adb5bd;
    color: #495057;
  }
  
  .btn-pagination:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8f9fa;
  }
  
  .page-numbers {
    display: flex;
    gap: 4px;
  }
  
  .btn-page {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #6c757d;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .btn-page:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    color: #495057;
  }
  
  .btn-page.active {
    background: #007bff;
    border-color: #007bff;
    color: white;
  }
  
  .btn-page.active:hover {
    background: #0056b3;
    border-color: #0056b3;
  }
  
  @media (max-width: 768px) {
    .pagination-container {
      flex-direction: column;
      gap: 15px;
      text-align: center;
    }
    
    .pagination-controls {
      flex-wrap: wrap;
      justify-content: center;
    }
    
    .btn-pagination,
    .btn-page {
      min-width: 35px;
      height: 35px;
      padding: 6px 10px;
      font-size: 12px;
    }
  }
  
  @media (max-width: 768px) {
    .ga-dashboard {
      padding: 15px;
    }
    
  
    
    .stats-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }
    
    .filter-section {
      flex-direction: column;
      gap: 15px;
    }
    
    .filter-group input,
    .filter-group select {
      min-width: auto;
    }
    
    .attendance-table {
      font-size: 14px;
    }
    
    .attendance-table th,
    .attendance-table td {
      padding: 10px;
    }
    
    /* Responsive table for leave data */
    .attendance-table th:nth-child(8),
    .attendance-table td:nth-child(8) {
      display: none; /* Hide "Disetujui Oleh" column on mobile */
    }
    
    .debug-controls {
      grid-template-columns: 1fr;
    }
    
    .debug-time-controls {
      flex-direction: column;
    }
    
    .debug-actions {
      flex-direction: column;
    }
  }
  
  .input-date-manual {
    padding: 8px 14px;
    border: 1.5px solid #d0e3f7;
    border-radius: 8px;
    background: #fff;
    font-size: 15px;
    color: #222e3a;
    transition: border 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 4px rgba(52, 152, 219, 0.06);
  }
  .input-date-manual:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.13);
  }
  
  .dropdown-status-beauty {
    padding: 9px 16px;
    border: 1.5px solid #b6d6f6;
    border-radius: 8px;
    background: #fff;
    font-size: 15px;
    color: #222e3a;
    transition: border 0.2s, box-shadow 0.2s;
    box-shadow: 0 1px 4px rgba(52, 152, 219, 0.06);
    min-width: 120px;
    cursor: pointer;
  }
  .dropdown-status-beauty:hover, .dropdown-status-beauty:focus {
    border-color: #3498db;
    box-shadow: 0 2px 12px rgba(52, 152, 219, 0.13);
    outline: none;
  }
  .dropdown-status-beauty option[value=""] {
    color: #b0b8c1;
    font-style: italic;
  }
  .manual-attendance-table {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.07);
    overflow: hidden;
  }
  .manual-attendance-table th {
    background: #f8f9fa;
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 1px solid #e1e8ed;
    font-size: 14px;
    vertical-align: middle;
  }
  .manual-attendance-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
    font-size: 14px;
    line-height: 1.4;
  }
  
  /* NEW: Method badge styles */
  .method-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .method-badge.method-online {
    background-color: #3498db;
    color: white;
  }
  
  .method-badge.method-manual {
    background-color: #f39c12;
    color: white;
  }
  
  /* Manual attendance styles */
  .manual-attendance-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
  }
  
  .manual-attendance-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 10px;
  }
  
  .btn-submit-manual {
    background: #3498db;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s ease;
  }
  
  .btn-submit-manual:hover {
    background: #2980b9;
  }
  
  .btn-submit-manual:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  
  /* Status badge styles for leave data */
  .status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  
  .status-badge.status-pending {
    background-color: #ff9800;
    color: white;
  }
  
  .status-badge.status-approved {
    background-color: #4caf50;
    color: white;
  }
  
  .status-badge.status-rejected {
    background-color: #f44336;
    color: white;
  }
  
  /* Stat icon colors */
  .stat-icon.pending {
    background: linear-gradient(135deg, #ff9800, #f57c00);
  }
  
  .stat-icon.rejected {
    background: linear-gradient(135deg, #f44336, #d32f2f);
  }
  
  
  
  
  </style>