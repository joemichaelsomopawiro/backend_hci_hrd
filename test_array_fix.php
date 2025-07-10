<?php

/**
 * Script Testing untuk Memverifikasi Perbaikan Error Array Data Employee
 * 
 * Script ini akan menguji berbagai skenario input data untuk memastikan
 * error "Undefined array key" sudah diperbaiki.
 */

$baseUrl = 'http://localhost:8000';
$apiUrl = $baseUrl . '/api';

echo "=== Testing Employee Array Data Fix ===\n\n";

// Test Case 1: Data Lengkap
echo "1. Testing dengan data lengkap...\n";
$testData1 = [
    'nama_lengkap' => 'Test Employee Complete',
    'nik' => '1234567890123456',
    'tanggal_lahir' => '1990-01-01',
    'jenis_kelamin' => 'Laki-laki',
    'alamat' => 'Jakarta',
    'status_pernikahan' => 'Belum Menikah',
    'jabatan_saat_ini' => 'staff',
    'tanggal_mulai_kerja' => '2020-01-01',
    'tingkat_pendidikan' => 'S1',
    'gaji_pokok' => 5000000,
    'employment_histories' => [
        [
            'company_name' => 'PT ABC Complete',
            'position' => 'Staff',
            'start_date' => '2020-01-01',
            'end_date' => '2022-12-31'
        ]
    ],
    'trainings' => [
        [
            'training_name' => 'Laravel Training Complete',
            'institution' => 'Laravel Academy',
            'completion_date' => '2021-06-15',
            'certificate_number' => 'CERT-001'
        ]
    ],
    'benefits' => [
        [
            'benefit_type' => 'BPJS Kesehatan',
            'amount' => 500000,
            'start_date' => '2020-01-01'
        ]
    ]
];

