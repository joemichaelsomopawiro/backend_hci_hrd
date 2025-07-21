<?php

// Test Enhanced Role Management System
echo "=== TEST ENHANCED ROLE MANAGEMENT SYSTEM ===\n\n";

// Base URL
$baseUrl = 'http://localhost:8000/api';

// Test token (ganti dengan token HR yang valid)
$token = 'your_hr_token_here';

// Function untuk test endpoint
function testEndpoint($endpoint, $method = 'GET', $data = null, $token = null) {
    $url = $GLOBALS['baseUrl'] . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== $method $endpoint ===\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

// Test 1: Get form options
echo "1. Testing GET /custom-roles/form-options\n";
$formOptions = testEndpoint('/custom-roles/form-options', 'GET', null, $token);

// Test 2: Create Tech Lead (Manager)
echo "2. Testing POST /custom-roles (Create Tech Lead)\n";
$techLeadData = [
    'role_name' => 'Tech Lead',
    'description' => 'Technical team leader for development team',
    'access_level' => 'manager',
    'department' => 'production',
    'supervisor_id' => null
];
$techLeadResponse = testEndpoint('/custom-roles', 'POST', $techLeadData, $token);

// Extract Tech Lead ID if created successfully
$techLeadId = null;
if (isset($techLeadResponse['data']['id'])) {
    $techLeadId = $techLeadResponse['data']['id'];
    echo "Tech Lead created with ID: $techLeadId\n\n";
}

// Test 3: Create Developer (Employee with Tech Lead as supervisor)
echo "3. Testing POST /custom-roles (Create Developer)\n";
$developerData = [
    'role_name' => 'Developer',
    'description' => 'Software developer responsible for coding',
    'access_level' => 'employee',
    'department' => 'production',
    'supervisor_id' => $techLeadId
];
$developerResponse = testEndpoint('/custom-roles', 'POST', $developerData, $token);

// Test 4: Create QA Engineer (Employee with Tech Lead as supervisor)
echo "4. Testing POST /custom-roles (Create QA Engineer)\n";
$qaData = [
    'role_name' => 'QA Engineer',
    'description' => 'Quality assurance engineer',
    'access_level' => 'employee',
    'department' => 'production',
    'supervisor_id' => $techLeadId
];
$qaResponse = testEndpoint('/custom-roles', 'POST', $qaData, $token);

// Test 5: Get all custom roles
echo "5. Testing GET /custom-roles\n";
$allRoles = testEndpoint('/custom-roles', 'GET', null, $token);

// Test 6: Get roles by department
echo "6. Testing GET /custom-roles/by-department/production\n";
$productionRoles = testEndpoint('/custom-roles/by-department/production', 'GET', null, $token);

// Test 7: Get role hierarchy for Developer
echo "7. Testing GET /custom-roles/hierarchy/Developer\n";
$developerHierarchy = testEndpoint('/custom-roles/hierarchy/Developer', 'GET', null, $token);

// Test 8: Get role hierarchy for Tech Lead
echo "8. Testing GET /custom-roles/hierarchy/Tech Lead\n";
$techLeadHierarchy = testEndpoint('/custom-roles/hierarchy/Tech Lead', 'GET', null, $token);

// Test 9: Update Developer to Senior Developer
echo "9. Testing PUT /custom-roles/{id} (Update Developer)\n";
if (isset($developerResponse['data']['id'])) {
    $developerId = $developerResponse['data']['id'];
    $updateData = [
        'role_name' => 'Senior Developer',
        'description' => 'Senior software developer with more experience',
        'access_level' => 'employee',
        'department' => 'production',
        'supervisor_id' => $techLeadId,
        'is_active' => true
    ];
    $updateResponse = testEndpoint("/custom-roles/$developerId", 'PUT', $updateData, $token);
}

// Test 10: Test validation - Employee without supervisor
echo "10. Testing POST /custom-roles (Employee without supervisor - should fail)\n";
$invalidData = [
    'role_name' => 'Invalid Employee',
    'description' => 'Employee without supervisor',
    'access_level' => 'employee',
    'department' => 'production',
    'supervisor_id' => null
];
$invalidResponse = testEndpoint('/custom-roles', 'POST', $invalidData, $token);

// Test 11: Test validation - Role name conflict
echo "11. Testing POST /custom-roles (Role name conflict - should fail)\n";
$conflictData = [
    'role_name' => 'HR', // Standard role name
    'description' => 'Trying to create HR role',
    'access_level' => 'employee',
    'department' => 'hr',
    'supervisor_id' => null
];
$conflictResponse = testEndpoint('/custom-roles', 'POST', $conflictData, $token);

// Test 12: Get all roles (including custom)
echo "12. Testing GET /custom-roles/all-roles\n";
$allRolesWithCustom = testEndpoint('/custom-roles/all-roles', 'GET', null, $token);

echo "=== TEST SELESAI ===\n";
echo "Sistem Enhanced Role Management telah diuji!\n";
echo "Fitur yang diuji:\n";
echo "- Form options (departments, access levels, supervisors)\n";
echo "- Create manager role (Tech Lead)\n";
echo "- Create employee roles (Developer, QA Engineer)\n";
echo "- Hierarchy management\n";
echo "- Department filtering\n";
echo "- Role updates\n";
echo "- Validation (employee without supervisor, role name conflict)\n";
echo "- Get all roles with custom roles\n";
?> 