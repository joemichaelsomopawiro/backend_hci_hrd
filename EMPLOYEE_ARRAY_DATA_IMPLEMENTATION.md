# Implementasi Data Array Employee (employment_histories, trainings, benefits)

## Status Implementasi ✅

Backend Laravel sudah **SELESAI** mengimplementasikan fitur untuk menyimpan dan mengembalikan data array (employment_histories, trainings, benefits) sesuai dengan requirement yang diminta.

## 1. Model & Relasi ✅

### Employee Model (`app/Models/Employee.php`)
```php
// Relasi sudah ada di model Employee
public function employmentHistories()
{
    return $this->hasMany(EmploymentHistory::class);
}

public function trainings()
{
    return $this->hasMany(Training::class);
}

public function benefits()
{
    return $this->hasMany(Benefit::class);
}
```

### Model Relasi
- **EmploymentHistory** (`app/Models/EmploymentHistory.php`) ✅
- **Training** (`app/Models/Training.php`) ✅  
- **Benefit** (`app/Models/Benefit.php`) ✅

## 2. Migration & Database ✅

### Tabel employment_histories
```php
Schema::create('employment_histories', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('employee_id'); // Foreign key
    $table->string('company_name', 255)->nullable();
    $table->string('position', 100)->nullable();
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->timestamps();
    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
});
```

### Tabel trainings
```php
Schema::create('trainings', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('employee_id'); // Foreign key
    $table->string('training_name', 255)->nullable();
    $table->string('institution', 255)->nullable();
    $table->date('completion_date')->nullable();
    $table->string('certificate_number', 100)->nullable();
    $table->timestamps();
    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
});
```

### Tabel benefits
```php
Schema::create('benefits', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('employee_id'); // Foreign key
    $table->string('benefit_type', 100)->nullable();
    $table->decimal('amount', 15, 2)->nullable();
    $table->date('start_date')->nullable();
    $table->timestamps();
    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
});
```

## 3. Controller Implementation ✅

### EmployeeController (`app/Http/Controllers/EmployeeController.php`)

#### A. Method `index()` - GET /api/employees
```php
public function index()
{
    $employees = Employee::with([
        'user',
        'documents',
        'employmentHistories',  // ✅ Load array data
        'promotionHistories',
        'trainings',           // ✅ Load array data
        'benefits'             // ✅ Load array data
    ])->get();
    return response()->json($employees);
}
```

#### B. Method `show()` - GET /api/employees/{id}
```php
public function show($id)
{
    $employee = Employee::with([
        'user',
        'documents',
        'employmentHistories',  // ✅ Load array data
        'promotionHistories',
        'trainings',           // ✅ Load array data
        'benefits'             // ✅ Load array data
    ])->findOrFail($id);
    return response()->json($employee);
}
```

#### C. Method `store()` - POST /api/employees
```php
// Validasi array data
$validated = $request->validate([
    // ... validasi employee fields
    'employment_histories.*.company_name' => 'nullable|string|max:255',
    'employment_histories.*.position' => 'nullable|string|max:100',
    'employment_histories.*.start_date' => 'nullable|date',
    'employment_histories.*.end_date' => 'nullable|date',
    'trainings.*.training_name' => 'nullable|string|max:255',
    'trainings.*.institution' => 'nullable|string|max:255',
    'trainings.*.completion_date' => 'nullable|date',
    'trainings.*.certificate_number' => 'nullable|string|max:100',
    'benefits.*.benefit_type' => 'nullable|string|max:100',
    'benefits.*.amount' => 'nullable|numeric|min:0',
    'benefits.*.start_date' => 'nullable|date',
]);

// Simpan employee
$employee = Employee::create([...]);

// Simpan employment_histories array
if (isset($validated['employment_histories'])) {
    foreach ($validated['employment_histories'] as $history) {
        if (!empty($history['company_name'])) {
            EmploymentHistory::create([
                'employee_id' => $employee->id,
                'company_name' => $history['company_name'],
                'position' => $history['position'],
                'start_date' => $history['start_date'],
                'end_date' => $history['end_date'],
            ]);
        }
    }
}

// Simpan trainings array
if (isset($validated['trainings'])) {
    foreach ($validated['trainings'] as $training) {
        if (!empty($training['training_name'])) {
            Training::create([
                'employee_id' => $employee->id,
                'training_name' => $training['training_name'],
                'institution' => $training['institution'],
                'completion_date' => $training['completion_date'],
                'certificate_number' => $training['certificate_number'],
            ]);
        }
    }
}

// Simpan benefits array
if (isset($validated['benefits'])) {
    foreach ($validated['benefits'] as $benefit) {
        if (!empty($benefit['benefit_type'])) {
            Benefit::create([
                'employee_id' => $employee->id,
                'benefit_type' => $benefit['benefit_type'],
                'amount' => $benefit['amount'],
                'start_date' => $benefit['start_date'],
            ]);
        }
    }
}

// Response dengan array data
return response()->json([
    'message' => 'Data pegawai berhasil disimpan',
    'employee' => $employee->load([
        'documents',
        'employmentHistories',  // ✅ Return array data
        'promotionHistories',
        'trainings',           // ✅ Return array data
        'benefits',            // ✅ Return array data
        'user'
    ]),
    // ... other response data
]);
```

