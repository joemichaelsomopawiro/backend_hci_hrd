# 📋 FLOW LENGKAP: CREATE TEAM & ASSIGN KE EPISODE

Dokumentasi lengkap tentang cara Manager Program membuat Production Team dan assign ke episode.

---

## 🎯 FLOW LENGKAP (URUTAN YANG BENAR)

### **STEP 1: Manager Program Create Team (KOSONG DULU)**

Manager Program membuat Production Team **tanpa members** terlebih dahulu.

```
Manager Program
    ↓
1. Create Production Team (kosong)
    - Name: "Tim Produksi Musik A"
    - Description: "Tim untuk program musik"
    - Producer ID: 5
    ↓
2. Team berhasil dibuat (ID: 1)
    - Team masih kosong, belum ada members
```

**API Call:**
```http
POST /api/live-tv/production-teams
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Tim Produksi Musik A",
  "description": "Tim untuk program musik",
  "producer_id": 5
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Tim Produksi Musik A",
    "description": "Tim untuk program musik",
    "producer_id": 5,
    "is_active": true,
    "members": []  // ← Masih kosong
  }
}
```

---

### **STEP 2: Manager Program Tambahkan Members ke Team**

Setelah team dibuat, Manager Program menambahkan members **satu per satu** atau **batch**.

```
Manager Program
    ↓
1. Tambah Member 1 (Creative)
    - User ID: 10
    - Role: "kreatif"
    ↓
2. Tambah Member 2 (Music Arranger)
    - User ID: 11
    - Role: "musik_arr"
    ↓
3. Tambah Member 3 (Sound Engineer)
    - User ID: 12
    - Role: "sound_eng"
    ↓
4. Tambah Member 4 (Produksi)
    - User ID: 13
    - Role: "produksi"
    ↓
5. Tambah Member 5 (Editor 1)
    - User ID: 14
    - Role: "editor"
    ↓
6. Tambah Member 6 (Editor 2) ← Bisa banyak dengan role sama
    - User ID: 15
    - Role: "editor"
    ↓
7. Tambah Member 7 (Art & Set Design)
    - User ID: 16
    - Role: "art_set_design"
    ↓
8. Team lengkap (minimal 1 per role)
```

**API Call (Tambah 1 Member):**
```http
POST /api/live-tv/production-teams/1/members
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 10,
  "role": "kreatif",
  "notes": "Creative utama"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 10,
    "user": {
      "id": 10,
      "name": "Creative 1",
      "email": "creative1@example.com"
    },
    "role": "kreatif",
    "is_active": true
  },
  "message": "Member added to team successfully"
}
```

**Cek Status Team:**
```http
GET /api/live-tv/production-teams/1/statistics
```

**Response:**
```json
{
  "success": true,
  "data": {
    "team_id": 1,
    "team_name": "Tim Produksi Musik A",
    "total_members": 7,
    "has_all_roles": true,  // ← Semua role sudah ada
    "is_ready_for_production": true,  // ← Team siap digunakan
    "role_distribution": {
      "kreatif": 1,
      "musik_arr": 1,
      "sound_eng": 1,
      "produksi": 1,
      "editor": 2,  // ← Bisa banyak
      "art_set_design": 1
    },
    "missing_roles": []  // ← Tidak ada role yang kurang
  }
}
```

---

### **STEP 3: Manager Program Assign Team ke Episode**

Setelah team lengkap dan ready, Manager Program assign team ke episode.

```
Manager Program
    ↓
1. Pilih Episode (misalnya Episode 1)
    ↓
2. Assign Team ke Episode
    - Team ID: 1
    - Episode ID: 1
    - Notes: "Assign team untuk episode ini"
    ↓
3. Semua members team mendapat notifikasi
    - Creative mendapat notifikasi
    - Music Arranger mendapat notifikasi
    - Sound Engineer mendapat notifikasi
    - Produksi mendapat notifikasi
    - Editor 1 & 2 mendapat notifikasi
    - Art & Set Design mendapat notifikasi
```

