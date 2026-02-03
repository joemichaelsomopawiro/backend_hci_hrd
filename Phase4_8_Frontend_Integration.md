# Frontend Integration Guide: Phase 4 - 8 (Approval to Broadcasting)

This document covers the final stages of the Music Program Workflow: **Approval**, **Production**, **Post-Production**, **QC**, and **Broadcasting**.

---

## Phase 4: Pre-Production Approval & Budget
**Goal**: Official approval of concepts and release of production funds.

### 1. Producer Approval
- **Endpoint**: `POST /api/live-tv/roles/producer/approve/{id}`
- **Body** (JSON):
  ```json
  {
    "approval_type": "creative_work", // or "music_arrangement"
    "notes": "Concept is approved, proceed to production."
  }
  ```

### 2. General Affairs Budget Approval
- **Endpoint**: `POST /api/live-tv/general-affairs/budget-requests/{id}/approve`
- **Body** (JSON):
  ```json
  {
    "notes": "Budget allocated for vocal recording and location."
  }
  ```

---

## Phase 5: Production Execution
**Goal**: Recording vocals and shooting the main content.

### 1. Sound Engineer: Complete Recording
- **Endpoint**: `POST /api/live-tv/roles/sound-engineer/recordings/{id}/complete`
- **Body** (JSON):
  ```json
  {
    "file_link": "https://drive.google.com/file/vocal_recording",
    "notes": "Vocal stems are ready for editing."
  }
  ```

### 2. Produksi: Create Run Sheet
- **Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet`
- **Body** (JSON):
  ```json
  {
    "notes": "Shooting went smoothly at Location X.",
    "crew_list": "...",
    "problems_encountered": "None"
  }
  ```

### 3. Produksi: Input Shooting Result Links
- **Endpoint**: `POST /api/live-tv/roles/produksi/works/{id}/input-file-links`
- **Body** (JSON):
  ```json
  {
    "file_links": [
      {"name": "Master Cam A", "url": "https://..."},
      {"name": "Drone Footage", "url": "https://..."}
    ]
  }
  ```

---

## Phase 6: Post-Production
**Goal**: Video editing and creation of promotional materials.

### 1. Editor: Input Edited Video Link
- **Endpoint**: `POST /api/live-tv/editor/works/{id}/input-file-links`
- **Body** (JSON):
  ```json
  {
    "file_link": "https://drive.google.com/edited_episode"
  }
  ```

### 2. Design Grafis: Complete Thumbnail
- **Endpoint**: `POST /api/live-tv/design-grafis/works/{id}/complete-work`
- **Body** (JSON):
  ```json
  {
    "youtube_thumbnail_url": "https://...",
    "bts_thumbnail_url": "https://..."
  }
  ```

---

## Phase 7: Quality Control (QC)
**Goal**: Final check before content goes live.

### Submit QC Form
- **Endpoint**: `POST /api/live-tv/roles/quality-control/work/{id}/submit-qc`
- **Body** (JSON):
  ```json
  {
    "status": "approved", // or "rejected"
    "notes": "Color grading is perfect.",
    "quality_score": 95
  }
  ```

---

## Phase 8: Broadcasting & Promotion
**Goal**: Content upload and social media distribution.

### 1. Broadcasting: Complete Work
- **Endpoint**: `POST /api/live-tv/broadcasting/complete-work/{id}`
- **Body** (JSON):
  ```json
  {
    "youtube_url": "https://youtube.com/watch?v=...",
    "website_url": "https://yourwebsite.com/episodes/..."
  }
  ```

### 2. Promotion: Share to Social Media
- **Endpoint**: `POST /api/live-tv/promosi/facebook/share`
- **Body** (multipart/form-data):
  ```
  episode_id: 101
  proof_file: (image of post)
  notes: "Shared to official page."
  ```

---
*End of Documentation set.*
