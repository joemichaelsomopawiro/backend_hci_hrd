# 📊 FLOW DIAGRAM SISTEM PROGRAM MUSIK
**Detail Workflow (Berdasarkan Deep Explanation - 28 Jan 2026)**

---

## 🎯 DIAGRAM FLOW LENGKAP

```
┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 1: SETUP PROGRAM                        │
└─────────────────────────────────────────────────────────────────┘

Manager Program
    │
    ├─► Create Program & Divide Teams
    │   - Assign Producer (CRUD rights on team)
    │   - Assign Core Roles: Arranger, Creative, SoundEng, Produksi, Editor
    │
    ├─► Episode Generation
    │   - Auto-generate 52 Episodes (Weekly dates: Jan 3, 10, 17...)
    │   - Auto-deadline (Editor H-7, Creative H-9)
    │
    └─► Submit Schedule Options
        POST /api/live-tv/programs/{id}/submit-schedule
        │
        ▼
    Manager Broadcasting / Distribution
        │
        ├─► Approve/Revise Schedule
        │   └─► Notify Manager Program
        │
        └─► Monitor Target Views

┌─────────────────────────────────────────────────────────────────┐
│              PHASE 2: MUSIC ARRANGEMENT                         │
└─────────────────────────────────────────────────────────────────┘

Music Arranger
    │
    ├─► Select Song & Singer
    │   Input Text (if not in DB) -> Auto-save to DB
    │   └─► Submit to Producer
    │
    ▼
Producer
    │
    ├─► Approve
    ├─► Reject (Loop back to Arranger)
    └─► **EDIT DIRECTLY** (Bypass Rejection)
        └─► Status: song_approved

Music Arranger
    │
    ├─► Input Link Arrangement (Server Storage)
    │   └─► Submit to Producer
    │
    ▼
Producer (QC)
    │
    ├─► Approve -> Trigger Creative Phase
    └─► Reject -> Back to Arranger OR Sound Engineer
        │
        ▼
    Sound Engineer (Assistance)
        └─► Input Link Fix -> Send to Arranger -> Submit Producer

┌─────────────────────────────────────────────────────────────────┐
│                  PHASE 3: CREATIVE WORK                          │
└─────────────────────────────────────────────────────────────────┘

Creative
    │
    ├─► Input Script & Storyboard (Link/Text)
    ├─► Input Schedule (Shooting/Vocal) & Location
    ├─► Input Budget Plan
    │   └─► Submit to Producer
    │
    ▼
Producer
    │
    ├─► Review & **EDIT DIRECTLY** (Bypass Rejection)
    ├─► **Special Budget Request** (if needed)
    │   └─► Manager Program (ACC/Edit/Reject Funds)
    │
    └─► Approve Work
        └─► Trigger: Production Phase

┌─────────────────────────────────────────────────────────────────┐
│              PHASE 4: EXECUTION (PARALLEL STREAMS)               │
└─────────────────────────────────────────────────────────────────┘

[STREAM A: FUNDING]
General Affairs
    │
    └─► View Approved Budget -> Release Funds -> Notify Producer

[STREAM B: AUDIO]
Sound Engineer
    │
    ├─► Request Alat -> Art Set Property (ACC)
    ├─► Recording -> Return Alat -> **Input Link (Raw)**
    ├─► Edit Vocal -> Submit to Producer (QC Audio)
    │
    ▼
Producer (QC Audio)
    │
    └─► Approve -> Trigger Editor (Main)

[STREAM C: CONTENT PROMO]
Promosi
    │
    ├─► Create BTS Video & Talent Photos
    └─► **Input Link** -> Notify Design Grafis & Editor Promosi

[STREAM D: SHOOTING]
Producer
    │
    └─► Add Extra Teams (Syuting/Setting) from **ALL USERS**

Produksi
    │
    ├─► Pre-Shoot: Request Alat (Validation: In Use) -> Art Set Prop (ACC)
    ├─► Execution: Run Sheet, Shooting -> **Input Link (Result)**
    └─► Post-Shoot: Return Alat -> Notify Producer/Editor/Design

┌─────────────────────────────────────────────────────────────────┐
│                  PHASE 5: POST-PRODUCTION                        │
└─────────────────────────────────────────────────────────────────┘

[STREAM 1: MAIN EPISODE]
Editor
    │
    ├─► Check Files (Audio + Visual)
    │   - Complete: Proceed
    │   - **Incomplete**: Report Producer -> Producer Order Produksi -> Fix
    │
    ├─► Edit Video -> **Input Link**
    └─► Submit to QC Manager Broadcasting

[STREAM 2: PROMO MATERIALS]
Design Grafis
    ├─► Create Thumbnails (Youtube/BTS) -> **Input Link**
    └─► Submit to QC Promosi

Editor Promosi
    ├─► Edit BTS, Ads, Highlights -> **Input Link**
    └─► Submit to QC Promosi

┌─────────────────────────────────────────────────────────────────┐
│                  PHASE 6: FINAL QC & BROADCASTING                │
└─────────────────────────────────────────────────────────────────┘

QC Manager Broadcasting
    │
    ├─► QC Main Video
    ├─► Approve -> Ready for Broadcast
    └─► Reject -> Back to Editor

QC Promosi (Specific Role)
    │
    ├─► QC Thumbnails, BTS, Ads
    ├─► Approve -> Ready for Distribution
    └─► Reject -> Back to Design/Editor Promosi

Broadcasting
    │
    ├─► Receive Main Video + Thumbnails
    ├─► Upload Youtube (SEO), Website
    └─► **Input Link Youtube** -> Notify Promosi

Promosi (Distribution)
    │
    ├─► Share FB, IG Story, Reels, WA Group
    └─► **Input Bukti** (Links/Screenshots)

```

---

## 🔍 KEY SYSTEM BEHAVIORS

### **1. Episode Inheritance**
- 52 Episodes are generated upfront (Jan-Dec).
- Dates are inherited weekly.
- Deadlines are auto-calculated (H-7/H-9) from these dates.

### **2. Producer Powers**
- **Direct Edit**: Can edit Songs, Scripts, Budgets directly without sending back for revision.
- **Team Flexibility**: Can add members to "Tim Syuting" from the entire user base.
- **Full CRUD**: Can replace active team members anytime.

### **3. Link Input Methodology**
- All heavy files (Video, Audio) are stored on external servers.
- System stores **Links (URLs)** as proof of work.

### **4. QC Separation**
- **Main Content** -> QC by Manager Broadcasting.
- **Promo Content** -> QC by "Quality Control" (Promosi specific).
- **Audio Content** -> QC by Producer.

---
**Last Updated:** 28 Jan 2026 (Deep Explanation Ver)
