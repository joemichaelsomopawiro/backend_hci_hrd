# 🎵 **WORKFLOW & API ENDPOINTS - MUSIC ARRANGER (Program Musik)**

## **Base URL:**
```
/api/live-tv/roles/music-arranger
```

---

## **📋 OVERVIEW**

Music Arranger bertanggung jawab untuk:
- **Pilih lagu dan penyanyi** untuk episode
- **Arrange lagu** di software (Cubase, Logic, dll) sesuai kebutuhan program
- **Upload file arrangement** (MP3/WAV/MIDI) hasil arrangement ke sistem
- **Submit arrangement** ke Producer untuk review dan quality control
- **Revisi arrangement** jika ditolak Producer

**PENTING - Upload File Arrangement:**
- ✅ **BISA upload file audio arrangement** (MP3, WAV, MIDI)
- ✅ File bisa diupload saat create ATAU nanti saat update
- ✅ File max 100MB, disimpan di server
- ✅ Producer bisa download/stream file untuk QC
- ✅ Jika update dengan file baru, file lama otomatis dihapus

**Akses Control:**
- Music Arranger hanya bisa melihat arrangement yang mereka buat sendiri
- Music Arranger hanya bisa create arrangement untuk episode dari program ProductionTeam mereka
- Music Arranger harus di-assign sebagai member ProductionTeam dengan role `musik_arr`

---

## **1. ARRANGEMENT MANAGEMENT**

### **Get All Arrangements**
```
GET /live-tv/roles/music-arranger/arrangements?status=draft&episode_id=1
```

