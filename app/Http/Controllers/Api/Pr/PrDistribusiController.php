<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrDistributionSchedule;
use App\Models\PrDistributionReport;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use App\Models\PrRevisionHistory;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Constants\Role;

class PrDistribusiController extends Controller
{
    /**
     * View Program Concept
     */
    public function viewProgramConcept($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::MANAGER_DISTRIBUSI, Role::PROGRAM_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $program = PrProgram::with([
                'concepts' => function ($q) {
                    $q->latest()->limit(1);
                }
            ])->findOrFail($id);

            return response()->json(['success' => true, 'data' => $program->concepts->first(), 'message' => 'Concept retrieved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View Production Schedules
     */
    public function viewProductionSchedules($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::MANAGER_DISTRIBUSI, Role::PROGRAM_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $schedules = \App\Models\PrProductionSchedule::where('pr_program_id', $id)
                ->orderBy('date_start', 'asc')
                ->get();

            return response()->json(['success' => true, 'data' => $schedules, 'message' => 'Production schedules retrieved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View Shooting Schedule
     */
    public function viewShootingSchedule($episodeId): JsonResponse
    {
        try {
            $episode = PrEpisode::with(['creativeWork', 'productionSchedules'])->findOrFail($episodeId);

            return response()->json([
                'success' => true,
                'data' => $episode,
                'message' => 'Shooting schedule retrieved'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View Program Files (Final edits, etc)
     */
    public function viewProgramFiles($id, Request $request): JsonResponse
    {
        try {
            // Logic to fetch files related to program (likely from episodes -> editor works)
            $program = PrProgram::findOrFail($id);
            $episodes = $program->episodes()->with([
                'editorWorks' => function ($q) {
                    $q->where('status', 'completed')->latest();
                }
            ])->get();

            $files = $episodes->pluck('editorWorks')->flatten();

            return response()->json(['success' => true, 'data' => $files, 'message' => 'Program files retrieved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create Distribution Schedule
     */
    public function createDistributionSchedule(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::MANAGER_DISTRIBUSI, Role::PROGRAM_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'pr_episode_id' => 'required|exists:pr_episodes,id',
                'air_date' => 'required|date',
                'air_time' => 'required',
                'platform' => 'required|string',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $schedule = PrDistributionSchedule::create([
                'pr_program_id' => $programId,
                'pr_episode_id' => $request->pr_episode_id,
                'air_date' => $request->air_date,
                'air_time' => $request->air_time,
                'platform' => $request->platform,
                'notes' => $request->notes,
                'status' => 'scheduled',
                'created_by' => Auth::id()
            ]);

            // Auto-create QC Work
            \App\Models\PrQualityControlWork::firstOrCreate(
                ['pr_episode_id' => $request->pr_episode_id, 'work_type' => 'main_episode'],
                ['status' => 'pending', 'created_by' => Auth::id()]
            );

            return response()->json(['success' => true, 'data' => $schedule, 'message' => 'Schedule created successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update Distribution Schedule
     */
    public function updateDistributionSchedule(Request $request, $id): JsonResponse
    {
        try {
            $schedule = PrDistributionSchedule::findOrFail($id);
            $schedule->update($request->all());
            return response()->json(['success' => true, 'data' => $schedule, 'message' => 'Schedule updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete Distribution Schedule
     */
    public function deleteDistributionSchedule($id): JsonResponse
    {
        try {
            $schedule = PrDistributionSchedule::findOrFail($id);
            $schedule->delete();
            return response()->json(['success' => true, 'message' => 'Schedule deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark Episode as Aired
     */
    public function markAsAired(Request $request, $episodeId): JsonResponse
    {
        try {
            $episode = PrEpisode::findOrFail($episodeId);
            // Logic to update episode status or schedule status
            $schedule = PrDistributionSchedule::where('pr_episode_id', $episodeId)->latest()->first();
            if ($schedule) {
                $schedule->update(['status' => 'aired']);
            }

            return response()->json(['success' => true, 'message' => 'Episode marked as aired']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create Distribution Report
     */
    public function createDistributionReport(Request $request, $programId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !Role::inArray($user->role, [Role::MANAGER_DISTRIBUSI, Role::PROGRAM_MANAGER])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'report_period' => 'required',
                'metrics' => 'required|array',
                'summary' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $report = PrDistributionReport::create([
                'pr_program_id' => $programId,
                'created_by' => Auth::id(),
                'report_period' => $request->report_period,
                'metrics' => $request->metrics,
                'summary' => $request->summary,
                'status' => 'draft'
            ]);

            return response()->json(['success' => true, 'data' => $report, 'message' => 'Report created successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List Distribution Reports
     */
    public function listDistributionReports(Request $request): JsonResponse
    {
        try {
            $query = PrDistributionReport::with(['program', 'createdBy']);

            if ($request->has('pr_program_id')) {
                $query->where('pr_program_id', $request->pr_program_id);
            }

            $reports = $query->latest()->get();
            return response()->json(['success' => true, 'data' => $reports, 'message' => 'Reports retrieved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update Distribution Report
     */
    public function updateDistributionReport(Request $request, $id): JsonResponse
    {
        try {
            $report = PrDistributionReport::findOrFail($id);
            $report->update($request->all());
            return response()->json(['success' => true, 'data' => $report, 'message' => 'Report updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete Distribution Report
     */
    public function deleteDistributionReport($id): JsonResponse
    {
        try {
            $report = PrDistributionReport::findOrFail($id);
            $report->delete();
            return response()->json(['success' => true, 'message' => 'Report deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View Revision History
     */
    public function viewDistribusiRevisionHistory($id, Request $request): JsonResponse
    {
        try {
            $history = PrRevisionHistory::where('pr_program_id', $id)
                ->with(['createdBy', 'reviewer'])
                ->latest()
                ->get();
            return response()->json(['success' => true, 'data' => $history, 'message' => 'History retrieved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
