# Frontend Integration Guide: Phase 2 & 3 (Pre-Production Workflow)

This document covers **Phase 2** (Lead Production: Team & Deadline Management) and **Phase 3** (Pre-Production: Creative & Music Arrangement).

---

## Phase 2: Lead Production (Team & Deadlines)
**Role**: Manager Program
**Goal**: Assign production teams to episodes and fine-tune deadlines.

### 1. Assign Production Team to Episode
- **Endpoint**: `POST /api/live-tv/manager-program/episodes/{episodeId}/assign-team`
- **Body** (JSON):
  ```json
  {
    "production_team_id": 5,
    "notes": "Assigning to Team A for the holiday special."
  }
  ```

### 2. Update Specific Deadline
- **Endpoint**: `PUT /api/live-tv/manager-program/deadlines/{deadlineId}`
- **Body** (JSON):
  ```json
  {
    "deadline_date": "2026-04-15",
    "reason": "Request from producer for extra preparation time.",
    "description": "Scripting deadline adjustment"
  }
  ```

---

## Phase 3: Pre-Production - Creative & Music

### Stream A: Creative Work (Script & Storyboard)
**Role**: Team Creative

#### 1. Submit Creative Concept (Initial)
- **Endpoint**: `POST /api/live-tv/roles/creative/works`
- **Body** (JSON):
  ```json
  {
    "episode_id": 101,
    "title": "Creative Concept Ep 101",
    "description": "Retro theme with neon aesthetics"
  }
  ```

#### 2. Input Links (Script/Storyboard)
- **Endpoint**: `PUT /api/live-tv/roles/creative/works/{id}/update-link`
- **Body** (JSON):
  ```json
  {
    "script_link": "https://docs.google.com/document/d/...",
    "storyboard_link": "https://docs.google.com/presentation/d/...",
    "notes": "Draft version 1 complete."
  }
  ```

#### 3. Submit for Producer Review
- **Endpoint**: `POST /api/live-tv/roles/creative/works/{id}/submit`
- **Body**: (Empty)

---

### Stream B: Music Arrangement
**Role**: Music Arranger

#### 1. Submit Song Proposal
**Goal**: Propose the song and singer for the episode.
- **Endpoint**: `POST /api/live-tv/music-arranger/arrangements/{id}/submit-song-proposal`
- **Body** (JSON):
  ```json
  {
    "song_title": "He Leadeth Me",
    "singer_name": "John Doe",
    "notes": "Standard upbeat arrangement."
  }
  ```

#### 2. Submit Audio Arrangement (Link/File)
- **Endpoint**: `POST /api/live-tv/music-arranger/arrangements/{id}/submit`
- **Body** (JSON):
  ```json
  {
    "arrangement_link": "https://drive.google.com/file/d/...",
    "notes": "Final arrangement attached."
  }
  ```

#### 3. Complete Music Work
- **Endpoint**: `POST /api/live-tv/music-arranger/arrangements/{id}/complete-work`
- **Body**: (Empty)

---

## Next: Phase 4 (Producer Review & Budget Approval)
Phase 4 involves the Producer reviewing Creative/Music work and General Affairs approving the budget.
