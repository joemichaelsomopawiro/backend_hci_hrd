<?php

// Debug script untuk menguji custom roles API

// URL endpoint
$url = 'http://localhost:5174/api/custom-roles';

// Data yang akan dikirim
$data = [
    'role_name' => 'Test Role Debug',
    'description' => 'Test description for debugging'
    // Tidak mengirim access_level untuk test default value
];

// Headers
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer test-token' // Ganti dengan token yang valid
];

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_HEADER, true);

echo "=== DEBUG CUSTOM ROLES API ===\n";
echo "URL: $url\n";
echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
echo "Headers: " . implode(', ', $headers) . "\n\n";

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Close cURL
curl_close($ch);

// Display results
echo "=== RESPONSE ===\n";
echo "HTTP Code: $httpCode\n";

if ($error) {
    echo "cURL Error: $error\n";
} else {
    // Split header and body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    echo "Headers:\n$header\n";
    echo "Body:\n";
    
    // Try to decode JSON response
    $jsonResponse = json_decode($body, true);
    if ($jsonResponse) {
        echo json_encode($jsonResponse, JSON_PRETTY_PRINT) . "\n";
        
        // Show validation errors if any
        if (isset($jsonResponse['errors'])) {
            echo "\n=== VALIDATION ERRORS ===\n";
            foreach ($jsonResponse['errors'] as $field => $errors) {
                echo "$field: " . implode(', ', $errors) . "\n";
            }
        }
    } else {
        echo $body . "\n";
    }
}

echo "\n=== TEST WITH ACCESS_LEVEL ===\n";

// Test dengan access_level eksplisit
$dataWithAccessLevel = [
    'role_name' => 'Test Role With Access Level',
    'description' => 'Test with explicit access level',
    'access_level' => 'employee'
];

echo "Data: " . json_encode($dataWithAccessLevel, JSON_PRETTY_PRINT) . "\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($dataWithAccessLevel));
curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCode2\n";
$jsonResponse2 = json_decode($response2, true);
if ($jsonResponse2) {
    echo json_encode($jsonResponse2, JSON_PRETTY_PRINT) . "\n";
} else {
    echo $response2 . "\n";
}

?>