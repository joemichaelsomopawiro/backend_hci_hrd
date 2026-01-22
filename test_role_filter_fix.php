<?php

// Test case sensitivity fix
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RoleHierarchyService;
use App\Constants\Role;

echo "===== Testing Role Filter Fix =====\n\n";

// Test case sensitivity
$testCases = [
    // [userRole, targetRole, expected]
    ['Program Manager', 'Producer', true],
    ['Program Manager', 'producer', true], // lowercase should work now
    ['Program Manager', 'PRODUCER', true], // uppercase should work
    ['Program Manager', 'Creative', true],
    ['Program Manager', 'creative', true],
    ['Producer', 'Creative', true],
    ['Producer', 'creative', true],
    ['Producer', 'Quality Control', true],
    ['Producer', 'quality control', true],
    ['Producer', 'Broadcasting', false], // Producer shouldn't access Broadcasting
    ['Producer', 'broadcasting', false],
    ['Distribution Manager', 'Broadcasting', true],
    ['Distribution Manager', 'broadcasting', true],
    ['Distribution Manager', 'Quality Control', true],
    ['Creative', 'Producer', false],
    ['creative', 'Producer', false], // lowercase user role
];

echo "Test Case Sensitivity & Normalization:\n";
echo "--------------------------------------\n";

foreach ($testCases as [$userRole, $targetRole, $expected]) {
    $result = RoleHierarchyService::canAccessRoleData($userRole, $targetRole);
    $status = ($result === $expected) ? '✓ PASS' : '✗ FAIL';
    $resultStr = $result ? 'true' : 'false';
    $expectedStr = $expected ? 'true' : 'false';

    echo sprintf(
        "%s: %-20s can access %-20s = %-5s (expected: %s)\n",
        $status,
        "'{$userRole}'",
        "'{$targetRole}'",
        $resultStr,
        $expectedStr
    );
}

echo "\n===== Test Complete =====\n";
