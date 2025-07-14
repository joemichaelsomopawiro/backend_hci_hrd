<?php
// debug_calendar_endpoints.php
// Script untuk debug endpoint calendar

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Debug Calendar Endpoints ===\n\n";

try {
    // Cek semua routes yang mengandung 'calendar'
    $routes = Route::getRoutes();
    $calendarRoutes = [];
    
    foreach ($routes as $route) {
        if (strpos($route->uri(), 'calendar') !== false) {
            $calendarRoutes[] = [
                'method' => $route->methods()[0],
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName()
            ];
        }
    }
    
    echo "Calendar Routes Found:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($calendarRoutes as $route) {
        echo "Method: " . $route['method'] . "\n";
        echo "URI: " . $route['uri'] . "\n";
        echo "Name: " . ($route['name'] ?? 'N/A') . "\n";
        echo "Action: " . $route['action'] . "\n";
        echo str_repeat("-", 40) . "\n";
    }
    
    // Cek apakah ada route POST /api/calendar
    $postCalendarRoute = null;
    foreach ($calendarRoutes as $route) {
        if ($route['method'] === 'POST' && $route['uri'] === 'api/calendar') {
            $postCalendarRoute = $route;
            break;
        }
    }
    
    if ($postCalendarRoute) {
        echo "✅ POST /api/calendar route found!\n";
        echo "Action: " . $postCalendarRoute['action'] . "\n";
    } else {
        echo "❌ POST /api/calendar route NOT found!\n";
    }
    
    // Cek middleware untuk route POST /api/calendar
    echo "\n=== Middleware Check ===\n";
    foreach ($routes as $route) {
        if ($route->methods()[0] === 'POST' && $route->uri() === 'api/calendar') {
            $middleware = $route->middleware();
            echo "POST /api/calendar middleware:\n";
            foreach ($middleware as $mw) {
                echo "- " . $mw . "\n";
            }
            break;
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?> 