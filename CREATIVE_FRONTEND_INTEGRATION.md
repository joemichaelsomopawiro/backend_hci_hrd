# Creative Dashboard - Frontend Integration Guide

## 📋 Daftar Isi
1. [Endpoint API](#endpoint-api)
2. [Struktur Data CreativeWork](#struktur-data-creativework)
3. [Field Mapping untuk Display](#field-mapping-untuk-display)
4. [Console Log untuk Debugging](#console-log-untuk-debugging)
5. [Contoh Implementasi](#contoh-implementasi)

---

## 🔌 Endpoint API

### Base URL
```
/api/live-tv/roles/creative
```

### 1. Get All Creative Works
**GET** `/works`

**Query Parameters:**
- `status` (optional): Filter by status (`draft`, `in_progress`, `submitted`, `approved`, `rejected`)
- `episode_id` (optional): Filter by episode
- `created_by` (optional): Filter by creator
- `ready_for_work` (optional): `true` untuk hanya yang status `draft`

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "episode_id": 2010,
        "script_content": "...",
        "storyboard_data": {...},
        "budget_data": {...},
        "recording_schedule": "2025-12-20 10:00:00",
        "shooting_schedule": "2025-12-25 08:00:00",
        "shooting_location": "Studio A",
        "status": "draft",
        "created_by": 3,
        "reviewed_by": null,
        "reviewed_at": null,
        "review_notes": null,
        "rejection_reason": null,
        "episode": {
          "id": 2010,
          "episode_number": 1,
          "title": "Episode 1",
          "description": "...",
          "program": {
            "id": 101,
            "name": "Program Live Musik Unai",
            "description": "..."
          }
        },
        "createdBy": {
          "id": 3,
          "name": "Creative User",
          "email": "creative@example.com"
        }
      }
    ],
    "current_page": 1,
    "total": 1
  },
  "message": "Creative works retrieved successfully"
}
```

### 2. Get Creative Work by ID
**GET** `/works/{id}`

**Response:** Sama seperti di atas, tapi single object

### 3. Create Creative Work
**POST** `/works`

**Request Body:**
```json
{
  "episode_id": 2010,
  "script_content": "...",
  "storyboard_data": {...},
  "budget_data": {...},
  "recording_schedule": "2025-12-20 10:00:00",
  "shooting_schedule": "2025-12-25 08:00:00",
  "shooting_location": "Studio A"
}
```

### 4. Update Creative Work
**PUT** `/works/{id}`

**Request Body:**
```json
{
  "script_content": "...",
  "storyboard_data": {...},
  "budget_data": {...},
  "recording_schedule": "2025-12-20 10:00:00",
  "shooting_schedule": "2025-12-25 08:00:00",
  "shooting_location": "Studio A"
}
```

### 5. Accept Work (Terima Pekerjaan)
**POST** `/works/{id}/accept-work`

**Response:**
```json
{
  "success": true,
  "data": {...},
  "message": "Work accepted successfully. You can now start working on script, storyboard, schedules, and budget."
}
```

### 6. Complete Work (Selesaikan Pekerjaan)
**POST** `/works/{id}/complete-work`

**Request Body:**
```json
{
  "script_content": "Script cerita video klip...",
  "storyboard_data": {
    "scenes": [...]
  },
  "recording_schedule": "2025-12-20 10:00:00",
  "shooting_schedule": "2025-12-25 08:00:00",
  "shooting_location": "Studio A",
  "budget_data": [
    {
      "category": "talent",
      "description": "Bayar talent untuk video klip",
      "amount": 5000000,
      "currency": "IDR"
    }
  ],
  "completion_notes": "Semua sudah selesai"
}
```

### 7. Submit Creative Work
**POST** `/works/{id}/submit`

### 8. Revise Creative Work
**PUT** `/works/{id}/revise`

### 9. Resubmit Creative Work
**POST** `/works/{id}/resubmit`

---

## 📊 Struktur Data CreativeWork

### Field Utama
```javascript
{
  id: number,
  episode_id: number,
  script_content: string | null,        // Script cerita video klip
  storyboard_data: object | null,        // Storyboard (JSON)
  budget_data: array | null,             // Budget data (array of objects)
  recording_schedule: string | null,      // Jadwal rekaman suara (datetime)
  shooting_schedule: string | null,      // Jadwal syuting (datetime)
  shooting_location: string | null,       // Lokasi syuting
  status: string,                         // draft, in_progress, submitted, approved, rejected
  created_by: number,
  reviewed_by: number | null,
  reviewed_at: string | null,
  review_notes: string | null,
  rejection_reason: string | null,
  
  // Relationships
  episode: {
    id: number,
    episode_number: number,
    title: string,
    description: string | null,
    program: {
      id: number,
      name: string,
      description: string | null
    }
  },
  createdBy: {
    id: number,
    name: string,
    email: string
  },
  reviewedBy: {
    id: number,
    name: string,
    email: string
  } | null
}
```

### Status Values
- `draft`: Draft, belum diterima
- `in_progress`: Sedang dikerjakan
- `submitted`: Sudah disubmit ke Producer
- `approved`: Disetujui oleh Producer
- `rejected`: Ditolak oleh Producer

---

## 🎨 Field Mapping untuk Display

### Untuk Menampilkan Data di Frontend

```javascript
// 1. Episode Title
const episodeTitle = work.episode?.title || `Episode ${work.episode?.episode_number || 'N/A'}`;

// 2. Program Name
const programName = work.episode?.program?.name || 'Untitled Program';

// 3. Music Arrangement (dari episode)
// Note: CreativeWork tidak langsung punya music_arrangement_id
// Tapi bisa diambil dari episode.musicArrangements
const musicArrangement = work.episode?.musicArrangements?.[0];
const songTitle = musicArrangement?.song_title || '-';

// 4. Status Badge
const statusBadge = {
  draft: { label: 'Draft', color: 'gray' },
  in_progress: { label: 'In Progress', color: 'blue' },
  submitted: { label: 'Submitted', color: 'yellow' },
  approved: { label: 'Approved', color: 'green' },
  rejected: { label: 'Rejected', color: 'red' }
}[work.status] || { label: 'Unknown', color: 'gray' };

// 5. Recording Schedule
const recordingSchedule = work.recording_schedule 
  ? new Date(work.recording_schedule).toLocaleString('id-ID')
  : '-';

// 6. Shooting Schedule
const shootingSchedule = work.shooting_schedule
  ? new Date(work.shooting_schedule).toLocaleString('id-ID')
  : '-';

// 7. Shooting Location
const shootingLocation = work.shooting_location || '-';

// 8. Total Budget
const totalBudget = work.budget_data?.reduce((sum, item) => sum + (item.amount || 0), 0) || 0;
const formattedBudget = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR'
}).format(totalBudget);

