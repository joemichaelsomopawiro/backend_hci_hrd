# Flow Setelah Produksi, Sound Engineer, dan Promosi Selesai

## ✅ STATUS: **LENGKAP - SEMUA LANJUTAN FLOW SUDAH ADA**

Dokumentasi ini menjelaskan lanjutan flow setelah Produksi, Sound Engineer, dan Promosi menyelesaikan pekerjaan mereka.

---

## 🔄 WORKFLOW LENGKAP

```
Producer Approve Creative Work
    ↓
Auto-Create:
    ├─► BudgetRequest → General Affairs ✅ (END - tidak ada lanjutan)
    ├─► PromotionWork → Promosi ✅
    ├─► ProduksiWork → Produksi ✅
    └─► SoundEngineerRecording → Sound Engineer ✅

SETELAH SELESAI:
    ├─► Produksi Complete → Editor ✅
    ├─► Sound Engineer Complete → Sound Engineer Editing → Producer → Editor ✅
    └─► Promosi Complete → Editor Promotion → QC → Broadcasting ✅
```

---

## 1. 📹 PRODUKSI → EDITOR

### **Flow Produksi:**
1. ✅ Terima Notifikasi
2. ✅ Terima Pekerjaan
3. ✅ Input list alat (Ajukan ke Art & Set Properti)
4. ✅ Ajukan kebutuhan
5. ✅ **Selesai Pekerjaan**
    - Upload hasil syuting (file video/foto dari shooting)
    - Status: `completed`
    - **→ Auto-notify Editor** ✅

### **Lanjutan ke Editor:**

#### **1.1. Editor Terima Notifikasi**
**Dipicu oleh:** Produksi upload hasil syuting & complete work  
**Notification Type:** `produksi_shooting_completed`

**Auto-notify di:** `ProduksiController::uploadShootingFiles()` (Line 678-691)

**Kode:**
```php
// Notify Editor
$editorUsers = \App\Models\User::where('role', 'Editor')->get();
foreach ($editorUsers as $editorUser) {
    Notification::create([
        'user_id' => $editorUser->id,
        'type' => 'produksi_shooting_completed',
        'title' => 'Hasil Syuting Tersedia',
        'message' => "Produksi telah mengupload hasil syuting untuk Episode {$work->episode->episode_number}. Siap untuk editing.",
        'data' => [
            'produksi_work_id' => $work->id,
            'episode_id' => $work->episode_id,
        ]
    ]);
}
```

**Hasil:**
- ✅ Editor di-notify bahwa hasil syuting tersedia
- ✅ Editor bisa mulai edit video

---

#### **1.2. Editor Workflow:**
1. **Terima Notifikasi** - Hasil syuting tersedia
2. **Terima Pekerjaan** - Accept work
3. **Edit Video** - Edit hasil syuting (gabung dengan audio dari Sound Engineer)
4. **Submit ke Producer** - Submit editor work untuk review
5. **Producer Review & Approve** - Producer approve editor work
6. **→ Auto-notify QC** - Quality Control siap untuk QC final

**Kode Auto-notify QC:** `ProducerController::approveItem()` (Line 1041-1044)

```php
// Notify Quality Control that editing is ready for QC
$qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
foreach ($qcUsers as $qcUser) {
    Notification::create([
        'user_id' => $qcUser->id,
        'type' => 'editor_work_approved',
        'title' => 'Editor Work Ready for QC',
        'message' => "Editor work untuk Episode {$item->episode->episode_number} telah disetujui. Siap untuk QC.",
        // ...
    ]);
}
```

---

## 2. 🎤 SOUND ENGINEER → SOUND ENGINEER EDITING → PRODUCER → EDITOR

### **Flow Sound Engineer:**
1. ✅ Terima Notifikasi
2. ✅ Terima Jadwal Rekaman Vocal
3. ✅ Terima pekerjaan
4. ✅ Input list Alat (ajukan ke art & set properti)
5. ✅ **Selesai Pekerjaan** (input equipment)
6. ✅ **Complete Recording** (upload file recording)
    - Status: `completed`
    - **→ Auto-create SoundEngineerEditing task** ✅
    - **→ Notify Producer** ✅

### **Lanjutan ke Sound Engineer Editing:**

#### **2.1. Sound Engineer Editing (Auto-Create)**
**Dipicu oleh:** Sound Engineer complete recording  
**Model:** `SoundEngineerEditing`  
**Auto-create di:** `SoundEngineerController::completeRecording()` (Line 429-443)

**Kode:**
```php
// Auto-create Sound Engineer Editing task
$editing = \App\Models\SoundEngineerEditing::create([
    'episode_id' => $recording->episode_id,
    'sound_engineer_recording_id' => $recording->id,
    'sound_engineer_id' => $user->id,
    'vocal_file_path' => $recording->file_path, // Copy recording file path
    'editing_notes' => "Editing task created automatically from completed recording...",
    'status' => 'in_progress',
    'created_by' => $user->id
]);
```

