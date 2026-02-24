<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrProduksiWork;
use App\Models\PrEpisode;
use App\Models\ProductionEquipment;
use App\Models\ShootingRunSheet;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryItem;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\PrWorkflowStep;

class PrProduksiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Production') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // AUTO-SYNC: Ensure all episodes with completed Step 4 have a Produksi Work
            // This matches the same logic in PrPromosiController
            $eligibleEpisodes = \App\Models\PrEpisodeWorkflowProgress::where('workflow_step', 4)
                ->where('status', 'completed')
                ->pluck('episode_id');

            foreach ($eligibleEpisodes as $episodeId) {
                try {
                    // Check if produksi work exists
                    $exists = PrProduksiWork::where('pr_episode_id', $episodeId)->exists();

                    if (!$exists) {
                        // Get creative work to link if available
                        $creativeWork = \App\Models\PrCreativeWork::where('pr_episode_id', $episodeId)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        PrProduksiWork::create([
                            'pr_episode_id' => $episodeId,
                            'pr_creative_work_id' => $creativeWork ? $creativeWork->id : null,
                            'status' => 'pending',
                            'created_by' => $user->id,
                            'shooting_notes' => 'Auto-created from dashboard sync'
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Auto-sync failed for episode $episodeId: " . $e->getMessage());
                }
            }

            $query = PrProduksiWork::with([
                'episode.program',
                'episode.files', // Load files for the episode (scripts etc)
                'creativeWork',
                'createdBy',
                'equipmentLoan.loanItems.inventoryItem',
                'editorWork'
            ]);
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Produksi works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Production') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrProduksiWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when pending'], 400);
            }

            $work->acceptWork($user->id);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'createdBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Production') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrProduksiWork::findOrFail($id);

            // Relaxed ownership check: Allow any Production user to update.
            // If we want to track who updated, we could add updated_by field later.
            // For now, if user has role 'Production', they can edit.

            if ($work->status === 'pending' && $work->created_by !== $user->id) {
                // Optional: Take ownership if pending? Or just allow edit.
                // $work->created_by = $user->id; 
                // $work->save();
            }

            $work->update($request->all());

            // Check if shooting files are uploaded, if so, maybe trigger next step logic?
            // Original `uploadShootingResults` triggered PrEditorWork creation.
            // We should replicate that check here if it's a completion update.

            if ($request->has('shooting_file_links') && !empty($request->shooting_file_links) && $work->status !== 'completed') {
                $work->update(['status' => 'completed']);

                // Auto-complete Step 5 in workflow
                $workflowStep = PrWorkflowStep::where('pr_episode_id', $work->pr_episode_id)
                    ->where('step_number', 5)
                    ->first();

                if ($workflowStep && !$workflowStep->is_completed) {
                    $workflowStep->markAsCompleted($user->id, 'Produksi completed with file upload');
                }
            }

            // Always ensure Editor work is ready when Production work is updated
            // This handles the case where revision was requested and Production is resubmitting
            $this->ensureEditorWorkReady($work->pr_episode_id, $user->id);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Production work updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Production') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.inventory_item_id' => 'required|exists:inventory_items,id',
                'equipment_list.*.quantity' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrProduksiWork::findOrFail($id);

            // Access check: User must be creator or it's pending (or just Production role generally?)
            // Allowing any Production generic user to edit for now as per role check above.

            // Update Work Record
            if ($work->created_by !== $user->id) {
                $work->created_by = $user->id;
            }

            $work->equipment_list = $request->equipment_list; // Keep JSON for backup/legacy
            $work->status = 'equipment_requested';
            $work->save();

            // Create or Update Equipment Loan
            $loan = EquipmentLoan::firstOrNew(['pr_produksi_work_id' => $work->id]);

            // Check if loan is already processed by Art
            if ($loan->exists && $loan->status !== 'pending') {
                // If Art already approved/rejected, we shouldn't modify the loan items freely.
                // Ideally throw error or just notify. 
                // For now, assuming "equipment_requested" typically happens before approval.
                // If it's already approved, we might need a "Revision" flow.
                // Let's assume we can't update if not pending.
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Cannot update equipment request because loan status is ' . $loan->status], 400);
            }

            $loan->borrower_id = $user->id;
            $loan->status = 'pending';
            // Loan date/Return date are set by Art when picking up/returning
            $loan->save();

            // Sync Items
            // Delete existing items for this loan
            EquipmentLoanItem::where('equipment_loan_id', $loan->id)->delete();

            foreach ($request->equipment_list as $item) {
                EquipmentLoanItem::create([
                    'equipment_loan_id' => $loan->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity' => $item['quantity'],
                    'notes' => $item['notes'] ?? null
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'data' => $work->fresh(['equipmentLoan.loanItems']), 'message' => 'Equipment requested successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function uploadShootingResults(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Production') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'shooting_file_links' => 'nullable|string', // Links are optional if files are provided
                'files.*' => 'nullable|file|max:10240', // 10MB max per file? Adjust as needed.
                'files' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PrProduksiWork::findOrFail($id);

            if ($work->created_by !== $user->id && $work->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if ($work->created_by !== $user->id) {
                $work->created_by = $user->id;
            }

            // Handle File Uploads
            $uploadedPaths = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('shooting_results/' . $work->id, 'public');
                    // Create full URL or relative path? Ideally full URL for frontend.
                    // But storage path is relative to storage/app/public.
                    // Use Storage::url($path) to get web accessible URL.
                    $uploadedPaths[] = \Illuminate\Support\Facades\Storage::url($path);
                }
            }

            // Combine with existing links
            $existingLinks = $request->shooting_file_links ? explode(',', $request->shooting_file_links) : [];
            // Clean/Trim
            $existingLinks = array_map('trim', $existingLinks);
            $existingLinks = array_filter($existingLinks);

            $allLinks = array_merge($existingLinks, $uploadedPaths);
            $finalLinksString = implode(', ', $allLinks);

            // Require at least one file or link
            if (empty($finalLinksString)) {
                return response()->json(['success' => false, 'message' => 'Please provide at least one link or upload a file.'], 422);
            }

            $updateData = [
                'shooting_file_links' => $finalLinksString,
                'shooting_notes' => $request->shooting_notes,
                'status' => 'completed'
            ];

            $work->update($updateData);

            // Access PrEditorWork to update its status if it was in revision
            $this->ensureEditorWorkReady($work->pr_episode_id, $user->id);

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Shooting results uploaded successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/pr/produksi/available-equipment
     * Get list of equipment with availability status
     */
    public function getAvailableEquipment(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Production') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $equipment = InventoryItem::where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'photo_url' => $item->photo_url,
                        'total_quantity' => $item->total_quantity,
                        'available_quantity' => $item->available_quantity,
                        'is_available' => $item->available_quantity > 0,
                        'status' => $item->status,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Available equipment retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/pr/produksi/works/{id}/complete
     * Mark production work as completed and auto-complete Step 5
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            if (!$user || $user->role !== 'Production') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrProduksiWork::with('episode')->findOrFail($id);

            if ($work->created_by !== $user->id && $work->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Validate that shooting files have been uploaded
            if (empty($work->shooting_file_links)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot complete work without uploading shooting files'
                ], 422);
            }

            // Mark work as completed
            $work->completeWork($user->id, $request->input('completion_notes'));

            // Check if both production and promotion are complete, then update workflow step 5
            $this->checkAndUpdateWorkflowStep5($work->episode);

            // Auto-create PrEditorWork for next step
            $this->ensureEditorWorkReady($work->pr_episode_id, $user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'equipmentLoan']),
                'message' => 'Production work completed successfully.',
                'workflow_updated' => true
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper to ensure Editor work is ready for editing/review.
     * If status is 'revision_requested', reset to 'revised'.
     * If not exists, create as 'draft'.
     */
    private function ensureEditorWorkReady($episodeId, $userId)
    {
        $editorWork = \App\Models\PrEditorWork::where('pr_episode_id', $episodeId)->first();

        if ($editorWork) {
            // If the work was revision requested, we move it to 'revised' status
            if ($editorWork->status === 'revision_requested') {
                $editorWork->update([
                    'status' => 'revised', // Set to 'revised' to distinguish from fresh 'draft' work
                    'files_complete' => true, // Assuming now it is complete as per production submission
                    'file_notes' => null
                ]);
            }
        } else {
            // Auto-create PrEditorWork if not exists
            \App\Models\PrEditorWork::firstOrCreate(
                ['pr_episode_id' => $episodeId, 'work_type' => 'main_episode'],
                ['status' => 'draft', 'created_by' => $userId]
            );
        }
    }

    /**
     * Check if both promotion and production are complete for an episode,
     * and update workflow step 5 accordingly. Also create Step 6 work records.
     */
    private function checkAndUpdateWorkflowStep5($episode)
    {
        if (!$episode)
            return;

        // Check if promotion work is complete
        $promotionWork = \App\Models\PrPromotionWork::where('pr_episode_id', $episode->id)
            ->where('status', 'completed')
            ->first();

        // Check if production work is complete
        $productionWork = PrProduksiWork::where('pr_episode_id', $episode->id)
            ->where('status', 'completed')
            ->first();

        // If both are complete, update workflow step 5 and create Step 6 records
        if ($promotionWork && $productionWork) {
            $progress = \App\Models\PrEpisodeWorkflowProgress::where('episode_id', $episode->id)
                ->where('workflow_step', 5)
                ->first();

            if ($progress) {
                $progress->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }

            // Auto-sync: Create Step 6 work records (Editor, Editor Promosi, Design Grafis)
            // Only create if they don't already exist

            // 1. Create Editor work
            \App\Models\PrEditorWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_production_work_id' => $productionWork->id,
                    'assigned_to' => null, // Can be assigned later
                    'status' => 'pending',
                    'files_complete' => false
                ]
            );

            // 2. Create Editor Promosi work
            \App\Models\PrEditorPromosiWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_editor_work_id' => null, // Will be linked when Editor work is created
                    'pr_promotion_work_id' => $promotionWork->id,
                    'assigned_to' => null,
                    'status' => 'pending'
                ]
            );

            // 3. Create Design Grafis work
            \App\Models\PrDesignGrafisWork::firstOrCreate(
                ['pr_episode_id' => $episode->id],
                [
                    'pr_production_work_id' => $productionWork->id,
                    'pr_promotion_work_id' => $promotionWork->id,
                    'assigned_to' => null,
                    'status' => 'pending'
                ]
            );
        }
    }
}
