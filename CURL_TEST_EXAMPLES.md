# CURL Test Examples - Employee Array Data

## Setup Environment

```bash
# Set base URL
BASE_URL="http://localhost:8000"
API_URL="$BASE_URL/api"

# Set headers
HEADERS="-H 'Content-Type: application/json' -H 'Accept: application/json'"
```

## Test Cases

### 1. Create Employee dengan Array Data

```bash
curl -X POST "$API_URL/employees" \
  $HEADERS \
  -d '{
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
      }
    ]
  }'
```

### 2. Get Employee dengan Array Data

```bash
# Ganti {id} dengan ID employee yang sudah dibuat
curl -X GET "$API_URL/employees/25" \
  -H 'Accept: application/json'
```

### 3. Get All Employees dengan Array Data

```bash
curl -X GET "$API_URL/employees" \
  -H 'Accept: application/json'
```

### 4. Update Employee dengan Array Data

```bash
curl -X PUT "$API_URL/employees/25" \
  $HEADERS \
  -d '{
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
        "benefit_type": "Tunjangan Pendidikan",
        "amount": 500000,
        "start_date": "2023-01-01"
      }
    ]
  }'
```

### 5. Delete Individual Array Items

#### A. Delete Employment History
```bash
curl -X DELETE "$API_URL/employees/25/employment-histories/3" \
  -H 'Accept: application/json'
```

#### B. Delete Training
```bash
curl -X DELETE "$API_URL/employees/25/trainings/4" \
  -H 'Accept: application/json'
```

#### C. Delete Benefit
```bash
curl -X DELETE "$API_URL/employees/25/benefits/5" \
  -H 'Accept: application/json'
```

### 6. Test Cases untuk Validasi

#### A. Test dengan Array Kosong
```bash
curl -X POST "$API_URL/employees" \
  $HEADERS \
  -d '{
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
  }'
```

#### B. Test dengan Data Array Sebagian Kosong
```bash
curl -X POST "$API_URL/employees" \
  $HEADERS \
  -d '{
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
  }'
```

## Script Testing Otomatis

### 1. Create Script (create_employee.sh)

```bash
#!/bin/bash

BASE_URL="http://localhost:8000"
API_URL="$BASE_URL/api"

echo "Creating employee with array data..."

RESPONSE=$(curl -s -X POST "$API_URL/employees" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "nama_lengkap": "Ahmad Rizki",
    "nik": "1234567890123456",
    "tanggal_lahir": "1985-01-01",
    "jenis_kelamin": "Laki-laki",
    "alamat": "Jakarta",
    "status_pernikahan": "Menikah",
    "jabatan_saat_ini": "staff",
    "tanggal_mulai_kerja": "2020-01-15",
    "tingkat_pendidikan": "S1",
    "gaji_pokok": 5000000,
    "employment_histories": [
      {
        "company_name": "PT Maju Bersama",
        "position": "Staff IT",
        "start_date": "2018-03-01",
        "end_date": "2019-12-31"
      }
    ],
    "trainings": [
      {
        "training_name": "Laravel Framework Masterclass",
        "institution": "Laravel Indonesia",
        "completion_date": "2021-08-15",
        "certificate_number": "LAR-2021-001"
      }
    ],
    "benefits": [
      {
        "benefit_type": "BPJS Kesehatan",
        "amount": 500000,
        "start_date": "2020-01-15"
      }
    ]
  }')

echo "Response:"
echo "$RESPONSE" | jq '.'

# Extract employee ID
EMPLOYEE_ID=$(echo "$RESPONSE" | jq -r '.employee.id')
echo "Employee ID: $EMPLOYEE_ID"

# Save employee ID to file for other scripts
echo "$EMPLOYEE_ID" > employee_id.txt
```

### 2. Get Script (get_employee.sh)

```bash
#!/bin/bash

BASE_URL="http://localhost:8000"
API_URL="$BASE_URL/api"

# Read employee ID from file
if [ -f employee_id.txt ]; then
    EMPLOYEE_ID=$(cat employee_id.txt)
else
    echo "Employee ID not found. Please run create_employee.sh first."
    exit 1
fi

echo "Getting employee with ID: $EMPLOYEE_ID"

RESPONSE=$(curl -s -X GET "$API_URL/employees/$EMPLOYEE_ID" \
  -H 'Accept: application/json')

echo "Response:"
echo "$RESPONSE" | jq '.'
```

### 3. Update Script (update_employee.sh)

```bash
#!/bin/bash

BASE_URL="http://localhost:8000"
API_URL="$BASE_URL/api"

# Read employee ID from file
if [ -f employee_id.txt ]; then
    EMPLOYEE_ID=$(cat employee_id.txt)
else
    echo "Employee ID not found. Please run create_employee.sh first."
    exit 1
fi

echo "Updating employee with ID: $EMPLOYEE_ID"

RESPONSE=$(curl -s -X PUT "$API_URL/employees/$EMPLOYEE_ID" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "nama_lengkap": "Ahmad Rizki Updated",
    "nik": "1234567890123456",
    "tanggal_lahir": "1985-01-01",
    "jenis_kelamin": "Laki-laki",
    "alamat": "Jakarta",
    "status_pernikahan": "Menikah",
    "jabatan_saat_ini": "supervisor",
    "tanggal_mulai_kerja": "2020-01-15",
    "tingkat_pendidikan": "S1",
    "gaji_pokok": 6000000,
    "employment_histories": [
      {
        "company_name": "PT Maju Bersama Updated",
        "position": "Senior Staff IT",
        "start_date": "2018-03-01",
        "end_date": "2019-12-31"
      }
    ],
    "trainings": [
      {
        "training_name": "Advanced Laravel Development",
        "institution": "Laravel Academy",
        "completion_date": "2023-09-15",
        "certificate_number": "ALD-2023-001"
      }
    ],
    "benefits": [
      {
        "benefit_type": "BPJS Kesehatan",
        "amount": 600000,
        "start_date": "2020-01-15"
      }
    ]
  }')

echo "Response:"
echo "$RESPONSE" | jq '.'
```

