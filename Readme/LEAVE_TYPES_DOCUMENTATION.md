# Dokumentasi Jenis Cuti - Backend HCI

## Jenis Cuti yang Tersedia

Sistem cuti kini mendukung 7 jenis cuti dengan quota masing-masing:

### 1. Cuti Tahunan (annual)
- **Quota**: 12 hari per tahun
- **Deskripsi**: Cuti tahunan untuk istirahat dan refreshing
- **Validasi**: Memerlukan pengecekan quota

### 2. Cuti Sakit (sick)
- **Quota**: 12 hari per tahun
- **Deskripsi**: Cuti untuk keperluan kesehatan
- **Validasi**: Unlimited (tidak dibatasi quota dalam controller)

### 3. Cuti Darurat (emergency)
- **Quota**: 2 hari per tahun
- **Deskripsi**: Cuti untuk keperluan mendesak
- **Validasi**: Memerlukan pengecekan quota

### 4. Cuti Melahirkan (maternity)
- **Quota**: 90 hari per tahun (3 bulan)
- **Deskripsi**: Cuti untuk ibu yang melahirkan
- **Validasi**: Memerlukan pengecekan quota

### 5. Cuti Ayah (paternity)
- **Quota**: 7 hari per tahun (1 minggu)
- **Deskripsi**: Cuti untuk ayah yang istrinya melahirkan
- **Validasi**: Memerlukan pengecekan quota

### 6. Cuti Menikah (marriage)
- **Quota**: 3 hari per tahun
- **Deskripsi**: Cuti untuk keperluan pernikahan
- **Validasi**: Memerlukan pengecekan quota

### 7. Cuti Duka (bereavement)
- **Quota**: 3 hari per tahun
- **Deskripsi**: Cuti untuk keperluan duka cita
- **Validasi**: Memerlukan pengecekan quota

## API Endpoints

### POST /api/leave-requests
Mengajukan permohonan cuti dengan validasi jenis cuti:

```json
{
  "leave_type": "annual|sick|emergency|maternity|paternity|marriage|bereavement",
  "start_date": "2025-01-25",
  "end_date": "2025-01-27",
  "reason": "Alasan pengajuan cuti"
}
```

### GET /api/leave-requests
Melihat daftar permohonan cuti (dengan filter berdasarkan role)

### PUT /api/leave-requests/{id}/approve
Menyetujui permohonan cuti (otomatis mengurangi quota)

### PUT /api/leave-requests/{id}/reject
Menolak permohonan cuti

### GET /api/leave-requests/approved
Melihat daftar cuti yang sudah disetujui (khusus HR)

## Database Schema

### Tabel: leave_requests
- `leave_type`: enum dengan 7 pilihan jenis cuti

### Tabel: leave_quotas
- Setiap jenis cuti memiliki 2 kolom:
  - `{type}_leave_quota`: Jatah cuti
  - `{type}_leave_used`: Cuti yang sudah digunakan

## Implementasi

### Model Employee
- Method `canTakeLeave()`: Mengecek apakah employee bisa mengambil cuti
- Method `updateLeaveQuota()`: Mengupdate quota saat cuti disetujui

### Model LeaveRequest
- Method `updateLeaveQuota()`: Mengupdate quota otomatis saat status berubah menjadi approved

### Controller LeaveRequestController
- Validasi jenis cuti di method `store()`
- Pengecekan quota untuk semua jenis cuti kecuali sick
- Logika approval dengan update quota otomatis

## Migration & Seeder

1. **Migration**: `2025_06_20_041805_add_new_leave_types_columns_to_leave_quotas_table.php`
   - Menambahkan kolom quota untuk 4 jenis cuti baru

2. **Seeder**: `UpdateLeaveQuotasSeeder.php`
   - Mengupdate data employee yang sudah ada dengan quota jenis cuti baru

## Testing

Untuk testing, gunakan endpoint POST /api/leave-requests dengan berbagai jenis cuti:

```bash
# Test cuti tahunan
curl -X POST /api/leave-requests \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "leave_type": "annual",
    "start_date": "2025-01-25",
    "end_date": "2025-01-27",
    "reason": "Liburan keluarga"
  }'

# Test cuti melahirkan
curl -X POST /api/leave-requests \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "leave_type": "maternity",
    "start_date": "2025-02-01",
    "end_date": "2025-04-30",
    "reason": "Cuti melahirkan"
  }'
```

## Status Implementasi

✅ Database migration untuk jenis cuti baru  
✅ Model Employee dengan support semua jenis cuti  
✅ Model LeaveRequest dengan update quota otomatis  
✅ Controller dengan validasi lengkap  
✅ API routes yang sudah disesuaikan  
✅ Seeder untuk update data existing  

Semua jenis cuti telah terintegrasi dengan sistem approval berbasis role hierarchy yang sudah ada.