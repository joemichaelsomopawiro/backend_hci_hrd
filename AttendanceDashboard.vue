<template>
  <div class="attendance-dashboard">
    <!-- Header -->
    <div class="header">
      <h1>📊 Attendance Hari Ini</h1>
      <div class="current-time">{{ currentTime }}</div>
      <div class="tips">
        <strong>🚀 Auto Sync:</strong> Sistem otomatis sync saat refresh halaman (throttling 5 menit)
        <br><strong>💡 Manual:</strong> Gunakan tombol sync jika diperlukan sync paksa
      </div>
    </div>

    <!-- Controls -->
    <div class="controls">
      <button 
        class="btn success" 
        @click="refreshData" 
        :disabled="loading.refresh"
        title="Refresh data terbaru dari mesin + update linking employee"
      >
        {{ loading.refresh ? '⏳ Refreshing...' : '🔄 Refresh' }}
      </button>
      <button 
        class="btn warning" 
        @click="showFullSyncConfirm" 
        :disabled="loading.fullSync"
        title="Sync penuh - semua data dari mesin (60-90 detik)"
      >
        {{ loading.fullSync ? '⏳ Full Syncing...' : '📡 Full Sync' }}
      </button>
    </div>
    
    <div class="sync-info">
      <span class="info-item">🚀 <strong>Auto Sync:</strong> Otomatis setiap refresh halaman</span>
      <span class="info-item">🔄 <strong>Manual Refresh:</strong> Tersedia untuk update paksa + linking employee</span>
    </div>

    <!-- Summary Cards -->
    <div class="summary" v-if="!loading.data">
      <div class="summary-card">
        <h3>{{ summary.total_users }}</h3>
        <p>Total Users</p>
      </div>
      <div class="summary-card success">
        <h3>{{ summary.present_ontime }}</h3>
        <p>Hadir Tepat Waktu</p>
      </div>
      <div class="summary-card warning">
        <h3>{{ summary.present_late }}</h3>
        <p>Hadir Terlambat</p>
      </div>
    </div>

    <!-- Loading Summary -->
    <div v-else class="summary">
      <div class="loading-message">{{ loadingMessage }}</div>
    </div>
    
    <!-- Sync Statistics -->
    <div v-if="showSyncStats" class="sync-stats">
      <h4>📊 Statistik Sync Terakhir</h4>
      <div class="sync-stats-content" v-html="syncStatsContent"></div>
    </div>

    <!-- Attendance Table -->
    <div class="attendance-data">
      <div v-if="loading.data" class="loading">📋 Mengambil data attendance...</div>
      <div v-else-if="attendances.length === 0" class="loading">📋 Tidak ada data attendance untuk hari ini</div>
      <table v-else class="attendance-table">
        <thead>
          <tr>
            <th>No.</th>
            <th>No.ID</th>
            <th>Nama</th>
            <th>Tanggal Absen</th>
            <th>Scan Masuk</th>
            <th>Scan Pulang</th>
            <th>Status</th>
            <th>Jam Kerja</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(att, index) in attendances" :key="att.id">
            <td><strong>{{ index + 1 }}</strong></td>
            <td><strong>{{ att.user_pin || 'N/A' }}</strong></td>
            <td><strong>{{ att.user_name || 'N/A' }}</strong></td>
            <td>{{ formatDate(att.date) }}</td>
            <td><strong>{{ formatTime(att.check_in) }}</strong></td>
            <td><strong>{{ formatTime(att.check_out) }}</strong></td>
            <td><span :class="['status', att.status]">{{ getStatusText(att.status) }}</span></td>
            <td><strong>{{ calculateWorkHours(att) }}</strong></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Last Updated -->
    <div class="last-updated">Terakhir diupdate: {{ lastUpdated }}</div>
    
    <!-- Auto Sync Status -->
    <div v-if="showAutoSyncStatus" class="auto-sync-status" :style="autoSyncStatusStyle">
      <span>{{ autoSyncStatusText }}</span>
    </div>

    <!-- Footer Info -->
    <div class="footer-info">
      <h4>🚀 Sistem Attendance Auto-Sync</h4>
      <p><strong>Smart Auto-Sync:</strong> Sistem otomatis sync ketika halaman dimuat (throttling 5 menit)</p>
      <p>Dashboard auto-refresh setiap 30 detik + auto-sync ketika refresh halaman.</p>
    </div>

    <!-- Custom Popup Component -->
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

