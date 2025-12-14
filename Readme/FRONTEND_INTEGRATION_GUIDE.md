# 🎨 FRONTEND INTEGRATION GUIDE - WORKFLOW PROGRAM REGULAR HCI

**Version**: 1.0.0  
**Target**: Frontend Developers  
**Last Updated**: 22 Oktober 2025

---

## 🎯 OVERVIEW UNTUK FRONTEND

Backend sudah menyediakan **61 API endpoints** untuk workflow lengkap Program Regular HCI. Dokumen ini akan guide Anda bagaimana mengintegrasikan API-API tersebut ke frontend.

---

## 📋 RINGKASAN WORKFLOW

Workflow Program Regular HCI terdiri dari **10 tahap** yang berjalan sequential (ada juga yang parallel):

```
1. Manager Program → Create Program
2. Creative → Submit Script & Rundown
3. Producer → Review & Approve
4. Produksi → Request Equipment & Shooting
5. Editor → Editing (7 hari before air)
6. QC → Quality Control Review
7. Design Grafis → Create Thumbnails (parallel)
8. Promosi → BTS Content (parallel)
9. Broadcasting → Upload YouTube & Website
10. Manager Distribusi → Track Analytics
```

---

## 🎨 UI YANG PERLU DIBUAT

### **1. Dashboard per Role** (10 dashboards)

Setiap role butuh dashboard sendiri dengan:
- **My Tasks** (pending, in-progress, completed)
- **Statistics** (daily, weekly, monthly)
- **Upcoming Deadlines**
- **Recent Activities**

#### **Roles**:
1. Manager Program
2. Producer
3. Creative (Kreatif)
4. Produksi
5. Editor
6. QC
7. Design Grafis
8. Broadcasting
9. Promosi
10. Manager Distribusi

---

### **2. Episode Detail Page**

**Shared across all roles**, tapi showing different actions based on role:

**Components**:
- Episode info (title, air date, status)
- Workflow progress bar (visual)
- Talent data (host, narasumber)
- Files section (script, rundown, raw files, final file, thumbnails)
- Timeline/history
- Actions (different per role)

---

### **3. Workflow Status Visualizer**

**Visual timeline** showing progress:
```
[✅ Creative] → [✅ Producer] → [✅ Produksi] → [🔄 Editor] → [⏳ QC] → [⏳ Broadcasting]
```

With color codes:
- ✅ Green = Completed
- 🔄 Yellow = In Progress
- ⏳ Gray = Pending
- ❌ Red = Overdue/Rejected

---

## 🔌 API SERVICE STRUCTURE

Buat **7 API service files** di frontend:

### **File Structure**:
```javascript
src/services/
├── workflowApi.js           // General workflow
├── creativeApi.js           // Creative-specific
├── producerApi.js           // Producer-specific
├── produksiApi.js           // Produksi-specific
├── editorApi.js             // Editor-specific
├── qcApi.js                 // QC-specific
├── broadcastingApi.js       // Broadcasting-specific
├── designGrafisApi.js       // Design Grafis-specific
├── promosiApi.js            // Promosi-specific
└── distribusiApi.js         // Distribusi-specific
```

---

## 📝 API SERVICE TEMPLATES

### **1. workflowApi.js** (General Workflow)

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const workflowApi = {
  /**
   * Get workflow status untuk episode
   * @param {number} episodeId 
   */
  getEpisodeStatus: async (episodeId) => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/workflow/episodes/${episodeId}/status`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting episode status:', error);
      throw error;
    }
  },

  /**
   * Get workflow dashboard overview
   */
  getDashboard: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/workflow/dashboard`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting dashboard:', error);
      throw error;
    }
  }
};

export default workflowApi;
```

---

### **2. creativeApi.js** (Creative/Kreatif)

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const creativeApi = {
  /**
   * Submit script & rundown
   * @param {number} episodeId 
   * @param {Object} scriptData 
   */
  submitScript: async (episodeId, scriptData) => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/workflow/creative/episodes/${episodeId}/script`,
        {
          title: scriptData.title,
          script: scriptData.script,
          rundown: scriptData.rundown,
          talent_data: {
            host: {
              name: scriptData.hostName,
              phone: scriptData.hostPhone,
              email: scriptData.hostEmail,
              ttl: scriptData.hostTTL,
              pendidikan: scriptData.hostEducation,
              latar_belakang: scriptData.hostBackground
            },
            narasumber: scriptData.narasumber, // Array
            kesaksian: scriptData.kesaksian // Array
          },
          location: scriptData.location,
          production_date: scriptData.productionDate,
          budget_talent: scriptData.budgetTalent,
          notes: scriptData.notes
        },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error submitting script:', error);
      throw error;
    }
  }
};

