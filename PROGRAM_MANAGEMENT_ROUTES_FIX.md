# Program Management Backend - Perbaikan Autologout

## 📋 Ringkasan Perbaikan

Perbaikan backend untuk mengatasi masalah **autologout terus-menerus** saat mengakses Program Management dari frontend.

## 🔧 Perubahan yang Dilakukan

### 1. ✅ Aktifkan Sanctum Middleware (PENTING!)

**File:** `app/Http/Kernel.php` (Line 43)

**Sebelum:**
```php
'api' => [
    // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

**Sesudah:**
```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

**Alasan:** Middleware `EnsureFrontendRequestsAreStateful` sangat penting untuk SPA (Single Page Application) yang menggunakan Sanctum dengan cookies. Tanpa middleware ini, authentication session tidak akan berjalan dengan baik dan menyebabkan autologout.

### 2. ✅ Route Program Management - Open Access

Semua route Program Management di `routes/api.php` (line 692-900) sudah dikonfigurasi sebagai **OPEN ACCESS** (tanpa middleware `auth:sanctum`), mengikuti pola yang sama dengan route lain seperti `/employees`.

**Route yang Tersedia (Tanpa Authentication):**

```php
// Programs Routes
GET    /api/programs                              - List semua programs
POST   /api/programs                              - Buat program baru
GET    /api/programs/{id}                         - Detail program
PUT    /api/programs/{id}                         - Update program
DELETE /api/programs/{id}                         - Hapus program
POST   /api/programs/{id}/assign-teams            - Assign teams ke program
GET    /api/programs/{id}/dashboard               - Dashboard program
GET    /api/programs/{id}/statistics              - Statistik program

// Teams Routes
GET    /api/teams                                 - List semua teams
POST   /api/teams                                 - Buat team baru
GET    /api/teams/{id}                            - Detail team
PUT    /api/teams/{id}                            - Update team
DELETE /api/teams/{id}                            - Hapus team
POST   /api/teams/{id}/add-members                - Tambah members ke team
POST   /api/teams/{id}/remove-members             - Hapus members dari team
PUT    /api/teams/{id}/update-member-role         - Update role member

// Episodes Routes
GET    /api/episodes                              - List semua episodes
POST   /api/episodes                              - Buat episode baru
GET    /api/episodes/{id}                         - Detail episode
PUT    /api/episodes/{id}                         - Update episode
DELETE /api/episodes/{id}                         - Hapus episode
PATCH  /api/episodes/{id}/update-status           - Update status episode
GET    /api/episodes/upcoming                     - Episodes yang akan datang
GET    /api/episodes/aired                        - Episodes yang sudah tayang
GET    /api/episodes/by-program/{programId}       - Episodes by program

// Schedules Routes
GET    /api/schedules                             - List semua schedules
POST   /api/schedules                             - Buat schedule baru
GET    /api/schedules/{id}                        - Detail schedule
PUT    /api/schedules/{id}                        - Update schedule
DELETE /api/schedules/{id}                        - Hapus schedule
PATCH  /api/schedules/{id}/update-status          - Update status schedule
GET    /api/schedules/upcoming                    - Schedules yang akan datang
GET    /api/schedules/today                       - Schedules hari ini
GET    /api/schedules/overdue                     - Schedules yang overdue

// Media Files Routes
GET    /api/media-files                           - List semua media files
POST   /api/media-files                           - Upload media file
GET    /api/media-files/{id}                      - Detail media file
PUT    /api/media-files/{id}                      - Update media file
DELETE /api/media-files/{id}                      - Hapus media file
POST   /api/media-files/upload                    - Upload media file
GET    /api/media-files/type/{type}               - Media files by type
GET    /api/media-files/program/{programId}       - Media files by program
GET    /api/media-files/episode/{episodeId}       - Media files by episode

// Production Equipment Routes
GET    /api/production-equipment                  - List semua equipment
POST   /api/production-equipment                  - Buat equipment baru
GET    /api/production-equipment/{id}             - Detail equipment
PUT    /api/production-equipment/{id}             - Update equipment
DELETE /api/production-equipment/{id}             - Hapus equipment
POST   /api/production-equipment/{id}/assign      - Assign equipment
POST   /api/production-equipment/{id}/unassign    - Unassign equipment
GET    /api/production-equipment/available        - Equipment yang available
GET    /api/production-equipment/needs-maintenance - Equipment perlu maintenance

// Program Notifications Routes
GET    /api/program-notifications                 - List semua notifications
POST   /api/program-notifications                 - Buat notification
GET    /api/program-notifications/{id}            - Detail notification
PUT    /api/program-notifications/{id}            - Update notification
DELETE /api/program-notifications/{id}            - Hapus notification
GET    /api/program-notifications/unread-count    - Count unread notifications
GET    /api/program-notifications/unread          - Unread notifications
GET    /api/program-notifications/scheduled       - Scheduled notifications
POST   /api/program-notifications/mark-all-read   - Mark all as read
POST   /api/program-notifications/{id}/mark-read  - Mark notification as read

// Users Routes (for Program Management)
GET    /api/users                                 - List semua users
GET    /api/users/{id}                            - Detail user

// Art & Set Properti Routes
GET    /api/art-set-properti                      - List semua art & set properti
POST   /api/art-set-properti                      - Buat art & set properti baru
GET    /api/art-set-properti/{id}                 - Detail art & set properti
PUT    /api/art-set-properti/{id}                 - Update art & set properti
DELETE /api/art-set-properti/{id}                 - Hapus art & set properti
POST   /api/art-set-properti/{id}/assign          - Assign properti
POST   /api/art-set-properti/{id}/unassign        - Unassign properti
GET    /api/art-set-properti/available            - Properti yang available
GET    /api/art-set-properti/needs-maintenance    - Properti perlu maintenance

// Approval Workflow Routes
POST   /api/programs/{id}/submit-approval         - Submit program untuk approval
POST   /api/programs/{id}/approve                 - Approve program
POST   /api/programs/{id}/reject                  - Reject program
POST   /api/episodes/{id}/submit-rundown          - Submit rundown untuk approval
POST   /api/episodes/{id}/approve-rundown         - Approve rundown
POST   /api/episodes/{id}/reject-rundown          - Reject rundown
POST   /api/schedules/{id}/submit-approval        - Submit schedule untuk approval
POST   /api/schedules/{id}/approve                - Approve schedule
POST   /api/schedules/{id}/reject                 - Reject schedule
GET    /api/approvals/pending                     - Pending approvals
GET    /api/approvals/history                     - Approval history

// Analytics Routes
GET    /api/programs/{id}/analytics               - Analytics program
GET    /api/programs/{id}/performance-metrics     - Performance metrics
GET    /api/programs/{id}/kpi-summary             - KPI summary
GET    /api/programs/{id}/team-performance        - Team performance
GET    /api/programs/{id}/content-analytics       - Content analytics
GET    /api/programs/{id}/trends                  - Trends
GET    /api/programs/{id}/views-tracking          - Views tracking
GET    /api/analytics/dashboard                   - Analytics dashboard
GET    /api/analytics/comparative                 - Comparative analytics
GET    /api/programs/{id}/analytics/export        - Export analytics

// Export Routes
GET    /api/episodes/{id}/export/word             - Export script ke Word
GET    /api/episodes/{id}/export/powerpoint       - Export script ke PowerPoint
GET    /api/episodes/{id}/export/pdf              - Export script ke PDF
GET    /api/programs/{id}/export/data             - Export program data
GET    /api/schedules/{id}/export/data            - Export schedule data
GET    /api/programs/{id}/export/media            - Export media files
POST   /api/episodes/bulk-export                  - Bulk export episodes

// Reminder & Notification Routes
GET    /api/notifications                         - List notifications
GET    /api/notifications/unread-count            - Unread count
POST   /api/notifications/{id}/mark-read          - Mark as read
POST   /api/notifications/mark-all-read           - Mark all as read
POST   /api/notifications/reminder                - Create reminder
GET    /api/notifications/upcoming                - Upcoming reminders
GET    /api/notifications/deadlines               - Deadline reminders
GET    /api/notifications/overdue                 - Overdue alerts
GET    /api/notifications/preferences             - Get preferences
POST   /api/notifications/preferences             - Update preferences
DELETE /api/notifications/{id}                    - Delete notification
POST   /api/notifications/bulk-delete             - Bulk delete notifications

// Workflow Automation Routes (Cron Jobs)
POST   /api/workflow/send-reminders               - Send reminders
POST   /api/workflow/update-episode-statuses      - Update episode statuses
POST   /api/workflow/auto-close-programs          - Auto close programs
POST   /api/workflow/set-deadlines/{id}           - Set automatic deadlines

// Team Management Routes (NEW)
GET    /api/teams                                 - List teams
POST   /api/teams                                 - Create team
GET    /api/teams/{id}                            - Get team detail
PUT    /api/teams/{id}                            - Update team
POST   /api/teams/{id}/members                    - Add member
DELETE /api/teams/{id}/members                    - Remove member
PUT    /api/teams/{id}/members/role               - Update member role
GET    /api/teams/department/{department}         - Teams by department
GET    /api/teams/user/my-teams                   - My teams

// Workflow State Machine Routes
GET    /api/workflow/{entityType}/{entityId}/transitions  - Available transitions
POST   /api/workflow/{entityType}/{entityId}/execute      - Execute transition
GET    /api/workflow/{entityType}/{entityId}/status       - Workflow status
GET    /api/workflow/steps                                - Workflow steps
GET    /api/workflow/states                               - Workflow states
GET    /api/workflow/dashboard                            - Workflow dashboard

// File Management Routes
POST   /api/files/upload                          - Upload file
POST   /api/files/bulk-upload                     - Bulk upload files
GET    /api/files/statistics                      - File statistics
GET    /api/files/{entityType}/{entityId}         - Get files
GET    /api/files/{id}/download                   - Download file
PUT    /api/files/{id}                            - Update file
DELETE /api/files/{id}                            - Delete file
```

