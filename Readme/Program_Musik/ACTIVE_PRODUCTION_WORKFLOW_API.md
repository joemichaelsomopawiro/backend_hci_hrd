# API Aktif Production – Workflow Lengkap Program Musik

Flow tampilan **mulai dari Music Arranger**; step "Program Aktif" (kredit program) ditampilkan di akhir. Setiap tahap: selesai = centang; belum selesai = bisa diklik untuk lihat alasan (`reason_if_not_completed`).

## Endpoint

```
GET /api/live-tv/manager-program/episodes/{episodeId}/monitor-workflow
```

(Dengan auth: Manager Program / Distribution Manager.)

## Response: `data.workflow_steps` & UI hint

- **`workflow_steps`**: Object yang key-nya step key (urutan pakai `workflow_order`).
- **`workflow_order`**: Array urutan step untuk tampilan (Aktif Production).
- **`data.ui_hint`**:
  - `aktif_production_collapsible`: `true` → section Aktif Production bisa dibuat collapsible.
  - `aktif_production_default_open`: `false` → **default ditutup**; user membuka sendiri kalau mau lihat.
  - `reason_clickable`: `true` → step yang belum selesai bisa diklik untuk tampilkan alasan.

## Bentuk tiap step

Setiap step di `workflow_steps` punya:

| Field | Tipe | Keterangan |
|-------|------|------------|
| `step_key` | string | Kunci step (untuk ordering & styling). |
| `step_name` | string | Label tampilan (contoh: "Music Arranger", "Producer (approve Music Arranger)"). |
| `completed` | boolean | `true` = tahap selesai (bisa centang). |
| `status` | string | Status teknis: `completed`, `pending`, `in_progress`, `rejected`, dll. |
| `reason_if_not_completed` | string \| null | **Alasan belum selesai / ditolak** — tampilkan saat step diklik (hanya ada kalau belum selesai). |
| `data` | object \| null | Data tambahan (id, status, rejection_reason, dll). |
| `deadline` | object \| null | Opsional; object deadline jika ada. |

## Urutan step (workflow_order) — flow mulai dari Music Arranger

1. Music Arranger  
2. Producer (approve Music Arranger)  
3. Creative  
4. Producer (approve Creative)  
5. Sound Engineer  
6. Tim Setting  
7. Tim Shooting  
8. Art Set / Property  
9. Promotion  
10. Design Grafis  
11. Editor Promosi  
12. Editing  
13. Quality Control  
14. Distribution Manager  
15. Broadcasting  
16. Program Manager  
17. Program Aktif (kredit program, ditampilkan terakhir)

## Cara pakai di frontend

1. **Section Aktif Production**
   - Buat section **collapsible**.
   - **Default: tertutup** (`aktif_production_default_open: false`).
   - User bisa expand untuk lihat daftar workflow.

2. **Daftar step**
   - Render sesuai **`workflow_order`**.
   - Untuk setiap step:
     - **Selesai** (`completed === true`): tampilkan centang (✓).
     - **Belum selesai**: jangan centang; tampilkan ikon/teks “belum selesai” dan **buat bisa diklik**.

3. **Saat step belum selesai diklik**
   - Tampilkan **`reason_if_not_completed`** (modal, tooltip, atau panel).
   - Contoh: "Ditolak oleh Producer: ...", "Belum sampai tahap Music Arranger.", "Menunggu approval QC."

Dengan ini, user bisa lihat semua tahap Program Musik di Aktif Production, mana yang sudah centang, dan kalau belum sampai/ditolak bisa klik untuk lihat alasannya.
