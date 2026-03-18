# 📋 Panduan Implementasi Frontend – Distribution Manager Program Musik

**Target:** Perbaikan tab Schedule, Schedule Options, Weekly Airing Control  
**Tanggal:** 26 Februari 2026  
**Backend:** Sudah di-update (meta, filter status)

---

## 1. API Backend yang Tersedia

### Schedule Options
```
GET /api/live-tv/distribution-manager/schedule-options
```
**Query params:**
- `status` (optional): `pending` | `approved` | `revised` | `rejected`
- `program_id` (optional): filter by program

**Response (baru):**
```json
{
  "success": true,
  "data": { /* paginated items */ },
  "meta": {
    "pending_count": 0,
    "approved_count": 2,
    "revised_count": 0,
    "rejected_count": 0,
    "note": "Approved options create BroadcastingSchedule. Check Schedule tab for approved air dates."
  },
  "message": "Schedule options retrieved successfully"
}
```

### Schedules
```
GET /api/live-tv/distribution-manager/schedules
```
**Query params:**
- `status`: pending | scheduled | published | failed
- `platform`: tv | youtube | website
- `start_date`, `end_date`: filter by date range

**Response (baru):**
```json
{
  "success": true,
  "data": { /* paginated items */ },
  "meta": {
    "week_start": "2026-02-24",
    "week_end": "2026-03-02",
    "note": "Use start_date/end_date params to filter by week. Schedules come from approved Schedule Options."
  },
  "message": "Schedules retrieved successfully"
}
```

---

## 2. Perbaikan Schedule Options Tab

### 2.1 Tambah Tab: Pending | Disetujui | Ditolak

```vue
<template>
  <div class="schedule-options-tab">
    <div class="tabs">
      <button 
        :class="{ active: activeTab === 'pending' }"
        @click="activeTab = 'pending'; loadOptions('pending')"
      >
        Pending
        <span v-if="meta.pending_count" class="badge">{{ meta.pending_count }}</span>
      </button>
      <button 
        :class="{ active: activeTab === 'approved' }"
        @click="activeTab = 'approved'; loadOptions('approved')"
      >
        Disetujui
      </button>
      <button 
        :class="{ active: activeTab === 'rejected' }"
        @click="activeTab = 'rejected'; loadOptions('rejected')"
      >
        Ditolak
      </button>
    </div>

    <!-- List -->
    <div v-if="loading">Loading...</div>
    <div v-else-if="items.length === 0" class="empty-state">
      <template v-if="activeTab === 'pending'">
        <p><strong>Semua proposal sudah diproses ✓</strong></p>
        <p class="text-muted">Proposal yang Anda setujui tampil di tab <strong>Schedule</strong>.</p>
        <p class="text-muted">Proposal baru akan muncul di sini setelah Program Manager mengirim usulan jadwal.</p>
      </template>
      <template v-else>
        <p>Tidak ada data</p>
      </template>
    </div>
    <div v-else>
      <!-- Card list -->
    </div>
  </div>
</template>

<script setup>
const activeTab = ref('pending');
const meta = ref({ pending_count: 0, approved_count: 0, rejected_count: 0 });

async function loadOptions(status) {
  const res = await musicWorkflowService.getScheduleOptions({ status });
  items.value = res.data?.data ?? [];
  meta.value = res.data?.meta ?? {};
}
</script>
```

### 2.2 Panggil API dengan Status

```javascript
// musicWorkflowService atau distributionManagerService
getScheduleOptions: (params) => api.get('/live-tv/distribution-manager/schedule-options', { params })
```

**Contoh:** `getScheduleOptions({ status: 'pending' })` → hanya yang pending.

### 2.3 Copy Empty State

Saat tab **Pending** kosong, tampilkan:

> **Semua proposal sudah diproses ✓**  
> Proposal yang Anda setujui tampil di tab **Schedule**.  
> Proposal baru akan muncul di sini setelah Program Manager mengirim usulan jadwal.

---

## 3. Perbaikan Schedule vs Weekly Airing Control

### 3.1 Opsi A (Disarankan): Satu Tab "Schedule" Saja

- Hapus tab "Weekly Airing Control".
- Di tab "Schedule", pertahankan sub-tab: **Minggu ini** | **Semua jadwal**.
- Untuk "Minggu ini", panggil:
  ```javascript
  getSchedules({ 
    start_date: meta.week_start, 
    end_date: meta.week_end 
  })
  ```

