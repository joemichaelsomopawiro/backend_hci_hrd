# 📋 MENTOR ANALYSIS: Distribution Manager Program Musik - Schedule, Weekly Airing Control, Schedule Options

**Tanggal:** 26 Februari 2026  
**Scope:** Program Musik (Live TV) - Distribution Manager  
**Role:** Senior Fullstack Engineer – Analisis & Rekomendasi

---

## 🎯 RINGKASAN MASALAH

1. **Tab Schedule vs Weekly Airing Control** → Menampilkan konten yang SAMA (redundant)
2. **Tab Schedule Options** → Menampilkan "No Pending Proposals" padahal user sudah approve → user bingung kemana data approved-nya
3. **Tab Weekly Airing Control** → Error JavaScript (listProgramsForDistribusi, schedules.filter, null.id)
4. **Design & UX** → Perlu perbaikan agar profesional, rapi, user-friendly

---

## 🔍 ROOT CAUSE ANALYSIS

### 1. Kenapa Schedule dan Weekly Airing Control SAMA?

**Penyebab utama: SALAH ROUTING / WRONG COMPONENT MAPPING**

Dari log yang Anda berikan:

| Tab | API yang dipanggil | Komponen |
|-----|-------------------|----------|
| **Schedule** | `GET /live-tv/distribution-manager/schedules` ✅ | DistributionScheduleList (Program Musik) |
| **Weekly Airing Control** | `GET /live-tv/distribution-manager/schedules` + `schedule-options` (sama!) | DistribusiDashboard (Program Regular!) |

**Temuan kritis:**
- Tab **Schedule** memakai flow Program Musik dengan benar
- Tab **Weekly Airing Control** memuat **DistribusiDashboard** dari **Program Regular** (bukan Program Musik!)
- Karena routing salah, keduanya akhirnya render data dari sumber yang sama (`distribution-manager/schedules`)
- Di backend, **Weekly Airing Control** sebenarnya fitur **Producer** (`GET /live-tv/producer/weekly-airing-control`), BUKAN Distribution Manager

**Kesimpulan:**  
Schedule dan Weekly Airing Control menampilkan hal yang sama karena:
1. Weekly Airing Control salah memakai komponen Program Regular
2. Komponen tersebut error → mungkin fallback ke parent yang sama dengan Schedule
3. Atau keduanya memang di-design untuk pakai komponen yang sama (design flaw)

---

### 2. Kenapa Schedule Options Kosong Setelah Approve?

**Ini BUKAN bug – ini EXPECTED BEHAVIOR, tapi UX-nya membingungkan user.**

**Alur yang benar:**

```
Manager Program submit Schedule Options (status: pending)
        ↓
Distribution Manager lihat di tab "Schedule Options" (pending)
        ↓
Distribution Manager APPROVE (pilih salah satu opsi)
        ↓
Backend: status → 'approved'
Backend: Auto-create BroadcastingSchedule
        ↓
Data TIDAK LAGI muncul di "Schedule Options" (karena UI hanya tampilkan PENDING)
Data MUNCUL di tab "Schedule" (BroadcastingSchedule)
```

**Yang membingungkan user:**
- User approve → expects to see "approved" di Schedule Options
- UI hanya menampilkan **pending** → user pikir datanya hilang
- Padahal data approved PINDAH ke tab Schedule (BroadcastingSchedule)
- Tidak ada petunjuk/CTA yang menjelaskan: "Setelah approve, jadwal muncul di tab Schedule"

**Backend API:**
- `GET /live-tv/distribution-manager/schedule-options` → Mengembalikan **SEMUA** status (pending, approved, revised, rejected) jika tidak ada filter
- Frontend kemungkinan filter client-side untuk tampilkan hanya `status: pending`
- Jadi saat tidak ada pending → tampil "No Pending Proposals" (benar secara logic, tapi misleading untuk user)

---

### 3. Kenapa Weekly Airing Control Error?

Dari log error:

| Error | Lokasi | Penyebab |
|-------|--------|----------|
| `programRegularService.listProgramsForDistribusi is not a function` | ProgramVerificationList, DistribusiDashboard, DistribusiRevisionHistory | Service Program Regular tidak punya method ini, ATAU salah import |
| `schedules.value.filter is not a function` | AiringCalendar.vue:139 | `schedules` adalah object `{ data: [...] }`, bukan array. Perlu `schedules.value?.data?.filter()` atau extract array dulu |
| `Cannot read properties of null (reading 'id')` | DistributionScheduleList, DistributionReports | Ada item `null` di array. Perlu `v-for` dengan filter `item && item.id` |
| `500 /pr/manager-distribusi-qc/works` | ManagerDistribusiQCDashboard | Endpoint Program Regular mengembalikan 500 – kemungkinan tidak cocok dengan struktur Program Musik |

