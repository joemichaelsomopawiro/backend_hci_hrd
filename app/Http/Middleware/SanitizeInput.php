<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     * Sanitize string inputs to prevent XSS attacks
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();

        // Sanitize string inputs
        array_walk_recursive($input, function (&$value, $key) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace("\0", '', $value);
                
                // Basic XSS prevention (for non-HTML fields)
                // Note: For HTML content, use HTMLPurifier instead
                if (!in_array($key, ['html_content', 'description', 'content', 'script_content', 'storyboard_data'])) {
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
        });

        $request->merge($input);

        return $next($request);
    }
}