### 3.2 Opsi B: Bedakan Konten

Jika tetap ingin dua tab:

- **Schedule:** Tetap pakai `GET /live-tv/distribution-manager/schedules` (BroadcastingSchedule).
- **Weekly Airing Control:** Bukan komponen Program Regular. Pakai data Schedule yang difilter minggu ini, atau endpoint khusus Program Musik. Jangan pakai `DistribusiDashboard`, `AiringCalendar`, `DistributionScheduleList` dari Program Regular.

---

## 4. Fix Error JavaScript

### 4.1 `schedules.value.filter is not a function`

Penyebab: response berbentuk `{ data: { data: [...], meta: {} } }`, bukan array.

**Perbaikan:**
```javascript
// Salah
const list = schedules.value.filter(...)

// Benar
const raw = schedules.value;
const list = Array.isArray(raw) 
  ? raw 
  : (raw?.data ?? []);
const filtered = list.filter(Boolean).filter(...);
```

### 4.2 `Cannot read properties of null (reading 'id')`

Penyebab: ada item `null` dalam array.

**Perbaikan:**
```vue
<template v-for="item in (items || []).filter(Boolean)" :key="item?.id ?? item">
  <!-- pastikan item ada sebelum akses item.id -->
</template>
```

Atau di script:
```javascript
const safeItems = (items.value ?? []).filter(item => item && item.id);
```

### 4.3 `programRegularService.listProgramsForDistribusi is not a function`

Penyebab: halaman Program Musik memakai komponen Program Regular.

**Perbaikan:**
- Pastikan tab Program Musik Distribution Manager hanya memakai service Program Musik (mis. `musicWorkflowService`), bukan `programRegularService`.
- Atau tambahkan method di `programRegularService` jika memang dipakai:
  ```javascript
  listProgramsForDistribusi: (params) => api.get('/api/pr/distribusi/programs', { params })
  ```
  Perlu dicek apakah route `/api/pr/distribusi/programs` ada di backend.

---

## 5. Jangan Campur Program Musik dan Program Regular

| Fitur           | Program Musik (Live TV)                 | Program Regular (PR)                      |
|----------------|-----------------------------------------|------------------------------------------|
| Base URL       | `/api/live-tv/`                         | `/api/pr/`                               |
| Schedule       | `distribution-manager/schedules`        | -                                        |
| Schedule Options | `distribution-manager/schedule-options` | -                                        |
| List Programs  | `live-tv/programs`                      | `pr/distribusi/programs`                 |
| Dashboard      | `distribution-manager/dashboard`        | `pr/manager-distribusi-qc/*` (PR only)   |

Halaman **Program Musik → Distribution Manager** harus memakai endpoint `live-tv` saja. Jangan load komponen yang memanggil `pr/` atau `distribusi/` kecuali itu memang halaman Program Regular.

---

## 6. Contoh Update musicWorkflowService

```javascript
// getScheduleOptions - support status filter
getScheduleOptions: (params = {}) => 
  api.get('/live-tv/distribution-manager/schedule-options', { params }),

// getSchedules - support week filter
getSchedules: (params = {}) => 
  api.get('/live-tv/distribution-manager/schedules', { params }),
```

Pastikan response di-handle dengan benar:
```javascript
const res = await getScheduleOptions({ status: 'pending' });
const items = res.data?.data?.data ?? res.data?.data ?? [];  // sesuaikan struktur
const meta = res.data?.meta ?? res.data?.data?.meta ?? {};
```

---

## 7. Checklist Implementasi

- [ ] Schedule Options: Tab Pending | Disetujui | Ditolak
- [ ] Schedule Options: Panggil API dengan `?status=pending` (atau approved/rejected)
- [ ] Schedule Options: Gunakan `meta.pending_count` untuk badge
- [ ] Schedule Options: Copy empty state yang jelas
- [ ] Schedule vs Weekly Airing Control: Hapus redundant atau bedakan konten
- [ ] Fix `schedules.value.filter` → extract array dengan aman
- [ ] Fix `null.id` → filter `null` / gunakan optional chaining
- [ ] Pisahkan komponen: Program Musik jangan pakai komponen Program Regular
- [ ] Uji flow: submit dari Program Manager → approve di Distribution Manager → cek tab Schedule

---

**Backend sudah siap.** Silakan implementasi perubahan frontend sesuai panduan di atas.