**API Call:**
```http
POST /api/live-tv/manager-program/episodes/1/assign-team
Authorization: Bearer {token}
Content-Type: application/json

{
  "production_team_id": 1,
  "notes": "Assign team untuk episode ini"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "episode_id": 1,
    "production_team_id": 1,
    "team_assigned_at": "2025-12-12T10:00:00.000000Z",
    "team_assigned_by": 1,
    "team_assignment_notes": "Assign team untuk episode ini"
  },
  "message": "Team assigned successfully"
}
```

---

## 📊 DIAGRAM FLOW

```
┌─────────────────────────────────────────────────────────────┐
│                    MANAGER PROGRAM                          │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  STEP 1: CREATE TEAM (KOSONG)         │
        │  POST /production-teams                │
        │  { name, description, producer_id }    │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  Team Created (ID: 1)                 │
        │  Members: [] ← Masih kosong            │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  STEP 2: TAMBAH MEMBERS               │
        │  POST /production-teams/1/members      │
        │  { user_id, role, notes }              │
        │                                        │
        │  • Tambah Creative                     │
        │  • Tambah Music Arranger               │
        │  • Tambah Sound Engineer               │
        │  • Tambah Produksi                     │
        │  • Tambah Editor 1                     │
        │  • Tambah Editor 2 (bisa banyak)       │
        │  • Tambah Art & Set Design             │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  Cek Status Team                      │
        │  GET /production-teams/1/statistics    │
        │                                        │
        │  ✓ has_all_roles: true                │
        │  ✓ is_ready_for_production: true      │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  STEP 3: ASSIGN TEAM KE EPISODE       │
        │  POST /episodes/1/assign-team          │
        │  { production_team_id: 1 }             │
        └───────────────────────────────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  Team Assigned Successfully           │
        │  ✓ Episode 1 punya team                │
        │  ✓ Semua members mendapat notifikasi  │
        └───────────────────────────────────────┘
```

---

## 💻 CONTOH IMPLEMENTASI FRONTEND

### **Flow di Frontend:**

