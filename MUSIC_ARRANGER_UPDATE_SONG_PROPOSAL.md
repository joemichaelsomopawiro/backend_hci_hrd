# Music Arranger - Update Song Proposal & Upload Arrangement File

## ✅ Fitur yang Tersedia

### 1. Update Song Proposal (Sebelum di-Submit ke Producer)

Music Arranger dapat **mengupdate song proposal** sebelum di-submit ke Producer atau sebelum Producer memeriksa.

**Endpoint:**
```
PUT /api/live-tv/roles/music-arranger/arrangements/{id}
```

**Request Body:**
```json
{
  "song_title": "Updated Song Title",
  "singer_name": "Updated Singer Name",
  "arrangement_notes": "Updated notes"
}
```

**Status yang Bisa di-Update:**
- ✅ `song_proposal` - Song proposal yang belum di-submit atau sudah di-submit tapi belum di-review Producer
- ✅ `draft` - Arrangement draft (backward compatibility)
- ✅ `song_approved` - Setelah Producer approve song proposal
- ✅ `arrangement_in_progress` - Sedang arrange lagu
- ✅ `arrangement_rejected` - Setelah arrangement ditolak Producer

**Response:**
```json
{
  "success": true,
  "message": "Arrangement updated successfully",
  "data": {
    "id": 37,
    "song_title": "Updated Song Title",
    "singer_name": "Updated Singer Name",
    "status": "song_proposal",
    ...
  }
}
```

---

### 2. Upload Arrangement File (Setelah Song Proposal Dibuat)

Music Arranger dapat **mengupload file arrangement** setelah song proposal dibuat, **bahkan sebelum Producer approve**.

**Endpoint:**
```
POST /api/live-tv/roles/music-arranger/arrangements/{id}/upload-file
```

**Request Body (Form Data):**
```
file: <audio_file.mp3>
```

**Status yang Bisa Upload File:**
- ✅ `song_proposal` - Upload file untuk preview (status tetap `song_proposal`)
- ✅ `song_approved` - Upload file dan auto-submit ke Producer (status jadi `arrangement_submitted`)
- ✅ `arrangement_in_progress` - Upload file dan auto-submit ke Producer (status jadi `arrangement_submitted`)
- ✅ `arrangement_rejected` - Upload file perbaikan (status jadi `arrangement_in_progress`)

**Response untuk Status `song_proposal`:**
```json
{
  "success": true,
  "message": "Arrangement file uploaded successfully. File will be available after Producer approves the song proposal.",
  "data": {
    "id": 37,
    "song_title": "Song Title",
    "status": "song_proposal",
    "file_path": "music-arrangements/xxx.mp3",
    "file_name": "audio.mp3",
    "file_size": 3664989,
    "mime_type": "audio/mpeg",
    "file_url": "http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/37/file?expires=...&signature=...",
    ...
  },
  "file_uploaded": true,
  "file_info": {
    "file_path": "music-arrangements/xxx.mp3",
    "file_name": "audio.mp3",
    "file_size": 3664989,
    "mime_type": "audio/mpeg",
    "file_url": "..."
  }
}
```

**Response untuk Status `song_approved` atau `arrangement_in_progress`:**
```json
{
  "success": true,
  "message": "File uploaded and submitted for review. Producer has been notified.",
  "data": {
    "id": 37,
    "status": "arrangement_submitted",
    "file_path": "music-arrangements/xxx.mp3",
    ...
  },
  "file_uploaded": true
}
```

---

## 📋 Workflow Lengkap

### **Skenario 1: Update Song Proposal (Tanpa File)**

```
1. Music Arranger membuat song proposal
   POST /arrangements
   → Status: song_proposal

2. Music Arranger update song proposal
   PUT /arrangements/{id}
   Body: { "song_title": "Updated", "singer_name": "Updated" }
   → Status: song_proposal (tetap)

3. Music Arranger submit ke Producer
   POST /arrangements/{id}/submit-song-proposal
   → Status: song_proposal (submitted_at di-set)
   → Notifikasi ke Producer
```

### **Skenario 2: Upload File Arrangement (Sebelum Producer Approve)**

```
1. Music Arranger membuat song proposal
   POST /arrangements
   → Status: song_proposal

2. Music Arranger upload file arrangement
   POST /arrangements/{id}/upload-file
   Body: file=<audio.mp3>
   → Status: song_proposal (tetap)
   → File tersimpan, tapi belum di-submit ke Producer

3. Producer approve song proposal
   POST /producer/approvals/{id}/approve
   → Status: song_approved
   → Notifikasi ke Music Arranger

4. Music Arranger bisa langsung submit arrangement
   (File sudah ada, tinggal submit)
   POST /arrangements/{id}/submit
   → Status: arrangement_submitted
```