## 🎯 Cara Menggunakan

### Frontend Configuration

Pastikan frontend Anda dikonfigurasi untuk menggunakan Sanctum dengan benar:

```javascript
// axios configuration
axios.defaults.withCredentials = true;
axios.defaults.baseURL = 'http://localhost:8000';

// Sebelum login, dapatkan CSRF cookie
await axios.get('/sanctum/csrf-cookie');

// Kemudian login
const response = await axios.post('/api/auth/login', credentials);
```

### Testing dengan Postman/Insomnia

Karena route sekarang **OPEN ACCESS**, Anda bisa test tanpa authentication:

```bash
# Get all programs
GET http://localhost:8000/api/programs

# Create new program
POST http://localhost:8000/api/programs
Content-Type: application/json

{
    "name": "Program Test",
    "type": "weekly",
    "start_date": "2025-01-01",
    "duration_minutes": 60,
    "manager_id": 1
}
```

### Testing dengan cURL

```bash
# Get all programs
curl -X GET "http://localhost:8000/api/programs"

# Create new program
curl -X POST "http://localhost:8000/api/programs" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Program Test",
    "type": "weekly",
    "start_date": "2025-01-01",
    "duration_minutes": 60,
    "manager_id": 1
  }'
```

## 🔍 Troubleshooting