**Penyebab utama:**  
Weekly Airing Control menggunakan komponen **Program Regular** (PR) yang:
- Mengharapkan API `/pr/`, `/distribusi/` 
- Program Musik memakai `/live-tv/`
- Struktur response berbeda → error

---

## ✅ REKOMENDASI PERBAIKAN

### A. BACKEND (Program Musik – yang bisa dikerjakan di repo ini)

#### 1. Schedule Options – Tambah Filter & Default Response Jelas

**File:** `app/Http/Controllers/Api/DistributionManagerController.php`

Pastikan response selalu konsisten. Tambah metadata agar frontend bisa tampilkan tab "Pending" vs "History":

```php
// Di getScheduleOptions, pastikan response punya structure:
return response()->json([
    'success' => true,
    'data' => $options,
    'meta' => [
        'pending_count' => ProgramScheduleOption::where('status', 'pending')->count(),
        'total' => $options->total(),
    ],
    'message' => 'Schedule options retrieved successfully'
]);
```

Frontend bisa pakai `pending_count` untuk badge "X proposals need review".

#### 2. Endpoint Weekly Airing Control untuk Distribution Manager (opsional)

Saat ini Weekly Airing Control hanya untuk Producer. Jika Distribution Manager butuh view mingguan:

**Opsi A:** Buat endpoint baru  
`GET /live-tv/distribution-manager/weekly-schedules`  
→ Return schedules grouped by week (dari BroadcastingSchedule), mirip Producer tapi scope Distribution Manager.

**Opsi B:** Jangan buat tab terpisah – cukup satu tab "Schedule" dengan filter "Minggu ini" / "Semua" (sudah ada). Hapus tab "Weekly Airing Control" agar tidak redundant.

---

### B. FRONTEND (Perlu di-repo frontend)

#### 1. Pisahkan Route & Komponen Program Musik vs Program Regular

- **Program Musik** Distribution Manager → Pakai komponen yang memanggil `musicWorkflowService`, `GET /live-tv/distribution-manager/*`
- **Program Regular** Manager Distribusi → Pakai `programRegularService`, `GET /pr/distribusi/*`, `GET /pr/manager-distribusi-qc/*`
- Jangan pakai `DistribusiDashboard`, `AiringCalendar`, `DistributionScheduleList` dari Program Regular di halaman Program Musik.

#### 2. Tab "Schedule" vs "Weekly Airing Control"

**Rekomendasi design:**

| Opsi | Keterangan |
|------|------------|
| **Opsi A (Disarankan)** | Hapus tab "Weekly Airing Control". Cukup satu tab "Schedule" dengan sub-tab "Minggu ini" dan "Semua jadwal". Hindari duplikasi. |
| **Opsi B** | Buat Weekly Airing Control khusus Program Musik: pakai data `BroadcastingSchedule` + readiness (jika ada) dari endpoint baru. Jangan pakai komponen Program Regular. |

#### 3. Schedule Options – Tambah Tab "Riwayat" / "History"

```
Tab: [Pending] [Disetujui] [Ditolak]
```

- **Pending:** Opsi yang perlu di-review (status `pending`)
- **Disetujui:** Opsi yang sudah di-approve (status `approved`) + info "Jadwal masuk ke tab Schedule"
- **Ditolak:** Opsi yang di-reject (status `rejected`)

Panggil API dengan query: `?status=pending`, `?status=approved`, `?status=rejected`.

#### 4. Perbaiki Error JavaScript

**AiringCalendar.vue (sekitar line 139):**
```javascript
// Salah:
schedules.value.filter(...)

// Benar – sesuaikan dengan response API:
const list = Array.isArray(schedules.value) 
  ? schedules.value 
  : (schedules.value?.data ?? []);
list.filter(...)
```

**DistributionScheduleList.vue, DistributionReports.vue:**
```vue
<!-- Tambah null check -->
<template v-for="item in (items || []).filter(Boolean)" :key="item?.id">
```

