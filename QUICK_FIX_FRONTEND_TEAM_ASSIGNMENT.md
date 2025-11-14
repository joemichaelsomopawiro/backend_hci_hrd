# 🚀 Quick Fix: Team Assignment Error

## ❌ Error Yang Terjadi
```
POST http://127.0.0.1:8000/api/programs/6/assign-teams 500 (Internal Server Error)
Error: Call to undefined method Illuminate\Database\Eloquent\Relations\HasMany::sync()
```

## ✅ Sudah Diperbaiki di Backend

1. ✅ Database unique constraint dihapus - sekarang **1 team bisa di banyak program**
2. ✅ Model relationships diupdate ke `BelongsToMany`
3. ✅ Controller diupdate untuk accept `teamId` (single) atau `team_ids` (array)
4. ✅ Migration sudah dijalankan

## 🔧 Yang Perlu Diupdate di Frontend

### File: `programApiService.js` (sekitar line 849)

#### ❌ BEFORE (yang error):
```javascript
assignTeamToProgram(programId, teamId) {
  return axios.post(`${API_BASE_URL}/programs/${programId}/assign-teams`, {
    programId,  // ❌ REMOVE INI
    teamId
  });
}
```

#### ✅ AFTER (yang benar):
```javascript
assignTeamToProgram(programId, teamId) {
  return axios.post(`${API_BASE_URL}/programs/${programId}/assign-teams`, {
    teamId  // ✅ HANYA INI
  });
}
```

**That's it!** Hanya hapus `programId` dari request body karena sudah ada di URL.

## 📝 Penjelasan

### Request Body yang Diterima Backend:

**Format 1 - Single Team (RECOMMENDED)**:
```json
{
  "teamId": 2
}
```

**Format 2 - Multiple Teams**:
```json
{
  "team_ids": [1, 2, 3]
}
```

**Optional - dengan mode**:
```json
{
  "teamId": 2,
  "mode": "attach"  // attach (default), sync, detach
}
```

### Mode Options:
- `attach` (default): Tambah team baru, tidak hapus yang lama, cegah duplikat
- `sync`: Replace semua teams
- `detach`: Remove team

## 🧪 Testing

### Test di Browser Console:
```javascript
// Pastikan sudah login dan ada token
const programId = 6;
const teamId = 2;

axios.post(`http://127.0.0.1:8000/api/programs/${programId}/assign-teams`, {
  teamId: teamId
}, {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
    'Content-Type': 'application/json'
  }
})
.then(res => console.log('✅ Success:', res.data))
.catch(err => console.error('❌ Error:', err.response?.data));
```

### Expected Response:
```json
{
  "success": true,
  "data": {
    "id": 6,
    "name": "Program Name",
    "teams": [
      {
        "id": 2,
        "name": "Team Creative",
        "role": "kreatif",
        "team_lead": { ... },
        "members": [ ... ]
      }
    ]
  },
  "message": "Teams assigned successfully"
}
```

## ✨ Bonus Features (Sekarang Bisa!)

### 1. Unassign Team
```javascript
// Di programApiService.js, tambahkan:
removeTeamFromProgram(programId, teamId) {
  return axios.post(`${API_BASE_URL}/programs/${programId}/assign-teams`, {
    teamId,
    mode: 'detach'
  });
}
```

### 2. Assign Multiple Teams Sekaligus
```javascript
assignMultipleTeams(programId, teamIds) {
  return axios.post(`${API_BASE_URL}/programs/${programId}/assign-teams`, {
    team_ids: teamIds,  // [1, 2, 3]
    mode: 'attach'
  });
}
```

## 🎯 Checklist

- [ ] Buka file `programApiService.js`
- [ ] Cari method `assignTeamToProgram` (sekitar line 849)
- [ ] Hapus `programId` dari request body
- [ ] Save file
- [ ] Refresh browser
- [ ] Test assign team - should work! ✅

## 📚 Full Documentation

Lihat file `PROGRAM_TEAM_ASSIGNMENT_UPDATE.md` untuk dokumentasi lengkap.

---

**Updated**: October 22, 2025  
**Status**: ✅ Backend Ready, Frontend perlu 1 line fix