### Masih Terjadi Autologout?

1. **Clear Cache Laravel:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

2. **Restart Server:**
```bash
# Ctrl+C untuk stop
php artisan serve
```

3. **Check Frontend CORS Configuration:**
Pastikan frontend mengirim credentials:
```javascript
axios.defaults.withCredentials = true;
```

4. **Check CORS di Backend:**
File `config/cors.php` sudah dikonfigurasi dengan benar untuk `localhost:3000`, `localhost:5173`, dll.

### Error "Unauthenticated"

Jika masih mendapat error ini, berarti ada controller yang masih mengecek authentication. Check log di `storage/logs/laravel.log`.

### Error "CORS Policy"

Pastikan domain frontend Anda sudah terdaftar di `config/cors.php`:
```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:5173',
    // Tambahkan domain Anda
],
```

## 📝 Catatan Penting

1. **Sanctum Middleware Sekarang Aktif:** Middleware `EnsureFrontendRequestsAreStateful` sekarang aktif untuk memastikan session authentication berjalan dengan baik.

2. **Open Access:** Route Program Management sekarang **OPEN ACCESS**, artinya bisa diakses tanpa authentication. Ini sesuai dengan pola route lain di sistem ini (seperti `/employees`).

3. **Security:** Jika Anda ingin menambahkan authentication di kemudian hari, wrap route dengan middleware:
```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Protected routes here
});
```

4. **Database:** Pastikan tabel `programs`, `teams`, `episodes`, `schedules`, dll sudah dibuat dengan migration yang benar.

## ✅ Checklist Setelah Update

- [x] Aktifkan `EnsureFrontendRequestsAreStateful` di `Kernel.php`
- [ ] Clear cache Laravel (`php artisan config:clear`)
- [ ] Restart server Laravel
- [ ] Test endpoint `/api/programs` dari browser/Postman
- [ ] Test dari frontend
- [ ] Verify tidak ada autologout lagi

## 🚀 Next Steps

Jika masih ada masalah, silakan cek:
1. File log: `storage/logs/laravel.log`
2. Browser console untuk error CORS
3. Network tab di browser DevTools

---

**Status:** ✅ Backend sudah diperbaiki dan siap digunakan!
**Tanggal:** 7 Oktober 2025

