# 📋 Workflow Program Musik - Setelah Manager Program Membuat Program

## 🎯 Overview

Dokumentasi ini menjelaskan workflow lengkap setelah Manager Program membuat program musik baru, termasuk status program, submit untuk approval, dan siapa yang approve.

---

## 📊 Status Program

Program memiliki status berikut:

1. **`draft`** - Program baru dibuat, belum di-submit
2. **`pending_approval`** - Program sudah di-submit, menunggu approval
3. **`approved`** - Program sudah di-approve, siap produksi
4. **`in_production`** - Program sedang dalam produksi
5. **`completed`** - Program selesai (semua episode sudah tayang)
6. **`cancelled`** - Program dibatalkan
7. **`rejected`** - Program ditolak

---

## 🔄 Workflow Lengkap

### STEP 1: Manager Program Membuat Program

**Endpoint:** `POST /api/live-tv/programs`

**Status Setelah Dibuat:** `draft`

**Yang Terjadi:**
1. Program dibuat dengan status `draft`
2. **Auto-generate 53 episodes** (1 tahun)
3. **Auto-generate deadlines** untuk setiap episode:
   - Editor: 7 hari sebelum tayang
   - Creative, Production, Music Arranger, Sound Engineer: 9 hari sebelum tayang
4. Notifikasi dikirim ke Manager Program bahwa program berhasil dibuat

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Live",
    "status": "draft", // ← Status draft
    "proposal_file_path": "programs/proposals/1234567890_proposal.pdf",
    "episodes": [
      {
        "id": 1,
        "episode_number": 1,
        "air_date": "2025-01-01",
        "deadlines": [...]
      }
      // ... 52 episode lainnya
    ]
  },
  "message": "Program created successfully with 53 episodes generated"
}
```

**Catatan:**
- Program masih dalam status `draft`, belum bisa langsung produksi
- Manager Program masih bisa edit program sebelum submit
- Episodes sudah ter-generate dengan deadlines

---

### STEP 2: Manager Program Submit Program untuk Approval

**Endpoint:** `POST /api/live-tv/programs/{id}/submit`

**Syarat Submit:**
- Status program harus `draft`
- Hanya Manager Program yang membuat program yang bisa submit

**Request Body:**
```json
{
  "submission_notes": "Program siap untuk approval, proposal sudah dilampirkan"
}
```

**Yang Terjadi:**
1. Status berubah: `draft` → `pending_approval`
2. `submitted_by` = ID Manager Program yang submit
3. `submitted_at` = Timestamp submit
4. **Notifikasi dikirim ke Manager Broadcasting (Distribution Manager)**
5. Program tidak bisa di-edit lagi sampai di-approve atau reject

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Live",
    "status": "pending_approval", // ← Status berubah
    "submitted_by": 1,
    "submitted_at": "2025-12-10 10:00:00",
    "submission_notes": "Program siap untuk approval, proposal sudah dilampirkan"
  },
  "message": "Program submitted for approval successfully"
}
```

**Notifikasi ke Manager Broadcasting:**
```json
{
  "type": "program_submitted",
  "title": "Program Submitted for Approval",
  "message": "Program 'Program Musik Live' has been submitted for approval by Manager Program.",
  "priority": "high"
}
```

---

### STEP 3: Manager Broadcasting Review & Approve/Reject

**Siapa yang Approve:** **Manager Broadcasting (Distribution Manager)**

**Endpoint Approve:** `POST /api/live-tv/programs/{id}/approve`

**Endpoint Reject:** `POST /api/live-tv/programs/{id}/reject`

#### 3A. Jika APPROVED

**Request Body:**
```json
{
  "approval_notes": "Program disetujui, siap untuk produksi"
}
```

**Yang Terjadi:**
1. Status berubah: `pending_approval` → `approved`
2. `approved_by` = ID Manager Broadcasting
3. `approved_at` = Timestamp approval
4. **Semua episodes status berubah menjadi `approved_for_production`**
5. **Workflow state semua episodes di-set ke `episode_generated`**
6. **Notifikasi dikirim ke:**
   - Manager Program (program di-approve)
   - Production Team (jika sudah di-assign)

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Live",
    "status": "approved", // ← Status approved
    "approved_by": 5,
    "approved_at": "2025-12-10 11:00:00",
    "approval_notes": "Program disetujui, siap untuk produksi",
    "episodes": [
      {
        "id": 1,
        "status": "approved_for_production", // ← Episode ready
        "current_workflow_state": "episode_generated"
      }
    ]
  },
  "message": "Program approved successfully"
}
```

**Setelah Approved:**
- Program siap untuk produksi
- Manager Program bisa assign production team ke episodes
- Producer bisa mulai workflow (Music Arranger → Sound Engineer → Editor, dll)

#### 3B. Jika REJECTED

**Request Body:**
```json
{
  "rejection_notes": "Proposal kurang lengkap, perlu revisi"
}
```

**Yang Terjadi:**
1. Status berubah: `pending_approval` → `rejected`
2. `rejected_by` = ID Manager Broadcasting
3. `rejected_at` = Timestamp rejection
4. **Notifikasi dikirim ke Manager Program** dengan alasan penolakan
5. Program bisa di-edit dan di-submit lagi

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Program Musik Live",
    "status": "rejected", // ← Status rejected
    "rejected_by": 5,
    "rejected_at": "2025-12-10 11:00:00",
    "rejection_notes": "Proposal kurang lengkap, perlu revisi"
  },
  "message": "Program rejected successfully"
}
```