**Hasil:**
- ✅ SoundEngineerEditing task dibuat otomatis
- ✅ Producer di-notify untuk review recording
- ✅ Sound Engineer bisa lanjut edit audio

---

#### **2.2. Producer Approve Sound Engineer Editing**
**Dipicu oleh:** Producer approve editing work  
**Notification Type:** `audio_ready_for_editing`

**Auto-notify di:** `ProducerController::approveItem()` (Line 958-993)

**Kode:**
```php
// Notify Editor that audio is ready
foreach ($editors as $editorId) {
    Notification::create([
        'user_id' => $editorId,
        'type' => 'audio_ready_for_editing',
        'title' => 'Audio Ready for Video Editing',
        'message' => "Final audio file is ready for episode {$episode->episode_number}. You can now start video editing.",
        'data' => [
            'editing_id' => $item->id,
            'episode_id' => $episode->id,
            'audio_file_path' => $item->final_file_path
        ]
    ]);
}
```

**Hasil:**
- ✅ Editor di-notify bahwa audio final sudah ready
- ✅ Editor bisa mulai edit video (gabung dengan audio)

---

#### **2.3. Editor Workflow (Lanjutan):**
1. **Terima Notifikasi** - Audio ready untuk video editing
2. **Edit Video** - Gabung video dari Produksi + audio dari Sound Engineer
3. **Submit ke Producer** - Submit editor work untuk review
4. **Producer Review & Approve** - Producer approve editor work
5. **→ Auto-notify QC** - Quality Control siap untuk QC final

**Sama seperti flow Produksi → Editor**

---

## 3. 📢 PROMOSI → EDITOR PROMOTION → QC → BROADCASTING

### **Flow Promosi:**
1. ✅ Terima Notifikasi
2. ✅ Terima Jadwal Syuting
3. ✅ Terima Pekerjaan
4. ✅ Buat Video BTS
5. ✅ Buat Foto Talent
6. ✅ Upload file ke storage
7. ✅ Input alamat file ke sistem
8. ✅ **Selesai Pekerjaan**
    - Status: `editing`
    - **→ Notify Producer** ✅
    - **→ Lanjut ke Editor Promotion** ✅

### **Lanjutan ke Editor Promotion:**

#### **3.1. Editor Promotion Workflow:**
1. **Terima Pekerjaan** - Edit BTS video dan talent photos dari Promosi
2. **Edit Konten Promosi** - Edit video BTS, edit foto talent
3. **Submit ke QC** - Submit hasil editing ke Quality Control
    - **→ Auto-create QualityControlWork** ✅
    - **→ Notify QC** ✅

**Kode Auto-create:** `EditorPromosiController::submitToQC()` (Line 588-607)

```php
// Create or update QualityControlWork
$qcWork = \App\Models\QualityControlWork::updateOrCreate(
    [
        'episode_id' => $work->episode_id,
        'qc_type' => 'main_episode'
    ],
    [
        'title' => "QC Work - Episode {$work->episode->episode_number}",
        'description' => "File dari Editor Promosi untuk QC",
        'editor_promosi_file_locations' => [...],
        'status' => 'pending',
        'created_by' => $user->id
    ]
);

// Notify Quality Control
$qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
foreach ($qcUsers as $qcUser) {
    Notification::create([...]);
}
```

---

#### **3.2. Quality Control Workflow:**
1. **Terima Notifikasi** - File dari Editor Promosi ready untuk QC
2. **Terima Pekerjaan** - Accept work
3. **Start QC** - Mulai proses QC
4. **QC Content** - QC video, foto, konten promosi
5. **Approve/Reject** - Approve atau reject hasil QC

**Jika Approved:**
- **→ Auto-create BroadcastingWork** ✅
- **→ Notify Broadcasting** ✅

**Kode Auto-create:** `QualityControlController::finalize()` (Line 785-797)

```php
// Auto-create BroadcastingWork
$broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->get();
if ($broadcastingUsers->isNotEmpty()) {
    $broadcastingWork = \App\Models\BroadcastingWork::create([
        'episode_id' => $work->episode_id,
        'work_type' => 'main_episode',
        'title' => "Broadcasting Work - Episode {$work->episode->episode_number}",
        'description' => "File materi dari QC yang telah disetujui",
        'video_file_path' => $work->files_to_check[0]['file_path'] ?? null,
        'status' => 'pending',
        'created_by' => $broadcastingUsers->first()->id
    ]);

    // Notify Broadcasting
    foreach ($broadcastingUsers as $broadcastingUser) {
        Notification::create([...]);
    }
}
```

---

#### **3.3. Broadcasting Workflow:**
1. **Terima Notifikasi** - File dari QC ready untuk broadcasting
2. **Terima Pekerjaan** - Accept work
3. **Upload ke YouTube** - Upload video ke YouTube dengan SEO
4. **Upload ke Website** - Upload video ke website
5. **Submit Schedule Options** - Ajukan opsi jadwal tayang
6. **→ Manager Broadcasting Review** - Review & approve/reject schedule
7. **Publish Schedule** - Publish jadwal tayang

