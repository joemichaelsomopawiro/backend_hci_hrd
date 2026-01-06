<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip untuk route yang tidak perlu auth
        if ($request->is('api/auth/login') || 
            $request->is('api/auth/register') || 
            $request->is('api/auth/forgot-password*') ||
            $request->is('api/auth/reset-password') ||
            $request->is('api/public/*')) {
            return $next($request);
        }

        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Unauthenticated' : 'Unauthorized',
                'code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Get token dari request
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Unauthenticated' : 'Unauthorized',
                'code' => 'TOKEN_MISSING'
            ], 401);
        }

        // Find token di database
        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Unauthenticated' : 'Unauthorized',
                'code' => 'TOKEN_NOT_FOUND'
            ], 401);
        }

        // Check if token is expired
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            // Delete expired token
            $accessToken->delete();

            Log::warning('Token expired', [
                'user_id' => $user->id ?? null,
                'token_id' => $accessToken->id,
                'reason' => 'expired',
                'expires_at' => $accessToken->expires_at,
                'last_used_at' => $accessToken->last_used_at,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Authentication required' : 'Unauthorized',
                'code' => 'TOKEN_EXPIRED'
            ], 401);
        }

        // Check inactivity timeout (optional)
        $inactivityTimeout = config('sanctum.inactivity_timeout');
        if ($inactivityTimeout && $accessToken->last_used_at) {
            $lastActivity = $accessToken->last_used_at;
            $inactivityDuration = now()->diffInSeconds($lastActivity);
            
            if ($inactivityDuration > $inactivityTimeout) {
                // Delete token karena inactivity
                $accessToken->delete();

                Log::warning('Token invalidated due to inactivity', [
                    'user_id' => $user->id ?? null,
                    'token_id' => $accessToken->id,
                    'reason' => 'inactivity_timeout',
                    'last_used_at' => $accessToken->last_used_at,
                    'inactivity_seconds' => $inactivityDuration,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? 'Authentication required' : 'Unauthorized',
                    'code' => 'INACTIVITY_TIMEOUT'
                ], 401);
            }
        }

        // Update last_used_at
        $accessToken->update([
            'last_used_at' => now()
        ]);

        // Add token info to request untuk digunakan di controller
        $request->merge([
            'token_info' => [
                'expires_at' => $accessToken->expires_at,
                'remaining_seconds' => $accessToken->expires_at 
                    ? max(0, now()->diffInSeconds($accessToken->expires_at, false))
                    : null,
                'is_expiring_soon' => $accessToken->expires_at 
                    ? now()->diffInSeconds($accessToken->expires_at, false) <= config('sanctum.refresh_threshold', 300)
                    : false
            ]
        ]);

        return $next($request);
    }
}

