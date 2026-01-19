# Flow Creative Work - Reject & Revision

## ✅ STATUS: **SUDAH LENGKAP & READY**

Setelah Producer reject creative work, ada 2 opsi:
1. **Producer dapat mengedit untuk perbaikan** - Producer edit langsung
2. **Creative perbaiki dan ajukan kembali** - Creative revise & resubmit

---

## 📋 WORKFLOW LENGKAP

```
Producer Reject Creative Work
    ↓
Status: rejected
    ↓
Producer di-notify
Creative di-notify
    ↓
    ├─► PATH 1: Producer Edit Langsung
    │   ↓
    │   Producer Edit Creative Work
    │   ↓
    │   Status: revised (auto)
    │   ↓
    │   Reset review fields
    │   ↓
    │   Creative di-notify (Producer telah edit)
    │   ↓
    │   Creative bisa langsung resubmit
    │
    └─► PATH 2: Creative Revise
        ↓
        Creative Revise Creative Work
        ↓
        Status: revised
        ↓
        Reset review fields
        ↓
        Creative Resubmit ke Producer
        ↓
        Status: submitted
        ↓
        Producer di-notify (resubmit)
```

---

## 📋 ENDPOINT YANG TERSEDIA

### **PATH 1: Producer Edit Langsung**

#### **1. Producer Terima Notifikasi (Setelah Reject)**
Tidak perlu endpoint khusus, cukup get notifications seperti biasa.

---

#### **2. Producer Edit Creative Work untuk Perbaikan** ⭐
**Endpoint:** `PUT /api/live-tv/producer/creative-works/{id}/edit`

**Fungsi:** Producer dapat mengedit creative work setelah reject untuk perbaikan

**Request Body:**
```json
{
  "script_content": "Script yang diperbaiki...",
  "storyboard_data": {...},
  "budget_data": {...},
  "recording_schedule": "2026-02-01 10:00:00",
  "shooting_schedule": "2026-02-03 08:00:00",
  "shooting_location": "Studio B",
  "edit_notes": "Diperbaiki oleh Producer sesuai feedback"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "revised",
    "script_content": "Script yang diperbaiki...",
    "script_approved": null,
    "storyboard_approved": null,
    "budget_approved": null
  },
  "message": "Creative work edited successfully"
}
```

**Fitur:**
- ✅ Producer bisa edit semua field (script, storyboard, budget, jadwal, lokasi)
- ✅ **Jika status `rejected`, otomatis berubah menjadi `revised`** ✅
- ✅ **Reset semua review fields** (script_approved, storyboard_approved, budget_approved = null) ✅
- ✅ **Creative di-notify** bahwa Producer telah mengedit ✅
- ✅ Creative bisa langsung resubmit setelah Producer edit

**Validasi:**
- ✅ Hanya bisa edit jika status: `submitted`, `rejected`, atau `revised`
- ✅ Producer harus dari production team yang sama

**Tersedia di Postman:** ✅ Yes

---

### **PATH 2: Creative Revise & Resubmit**

#### **3. Creative Terima Notifikasi (Setelah Reject)**
**Endpoint:** `GET /api/live-tv/notifications?type=creative_work_rejected`

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "type": "creative_work_rejected",
        "title": "Creative Work Ditolak",
        "message": "Creative work untuk Episode 001 telah ditolak oleh Producer. Alasan: Perlu revisi script dan budget",
        "data": {
          "creative_work_id": 1,
          "episode_id": 1,
          "rejection_reason": "Perlu revisi script dan budget"
        }
      }
    ]
  }
}
```

**Tersedia di Postman:** ✅ Yes

---

#### **4. Creative Revise Creative Work** ⭐
**Endpoint:** `PUT /api/live-tv/roles/creative/works/{id}/revise`

**Fungsi:** Creative perbaiki creative work setelah reject

**Request Body:**
```json
{
  "script_content": "Script yang diperbaiki...",
  "storyboard_data": {...},
  "budget_data": {...},
  "recording_schedule": "2026-02-01 10:00:00",
  "shooting_schedule": "2026-02-03 08:00:00",
  "shooting_location": "Studio B",
  "revision_notes": "Diperbaiki sesuai feedback Producer"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "revised",
    "script_content": "Script yang diperbaiki...",
    "script_approved": null,
    "storyboard_approved": null,
    "budget_approved": null
  },
  "message": "Creative work revised successfully. You can now resubmit to Producer."
}
```

**Fitur:**
- ✅ Creative bisa edit semua field (script, storyboard, budget, jadwal, lokasi)
- ✅ Status berubah menjadi `revised`
- ✅ **Reset semua review fields** (script_approved, storyboard_approved, budget_approved = null)
- ✅ Validasi: hanya bisa revise jika status `rejected` atau `revised`

**Tersedia di Postman:** ✅ Yes

---

#### **5. Creative Resubmit ke Producer** ⭐
**Endpoint:** `POST /api/live-tv/roles/creative/works/{id}/resubmit`

**Fungsi:** Creative ajukan kembali creative work yang sudah direvisi ke Producer

**Request Body:** (Optional)
```json
{}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "submitted",
    "script_content": "Script yang diperbaiki...",
    "storyboard_data": {...},
    "budget_data": {...}
  },
  "message": "Creative work resubmitted successfully. Producer has been notified."
}
```

**Fitur:**
- ✅ Status berubah menjadi `submitted`
- ✅ **Producer di-notify** bahwa Creative telah resubmit ✅
- ✅ Producer bisa review kembali
- ✅ Validasi: hanya bisa resubmit jika status `revised`
- ✅ Validasi: script, storyboard, dan budget harus sudah diisi

**Tersedia di Postman:** ✅ Yes

---

## 🔄 DETAIL WORKFLOW

### **PHASE 1: Producer Reject Creative Work**

**Producer:**
```http
POST /api/live-tv/producer/creative-works/{id}/final-approval
Content-Type: application/json

