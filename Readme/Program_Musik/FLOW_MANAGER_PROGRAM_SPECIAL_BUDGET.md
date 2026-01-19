# Flow Manager Program - Special Budget Approval dari Producer

## ✅ STATUS: **SUDAH LENGKAP & READY**

Manager Program dapat menangani permintaan budget khusus dari Producer dengan semua fitur yang diperlukan.

---

## 📋 WORKFLOW LENGKAP

```
Producer Ajukan Budget Khusus
    ↓
Manager Program
    ↓ Terima Notifikasi Permintaan
    ↓ Get Special Budget Approvals (Pending)
    ↓ Review Budget Request
    ↓ 
    ├─► Option 1: ACC Budget Khusus
    │   ├─► ACC dengan amount yang diminta (tanpa edit)
    │   └─► ACC dengan amount yang di-edit (jika tidak sesuai)
    │
    └─► Option 2: Tolak Budget Khusus
        └─► Reject dengan alasan
```

---

## 📋 ENDPOINT YANG TERSEDIA

### 1. **Terima Notifikasi Permintaan Budget Khusus** ⭐
**Endpoint:** `GET /api/live-tv/notifications?type=special_budget_request`

**Fungsi:** Get notifikasi bahwa Producer telah mengajukan budget khusus

**Response:**
```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 1,
        "type": "special_budget_request",
        "title": "Permintaan Budget Khusus",
        "message": "Producer meminta budget khusus sebesar Rp 5.000.000 untuk Episode 001 dari Program 'Program A'. Alasan: Perlu tambahan budget untuk talent khusus",
        "data": {
          "approval_id": 1,
          "creative_work_id": 1,
          "episode_id": 1,
          "program_id": 1,
          "program_name": "Program A",
          "budget_amount": 5000000
        },
        "read_at": null,
        "created_at": "2026-01-27T13:00:00.000000Z"
      }
    ]
  }
}
```

**Tersedia di Postman:** ✅ Yes

---

### 2. **Get Special Budget Approvals (Pending)** ⭐
**Endpoint:** `GET /api/live-tv/manager-program/special-budget-approvals`

**Fungsi:** Get semua special budget approval requests yang pending untuk program yang dikelola Manager Program

**Query Parameters:**
- `status` (optional): Filter by status (`pending`, `approved`, `rejected`)

**Response:**
```json
{
  "success": true,
  "data": {
    "special_budgets": [
      {
        "id": 1,
        "approval_type": "special_budget",
        "status": "pending",
        "special_budget_amount": 5000000,
        "requested_amount": 5000000,
        "special_budget_reason": "Perlu tambahan budget untuk talent khusus yang lebih mahal",
        "requested_by": {
          "id": 5,
          "name": "Producer Name"
        },
        "creative_work": {
          "id": 1,
          "episode_id": 1,
          "episode_number": 1,
          "program": {
            "id": 1,
            "name": "Program A"
          }
        },
        "requested_at": "2026-01-27T13:00:00.000000Z",
        "formatted_amount": "Rp 5.000.000"
      }
    ],
    "total_pending": 1,
    "total_all": 1
  },
  "message": "Special budget approvals retrieved successfully"
}
```

**Fitur:**
- ✅ Hanya menampilkan budget requests untuk program yang dikelola Manager Program
- ✅ Filter berdasarkan `manager_program_id` di Program
- ✅ Menampilkan detail: amount, reason, creative work, episode, program

**Tersedia di Postman:** ✅ Yes

---

### 3. **ACC Budget Khusus (Approve dengan Amount yang Diminta)** ⭐
**Endpoint:** `POST /api/live-tv/manager-program/special-budget-approvals/{id}/approve`

**Fungsi:** Approve budget khusus dengan amount yang diminta Producer (tanpa edit)

**Request Body:**
```json
{
  "approved_amount": null,
  "approval_notes": "Budget disetujui sesuai permintaan"
}
```