**Query Parameters:**
- `status`: optional (draft, submitted, approved, rejected, revised)
- `episode_id`: optional

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "episode_id": 1,
        "song_title": "Lagu Title",
        "singer_name": "Nama Penyanyi",
        "arrangement_notes": "Catatan arrangement",
        "file_path": "music-arrangements/xxx.mp3",
        "file_name": "arrangement.mp3",
        "file_size": 5242880,
        "mime_type": "audio/mpeg",
        "status": "draft",
        "created_by": 5,
        "reviewed_by": null,
        "reviewed_at": null,
        "review_notes": null,
        "rejection_reason": null,
        "episode": {...},
        "createdBy": {...}
      }
    ]
  }
}
```

**Catatan:**
- Hanya menampilkan arrangement yang dibuat oleh Music Arranger yang sedang login
- Auto-filter berdasarkan `created_by = user.id`

---

### **Get Arrangement by ID**
```
GET /live-tv/roles/music-arranger/arrangements/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "episode_id": 1,
    "song_title": "Lagu Title",
    "singer_name": "Nama Penyanyi",
    "arrangement_notes": "...",
    "file_path": "...",
    "status": "draft",
    "episode": {...},
    "createdBy": {...}
  }
}
```

**Catatan:**
- Hanya bisa melihat arrangement yang mereka buat sendiri
- Jika arrangement tidak ditemukan atau bukan milik mereka, return 404

---

### **Create New Arrangement**
```
POST /live-tv/roles/music-arranger/arrangements
Content-Type: multipart/form-data
```

**Request Body:**
```json
{
  "episode_id": 1,
  "song_title": "Lagu Title",
  "singer_name": "Nama Penyanyi (optional)",
  "arrangement_notes": "Catatan arrangement (optional)",
  "file": "[file audio] (optional, max 100MB)"
}
```

**File Types Allowed:**
- `mp3`, `wav`, `midi`
- Max size: 100MB

**Validasi:**
- ✅ Episode harus ada
- ✅ Episode harus memiliki ProductionTeam yang di-assign
- ✅ Music Arranger harus menjadi member ProductionTeam dengan role `musik_arr`
- ✅ Music Arranger harus aktif di ProductionTeam

**Response:**
```json
{
  "success": true,
  "message": "Music arrangement created successfully",
  "data": {
    "id": 1,
    "episode_id": 1,
    "song_title": "Lagu Title",
    "status": "draft",
    "file_path": "music-arrangements/xxx.mp3",
    "episode": {...},
    "createdBy": {...}
  }
}
```

**Error Response (403):**
```json
{
  "success": false,
  "message": "Anda tidak di-assign ke ProductionTeam untuk program episode ini"
}
```

**Catatan:**
- Arrangement dibuat dengan status `draft`
- Auto-create notification untuk Producer
- File audio disimpan di `storage/app/public/music-arrangements/`
- File bisa diakses via URL: `{base_url}/storage/music-arrangements/{filename}`
- Producer bisa download/stream file untuk quality control

---

### **Update Arrangement**
```
PUT /live-tv/roles/music-arranger/arrangements/{id}
Content-Type: multipart/form-data
```

**Request Body:**
```json
{
  "song_title": "Updated Title (optional)",
  "arrangement_notes": "Updated notes (optional)",
  "file": "[new file audio] (optional)"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Arrangement updated successfully",
  "data": {
    "id": 1,
    "song_title": "Updated Title",
    "status": "draft",
    "episode": {...}
  }
}
```

**Catatan:**
- Hanya bisa update arrangement yang mereka buat sendiri
- Hanya bisa update jika status masih `draft`
- Jika upload file baru, file lama akan dihapus otomatis

---

### **Submit Arrangement for Review**
```
POST /live-tv/roles/music-arranger/arrangements/{id}/submit
```

**Response:**
```json
{
  "success": true,
  "message": "Arrangement submitted for review",
  "data": {
    "id": 1,
    "status": "submitted",
    "submitted_at": "2025-01-15T10:00:00.000000Z",
    "episode": {...}
  }
}
```

**Catatan:**
- Hanya bisa submit arrangement dengan status `draft`
- Status berubah menjadi `submitted`
- Auto-create notification untuk Producer
- Producer akan menerima notifikasi untuk review

---

## **2. STATISTICS**

### **Get Statistics**
```
GET /live-tv/roles/music-arranger/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_arrangements": 15,
    "draft": 3,
    "submitted": 5,
    "approved": 6,
    "rejected": 1
  }
}
```

**Catatan:**
- Menampilkan statistik arrangement yang dibuat oleh Music Arranger
- Breakdown berdasarkan status

---

## **🔄 WORKFLOW LENGKAP MUSIC ARRANGER - PENJELASAN DETAIL:**

### **STEP 1: CREATE ARRANGEMENT (Buat Arrangement Baru)**

Music Arranger membuat arrangement baru untuk episode tertentu:

**Action:**
```
POST /live-tv/roles/music-arranger/arrangements
Content-Type: multipart/form-data
```

**Input yang WAJIB:**
- ✅ `episode_id` - Episode yang akan di-arrange
- ✅ `song_title` - Judul lagu yang akan di-arrange

**Input yang OPSIONAL:**
- ⚠️ `singer_name` - Nama penyanyi (bisa kosong)
- ⚠️ `arrangement_notes` - Catatan arrangement (bisa kosong)
- ⚠️ `file` - **FILE AUDIO ARRANGEMENT** (bisa upload sekarang atau nanti)

**File Audio Arrangement:**
- **Format:** `mp3`, `wav`, atau `midi`
- **Max Size:** 100MB
- **Lokasi Simpan:** `storage/app/public/music-arrangements/`
- **Status:** File BISA diupload saat create ATAU nanti saat update

**Hasil:**
- ✅ Arrangement dibuat dengan status `draft`
- ✅ File audio (jika diupload) tersimpan di server
- ✅ Producer menerima notifikasi: "New Music Arrangement Created"
- ✅ Music Arranger bisa langsung edit atau submit nanti

---

### **STEP 2: UPDATE ARRANGEMENT (Edit Arrangement)**

Jika Music Arranger perlu mengubah arrangement atau belum upload file:

**Action:**
```
PUT /live-tv/roles/music-arranger/arrangements/{id}
Content-Type: multipart/form-data
```

**Yang BISA Diubah:**
- ✅ `song_title` - Ubah judul lagu
- ✅ `arrangement_notes` - Ubah catatan
- ✅ `file` - **UPLOAD FILE BARU** (jika belum upload atau mau ganti file)

**Catatan Penting:**
- ⚠️ Hanya bisa update jika status masih `draft`
- ⚠️ Jika upload file baru, file lama otomatis DIHAPUS
- ⚠️ Bisa update berkali-kali selama masih `draft`

**Contoh Skenario:**
1. Create arrangement tanpa file → Status: `draft`
2. Arrange lagu di software luar → Simpan jadi file audio
3. Update arrangement → Upload file audio → Status tetap `draft`
4. Submit arrangement → Status: `submitted`

---

### **STEP 3: SUBMIT ARRANGEMENT (Kirim ke Producer untuk Review)**

Setelah arrangement selesai dan file sudah diupload (jika ada), Music Arranger submit:

**Action:**
```
POST /live-tv/roles/music-arranger/arrangements/{id}/submit
```

**Syarat Submit:**
- ✅ Status harus `draft`
- ⚠️ File audio BISA ada atau tidak (tidak wajib, tapi disarankan ada)

**Hasil:**
- ✅ Status berubah: `draft` → `submitted`
- ✅ Producer menerima notifikasi: "Music Arrangement Submitted for Review"
- ✅ Music Arranger TIDAK bisa edit lagi sampai Producer review selesai

---

### **STEP 4: PRODUCER REVIEW (Menunggu Producer Review)**

Producer akan review arrangement yang sudah di-submit:

**Yang Producer Lakukan:**
- ✅ Lihat arrangement (judul lagu, penyanyi, catatan)
- ✅ **Download/stream file audio** (jika ada) untuk didengarkan
- ✅ Approve atau Reject

**Hasil dari Producer:**

**A. JIKA APPROVED:**
- ✅ Status berubah: `submitted` → `approved`
- ✅ Music Arranger mendapat notifikasi
- ✅ Workflow lanjut ke **Sound Engineer** untuk proses rekaman

**B. JIKA REJECTED:**
- ✅ Status berubah: `submitted` → `rejected`
- ✅ Music Arranger mendapat notifikasi + alasan penolakan
- ✅ Music Arranger bisa:
  - Buat arrangement baru untuk episode yang sama, ATAU
  - Update arrangement yang ditolak (jika masih bisa)

---

### **INTEGRASI DENGAN WORKFLOW PROGRAM MUSIK:**

```
┌─────────────────────────────────────────────────────────┐
│ PHASE 1: MUSIC PRODUCTION                              │
└─────────────────────────────────────────────────────────┘

