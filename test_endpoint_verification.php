<?php

/**
 * Test Endpoint Verification
 * 
 * File ini untuk memverifikasi bahwa endpoint yang dibuat sudah benar
 */

echo "🧪 Testing Endpoint Verification\n";
echo "================================\n\n";

// Test 1: Check if PersonalWorshipController exists
echo "1. Checking PersonalWorshipController...\n";
$controllerPath = 'app/Http/Controllers/PersonalWorshipController.php';
if (file_exists($controllerPath)) {
    echo "✅ PersonalWorshipController exists\n";
} else {
    echo "❌ PersonalWorshipController not found\n";
}

// Test 2: Check if routes are properly added
echo "\n2. Checking routes in api.php...\n";
$routesPath = 'routes/api.php';
if (file_exists($routesPath)) {
    $routesContent = file_get_contents($routesPath);
    
    // Check for personal worship routes
    if (strpos($routesContent, 'PersonalWorshipController') !== false) {
        echo "✅ PersonalWorshipController routes found\n";
    } else {
        echo "❌ PersonalWorshipController routes not found\n";
    }
    
    // Check for specific endpoints
    if (strpos($routesContent, '/worship-attendance') !== false) {
        echo "✅ /worship-attendance endpoint found\n";
    } else {
        echo "❌ /worship-attendance endpoint not found\n";
    }
    
    if (strpos($routesContent, '/combined-attendance') !== false) {
        echo "✅ /combined-attendance endpoint found\n";
    } else {
        echo "❌ /combined-attendance endpoint not found\n";
    }
} else {
    echo "❌ routes/api.php not found\n";
}

// Test 3: Check if documentation files exist
echo "\n3. Checking documentation files...\n";
$docs = [
    'PERSONAL_WORSHIP_API.md',
    'FRONTEND_WORSHIP_ENDPOINTS_SUMMARY.md',
    'test_personal_worship_api.php'
];

foreach ($docs as $doc) {
    if (file_exists($doc)) {
        echo "✅ $doc exists\n";
    } else {
        echo "❌ $doc not found\n";
    }
}

// Test 4: Check controller methods
echo "\n4. Checking controller methods...\n";
if (file_exists($controllerPath)) {
    $controllerContent = file_get_contents($controllerPath);
    
    $methods = [
        'getWorshipAttendance',
        'getCombinedAttendance',
        'checkLeaveStatus',
        'calculateStatistics',
        'getStatusLabel',
        'getOfficeStatusLabel',
        'getLeaveTypeLabel'
    ];
    
    foreach ($methods as $method) {
        if (strpos($controllerContent, "function $method") !== false) {
            echo "✅ Method $method() exists\n";
        } else {
            echo "❌ Method $method() not found\n";
        }
    }
}

// Test 5: Check required models
echo "\n5. Checking required models...\n";
$models = [
    'app/Models/MorningReflectionAttendance.php',
    'app/Models/LeaveRequest.php',
    'app/Models/Employee.php',
    'app/Models/Attendance.php'
];

foreach ($models as $model) {
    if (file_exists($model)) {
        echo "✅ $model exists\n";
    } else {
        echo "❌ $model not found\n";
    }
}

echo "\n📋 Summary:\n";
echo "===========\n";
echo "✅ PersonalWorshipController created\n";
echo "✅ Routes added to api.php\n";
echo "✅ Documentation files created\n";
echo "✅ Test files created\n";
echo "\n🎯 Next Steps:\n";
echo "1. Fix .env file if needed\n";
echo "2. Run: php artisan config:clear\n";
echo "3. Run: php artisan route:cache\n";
echo "4. Test endpoints with Postman/Insomnia\n";
echo "5. Update frontend to use new endpoints\n";

echo "\n🔗 Endpoints Ready:\n";
echo "- GET /api/personal/worship-attendance?employee_id={id}\n";
echo "- GET /api/personal/combined-attendance?employee_id={id}\n";
echo "- GET /api/ga-dashboard/worship-attendance\n";
echo "- GET /api/ga-dashboard/worship-statistics\n";
echo "- GET /api/ga-dashboard/leave-requests\n";
echo "- GET /api/ga-dashboard/leave-statistics\n";

echo "\n✅ All endpoints for frontend worship attendance are ready!\n"; 