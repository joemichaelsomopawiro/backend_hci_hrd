<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateGARole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Cek apakah user sudah login
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi'
            ], 401);
        }

        // Cek apakah user memiliki role GA atau Admin
        if (!in_array($user->role, ['General Affairs', 'Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Hanya GA/Admin yang dapat mengakses endpoint ini.'
            ], 403);
        }

        return $next($request);
    }
}
