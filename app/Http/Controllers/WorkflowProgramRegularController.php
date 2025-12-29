<?php

namespace App\Http\Controllers;

use App\Models\ProgramEpisode;
use App\Models\ProductionTeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * Workflow Program Regular Controller
 * 
 * Menghandle workflow lengkap dari diagram:
 * Manager Program → Producer → Creative → Produksi → Editor → QC → Broadcasting → Promosi → Design Grafis → Manager Distribusi
 */
class WorkflowProgramRegularController extends Controller
{
    /**
     * CREATIVE WORKFLOW
     * Write script & rundown
     * POST /api/workflow/creative/episodes/{id}/script
     */
    public function submitScript(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'script' => 'required|string',
                'rundown' => 'required|string',
                'talent_data' => 'required|array',
                'talent_data.host' => 'required|array',
                'talent_data.host.name' => 'required|string',
                'talent_data.host.phone' => 'nullable|string',
                'talent_data.host.email' => 'nullable|email',
                'talent_data.narasumber' => 'nullable|array',
                'talent_data.narasumber.*.name' => 'required|string',
                'talent_data.narasumber.*.expertise' => 'nullable|string',
                'talent_data.narasumber.*.phone' => 'nullable|string',
                'talent_data.kesaksian' => 'nullable|array',
                'location' => 'required|string|max:255',
                'production_date' => 'required|date',
                'budget_talent' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update episode dengan data script
            $episode->update([
                'title' => $request->title,
                'script' => $request->script,
                'rundown' => $request->rundown,
                'talent_data' => $request->talent_data,
                'location' => $request->location,
                'production_date' => $request->production_date,
                'budget_talent' => $request->budget_talent,
                'notes' => $request->notes,
                'script_submitted_at' => now(),
                'script_submitted_by' => auth()->id(),
                'status' => 'script_review'
            ]);

            // Notify Producer untuk review
            // TODO: Implement notification

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Script submitted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error submitting script: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PRODUCER WORKFLOW
     * Review & approve rundown dari Creative
     * POST /api/workflow/producer/episodes/{id}/review-rundown
     */
    public function reviewRundown(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'decision' => 'required|in:approved,rejected,needs_revision',
                'notes' => 'required|string|max:2000',
                'revision_points' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->decision === 'approved') {
                $episode->update([
                    'status' => 'rundown_approved',
                    'rundown_approved_at' => now(),
                    'rundown_approved_by' => auth()->id(),
                    'producer_notes' => $request->notes
                ]);

                // Notify Produksi untuk prepare shooting
                // TODO: Implement notification
            } else {
                $episode->update([
                    'status' => 'rundown_rejected',
                    'rundown_rejected_at' => now(),
                    'rundown_rejected_by' => auth()->id(),
                    'rundown_rejection_notes' => $request->notes,
                    'rundown_revision_points' => $request->revision_points
                ]);

                // Notify Creative untuk revisi
                // TODO: Implement notification
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Rundown reviewed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error reviewing rundown: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PRODUKSI WORKFLOW
     * Request alat ke Art & Set Properti
     * POST /api/workflow/produksi/episodes/{id}/request-equipment
     */
    public function requestEquipment(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.name' => 'required|string',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.notes' => 'nullable|string',
                'request_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create equipment request
            $equipmentRequest = DB::table('produksi_equipment_requests')->insert([
                'episode_id' => $episode->id,
                'requested_by' => auth()->id(),
                'equipment_list' => json_encode($request->equipment_list),
                'request_notes' => $request->request_notes,
                'status' => 'pending',
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Notify Art & Set Properti
            // TODO: Implement notification

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipment request submitted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error requesting equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PRODUKSI WORKFLOW
     * Complete shooting & upload file
     * POST /api/workflow/produksi/episodes/{id}/complete-shooting
     */
    public function completeShooting(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $episode = ProgramEpisode::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'raw_file_urls' => 'required|array|min:1',
                'raw_file_urls.*' => 'url',
                'shooting_notes' => 'required|string|max:2000',
                'actual_shooting_date' => 'nullable|date',
                'duration_minutes' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update episode
            $episode->update([
                'status' => 'post_production',
                'raw_file_urls' => $request->raw_file_urls,
                'shooting_notes' => $request->shooting_notes,
                'actual_shooting_date' => $request->actual_shooting_date ?? now(),
                'shooting_completed_at' => now(),
                'shooting_completed_by' => auth()->id()
            ]);

            // Notify Editor
            // TODO: Implement notification

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Shooting completed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error completing shooting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow status for episode
     * GET /api/workflow/episodes/{id}/status
     */
    public function getWorkflowStatus(string $id): JsonResponse
    {
        try {
            $episode = ProgramEpisode::with([
                'programRegular',
                'editorWork',
                'qc',
                'designGrafisWork'
            ])->findOrFail($id);

            $workflow = [
                'episode' => $episode,
                'current_status' => $episode->status,
                'workflow_steps' => [
                    'creative' => [
                        'status' => $episode->script_submitted_at ? 'completed' : 'pending',
                        'completed_at' => $episode->script_submitted_at,
                        'completed_by' => $episode->scriptSubmittedBy
                    ],
                    'producer_review' => [
                        'status' => $episode->rundown_approved_at ? 'completed' : ($episode->rundown_rejected_at ? 'rejected' : 'pending'),
                        'completed_at' => $episode->rundown_approved_at,
                        'approved_by' => $episode->rundownApprovedBy
                    ],
                    'production' => [
                        'status' => $episode->shooting_completed_at ? 'completed' : 'pending',
                        'completed_at' => $episode->shooting_completed_at,
                        'completed_by' => $episode->shootingCompletedBy
                    ],
                    'editor' => [
                        'status' => $episode->editorWork && $episode->editorWork->status === 'completed' ? 'completed' : 'pending',
                        'completed_at' => $episode->editorWork->completed_at ?? null
                    ],
                    'qc' => [
                        'status' => $episode->qc && $episode->qc->status === 'approved' ? 'completed' : 'pending',
                        'reviewed_at' => $episode->qc->reviewed_at ?? null
                    ],
                    'broadcasting' => [
                        'status' => $episode->status === 'aired' ? 'completed' : 'pending',
                        'completed_at' => $episode->broadcast_completed_at
                    ],
                    'promotion' => [
                        'status' => $episode->promosi_completed_at ? 'completed' : 'pending',
                        'completed_at' => $episode->promosi_completed_at
                    ],
                    'graphic_design' => [
                        'status' => $episode->designGrafisWork && $episode->designGrafisWork->status === 'completed' ? 'completed' : 'pending',
                        'completed_at' => $episode->designGrafisWork->completed_at ?? null
                    ]
                ],
                'days_until_air' => now()->diffInDays($episode->air_date, false),
                'is_overdue' => $episode->air_date < now() && $episode->status !== 'aired'
            ];

            return response()->json([
                'success' => true,
                'data' => $workflow,
                'message' => 'Workflow status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving workflow status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workflow dashboard
     * GET /api/workflow/dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $stats = [
                'pending_script' => ProgramEpisode::where('status', 'planning')->count(),
                'pending_producer_review' => ProgramEpisode::where('status', 'script_review')->count(),
                'pending_production' => ProgramEpisode::where('status', 'rundown_approved')->count(),
                'in_post_production' => ProgramEpisode::where('status', 'post_production')->count(),
                'pending_qc' => ProgramEpisode::where('status', 'post_production')
                    ->whereHas('editorWork', function ($q) {
                        $q->where('status', 'completed');
                    })->count(),
                'ready_to_broadcast' => ProgramEpisode::where('status', 'ready_to_air')->count(),
                'aired' => ProgramEpisode::where('status', 'aired')->count(),
                'overdue_episodes' => ProgramEpisode::where('air_date', '<', now())
                    ->where('status', '!=', 'aired')
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Workflow dashboard retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard: ' . $e->getMessage()
            ], 500);
        }
    }
}

