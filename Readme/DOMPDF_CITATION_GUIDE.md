# 📚 Panduan Sitasi DomPDF untuk Laporan Magang

## ❓ Apakah Harus Menjelaskan DomPDF di Laporan?

### ✅ **YA, WAJIB** untuk menjelaskan:

1. **Teknologi yang Digunakan**: DomPDF adalah library yang digunakan untuk generate surat cuti
2. **Justifikasi Pemilihan**: Jelaskan mengapa memilih DomPDF
3. **Implementasi**: Jelaskan bagaimana DomPDF diimplementasikan

---

## 📖 Format Sitasi IEEE untuk DomPDF

### **1. Core DomPDF Library**

#### **Format IEEE Standar:**
```
[1] The Dompdf Community, "DOMPDF - A CSS 2.1 compliant HTML to PDF converter," 
    GitHub repository, 2024. [Online]. Available: https://github.com/dompdf/dompdf. 
    [Accessed: Jan. 23, 2025].
```

#### **Detail:**
- **Author**: The Dompdf Community
- **Title**: DOMPDF - A CSS 2.1 compliant HTML to PDF converter
- **Version**: v3.1.0
- **URL**: https://github.com/dompdf/dompdf
- **License**: LGPL-2.1

---

### **2. Laravel DomPDF Wrapper**

#### **Format IEEE Standar:**
```
[2] B. vd. Heuvel, "Laravel DomPDF Wrapper," GitHub repository, 2025. [Online]. 
    Available: https://github.com/barryvdh/laravel-dompdf. 
    [Accessed: Jan. 23, 2025].
```

#### **Detail:**
- **Author**: Barry vd. Heuvel (barryvdh)
- **Title**: Laravel DomPDF Wrapper
- **Version**: v3.1.1
- **URL**: https://github.com/barryvdh/laravel-dompdf
- **License**: MIT

---

## 📝 Contoh Penjelasan di Laporan

### **Di Bab Metodologi:**

```
Sistem generate surat cuti menggunakan library DomPDF versi 3.1.0 [1] yang 
merupakan HTML to PDF converter berbasis PHP. DomPDF dipilih karena terintegrasi 
dengan Laravel melalui package barryvdh/laravel-dompdf versi 3.1.1 [2], 
mendukung template Blade, dan dapat melakukan konversi HTML/CSS ke PDF secara 
server-side tanpa perlu JavaScript di client.
```

### **Di Bab Implementasi:**

```
Implementasi generate surat cuti dilakukan dengan membuat template Blade di 
resources/views/pdfs/leave_letter_simple.blade.php. Data dari database di-pass 
ke template melalui controller LeaveRequestController, kemudian DomPDF [1] 
mengkonversi template HTML tersebut menjadi file PDF menggunakan wrapper 
barryvdh/laravel-dompdf [2].
```

---

## 📋 Daftar Pustaka Lengkap

```
[1] The Dompdf Community, "DOMPDF v3.1.0 - A CSS 2.1 compliant HTML to PDF 
    converter," GitHub repository, 2024. [Online]. Available: 
    https://github.com/dompdf/dompdf/releases/tag/v3.1.0. 
    [Accessed: Jan. 23, 2025].

[2] B. vd. Heuvel, "Laravel DomPDF Wrapper v3.1.1," GitHub repository, 2025. 
    [Online]. Available: https://github.com/barryvdh/laravel-dompdf/releases/tag/v3.1.1. 
    [Accessed: Jan. 23, 2025].
```

---

## 🔍 Informasi untuk Laporan

### **Spesifikasi:**
- **Library**: DomPDF v3.1.0
- **Wrapper**: barryvdh/laravel-dompdf v3.1.1
- **Platform**: PHP 8.1+, Laravel 10+
- **Format**: PDF (A4)
- **Font**: DejaVu Sans (UTF-8 support)

### **Alasan Pemilihan:**
1. Server-side processing (tidak perlu JavaScript)
2. Terintegrasi dengan Laravel
3. Mendukung template Blade
4. Font support untuk karakter Indonesia
5. CSS 2.1 compliant

---

**Catatan**: Ganti tanggal akses sesuai kapan Anda mengakses sumber!


