# 🎵 MUSIC PROGRAM - ALL ROLES TESTING GUIDE

## 📋 **OVERVIEW**

Berdasarkan analisis routes yang tersedia, berikut adalah **semua roles** dan **endpoints** yang bisa di-test dalam Music Program system:

---

## 🎯 **ROLES YANG TERSEDIA:**

### **1. Music Arranger** (60 routes)
### **2. Producer** (43 routes) 
### **3. Sound Engineer** (6 routes)
### **4. Creative** (9 routes)
### **5. Manager Program** (5 routes)

---

## 🎵 **1. MUSIC ARRANGER ROUTES**

### **Base URL:** `http://localhost:8000/api/music/music-arranger`

### **✅ AVAILABLE ENDPOINTS:**

#### **Dashboard & Submissions:**
```http
GET    /dashboard                    # Dashboard
GET    /submissions                 # Get all submissions
GET    /submissions/{id}            # Get submission by ID
POST   /submissions                 # Create new submission
PUT    /submissions/{id}            # Update submission
DELETE /submissions/{id}             # Delete submission
```

#### **Requests Management:**
```http
GET    /requests                     # Get my requests
GET    /requests/{id}                # Get request by ID
POST   /requests                     # Submit new request
PUT    /requests/{id}                # Update request
DELETE /requests/{id}                # Cancel request
PUT    /requests/{id}/update         # Update request (alias)
POST   /requests/{id}/update         # Update request (alias)
```

#### **Songs CRUD (FULL):**
```http
GET    /songs                        # Get all songs
POST   /songs                        # Create new song
PUT    /songs/{id}                   # Update song (full)
PATCH  /songs/{id}                    # Update song (partial)
DELETE /songs/{id}                    # Delete song
GET    /songs/{id}/audio             # Get song audio
```

#### **Singers CRUD (FULL):**
```http
GET    /singers                       # Get all singers
POST   /singers                       # Create new singer
PUT    /singers/{id}                  # Update singer (full)
PATCH  /singers/{id}                  # Update singer (partial)
POST   /singers/{id}                  # Update singer (alias)
DELETE /singers/{id}                  # Delete singer
GET    /singers-fixed                 # Get singers (fixed version)
```

#### **Workflow Actions:**
```http
POST   /music-workflow/music-arranger/workflow/{id}/arrange
POST   /music-workflow/music-arranger/workflow/{id}/start-arranging
POST   /music-workflow/music-arranger/workflow/{id}/submit-arrangement
POST   /music-workflow/music-arranger/workflow/{id}/resubmit-arrangement
POST   /music-workflow/music-arranger/submissions/create-with-choice
```

#### **History & Utilities:**
```http
GET    /music-arranger-history/submissions
GET    /music-arranger-history/submissions/{id}
PUT    /music-arranger-history/submissions/{id}
DELETE /music-arranger-history/submissions/{id}
POST   /music-arranger-history/submissions/{id}/cancel
GET    /music-arranger-history/submissions/{id}/download
POST   /music-arranger-history/submissions/{id}/resubmit
POST   /music-arranger-history/submissions/{id}/submit
GET    /test-profile-url
```

---

## 🎬 **2. PRODUCER ROUTES**

### **Base URL:** `http://localhost:8000/api/music/producer/music`

### **✅ AVAILABLE ENDPOINTS:**

#### **Dashboard:**
```http
GET    /dashboard                    # Dashboard
```

#### **Requests Management:**
```http
GET    /requests                     # Get all requests
GET    /requests/pending             # Get pending requests
GET    /requests/approved             # Get approved requests
GET    /requests/rejected             # Get rejected requests
GET    /requests/my                   # Get my requests
GET    /requests/status/{status}      # Get requests by status
GET    /requests/{id}                 # Get request by ID
POST   /requests/{id}/approve         # Approve request
POST   /requests/{id}/reject          # Reject request
POST   /requests/{id}/take            # Take request
```

#### **Songs Management (LIMITED):**
```http
GET    /songs                        # Get all songs
POST   /songs                        # Create new song
GET    /songs/{id}/audio              # Get song audio
# ❌ NO PUT/PATCH/DELETE for songs
```

#### **Singers CRUD (FULL):**
```http
GET    /singers                       # Get all singers
POST   /singers                       # Create new singer
PUT    /singers/{id}                  # Update singer (full)
PATCH  /singers/{id}                  # Update singer (partial)
DELETE /singers/{id}                  # Delete singer
```

