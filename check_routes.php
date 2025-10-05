<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Route;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 CHECKING MUSIC WORKFLOW ROUTES:\n\n";
    
    $routes = Route::getRoutes();
    
    foreach ($routes as $route) {
        $uri = $route->uri();
        $methods = $route->methods();
        
        if (strpos($uri, 'music-workflow') !== false) {
            echo "Method: " . implode('|', $methods) . " | URI: " . $uri . "\n";
        }
    }
    
    echo "\n🔍 CHECKING SPECIFIC ROUTE:\n";
    echo "Looking for: POST /api/music-workflow/submissions\n";
    
    $targetRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/music-workflow/submissions' && in_array('POST', $route->methods())) {
            $targetRoute = $route;
            break;
        }
    }
    
    if ($targetRoute) {
        echo "✅ Route found: " . $targetRoute->uri() . "\n";
        echo "Methods: " . implode('|', $targetRoute->methods()) . "\n";
        echo "Controller: " . $targetRoute->getActionName() . "\n";
    } else {
        echo "❌ Route not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}