export default creativeApi;
```

---

### **3. qcApi.js** (Quality Control)

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const qcApi = {
  /**
   * Get pending episodes untuk QC
   */
  getPendingEpisodes: async (filters = {}) => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/qc/episodes/pending`,
        {
          params: filters,
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting pending episodes:', error);
      throw error;
    }
  },

  /**
   * Get episode details untuk QC
   */
  getEpisode: async (episodeId) => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/qc/episodes/${episodeId}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting episode:', error);
      throw error;
    }
  },

  /**
   * Submit QC review
   * @param {number} episodeId 
   * @param {Object} reviewData 
   */
  submitReview: async (episodeId, reviewData) => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/qc/episodes/${episodeId}/review`,
        {
          decision: reviewData.decision, // 'approved' or 'revision_needed'
          quality_score: reviewData.qualityScore, // 1-10
          video_quality_score: reviewData.videoScore, // 1-10
          audio_quality_score: reviewData.audioScore, // 1-10
          content_quality_score: reviewData.contentScore, // 1-10
          notes: reviewData.notes,
          revision_points: reviewData.revisionPoints // Array of revision items
        },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error submitting QC review:', error);
      throw error;
    }
  },

  /**
   * Get my QC tasks
   */
  getMyTasks: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/qc/my-tasks`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting tasks:', error);
      throw error;
    }
  },

  /**
   * Get statistics
   */
  getStatistics: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/qc/statistics`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting statistics:', error);
      throw error;
    }
  }
};

export default qcApi;
```

---

### **4. broadcastingApi.js** (Broadcasting)

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const broadcastingApi = {
  /**
   * Get episodes ready for broadcasting
   */
  getReadyEpisodes: async (filters = {}) => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/broadcasting/episodes/ready`,
        {
          params: filters,
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting ready episodes:', error);
      throw error;
    }
  },

  /**
   * Update metadata SEO
   */
  updateMetadata: async (episodeId, metadataData) => {
    try {
      const response = await axios.put(
        `${API_BASE_URL}/broadcasting/episodes/${episodeId}/metadata`,
        {
          seo_title: metadataData.seoTitle,
          seo_description: metadataData.seoDescription,
          seo_tags: metadataData.seoTags, // Array of tags
          youtube_category: metadataData.youtubeCategory,
          youtube_privacy: metadataData.youtubePrivacy // 'public', 'unlisted', 'private'
        },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error updating metadata:', error);
      throw error;
    }
  },

  /**
   * Set YouTube link setelah upload
   */
  setYouTubeLink: async (episodeId, youtubeData) => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/broadcasting/episodes/${episodeId}/youtube-link`,
        {
          youtube_url: youtubeData.url,
          youtube_video_id: youtubeData.videoId
        },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error setting YouTube link:', error);
      throw error;
    }
  },

  /**
   * Set Website URL
   */
  setWebsiteLink: async (episodeId, websiteUrl) => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/broadcasting/episodes/${episodeId}/upload-website`,
        {
          website_url: websiteUrl,
          website_publish_date: new Date().toISOString()
        },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error setting website link:', error);
      throw error;
    }
  },

  /**
   * Complete broadcasting (mark as aired)
   */
  completeBroadcast: async (episodeId, notes = '') => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/broadcasting/episodes/${episodeId}/complete`,
        {
          broadcast_notes: notes,
          actual_air_date: new Date().toISOString()
        },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error completing broadcast:', error);
      throw error;
    }
  },

  /**
   * Get my tasks
   */
  getMyTasks: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/broadcasting/my-tasks`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting tasks:', error);
      throw error;
    }
  }
};

export default broadcastingApi;
```

---

### **5. editorApi.js** (Editor)

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const editorApi = {
  /**
   * Get my editing tasks
   */
  getMyTasks: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/editor/my-tasks`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting tasks:', error);
      throw error;
    }
  },

  /**
   * Check file completeness
   */
  checkFiles: async (episodeId) => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/editor/episodes/${episodeId}/check-files`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error checking files:', error);
      throw error;
    }
  },

  /**
   * Start editing
   */
  startEditing: async (episodeId, notes = '') => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/editor/episodes/${episodeId}/start-editing`,
        { notes },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error starting editing:', error);
      throw error;
    }
  },

  /**
   * Upload final file
   * @param {number} episodeId 
   * @param {File} file - Video file
   * @param {Object} metadata 
   */
  uploadFinalFile: async (episodeId, file, metadata) => {
    try {
      const formData = new FormData();
      formData.append('final_file', file);
      formData.append('completion_notes', metadata.notes);
      formData.append('duration_minutes', metadata.duration);
      formData.append('file_size_mb', (file.size / 1024 / 1024).toFixed(2));

      const response = await axios.post(
        `${API_BASE_URL}/editor/episodes/${episodeId}/complete`,
        formData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'multipart/form-data'
          },
          onUploadProgress: (progressEvent) => {
            const percentCompleted = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
            console.log(`Upload progress: ${percentCompleted}%`);
            // You can emit event here untuk progress bar
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error uploading final file:', error);
      throw error;
    }
  },

  /**
   * Upload final file (URL-based, jika file sudah di storage)
   */
  submitFinalFileUrl: async (episodeId, fileUrl, metadata) => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/editor/episodes/${episodeId}/complete`,
        {
          final_url: fileUrl,
          completion_notes: metadata.notes,
          duration_minutes: metadata.duration,
          file_size_mb: metadata.fileSize
        },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error submitting final file URL:', error);
      throw error;
    }
  },

  /**
   * Handle revision dari QC
   */
  handleRevision: async (episodeId, revisionData) => {
    try {
      const formData = new FormData();
      formData.append('action', revisionData.action); // 'acknowledge' or 'reupload'
      formData.append('revision_notes', revisionData.notes);
      
      if (revisionData.action === 'reupload' && revisionData.file) {
        formData.append('revised_file', revisionData.file);
      }

      const response = await axios.post(
        `${API_BASE_URL}/editor/episodes/${episodeId}/handle-revision`,
        formData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'multipart/form-data'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error handling revision:', error);
      throw error;
    }
  }
};

