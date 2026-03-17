<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrCreativeWork;
use App\Constants\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PrGaController extends Controller
{
    /**
     * Get approved budgets for GA Dashboard
     * GET /api/pr/ga/budgets
     */
    public function getBudgets(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::GENERAL_AFFAIRS, Role::HR, Role::FINANCE])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only GA can access this endpoint.'
                ], 403);
            }

            // Get all creative works where budget is approved
            // We'll return both pending and processed so the frontend can filter
            // Or we can let frontend request specific ones. 
            // For now, let's just send all approved budgets
            $works = PrCreativeWork::with([
                    'episode.program'
                ])
                ->where('budget_approved', true)
                ->orderBy('created_at', 'desc')
                ->get();

            // Transform data for frontend
            $budgets = $works->map(function ($work) {
                // Determine status based on flags
                $status = 'approved';
                if ($work->budget_processed_by_ga) {
                    $status = 'transferred';
                }

                // If budget_data has specific purpose or notes
                $purpose = 'Budget Produksi';
                if (!empty($work->budget_review_notes)) {
                    $purpose = $work->budget_review_notes;
                }

                return [
                    'id' => $work->id,
                    'work_id' => $work->id,
                    'episode_id' => $work->pr_episode_id,
                    'program_name' => $work->episode?->program?->name ?? 'Unknown Program',
                    'episode_number' => $work->episode?->episode_number ?? 0,
                    'title' => $work->episode?->title ?? 'Unknown Title',
                    'amount' => $work->total_budget,
                    'purpose' => $purpose,
                    'status' => $status,
                    'transfer_date' => $work->budget_processed_at,
                    'budget_processing_notes' => $work->budget_processing_notes,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $budgets,
                'message' => 'GA Budgets retrieved successfully'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving budgets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Budget Transfer
     * POST /api/pr/ga/budgets/{id}/transfer
     */
    public function processTransfer(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !Role::inArray($user->role, [Role::GENERAL_AFFAIRS, Role::HR, Role::FINANCE])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PrCreativeWork::findOrFail($id);

            if (!$work->budget_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot transfer. Budget is not approved yet.'
                ], 400);
            }

            if ($work->budget_processed_by_ga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Budget has already been processed and transferred.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'transfer_type' => 'required|string',
                'amount_transferred' => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Construct processing notes to save the transfer details
            $notesArr = [
                'Method: ' . $request->transfer_type,
                'Amount: IDR ' . number_format($request->amount_transferred, 0, ',', '.')
            ];

            if ($request->filled('bank_name')) {
                $notesArr[] = 'Bank/Platform: ' . $request->bank_name;
            }
            if ($request->filled('account_number')) {
                $notesArr[] = 'Account No: ' . $request->account_number;
            }
            if ($request->filled('account_name')) {
                $notesArr[] = 'Account Name: ' . $request->account_name;
            }
            if ($request->filled('notes')) {
                $notesArr[] = 'Notes: ' . $request->notes;
            }

            $processingNotes = implode(' | ', $notesArr);

            $work->update([
                'budget_processed_by_ga' => true,
                'budget_processed_at' => now(),
                'budget_processed_by' => $user->id,
                'budget_processing_notes' => $processingNotes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer processed successfully recorded.',
                'data' => $work
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing transfer: ' . $e->getMessage()
            ], 500);
        }
    }
}
