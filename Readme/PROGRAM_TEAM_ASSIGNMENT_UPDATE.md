# Program Team Assignment - Update Documentation

## 📋 Summary

Backend telah diupdate untuk **mendukung satu team dapat di-assign ke banyak program**. Sebelumnya ada constraint unique yang mencegah hal ini.

## 🔧 Perubahan Backend

### 1. Database Migration
- **File**: `database/migrations/2025_10_22_084128_remove_unique_constraint_from_program_team_table.php`
- **Perubahan**: 
  - Menghapus unique constraint `(program_id, team_id)` dari tabel `program_team`
  - Sekarang satu team bisa di-assign ke multiple programs
  - Foreign keys tetap ada untuk data integrity

### 2. Model Updates

#### Program Model (`app/Models/Program.php`)
```php
// BEFORE (HasMany - SALAH)
public function teams(): HasMany
{
    return $this->hasMany(Team::class);
}

// AFTER (BelongsToMany - BENAR)
public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, 'program_team')
        ->withTimestamps();
}
```

#### Team Model (`app/Models/Team.php`)
```php
// DITAMBAHKAN - Relasi many-to-many
public function programs(): BelongsToMany
{
    return $this->belongsToMany(Program::class, 'program_team')
        ->withTimestamps();
}

// TETAP ADA - Untuk backward compatibility
public function program(): BelongsTo
{
    return $this->belongsTo(Program::class);
}
```

### 3. Controller Update

#### ProgramController (`app/Http/Controllers/ProgramController.php`)

Method `assignTeams()` sekarang mendukung:
- ✅ Single team assignment
- ✅ Multiple teams assignment
- ✅ Multiple modes: attach, sync, detach

## 🚀 API Usage untuk Frontend

### Endpoint
```
POST /api/programs/{programId}/assign-teams
```

### Format Request yang Didukung

#### 1. Assign Single Team (RECOMMENDED untuk kasus Anda)
```javascript
// Format 1: camelCase (yang frontend Anda gunakan)
{
  "teamId": 2,
  "mode": "attach"  // optional, default: "attach"
}

// Format 2: snake_case
{
  "team_id": 2,
  "mode": "attach"  // optional
}
```

#### 2. Assign Multiple Teams
```javascript
{
  "team_ids": [1, 2, 3],
  "mode": "attach"  // optional
}
```

### Modes

| Mode | Behavior | Use Case |
|------|----------|----------|
| `attach` (default) | Menambahkan team baru tanpa menghapus yang lama. Cegah duplikat otomatis. | ✅ Assign team ke program baru |
| `sync` | Replace semua teams dengan yang baru | Update complete team list |
| `detach` | Remove team dari program | Unassign team |

### Response Format

#### Success Response (200)
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
        "description": "...",
        "role": "kreatif",
        "is_active": true,
        "team_lead": {
          "id": 10,
          "name": "John Doe",
          "email": "john@example.com"
        },
        "members": [
          // ... team members
        ],
        "pivot": {
          "program_id": 6,
          "team_id": 2,
          "created_at": "2025-10-22T08:45:00.000000Z",
          "updated_at": "2025-10-22T08:45:00.000000Z"
        }
      }
    ]
  },
  "message": "Teams assigned successfully"
}
```

#### Error Response (422 - Validation)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "team_ids": [
      "The team_ids field is required."
    ]
  }
}
```

#### Error Response (404 - Not Found)
```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\Program] 999"
}
```

#### Error Response (500 - Server Error)
```json
{
  "success": false,
  "message": "Error assigning teams: [error detail]"
}
```

## 📝 Update yang Diperlukan di Frontend

### 1. Update API Service (`programApiService.js`)

#### Current Code (yang error)
```javascript
assignTeamToProgram(programId, teamId) {
  console.log('Assigning team to program:', { programId, teamId });
  return axios.post(`${API_BASE_URL}/programs/${programId}/assign-teams`, {
    programId,  // ❌ TIDAK PERLU - sudah ada di URL
    teamId
  })
  .then(response => {
    console.log('Team assigned successfully:', response.data);
    return response.data;
  })
  .catch(error => {
    console.error('Error assigning team to program:', error);
    this.handleError(error, `/api/programs/${programId}/assign-teams`);
    throw error;
  });
}
```