**programRegularService:**
- Pastikan `listProgramsForDistribusi` ter-export dan dipanggil dengan base URL yang benar (`/api/pr/distribusi/programs` atau sesuai route backend)
- Jika dipakai di Program Musik, pastikan base URL-nya `/live-tv/` dan endpoint memang ada

#### 5. UX Copy – Kurangi Kebingungan

**Tab Schedule Options – empty state (saat tidak ada pending):**

```
Semua proposal sudah diproses ✓
Proposal yang Anda setujui tampil di tab **Schedule**.
Proposal baru akan muncul di sini setelah Program Manager mengirim usulan jadwal.
```

**Tab Schedule – header:**

```
Jadwal Tayang (dari Schedule Options yang disetujui)
Kelola jadwal tayang, approve/reject, dan handover ke staf broadcasting.
```

---

## 📐 REKOMENDASI DESIGN & UX

### 1. Simplifikasi Menu Sidebar

**Saat ini (membingungkan):**
- Schedule
- Weekly Airing Control  ← redundant
- Schedule Options

**Usulan (lebih jelas):**

```
Program Musik
├── Dashboard
├── Programs & Episodes
├── Schedule Options      ← Usulan jadwal dari Program Manager (approve/reject)
├── Schedule              ← Jadwal tayang yang sudah disetujui (BroadcastingSchedule)
├── Production Overview
├── Workflow Monitoring
└── ...
```

Hapus "Weekly Airing Control" atau ganti menjadi filter di dalam tab "Schedule" (Minggu ini / Semua).

### 2. Layout yang Lebih Rapi

- Card jadwal: padding konsisten, typography jelas (program, episode, tanggal, status)
- Tombol Approve/Reject/Revise: warna dan status yang jelas (mis. hijau/orange/merah)
- Empty state: ikon + teks singkat + CTA jika perlu
- Loading state: skeleton atau spinner saat fetch data

### 3. Responsive

- Card stack vertical di mobile
- Filter/Sort tetap bisa diakses (collapse atau dropdown di mobile)
- Tombol aksi tidak terlalu kecil untuk touch

---

## 📊 RINGKASAN AKSI

| # | Pekerjaan | Prioritas | Owner |
|---|-----------|-----------|-------|
| 1 | Hapus atau ganti tab "Weekly Airing Control" (jangan pakai komponen Program Regular) | Tinggi | Frontend |
| 2 | Schedule Options: tambah tab Riwayat (Pending/Disetujui/Ditolak) | Tinggi | Frontend |
| 3 | Schedule Options: perbaiki copy empty state – jelaskan bahwa approved ada di tab Schedule | Tinggi | Frontend |
| 4 | Perbaiki `schedules.value.filter` dan null check di AiringCalendar, DistributionScheduleList | Tinggi | Frontend |
| 5 | Pisahkan komponen Program Musik vs Program Regular (jangan campur) | Tinggi | Frontend |
| 6 | Backend: tambah `pending_count` di response schedule-options | ✅ DONE | Backend |
| 7 | Fix 500 error `/pr/manager-distribusi-qc/works` jika dipakai di Program Musik | Sedang | Backend |
| 8 | UI polish: card, spacing, empty state, loading | Sedang | Frontend |

---

## 🧠 KESIMPULAN SEBAGAI MENTOR

1. **Schedule = Schedule Options (approved)**  
   Schedule menampilkan hasil approve Schedule Options. Alurnya sudah benar.

2. **Schedule Options tampak kosong = Semua sudah diproses**  
   UI hanya menampilkan pending. Perlu tab Riwayat + copy yang jelas.

3. **Weekly Airing Control = Fitur salah tempat**  
   Di backend ini fitur Producer. Untuk Distribution Manager lebih baik dihapus atau diganti dengan filter di tab Schedule.

4. **Error = Campur Program Regular & Program Musik**  
   Komponen Program Regular dipakai di halaman Program Musik. Routing dan service harus dipisah per modul.

5. **UX = Kurang petunjuk**  
   User tidak tahu "setelah approve, kemana datanya". Perlu copy dan alur yang lebih eksplisit.

Lakukan perbaikan bertahap: fix error dulu, pisahkan komponen, lalu perbaiki UX dan design.

---

*Dokumen ini dibuat dari analisis backend dan log frontend yang diberikan. Perlu akses ke repo frontend untuk implementasi lengkap.*
