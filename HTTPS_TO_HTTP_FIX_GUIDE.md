# Panduan Mengubah HTTPS ke HTTP - Fix Login Error

## Masalah yang Ditemukan
Server Anda hanya mendukung HTTP, bukan HTTPS. Ini menyebabkan error `Network Error` dan `ERR_CONNECTION_CLOSED` saat login.

## Langkah-langkah Perbaikan

### 1. Jalankan Script Fix Otomatis

```bash
php fix_https_to_http.php
```

Script ini akan:
- Mengubah `APP_URL` di file `.env` dari HTTPS ke HTTP
- Test koneksi HTTP
- Clear Laravel cache
- Memberikan konfigurasi frontend yang benar

### 2. Update File .env Manual

Jika script tidak berhasil, update manual file `.env`:

```env
# Ubah dari:
APP_URL=https://api.hopechannel.id

# Menjadi:
APP_URL=http://api.hopechannel.id
```

### 3. Update Frontend Configuration

Ubah konfigurasi axios di frontend:

```javascript
// Dari:
const api = axios.create({
    baseURL: 'https://api.hopechannel.id/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Menjadi:
const api = axios.create({
    baseURL: 'http://api.hopechannel.id/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});
```

### 4. Clear Laravel Cache

Jalankan command berikut di server:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 5. Test Koneksi HTTP

Test apakah HTTP berfungsi:

```bash
# Test basic connection
curl -I http://api.hopechannel.id/api/auth/login

# Test POST request
curl -X POST http://api.hopechannel.id/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"login":"test@example.com","password":"password123"}'
```

### 6. Clear Browser Cache

- Buka Developer Tools (F12)
- Klik kanan pada tombol refresh
- Pilih "Empty Cache and Hard Reload"

### 7. Test Login

Setelah semua perubahan, test login lagi di frontend.

## Verifikasi Perubahan

### 1. Check .env File
```bash
grep "APP_URL" .env
```
Harus menampilkan: `APP_URL=http://api.hopechannel.id`

### 2. Test API Endpoint
```bash
curl -X POST http://api.hopechannel.id/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"test@example.com","password":"password123"}'
```

Response yang diharapkan:
- `422` untuk invalid credentials (normal)
- `200` untuk valid credentials

### 3. Check Frontend Network Tab
- Buka Developer Tools
- Buka tab Network
- Coba login
- Pastikan request menggunakan `http://` bukan `https://`

## Troubleshooting

### Jika Masih Error

1. **Check Server Logs**
```bash
tail -f storage/logs/laravel.log
```

2. **Test Server Response**
```bash
curl -v http://api.hopechannel.id/api/auth/login
```

3. **Check CORS Configuration**
Pastikan `config/cors.php` sudah benar:
```php
'allowed_origins' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```

4. **Check Route Registration**
```bash
php artisan route:list | grep login
```

### Jika HTTP Tidak Berfungsi

1. **Contact Hosting Provider**
   - Tanyakan apakah HTTP diizinkan
   - Minta enable HTTP jika belum
   - Tanyakan tentang SSL certificate

2. **Alternative Solutions**
   - Gunakan IP address langsung
   - Setup SSL certificate yang valid
   - Gunakan hosting provider lain

## Keamanan

### Untuk Production
Meskipun menggunakan HTTP untuk sementara, pertimbangkan:

1. **Setup SSL Certificate**
   - Let's Encrypt (gratis)
   - SSL certificate dari hosting provider
   - Self-signed certificate (untuk testing)

2. **Security Headers**
```php
// Di AppServiceProvider.php
public function boot()
{
    if (app()->environment('production')) {
        \URL::forceScheme('https');
    }
}
```

3. **HTTPS Redirect**
```php
// Di middleware atau .htaccess
if (!request()->secure() && app()->environment('production')) {
    return redirect()->secure(request()->getRequestUri());
}
```

## Monitoring

Setelah fix, monitor:
- Response time
- Error rates
- SSL certificate status
- Server uptime

## Prevention

Untuk mencegah masalah serupa:
1. Test HTTP dan HTTPS sebelum deployment
2. Setup SSL certificate yang valid
3. Monitor SSL certificate expiration
4. Implement fallback mechanism
5. Regular security audits 