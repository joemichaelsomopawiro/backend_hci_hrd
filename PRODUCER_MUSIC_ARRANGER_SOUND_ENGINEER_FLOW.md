# Producer - Music Arranger - Sound Engineer Flow & API Documentation

## 📋 Daftar Isi
1. [Flow Overview](#flow-overview)
2. [Producer API](#producer-api)
3. [Music Arranger API](#music-arranger-api)
4. [Sound Engineer API](#sound-engineer-api)
5. [Frontend Integration dengan Console Log](#frontend-integration-dengan-console-log)

---

## 🔄 Flow Overview

```
1. Music Arranger
   ↓ Upload file arrangement (status: song_approved)
   ↓ Auto-submit → status: arrangement_submitted
   ↓ Notifikasi ke Producer ✅
   
2. Producer
   ↓ GET /api/live-tv/producer/approvals
   ↓ Melihat arrangement di music_arrangements ✅
   ↓ POST /api/live-tv/producer/approvals/{id}/approve
   ↓ Status: arrangement_submitted → arrangement_approved ✅
   ↓ Auto-create Creative Work ✅
   
   ATAU
   
   ↓ POST /api/live-tv/producer/approvals/{id}/reject
   ↓ Status: arrangement_submitted → arrangement_rejected ✅
   ↓ needs_sound_engineer_help: true ✅
   ↓ Notifikasi ke Music Arranger ✅
   ↓ Notifikasi ke Sound Engineer ✅
   
3. Music Arranger (setelah reject)
   ↓ GET /api/live-tv/roles/music-arranger/arrangements?status=arrangement_rejected
   ↓ Melihat arrangement yang di-reject ✅
   ↓ PUT /api/live-tv/roles/music-arranger/arrangements/{id}
   ↓ Upload file baru → status: arrangement_in_progress
   ↓ Auto-submit → status: arrangement_submitted ✅
   
4. Sound Engineer (setelah reject)
   ↓ GET /api/live-tv/roles/sound-engineer/rejected-arrangements
   ↓ Melihat arrangement yang di-reject ✅
   ↓ POST /api/live-tv/roles/sound-engineer/arrangements/{id}/help-fix
   ↓ Upload file baru → status: arrangement_submitted ✅
```

---

## 🎬 Producer API

### Base URL
```
/api/live-tv/producer
```

### 1. Get Pending Approvals (Arrangement dari Music Arranger)
**GET** `/approvals` atau `/pending-approvals`

**Headers:**
```
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "song_proposals": [...],
    "music_arrangements": [
      {
        "id": 32,
        "episode_id": 2010,
        "song_title": "1 live musik unai",
        "singer_name": "live musik unai",
        "file_path": "music-arrangements/xxx.mp3",
        "file_name": "song.mp3",
        "file_size": 3664989,
        "mime_type": "audio/mpeg",
        "status": "arrangement_submitted",
        "submitted_at": "2025-12-17T07:54:35.000000Z",
        "file_url": "http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/32/file?expires=...&signature=...",
        "created_by": 1,
        "createdBy": {
          "id": 1,
          "name": "Music Arranger Test",
          "email": "musicarranger@example.com"
        },
        "episode": {
          "id": 2010,
          "episode_number": 1,
          "title": "Episode 1",
          "program": {
            "id": 101,
            "name": "Program Live Musik Unai"
          }
        }
      }
    ],
    "creative_works": [...],
    "equipment_requests": [...],
    "budget_requests": [...],
    "sound_engineer_recordings": [...],
    "sound_engineer_editing": [...],
    "editor_works": [...]
  },
  "message": "Pending approvals retrieved successfully"
}
```

**Console Log untuk Frontend:**
```javascript
async function loadProducerApprovals() {
  console.log('🔄 [Producer] Fetching pending approvals...');
  
  try {
    const response = await api.get('/live-tv/producer/pending-approvals');
    
    console.log('✅ [Producer] Approvals response:', {
      success: response.data.success,
      hasData: !!response.data.data,
      hasMusicArrangements: !!response.data.data?.music_arrangements,
      musicArrangementsCount: response.data.data?.music_arrangements?.length || 0,
      songProposalsCount: response.data.data?.song_proposals?.length || 0
    });
    
    if (response.data.success && response.data.data?.music_arrangements) {
      const arrangements = response.data.data.music_arrangements;
      
      console.log('📊 [Producer] Music Arrangements:', {
        total: arrangements.length,
        byStatus: {
          submitted: arrangements.filter(a => a.status === 'arrangement_submitted').length,
          in_progress: arrangements.filter(a => a.status === 'arrangement_in_progress').length
        }
      });
      
      arrangements.forEach((arr, index) => {
        console.log(`🎵 [Producer] Arrangement #${index + 1}:`, {
          id: arr.id,
          song_title: arr.song_title,
          singer_name: arr.singer_name,
          status: arr.status,
          episode_number: arr.episode?.episode_number,
          episode_title: arr.episode?.title,
          program_name: arr.episode?.program?.name,
          created_by: arr.createdBy?.name,
          has_file: !!arr.file_path,
          file_url: arr.file_url,
          submitted_at: arr.submitted_at
        });
      });
    } else {
      console.warn('⚠️ [Producer] No music arrangements found');
    }
    
    return response.data;
  } catch (error) {
    console.error('❌ [Producer] Error fetching approvals:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}
```

### 2. Approve Arrangement
**POST** `/approvals/{id}/approve`

**Request Body:**
```json
{
  "type": "music_arrangement",
  "notes": "Arrangement sudah bagus, bisa lanjut ke creative work"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 32,
    "status": "arrangement_approved",
    "reviewed_at": "2025-12-17T19:00:00.000000Z",
    "review_notes": "Arrangement sudah bagus, bisa lanjut ke creative work"
  },
  "message": "Arrangement approved successfully"
}
```

**Console Log:**
```javascript
async function approveArrangement(id, notes) {
  console.log(`🔄 [Producer] Approving arrangement #${id}...`, { notes });
  
  try {
    const response = await api.post(`/live-tv/producer/approvals/${id}/approve`, {
      type: 'music_arrangement',
      notes: notes
    });
    
    console.log('✅ [Producer] Arrangement approved:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      message: response.data.message
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Producer] Error approving arrangement #${id}:`, {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}
```

### 3. Reject Arrangement
**POST** `/approvals/{id}/reject`

**Request Body:**
```json
{
  "type": "music_arrangement",
  "reason": "Volume terlalu rendah, perlu dinaikkan"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 32,
    "status": "arrangement_rejected",
    "reviewed_at": "2025-12-17T19:00:00.000000Z",
    "rejection_reason": "Volume terlalu rendah, perlu dinaikkan",
    "needs_sound_engineer_help": true
  },
  "message": "Arrangement rejected successfully"
}
```

**Console Log:**
```javascript
async function rejectArrangement(id, reason) {
  console.log(`🔄 [Producer] Rejecting arrangement #${id}...`, { reason });
  
  try {
    const response = await api.post(`/live-tv/producer/approvals/${id}/reject`, {
      type: 'music_arrangement',
      reason: reason
    });
    
    console.log('✅ [Producer] Arrangement rejected:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      rejection_reason: response.data.data?.rejection_reason,
      needs_sound_engineer_help: response.data.data?.needs_sound_engineer_help,
      message: response.data.message
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Producer] Error rejecting arrangement #${id}:`, {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}
```

### 4. Get Rejected Arrangements History
**GET** `/rejected-arrangements`

**Query Parameters:**
- `episode_id` (optional)
- `date_from` (optional)
- `date_to` (optional)

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 32,
        "status": "arrangement_rejected",
        "song_title": "...",
        "rejection_reason": "...",
        "reviewed_at": "...",
        "episode": {...},
        "createdBy": {...},
        "reviewedBy": {...}
      }
    ],
    "current_page": 1,
    "total": 1
  },
  "message": "Rejected arrangements history retrieved successfully"
}
```

---

## 🎵 Music Arranger API

### Base URL
```
/api/live-tv/roles/music-arranger
```

### 1. Get All Arrangements (Termasuk yang Rejected)
**GET** `/arrangements`

**Query Parameters:**
- `status` (optional): Filter by status (`arrangement_rejected`, `rejected`, dll)
- `episode_id` (optional)
- `ready_for_arrangement` (optional): `true` untuk hanya yang status `song_approved` atau `song_rejected`

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 32,
        "episode_id": 2010,
        "song_title": "1 live musik unai",
        "status": "arrangement_rejected",
        "rejection_reason": "Volume terlalu rendah, perlu dinaikkan",
        "reviewed_at": "2025-12-17T19:00:00.000000Z",
        "file_path": "music-arrangements/xxx.mp3",
        "file_url": "...",
        "episode": {...},
        "reviewedBy": {...}
      }
    ],
    "current_page": 1,
    "total": 1
  },
  "message": "Music arrangements retrieved successfully"
}
```

**Console Log:**
```javascript
async function loadMusicArrangerArrangements(status = null) {
  console.log(`🔄 [Music Arranger] Fetching arrangements...`, { status });
  
  try {
    const params = {};
    if (status) params.status = status;
    
    const response = await api.get('/live-tv/roles/music-arranger/arrangements', { params });
    
    console.log('✅ [Music Arranger] Arrangements response:', {
      success: response.data.success,
      hasData: !!response.data.data,
      hasDataData: !!response.data.data?.data,
      total: response.data.data?.total || 0,
      arrangementsCount: response.data.data?.data?.length || 0
    });
    
    if (response.data.success && response.data.data?.data) {
      const arrangements = response.data.data.data;
      
      console.log('📊 [Music Arranger] Arrangements breakdown:', {
        total: arrangements.length,
        byStatus: {
          draft: arrangements.filter(a => a.status === 'draft').length,
          song_proposal: arrangements.filter(a => a.status === 'song_proposal').length,
          song_approved: arrangements.filter(a => a.status === 'song_approved').length,
          arrangement_in_progress: arrangements.filter(a => a.status === 'arrangement_in_progress').length,
          arrangement_submitted: arrangements.filter(a => a.status === 'arrangement_submitted').length,
          arrangement_approved: arrangements.filter(a => a.status === 'arrangement_approved').length,
          arrangement_rejected: arrangements.filter(a => a.status === 'arrangement_rejected').length
        }
      });
      
      // Log rejected arrangements
      const rejected = arrangements.filter(a => a.status === 'arrangement_rejected');
      if (rejected.length > 0) {
        console.log('🚫 [Music Arranger] Rejected arrangements:', rejected.map(arr => ({
          id: arr.id,
          song_title: arr.song_title,
          rejection_reason: arr.rejection_reason,
          reviewed_at: arr.reviewed_at,
          reviewed_by: arr.reviewedBy?.name
        })));
      }
    }
    
    return response.data;
  } catch (error) {
    console.error('❌ [Music Arranger] Error fetching arrangements:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}

// Load rejected arrangements
async function loadRejectedArrangements() {
  return loadMusicArrangerArrangements('arrangement_rejected');
}
```

### 2. Update Arrangement (Upload File Baru setelah Reject)
**PUT** `/arrangements/{id}`

**Request Body (multipart/form-data):**
```
file: [File]
song_title: "Updated Song Title" (optional)
singer_name: "Updated Singer" (optional)
arrangement_notes: "Notes" (optional)
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 32,
    "status": "arrangement_submitted",
    "file_path": "music-arrangements/new-file.mp3",
    "file_name": "new-file.mp3",
    "submitted_at": "2025-12-17T20:00:00.000000Z"
  },
  "message": "Arrangement file uploaded and submitted for review. Producer has been notified."
}
```

**Console Log:**
```javascript
async function updateArrangementAfterReject(id, file, notes) {
  console.log(`🔄 [Music Arranger] Updating arrangement #${id} after reject...`, {
    hasFile: !!file,
    fileName: file?.name,
    notes: notes
  });
  
  try {
    const formData = new FormData();
    if (file) formData.append('file', file);
    if (notes) formData.append('arrangement_notes', notes);
    
    const response = await api.put(`/live-tv/roles/music-arranger/arrangements/${id}`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    
    console.log('✅ [Music Arranger] Arrangement updated:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      hasFile: !!response.data.data?.file_path,
      message: response.data.message
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Music Arranger] Error updating arrangement #${id}:`, {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status,
      errors: error.response?.data?.errors
    });
    throw error;
  }
}
```

### 3. Get Approved Arrangements History
**GET** `/approved-arrangements`

**Query Parameters:**
- `episode_id` (optional)
- `date_from` (optional)
- `date_to` (optional)

---

## 🔊 Sound Engineer API

### Base URL
```
/api/live-tv/roles/sound-engineer
```

### 1. Get Rejected Arrangements Needing Help
**GET** `/rejected-arrangements`

**Query Parameters:**
- `episode_id` (optional)

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 32,
        "episode_id": 2010,
        "song_title": "1 live musik unai",
        "status": "arrangement_rejected",
        "rejection_reason": "Volume terlalu rendah, perlu dinaikkan",
        "needs_sound_engineer_help": true,
        "reviewed_at": "2025-12-17T19:00:00.000000Z",
        "file_path": "music-arrangements/xxx.mp3",
        "file_url": "...",
        "episode": {
          "id": 2010,
          "episode_number": 1,
          "title": "Episode 1",
          "program": {
            "id": 101,
            "name": "Program Live Musik Unai"
          }
        },
        "createdBy": {
          "id": 1,
          "name": "Music Arranger Test"
        },
        "reviewedBy": {
          "id": 2,
          "name": "Producer Test"
        }
      }
    ],
    "current_page": 1,
    "total": 1
  },
  "message": "Rejected arrangements needing help retrieved successfully"
}
```