#### **Phase 2 - Creative Work:**
```http
GET    /submissions/{id}/creative-work
POST   /submissions/{id}/review-creative-work
POST   /submissions/{id}/assign-teams
POST   /schedules/{id}/cancel
POST   /schedules/{id}/reschedule
```

#### **Alternative Routes (Legacy):**
```http
GET    /api/producer/music/dashboard
GET    /api/producer/music/requests
GET    /api/producer/music/requests/approved
GET    /api/producer/music/requests/my
GET    /api/producer/music/requests/pending
GET    /api/producer/music/requests/rejected
GET    /api/producer/music/requests/status/{status}
GET    /api/producer/music/requests/{id}
POST   /api/producer/music/requests/{id}/approve
POST   /api/producer/music/requests/{id}/modify
POST   /api/producer/music/requests/{id}/reject
POST   /api/producer/music/requests/{id}/take
GET    /api/producer/music/singers
POST   /api/producer/music/singers
PUT    /api/producer/music/singers/{id}
PATCH  /api/producer/music/singers/{id}
DELETE /api/producer/music/singers/{id}
GET    /api/producer/music/songs
POST   /api/producer/music/songs
PUT    /api/producer/music/songs/{id}
PATCH  /api/producer/music/songs/{id}
DELETE /api/producer/music/songs/{id}
GET    /api/producer/music/songs/{id}/audio
GET    /api/producer/music/submissions
```

---

## 🔊 **3. SOUND ENGINEER ROUTES**

### **Base URL:** `http://localhost:8000/api/music/music-workflow`

### **✅ AVAILABLE ENDPOINTS:**

#### **Workflow Actions:**
```http
POST   /sound-engineer/workflow/{id}/accept
POST   /sound-engineer/workflow/{id}/complete
POST   /sound-engineer/workflow/{id}/reject-to-arranger
POST   /sound-engineer/workflow/{id}/final-complete
```

#### **Producer Request Sound Engineering:**
```http
POST   /producer/workflow/{id}/request-sound-engineering
```

---

## 🎨 **4. CREATIVE ROUTES**

### **Base URL:** `http://localhost:8000/api/music/creative`

### **✅ AVAILABLE ENDPOINTS:**

#### **Creative Work Management:**
```http
GET    /submissions/{id}/creative-work
PATCH  /submissions/{id}/creative-work
POST   /submissions/{id}/submit-creative-work
```

#### **Workflow Actions:**
```http
POST   /music-workflow/creative/workflow/{id}/accept
POST   /music-workflow/creative/workflow/{id}/submit-work
```

#### **Producer Review Creative:**
```http
GET    /producer/submissions/{id}/creative-work
POST   /producer/submissions/{id}/review-creative-work
```

---

## 💼 **5. MANAGER PROGRAM ROUTES**

### **Base URL:** `http://localhost:8000/api/music/manager-program`

### **✅ AVAILABLE ENDPOINTS:**

#### **Dashboard & Budget Management:**
```http
GET    /dashboard                    # Dashboard
GET    /budget-approvals            # Get all budget approvals
GET    /budget-approvals/{id}        # Get budget approval detail
POST   /budget-approvals/{id}/approve # Approve budget
POST   /budget-approvals/{id}/reject  # Reject budget
```

---

## 🧪 **TESTING SCENARIOS BY ROLE**

### **🎵 Music Arranger Testing:**
```http
# 1. Dashboard
GET /music/music-arranger/dashboard

# 2. Songs CRUD
GET /music/music-arranger/songs
POST /music/music-arranger/songs
PUT /music/music-arranger/songs/{id}
DELETE /music/music-arranger/songs/{id}

# 3. Singers CRUD
GET /music/music-arranger/singers
POST /music/music-arranger/singers
PUT /music/music-arranger/singers/{id}
DELETE /music/music-arranger/singers/{id}

# 4. Submissions
GET /music/music-arranger/submissions
POST /music/music-arranger/submissions
POST /music/music-workflow/music-arranger/workflow/{id}/submit-arrangement
```

