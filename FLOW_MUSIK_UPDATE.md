# Music Program System Workflow Documentation

This document outlines the detailed workflow for the "Program Musik" system, covering Pre-Production, Production, Introduction, and Distribution phases as defined by the user.

## 1. Program Manager & Team Initiation

### 1.1 Program Creation & Team Formation
*   **Program Creation:** Manager Program creates a "Live Program" (Database Program Proposal).
*   **Team Management (Crucial):**
    *   Manager Program creates/divides a work team.
    *   **Producer Selection:** Manager selects users with the **Producer** role. *Note: Multiple producers can be assigned.*
    *   **Role Assignments:** The team consists of the following roles (each can have multiple users assigned):
        1.  Music Arranger
        2.  Creative
        3.  Sound Engineer
        4.  Produksi
        5.  Editor
    *   **Producer Scope:** Ideally, the Producer can replace/manage these team members throughout the process.

### 1.2 Program Scheduling (Episode Generation)
*   **Schedule Options:** Manager Program creates schedule options (dates/times) and submits them to **Distribution Manager (Manager Broadcasting)**.
*   **Approval & Auto-Generation:**
    *   Once Distribution Manager approves a schedule option:
    *   **Auto-Sequence:** System generates Episodes 1 through 52 automatically (Weekly frequency).
    *   **Logic:** Episode 1 starts on the first approved date (e.g., First Saturday of Jan). Episode 2 is 7 days later, etc.
    *   **Inheritance:** Dates are inherited sequentially.
*   **Auto-Deadlines:**
    *   **Editor:** 7 days before Air Date.
    *   **Creative & Produksi:** 9 days before Air Date.
    *   *Note: Manager Program can edit deadlines for special cases.*
*   **Yearly Cycle:** Data is verified per year. Frontend filters episodes by year.

### 1.3 Manager Monitoring
*   **Targets:** Manager sets View Targets (Weekly Data).
*   **Intervention:** Manager can intervene in all schedules, approve all areas, cancel shooting, and close non-performing programs.

## 2. Distribution Manager (Manager Broadcasting)

*   **Schedule Approval:** Receives schedule options from Manager Program -> Approve/Revise.
*   **Work Assignment:** Can divide distribution work based on roles.
*   **Monitoring:** Monitors work until airing.
*   **Performance:** Evaluates programs and can close non-performing "Regular Programs".

## 3. Producer Workflow (The Core Controller)

*   **Responsibility:** Receives responsibility for the Live Programs.
*   **Rundown:**
    *   View Program Details (Name, Rundown).anda yakin?
    *   **Edit Rundown:** Can edit rundowns directly (or submit changes to Manager Program if required).
*   **Control:** Controls 1 Episode/week.
*   **Team & Schedule Management:**
    *   **Reminders:** Can send system reminders to every crew member in their team.
    *   **Monitoring:** Monitors all team progress/status in the workflow (Step-by-step tracking).
    *   **Team Substitution:** Can replace team members (e.g., if someone is sick) with any other user.
    *   **Schedule Intervention:** Can intervene in/edit Shooting & Vocal Recording schedules.
    *   **Team Additions (Syuting, Setting, Vocal):**
        *   Can add *any* user in the system to these sub-teams.
        *   **Constraint:** Cannot add "Manager" roles to these teams.
        *   *Note:* "Tim Syuting" can take all crew except Manager.

## 4. Phase 1: Music Arrangement

*   **Step 4.1: Proposal:**
    *   **Music Arranger** selects Song & Singer.
    *   *Feature:* If Song/Singer not in DB, input text -> saves to DB for future use.
    *   Submits to **Producer**.
*   **Step 4.2: Producer Review:**
    *   Producer receives notification.
    *   **Action:** Approve, Reject, or **Edit Directly**.
    *   *Efficiency:* If Producer edits/swaps the song/singer, it is auto-approved (no need to send back for approval).
*   **Step 4.3: Arrangement Execution:**
    *   **Music Arranger** receives approval.
    *   **Constraint:** Uploads **LINK** to file (due to 20GB storage limit on app server). File stored on external storage/server.
    *   Submits Link to **Producer**.
*   **Step 4.4: Arrangement QC:**
    *   **Producer** QCs the arrangement.
    *   **If Approved:** User proceeds to Creative.
    *   **If Rejected:** Returns to Music Arranger.
    *   **Help Loop (Sound Eng):**
        *   If rejected, **Sound Engineer** can be notified to help.
        *   Sound Engineer fixes -> Sends Link to Music Arranger.
        *   Music Arranger submits to Producer.

## 5. Phase 2: Creative

*   **Step 5.1: Creative Work:**
    *   **Creative** receives task (after Arrangement approval).
    *   **Outputs (Links/Text):**
        *   Script (Video Clip Story).
        *   Storyboard.
    *   **Inputs Schedules:**
        *   Vocal Recording Schedule.
        *   Shooting Schedule.
        *   Location.
    *   **Budget:** Creates budget for Talent, etc.
    *   Submits to **Producer**.