**Console Log:**
```javascript
async function loadRejectedArrangementsForSoundEngineer() {
  console.log('🔄 [Sound Engineer] Fetching rejected arrangements...');
  
  try {
    const response = await api.get('/live-tv/roles/sound-engineer/rejected-arrangements');
    
    console.log('✅ [Sound Engineer] Rejected arrangements response:', {
      success: response.data.success,
      hasData: !!response.data.data,
      hasDataData: !!response.data.data?.data,
      total: response.data.data?.total || 0,
      arrangementsCount: response.data.data?.data?.length || 0
    });
    
    if (response.data.success && response.data.data?.data) {
      const arrangements = response.data.data.data;
      
      console.log('📊 [Sound Engineer] Rejected arrangements:', {
        total: arrangements.length,
        arrangements: arrangements.map(arr => ({
          id: arr.id,
          song_title: arr.song_title,
          status: arr.status,
          rejection_reason: arr.rejection_reason,
          needs_help: arr.needs_sound_engineer_help,
          episode_number: arr.episode?.episode_number,
          episode_title: arr.episode?.title,
          program_name: arr.episode?.program?.name,
          created_by: arr.createdBy?.name,
          reviewed_by: arr.reviewedBy?.name,
          has_file: !!arr.file_path,
          file_url: arr.file_url
        }))
      });
    } else {
      console.warn('⚠️ [Sound Engineer] No rejected arrangements found');
    }
    
    return response.data;
  } catch (error) {
    console.error('❌ [Sound Engineer] Error fetching rejected arrangements:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status
    });
    throw error;
  }
}
```

