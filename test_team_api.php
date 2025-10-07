<?php

// Test Team Management API dengan nilai role yang benar
$baseUrl = 'http://127.0.0.1:8000/api';

// Test data dengan role yang sesuai ENUM
$testData = [
    'name' => 'Tim Kreatif Program Pagi malam',
    'description' => 'Tim kreatif untuk program pagi',
    'role' => 'kreatif', // Menggunakan 'kreatif' bukan 'creative'
    'program_id' => 4,
    'team_lead_id' => 13,
    'members' => [6, 24]
];

echo "Testing Team Management API...\n";
echo "================================\n\n";

// Test 1: Create Team
echo "1. Testing Create Team...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/teams');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

$responseData = json_decode($response, true);
if ($responseData && $responseData['success']) {
    $teamId = $responseData['data']['id'];
    echo "✅ Team created successfully with ID: $teamId\n\n";
    
    // Test 2: Get Team Details
    echo "2. Testing Get Team Details...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . "/teams/$teamId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
    // Test 3: Get All Teams
    echo "3. Testing Get All Teams...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/teams');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
} else {
    echo "❌ Failed to create team\n";
    if ($responseData) {
        echo "Error: " . $responseData['message'] . "\n";
    }
}

echo "Test completed!\n";
