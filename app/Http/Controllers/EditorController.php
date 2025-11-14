<?php

namespace App\Http\Controllers;

use App\Models\ProgramEpisode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Editor Controller - Complete Workflow
 * 
 * Workflow:
 * 1. Receive notification tugas dari sistem (episode post shooting)
 * 2. Check kelengkapan file syuting
 * 3. Start editing
 * 4. Upload draft (optional, internal review)
 * 5. Upload final file
 * 6. Submit ke QC
 * 7. Handle revision dari QC
 */
class EditorController extends Controller
{
    /**
     * Get episodes assigned to editor (pending editing)
     * GET /api/editor/episodes/pending
     */
    public function getPendingEpisodes(Request $request): JsonResponse
    {
        try {
            $query = ProgramEpisode::with(['programRegular'])
                ->where('status', 'post_production')
                ->whereNull('editing_started_at');

            // Filter by program
            if ($request->has('program_regular_id')) {
                $query->where('program_regular_id', $request->program_regular_id);
            }

            $episodes = $query->orderBy('air_date', 'asc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Pending episodes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my editing tasks
     * GET /api/editor/my-tasks
     */
    public function getMyTasks(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $tasks = [
                'pending_start' => ProgramEpisode::where('status', 'post_production')
                    ->whereNull('editing_started_at')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'in_progress' => ProgramEpisode::where('editing_started_by', $user->id)
                    ->where('editing_status', 'in_progress')
                    ->orderBy('air_date', 'asc')
                    ->get(),
                'pending_revision' => ProgramEpisode::where('status', 'revision')
                    ->where('editing_started_by', $user->id)
                    ->orderBy('qc_revision_requested_at', 'desc')
                    ->get(),
                'completed_this_week' => ProgramEpisode::where('editing_completed_by', $user->id)
                    ->whereBetween('editing_completed_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->orderBy('editing_completed_at', 'desc')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => 'Tasks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check file completeness
     * GET /api/editor/episodes/{id}/check-files
     */
    public function checkFileCompleteness(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            // Check if raw files exist
            if (empty($episode->raw_file_urls)) {
                return response()->json([
                    'success' => false,
                    'complete' => false,
                    'message' => 'No raw files uploaded from production',
                    'missing_items' => ['raw_files']
                ], 400);
            }

            $issues = [];
            
            // Check each requirement
            if (empty($episode->script)) {
                $issues[] = 'Script not available';
            }
            
            if (empty($episode->rundown)) {
                $issues[] = 'Rundown not available';
            }

            if (empty($episode->shooting_notes)) {
                $issues[] = 'Shooting notes not available';
            }

            $isComplete = empty($issues);

            return response()->json([
                'success' => true,
                'complete' => $isComplete,
                'raw_files' => $episode->raw_file_urls,
                'issues' => $issues,
                'message' => $isComplete ? 'All files complete' : 'Some items missing'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start editing
     * POST /api/editor/episodes/{id}/start-editing
     */
    public function startEditing(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            // Check if already started
            if ($episode->editing_started_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Editing already started'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Mark as started
            $episode->update([
                'editing_status' => 'in_progress',
                'editing_started_at' => now(),
                'editing_started_by' => auth()->id(),
                'editing_notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Editing started successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error starting editing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload draft for internal review (optional)
     * POST /api/editor/episodes/{id}/upload-draft
     */
    public function uploadDraft(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'draft_file' => 'nullable|file|mimes:mp4,mov,avi,mkv|max:512000', // Max 500MB
                'draft_url' => 'nullable|url',
                'version' => 'required|string|max:50',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload file if provided
            $draftUrl = $request->draft_url;
            if ($request->hasFile('draft_file')) {
                $path = $request->file('draft_file')->store('editor/drafts', 'public');
                $draftUrl = Storage::url($path);
            }

            // Store draft info
            $drafts = $episode->editing_drafts ?? [];
            $drafts[] = [
                'version' => $request->version,
                'url' => $draftUrl,
                'notes' => $request->notes,
                'uploaded_at' => now(),
                'uploaded_by' => auth()->id()
            ];

            $episode->update([
                'editing_status' => 'draft',
                'editing_drafts' => $drafts
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Draft uploaded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error uploading draft: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete editing & upload final file
     * POST /api/editor/episodes/{id}/complete
     */
    public function completeEditing(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'final_file' => 'nullable|file|mimes:mp4,mov,avi,mkv|max:1024000', // Max 1GB
                'final_url' => 'nullable|url',
                'completion_notes' => 'required|string|max:2000',
                'duration_minutes' => 'required|integer|min:1',
                'file_size_mb' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload final file
            $finalUrl = $request->final_url;
            if ($request->hasFile('final_file')) {
                $path = $request->file('final_file')->store('editor/final', 'public');
                $finalUrl = Storage::url($path);
            }

            if (empty($finalUrl)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Final file URL is required'
                ], 400);
            }

            // Update episode
            $episode->update([
                'editing_status' => 'completed',
                'final_file_url' => $finalUrl,
                'editing_completion_notes' => $request->completion_notes,
                'edited_duration_minutes' => $request->duration_minutes,
                'final_file_size_mb' => $request->file_size_mb,
                'editing_completed_at' => now(),
                'editing_completed_by' => auth()->id()
            ]);

            // Notify QC
            // TODO: Implement notification

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Editing completed successfully. Submitted to QC.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error completing editing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle revision dari QC
     * POST /api/editor/episodes/{id}/handle-revision
     */
    public function handleRevision(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            // Check if there's revision request
            if ($episode->status !== 'revision') {
                return response()->json([
                    'success' => false,
                    'message' => 'No revision requested for this episode'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:acknowledge,reupload',
                'revised_file' => 'nullable|file|mimes:mp4,mov,avi,mkv|max:1024000',
                'revised_url' => 'nullable|url',
                'revision_notes' => 'required|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->action === 'acknowledge') {
                // Just acknowledge, will reupload later
                $episode->update([
                    'editing_status' => 'in_progress',
                    'revision_acknowledged_at' => now(),
                    'revision_acknowledged_by' => auth()->id()
                ]);

                $message = 'Revision acknowledged. Please reupload when ready.';
            } else {
                // Reupload revised file
                $revisedUrl = $request->revised_url;
                if ($request->hasFile('revised_file')) {
                    $path = $request->file('revised_file')->store('editor/revisions', 'public');
                    $revisedUrl = Storage::url($path);
                }

                if (empty($revisedUrl)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Revised file URL is required when reuploading'
                    ], 400);
                }

                // Track revision history
                $revisions = $episode->editing_revisions ?? [];
                $revisions[] = [
                    'revision_number' => count($revisions) + 1,
                    'url' => $revisedUrl,
                    'notes' => $request->revision_notes,
                    'revised_at' => now(),
                    'revised_by' => auth()->id()
                ];

                $episode->update([
                    'editing_status' => 'completed',
                    'final_file_url' => $revisedUrl,
                    'editing_revisions' => $revisions,
                    'editing_completion_notes' => $request->revision_notes,
                    'editing_completed_at' => now(),
                    'editing_completed_by' => auth()->id(),
                    'status' => 'post_production' // Reset to post_production for QC
                ]);

                // Notify QC again
                // TODO: Implement notification

                $message = 'Revised file uploaded successfully. Resubmitted to QC.';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error handling revision: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics
     * GET /api/editor/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $stats = [
                'pending_editing' => ProgramEpisode::where('status', 'post_production')
                    ->whereNull('editing_started_at')
                    ->count(),
                'in_progress' => ProgramEpisode::where('editing_started_by', $user->id)
                    ->where('editing_status', 'in_progress')
                    ->count(),
                'completed_today' => ProgramEpisode::where('editing_completed_by', $user->id)
                    ->whereDate('editing_completed_at', today())
                    ->count(),
                'completed_this_week' => ProgramEpisode::where('editing_completed_by', $user->id)
                    ->whereBetween('editing_completed_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                'completed_this_month' => ProgramEpisode::where('editing_completed_by', $user->id)
                    ->whereMonth('editing_completed_at', now()->month)
                    ->whereYear('editing_completed_at', now()->year)
                    ->count(),
                'pending_revision' => ProgramEpisode::where('status', 'revision')
                    ->where('editing_started_by', $user->id)
                    ->count(),
                'average_editing_time_hours' => 0, // TODO: Calculate from started_at to completed_at
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
