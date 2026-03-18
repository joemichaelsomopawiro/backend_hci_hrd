<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetRequest;
use App\Models\Program;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GeneralAffairsController extends Controller
{
    /**
     * Get all budget requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $query = BudgetRequest::with(['program', 'requestedBy', 'approvedBy']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by program
        if ($request->has('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Get specific budget request
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request = BudgetRequest::with(['program', 'requestedBy', 'approvedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $request
        ]);
    }

    /**
     * Approve budget request
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $budgetRequest = BudgetRequest::findOrFail($id);

        if ($budgetRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Budget request is not pending'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'approved_amount' => 'required|numeric|min:0',
            'approval_notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'payment_schedule' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $budgetRequest->update([
            'status' => 'approved',
            'approved_amount' => $request->approved_amount,
            'approval_notes' => $request->approval_notes,
            'payment_method' => $request->payment_method,
            'payment_schedule' => $request->payment_schedule,
            'approved_by' => $user->id,
            'approved_at' => now()
        ]);

        // Notify requester
        $this->notifyRequester($budgetRequest, 'approved');

        return response()->json([
            'success' => true,
            'message' => 'Budget request approved successfully',
            'data' => $budgetRequest->load(['program', 'requestedBy', 'approvedBy'])
        ]);
    }

    /**
     * Reject budget request
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $budgetRequest = BudgetRequest::findOrFail($id);

        if ($budgetRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Budget request is not pending'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
            'rejection_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $budgetRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'rejection_notes' => $request->rejection_notes,
            'rejected_by' => $user->id,
            'rejected_at' => now()
        ]);

        // Notify requester
        $this->notifyRequester($budgetRequest, 'rejected');

        return response()->json([
            'success' => true,
            'message' => 'Budget request rejected',
            'data' => $budgetRequest->load(['program', 'requestedBy', 'rejectedBy'])
        ]);
    }

    /**
     * Process payment
     */
    public function processPayment(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $budgetRequest = BudgetRequest::findOrFail($id);

        if ($budgetRequest->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Budget request is not approved'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_receipt' => 'nullable|string',
            'payment_notes' => 'nullable|string',
            'payment_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $paymentReceipt = trim((string) ($request->payment_receipt ?? ''));
        if ($paymentReceipt === '' && !empty(trim((string) ($budgetRequest->approval_notes ?? '')))) {
            $notes = $budgetRequest->approval_notes;
            if (preg_match('#(general-affairs/receipts/[^\s]+\.(?:jpe?g|png|gif|webp|pdf))#i', $notes, $m)) {
                $paymentReceipt = trim(preg_replace('/[^\w.\/-]/', '', $m[1]));
            } elseif (preg_match('#https?://[^\s]+/storage/(general-affairs/receipts/[^\s]+)#i', $notes, $m)) {
                $paymentReceipt = $m[1];
            }
        }

        $budgetRequest->update([
            'status' => 'paid',
            'payment_receipt' => $paymentReceipt ?: $budgetRequest->payment_receipt,
            'payment_notes' => $request->payment_notes ?? $budgetRequest->payment_notes,
            'payment_date' => $request->payment_date ?? now(),
            'processed_by' => $user->id
        ]);

        // Notify requester (Producer)
        $this->notifyRequester($budgetRequest, 'paid');

        // Notify Producer bahwa dana telah diberikan (bisa lihat bukti transfer di Permohonan Dana Saya)
        if ($budgetRequest->requestedBy) {
            $amount = $budgetRequest->approved_amount ?? $budgetRequest->requested_amount;
            $programName = $budgetRequest->program?->name ?? 'program';
            Notification::create([
                'user_id' => $budgetRequest->requested_by,
                'type' => 'fund_released',
                'title' => 'Dana Telah Diberikan oleh General Affairs',
                'message' => "Dana sebesar Rp " . number_format($amount, 0, ',', '.') . " untuk {$programName} telah diberikan. Anda dapat melihat bukti transfer di menu Permohonan Dana Saya.",
                'data' => [
                    'budget_request_id' => $budgetRequest->id,
                    'program_id' => $budgetRequest->program_id,
                    'program_name' => $programName,
                    'amount' => $amount,
                    'payment_receipt' => $budgetRequest->payment_receipt,
                    'payment_date' => $budgetRequest->payment_date ?? now(),
                    'status' => 'paid'
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully. Producer has been notified.',
            'data' => $budgetRequest->load(['program', 'requestedBy', 'approvedBy'])
        ]);
    }

    /**
     * Upload payment receipt / bukti transfer (optional file)
     * POST /api/live-tv/general-affairs/upload-payment-receipt
     */
    public function uploadPaymentReceipt(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'General Affairs') {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $path = $file->store('general-affairs/receipts', 'public');
            $url = asset('storage/' . $path);

            return response()->json([
                'success' => true,
                'data' => [
                    'path' => $path,
                    'url' => $url,
                ],
                'message' => 'File uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve receipt file (bukti transfer) via API dengan auth — agar bisa diakses tanpa bergantung storage link.
     * GET /api/live-tv/general-affairs/receipts/file?path=general-affairs/receipts/xxx.jpg
     */
    public function showReceiptFile(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $path = $request->query('path');
        if (!$path || !is_string($path)) {
            return response()->json(['message' => 'Path required'], 400);
        }
        if (preg_match('#^https?://#i', $path) && !str_contains($path, 'general-affairs/receipts')) {
            return response()->json(['message' => 'Invalid path'], 400);
        }
        $path = $this->resolveReceiptPath($path);
        if (!$path) {
            \Log::warning('GeneralAffairs showReceiptFile: could not resolve path', ['path' => $request->query('path')]);
            return response()->json(['message' => 'Invalid path'], 400);
        }

        $fullPath = Storage::disk('public')->path($path);
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            \Log::warning('GeneralAffairs showReceiptFile: file not found', ['path' => $path, 'fullPath' => $fullPath]);
            return response()->json(['message' => 'File not found'], 404);
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        return response()->file($fullPath, ['Content-Type' => $mime]);
    }

    /**
     * Dapatkan URL sekali pakai untuk buka bukti transfer di tab baru (untuk production, bukan blob).
     * POST /api/live-tv/general-affairs/receipts/view-url { path: "general-affairs/receipts/xxx.jpg" }
     */
    public function getReceiptViewUrl(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $path = $request->input('path');
        if (!$path || !is_string($path)) {
            return response()->json(['message' => 'Path required'], 400);
        }
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');
        if (preg_match('#^https?://#i', $path) && !str_contains($path, 'general-affairs/receipts')) {
            return response()->json(['message' => 'Invalid path'], 400);
        }
        $resolved = $this->resolveReceiptPath($path);
        if (!$resolved) {
            return response()->json(['message' => 'Invalid path'], 400);
        }
        $fullPath = Storage::disk('public')->path($resolved);
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $token = Str::random(64);
        Cache::put('receipt_view:' . $token, $resolved, now()->addMinutes(5));
        $url = url('/api/live-tv/general-affairs/receipts/view?token=' . $token);

        return response()->json([
            'success' => true,
            'url' => $url,
        ]);
    }

    /**
     * Buka bukti transfer dengan token (tanpa auth — untuk new tab di production).
     * GET /api/live-tv/general-affairs/receipts/view?token=xxx
     */
    public function showReceiptByToken(Request $request)
    {
        $token = $request->query('token');
        if (!$token || !is_string($token)) {
            return response()->json(['message' => 'Token required'], 400);
        }
        $path = Cache::pull('receipt_view:' . $token);
        if (!$path) {
            return response()->json(['message' => 'Link expired or invalid'], 404);
        }
        $fullPath = Storage::disk('public')->path($path);
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        return response()->file($fullPath, ['Content-Type' => $mime]);
    }

    /**
     * Resolve path bukti ke path relatif storage (general-affairs/receipts/xxx).
     */
    private function resolveReceiptPath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = ltrim($path, '/');
        if (strpos($path, 'general-affairs/receipts/') === 0) {
            return $path;
        }
        if (preg_match('#/storage/(general-affairs/receipts/[^\s?#]+)#', $path, $m)) {
            return $m[1];
        }
        if (preg_match('#(general-affairs/receipts/[^\s]+)#', $path, $m)) {
            return preg_replace('/[^\w.\/-]/', '', $m[1]);
        }
        if (strpos($path, '/') === false && preg_match('/\.(jpe?g|png|gif|webp|pdf)$/i', $path)) {
            return 'general-affairs/receipts/' . $path;
        }
        if (preg_match('/([^\s\/\\\\]+\.(jpe?g|png|gif|webp|pdf))$/i', $path, $m)) {
            return 'general-affairs/receipts/' . $m[1];
        }
        if (preg_match('/([^\s\/\\\\]+\.(jpe?g|png|gif|webp|pdf))/i', $path, $m)) {
            return 'general-affairs/receipts/' . $m[1];
        }
        $base = basename($path);
        if (preg_match('/\.(jpe?g|png|gif|webp|pdf)$/i', $base)) {
            return 'general-affairs/receipts/' . $base;
        }
        return null;
    }

    /**
     * Get budget requests from Creative Work (permohonan dana setelah Producer approve)
     * GET /api/live-tv/general-affairs/budget-requests/from-creative-work
     */
    public function getCreativeWorkBudgetRequests(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'General Affairs') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = BudgetRequest::where('request_type', 'creative_work')
                ->with(['program', 'requestedBy', 'approvedBy', 'processedBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                // Default: show pending requests
                $query->where('status', 'pending');
            }

            // Filter by program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            $requests = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Creative work budget requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve budget requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget statistics
     */
    public function statistics(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if requested_amount column exists
        $hasRequestedAmount = \Schema::hasColumn('budget_requests', 'requested_amount');
        
        $stats = [
            'total_requests' => BudgetRequest::count(),
            'pending_requests' => BudgetRequest::where('status', 'pending')->count(),
            'approved_requests' => BudgetRequest::where('status', 'approved')->count(),
            'rejected_requests' => BudgetRequest::where('status', 'rejected')->count(),
            'paid_requests' => BudgetRequest::where('status', 'paid')->count(),
        ];
        
        if ($hasRequestedAmount) {
            $stats['total_requested_amount'] = BudgetRequest::sum('requested_amount');
            $stats['total_approved_amount'] = BudgetRequest::where('status', 'approved')->sum('approved_amount');
            $stats['total_paid_amount'] = BudgetRequest::where('status', 'paid')->sum('approved_amount');
            $stats['pending_amount'] = BudgetRequest::where('status', 'pending')->sum('requested_amount');
        } else {
            // Fallback if column doesn't exist
            $stats['total_requested_amount'] = 0;
            $stats['total_approved_amount'] = 0;
            $stats['total_paid_amount'] = 0;
            $stats['pending_amount'] = 0;
            \Log::warning('GeneralAffairsController::statistics - requested_amount column not found in budget_requests table');
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get budget requests by program
     */
    public function getByProgram($programId): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $requests = BudgetRequest::with(['requestedBy', 'approvedBy'])
            ->where('program_id', $programId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Notify requester about budget request status
     */
    private function notifyRequester($budgetRequest, $status)
    {
        $statusMessages = [
            'approved' => 'Budget request approved',
            'rejected' => 'Budget request rejected',
            'paid' => 'Payment processed'
        ];

        $statusDescriptions = [
            'approved' => "Your budget request for {$budgetRequest->program->name} has been approved",
            'rejected' => "Your budget request for {$budgetRequest->program->name} has been rejected",
            'paid' => "Payment for your budget request has been processed"
        ];

        Notification::create([
            'user_id' => $budgetRequest->requested_by,
            'type' => "budget_request_{$status}",
            'title' => $statusMessages[$status],
            'message' => $statusDescriptions[$status],
            'data' => [
                'budget_request_id' => $budgetRequest->id,
                'program_id' => $budgetRequest->program_id,
                'program_name' => $budgetRequest->program->name,
                'status' => $status
            ]
        ]);
    }
}