export default editorApi;
```

---

### **6. designGrafisApi.js** (Design Grafis)

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const designGrafisApi = {
  /**
   * Get my tasks
   */
  getMyTasks: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/design-grafis/my-tasks`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting tasks:', error);
      throw error;
    }
  },

  /**
   * Upload thumbnail YouTube
   * @param {number} episodeId 
   * @param {File} file - Image file (JPG/PNG)
   * @param {string} notes 
   */
  uploadThumbnailYouTube: async (episodeId, file, notes = '') => {
    try {
      const formData = new FormData();
      formData.append('thumbnail_file', file);
      formData.append('design_notes', notes);

      const response = await axios.post(
        `${API_BASE_URL}/design-grafis/episodes/${episodeId}/upload-thumbnail-youtube`,
        formData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'multipart/form-data'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error uploading YouTube thumbnail:', error);
      throw error;
    }
  },

  /**
   * Upload thumbnail BTS
   */
  uploadThumbnailBTS: async (episodeId, file, notes = '') => {
    try {
      const formData = new FormData();
      formData.append('thumbnail_file', file);
      formData.append('design_notes', notes);

      const response = await axios.post(
        `${API_BASE_URL}/design-grafis/episodes/${episodeId}/upload-thumbnail-bts`,
        formData,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'multipart/form-data'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error uploading BTS thumbnail:', error);
      throw error;
    }
  },

  /**
   * Complete design work
   */
  completeDesign: async (episodeId, notes = '') => {
    try {
      const response = await axios.post(
        `${API_BASE_URL}/design-grafis/episodes/${episodeId}/complete`,
        { completion_notes: notes },
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error completing design:', error);
      throw error;
    }
  }
};

export default designGrafisApi;
```

---

### **7. distribusiApi.js** (Manager Distribusi)

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const distribusiApi = {
  /**
   * Get dashboard overview
   */
  getDashboard: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/distribusi/dashboard`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting dashboard:', error);
      throw error;
    }
  },

  /**
   * Get YouTube analytics
   */
  getYouTubeAnalytics: async (period = '30days') => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/distribusi/analytics/youtube`,
        {
          params: { period },
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting YouTube analytics:', error);
      throw error;
    }
  },

  /**
   * Get weekly KPI
   */
  getWeeklyKPI: async (weekStart = null) => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/distribusi/kpi/weekly`,
        {
          params: weekStart ? { week_start: weekStart } : {},
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting weekly KPI:', error);
      throw error;
    }
  },

  /**
   * Get episode performance
   */
  getEpisodePerformance: async (episodeId) => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/distribusi/episodes/${episodeId}/performance`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting episode performance:', error);
      throw error;
    }
  }
};

export default distribusiApi;
```

---

## 🎨 COMPONENT EXAMPLES (Vue.js)

### **1. QC Review Form Component**

```vue
<template>
  <div class="qc-review-form">
    <h2>QC Review - Episode {{ episode.episode_number }}</h2>
    
    <!-- Episode Info -->
    <div class="episode-info">
      <h3>{{ episode.title }}</h3>
      <p>Air Date: {{ formatDate(episode.air_date) }}</p>
      <p>Final File: <a :href="episode.editorWork.final_file_url" target="_blank">View File</a></p>
    </div>

    <!-- QC Form -->
    <form @submit.prevent="submitReview">
      <!-- Decision -->
      <div class="form-group">
        <label>Decision</label>
        <select v-model="reviewData.decision" required>
          <option value="approved">Approve</option>
          <option value="revision_needed">Needs Revision</option>
        </select>
      </div>

      <!-- Quality Scores -->
      <div class="scores-section">
        <h4>Quality Scores (1-10)</h4>
        
        <div class="score-input">
          <label>Overall Quality</label>
          <input type="number" v-model.number="reviewData.qualityScore" 
                 min="1" max="10" required />
        </div>

        <div class="score-input">
          <label>Video Quality</label>
          <input type="number" v-model.number="reviewData.videoScore" 
                 min="1" max="10" />
        </div>

        <div class="score-input">
          <label>Audio Quality</label>
          <input type="number" v-model.number="reviewData.audioScore" 
                 min="1" max="10" />
        </div>

        <div class="score-input">
          <label>Content Quality</label>
          <input type="number" v-model.number="reviewData.contentScore" 
                 min="1" max="10" />
        </div>
      </div>

      <!-- Notes -->
      <div class="form-group">
        <label>Review Notes</label>
        <textarea v-model="reviewData.notes" required rows="5"></textarea>
      </div>

      <!-- Revision Points (if needs revision) -->
      <div v-if="reviewData.decision === 'revision_needed'" class="revision-section">
        <h4>Revision Points</h4>
        <button type="button" @click="addRevisionPoint">+ Add Revision Point</button>
        
        <div v-for="(point, index) in reviewData.revisionPoints" :key="index" 
             class="revision-point">
          <select v-model="point.category" required>
            <option value="video">Video</option>
            <option value="audio">Audio</option>
            <option value="content">Content</option>
            <option value="subtitle">Subtitle</option>
            <option value="transition">Transition</option>
            <option value="effect">Effect</option>
            <option value="other">Other</option>
          </select>

          <input v-model="point.description" placeholder="Description" required />

          <select v-model="point.priority" required>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>

          <button type="button" @click="removeRevisionPoint(index)">Remove</button>
        </div>
      </div>

      <!-- Submit Button -->
      <button type="submit" :disabled="submitting">
        {{ submitting ? 'Submitting...' : 'Submit QC Review' }}
      </button>
    </form>
  </div>
</template>

<script>
import qcApi from '@/services/qcApi';

export default {
  name: 'QCReviewForm',
  props: {
    episodeId: {
      type: Number,
      required: true
    }
  },
  data() {
    return {
      episode: null,
      reviewData: {
        decision: 'approved',
        qualityScore: 8,
        videoScore: 8,
        audioScore: 8,
        contentScore: 8,
        notes: '',
        revisionPoints: []
      },
      submitting: false
    };
  },
  async mounted() {
    await this.loadEpisode();
  },
  methods: {
    async loadEpisode() {
      try {
        const response = await qcApi.getEpisode(this.episodeId);
        this.episode = response.data.episode;
      } catch (error) {
        console.error('Error loading episode:', error);
        this.$emit('error', 'Failed to load episode');
      }
    },

    addRevisionPoint() {
      this.reviewData.revisionPoints.push({
        category: 'video',
        description: '',
        priority: 'medium'
      });
    },

    removeRevisionPoint(index) {
      this.reviewData.revisionPoints.splice(index, 1);
    },

    async submitReview() {
      this.submitting = true;
      try {
        const response = await qcApi.submitReview(this.episodeId, this.reviewData);
        this.$emit('success', 'QC review submitted successfully');
        this.$emit('submitted', response.data);
      } catch (error) {
        console.error('Error submitting review:', error);
        this.$emit('error', 'Failed to submit QC review');
      } finally {
        this.submitting = false;
      }
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
  }
};
</script>
```

