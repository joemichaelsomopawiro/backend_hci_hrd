<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PrActivityLogService;
use App\Constants\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PrHistoryController extends Controller
{
    protected $activityLogService;

    public function __construct(PrActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Get combined history for a program
     */
    public function getProgramHistory(int $programId): JsonResponse
    {
        $user = Auth::user();

        // Access Control
        $allowedRoles = [
            Role::PROGRAM_MANAGER,
            Role::DISTRIBUTION_MANAGER,
            Role::PRODUCER
        ];

        // Use Role::inArray to handle variations safely
        if (!Role::inArray($user->role, $allowedRoles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Only Managers and Producers can view history.'
            ], 403);
        }

        $history = $this->activityLogService->getProgramHistory($programId);

        return response()->json([
            'status' => 'success',
            'data' => $history
        ]);
    }
}
