<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramProposal;
use App\Models\ProgramRegular;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProgramProposalController extends Controller
{
    /**
     * Get all program proposals
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProgramProposal::with(['programRegular', 'createdBy', 'reviewedBy']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by program
        if ($request->has('program_id')) {
            $query->where('program_regular_id', $request->program_id);
        }
        
        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('proposal_title', 'like', "%{$search}%")
                  ->orWhere('proposal_description', 'like', "%{$search}%");
            });
        }
        
        $proposals = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $proposals,
            'message' => 'Proposals retrieved successfully'
        ]);
    }

    /**
     * Get proposal by ID
     */
    public function show(int $id): JsonResponse
    {
        $proposal = ProgramProposal::with([
            'programRegular',
            'createdBy',
            'reviewedBy'
        ])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $proposal,
            'message' => 'Proposal retrieved successfully'
        ]);
    }

    /**
     * Create new proposal
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'program_regular_id' => 'nullable|exists:program_regular,id',
            'spreadsheet_id' => 'required|string|max:255',
            'spreadsheet_url' => 'nullable|url',
            'sheet_name' => 'nullable|string|max:255',
            'proposal_title' => 'required|string|max:255',
            'proposal_description' => 'nullable|string',
            'format_type' => 'required|in:mingguan,kwartal',
            'kwartal_data' => 'nullable|array',
            'schedule_options' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $proposalData = $request->all();
            $proposalData['created_by'] = auth()->id();
            $proposalData['status'] = 'draft';
            
            $proposal = ProgramProposal::create($proposalData);
            
            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create proposal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update proposal
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $proposal = ProgramProposal::findOrFail($id);
        
        // Only allow update if draft or needs_revision
        if (!in_array($proposal->status, ['draft', 'needs_revision'])) {
            return response()->json([
                'success' => false,
                'message' => 'Can only update draft or needs_revision proposals'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'spreadsheet_id' => 'sometimes|string|max:255',
            'spreadsheet_url' => 'nullable|url',
            'sheet_name' => 'nullable|string|max:255',
            'proposal_title' => 'sometimes|string|max:255',
            'proposal_description' => 'nullable|string',
            'format_type' => 'sometimes|in:mingguan,kwartal',
            'kwartal_data' => 'nullable|array',
            'schedule_options' => 'nullable|array'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $proposal->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update proposal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit proposal for review
     */
    public function submit(int $id): JsonResponse
    {
        $proposal = ProgramProposal::findOrFail($id);
        
        if ($proposal->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Can only submit draft proposals'
            ], 400);
        }
        
        try {
            $proposal->submitForReview();
            
            // Send notification to Manager Program
            \App\Models\Notification::create([
                'user_id' => $proposal->programRegular->manager_id ?? auth()->id(),
                'type' => 'proposal_submitted',
                'title' => 'Proposal Baru Diajukan',
                'message' => "Proposal '{$proposal->proposal_title}' telah diajukan untuk review",
                'data' => [
                    'proposal_id' => $proposal->id,
                    'program_id' => $proposal->program_regular_id
                ]
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal submitted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit proposal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve proposal (Manager Program only)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can approve proposals'
            ], 403);
        }
        
        $proposal = ProgramProposal::findOrFail($id);
        
        if (!in_array($proposal->status, ['submitted', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Can only approve submitted or under_review proposals'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'review_notes' => 'nullable|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $proposal->approve($user->id, $request->review_notes);
            
            // Send notification to creator
            \App\Models\Notification::create([
                'user_id' => $proposal->created_by,
                'type' => 'proposal_approved',
                'title' => 'Proposal Disetujui',
                'message' => "Proposal '{$proposal->proposal_title}' telah disetujui",
                'data' => [
                    'proposal_id' => $proposal->id,
                    'review_notes' => $request->review_notes
                ]
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve proposal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject proposal (Manager Program only)
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can reject proposals'
            ], 403);
        }
        
        $proposal = ProgramProposal::findOrFail($id);
        
        if (!in_array($proposal->status, ['submitted', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Can only reject submitted or under_review proposals'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'review_notes' => 'required|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $proposal->reject($user->id, $request->review_notes);
            
            // Send notification to creator
            \App\Models\Notification::create([
                'user_id' => $proposal->created_by,
                'type' => 'proposal_rejected',
                'title' => 'Proposal Ditolak',
                'message' => "Proposal '{$proposal->proposal_title}' ditolak",
                'data' => [
                    'proposal_id' => $proposal->id,
                    'review_notes' => $request->review_notes
                ]
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject proposal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request revision (Manager Program only)
     */
    public function requestRevision(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Manager Program can request revision'
            ], 403);
        }
        
        $proposal = ProgramProposal::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'review_notes' => 'required|string|max:1000'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $proposal->requestRevision($user->id, $request->review_notes);
            
            // Send notification to creator
            \App\Models\Notification::create([
                'user_id' => $proposal->created_by,
                'type' => 'proposal_needs_revision',
                'title' => 'Proposal Perlu Revisi',
                'message' => "Proposal '{$proposal->proposal_title}' perlu direvisi",
                'data' => [
                    'proposal_id' => $proposal->id,
                    'review_notes' => $request->review_notes
                ]
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Revision requested successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request revision',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete proposal
     */
    public function destroy(int $id): JsonResponse
    {
        $proposal = ProgramProposal::findOrFail($id);
        
        // Only creator or manager can delete
        if ($proposal->created_by !== auth()->id() && !in_array(auth()->user()->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this proposal'
            ], 403);
        }
        
        // Cannot delete approved proposals
        if ($proposal->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete approved proposals'
            ], 400);
        }
        
        try {
            $proposal->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Proposal deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete proposal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

