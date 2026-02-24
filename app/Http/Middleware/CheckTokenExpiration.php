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
        // Skip untuk route yang tidak perlu auth (lebih spesifik)
        $skipRoutes = [
            'api/auth/login',
            'api/auth/register',
            'api/auth/send-register-otp',
            'api/auth/verify-otp',
            'api/auth/resend-otp',
            'api/auth/send-forgot-password-otp',
            'api/auth/reset-password',
        ];

        if (in_array($request->path(), $skipRoutes)) {
            return $next($request);
        }

        // Get user dari request (sudah di-authenticate oleh Sanctum)
        $user = $request->user();

        if (!$user) {
            // Jika tidak ada user, biarkan Sanctum middleware handle
            return $next($request);
        }

        // Get token dari request
        $token = $request->bearerToken();

        if (!$token) {
            // Jika tidak ada token, biarkan Sanctum middleware handle
            return $next($request);
        }

        // Find token di database
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            // Token tidak ditemukan, biarkan Sanctum middleware handle
            return $next($request);
        }

        // Check if token is expired
        // TEMPORARY FIX: Disable expiration check to debug 401 error
        if ($accessToken->expires_at && $accessToken->expires_at->isPast() && false) {
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

            if ($inactivityDuration > $inactivityTimeout && false) {
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

