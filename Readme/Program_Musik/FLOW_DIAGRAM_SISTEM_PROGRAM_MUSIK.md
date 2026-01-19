# 📊 FLOW DIAGRAM SISTEM PROGRAM MUSIK

**Visual Guide untuk Testing**

---

## 🎯 DIAGRAM FLOW LENGKAP

```
┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 1: SETUP PROGRAM                        │
└─────────────────────────────────────────────────────────────────┘

Manager Program
    │
    ├─► Create Program
    │   POST /api/live-tv/programs
    │   └─► System auto-generate 53 episodes
    │
    ├─► Assign Production Team
    │   POST /api/live-tv/manager-program/episodes/{id}/assign-team
    │
    └─► Submit Program
        POST /api/live-tv/programs/{id}/submit
        │
        ▼
    Manager Broadcasting
        │
        ├─► Approve Program
        │   POST /api/live-tv/manager-broadcasting/schedules/{id}/approve
        │   └─► Status: approved
        │
        └─► Reject Program
            POST /api/live-tv/manager-broadcasting/schedules/{id}/reject
            └─► Status: rejected

┌─────────────────────────────────────────────────────────────────┐
│              PHASE 2: MUSIC ARRANGEMENT                         │
└─────────────────────────────────────────────────────────────────┘

Music Arranger
    │
    ├─► Create Arrangement (Song Proposal)
    │   POST /api/live-tv/roles/music-arranger/arrangements
    │   └─► Status: song_proposal
    │   └─► Notification → Producer
    │
    └─► Submit Song Proposal
        POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit-song-proposal
        │
        ▼
    Producer
        │
        ├─► Approve Song Proposal
        │   POST /api/live-tv/producer/approvals/{id}/approve
        │   └─► Status: song_approved
        │   └─► Notification → Music Arranger
        │
        └─► Reject Song Proposal
            POST /api/live-tv/producer/approvals/{id}/reject
            └─► Status: song_rejected

Music Arranger (jika approved)
    │
    ├─► Accept Work
    │   POST /api/live-tv/roles/music-arranger/arrangements/{id}/accept-work
    │   └─► Status: arrangement_in_progress
    │
    ├─► Upload Arrangement File
    │   PUT /api/live-tv/roles/music-arranger/arrangements/{id}
    │
    └─► Submit Arrangement
        POST /api/live-tv/roles/music-arranger/arrangements/{id}/submit
        └─► Status: arrangement_submitted
        └─► Notification → Producer
        │
        ▼
    Producer
        │
        ├─► Approve Arrangement
        │   POST /api/live-tv/producer/approvals/{id}/approve
        │   └─► Status: arrangement_approved
        │   └─► Auto-create: Recording Task (Sound Engineer)
        │   └─► Auto-create: Creative Work (Creative)
        │   └─► Notification → Sound Engineer, Creative
        │
        └─► Reject Arrangement
            POST /api/live-tv/producer/approvals/{id}/reject
            └─► Status: arrangement_rejected

┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 3: RECORDING                            │
└─────────────────────────────────────────────────────────────────┘

Sound Engineer
    │
    ├─► View Recording Tasks
    │   GET /api/live-tv/roles/sound-engineer/recordings
    │
    ├─► Accept Work
    │   POST /api/live-tv/roles/sound-engineer/recordings/{id}/accept-work
    │
    ├─► Start Recording
    │   POST /api/live-tv/roles/sound-engineer/recordings/{id}/start
    │   └─► Status: in_progress
    │
    ├─► Upload Recording File
    │   PUT /api/live-tv/roles/sound-engineer/recordings/{id}
    │
    └─► Complete Recording
        POST /api/live-tv/roles/sound-engineer/recordings/{id}/complete
        └─► Status: completed
        └─► Notification → Producer
        │
        ▼
    Producer
        │
        └─► Review Recording
            POST /api/live-tv/producer/approvals/{id}/approve
            └─► Status: reviewed

┌─────────────────────────────────────────────────────────────────┐
│                  PHASE 4: CREATIVE WORK                          │
└─────────────────────────────────────────────────────────────────┘

Creative
    │
    ├─► View Creative Works
    │   GET /api/live-tv/roles/creative/works
    │
    ├─► Accept Work
    │   POST /api/live-tv/roles/creative/works/{id}/accept-work
    │   └─► Status: in_progress
    │
    ├─► Update Creative Work
    │   PUT /api/live-tv/roles/creative/works/{id}
    │   └─► Input: script, storyboard, budget, schedules
    │
    └─► Submit Creative Work
        POST /api/live-tv/roles/creative/works/{id}/submit
        └─► Status: submitted
        └─► Notification → Producer
        │
        ▼
    Producer
        │
        ├─► Approve Creative Work
        │   POST /api/live-tv/producer/approvals/{id}/approve
        │   └─► Status: approved
        │   └─► Auto-create: Produksi Work
        │   └─► Notification → Produksi
        │
        └─► Reject Creative Work
            POST /api/live-tv/producer/approvals/{id}/reject
            └─► Status: rejected
            └─► Creative bisa revise dan resubmit

┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 5: PRODUCTION                           │
└─────────────────────────────────────────────────────────────────┘

Produksi
    │
    ├─► View Produksi Works
    │   GET /api/live-tv/roles/produksi/works
    │
    ├─► Accept Work
    │   POST /api/live-tv/roles/produksi/works/{id}/accept-work
    │
    ├─► Request Equipment
    │   POST /api/live-tv/roles/produksi/works/{id}/request-equipment
    │   └─► Notification → Art & Set Properti
    │
    ├─► Create Run Sheet
    │   POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet
    │
    ├─► Upload Shooting Results
    │   POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results
    │
    └─► Complete Work
        POST /api/live-tv/roles/produksi/works/{id}/complete-work
        └─► Status: completed

┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 6: EDITING                              │
└─────────────────────────────────────────────────────────────────┘

Editor
    │
    ├─► View Editor Works
    │   GET /api/live-tv/roles/editor/works
    │
    ├─► Accept Work
    │   POST /api/live-tv/roles/editor/works/{id}/accept-work
    │
    ├─► Update Editor Work
    │   PUT /api/live-tv/roles/editor/works/{id}
    │
    └─► Submit Editor Work
        POST /api/live-tv/roles/editor/works/{id}/submit
        └─► Status: submitted
        └─► Auto-create: QC Work

┌─────────────────────────────────────────────────────────────────┐
│                PHASE 7: QUALITY CONTROL                          │
└─────────────────────────────────────────────────────────────────┘

Quality Control
    │
    ├─► View QC Works
    │   GET /api/live-tv/roles/quality-control/controls
    │
    ├─► Accept Work
    │   POST /api/live-tv/roles/quality-control/works/{id}/accept-work
    │
    ├─► Start QC
    │   POST /api/live-tv/roles/quality-control/controls/{id}/start
    │   └─► Status: in_progress
    │
    ├─► Complete QC
    │   POST /api/live-tv/roles/quality-control/controls/{id}/complete
    │   └─► Status: completed
    │
    ├─► Approve QC
    │   POST /api/live-tv/roles/quality-control/controls/{id}/approve
    │   └─► Status: approved
    │   └─► Auto-create: Broadcasting Work
    │
    └─► Reject QC
        POST /api/live-tv/roles/quality-control/controls/{id}/reject
        └─► Status: rejected

┌─────────────────────────────────────────────────────────────────┐
│                  PHASE 8: BROADCASTING                           │
└─────────────────────────────────────────────────────────────────┘

Broadcasting
    │
    ├─► View Broadcasting Works
    │   GET /api/live-tv/roles/broadcasting/works
    │
    ├─► Accept Work
    │   POST /api/live-tv/roles/broadcasting/works/{id}/accept-work
    │
    ├─► Upload YouTube
    │   POST /api/live-tv/roles/broadcasting/works/{id}/upload-youtube
    │
    ├─► Upload Website
    │   POST /api/live-tv/roles/broadcasting/works/{id}/upload-website
    │
    └─► Submit Schedule Options
        POST /api/live-tv/manager-program/programs/{program_id}/submit-schedule-options
        └─► Notification → Manager Broadcasting
        │
        ▼
    Manager Broadcasting
        │
        ├─► Approve Schedule
        │   POST /api/live-tv/manager-broadcasting/schedule-options/{id}/approve
        │   └─► Status: approved
        │
        ├─► Reject Schedule
        │   POST /api/live-tv/manager-broadcasting/schedule-options/{id}/reject
        │   └─► Status: rejected
        │
        └─► Revise Schedule
            POST /api/live-tv/manager-broadcasting/schedules/{id}/revise
            └─► Status: revised
            └─► Notification → Manager Program

Broadcasting
    │
    └─► Publish Schedule
        POST /api/live-tv/roles/broadcasting/schedules/{id}/publish
        └─► Status: published
        └─► Episode siap tayang

```

