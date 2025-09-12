# PDF Download Fix Guide - Error 500 di Hosting

## Masalah
Setelah deploy ke hosting, download surat cuti (PDF) mengalami error 500, padahal di localhost berfungsi normal.

## Root Cause
**DomPDF library tidak terinstall** di hosting. Error yang muncul:
```
Class "Barryvdh\DomPDF\Facade\Pdf" not found
```

## Solusi Lengkap

### 1. Install DomPDF Library
Tambahkan ke `composer.json`:
```json
"require": {
    "barryvdh/laravel-dompdf": "^2.0"
}
```

Kemudian jalankan di hosting:
```bash
composer install --no-dev --optimize-autoloader
```

### 2. Publish DomPDF Config (Opsional)
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### 3. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 4. Verifikasi Storage Permissions
Pastikan folder storage dapat ditulis:
```bash
chmod -R 775 storage
chmod -R 775 public/downloads
```

### 5. Test PDF Generation
Jalankan script test:
```bash
php test_pdf_generation.php
```

## File yang Sudah Diperbaiki

### composer.json
- ✅ Ditambahkan `"barryvdh/laravel-dompdf": "^2.0"`

### Storage Structure
- ✅ `storage/app/public/signatures/` - Signature files tersedia
- ✅ `storage/app/public/leave-letters/` - Generated PDFs tersedia
- ✅ `public/downloads/` - Download directory tersedia

### PDF Template
- ✅ `resources/views/pdfs/leave_letter_simple.blade.php` - Template tersedia

## Endpoint yang Bermasalah
```
GET /api/leave-requests/{id}/letter
```

## Controller Method
```php
public function downloadLetter($id)
{
    // Method sudah benar, hanya perlu DomPDF library
    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.leave_letter_simple', $data)->setPaper('A4');
        return $pdf->download('surat_cuti_' . $leave->id . '.pdf');
    }
    // Fallback ke HTML jika PDF tidak tersedia
    return response()->view('pdfs.leave_letter_simple', $data, 200);
}
```

## Langkah Deploy ke Hosting

1. **Upload composer.json yang sudah diupdate**
2. **Jalankan composer install di hosting:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. **Clear cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```
4. **Test endpoint:**
   ```bash
   curl -X GET "https://api.hopemedia.id/api/leave-requests/62/letter" \
        -H "Authorization: Bearer YOUR_TOKEN"
   ```

## Troubleshooting

### Jika masih error 500:
1. Cek log error: `tail -f storage/logs/laravel.log`
2. Pastikan PHP version >= 8.1
3. Pastikan extension `mbstring` dan `gd` terinstall
4. Cek memory limit PHP (minimal 256MB)

### Jika PDF kosong:
1. Cek template path: `resources/views/pdfs/leave_letter_simple.blade.php`
2. Cek signature files di `storage/app/public/signatures/`
3. Cek logo files di `public/images/`

## Status
- ✅ DomPDF library ditambahkan ke composer.json
- ✅ Storage permissions verified
- ✅ Signature files tersedia
- ✅ PDF template tersedia
- ✅ Test script dibuat

**Next Step:** Deploy composer.json ke hosting dan jalankan `composer install`





