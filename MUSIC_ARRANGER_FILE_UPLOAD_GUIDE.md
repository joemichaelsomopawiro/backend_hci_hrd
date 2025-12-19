# Music Arranger - File Upload Guide

## ⚠️ MASALAH: PUT Method Tidak Support File Upload

**PUT method di Laravel TIDAK bisa membaca multipart/form-data dengan baik.** 
Jika frontend menggunakan PUT untuk upload file, file tidak akan terdeteksi oleh backend.

## ✅ SOLUSI: Gunakan Endpoint POST Khusus

### Endpoint untuk Upload File

```
POST /api/live-tv/roles/music-arranger/arrangements/{id}/upload-file
```

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Body (Form Data):**
```
file: <file_audio.mp3>
```

### Contoh Request (JavaScript/Axios)

```javascript
// ✅ BENAR - Gunakan POST endpoint khusus
const uploadFile = async (arrangementId, file) => {
  const formData = new FormData();
  formData.append('file', file);
  
  try {
    const response = await api.post(
      `/live-tv/roles/music-arranger/arrangements/${arrangementId}/upload-file`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      }
    );
    
    console.log('✅ File uploaded:', response.data);
    return response.data;
  } catch (error) {
    console.error('❌ Upload failed:', error);
    throw error;
  }
};
```

### ❌ JANGAN Gunakan Ini

```javascript
// ❌ SALAH - PUT tidak support multipart/form-data
const formData = new FormData();
formData.append('file', file);
await api.put(`/live-tv/roles/music-arranger/arrangements/${id}`, formData);
```

## Response Format

### Success Response

```json
{
  "success": true,
  "message": "File uploaded and submitted for review. Producer has been notified.",
  "data": {
    "id": 37,
    "episode_id": 2011,
    "song_title": "Song Title",
    "status": "arrangement_submitted",
    "file_path": "music-arrangements/xxx.mp3",
    "file_name": "audio.mp3",
    "file_size": 3664989,
    "mime_type": "audio/mpeg",
    "file_url": "http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/37/file?expires=...&signature=...",
    "episode": {...},
    "createdBy": {...}
  },
  "file_url": "http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/37/file?expires=...&signature=...",
  "file_uploaded": true,
  "file_info": {
    "file_path": "music-arrangements/xxx.mp3",
    "file_name": "audio.mp3",
    "file_size": 3664989,
    "mime_type": "audio/mpeg",
    "file_url": "http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/37/file?expires=...&signature=..."
  }
}
```

### Error Response (Validation Failed)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "file": [
      "The file field is required.",
      "The file must be a file of type: mp3, wav, midi.",
      "The file may not be greater than 102400 kilobytes."
    ]
  }
}
```

## Auto-Submit Behavior

Endpoint ini **otomatis mengubah status** setelah file di-upload:

1. **Jika status = `song_approved`**:
   - Status berubah menjadi `arrangement_submitted`
   - Notifikasi dikirim ke Producer
   - Message: "File uploaded and submitted for review. Producer has been notified."

2. **Jika status = `arrangement_in_progress`**:
   - Status berubah menjadi `arrangement_submitted`
   - Notifikasi dikirim ke Producer
   - Message: "File uploaded and submitted for review. Producer has been notified."

3. **Jika status = `arrangement_rejected`**:
   - Status berubah menjadi `arrangement_in_progress`
   - Message: "File uploaded and arrangement updated successfully"

## Cara Cek File di Frontend

Setelah upload berhasil, file akan otomatis ter-include dalam response. Untuk menampilkan file di daftar arrangements:

```javascript
// Get arrangements list
const response = await api.get('/live-tv/roles/music-arranger/arrangements');

// Setiap arrangement akan memiliki file_url
response.data.data.data.forEach(arrangement => {
  if (arrangement.file_url) {
    console.log('File URL:', arrangement.file_url);
    // Gunakan file_url untuk audio player atau download link
  }
});
```

## Debugging

Jika file tidak muncul setelah upload:

1. **Cek Response**: Pastikan `file_uploaded: true` dan `file_info` ada di response
2. **Cek Log Laravel**: Cari log dengan key `MusicArrangerController@uploadFile`
3. **Cek Storage**: Pastikan file benar-benar tersimpan di `storage/app/public/music-arrangements/`
4. **Cek Database**: Pastikan `file_path`, `file_name`, `file_size` ter-update di database

## Testing di Postman

1. **Method**: POST
2. **URL**: `http://localhost:8000/api/live-tv/roles/music-arranger/arrangements/{id}/upload-file`
3. **Headers**:
   - `Authorization: Bearer {token}`
   - `Accept: application/json`
4. **Body**: 
   - Type: `form-data`
   - Key: `file` (type: File)
   - Value: Pilih file audio (mp3, wav, atau midi)

## Catatan Penting

- ✅ **Gunakan POST** untuk upload file
- ✅ **File akan otomatis ter-include** dalam response dengan `file_url` (signed URL)
- ✅ **Status akan otomatis berubah** sesuai dengan status sebelumnya
- ✅ **Notifikasi otomatis dikirim** ke Producer jika status menjadi `arrangement_submitted`
- ❌ **JANGAN gunakan PUT** untuk upload file