### 2. Help Fix Arrangement (Upload File Baru)
**POST** `/arrangements/{arrangementId}/help-fix`

**Request Body (multipart/form-data):**
```
help_notes: "Saya sudah perbaiki volume dan EQ" (required)
suggested_fixes: "Volume dinaikkan 3dB, EQ adjusted" (optional)
file: [File] (optional - jika upload file baru)
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 32,
    "status": "arrangement_submitted",
    "file_path": "music-arrangements/fixed-file.mp3",
    "file_name": "fixed-file.mp3",
    "sound_engineer_helper_id": 3,
    "sound_engineer_help_notes": "Saya sudah perbaiki volume dan EQ",
    "sound_engineer_help_at": "2025-12-17T20:00:00.000000Z"
  },
  "message": "Arrangement help provided successfully. Music Arranger and Producer have been notified. File uploaded."
}
```

**Console Log:**
```javascript
async function helpFixArrangement(arrangementId, helpNotes, suggestedFixes, file) {
  console.log(`🔄 [Sound Engineer] Helping fix arrangement #${arrangementId}...`, {
    hasFile: !!file,
    fileName: file?.name,
    helpNotes: helpNotes,
    suggestedFixes: suggestedFixes
  });
  
  try {
    const formData = new FormData();
    formData.append('help_notes', helpNotes);
    if (suggestedFixes) formData.append('suggested_fixes', suggestedFixes);
    if (file) formData.append('file', file);
    
    const response = await api.post(
      `/live-tv/roles/sound-engineer/arrangements/${arrangementId}/help-fix`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      }
    );
    
    console.log('✅ [Sound Engineer] Arrangement help provided:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      hasFile: !!response.data.data?.file_path,
      message: response.data.message
    });
    
    return response.data;
  } catch (error) {
    console.error(`❌ [Sound Engineer] Error helping fix arrangement #${arrangementId}:`, {
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

## 🎯 Frontend Integration - Complete Example

### Vue 3 Composition API - Producer Dashboard

```vue
<template>
  <div class="producer-dashboard">
    <h1>Producer Dashboard</h1>
    
    <!-- Music Arrangements Pending Approval -->
    <div class="section">
      <h2>Music Arrangements Pending Approval</h2>
      
      <div v-if="loading">Loading...</div>
      <div v-if="error" class="error">{{ error }}</div>
      
      <div v-if="musicArrangements.length > 0">
        <div v-for="arr in musicArrangements" :key="arr.id" class="arrangement-card">
          <h3>{{ arr.song_title }}</h3>
          <p><strong>Singer:</strong> {{ arr.singer_name }}</p>
          <p><strong>Episode:</strong> {{ getEpisodeTitle(arr) }}</p>
          <p><strong>Program:</strong> {{ getProgramName(arr) }}</p>
          <p><strong>Status:</strong> {{ arr.status }}</p>
          <p><strong>Created by:</strong> {{ arr.createdBy?.name }}</p>
          
          <!-- Audio Player -->
          <audio v-if="arr.file_url" :src="arr.file_url" controls></audio>
          
          <!-- Actions -->
          <div class="actions">
            <button @click="approveArrangement(arr.id)">Approve</button>
            <button @click="openRejectModal(arr)">Reject</button>
            <a :href="arr.file_url" download>Download</a>
          </div>
        </div>
      </div>
      
      <div v-else>No arrangements pending approval</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const musicArrangements = ref([])
const loading = ref(false)
const error = ref(null)

// Load pending approvals
const loadApprovals = async () => {
  loading.value = true
  error.value = null
  
  console.log('🔄 [Producer] Fetching pending approvals...')
  
  try {
    const response = await api.get('/live-tv/producer/pending-approvals')
    
    console.log('✅ [Producer] Approvals response:', {
      success: response.data.success,
      hasData: !!response.data.data,
      hasMusicArrangements: !!response.data.data?.music_arrangements,
      musicArrangementsCount: response.data.data?.music_arrangements?.length || 0
    })
    
    if (response.data.success && response.data.data?.music_arrangements) {
      musicArrangements.value = response.data.data.music_arrangements
      
      console.log('📊 [Producer] Music Arrangements loaded:', {
        total: musicArrangements.value.length,
        byStatus: {
          submitted: musicArrangements.value.filter(a => a.status === 'arrangement_submitted').length,
          in_progress: musicArrangements.value.filter(a => a.status === 'arrangement_in_progress').length
        }
      })
      
      musicArrangements.value.forEach((arr, index) => {
        console.log(`🎵 [Producer] Arrangement #${index + 1}:`, {
          id: arr.id,
          song_title: arr.song_title,
          status: arr.status,
          episode_number: arr.episode?.episode_number,
          program_name: arr.episode?.program?.name,
          has_file: !!arr.file_path,
          file_url: arr.file_url
        })
      })
    } else {
      console.warn('⚠️ [Producer] No music arrangements found')
      musicArrangements.value = []
    }
  } catch (err) {
    console.error('❌ [Producer] Error:', {
      message: err.message,
      response: err.response?.data,
      status: err.response?.status
    })
    error.value = err.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

// Approve arrangement
const approveArrangement = async (id) => {
  console.log(`🔄 [Producer] Approving arrangement #${id}...`)
  
  try {
    const response = await api.post(`/live-tv/producer/approvals/${id}/approve`, {
      type: 'music_arrangement',
      notes: 'Arrangement approved'
    })
    
    console.log('✅ [Producer] Arrangement approved:', {
      success: response.data.success,
      newStatus: response.data.data?.status
    })
    
    await loadApprovals() // Reload
  } catch (err) {
    console.error(`❌ [Producer] Error approving:`, err)
    alert(err.response?.data?.message || 'Error approving arrangement')
  }
}

// Reject arrangement
const rejectArrangement = async (id, reason) => {
  console.log(`🔄 [Producer] Rejecting arrangement #${id}...`, { reason })
  
  try {
    const response = await api.post(`/live-tv/producer/approvals/${id}/reject`, {
      type: 'music_arrangement',
      reason: reason
    })
    
    console.log('✅ [Producer] Arrangement rejected:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      rejection_reason: response.data.data?.rejection_reason
    })
    
    await loadApprovals() // Reload
  } catch (err) {
    console.error(`❌ [Producer] Error rejecting:`, err)
    alert(err.response?.data?.message || 'Error rejecting arrangement')
  }
}

