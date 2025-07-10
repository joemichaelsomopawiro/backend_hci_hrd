# Postman Test Examples - Employee Array Data

## Setup Postman Collection

### 1. Environment Variables
```
BASE_URL: http://localhost:8000
API_URL: {{BASE_URL}}/api
```

## Test Cases

### 1. Create Employee dengan Array Data

**Request:**
- **Method:** POST
- **URL:** `{{API_URL}}/employees`
- **Headers:**
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (raw JSON):**
```json
{
  "nama_lengkap": "Ahmad Rizki",
  "nik": "1234567890123456",
  "nip": "198501012010011001",
  "tanggal_lahir": "1985-01-01",
  "jenis_kelamin": "Laki-laki",
  "alamat": "Jl. Sudirman No. 123, Jakarta Pusat",
  "status_pernikahan": "Menikah",
  "jabatan_saat_ini": "staff",
  "tanggal_mulai_kerja": "2020-01-15",
  "tingkat_pendidikan": "S1",
  "gaji_pokok": 5000000,
  "tunjangan": 1000000,
  "bonus": 500000,
  "nomor_bpjs_kesehatan": "0001234567890",
  "nomor_bpjs_ketenagakerjaan": "1234567890123456",
  "npwp": "12.345.678.9-123.456",
  "nomor_kontrak": "KONT-2020-001",
  "tanggal_kontrak_berakhir": "2025-01-15",
  "employment_histories": [
    {
      "company_name": "PT Maju Bersama",
      "position": "Staff IT",
      "start_date": "2018-03-01",
      "end_date": "2019-12-31"
    },
    {
      "company_name": "PT Teknologi Indonesia",
      "position": "Senior Staff IT",
      "start_date": "2020-01-01",
      "end_date": "2022-06-30"
    }
  ],
  "trainings": [
    {
      "training_name": "Laravel Framework Masterclass",
      "institution": "Laravel Indonesia",
      "completion_date": "2021-08-15",
      "certificate_number": "LAR-2021-001"
    },
    {
      "training_name": "Project Management Professional",
      "institution": "PMI Indonesia",
      "completion_date": "2022-03-20",
      "certificate_number": "PMP-2022-001"
    },
    {
      "training_name": "Leadership & Team Management",
      "institution": "Leadership Academy",
      "completion_date": "2023-06-10",
      "certificate_number": "LTM-2023-001"
    }
  ],
  "benefits": [
    {
      "benefit_type": "BPJS Kesehatan",
      "amount": 500000,
      "start_date": "2020-01-15"
    },
    {
      "benefit_type": "Tunjangan Transport",
      "amount": 1000000,
      "start_date": "2020-01-15"
    },
    {
      "benefit_type": "Tunjangan Makan",
      "amount": 750000,
      "start_date": "2020-01-15"
    },
    {
      "benefit_type": "Asuransi Jiwa",
      "amount": 250000,
      "start_date": "2020-01-15"
    }
  ]
}
```

**Expected Response (201 Created):**
```json
{
  "message": "Data pegawai berhasil disimpan",
  "employee": {
    "id": 25,
    "nama_lengkap": "Ahmad Rizki",
    "nik": "1234567890123456",
    "nip": "198501012010011001",
    "jabatan_saat_ini": "staff",
    "employment_histories": [
      {
        "id": 1,
        "employee_id": 25,
        "company_name": "PT Maju Bersama",
        "position": "Staff IT",
        "start_date": "2018-03-01",
        "end_date": "2019-12-31"
      },
      {
        "id": 2,
        "employee_id": 25,
        "company_name": "PT Teknologi Indonesia",
        "position": "Senior Staff IT",
        "start_date": "2020-01-01",
        "end_date": "2022-06-30"
      }
    ],
    "trainings": [
      {
        "id": 1,
        "employee_id": 25,
        "training_name": "Laravel Framework Masterclass",
        "institution": "Laravel Indonesia",
        "completion_date": "2021-08-15",
        "certificate_number": "LAR-2021-001"
      },
      {
        "id": 2,
        "employee_id": 25,
        "training_name": "Project Management Professional",
        "institution": "PMI Indonesia",
        "completion_date": "2022-03-20",
        "certificate_number": "PMP-2022-001"
      },
      {
        "id": 3,
        "employee_id": 25,
        "training_name": "Leadership & Team Management",
        "institution": "Leadership Academy",
        "completion_date": "2023-06-10",
        "certificate_number": "LTM-2023-001"
      }
    ],
    "benefits": [
      {
        "id": 1,
        "employee_id": 25,
        "benefit_type": "BPJS Kesehatan",
        "amount": "500000.00",
        "start_date": "2020-01-15"
      },
      {
        "id": 2,
        "employee_id": 25,
        "benefit_type": "Tunjangan Transport",
        "amount": "1000000.00",
        "start_date": "2020-01-15"
      },
      {
        "id": 3,
        "employee_id": 25,
        "benefit_type": "Tunjangan Makan",
        "amount": "750000.00",
        "start_date": "2020-01-15"
      },
      {
        "id": 4,
        "employee_id": 25,
        "benefit_type": "Asuransi Jiwa",
        "amount": "250000.00",
        "start_date": "2020-01-15"
      }
    ]
  },
  "user_linked": false,
  "leave_quota_created": true,
  "default_leave_quota_year": "2025"
}
```