---

### **2. Workflow Status Component**

```vue
<template>
  <div class="workflow-status">
    <h3>Workflow Progress</h3>
    
    <!-- Progress Bar -->
    <div class="progress-bar">
      <div class="progress-fill" :style="{ width: progressPercentage + '%' }"></div>
    </div>
    <p>{{ progressPercentage }}% Complete</p>

    <!-- Workflow Steps -->
    <div class="workflow-steps">
      <div v-for="(step, key) in workflowSteps" :key="key" 
           class="workflow-step"
           :class="getStepClass(step)">
        <div class="step-icon">
          <i :class="getStepIcon(step)"></i>
        </div>
        <div class="step-info">
          <h4>{{ getStepLabel(key) }}</h4>
          <p v-if="step.completed_at">
            ✅ {{ formatDate(step.completed_at) }}
          </p>
          <p v-else-if="step.status === 'rejected'">
            ❌ Rejected
          </p>
          <p v-else-if="step.status === 'in_progress'">
            🔄 In Progress
          </p>
          <p v-else>
            ⏳ Pending
          </p>
        </div>
      </div>
    </div>

    <!-- Days Until Air -->
    <div class="deadline-info" :class="{ 'overdue': isOverdue }">
      <p v-if="isOverdue">
        ⚠️ OVERDUE: Episode should have aired {{ Math.abs(daysUntilAir) }} days ago
      </p>
      <p v-else-if="daysUntilAir === 0">
        🔴 URGENT: Episode airs TODAY!
      </p>
      <p v-else-if="daysUntilAir <= 3">
        🟡 {{ daysUntilAir }} days until air date
      </p>
      <p v-else>
        🟢 {{ daysUntilAir }} days until air date
      </p>
    </div>
  </div>
</template>

<script>
import workflowApi from '@/services/workflowApi';

export default {
  name: 'WorkflowStatus',
  props: {
    episodeId: {
      type: Number,
      required: true
    }
  },
  data() {
    return {
      workflowSteps: {},
      daysUntilAir: 0,
      isOverdue: false,
      currentStatus: ''
    };
  },
  computed: {
    progressPercentage() {
      const steps = Object.values(this.workflowSteps);
      const completed = steps.filter(s => s.status === 'completed').length;
      return Math.round((completed / steps.length) * 100);
    }
  },
  async mounted() {
    await this.loadWorkflowStatus();
    
    // Auto-refresh every 30 seconds
    this.refreshInterval = setInterval(() => {
      this.loadWorkflowStatus();
    }, 30000);
  },
  beforeUnmount() {
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
    }
  },
  methods: {
    async loadWorkflowStatus() {
      try {
        const response = await workflowApi.getEpisodeStatus(this.episodeId);
        this.workflowSteps = response.data.workflow_steps;
        this.daysUntilAir = response.data.days_until_air;
        this.isOverdue = response.data.is_overdue;
        this.currentStatus = response.data.current_status;
      } catch (error) {
        console.error('Error loading workflow status:', error);
      }
    },

    getStepClass(step) {
      if (step.status === 'completed') return 'step-completed';
      if (step.status === 'rejected') return 'step-rejected';
      if (step.status === 'in_progress') return 'step-in-progress';
      return 'step-pending';
    },

    getStepIcon(step) {
      if (step.status === 'completed') return 'fa fa-check-circle';
      if (step.status === 'rejected') return 'fa fa-times-circle';
      if (step.status === 'in_progress') return 'fa fa-spinner fa-spin';
      return 'fa fa-circle';
    },

    getStepLabel(key) {
      const labels = {
        'creative': 'Creative',
        'producer_review': 'Producer Review',
        'produksi': 'Produksi',
        'editor': 'Editor',
        'qc': 'Quality Control',
        'design_grafis': 'Design Grafis',
        'broadcasting': 'Broadcasting',
        'promosi': 'Promosi'
      };
      return labels[key] || key;
    },

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('id-ID');
    }
  }
};
</script>

<style scoped>
.workflow-status {
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
}

.progress-bar {
  height: 20px;
  background: #e9ecef;
  border-radius: 10px;
  overflow: hidden;
  margin: 10px 0;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #28a745, #20c997);
  transition: width 0.3s ease;
}

.workflow-steps {
  display: flex;
  flex-direction: column;
  gap: 15px;
  margin-top: 20px;
}

.workflow-step {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: white;
  border-radius: 8px;
  border-left: 4px solid #6c757d;
}

.step-completed {
  border-left-color: #28a745;
}

.step-in-progress {
  border-left-color: #ffc107;
}

.step-rejected {
  border-left-color: #dc3545;
}

.step-pending {
  border-left-color: #6c757d;
}

.deadline-info {
  margin-top: 20px;
  padding: 15px;
  border-radius: 8px;
  background: #d1ecf1;
  color: #0c5460;
  text-align: center;
  font-weight: bold;
}

.deadline-info.overdue {
  background: #f8d7da;
  color: #721c24;
}
</style>
```

