<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddCorsHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Handle preflight requests
        $origin = $request->header('Origin');
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:5173',
            'http://localhost:8000',
            'http://127.0.0.1:8000'
        ];

        if ($request->isMethod('OPTIONS')) {
            $responseOrigin = in_array($origin, $allowedOrigins) ? $origin : $allowedOrigins[0];
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $responseOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);
        
        // Add CORS headers to all responses
        $origin = $request->header('Origin');
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:5173',
            'http://localhost:8000',
            'http://127.0.0.1:8000'
        ];

        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        } else {
            // Fallback for security, or set to a default if appropriate
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigins[0]);
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        
        return $response;
    }
}