### 2. Get Employee dengan Array Data

**Request:**
- **Method:** GET
- **URL:** `{{API_URL}}/employees/25`
- **Headers:**
  ```
  Accept: application/json
  ```

**Expected Response (200 OK):**
```json
{
  "id": 25,
  "nama_lengkap": "Ahmad Rizki",
  "nik": "1234567890123456",
  "jabatan_saat_ini": "staff",
  "employment_histories": [
    {
      "id": 1,
      "employee_id": 25,
      "company_name": "PT Maju Bersama",
      "position": "Staff IT",
      "start_date": "2018-03-01",
      "end_date": "2019-12-31"
    },
    {
      "id": 2,
      "employee_id": 25,
      "company_name": "PT Teknologi Indonesia",
      "position": "Senior Staff IT",
      "start_date": "2020-01-01",
      "end_date": "2022-06-30"
    }
  ],
  "trainings": [
    {
      "id": 1,
      "employee_id": 25,
      "training_name": "Laravel Framework Masterclass",
      "institution": "Laravel Indonesia",
      "completion_date": "2021-08-15",
      "certificate_number": "LAR-2021-001"
    },
    {
      "id": 2,
      "employee_id": 25,
      "training_name": "Project Management Professional",
      "institution": "PMI Indonesia",
      "completion_date": "2022-03-20",
      "certificate_number": "PMP-2022-001"
    },
    {
      "id": 3,
      "employee_id": 25,
      "training_name": "Leadership & Team Management",
      "institution": "Leadership Academy",
      "completion_date": "2023-06-10",
      "certificate_number": "LTM-2023-001"
    }
  ],
  "benefits": [
    {
      "id": 1,
      "employee_id": 25,
      "benefit_type": "BPJS Kesehatan",
      "amount": "500000.00",
      "start_date": "2020-01-15"
    },
    {
      "id": 2,
      "employee_id": 25,
      "benefit_type": "Tunjangan Transport",
      "amount": "1000000.00",
      "start_date": "2020-01-15"
    },
    {
      "id": 3,
      "employee_id": 25,
      "benefit_type": "Tunjangan Makan",
      "amount": "750000.00",
      "start_date": "2020-01-15"
    },
    {
      "id": 4,
      "employee_id": 25,
      "benefit_type": "Asuransi Jiwa",
      "amount": "250000.00",
      "start_date": "2020-01-15"
    }
  ]
}
```

### 3. Update Employee dengan Array Data

**Request:**
- **Method:** PUT
- **URL:** `{{API_URL}}/employees/25`
- **Headers:**
  ```
  Content-Type: application/json
  Accept: application/json
  ```
- **Body (raw JSON):**
```json
{
  "nama_lengkap": "Ahmad Rizki Updated",
  "nik": "1234567890123456",
  "nip": "198501012010011001",
  "tanggal_lahir": "1985-01-01",
  "jenis_kelamin": "Laki-laki",
  "alamat": "Jl. Sudirman No. 123, Jakarta Pusat",
  "status_pernikahan": "Menikah",
  "jabatan_saat_ini": "supervisor",
  "tanggal_mulai_kerja": "2020-01-15",
  "tingkat_pendidikan": "S1",
  "gaji_pokok": 6000000,
  "tunjangan": 1200000,
  "bonus": 600000,
  "nomor_bpjs_kesehatan": "0001234567890",
  "nomor_bpjs_ketenagakerjaan": "1234567890123456",
  "npwp": "12.345.678.9-123.456",
  "nomor_kontrak": "KONT-2020-001",
  "tanggal_kontrak_berakhir": "2025-01-15",
  "employment_histories": [
    {
      "company_name": "PT Maju Bersama Updated",
      "position": "Senior Staff IT",
      "start_date": "2018-03-01",
      "end_date": "2019-12-31"
    },
    {
      "company_name": "PT Teknologi Indonesia",
      "position": "Team Lead IT",
      "start_date": "2020-01-01",
      "end_date": "2022-06-30"
    },
    {
      "company_name": "PT Digital Solutions",
      "position": "IT Manager",
      "start_date": "2022-07-01",
      "end_date": "2023-12-31"
    }
  ],
  "trainings": [
    {
      "training_name": "Advanced Laravel Development",
      "institution": "Laravel Academy",
      "completion_date": "2023-09-15",
      "certificate_number": "ALD-2023-001"
    },
    {
      "training_name": "Agile Project Management",
      "institution": "Agile Institute",
      "completion_date": "2023-11-20",
      "certificate_number": "APM-2023-001"
    }
  ],
  "benefits": [
    {
      "benefit_type": "BPJS Kesehatan",
      "amount": 600000,
      "start_date": "2020-01-15"
    },
    {
      "benefit_type": "Tunjangan Transport",
      "amount": 1200000,
      "start_date": "2020-01-15"
    },
    {
      "benefit_type": "Tunjangan Makan",
      "amount": 900000,
      "start_date": "2020-01-15"
    },
    {
      "benefit_type": "Asuransi Jiwa",
      "amount": 300000,
      "start_date": "2020-01-15"
    },
    {
      "benefit_type": "Tunjangan Pendidikan",
      "amount": 500000,
      "start_date": "2023-01-01"
    }
  ]
}
```

