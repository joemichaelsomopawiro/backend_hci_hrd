<?php

// Simple test to view the exact error
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/pr/design-grafis/works');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer test-token'  // Add token if needed
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response:\n$response\n";