---

## 📋 RINGKASAN FLOW LENGKAP

| Role Awal | Complete | Lanjutan ke | Auto-Create | Auto-Notify |
|-----------|----------|-------------|-------------|-------------|
| **Produksi** | Upload hasil syuting | **Editor** | ❌ | ✅ Editor |
| **Sound Engineer** | Complete recording | **Sound Engineer Editing** | ✅ SoundEngineerEditing | ✅ Producer |
| **Sound Engineer Editing** | Approved by Producer | **Editor** | ❌ | ✅ Editor (audio ready) |
| **Promosi** | Complete work | **Editor Promotion** | ❌ | ✅ Producer |
| **Editor Promotion** | Submit to QC | **Quality Control** | ✅ QualityControlWork | ✅ QC |
| **Quality Control** | Approve QC | **Broadcasting** | ✅ BroadcastingWork | ✅ Broadcasting |
| **General Affairs** | Process payment | **END** | ❌ | ✅ Producer (fund released) |

---

## 🔄 CONVERGENCE POINT: EDITOR

### **Editor Menerima Dari 2 Sumber:**

1. **Dari Produksi:**
   - Video hasil syuting
   - Notifikasi: `produksi_shooting_completed`

2. **Dari Sound Engineer Editing:**
   - Audio final yang sudah di-edit
   - Notifikasi: `audio_ready_for_editing`

### **Editor Workflow Lengkap:**
1. Terima notifikasi dari Produksi (video ready)
2. Terima notifikasi dari Sound Engineer (audio ready)
3. Accept work
4. Edit video (gabung video + audio)
5. Submit editor work ke Producer
6. Producer approve
7. → **Auto-notify QC** (sama seperti flow Editor Promotion → QC)

---

## 📋 ENDPOINT YANG TERSEDIA

### **Editor:**
- `GET /api/live-tv/editor/works` - Get editor works
- `POST /api/live-tv/editor/works/{id}/accept-work` - Accept work
- `POST /api/live-tv/editor/works/{id}/submit` - Submit editor work ke Producer

### **Sound Engineer Editing:**
- `GET /api/live-tv/sound-engineer/editings` - Get editing tasks
- `POST /api/live-tv/sound-engineer/editings/{id}/submit` - Submit editing ke Producer

### **Editor Promotion:**
- `GET /api/live-tv/editor-promosi/works` - Get promotion editing works
- `POST /api/live-tv/editor-promosi/works/{id}/accept-work` - Accept work
- `POST /api/live-tv/editor-promosi/works/{id}/submit-to-qc` - Submit ke QC

### **Quality Control:**
- `GET /api/live-tv/quality-control/works` - Get QC works
- `POST /api/live-tv/quality-control/works/{id}/accept-work` - Accept work
- `POST /api/live-tv/quality-control/works/{id}/qc-content` - QC content
- `POST /api/live-tv/quality-control/works/{id}/finalize` - Approve/reject (auto-create BroadcastingWork)

### **Broadcasting:**
- `GET /api/live-tv/broadcasting/works` - Get broadcasting works
- `POST /api/live-tv/broadcasting/works/{id}/accept-work` - Accept work
- `POST /api/live-tv/broadcasting/works/{id}/upload-youtube` - Upload ke YouTube
- `POST /api/live-tv/broadcasting/works/{id}/upload-website` - Upload ke website
- `POST /api/live-tv/broadcasting/works/{id}/submit-schedule` - Submit schedule options

---

## ✅ STATUS IMPLEMENTASI

| Flow | Status | Auto-Create | Auto-Notify | Controller |
|------|--------|-------------|-------------|------------|
| **Produksi → Editor** | ✅ Ready | ❌ | ✅ | ProduksiController, EditorController |
| **Sound Engineer → Editing → Editor** | ✅ Ready | ✅ | ✅ | SoundEngineerController, SoundEngineerEditingController, ProducerController |
| **Promosi → Editor Promotion → QC → Broadcasting** | ✅ Ready | ✅ | ✅ | PromosiController, EditorPromosiController, QualityControlController, BroadcastingController |

---

## 🎯 KESIMPULAN

### **Yang Sudah Lengkap:**
1. ✅ **Produksi** → Notify Editor (video hasil syuting ready)
2. ✅ **Sound Engineer** → Auto-create Editing → Producer approve → Notify Editor (audio ready)
3. ✅ **Promosi** → Editor Promotion → Submit to QC → QC approve → Auto-create BroadcastingWork
4. ✅ **Editor** → Gabung video + audio → Submit ke Producer → Producer approve → Notify QC
5. ✅ **QC** → Approve → Auto-create BroadcastingWork → Notify Broadcasting
6. ✅ **Broadcasting** → Upload YouTube/Website → Submit schedule → Manager Broadcasting approve

**Semua lanjutan flow sudah terimplementasikan dengan lengkap!**

---

**Last Updated:** 2026-01-27
