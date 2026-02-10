<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrEditorRevisionNote;
use App\Models\PrEpisode;
use App\Models\PrProduksiWork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrProducerController extends Controller
{
    /**
     * Get all pending revision requests from Editor
     */
    public function getRevisionRequests()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $revisionNotes = PrEditorRevisionNote::with([
            'episode',
            'episode.program',
            'editorWork',
            'creator'
        ])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revisionNotes
        ]);
    }

    /**
     * Approve revision note and update episode status to 'waiting_for_revision'
     */
    public function approveRevision($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $revisionNote = PrEditorRevisionNote::with(['episode', 'editorWork'])->find($id);

        if (!$revisionNote) {
            return response()->json([
                'success' => false,
                'message' => 'Revision note not found'
            ], 404);
        }

        // Update revision note status
        $revisionNote->update([
            'status' => 'approved_by_producer',
            'approved_by' => $user->id,
            'approved_at' => now()
        ]);

        // Update episode status to waiting for revision
        $episode = $revisionNote->episode;
        if ($episode) {
            $episode->update([
                'status' => 'waiting_for_revision'
            ]);
        }

        // Update production work status to needs_revision
        $productionWork = PrProduksiWork::where('pr_episode_id', $episode->id)->first();
        if ($productionWork) {
            $productionWork->update([
                'status' => 'needs_revision'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Revision request approved successfully',
            'data' => $revisionNote
        ]);
    }

    /**
     * Reject revision note
     */
    public function rejectRevision($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $revisionNote = PrEditorRevisionNote::find($id);

        if (!$revisionNote) {
            return response()->json([
                'success' => false,
                'message' => 'Revision note not found'
            ], 404);
        }

        // Update revision note status to rejected
        $revisionNote->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Revision request rejected',
            'data' => $revisionNote
        ]);
    }
}
