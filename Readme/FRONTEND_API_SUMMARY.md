# 📱 RINGKASAN API UNTUK FRONTEND - WORKFLOW HCI

**Untuk**: Frontend Developer  
**Backend Status**: ✅ Ready  
**Base URL**: `http://localhost:8000/api`

---

## 🎯 QUICK SUMMARY

Backend telah menyediakan **61 API endpoints** untuk workflow Program Regular HCI.

**10 Role Workflow**:
Manager Program → Producer → Creative → Produksi → Editor → QC → Broadcasting → Promosi → Design Grafis → Manager Distribusi

---

## 📋 API ENDPOINTS PER ROLE

### **1. CREATIVE (Kreatif)** - 2 endpoints

```javascript
// Submit script & rundown
POST /api/workflow/creative/episodes/{id}/script
Body: {
  title, script, rundown, talent_data, location, production_date, budget_talent
}

// Get workflow status
GET /api/workflow/episodes/{id}/status
```

---

### **2. PRODUCER** - 2 endpoints

```javascript
// Review & approve/reject rundown
POST /api/workflow/producer/episodes/{id}/review-rundown
Body: {
  decision: 'approved' | 'rejected',
  notes,
  revision_points // jika rejected
}

// Get dashboard
GET /api/workflow/dashboard
```

---

### **3. PRODUKSI** - 2 endpoints

```javascript
// Request equipment
POST /api/workflow/produksi/episodes/{id}/request-equipment
Body: {
  equipment_list: [{ name, quantity, notes }]
}

// Complete shooting
POST /api/workflow/produksi/episodes/{id}/complete-shooting
Body: {
  raw_file_urls: [url1, url2],
  shooting_notes,
  actual_shooting_date
}
```

---

### **4. EDITOR** - 8 endpoints

```javascript
GET  /api/editor/my-tasks
GET  /api/editor/episodes/pending
GET  /api/editor/episodes/{id}/check-files
POST /api/editor/episodes/{id}/start-editing
POST /api/editor/episodes/{id}/upload-draft        // Optional
POST /api/editor/episodes/{id}/complete            // Upload final file
POST /api/editor/episodes/{id}/handle-revision     // Handle QC revision
GET  /api/editor/statistics
```

**Key Request**:
```javascript
// Complete editing (FILE UPLOAD)
POST /api/editor/episodes/{id}/complete
FormData: {
  final_file: [Video File, max 1GB],
  completion_notes: string,
  duration_minutes: number
}

// OR (URL-based)
Body: {
  final_url: string,
  completion_notes: string,
  duration_minutes: number
}
```

---

### **5. QC (Quality Control)** - 7 endpoints

```javascript
GET  /api/qc/episodes/pending
GET  /api/qc/episodes/{id}
POST /api/qc/episodes/{id}/review
GET  /api/qc/episodes/{id}/history
GET  /api/qc/episodes/{id}/revision-feedback
GET  /api/qc/my-tasks
GET  /api/qc/statistics
```

**Key Request**:
```javascript
// Submit QC review
POST /api/qc/episodes/{id}/review
Body: {
  decision: 'approved' | 'revision_needed',
  quality_score: 1-10,
  video_quality_score: 1-10,     // Optional
  audio_quality_score: 1-10,     // Optional
  content_quality_score: 1-10,   // Optional
  notes: string,
  revision_points: [              // Jika revision_needed
    {
      category: 'video' | 'audio' | 'content' | 'subtitle' | 'transition' | 'effect',
      description: string,
      priority: 'low' | 'medium' | 'high' | 'critical'
    }
  ]
}
```

---

### **6. DESIGN GRAFIS** - 8 endpoints

```javascript
GET  /api/design-grafis/episodes/pending
GET  /api/design-grafis/episodes/{id}
POST /api/design-grafis/episodes/{id}/receive-assets
POST /api/design-grafis/episodes/{id}/upload-thumbnail-youtube
POST /api/design-grafis/episodes/{id}/upload-thumbnail-bts
POST /api/design-grafis/episodes/{id}/complete
GET  /api/design-grafis/my-tasks
GET  /api/design-grafis/statistics
```

**Key Request**:
```javascript
// Upload thumbnail (FILE UPLOAD)
POST /api/design-grafis/episodes/{id}/upload-thumbnail-youtube
FormData: {
  thumbnail_file: [Image File JPG/PNG, max 2MB],
  design_notes: string
}
```

---

### **7. BROADCASTING** - 9 endpoints

```javascript
GET  /api/broadcasting/episodes/ready
GET  /api/broadcasting/episodes/{id}
PUT  /api/broadcasting/episodes/{id}/metadata
POST /api/broadcasting/episodes/{id}/upload-youtube
POST /api/broadcasting/episodes/{id}/youtube-link
POST /api/broadcasting/episodes/{id}/upload-website
POST /api/broadcasting/episodes/{id}/complete
GET  /api/broadcasting/my-tasks
GET  /api/broadcasting/statistics
```