$response1 = makeRequest('POST', $apiUrl . '/employees', $testData1);
echo "Status: " . ($response1['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if ($response1['success']) {
    echo "Employee ID: " . $response1['data']['employee']['id'] . "\n";
    $employeeId1 = $response1['data']['employee']['id'];
} else {
    echo "Error: " . $response1['data']['error'] . "\n";
}
echo "\n";

// Test Case 2: Data Sebagian (hanya field wajib)
echo "2. Testing dengan data sebagian...\n";
$testData2 = [
    'nama_lengkap' => 'Test Employee Partial',
    'nik' => '1234567890123457',
    'tanggal_lahir' => '1990-01-01',
    'jenis_kelamin' => 'Laki-laki',
    'alamat' => 'Jakarta',
    'status_pernikahan' => 'Belum Menikah',
    'jabatan_saat_ini' => 'staff',
    'tanggal_mulai_kerja' => '2020-01-01',
    'tingkat_pendidikan' => 'S1',
    'gaji_pokok' => 5000000,
    'employment_histories' => [
        [
            'company_name' => 'PT ABC Partial'
            // Field lain tidak dikirim
        ]
    ],
    'trainings' => [
        [
            'training_name' => 'Laravel Training Partial'
            // Field lain tidak dikirim
        ]
    ],
    'benefits' => [
        [
            'benefit_type' => 'BPJS Kesehatan'
            // Field lain tidak dikirim
        ]
    ]
];

$response2 = makeRequest('POST', $apiUrl . '/employees', $testData2);
echo "Status: " . ($response2['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if ($response2['success']) {
    echo "Employee ID: " . $response2['data']['employee']['id'] . "\n";
    $employeeId2 = $response2['data']['employee']['id'];
} else {
    echo "Error: " . $response2['data']['error'] . "\n";
}
echo "\n";

// Test Case 3: Array Kosong
echo "3. Testing dengan array kosong...\n";
$testData3 = [
    'nama_lengkap' => 'Test Employee Empty',
    'nik' => '1234567890123458',
    'tanggal_lahir' => '1990-01-01',
    'jenis_kelamin' => 'Laki-laki',
    'alamat' => 'Jakarta',
    'status_pernikahan' => 'Belum Menikah',
    'jabatan_saat_ini' => 'staff',
    'tanggal_mulai_kerja' => '2020-01-01',
    'tingkat_pendidikan' => 'S1',
    'gaji_pokok' => 5000000,
    'employment_histories' => [],
    'trainings' => [],
    'benefits' => []
];

$response3 = makeRequest('POST', $apiUrl . '/employees', $testData3);
echo "Status: " . ($response3['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if ($response3['success']) {
    echo "Employee ID: " . $response3['data']['employee']['id'] . "\n";
    $employeeId3 = $response3['data']['employee']['id'];
} else {
    echo "Error: " . $response3['data']['error'] . "\n";
}
echo "\n";

// Test Case 4: Update dengan data baru
if (isset($employeeId1)) {
    echo "4. Testing update employee...\n";
    $updateData = [
        'nama_lengkap' => 'Test Employee Updated',
        'nik' => '1234567890123456',
        'tanggal_lahir' => '1990-01-01',
        'jenis_kelamin' => 'Laki-laki',
        'alamat' => 'Jakarta',
        'status_pernikahan' => 'Belum Menikah',
        'jabatan_saat_ini' => 'supervisor',
        'tanggal_mulai_kerja' => '2020-01-01',
        'tingkat_pendidikan' => 'S1',
        'gaji_pokok' => 6000000,
        'employment_histories' => [
            [
                'company_name' => 'PT ABC Updated',
                'position' => 'Senior Staff'
                // Field lain tidak dikirim
            ]
        ],
        'trainings' => [
            [
                'training_name' => 'Advanced Laravel'
                // Field lain tidak dikirim
            ]
        ],
        'benefits' => [
            [
                'benefit_type' => 'BPJS Kesehatan Updated'
                // Field lain tidak dikirim
            ]
        ]
    ];

    $response4 = makeRequest('PUT', $apiUrl . '/employees/' . $employeeId1, $updateData);
    echo "Status: " . ($response4['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    if (!$response4['success']) {
        echo "Error: " . $response4['data']['error'] . "\n";
    }
    echo "\n";
}

// Test Case 5: Get employee dengan array data
if (isset($employeeId1)) {
    echo "5. Testing get employee dengan array data...\n";
    $response5 = makeRequest('GET', $apiUrl . '/employees/' . $employeeId1);
    echo "Status: " . ($response5['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    if ($response5['success']) {
        $employee = $response5['data'];
        echo "Employee: " . $employee['nama_lengkap'] . "\n";
        echo "Employment Histories: " . count($employee['employment_histories']) . " items\n";
        echo "Trainings: " . count($employee['trainings']) . " items\n";
        echo "Benefits: " . count($employee['benefits']) . " items\n";
    } else {
        echo "Error: " . $response5['data']['error'] . "\n";
    }
    echo "\n";
}

// Test Case 6: Get all employees
echo "6. Testing get all employees...\n";
$response6 = makeRequest('GET', $apiUrl . '/employees');
echo "Status: " . ($response6['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if ($response6['success']) {
    echo "Total Employees: " . count($response6['data']) . "\n";
    foreach ($response6['data'] as $employee) {
        echo "- " . $employee['nama_lengkap'] . " (ID: " . $employee['id'] . ")\n";
        echo "  Employment Histories: " . count($employee['employment_histories']) . " items\n";
        echo "  Trainings: " . count($employee['trainings']) . " items\n";
        echo "  Benefits: " . count($employee['benefits']) . " items\n";
    }
} else {
    echo "Error: " . $response6['data']['error'] . "\n";
}
echo "\n";

// Cleanup: Delete test employees
echo "7. Cleaning up test data...\n";
if (isset($employeeId1)) {
    $delete1 = makeRequest('DELETE', $apiUrl . '/employees/' . $employeeId1);
    echo "Delete Employee 1: " . ($delete1['success'] ? 'SUCCESS' : 'FAILED') . "\n";
}
if (isset($employeeId2)) {
    $delete2 = makeRequest('DELETE', $apiUrl . '/employees/' . $employeeId2);
    echo "Delete Employee 2: " . ($delete2['success'] ? 'SUCCESS' : 'FAILED') . "\n";
}
if (isset($employeeId3)) {
    $delete3 = makeRequest('DELETE', $apiUrl . '/employees/' . $employeeId3);
    echo "Delete Employee 3: " . ($delete3['success'] ? 'SUCCESS' : 'FAILED') . "\n";
}

echo "\n=== Testing Complete ===\n";

/**
 * Helper function untuk membuat HTTP request
 */
function makeRequest($method, $url, $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $responseData
    ];
}

?> 