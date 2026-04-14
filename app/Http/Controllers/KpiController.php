<?php

namespace App\Http\Controllers;

use App\Services\KpiService;
use App\Models\Employee;
use App\Models\User;
use App\Models\KpiPointSetting;
use App\Models\KpiQualityScore;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class KpiController extends Controller
{
    protected KpiService $kpiService;

    public function __construct(KpiService $kpiService)
    {
        $this->kpiService = $kpiService;
    }

    /**
     * GET /api/kpi/employees
     * List all employees for KPI overview (Program Manager & Distribution Manager only)
     */
    public function employees(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, ['Program Manager', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Program Manager and Distribution Manager can access employee KPI list.'
                ], 403);
            }

            $query = Employee::with('user')
                ->whereHas('user');

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            }

            // Filter by role
            if ($request->has('role') && $request->role) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('role', $request->role);
                });
            }

            $employees = $query->paginate($request->get('per_page', 20));

            // Add basic KPI score for each employee
            $month = $request->get('month') ? (int) $request->get('month') : null;
            $year = $request->get('year', now()->year);

            $employees->getCollection()->transform(function ($employee) use ($month, $year) {
                $user = $employee->user;
                if ($user) {
                    try {
                        $score = $this->kpiService->calculateOverallScore($user, $employee, $month, $year);
                        $employee->kpi_score = $score['score'];
                        $employee->kpi_label = $score['label'];
                        $employee->kpi_color = $score['color'];
                    } catch (\Exception $e) {
                        $employee->kpi_score = 0;
                        $employee->kpi_label = 'N/A';
                        $employee->kpi_color = '#9ca3af';
                    }
                }
                return $employee;
            });

            return response()->json([
                'success' => true,
                'data' => $employees,
                'message' => 'Employee KPI list retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving employee KPI list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/kpi/employee/{id}
     * Get detailed KPI for a specific employee
     */
    public function employeeDetail(int $id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $employee = Employee::with('user')->findOrFail($id);

            // Only managers and the employee themselves can view
            $isManager = in_array($user->role, ['Program Manager', 'Distribution Manager']);
            $isSelf = $user->id === ($employee->user->id ?? null);

            if (!$isManager && !$isSelf) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view your own KPI data.'
                ], 403);
            }

            $month = $request->get('month') ? (int) $request->get('month') : null;
            $year = (int) $request->get('year', now()->year);

            $kpiData = $this->kpiService->getEmployeeKpi($id, $month, $year);

            return response()->json([
                'success' => true,
                'data' => $kpiData,
                'message' => 'Employee KPI retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving employee KPI: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/kpi/my
     * Get KPI for the currently logged in user
     */
    public function myKpi(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $employee = Employee::find($user->employee_id);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'No employee record found for your account.'
                ], 404);
            }

            $month = $request->get('month') ? (int) $request->get('month') : null;
            $year = (int) $request->get('year', now()->year);

            $kpiData = $this->kpiService->getEmployeeKpi($employee->id, $month, $year);

            return response()->json([
                'success' => true,
                'data' => $kpiData,
                'message' => 'Your KPI retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving your KPI: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/kpi/settings
     * Get KPI point settings
     */
    public function getSettings(): JsonResponse
    {
        try {
            $settings = KpiPointSetting::with('updatedByUser')
                ->orderBy('program_type')
                ->orderBy('role')
                ->get();

            $defaults = KpiPointSetting::getDefaults();

            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => $settings,
                    'defaults' => $defaults,
                ],
                'message' => 'KPI settings retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving KPI settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/kpi/settings
     * Update KPI point settings (Program Manager & Distribution Manager only)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, ['Program Manager', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Program Manager and Distribution Manager can update KPI settings.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'settings' => 'required|array',
                'settings.*.role' => 'required|string',
                'settings.*.program_type' => 'required|string|in:regular,musik',
                'settings.*.points_on_time' => 'required|integer|min:0|max:100',
                'settings.*.points_late' => 'required|integer|min:0|max:100',
                'settings.*.points_not_done' => 'required|integer|min:-100|max:0',
                'settings.*.quality_min' => 'required|integer|min:0',
                'settings.*.quality_max' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->settings as $setting) {
                KpiPointSetting::updateOrCreate(
                    ['role' => $setting['role'], 'program_type' => $setting['program_type']],
                    array_merge($setting, ['updated_by' => $user->id])
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'KPI settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating KPI settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/kpi/settings/restore-defaults
     * Restore KPI settings to spreadsheet defaults
     */
    public function restoreDefaults(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, ['Program Manager', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Program Manager and Distribution Manager can restore KPI defaults.'
                ], 403);
            }

            $defaults = KpiPointSetting::getDefaults();

            foreach ($defaults as $setting) {
                KpiPointSetting::updateOrCreate(
                    ['role' => $setting['role'], 'program_type' => $setting['program_type']],
                    array_merge($setting, ['updated_by' => $user->id])
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'KPI settings restored to defaults successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring defaults: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/kpi/employee/{id}/quality-score
     * Update quality score for employee's work item (managers only)
     */
    public function updateQualityScore(int $employeeId, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!in_array($user->role, ['Program Manager', 'Distribution Manager'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Program Manager and Distribution Manager can update quality scores.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'pr_episode_id' => 'nullable|integer',
                'music_episode_id' => 'nullable|integer',
                'program_type' => 'required|string|in:regular,musik',
                'workflow_step' => 'required|string',
                'quality_score' => 'required|integer',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $score = KpiQualityScore::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'pr_episode_id' => $request->pr_episode_id,
                    'music_episode_id' => $request->music_episode_id,
                    'workflow_step' => $request->workflow_step,
                ],
                [
                    'program_type' => $request->program_type,
                    'quality_score' => $request->quality_score,
                    'notes' => $request->notes,
                    'scored_by' => $user->id,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $score,
                'message' => 'Quality score updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating quality score: ' . $e->getMessage()
            ], 500);
        }
    }
}