---

## 🔄 STATUS FLOW DIAGRAM

### **Program Status**
```
draft → pending_approval → approved → in_production → completed
                              ↓
                          rejected
```

### **Arrangement Status**
```
song_proposal → song_approved → arrangement_in_progress → arrangement_submitted → arrangement_approved
                    ↓
              song_rejected
```

### **Recording Status**
```
draft → in_progress → completed → reviewed
```

### **Creative Work Status**
```
draft → in_progress → submitted → approved
                          ↓
                      rejected → revised → submitted
```

### **Editor Work Status**
```
draft → in_progress → submitted
```

### **QC Status**
```
pending → in_progress → completed → approved
                            ↓
                        rejected
```

### **Broadcasting Status**
```
draft → in_progress → completed → published
```

---

## 🎯 POINT CHECKING SAAT TESTING

### **1. Auto-Creation Check**
- ✅ Recording task auto-created setelah arrangement approved?
- ✅ Creative work auto-created setelah arrangement approved?
- ✅ Produksi work auto-created setelah creative work approved?
- ✅ QC work auto-created setelah editor work submitted?
- ✅ Broadcasting work auto-created setelah QC approved?

### **2. Notification Check**
- ✅ Notification terkirim ke role yang tepat?
- ✅ Notification content sesuai dengan action?
- ✅ Notification link ke resource yang benar?