---

### **3. My Tasks Dashboard Component**

```vue
<template>
  <div class="my-tasks-dashboard">
    <h2>My Tasks</h2>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <h3>{{ tasks.pending_start?.length || 0 }}</h3>
        <p>Pending</p>
      </div>
      <div class="stat-card in-progress">
        <h3>{{ tasks.in_progress?.length || 0 }}</h3>
        <p>In Progress</p>
      </div>
      <div class="stat-card completed">
        <h3>{{ tasks.completed_this_week?.length || 0 }}</h3>
        <p>Completed This Week</p>
      </div>
      <div class="stat-card revision">
        <h3>{{ tasks.pending_revision?.length || 0 }}</h3>
        <p>Needs Revision</p>
      </div>
    </div>

    <!-- Task Lists -->
    <div class="task-sections">
      <!-- Pending Tasks -->
      <div class="task-section" v-if="tasks.pending_start?.length">
        <h3>Pending Start</h3>
        <div v-for="episode in tasks.pending_start" :key="episode.id" 
             class="task-item"
             @click="openEpisode(episode.id)">
          <div class="task-info">
            <h4>Episode {{ episode.episode_number }}: {{ episode.title }}</h4>
            <p>Air Date: {{ formatDate(episode.air_date) }}</p>
            <p>{{ getDaysUntilText(episode.air_date) }}</p>
          </div>
          <button class="btn-primary">Start Work</button>
        </div>
      </div>

      <!-- In Progress Tasks -->
      <div class="task-section" v-if="tasks.in_progress?.length">
        <h3>In Progress</h3>
        <div v-for="episode in tasks.in_progress" :key="episode.id" 
             class="task-item in-progress"
             @click="openEpisode(episode.id)">
          <div class="task-info">
            <h4>Episode {{ episode.episode_number }}: {{ episode.title }}</h4>
            <p>Started: {{ formatDate(episode.editing_started_at) }}</p>
          </div>
          <button class="btn-warning">Continue</button>
        </div>
      </div>

      <!-- Revision Needed -->
      <div class="task-section" v-if="tasks.pending_revision?.length">
        <h3>🔴 Needs Revision (Urgent)</h3>
        <div v-for="episode in tasks.pending_revision" :key="episode.id" 
             class="task-item urgent"
             @click="openEpisode(episode.id)">
          <div class="task-info">
            <h4>Episode {{ episode.episode_number }}: {{ episode.title }}</h4>
            <p>Revision Count: {{ episode.qc_revision_count }}</p>
            <p class="urgent-text">⚠️ Needs immediate attention</p>
          </div>
          <button class="btn-danger">View Feedback</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import editorApi from '@/services/editorApi';

export default {
  name: 'MyTasksDashboard',
  data() {
    return {
      tasks: {
        pending_start: [],
        in_progress: [],
        pending_revision: [],
        completed_this_week: []
      },
      loading: false
    };
  },
  async mounted() {
    await this.loadTasks();
  },
  methods: {
    async loadTasks() {
      this.loading = true;
      try {
        const response = await editorApi.getMyTasks();
        this.tasks = response.data;
      } catch (error) {
        console.error('Error loading tasks:', error);
        this.$emit('error', 'Failed to load tasks');
      } finally {
        this.loading = false;
      }
    },

    openEpisode(episodeId) {
      this.$router.push(`/editor/episodes/${episodeId}`);
    },

    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('id-ID');
    },

    getDaysUntilText(airDate) {
      const days = Math.ceil((new Date(airDate) - new Date()) / (1000 * 60 * 60 * 24));
      if (days < 0) return `⚠️ OVERDUE: ${Math.abs(days)} days ago`;
      if (days === 0) return '🔴 AIRS TODAY!';
      if (days <= 3) return `🟡 ${days} days until air`;
      return `🟢 ${days} days until air`;
    }
  }
};
</script>
```

