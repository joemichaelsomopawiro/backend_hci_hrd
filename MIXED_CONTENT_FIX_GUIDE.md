# Panduan Mengatasi Mixed Content Error

## Masalah yang Dialami
```
Mixed Content: The page at 'https://work.hopechannel.id/login' was loaded over HTTPS, 
but requested an insecure XMLHttpRequest endpoint 'http://api.hopechannel.id/api/auth/login'. 
This request has been blocked; the content must be served over HTTPS.
```

## Penyebab Masalah
Browser memblokir request HTTP dari halaman HTTPS karena alasan keamanan. Ini disebut **Mixed Content Error**.

## Solusi yang Tersedia

### Solusi 1: Setup SSL Certificate untuk API (Recommended)

#### A. Jalankan Script Setup SSL
```bash
php setup_ssl_for_api.php
```

#### B. Contact Hosting Provider
Hubungi hosting provider Anda untuk:
- Enable SSL certificate untuk `api.hopechannel.id`
- Setup Let's Encrypt (gratis)
- Konfigurasi SSL certificate

#### C. Update Frontend Configuration
Setelah SSL aktif, update frontend:
```javascript
const api = axios.create({
    baseURL: 'https://api.hopechannel.id/api',  // HTTPS
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});
```

### Solusi 2: Ubah Frontend ke HTTP (Quick Fix)

#### A. Update Frontend URL
Ubah frontend dari HTTPS ke HTTP:
```
Dari: https://work.hopechannel.id
Ke:   http://work.hopechannel.id
```

#### B. Update API Configuration
```javascript
const api = axios.create({
    baseURL: 'http://api.hopechannel.id/api',  // HTTP
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});
```

#### C. Update Environment Variables
Jika menggunakan environment variables:
```env
# Vite
VITE_API_URL=http://api.hopechannel.id/api

# React
REACT_APP_API_URL=http://api.hopechannel.id/api

# Nuxt
NUXT_PUBLIC_API_URL=http://api.hopechannel.id/api
```

### Solusi 3: Setup Proxy (Alternative)

#### A. Nginx Proxy Configuration
```nginx
server {
    listen 443 ssl;
    server_name work.hopechannel.id;
    
    # SSL configuration
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    # Frontend
    location / {
        root /var/www/frontend;
        try_files $uri $uri/ /index.html;
    }
    
    # API Proxy
    location /api/ {
        proxy_pass http://api.hopechannel.id;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### B. Apache Proxy Configuration
```apache
<VirtualHost *:443>
    ServerName work.hopechannel.id
    
    # SSL configuration
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    
    # Frontend
    DocumentRoot /var/www/frontend
    
    # API Proxy
    ProxyPreserveHost On
    ProxyPass /api/ http://api.hopechannel.id/api/
    ProxyPassReverse /api/ http://api.hopechannel.id/api/
</VirtualHost>
```

## Langkah-langkah Implementasi

### Untuk Solusi 1 (SSL Setup)

1. **Jalankan script setup**:
   ```bash
   php setup_ssl_for_api.php
   ```

2. **Contact hosting provider** untuk SSL certificate

3. **Test SSL connection**:
   ```bash
   curl -I https://api.hopechannel.id/api/auth/login
   ```

4. **Update frontend** untuk menggunakan HTTPS

### Untuk Solusi 2 (HTTP Frontend)

1. **Jalankan script HTTP setup**:
   ```bash
   php change_frontend_to_http.php
   ```

2. **Update frontend URL** dari HTTPS ke HTTP

3. **Update API configuration** untuk menggunakan HTTP

4. **Test connection**:
   ```bash
   curl -X POST http://api.hopechannel.id/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"login":"test@example.com","password":"password123"}'
   ```

### Untuk Solusi 3 (Proxy)

1. **Setup proxy configuration** di web server

2. **Update frontend** untuk menggunakan relative URL:
   ```javascript
   const api = axios.create({
       baseURL: '/api',  // Relative URL
       timeout: 30000,
       headers: {
           'Content-Type': 'application/json',
           'Accept': 'application/json'
       }
   });
   ```

## Verifikasi Perbaikan

### 1. Test API Endpoint
```bash
# Untuk HTTPS
curl -X POST https://api.hopechannel.id/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"test@example.com","password":"password123"}'

# Untuk HTTP
curl -X POST http://api.hopechannel.id/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"test@example.com","password":"password123"}'
```

### 2. Check Browser Network Tab
- Buka Developer Tools (F12)
- Buka tab Network
- Coba login
- Pastikan tidak ada Mixed Content error

### 3. Check Console
- Pastikan tidak ada error di console
- Pastikan request berhasil

## Security Considerations

### Untuk HTTP (Solusi 2)
⚠️ **WARNING**: Menggunakan HTTP di production tidak aman!

**Risks:**
- Data ditransmisikan dalam plain text
- Vulnerable terhadap man-in-the-middle attacks
- Credentials bisa diintercept
- Tidak compliant dengan security standards

**Recommendations:**
1. Setup SSL certificate secepatnya
2. Gunakan Let's Encrypt (gratis)
3. Contact hosting provider untuk SSL support
4. Consider menggunakan CDN dengan SSL

### Untuk HTTPS (Solusi 1 & 3)
✅ **Recommended untuk production**

**Benefits:**
- Data encrypted
- Secure transmission
- Compliant dengan security standards
- Better SEO ranking

## Troubleshooting

### Jika SSL Setup Gagal

1. **Check hosting provider support**
   - Tanyakan apakah SSL diizinkan
   - Minta bantuan setup SSL
   - Tanyakan tentang Let's Encrypt

2. **Alternative SSL providers**
   - Let's Encrypt (gratis)
   - Cloudflare (gratis SSL)
   - Hosting provider SSL

### Jika HTTP Frontend Gagal

1. **Check server configuration**
   - Pastikan HTTP diizinkan
   - Check firewall settings
   - Verify DNS settings

2. **Test server accessibility**
   ```bash
   curl -I http://work.hopechannel.id
   curl -I http://api.hopechannel.id
   ```

### Jika Proxy Setup Gagal

1. **Check web server configuration**
   - Verify proxy modules enabled
   - Check configuration syntax
   - Restart web server

2. **Test proxy connection**
   ```bash
   curl -I https://work.hopechannel.id/api/auth/login
   ```

## Monitoring

Setelah implementasi, monitor:
- Response time
- Error rates
- SSL certificate status
- Security headers
- Mixed Content errors

## Prevention

Untuk mencegah masalah serupa:
1. **Plan SSL setup** sebelum deployment
2. **Test both HTTP and HTTPS** di development
3. **Use relative URLs** di frontend
4. **Implement proper error handling**
5. **Regular security audits** 