<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Exception;

abstract class BaseController extends Controller
{
    /**
     * Success response
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ], $code);
    }

    /**
     * Error response
     */
    protected function errorResponse(string $message = 'Error', $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString()
        ], $code);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $exception->errors(),
            'timestamp' => now()->toISOString()
        ], 422);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], 404);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], 401);
    }

    /**
     * Forbidden response
     */
    protected function forbiddenResponse(string $message = 'Access denied'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], 403);
    }

    /**
     * Server error response
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], 500);
    }

    /**
     * Handle exceptions
     */
    protected function handleException(Exception $exception): JsonResponse
    {
        // Log the exception
        Log::error('API Exception: ' . $exception->getMessage(), [
            'exception' => $exception,
            'trace' => $exception->getTraceAsString()
        ]);

        // Handle specific exceptions
        if ($exception instanceof ValidationException) {
            return $this->validationErrorResponse($exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->notFoundResponse('Resource not found');
        }

        if ($exception instanceof AuthenticationException) {
            return $this->unauthorizedResponse('Authentication required');
        }

        if ($exception instanceof AuthorizationException) {
            return $this->forbiddenResponse('Access denied');
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->notFoundResponse('Endpoint not found');
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse('Method not allowed', null, 405);
        }

        if ($exception instanceof TooManyRequestsHttpException || $exception instanceof ThrottleRequestsException) {
            return $this->errorResponse('Too many requests', null, 429);
        }

        if ($exception instanceof QueryException) {
            return $this->serverErrorResponse('Database error occurred');
        }

        // Generic server error
        return $this->serverErrorResponse('An unexpected error occurred');
    }

    /**
     * Paginated response
     */
    protected function paginatedResponse($data, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'total_pages' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'has_more' => $data->hasMorePages()
            ],
            'timestamp' => now()->toISOString()
        ]);
    }
}