---

## 📱 STATE MANAGEMENT (Vuex/Pinia Example)

### **workflowStore.js** (Pinia)

```javascript
import { defineStore } from 'pinia';
import workflowApi from '@/services/workflowApi';
import creativeApi from '@/services/creativeApi';
import qcApi from '@/services/qcApi';
import editorApi from '@/services/editorApi';

export const useWorkflowStore = defineStore('workflow', {
  state: () => ({
    currentEpisode: null,
    workflowStatus: null,
    myTasks: null,
    statistics: null,
    loading: false,
    error: null
  }),

  actions: {
    /**
     * Load episode workflow status
     */
    async loadEpisodeStatus(episodeId) {
      this.loading = true;
      this.error = null;
      try {
        const response = await workflowApi.getEpisodeStatus(episodeId);
        this.workflowStatus = response.data;
        this.currentEpisode = response.data.episode;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to load episode status';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Load my tasks (role-specific)
     */
    async loadMyTasks(role) {
      this.loading = true;
      try {
        let response;
        switch (role) {
          case 'Editor':
            response = await editorApi.getMyTasks();
            break;
          case 'QC':
            response = await qcApi.getMyTasks();
            break;
          // Add other roles...
          default:
            throw new Error('Unknown role');
        }
        this.myTasks = response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to load tasks';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Submit script (Creative)
     */
    async submitScript(episodeId, scriptData) {
      this.loading = true;
      try {
        const response = await creativeApi.submitScript(episodeId, scriptData);
        // Reload workflow status
        await this.loadEpisodeStatus(episodeId);
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to submit script';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    /**
     * Submit QC review
     */
    async submitQCReview(episodeId, reviewData) {
      this.loading = true;
      try {
        const response = await qcApi.submitReview(episodeId, reviewData);
        await this.loadEpisodeStatus(episodeId);
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to submit QC review';
        throw error;
      } finally {
        this.loading = false;
      }
    }
  },

  getters: {
    isLoading: (state) => state.loading,
    hasError: (state) => state.error !== null,
    currentStep: (state) => state.currentEpisode?.status || '',
    progressPercentage: (state) => {
      if (!state.workflowStatus) return 0;
      const steps = Object.values(state.workflowStatus.workflow_steps);
      const completed = steps.filter(s => s.status === 'completed').length;
      return Math.round((completed / steps.length) * 100);
    }
  }
});
```

---

## 🎨 UI/UX RECOMMENDATIONS

### **1. Color Coding untuk Status**

```css
/* Status Colors */
.status-planning { background: #6c757d; color: white; }
.status-script-review { background: #17a2b8; color: white; }
.status-rundown-approved { background: #28a745; color: white; }
.status-post-production { background: #ffc107; color: #000; }
.status-ready-to-air { background: #007bff; color: white; }
.status-aired { background: #28a745; color: white; }
.status-revision { background: #dc3545; color: white; }
.status-overdue { background: #dc3545; color: white; animation: blink 1s infinite; }

@keyframes blink {
  50% { opacity: 0.5; }
}
```

### **2. Priority Indicators**

```javascript
const priorityConfig = {
  critical: { color: '#dc3545', icon: '🔴', label: 'CRITICAL' },
  high: { color: '#fd7e14', icon: '🟠', label: 'HIGH' },
  medium: { color: '#ffc107', icon: '🟡', label: 'MEDIUM' },
  low: { color: '#28a745', icon: '🟢', label: 'LOW' }
};
```

### **3. Notification Badge**

```vue
<template>
  <div class="notification-badge">
    <i class="fa fa-bell"></i>
    <span class="badge" v-if="unreadCount > 0">{{ unreadCount }}</span>
  </div>
</template>
```

---

## 📊 DATA FLOW EXAMPLE

### **Contoh: Complete Editing Flow**

```javascript
// 1. Editor opens dashboard
const tasks = await editorApi.getMyTasks();
// Returns: { pending_start: [...], in_progress: [...], pending_revision: [...] }

// 2. Editor clicks episode → check files
const fileCheck = await editorApi.checkFiles(episodeId);
// Returns: { complete: true/false, raw_files: [...], issues: [...] }

// 3. If files complete → start editing
await editorApi.startEditing(episodeId, 'Mulai editing episode 1');
// Returns: { episode: {...}, status: 'in_progress' }

// 4. Editor works on video...

// 5. Upload final file
await editorApi.uploadFinalFile(episodeId, videoFile, {
  notes: 'Editing selesai',
  duration: 60
});
// Returns: { episode: {...}, message: 'Editing completed successfully. Submitted to QC.' }

// 6. QC receives notification automatically

// 7. QC reviews
await qcApi.submitReview(episodeId, {
  decision: 'approved',
  qualityScore: 9,
  notes: 'Great work!'
});
// Returns: { qc: {...}, episode: {...status: 'ready_to_air'} }

// 8. Broadcasting receives notification automatically
```

---

## 🔔 NOTIFICATION HANDLING

