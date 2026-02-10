<?php

namespace App\Http\Controllers\Api\Pr;

use App\Models\PrEditorWork;
use App\Models\PrEditorRevisionNote;
use App\Models\PrEpisode;
use App\Models\PrProduksiWork;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PrEditorController extends Controller
{
    /**
     * Get list of editor works with filters
     */
    public function index(Request $request)
    {
        $query = PrEditorWork::with([
            'episode.program',
            'episode.productionWork',
            'episode.creativeWork',
            'assignedUser',
            'revisionNotes'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assigned user
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $works = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $works
        ]);
    }

    /**
     * Get detail of specific editor work
     */
    public function show($id)
    {
        $work = PrEditorWork::with([
            'episode.program',
            'episode.creativeWork',
            'episode.productionWork', // Load via episode
            'assignedUser',
            'revisionNotes.creator',
            'revisionNotes.approver'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $work
        ]);
    }

    /**
     * Start working on an episode
     */
    public function start($episodeId)
    {
        try {
            $episode = PrEpisode::findOrFail($episodeId);

            $work = PrEditorWork::where('pr_episode_id', $episodeId)->first();

            if (!$work) {
                return response()->json([
                    'success' => false,
                    'message' => 'Editor work not found for this episode'
                ], 404);
            }

            $work->update([
                'status' => 'checking_files',
                'assigned_to' => Auth::id(),
                'started_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Started working on episode',
                'data' => $work->load(['episode', 'productionWork'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update file completeness check
     */
    public function updateFileCheck(Request $request, $id)
    {
        $request->validate([
            'files_complete' => 'required|boolean'
        ]);

        try {
            $work = PrEditorWork::findOrFail($id);

            $work->update([
                'files_complete' => $request->files_complete,
                'status' => $request->files_complete ? 'in_progress' : 'checking_files'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File check updated',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update file check: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create revision note
     */
    public function createRevisionNote(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $work = PrEditorWork::findOrFail($id);

            $revisionNote = PrEditorRevisionNote::create([
                'pr_editor_work_id' => $work->id,
                'pr_episode_id' => $work->pr_episode_id,
                'created_by' => Auth::id(),
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            // Update editor work status
            $work->update([
                'status' => 'waiting_producer_approval'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Revision note created and sent to Producer',
                'data' => $revisionNote->load(['creator'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create revision note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update work progress (notes)
     */
    /**
     * Update editor work details
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'work_type' => 'nullable|string',
            'file_complete' => 'nullable|boolean',
            'file_notes' => 'nullable|string',
            'editing_notes' => 'nullable|string',
            'file_path' => 'nullable|string',
            'file_name' => 'nullable|string',
            'file_size' => 'nullable|integer',
        ]);

        try {
            $work = PrEditorWork::findOrFail($id);

            $updateData = $request->only([
                'work_type',
                'file_complete',
                'file_notes',
                'editing_notes',
                'file_path',
                'file_name',
                'file_size'
            ]);

            // If file_path is provided, update status to in_progress if currently draft
            if ($request->has('file_path') && !empty($request->file_path)) {
                if ($work->status === 'draft' || $work->status === 'checking_files') {
                    $updateData['status'] = 'in_progress';
                }
            }

            $work->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Work updated successfully',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit completed work
     */
    public function submit($id)
    {
        try {
            DB::beginTransaction();

            $work = PrEditorWork::findOrFail($id);

            // Validate that video link is uploaded
            if (empty($work->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload edited video file/link before submitting'
                ], 400);
            }

            $work->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            // Check if all Step 6 roles are completed
            $this->checkStep6Completion($work->pr_episode_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Work submitted successfully',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if all Step 6 works are completed
     */
    private function checkStep6Completion($episodeId)
    {
        $episode = PrEpisode::findOrFail($episodeId);

        $editorCompleted = PrEditorWork::where('pr_episode_id', $episodeId)
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