#### D. Method `update()` - PUT /api/employees/{id}
```php
// Validasi sama seperti store
$validated = $request->validate([...]);

// Update employee
$employee->update([...]);

// Update employment_histories (delete existing, create new)
if (isset($validated['employment_histories'])) {
    $employee->employmentHistories()->delete(); // Hapus data lama
    foreach ($validated['employment_histories'] as $history) {
        if (!empty($history['company_name'])) {
            EmploymentHistory::create([
                'employee_id' => $employee->id,
                'company_name' => $history['company_name'],
                'position' => $history['position'],
                'start_date' => $history['start_date'],
                'end_date' => $history['end_date'],
            ]);
        }
    }
}

// Update trainings (delete existing, create new)
if (isset($validated['trainings'])) {
    $employee->trainings()->delete(); // Hapus data lama
    foreach ($validated['trainings'] as $training) {
        if (!empty($training['training_name'])) {
            Training::create([...]);
        }
    }
}

// Update benefits (delete existing, create new)
if (isset($validated['benefits'])) {
    $employee->benefits()->delete(); // Hapus data lama
    foreach ($validated['benefits'] as $benefit) {
        if (!empty($benefit['benefit_type'])) {
            Benefit::create([...]);
        }
    }
}

// Response dengan array data
return response()->json([
    'message' => 'Data pegawai berhasil diperbarui',
    'employee' => $employee->load([
        'documents',
        'employmentHistories',  // ✅ Return array data
        'promotionHistories',
        'trainings',           // ✅ Return array data
        'benefits',            // ✅ Return array data
        'user'
    ]),
    // ... other response data
]);
```

## 4. Routes ✅

```php
// Employee routes dengan array data support
Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/employees/{id}', [EmployeeController::class, 'show']);
Route::post('/employees', [EmployeeController::class, 'store']);
Route::put('/employees/{id}', [EmployeeController::class, 'update']);
Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);

// Delete individual array items
Route::delete('/employees/{employeeId}/employment-histories/{historyId}', [EmployeeController::class, 'deleteEmploymentHistory']);
Route::delete('/employees/{employeeId}/trainings/{trainingId}', [EmployeeController::class, 'deleteTraining']);
Route::delete('/employees/{employeeId}/benefits/{benefitId}', [EmployeeController::class, 'deleteBenefit']);
```

## 5. Contoh Request & Response

### A. POST /api/employees (Create Employee dengan Array Data)

**Request Body:**
```json
{
  "nama_lengkap": "John Doe",
  "nik": "1234567890123456",
  "tanggal_lahir": "1990-01-01",
  "jenis_kelamin": "Laki-laki",
  "alamat": "Jakarta",
  "status_pernikahan": "Menikah",
  "jabatan_saat_ini": "staff",
  "tanggal_mulai_kerja": "2020-01-01",
  "tingkat_pendidikan": "S1",
  "gaji_pokok": 5000000,
  "employment_histories": [
    {
      "company_name": "PT ABC",
      "position": "Staff",
      "start_date": "2018-01-01",
      "end_date": "2019-12-31"
    },
    {
      "company_name": "PT XYZ",
      "position": "Senior Staff",
      "start_date": "2020-01-01",
      "end_date": "2022-12-31"
    }
  ],
  "trainings": [
    {
      "training_name": "Leadership Training",
      "institution": "Training Center",
      "completion_date": "2021-06-15",
      "certificate_number": "CERT-001"
    },
    {
      "training_name": "Project Management",
      "institution": "PMI",
      "completion_date": "2022-03-20",
      "certificate_number": "CERT-002"
    }
  ],
  "benefits": [
    {
      "benefit_type": "BPJS Kesehatan",
      "amount": 500000,
      "start_date": "2020-01-01"
    },
    {
      "benefit_type": "Tunjangan Transport",
      "amount": 1000000,
      "start_date": "2020-01-01"
    }
  ]
}
```

