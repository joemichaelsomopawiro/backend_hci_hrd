<?php

namespace App\Http\Controllers;

use App\Models\ProgramEpisode;
use App\Models\ProgramRegular;
use App\Models\EpisodeDeadline;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProgramEpisodeController extends Controller
{
    /**
     * Display a listing of episodes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProgramEpisode::with(['programRegular', 'deadlines']);

            // Filter by program
            if ($request->has('program_regular_id')) {
                $query->where('program_regular_id', $request->program_regular_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by format type
            if ($request->has('format_type')) {
                $query->where('format_type', $request->format_type);
            }

            // Filter upcoming
            if ($request->boolean('upcoming')) {
                $query->upcoming();
            }

            // Filter aired
            if ($request->boolean('aired')) {
                $query->aired();
            }

            // Filter overdue
            if ($request->boolean('overdue')) {
                $query->overdue();
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'episode_number');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $episodes = $query->paginate($request->get('per_page', 15));

            // Add additional info
            $episodes->getCollection()->transform(function ($episode) {
                $episode->days_until_air = $episode->days_until_air;
                $episode->progress_percentage = $episode->progress_percentage;
                $episode->is_overdue = $episode->isOverdue();
                return $episode;
            });

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
     * Display the specified episode
     */
    public function show(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::with([
                'programRegular.productionTeam.producer',
                'deadlines.completedBy',
                'approvals.requestedBy'
            ])->findOrFail($id);

            $episode->days_until_air = $episode->days_until_air;
            $episode->progress_percentage = $episode->progress_percentage;
            $episode->is_overdue = $episode->isOverdue();
            $episode->overdue_deadlines = $episode->getOverdueDeadlines();
            $episode->upcoming_deadlines = $episode->getUpcomingDeadlines();

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving episode: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified episode
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            // Cannot update if status is aired
            if ($episode->status === 'aired') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update aired episode'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'production_date' => 'nullable|date',
                'status' => 'sometimes|in:planning,ready_to_produce,in_production,post_production,ready_to_air,aired,cancelled',
                'rundown' => 'nullable|string',
                'script' => 'nullable|string',
                'talent_data' => 'nullable|array',
                'location' => 'nullable|string',
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
            $episode->load(['programRegular', 'deadlines']);

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
     * Update episode status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:planning,ready_to_produce,in_production,post_production,ready_to_air,aired,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Episode status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episode deadlines
     */
    public function getDeadlines(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);
            $deadlines = $episode->deadlines()->with('completedBy')->orderBy('deadline_date')->get();

            // Add additional info
            $deadlines->transform(function ($deadline) {
                $deadline->role_label = $deadline->role_label;
                $deadline->is_overdue = $deadline->isOverdue();
                $deadline->days_until_deadline = $deadline->days_until_deadline;
                return $deadline;
            });

            return response()->json([
                'success' => true,
                'data' => $deadlines,
                'message' => 'Episode deadlines retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving deadlines: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark deadline as completed
     */
    public function completeDeadline(Request $request, string $episodeId, string $deadlineId): JsonResponse
    {
        try {
            $deadline = EpisodeDeadline::where('program_episode_id', $episodeId)
                ->where('id', $deadlineId)
                ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($deadline->is_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deadline already completed'
                ], 422);
            }

            $deadline->markAsCompleted($request->user_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => $deadline,
                'message' => 'Deadline marked as completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing deadline: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit episode rundown for approval
     */
    public function submitRundown(Request $request, string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (empty($episode->rundown)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot submit empty rundown'
                ], 422);
            }

            $approval = $episode->submitRundown($request->user_id, $request->notes);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode,
                    'approval' => $approval
                ],
                'message' => 'Rundown submitted for approval successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting rundown: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming episodes (within specified days)
     */
    public function getUpcoming(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);
            
            $episodes = ProgramEpisode::with(['programRegular', 'deadlines'])
                ->where('air_date', '>=', now())
                ->where('air_date', '<=', now()->addDays($days))
                ->where('status', '!=', 'aired')
                ->orderBy('air_date', 'asc')
                ->paginate($request->get('per_page', 15));

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
}