**Setelah Rejected:**
- Manager Program bisa edit program
- Bisa submit ulang setelah revisi
- Status akan kembali ke `draft` setelah di-edit

---

## 📋 Ringkasan Workflow

```
┌─────────────────────────────────────────────────────────┐
│ STEP 1: Manager Program Membuat Program               │
│ POST /api/live-tv/programs                             │
│ Status: draft                                          │
│ - Auto-generate 53 episodes                            │
│ - Auto-generate deadlines                              │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 2: Manager Program Submit untuk Approval         │
│ POST /api/live-tv/programs/{id}/submit                 │
│ Status: draft → pending_approval                       │
│ - Notifikasi ke Manager Broadcasting                   │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 3: Manager Broadcasting Review                   │
│                                                         │
│ A. APPROVE                                             │
│ POST /api/live-tv/programs/{id}/approve                │
│ Status: pending_approval → approved                     │
│ - Episodes ready untuk produksi                        │
│ - Notifikasi ke Manager Program & Production Team      │
│                                                         │
│ B. REJECT                                              │
│ POST /api/live-tv/programs/{id}/reject                 │
│ Status: pending_approval → rejected                    │
│ - Manager Program bisa revisi & submit ulang          │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ STEP 4: Produksi Dimulai (jika approved)              │
│ - Manager Program assign production team              │
│ - Producer mulai workflow                             │
│ - Music Arranger → Sound Engineer → Editor            │
└─────────────────────────────────────────────────────────┘
```

---

## 🔑 Key Points

### 1. Status Default Setelah Create
- **Status:** `draft`
- Program belum bisa langsung produksi
- Harus di-submit dulu untuk approval

### 2. Submit Program
- **Hanya bisa submit jika status `draft`**
- **Hanya Manager Program yang membuat program yang bisa submit**
- Setelah submit, status menjadi `pending_approval`
- Program tidak bisa di-edit lagi sampai di-approve/reject

### 3. Siapa yang Approve?
- **Manager Broadcasting (Distribution Manager)**
- Bukan Manager Program sendiri
- Approval diperlukan sebelum program bisa produksi

### 4. Setelah Approved
- Status menjadi `approved`
- Semua episodes status menjadi `approved_for_production`
- Program siap untuk produksi
- Manager Program bisa assign production team
- Producer bisa mulai workflow

### 5. Setelah Rejected
- Status menjadi `rejected`
- Manager Program bisa edit program
- Bisa submit ulang setelah revisi
- Status akan kembali ke `draft` setelah di-edit

---

## 📝 Contoh Skenario Lengkap

### Skenario 1: Program Berhasil Di-approve

```
1. Manager Program create program
   → Status: draft
   → Episodes: 53 episodes generated
   → Deadlines: Auto-generated

2. Manager Program submit program
   → Status: draft → pending_approval
   → Notifikasi ke Manager Broadcasting

3. Manager Broadcasting approve
   → Status: pending_approval → approved
   → Episodes: approved_for_production
   → Notifikasi ke Manager Program & Production Team

4. Produksi dimulai
   → Manager Program assign team
   → Producer mulai workflow
```

### Skenario 2: Program Ditolak & Revisi

```
1. Manager Program create program
   → Status: draft

2. Manager Program submit program
   → Status: draft → pending_approval

3. Manager Broadcasting reject
   → Status: pending_approval → rejected
   → Alasan: "Proposal kurang lengkap"

4. Manager Program edit program
   → Update proposal file
   → Status: rejected → draft (setelah edit)

5. Manager Program submit ulang
   → Status: draft → pending_approval

6. Manager Broadcasting approve
   → Status: pending_approval → approved
```

---

## 🚨 Validasi & Error Handling

### Error: Submit Program yang Bukan Draft

**Request:**
```bash
POST /api/live-tv/programs/1/submit
```

**Response (400):**
```json
{
  "success": false,
  "message": "Program can only be submitted from draft status"
}
```

### Error: Submit Program oleh User yang Tidak Berwenang

**Response (403):**
```json
{
  "success": false,
  "message": "Unauthorized: Only Manager Program of this program can submit for approval"
}
```

### Error: Approve Program yang Bukan Pending Approval

**Response (400):**
```json
{
  "success": false,
  "message": "Program can only be approved from pending_approval status"
}
```

---

## 📌 API Endpoints Summary

| Action | Endpoint | Method | Status Change |
|--------|----------|--------|---------------|
| Create Program | `/api/live-tv/programs` | POST | → `draft` |
| Submit Program | `/api/live-tv/programs/{id}/submit` | POST | `draft` → `pending_approval` |
| Approve Program | `/api/live-tv/programs/{id}/approve` | POST | `pending_approval` → `approved` |
| Reject Program | `/api/live-tv/programs/{id}/reject` | POST | `pending_approval` → `rejected` |
| Update Program | `/api/live-tv/programs/{id}` | PUT | (hanya jika `draft` atau `rejected`) |

---

## ✅ Checklist untuk Frontend

- [ ] Tampilkan status program di UI (`draft`, `pending_approval`, `approved`, `rejected`)
- [ ] Tombol "Submit untuk Approval" hanya muncul jika status `draft`
- [ ] Setelah submit, disable tombol edit program
- [ ] Tampilkan notifikasi ke Manager Broadcasting saat program di-submit
- [ ] Tampilkan status approval di dashboard Manager Broadcasting
- [ ] Setelah approved, tampilkan tombol "Assign Team" untuk Manager Program
- [ ] Setelah rejected, tampilkan alasan penolakan dan tombol "Edit & Submit Ulang"

---

**Last Updated:** December 10, 2025