**Response:**
```json
{
  "message": "Data pegawai berhasil disimpan",
  "employee": {
    "id": 25,
    "nama_lengkap": "John Doe",
    "nik": "1234567890123456",
    "jabatan_saat_ini": "staff",
    "employment_histories": [
      {
        "id": 1,
        "employee_id": 25,
        "company_name": "PT ABC",
        "position": "Staff",
        "start_date": "2018-01-01",
        "end_date": "2019-12-31"
      },
      {
        "id": 2,
        "employee_id": 25,
        "company_name": "PT XYZ",
        "position": "Senior Staff",
        "start_date": "2020-01-01",
        "end_date": "2022-12-31"
      }
    ],
    "trainings": [
      {
        "id": 1,
        "employee_id": 25,
        "training_name": "Leadership Training",
        "institution": "Training Center",
        "completion_date": "2021-06-15",
        "certificate_number": "CERT-001"
      },
      {
        "id": 2,
        "employee_id": 25,
        "training_name": "Project Management",
        "institution": "PMI",
        "completion_date": "2022-03-20",
        "certificate_number": "CERT-002"
      }
    ],
    "benefits": [
      {
        "id": 1,
        "employee_id": 25,
        "benefit_type": "BPJS Kesehatan",
        "amount": "500000.00",
        "start_date": "2020-01-01"
      },
      {
        "id": 2,
        "employee_id": 25,
        "benefit_type": "Tunjangan Transport",
        "amount": "1000000.00",
        "start_date": "2020-01-01"
      }
    ]
  },
  "user_linked": false,
  "leave_quota_created": true,
  "default_leave_quota_year": "2025"
}
```

### B. GET /api/employees/{id} (Get Employee dengan Array Data)

**Response:**
```json
{
  "id": 25,
  "nama_lengkap": "John Doe",
  "nik": "1234567890123456",
  "jabatan_saat_ini": "staff",
  "employment_histories": [
    {
      "id": 1,
      "employee_id": 25,
      "company_name": "PT ABC",
      "position": "Staff",
      "start_date": "2018-01-01",
      "end_date": "2019-12-31"
    }
  ],
  "trainings": [
    {
      "id": 1,
      "employee_id": 25,
      "training_name": "Leadership Training",
      "institution": "Training Center",
      "completion_date": "2021-06-15",
      "certificate_number": "CERT-001"
    }
  ],
  "benefits": [
    {
      "id": 1,
      "employee_id": 25,
      "benefit_type": "BPJS Kesehatan",
      "amount": "500000.00",
      "start_date": "2020-01-01"
    }
  ]
}
```

### C. PUT /api/employees/{id} (Update Employee dengan Array Data)

**Request Body:** (sama seperti POST, tapi untuk update)
```json
{
  "nama_lengkap": "John Doe Updated",
  "nik": "1234567890123456",
  "jabatan_saat_ini": "supervisor",
  "employment_histories": [
    {
      "company_name": "PT ABC Updated",
      "position": "Senior Staff",
      "start_date": "2018-01-01",
      "end_date": "2019-12-31"
    }
  ],
  "trainings": [
    {
      "training_name": "Advanced Leadership",
      "institution": "Leadership Institute",
      "completion_date": "2023-06-15",
      "certificate_number": "CERT-003"
    }
  ],
  "benefits": [
    {
      "benefit_type": "BPJS Kesehatan",
      "amount": 750000,
      "start_date": "2020-01-01"
    }
  ]
}
```

## 6. Testing di Postman

### A. Test Create Employee dengan Array Data
1. **Method:** POST
2. **URL:** `http://localhost:8000/api/employees`
3. **Headers:** `Content-Type: application/json`
4. **Body:** Gunakan contoh JSON di atas
5. **Expected:** Response 201 dengan data employee + array data

### B. Test Get Employee dengan Array Data
1. **Method:** GET
2. **URL:** `http://localhost:8000/api/employees/25`
3. **Expected:** Response 200 dengan data employee + array data

### C. Test Update Employee dengan Array Data
1. **Method:** PUT
2. **URL:** `http://localhost:8000/api/employees/25`
3. **Headers:** `Content-Type: application/json`
4. **Body:** Gunakan contoh JSON update di atas
5. **Expected:** Response 200 dengan data employee + array data yang diupdate

## 7. Fitur Tambahan

### A. Delete Individual Array Items
```php
// Delete employment history
DELETE /api/employees/{employeeId}/employment-histories/{historyId}

// Delete training
DELETE /api/employees/{employeeId}/trainings/{trainingId}

// Delete benefit
DELETE /api/employees/{employeeId}/benefits/{benefitId}
```

### B. Auto-sync dengan User System
- Otomatis menghubungkan employee dengan user yang sudah ada
- Sinkronisasi role antara employee dan user
- Auto-create leave quota untuk employee baru

## 8. Kesimpulan ✅

Backend Laravel sudah **LENGKAP** mengimplementasikan:

1. ✅ **Menyimpan Data Array** - employment_histories, trainings, benefits
2. ✅ **Mengembalikan Data Array** - di response GET/POST/PUT
3. ✅ **Model & Relasi** - sudah benar dengan foreign key
4. ✅ **Migration** - tabel dengan foreign key employee_id
5. ✅ **Validation** - validasi untuk array data
6. ✅ **CRUD Operations** - create, read, update, delete
7. ✅ **Individual Delete** - hapus item array satu per satu

**Status:** ✅ **IMPLEMENTASI SELESAI DAN SIAP DIGUNAKAN** 