<?php
/**
 * Simple API Tester - Windows Compatible (No curl needed!)
 * 
 * Usage: php test_api_simple.php
 */


$baseUrl = 'http://backend_hci_hrd.test'; // Laragon default domain for this project (or http://localhost/backend_hci_hrd/public)

// IMPORTANT: Make sure Laravel dev server is running!
// Run in separate terminal: php artisan serve

// Test credentials (from your database)
$testUser = [
    'login' => 'test_pm_reassign@test.com', // API uses 'login' field, not 'email'
    'password' => 'password'
];

echo "========================================\n";
echo "  TASK REASSIGNMENT API - SIMPLE TEST\n";
echo "========================================\n\n";

// Function to make API request
function apiCall($method, $endpoint, $data = null, $token = null) {
    global $baseUrl;
    
    $url = $baseUrl . $endpoint;
    $ch = curl_init($url);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'code' => 0];
    }
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Test 1: Login
echo "TEST 1: Login as Manager Program\n";
echo "Email: {$testUser['login']}\n"; // Updated key to match array

$loginResponse = apiCall('POST', '/api/auth/login', $testUser);

if ($loginResponse['code'] !== 200) {
    echo "❌ Login FAILED!\n";
    echo "Response: " . json_encode($loginResponse, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$token = $loginResponse['body']['data']['token'] ?? $loginResponse['body']['token'] ?? null;
if (!$token) {
    echo "❌ No token in response!\n";
    echo "Full Response Body:\n";
    print_r($loginResponse['body']);
    exit(1);
}

echo "✅ Login SUCCESS! Token received.\n\n";

// Test 2: Get All Tasks
echo "TEST 2: View All Tasks (Read-Only)\n";
$tasksResponse = apiCall('GET', '/api/tasks/all', null, $token);

if ($tasksResponse['code'] === 200) {
    $taskCount = count($tasksResponse['body']['data'] ?? []);
    echo "✅ SUCCESS! Found $taskCount tasks\n";
    
    if ($taskCount > 0) {
        echo "\nFirst few tasks:\n";
        foreach (array_slice($tasksResponse['body']['data'], 0, 3) as $task) {
            echo "  - [{$task['task_type']}] ID: {$task['id']}, Status: {$task['status']}\n";
            echo "    Assigned to: {$task['assigned_to']['name']}\n";
        }
    }
} else {
    echo "❌ FAILED! Code: {$tasksResponse['code']}\n";
    echo json_encode($tasksResponse['body'], JSON_PRETTY_PRINT) . "\n";
}
echo "\n";

// Test 3: Get Statistics
echo "TEST 3: Get Task Statistics\n";
$statsResponse = apiCall('GET', '/api/tasks/statistics', null, $token);

if ($statsResponse['code'] === 200) {
    $stats = $statsResponse['body']['data'];
    echo "✅ SUCCESS!\n";
    echo "  Total tasks: {$stats['total_tasks']}\n";
    echo "  By status: " . json_encode($stats['by_status']) . "\n";
    echo "  Reassigned tasks: {$stats['reassigned_tasks']}\n";
} else {
    echo "❌ FAILED!\n";
}
echo "\n";

// Test 4: Test Reassignment (if there are tasks)
echo "TEST 4: Task Reassignment\n";

if ($taskCount > 0) {
    // Get first editor work task
    $editorWork = null;
    foreach ($tasksResponse['body']['data'] as $task) {
        if ($task['task_type'] === 'editor_work' && $task['status'] !== 'completed') {
            $editorWork = $task;
            break;
        }
    }
    
    if ($editorWork) {
        echo "Found editor work: ID {$editorWork['id']}\n";
        echo "Currently assigned to: {$editorWork['assigned_to']['name']} (ID: {$editorWork['assigned_to']['id']})\n";
        
        // Get available users
        $usersResponse = apiCall('GET', '/api/tasks/available-users?task_type=editor_work', null, $token);
        
        if ($usersResponse['code'] === 200) {
            $availableUsers = $usersResponse['body']['data'];
            echo "Available users: " . count($availableUsers) . "\n";
            
            // Find different user
            $newUser = null;
            foreach ($availableUsers as $user) {
                if ($user['id'] != $editorWork['assigned_to']['id']) {
                    $newUser = $user;
                    break;
                }
            }
            
            if ($newUser) {
                echo "\n🔄 Attempting to reassign to: {$newUser['name']} (ID: {$newUser['id']})\n";
                
                $reassignResponse = apiCall('POST', '/api/tasks/reassign', [
                    'task_type' => 'editor_work',
                    'task_id' => $editorWork['id'],
                    'new_user_id' => $newUser['id'],
                    'reason' => 'Testing task reassignment feature - Original user was sick'
                ], $token);
                
                if ($reassignResponse['code'] === 200) {
                    echo "✅ REASSIGNMENT SUCCESS!\n";
                    echo "  Notifications sent: {$reassignResponse['body']['data']['notifications_sent']}\n";
                    echo "  Reassignment ID: {$reassignResponse['body']['data']['reassignment_id']}\n";
                    
                    // Test: Get reassignment history
                    echo "\n📋 Checking reassignment history...\n";
                    $historyResponse = apiCall('GET', "/api/tasks/editor_work/{$editorWork['id']}/reassignment-history", null, $token);
                    
                    if ($historyResponse['code'] === 200) {
                        $history = $historyResponse['body']['data'];
                        echo "✅ History retrieved: " . count($history) . " reassignment(s)\n";
                        
                        foreach ($history as $h) {
                            echo "  From: {$h['from_user']['name']} → To: {$h['to_user']['name']}\n";
                            echo "  By: {$h['reassigned_by']['name']}\n";
                            echo "  Reason: {$h['reason']}\n";
                        }
                    }
                } else {
                    echo "❌ REASSIGNMENT FAILED!\n";
                    echo "Response: " . json_encode($reassignResponse['body'], JSON_PRETTY_PRINT) . "\n";
                }
            } else {
                echo "⚠️  No alternative user found for reassignment test\n";
            }
        }
    } else {
        echo "⚠️  No editor work tasks found for reassignment test\n";
    }
} else {
    echo "⚠️  No tasks available for reassignment test\n";
}

echo "\n========================================\n";
echo "  TESTING COMPLETE!\n";
echo "========================================\n";
