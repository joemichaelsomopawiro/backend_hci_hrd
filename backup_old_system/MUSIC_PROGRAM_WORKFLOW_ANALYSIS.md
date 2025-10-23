# 🎵 MUSIC PROGRAM WORKFLOW ANALYSIS

## 📋 **WORKFLOW YANG DIINGINKAN USER**

Berdasarkan penjelasan detail dari user, berikut adalah analisis workflow sistem program musik yang diinginkan:

---

## 🏗️ **STRUKTUR ORGANISASI**

### **Hierarchy Management:**
```
Manager Program (Paling Atas)
├── Producer (Dapat mengganti semua kelompok kerja)
│   ├── Kreatif
│   ├── Music Arranger  
│   ├── Sound Engineer
│   ├── Produksi
│   └── Editor
└── Tim Manager Distribusi
    ├── Promosi
    ├── Design Grafis
    ├── Editor Promosi
    ├── Quality Control
    └── Broadcasting
```

---

## 🔄 **WORKFLOW UTAMA**

### **Phase 1: Program Setup**
1. **Manager Program** membuat program live
2. **Database Program** dibuat
3. **Proposal Program** dengan lampiran
4. **Opsi Jadwal Tayang** diajukan ke Manager Broadcasting
5. **Sistem Auto-Generate** episode 1 sampai seterusnya
6. **Auto-Deadline**: Editor 7 hari, Kreatif/Produksi 9 hari sebelum tayang

### **Phase 2: Music Production**
```
Music Arranger → Producer → Sound Engineer → Producer (QC)
```

**Detail Flow:**
1. **Music Arranger**: Pilih lagu, penyanyi (opsional) → Ajukan ke Producer
2. **Producer**: Terima notifikasi → Terima/Tolak usulan → Jika tolak, kembali ke Music Arranger
3. **Music Arranger**: Terima notifikasi → Arrange lagu → Selesaikan → Ajukan ke Producer
4. **Producer**: QC Music manual → Terima/Tolak
   - **Jika Tolak**: Kembali ke Music Arranger + Sound Engineer
   - **Jika Terima**: Lanjut ke Kreatif

### **Phase 3: Creative Work**
```
Kreatif → Producer → Manager Program (Budget) → Producer
```

**Detail Flow:**
1. **Kreatif**: Script cerita, storyboard, jadwal rekaman, jadwal syuting, lokasi, budget talent
2. **Producer**: Cek script, storyboard, budget, tambah tim syuting/setting, edit jika perlu
3. **Manager Program**: Acc budget khusus (jika ada)
4. **Producer**: Terima/Tolak → Jika tolak, kembali ke Kreatif

### **Phase 4: Production & Distribution**
```
Produksi + Sound Engineer → Art & Set Properti → Recording/Shooting → Editor → QC → Broadcasting
```

**Detail Flow:**
1. **Produksi**: Input list alat → Ajukan ke Art & Set Properti
2. **Sound Engineer**: Input list alat → Ajukan ke Art & Set Properti  
3. **Art & Set Properti**: Acc alat → Kembalikan ke Produksi + Sound Engineer
4. **Produksi**: Syuting → Upload hasil → Kembalikan alat
5. **Sound Engineer Recording**: Rekam vocal → Upload file → Kembalikan alat
6. **Editor**: Cek kelengkapan file → Edit → Upload hasil
7. **QC**: Quality control → Terima/Tolak
8. **Broadcasting**: Upload ke YouTube, website, playlist

---

## 🎯 **ROLE RESPONSIBILITIES**

### **Manager Program:**
- Membagi kelompok team kerja
- Membuat program live
- Membuat target pencapaian views
- Monitoring semua pekerjaan
- Menutup program yang tidak berkembang
- Dapat mengintervensi semua jadwal

### **Producer:**
- Menerima program live
- Mengontrol program untuk tayang 1 episode/minggu
- Mengingatkan crew melalui sistem
- Monitoring semua pekerjaan tim
- Dapat mengintervensi jadwal syuting/rekaman
- QC Music manual
- Cek script, storyboard, budget
- Tambah tim syuting/setting
- Edit langsung jika diperlukan

### **Music Arranger:**
- Pilih lagu dan penyanyi
- Arrange lagu
- Terima notifikasi dan pekerjaan

### **Sound Engineer:**
- Bantu perbaikan arrangement
- Rekam vocal
- Edit vocal
- Input list alat