### 4. Full Test Script (test_all.sh)

```bash
#!/bin/bash

BASE_URL="http://localhost:8000"
API_URL="$BASE_URL/api"

echo "=== Employee Array Data API Test ==="
echo ""

# Test 1: Create Employee
echo "1. Creating employee..."
RESPONSE=$(curl -s -X POST "$API_URL/employees" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "nama_lengkap": "Test Employee",
    "nik": "1234567890123456",
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
    "trainings": [
      {
        "training_name": "Test Training",
        "institution": "Test Institute",
        "completion_date": "2021-06-15",
        "certificate_number": "TEST-001"
      }
    ],
    "benefits": [
      {
        "benefit_type": "BPJS Kesehatan",
        "amount": 500000,
        "start_date": "2020-01-01"
      }
    ]
  }')

EMPLOYEE_ID=$(echo "$RESPONSE" | jq -r '.employee.id')
echo "Employee created with ID: $EMPLOYEE_ID"
echo ""

# Test 2: Get Employee
echo "2. Getting employee..."
curl -s -X GET "$API_URL/employees/$EMPLOYEE_ID" \
  -H 'Accept: application/json' | jq '.employee | {id, nama_lengkap, employment_histories: .employment_histories | length, trainings: .trainings | length, benefits: .benefits | length}'
echo ""

# Test 3: Update Employee
echo "3. Updating employee..."
curl -s -X PUT "$API_URL/employees/$EMPLOYEE_ID" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "nama_lengkap": "Test Employee Updated",
    "nik": "1234567890123456",
    "tanggal_lahir": "1990-01-01",
    "jenis_kelamin": "Laki-laki",
    "alamat": "Jakarta",
    "status_pernikahan": "Belum Menikah",
    "jabatan_saat_ini": "supervisor",
    "tanggal_mulai_kerja": "2020-01-01",
    "tingkat_pendidikan": "S1",
    "gaji_pokok": 6000000,
    "employment_histories": [
      {
        "company_name": "PT Test Updated",
        "position": "Senior Staff",
        "start_date": "2020-01-01",
        "end_date": "2022-12-31"
      }
    ],
    "trainings": [
      {
        "training_name": "Advanced Test Training",
        "institution": "Advanced Institute",
        "completion_date": "2023-06-15",
        "certificate_number": "ADV-001"
      }
    ],
    "benefits": [
      {
        "benefit_type": "BPJS Kesehatan",
        "amount": 600000,
        "start_date": "2020-01-01"
      },
      {
        "benefit_type": "Tunjangan Transport",
        "amount": 1000000,
        "start_date": "2020-01-01"
      }
    ]
  }' | jq '.employee | {id, nama_lengkap, employment_histories: .employment_histories | length, trainings: .trainings | length, benefits: .benefits | length}'
echo ""

# Test 4: Get All Employees
echo "4. Getting all employees..."
curl -s -X GET "$API_URL/employees" \
  -H 'Accept: application/json' | jq '.[] | {id, nama_lengkap, employment_histories: .employment_histories | length, trainings: .trainings | length, benefits: .benefits | length}'
echo ""

echo "=== Test completed ==="
```

## Cara Menjalankan Script

### 1. Buat script executable
```bash
chmod +x create_employee.sh
chmod +x get_employee.sh
chmod +x update_employee.sh
chmod +x test_all.sh
```

### 2. Jalankan test
```bash
# Test individual
./create_employee.sh
./get_employee.sh
./update_employee.sh

# Test semua sekaligus
./test_all.sh
```

### 3. Install jq untuk formatting JSON (optional)
```bash
# Ubuntu/Debian
sudo apt-get install jq

# macOS
brew install jq

# CentOS/RHEL
sudo yum install jq
```

## Expected Output

### Create Employee Response
```json
{
  "message": "Data pegawai berhasil disimpan",
  "employee": {
    "id": 25,
    "nama_lengkap": "Ahmad Rizki",
    "employment_histories": [
      {
        "id": 1,
        "company_name": "PT Maju Bersama",
        "position": "Staff IT"
      }
    ],
    "trainings": [
      {
        "id": 1,
        "training_name": "Laravel Framework Masterclass"
      }
    ],
    "benefits": [
      {
        "id": 1,
        "benefit_type": "BPJS Kesehatan",
        "amount": "500000.00"
      }
    ]
  }
}
```

### Get Employee Response
```json
{
  "id": 25,
  "nama_lengkap": "Ahmad Rizki",
  "employment_histories": [...],
  "trainings": [...],
  "benefits": [...]
}
```

## Tips Testing dengan CURL

1. **Gunakan jq** untuk formatting JSON response
2. **Simpan employee ID** untuk test berikutnya
3. **Test error cases** dengan data yang tidak valid
4. **Verifikasi array data** di response
5. **Gunakan script** untuk automation testing 