#### Updated Code (RECOMMENDED)
```javascript
/**
 * Assign team to program
 * @param {number} programId - Program ID
 * @param {number|number[]} teamId - Single team ID or array of team IDs
 * @param {string} mode - 'attach' (default), 'sync', or 'detach'
 * @returns {Promise}
 */
assignTeamToProgram(programId, teamId, mode = 'attach') {
  console.log('Assigning team to program:', { programId, teamId, mode });
  
  // Prepare request body
  const requestBody = Array.isArray(teamId)
    ? { team_ids: teamId, mode }
    : { teamId, mode };
  
  return axios.post(`${API_BASE_URL}/programs/${programId}/assign-teams`, requestBody)
    .then(response => {
      console.log('✅ Team assigned successfully:', response.data);
      return response.data;
    })
    .catch(error => {
      console.error('❌ Error assigning team to program:', error);
      this.handleError(error, `/api/programs/${programId}/assign-teams`);
      throw error;
    });
}

/**
 * Remove team from program
 * @param {number} programId 
 * @param {number|number[]} teamId 
 * @returns {Promise}
 */
removeTeamFromProgram(programId, teamId) {
  return this.assignTeamToProgram(programId, teamId, 'detach');
}

/**
 * Sync teams to program (replace all teams)
 * @param {number} programId 
 * @param {number[]} teamIds 
 * @returns {Promise}
 */
syncTeamsToProgram(programId, teamIds) {
  return this.assignTeamToProgram(programId, teamIds, 'sync');
}
```

### 2. Update Component (`ProgramManagementApp.vue` atau `ManagerDashboard.vue`)

#### Current Usage
```javascript
async handleAssignTeam(programId, teamId) {
  try {
    await programApiService.assignTeamToProgram(programId, teamId);
    // refresh data
  } catch (error) {
    console.error('Error assigning team:', error);
  }
}
```

#### Updated Usage (tetap sama, tapi sekarang bekerja!)
```javascript
async handleAssignTeam(programId, teamId) {
  try {
    // Mode attach (default) - akan menambahkan team tanpa menghapus yang lama
    const result = await programApiService.assignTeamToProgram(programId, teamId);
    
    console.log('✅ Team assigned:', result);
    
    // Update UI - team sekarang ada di result.data.teams
    this.refreshProgramData(programId);
    
  } catch (error) {
    console.error('❌ Error assigning team:', error);
    // Show error to user
    this.showErrorNotification('Failed to assign team');
  }
}

// OPTIONAL: Unassign team
async handleUnassignTeam(programId, teamId) {
  try {
    await programApiService.removeTeamFromProgram(programId, teamId);
    console.log('✅ Team removed');
    this.refreshProgramData(programId);
  } catch (error) {
    console.error('❌ Error removing team:', error);
  }
}

// OPTIONAL: Replace all teams
async handleSyncTeams(programId, teamIds) {
  try {
    await programApiService.syncTeamsToProgram(programId, teamIds);
    console.log('✅ Teams synced');
    this.refreshProgramData(programId);
  } catch (error) {
    console.error('❌ Error syncing teams:', error);
  }
}
```

### 3. Fallback Routes Check

Error log Anda menunjukkan ada 2 endpoint yang dipanggil:
1. ✅ `POST /api/programs/6/assign-teams` (200) - **INI YANG BENAR**
2. ❌ `POST /api/program-regular/6/assign-teams` (404) - Tidak ada route ini

**Action**: Pastikan frontend hanya memanggil `/api/programs/{id}/assign-teams`

Cek file frontend, mungkin ada fallback logic:
```javascript
// ❌ REMOVE atau FIX ini jika ada
try {
  await axios.post(`/api/programs/${programId}/assign-teams`, data);
} catch (error) {
  // Fallback to program-regular? JANGAN!
  await axios.post(`/api/program-regular/${programId}/assign-teams`, data);
}
```

## 🧪 Testing

### Test via Postman/cURL

```bash
# Test 1: Assign single team
curl -X POST http://127.0.0.1:8000/api/programs/6/assign-teams \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "teamId": 2,
    "mode": "attach"
  }'

# Test 2: Assign multiple teams
curl -X POST http://127.0.0.1:8000/api/programs/6/assign-teams \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "team_ids": [1, 2, 3],
    "mode": "attach"
  }'

# Test 3: Remove team
curl -X POST http://127.0.0.1:8000/api/programs/6/assign-teams \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "teamId": 2,
    "mode": "detach"
  }'
```

### Test via Browser Console

```javascript
// Assuming you're logged in and axios is available
const programId = 6;
const teamId = 2;

axios.post(`http://127.0.0.1:8000/api/programs/${programId}/assign-teams`, {
  teamId: teamId,
  mode: 'attach'
}, {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
    'Content-Type': 'application/json'
  }
})
.then(response => console.log('✅ Success:', response.data))
.catch(error => console.error('❌ Error:', error.response?.data || error));
```

## 📊 Data Flow

```
Frontend Component
    ↓
    │ assignTeamToProgram(programId: 6, teamId: 2)
    ↓
API Service (programApiService.js)
    ↓
    │ POST /api/programs/6/assign-teams
    │ Body: { teamId: 2, mode: 'attach' }
    ↓
Backend Route (routes/api.php)
    ↓
    │ Route::post('/programs/{program}/assign-teams', [ProgramController::class, 'assignTeams'])
    ↓