{
  "action": "reject",
  "notes": "Perlu revisi script dan budget"
}
```

**Hasil:**
- ✅ Status: `rejected`
- ✅ **Creative di-notify** dengan alasan rejection ✅

---

### **PHASE 2A: PATH 1 - Producer Edit Langsung**

#### **Step 1: Producer Edit Creative Work**
```http
PUT /api/live-tv/producer/creative-works/{id}/edit
Content-Type: application/json

{
  "script_content": "Script yang diperbaiki...",
  "budget_data": {...},
  "edit_notes": "Diperbaiki sesuai kebutuhan"
}
```

**Hasil:**
- ✅ Status: `revised` (auto)
- ✅ Reset review fields
- ✅ **Creative di-notify** bahwa Producer telah mengedit ✅
- ✅ Creative bisa langsung resubmit (status sudah `revised`)

---

#### **Step 2: Creative Resubmit (Opsional)**
```http
POST /api/live-tv/roles/creative/works/{id}/resubmit
```

**Hasil:**
- ✅ Status: `submitted`
- ✅ **Producer di-notify** untuk review kembali ✅

---

### **PHASE 2B: PATH 2 - Creative Revise & Resubmit**

#### **Step 1: Creative Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=creative_work_rejected
```

**Hasil:**
- ✅ Creative melihat notifikasi rejection dengan alasan

---

#### **Step 2: Creative Revise Creative Work**
```http
PUT /api/live-tv/roles/creative/works/{id}/revise
Content-Type: application/json

{
  "script_content": "Script yang diperbaiki...",
  "budget_data": {...},
  "revision_notes": "Diperbaiki sesuai feedback Producer"
}
```

**Hasil:**
- ✅ Status: `revised`
- ✅ Reset review fields
- ✅ Siap untuk resubmit

---

#### **Step 3: Creative Resubmit ke Producer**
```http
POST /api/live-tv/roles/creative/works/{id}/resubmit
```

**Hasil:**
- ✅ Status: `submitted`
- ✅ **Producer di-notify** untuk review kembali ✅
- ✅ Producer bisa approve/reject lagi

---

## 📝 CONTOH KASUS PENGGUNAAN

### **Kasus 1: Producer Edit Langsung**
```
1. Producer reject creative work
   → Status: rejected
   → Creative di-notify

2. Producer edit langsung untuk perbaikan
   → PUT /api/live-tv/producer/creative-works/{id}/edit
   → Status: revised (auto)
   → Creative di-notify (Producer telah edit)

3. Creative resubmit (opsional, karena status sudah revised)
   → POST /api/live-tv/roles/creative/works/{id}/resubmit
   → Status: submitted
   → Producer di-notify untuk review kembali
```

---

### **Kasus 2: Creative Revise & Resubmit**
```
1. Producer reject creative work
   → Status: rejected
   → Creative di-notify

2. Creative terima notifikasi
   → GET /api/live-tv/notifications?type=creative_work_rejected

3. Creative revise creative work
   → PUT /api/live-tv/roles/creative/works/{id}/revise
   → Status: revised

4. Creative resubmit ke Producer
   → POST /api/live-tv/roles/creative/works/{id}/resubmit
   → Status: submitted
   → Producer di-notify untuk review kembali
```

---

## ✅ VALIDATION & SECURITY

### **Producer Edit:**
- ✅ Hanya bisa edit jika status: `submitted`, `rejected`, atau `revised`
- ✅ Jika status `rejected`, otomatis berubah menjadi `revised`
- ✅ Reset review fields setelah edit (jika rejected)
- ✅ Creative di-notify tentang perubahan

### **Creative Revise:**
- ✅ Hanya bisa revise jika status: `rejected` atau `revised`
- ✅ Reset review fields setelah revise
- ✅ Status berubah menjadi `revised`

### **Creative Resubmit:**
- ✅ Hanya bisa resubmit jika status: `revised`
- ✅ Validasi: script, storyboard, dan budget harus sudah diisi
- ✅ Producer di-notify untuk review kembali

---

## 📋 ENDPOINT SUMMARY UNTUK FRONTEND

| Fitur | Endpoint | Method | Body |
|-------|----------|--------|------|
| Get Notifications (Rejected) | `/api/live-tv/notifications?type=creative_work_rejected` | GET | - |
| Producer Edit (Perbaikan) | `/api/live-tv/producer/creative-works/{id}/edit` | PUT | `{script_content, budget_data, ...}` |
| Creative Revise | `/api/live-tv/roles/creative/works/{id}/revise` | PUT | `{script_content, budget_data, ...}` |
| Creative Resubmit | `/api/live-tv/roles/creative/works/{id}/resubmit` | POST | `{}` |

---

## ✅ VERIFIKASI SEMUA FITUR

### **Setelah Producer Reject:**

1. ✅ **Producer Dapat Edit Langsung** - Endpoint edit tersedia
   - Jika status `rejected`, otomatis menjadi `revised`
   - Reset review fields
   - Creative di-notify

2. ✅ **Creative Perbaiki** - Endpoint revise tersedia
   - Bisa revise jika status `rejected` atau `revised`
   - Status menjadi `revised`
   - Reset review fields

3. ✅ **Creative Ajukan Kembali** - Endpoint resubmit tersedia
   - Bisa resubmit jika status `revised`
   - Status menjadi `submitted`
   - Producer di-notify untuk review kembali

**Status:** ✅ **READY FOR FRONTEND INTEGRATION**

---

**Last Updated:** 2026-01-27
