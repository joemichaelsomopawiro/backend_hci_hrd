// =======================
// main.js - Vue.js App Entry Point
// =======================

import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { createPinia } from 'pinia'
import App from './App.vue'

// Import components
import AttendanceDashboard from '@/components/AttendanceDashboard.vue'

// Create router
const routes = [
  {
    path: '/',
    name: 'Home',
    redirect: '/attendance'
  },
  {
    path: '/attendance',
    name: 'Attendance',
    component: AttendanceDashboard,
    meta: {
      title: 'Attendance Dashboard',
      requiresAuth: false // Set true jika butuh authentication
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Global navigation guard (optional)
router.beforeEach((to, from, next) => {
  // Set page title
  document.title = to.meta.title || 'Attendance System'
  
  // Add authentication check if needed
  // if (to.meta.requiresAuth && !isAuthenticated()) {
  //   next('/login')
  // } else {
  //   next()
  // }
  
  next()
})

// Create app
const app = createApp(App)

// Install plugins
app.use(router)
app.use(createPinia())

// Global properties (optional)
app.config.globalProperties.$apiBaseUrl = process.env.VUE_APP_API_BASE_URL || 'http://127.0.0.1:8000/api'

// Mount app
app.mount('#app')

// =======================
// App.vue - Root Component
// =======================

/* 
<template>
  <div id="app">
    <nav class="navbar" v-if="showNavigation">
      <div class="nav-brand">
        <h2>🏢 Attendance System</h2>
      </div>
      <div class="nav-links">
        <router-link to="/attendance" class="nav-link">
          📊 Dashboard
        </router-link>
        <!-- Add more navigation links here -->
      </div>
    </nav>
    
    <main class="main-content">
      <router-view />
    </main>
    
    <footer class="footer" v-if="showFooter">
      <p>&copy; 2024 Attendance System. All rights reserved.</p>
    </footer>
  </div>
</template>

<script>
export default {
  name: 'App',
  data() {
    return {
      showNavigation: true,
      showFooter: true
    }
  }
}
</script>

<style>
/* Global styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f5f5f5;
}

#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.navbar {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.nav-brand h2 {
  margin: 0;
}

.nav-links {
  display: flex;
  gap: 1rem;
}

.nav-link {
  color: white;
  text-decoration: none;
  padding: 0.5rem 1rem;
  border-radius: 5px;
  transition: background 0.3s;
}

.nav-link:hover,
.nav-link.router-link-active {
  background: rgba(255, 255, 255, 0.2);
}

.main-content {
  flex: 1;
  padding: 2rem;
}

.footer {
  background: #333;
  color: white;
  text-align: center;
  padding: 1rem;
  margin-top: auto;
}

@media (max-width: 768px) {
  .navbar {
    flex-direction: column;
    gap: 1rem;
  }
  
  .main-content {
    padding: 1rem;
  }
}
</style>
*/

// =======================
// .env - Environment Variables
// =======================

/*
# Vue.js Environment Variables
VUE_APP_API_BASE_URL=http://127.0.0.1:8000/api
VUE_APP_APP_NAME=Attendance System
VUE_APP_VERSION=1.0.0

# Development
NODE_ENV=development
VUE_APP_DEBUG=true

# Production (untuk production build)
# NODE_ENV=production
# VUE_APP_API_BASE_URL=https://yourdomain.com/api
# VUE_APP_DEBUG=false
*/

// =======================
// vite.config.js - Vite Configuration
// =======================

/*
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false
      }
    }
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    sourcemap: false
  }
})
*/

// =======================
// Pinia Store Example (Optional)
// =======================

/*
// stores/attendance.js
import { defineStore } from 'pinia'

export const useAttendanceStore = defineStore('attendance', {
  state: () => ({
    attendances: [],
    summary: {
      total_users: 0,
      present_ontime: 0,
      present_late: 0
    },
    loading: false,
    lastSync: null,
    apiBaseUrl: process.env.VUE_APP_API_BASE_URL || 'http://127.0.0.1:8000/api'
  }),
  
  getters: {
    totalAttendances: (state) => state.attendances.length,
    presentToday: (state) => state.attendances.filter(att => 
      att.status === 'present_ontime' || att.status === 'present_late'
    ).length,
    isReccentlySync: (state) => {
      if (!state.lastSync) return false
      const fiveMinutesAgo = new Date().getTime() - (5 * 60 * 1000)
      return new Date(state.lastSync).getTime() > fiveMinutesAgo
    }
  },
  
  actions: {
    async fetchAttendances() {
      this.loading = true
      try {
        const response = await fetch(`${this.apiBaseUrl}/attendance/today-realtime`)
        const data = await response.json()
        
        if (data.success) {
          this.attendances = data.data.attendances
          this.summary = data.data.summary
        }
      } catch (error) {
        console.error('Error fetching attendances:', error)
      } finally {
        this.loading = false
      }
    },
    
    async syncData() {
      // Sync logic here
      this.lastSync = new Date()
    }
  }
})

// Usage in component:
// import { useAttendanceStore } from '@/stores/attendance'
// const attendanceStore = useAttendanceStore()
*/

// =======================
// TypeScript Support (Optional)
// =======================

/*
// types/attendance.ts
export interface Attendance {
  id: number
  user_pin: string
  user_name: string
  date: string
  check_in: string | null
  check_out: string | null
  status: 'present_ontime' | 'present_late' | 'absent' | 'on_leave'
  work_hours: number
}

export interface AttendanceSummary {
  total_users: number
  present_ontime: number
  present_late: number
}

export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

// Usage:
// import type { Attendance, AttendanceSummary } from '@/types/attendance'
*/

// =======================
// Axios Setup (Alternative to Fetch)
// =======================

/*
// services/api.js
import axios from 'axios'

const apiClient = axios.create({
  baseURL: process.env.VUE_APP_API_BASE_URL || 'http://127.0.0.1:8000/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
})

// Request interceptor
apiClient.interceptors.request.use(
  config => {
    // Add auth token if available
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  error => Promise.reject(error)
)

// Response interceptor
apiClient.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      // Handle unauthorized
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default apiClient

// Usage in component:
// import apiClient from '@/services/api'
// const response = await apiClient.get('/attendance/today-realtime')
*/

export default {
  // This file serves as documentation and examples
  // Copy the relevant parts to your Vue.js project
} 