```vue
<template>
  <div class="team-management">
    <!-- STEP 1: Create Team -->
    <div v-if="step === 1" class="step-create-team">
      <h2>Buat Production Team</h2>
      <form @submit.prevent="createTeam">
        <input v-model="teamForm.name" placeholder="Nama Team" required />
        <textarea v-model="teamForm.description" placeholder="Deskripsi" />
        <select v-model="teamForm.producer_id" required>
          <option value="">Pilih Producer</option>
          <option v-for="p in producers" :key="p.id" :value="p.id">
            {{ p.name }}
          </option>
        </select>
        <button type="submit">Buat Team</button>
      </form>
    </div>

    <!-- STEP 2: Add Members -->
    <div v-if="step === 2" class="step-add-members">
      <h2>Tambah Members ke Team: {{ currentTeam.name }}</h2>
      
      <!-- Team Status -->
      <div class="team-status">
        <p>Status: {{ teamStats.is_ready_for_production ? '✅ Ready' : '❌ Belum Ready' }}</p>
        <p>Missing Roles: {{ teamStats.missing_roles.join(', ') || 'Tidak ada' }}</p>
      </div>

      <!-- Form Tambah Member -->
      <form @submit.prevent="addMember">
        <select v-model="memberForm.role" @change="loadUsersForRole" required>
          <option value="">Pilih Role</option>
          <option value="kreatif">Creative</option>
          <option value="musik_arr">Music Arranger</option>
          <option value="sound_eng">Sound Engineer</option>
          <option value="produksi">Produksi</option>
          <option value="editor">Editor</option>
          <option value="art_set_design">Art & Set Design</option>
        </select>
        
        <select v-model="memberForm.user_id" required>
          <option value="">Pilih User</option>
          <option v-for="u in availableUsers" :key="u.id" :value="u.id">
            {{ u.name }} ({{ u.email }})
          </option>
        </select>
        
        <button type="submit">Tambah Member</button>
      </form>

      <!-- List Members -->
      <div class="members-list">
        <h3>Members Saat Ini:</h3>
        <ul>
          <li v-for="m in currentMembers" :key="m.id">
            {{ m.user?.name }} - {{ m.role_label }}
          </li>
        </ul>
      </div>

      <!-- Button Next (jika team ready) -->
      <button 
        v-if="teamStats.is_ready_for_production" 
        @click="step = 3"
      >
        Lanjut ke Assign Episode
      </button>
    </div>

    <!-- STEP 3: Assign to Episode -->
    <div v-if="step === 3" class="step-assign-episode">
      <h2>Assign Team ke Episode</h2>
      
      <select v-model="selectedEpisodeId" required>
        <option value="">Pilih Episode</option>
        <option v-for="ep in episodes" :key="ep.id" :value="ep.id">
          Episode {{ ep.episode_number }} - {{ ep.title }}
        </option>
      </select>
      
      <button @click="assignTeamToEpisode">Assign Team</button>
    </div>
  </div>
</template>

<script>
import { productionTeamService } from '@/services/productionTeamService';

export default {
  data() {
    return {
      step: 1, // 1: Create Team, 2: Add Members, 3: Assign Episode
      teamForm: {
        name: '',
        description: '',
        producer_id: '',
      },
      currentTeam: null,
      currentMembers: [],
      teamStats: {},
      memberForm: {
        role: '',
        user_id: '',
        notes: '',
      },
      availableUsers: [],
      selectedEpisodeId: '',
      episodes: [],
      producers: [],
    };
  },
  methods: {
    // STEP 1: Create Team
    async createTeam() {
      try {
        const response = await productionTeamService.createTeam({
          name: this.teamForm.name,
          description: this.teamForm.description,
          producer_id: parseInt(this.teamForm.producer_id),
        });

        if (response.success) {
          this.currentTeam = response.data;
          this.$toast.success('Team berhasil dibuat!');
          this.step = 2; // Lanjut ke step 2
          await this.loadTeamData();
        }
      } catch (error) {
        this.$toast.error('Gagal membuat team');
        console.error(error);
      }
    },

    // STEP 2: Load Team Data
    async loadTeamData() {
      if (!this.currentTeam?.id) return;

      try {
        // Load team details
        const teamResponse = await productionTeamService.getTeam(this.currentTeam.id);
        if (teamResponse.success) {
          this.currentTeam = teamResponse.data;
          this.currentMembers = teamResponse.data.members || [];
        }

        // Load statistics
        const statsResponse = await productionTeamService.getTeamStatistics(
          this.currentTeam.id
        );
        if (statsResponse.success) {
          this.teamStats = statsResponse.data;
        }
      } catch (error) {
        console.error('Error loading team data:', error);
      }
    },

    // STEP 2: Load Users for Role
    async loadUsersForRole() {
      if (!this.memberForm.role) return;

      try {
        const response = await productionTeamService.getAvailableUsersForRole(
          this.memberForm.role
        );
        if (response.success) {
          this.availableUsers = response.data;
        }
      } catch (error) {
        console.error('Error loading users:', error);
      }
    },

    // STEP 2: Add Member
    async addMember() {
      try {
        const response = await productionTeamService.addMember(
          this.currentTeam.id,
          {
            user_id: parseInt(this.memberForm.user_id),
            role: this.memberForm.role,
            notes: this.memberForm.notes,
          }
        );

        if (response.success) {
          this.$toast.success('Member berhasil ditambahkan!');
          this.memberForm = { role: '', user_id: '', notes: '' };
          this.availableUsers = [];
          await this.loadTeamData(); // Reload untuk update stats
        }
      } catch (error) {
        this.$toast.error('Gagal menambahkan member');
        console.error(error);
      }
    },

    // STEP 3: Assign Team to Episode
    async assignTeamToEpisode() {
      if (!this.selectedEpisodeId) {
        this.$toast.error('Pilih episode terlebih dahulu');
        return;
      }

      try {
        const response = await productionTeamService.assignTeamToEpisode(
          parseInt(this.selectedEpisodeId),
          this.currentTeam.id,
          'Assign team untuk episode ini'
        );

        if (response.success) {
          this.$toast.success('Team berhasil di-assign ke episode!');
          // Reset atau redirect
          this.$router.push('/episodes');
        }
      } catch (error) {
        this.$toast.error('Gagal assign team');
        console.error(error);
      }
    },
  },
};
</script>
```