### **Skenario 3: Upload File Arrangement (Setelah Producer Approve)**

```
1. Music Arranger membuat song proposal
   POST /arrangements
   → Status: song_proposal

2. Producer approve song proposal
   POST /producer/approvals/{id}/approve
   → Status: song_approved

3. Music Arranger upload file arrangement
   POST /arrangements/{id}/upload-file
   Body: file=<audio.mp3>
   → Status: arrangement_submitted (auto-submit)
   → Notifikasi ke Producer
```

---

## 🔍 Catatan Penting

### **Update Song Proposal:**
- ✅ Bisa update `song_title`, `singer_name`, `arrangement_notes`
- ✅ Bisa update sebelum di-submit ke Producer
- ✅ Bisa update setelah di-submit tapi belum di-review Producer
- ✅ Status tetap `song_proposal` setelah update

### **Upload File Arrangement:**
- ✅ Bisa upload file untuk status `song_proposal` (untuk preview)
- ✅ File tersimpan, tapi status tetap `song_proposal`
- ✅ Setelah Producer approve, file sudah tersedia
- ✅ Jika upload file saat status `song_approved`, akan auto-submit ke Producer

### **File URL:**
- ✅ File URL (signed URL) otomatis ter-include dalam response
- ✅ Bisa digunakan untuk preview audio di frontend
- ✅ URL berlaku selama 24 jam

---

## 🧪 Testing

### **Test 1: Update Song Proposal**

```bash
# 1. Create song proposal
POST /api/live-tv/roles/music-arranger/arrangements
Body: {
  "episode_id": 2011,
  "song_title": "Original Title",
  "singer_name": "Original Singer"
}

# 2. Update song proposal
PUT /api/live-tv/roles/music-arranger/arrangements/{id}
Body: {
  "song_title": "Updated Title",
  "singer_name": "Updated Singer"
}

# Expected: Status tetap song_proposal, data ter-update
```

### **Test 2: Upload File untuk Song Proposal**

```bash
# 1. Create song proposal
POST /api/live-tv/roles/music-arranger/arrangements
Body: {
  "episode_id": 2011,
  "song_title": "Song Title"
}

# 2. Upload file arrangement
POST /api/live-tv/roles/music-arranger/arrangements/{id}/upload-file
Body: file=<audio.mp3>

# Expected: 
# - Status tetap song_proposal
# - File tersimpan
# - file_url tersedia dalam response
# - Message: "Arrangement file uploaded successfully. File will be available after Producer approves the song proposal."
```

### **Test 3: Upload File Setelah Producer Approve**

```bash
# 1. Producer approve song proposal
POST /api/live-tv/producer/approvals/{id}/approve
Body: { "type": "song_proposal" }

# 2. Music Arranger upload file
POST /api/live-tv/roles/music-arranger/arrangements/{id}/upload-file
Body: file=<audio.mp3>

# Expected:
# - Status berubah menjadi arrangement_submitted
# - Notifikasi ke Producer
# - Message: "File uploaded and submitted for review. Producer has been notified."
```

---

## 📝 Contoh Kode Frontend

### **Update Song Proposal**

```javascript
const updateSongProposal = async (arrangementId, data) => {
  const response = await api.put(
    `/live-tv/roles/music-arranger/arrangements/${arrangementId}`,
    {
      song_title: data.songTitle,
      singer_name: data.singerName,
      arrangement_notes: data.notes
    }
  );
  
  return response.data;
};
```

### **Upload Arrangement File**

```javascript
const uploadArrangementFile = async (arrangementId, file) => {
  const formData = new FormData();
  formData.append('file', file);
  
  const response = await api.post(
    `/live-tv/roles/music-arranger/arrangements/${arrangementId}/upload-file`,
    formData,
    {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    }
  );
  
  return response.data;
};
```

---

## ✅ Summary

1. ✅ **Music Arranger bisa update song proposal** sebelum di-submit atau sebelum Producer review
2. ✅ **Music Arranger bisa upload file arrangement** setelah song proposal dibuat (bahkan sebelum Producer approve)
3. ✅ **File tersimpan** dan tersedia untuk preview, tapi status tetap `song_proposal` sampai Producer approve
4. ✅ **Setelah Producer approve**, file sudah tersedia dan Music Arranger bisa langsung submit arrangement
5. ✅ **Jika upload file saat status `song_approved`**, akan auto-submit ke Producer