// Helper functions
const getEpisodeTitle = (arr) => {
  return arr.episode?.title || `Episode ${arr.episode?.episode_number || 'N/A'}`
}

const getProgramName = (arr) => {
  return arr.episode?.program?.name || 'Untitled Program'
}

onMounted(() => {
  loadApprovals()
})
</script>
```

### Vue 3 Composition API - Music Arranger Dashboard

```vue
<template>
  <div class="music-arranger-dashboard">
    <h1>Music Arranger Dashboard</h1>
    
    <!-- Rejected Arrangements -->
    <div class="section">
      <h2>Rejected Arrangements</h2>
      
      <div v-if="rejectedArrangements.length > 0">
        <div v-for="arr in rejectedArrangements" :key="arr.id" class="arrangement-card">
          <h3>{{ arr.song_title }}</h3>
          <p><strong>Rejection Reason:</strong> {{ arr.rejection_reason }}</p>
          <p><strong>Reviewed by:</strong> {{ arr.reviewedBy?.name }}</p>
          <p><strong>Reviewed at:</strong> {{ formatDate(arr.reviewed_at) }}</p>
          
          <!-- Upload New File -->
          <input type="file" @change="handleFileChange(arr.id, $event)" accept="audio/*" />
          <button @click="updateArrangement(arr.id)">Upload & Submit</button>
        </div>
      </div>
      
      <div v-else>No rejected arrangements</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const rejectedArrangements = ref([])
