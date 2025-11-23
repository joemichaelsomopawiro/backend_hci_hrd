# 📚 Dokumentasi DomPDF

## 🔗 Link Dokumentasi Resmi

### 1. Laravel DomPDF Wrapper (barryvdh/laravel-dompdf)
- **GitHub**: https://github.com/barryvdh/laravel-dompdf
- **Packagist**: https://packagist.org/packages/barryvdh/laravel-dompdf
- **Version**: v3.1.1 (yang digunakan di project ini)

### 2. Core DomPDF Library
- **GitHub**: https://github.com/dompdf/dompdf
- **Homepage**: https://github.com/dompdf/dompdf
- **Version**: v3.1.0 (yang digunakan di project ini)
- **Documentation**: https://github.com/dompdf/dompdf/wiki

---

## 📖 Dokumentasi Lokal di Project

### 1. README dari Package
**Lokasi**: `vendor/barryvdh/laravel-dompdf/readme.md`

### 2. Config File
**Lokasi**: `config/dompdf.php`
- Berisi semua konfigurasi DomPDF
- Font directory, paper size, DPI, dll

### 3. Contoh Penggunaan di Project
**Lokasi**: `app/Http/Controllers/LeaveRequestController.php`
- Method: `downloadLetter($id)`
- Template: `resources/views/pdfs/leave_letter_simple.blade.php`

---

## 🚀 Quick Start

### Basic Usage

```php
use Barryvdh\DomPDF\Facade\Pdf;

// Load dari View (Blade)
$pdf = Pdf::loadView('pdfs.leave_letter_simple', $data);
return $pdf->download('surat_cuti.pdf');

// Load dari HTML string
$pdf = Pdf::loadHTML('<h1>Test</h1>');
return $pdf->stream();

// Load dari file
$pdf = Pdf::loadFile(public_path().'/myfile.html');
return $pdf->save('/path-to/my_stored_file.pdf')->stream('download.pdf');
```

### Method Chaining

```php
Pdf::loadHTML($html)
    ->setPaper('a4', 'landscape')
    ->setWarnings(false)
    ->save('myfile.pdf');
```

### Output Options

```php
// Download (force download)
$pdf->download('filename.pdf');

// Stream (show in browser)
$pdf->stream('filename.pdf');

// Save to file
$pdf->save('/path/to/file.pdf');

// Get output as string
$output = $pdf->output();
```

---

## ⚙️ Konfigurasi

### Publish Config
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### Set Options Programmatically
```php
Pdf::setOption([
    'dpi' => 150,
    'defaultFont' => 'sans-serif',
    'isRemoteEnabled' => true,
    'isHtml5ParserEnabled' => true
]);
```

### Available Options

| Option | Default | Description |
|--------|---------|-------------|
| `defaultPaperSize` | `a4` | Ukuran kertas (a4, letter, legal, dll) |
| `defaultPaperOrientation` | `portrait` | Orientasi (portrait/landscape) |
| `defaultFont` | `serif` | Font default |
| `dpi` | `96` | DPI untuk rendering |
| `fontDir` | `storage/fonts` | Directory untuk font |
| `isRemoteEnabled` | `false` | Enable remote images/CSS |
| `isHtml5ParserEnabled` | `true` | Enable HTML5 parser |
| `isJavascriptEnabled` | `true` | Enable JavaScript |
| `isPhpEnabled` | `false` | Enable embedded PHP |

---

## 📝 Tips & Best Practices

### 1. UTF-8 Support
Tambahkan meta tag di template:
```html
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
```

### 2. Page Breaks
Gunakan CSS untuk page break:
```css
.page-break {
    page-break-after: always;
}
```

### 3. Font Support
- Default font: DejaVu Sans (mendukung karakter Indonesia)
- Font directory: `storage/fonts`
- Untuk font custom, copy font file ke `storage/fonts`

### 4. Image Support
- Local images: gunakan absolute path
- Remote images: enable `isRemoteEnabled` (hati-hati dengan security)
- Data URI: support untuk inline images

### 5. CSS Support
- CSS 2.1 compliant
- Inline CSS lebih reliable
- External CSS perlu enable `isRemoteEnabled`

---

## 🔍 Contoh di Project Ini

### Controller: LeaveRequestController.php

```php
public function downloadLetter($id)
{
    $leave = LeaveRequest::with(['employee.user', 'approvedBy.user'])->findOrFail($id);
    
    // Prepare data
    $data = [
        'employee_name' => $employee->nama_lengkap,
        'employee_position' => $employeePosition,
        'date_range_text' => $dateRange,
        'total_days' => $leave->total_days,
        // ... more data
    ];
    
    // Generate PDF
    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        if (\View::exists('pdfs.leave_letter_simple')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.leave_letter_simple', $data)
                ->setPaper('A4');
            return $pdf->download('surat_cuti_' . $leave->id . '.pdf');
        }
    }
}
```

### Template: leave_letter_simple.blade.php

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Cuti</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; }
        /* ... more styles */
    </style>
</head>
<body>
    <!-- Template content -->
</body>
</html>
```

---

## 🐛 Troubleshooting

### Problem: Font tidak muncul
**Solution**: 
- Pastikan font ada di `storage/fonts`
- Gunakan font yang support UTF-8 (DejaVu Sans)

### Problem: Image tidak muncul
**Solution**:
- Gunakan absolute path untuk local images
- Enable `isRemoteEnabled` untuk remote images
- Atau gunakan Data URI

### Problem: CSS tidak ter-apply
**Solution**:
- Gunakan inline CSS
- Atau enable `isRemoteEnabled` untuk external CSS

### Problem: Karakter Indonesia tidak muncul
**Solution**:
- Tambahkan meta charset UTF-8
- Gunakan font DejaVu Sans
- Pastikan file encoding UTF-8

---

## 📚 Referensi Tambahan

1. **DomPDF Wiki**: https://github.com/dompdf/dompdf/wiki
2. **Laravel DomPDF Issues**: https://github.com/barryvdh/laravel-dompdf/issues
3. **DomPDF Issues**: https://github.com/dompdf/dompdf/issues
4. **CSS Support**: https://github.com/dompdf/dompdf/wiki/CSS-Support

---

## ✅ Checklist Implementasi

- [x] Package terinstall (`barryvdh/laravel-dompdf`)
- [x] Config file ada (`config/dompdf.php`)
- [x] Template Blade ada (`resources/views/pdfs/leave_letter_simple.blade.php`)
- [x] Controller method ada (`LeaveRequestController::downloadLetter()`)
- [x] Route terdaftar (`/api/leave-requests/{id}/letter`)

---

## 📌 Catatan Penting

1. **Security**: Remote access disabled by default (v3.x)
2. **Performance**: Cache font metrics untuk performa lebih baik
3. **Compatibility**: CSS 2.1 compliant, tidak semua CSS3 support
4. **Font**: Hanya Base 14 fonts yang guaranteed support, font lain perlu embed

---

**Last Updated**: 2025-01-23
**Version**: DomPDF v3.1.0, Laravel DomPDF v3.1.1