### **notificationService.js**

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const notificationService = {
  /**
   * Get unread notifications
   */
  getUnread: async () => {
    try {
      const response = await axios.get(
        `${API_BASE_URL}/notifications/unread`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error getting notifications:', error);
      throw error;
    }
  },

  /**
   * Mark as read
   */
  markAsRead: async (notificationId) => {
    try {
      await axios.post(
        `${API_BASE_URL}/notifications/${notificationId}/read`,
        {},
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
    } catch (error) {
      console.error('Error marking as read:', error);
      throw error;
    }
  },

  /**
   * Poll for new notifications (every 30 seconds)
   */
  startPolling: (callback) => {
    const poll = async () => {
      try {
        const notifications = await notificationService.getUnread();
        callback(notifications.data);
      } catch (error) {
        console.error('Polling error:', error);
      }
    };

    // Initial poll
    poll();

    // Poll every 30 seconds
    const intervalId = setInterval(poll, 30000);

    // Return cleanup function
    return () => clearInterval(intervalId);
  }
};

export default notificationService;
```

---

## 📋 RESPONSE DATA STRUCTURES

### **Episode Object Structure**

```typescript
interface Episode {
  id: number;
  episode_number: number;
  title: string;
  description: string;
  air_date: string; // ISO date
  production_date: string;
  status: EpisodeStatus;
  script: string;
  rundown: string;
  talent_data: TalentData;
  location: string;
  
  // Workflow tracking
  script_submitted_at: string | null;
  rundown_approved_at: string | null;
  shooting_completed_at: string | null;
  editing_completed_at: string | null;
  qc_approved_at: string | null;
  broadcast_completed_at: string | null;
  
  // Files
  raw_file_urls: string[];
  final_file_url: string | null;
  thumbnail_youtube: string | null;
  thumbnail_bts: string | null;
  youtube_url: string | null;
  website_url: string | null;
}

type EpisodeStatus = 
  | 'planning'
  | 'script_review'
  | 'rundown_approved'
  | 'post_production'
  | 'revision'
  | 'ready_to_air'
  | 'aired';

interface TalentData {
  host: {
    name: string;
    phone?: string;
    email?: string;
    ttl?: string;
    pendidikan?: string;
    latar_belakang?: string;
  };
  narasumber: Array<{
    name: string;
    gelar?: string;
    keahlian?: string;
    phone?: string;
    email?: string;
    ttl?: string;
    pendidikan?: string;
    latar_belakang?: string;
  }>;
  kesaksian?: Array<{
    name: string;
    testimony: string;
    phone?: string;
  }>;
}
```

### **QC Review Object**

```typescript
interface QCReview {
  decision: 'approved' | 'revision_needed';
  quality_score: number; // 1-10
  video_quality_score?: number;
  audio_quality_score?: number;
  content_quality_score?: number;
  notes: string;
  revision_points?: RevisionPoint[];
}

interface RevisionPoint {
  category: 'video' | 'audio' | 'content' | 'subtitle' | 'transition' | 'effect' | 'other';
  description: string;
  priority: 'low' | 'medium' | 'high' | 'critical';
}
```

---

## 🎯 ROUTING STRUCTURE (Vue Router)

```javascript
const routes = [
  // Dashboard routes per role
  {
    path: '/creative/dashboard',
    component: () => import('@/views/Creative/Dashboard.vue'),
    meta: { role: 'Creative' }
  },
  {
    path: '/editor/dashboard',
    component: () => import('@/views/Editor/Dashboard.vue'),
    meta: { role: 'Editor' }
  },
  {
    path: '/qc/dashboard',
    component: () => import('@/views/QC/Dashboard.vue'),
    meta: { role: 'QC' }
  },
  {
    path: '/broadcasting/dashboard',
    component: () => import('@/views/Broadcasting/Dashboard.vue'),
    meta: { role: 'Broadcasting' }
  },
  {
    path: '/distribusi/dashboard',
    component: () => import('@/views/Distribusi/Dashboard.vue'),
    meta: { role: 'Manager Distribusi' }
  },

  // Episode detail routes
  {
    path: '/creative/episodes/:id',
    component: () => import('@/views/Creative/EpisodeForm.vue')
  },
  {
    path: '/editor/episodes/:id',
    component: () => import('@/views/Editor/EpisodeEditor.vue')
  },
  {
    path: '/qc/episodes/:id',
    component: () => import('@/views/QC/EpisodeReview.vue')
  },
  {
    path: '/broadcasting/episodes/:id',
    component: () => import('@/views/Broadcasting/EpisodeBroadcast.vue')
  },

  // Workflow tracking
  {
    path: '/workflow/episodes/:id/status',
    component: () => import('@/views/Workflow/EpisodeStatus.vue')
  },

  // Analytics & KPI
  {
    path: '/distribusi/analytics',
    component: () => import('@/views/Distribusi/Analytics.vue')
  },
  {
    path: '/distribusi/kpi',
    component: () => import('@/views/Distribusi/KPI.vue')
  }
];
```

---

## 🔐 AUTHENTICATION & AUTHORIZATION

### **authService.js**

```javascript
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

const authService = {
  /**
   * Login
   */
  login: async (email, password) => {
    const response = await axios.post(`${API_BASE_URL}/login`, {
      email,
      password
    });
    
    const { token, user } = response.data.data;
    
    // Store token & user info
    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(user));
    localStorage.setItem('role', user.role);
    
    return response.data;
  },

  /**
   * Get current user
   */
  getCurrentUser: () => {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
  },

  /**
   * Check if user has role
   */
  hasRole: (role) => {
    const userRole = localStorage.getItem('role');
    return userRole === role;
  },

  /**
   * Logout
   */
  logout: () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    localStorage.removeItem('role');
  }
};

