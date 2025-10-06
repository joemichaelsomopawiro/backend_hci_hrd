<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EpisodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Episode::with(['program', 'schedules', 'mediaFiles', 'productionEquipment']);

            // Filter berdasarkan program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan tanggal
            if ($request->has('air_date_from')) {
                $query->where('air_date', '>=', $request->air_date_from);
            }

            if ($request->has('air_date_to')) {
                $query->where('air_date', '<=', $request->air_date_to);
            }

            // Search
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $episodes = $query->orderBy('air_date', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'episode_number' => 'required|integer|min:1',
                'program_id' => 'required|exists:programs,id',
                'air_date' => 'required|date',
                'production_date' => 'nullable|date',
                'status' => 'required|in:draft,in_production,review,approved,ready_to_air,aired,archived',
                'script' => 'nullable|string',
                'talent_data' => 'nullable|array',
                'location' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'production_notes' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::create($request->all());
            $episode->load(['program', 'schedules', 'mediaFiles', 'productionEquipment']);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating episode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $episode = Episode::with(['program', 'schedules', 'mediaFiles', 'productionEquipment'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'episode_number' => 'sometimes|required|integer|min:1',
                'program_id' => 'sometimes|required|exists:programs,id',
                'air_date' => 'sometimes|required|date',
                'production_date' => 'nullable|date',
                'status' => 'sometimes|required|in:draft,in_production,review,approved,ready_to_air,aired,archived',
                'script' => 'nullable|string',
                'talent_data' => 'nullable|array',
                'location' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'production_notes' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update($request->all());
            $episode->load(['program', 'schedules', 'mediaFiles', 'productionEquipment']);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating episode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            $episode->delete();

            return response()->json([
                'success' => true,
                'message' => 'Episode deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting episode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update episode status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:draft,in_production,review,approved,ready_to_air,aired,archived'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update(['status' => $request->status]);
            $episode->load(['program', 'schedules', 'mediaFiles', 'productionEquipment']);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating episode status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming episodes
     */
    public function getUpcoming(Request $request): JsonResponse
    {
        try {
            $query = Episode::with(['program', 'schedules', 'mediaFiles'])
                ->where('air_date', '>', now())
                ->where('status', '!=', 'aired')
                ->where('status', '!=', 'archived');

            if ($request->has('days')) {
                $days = $request->days;
                $query->where('air_date', '<=', now()->addDays($days));
            }

            $episodes = $query->orderBy('air_date')->get();

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Upcoming episodes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving upcoming episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get aired episodes
     */
    public function getAired(Request $request): JsonResponse
    {
        try {
            $query = Episode::with(['program', 'schedules', 'mediaFiles'])
                ->where('status', 'aired');

            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            $episodes = $query->orderBy('air_date', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Aired episodes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving aired episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit episode rundown for approval
     */
    public function submitRundownForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'submission_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update([
                'status' => 'submitted',
                'submission_notes' => $request->submission_notes,
                'submitted_at' => now(),
                'submitted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode rundown submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting episode rundown for approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve episode rundown
     */
    public function approveRundown(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'approval_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update([
                'status' => 'approved',
                'approval_notes' => $request->approval_notes,
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode rundown approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving episode rundown: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject episode rundown
     */
    public function rejectRundown(Request $request, string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'rejection_notes' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update([
                'status' => 'rejected',
                'rejection_notes' => $request->rejection_notes,
                'rejected_by' => auth()->id(),
                'rejected_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode rundown rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting episode rundown: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export script to Word
     */
    public function exportScriptToWord(string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            // Placeholder for Word export - implement based on your export system
            $exportData = [
                'episode' => $episode,
                'script' => $episode->script,
                'exported_at' => now(),
                'format' => 'word'
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Script exported to Word successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting script to Word: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export script to PowerPoint
     */
    public function exportScriptToPowerPoint(string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            // Placeholder for PowerPoint export - implement based on your export system
            $exportData = [
                'episode' => $episode,
                'script' => $episode->script,
                'exported_at' => now(),
                'format' => 'powerpoint'
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Script exported to PowerPoint successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting script to PowerPoint: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export script to PDF
     */
    public function exportScriptToPDF(string $id): JsonResponse
    {
        try {
            $episode = Episode::findOrFail($id);
            
            // Placeholder for PDF export - implement based on your export system
            $exportData = [
                'episode' => $episode,
                'script' => $episode->script,
                'exported_at' => now(),
                'format' => 'pdf'
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Script exported to PDF successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting script to PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk export episodes
     */
    public function bulkExportEpisodes(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'episode_ids' => 'required|array',
                'episode_ids.*' => 'exists:episodes,id',
                'format' => 'required|in:word,powerpoint,pdf'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episodes = Episode::whereIn('id', $request->episode_ids)->get();
            
            $exportData = [
                'episodes' => $episodes,
                'format' => $request->format,
                'exported_at' => now(),
                'total_count' => $episodes->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Episodes exported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error bulk exporting episodes: ' . $e->getMessage()
            ], 500);
        }
    }
}