export default {
  name: 'AttendanceDashboard',
  components: {
    CustomPopup
  },
  data() {
    return {
      // Main data
      attendances: [],
      summary: {
        total_users: 0,
        present_ontime: 0,
        present_late: 0
      },
      
      // Loading states
      loading: {
        data: false,
        refresh: false,
        fullSync: false
      },
      
      // UI states
      currentTime: '',
      lastUpdated: '',
      loadingMessage: '⏳ Loading data...',
      
      // Sync stats
      showSyncStats: false,
      syncStatsContent: '',
      
      // Auto sync status
      showAutoSyncStatus: false,
      autoSyncStatusText: '',
      autoSyncStatusStyle: {},
      
      // Popup
      popup: {
        show: false,
        title: '',
        message: '',
        icon: 'ℹ️',
        buttons: []
      },
      
      // Intervals
      timeInterval: null,
      dataInterval: null,
      
      // API base URL - sesuaikan dengan backend Anda
      apiBaseUrl: 'http://127.0.0.1:8000/api'
    }
  },
  
  mounted() {
    this.initializeComponent()
  },
  
  beforeUnmount() {
    // Cleanup intervals
    if (this.timeInterval) clearInterval(this.timeInterval)
    if (this.dataInterval) clearInterval(this.dataInterval)
  },
  
  methods: {
    // Initialization
    async initializeComponent() {
      this.updateTime()
      this.timeInterval = setInterval(this.updateTime, 1000)
      
      // Auto sync on component load
      await this.autoSyncOnLoad()
      
      // Auto refresh every 30 seconds
      this.dataInterval = setInterval(this.loadData, 30000)
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
    
    // Auto sync on component load
    async autoSyncOnLoad() {
      try {
        this.loading.data = true
        this.loadingMessage = '🚀 Auto sync sedang berjalan...'
        
        // Check throttling
        const lastSync = localStorage.getItem('lastAutoSync')
        const now = new Date().getTime()
        const fiveMinutesAgo = now - (5 * 60 * 1000)
        
        if (lastSync && parseInt(lastSync) > fiveMinutesAgo) {
          console.log('Auto sync skipped - recent sync detected')
          this.loadingMessage = '✅ Menggunakan data terbaru...'
          this.updateAutoSyncStatus(false, 0, 'Menggunakan cache (< 5 menit)')
          await this.loadData()
          return
        }

        // Step 1: Sync users
        const usersResponse = await fetch(`${this.apiBaseUrl}/attendance/sync/users`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const usersData = await usersResponse.json()
        
        if (!usersData.success) {
          throw new Error('Auto sync users failed: ' + usersData.message)
        }

        // Step 2: Link employees
        const linkResponse = await fetch(`${this.apiBaseUrl}/attendance/link-employees`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const linkData = await linkResponse.json()

        // Step 3: Sync attendance
        const attendanceResponse = await fetch(`${this.apiBaseUrl}/attendance/sync-today-only`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const attendanceData = await attendanceResponse.json()
        
        if (!attendanceData.success) {
          throw new Error('Auto sync attendance failed: ' + attendanceData.message)
        }

        // Save last sync time
        localStorage.setItem('lastAutoSync', now.toString())

        // Show success
        const linkedCount = linkData.success ? (linkData.data?.linked || 0) : 0
        this.loadingMessage = `✅ Auto sync berhasil! Users: ${usersData.total || 0}, Linked: ${linkedCount}, Attendance: Hari ini`
        this.updateAutoSyncStatus(true, usersData.total || 0)

        // Load final data
        setTimeout(() => {
          this.loadData()
        }, 1000)
        
      } catch (error) {
        console.error('Auto sync error:', error)
        this.loadingMessage = '⚠️ Auto sync gagal, menampilkan data tersimpan...'
        this.updateAutoSyncStatus(false, 0, 'Error: ' + error.message)
        
        setTimeout(() => {
          this.loadData()
        }, 1000)
      }
    },
    
    // Load attendance data
    async loadData() {
      try {
        this.loading.data = true
        const response = await fetch(`${this.apiBaseUrl}/attendance/today-realtime`)
        const data = await response.json()
        
        if (data.success) {
          this.summary = data.data.summary
          this.attendances = data.data.attendances
          this.updateLastUpdated()
        } else {
          throw new Error(data.message || 'Failed to load data')
        }
      } catch (error) {
        console.error('Error loading data:', error)
        this.showErrorPopup('Error Loading Data', error.message)
      } finally {
        this.loading.data = false
      }
    },
    
    // Manual refresh
    async refreshData() {
      this.loading.refresh = true
      
      try {
        // Step 1: Sync users
        const usersResponse = await fetch(`${this.apiBaseUrl}/attendance/sync/users`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const usersData = await usersResponse.json()
        
        if (!usersData.success) {
          throw new Error('Sync users failed: ' + usersData.message)
        }

        // Step 2: Link employees
        const linkResponse = await fetch(`${this.apiBaseUrl}/attendance/link-employees`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const linkData = await linkResponse.json()

        // Step 3: Sync attendance
        const attendanceResponse = await fetch(`${this.apiBaseUrl}/attendance/sync-today-only`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const attendanceData = await attendanceResponse.json()
        
        if (!attendanceData.success) {
          throw new Error('Sync attendance failed: ' + attendanceData.message)
        }

        // Show success popup
        const linkedCount = linkData.success ? (linkData.linked || 0) : 0
        this.showSuccessPopup(
          'Refresh Berhasil!',
          `👥 <strong>Users:</strong> ${usersData.total || 0} karyawan<br>` +
          `🔗 <strong>Linked:</strong> ${linkedCount} employee terhubung<br>` +
          `📊 <strong>Attendance:</strong> Data hari ini telah diproses<br><br>` +
          `Data akan direfresh...`
        )
        
        // Show sync statistics
        const stats = attendanceData.data.optimization_info || {}
        this.displaySyncStats('today', stats)
        
        setTimeout(() => {
          this.loadData()
        }, 1500)
        
      } catch (error) {
        console.error('Error refreshing:', error)
        this.showErrorPopup('Error Refresh', error.message)
      } finally {
        this.loading.refresh = false
      }
    },
    
    // Full sync confirmation
    showFullSyncConfirm() {
      this.showConfirmPopup(
        'PERHATIAN - FULL SYNC!',
        'Full sync akan:<br>' +
        '• Sync SEMUA data karyawan dari mesin<br>' +
        '• Sync SEMUA data attendance dari mesin<br>' +
        '• Memakan waktu 60-90 detik<br>' +
        '• Sebaiknya hanya dilakukan saat setup awal<br><br>' +
        '<strong>Untuk penggunaan harian, gunakan tombol "Refresh" saja.</strong><br><br>' +
        'Apakah Anda yakin ingin melanjutkan?',
        this.performFullSync
      )
    },
    
    // Perform full sync
    async performFullSync() {
      this.loading.fullSync = true
      
      try {
        // Step 1: Sync users
        const usersResponse = await fetch(`${this.apiBaseUrl}/attendance/sync/users`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const usersData = await usersResponse.json()
        
        if (!usersData.success) {
          throw new Error('Sync users failed: ' + usersData.message)
        }

        // Step 2: Link employees
        const linkResponse = await fetch(`${this.apiBaseUrl}/attendance/link-employees`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const linkData = await linkResponse.json()

        // Step 3: Sync all attendance data
        const attendanceResponse = await fetch(`${this.apiBaseUrl}/attendance/sync`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        })
        const attendanceData = await attendanceResponse.json()
        
        if (!attendanceData.success) {
          throw new Error('Sync attendance failed: ' + attendanceData.message)
        }

        // Show success popup
        const linkedCount = linkData.success ? (linkData.linked || 0) : 0
        this.showSuccessPopup(
          'Full Sync Berhasil!',
          `👥 <strong>Users:</strong> ${usersData.total || 0} karyawan<br>` +
          `🔗 <strong>Linked:</strong> ${linkedCount} employee terhubung<br>` +
          `📊 <strong>Attendance:</strong> Semua data telah diproses<br><br>` +
          `Data akan direfresh...`
        )
        
        // Show sync statistics
        this.displaySyncStats('full', attendanceData.data || {})
        
        setTimeout(() => {
          this.loadData()
        }, 1500)
        
      } catch (error) {
        console.error('Error full syncing:', error)
        this.showErrorPopup('Error Full Sync', error.message)
      } finally {
        this.loading.fullSync = false
      }
    },
    
    // Utility methods
    formatDate(dateString) {
      if (!dateString) return '-'
      return new Date(dateString).toLocaleDateString('id-ID')
    },
    
    formatTime(timeString) {
      if (!timeString) return '-'
      return new Date(timeString).toLocaleTimeString('id-ID')
    },
    
    getStatusText(status) {
      const statusMap = {
        'present_ontime': 'Hadir Tepat Waktu',
        'present_late': 'Hadir Terlambat',
        'absent': 'Tidak Hadir',
        'on_leave': 'Cuti',
        'sick_leave': 'Sakit',
        'permission': 'Izin'
      }
      return statusMap[status] || status
    },
    
    calculateWorkHours(attendance) {
      if (attendance.work_hours && attendance.work_hours > 0) {
        return attendance.work_hours + ' jam'
      }
      
      if (attendance.check_in && attendance.check_out) {
        const checkInTime = new Date(attendance.check_in)
        const checkOutTime = new Date(attendance.check_out)
        const diffMs = checkOutTime - checkInTime
        const diffHours = diffMs / (1000 * 60 * 60)
        
        const finalHours = diffHours > 4 ? diffHours - 1 : diffHours
        return finalHours > 0 ? finalHours.toFixed(2) + ' jam' : '0 jam'
      }
      
      return '-'
    },
    
    updateLastUpdated() {
      this.lastUpdated = new Date().toLocaleTimeString('id-ID')
    },
    
    // Auto sync status
    updateAutoSyncStatus(success, userCount = 0, message = '') {
      if (success) {
        this.autoSyncStatusStyle = { background: '#e8f5e8', color: '#2e7d32' }
        this.autoSyncStatusText = `✅ Auto sync berhasil - ${userCount} users (${new Date().toLocaleTimeString('id-ID')})`
      } else if (message.includes('cache')) {
        this.autoSyncStatusStyle = { background: '#fff3e0', color: '#f57c00' }
        this.autoSyncStatusText = `⚡ ${message} (${new Date().toLocaleTimeString('id-ID')})`
      } else {
        this.autoSyncStatusStyle = { background: '#ffebee', color: '#c62828' }
        this.autoSyncStatusText = `❌ ${message} (${new Date().toLocaleTimeString('id-ID')})`
      }
      
      this.showAutoSyncStatus = true
      
      // Auto hide after 15 seconds
      setTimeout(() => {
        this.showAutoSyncStatus = false
      }, 15000)
    },
    
    // Sync statistics
    displaySyncStats(type, data) {
      let statsHtml = ''
      
      if (type === 'today') {
        statsHtml = `
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; font-size: 14px;">
            <div><strong>📊 Total dari mesin:</strong> ${data.total_from_machine || 0}</div>
            <div><strong>📅 Filtered hari ini:</strong> ${data.filtered_today || 0}</div>
            <div><strong>✅ Processed:</strong> ${data.processed || 0}</div>
            <div><strong>⚡ Jenis:</strong> <span style="color: #28a745;">Sync Hari Ini</span></div>
          </div>
          <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <strong>Optimasi:</strong> ${data.message || 'Hanya data hari ini yang diproses - lebih cepat!'}
          </div>
        `
      } else if (type === 'full') {
        const pullResult = data.pull_result || {}
        const processResult = data.process_result || {}
        
        statsHtml = `
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; font-size: 14px;">
            <div><strong>📊 Data pulled:</strong> ${pullResult.data ? pullResult.data.length : 0}</div>
            <div><strong>✅ Processed:</strong> ${processResult.processed || 0}</div>
            <div><strong>🔄 Jenis:</strong> <span style="color: #ffc107;">Sync Semua Data</span></div>
            <div><strong>⏱️ Status:</strong> Selesai</div>
          </div>
          <div style="margin-top: 10px; font-size: 12px; color: #666;">
            <strong>Info:</strong> Semua data dari mesin telah diproses. Untuk sync rutin selanjutnya, gunakan "Refresh".
          </div>
        `
      }
      
      this.syncStatsContent = statsHtml
      this.showSyncStats = true
      
      // Auto hide after 10 seconds
      setTimeout(() => {
        this.showSyncStats = false
      }, 10000)
    },
    
    // Popup methods
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
    
    showSuccessPopup(title, message) {
      this.showPopup(title, message, '✅')
    },
    
    showErrorPopup(title, message) {
      this.showPopup(title, message, '❌')
    },
    
    showConfirmPopup(title, message, onConfirm) {
      const buttons = [
        { text: 'Ya', class: 'primary', action: 'confirm', handler: onConfirm },
        { text: 'Batal', class: 'secondary', action: 'close' }
      ]
      this.showPopup(title, message, '🚨', buttons)
    }
  }
}
</script>

<style scoped>
.attendance-dashboard {
  font-family: Arial, sans-serif;
  margin: 0;
  padding: 20px;
  background-color: #f5f5f5;
  max-width: 1200px;
  margin: 0 auto;
  background: white;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.header {
  text-align: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid #007bff;
}

.header h1 {
  color: #007bff;
  margin: 0;
}

.current-time {
  font-size: 18px;
  color: #666;
  margin-top: 10px;
}

.tips {
  margin-top: 15px;
  font-size: 14px;
  color: #666;
}

.summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.summary-card {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  padding: 20px;
  border-radius: 10px;
  text-align: center;
}

.summary-card.success {
  background: linear-gradient(135deg, #28a745, #1e7e34);
}

.summary-card.warning {
  background: linear-gradient(135deg, #ffc107, #e0a800);
}

.summary-card h3 {
  margin: 0 0 10px 0;
  font-size: 24px;
}

.summary-card p {
  margin: 0;
  font-size: 14px;
  opacity: 0.9;
}

.loading-message {
  color: #007bff;
  text-align: center;
  padding: 20px;
  font-size: 16px;
}

.controls {
  margin-bottom: 20px;
  text-align: center;
}

.btn {
  background: #007bff;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 16px;
  margin: 0 10px;
  transition: background 0.3s;
}

.btn:hover:not(:disabled) {
  background: #0056b3;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn.success {
  background: #28a745;
}

.btn.success:hover:not(:disabled) {
  background: #1e7e34;
}

.btn.warning {
  background: #ffc107;
  color: #333;
}

.btn.warning:hover:not(:disabled) {
  background: #e0a800;
}

.sync-info {
  text-align: center;
  margin-top: 10px;
  font-size: 12px;
  color: #888;
  margin-bottom: 20px;
}

.info-item {
  margin-right: 20px;
}

.attendance-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

.attendance-table th,
.attendance-table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.attendance-table th {
  background-color: #007bff;
  color: white;
  font-weight: bold;
}

.attendance-table tr:hover {
  background-color: #f8f9fa;
}

.status {
  padding: 5px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: bold;
  color: white;
}

.status.present_ontime {
  background: #28a745;
}

.status.present_late {
  background: #ffc107;
  color: #333;
}

.status.absent {
  background: #dc3545;
}

.loading {
  text-align: center;
  padding: 50px;
  color: #666;
}

.last-updated {
  text-align: center;
  color: #666;
  font-size: 14px;
  margin-top: 20px;
}

.auto-sync-status {
  text-align: center;
  margin-top: 10px;
  padding: 8px;
  border-radius: 5px;
  font-size: 12px;
}

.sync-stats {
  margin-bottom: 20px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 4px solid #28a745;
}

.sync-stats h4 {
  margin: 0 0 10px 0;
  color: #28a745;
}

.footer-info {
  margin-top: 30px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
  text-align: center;
  border-top: 3px solid #007bff;
}

.footer-info h4 {
  margin: 0 0 10px 0;
  color: #007bff;
}

.footer-info p {
  margin: 5px 0;
  font-size: 14px;
  color: #666;
}

@media (max-width: 768px) {
  .controls .btn {
    display: block;
    margin: 5px auto;
    width: 200px;
  }
  
  .info-item {
    display: block;
    margin: 5px 0;
  }
}
</style> 