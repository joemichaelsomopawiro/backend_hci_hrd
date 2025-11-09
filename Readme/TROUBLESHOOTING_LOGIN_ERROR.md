# Troubleshooting Login Error - Network Error & Connection Closed

## Masalah yang Dialami
```
Login error details: Object
config: {url: '/auth/login', method: 'post', baseURL: 'https://api.hopechannel.id/api'}
data: undefined
message: "Network Error"
status: undefined
statusText: undefined

Failed to load resource: net::ERR_CONNECTION_CLOSED
```

## Langkah-langkah Troubleshooting

### 1. Jalankan Script Testing

Jalankan script berikut untuk mendiagnosis masalah:

```bash
# Test koneksi API
php test_api_connection.php

# Check log Laravel
php check_laravel_logs.php

# Check konfigurasi hosting
php check_hosting_config.php
```

### 2. Periksa Konfigurasi Frontend

Pastikan konfigurasi axios di frontend sudah benar:

```javascript
// Contoh konfigurasi yang benar
const api = axios.create({
    baseURL: 'https://api.hopechannel.id/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});
```

### 3. Periksa Konfigurasi Backend

#### A. File .env
Pastikan konfigurasi di file `.env` sudah benar:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.hopechannel.id

DB_CONNECTION=mysql
DB_HOST=your_database_host
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

#### B. CORS Configuration
Pastikan file `config/cors.php` sudah benar:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => false,
```

### 4. Periksa Server Configuration

#### A. .htaccess File
Pastikan file `public/.htaccess` ada dan berisi:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

#### B. SSL Certificate
Pastikan SSL certificate sudah terpasang dengan benar di hosting.

### 5. Periksa Database Connection

Pastikan database dapat diakses dari server hosting:

```bash
# Test database connection
php artisan tinker
DB::connection()->getPdo();
```

### 6. Periksa Laravel Logs

Cek file log untuk error detail:

```bash
# Cek log terbaru
tail -f storage/logs/laravel.log
```

### 7. Periksa Permissions

Pastikan folder memiliki permission yang benar:

```bash
# Set permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### 8. Clear Laravel Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 9. Periksa Route

Pastikan route login sudah terdaftar:

```bash
php artisan route:list | grep login
```

### 10. Test API Endpoint Manual

Test endpoint menggunakan curl:

```bash
curl -X POST https://api.hopechannel.id/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"login":"test@example.com","password":"password123"}'
```

## Kemungkinan Penyebab

### 1. Server Issues
- Server down atau maintenance
- Firewall blocking requests
- SSL certificate expired
- DNS resolution issues

### 2. Configuration Issues
- Wrong base URL in frontend
- CORS not properly configured
- Database connection issues
- Laravel not properly configured

### 3. Network Issues
- Internet connection problems
- CDN or proxy issues
- DNS propagation delays

### 4. Hosting Issues
- Shared hosting limitations
- PHP version incompatibility
- Missing PHP extensions
- File permission issues

## Solusi yang Disarankan

### 1. Immediate Actions
1. Restart web server
2. Clear browser cache
3. Check if server is accessible via browser
4. Verify SSL certificate

### 2. Configuration Fixes
1. Update .env file with correct settings
2. Clear Laravel cache
3. Check database connection
4. Verify route registration

### 3. Hosting Provider Contact
Jika masalah berlanjut, hubungi hosting provider untuk:
- Check server status
- Verify SSL configuration
- Check firewall settings
- Confirm PHP extensions

### 4. Alternative Solutions
1. Use HTTP instead of HTTPS temporarily
2. Change base URL to IP address
3. Use different hosting provider
4. Implement fallback mechanism

## Monitoring

Setelah memperbaiki, monitor:
- Response time
- Error rates
- Server logs
- Database performance

## Prevention

Untuk mencegah masalah serupa:
1. Regular server monitoring
2. SSL certificate auto-renewal
3. Database backup
4. Error logging and alerting
5. Load testing before deployment 