**Expected Response (200 OK):**
```json
{
  "message": "Data pegawai berhasil diperbarui",
  "employee": {
    "id": 25,
    "nama_lengkap": "Ahmad Rizki Updated",
    "nik": "1234567890123456",
    "jabatan_saat_ini": "supervisor",
    "employment_histories": [
      {
        "id": 3,
        "employee_id": 25,
        "company_name": "PT Maju Bersama Updated",
        "position": "Senior Staff IT",
        "start_date": "2018-03-01",
        "end_date": "2019-12-31"
      },
      {
        "id": 4,
        "employee_id": 25,
        "company_name": "PT Teknologi Indonesia",
        "position": "Team Lead IT",
        "start_date": "2020-01-01",
        "end_date": "2022-06-30"
      },
      {
        "id": 5,
        "employee_id": 25,
        "company_name": "PT Digital Solutions",
        "position": "IT Manager",
        "start_date": "2022-07-01",
        "end_date": "2023-12-31"
      }
    ],
    "trainings": [
      {
        "id": 4,
        "employee_id": 25,
        "training_name": "Advanced Laravel Development",
        "institution": "Laravel Academy",
        "completion_date": "2023-09-15",
        "certificate_number": "ALD-2023-001"
      },
      {
        "id": 5,
        "employee_id": 25,
        "training_name": "Agile Project Management",
        "institution": "Agile Institute",
        "completion_date": "2023-11-20",
        "certificate_number": "APM-2023-001"
      }
    ],
    "benefits": [
      {
        "id": 5,
        "employee_id": 25,
        "benefit_type": "BPJS Kesehatan",
        "amount": "600000.00",
        "start_date": "2020-01-15"
      },
      {
        "id": 6,
        "employee_id": 25,
        "benefit_type": "Tunjangan Transport",
        "amount": "1200000.00",
        "start_date": "2020-01-15"
      },
      {
        "id": 7,
        "employee_id": 25,
        "benefit_type": "Tunjangan Makan",
        "amount": "900000.00",
        "start_date": "2020-01-15"
      },
      {
        "id": 8,
        "employee_id": 25,
        "benefit_type": "Asuransi Jiwa",
        "amount": "300000.00",
        "start_date": "2020-01-15"
      },
      {
        "id": 9,
        "employee_id": 25,
        "benefit_type": "Tunjangan Pendidikan",
        "amount": "500000.00",
        "start_date": "2023-01-01"
      }
    ]
  },
  "user_linked": false
}
```

### 4. Get All Employees dengan Array Data

**Request:**
- **Method:** GET
- **URL:** `{{API_URL}}/employees`
- **Headers:**
  ```
  Accept: application/json
  ```

**Expected Response (200 OK):**
```json
[
  {
    "id": 1,
    "nama_lengkap": "John Doe",
    "nik": "1234567890123457",
    "jabatan_saat_ini": "manager",
    "employment_histories": [
      {
        "id": 1,
        "employee_id": 1,
        "company_name": "PT ABC",
        "position": "Manager",
        "start_date": "2015-01-01",
        "end_date": "2019-12-31"
      }
    ],
    "trainings": [
      {
        "id": 1,
        "employee_id": 1,
        "training_name": "Leadership Training",
        "institution": "Leadership Center",
        "completion_date": "2018-06-15",
        "certificate_number": "LT-2018-001"
      }
    ],
    "benefits": [
      {
        "id": 1,
        "employee_id": 1,
        "benefit_type": "BPJS Kesehatan",
        "amount": "800000.00",
        "start_date": "2015-01-01"
      }
    ]
  },
  {
    "id": 25,
    "nama_lengkap": "Ahmad Rizki Updated",
    "nik": "1234567890123456",
    "jabatan_saat_ini": "supervisor",
    "employment_histories": [...],
    "trainings": [...],
    "benefits": [...]
  }
]
```