**Atau (lebih simple, karena approved_amount null akan menggunakan requested_amount):**
```json
{
  "approval_notes": "Budget disetujui sesuai permintaan"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "approval": {
      "id": 1,
      "status": "approved",
      "approved_by": 2,
      "approved_at": "2026-01-27T14:00:00.000000Z",
      "approval_notes": "Budget disetujui sesuai permintaan",
      "request_data": {
        "special_budget_amount": 5000000,
        "approved_amount": 5000000,
        "is_revised": false
      }
    },
    "creative_work": {
      "id": 1,
      "requires_special_budget_approval": false,
      "budget_approved": true,
      "budget_data": [
        {
          "category": "Special Budget",
          "amount": 5000000,
          "is_special_budget": true,
          "approved_amount": 5000000,
          "requested_amount": 5000000,
          "is_revised": false
        }
      ]
    }
  },
  "message": "Special budget approved successfully. Producer and Creative have been notified."
}
```

**Fitur:**
- ✅ Approve dengan amount yang diminta Producer
- ✅ Update Creative Work budget_data dengan special budget item
- ✅ **Producer di-notify** ✅
- ✅ **Creative di-notify** ✅
- ✅ Set `requires_special_budget_approval = false`
- ✅ Set `budget_approved = true`

**Tersedia di Postman:** ✅ Yes

---

### 4. **ACC Budget Khusus dengan Edit Amount (Jika Tidak Sesuai)** ⭐
**Endpoint:** `POST /api/live-tv/manager-program/special-budget-approvals/{id}/approve`

**Fungsi:** Approve budget khusus dengan amount yang di-edit Manager Program (jika budget tidak sesuai)

**Request Body:**
```json
{
  "approved_amount": 3000000,
  "approval_notes": "Budget disetujui dengan revisi: dari Rp 5.000.000 menjadi Rp 3.000.000 karena ada penghematan"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "approval": {
      "id": 1,
      "status": "approved",
      "approved_by": 2,
      "approved_at": "2026-01-27T14:00:00.000000Z",
      "approval_notes": "Budget disetujui dengan revisi: dari Rp 5.000.000 menjadi Rp 3.000.000 karena ada penghematan",
      "request_data": {
        "special_budget_amount": 5000000,
        "approved_amount": 3000000,
        "is_revised": true
      }
    },
    "creative_work": {
      "id": 1,
      "requires_special_budget_approval": false,
      "budget_approved": true,
      "budget_data": [
        {
          "category": "Special Budget",
          "amount": 3000000,
          "is_special_budget": true,
          "approved_amount": 3000000,
          "requested_amount": 5000000,
          "is_revised": true
        }
      ]
    }
  },
  "message": "Special budget approved with revised amount. Producer and Creative have been notified."
}
```

**Fitur:**
- ✅ Manager Program bisa edit `approved_amount` (kurang dari atau sama dengan requested)
- ✅ Track `requested_amount` vs `approved_amount`
- ✅ Set flag `is_revised = true` jika amount berbeda
- ✅ Update Creative Work budget_data dengan amount yang sudah di-edit
- ✅ **Producer di-notify** dengan info revisi ✅
- ✅ **Creative di-notify** dengan info revisi ✅

**Catatan:**
- `approved_amount` adalah **nullable**
- Jika `approved_amount` tidak diisi atau null, akan menggunakan `requested_amount`
- Jika `approved_amount` diisi, akan menggunakan amount yang diisi (bisa lebih kecil atau sama)

**Tersedia di Postman:** ✅ Yes

---

### 5. **Tolak Budget Khusus** ⭐
**Endpoint:** `POST /api/live-tv/manager-program/special-budget-approvals/{id}/reject`

**Fungsi:** Reject budget khusus jika tidak sesuai

**Request Body:**
```json
{
  "rejection_notes": "Budget terlalu besar, tidak sesuai dengan alokasi program. Silakan gunakan budget normal atau ajukan kembali dengan amount yang lebih kecil"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "approval": {
      "id": 1,
      "status": "rejected",
      "rejected_by": 2,
      "rejected_at": "2026-01-27T14:00:00.000000Z",
      "rejection_notes": "Budget terlalu besar, tidak sesuai dengan alokasi program..."
    },
    "creative_work": {
      "id": 1,
      "requires_special_budget_approval": false,
      "budget_approved": false,
      "special_budget_reason": null,
      "budget_data": [
        // Special budget item dihapus dari budget_data
      ]
    }
  },
  "message": "Special budget rejected. Producer and Creative have been notified."
}
```

