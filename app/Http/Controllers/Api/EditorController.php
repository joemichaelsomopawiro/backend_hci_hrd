<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditorWork;
use App\Models\Episode;
use App\Models\ProduksiWork;
use App\Models\ShootingRunSheet;
use App\Models\SoundEngineerEditing;
use App\Models\SoundEngineerRecording;
use App\Models\Notification;
use App\Helpers\FileUploadHelper;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use App\Services\WorkAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class EditorController extends Controller
{
    /**
     * Get all editor works
     * GET /api/live-tv/editor/works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = EditorWork::with(['episode.program', 'createdBy', 'reviewedBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by work type
            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            // Filter by episode
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }

            // Show only current user's works
            if ($request->boolean('my_works', false)) {
                $query->where('created_by', $user->id);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Editor works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving editor works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new editor work
     * POST /api/live-tv/editor/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:main_episode,bts,highlight_ig,highlight_tv,highlight_facebook,advertisement',
                'editing_notes' => 'nullable|string|max:5000',
                'source_files' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get episode details for auto-assignment logic
            $episode = Episode::with('program')->findOrFail($request->episode_id);
            
            // AUTO-ASSIGNMENT LOGIC: Use WorkAssignmentService to determine assignee
            // Checks if previous episode's work was reassigned, and reverts to original user if yes
            $assignedUserId = WorkAssignmentService::getNextAssignee(
                EditorWork::class,
                $episode->program_id,
                $episode->episode_number,
                $request->work_type,  // Work type filter
                $user->id             // Fallback to current user
            );

            $work = EditorWork::create([
                'episode_id' => $request->episode_id,
                'work_type' => $request->work_type,
                'editing_notes' => $request->editing_notes,
                'source_files' => $request->source_files,
                'status' => 'draft',
                'file_complete' => false,
                'created_by' => $assignedUserId,         // AUTO-ASSIGNED (may be different from current user)
                'originally_assigned_to' => null,         // Reset for new task
                'was_reassigned' => false                 // Reset for new task
            ]);

            // Audit logging
            ControllerSecurityHelper::logCreate($work, $request->all(), $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Editor work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific editor work
     * GET /api/live-tv/editor/works/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with([
                'episode.program',
                'createdBy',
                'reviewedBy'
            ])->findOrFail($id);

            // Get production files
            $produksiWork = ProduksiWork::where('episode_id', $work->episode_id)
                ->where('status', 'completed')
                ->first();

            // Get approved audio (Mix)
            $approvedAudio = SoundEngineerEditing::where('episode_id', $work->episode_id)
                ->where('status', 'approved')
                ->first();

            // Get approved vocal (Recording)
            $approvedVocal = SoundEngineerRecording::where('episode_id', $work->episode_id)
                ->where('status', 'reviewed')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work,
                    'produksi_files' => $produksiWork ? [
                        'shooting_files' => $produksiWork->shooting_files,
                        'shooting_file_links' => $produksiWork->shooting_file_links,
                        'run_sheet_id' => $produksiWork->run_sheet_id
                    ] : null,
                    'approved_audio' => $approvedAudio ? [
                        'id' => $approvedAudio->id,
                        'final_file_path' => $approvedAudio->final_file_path,
                        'final_file_link' => $approvedAudio->final_file_link ? (strpos($approvedAudio->final_file_link, 'http') === 0 ? $approvedAudio->final_file_link : 'https://' . $approvedAudio->final_file_link) : null,
                        'editing_notes' => $approvedAudio->editing_notes
                    ] : null,
                    'approved_vocal' => $approvedVocal ? [
                        'id' => $approvedVocal->id,
                        'file_path' => $approvedVocal->file_path,
                        'file_link' => $approvedVocal->file_link ? (strpos($approvedVocal->file_link, 'http') === 0 ? $approvedVocal->file_link : 'https://' . $approvedVocal->file_link) : null,
                        'review_notes' => $approvedVocal->review_notes
                    ] : null
                ],
                'message' => 'Editor work retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept work
     * POST /api/live-tv/editor/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            if (!in_array($work->status, ['draft', 'editing', 'rejected'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be accepted. Current status: {$work->status}"
                ], 400);
            }

            $work->update([
                'status' => 'editing',
                'created_by' => $user->id
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_work_accepted',
                    'title' => 'Editor Work Accepted',
                    'message' => "Editor {$user->name} telah menerima pekerjaan editing untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_id' => $user->id
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], ['status' => 'editing', 'created_by' => $user->id], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now check file completeness and proceed with editing.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check file completeness
     * POST /api/live-tv/editor/works/{id}/check-file-completeness
     */
    public function checkFileCompleteness(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            // Team-based authorization: allow if creator OR production team member
            $productionTeam = $work->episode->program->productionTeam;
            $isTeamMember = false;
            
            if ($productionTeam) {
                $isTeamMember = $productionTeam->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'editor')
                    ->where('is_active', true)
                    ->exists();
            }
            
            if ($work->created_by !== $user->id && !$isTeamMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you or your production team.'
                ], 403);
            }

            // Get production files (Allow in_progress if links exist)
            $produksiWork = ProduksiWork::where('episode_id', $work->episode_id)
                ->whereIn('status', ['completed', 'in_progress'])
                ->orderBy('status', 'desc') // completed first
                ->first();

            // Get approved audio (Mixing/Editing)
            $approvedAudio = SoundEngineerEditing::where('episode_id', $work->episode_id)
                ->where('status', 'approved')
                ->first();
            
            // Get approved vocal (Recording)
            $approvedVocal = SoundEngineerRecording::where('episode_id', $work->episode_id)
                ->where('status', 'reviewed')
                ->first();

            // Check completeness
            $hasProductionFiles = $produksiWork && (!empty($produksiWork->shooting_files) || !empty($produksiWork->shooting_file_links));
            // Check audio from SoundEngineerEditing (support both file_path and file_link)
            $hasAudio = $approvedAudio && (!empty($approvedAudio->final_file_path) || !empty($approvedAudio->final_file_link));
            $isComplete = $hasProductionFiles && $hasAudio;

            // Update work dengan source files lengkap
            $sourceFiles = $work->source_files ?? [];
            $sourceFiles['produksi_files_available'] = $hasProductionFiles;
            $sourceFiles['audio_available'] = $hasAudio;
            $sourceFiles['checked_at'] = now()->toDateTimeString();
            $sourceFiles['checked_by'] = $user->id;
            
            // Jika audio available, tambahkan info audio ke source_files
            if ($hasAudio && $approvedAudio) {
                $audioUrl = $approvedAudio->final_file_link;
                if ($audioUrl) {
                    if (strpos($audioUrl, 'http') !== 0) {
                        $audioUrl = 'https://' . $audioUrl;
                    }
                } elseif ($approvedAudio->final_file_path) {
                    $audioUrl = url('storage/' . $approvedAudio->final_file_path);
                }

                $sourceFiles['approved_audio'] = [
                    'editing_id' => $approvedAudio->id,
                    'final_file_path' => $approvedAudio->final_file_path,
                    'final_file_link' => $audioUrl,
                    'editing_notes' => $approvedAudio->editing_notes,
                    'approved_at' => $approvedAudio->approved_at?->toDateTimeString()
                ];
            }

            // Jika vocal recording available (yang sudah diapprove producer), tambambahkan ke source_files
            if ($approvedVocal) {
                $vocalUrl = $approvedVocal->file_link;
                if ($vocalUrl) {
                    if (strpos($vocalUrl, 'http') !== 0) {
                        $vocalUrl = 'https://' . $vocalUrl;
                    }
                } elseif ($approvedVocal->file_path) {
                    $vocalUrl = url('storage/' . $approvedVocal->file_path);
                }

                $sourceFiles['approved_vocal'] = [
                    'recording_id' => $approvedVocal->id,
                    'file_path' => $approvedVocal->file_path,
                    'file_link' => $vocalUrl,
                    'review_notes' => $approvedVocal->review_notes,
                    'approved_at' => $approvedVocal->reviewed_at?->toDateTimeString()
                ];
            }
            
            // Jika produksi files available, tambahkan info produksi
            if ($hasProductionFiles && $produksiWork) {
                $links = $produksiWork->shooting_file_links ?? [];
                
                // Ensure all links are absolute
                $formattedLinks = array_map(function($link) {
                    $url = is_array($link) ? ($link['file_link'] ?? '') : $link;
                    
                    if ($url && strpos($url, 'http') !== 0) {
                        // Case 1: Likely external link missing protocol (google.com, drive.google.com)
                        if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}/i', $url) && !preg_match('/^(shooting|audio|video|export|vocal)\//i', $url)) {
                            $url = 'https://' . $url;
                        } else {
                            // Case 2: Likely local path
                            $url = url('storage/' . ltrim($url, '/'));
                        }
                    }
                    
                    if (is_array($link)) {
                        $link['file_link'] = $url;
                        return $link;
                    }
                    
                    return ['file_link' => $url];
                }, $links);

                $sourceFiles['produksi_work'] = [
                    'produksi_work_id' => $produksiWork->id,
                    'shooting_files' => $produksiWork->shooting_files,
                    'shooting_file_links' => $formattedLinks
                ];
            }
            
            $work->update([
                'source_files' => $sourceFiles
            ]);

            $missingFiles = [];
            if (!$hasProductionFiles) {
                $missingFiles[] = 'Production shooting files';
            }
            if (!$hasAudio) {
                $missingFiles[] = 'Approved audio file from Sound Engineer';
            }

            $message = $isComplete 
                ? 'All files are available. Please review each file and confirm completeness.' 
                : 'Some files are missing. Please review and report issues to Producer.';

            // Return files for manual review - do NOT auto-advance status
            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode']),
                    'file_complete' => $isComplete,
                    'missing_files' => $missingFiles,
                    'has_production_files' => $hasProductionFiles,
                    'has_audio' => $hasAudio,
                    'has_vocal' => !!$approvedVocal,
                    'requires_manual_confirmation' => true
                ],
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking file completeness: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report missing files to Producer
     * POST /api/live-tv/editor/works/{id}/report-missing-files
     */
    public function reportMissingFiles(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'missing_files' => 'required|array|min:1',
                'missing_files.*.file_type' => 'required|string',
                'missing_files.*.description' => 'required|string|max:1000',
                'missing_files.*.notes' => 'nullable|string|max:2000',
                'notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            // Editor role already verified - any editor can report missing files

            // Update work with missing files report
            $work->update([
                'file_notes' => ($work->file_notes ? $work->file_notes . "\n\n" : '') . 
                    "[Missing Files Report - " . now()->format('Y-m-d H:i:s') . "]\n" .
                    ($request->notes ?? '') . "\n" .
                    json_encode($request->missing_files, JSON_PRETTY_PRINT),
                'file_complete' => false,
                'status' => 'editing' // Tetap editing, tapi file tidak lengkap
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_missing_files_reported',
                    'title' => 'Editor Melaporkan File Kurang',
                    'message' => "Editor {$user->name} melaporkan file yang kurang atau perlu perbaikan untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'missing_files' => $request->missing_files,
                        'notes' => $request->notes,
                        'editor_id' => $user->id
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], [
                'file_notes' => $work->file_notes,
                'file_complete' => false
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Missing files reported to Producer successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting missing files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm file completeness (manual verification by Editor)
     * POST /api/live-tv/editor/works/{id}/confirm-file-completeness
     */
    public function confirmFileCompleteness(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_status' => 'required|array',
                'file_status.production_files' => 'required|in:ok,issue,missing',
                'file_status.audio' => 'required|in:ok,issue,missing',
                'file_status.vocal' => 'nullable|in:ok,issue,missing,not_applicable',
                'notes' => 'nullable|string|max:5000',
                'file_notes' => 'nullable|array',
                'file_notes.production_files' => 'nullable|string|max:2000',
                'file_notes.audio' => 'nullable|string|max:2000',
                'file_notes.vocal' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::with(['episode.program.productionTeam'])->findOrFail($id);

            // Team-based authorization
            $productionTeam = $work->episode->program->productionTeam;
            $isTeamMember = false;
            
            if ($productionTeam) {
                $isTeamMember = $productionTeam->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'editor')
                    ->where('is_active', true)
                    ->exists();
            }
            
            if ($work->created_by !== $user->id && !$isTeamMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you or your production team.'
                ], 403);
            }

            $fileStatus = $request->file_status;
            $fileNotes = $request->file_notes ?? [];
            
            // Check if all required files are marked as OK
            $allOk = ($fileStatus['production_files'] === 'ok') && ($fileStatus['audio'] === 'ok');

            // Update source_files with manual check results
            $sourceFiles = $work->source_files ?? [];
            $sourceFiles['manual_check'] = [
                'checked_by' => $user->id,
                'checked_by_name' => $user->name,
                'checked_at' => now()->toDateTimeString(),
                'file_status' => $fileStatus,
                'file_notes' => $fileNotes,
                'overall_notes' => $request->notes,
                'result' => $allOk ? 'complete' : 'incomplete'
            ];

            if ($allOk) {
                // All files verified OK - advance to editing
                $work->update([
                    'file_complete' => true,
                    'source_files' => $sourceFiles,
                    'status' => 'editing'
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $work->fresh(['episode', 'createdBy']),
                    'message' => 'File completeness confirmed. You can now proceed with editing.'
                ]);
            }

            // Has issues - store notes and notify Producer
            $issueNotes = "[File Check Report - " . now()->format('Y-m-d H:i:s') . "]\n";
            $issueNotes .= "Checked by: {$user->name}\n\n";
            
            foreach ($fileStatus as $category => $status) {
                if ($status !== 'ok') {
                    $label = str_replace('_', ' ', ucfirst($category));
                    $issueNotes .= "- {$label}: {$status}";
                    if (!empty($fileNotes[$category])) {
                        $issueNotes .= " — {$fileNotes[$category]}";
                    }
                    $issueNotes .= "\n";
                }
            }
            
            if ($request->notes) {
                $issueNotes .= "\nAdditional Notes: {$request->notes}";
            }

            $work->update([
                'file_complete' => false,
                'file_notes' => ($work->file_notes ? $work->file_notes . "\n\n" : '') . $issueNotes,
                'source_files' => $sourceFiles
            ]);

            // Notify Producer
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                $episode = $work->episode;
                
                // Build issue summary for notification
                $issueSummary = [];
                foreach ($fileStatus as $category => $status) {
                    if ($status !== 'ok') {
                        $label = str_replace('_', ' ', ucfirst($category));
                        $note = !empty($fileNotes[$category]) ? ": {$fileNotes[$category]}" : '';
                        $issueSummary[] = "{$label} ({$status}){$note}";
                    }
                }

                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_file_issues_reported',
                    'title' => 'File Bermasalah - Perlu Tindakan',
                    'message' => "Editor {$user->name} menemukan masalah file untuk Episode {$episode->episode_number}: " . implode('; ', $issueSummary),
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_status' => $fileStatus,
                        'file_notes' => $fileNotes,
                        'overall_notes' => $request->notes,
                        'editor_id' => $user->id
                    ]
                ]);
            }

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'File issues reported to Producer. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error confirming file completeness: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process work (start editing)
     * POST /api/live-tv/editor/works/{id}/process-work
     */
    public function processWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            // Team-based authorization: allow if creator OR production team member
            $productionTeam = $work->episode->program->productionTeam;
            $isTeamMember = false;
            
            if ($productionTeam) {
                $isTeamMember = $productionTeam->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'editor')
                    ->where('is_active', true)
                    ->exists();
            }
            
            if ($work->created_by !== $user->id && !$isTeamMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you or your production team.'
                ], 403);
            }

            if ($work->status !== 'editing') {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be processed. Current status: {$work->status}. Please accept work first."
                ], 400);
            }

            if (!$work->file_complete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Files are not complete. Please check file completeness or report missing files first.'
                ], 400);
            }

            // Update status (already editing, but this confirms processing has started)
            $work->update([
                'status' => 'editing',
                'editing_notes' => ($work->editing_notes ? $work->editing_notes . "\n\n" : '') .
                    "[Processing Started - " . now()->format('Y-m-d H:i:s') . "]\n" .
                    ($request->notes ?? 'Processing started')
            ]);

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], ['status' => 'editing'], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work processing started successfully. You can now proceed with editing.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get run sheet (catatan syuting)
     * GET /api/live-tv/editor/episodes/{id}/run-sheet
     */
    public function getRunSheet(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episode = Episode::findOrFail($id);

            // Get run sheet from produksi work (Allow in_progress if runSheet exists)
            $produksiWork = ProduksiWork::where('episode_id', $episode->id)
                ->whereIn('status', ['completed', 'in_progress'])
                ->whereNotNull('run_sheet_id')
                ->with('runSheet')
                ->orderBy('status', 'desc')
                ->first();

            if (!$produksiWork || !$produksiWork->runSheet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Run sheet not found for this episode'
                ], 404);
            }

            $runSheet = $produksiWork->runSheet;

            return response()->json([
                'success' => true,
                'data' => [
                    'run_sheet' => $runSheet,
                    'episode' => [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title
                    ],
                    'produksi_work' => [
                        'id' => $produksiWork->id,
                        'status' => $produksiWork->status,
                        'shooting_files' => $produksiWork->shooting_files,
                        'shooting_file_links' => array_map(function($link) {
                            $url = is_array($link) ? ($link['file_link'] ?? '') : $link;
                            
                            if ($url && strpos($url, 'http') !== 0) {
                                // Case 1: Likely external link missing protocol
                                if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}/i', $url) && !preg_match('/^(shooting|audio|video|export|vocal)\//i', $url)) {
                                    $url = 'https://' . $url;
                                } else {
                                    // Case 2: Likely local path
                                    $url = url('storage/' . ltrim($url, '/'));
                                }
                            }
                            
                            if (is_array($link)) {
                                $link['file_link'] = $url;
                                return $link;
                            }
                            
                            return ['file_link' => $url];
                        }, $produksiWork->shooting_file_links ?? [])
                    ]
                ],
                'message' => 'Run sheet retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving run sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update editor work (with file upload support)
     * PUT /api/live-tv/editor/works/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            // Team-based authorization: allow if creator OR production team member
            $productionTeam = $work->episode->program->productionTeam;
            $isTeamMember = false;
            
            if ($productionTeam) {
                $isTeamMember = $productionTeam->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'editor')
                    ->where('is_active', true)
                    ->exists();
            }
            
            if ($work->created_by !== $user->id && !$isTeamMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you or your production team.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'editing_notes' => 'nullable|string|max:5000',
                'file' => 'nullable|file|mimes:mp4,avi,mov,mkv|max:1024000', // Max 1GB (backward compatibility)
                'file_link' => 'nullable|url|max:2048', // New: External storage link
                'file_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldData = $work->toArray();
            $updateData = [];

            // Handle file upload (backward compatibility)
            // Physical file upload removed
            if ($request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical file uploads are disabled. Please use the file_link or inputFileLinks endpoint.'
                ], 405);
            }
            
            // Handle file_link (new: external storage link)
            if ($request->has('file_link')) {
                $updateData['file_link'] = $request->file_link;
            }

            // Update other fields
            if ($request->has('editing_notes')) {
                $updateData['editing_notes'] = $request->editing_notes;
            }

            if ($request->has('file_notes')) {
                $updateData['file_notes'] = $request->file_notes;
            }

            if (!empty($updateData)) {
                $work->update($updateData);
            }

            // Audit logging
            if (!empty($updateData)) {
                ControllerSecurityHelper::logUpdate($work, $oldData, $updateData, $request);
            }

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => $request->hasFile('file') 
                    ? 'File uploaded and work updated successfully. File path has been saved to system.'
                    : 'Work updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input file links (manual input)
     * POST /api/live-tv/editor/works/{id}/input-file-links
     */
    public function inputFileLinks(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_links' => 'required|array|min:1',
                'file_links.*.url' => 'required|url',
                'file_links.*.file_name' => 'nullable|string|max:255',
                'file_links.*.file_size' => 'nullable|integer',
                'file_links.*.mime_type' => 'nullable|string|max:100',
                'file_links.*.type' => 'nullable|string|max:50' // video, audio, etc
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            // Team-based authorization: allow if creator OR production team member
            $productionTeam = $work->episode->program->productionTeam;
            $isTeamMember = false;
            
            if ($productionTeam) {
                $isTeamMember = $productionTeam->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'editor')
                    ->where('is_active', true)
                    ->exists();
            }
            
            if ($work->created_by !== $user->id && !$isTeamMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you or your production team.'
                ], 403);
            }

            // Update work with file links
            $existingSourceFiles = $work->source_files ?? [];
            $updateData = [
                'source_files' => array_merge($existingSourceFiles, [
                    'manual_file_links' => $request->file_links,
                    'links_added_at' => now()->toDateTimeString(),
                    'links_added_by' => $user->id
                ])
            ];

            // If no file_path exists, use first link as file_path
            if (!$work->file_path && !empty($request->file_links)) {
                $firstLink = $request->file_links[0];
                $updateData['file_path'] = $firstLink['url'];
                $updateData['file_name'] = $firstLink['file_name'] ?? 'External File';
                $updateData['file_size'] = $firstLink['file_size'] ?? null;
                $updateData['mime_type'] = $firstLink['mime_type'] ?? null;
            }

            $work->update($updateData);

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], $updateData, $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'File links added successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding file links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit work to Producer
     * POST /api/live-tv/editor/works/{id}/submit
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_notes' => 'nullable|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = EditorWork::with(['episode'])->findOrFail($id);

            // Team-based authorization: allow if creator OR production team member
            $productionTeam = $work->episode->program->productionTeam;
            $isTeamMember = false;
            
            if ($productionTeam) {
                $isTeamMember = $productionTeam->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'editor')
                    ->where('is_active', true)
                    ->exists();
            }
            
            if ($work->created_by !== $user->id && !$isTeamMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you or your production team.'
                ], 403);
            }

            if ($work->status !== 'editing' && $work->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => "Work cannot be submitted. Current status: {$work->status}"
                ], 400);
            }

            // Validate if file is uploaded or file_link is provided
            if (!$work->file_path && !$work->file_link) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload edited file or provide file_link before submitting.'
                ], 400);
            }

            // Update work - Set status to 'submitted' (Waiting for QC/Producer approval)
            $work->update([
                'status' => 'submitted',
                'editing_notes' => ($work->editing_notes ? $work->editing_notes . "\n\n" : '') .
                    "[Submitted - " . now()->format('Y-m-d H:i:s') . "]\n" .
                    ($request->submission_notes ?? '')
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_work_submitted',
                    'title' => 'Editor Work Submitted',
                    'message' => "Editor {$user->name} telah submit hasil editing untuk Episode {$episode->episode_number}. Mohon review.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_path' => $work->file_path, // Backward compatibility
                        'file_link' => $work->file_link, // New: External storage link
                        'submission_notes' => $request->submission_notes
                    ]
                ]);
            }

            // Notify Manager Broadcasting and Distribution Manager for QC
            $qcManagers = \App\Models\User::whereIn('role', ['Manager Broadcasting', 'Distribution Manager'])->get();
            foreach ($qcManagers as $qcManager) {
                Notification::create([
                    'user_id' => $qcManager->id,
                    'type' => 'editor_work_submitted_to_qc',
                    'title' => 'Materi Episode Siap QC',
                    'message' => "Editor {$user->name} telah submit hasil editing untuk Episode {$episode->episode_number}. Produk siap untuk dilakukan Quality Control.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_link' => $work->file_link,
                        'submission_notes' => $request->submission_notes
                    ]
                ]);
            }

            // Auto-create PromotionWork for Editor Promosi (multiple work types)
            $editorPromosiWorkTypes = [
                'bts_video' => 'Edit Video BTS',
                'highlight_ig' => 'Buat Highlight Episode IG',
                'highlight_tv' => 'Buat Highlight Episode TV',
                'highlight_facebook' => 'Buat Highlight Episode Facebook',
                'iklan_episode_tv' => 'Edit Iklan Episode TV'
            ];

            $createdPromotionWorks = [];

            foreach ($editorPromosiWorkTypes as $workType => $titlePrefix) {
                $existingPromotionWork = \App\Models\PromotionWork::where('episode_id', $work->episode_id)
                    ->where('work_type', $workType)
                    ->first();

                if (!$existingPromotionWork) {
                    $promotionWork = \App\Models\PromotionWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => $workType,
                        'title' => "{$titlePrefix} - Episode {$episode->episode_number}",
                        'description' => "Editing task untuk {$titlePrefix}. File referensi dari Editor sudah tersedia.",
                        'status' => 'editing', // Siap untuk diterima Editor Promosi
                        'file_paths' => [
                            'editor_work_id' => $work->id,
                            'editor_file_path' => $work->file_path, // Backward compatibility
                            'editor_file_link' => $work->file_link, // New: External storage link
                            'editor_file_name' => $work->file_name,
                            'available' => true,
                            'fetched_at' => now()->toDateTimeString()
                        ],
                        'created_by' => $user->id // Assign ke Editor yang submit (akan diganti saat Editor Promosi accept)
                    ]);
                    $createdPromotionWorks[] = $promotionWork;
                } else {
                    // Update existing PromotionWork dengan file terbaru dari Editor
                    $existingFilePaths = $existingPromotionWork->file_paths ?? [];
                    $existingPromotionWork->update([
                        'file_paths' => array_merge($existingFilePaths, [
                            'editor_work_id' => $work->id,
                            'editor_file_path' => $work->file_path, // Backward compatibility
                            'editor_file_link' => $work->file_link, // New: External storage link
                            'editor_file_name' => $work->file_name,
                            'updated_at' => now()->toDateTimeString()
                        ])
                    ]);
                    $createdPromotionWorks[] = $existingPromotionWork;
                }
            }

            // Notify Editor Promosi - File dari Editor sudah tersedia
            $editorPromosiUsers = \App\Models\User::where('role', 'Editor Promotion')->get();
            $editorPromosiUsers = \App\Models\User::where('role', 'Editor Promotion')->get();
            $promosiNotifications = [];
            $now = now();

            foreach ($editorPromosiUsers as $editorPromosiUser) {
                $promosiNotifications[] = [
                    'user_id' => $editorPromosiUser->id,
                    'type' => 'editor_files_available',
                    'title' => 'File Editor Tersedia',
                    'message' => "Editor telah submit hasil editing untuk Episode {$episode->episode_number}. PromotionWork untuk BTS, Highlight, dan Iklan TV sudah dibuat.",
                    'data' => json_encode([
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_file_path' => $work->file_path, // Backward compatibility
                        'editor_file_link' => $work->file_link, // New: External storage link
                        'promotion_works' => array_map(function($pw) {
                            return [
                                'id' => $pw->id,
                                'work_type' => $pw->work_type,
                                'title' => $pw->title
                            ];
                        }, $createdPromotionWorks)
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            if (!empty($promosiNotifications)) {
                Notification::insert($promosiNotifications);
            }

            // ✨ NEW: Auto-create BroadcastingWork for Distribution Manager (Editor Approval) ✨
            // Per user request: Distribution Manager performs QC for main episode editing.
            $existingBroadcastingWork = \App\Models\BroadcastingWork::where('episode_id', $work->episode_id)
                ->where('work_type', 'main_episode')
                ->first();

            $dmUsers = \App\Models\User::whereIn('role', ['Distribution Manager', 'Manager Broadcasting'])->get();
            $broadcastingUsers = \App\Models\User::where('role', 'Broadcasting')->get();
            
            // We need a creator for the BroadcastingWork. If no broadcasting user exists, use a DM or system user.
            $assignToId = $broadcastingUsers->first()?->id ?? ($dmUsers->first()?->id ?? $user->id);

            if (!$existingBroadcastingWork) {
                \App\Models\BroadcastingWork::create([
                    'episode_id' => $work->episode_id,
                    'editor_work_id' => $work->id,
                    'work_type' => 'main_episode',
                    'title' => "Broadcasting Work - Episode {$episode->episode_number}",
                    'description' => "File dari Editor untuk QC & Approval. Menunggu persetujuan Distribution Manager.",
                    'video_file_path' => $work->file_path,
                    'file_link' => $work->file_link,
                    'status' => 'pending_approval',
                    'created_by' => $assignToId
                ]);
            } else {
                // Update existing work with new files if it's still in approval or pending state
                if (in_array($existingBroadcastingWork->status, ['pending_approval', 'pending', 'preparing', 'rejected'])) {
                    $existingBroadcastingWork->update([
                        'editor_work_id' => $work->id,
                        'video_file_path' => $work->file_path,
                        'file_link' => $work->file_link,
                        'status' => 'pending_approval' // Reset to pending approval if updated
                    ]);
                }
            }

            // Auto-create QualityControlWork untuk QC (Optional / Legacy)
            $existingQCWork = \App\Models\QualityControlWork::where('episode_id', $work->episode_id)
                ->where('qc_type', 'main_episode')
                ->first();

            if (!$existingQCWork) {
                $qcWork = \App\Models\QualityControlWork::create([
                    'episode_id' => $work->episode_id,
                    'qc_type' => 'main_episode',
                    'title' => "QC Work - Episode {$episode->episode_number}",
                    'description' => "File dari Editor untuk QC. Editor telah submit hasil editing.",
                    'files_to_check' => [
                        [
                            'editor_work_id' => $work->id,
                            'file_path' => $work->file_path, // Backward compatibility
                            'file_link' => $work->file_link, // New: External storage link
                            'file_name' => $work->file_name,
                            'file_size' => $work->file_size,
                            'mime_type' => $work->mime_type,
                            'source' => 'editor',
                            'submitted_at' => now()->toDateTimeString()
                        ]
                    ],
                    'status' => 'pending',
                    'created_by' => $user->id // Assign ke Editor yang submit (akan diganti saat QC accept)
                ]);

                // Notify Quality Control and related managers
                $qcUsers = \App\Models\User::whereIn('role', ['Quality Control', 'Manager Broadcasting', 'Distribution Manager'])->get();
                $qcNotifications = [];
                $now = now();

                foreach ($qcUsers as $qcUser) {
                    $qcNotifications[] = [
                        'user_id' => $qcUser->id,
                        'type' => 'qc_work_assigned',
                        'title' => 'Tugas QC Baru',
                        'message' => "Editor telah mengajukan file untuk QC Episode {$episode->episode_number}.",
                        'data' => json_encode([
                            'qc_work_id' => $qcWork->id,
                            'episode_id' => $work->episode_id,
                            'editor_work_id' => $work->id,
                            'file_path' => $work->file_path, // Backward compatibility
                            'file_link' => $work->file_link // New: External storage link
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($qcNotifications)) {
                    Notification::insert($qcNotifications);
                }
            } else {
                // Update existing QCWork dengan file terbaru dari Editor
                $existingFiles = $existingQCWork->files_to_check ?? [];
                $existingFiles[] = [
                    'editor_work_id' => $work->id,
                    'file_path' => $work->file_path, // Backward compatibility
                    'file_link' => $work->file_link, // New: External storage link
                    'file_name' => $work->file_name,
                    'file_size' => $work->file_size,
                    'mime_type' => $work->mime_type,
                    'source' => 'editor',
                    'updated_at' => now()->toDateTimeString()
                ];
                $existingQCWork->update(['files_to_check' => $existingFiles]);

                // Notify QC jika status masih pending
                if ($existingQCWork->status === 'pending') {
                    $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
                    $qcNotifications = [];
                    $now = now();

                    foreach ($qcUsers as $qcUser) {
                        $qcNotifications[] = [
                            'user_id' => $qcUser->id,
                            'type' => 'qc_work_updated',
                            'title' => 'QC Work Diperbarui',
                            'message' => "Editor telah mengupdate file untuk QC Episode {$episode->episode_number}.",
                            'data' => json_encode([
                                'qc_work_id' => $existingQCWork->id,
                                'episode_id' => $work->episode_id,
                                'editor_work_id' => $work->id
                            ]),
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    if (!empty($qcNotifications)) {
                        Notification::insert($qcNotifications);
                    }
                }
            }

            // ✨ PARALLEL NOTIFICATIONS ✨
            // Editor submits work → Notify Producer, Editor Promosi, and QC simultaneously
            $episode = $work->episode;
            $program = $episode->program;

            // 1. Notify Producer (single user)
            $productionTeam = $program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'editor_work_submitted',
                    'title' => 'Editor Work Submitted',
                    'message' => "Editor {$user->name} telah submit hasil editing untuk Episode {$episode->episode_number}. Mohon review.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_path' => $work->file_path,
                        'file_link' => $work->file_link,
                        'submission_notes' => $request->submission_notes
                    ]
                ]);
            }

            // 2. Send PARALLEL notifications to Editor Promosi and QC
            \App\Services\ParallelNotificationService::notifyRoles(
                ['Editor Promotion', 'Quality Control'],
                [
                    'type' => 'editor_submitted_files_available',
                    'title' => 'File Editor Tersedia',
                    'message' => "Editor telah submit hasil editing untuk Episode {$episode->episode_number}. Files ready for review and further processing.",
                    'data' => [
                        'editor_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_file_link' => $work->file_link,
                        'submission_notes' => $request->submission_notes
                    ]
                ],
                $program->id
            );

            // Audit logging
            ControllerSecurityHelper::logUpdate($work, [], [
                'status' => 'completed',
                'parallel_notifications_sent' => 2 // Editor Promosi, QC
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Editor work submitted successfully. 3 notifications sent (Producer, Editor Promosi, QC).'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting editor work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved audio files for episode
     * GET /api/live-tv/editor/episodes/{episodeId}/approved-audio
     */
    public function getApprovedAudioFiles(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episode = Episode::findOrFail($episodeId);

            // Get approved audio files from Sound Engineer Editing
            $approvedAudio = SoundEngineerEditing::where('episode_id', $episodeId)
                ->where('status', 'approved')
                ->with(['soundEngineer', 'recording'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => [
                        'id' => $episode->id,
                        'episode_number' => $episode->episode_number,
                        'title' => $episode->title
                    ],
                    'approved_audio_files' => $approvedAudio->map(function($audio) {
                        $audioUrl = $audio->final_file_link;
                        if ($audioUrl) {
                            if (strpos($audioUrl, 'http') !== 0) {
                                $audioUrl = 'https://' . $audioUrl;
                            }
                        } elseif ($audio->final_file_path) {
                            $audioUrl = url('storage/' . $audio->final_file_path);
                        }

                        return [
                            'id' => $audio->id,
                            'type' => 'editing_mix',
                            'final_file_path' => $audio->final_file_path,
                            'final_file_link' => $audioUrl,
                            'editing_notes' => $audio->editing_notes,
                            'submission_notes' => $audio->submission_notes,
                            'approved_at' => $audio->approved_at,
                            'sound_engineer' => $audio->soundEngineer ? [
                                'id' => $audio->soundEngineer->id,
                                'name' => $audio->soundEngineer->name
                            ] : null
                        ];
                    })->toArray(),
                    'approved_vocal_files' => SoundEngineerRecording::where('episode_id', $episodeId)
                        ->where('status', 'reviewed')
                        ->with(['createdBy'])
                        ->get()
                        ->map(function($vocal) {
                            $vocalUrl = $vocal->file_link;
                            if ($vocalUrl) {
                                if (strpos($vocalUrl, 'http') !== 0) {
                                    $vocalUrl = 'https://' . $vocalUrl;
                                }
                            } elseif ($vocal->file_path) {
                                $vocalUrl = url('storage/' . $vocal->file_path);
                            }

                            return [
                                'id' => $vocal->id,
                                'type' => 'vocal_recording',
                                'file_path' => $vocal->file_path,
                                'file_link' => $vocalUrl,
                                'review_notes' => $vocal->review_notes,
                                'approved_at' => $vocal->reviewed_at,
                                'sound_engineer' => $vocal->createdBy ? [
                                    'id' => $vocal->createdBy->id,
                                    'name' => $vocal->createdBy->name
                                ] : null
                            ];
                        })->toArray()
                ],
                'message' => 'Approved audio files retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approved audio files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get editor statistics
     * GET /api/live-tv/editor/works/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Editor') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = EditorWork::where('created_by', $user->id);

            // Total works for this editor
            $totalWorks = (clone $query)->count();
            
            // Draft/Pending/Rejected works (Needs revision or initial)
            $draftWorks = (clone $query)->whereIn('status', ['draft', 'pending', 'rejected'])->count();
            
            // In Progress/Editing works
            $editingWorks = (clone $query)->whereIn('status', ['editing', 'in_progress'])->count();
            
            // Submitted works (waiting for QC/Producer)
            $submittedWorks = (clone $query)->where('status', 'submitted')->count();

            // Approved/Completed works (Final)
            $approvedWorks = (clone $query)->whereIn('status', ['approved', 'completed'])->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_works' => $totalWorks,
                    'draft_works' => $draftWorks,
                    'editing_works' => $editingWorks,
                    'submitted_works' => $submittedWorks,
                    'approved_works' => $approvedWorks
                ],
                'message' => 'Editor statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
