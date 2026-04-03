<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use Illuminate\Support\Facades\Auth;
use App\Constants\Role;
use Illuminate\Support\Facades\Log;

class PrDatabaseController extends Controller
{
    private function canViewProgramRegular($user): bool
    {
        $role = Role::normalize($user->role);
        $allowed = array_values(array_unique(array_merge(
            Role::getManagerRoles(),
            [Role::PRODUCER, Role::QUALITY_CONTROL],
            Role::getProductionTeamRoles(),
            Role::getDistributionTeamRoles()
        )));

        return in_array($role, $allowed);
    }

    /**
     * Helper to verify if an action completed on time
     */
    private function isOnTime($completedAt, $deadlineStr)
    {
        if (!$completedAt) return null;
        if (!$deadlineStr) return true; // No deadline, so always considered on time
        try {
            $completed = \Carbon\Carbon::parse($completedAt);
            $deadline = \Carbon\Carbon::parse($deadlineStr);
            return $completed->lessThanOrEqualTo($deadline);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get aggregated programs for the database view
     */
    public function getPrograms(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$this->canViewProgramRegular($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // We fetch essentially all programs that aren't cancelled
            // With comprehensive relations needed for the aggregate view.
            $query = PrProgram::where('status', '!=', 'cancelled')
                ->with([
                    'episodes' => function ($query) {
                        $query->orderBy('episode_number', 'asc');
                    },
                    'episodes.creativeWork.createdBy',
                    'episodes.productionWork.createdBy',
                    'episodes.qualityControlWork.createdBy',
                    'episodes.managerDistribusiQcWork.createdBy',
                    'episodes.broadcastingWork.createdBy',
                    'episodes.designGrafisWork.createdBy',
                    'episodes.editorPromosiWork.createdBy',
                    'episodes.promotionWork.createdBy',
                ]);

            $programs = $query->orderBy('created_at', 'desc')->get();
            $rows = [];

            foreach ($programs as $program) {
                foreach ($program->episodes as $ep) {
                    
                    // Merge QC logic
                    $qcRecords = [];
                    if ($ep->qualityControlWork) {
                        $qcRecords[] = [
                            'role' => 'Quality Control',
                            'status' => $ep->qualityControlWork->status,
                            'link' => $ep->qualityControlWork->qc_link,
                            'completed_at' => $ep->qualityControlWork->completed_at,
                            'completed_by' => $ep->qualityControlWork->createdBy->name ?? null,
                            'feedback' => $ep->qualityControlWork->qc_feedback,
                        ];
                    }
                    if ($ep->managerDistribusiQcWork) {
                        $qcRecords[] = [
                            'role' => 'Distribution Manager QC',
                            'status' => $ep->managerDistribusiQcWork->status,
                            'link' => $ep->managerDistribusiQcWork->qc_link,
                            'completed_at' => $ep->managerDistribusiQcWork->completed_at,
                            'completed_by' => $ep->managerDistribusiQcWork->createdBy->name ?? null,
                            'feedback' => $ep->managerDistribusiQcWork->qc_feedback,
                        ];
                    }

                    // Promotion & Design Links array
                    $promotionLinks = [];
                    if ($ep->promotionWork && $ep->promotionWork->status === 'completed') {
                        // Assuming promotionWork might store chips link. Typically stored in notes or dedicated field.
                        // Let's grab chips_link or default properties if they exist
                        // As standard, we'll use `chips_link` and `notes`
                        if (isset($ep->promotionWork->chips_link)) {
                            $promotionLinks[] = $ep->promotionWork->chips_link;
                        }
                        if (isset($ep->promotionWork->notes)) {
                             $promotionLinks[] = $ep->promotionWork->notes;
                        }
                    }
                    if ($ep->editorPromosiWork && $ep->editorPromosiWork->status === 'completed') {
                        if (isset($ep->editorPromosiWork->file_link)) {
                             $promotionLinks[] = $ep->editorPromosiWork->file_link;
                        }
                    }

                    // Create aggregate row
                    $rows[] = [
                        'episode_id' => $ep->id,
                        'program_name' => $program->name,
                        'air_date' => $ep->air_date,
                        'title' => $ep->title,
                        'script' => [
                            'file_url' => $ep->creativeWork->script_file_link ?? null,
                            'submitted_at' => $ep->creativeWork->completed_at ?? null,
                            'submitted_by' => $ep->creativeWork->createdBy->name ?? null,
                            'on_time' => $this->isOnTime($ep->creativeWork->completed_at ?? null, $ep->air_date) // A mock if we don't have the explicit script deadline
                        ],
                        'hosts' => [], // Add if exist
                        'guests' => [], // Add if exist
                        'audience_questions' => $ep->creativeWork->script_content ?? null,
                        'shooting_date' => $ep->creativeWork->shooting_schedule ?? null,
                        'qc' => $qcRecords,
                        'poster' => [
                            'link' => $ep->designGrafisWork->episode_poster_link ?? null,
                            'submitted_at' => $ep->designGrafisWork->completed_at ?? null,
                            'submitted_by' => $ep->designGrafisWork->createdBy->name ?? null,
                            'on_time' => $this->isOnTime($ep->designGrafisWork->completed_at ?? null, $ep->air_date)
                        ],
                        'promotion' => [
                            'links' => array_filter($promotionLinks),
                            'completed_at' => $ep->promotionWork->completed_at ?? null,
                            'completed_by' => $ep->promotionWork->createdBy->name ?? null,
                            'on_time' => $this->isOnTime($ep->promotionWork->completed_at ?? null, $ep->air_date)
                        ],
                        'youtube_url' => $ep->broadcastingWork->youtube_url ?? null,
                        'workflow_status' => $ep->status,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $rows
            ]);

        } catch (\Exception $e) {
            Log::error('PrDatabaseController.getPrograms Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load database programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Data CSV/Excel stub
     */
    public function importData(Request $request): JsonResponse
    {
        // To be implemented: Parsing the raw file data
        return response()->json([
            'success' => true,
            'message' => 'Import received successfully. Processing logic is pending implementation.',
            'data' => []
        ]);
    }

    /**
     * Import Data from Google Sheet stub
     */
    public function importFromSheet(Request $request): JsonResponse
    {
        // To be implemented: Fetching CSV from Google Sheet
        $url = $request->input('url');
        return response()->json([
            'success' => true,
            'message' => 'Sheet import requested. Processing logic is pending implementation.',
            'url' => $url,
            'data' => []
        ]);
    }

    /**
     * Export data to CSV format
     */
    public function exportData(Request $request)
    {
        try {
            // Re-fetch aggregate logic roughly
            // In a real scenario, could use Maatwebsite/Excel
            $data = json_decode($this->getPrograms($request)->getContent(), true);
            if (!isset($data['success']) || !$data['success']) {
                return response()->json(['success' => false, 'message' => 'Failed export'], 500);
            }

            $rows = $data['data'];

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=program_database.csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $columns = array('Episode ID', 'Program Name', 'Episode Title', 'Air Date', 'Shooting Date', 'YouTube URL');

            $callback = function() use($rows, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);
                foreach ($rows as $row) {
                    fputcsv($file, array(
                        $row['episode_id'],
                        $row['program_name'],
                        $row['title'],
                        $row['air_date'],
                        $row['shooting_date'],
                        $row['youtube_url']
                    ));
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'Failed to export',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