---

## ✅ CHECKLIST FLOW

### **STEP 1: Create Team**
- [ ] Manager Program login
- [ ] Buka form Create Team
- [ ] Isi nama team (unik, tidak kosong)
- [ ] Pilih Producer
- [ ] Submit → Team berhasil dibuat
- [ ] Team ID didapat (misalnya: 1)

### **STEP 2: Add Members**
- [ ] Buka form Add Members untuk team yang baru dibuat
- [ ] Pilih role (kreatif, musik_arr, sound_eng, produksi, editor, art_set_design)
- [ ] Pilih user untuk role tersebut
- [ ] Submit → Member berhasil ditambahkan
- [ ] Ulangi sampai semua role terisi (minimal 1 per role)
- [ ] **Bisa tambah multiple members dengan role sama** (misalnya 5 editor)
- [ ] Cek status team → `is_ready_for_production: true`

### **STEP 3: Assign to Episode**
- [ ] Pilih episode yang akan di-assign team
- [ ] Pilih team yang sudah ready
- [ ] Submit → Team berhasil di-assign
- [ ] Semua members mendapat notifikasi

---

## 🎯 POIN PENTING

1. **Create Team DULU (kosong)**
   - Team dibuat tanpa members
   - Hanya berisi: name, description, producer_id

2. **Tambah Members SETELAH team dibuat**
   - Members ditambahkan satu per satu
   - Bisa banyak members dengan role sama
   - Minimal 1 member per role (6 roles wajib)

3. **Assign Team SETELAH team ready**
   - Team harus `is_ready_for_production: true`
   - Semua role harus terisi minimal 1 orang

4. **Multiple Members dengan Role Sama**
   - ✅ Boleh ada 5 editor dalam 1 team
   - ✅ Semua akan mendapat notifikasi saat team di-assign
   - ✅ Producer/Manager bisa pilih editor mana yang akan mengerjakan episode tertentu

---

## 📝 CONTOH LENGKAP

### **Scenario: Team dengan 5 Editor**

**STEP 1: Create Team**
```json
POST /api/live-tv/production-teams
{
  "name": "Tim Produksi Musik A",
  "producer_id": 5
}
→ Team ID: 1
```

**STEP 2: Add Members**
```json
// Tambah Creative
POST /api/live-tv/production-teams/1/members
{"user_id": 10, "role": "kreatif"}

// Tambah Music Arranger
POST /api/live-tv/production-teams/1/members
{"user_id": 11, "role": "musik_arr"}

// Tambah Sound Engineer
POST /api/live-tv/production-teams/1/members
{"user_id": 12, "role": "sound_eng"}

// Tambah Produksi
POST /api/live-tv/production-teams/1/members
{"user_id": 13, "role": "produksi"}

// Tambah Editor 1
POST /api/live-tv/production-teams/1/members
{"user_id": 14, "role": "editor"}

// Tambah Editor 2
POST /api/live-tv/production-teams/1/members
{"user_id": 15, "role": "editor"}

// Tambah Editor 3
POST /api/live-tv/production-teams/1/members
{"user_id": 16, "role": "editor"}

// Tambah Editor 4
POST /api/live-tv/production-teams/1/members
{"user_id": 17, "role": "editor"}

// Tambah Editor 5
POST /api/live-tv/production-teams/1/members
{"user_id": 18, "role": "editor"}

// Tambah Art & Set Design
POST /api/live-tv/production-teams/1/members
{"user_id": 19, "role": "art_set_design"}
```

**Cek Status:**
```json
GET /api/live-tv/production-teams/1/statistics
→ {
  "has_all_roles": true,
  "is_ready_for_production": true,
  "role_distribution": {
    "editor": 5  // ← 5 editor
  }
}
```

**STEP 3: Assign to Episode**
```json
POST /api/live-tv/manager-program/episodes/1/assign-team
{
  "production_team_id": 1
}
→ Semua 5 editor mendapat notifikasi
```

---

**Last Updated:** 2025-12-12  
**Created By:** AI Assistant