1. Music Arranger
   ├── Pilih lagu & penyanyi
   ├── Arrange lagu (di software)
   ├── Upload file arrangement (MP3/WAV/MIDI)
   └── Submit ke Producer

2. Producer Review
   ├── Download/stream file arrangement
   ├── Dengarkan & QC
   └── Approve/Reject

3a. Jika Approved:
    └── Lanjut ke Sound Engineer
        ├── Sound Engineer rekam vocal
        └── Producer QC final

3b. Jika Rejected:
    └── Kembali ke Music Arranger (revisi)
```

---

### **CONTOH WORKFLOW NYATA:**

**Scenario: Episode 1 - Program Musik "Hope Channel Live"**

1. **Hari 1 - Music Arranger:**
   - Buka dashboard → Lihat episode 1
   - Create arrangement:
     - Episode: Episode 1
     - Song Title: "Amazing Grace"
     - Singer: "John Doe"
     - Notes: "Slow tempo, jazz arrangement"
     - File: (belum upload, masih arrange)
   - Status: `draft`

2. **Hari 2 - Music Arranger:**
   - Selesai arrange di Cubase
   - Update arrangement → Upload file `amazing_grace_jazz.mp3`
   - Status: `draft` (masih bisa edit)

3. **Hari 3 - Music Arranger:**
   - Review file → Sudah OK
   - Submit arrangement
   - Status: `submitted`
   - Producer dapat notifikasi

4. **Hari 3 - Producer:**
   - Buka notifikasi
   - Download file `amazing_grace_jazz.mp3`
   - Dengarkan → QC
   - Approve
   - Status: `approved`

5. **Hari 4 - Sound Engineer:**
   - Terima arrangement yang approved
   - Rekam vocal sesuai arrangement
   - Lanjut ke proses berikutnya...

---

## **📊 STATUS ARRANGEMENT:**

| Status | Deskripsi | Action Available |
|--------|-----------|------------------|
| `draft` | Draft, belum submit | ✅ Update, ✅ Submit, ✅ Delete |
| `submitted` | Sudah submit, menunggu review Producer | ❌ Update, ❌ Submit |
| `approved` | Disetujui Producer | ❌ Update, ❌ Submit |
| `rejected` | Ditolak Producer | ✅ Create new, ✅ Update existing |
| `revised` | Sudah direvisi setelah rejection | ❌ Update, ✅ Submit |

---

## **🔒 PERMISSIONS & VALIDATIONS:**

### **BISA:**
- ✅ Create arrangement untuk episode dari program ProductionTeam mereka
- ✅ View semua arrangement yang mereka buat sendiri
- ✅ Update arrangement dengan status `draft`
- ✅ Submit arrangement untuk review Producer
- ✅ View statistics arrangement mereka

### **TIDAK BISA:**
- ❌ Create arrangement untuk episode dari program ProductionTeam lain
- ❌ View arrangement yang dibuat oleh Music Arranger lain
- ❌ Update arrangement yang sudah di-submit
- ❌ Approve/reject arrangement (itu tugas Producer)
- ❌ Delete arrangement yang sudah di-submit

---

## **🔗 RELATIONSHIP:**

```
Music Arranger
  └── User.role = 'Music Arranger'
       └── ProductionTeamMember
            └── role = 'musik_arr'
                 └── ProductionTeam
                      └── Program.production_team_id
                           └── Episode.program_id
                                └── MusicArrangement.episode_id