const files = ref({}) // { arrangementId: File }

// Load rejected arrangements
const loadRejectedArrangements = async () => {
  console.log('🔄 [Music Arranger] Fetching rejected arrangements...')
  
  try {
    const response = await api.get('/live-tv/roles/music-arranger/arrangements', {
      params: { status: 'arrangement_rejected' }
    })
    
    console.log('✅ [Music Arranger] Rejected arrangements:', {
      success: response.data.success,
      total: response.data.data?.total || 0,
      count: response.data.data?.data?.length || 0
    })
    
    if (response.data.success && response.data.data?.data) {
      rejectedArrangements.value = response.data.data.data
      
      rejectedArrangements.value.forEach((arr, index) => {
        console.log(`🚫 [Music Arranger] Rejected #${index + 1}:`, {
          id: arr.id,
          song_title: arr.song_title,
          rejection_reason: arr.rejection_reason,
          reviewed_by: arr.reviewedBy?.name
        })
      })
    }
  } catch (err) {
    console.error('❌ [Music Arranger] Error:', err)
  }
}

// Handle file change
const handleFileChange = (arrangementId, event) => {
  files.value[arrangementId] = event.target.files[0]
  console.log(`📁 [Music Arranger] File selected for arrangement #${arrangementId}:`, {
    fileName: event.target.files[0]?.name,
    fileSize: event.target.files[0]?.size
  })
}

