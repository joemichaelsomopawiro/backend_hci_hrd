# Update Validasi Nomor Telepon - Support 14 Digit

## Perubahan yang Dilakukan

Sistem sekarang mendukung nomor telepon dengan **14 digit** dan berbagai format yang fleksibel.

### Validasi yang Diperbarui

**Sebelum:**
- Hanya mendukung format tertentu
- Batasan panjang tidak jelas
- Tidak mendukung nomor 14 digit

**Sesudah:**
- Minimal: 10 digit
- Maksimal: 20 digit (untuk mengakomodasi format dengan spasi/dash)
- Format: angka, +, -, spasi
- **Mendukung nomor 14 digit**

### Endpoint yang Diperbarui

Semua endpoint auth yang menggunakan validasi nomor telepon:

1. **POST** `/api/auth/send-register-otp`
2. **POST** `/api/auth/verify-otp`
3. **POST** `/api/auth/register`
4. **POST** `/api/auth/send-forgot-password-otp`
5. **POST** `/api/auth/reset-password`
6. **POST** `/api/auth/resend-otp`

### Format Nomor Telepon yang Didukung

#### ✅ Format yang Valid (14 digit):

**Format Indonesia:**
- `08123456789012` (14 digit - 08xxx)
- `628123456789012` (14 digit - 62xxx)
- `+628123456789012` (14 digit dengan +)

**Format dengan pemisah:**
- `0812-3456-7890-12` (14 digit dengan dash)
- `0812 3456 7890 12` (14 digit dengan spasi)
- `+62 812 3456 7890 12` (14 digit dengan + dan spasi)

#### ❌ Format yang Tidak Valid:

- `081234567` (9 digit - terlalu pendek)
- `081234567890123456789` (21 digit - terlalu panjang)
- `abc123def` (mengandung huruf)
- `0812-abc-7890` (mengandung huruf dengan dash)

### Contoh Penggunaan

#### Register dengan nomor 14 digit:

```bash
# Send OTP
curl -X POST http://localhost/backend_hci/public/api/auth/send-register-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "08123456789012"
  }'

# Verify OTP
curl -X POST http://localhost/backend_hci/public/api/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "08123456789012",
    "otp_code": "123456"
  }'

# Register
curl -X POST http://localhost/backend_hci/public/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "08123456789012",
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

#### Format dengan pemisah:

```bash
# Dengan dash
curl -X POST http://localhost/backend_hci/public/api/auth/send-register-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "0812-3456-7890-12"
  }'

# Dengan spasi
curl -X POST http://localhost/backend_hci/public/api/auth/send-register-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "0812 3456 7890 12"
  }'

# Dengan + dan spasi
curl -X POST http://localhost/backend_hci/public/api/auth/send-register-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+62 812 3456 7890 12"
  }'
```

### Validasi Rules

```php
'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|min:10|max:20|unique:users,phone'
```

**Penjelasan:**
- `required`: Wajib diisi
- `string`: Harus berupa string
- `regex:/^[0-9+\-\s]+$/`: Hanya boleh mengandung angka, +, -, spasi
- `min:10`: Minimal 10 karakter
- `max:20`: Maksimal 20 karakter (untuk mengakomodasi format dengan pemisah)
- `unique:users,phone`: Harus unik di database

### Testing

Untuk memverifikasi perubahan, jalankan:

```bash
php test_phone_validation.php
```

Test script akan menguji:
- ✅ Nomor 14 digit dengan berbagai format
- ✅ Batasan panjang (10-20 karakter)
- ✅ Format yang valid dan tidak valid
- ✅ Error handling

### Kesimpulan

✅ **Sistem sekarang mendukung nomor telepon 14 digit**
✅ **Fleksibel dengan berbagai format (dash, spasi, +)**
✅ **Validasi yang lebih robust**
✅ **Backward compatible dengan nomor lama**

Teman Anda dengan nomor 14 digit sekarang bisa register tanpa masalah! 🎉 