// 9. Script Preview
const scriptPreview = work.script_content 
  ? work.script_content.substring(0, 100) + '...'
  : 'Belum ada script';
```

---

## 🐛 Console Log untuk Debugging

### Template Console Log untuk Creative Dashboard

```javascript
// 1. Log saat fetch creative works
async function loadCreativeWorks() {
  console.log('🔄 [Creative] Fetching creative works...');
  
  try {
    const response = await api.get('/live-tv/roles/creative/works');
    
    console.log('✅ [Creative] Fetch response:', {
      success: response.data.success,
      hasData: !!response.data.data,
      hasDataData: !!response.data.data?.data,
      total: response.data.data?.total || 0,
      currentPage: response.data.data?.current_page || 0,
      worksCount: response.data.data?.data?.length || 0
    });
    
    if (response.data.success && response.data.data?.data) {
      const works = response.data.data.data;
      
      console.log('📊 [Creative] Works breakdown:', {
        total: works.length,
        byStatus: {
          draft: works.filter(w => w.status === 'draft').length,
          in_progress: works.filter(w => w.status === 'in_progress').length,
          submitted: works.filter(w => w.status === 'submitted').length,
          approved: works.filter(w => w.status === 'approved').length,
          rejected: works.filter(w => w.status === 'rejected').length
        }
      });
      
      // Log detail setiap work
      works.forEach((work, index) => {
        console.log(`📝 [Creative] Work #${index + 1}:`, {
          id: work.id,
          status: work.status,
          episode_id: work.episode_id,
          episode_number: work.episode?.episode_number || 'N/A',
          episode_title: work.episode?.title || 'Untitled',
          program_name: work.episode?.program?.name || 'Untitled Program',
          has_script: !!work.script_content,
          has_storyboard: !!work.storyboard_data,
          has_budget: !!work.budget_data,
          recording_schedule: work.recording_schedule || 'Not set',
          shooting_schedule: work.shooting_schedule || 'Not set',
          shooting_location: work.shooting_location || 'Not set'
        });
      });
    } else {
      console.warn('⚠️ [Creative] No works found or invalid response structure');
    }
    
    return response.data;
  } catch (error) {
    console.error('❌ [Creative] Error fetching works:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}

// 2. Log saat fetch single work
async function loadCreativeWork(id) {
  console.log(`🔄 [Creative] Fetching creative work #${id}...`);
  
  try {
    const response = await api.get(`/live-tv/roles/creative/works/${id}`);
    
    console.log('✅ [Creative] Work detail:', {
      success: response.data.success,
      hasData: !!response.data.data,
      work: response.data.data ? {
        id: response.data.data.id,
        status: response.data.data.status,
        episode: {
          id: response.data.data.episode_id,
          number: response.data.data.episode?.episode_number,
          title: response.data.data.episode?.title,
          program: response.data.data.episode?.program?.name
        },
        has_script: !!response.data.data.script_content,
        has_storyboard: !!response.data.data.storyboard_data,
        has_budget: !!response.data.data.budget_data,
        schedules: {
          recording: response.data.data.recording_schedule,
          shooting: response.data.data.shooting_schedule,
          location: response.data.data.shooting_location
        }
      } : null
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Creative] Error fetching work #${id}:`, {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}

// 3. Log saat accept work
async function acceptWork(id) {
  console.log(`🔄 [Creative] Accepting work #${id}...`);
  
  try {
    const response = await api.post(`/live-tv/roles/creative/works/${id}/accept-work`);
    
    console.log('✅ [Creative] Work accepted:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      message: response.data.message
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Creative] Error accepting work #${id}:`, {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}

// 4. Log saat complete work
async function completeWork(id, data) {
  console.log(`🔄 [Creative] Completing work #${id}...`, {
    has_script: !!data.script_content,
    has_storyboard: !!data.storyboard_data,
    has_budget: !!data.budget_data,
    recording_schedule: data.recording_schedule,
    shooting_schedule: data.shooting_schedule,
    shooting_location: data.shooting_location
  });
  
  try {
    const response = await api.post(`/live-tv/roles/creative/works/${id}/complete-work`, data);
    
    console.log('✅ [Creative] Work completed:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      message: response.data.message
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Creative] Error completing work #${id}:`, {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status,
      errors: error.response?.data?.errors
    });
    throw error;
  }
}

// 5. Log saat update work
async function updateWork(id, data) {
  console.log(`🔄 [Creative] Updating work #${id}...`, {
    fields: Object.keys(data),
    has_script: !!data.script_content,
    has_storyboard: !!data.storyboard_data,
    has_budget: !!data.budget_data
  });
  
  try {
    const response = await api.put(`/live-tv/roles/creative/works/${id}`, data);
    
    console.log('✅ [Creative] Work updated:', {
      success: response.data.success,
      message: response.data.message
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Creative] Error updating work #${id}:`, {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status,
      errors: error.response?.data?.errors
    });
    throw error;
  }
}
```

---

## 💡 Contoh Implementasi

### Vue 3 Composition API

```vue
<template>
  <div class="creative-dashboard">
    <h1>Creative Dashboard</h1>
    
    <!-- Loading State -->
    <div v-if="loading">Loading...</div>
    
    <!-- Error State -->
    <div v-if="error" class="error">{{ error }}</div>
    
    <!-- Works List -->
    <div v-if="works.length > 0">
      <div v-for="work in works" :key="work.id" class="work-card">
        <h3>{{ getWorkTitle(work) }}</h3>
        <p><strong>Status:</strong> {{ work.status }}</p>
        <p><strong>Episode:</strong> {{ getEpisodeTitle(work) }}</p>
        <p><strong>Program:</strong> {{ getProgramName(work) }}</p>
        <p><strong>Song:</strong> {{ getSongTitle(work) }}</p>
        <p><strong>Recording:</strong> {{ formatDate(work.recording_schedule) }}</p>
        <p><strong>Shooting:</strong> {{ formatDate(work.shooting_schedule) }}</p>
        <p><strong>Location:</strong> {{ work.shooting_location || '-' }}</p>
        <p><strong>Budget:</strong> {{ formatBudget(work.budget_data) }}</p>
        
        <button 
          v-if="work.status === 'draft'" 
          @click="acceptWork(work.id)"
        >
          Terima Pekerjaan
        </button>
        
        <button 
          v-if="work.status === 'in_progress'" 
          @click="openCompleteModal(work)"
        >
          Selesaikan Pekerjaan
        </button>
      </div>
    </div>
    
    <div v-else>No works found</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const works = ref([])
const loading = ref(false)
const error = ref(null)
const showCompleteWorkModalVisible = ref(false) // Fix untuk error Vue

// Load works
const loadWorks = async () => {
  loading.value = true
  error.value = null
  
  console.log('🔄 [Creative] Fetching creative works...')
  
  try {
    const response = await api.get('/live-tv/roles/creative/works')
    
    console.log('✅ [Creative] Response:', {
      success: response.data.success,
      hasData: !!response.data.data,
      hasDataData: !!response.data.data?.data,
      total: response.data.data?.total || 0,
      worksCount: response.data.data?.data?.length || 0
    })
    
    if (response.data.success && response.data.data?.data) {
      works.value = response.data.data.data
      
      console.log('📊 [Creative] Works loaded:', {
        total: works.value.length,
        byStatus: {
          draft: works.value.filter(w => w.status === 'draft').length,
          in_progress: works.value.filter(w => w.status === 'in_progress').length,
          submitted: works.value.filter(w => w.status === 'submitted').length,
          approved: works.value.filter(w => w.status === 'approved').length,
          rejected: works.value.filter(w => w.status === 'rejected').length
        }
      })
      
      // Log detail setiap work
      works.value.forEach((work, index) => {
        console.log(`📝 [Creative] Work #${index + 1}:`, {
          id: work.id,
          status: work.status,
          episode_id: work.episode_id,
          episode_number: work.episode?.episode_number || 'N/A',
          episode_title: work.episode?.title || 'Untitled',
          program_name: work.episode?.program?.name || 'Untitled Program',
          has_script: !!work.script_content,
          has_storyboard: !!work.storyboard_data,
          has_budget: !!work.budget_data
        })
      })
    } else {
      console.warn('⚠️ [Creative] No works found')
      works.value = []
    }
  } catch (err) {
    console.error('❌ [Creative] Error:', {
      message: err.message,
      response: err.response?.data,
      status: err.response?.status
    })
    error.value = err.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

// Helper functions
const getWorkTitle = (work) => {
  return work.episode?.title || `Episode ${work.episode?.episode_number || 'N/A'}`
}

const getEpisodeTitle = (work) => {
  return work.episode?.title || `Episode ${work.episode?.episode_number || 'N/A'}`
}

const getProgramName = (work) => {
  return work.episode?.program?.name || 'Untitled Program'
}

const getSongTitle = (work) => {
  // Music arrangement sekarang sudah include di response
  const arrangement = work.episode?.musicArrangements?.[0]
  return arrangement?.song_title || '-'
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('id-ID')
}

const formatBudget = (budgetData) => {
  if (!budgetData || !Array.isArray(budgetData)) return 'Rp 0'
  const total = budgetData.reduce((sum, item) => sum + (item.amount || 0), 0)
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(total)
}

// Accept work
const acceptWork = async (id) => {
  console.log(`🔄 [Creative] Accepting work #${id}...`)
  
  try {
    const response = await api.post(`/live-tv/roles/creative/works/${id}/accept-work`)
    
    console.log('✅ [Creative] Work accepted:', {
      success: response.data.success,
      newStatus: response.data.data?.status
    })
    
    await loadWorks() // Reload
  } catch (err) {
    console.error(`❌ [Creative] Error accepting work:`, err)
    alert(err.response?.data?.message || 'Error accepting work')
  }
}

// Complete work
const openCompleteModal = (work) => {
  showCompleteWorkModalVisible.value = true
  // Set current work untuk modal
}

onMounted(() => {
  loadWorks()
})
</script>
```

---

## 🔍 Troubleshooting "Untitled" Issue

### Masalah: Episode/Program menampilkan "Untitled"

**Penyebab:**
1. Episode tidak punya `title`
2. Program tidak punya `name`
3. Relationship tidak ter-load dengan benar

**Solusi:**

```javascript
// 1. Pastikan eager loading di backend sudah benar
// Backend sudah include: episode.program

// 2. Di frontend, gunakan fallback
const episodeTitle = work.episode?.title || `Episode ${work.episode?.episode_number || 'N/A'}`
const programName = work.episode?.program?.name || 'Untitled Program'

// 3. Cek apakah data ter-load dengan console log
console.log('Episode data:', {
  episode_id: work.episode_id,
  episode: work.episode,
  episode_title: work.episode?.title,
  episode_number: work.episode?.episode_number,
  program: work.episode?.program,
  program_name: work.episode?.program?.name
})

// 4. Jika episode atau program null, fetch terpisah
if (!work.episode) {
  // Fetch episode detail
  const episodeResponse = await api.get(`/live-tv/episodes/${work.episode_id}`)
  work.episode = episodeResponse.data.data
}
```

---

## 📝 Checklist Integrasi

- [ ] Setup API base URL
- [ ] Implement loadCreativeWorks() dengan console log
- [ ] Implement loadCreativeWork(id) dengan console log
- [ ] Implement acceptWork(id) dengan console log
- [ ] Implement updateWork(id, data) dengan console log
- [ ] Implement completeWork(id, data) dengan console log
- [ ] Handle semua status (draft, in_progress, submitted, approved, rejected)
- [ ] Display episode title dengan fallback
- [ ] Display program name dengan fallback
- [ ] Format dates dengan benar
- [ ] Format budget dengan benar
- [ ] Handle error states
- [ ] Handle loading states
- [ ] Fix property `showCompleteWorkModalVisible` di Vue component

---

## 🎯 Quick Reference

### Status Flow
```
draft → acceptWork() → in_progress → completeWork() → submitted → (Producer approve/reject)
```

### Required Fields untuk Complete Work
- `script_content` (required)
- `storyboard_data` (required, array)
- `recording_schedule` (required, datetime)
- `shooting_schedule` (required, datetime)
- `shooting_location` (required, string)
- `budget_data` (required, array)

### Budget Data Structure
```javascript
[
  {
    category: "talent",
    description: "Bayar talent untuk video klip",
    amount: 5000000,
    currency: "IDR"
  },
  {
    category: "production",
    description: "Equipment rental",
    amount: 2000000,
    currency: "IDR"
  }
]
```

---

**Last Updated:** 2025-12-17