// Update arrangement
const updateArrangement = async (arrangementId) => {
  const file = files.value[arrangementId]
  if (!file) {
    alert('Please select a file')
    return
  }
  
  console.log(`🔄 [Music Arranger] Updating arrangement #${arrangementId}...`, {
    fileName: file.name,
    fileSize: file.size
  })
  
  try {
    const formData = new FormData()
    formData.append('file', file)
    
    const response = await api.put(
      `/live-tv/roles/music-arranger/arrangements/${arrangementId}`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      }
    )
    
    console.log('✅ [Music Arranger] Arrangement updated:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      message: response.data.message
    })
    
    await loadRejectedArrangements() // Reload
  } catch (err) {
    console.error(`❌ [Music Arranger] Error updating:`, err)
    alert(err.response?.data?.message || 'Error updating arrangement')
  }
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('id-ID')
}

onMounted(() => {
  loadRejectedArrangements()
})
</script>
```

### Vue 3 Composition API - Sound Engineer Dashboard

```vue
<template>
  <div class="sound-engineer-dashboard">
    <h1>Sound Engineer Dashboard</h1>
    
    <!-- Rejected Arrangements Needing Help -->
    <div class="section">
      <h2>Rejected Arrangements Needing Help</h2>
      
      <div v-if="rejectedArrangements.length > 0">
        <div v-for="arr in rejectedArrangements" :key="arr.id" class="arrangement-card">
          <h3>{{ arr.song_title }}</h3>
          <p><strong>Rejection Reason:</strong> {{ arr.rejection_reason }}</p>
          <p><strong>Episode:</strong> {{ getEpisodeTitle(arr) }}</p>
          <p><strong>Program:</strong> {{ getProgramName(arr) }}</p>
          
          <!-- Help Fix Form -->
          <div>
            <textarea v-model="helpNotes[arr.id]" placeholder="Help notes (required)"></textarea>
            <textarea v-model="suggestedFixes[arr.id]" placeholder="Suggested fixes (optional)"></textarea>
            <input type="file" @change="handleFileChange(arr.id, $event)" accept="audio/*" />
            <button @click="helpFixArrangement(arr.id)">Help Fix & Submit</button>
          </div>
        </div>
      </div>
      
      <div v-else>No rejected arrangements needing help</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const rejectedArrangements = ref([])
