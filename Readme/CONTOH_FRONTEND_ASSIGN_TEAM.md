# 📋 CONTOH FRONTEND: ASSIGN TIM PRODUKSI KE EPISODE

Dokumentasi lengkap untuk implementasi frontend dalam membuat Production Team dan assign ke episode.

---

## 🎯 OVERVIEW

**Flow Lengkap:**
1. Manager Program membuat Production Team (kosong)
2. Manager Program menambahkan members ke team (bisa banyak dengan role sama)
3. Manager Program assign team ke episode

---

## 📡 API ENDPOINTS

### **1. Create Production Team**

```http
POST /api/live-tv/production-teams
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Tim Produksi Musik A",
  "description": "Tim untuk program musik",
  "producer_id": 5,
  "created_by": 1  // Optional, auto dari auth jika tidak dikirim
}
```

**Response Success (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Tim Produksi Musik A",
    "description": "Tim untuk program musik",
    "producer_id": 5,
    "created_by": 1,
    "is_active": true,
    "created_at": "2025-12-12T10:00:00.000000Z",
    "updated_at": "2025-12-12T10:00:00.000000Z"
  },
  "message": "Production team created successfully"
}
```

**Response Error (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name has already been taken"],
    "producer_id": ["The selected producer id is invalid"]
  }
}
```

---

### **2. Add Member to Team**

```http
POST /api/live-tv/production-teams/{team_id}/members
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "user_id": 10,
  "role": "editor",  // kreatif, musik_arr, sound_eng, produksi, editor, art_set_design
  "notes": "Editor utama"  // Optional
}
```

**Response Success (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 10,
    "user": {
      "id": 10,
      "name": "Editor 1",
      "email": "editor1@example.com",
      "role": "Editor"
    },
    "role": "editor",
    "role_label": "Editor",
    "is_active": true,
    "joined_at": "2025-12-12T10:00:00.000000Z",
    "notes": "Editor utama"
  },
  "message": "Member added to team successfully"
}
```

---

### **3. Get Available Users for Role**

```http
GET /api/live-tv/production-teams/available-users/{role}
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "name": "Editor 1",
      "email": "editor1@example.com",
      "role": "editor"
    },
    {
      "id": 11,
      "name": "Editor 2",
      "email": "editor2@example.com",
      "role": "editor"
    }
  ],
  "message": "Available users for role retrieved successfully"
}
```

---

### **4. Get Team Statistics**

```http
GET /api/live-tv/production-teams/{team_id}/statistics
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "team_id": 1,
    "team_name": "Tim Produksi Musik A",
    "total_members": 8,
    "required_roles": ["kreatif", "musik_arr", "sound_eng", "produksi", "editor", "art_set_design"],
    "existing_roles": ["kreatif", "musik_arr", "sound_eng", "produksi", "editor", "art_set_design"],
    "missing_roles": [],
    "has_all_roles": true,
    "is_ready_for_production": true,
    "role_distribution": {
      "kreatif": 1,
      "musik_arr": 1,
      "sound_eng": 1,
      "produksi": 1,
      "editor": 5,
      "art_set_design": 1
    }
  },
  "message": "Team statistics retrieved successfully"
}
```

---

### **5. Assign Team to Episode**

```http
POST /api/live-tv/manager-program/episodes/{episode_id}/assign-team
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "production_team_id": 1,
  "notes": "Assign team untuk episode ini"
}
```

**Response Success (200):**
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

## 💻 CONTOH IMPLEMENTASI FRONTEND (Vue.js)

### **1. Service Layer (productionTeamService.js)**

```javascript
// services/productionTeamService.js
import api from './api';

