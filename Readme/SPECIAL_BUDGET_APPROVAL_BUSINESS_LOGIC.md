# Special Budget Approval - Business Logic & Design Options

## 📋 Current Design (Sebelum Perbaikan)

### Design Awal:
- **One-to-One Relationship**: Satu Program = Satu Manager Program (`manager_program_id`)
- **Filter Berdasarkan ID**: Hanya Program Manager dengan ID yang sama dengan `manager_program_id` yang bisa melihat approvals
- **Notifikasi**: Hanya dikirim ke `manager_program_id` program

### Masalah:
- Jika ada banyak Program Manager, hanya satu yang bisa approve
- Tidak fleksibel untuk perusahaan yang berkembang
- Jika Program Manager utama tidak available, approval stuck

---

## 🔄 Design Saat Ini (Setelah Perbaikan)

### Perubahan:
- **Semua Program Manager bisa melihat semua approvals**
- **Notifikasi dikirim ke semua Program Manager**
- **Tidak ada filter berdasarkan `manager_program_id`**

### Kelebihan:
- ✅ Fleksibel - siapa saja Program Manager bisa approve
- ✅ Tidak stuck jika Program Manager utama tidak available
- ✅ Kolaborasi lebih mudah

### Kekurangan:
- ⚠️ Bisa terjadi konflik jika 2 Program Manager approve/reject berbeda
- ⚠️ Tidak ada tracking siapa yang seharusnya approve
- ⚠️ Tidak ada hierarchy atau priority

---

## 🎯 Opsi Design untuk Perusahaan Besar

### **Opsi 1: Multiple Managers per Program (Recommended untuk Scale Besar)**

**Design:**
- Buat tabel pivot `program_managers` (many-to-many)
- Satu program bisa punya banyak Program Manager
- Satu Program Manager bisa manage banyak program
- Ada field `is_primary` untuk menentukan manager utama

**Database Schema:**
```sql
CREATE TABLE program_managers (
    id BIGINT PRIMARY KEY,
    program_id BIGINT,
    manager_program_id BIGINT,
    is_primary BOOLEAN DEFAULT FALSE,
    assigned_at TIMESTAMP,
    assigned_by BIGINT,
    FOREIGN KEY (program_id) REFERENCES programs(id),
    FOREIGN KEY (manager_program_id) REFERENCES users(id)
);
```

**Business Logic:**
- Approval dikirim ke semua Program Manager yang assigned ke program tersebut
- Bisa set primary manager untuk tracking
- Lebih terstruktur dan scalable

**Kelebihan:**
- ✅ Scalable untuk perusahaan besar
- ✅ Clear assignment - tahu siapa manage apa
- ✅ Bisa set primary manager
- ✅ Fleksibel - bisa assign multiple managers

**Kekurangan:**
- ⚠️ Perlu migration database
- ⚠️ Perlu update banyak controller
- ⚠️ Lebih kompleks

---

### **Opsi 2: Role-Based dengan Assignment (Hybrid)**

**Design:**
- Tetap pakai `manager_program_id` untuk primary manager
- Tapi semua Program Manager bisa approve special budget
- Bisa tambahkan field `requires_approval_from` untuk menentukan siapa yang harus approve

**Business Logic:**
- Primary manager (`manager_program_id`) tetap ada untuk tracking
- Semua Program Manager bisa approve, tapi ada log siapa yang approve
- Bisa set requirement: "Harus di-approve oleh primary manager" atau "Siapa saja boleh"

**Kelebihan:**
- ✅ Tidak perlu migration besar
- ✅ Tetap ada primary manager untuk accountability
- ✅ Fleksibel untuk approval

**Kekurangan:**
- ⚠️ Bisa ambigu siapa yang bertanggung jawab
- ⚠️ Tidak ada clear assignment untuk multiple managers

---

### **Opsi 3: Hierarchical Approval (Untuk Perusahaan Sangat Besar)**

