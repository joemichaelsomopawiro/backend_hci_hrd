<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Database\QueryException;
use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Handle API exceptions
     */
    protected function handleApiException($request, Throwable $exception)
    {
        // Log the exception
        Log::error('API Exception: ' . $exception->getMessage(), [
            'exception' => $exception,
            'trace' => $exception->getTraceAsString(),
            'request' => $request->all(),
            'url' => $request->fullUrl(),
            'method' => $request->method()
        ]);

        // Handle specific exceptions
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
                'timestamp' => now()->toISOString()
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'timestamp' => now()->toISOString()
            ], 404);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'timestamp' => now()->toISOString()
            ], 401);
        }

        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
                'timestamp' => now()->toISOString()
            ], 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
                'timestamp' => now()->toISOString()
            ], 405);
        }

        if ($exception instanceof TooManyRequestsHttpException || $exception instanceof ThrottleRequestsException) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests',
                'timestamp' => now()->toISOString()
            ], 429);
        }

        if ($exception instanceof QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'timestamp' => now()->toISOString()
            ], 500);
        }

        // Generic server error
        // Jangan expose error details atau kode PHP ke frontend
        $message = 'Internal server error';
        
        // Hanya tampilkan error details jika APP_DEBUG=true (development only)
        if (config('app.debug')) {
            $message = $exception->getMessage();
        }
        
        return response()->json([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
            // Jangan include stack trace, file path, atau kode PHP
        ], 500);
    }
}