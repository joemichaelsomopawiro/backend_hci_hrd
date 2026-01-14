<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrProgram;
use App\Models\PrProgramRevision;
use App\Services\PrRevisionService;
use App\Services\PrNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PrRevisionController extends Controller
{
    protected $revisionService;
    protected $notificationService;

    public function __construct(
        PrRevisionService $revisionService,
        PrNotificationService $notificationService
    ) {
        $this->revisionService = $revisionService;
        $this->notificationService = $notificationService;
    }

    /**
     * Request revisi
     */
    public function requestRevision(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            $program = PrProgram::findOrFail($programId);

            $validator = Validator::make($request->all(), [
                'revision_type' => 'required|in:concept,production,editing,distribution',
                'revision_reason' => 'required|string',
                'after_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $revision = $this->revisionService->requestRevision(
                $program,
                $request->all(),
                $user->id
            );

            // Send notification
            $this->notificationService->notifyRevisionRequested(
                $program,
                $request->revision_type,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Revisi berhasil diminta',
                'data' => $revision->load(['program', 'requester'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revision history
     */
    public function getRevisionHistory(Request $request, $programId): JsonResponse
    {
        try {
            $program = PrProgram::findOrFail($programId);
            $revisionType = $request->get('revision_type');

            $revisions = $this->revisionService->getRevisionHistory($program, $revisionType)
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $revisions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve revisi
     */
    public function approveRevision(Request $request, $revisionId): JsonResponse
    {
        try {
            $user = Auth::user();
            $revision = PrProgramRevision::findOrFail($revisionId);

            // Hanya Manager Program yang bisa approve revisi
            if ($user->role !== 'Manager Program') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $revision = $this->revisionService->approveRevision(
                $revision,
                $user->id,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Revisi berhasil disetujui',
                'data' => $revision->load(['program', 'reviewer'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject revisi
     */
    public function rejectRevision(Request $request, $revisionId): JsonResponse
    {
        try {
            $user = Auth::user();
            $revision = PrProgramRevision::findOrFail($revisionId);

            // Hanya Manager Program yang bisa reject revisi
            if ($user->role !== 'Manager Program') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $revision = $this->revisionService->rejectRevision(
                $revision,
                $user->id,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Revisi ditolak',
                'data' => $revision->load(['program', 'reviewer'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