**Key Requests**:
```javascript
// 1. Update metadata SEO
PUT /api/broadcasting/episodes/{id}/metadata
Body: {
  seo_title: string,
  seo_description: string,
  seo_tags: [string],
  youtube_category: string,
  youtube_privacy: 'public' | 'unlisted' | 'private'
}

// 2. Set YouTube link
POST /api/broadcasting/episodes/{id}/youtube-link
Body: {
  youtube_url: string,
  youtube_video_id: string
}

// 3. Set Website URL
POST /api/broadcasting/episodes/{id}/upload-website
Body: {
  website_url: string
}

// 4. Complete (mark as aired)
POST /api/broadcasting/episodes/{id}/complete
Body: {
  broadcast_notes: string
}
```

---

### **8. PROMOSI** - 7 endpoints

```javascript
GET  /api/promosi/episodes/shooting-schedule
GET  /api/promosi/episodes/published
POST /api/promosi/episodes/{id}/create-bts
POST /api/promosi/episodes/{id}/create-highlight
POST /api/promosi/episodes/{id}/share-social-media
GET  /api/promosi/my-tasks
GET  /api/promosi/statistics
```

**Key Requests**:
```javascript
// TAHAP 1: Create BTS (saat produksi)
POST /api/promosi/episodes/{id}/create-bts
FormData: {
  bts_video_files: [File],
  talent_photo_files: [File],
  notes: string
}

// TAHAP 2: Create Highlight (setelah aired)
POST /api/promosi/episodes/{id}/create-highlight
FormData: {
  ig_story_files: [File],
  fb_reel_files: [File],
  notes: string
}

// Share to social media
POST /api/promosi/episodes/{id}/share-social-media
FormData: {
  platform: 'facebook' | 'instagram' | 'tiktok' | 'whatsapp',
  post_url: string,
  proof_screenshot_file: [File],
  notes: string
}
```

---

### **9. MANAGER DISTRIBUSI** - 9 endpoints

```javascript
GET  /api/distribusi/dashboard
GET  /api/distribusi/analytics/youtube?period=30days
GET  /api/distribusi/analytics/facebook
GET  /api/distribusi/analytics/instagram
GET  /api/distribusi/analytics/tiktok
GET  /api/distribusi/analytics/website
GET  /api/distribusi/kpi/weekly?week_start=2025-01-06
POST /api/distribusi/kpi/export
GET  /api/distribusi/episodes/{id}/performance
```

**Key Request**:
```javascript
// Get dashboard (overview semua platform)
GET /api/distribusi/dashboard
Response: {
  overview: { total_aired_episodes, aired_this_month, ... },
  platforms: {
    youtube: { total_videos, total_views, subscriber_count, ... },
    facebook: { ... },
    instagram: { ... },
    tiktok: { ... },
    website: { ... }
  },
  recent_episodes: [...],
  top_performing_episodes: [...]
}
```

---

## 🔄 WORKFLOW STATUS VALUES

### **Episode Status** (field: `status`):
```javascript
const episodeStatuses = {
  'planning': 'Planning',
  'script_review': 'Script Review',
  'rundown_approved': 'Rundown Approved',
  'post_production': 'Post Production',
  'revision': 'Needs Revision',
  'ready_to_air': 'Ready to Air',
  'aired': 'Aired'
};
```

### **Editing Status** (field: `editing_status`):
```javascript
const editingStatuses = {
  'pending': 'Pending',
  'in_progress': 'In Progress',
  'draft': 'Draft Uploaded',
  'completed': 'Completed',
  'revision': 'Needs Revision'
};
```

---

## 📊 RESPONSE FORMAT

