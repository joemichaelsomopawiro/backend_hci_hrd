<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RoleHierarchyService;
use Symfony\Component\HttpFoundation\Response;

class ReadOnlyRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $userRole = $user->role;
        
        // Jika user adalah VP President atau President Director
        if (RoleHierarchyService::isExecutiveRole($userRole)) {
            // Hanya izinkan method GET (read-only)
            if (!$request->isMethod('GET')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Read-only access for executive roles.'
                ], 403);
            }
        }
        
        // Jika user adalah HR, izinkan semua akses
        if (RoleHierarchyService::isHrManager($userRole)) {
            return $next($request);
        }
        
        // Jika user adalah role read-only lainnya, hanya izinkan GET
        if (RoleHierarchyService::isReadOnlyRole($userRole) && !$request->isMethod('GET')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Read-only access only.'
            ], 403);
        }

        return $next($request);
    }
}