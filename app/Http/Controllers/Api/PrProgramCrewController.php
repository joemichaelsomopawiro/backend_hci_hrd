<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrProgram;
use App\Models\PrProgramCrew;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Constants\Role;
use App\Services\PrActivityLogService;
use App\Services\PrWorkflowService;

class PrProgramCrewController extends Controller
{
    protected $activityLogService;
    protected $workflowService;

    public function __construct(PrActivityLogService $activityLogService, PrWorkflowService $workflowService)
    {
        $this->activityLogService = $activityLogService;
        $this->workflowService = $workflowService;
    }
    /**
     * Get list of crew members for a program
     */
    public function index($programId)
    {
        $program = PrProgram::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program tidak ditemukan'
            ], 404);
        }

        // Optional: Check permissions specific to viewing crew
        // For now, allow authenticated users to view

        $crews = PrProgramCrew::with([
            'user' => function ($query) {
                // Select only relevant user fields, adding profile_picture for avatar display
                $query->select('id', 'name', 'email', 'role', 'profile_picture');
            }
        ])
            ->where('program_id', $programId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $crews
        ]);
    }

    /**
     * Add a crew member to a program
     */
    /**
     * Add crew members to a program (Bulk Support)
     */
    public function store(Request $request, $programId)
    {
        $program = PrProgram::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program tidak ditemukan'
            ], 404);
        }

        // Support both single entry (legacy) and bulk (new)
        $members = $request->input('members');

        // If 'members' is not present, assume single entry and normalize to array
        if (!$members) {
            $members = [
                [
                    'user_id' => $request->user_id,
                    'role' => $request->role
                ]
            ];
        }

        $addedMembers = [];
        $errors = [];

        foreach ($members as $index => $memberData) {
            $validator = Validator::make($memberData, [
                'user_id' => 'required|exists:users,id',
                'role' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ];
                continue;
            }

            // CRITICAL: Check if USER already has a ROLE in this program
            // "Satu orang hanya boleh memegang 1 posisi (Role) per program."
            $userRoleExists = PrProgramCrew::where('program_id', $programId)
                ->where('user_id', $memberData['user_id'])
                ->first();

            if ($userRoleExists) {
                // If it's the exact same user AND role, just skip (idempotent)
                if ($userRoleExists->role === $memberData['role']) {
                    continue; // Already exists, perfectly fine
                }

                // If the user already has a DIFFERENT role
                $errors[] = [
                    'index' => $index,
                    'message' => "Anggota tim ini sudah memegang posisi '{$userRoleExists->role}' di program ini."
                ];
                continue;
            }

            // Note: We DO NOT check if role exists anymore. 
            // "Satu posisi boleh diisi lebih dari 1 orang" means multiple people can hold the same role.

            try {
                $crew = PrProgramCrew::create([
                    'program_id' => $programId,
                    'user_id' => $memberData['user_id'],
                    'role' => $memberData['role']
                ]);

                // Transform response to include user details
                $crew->load([
                    'user' => function ($query) {
                        $query->select('id', 'name', 'email', 'role', 'profile_picture');
                    }
                ]);

                $addedMembers[] = $crew;

                // Log Activity
                $this->activityLogService->logProgramActivity(
                    $program,
                    'assign_crew',
                    "Assigned user {$crew->user->name} as {$memberData['role']}",
                    ['user_id' => $memberData['user_id'], 'role' => $memberData['role']]
                );

            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Error database: ' . $e->getMessage()
                ];
            }
        }

        if (count($errors) > 0 && count($addedMembers) === 0) {
            // If all failed
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan anggota tim',
                'errors' => $errors
            ], 422);
        }

        // Check for workflow auto-completion (Step 1: Program Created & Team Formed)
        $stepCompleted = false;

        // Define all required roles for Step 1 completion
        $requiredRoles = [
            'Producer',
            'Kreatif',
            'Produksi',
            'Editor',
            'Editor Promosi',
            'Design Grafis',
            'Art & Set Design',
            'Manager Distribusi',
            'QC',
            'Broadcasting',
            'Promosi'
        ];

        // Get unique filled roles for this program
        $filledRoles = PrProgramCrew::where('program_id', $programId)
            ->distinct()
            ->pluck('role')
            ->toArray();

        // Check if all required roles are present
        $missingRoles = array_diff($requiredRoles, $filledRoles);

        // If no missing roles, we can complete Step 1
        if (empty($missingRoles)) {
            try {
                // Get all episodes for this program
                $episodes = \App\Models\PrEpisode::where('program_id', $programId)->get();
                foreach ($episodes as $episode) {
                    try {
                        // Attempt to complete Step 1 (Create Program & Team)
                        // This will throw exception if already completed, which we catch and ignore
                        $this->workflowService->completeStep($episode->id, 1, 'Auto-completed: All required team roles assigned');

                        // If we get here, it means completion was successful (status changed)
                        $stepCompleted = true;
                    } catch (\Exception $e) {
                        // Ignore if already completed
                    }
                }
            } catch (\Exception $e) {
                // Log but don't fail the request
                \Illuminate\Support\Facades\Log::warning('Failed to auto-complete workflow step 1', [
                    'program_id' => $programId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menambahkan ' . count($addedMembers) . ' anggota tim',
            'data' => $addedMembers,
            'workflow_step_completed' => $stepCompleted,
            'partial_errors' => count($errors) > 0 ? $errors : null
        ], 201);
    }

    /**
     * Remove a crew member from a program
     */
    public function destroy($programId, $memberId)
    {
        // memberId here is the ID of the pr_program_crews record (the "assignment" ID), not the user_id

        $crew = PrProgramCrew::where('program_id', $programId)
            ->where('id', $memberId)
            ->first();

        if (!$crew) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tim tidak ditemukan di program ini'
            ], 404);
        }

        // Eager load user for logging name before delete
        $crew->load('user');
        $program = PrProgram::find($programId);

        try {
            $crew->delete();

            // Log Activity
            if ($program) {
                $this->activityLogService->logProgramActivity(
                    $program,
                    'remove_crew',
                    "Removed user {$crew->user->name} from role {$crew->role}",
                    ['user_id' => $crew->user_id, 'role' => $crew->role]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus anggota tim'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota tim: ' . $e->getMessage()
            ], 500);
        }
    }
}