### **Success Response**:
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation successful"
}
```

### **Error Response**:
```json
{
  "success": false,
  "message": "Error message",
  "errors": {  // Untuk validation errors
    "field_name": ["Error detail"]
  }
}
```

---

## 🎨 UI COMPONENTS CHECKLIST

### **Per Role Dashboard**:
- [ ] Header dengan role name & user info
- [ ] Stats cards (4 metrics: pending, in-progress, completed, overdue)
- [ ] Task list (filterable & sortable)
- [ ] Quick actions
- [ ] Search & filter
- [ ] Pagination
- [ ] Refresh button
- [ ] Notification bell

### **Episode Detail Page**:
- [ ] Episode header (title, air date, status)
- [ ] Workflow progress visualizer
- [ ] Talent data section
- [ ] Files section (script, rundown, videos, thumbnails)
- [ ] Timeline/history
- [ ] Role-specific action buttons
- [ ] Notes/comments section

### **Forms**:
- [ ] Script submission form (Creative)
- [ ] Rundown review form (Producer)
- [ ] Equipment request form (Produksi)
- [ ] File upload form dengan progress (Editor, Design, Promosi)
- [ ] QC review form dengan scoring
- [ ] Metadata form (Broadcasting)

### **Analytics Dashboard** (Manager Distribusi):
- [ ] Multi-platform overview
- [ ] Charts (line, bar, pie)
- [ ] KPI cards
- [ ] Episode performance table
- [ ] Date range picker
- [ ] Export button

---

## 💡 QUICK START TEMPLATE

### **Minimal Frontend Structure**:

```
src/
├── services/
│   ├── api/
│   │   ├── workflowApi.js
│   │   ├── creativeApi.js
│   │   ├── qcApi.js
│   │   ├── editorApi.js
│   │   ├── broadcastingApi.js
│   │   ├── designGrafisApi.js
│   │   ├── promosiApi.js
│   │   └── distribusiApi.js
│   ├── authService.js
│   └── notificationService.js
│
├── stores/
│   └── workflowStore.js
│
├── views/
│   ├── Creative/
│   │   ├── Dashboard.vue
│   │   └── EpisodeForm.vue
│   ├── Editor/
│   │   ├── Dashboard.vue
│   │   └── EpisodeEditor.vue
│   ├── QC/
│   │   ├── Dashboard.vue
│   │   └── EpisodeReview.vue
│   ├── Broadcasting/
│   │   ├── Dashboard.vue
│   │   └── EpisodeBroadcast.vue
│   └── Distribusi/
│       ├── Dashboard.vue
│       └── Analytics.vue
│
├── components/
│   ├── WorkflowStatus.vue
│   ├── QCReviewForm.vue
│   ├── FileUploader.vue
│   ├── MyTasksList.vue
│   └── StatisticsCard.vue
│
└── router/
    └── index.js
```

---

## 🚀 IMPLEMENTATION PRIORITY

### **Phase 1 (Week 1) - Core Functions**:
1. ✅ Setup API services
2. ✅ Create authentication
3. ✅ Build 3 main dashboards: Creative, Editor, QC
4. ✅ Implement workflow status component

### **Phase 2 (Week 2) - Complete Workflow**:
5. ✅ Build remaining dashboards (Broadcasting, Design, Promosi, Distribusi)
6. ✅ Implement file upload components
7. ✅ Build forms (script submission, QC review, metadata)

### **Phase 3 (Week 3) - Enhancement**:
8. ✅ Add notification system
9. ✅ Implement charts & analytics
10. ✅ Add real-time updates
11. ✅ Polish UI/UX

---

## 📞 API TESTING

### **Postman Collection**:
Import dari: `COMPLETE_WORKFLOW_API_DOCUMENTATION.md`

### **Quick Test**:
```bash
# Get workflow dashboard
curl http://localhost:8000/api/workflow/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📚 FULL DOCUMENTATION

**Detail lengkap ada di**:
1. 📖 **`FRONTEND_INTEGRATION_GUIDE.md`** ⭐ **BACA INI DULU!**
2. 📋 **`COMPLETE_WORKFLOW_API_DOCUMENTATION.md`** - Complete API reference
3. 🚀 **`API_CHEAT_SHEET_WORKFLOW.md`** - Quick endpoints reference

---

## ✅ WHAT YOU NEED TO KNOW

### **Authentication**:
```javascript
// All requests need:
headers: {
  'Authorization': 'Bearer ' + token,
  'Content-Type': 'application/json'
}
```

### **File Uploads**:
```javascript
// Use FormData for file uploads:
const formData = new FormData();
formData.append('final_file', videoFile);
formData.append('completion_notes', notes);

// Set Content-Type to multipart/form-data
headers: {
  'Content-Type': 'multipart/form-data'
}
```

### **Error Handling**:
```javascript
try {
  const response = await api.submitScript(data);
  // Success
} catch (error) {
  // error.response.data.message = Error message
  // error.response.data.errors = Validation errors (jika 422)
}
```

---

## 🎯 DATA YANG PENTING

### **Episode Status Flow**:
```
planning → script_review → rundown_approved → post_production → 
revision (optional) → ready_to_air → aired
```

### **Role-Specific Actions**:

| Role | Main Action | Endpoint |
|------|-------------|----------|
| Creative | Submit Script | `POST /workflow/creative/episodes/{id}/script` |
| Producer | Review Rundown | `POST /workflow/producer/episodes/{id}/review-rundown` |
| Produksi | Complete Shooting | `POST /workflow/produksi/episodes/{id}/complete-shooting` |
| Editor | Upload Final | `POST /editor/episodes/{id}/complete` |
| QC | Submit Review | `POST /qc/episodes/{id}/review` |
| Design | Upload Thumbnail | `POST /design-grafis/episodes/{id}/upload-thumbnail-youtube` |
| Broadcasting | Set Links | `POST /broadcasting/episodes/{id}/youtube-link` |
| Promosi | Create BTS | `POST /promosi/episodes/{id}/create-bts` |