const helpNotes = ref({})
const suggestedFixes = ref({})
const files = ref({})

// Load rejected arrangements
const loadRejectedArrangements = async () => {
  console.log('🔄 [Sound Engineer] Fetching rejected arrangements...')
  
  try {
    const response = await api.get('/live-tv/roles/sound-engineer/rejected-arrangements')
    
    console.log('✅ [Sound Engineer] Rejected arrangements:', {
      success: response.data.success,
      total: response.data.data?.total || 0,
      count: response.data.data?.data?.length || 0
    })
    
    if (response.data.success && response.data.data?.data) {
      rejectedArrangements.value = response.data.data.data
      
      rejectedArrangements.value.forEach((arr, index) => {
        console.log(`🚫 [Sound Engineer] Rejected #${index + 1}:`, {
          id: arr.id,
          song_title: arr.song_title,
          rejection_reason: arr.rejection_reason,
          needs_help: arr.needs_sound_engineer_help
        })
      })
    }
  } catch (err) {
    console.error('❌ [Sound Engineer] Error:', err)
  }
}

// Handle file change
const handleFileChange = (arrangementId, event) => {
  files.value[arrangementId] = event.target.files[0]
}

// Help fix arrangement
const helpFixArrangement = async (arrangementId) => {
  const notes = helpNotes.value[arrangementId]
  if (!notes) {
    alert('Please provide help notes')
    return
  }
  
  console.log(`🔄 [Sound Engineer] Helping fix arrangement #${arrangementId}...`, {
    hasFile: !!files.value[arrangementId],
    notes: notes
  })
  
  try {
    const formData = new FormData()
    formData.append('help_notes', notes)
    if (suggestedFixes.value[arrangementId]) {
      formData.append('suggested_fixes', suggestedFixes.value[arrangementId])
    }
    if (files.value[arrangementId]) {
      formData.append('file', files.value[arrangementId])
    }
    
    const response = await api.post(
      `/live-tv/roles/sound-engineer/arrangements/${arrangementId}/help-fix`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      }
    )
    
    console.log('✅ [Sound Engineer] Arrangement help provided:', {
      success: response.data.success,
      newStatus: response.data.data?.status,
      message: response.data.message
    })
    
    await loadRejectedArrangements() // Reload
  } catch (err) {
    console.error(`❌ [Sound Engineer] Error helping fix:`, err)
    alert(err.response?.data?.message || 'Error helping fix arrangement')
  }
}

const getEpisodeTitle = (arr) => {
  return arr.episode?.title || `Episode ${arr.episode?.episode_number || 'N/A'}`
}

const getProgramName = (arr) => {
  return arr.episode?.program?.name || 'Untitled Program'
}

onMounted(() => {
  loadRejectedArrangements()
})
</script>
```

---

## 📝 Checklist Integrasi Frontend

### Producer Dashboard
- [ ] Load pending approvals dengan console log
- [ ] Display music_arrangements dengan detail lengkap
- [ ] Audio player untuk preview file
- [ ] Approve arrangement dengan console log
- [ ] Reject arrangement dengan console log
- [ ] Handle error states
- [ ] Handle loading states

### Music Arranger Dashboard
- [ ] Load rejected arrangements dengan console log
- [ ] Display rejection reason dan reviewed by
- [ ] Upload file baru setelah reject
- [ ] Auto-submit setelah upload file
- [ ] Handle error states

### Sound Engineer Dashboard
- [ ] Load rejected arrangements dengan console log
- [ ] Display rejection reason
- [ ] Help fix form dengan help_notes dan file upload
- [ ] Auto-submit setelah upload file
- [ ] Handle error states

---

**Last Updated:** 2025-12-17

