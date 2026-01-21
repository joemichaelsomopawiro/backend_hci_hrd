# 🚀 Frontend Implementation Summary: Program Regular

Dokumen ini merangkum implementasi Frontend untuk modul Program Regular yang telah selesai dikerjakan. Dokumen ini ditujukan untuk tim Backend guna memverifikasi kesiapan dan keselarasan integrasi API.

**Status:** ✅ **Frontend Ready for Integration**
**Referensi Utama:** `PROGRAM_REGULAR_FRONTEND_GUIDE.md`

---

## 1. 👥 Role & Workflow Implementation

Frontend telah mengimplementasikan 3 role utama dengan workflow sebagai berikut:

### **A. Manager Program**
*   **Create Program**: Menggunakan endpoint `POST /manager-program/programs`.
*   **Concept Management**: Membuat konsep awal (`POST .../concepts`).
*   **Approval Workflow**:
    *   **Approve Program**: Tombol muncul saat status `submitted_to_manager`. Hit endpoint `POST .../approve`.
    *   **Request Revision**: Tombol muncul saat status `submitted_to_manager`. Hit endpoint `POST /revisions/.../request`.
*   **Handoff**: Submit ke Distribusi (`POST .../submit-to-distribusi`).

### **B. Producer**
*   **Concept Approval**: Menerima/Menolak konsep dari Manager (`POST .../approve` atau `reject`).
*   **Episode Management**:
    *   **Status Update**: Mengubah status episode (Scheduled -> Production -> Editing -> Ready) via `PUT /producer/episodes/{id}/status` & `PUT /producer/episodes/{id}`.
    *   **File Upload**: Implementasi `multipart/form-data` dengan field: `file`, `category` (raw_footage, edited_video, dll), `description`.
*   **Handoff**: Submit ke Manager Program (`POST .../submit-to-manager`).

### **C. Manager Distribusi**
*   **Verification**:
    *   **Verify Program**: Menyetujui program siap tayang (`POST .../verify`).
    *   **Request Revision**: Mengembalikan program ke Manager jika belum layak (`POST /revisions/.../request`).
*   **Scheduling**: Membuat jadwal tayang (`POST .../distribution-schedules`).
*   **Mark as Aired**: Menandai episode sudah tayang (`POST .../mark-aired`).

---

## 2. 🔌 Critical Integration Points (Backend Check)

Mohon backend memastikan endpoint berikut berfungsi sesuai ekspektasi frontend:

### **a. File Upload**
*   **Endpoint**: `POST /producer/episodes/{id}/files`
*   **Payload**: `FormData`
    *   `file`: Binary file (Max 100GB handle di server config).
    *   `category`: Enum string (`raw_footage`, `edited_video`, `thumbnail`, `script`, `rundown`, `other`).
    *   `description`: String (optional).

### **b. Revision Loop**
*   Frontend mengharapkan status program kembali ke `editing` atau status relevan lainnya saat revisi diminta via `POST /revisions/programs/{id}/request`.
*   Frontend mengharapkan data revisi tersimpan di `revision_history` untuk ditampilkan di UI.

### **c. Status Transitions**
Frontend mengandalkan perubahan status otomatis di backend untuk beberapa trigger, namun juga mengirim update manual untuk:
*   **Episode**: Producer mengubah manual dari `production` -> `editing` -> `ready_for_review`.
*   **Program**:
    *   `submitted_to_manager` (Triggered by Producer Submit).
    *   `manager_approved` (Triggered by Manager Approve).
    *   `submitted_to_distribusi` (Triggered by Manager Submit).
    *   `distribusi_approved` (Triggered by Distribusi Verify).

---

## 3. 🛠 Data Structures & Enums

Frontend telah menggunakan Enum yang konsisten dengan dokumentasi:

*   **Program Status**: `draft`, `concept_pending`, `concept_approved`, `production_scheduled`, `in_production`, `editing`, `submitted_to_manager`, `manager_approved`, `submitted_to_distribusi`, `distribusi_approved`, `distributed`.
*   **Episode Status**: `scheduled`, `production`, `editing`, `ready_for_review`, `aired`.
*   **Revision Type**: `concept`, `production`, `editing`, `distribution`.

---

## 4. ✅ Next Steps

1.  **Backend Verification**: Tim backend memverifikasi endpoint di atas (terutama File Upload & Revision) sudah live di server dev/staging.
2.  **Integration Testing**: Frontend akan melakukan tes *end-to-end* sesuai "Skenario Pengujian" yang telah dibuat di file terpisah.
