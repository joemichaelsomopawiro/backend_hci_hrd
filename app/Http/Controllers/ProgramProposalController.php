<?php

namespace App\Http\Controllers;

use App\Models\ProgramProposal;
use App\Models\ProgramRegular;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProgramProposalController extends Controller
{
    /**
     * Display a listing of proposals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProgramProposal::with([
                'programRegular.productionTeam',
                'createdBy',
                'reviewedBy'
            ]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by program
            if ($request->has('program_regular_id')) {
                $query->where('program_regular_id', $request->program_regular_id);
            }

            // Filter by format type
            if ($request->has('format_type')) {
                $query->where('format_type', $request->format_type);
            }

            // Search
            if ($request->has('search')) {
                $query->where('proposal_title', 'like', '%' . $request->search . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $proposals = $query->paginate($request->get('per_page', 15));

            // Add additional info
            $proposals->getCollection()->transform(function ($proposal) {
                $proposal->full_spreadsheet_url = $proposal->full_spreadsheet_url;
                $proposal->embedded_url = $proposal->embedded_url;
                $proposal->needs_sync = $proposal->needsSync();
                return $proposal;
            });

            return response()->json([
                'success' => true,
                'data' => $proposals,
                'message' => 'Proposals retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving proposals: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created proposal
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'program_regular_id' => 'nullable|exists:program_regular,id',
                'spreadsheet_id' => 'required|string',
                'spreadsheet_url' => 'nullable|url',
                'sheet_name' => 'nullable|string',
                'proposal_title' => 'required|string|max:255',
                'proposal_description' => 'nullable|string',
                'format_type' => 'required|in:mingguan,kwartal',
                'kwartal_data' => 'nullable|array',
                'schedule_options' => 'nullable|array',
                'auto_sync' => 'boolean',
                'created_by' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $proposal = ProgramProposal::create($request->all());

            // Sync from spreadsheet if auto_sync is enabled
            if ($proposal->auto_sync) {
                $proposal->syncFromSpreadsheet();
            }

            $proposal->load(['programRegular', 'createdBy']);

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified proposal
     */
    public function show(string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::with([
                'programRegular.productionTeam.producer',
                'createdBy',
                'reviewedBy'
            ])->findOrFail($id);

            $proposal->full_spreadsheet_url = $proposal->full_spreadsheet_url;
            $proposal->embedded_url = $proposal->embedded_url;
            $proposal->needs_sync = $proposal->needsSync();

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving proposal: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified proposal
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            // Cannot update if approved or rejected
            if (in_array($proposal->status, ['approved', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update proposal with status: ' . $proposal->status
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'spreadsheet_id' => 'sometimes|required|string',
                'spreadsheet_url' => 'nullable|url',
                'sheet_name' => 'nullable|string',
                'proposal_title' => 'sometimes|required|string|max:255',
                'proposal_description' => 'nullable|string',
                'format_type' => 'sometimes|required|in:mingguan,kwartal',
                'kwartal_data' => 'nullable|array',
                'schedule_options' => 'nullable|array',
                'auto_sync' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $proposal->update($request->all());
            $proposal->load(['programRegular', 'createdBy']);

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified proposal
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            // Cannot delete if approved
            if ($proposal->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete approved proposal'
                ], 422);
            }

            $proposal->delete();

            return response()->json([
                'success' => true,
                'message' => 'Proposal deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync proposal data from Google Spreadsheet
     */
    public function syncFromSpreadsheet(string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            $success = $proposal->syncFromSpreadsheet();

            if ($success) {
                return response()->json([
                    'success' => true,
                    'data' => $proposal,
                    'message' => 'Proposal synced successfully from spreadsheet',
                    'last_synced_at' => $proposal->last_synced_at
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync proposal from spreadsheet'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit proposal for review
     */
    public function submitForReview(string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            if ($proposal->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft proposals can be submitted'
                ], 422);
            }

            $proposal->submitForReview();

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal submitted for review successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark proposal as under review
     */
    public function markAsUnderReview(Request $request, string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reviewer_id' => 'required|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($proposal->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted proposals can be marked as under review'
                ], 422);
            }

            $proposal->markAsUnderReview($request->reviewer_id);

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal marked as under review'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve proposal
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reviewer_id' => 'required|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!in_array($proposal->status, ['submitted', 'under_review'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or under review proposals can be approved'
                ], 422);
            }

            $proposal->approve($request->reviewer_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject proposal
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reviewer_id' => 'required|exists:users,id',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!in_array($proposal->status, ['submitted', 'under_review'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or under review proposals can be rejected'
                ], 422);
            }

            $proposal->reject($request->reviewer_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal rejected'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting proposal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request revision for proposal
     */
    public function requestRevision(Request $request, string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'reviewer_id' => 'required|exists:users,id',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!in_array($proposal->status, ['submitted', 'under_review'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or under review proposals can request revision'
                ], 422);
            }

            $proposal->requestRevision($request->reviewer_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $proposal,
                'message' => 'Revision requested successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting revision: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get embedded spreadsheet view data
     */
    public function getEmbeddedView(string $id): JsonResponse
    {
        try {
            $proposal = ProgramProposal::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'proposal_id' => $proposal->id,
                    'proposal_title' => $proposal->proposal_title,
                    'spreadsheet_id' => $proposal->spreadsheet_id,
                    'embedded_url' => $proposal->embedded_url,
                    'full_url' => $proposal->full_spreadsheet_url,
                    'last_synced_at' => $proposal->last_synced_at,
                    'auto_sync' => $proposal->auto_sync
                ],
                'message' => 'Embedded view data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving embedded view: ' . $e->getMessage()
            ], 404);
        }
    }
}