### **3. Status Transition Check**
- ✅ Status berubah sesuai workflow?
- ✅ Status validation bekerja (tidak bisa skip step)?
- ✅ Status history tercatat?

### **4. Permission Check**
- ✅ Role validation bekerja?
- ✅ User hanya bisa akses data mereka sendiri?
- ✅ Override permission bekerja (Manager Program)?

### **5. Data Integrity Check**
- ✅ Relasi data tetap konsisten?
- ✅ Foreign key constraint bekerja?
- ✅ Soft delete bekerja dengan benar?

---

## 🐛 COMMON ISSUES & SOLUTIONS

### **Issue 1: Auto-creation tidak terjadi**
**Penyebab**: Service method tidak dipanggil atau error  
**Solusi**: 
- Periksa log di `storage/logs/laravel.log`
- Pastikan method `createRecordingFromArrangement()` dipanggil
- Periksa production team assignment

### **Issue 2: Notification tidak terkirim**
**Penyebab**: User tidak ditemukan atau notification service error  
**Solusi**:
- Periksa production team members
- Pastikan user target ada di database
- Periksa notification service log

### **Issue 3: Status tidak bisa diubah**
**Penyebab**: Status validation gagal atau role tidak sesuai  
**Solusi**:
- Periksa status sebelumnya (harus sesuai requirement)
- Periksa role validation di controller
- Periksa workflow state

### **Issue 4: File upload gagal**
**Penyebab**: Permission folder atau file size limit  
**Solusi**:
- Periksa permission folder `storage/app/public`
- Periksa `php.ini` untuk `upload_max_filesize`
- Periksa validation di controller

---

**Last Updated:** 2025-12-12


