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
use Illuminate\Support\Facades\Storage;

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

            // Target PrEpisode directly for better filtering and accuracy
            $query = PrEpisode::query()
                ->whereHas('program', function($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->with([
                    'program.crews.user',
                    'program.producer',
                    'program.managerDistribusi',
                    'files',
                    'crews.user',
                    'workflowProgress.assignedUser',
                    'creativeWork.createdBy',
                    'creativeWork.reviewedBy',
                    'creativeWork.budgetApprovedBy',
                    'creativeWork.specialBudgetApprover',
                    'productionWork.createdBy',
                    'qualityControlWork.createdBy',
                    'qualityControlWork.reviewedBy',
                    'managerDistribusiQcWork.createdBy',
                    'managerDistribusiQcWork.reviewedBy',
                    'broadcastingWork.createdBy',
                    'broadcastingWork.createdBy',
                    'designGrafisWork.assignedUser',
                    'editorPromosiWork.assignedUser',
                    'editorWork.assignedUser',
                    'promotionWork.createdBy',
                ]);

            // --- Apply Filtering ---
            
            // 1. Universal Keyword Search (q)
            if ($request->filled('q')) {
                $q = $request->q;
                $query->where(function($fq) use ($q) {
                    $fq->where('id', 'like', "%$q%")
                       ->orWhere('title', 'like', "%$q%")
                       ->orWhere('air_date', 'like', "%$q%")
                       ->orWhere('production_date', 'like', "%$q%")
                       ->orWhereHas('program', function($pq) use ($q) {
                           $pq->where('name', 'like', "%$q%");
                       })
                       ->orWhereHas('files', function($ffq) use ($q) {
                           $ffq->where('file_path', 'like', "%$q%")
                              ->orWhere('file_name', 'like', "%$q%");
                       })
                       ->orWhereHas('creativeWork', function($cw) use ($q) {
                           $cw->where('script_content', 'like', "%$q%")
                              ->orWhereRaw("CAST(talent_data AS CHAR) like ?", ["%$q%"])
                              ->orWhereRaw("CAST(budget_data AS CHAR) like ?", ["%$q%"])
                              ->orWhere('shooting_schedule', 'like', "%$q%")
                              ->orWhereHas('createdBy', fn($u) => $u->where('name', 'like', "%$q%"))
                              ->orWhereHas('reviewedBy', fn($u) => $u->where('name', 'like', "%$q%"))
                              ->orWhereHas('budgetApprovedBy', fn($u) => $u->where('name', 'like', "%$q%"));
                       })
                       ->orWhereHas('workflowProgress.assignedUser', fn($u) => $u->where('name', 'like', "%$q%"))
                       ->orWhereHas('broadcastingWork', fn($bw) => $bw->where('youtube_url', 'like', "%$q%"))
                       ->orWhereHas('designGrafisWork', function($dg) use ($q) {
                           $dg->where('episode_poster_link', 'like', "%$q%")
                              ->orWhere('youtube_thumbnail_link', 'like', "%$q%")
                              ->orWhere('bts_thumbnail_link', 'like', "%$q%")
                              ->orWhereHas('assignedUser', fn($u) => $u->where('name', 'like', "%$q%"));
                       })
                       ->orWhereHas('editorPromosiWork', function($epw) use ($q) {
                           $epw->where('bts_video_link', 'like', "%$q%")
                               ->orWhere('tv_ad_link', 'like', "%$q%")
                               ->orWhere('ig_highlight_link', 'like', "%$q%")
                               ->orWhere('tv_highlight_link', 'like', "%$q%")
                               ->orWhereRaw("CAST(fb_highlight_link AS CHAR) like ?", ["%$q%"])
                               ->orWhereHas('assignedUser', fn($u) => $u->where('name', 'like', "%$q%"));
                       })
                       ->orWhereHas('promotionWork', function($pw) use ($q) {
                           $pw->whereRaw("CAST(sharing_proof AS CHAR) like ?", ["%$q%"])
                              ->orWhere('completion_notes', 'like', "%$q%")
                              ->orWhereHas('createdBy', fn($u) => $u->where('name', 'like', "%$q%"));
                       })
                       ->orWhereHas('qualityControlWork', function($qc) use ($q) {
                           $qc->where('qc_notes', 'like', "%$q%")
                              ->orWhereRaw("CAST(editor_promosi_file_locations AS CHAR) like ?", ["%$q%"])
                              ->orWhereRaw("CAST(design_grafis_file_locations AS CHAR) like ?", ["%$q%"])
                              ->orWhereHas('createdBy', fn($u) => $u->where('name', 'like', "%$q%"));
                       });
                });
            }

            // 2. Year Filter
            if ($request->filled('year') && $request->year !== 'all') {
                $query->whereYear('air_date', $request->year);
            }

            // 3. Date Range (Air Date)
            if ($request->filled('date_start')) {
                $query->where('air_date', '>=', $request->date_start);
            }
            if ($request->filled('date_end')) {
                $query->where('air_date', '<=', $request->date_end);
            }

            // 4. Status Filter (Custom logic for On-time vs Late)
            if ($request->filled('status') && $request->status !== 'all') {
                if ($request->status === 'on_time') {
                    // Consider it on-time if the latest work (promotion) was completed on or before air_date
                    $query->whereHas('promotionWork', function($pq) {
                        $pq->whereColumn('updated_at', '<=', 'pr_episodes.air_date');
                    });
                } elseif ($request->status === 'late') {
                    $query->whereHas('promotionWork', function($pq) {
                        $pq->whereColumn('updated_at', '>', 'pr_episodes.air_date');
                    });
                }
            }

            // 5. Sorting
            $sort = $request->sort ?? 'air_date_desc';
            $sortColumn = 'air_date';
            $sortDirection = 'desc';

            // Extract column and direction (e.g., "id_asc" -> "id", "asc")
            if (preg_match('/^(.*)_(asc|desc)$/', $sort, $matches)) {
                $sortColumn = $matches[1];
                $sortDirection = $matches[2];
            }

            switch ($sortColumn) {
                case 'id':
                    $query->orderBy('id', $sortDirection);
                    break;
                case 'program':
                    // Join with pr_programs table to sort by program name
                    $query->join('pr_programs', 'pr_episodes.program_id', '=', 'pr_programs.id')
                          ->select('pr_episodes.*') // Ensure we only get episode columns to avoid ID conflicts
                          ->orderBy('pr_programs.name', $sortDirection);
                    break;
                case 'title':
                    $query->orderBy('title', $sortDirection);
                    break;
                case 'shooting':
                    $query->orderBy('production_date', $sortDirection);
                    break;
                case 'air_date':
                default:
                    $query->orderBy('air_date', $sortDirection);
                    break;
            }

            $episodes = $query->paginate($request->per_page ?? 100);
            $rows = [];

            foreach ($episodes as $ep) {
                $program = $ep->program;
                
                // --- Grouped Links & Output Logic ---
                $graphicDesign = [];
                $promotion = [];
                $promotionEditor = [];
                
                // 1. Graphic Design (Poster, Thumbnails)
                if ($ep->designGrafisWork) {
                    $dg = $ep->designGrafisWork;
                    $dgLinks = [
                        'episode_poster_link' => 'POSTER LINK',
                        'youtube_thumbnail_link' => 'YT THUMBNAIL LINK',
                        'bts_thumbnail_link' => 'BTS THUMBNAIL LINK',
                    ];
                    foreach ($dgLinks as $col => $label) {
                        if (!empty($dg->$col)) {
                            $isLate = false;
                            if ($dg->submitted_at && $dg->deadline) {
                                $isLate = \Carbon\Carbon::parse($dg->submitted_at)->gt(\Carbon\Carbon::parse($dg->deadline));
                            }
                            $graphicDesign[] = [
                                'label' => $label,
                                'link' => str_starts_with($dg->$col, 'http') ? $dg->$col : asset('storage/' . $dg->$col),
                                'completed_at' => $dg->submitted_at,
                                'completed_by' => $dg->assignedUser?->name ?? null,
                                'is_late' => $isLate
                            ];
                        }
                    }
                }

                // 2. Promotion Editor (Video Editor output + Promotion Assets & Sharing Links)
                if ($ep->editorPromosiWork) {
                    $epw = $ep->editorPromosiWork;
                    $links = [
                        'bts_video_link' => 'BTS VIDEO LINK',
                        'tv_ad_link' => 'TV AD LINK',
                        'ig_highlight_link' => 'INSTAGRAM LINK',
                        'tv_highlight_link' => 'TV HIGHLIGHT LINK',
                    ];
                    foreach ($links as $col => $label) {
                        if (!empty($epw->$col)) {
                            $isLate = false;
                            if ($epw->completed_at && $epw->deadline) {
                                $isLate = \Carbon\Carbon::parse($epw->completed_at)->gt(\Carbon\Carbon::parse($epw->deadline));
                            }
                            $promotionEditor[] = [
                                'label' => $label,
                                'link' => $epw->$col,
                                'completed_at' => $epw->completed_at ?? $epw->updated_at,
                                'completed_by' => $epw->assignedUser?->name ?? null,
                                'is_late' => $isLate
                            ];
                        }
                    }
                    if (is_array($epw->fb_highlight_link)) {
                        foreach ($epw->fb_highlight_link as $fl) {
                            if (!empty($fl)) {
                                $promotionEditor[] = [
                                    'label' => 'FACEBOOK LINK',
                                    'link' => $fl,
                                    'completed_at' => $epw->completed_at ?? $epw->updated_at,
                                    'completed_by' => $epw->assignedUser?->name ?? null,
                                ];
                            }
                        }
                    }
                }

                // Add Promotion Work assets/links to the Promotion Editor column for centralized links
                if ($ep->promotionWork) {
                    $pw = $ep->promotionWork;
                    // Platform Sharing Links
                    if (is_array($pw->sharing_proof) && isset($pw->sharing_proof['share_konten_tasks'])) {
                        foreach($pw->sharing_proof['share_konten_tasks'] as $task) {
                            if (!empty($task['link'])) {
                                $promotionEditor[] = [
                                    'label' => strtoupper(($task['platform'] ?? 'SHARE') . ' LINK'),
                                    'link' => $task['link'],
                                    'completed_at' => $pw->updated_at,
                                    'completed_by' => $pw->createdBy?->name ?? null,
                                ];
                            }
                        }
                    }
                    // Assets / File Paths (Foto Talent, etc)
                    if (is_array($pw->file_paths)) {
                        foreach ($pw->file_paths as $fp) {
                            if (!empty($fp)) {
                                $promotionEditor[] = [
                                    'label' => 'PROMOTION ASSET (FOTO)',
                                    'link' => asset('storage/' . $fp),
                                    'completed_at' => $pw->updated_at,
                                    'completed_by' => $pw->createdBy?->name ?? null,
                                ];
                            }
                        }
                    }
                }

                // 3. Promotion (Step 5: Asset Production) Name & Date
                $promotionInfo = null;
                if ($ep->promotionWork) {
                    $promotionInfo = [
                        'completed_by' => $ep->promotionWork->createdBy?->name,
                        'completed_at' => $ep->promotionWork->updated_at,
                        'is_late' => \Carbon\Carbon::parse($ep->promotionWork->updated_at)->gt(\Carbon\Carbon::parse($ep->air_date))
                    ];
                }

                // 4. Promotion (Share) Info (Step 10: Sharing)
                $promotionShare = null;
                $step10 = $ep->workflowProgress->where('workflow_step', 10)->where('status', 'completed')->first();
                if ($step10) {
                    $promotionShare = [
                        'completed_by' => $step10->assignedUser?->name ?? ($ep->promotionWork?->createdBy?->name),
                        'completed_at' => $step10->completed_at ?? $ep->promotionWork?->updated_at,
                        'is_late' => \Carbon\Carbon::parse($step10->completed_at ?? $ep->promotionWork?->updated_at)->gt(\Carbon\Carbon::parse($ep->air_date))
                    ];
                } elseif ($ep->promotionWork && !empty($ep->promotionWork->sharing_proof['share_konten_tasks'])) {
                    // Fallback to promotionWork update if step 10 not explicit but sharing exists
                    $promotionShare = [
                        'completed_by' => $ep->promotionWork->createdBy?->name,
                        'completed_at' => $ep->promotionWork->updated_at,
                        'is_late' => \Carbon\Carbon::parse($ep->promotionWork->updated_at)->gt(\Carbon\Carbon::parse($ep->air_date))
                    ];
                }

                // 5. QC Info (Approver and Date only)
                $qcInfo = null;
                $step8 = $ep->workflowProgress->where('workflow_step', 8)->first();
                $step7 = $ep->workflowProgress->where('workflow_step', 7)->first();
                
                // Hierarchical fallback: 
                // 1. QC Work Reviewer (person who finished it)
                // 2. QC Work Creator
                // 3. Last person who checked a checklist item
                // 4. Assigned user in Step 8 (QC Final)
                // 5. Manager Distribusi QC Creator
                // 6. Assigned user in Step 7 (QC Manager Distribusi)
                $qcReviewer = $ep->qualityControlWork?->reviewedBy?->name ?? $ep->qualityControlWork?->createdBy?->name;
                
                // Extra fallback: Extract from checklist if available
                if (!$qcReviewer && $ep->qualityControlWork && !empty($ep->qualityControlWork->qc_checklist)) {
                    $checklist = $ep->qualityControlWork->qc_checklist;
                    if (is_array($checklist)) {
                        $lastItem = end($checklist);
                        if (is_array($lastItem)) {
                            $qcReviewer = $lastItem['checked_by'] ?? null;
                        }
                    }
                }

                $rawCompletedBy = $qcReviewer 
                             ?? $step8?->assignedUser?->name 
                             ?? $ep->managerDistribusiQcWork?->reviewedBy?->name
                             ?? $ep->managerDistribusiQcWork?->createdBy?->name 
                             ?? $step7?->assignedUser?->name;

                $completedBy = (!empty($rawCompletedBy) && $rawCompletedBy !== '-') ? $rawCompletedBy : null;

                if ($ep->qualityControlWork || $ep->managerDistribusiQcWork || $completedBy) {
                    $qcWork = $ep->qualityControlWork ?? $ep->managerDistribusiQcWork;
                    $qcDate = $qcWork?->qc_completed_at ?? $qcWork?->updated_at ?? ($step8?->completed_at ?? $step7?->completed_at);
                    $qcInfo = [
                        'completed_by' => $completedBy ?? 'QC Team',
                        'completed_at' => $qcDate,
                        'is_late' => $qcDate ? \Carbon\Carbon::parse($qcDate)->gt(\Carbon\Carbon::parse($ep->air_date)) : false
                    ];
                }

                $scriptFile = $ep->files->whereIn('category', ['script', 'naskah', 'rundown'])->first();
                $scriptUrl = $scriptFile ? $scriptFile->file_url : null;

                $creativeName = $ep->creativeWork?->createdBy?->name ?? null;
                if (!$creativeName || $creativeName === '-') {
                    $creativeCrew = $ep->crews->filter(function($c) {
                        $role = strtolower($c->role ?? '');
                        return str_contains($role, 'creative') || str_contains($role, 'kreatif') || str_contains($role, 'naskah') || str_contains($role, 'script');
                    })->first();
                    $creativeName = $creativeCrew?->user?->name ?? null;
                }
                if (!$creativeName || $creativeName === '-') {
                    $programCreative = $program?->crews->filter(function($c) {
                        $role = strtolower($c->role ?? '');
                        return str_contains($role, 'creative') || str_contains($role, 'kreatif') || str_contains($role, 'naskah') || str_contains($role, 'script');
                    })->first();
                    $creativeName = $programCreative?->user?->name ?? '-';
                }

                $submittedAt = $ep->creativeWork?->updated_at ?? null;

                // Website URL check in both website_url field and metadata
                $websiteUrl = $ep->broadcastingWork?->website_url;
                if (!$websiteUrl && $ep->broadcastingWork) {
                    $metadata = $ep->broadcastingWork->metadata;
                    if (is_string($metadata)) {
                        $metadata = json_decode($metadata, true);
                    }
                    if (is_array($metadata)) {
                        $websiteUrl = $metadata['jetstream_url'] ?? $metadata['jetstream_link'] ?? null;
                    }
                }

                $rows[] = [
                    'episode_id' => $ep->id,
                    'program_name' => $program?->name ?? 'N/A',
                    'air_date' => $ep->air_date,
                    'title' => $ep->title,
                    'creative_name' => $creativeName,
                    'creative_submitted_at' => $submittedAt,
                    'script' => [
                        'file_url' => $scriptUrl,
                        'submitted_at' => $submittedAt,
                        'submitted_by' => $creativeName,
                    ],
                    'budget' => (function() use ($ep) {
                        $creative = $ep->creativeWork;
                        $budgetTotal = 0;
                        if ($creative) {
                            if (isset($creative->budget_data['total'])) {
                                $budgetTotal = $creative->budget_data['total'];
                            } else {
                                $budgetTotal = $creative->total_budget;
                            }
                        }

                        $step4Approval = $ep->workflowProgress->where('workflow_step', 4)->where('status', 'completed')->first();
                        $approvedBy = null;
                        $approvedAt = null;

                        if ($step4Approval) {
                            $approvedBy = $step4Approval->assignedUser?->name;
                            $approvedAt = $step4Approval->completed_at;
                        }

                        if (!$approvedBy && $creative) {
                            $approvedBy = $creative->budgetApprovedBy?->name ?? $creative->reviewedBy?->name ?? null;
                            $approvedAt = $creative->budget_approved_at ?? $creative->reviewed_at ?? null;
                        }

                        return [
                            'total' => $budgetTotal,
                            'approved_by' => $approvedBy,
                            'approved_at' => $approvedAt,
                            'producer_approval' => $creative ? [
                                'name' => $creative->budgetApprovedBy?->name,
                                'at' => $creative->budget_approved_at
                            ] : null,
                            'manager_approval' => $creative ? [
                                'name' => $creative->specialBudgetApprover?->name,
                                'at' => $creative->special_budget_approved_at
                            ] : null,
                        ];
                    })(),
                    'hosts' => (function() use ($ep) {
                        $talent = $ep->creativeWork?->talent_data;
                        if (!is_array($talent)) return [];
                        return $talent['host'] ?? $talent['hosts'] ?? [];
                    })(),
                    'guests' => (function() use ($ep) {
                        $talent = $ep->creativeWork?->talent_data;
                        if (!is_array($talent)) return [];
                        return $talent['guest'] ?? $talent['guests'] ?? [];
                    })(),
                    'audience_questions' => $ep->creativeWork?->script_content ?? null,
                    'shooting_date' => $ep->creativeWork?->shooting_schedule ?? null,
                    'graphic_design' => $graphicDesign,
                    'promotion' => $promotionInfo,
                    'promotion_share' => $promotionShare,
                    'promotion_editor' => $promotionEditor,
                    'qc' => $qcInfo,
                    'editor' => (function() use ($ep) {
                        $ew = $ep->editorWork;
                        if (!$ew) return null;
                        
                        $name = $ew->assignedUser?->name;
                        
                        // Fallback 1: Check step 6 workflow progress
                        if (!$name || $name === '-' || $name === 'Editor') {
                            $step6 = $ep->workflowProgress->where('workflow_step', 6)->first();
                            $name = $step6?->assignedUser?->name;
                        }

                        // Fallback 2: Check any editor crew in Episode
                        if (!$name || $name === '-' || $name === 'Editor') {
                            $editorCrew = $ep->crews->filter(function($c) {
                                $role = strtolower($c->role ?? '');
                                return str_contains($role, 'editor') && !str_contains($role, 'promosi') && !str_contains($role, 'promotion');
                            })->first();
                            $name = $editorCrew?->user?->name;
                        }

                        // Fallback 3: Check any editor crew in Program (Inheritance)
                        if (!$name || $name === '-' || $name === 'Editor') {
                            $programEditor = ($ep->program?->crews ?? collect())->filter(function($c) {
                                $role = strtolower($c->role ?? '');
                                return str_contains($role, 'editor') && !str_contains($role, 'promosi') && !str_contains($role, 'promotion');
                            })->first();
                            $name = $programEditor?->user?->name;
                        }

                        $completedAt = $ew->completed_at ?? $ew->updated_at;
                        return [
                            'name' => $name ?? 'Editor',
                            'completed_at' => $completedAt,
                            'is_late' => $completedAt ? \Carbon\Carbon::parse($completedAt)->gt(\Carbon\Carbon::parse($ep->air_date)->endOfDay()) : false
                        ];
                    })(),
                    'producer' => (function() use ($ep, $program, $step7) {
                        $name = $program?->producer?->name;
                        if (!$name || $name === '-') {
                            $programProducer = ($program?->crews ?? collect())->filter(function($c) {
                                return strtolower($c->role ?? '') === 'producer';
                            })->first();
                            $name = $programProducer?->user?->name ?? '-';
                        }
                        
                        $episodeProducer = $ep->crews->filter(function($c) {
                             return strtolower($c->role ?? '') === 'producer';
                        })->first();
                        $name = $episodeProducer?->user?->name ?? $name;

                        $qcDateRaw = $ep->managerDistribusiQcWork?->qc_completed_at ?? $ep->managerDistribusiQcWork?->updated_at ?? $step7?->completed_at;
                        return [
                            'name' => $name,
                            'completed_at' => $qcDateRaw,
                            'is_late' => $qcDateRaw ? \Carbon\Carbon::parse($qcDateRaw)->gt(\Carbon\Carbon::parse($ep->air_date)) : false
                        ];
                    })(),
                    'distribution_manager' => (function() use ($ep, $program, $step7) {
                        $name = $program?->managerDistribusi?->name;
                        if (!$name || $name === '-') {
                            $programDm = ($program?->crews ?? collect())->filter(function($c) {
                                return strtolower($c->role ?? '') === 'manager distribusi';
                            })->first();
                            $name = $programDm?->user?->name ?? '-';
                        }
                        
                        $episodeDm = $ep->crews->filter(function($c) {
                             return strtolower($c->role ?? '') === 'manager distribusi';
                        })->first();
                        $name = $episodeDm?->user?->name ?? $name;

                        $qcDateRaw = $ep->managerDistribusiQcWork?->qc_completed_at ?? $ep->managerDistribusiQcWork?->updated_at ?? $step7?->completed_at;
                        return [
                            'name' => $name,
                            'completed_at' => $qcDateRaw,
                            'is_late' => $qcDateRaw ? \Carbon\Carbon::parse($qcDateRaw)->gt(\Carbon\Carbon::parse($ep->air_date)) : false
                        ];
                    })(),
                    'youtube' => (function() use ($ep) {
                        $bw = $ep->broadcastingWork;
                        if (!$bw || !$bw->youtube_url) return null;
                        $completedAt = $bw->published_at ?? $bw->updated_at;
                        return [
                            'url' => $bw->youtube_url,
                            'completed_by' => $bw->createdBy?->name ?? 'Broadcasting',
                            'completed_at' => $completedAt,
                            'is_late' => $completedAt ? \Carbon\Carbon::parse($completedAt)->gt(\Carbon\Carbon::parse($ep->air_date)->endOfDay()) : false
                        ];
                    })(),
                    'website' => (function() use ($ep, $websiteUrl) {
                        $bw = $ep->broadcastingWork;
                        if (!$websiteUrl) return null;
                        $completedAt = $bw?->published_at ?? $bw?->updated_at;
                        return [
                            'url' => $websiteUrl,
                            'completed_by' => $bw?->createdBy?->name ?? 'Broadcasting',
                            'completed_at' => $completedAt,
                            'is_late' => $completedAt ? \Carbon\Carbon::parse($completedAt)->gt(\Carbon\Carbon::parse($ep->air_date)->endOfDay()) : false
                        ];
                    })(),
                    'setting_crews' => (function() use ($ep) {
                        return $ep->crews->filter(function($c) {
                            $role = strtolower($c->role ?? '');
                            return str_contains($role, 'setting');
                        })->map(function($c) {
                            return [
                                'name' => $c->user?->name ?? 'N/A',
                                'role' => $c->role,
                                'is_coordinator' => (bool)$c->is_coordinator
                            ];
                        })->sortByDesc('is_coordinator')->values();
                    })(),
                    'syuting_crews' => (function() use ($ep) {
                        return $ep->crews->filter(function($c) {
                            $role = strtolower($c->role ?? '');
                            return str_contains($role, 'syuting') || str_contains($role, 'shooting');
                        })->map(function($c) {
                            return [
                                'name' => $c->user?->name ?? 'N/A',
                                'role' => $c->role,
                                'is_coordinator' => (bool)$c->is_coordinator
                            ];
                        })->sortByDesc('is_coordinator')->values();
                    })(),
                    'workflow_status' => $ep->status,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $rows,
                'meta' => [
                    'current_page' => $episodes->currentPage(),
                    'last_page' => $episodes->lastPage(),
                    'total' => $episodes->total(),
                    'per_page' => $episodes->perPage(),
                ]
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