**Fitur:**
- ✅ Reject budget khusus dengan alasan
- ✅ Hapus special budget item dari Creative Work budget_data
- ✅ Reset `requires_special_budget_approval = false`
- ✅ Reset `budget_approved = false`
- ✅ Reset `special_budget_reason = null`
- ✅ **Producer di-notify** dengan alasan rejection ✅
- ✅ **Creative di-notify** dengan alasan rejection ✅

**Tersedia di Postman:** ✅ Yes

---

## 🔄 WORKFLOW LENGKAP

### **Step 1: Producer Ajukan Budget Khusus**
```http
POST /api/live-tv/producer/creative-works/{id}/request-special-budget
Content-Type: application/json

{
  "special_budget_amount": 5000000,
  "special_budget_reason": "Perlu tambahan budget untuk talent khusus"
}
```

**Hasil:**
- ✅ Special Budget Approval dibuat dengan status: `pending`
- ✅ **Manager Program di-notify** ✅

---

### **Step 2: Manager Program Terima Notifikasi**
```http
GET /api/live-tv/notifications?type=special_budget_request
```

**Hasil:**
- ✅ Manager Program melihat notifikasi ada permintaan budget khusus

---

### **Step 3: Manager Program Lihat Pending Approvals**
```http
GET /api/live-tv/manager-program/special-budget-approvals
```

**Hasil:**
- ✅ Manager Program melihat daftar special budget requests yang pending
- ✅ Melihat detail: amount, reason, episode, program

---

### **Step 4: Manager Program Review & Decide**

**Option A: ACC dengan Amount yang Diminta (Tanpa Edit)**
```http
POST /api/live-tv/manager-program/special-budget-approvals/{id}/approve
Content-Type: application/json

{
  "approval_notes": "Budget disetujui sesuai permintaan"
}
```

**Hasil:**
- ✅ Status: `approved`
- ✅ `approved_amount` = `requested_amount` (5000000)
- ✅ `is_revised` = false
- ✅ Creative Work budget_data updated
- ✅ Producer & Creative di-notify

---

**Option B: ACC dengan Amount yang Di-edit (Jika Tidak Sesuai)**
```http
POST /api/live-tv/manager-program/special-budget-approvals/{id}/approve
Content-Type: application/json

{
  "approved_amount": 3000000,
  "approval_notes": "Budget disetujui dengan revisi: dari Rp 5.000.000 menjadi Rp 3.000.000"
}
```

**Hasil:**
- ✅ Status: `approved`
- ✅ `approved_amount` = 3000000 (di-edit oleh Manager)
- ✅ `requested_amount` = 5000000 (tetap)
- ✅ `is_revised` = true
- ✅ Creative Work budget_data updated dengan amount yang sudah di-edit
- ✅ Producer & Creative di-notify dengan info revisi

---

**Option C: Tolak Budget Khusus**
```http
POST /api/live-tv/manager-program/special-budget-approvals/{id}/reject
Content-Type: application/json

{
  "rejection_notes": "Budget terlalu besar, tidak sesuai dengan alokasi program"
}
```

**Hasil:**
- ✅ Status: `rejected`
- ✅ Special budget item dihapus dari Creative Work budget_data
- ✅ Producer & Creative di-notify dengan alasan rejection

---

## 📝 CONTOH KASUS PENGGUNAAN

### **Kasus 1: ACC dengan Amount yang Diminta**
```
1. Producer ajukan: Rp 5.000.000
2. Manager Program approve tanpa edit
   → approved_amount: Rp 5.000.000 (sama dengan requested)
   → is_revised: false
```

---