### **🎬 Producer Testing:**
```http
# 1. Dashboard
GET /music/producer/music/dashboard

# 2. Requests Management
GET /music/producer/music/requests
GET /music/producer/music/requests/pending
POST /music/producer/music/requests/{id}/approve
POST /music/producer/music/requests/{id}/reject

# 3. Songs (Limited)
GET /music/producer/music/songs
POST /music/producer/music/songs
# ❌ NO PUT/DELETE for songs

# 4. Singers (Full CRUD)
GET /music/producer/music/singers
POST /music/producer/music/singers
PUT /music/producer/music/singers/{id}
DELETE /music/producer/music/singers/{id}

# 5. Phase 2 - Creative Work
GET /music/producer/submissions/{id}/creative-work
POST /music/producer/submissions/{id}/review-creative-work
POST /music/producer/submissions/{id}/assign-teams
```

### **🔊 Sound Engineer Testing:**
```http
# 1. Accept Work
POST /music/music-workflow/sound-engineer/workflow/{id}/accept

# 2. Complete Work
POST /music/music-workflow/sound-engineer/workflow/{id}/complete
POST /music/music-workflow/sound-engineer/workflow/{id}/final-complete

# 3. Reject Back to Arranger
POST /music/music-workflow/sound-engineer/workflow/{id}/reject-to-arranger
```

### **🎨 Creative Testing:**
```http
# 1. Get Creative Work
GET /music/creative/submissions/{id}/creative-work

# 2. Submit Creative Work
POST /music/creative/submissions/{id}/submit-creative-work

# 3. Update Creative Work
PATCH /music/creative/submissions/{id}/creative-work

# 4. Accept Work
POST /music/music-workflow/creative/workflow/{id}/accept
```

### **💼 Manager Program Testing:**
```http
# 1. Dashboard
GET /music/manager-program/dashboard

# 2. Budget Approvals
GET /music/manager-program/budget-approvals
GET /music/manager-program/budget-approvals/{id}

# 3. Approve/Reject Budget
POST /music/manager-program/budget-approvals/{id}/approve
POST /music/manager-program/budget-approvals/{id}/reject
```

---

## ⚠️ **LIMITATIONS FOUND:**

### **✅ Producer - FIXED:**
- **FULL CRUD** for songs now available
- **PUT/PATCH/DELETE** for songs in `/music/producer/music/songs/{id}` ✅
- **GET, POST, PUT, PATCH, DELETE** all available for songs

### **✅ Full CRUD Available:**
- **Music Arranger**: Full CRUD for songs & singers
- **Producer**: Full CRUD for songs & singers ✅ FIXED
- **Creative**: Creative work management only
- **Sound Engineer**: Workflow actions only
- **Manager Program**: Budget approval only

---

## 🔧 **AUDIO FILE MANAGEMENT:**

### **Upload Audio:**
```http
POST /music/audio/{song_id}/upload
Content-Type: multipart/form-data

audio: [audio file]  # Field name: "audio" ✅ FIXED
```

### **Get Audio Info:**
```http
GET /music/audio/{song_id}/info
```

### **Stream Audio:**
```http
GET /music/audio/{song_id}
```

### **Delete Audio:**
```http
DELETE /music/audio/{song_id}
```

---

## 📊 **TESTING CHECKLIST BY ROLE:**

### **🎵 Music Arranger (60 routes) ✅**
- [ ] Dashboard
- [ ] Songs CRUD (Full)
- [ ] Singers CRUD (Full)
- [ ] Submissions CRUD
- [ ] Requests CRUD
- [ ] Workflow actions
- [ ] History management

### **🎬 Producer (43 routes) ✅**
- [ ] Dashboard
- [ ] Requests management
- [ ] Songs CRUD (Full) ✅ FIXED
- [ ] Singers CRUD (Full)
- [ ] Creative work review
- [ ] Team assignment
- [ ] Schedule management

### **🔊 Sound Engineer (6 routes) ✅**
- [ ] Accept work
- [ ] Complete work
- [ ] Final complete
- [ ] Reject to arranger

### **🎨 Creative (9 routes) ✅**
- [ ] Get creative work
- [ ] Submit creative work
- [ ] Update creative work
- [ ] Accept work

### **💼 Manager Program (5 routes) ✅**
- [ ] Dashboard
- [ ] Budget approvals
- [ ] Approve budget
- [ ] Reject budget

---

## 🚀 **READY FOR TESTING**

**Total Routes**: 123+ endpoints  
**Roles**: 5 roles  
**CRUD Operations**: Varies by role  
**File Management**: Audio upload/stream/delete  
**Workflow**: Complete Phase 1 & 2  

**🎵 Music Program - All Roles Testing Ready! 🎵**

