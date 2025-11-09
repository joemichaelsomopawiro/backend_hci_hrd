# Panduan Upload Absensi TXT (Fixed Width)

## Format File TXT
- File harus berekstensi `.txt`
- Baris pertama adalah header, urutan dan lebar kolom harus sama seperti contoh
- Setiap baris data harus mengikuti posisi kolom (fixed width)
- Kolom kosong tetap harus ada spasinya

### Contoh Header dan Data
```
No. ID        Nama                   Tanggal    Scan Masuk Scan Pulang Absent Jml Jam Kerja Jml Kehadiran
1             E.H Michael Palar      07-Jul-25                                      
1             E.H Michael Palar      08-Jul-25                                      
20111201      Steven Albert Reynold M07-Jul-25                                      
20111201      Steven Albert Reynold M11-Jul-25   14:22      19:29             05:06         05:06
```

## Cara Upload
1. Masuk ke halaman upload absensi
2. Pilih file TXT (format sesuai contoh di atas)
3. Klik **Preview** untuk melihat 10 data pertama
4. Jika data sudah benar, klik **Upload** untuk menyimpan ke database

## Error Handling
- Jika ada baris yang error (misal: tanggal tidak valid, kolom wajib kosong), akan muncul pesan error di preview
- Data yang gagal upload akan dilaporkan di hasil upload

## Catatan
- Hanya file TXT yang diterima
- Fitur export absensi harian/bulanan tetap berjalan seperti biasa 