```

**Struktur:**
- Music Arranger → ProductionTeamMember (role: `musik_arr`)
- ProductionTeam → Program (production_team_id)
- Program → Episode (program_id)
- Episode → MusicArrangement (episode_id)

---

## **⚠️ VALIDATION RULES:**

### **Create Arrangement:**
1. ✅ User harus authenticated
2. ✅ User role harus `Music Arranger`
3. ✅ Episode harus ada di database
4. ✅ Episode harus memiliki ProductionTeam yang di-assign
5. ✅ Music Arranger harus menjadi member ProductionTeam dengan role `musik_arr`
6. ✅ Music Arranger harus aktif (`is_active = true`)
7. ✅ File audio (jika ada) harus format: `mp3`, `wav`, atau `midi`
8. ✅ File audio max size: 100MB

### **Update Arrangement:**
1. ✅ Arrangement harus ada
2. ✅ Arrangement harus dibuat oleh Music Arranger yang sedang login
3. ✅ Status arrangement harus `draft`

### **Submit Arrangement:**
1. ✅ Arrangement harus ada
2. ✅ Arrangement harus dibuat oleh Music Arranger yang sedang login
3. ✅ Status arrangement harus `draft`

---

## **📝 ENDPOINT SUMMARY:**

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/arrangements` | Get all arrangements (user sendiri) |
| GET | `/arrangements/{id}` | Get arrangement by ID (user sendiri) |
| POST | `/arrangements` | Create new arrangement |
| PUT | `/arrangements/{id}` | Update arrangement (draft only) |
| POST | `/arrangements/{id}/submit` | Submit arrangement for review |
| GET | `/statistics` | Get arrangement statistics |

**Total: 6 Endpoints**

---

## **🔔 NOTIFICATIONS:**

### **Arrangement Created:**
- Producer menerima notifikasi: `music_arrangement_created`
- Title: "New Music Arrangement Created"
- Data: `arrangement_id`, `episode_id`

### **Arrangement Submitted:**
- Producer menerima notifikasi: `music_arrangement_submitted`
- Title: "Music Arrangement Submitted for Review"
- Data: `arrangement_id`, `episode_id`

---

## **✅ KESIMPULAN:**

✅ **Validasi sudah benar:**
- Music Arranger hanya bisa create arrangement untuk episode dari program ProductionTeam mereka
- Music Arranger hanya bisa melihat arrangement yang mereka buat sendiri
- Semua validasi sudah sesuai dengan struktur ProductionTeam

✅ **Workflow sudah lengkap:**
- Create → Update → Submit → Producer Review → Approved/Rejected

✅ **Endpoints sudah lengkap:**
- CRUD untuk Arrangement
- Submit untuk Review
- Statistics

**Semua endpoint memerlukan:**
- **Authentication:** `Bearer {token}`
- **Role:** `Music Arranger`

**Semua endpoint mengembalikan response dalam format:**
```json
{
  "success": true/false,
  "data": {...},
  "message": "..."
}
```


