<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrManagerDistribusiQcWork;
use App\Models\PrEditorWork;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrManagerDistribusiQcController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Manager Distribusi', 'Manager Program', 'Producer', 'Super Admin', 'distribution_manager', 'Distribution Manager', 'manager_distribusi', 'program_manager', 'manager_program', 'producer'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // Sync: Find episodes where Editor has completed editing (status is pending_qc)
            // But actually editor changes status to pending_qc upon submit.
            // Let's create QC works for editor works that are pending_qc or completed 
            // but don't have a QC work entry yet.
            $editorWorks = PrEditorWork::whereIn('status', ['pending_qc', 'completed'])
                ->where('work_type', 'main_episode')
                ->get();

            foreach ($editorWorks as $editorWork) {
                // Check if QC work exists
                $exists = PrManagerDistribusiQcWork::where('pr_episode_id', $editorWork->pr_episode_id)->exists();

                if (!$exists) {
                    // Create QC Work
                    PrManagerDistribusiQcWork::create([
                        'pr_episode_id' => $editorWork->pr_episode_id,
                        'status' => 'pending',
                        'recieved_at' => now(), // Mark when it entered QC
                    ]);
                }
            }

            $query = PrManagerDistribusiQcWork::with(['episode.program', 'createdBy', 'reviewedBy']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json(['success' => true, 'data' => $works, 'message' => 'Manager Distribusi QC works retrieved successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function acceptWork(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Manager Distribusi', 'Manager Program', 'Producer', 'Super Admin', 'distribution_manager', 'Distribution Manager', 'manager_distribusi', 'program_manager', 'manager_program', 'producer'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrManagerDistribusiQcWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Work can only be accepted when pending'], 400);
            }

            $work->markAsInProgress();
            $work->update(['reviewed_by' => $user->id]);

            return response()->json(['success' => true, 'data' => $work->fresh(['episode', 'reviewedBy']), 'message' => 'Work accepted successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !in_array($user->role, ['Manager Distribusi', 'Manager Program', 'Producer', 'Super Admin', 'distribution_manager', 'Distribution Manager', 'manager_distribusi', 'program_manager', 'manager_program', 'producer'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrManagerDistribusiQcWork::with(['episode.program', 'createdBy', 'reviewedBy'])->findOrFail($id);

            // Fetch Editor's work (main episode) to provide links
            $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                ->where('work_type', 'main_episode')
                ->first();

            if ($editorWork) {
                $work->editor_file_path = $editorWork->file_path; // Frontend can use this link
            }

            return response()->json(['success' => true, 'data' => $work]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateChecklistItem(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['Manager Distribusi', 'Manager Program', 'Producer', 'Super Admin', 'distribution_manager', 'Distribution Manager', 'manager_distribusi', 'program_manager', 'manager_program', 'producer'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $request->validate([
                'item_key' => 'required|string', // e.g., 'video_episode'
                'status' => 'required|in:approved,revision',
                'note' => 'nullable|string',
            ]);

            $work = PrManagerDistribusiQcWork::findOrFail($id);

            $checklist = $work->qc_checklist ?? [];
            $checklist[$request->item_key] = [
                'status' => $request->status,
                'note' => $request->note,
                'checked_at' => now()->toIso8601String(),
                'checked_by' => $user->name // or id
            ];

            $work->qc_checklist = $checklist;
            $work->save();

            // Handle Revision Logic
            if ($request->status === 'revision') {
                $this->handleRevisionRequest($work, $request->item_key, $request->note);
            }

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'Item updated']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function handleRevisionRequest(PrManagerDistribusiQcWork $work, string $itemKey, ?string $note)
    {
        // Update Editor Work (main episode)
        $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
            ->where('work_type', 'main_episode')
            ->first();
        if ($editorWork) {
            $newNote = "[QC Revision: " . $itemKey . "] " . $note;
            $currentNotes = $editorWork->review_notes ? $editorWork->review_notes . "\n" : "";

            $editorWork->update([
                'status' => 'needs_revision',
                'review_notes' => $currentNotes . $newNote
            ]);
        }
    }

    public function finish(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !in_array($user->role, ['Manager Distribusi', 'Manager Program', 'Producer', 'Super Admin', 'distribution_manager', 'Distribution Manager', 'manager_distribusi', 'program_manager', 'manager_program', 'producer'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $work = PrManagerDistribusiQcWork::findOrFail($id);
            $checklist = $work->qc_checklist ?? [];

            // Define required items (based on user request, Manager Distribusi checks Video Episode)
            $requiredItems = ['video_episode'];

            $allApproved = true;
            foreach ($checklist as $item) {
                if (($item['status'] ?? '') !== 'approved') {
                    $allApproved = false;
                    break;
                }
            }

            if (!$allApproved || empty($checklist)) {
                return response()->json(['success' => false, 'message' => 'All items must be approved before finishing.'], 400);
            }

            DB::beginTransaction();

            $work->update([
                'status' => 'completed',
                'qc_completed_at' => now(),
                'reviewed_by' => $user->id
            ]);

            // Update Editor Work Status to completed
            $editorWork = PrEditorWork::where('pr_episode_id', $work->pr_episode_id)
                ->where('work_type', 'main_episode')
                ->first();
            if ($editorWork) {
                $editorWork->update(['status' => 'completed']);

                // Check if step 6 is totally finished
                $this->checkStep6Completion($editorWork->pr_episode_id);
            }

            // Auto-create Broadcasting work so it shows up in broadcasting dashboard
            \App\Models\PrBroadcastingWork::firstOrCreate(
                ['pr_episode_id' => $work->pr_episode_id, 'work_type' => 'main_episode'],
                ['status' => 'preparing']
            );

            DB::commit();

            return response()->json(['success' => true, 'data' => $work->fresh(), 'message' => 'QC Work Finished']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function checkStep6Completion($episodeId)
    {
        $episode = \App\Models\PrEpisode::findOrFail($episodeId);

        $editorCompleted = \App\Models\PrEditorWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        $editorPromosiCompleted = \App\Models\PrEditorPromosiWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        $designGrafisCompleted = \App\Models\PrDesignGrafisWork::where('pr_episode_id', $episodeId)
            ->where('status', 'completed')
            ->exists();

        // If all three are completed, mark Step 6 as completed
        if ($editorCompleted && $editorPromosiCompleted && $designGrafisCompleted) {
            $episode->update([
                'workflow_step' => 7, // Move to next step
                'status' => 'step_6_completed'
            ]);
        }
    }
}
