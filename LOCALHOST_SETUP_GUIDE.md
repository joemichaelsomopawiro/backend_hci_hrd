# Panduan Setup Backend untuk Localhost

## 1. File .env yang Diperlukan

Buat file `.env` di root directory dengan konfigurasi berikut:

```env
APP_NAME="Backend HCI"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database Configuration untuk XAMPP
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=backend_hci
DB_USERNAME=root
DB_PASSWORD=

# Cache & Session
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Redis (opsional untuk localhost)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration (untuk testing)
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@localhost"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:3000,localhost:8080,localhost:5173
SESSION_DOMAIN=localhost

# CORS Configuration
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:8080,http://127.0.0.1:5173

# Google Calendar Integration (jika diperlukan)
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT_URI=http://localhost:8000/auth/google/callback

# Zoom Integration (jika diperlukan)
ZOOM_API_KEY=
ZOOM_API_SECRET=
ZOOM_WEBHOOK_SECRET=

# File Upload Configuration
MAX_FILE_SIZE=10240
ALLOWED_FILE_TYPES=jpg,jpeg,png,pdf,doc,docx,xls,xlsx,txt

# Rate Limiting untuk localhost
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=60
RATE_LIMIT_DECAY_MINUTES=1

# Development Settings
DEVELOPMENT_MODE=true
SKIP_CSRF_VERIFICATION=false
ENABLE_DEBUG_BAR=true
```

## 2. Langkah-langkah Setup

### A. Persiapan Database
1. Buka XAMPP Control Panel
2. Start Apache dan MySQL
3. Buka phpMyAdmin (http://localhost/phpmyadmin)
4. Buat database baru dengan nama `backend_hci`

### B. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (jika ada frontend assets)
npm install
```

### C. Setup Laravel
```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Run seeders (jika ada)
php artisan db:seed

# Create storage link
php artisan storage:link

# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### D. Jalankan Server
```bash
# Jalankan Laravel development server
php artisan serve

# Server akan berjalan di http://localhost:8000
```

## 3. Konfigurasi Tambahan yang Sudah Diperbaiki

### A. File config/app.php
- Environment diubah ke 'local'
- Debug mode diaktifkan
- URL diubah ke localhost:8000

### B. File config/cors.php
- Allowed origins sudah dikonfigurasi untuk localhost
- Mendukung port 3000, 8080, 5173, dan 8000

### C. File config/database.php
- Sudah dikonfigurasi untuk MySQL
- Host: 127.0.0.1
- Port: 3306
- Username: root (default XAMPP)

## 4. Testing API Endpoints

Setelah server berjalan, Anda bisa test endpoint berikut:

### Authentication
```
POST http://localhost:8000/api/auth/register
POST http://localhost:8000/api/auth/login
GET  http://localhost:8000/api/auth/me
```

### Employees
```
GET  http://localhost:8000/api/employees
POST http://localhost:8000/api/employees
```

### Attendance
```
GET  http://localhost:8000/api/attendance/dashboard
GET  http://localhost:8000/api/attendance/list
```

## 5. Troubleshooting

### Masalah Umum:
1. **Database connection error**: Pastikan MySQL di XAMPP sudah running
2. **Permission denied**: Jalankan `chmod -R 755 storage bootstrap/cache`
3. **Class not found**: Jalankan `composer dump-autoload`
4. **CORS error**: Pastikan frontend menggunakan URL yang ada di allowed_origins

### Log Files:
- Application logs: `storage/logs/laravel.log`
- Error logs: Check browser console dan Laravel logs

## 6. Environment Variables yang Penting

Pastikan file `.env` memiliki variabel berikut:
- `APP_KEY`: Generate dengan `php artisan key:generate`
- `DB_DATABASE`: Nama database yang sudah dibuat
- `DB_USERNAME` dan `DB_PASSWORD`: Sesuaikan dengan konfigurasi MySQL
- `APP_URL`: Harus sesuai dengan URL yang digunakan

## 7. Development Tips

1. **Enable Debug Mode**: Pastikan `APP_DEBUG=true`
2. **Check Logs**: Monitor `storage/logs/laravel.log`
3. **Clear Cache**: Gunakan `php artisan optimize:clear`
4. **Database Seeding**: Jalankan `php artisan db:seed` untuk data dummy

## 8. Production vs Development

Untuk development, pastikan:
- `APP_ENV=local`
- `APP_DEBUG=true`
- `LOG_LEVEL=debug`
- Database menggunakan local MySQL

Untuk production, ubah ke:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `LOG_LEVEL=error`
