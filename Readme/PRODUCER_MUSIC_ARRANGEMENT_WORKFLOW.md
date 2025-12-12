    # 🎵 Producer - Music Arrangement Workflow

    Dokumentasi workflow Producer untuk menangani usulan lagu dan penyanyi dari Music Arranger.

    ---

    ## 📋 Checklist Flow Producer

    ### ✅ 1. Terima Notifikasi

    **Status:** ✅ **SUDAH ADA**

    **Endpoint:**
    - `GET /api/live-tv/producer/approvals` - Melihat semua pending approvals termasuk music arrangements

    **Notifikasi yang diterima:**
    - ✅ `music_arrangement_submitted` - Music Arranger submit arrangement baru
    - ✅ `music_arrangement_created` - Music Arranger create arrangement (draft)

    **Controller:** `ProducerController.php`
    - Method: `getApprovals()`

    **Response Example:**
    ```json
    {
    "success": true,
    "data": {
        "music_arrangements": [
        {
            "id": 1,
            "episode_id": 1,
            "song_title": "Amazing Grace",
            "singer_name": "John Doe",
            "song_id": 5,
            "singer_id": 10,
            "status": "submitted",
            "submitted_at": "2025-12-10 10:00:00",
            "created_by": 2,
            "episode": {...},
            "createdBy": {...}
        }
        ]
    }
    }
    ```

    ---

    ### ✅ 2. Terima atau Tidak Usulan Lagu dan Penyanyi

    **Status:** ✅ **SUDAH ADA**

    **Endpoint:**
    - `POST /api/live-tv/producer/approvals/{id}/approve` - Approve arrangement
    - `POST /api/live-tv/producer/approvals/{id}/reject` - Reject arrangement

    **Request Body (Approve):**
    ```json
    {
    "type": "music_arrangement",
    "notes": "Arrangement sudah bagus, approved!"
    }
    ```

    **Request Body (Reject):**
    ```json
    {
    "type": "music_arrangement",
    "reason": "Tempo terlalu cepat, perlu diperlambat"
    }
    ```

    **Flow:**
    1. Producer review arrangement yang status `submitted`
    2. Producer bisa approve atau reject
    3. Jika approve:
    - Status: `submitted` → `approved`
    - Notifikasi ke Music Arranger
    - Auto-create Sound Engineer Recording task
    - Auto-create Creative Work task
    4. Jika reject:
    - Status: `submitted` → `rejected`
    - Notifikasi ke Music Arranger dengan alasan
    - Notifikasi ke Sound Engineers bahwa mereka bisa membantu perbaikan

    **Controller:** `ProducerController.php`
    - Method: `approve()`
    - Method: `reject()`

    **Notifikasi ke Music Arranger:**
    - Type: `music_arrangement_approved` atau `music_arrangement_rejected`
    - Message: Include review notes atau rejection reason

    ---

    ### ✅ 3. Dapat Mengganti Usulan dari Music Arranger

    **Status:** ✅ **SUDAH ADA**

    **Endpoint:**
    - `PUT /api/live-tv/producer/arrangements/{arrangementId}/edit-song-singer` - Edit song/singer sebelum approve

    **Request Body:**
    ```json
    {
    "song_title": "New Song Title",
    "singer_name": "New Singer Name",
    "song_id": 6,
    "singer_id": 11,
    "modification_notes": "Perlu ganti lagu karena lagu sebelumnya sudah pernah digunakan"
    }
    ```

    **Flow:**
    1. Producer dapat edit song/singer arrangement yang status `submitted`
    2. Original values disimpan di `original_song_title` dan `original_singer_name`
    3. Modified values disimpan di `producer_modified_song_title` dan `producer_modified_singer_name`
    4. Flag `producer_modified` di-set menjadi `true`
    5. Status tetap `submitted` (belum approve)
    6. Notifikasi ke Music Arranger tentang perubahan

    **Controller:** `ProducerController.php`
    - Method: `editArrangementSongSinger()`

    **Model:** `MusicArrangement.php`
    - Method: `producerModify()`

    **Notifikasi ke Music Arranger:**
    - Type: `arrangement_modified_by_producer`
    - Message: Include original dan modified values
    - Data: Include modification notes

    **Response Example:**
    ```json
    {
    "success": true,
    "data": {
        "id": 1,
        "song_title": "New Song Title",
        "singer_name": "New Singer Name",
        "original_song_title": "Amazing Grace",
        "original_singer_name": "John Doe",
        "producer_modified": true,
        "producer_modified_at": "2025-12-10 11:00:00",
        "status": "submitted"
    },
    "message": "Arrangement song/singer modified successfully. Music Arranger has been notified."
    }
    ```

    ---

    ### ✅ 4. Selesai Pekerjaan

    **Status:** ✅ **SUDAH ADA (Otomatis)**

    **Penjelasan:**
    Setelah Producer melakukan review (approve/reject/edit), pekerjaan Producer untuk arrangement tersebut sudah otomatis selesai.

    **Flow:**
    1. **Setelah Producer Approve:**
    - Status: `submitted` → `approved`
    - Pekerjaan Producer selesai
    - Workflow lanjut ke Sound Engineer dan Creative Work
    - Notifikasi ke Music Arranger

    2. **Setelah Producer Reject:**
    - Status: `submitted` → `rejected`
    - Pekerjaan Producer selesai
    - Music Arranger bisa revisi atau buat arrangement baru
    - Notifikasi ke Music Arranger dengan alasan rejection

    3. **Setelah Producer Edit Song/Singer:**
    - Status tetap `submitted` (belum approve)
    - Producer bisa langsung approve dengan modified values
    - Atau bisa edit lagi sebelum approve
    - Notifikasi ke Music Arranger tentang perubahan

    **Controller:** `ProducerController.php`
    - Method: `approve()` - Setelah approve, pekerjaan selesai
    - Method: `reject()` - Setelah reject, pekerjaan selesai
    - Method: `editArrangementSongSinger()` - Edit sebelum approve

    **Kesimpulan:**
    Tidak perlu endpoint khusus untuk "selesaikan pekerjaan" karena setelah approve/reject, pekerjaan Producer sudah otomatis selesai dan workflow lanjut.

    ---

    ## 🔄 Current Workflow Status

    ### Status Flow Saat Ini:

    ```
    Music Arranger
    ↓ (Submit)
    submitted
    ↓ (Producer Edit Song/Singer - Optional)
    submitted (with producer_modified = true)
    ↓ (Producer Approve)
    approved
    ↓ (Auto-create tasks)
    Sound Engineer Recording + Creative Work

    OR

    submitted
    ↓ (Producer Reject)
    rejected
    ↓ (Music Arranger can revise)
    draft (if Music Arranger update)
    ```

    ### Status yang Tersedia:

    | Status | Deskripsi | Action Available |
    |--------|-----------|------------------|
    | `draft` | Draft, belum submit | Music Arranger: Update, Submit |
    | `submitted` | Sudah submit, menunggu Producer | Producer: Approve, Reject, Edit Song/Singer |
    | `approved` | Producer approve | Workflow lanjut ke Sound Engineer |
    | `rejected` | Producer reject | Music Arranger: Revisi atau buat baru |

    ---

    ## 📊 Summary Status

    | Fitur | Status | Endpoint | Notes |
    |-------|--------|----------|-------|
    | Terima Notifikasi | ✅ | `/producer/approvals` | Full support |
    | Terima/Tidak Usulan | ✅ | `/producer/approvals/{id}/approve` | Approve & Reject |
    | Ganti Usulan | ✅ | `/producer/arrangements/{id}/edit-song-singer` | Edit song/singer |
    | Selesaikan Pekerjaan | ✅ | Otomatis setelah approve/reject | Pekerjaan selesai otomatis |

    ---

    ## 🔄 Complete Workflow

    ### Flow Lengkap Producer:

    ```
    1. Terima Notifikasi
    ↓
    2. Review Arrangement (Lihat song, singer, file)
    ↓
    3. Pilih Action:
    ├─ A. Approve langsung
    │   └─ Status: submitted → approved
    │   └─ Pekerjaan selesai ✅
    │   └─ Workflow lanjut ke Sound Engineer
    │
    ├─ B. Edit Song/Singer dulu
    │   └─ Edit song/singer
    │   └─ Status: tetap submitted
    │   └─ Notifikasi ke Music Arranger
    │   └─ Producer bisa approve setelah edit
    │   └─ Setelah approve → Pekerjaan selesai ✅
    │
    └─ C. Reject
        └─ Status: submitted → rejected
        └─ Pekerjaan selesai ✅
        └─ Music Arranger bisa revisi
    ```

    ### Timeline Example:

    **Hari 1 - Producer:**
    - Terima notifikasi arrangement submitted
    - Review arrangement
    - Edit song/singer (opsional)
    - Approve arrangement
    - ✅ **Pekerjaan selesai**

    **Hari 2 - Workflow Lanjut:**
    - Sound Engineer dapat arrangement approved
    - Creative Work task dibuat
    - Workflow lanjut ke tahap berikutnya

    ---

    ## ✅ Kesimpulan

    **Status Overall:** 🟢 **100% COMPLETE**

    **Yang Sudah Lengkap:**
    - ✅ Terima notifikasi
    - ✅ Terima/tidak usulan lagu dan penyanyi
    - ✅ Ganti usulan dari Music Arranger
    - ✅ Selesaikan pekerjaan (otomatis setelah approve/reject)

    **Workflow:**
    1. Producer terima notifikasi arrangement submitted
    2. Producer review dan bisa edit song/singer
    3. Producer approve atau reject
    4. Setelah approve/reject, pekerjaan Producer selesai otomatis
    5. Workflow lanjut ke tahap berikutnya (Sound Engineer, Creative Work)

    **Tidak perlu endpoint tambahan** - Semua flow sudah lengkap dan terintegrasi dengan baik.

    ---

    **Last Updated:** December 10, 2025