### **Kasus 2: ACC dengan Amount yang Di-edit**
```
1. Producer ajukan: Rp 5.000.000
2. Manager Program review, anggap terlalu besar
3. Manager Program approve dengan amount yang di-edit: Rp 3.000.000
   → approved_amount: Rp 3.000.000 (di-edit)
   → requested_amount: Rp 5.000.000 (tetap)
   → is_revised: true
4. Producer & Creative di-notify tentang revisi
```

---

### **Kasus 3: Tolak Budget Khusus**
```
1. Producer ajukan: Rp 5.000.000
2. Manager Program review, anggap tidak sesuai
3. Manager Program reject dengan alasan
   → Status: rejected
   → Special budget item dihapus
   → Producer & Creative di-notify dengan alasan
```

---

## ✅ VALIDATION & SECURITY

### **Access Control:**
- ✅ Hanya Manager Program yang mengelola program tersebut yang bisa approve/reject
- ✅ Validasi: `program->manager_program_id === user->id`
- ✅ Tidak bisa approve/reject approval yang sudah diproses

### **Amount Validation:**
- ✅ `approved_amount` harus numeric, min: 0
- ✅ `approved_amount` nullable (jika null, gunakan requested_amount)
- ✅ Manager bisa set amount lebih kecil dari requested (untuk revisi)

### **Notifications:**
- ✅ Manager Program di-notify saat Producer ajukan budget khusus
- ✅ Producer di-notify saat Manager approve/reject
- ✅ Creative di-notify saat Manager approve/reject
- ✅ Notifikasi berisi info revisi jika amount di-edit

---

## 📋 ENDPOINT SUMMARY UNTUK FRONTEND

| Fitur | Endpoint | Method | Body |
|-------|----------|--------|------|
| Get Notifications | `/api/live-tv/notifications?type=special_budget_request` | GET | - |
| Get Pending Approvals | `/api/live-tv/manager-program/special-budget-approvals` | GET | - |
| Approve (Amount Diminta) | `/api/live-tv/manager-program/special-budget-approvals/{id}/approve` | POST | `{approval_notes}` |
| Approve (Amount Di-edit) | `/api/live-tv/manager-program/special-budget-approvals/{id}/approve` | POST | `{approved_amount, approval_notes}` |
| Reject | `/api/live-tv/manager-program/special-budget-approvals/{id}/reject` | POST | `{rejection_notes}` |

---

## ✅ VERIFIKASI SEMUA FITUR

### **Manager Program Special Budget Approval:**

1. ✅ **Terima Notifikasi Permintaan** - Endpoint get notifications tersedia
2. ✅ **ACC Budget Khusus (Amount Diminta)** - Endpoint approve tersedia (approved_amount null)
3. ✅ **ACC Budget Khusus (Amount Di-edit)** - Endpoint approve tersedia (approved_amount diisi)
4. ✅ **Tolak Budget Khusus** - Endpoint reject tersedia

### **Yang Sudah Bekerja:**

- ✅ Notifikasi otomatis saat Producer ajukan budget khusus
- ✅ Manager Program bisa approve dengan amount yang diminta
- ✅ Manager Program bisa edit approved_amount jika tidak sesuai
- ✅ Track requested_amount vs approved_amount
- ✅ Flag is_revised jika amount berbeda
- ✅ Update Creative Work budget_data dengan special budget item
- ✅ Hapus special budget item jika reject
- ✅ Notifikasi ke Producer & Creative
- ✅ Validasi akses (hanya Manager Program yang mengelola program)
- ✅ Postman collection sudah tersedia

---

## 🎯 KESIMPULAN

### ✅ **Semua Fitur Sudah Ada:**

1. ✅ **Terima Notifikasi Permintaan** - Endpoint tersedia
2. ✅ **ACC Budget Khusus** - Endpoint approve tersedia (dengan atau tanpa edit amount)
3. ✅ **Edit Amount yang Diizinkan** - Parameter `approved_amount` nullable, bisa diisi untuk edit
4. ✅ **Tolak Budget Khusus** - Endpoint reject tersedia

**Status:** ✅ **READY FOR FRONTEND INTEGRATION**

---

**Last Updated:** 2026-01-27
