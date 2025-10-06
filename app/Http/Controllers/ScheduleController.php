<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Schedule::with(['program', 'episode', 'team', 'assignedUser']);

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter berdasarkan program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            // Filter berdasarkan episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Filter berdasarkan team
            if ($request->has('team_id')) {
                $query->where('team_id', $request->team_id);
            }

            // Filter berdasarkan assigned user
            if ($request->has('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            }

            // Filter berdasarkan tanggal
            if ($request->has('start_date')) {
                $query->whereDate('start_time', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('end_time', '<=', $request->end_date);
            }

            // Search
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }

            $schedules = $query->orderBy('start_time')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Schedules retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedules: ' . $e->getMessage()
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
                'type' => 'required|in:production,meeting,deadline,review,other',
                'program_id' => 'required|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'team_id' => 'nullable|exists:teams,id',
                'assigned_to' => 'nullable|exists:users,id',
                'start_time' => 'required|date',
                'end_time' => 'required|date|after:start_time',
                'deadline' => 'required|date',
                'location' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'is_recurring' => 'boolean',
                'recurring_pattern' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = Schedule::create($request->all());
            $schedule->load(['program', 'episode', 'team', 'assignedUser']);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $schedule = Schedule::with(['program', 'episode', 'team', 'assignedUser'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'sometimes|required|in:production,meeting,deadline,review,other',
                'program_id' => 'sometimes|required|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'team_id' => 'nullable|exists:teams,id',
                'assigned_to' => 'nullable|exists:users,id',
                'start_time' => 'sometimes|required|date',
                'end_time' => 'sometimes|required|date|after:start_time',
                'deadline' => 'sometimes|required|date',
                'location' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'is_recurring' => 'sometimes|boolean',
                'recurring_pattern' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule->update($request->all());
            $schedule->load(['program', 'episode', 'team', 'assignedUser']);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update schedule status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,in_progress,completed,overdue,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule->update(['status' => $request->status]);
            $schedule->load(['program', 'episode', 'team', 'assignedUser']);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating schedule status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming schedules
     */
    public function getUpcoming(Request $request): JsonResponse
    {
        try {
            $query = Schedule::with(['program', 'episode', 'team', 'assignedUser'])
                ->where('start_time', '>', now())
                ->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled');

            if ($request->has('days')) {
                $days = $request->days;
                $query->where('start_time', '<=', now()->addDays($days));
            }

            $schedules = $query->orderBy('start_time')->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Upcoming schedules retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving upcoming schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's schedules
     */
    public function getToday(Request $request): JsonResponse
    {
        try {
            $schedules = Schedule::with(['program', 'episode', 'team', 'assignedUser'])
                ->whereDate('start_time', today())
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Today\'s schedules retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving today\'s schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overdue schedules
     */
    public function getOverdue(Request $request): JsonResponse
    {
        try {
            $schedules = Schedule::with(['program', 'episode', 'team', 'assignedUser'])
                ->where('deadline', '<', now())
                ->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->orderBy('deadline')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Overdue schedules retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving overdue schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit schedule for approval
     */
    public function submitForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            
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

            $schedule->update([
                'status' => 'submitted',
                'submission_notes' => $request->submission_notes,
                'submitted_at' => now(),
                'submitted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting schedule for approval: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve schedule
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            
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

            $schedule->update([
                'status' => 'approved',
                'approval_notes' => $request->approval_notes,
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject schedule
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $schedule = Schedule::findOrFail($id);
            
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

            $schedule->update([
                'status' => 'rejected',
                'rejection_notes' => $request->rejection_notes,
                'rejected_by' => auth()->id(),
                'rejected_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'message' => 'Schedule rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export schedule data
     */
    public function exportScheduleData(string $id): JsonResponse
    {
        try {
            $schedule = Schedule::with(['program', 'episode', 'team', 'assignedUser'])->findOrFail($id);
            
            // Placeholder for schedule export - implement based on your export system
            $exportData = [
                'schedule' => $schedule,
                'exported_at' => now(),
                'format' => 'json'
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Schedule data exported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting schedule data: ' . $e->getMessage()
            ], 500);
        }
    }
}