---

## 💡 TIPS PENTING

### **1. My Tasks Pattern**:
Setiap role punya endpoint `/my-tasks`:
```javascript
GET /api/{role}/my-tasks

// Returns:
{
  pending: [...],
  in_progress: [...],
  completed_this_week: [...]
}
```

### **2. Statistics Pattern**:
Setiap role punya endpoint `/statistics`:
```javascript
GET /api/{role}/statistics

// Returns stats specific to that role
```

### **3. Dual Upload Support**:
Semua file upload support 2 cara:
- **Multipart upload**: Actual file
- **URL-based**: Jika file sudah di storage

```javascript
// Option 1: Upload file
FormData with file

// Option 2: Submit URL
JSON with URL field
```

---

## 🎨 UI MOCKUP SUGGESTIONS

### **Dashboard Layout**:
```
┌─────────────────────────────────────────┐
│ [Logo] My Dashboard - {Role}    [🔔 3]  │
├─────────────────────────────────────────┤
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐   │
│ │  5   │ │  3   │ │ 12   │ │  2   │   │
│ │Pend. │ │Prog. │ │Done  │ │Rev.  │   │
│ └──────┘ └──────┘ └──────┘ └──────┘   │
├─────────────────────────────────────────┤
│ MY TASKS                                │
│ ┌─────────────────────────────────────┐ │
│ │ [⚠️] Episode 5 - URGENT (2 days)  │ │
│ │      Air Date: Jan 15, 2025         │ │
│ │      [View Details] [Start Work]    │ │
│ └─────────────────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │
│ │ [🟡] Episode 3 - In Progress       │ │
│ │      Started: 2 hours ago           │ │
│ │      [Continue] [Upload File]       │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

### **Episode Detail Layout**:
```
┌─────────────────────────────────────────┐
│ Episode 5: Kasih Tuhan                  │
│ Status: Post Production | Air: Jan 15   │
├─────────────────────────────────────────┤
│ WORKFLOW PROGRESS                       │
│ [✅]──[✅]──[✅]──[🔄]──[⏳]──[⏳]      │
│  Cr   Pr   Pd   Ed   QC   Br            │
├─────────────────────────────────────────┤
│ TALENT DATA                             │
│ Host: John Doe                          │
│ Narasumber: Dr. Jane Smith              │
├─────────────────────────────────────────┤
│ FILES                                   │
│ Script: ✅ view                         │
│ Raw Files: ✅ 3 files                   │
│ Final File: 🔄 Uploading...             │
├─────────────────────────────────────────┤
│ [Upload Final File] [View History]      │
└─────────────────────────────────────────┘
```

---

## ⚡ QUICK CODE SNIPPETS

### **Load Episode & Display**:
```javascript
// In component
async mounted() {
  const response = await workflowApi.getEpisodeStatus(this.$route.params.id);
  this.episode = response.data.episode;
  this.workflowSteps = response.data.workflow_steps;
}
```

### **Submit Form**:
```javascript
async handleSubmit() {
  try {
    await creativeApi.submitScript(this.episodeId, this.formData);
    this.$notify({ type: 'success', message: 'Script submitted!' });
    this.$router.push('/creative/dashboard');
  } catch (error) {
    this.$notify({ type: 'error', message: error.response?.data?.message });
  }
}
```

### **File Upload dengan Progress**:
```javascript
const formData = new FormData();
formData.append('final_file', this.videoFile);

await axios.post(url, formData, {
  headers: { 'Content-Type': 'multipart/form-data' },
  onUploadProgress: (e) => {
    this.uploadProgress = Math.round((e.loaded * 100) / e.total);
  }
});
```

---

## 🎉 SUMMARY

**Backend Provides**:
- ✅ 61 API Endpoints
- ✅ Complete workflow coverage
- ✅ File upload support
- ✅ Notification system
- ✅ Analytics framework

**Frontend Needs to Build**:
- 📱 10 Dashboard pages
- 📝 8-10 Forms
- 📊 Analytics views
- 🔔 Notification UI
- 📈 Charts & visualizations

**Estimated Time**: 2-3 weeks (1 frontend developer)

---

**🚀 Ready to Build? Start Here:**
👉 **`FRONTEND_INTEGRATION_GUIDE.md`**

**Need API Details?**
👉 **`COMPLETE_WORKFLOW_API_DOCUMENTATION.md`**

**Quick Reference?**
👉 **`API_CHEAT_SHEET_WORKFLOW.md`**

---

**Backend sudah siap, tinggal frontend connect aja!** 🎊

