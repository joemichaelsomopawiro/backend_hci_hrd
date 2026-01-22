<?php

// Test RoleHierarchyService functionality
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RoleHierarchyService;

echo "===== Testing RoleHierarchyService =====\n\n";

// Test 1: Program Manager
echo "Test 1: Program Manager Accessible Roles\n";
$roles = RoleHierarchyService::getRolesAccessibleByUser('Program Manager');
echo "Accessible Roles: " . json_encode($roles, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Producer
echo "Test 2: Producer Accessible Roles\n";
$roles = RoleHierarchyService::getRolesAccessibleByUser('Producer');
echo "Accessible Roles: " . json_encode($roles, JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Distribution Manager
echo "Test 3: Distribution Manager Accessible Roles\n";
$roles = RoleHierarchyService::getRolesAccessibleByUser('Distribution Manager');
echo "Accessible Roles: " . json_encode($roles, JSON_PRETTY_PRINT) . "\n\n";

// Test 4: Creative (should have no subordinates)
echo "Test 4: Creative Accessible Roles (should be empty)\n";
$roles = RoleHierarchyService::getRolesAccessibleByUser('Creative');
echo "Accessible Roles: " . json_encode($roles, JSON_PRETTY_PRINT) . "\n\n";

// Test 5: Access validation
echo "Test 5: Access Validation\n";
$tests = [
    ['Program Manager', 'Producer', true],
    ['Program Manager', 'Creative', true],
    ['Producer', 'Creative', true],
    ['Producer', 'Quality Control', true],
    ['Producer', 'Broadcasting', false],
    ['Distribution Manager', 'Broadcasting', true],
    ['Distribution Manager', 'Quality Control', true],
    ['Creative', 'Producer', false],
];

foreach ($tests as [$userRole, $targetRole, $expected]) {
    $result = RoleHierarchyService::canAccessRoleData($userRole, $targetRole);
    $status = ($result === $expected) ? '✓ PASS' : '✗ FAIL';
    echo "{$status}: {$userRole} -> {$targetRole} = " . ($result ? 'true' : 'false') .
        " (expected: " . ($expected ? 'true' : 'false') . ")\n";
}

echo "\n===== Tests Complete =====\n";