### 5. Delete Individual Array Items

#### A. Delete Employment History
**Request:**
- **Method:** DELETE
- **URL:** `{{API_URL}}/employees/25/employment-histories/3`
- **Headers:**
  ```
  Accept: application/json
  ```

**Expected Response (200 OK):**
```json
{
  "message": "Employment history berhasil dihapus"
}
```

#### B. Delete Training
**Request:**
- **Method:** DELETE
- **URL:** `{{API_URL}}/employees/25/trainings/4`
- **Headers:**
  ```
  Accept: application/json
  ```

**Expected Response (200 OK):**
```json
{
  "message": "Training berhasil dihapus"
}
```

#### C. Delete Benefit
**Request:**
- **Method:** DELETE
- **URL:** `{{API_URL}}/employees/25/benefits/5`
- **Headers:**
  ```
  Accept: application/json
  ```

**Expected Response (200 OK):**
```json
{
  "message": "Benefit berhasil dihapus"
}
```

### 6. Test Cases untuk Validasi

#### A. Test dengan Array Kosong
```json
{
  "nama_lengkap": "Test Employee",
  "nik": "1234567890123458",
  "tanggal_lahir": "1990-01-01",
  "jenis_kelamin": "Laki-laki",
  "alamat": "Jakarta",
  "status_pernikahan": "Belum Menikah",
  "jabatan_saat_ini": "staff",
  "tanggal_mulai_kerja": "2020-01-01",
  "tingkat_pendidikan": "S1",
  "gaji_pokok": 5000000,
  "employment_histories": [],
  "trainings": [],
  "benefits": []
}
```

#### B. Test dengan Data Array Sebagian Kosong
```json
{
  "nama_lengkap": "Test Employee 2",
  "nik": "1234567890123459",
  "tanggal_lahir": "1990-01-01",
  "jenis_kelamin": "Laki-laki",
  "alamat": "Jakarta",
  "status_pernikahan": "Belum Menikah",
  "jabatan_saat_ini": "staff",
  "tanggal_mulai_kerja": "2020-01-01",
  "tingkat_pendidikan": "S1",
  "gaji_pokok": 5000000,
  "employment_histories": [
    {
      "company_name": "PT Test",
      "position": "Staff",
      "start_date": "2020-01-01",
      "end_date": "2022-12-31"
    }
  ],
  "trainings": [],
  "benefits": [
    {
      "benefit_type": "BPJS Kesehatan",
      "amount": 500000,
      "start_date": "2020-01-01"
    }
  ]
}
```

## Postman Collection Export

Anda dapat mengimpor collection ini ke Postman:

```json
{
  "info": {
    "name": "Employee Array Data API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Create Employee with Array Data",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"nama_lengkap\": \"Ahmad Rizki\",\n  \"nik\": \"1234567890123456\",\n  \"employment_histories\": [...],\n  \"trainings\": [...],\n  \"benefits\": [...]\n}"
        },
        "url": {
          "raw": "{{API_URL}}/employees",
          "host": ["{{API_URL}}"],
          "path": ["employees"]
        }
      }
    },
    {
      "name": "Get Employee with Array Data",
      "request": {
        "method": "GET",
        "url": {
          "raw": "{{API_URL}}/employees/25",
          "host": ["{{API_URL}}"],
          "path": ["employees", "25"]
        }
      }
    },
    {
      "name": "Update Employee with Array Data",
      "request": {
        "method": "PUT",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"nama_lengkap\": \"Ahmad Rizki Updated\",\n  \"employment_histories\": [...],\n  \"trainings\": [...],\n  \"benefits\": [...]\n}"
        },
        "url": {
          "raw": "{{API_URL}}/employees/25",
          "host": ["{{API_URL}}"],
          "path": ["employees", "25"]
        }
      }
    }
  ]
}
```

## Tips Testing

1. **Simpan Employee ID** dari response create untuk digunakan di test berikutnya
2. **Gunakan Environment Variables** untuk BASE_URL
3. **Test Error Cases** dengan data yang tidak valid
4. **Verifikasi Array Data** di response sesuai dengan yang dikirim
5. **Test Update** untuk memastikan data lama terhapus dan data baru tersimpan 