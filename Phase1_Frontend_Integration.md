# Frontend Integration Guide: Phase 1 (Program Planning & Scheduling)

This document provides the API specifications for implementing **Phase 1** of the Music Program Workflow. This phase covers Program Creation, Approval, Episode Generation, and Scheduling.

---

## 1. Program Creation
**Role**: Manager Program / Program Manager
**Goal**: Create a new program draft and upload its proposal.

- **Endpoint**: `POST /api/live-tv/programs`
- **Content-Type**: `multipart/form-data` (due to file upload)

| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `name` | string | Yes | Program title (max 255) |
| `description` | string | No | Description of the program |
| `category` | string | No | `musik`, `live_tv`, `regular`, `special`, `other` (default: `regular`) |
| `manager_program_id` | integer | Yes | User ID of the assigned Manager Program |
| `production_team_id` | integer | No | Initial production team ID |
| `start_date` | date | Yes | Planned start date for Episode 1 (YYYY-MM-DD) |
| `air_time` | string | Yes | Default airing time (HH:mm, e.g., "19:00") |
| `duration_minutes` | integer | No | Expected duration per episode |
| `broadcast_channel` | string | No | Channel name |
| `target_views_per_episode` | integer | No | KPI target for views |
| `proposal_file` | file | No | PDF/DOC/DOCX (Max 10MB) |

---

## 2. Submit Program for Approval
**Role**: Manager Program
**Goal**: Transition program status from `draft` to `pending_approval`.

- **Endpoint**: `POST /api/live-tv/programs/{id}/submit`
- **Body**: (Empty)

---

## 3. Program Approval / Rejection
**Role**: Distribution Manager
**Goal**: Approve the program to allow episode generation and scheduling.

### Approve Program
- **Endpoint**: `POST /api/live-tv/programs/{id}/approve`
- **Body** (JSON):
  ```json
  {
    "approval_notes": "Proposal looks solid, proceeding to planning."
  }
  ```

### Reject Program
- **Endpoint**: `POST /api/live-tv/programs/{id}/reject`
- **Body** (JSON):
  ```json
  {
    "rejection_notes": "Budget calculation is unclear. Please revise."
  }
  ```

---

## 4. Episode Generation
**Role**: Manager Program
**Goal**: Automatically create episodes based on frequency and start date.

- **Endpoint**: `POST /api/live-tv/manager-program/programs/{programId}/generate-episodes`
- **Body** (JSON):

| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `number_of_episodes` | integer | Yes | Number of episodes to create (1-100) |
| `start_date` | date | Yes | Start date for Ep 1 (YYYY-MM-DD) |
| `interval_days` | integer | Yes | Days between episodes (e.g., 7 for weekly) |
| `regenerate` | boolean | No | Set to `true` to delete existing episodes and recreate |

---

## 5. Submit Schedule Options
**Role**: Manager Program
**Goal**: Propose up to 3 date/time slots to the Distribution Manager.

- **Endpoint**: `POST /api/live-tv/manager-program/programs/{programId}/submit-schedule-options`
- **Body** (JSON):

```json
{
  "apply_to": "all",  // "all" for program level, "select" for specific episodes
  "episode_ids": [],   // Array of IDs if apply_to is "select"
  "platform": "all",   // "tv", "youtube", "website", "all"
  "submission_notes": "Optimizing for prime time slots",
  "schedule_options": [
    {
      "date": "2026-03-01",
      "time": "19:00",
      "notes": "Option 1: Primary Slot"
    },
    {
      "date": "2026-03-01",
      "time": "20:30",
      "notes": "Option 2: Late Night"
    }
  ]
}
```

---

## 6. Approve Schedule Option
**Role**: Distribution Manager
**Goal**: Select one of the proposed options to finalize the airing schedule.

- **Endpoint**: `POST /api/live-tv/distribution-manager/schedule-options/{id}/approve`
- **Body** (JSON):

| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `selected_option_index` | integer | Yes | The index (0, 1, or 2) of the chosen option |
| `review_notes` | string | No | Approval feedback |

**Success Result**: 
The program's `start_date` and `air_time` will be updated, and all episode `air_date` values will be automatically recalculated relative to this selection.