**Design:**
- Ada Program Manager Level 1 (junior) dan Level 2 (senior)
- Special budget kecil (< threshold) bisa di-approve Level 1
- Special budget besar (>= threshold) harus di-approve Level 2
- Bisa tambahkan field `approval_level` di User model

**Business Logic:**
```php
if ($amount < 10000000) {
    // Level 1 Program Manager bisa approve
    $canApprove = $user->approval_level >= 1;
} else {
    // Level 2 Program Manager harus approve
    $canApprove = $user->approval_level >= 2;
}
```

**Kelebihan:**
- ✅ Clear hierarchy
- ✅ Sesuai dengan amount budget
- ✅ Scalable untuk perusahaan besar

**Kekurangan:**
- ⚠️ Perlu tambah field `approval_level` di User
- ⚠️ Lebih kompleks logic-nya

---

## 💡 Rekomendasi

### **Untuk Perusahaan Kecil-Sedang (Saat Ini):**
✅ **Gunakan Design Saat Ini** (Opsi yang sudah diimplement)
- Semua Program Manager bisa approve
- Simple dan fleksibel
- Cukup untuk kebutuhan sekarang

### **Untuk Perusahaan Besar (Future):**
✅ **Implement Opsi 1 (Multiple Managers per Program)**
- Buat tabel pivot `program_managers`
- Assign multiple managers ke program
- Set primary manager untuk accountability
- Notifikasi ke semua assigned managers

---

## 🔧 Implementation Guide untuk Opsi 1 (Future)

### 1. Migration
```php
Schema::create('program_managers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('program_id')->constrained()->onDelete('cascade');
    $table->foreignId('manager_program_id')->constrained('users')->onDelete('cascade');
    $table->boolean('is_primary')->default(false);
    $table->timestamp('assigned_at')->useCurrent();
    $table->foreignId('assigned_by')->nullable()->constrained('users');
    $table->timestamps();
    
    $table->unique(['program_id', 'manager_program_id']);
});
```

### 2. Model Update
```php
// Program.php
public function managers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'program_managers', 'program_id', 'manager_program_id')
        ->withPivot('is_primary', 'assigned_at', 'assigned_by')
        ->withTimestamps();
}

public function primaryManager(): BelongsTo
{
    return $this->belongsTo(User::class, 'manager_program_id');
}
```

### 3. Controller Update
```php
// Notify semua assigned managers
$assignedManagers = $program->managers;
foreach ($assignedManagers as $manager) {
    Notification::create([...]);
}
```

---

## 📊 Comparison Table

| Aspek | Current (Fixed) | Opsi 1 (Multiple) | Opsi 2 (Hybrid) | Opsi 3 (Hierarchical) |
|-------|----------------|-------------------|-----------------|------------------------|
| **Complexity** | ⭐ Simple | ⭐⭐⭐ Complex | ⭐⭐ Medium | ⭐⭐⭐ Complex |
| **Scalability** | ⭐⭐ Limited | ⭐⭐⭐ Excellent | ⭐⭐ Good | ⭐⭐⭐ Excellent |
| **Flexibility** | ⭐⭐ Medium | ⭐⭐⭐ Excellent | ⭐⭐⭐ Good | ⭐⭐ Medium |
| **Implementation** | ✅ Done | ❌ Need Migration | ✅ Easy | ⚠️ Medium |
| **Best For** | Small-Medium | Large | Medium-Large | Very Large |

---

## 🎯 Kesimpulan

**Untuk saat ini:**
- Design yang sudah diimplement (semua Program Manager bisa approve) **SUDAH CUKUP BAIK**
- Simple, fleksibel, dan tidak stuck
- Bisa digunakan sampai perusahaan berkembang lebih besar

**Untuk future:**
- Jika perusahaan berkembang dan perlu lebih terstruktur, implement **Opsi 1 (Multiple Managers)**
- Akan lebih scalable dan professional
- Tapi tidak urgent untuk sekarang

**Rekomendasi:**
✅ **Tetap pakai design saat ini** sampai ada kebutuhan yang lebih spesifik (misalnya: perlu tracking siapa manage apa, atau ada requirement khusus untuk approval hierarchy).

