<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AttendanceRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $employeeId = $request->input('employee_id');
        
        if (!$employeeId) {
            return $next($request);
        }
        
        // Create unique key for this employee's attendance attempts
        $key = 'attendance_attempt_' . $employeeId . '_' . date('Y-m-d');
        
        // Get current attempt count
        $attempts = Cache::get($key, 0);
        
        // Allow maximum 5 attempts per employee per day
        if ($attempts >= 5) {
            Log::warning('Rate limit exceeded for attendance', [
                'employee_id' => $employeeId,
                'attempts' => $attempts,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json([
                'errors' => [
                    'rate_limit' => 'Terlalu banyak percobaan absensi. Silakan coba lagi nanti atau hubungi admin.'
                ]
            ], 429);
        }
        
        // Increment attempt count (expires at end of day)
        $secondsUntilMidnight = strtotime('tomorrow') - time();
        Cache::put($key, $attempts + 1, $secondsUntilMidnight);
        
        return $next($request);
    }
}