export default authService;
```

### **Axios Interceptor (setup.js)**

```javascript
import axios from 'axios';
import router from '@/router';

// Request interceptor - add token
axios.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - handle errors
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Unauthorized - redirect to login
      localStorage.clear();
      router.push('/login');
    }
    return Promise.reject(error);
  }
);
```

---

## 🎨 PAGE STRUCTURE RECOMMENDATIONS

### **1. Creative Dashboard** (`/creative/dashboard`)

**Components**:
- Pending episodes (belum submit script)
- Recent submitted scripts
- Rejected scripts (needs revision)
- Statistics (scripts submitted this month)

**Actions**:
- View episode detail
- Submit script
- View rejection feedback

---

### **2. Editor Dashboard** (`/editor/dashboard`)

**Components**:
- Pending editing (files ready)
- In progress editing
- Pending revision (from QC)
- Completed this week
- Statistics

**Actions**:
- Check files
- Start editing
- Upload final file
- Handle revision

---

### **3. QC Dashboard** (`/qc/dashboard`)

**Components**:
- Pending QC review
- Urgent episodes (< 3 days to air)
- Recent reviews
- Statistics (approval rate, average score)

**Actions**:
- Review episode
- View QC history
- Track revision status

---

### **4. Broadcasting Dashboard** (`/broadcasting/dashboard`)

**Components**:
- Ready to broadcast
- Pending metadata input
- Pending YouTube upload
- Pending website upload
- Recently aired
- Statistics

**Actions**:
- Update metadata
- Upload to YouTube
- Upload to website
- Mark as aired

---

### **5. Manager Distribusi Dashboard** (`/distribusi/dashboard`)

**Components**:
- Multi-platform analytics overview
- Top performing episodes
- Weekly KPI summary
- Recent aired episodes
- Platform-specific stats (YT, FB, IG, TikTok, Website)

**Actions**:
- View detailed analytics per platform
- Export KPI report
- View episode performance

---

## 📱 MOBILE RESPONSIVENESS

### **Breakpoints**:
```css
/* Mobile */
@media (max-width: 768px) {
  .workflow-steps { flex-direction: column; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Tablet */
@media (min-width: 769px) and (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

/* Desktop */
@media (min-width: 1025px) {
  .stats-grid { grid-template-columns: repeat(4, 1fr); }
}
```

---

## ⚡ PERFORMANCE TIPS

### **1. Lazy Loading**
```javascript
// Load components only when needed
const CreativeDashboard = () => import('@/views/Creative/Dashboard.vue');
```

### **2. Caching**
```javascript
// Cache episode data untuk 5 menit
const cache = new Map();
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

const getCachedData = async (key, fetchFunction) => {
  const cached = cache.get(key);
  if (cached && Date.now() - cached.timestamp < CACHE_DURATION) {
    return cached.data;
  }
  
  const data = await fetchFunction();
  cache.set(key, { data, timestamp: Date.now() });
  return data;
};
```

### **3. Debouncing**
```javascript
// Untuk search/filter
import { debounce } from 'lodash';

const searchEpisodes = debounce(async (query) => {
  const results = await api.search(query);
  // Update results
}, 300);
```

---

## 🧪 TESTING CHECKLIST

### **Untuk Setiap Role Dashboard**:
- [ ] Can load my tasks
- [ ] Can view episode details
- [ ] Can perform role-specific actions
- [ ] Can view statistics
- [ ] Notifications appear
- [ ] Loading states work
- [ ] Error handling works

### **Untuk Workflow**:
- [ ] Status updates in real-time
- [ ] Progress bar shows correct percentage
- [ ] Each step shows correct status
- [ ] Deadline warnings appear
- [ ] Overdue alerts work

### **Untuk File Uploads**:
- [ ] Can upload files
- [ ] Progress bar shows
- [ ] File validation works
- [ ] Error messages clear
- [ ] Success confirmation shows

---

## 📚 DOCUMENTATION REFERENCE

Untuk detail lengkap setiap endpoint:
👉 **`COMPLETE_WORKFLOW_API_DOCUMENTATION.md`**

Untuk quick reference:
👉 **`API_CHEAT_SHEET_WORKFLOW.md`**

---

## ✅ SUMMARY

**Yang Perlu Dibuat di Frontend**:
1. ✅ **7 API Service files** (workflowApi, creativeApi, qcApi, dll)
2. ✅ **10 Dashboard pages** (satu per role)
3. ✅ **Episode detail pages** (view berbeda per role)
4. ✅ **Workflow status visualizer** (progress bar & timeline)
5. ✅ **Notification system** (badge & dropdown)
6. ✅ **File upload components** (dengan progress)
7. ✅ **Forms** (script submission, QC review, metadata, dll)
8. ✅ **Statistics widgets** (charts & metrics)

**Tools & Libraries yang Direkomendasikan**:
- **Vue.js 3** (atau React)
- **Pinia/Vuex** (state management)
- **Axios** (HTTP client)
- **Chart.js** (untuk charts & analytics)
- **Element Plus / Vuetify** (UI framework)
- **Day.js** (date handling)
- **VeeValidate** (form validation)

---

**🚀 Backend Ready - Frontend Integration Can Start Now!**

Semua API sudah documented lengkap dan siap digunakan! 🎉