ProgramController::assignTeams()
    ↓
    │ 1. Validate request (teamId/team_ids exists)
    │ 2. Convert teamId → team_ids array
    │ 3. Check mode (attach/sync/detach)
    │ 4. Execute: $program->teams()->attach($teamIds)
    │ 5. Load teams with relationships
    ↓
Response
    ↓
    │ {
    │   success: true,
    │   data: { program with teams },
    │   message: "Teams assigned successfully"
    │ }
    ↓
Frontend Update
```

## ⚠️ Breaking Changes

**TIDAK ADA** - Ini adalah backward compatible update!

- ✅ Old code yang mengirim `team_ids` array tetap bekerja
- ✅ New code yang mengirim `teamId` single value juga bekerja
- ✅ Default mode adalah `attach` jadi behavior tetap sama (menambahkan, tidak replace)

## 🎯 Checklist Frontend Update

- [ ] Update `programApiService.js`:
  - [ ] Hapus `programId` dari request body (sudah ada di URL)
  - [ ] Pastikan mengirim `teamId` atau `team_ids`
  - [ ] (Optional) Tambah parameter `mode`
  
- [ ] Update error handling:
  - [ ] Handle 422 (validation error)
  - [ ] Handle 404 (program not found)
  - [ ] Handle 500 (server error)
  
- [ ] Remove fallback route:
  - [ ] Hapus call ke `/api/program-regular/.../assign-teams`
  - [ ] Hanya gunakan `/api/programs/.../assign-teams`
  
- [ ] Test scenarios:
  - [ ] Assign team to program (first time)
  - [ ] Assign same team to another program
  - [ ] Assign same team to same program (should not duplicate)
  - [ ] Unassign team from program
  
- [ ] UI Updates:
  - [ ] Show success message after assign
  - [ ] Refresh program data to show updated teams
  - [ ] (Optional) Show team can be in multiple programs

## 🔍 Debugging Tips

### Error: "Call to undefined method HasMany::sync()"
**Cause**: Model masih menggunakan `HasMany` instead of `BelongsToMany`  
**Fix**: ✅ SUDAH DIPERBAIKI di backend

### Error: "SQLSTATE[23000]: Duplicate entry"
**Cause**: Unique constraint masih ada  
**Fix**: ✅ SUDAH DIPERBAIKI - migration sudah dijalankan

### Error: 500 Internal Server Error
**Check**:
1. Backend logs: `storage/logs/laravel.log`
2. Browser console untuk detail error message
3. Network tab untuk response body

### Error: 404 Not Found
**Check**: 
- URL benar? `/api/programs/6/assign-teams` (bukan `/api/program-regular/...`)
- Route sudah didefinisikan di `routes/api.php`

## 📚 Additional Resources

- Laravel Many-to-Many Relationships: https://laravel.com/docs/eloquent-relationships#many-to-many
- Pivot Tables: https://laravel.com/docs/eloquent-relationships#updating-many-to-many-relationships

## 💡 Quick Fix untuk Frontend

Jika Anda ingin **minimal change**, update hanya ini di `programApiService.js`:

```javascript
assignTeamToProgram(programId, teamId) {
  console.log('Assigning team to program:', { programId, teamId });
  return axios.post(`${API_BASE_URL}/programs/${programId}/assign-teams`, {
    teamId  // ← HAPUS programId, HANYA teamId
  })
  .then(response => {
    console.log('Team assigned successfully:', response.data);
    return response.data;
  })
  .catch(error => {
    console.error('Error assigning team to program:', error);
    this.handleError(error, `/api/programs/${programId}/assign-teams`);
    throw error;
  });
}
```

**DONE!** ✅ Ini sudah cukup untuk membuat assign team bekerja!

---

## 📝 Summary for Frontend Developer

### What Changed?
- Backend sekarang **support one team → many programs**
- Unique constraint dihapus dari database
- Model relationships diupdate ke BelongsToMany
- Controller sekarang accept `teamId` (single) atau `team_ids` (array)

### What You Need to Do?
**MINIMAL CHANGE**:
1. Di `programApiService.js` line ~849, **hapus `programId` dari request body**
2. Pastikan hanya ada `teamId` di request body
3. Test assign team - should work! ✅

**OPTIONAL ENHANCEMENTS**:
- Add mode parameter (attach/sync/detach)
- Add unassign team feature
- Show team can be in multiple programs in UI

### Expected Behavior After Fix:
- ✅ Assign team 2 to program 6 → SUCCESS
- ✅ Assign team 2 to program 7 → SUCCESS (team bisa di multiple programs)
- ✅ Assign team 2 to program 6 again → SUCCESS (no duplicate, existing relationship maintained)
- ✅ No more 500 errors!

---

**Last Updated**: October 22, 2025  
**Backend Version**: Ready for production  
**Migration Status**: ✅ Applied  
**Breaking Changes**: None