### **Kreatif:**
- Tulis script cerita video klip
- Buat storyboard
- Input jadwal rekaman/syuting
- Input lokasi syuting
- Buat budget bayar talent

### **Produksi:**
- Input list alat
- Proses syuting
- Input form catatan syuting (run sheet)
- Upload hasil syuting
- Kembalikan alat

### **Editor:**
- Cek kelengkapan file
- Edit video
- Upload hasil edit
- Buat catatan file yang kurang

### **Quality Control:**
- QC video BTS
- QC iklan episode TV
- QC highlight episode IG/TV/Facebook
- QC thumbnail YouTube/BTS
- Isi form catatan QC

### **Broadcasting:**
- Masukkan ke jadwal playlist
- Upload ke YouTube (thumbnail, deskripsi, tag, judul SEO)
- Upload ke website
- Input link YouTube ke sistem

### **Promosi:**
- Buat video BTS
- Buat foto talent
- Upload file ke storage
- Share link website ke Facebook
- Buat video highlight untuk story IG
- Buat video highlight untuk reels Facebook
- Share ke grup promosi WA

### **Design Grafis:**
- Buat thumbnail YouTube
- Buat thumbnail BTS
- Terima lokasi file dari produksi
- Terima lokasi foto talent dari promosi

### **Art & Set Properti:**
- Acc alat
- Kelola inventory alat
- Kembalikan alat

---

## 🔄 **WORKFLOW BRANCHES**

### **Branch 1: Promosi → Design Grafis → QC → Broadcasting + Promosi**
```
Promosi → Design Grafis → QC → Broadcasting
                                ↓
                              Promosi
```

### **Branch 2: Editor Promosi → QC → Broadcasting + Promosi**
```
Editor Promosi → QC → Broadcasting
                      ↓
                    Promosi
```

### **Branch 3: Produksi → Art & Set Properti → Produksi → Editor → QC → Broadcasting**
```
Produksi → Art & Set Properti → Produksi → Editor → QC → Broadcasting
```

### **Branch 4: Sound Engineer → Art & Set Properti → Sound Engineer Recording → Sound Engineer Editing → Producer QC → Editor → QC → Broadcasting**
```
Sound Engineer → Art & Set Properti → Sound Engineer Recording → Sound Engineer Editing → Producer QC → Editor → QC → Broadcasting
```

---

## 📊 **NOTIFICATION SYSTEM**

### **Auto-Generate:**
- Episode 1 sampai seterusnya
- Deadline Editor: 7 hari sebelum tayang
- Deadline Kreatif/Produksi: 9 hari sebelum tayang

### **Manual Notifications:**
- Semua role menerima notifikasi untuk setiap pekerjaan
- Producer dapat mengingatkan crew
- Manager Program menerima notifikasi program
- QC memberikan notifikasi hasil

---

## 🎯 **KEY FEATURES**

### **Auto-Generation:**
- Episode numbering
- Deadline calculation
- Notification system

### **Manual Control:**
- Producer dapat mengintervensi semua jadwal
- Manager Program dapat edit deadline khusus
- QC manual untuk music quality
- Budget approval system

### **File Management:**
- Upload ke storage
- Input link ke sistem
- Kelengkapan file checking

### **Quality Control:**
- Music QC manual
- Video QC
- File completeness check
- Form catatan QC

---

## 🔍 **ANALISIS MEDIA SOSIAL**

### **Platforms:**
- YouTube
- Instagram  
- Facebook
- TikTok
- Website
- TV

### **Content Types:**
- Video BTS
- Thumbnail YouTube/BTS
- Highlight episode IG/TV/Facebook
- Iklan episode TV
- Link sharing

---

## ✅ **KESIMPULAN WORKFLOW**

Sistem yang diinginkan adalah **workflow kompleks** dengan:

1. **Multiple Branches** - Banyak alur paralel
2. **Role-based Notifications** - Setiap role mendapat notifikasi
3. **Auto-Generation** - Episode dan deadline otomatis
4. **Manual QC** - Quality control manual di beberapa titik
5. **File Management** - Upload dan link management
6. **Budget Control** - Sistem approval budget
7. **Intervention Capability** - Producer dan Manager dapat mengintervensi

**Workflow ini lebih kompleks** dari sistem yang sudah ada, dengan lebih banyak role dan branch yang paralel.
