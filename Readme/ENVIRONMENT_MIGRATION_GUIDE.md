# Panduan Migrasi Environment Production ke Localhost

## File .env untuk Localhost

Buat file `.env` baru dengan konfigurasi berikut (berdasarkan environment production Anda):

```env
APP_NAME=BackendHCI
APP_ENV=local
APP_KEY=base64:dwZXdTBz6UWGsPOA19ixdY5jdhWyvaG7CYUnUqTgI64=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database Configuration untuk Localhost (XAMPP)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=backend_hci
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@localhost"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

FONTTE_TOKEN=Tvyzz2H3fxBaLnFA9dr7

# ============================================
# ATTENDANCE MACHINE CONFIGURATION
# ============================================

# Solution X304 Machine Default Settings (untuk localhost)
ATTENDANCE_MACHINE_IP=127.0.0.1
ATTENDANCE_MACHINE_PORT=80
ATTENDANCE_MACHINE_COMM_KEY=0
ATTENDANCE_MACHINE_TIMEOUT=10

# Work Schedule Configuration
ATTENDANCE_WORK_START_TIME=07:30:00
ATTENDANCE_WORK_END_TIME=16:30:00
ATTENDANCE_LUNCH_BREAK_DURATION=60
ATTENDANCE_LATE_TOLERANCE_MINUTES=0

# Sync Settings (dinonaktifkan untuk localhost)
ATTENDANCE_AUTO_SYNC_ENABLED=false
ATTENDANCE_SYNC_INTERVAL_MINUTES=15
ATTENDANCE_PROCESS_INTERVAL_MINUTES=60
ATTENDANCE_DAILY_SUMMARY_TIME=06:00

# Debugging & Logging (diaktifkan untuk development)
ATTENDANCE_DEBUG_MODE=true
ATTENDANCE_LOG_LEVEL=debug
ATTENDANCE_KEEP_LOGS_DAYS=30

# Advanced Settings
ATTENDANCE_DUPLICATE_DETECTION_MINUTES=1
ATTENDANCE_OVERTIME_START_TIME=16:30:00
ATTENDANCE_MIN_WORK_HOURS=8
ATTENDANCE_MAX_DAILY_TAPS=20
ATTENDANCE_AUTO_CREATE_EMPLOYEE=true

# ============================================
# LOCALHOST SPECIFIC CONFIGURATIONS
# ============================================

# CORS Configuration untuk localhost
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:8080,http://127.0.0.1:5173,http://localhost:8000,http://127.0.0.1:8000

# Sanctum Configuration untuk localhost
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:3000,localhost:8080,localhost:5173,localhost:8000
SESSION_DOMAIN=localhost

# Development Settings
DEVELOPMENT_MODE=true
SKIP_CSRF_VERIFICATION=false
ENABLE_DEBUG_BAR=true

# Rate Limiting untuk localhost (lebih permisif)
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=100
RATE_LIMIT_DECAY_MINUTES=1

# File Upload Configuration
MAX_FILE_SIZE=10240
ALLOWED_FILE_TYPES=jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt

# Google Calendar Integration (jika diperlukan)
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT_URI=http://localhost:8000/auth/google/callback

# Zoom Integration (jika diperlukan)
ZOOM_API_KEY=
ZOOM_API_SECRET=
ZOOM_WEBHOOK_SECRET=
```

## Perubahan Utama dari Production ke Localhost:

### 1. Environment & Debug
- `APP_ENV=local` (dari production)
- `APP_DEBUG=true` (dari false)
- `APP_URL=http://localhost:8000` (dari https://api.hopemedia.id)

### 2. Database Configuration
- `DB_HOST=127.0.0.1` (dari localhost)
- `DB_DATABASE=backend_hci` (dari u858985646_hci)
- `DB_USERNAME=root` (dari u858985646_hci)
- `DB_PASSWORD=` (kosong untuk XAMPP)

### 3. Attendance Machine
- `ATTENDANCE_MACHINE_IP=127.0.0.1` (dari 10.10.10.85)
- `ATTENDANCE_AUTO_SYNC_ENABLED=false` (dari true)
- `ATTENDANCE_DEBUG_MODE=true` (dari false)

### 4. CORS & Sanctum
- Ditambahkan konfigurasi untuk localhost ports
- Allowed origins untuk development

## Langkah Setup:

1. **Backup file .env production** (jika diperlukan)
2. **Buat file .env baru** dengan konfigurasi di atas
3. **Setup database lokal**:
   ```sql
   CREATE DATABASE backend_hci;
   ```
4. **Jalankan perintah setup**:
   ```bash
   composer install
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```
5. **Jalankan server**:
   ```bash
   php artisan serve
   ```

## Testing Endpoints:

Setelah setup, test endpoint berikut:
- `GET http://localhost:8000/api/employees`
- `GET http://localhost:8000/api/attendance/dashboard`
- `POST http://localhost:8000/api/auth/login`

## Troubleshooting:

1. **Database Error**: Pastikan MySQL XAMPP running dan database `backend_hci` sudah dibuat
2. **CORS Error**: Pastikan frontend menggunakan URL yang ada di `CORS_ALLOWED_ORIGINS`
3. **Permission Error**: Jalankan `chmod -R 755 storage bootstrap/cache`
4. **Class Not Found**: Jalankan `composer dump-autoload`

## Catatan Penting:

- **APP_KEY** tetap sama dengan production untuk kompatibilitas
- **FONTTE_TOKEN** tetap sama untuk integrasi yang ada
- **Attendance machine** dinonaktifkan untuk localhost
- **Debug mode** diaktifkan untuk development
- **Rate limiting** dibuat lebih permisif untuk testing