*   **Step 5.2: Producer Review & Team Augmentation:**
    *   Producer checks Script, Storyboard, Budget.
    *   **Assigns Additional Teams:** (Can pick *any* user in system excluding Manager)
        *   **Shooting Team:** Crew for the shoot.
        *   **Setting Team:** Crew for set design.
        *   **Vocal Recording Team:** Crew for recording (Sound Eng).
    *   **Actions:**
        *   Can cancel/change schedules.
        *   Can **Edit Creative's work directly**.
        *   **Special Budget:** If budget > limit (or special), request **Manager Program** approval.
    *   **Decision:** Approve or Reject Creative Work.
        *   If Producer edits directly -> Auto-approve.
        *   If Special Budget requested -> Manager Program Approves/Edits Amount/Rejects.

## 6. Phase 3: Pre-Production Executions (Parallel)

*   **Stream A: General Affairs:** Receives Budget Request -> Processes Payment -> Returns result to Producer.
*   **Stream B: Sound Engineer (Prep):**
    *   Receives Vocal Recording Schedule.
    *   Inputs Equipment List -> Requests to **Art & Set Property**.
*   **Stream C: Promosi (Prep):**
    *   Receives Shooting Schedule.
    *   Task: BTS Video, Talent Photos.
*   **Stream D: Produksi (Prep):**
    *   Receives Shooting Schedule.
    *   Inputs Equipment List -> Requests to **Art & Set Property**.
    *   Inputs Needs.

## 7. Phase 4: Production & Equipment

*   **Art & Set Property:**
    *   Receives Equipment Requests (from Sound Eng & Produksi).
    *   Check availability (if used, cannot request).
    *   Action: ACC (Approve) Equipment.
*   **Execution - Sound Engineer (Recording):**
    *   Records Vocal.
    *   Returns Equipment -> Art & Set Property ACCs return.
    *   Uploads **Link** of Recording.
*   **Execution - Produksi (Shooting):**
    *   Shooting Team (assigned by Producer) executes.
    *   Inputs Run Sheet (Catatan Syuting).
    *   Uploads **Link** of Shooting Results (Storage Server).
    *   Returns Equipment -> Art & Set Property ACCs return.

## 8. Phase 5: Post-Production

### 8.1 Sound Engineer (Editing)
*   Receives task after Recording.
*   Edits Vocal.
*   Submits to QC (**Producer**).
*   **Producer QC:**
    *   Approve -> Goes to Editor.
    *   Reject -> Back to Sound Engineer.

### 8.2 Editor (Main Video)
*   Receives Approved Vocal & Shooting Files (Links).
*   **File Check:**
    *   Checks completeness.
    *   If incomplete -> Report to Producer.
    *   Producer checks -> Assigns **Produksi** to fix/reshoot.
*   **Editing:**
    *   Edits Video.
    *   Uploads **Link** to storage.
    *   Submits to QC (**Manager Broadcasting / Distribution Manager**).

### 8.3 QC (Manager Broadcasting)
*   Receives Editor's work.
*   **Action:** QC Form (Notes, No Revision/Yes).
*   **Result:**
    *   **Reject:** Back to Editor (Notify Producer + QC Notes).
    *   **Approve:** Forward to **Broadcasting**.

## 9. Phase 6: Promotion & Graphics

*   *Triggered by Producer Approval of Creative Work & Completion of Shooting.*

### 9.1 Content Creation
*   **Promosi:**
    *   Creates BTS Video & Talent Photos.
    *   Uploads **Links**.
*   **Design Grafis:**
    *   Receives notifications (from Promosi & Produksi).
    *   Receives File Locations (Shooting + Talent Photos).
    *   **Task:** Create Thumbnails (YouTube, BTS).
    *   Submits to **QC Promosi**.
*   **Editor Promosi:**
    *   Receives notifications (from Promosi & Editor).
    *   Receives File Locations (Editor's Video + BTS).
    *   **Task:** Edit BTS, TV Ads, Highlights (IG, TV, FB).
    *   Submits to **QC Promosi**.

### 9.2 QC Promosi
*   **Role:** Specific QC role for Promotion.
*   **Action:** QC all assets (Thumbnails, BTS, Ads, Highlights).
*   **Result:**
    *   Reject -> Back to Designer/Editor Promosi.
    *   Approve -> Forward to **Broadcasting** & **Promosi**.

## 10. Phase 7: Broadcasting & Distribution

### 10.1 Broadcasting
*   Receives Approved Main Video (from QC Manager Broadcasting).
*   Receives Approved Thumbnails (from QC Promosi).
*   **Task:**
    *   Schedule Playlist.
    *   Upload to YouTube (Thumbnail, SEO).
    *   Upload to Website.
    *   Input **YouTube Link** to System.

### 10.2 Final Promotion (Social Media)
*   **Promosi:**
    *   Receives YouTube Link & Website Link.
    *   **Tasks:**
        *   Share Website Link to FB -> Upload Proof.
        *   Create IG Story Highlight -> Upload Proof.
        *   Create FB Reels Highlight -> Upload Proof.
        *   Share to WA Group -> Upload Proof.

---
**System Constraints:**
*   **Storage:** 20GB Limit. All large files (Video, Audio) must be stored on external server/storage, and **only Links** are entered into the system.