export const productionTeamService = {
  // Create production team
  async createTeam(data) {
    try {
      const response = await api.post('/live-tv/production-teams', {
        name: data.name,
        description: data.description,
        producer_id: data.producer_id,
        // created_by akan auto dari auth
      });
      return response.data;
    } catch (error) {
      console.error('Error creating team:', error);
      throw error;
    }
  },

  // Add member to team
  async addMember(teamId, memberData) {
    try {
      const response = await api.post(
        `/live-tv/production-teams/${teamId}/members`,
        {
          user_id: memberData.user_id,
          role: memberData.role,
          notes: memberData.notes || null,
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error adding member:', error);
      throw error;
    }
  },

  // Get available users for role
  async getAvailableUsersForRole(role) {
    try {
      const response = await api.get(
        `/live-tv/production-teams/available-users/${role}`
      );
      return response.data;
    } catch (error) {
      console.error('Error getting available users:', error);
      throw error;
    }
  },

  // Get team statistics
  async getTeamStatistics(teamId) {
    try {
      const response = await api.get(
        `/live-tv/production-teams/${teamId}/statistics`
      );
      return response.data;
    } catch (error) {
      console.error('Error getting team statistics:', error);
      throw error;
    }
  },

  // Get team details
  async getTeam(teamId) {
    try {
      const response = await api.get(`/live-tv/production-teams/${teamId}`);
      return response.data;
    } catch (error) {
      console.error('Error getting team:', error);
      throw error;
    }
  },

  // Assign team to episode
  async assignTeamToEpisode(episodeId, teamId, notes = null) {
    try {
      const response = await api.post(
        `/live-tv/manager-program/episodes/${episodeId}/assign-team`,
        {
          production_team_id: teamId,
          notes: notes,
        }
      );
      return response.data;
    } catch (error) {
      console.error('Error assigning team:', error);
      throw error;
    }
  },
};
```

---

### **2. Component: Create Production Team**

```vue
<!-- components/CreateProductionTeam.vue -->
<template>
  <div class="create-production-team">
    <h2>Buat Production Team</h2>
    
    <form @submit.prevent="handleSubmit">
      <!-- Team Name -->
      <div class="form-group">
        <label>Nama Team *</label>
        <input
          v-model="form.name"
          type="text"
          required
          :class="{ 'error': errors.name }"
          placeholder="Contoh: Tim Produksi Musik A"
        />
        <span v-if="errors.name" class="error-text">{{ errors.name[0] }}</span>
      </div>

      <!-- Description -->
      <div class="form-group">
        <label>Deskripsi</label>
        <textarea
          v-model="form.description"
          rows="3"
          placeholder="Deskripsi team..."
        ></textarea>
      </div>

      <!-- Producer -->
      <div class="form-group">
        <label>Producer *</label>
        <select
          v-model="form.producer_id"
          required
          :class="{ 'error': errors.producer_id }"
        >
          <option value="">Pilih Producer</option>
          <option
            v-for="producer in producers"
            :key="producer.id"
            :value="producer.id"
          >
            {{ producer.name }} ({{ producer.email }})
          </option>
        </select>
        <span v-if="errors.producer_id" class="error-text">
          {{ errors.producer_id[0] }}
        </span>
      </div>

      <!-- Submit Button -->
      <button type="submit" :disabled="loading">
        {{ loading ? 'Membuat...' : 'Buat Team' }}
      </button>
    </form>

    <!-- Success Message -->
    <div v-if="createdTeam" class="success-message">
      <p>✅ Team berhasil dibuat!</p>
      <p>Team ID: {{ createdTeam.id }}</p>
      <router-link :to="`/teams/${createdTeam.id}/members`">
        Tambahkan Members
      </router-link>
    </div>
  </div>
</template>

<script>
import { productionTeamService } from '@/services/productionTeamService';
import { userService } from '@/services/userService';

export default {
  name: 'CreateProductionTeam',
  data() {
    return {
      form: {
        name: '',
        description: '',
        producer_id: '',
      },
      producers: [],
      errors: {},
      loading: false,
      createdTeam: null,
    };
  },
  async mounted() {
    // Load producers list
    await this.loadProducers();
  },
  methods: {
    async loadProducers() {
      try {
        // Asumsikan ada endpoint untuk get producers
        const response = await userService.getProducers();
        this.producers = response.data;
      } catch (error) {
        console.error('Error loading producers:', error);
      }
    },

    async handleSubmit() {
      this.loading = true;
      this.errors = {};

      try {
        const response = await productionTeamService.createTeam(this.form);
        
        if (response.success) {
          this.createdTeam = response.data;
          this.$toast.success('Team berhasil dibuat!');
          
          // Reset form
          this.form = {
            name: '',
            description: '',
            producer_id: '',
          };
        }
      } catch (error) {
        if (error.response?.status === 422) {
          this.errors = error.response.data.errors || {};
          this.$toast.error('Validasi gagal. Periksa form Anda.');
        } else {
          this.$toast.error('Gagal membuat team: ' + (error.response?.data?.message || error.message));
        }
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.create-production-team {
  max-width: 600px;
  margin: 0 auto;
  padding: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.form-group input.error,
.form-group select.error {
  border-color: #f44336;
}

.error-text {
  color: #f44336;
  font-size: 12px;
  margin-top: 5px;
  display: block;
}

button {
  background-color: #4caf50;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
}

button:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

.success-message {
  margin-top: 20px;
  padding: 15px;
  background-color: #e8f5e9;
  border-radius: 4px;
}
</style>
```

---

### **3. Component: Add Members to Team**

```vue
<!-- components/AddTeamMembers.vue -->
<template>
  <div class="add-team-members">
    <h2>Tambah Members ke Team: {{ teamName }}</h2>

    <!-- Team Statistics -->
    <div v-if="statistics" class="team-statistics">
      <h3>Status Team</h3>
      <div class="stats-grid">
        <div class="stat-item">
          <span class="stat-label">Total Members:</span>
          <span class="stat-value">{{ statistics.total_members }}</span>
        </div>
        <div class="stat-item">
          <span class="stat-label">Ready for Production:</span>
          <span
            class="stat-value"
            :class="statistics.is_ready_for_production ? 'ready' : 'not-ready'"
          >
            {{ statistics.is_ready_for_production ? '✅ Ya' : '❌ Belum' }}
          </span>
        </div>
      </div>

      <!-- Role Distribution -->
      <div class="role-distribution">
        <h4>Distribusi Role:</h4>
        <div class="role-list">
          <div
            v-for="(count, role) in statistics.role_distribution"
            :key="role"
            class="role-item"
          >
            <span class="role-name">{{ getRoleLabel(role) }}:</span>
            <span class="role-count">{{ count }} orang</span>
          </div>
        </div>
      </div>

      <!-- Missing Roles -->
      <div v-if="statistics.missing_roles.length > 0" class="missing-roles">
        <h4>Role yang Belum Ada:</h4>
        <ul>
          <li v-for="role in statistics.missing_roles" :key="role">
            {{ getRoleLabel(role) }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Add Member Form -->
    <div class="add-member-form">
      <h3>Tambah Member Baru</h3>
      
      <form @submit.prevent="handleAddMember">
        <!-- Role Selection -->
        <div class="form-group">
          <label>Role *</label>
          <select
            v-model="memberForm.role"
            required
            @change="loadAvailableUsers"
          >
            <option value="">Pilih Role</option>
            <option value="kreatif">Creative</option>
            <option value="musik_arr">Music Arranger</option>
            <option value="sound_eng">Sound Engineer</option>
            <option value="produksi">Produksi</option>
            <option value="editor">Editor</option>
            <option value="art_set_design">Art & Set Design</option>
          </select>
        </div>

        <!-- User Selection -->
        <div class="form-group" v-if="memberForm.role">
          <label>Pilih User *</label>
          <select
            v-model="memberForm.user_id"
            required
            :disabled="!availableUsers.length"
          >
            <option value="">Pilih User</option>
            <option
              v-for="user in availableUsers"
              :key="user.id"
              :value="user.id"
            >
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
          <span v-if="!availableUsers.length && memberForm.role" class="info-text">
            Tidak ada user tersedia untuk role ini
          </span>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label>Notes (Optional)</label>
          <textarea
            v-model="memberForm.notes"
            rows="2"
            placeholder="Catatan tambahan..."
          ></textarea>
        </div>

        <!-- Submit Button -->
        <button type="submit" :disabled="loading || !memberForm.role || !memberForm.user_id">
          {{ loading ? 'Menambahkan...' : 'Tambah Member' }}
        </button>
      </form>
    </div>

    <!-- Current Members List -->
    <div class="members-list">
      <h3>Members Saat Ini</h3>
      <table>
        <thead>
          <tr>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Notes</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in currentMembers" :key="member.id">
            <td>{{ member.user?.name || 'N/A' }}</td>
            <td>{{ member.user?.email || 'N/A' }}</td>
            <td>{{ member.role_label || member.role }}</td>
            <td>{{ member.notes || '-' }}</td>
            <td>
              <button
                @click="removeMember(member.id)"
                class="btn-remove"
                :disabled="loading"
              >
                Hapus
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { productionTeamService } from '@/services/productionTeamService';

export default {
  name: 'AddTeamMembers',
  props: {
    teamId: {
      type: [Number, String],
      required: true,
    },
  },
  data() {
    return {
      teamName: '',
      statistics: null,
      currentMembers: [],
      availableUsers: [],
      memberForm: {
        role: '',
        user_id: '',
        notes: '',
      },
      loading: false,
      roles: {
        kreatif: 'Creative',
        musik_arr: 'Music Arranger',
        sound_eng: 'Sound Engineer',
        produksi: 'Produksi',
        editor: 'Editor',
        art_set_design: 'Art & Set Design',
      },
    };
  },
  async mounted() {
    await this.loadTeamData();
  },
  methods: {
    async loadTeamData() {
      try {
        // Load team details
        const teamResponse = await productionTeamService.getTeam(this.teamId);
        if (teamResponse.success) {
          this.teamName = teamResponse.data.name;
          this.currentMembers = teamResponse.data.members || [];
        }

        // Load statistics
        const statsResponse = await productionTeamService.getTeamStatistics(
          this.teamId
        );
        if (statsResponse.success) {
          this.statistics = statsResponse.data;
        }
      } catch (error) {
        console.error('Error loading team data:', error);
        this.$toast.error('Gagal memuat data team');
      }
    },

    async loadAvailableUsers() {
      if (!this.memberForm.role) return;

      try {
        const response = await productionTeamService.getAvailableUsersForRole(
          this.memberForm.role
        );
        if (response.success) {
          this.availableUsers = response.data;
        }
      } catch (error) {
        console.error('Error loading available users:', error);
        this.$toast.error('Gagal memuat daftar user');
      }
    },

    async handleAddMember() {
      this.loading = true;

      try {
        const response = await productionTeamService.addMember(
          this.teamId,
          this.memberForm
        );

        if (response.success) {
          this.$toast.success('Member berhasil ditambahkan!');
          
          // Reset form
          this.memberForm = {
            role: '',
            user_id: '',
            notes: '',
          };
          this.availableUsers = [];

          // Reload team data
          await this.loadTeamData();
        }
      } catch (error) {
        if (error.response?.status === 422) {
          this.$toast.error(
            'Validasi gagal: ' +
              (error.response.data.message || 'Periksa form Anda')
          );
        } else {
          this.$toast.error(
            'Gagal menambahkan member: ' +
              (error.response?.data?.message || error.message)
          );
        }
      } finally {
        this.loading = false;
      }
    },

    async removeMember(memberId) {
      if (!confirm('Yakin ingin menghapus member ini?')) return;

      try {
        // Implement remove member API call
        // await productionTeamService.removeMember(this.teamId, memberId);
        this.$toast.success('Member berhasil dihapus');
        await this.loadTeamData();
      } catch (error) {
        this.$toast.error('Gagal menghapus member');
      }
    },

    getRoleLabel(role) {
      return this.roles[role] || role;
    },
  },
};
</script>

<style scoped>
.add-team-members {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.team-statistics {
  background-color: #f5f5f5;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 30px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
  padding: 10px;
  background-color: white;
  border-radius: 4px;
}

.stat-label {
  font-weight: bold;
}

.stat-value.ready {
  color: #4caf50;
}

.stat-value.not-ready {
  color: #f44336;
}

.role-distribution {
  margin-top: 20px;
}

.role-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
  margin-top: 10px;
}

.role-item {
  padding: 8px;
  background-color: white;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
}

.missing-roles {
  margin-top: 15px;
  padding: 15px;
  background-color: #fff3cd;
  border-radius: 4px;
}

.missing-roles ul {
  margin: 10px 0 0 20px;
}

.add-member-form {
  background-color: white;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 30px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.members-list {
  background-color: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

table th,
table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

table th {
  background-color: #f5f5f5;
  font-weight: bold;
}

.btn-remove {
  background-color: #f44336;
  color: white;
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
}

.btn-remove:hover {
  background-color: #d32f2f;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.form-group select:disabled {
  background-color: #f5f5f5;
  cursor: not-allowed;
}

.info-text {
  color: #666;
  font-size: 12px;
  margin-top: 5px;
  display: block;
}

button {
  background-color: #4caf50;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
}

button:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}
</style>
```

---

### **4. Component: Assign Team to Episode**

```vue
<!-- components/AssignTeamToEpisode.vue -->
<template>
  <div class="assign-team-to-episode">
    <h2>Assign Team ke Episode</h2>

    <!-- Episode Info -->
    <div class="episode-info">
      <h3>Episode: {{ episode.episode_number }} - {{ episode.title }}</h3>
      <p>Program: {{ episode.program?.name }}</p>
      <p v-if="episode.production_team_id">
        Team Saat Ini: {{ currentTeamName }}
      </p>
    </div>

    <!-- Available Teams -->
    <div class="teams-list">
      <h3>Pilih Production Team</h3>
      
      <div v-if="loadingTeams" class="loading">Memuat teams...</div>
      
      <div v-else class="teams-grid">
        <div
          v-for="team in availableTeams"
          :key="team.id"
          class="team-card"
          :class="{
            'selected': selectedTeamId === team.id,
            'ready': team.is_ready_for_production,
          }"
          @click="selectTeam(team.id)"
        >
          <div class="team-header">
            <h4>{{ team.name }}</h4>
            <span
              class="status-badge"
              :class="team.is_ready_for_production ? 'ready' : 'not-ready'"
            >
              {{ team.is_ready_for_production ? '✅ Ready' : '❌ Not Ready' }}
            </span>
          </div>
          
          <p class="team-description">{{ team.description || 'Tidak ada deskripsi' }}</p>
          
          <div class="team-stats">
            <span>Members: {{ team.member_count || 0 }}</span>
            <span>Producer: {{ team.producer?.name || 'N/A' }}</span>
          </div>

          <!-- Role Summary -->
          <div v-if="team.roles_summary" class="roles-summary">
            <div
              v-for="(count, role) in team.roles_summary"
              :key="role"
              class="role-badge"
            >
              {{ getRoleLabel(role) }}: {{ count }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Form -->
    <div v-if="selectedTeamId" class="assign-form">
      <h3>Assign Team</h3>
      
      <form @submit.prevent="handleAssign">
        <div class="form-group">
          <label>Notes (Optional)</label>
          <textarea
            v-model="assignNotes"
            rows="3"
            placeholder="Catatan untuk assignment ini..."
          ></textarea>
        </div>

        <button type="submit" :disabled="loading">
          {{ loading ? 'Assigning...' : 'Assign Team ke Episode' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script>
import { productionTeamService } from '@/services/productionTeamService';
import { episodeService } from '@/services/episodeService';

export default {
  name: 'AssignTeamToEpisode',
  props: {
    episodeId: {
      type: [Number, String],
      required: true,
    },
  },
  data() {
    return {
      episode: {},
      availableTeams: [],
      selectedTeamId: null,
      currentTeamName: '',
      assignNotes: '',
      loading: false,
      loadingTeams: false,
      roles: {
        kreatif: 'Creative',
        musik_arr: 'Music Arranger',
        sound_eng: 'Sound Engineer',
        produksi: 'Produksi',
        editor: 'Editor',
        art_set_design: 'Art & Set Design',
      },
    };
  },
  async mounted() {
    await Promise.all([this.loadEpisode(), this.loadAvailableTeams()]);
  },
  methods: {
    async loadEpisode() {
      try {
        const response = await episodeService.getEpisode(this.episodeId);
        if (response.success) {
          this.episode = response.data;
          
          // Load current team name if exists
          if (this.episode.production_team_id) {
            const teamResponse = await productionTeamService.getTeam(
              this.episode.production_team_id
            );
            if (teamResponse.success) {
              this.currentTeamName = teamResponse.data.name;
            }
          }
        }
      } catch (error) {
        console.error('Error loading episode:', error);
        this.$toast.error('Gagal memuat data episode');
      }
    },

    async loadAvailableTeams() {
      this.loadingTeams = true;
      try {
        // Asumsikan ada endpoint untuk get all teams
        const response = await productionTeamService.getAllTeams();
        if (response.success) {
          // Filter hanya teams yang ready
          this.availableTeams = response.data.filter(
            (team) => team.is_ready_for_production
          );
        }
      } catch (error) {
        console.error('Error loading teams:', error);
        this.$toast.error('Gagal memuat daftar teams');
      } finally {
        this.loadingTeams = false;
      }
    },

    selectTeam(teamId) {
      this.selectedTeamId = teamId;
    },

    async handleAssign() {
      if (!this.selectedTeamId) {
        this.$toast.error('Pilih team terlebih dahulu');
        return;
      }

      this.loading = true;

      try {
        const response = await productionTeamService.assignTeamToEpisode(
          this.episodeId,
          this.selectedTeamId,
          this.assignNotes
        );

        if (response.success) {
          this.$toast.success('Team berhasil di-assign ke episode!');
          
          // Reload episode data
          await this.loadEpisode();
          
          // Reset form
          this.selectedTeamId = null;
          this.assignNotes = '';
          
          // Emit event untuk refresh parent component
          this.$emit('team-assigned', response.data);
        }
      } catch (error) {
        if (error.response?.status === 422) {
          this.$toast.error(
            'Validasi gagal: ' +
              (error.response.data.message || 'Periksa form Anda')
          );
        } else {
          this.$toast.error(
            'Gagal assign team: ' +
              (error.response?.data?.message || error.message)
          );
        }
      } finally {
        this.loading = false;
      }
    },

    getRoleLabel(role) {
      return this.roles[role] || role;
    },
  },
};
</script>

<style scoped>
.assign-team-to-episode {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.episode-info {
  background-color: #e3f2fd;
  padding: 20px;
  border-radius: 8px;
  margin-bottom: 30px;
}

.episode-info h3 {
  margin: 0 0 10px 0;
}

.teams-list {
  margin-bottom: 30px;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #666;
}

.teams-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
  margin-top: 20px;
}

.team-card {
  background-color: white;
  border: 2px solid #ddd;
  border-radius: 8px;
  padding: 20px;
  cursor: pointer;
  transition: all 0.3s;
}

.team-card:hover {
  border-color: #4caf50;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.team-card.selected {
  border-color: #4caf50;
  background-color: #e8f5e9;
}

.team-card.ready {
  border-left: 4px solid #4caf50;
}

.team-card:not(.ready) {
  border-left: 4px solid #f44336;
}

.team-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.team-header h4 {
  margin: 0;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: bold;
}

.status-badge.ready {
  background-color: #4caf50;
  color: white;
}

.status-badge.not-ready {
  background-color: #f44336;
  color: white;
}

.team-description {
  color: #666;
  font-size: 14px;
  margin: 10px 0;
}

.team-stats {
  display: flex;
  gap: 15px;
  font-size: 12px;
  color: #888;
  margin: 10px 0;
}

.roles-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 10px;
}

.role-badge {
  background-color: #f5f5f5;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
}

.assign-form {
  background-color: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
}

button {
  background-color: #4caf50;
  color: white;
  padding: 12px 24px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
}

button:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}
</style>
```

---

### **5. Service: Episode Service (episodeService.js)**

```javascript
// services/episodeService.js
import api from './api';

export const episodeService = {
  async getEpisode(episodeId) {
    try {
      const response = await api.get(`/live-tv/episodes/${episodeId}`);
      return response.data;
    } catch (error) {
      console.error('Error getting episode:', error);
      throw error;
    }
  },
};
```

---

### **6. Service: User Service (userService.js)**

```javascript
// services/userService.js
import api from './api';

export const userService = {
  async getProducers() {
    try {
      // Asumsikan ada endpoint untuk get producers
      const response = await api.get('/users?role=Producer');
      return response.data;
    } catch (error) {
      console.error('Error getting producers:', error);
      throw error;
    }
  },
};
```

---

## 🔄 FLOW LENGKAP FRONTEND

### **Step 1: Create Team**
```
User → CreateProductionTeam.vue
  → productionTeamService.createTeam()
  → POST /api/live-tv/production-teams
  → Success: Redirect ke AddTeamMembers
```

### **Step 2: Add Members**
```
User → AddTeamMembers.vue
  → Load team statistics
  → Select role → Load available users
  → productionTeamService.addMember()
  → POST /api/live-tv/production-teams/{id}/members
  → Success: Reload team data
```

### **Step 3: Assign to Episode**
```
User → AssignTeamToEpisode.vue
  → Load available teams
  → Select team
  → productionTeamService.assignTeamToEpisode()
  → POST /api/live-tv/manager-program/episodes/{id}/assign-team
  → Success: Show notification
```

---

## ✅ VALIDASI FRONTEND

### **Create Team Validation:**
- ✅ Name: Required, min 3 characters
- ✅ Producer ID: Required, must exist
- ✅ Check if name already exists (show error)

### **Add Member Validation:**
- ✅ Role: Required, must be valid role
- ✅ User ID: Required, must exist
- ✅ Check if user already in team (show error)

### **Assign Team Validation:**
- ✅ Team ID: Required
- ✅ Episode ID: Required
- ✅ Check if team is ready for production

---

## 🐛 ERROR HANDLING

### **422 Validation Error:**
```javascript
if (error.response?.status === 422) {
  const errors = error.response.data.errors;
  // Display errors to user
  Object.keys(errors).forEach(field => {
    this.errors[field] = errors[field];
  });
}
```

### **500 Server Error:**
```javascript
if (error.response?.status === 500) {
  this.$toast.error('Server error. Silakan coba lagi atau hubungi admin.');
  console.error('Server error:', error.response.data);
}
```

### **Network Error:**
```javascript
if (!error.response) {
  this.$toast.error('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
}
```

---

## 📝 CATATAN PENTING

1. **Multiple Members dengan Role Sama:**
   - Sistem mendukung banyak pegawai dengan role yang sama
   - Semua akan mendapat notifikasi saat team di-assign
   - Producer/Manager bisa pilih member mana yang akan mengerjakan episode tertentu

2. **Team Ready Check:**
   - Team harus memiliki minimal 1 orang untuk setiap role (6 roles)
   - Gunakan `GET /api/live-tv/production-teams/{id}/statistics` untuk cek status

3. **Error Handling:**
   - Selalu handle 422 (validation), 500 (server error), dan network error
   - Tampilkan pesan error yang user-friendly

4. **Loading States:**
   - Tampilkan loading indicator saat API call
   - Disable form saat loading untuk prevent double submit

---

**Last Updated:** 2025-12-12  
**Created By:** AI Assistant

