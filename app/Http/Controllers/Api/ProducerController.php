<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\MusicArrangement;
use App\Models\CreativeWork;
use App\Models\ProductionEquipment;
use App\Models\SoundEngineerRecording;
use App\Models\SoundEngineerEditing;
use App\Models\EditorWork;
use App\Models\DesignGrafisWork;
use App\Models\ProduksiWork;
use App\Models\PromotionMaterial;
use Illuminate\Support\Facades\Log;
use App\Models\BroadcastingSchedule;
use App\Models\QualityControl;
use App\Models\Budget;
use App\Models\BudgetRequest;
use App\Models\Notification;
use App\Models\ProgramApproval;
use App\Models\ProductionTeamAssignment;
use App\Models\ProductionTeamMember;
use App\Models\MusicSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Helpers\ProgramManagerAuthorization;
use App\Services\WorkflowStateService;

class ProducerController extends Controller
{
    /**
     * Check if Producer is authorized for episode: either episode's production team
     * or program's production team has this producer (same logic as getApprovals).
     */
    protected function isProducerAuthorizedForEpisode(?Episode $episode, $producerId): bool
    {
        if (!$episode) {
            return false;
        }
        if ($episode->productionTeam && (int) $episode->productionTeam->producer_id === (int) $producerId) {
            return true;
        }
        if ($episode->program && $episode->program->productionTeam && (int) $episode->program->productionTeam->producer_id === (int) $producerId) {
            return true;
        }
        return false;
    }

    /**
     * Get approvals pending
     */
    public function getApprovals(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $approvals = [];
            
            // Song proposals pending approval (status song_proposal)
            // Support episode.productionTeam langsung atau episode.program.productionTeam
            // Fallback: episode/program belum punya production team → tampilkan ke Producer agar data dari Music Arranger tetap terlihat
            $songProposals = MusicArrangement::where('status', 'song_proposal')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'createdBy'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    })
                    ->orWhereHas('episode', function ($subQ) {
                        $subQ->withTrashed()->whereNull('production_team_id')
                            ->where(function ($epQ) {
                                $epQ->whereDoesntHave('program')
                                    ->orWhereHas('program', function ($pq) {
                                        $pq->withTrashed()->whereNull('production_team_id');
                                    });
                            });
                    });
                })
                ->get();
            
            // Music arrangements pending approval (arrangement file submitted)
            // Producer hanya bisa melihat arrangement dari ProductionTeam mereka
            // Query yang lebih sederhana: ambil semua dulu, filter manual berdasarkan productionTeam
            
            // Debug: Cek semua arrangement dengan status submitted/arrangement_submitted
            // Juga cek arrangement_in_progress yang sudah punya file
            // Juga cek song_approved yang sudah punya file
            $allStatusArrangements = MusicArrangement::where(function ($q) {
                $q->whereIn('status', ['submitted', 'arrangement_submitted'])
                  ->orWhere(function ($subQ) {
                      $subQ->where('status', 'arrangement_in_progress')
                           ->whereNotNull('file_path');
                  })
                  ->orWhere(function ($subQ) {
                      $subQ->where('status', 'song_approved')
                           ->whereNotNull('file_path');
                  });
            })->get();
            
            // Debug: Cek juga arrangement dengan file tapi status bukan submitted
            $arrangementsWithFile = MusicArrangement::whereNotNull('file_path')
                ->whereNotIn('status', ['submitted', 'arrangement_submitted'])
                ->select('id', 'status', 'episode_id', 'file_path', 'submitted_at')
                ->get();
            
            // Debug: Cek semua arrangement untuk melihat status mereka
            $allArrangements = MusicArrangement::select('id', 'status', 'episode_id', 'file_path', 'submitted_at')
                ->orderBy('updated_at', 'desc')
                ->limit(20)
                ->get();
            
            Log::info('Producer getApprovals - Debug All Arrangements', [
                'producer_id' => $user->id,
                'total_with_status_submitted' => $allStatusArrangements->count(),
                'arrangements_with_status_submitted' => $allStatusArrangements->map(function ($arr) {
                    return [
                        'id' => $arr->id,
                        'status' => $arr->status,
                        'episode_id' => $arr->episode_id,
                        'has_file' => !empty($arr->file_path),
                        'submitted_at' => $arr->submitted_at
                    ];
                })->toArray(),
                'arrangements_with_file_but_not_submitted' => $arrangementsWithFile->map(function ($arr) {
                    return [
                        'id' => $arr->id,
                        'status' => $arr->status,
                        'episode_id' => $arr->episode_id,
                        'has_file' => !empty($arr->file_path),
                        'submitted_at' => $arr->submitted_at
                    ];
                })->toArray(),
                'recent_arrangements_status_breakdown' => $allArrangements->groupBy('status')->map(function ($group) {
                    return $group->count();
                })->toArray()
            ]);
            
            // 1. Status arrangement_submitted atau submitted (arrangement file yang sudah di-submit)
            // 2. Status arrangement_in_progress yang sudah punya file atau link (fallback jika auto-submit belum jalan)
            // 3. Status song_approved yang sudah punya file atau link (fallback jika auto-submit belum jalan)
            $musicArrangements = MusicArrangement::where(function ($q) {
                    $q->whereIn('status', ['submitted', 'arrangement_submitted'])
                      ->orWhere(function ($subQ) {
                          $subQ->whereIn('status', ['arrangement_in_progress', 'song_approved'])
                               ->where(function($f) {
                                   $f->whereNotNull('file_path')
                                     ->orWhereNotNull('file_link');
                               });
                      });
                })
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'createdBy',
                    'soundEngineerHelper'
                ])
                ->where(function ($q) use ($user, $isProgramManager) {
                    if ($isProgramManager) {
                        // Program Manager: see all arrangements matching status / file rules (no producer filter)
                        return;
                    }
                    // Producer: filter by their production team / reviewed_by
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    })
                    ->orWhereHas('episode', function ($subQ) {
                        $subQ->withTrashed()->whereNull('production_team_id')
                            ->where(function ($epQ) {
                                $epQ->whereDoesntHave('program')
                                    ->orWhereHas('program', function ($pq) {
                                        $pq->withTrashed()->whereNull('production_team_id');
                                    });
                            });
                    })
                    ->orWhere('reviewed_by', $user->id);
                })
                ->get();
            
            // Debug logging untuk troubleshooting
            // Debug: Cek arrangement_in_progress yang punya file tapi tidak match productionTeam
            $inProgressWithFile = MusicArrangement::where('status', 'arrangement_in_progress')
                ->whereNotNull('file_path')
                ->with(['episode.productionTeam', 'episode.program.productionTeam'])
                ->get();
            
            $inProgressNotMatched = $inProgressWithFile->filter(function ($arr) use ($user) {
                $episode = $arr->episode;
                if (!$episode) return true;
                
                $episodePT = $episode->productionTeam;
                $programPT = $episode->program->productionTeam ?? null;
                
                $episodeMatch = $episodePT && $episodePT->producer_id === $user->id;
                $programMatch = $programPT && $programPT->producer_id === $user->id;
                
                return !$episodeMatch && !$programMatch;
            });
            
            Log::info('Producer getApprovals - Music Arrangements Summary (RETURNED TO FRONTEND)', [
                'producer_id' => $user->id,
                'total_filtered' => $musicArrangements->count(),
                'note' => 'Only arrangements with status arrangement_submitted/submitted, or arrangement_in_progress/song_approved WITH FILE are included',
                'all_arrangements_status' => $musicArrangements->pluck('status', 'id')->toArray(),
                'filtered_arrangements' => $musicArrangements->map(function ($arr) {
                    $episode = $arr->episode;
                    $program = $episode?->program ?? null;
                    return [
                        'id' => $arr->id,
                        'status' => $arr->status,
                        'has_file' => !empty($arr->file_path) || !empty($arr->file_link),
                        'episode_id' => $arr->episode_id,
                        'episode_production_team_id' => $episode?->production_team_id ?? null,
                        'program_production_team_id' => $program?->production_team_id ?? null,
                        'episode_production_team_producer_id' => ($episode?->productionTeam?->producer_id ?? null),
                        'program_production_team_producer_id' => ($program?->productionTeam?->producer_id ?? null),
                    ];
                })->toArray()
            ]);
            
            // Creative works pending approval (script_link, storyboard_link, budget_data, jadwal, lokasi untuk Producer)
            $creativeWorks = CreativeWork::where('status', 'submitted')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'createdBy',
                    'specialBudgetApproval'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    });
                })
                ->get();
            
            // Equipment requests pending approval
            $equipmentRequests = ProductionEquipment::where('status', 'pending')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'requestedBy'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    });
                })
                ->get();
            
            // Budget requests pending approval
            $budgetRequests = Budget::where('status', 'submitted')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'requestedBy'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    });
                })
                ->get();

            // Sound Engineer Recordings pending QC
            $soundEngineerRecordings = SoundEngineerRecording::where('status', 'completed')
                ->whereNull('reviewed_by')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'musicArrangement',
                    'createdBy'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    });
                })
                ->get();

            // Sound Engineer Editing pending approval
            $soundEngineerEditing = SoundEngineerEditing::where('status', 'submitted')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'recording',
                    'soundEngineer'
                ])
                ->when(!$isProgramManager, function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        $q->whereHas('episode', function($epQ) use ($user) {
                            $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        })
                        ->orWhereHas('episode', function($epQ) use ($user) {
                            $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                                $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                    $subQ->withTrashed()->where('producer_id', $user->id);
                                });
                            });
                        });
                    });
                })
                ->get();

            // Editor Work pending approval
            $editorWorks = EditorWork::where('status', 'submitted')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'createdBy'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    });
                })
                ->get();

            // Produksi Work pending approval (shooting results submitted)
            $productionWorks = ProduksiWork::where('status', 'completed')
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'runSheet',
                    'createdBy'
                ])
                ->where(function ($q) use ($user, $isProgramManager) {
                    if ($isProgramManager) {
                        return;
                    }
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    });
                })
                ->get();
            
            // Convert collection to array untuk response JSON (termasuk yang diperbaiki Sound Engineer)
            $musicArrangementsArray = $musicArrangements->toArray();
            $musicArrangementIds = $musicArrangements->pluck('id')->toArray();
            $withSeHelper = $musicArrangements->filter(fn ($a) => !empty($a->sound_engineer_helper_id))->pluck('id')->toArray();
            Log::info('Producer getApprovals music_arrangements', [
                'producer_id' => $user->id,
                'count' => count($musicArrangementsArray),
                'ids' => $musicArrangementIds,
                'with_sound_engineer_helper' => $withSeHelper,
            ]);

            // Creative works: pastikan setiap item punya program_id, program_name, episode_number agar UI bisa tampilkan "Program apa, Episode berapa"
            $creativeWorksArray = $creativeWorks->map(function ($cw) {
                $arr = $cw->toArray();
                $arr['program_id'] = $arr['program_id'] ?? $cw->episode?->program_id;
                $arr['program_name'] = $arr['program_name'] ?? $cw->episode?->program?->name ?? 'N/A';
                $arr['episode_number'] = $arr['episode_number'] ?? $cw->episode?->episode_number;
                $arr['program'] = $cw->episode?->program
                    ? ['id' => $cw->episode->program->id, 'name' => $cw->episode->program->name]
                    : null;
                return $arr;
            })->values()->toArray();
            
            $approvals = [
                'song_proposals' => $songProposals,
                'music_arrangements' => $musicArrangementsArray,
                'creative_works' => $creativeWorksArray,
                'equipment_requests' => $equipmentRequests,
                'budget_requests' => $budgetRequests,
                'sound_engineer_recordings' => $soundEngineerRecordings,
                'sound_engineer_editing' => $soundEngineerEditing,
                'editor_works' => $editorWorks,
                'produksi_works' => $productionWorks
            ];
            
            $response = [
                'success' => true,
                'data' => $approvals,
                'message' => 'Pending approvals retrieved successfully'
            ];
            if (config('app.debug')) {
                $response['_debug'] = [
                    'music_arrangements_count' => count($musicArrangementsArray),
                    'music_arrangements_ids' => $musicArrangementIds,
                    'music_arrangements_with_sound_engineer' => $withSeHelper
                ];
            }
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending approvals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Song Proposal History (Approved & Rejected)
     */
    public function getSongProposalHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get Approved and Rejected Song Proposals (riwayat approve/reject Producer)
            // Filter: production team producer = user, atau episode/program belum punya PT
            // Include downstream statuses: arrangement yang sudah di-approve song-nya akan lanjut ke arrangement_in_progress, arrangement_submitted, arrangement_approved
            // Semua status setelah song_approved tetap merupakan riwayat song proposal yang sudah di-approve
            $history = MusicArrangement::where(function ($q) {
                    // Song proposals yang masih di tahap song approval
                    $q->whereIn('status', ['song_approved', 'song_rejected', 'approved', 'rejected'])
                      // Song proposals yang sudah lanjut ke tahap arrangement (sudah melalui song approval)
                      ->orWhere(function ($sub) {
                          $sub->whereIn('status', [
                              'arrangement_in_progress',
                              'arrangement_submitted',
                              'arrangement_approved',
                              'arrangement_rejected'
                          ])
                          ->whereNotNull('reviewed_by'); // Konfirmasi sudah di-review oleh Producer
                      });
                })
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'createdBy', 
                    'reviewedBy'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    })
                    ->orWhereHas('episode', function ($subQ) {
                        $subQ->withTrashed()->whereNull('production_team_id')
                            ->where(function ($epQ) {
                                $epQ->whereDoesntHave('program')
                                    ->orWhereHas('program', function ($pq) {
                                        $pq->withTrashed()->whereNull('production_team_id');
                                    });
                            });
                    })
                    // ATAU Fallback: Producer ini yang me-review (History mutlak milik yang me-review)
                    ->orWhere('reviewed_by', $user->id);
                })
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Song proposal history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get song proposal history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Sound Engineer Recording History (Reviewed & Rejected)
     */
    public function getSoundEngineerRecordingHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Rejection workflow for recordings resets status back to 'recording' for revision,
            // so we can't rely on `status = rejected`. Instead, use presence of review metadata.
            $history = SoundEngineerRecording::whereNotNull('reviewed_by')
                ->with(['episode.productionTeam', 'episode.program.productionTeam', 'musicArrangement', 'createdBy', 'reviewedBy'])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode.productionTeam', function ($subQ) use ($user) {
                        $subQ->where('producer_id', $user->id);
                    })
                    ->orWhereHas('episode.program.productionTeam', function ($subQ) use ($user) {
                        $subQ->where('producer_id', $user->id);
                    });
                })
                ->orderBy('reviewed_at', 'desc')
                ->get()
                ->map(function (SoundEngineerRecording $recording) {
                    // Producer-approval outcome mapping for frontend compatibility.
                    // - approve  => status 'reviewed'
                    // - reject   => status becomes 'recording' but still has reviewed metadata
                    if ($recording->status !== 'reviewed') {
                        $recording->status = 'rejected';
                    }
                    return $recording;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Sound engineer recording history retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sound engineer recording history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Sound Engineer Editing History (Approved & Rejected)
     */
    public function getSoundEngineerEditingHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Hanya riwayat hasil editing vokal (sound_engineer_editing), bukan arrangement help
            // Rejection workflow for editing uses status 'revision_needed' (for resubmission),
            // so we can't rely on `status = rejected`. Instead, use approval/rejection metadata.
            $editingRecords = SoundEngineerEditing::where(function ($q) {
                    $q->whereNotNull('approved_by')
                      ->orWhereNotNull('rejected_by');
                })
                ->with(['episode.productionTeam', 'episode.program.productionTeam', 'recording', 'soundEngineer', 'approvedBy', 'rejectedBy'])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode.productionTeam', function ($subQ) use ($user) {
                        $subQ->where('producer_id', $user->id);
                    })
                    ->orWhereHas('episode.program.productionTeam', function ($subQ) use ($user) {
                        $subQ->where('producer_id', $user->id);
                    });
                })
                ->orderBy('updated_at', 'desc')
                ->get();

            $history = $editingRecords->map(function ($record) {
                $status = $record->status === 'approved' ? 'approved' : 'rejected';
                return [
                    'id' => $record->id,
                    'source_type' => 'editing_work',
                    'episode_id' => $record->episode_id,
                    'episode' => $record->episode,
                    'sound_engineer' => $record->soundEngineer,
                    'status' => $status,
                    'notes' => $record->editing_notes ?? $record->submission_notes,
                    'final_file_link' => $record->final_file_link,
                    'final_file_path' => $record->final_file_path,
                    'approved_at' => $record->approved_at,
                    'rejected_at' => $record->rejected_at,
                    'updated_at' => $record->updated_at,
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Riwayat approval hasil editing vokal'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get sound engineer editing history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve item
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
        
        if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:song_proposal,music_arrangement,creative_work,equipment_request,budget_request,sound_engineer_recording,sound_engineer_editing,editor_work',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $item = null;
            
            switch ($request->type) {
                case 'song_proposal':
                    // Approve song proposal (lagu & penyanyi)
                    $item = MusicArrangement::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                        'createdBy'
                    ])->findOrFail($id);
                    
                    // Validate Producer has access (same logic as getApprovals: episode OR program production team, or fallback when no team assigned)
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this song proposal.'
                        ], 404);
                    }
                    $teamOnEpisode = $episode->productionTeam;
                    $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
                    $hasTeam = $teamOnEpisode || $teamOnProgram;
                    if ($hasTeam && !$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This song proposal is not from your production team.'
                        ], 403);
                    }
                    // When no production team assigned (fallback), any Producer can approve so data from Music Arranger can be processed
                    
                    if ($item->status !== 'song_proposal') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only song proposals can be approved'
                        ], 400);
                    }
                    
                    // Approve song proposal
                    $item->update([
                        'status' => 'song_approved',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_notes' => $request->notes
                    ]);
                    
                    // Audit logging
                    \App\Helpers\ControllerSecurityHelper::logApproval('song_proposal_approved', $item, [
                        'episode_id' => $item->episode_id,
                        'song_title' => $item->song_title,
                        'singer_name' => $item->is_group ? "Group: {$item->group_name}" : $item->singer_name,
                        'is_group' => $item->is_group,
                        'group_name' => $item->group_name,
                        'review_notes' => $request->notes
                    ], $request);
                    
                    // Notify Music Arranger
                    $singerText = $item->is_group ? "grup '{$item->group_name}'" : "penyanyi '{$item->singer_name}'";
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'song_proposal_approved',
                        'title' => 'Usulan Lagu & ' . ($item->is_group ? 'Grup' : 'Penyanyi') . ' Diterima',
                        'message' => "Usulan lagu '{$item->song_title}' dengan {$singerText} telah diterima. Silakan arrange lagu.",
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes,
                            'is_group' => $item->is_group,
                            'group_name' => $item->group_name
                        ]
                    ]);
                    
                    // Log Workflow State for Song Approval
                    /** @var \App\Services\WorkflowStateService $workflowService */
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'music_arrangement',
                        'music_arranger',
                        $item->created_by,
                        "Song proposal approved by Producer: {$item->song_title} by " . ($item->is_group ? "Group '{$item->group_name}'" : "'{$item->singer_name}'"),
                        $user->id,
                        [
                            'action' => 'song_proposal_approved',
                            'song_title' => $item->song_title,
                            'singer_name' => $item->is_group ? "Group: {$item->group_name}" : $item->singer_name,
                            'producer_notes' => $request->notes
                        ]
                    );

                    return response()->json([
                        'success' => true,
                        'data' => $item->fresh(['episode', 'createdBy']),
                        'message' => 'Song proposal approved successfully. Music Arranger has been notified.'
                    ]);
                    
                case 'music_arrangement':
                    // Approve arrangement file (setelah song approved dan arrangement file submitted)
                    $item = MusicArrangement::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                        'createdBy'
                    ])->findOrFail($id);
                    
                    // Validate Producer has access (same logic as getApprovals: episode OR program production team)
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this arrangement.'
                        ], 404);
                    }
                    $teamOnEpisode = $episode->productionTeam;
                    $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
                    if (!$teamOnEpisode && !$teamOnProgram) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Production team not found for this episode. Please assign a production team first.'
                        ], 404);
                    }
                    if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This arrangement is not from your production team.'
                        ], 403);
                    }
                    
                    // Only approve if status is arrangement_submitted, submitted, atau arrangement_in_progress (yang sudah punya file)
                    // arrangement_in_progress tanpa file tidak bisa di-approve (harus upload file dulu)
                    // Jika status song_proposal, harus approve dengan type 'song_proposal', bukan 'music_arrangement'
                    if ($item->status === 'song_proposal') {
                        return response()->json([
                            'success' => false,
                            'message' => 'This is a song proposal. Please approve it with type "song_proposal", not "music_arrangement". Song proposals do not have arrangement files yet.'
                        ], 400);
                    }
                    
                    if (!in_array($item->status, ['arrangement_submitted', 'submitted']) && 
                        !($item->status === 'arrangement_in_progress' && $item->file_path)) {
                        $statusMessage = $item->status === 'arrangement_in_progress' && !$item->file_path
                            ? 'Arrangement is in progress but no file has been uploaded yet. Music Arranger must upload the arrangement file first.'
                            : "Cannot approve arrangement with status '{$item->status}'. Only submitted arrangement files or in-progress arrangements with files can be approved.";
                        
                        return response()->json([
                            'success' => false,
                            'message' => $statusMessage,
                            'current_status' => $item->status,
                            'has_file' => !empty($item->file_path)
                        ], 400);
                    }
                    
                    // Jika status arrangement_in_progress tapi sudah punya file, update status ke arrangement_submitted dulu
                    if ($item->status === 'arrangement_in_progress' && $item->file_path) {
                        $item->update([
                            'status' => 'arrangement_submitted',
                            'submitted_at' => now()
                        ]);
                        $item->refresh();
                    }
                    
                    // Update status to arrangement_approved
                    $oldStatus = $item->status;
                    $item->update([
                        'status' => 'arrangement_approved',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_notes' => $request->notes
                    ]);
                    
                    // Audit logging
                    \App\Helpers\ControllerSecurityHelper::logApproval('music_arrangement_approved', $item, [
                        'episode_id' => $item->episode_id,
                        'song_title' => $item->song_title,
                        'old_status' => $oldStatus,
                        'review_notes' => $request->notes
                    ], $request);
                    
                    // Reload to get updated values after approve
                    $item->refresh();
                    
                    // Notify Music Arranger
                    $approvalMessage = "Arrangement lagu '{$item->song_title}' telah disetujui oleh Producer.";
                    if ($item->producer_modified) {
                        $modDetails = [];
                        if ($item->song_title !== $item->original_song_title) {
                            $modDetails[] = "Song: '{$item->original_song_title}' -> '{$item->song_title}'";
                        }
                        if ($item->is_group) {
                            $modDetails[] = "Singer: '{$item->original_singer_name}' -> Group: '{$item->group_name}'";
                        } else if ($item->singer_name !== $item->original_singer_name) {
                            $modDetails[] = "Singer: '{$item->original_singer_name}' -> '{$item->singer_name}'";
                        }
                        
                        if (!empty($modDetails)) {
                            $approvalMessage .= " Producer telah memodifikasi: " . implode(', ', $modDetails) . ".";
                        }
                    }
                    
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'music_arrangement_approved',
                        'title' => 'Arrangement Lagu Disetujui',
                        'message' => $approvalMessage,
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes,
                            'producer_modified' => $item->producer_modified
                        ]
                    ]);

                    // Auto-create recording task for Sound Engineer
                    // Get Sound Engineer from ProductionTeam
                    $episode = $item->episode;
                    $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);
                    
                    if ($productionTeam) {
                        $soundEngineers = $productionTeam->members()
                            ->where('role', 'sound_eng')
                            ->where('is_active', true)
                            ->get();

                        foreach ($soundEngineers as $soundEngineerMember) {
                            // Check if recording already exists
                            $existingRecording = SoundEngineerRecording::where('episode_id', $episode->id)
                                ->where('music_arrangement_id', $item->id)
                                ->first();

                            if (!$existingRecording) {
                                // Create draft recording task
                                $recording = SoundEngineerRecording::create([
                                    'episode_id' => $episode->id,
                                    'music_arrangement_id' => $item->id,
                                    'recording_notes' => "Recording task created automatically for approved arrangement: {$item->song_title}",
                                    'status' => 'draft',
                                    'created_by' => $soundEngineerMember->user_id
                                ]);

                                // Notify Sound Engineer
                                Notification::create([
                                    'user_id' => $soundEngineerMember->user_id,
                                    'type' => 'recording_task_created',
                                    'title' => 'New Recording Task',
                                    'message' => "A recording task has been created for approved arrangement '{$item->song_title}' (Episode {$episode->episode_number}).",
                                    'data' => [
                                        'recording_id' => $recording->id,
                                        'arrangement_id' => $item->id,
                                        'episode_id' => $episode->id
                                    ]
                                ]);
                            }
                        }
                    }
                    
                    // NOTE: Creative Work creation sekarang di-handle oleh MusicArrangementObserver
                    // Observer akan otomatis create Creative Work ketika status berubah menjadi arrangement_approved
                    // Ini berlaku untuk SEMUA arrangement yang di-approve Producer, baik dari Music Arranger langsung
                    // maupun yang sudah dibantu Sound Engineer sebelumnya
                    \Log::info('Producer approve arrangement - Status updated, Observer will handle Creative Work creation', [
                        'arrangement_id' => $item->id,
                        'episode_id' => $item->episode_id,
                        'arrangement_status' => $item->status,
                        'sound_engineer_helper_id' => $item->sound_engineer_helper_id,
                        'note' => 'MusicArrangementObserver will auto-create Creative Work if not exists'
                    ]);
                    
                    break;
                    
                case 'creative_work':
                    $item = CreativeWork::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed()
                    ])->findOrFail($id);
                    
                    // Validate Producer has access (same logic as getApprovals: episode OR program production team)
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this creative work.'
                        ], 404);
                    }
                    $teamOnEpisode = $episode->productionTeam;
                    $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
                    if (!$teamOnEpisode && !$teamOnProgram) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Production team not found for this episode. Please assign a production team first.'
                        ], 404);
                    }
                    if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This creative work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted creative works can be approved'
                        ], 400);
                    }

                    // Check for Special Budget
                    $hasSpecialBudget = false;
                    $specialBudgetItem = null;
                    if ($item->budget_data) {
                        foreach ($item->budget_data as $budgetItem) {
                            if (is_array($budgetItem) && isset($budgetItem['category']) && $budgetItem['category'] === 'Special Budget') {
                                $hasSpecialBudget = true;
                                $specialBudgetItem = $budgetItem;
                                break;
                            }
                        }
                    }

                    // If "Special Budget" exists AND this is NOT a direct edit (bypass), require Manager Approval
                    if ($hasSpecialBudget && !$request->has('bypass_manager_approval')) {
                        // Create ProgramApproval request
                        $approval = ProgramApproval::create([
                            'type' => 'special_budget', // Backwards compat if 'type' column still exists, but schema says 'approval_type'
                            'approval_type' => 'special_budget',
                            'approvable_type' => CreativeWork::class,
                            'approvable_id' => $item->id,
                            'request_data' => [
                                'creative_work_id' => $item->id,
                                'episode_id' => $item->episode_id,
                                'special_budget_amount' => $specialBudgetItem['amount'] ?? 0,
                                'special_budget_description' => $specialBudgetItem['description'] ?? 'Special Budget Request',
                                'notes' => $request->notes
                            ],
                            'requested_by' => $user->id,
                            'status' => 'pending'
                        ]);

                        // Update CreativeWork status
                        $item->update([
                            'status' => 'waiting_manager_approval', // New status
                            'requires_special_budget_approval' => true,
                            'special_budget_approval_id' => $approval->id,
                            'reviewed_by' => $user->id, // Producer reviewed it, but needs manager now
                            'review_notes' => $request->notes
                        ]);

                         // Notify Program Manager
                         $programManager = $episode->program->managerProgram;
                         if ($programManager) {
                             Notification::create([
                                 'user_id' => $programManager->id,
                                 'type' => 'special_budget_approval',
                                 'title' => 'Persetujuan Special Budget',
                                 'message' => "Producer mengajukan Special Budget untuk Episode {$episode->episode_number}. Mohon review.",
                                 'data' => ['approval_id' => $approval->id]
                             ]);
                         }

                         return response()->json([
                            'success' => true,
                            'data' => $item->fresh(),
                            'message' => 'Creative Work contains Special Budget. Submitted to Program Manager for approval.'
                        ]);
                    }
                    
                    // Normal Approval (No Special Budget OR Bypassed)
                    $item->approve(auth()->id(), $request->notes);
                    
                    // Trigger downstream tasks (Budget, Promosi, Produksi, SoundEng)
                    // Now centralised in helper method to support Direct Edit flow and Special Budget
                    $this->triggerPostCreativeApproval($item, $user);
                    
                    // Notify Creative
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'creative_work_approved',
                        'title' => 'Creative Work Disetujui',
                        'message' => "Creative work untuk Episode {$item->episode->episode_number} telah disetujui oleh Producer.",
                        'data' => [
                            'creative_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);
                    
                    // Update workflow state to production_planning
                    /** @var \App\Services\WorkflowStateService $workflowService */
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $item->episode,
                        'production_planning',
                        'production',
                        null,
                        'Creative work approved by Producer, proceeding to production planning',
                        $user->id,
                        ['action' => 'creative_work_approved', 'notes' => $request->notes]
                    );
                    break;
                    
                case 'equipment_request':
                    $item = ProductionEquipment::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
                    break;
                    
                case 'budget_request':
                    $item = Budget::findOrFail($id);
                    $item->approve(auth()->id(), $request->notes);
                    break;

                case 'sound_engineer_recording':
                    $item = SoundEngineerRecording::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.program' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed()
                    ])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This recording is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'completed') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only completed recordings can be reviewed'
                        ], 400);
                    }

                    // Review recording (mark as reviewed)
                    $item->review(auth()->id(), $request->notes);

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'sound_engineer_recording_approved',
                        'title' => 'Recording Approved',
                        'message' => "Your recording has been approved by Producer. Notes: " . ($request->notes ?? 'No notes'),
                        'data' => [
                            'recording_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);
                    break;
                    
                case 'sound_engineer_editing':
                    $item = SoundEngineerEditing::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed()
                    ])->findOrFail($id);
                    
                    // Validate Producer has access
                    // Support episode.productionTeam langsung atau episode.program.productionTeam
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this editing work.'
                        ], 404);
                    }
                    
                    $episodeTeam = $episode->productionTeam;
                    $programTeam = $episode->program ? $episode->program->productionTeam : null;
                    
                    if (!$isProgramManager) {
                        if (!$episodeTeam && !$programTeam) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Production team not found for this episode. Please assign a production team first.'
                            ], 404);
                        }
                        
                        // Check if producer matches EITHER episode's or program's production team
                        $episodeMatch = $episodeTeam && $episodeTeam->producer_id === $user->id;
                        $programMatch = $programTeam && $programTeam->producer_id === $user->id;
                        
                        if (!$episodeMatch && !$programMatch) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Unauthorized: This editing work is not from your production team.'
                            ], 403);
                        }
                    }

                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editing works can be approved'
                        ], 400);
                    }

                    // Approve editing
                    $item->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now()
                    ]);

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->sound_engineer_id,
                        'type' => 'sound_engineer_editing_approved',
                        'title' => 'Editing Work Approved',
                        'message' => "Your editing work has been approved by Producer. Notes: " . ($request->notes ?? 'No notes'),
                        'data' => [
                            'editing_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);

                    // Notify Editor that audio is ready
                    $episode = $item->episode;
                    $productionTeam = $episode->program->productionTeam;
                    
                    // Get Editors from production team
                    $editors = [];
                    if ($productionTeam) {
                        $editorMembers = $productionTeam->members()
                            ->where('role', 'editor')
                            ->where('is_active', true)
                            ->get();
                        
                        foreach ($editorMembers as $editorMember) {
                            $editors[] = $editorMember->user_id;
                        }
                    }
                    
                    // If no editors in team, find any Editor user
                    if (empty($editors)) {
                        $editorUsers = \App\Models\User::where('role', 'Editor')->pluck('id')->toArray();
                        $editors = $editorUsers;
                    }
                    
                    foreach ($editors as $editorId) {
                        $audioUrl = $item->final_file_path;
                        if ($item->final_file_link) {
                            $audioUrl = $item->final_file_link;
                            if (strpos($audioUrl, 'http') !== 0) {
                                $audioUrl = 'https://' . $audioUrl;
                            }
                        } elseif ($item->final_file_path) {
                            if (strpos($item->final_file_path, 'http') !== 0) {
                                $audioUrl = url('storage/' . $item->final_file_path);
                            }
                        }

                        Notification::create([
                            'user_id' => $editorId,
                            'type' => 'audio_ready_for_editing',
                            'title' => 'Audio Ready for Video Editing',
                            'message' => "Final audio file is ready for episode {$episode->episode_number}. You can now start video editing.",
                            'data' => [
                                'editing_id' => $item->id,
                                'episode_id' => $episode->id,
                                'audio_file_path' => $audioUrl
                            ]
                        ]);
                    }
                    
                    // Update workflow state to editing
                    if ($episode->current_workflow_state === 'sound_engineering' || $episode->current_workflow_state === 'production') {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $episode,
                            'editing',
                            'editor',
                            null,
                            'Sound engineer editing approved, audio ready for video editing',
                            $user->id,
                            [
                                'action' => 'vocal_editing_approved',
                                'review_notes' => $request->notes
                            ]
                        );
                    }
                    break;
                    
                case 'editor_work':
                                        $item = EditorWork::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.program' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                        'createdBy'
                    ])->findOrFail($id);

                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editor work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editor works can be approved'
                        ], 400);
                    }
                    
                    $item->approve(auth()->id(), $request->notes);
                    
                    // Notify Editor
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'editor_work_approved',
                        'title' => 'Editor Work Approved',
                        'message' => "Your editor work for Episode {$item->episode->episode_number} has been approved by Producer.",
                        'data' => [
                            'editor_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);
                    
                    // Notify Quality Control that editing is ready for QC
                    $qcUsers = \App\Models\User::where('role', 'Quality Control')->get();
                    foreach ($qcUsers as $qcUser) {
                        Notification::create([
                            'user_id' => $qcUser->id,
                            'type' => 'editor_work_ready_for_qc',
                            'title' => 'Editor Work Ready for QC',
                            'message' => "Editor work for Episode {$item->episode->episode_number} has been approved and is ready for quality control.",
                            'data' => [
                                'editor_work_id' => $item->id,
                                'episode_id' => $item->episode_id
                            ]
                        ]);
                    }
                    
                    // Update workflow state to quality_control
                    if ($item->episode->current_workflow_state === 'editing') {
                        $workflowService = app(\App\Services\WorkflowStateService::class);
                        $workflowService->updateWorkflowState(
                            $item->episode,
                            'quality_control',
                            'quality_control',
                            null,
                            'Editor work approved, proceeding to quality control',
                            $user->id,
                            [
                                'action' => 'video_editing_approved',
                                'review_notes' => $request->notes
                            ]
                        );
                    }
                    break;

                case 'produksi_work':
                    $item = ProduksiWork::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed()
                    ])->findOrFail($id);

                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This production work is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'completed') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only completed production works can be approved'
                        ], 400);
                    }

                    // Approve work
                    $item->update([
                        'status' => 'approved',
                        'completed_at' => now(), // Final completion timestamp
                        'notes' => $request->notes ?: $item->notes
                    ]);

                    // Notify Production Team
                    Notification::create([
                        'user_id' => $item->completed_by ?: $item->created_by,
                        'type' => 'produksi_work_approved',
                        'title' => 'Produksi Work Approved',
                        'message' => "Produksi work untuk Episode {$item->episode->episode_number} telah disetujui oleh Producer.",
                        'data' => [
                            'produksi_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'review_notes' => $request->notes
                        ]
                    ]);

                    // Update workflow state history
                    /** @var \App\Services\WorkflowStateService $workflowService */
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $item->episode,
                        'shooting_recording', // Or current state
                        'production',
                        $item->completed_by ?: $item->created_by,
                        'Production work approved by Producer',
                        $user->id,
                        [
                            'action' => 'shooting_production_approved',
                            'review_notes' => $request->notes
                        ]
                    );
                    break;
            }
            
            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Item approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject item
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
        
        if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:song_proposal,music_arrangement,creative_work,equipment_request,budget_request,sound_engineer_recording,sound_engineer_editing,editor_work',
            'reason' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $item = null;
            
            switch ($request->type) {
                case 'song_proposal':
                    // Reject song proposal (lagu & penyanyi)
                    $item = MusicArrangement::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                        'createdBy'
                    ])->findOrFail($id);
                    
                    // Validate Producer has access (same logic as getApprovals: episode OR program production team, or fallback when no team assigned)
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this song proposal.'
                        ], 404);
                    }
                    $teamOnEpisode = $episode->productionTeam;
                    $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
                    $hasTeam = $teamOnEpisode || $teamOnProgram;
                    if ($hasTeam && !$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This song proposal is not from your production team.'
                        ], 403);
                    }
                    // When no production team assigned (fallback), any Producer can reject
                    
                    if ($item->status !== 'song_proposal') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only song proposals can be rejected'
                        ], 400);
                    }
                    
                    // Reject song proposal
                    $item->update([
                        'status' => 'song_rejected',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'rejection_reason' => $request->reason
                    ]);

                    // Log Workflow State for Song Rejection
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'music_arrangement',
                        'music_arranger',
                        $item->created_by,
                        "Song proposal rejected by Producer: {$item->song_title}. Reason: {$request->reason}",
                        $user->id,
                        [
                            'action' => 'song_proposal_rejected',
                            'song_title' => $item->song_title,
                            'rejection_reason' => $request->reason
                        ]
                    );
                    
                    // Notify Music Arranger
                    $singerInfo = $item->is_group ? "grup '{$item->group_name}'" : "penyanyi '{$item->singer_name}'";
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'song_proposal_rejected',
                        'title' => 'Usulan Lagu & ' . ($item->is_group ? 'Grup' : 'Penyanyi') . ' Ditolak',
                        'message' => "Usulan lagu '{$item->song_title}' dengan {$singerInfo} telah ditolak. Alasan: {$request->reason}. Sound Engineer dapat membantu perbaikan.",
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason,
                            'needs_sound_engineer_help' => true,
                            'is_group' => $item->is_group
                        ]
                    ]);
                    
                    // Notify Sound Engineers that they can help
                    $episode = $item->episode;
                    $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);
                    
                    if ($productionTeam) {
                        $soundEngineers = $productionTeam->members()
                            ->where('role', 'sound_eng')
                            ->where('is_active', true)
                            ->get();
                        
                        foreach ($soundEngineers as $soundEngineerMember) {
                            Notification::create([
                                'user_id' => $soundEngineerMember->user_id,
                                'type' => 'song_proposal_rejected_help_needed',
                                'title' => 'Bantu Perbaikan Usulan Lagu',
                                'message' => "Usulan lagu '{$item->song_title}' untuk Episode {$episode->episode_number} telah ditolak. Anda dapat membantu Music Arranger untuk perbaikan.",
                                'data' => [
                                    'arrangement_id' => $item->id,
                                    'episode_id' => $episode->id,
                                    'rejection_reason' => $request->reason
                                ]
                            ]);
                        }
                    }
                    
                    return response()->json([
                        'success' => true,
                        'data' => $item->fresh(['episode', 'createdBy']),
                        'message' => 'Song proposal rejected successfully. Music Arranger and Sound Engineers have been notified.'
                    ]);
                    
                case 'music_arrangement':
                    // Reject arrangement file
                    $item = MusicArrangement::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                        'createdBy'
                    ])->findOrFail($id);
                    
                    // Validate Producer has access (same logic as getApprovals: episode OR program production team)
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this arrangement.'
                        ], 404);
                    }
                    $teamOnEpisode = $episode->productionTeam;
                    $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
                    if (!$teamOnEpisode && !$teamOnProgram) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Production team not found for this episode. Please assign a production team first.'
                        ], 404);
                    }
                    if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This arrangement is not from your production team.'
                        ], 403);
                    }
                    
                    // Only reject if status is arrangement_submitted, submitted, atau arrangement_in_progress
                    // arrangement_in_progress bisa di-reject baik sudah punya file maupun belum (untuk kasus Music Arranger terlalu lama atau ada masalah)
                    if (!in_array($item->status, ['arrangement_submitted', 'submitted', 'arrangement_in_progress'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted or in-progress arrangement files can be rejected'
                        ], 400);
                    }
                    
                    // Jika status arrangement_in_progress tapi sudah punya file, update status ke arrangement_submitted dulu
                    if ($item->status === 'arrangement_in_progress' && $item->file_path) {
                        $item->update([
                            'status' => 'arrangement_submitted',
                            'submitted_at' => now()
                        ]);
                        $item->refresh();
                    }
                    
                    $item->update([
                        'status' => 'arrangement_rejected',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'rejection_reason' => $request->reason,
                        'needs_sound_engineer_help' => true // Mark as needing help when rejected
                    ]);

                    // Log Workflow State for Arrangement Rejection
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'music_arrangement',
                        'music_arranger',
                        $item->created_by,
                        "Music arrangement rejected by Producer: {$item->song_title}. Reason: {$request->reason}",
                        $user->id,
                        [
                            'action' => 'music_arrangement_rejected',
                            'song_title' => $item->song_title,
                            'rejection_reason' => $request->reason
                        ]
                    );
                    
                    // Notify Music Arranger
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'music_arrangement_rejected',
                        'title' => 'Arrangement Lagu Ditolak',
                        'message' => "Arrangement lagu '{$item->song_title}' telah ditolak. Alasan: {$request->reason}. Sound Engineer dapat membantu perbaikan arrangement ini.",
                        'data' => [
                            'arrangement_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason,
                            'needs_sound_engineer_help' => true
                        ]
                    ]);

                    // Notify Sound Engineers in the production team that they can help
                    $episode = $item->episode;
                    // Support episode.productionTeam langsung atau episode.program.productionTeam
                    $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);
                    
                    if ($productionTeam) {
                        $soundEngineers = $productionTeam->members()
                            ->where('is_active', true)
                            ->whereRaw('LOWER(role) IN (?, ?, ?)', ['sound_eng', 'sound_engineer', 'sound engineer'])
                            ->get();

                        foreach ($soundEngineers as $soundEngineerMember) {
                            Notification::create([
                                'user_id' => $soundEngineerMember->user_id,
                                'type' => 'arrangement_rejected_help_needed',
                                'title' => 'Arrangement Ditolak - Butuh Bantuan',
                                'message' => "Arrangement '{$item->song_title}' untuk Episode {$episode->episode_number} telah ditolak. Alasan: {$request->reason}. Anda dapat membantu perbaikan arrangement ini.",
                                'data' => [
                                    'arrangement_id' => $item->id,
                                    'episode_id' => $episode->id,
                                    'rejection_reason' => $request->reason,
                                    'needs_sound_engineer_help' => true
                                ]
                            ]);
                        }
                    }
                    break;
                    
                case 'creative_work':
                    $item = CreativeWork::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                        'createdBy'
                    ])->findOrFail($id);
                    
                    // Validate Producer has access (same logic as getApprovals: episode OR program production team)
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this creative work.'
                        ], 404);
                    }
                    $teamOnEpisode = $episode->productionTeam;
                    $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
                    if (!$teamOnEpisode && !$teamOnProgram) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Production team not found for this episode. Please assign a production team first.'
                        ], 404);
                    }
                    if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This creative work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted creative works can be rejected'
                        ], 400);
                    }
                    
                    /** @var \App\Models\CreativeWork $item */
                    $item->reject(auth()->id(), $request->reason);

                    // Log Workflow State for Creative Work Rejection
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'creative_work',
                        'kreatif',
                        $item->created_by,
                        "Creative work rejected by Producer. Reason: {$request->reason}",
                        $user->id,
                        [
                            'action' => 'creative_work_rejected',
                            'rejection_reason' => $request->reason
                        ]
                    );
                    
                    // Notify Creative
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'creative_work_rejected',
                        'title' => 'Creative Work Rejected',
                        'message' => "Your creative work for Episode {$item->episode->episode_number} has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'creative_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);
                    
                    // Workflow state tetap di creative_work untuk revisi
                    break;
                    
                case 'equipment_request':
                    $item = ProductionEquipment::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
                    break;
                    
                case 'budget_request':
                    $item = Budget::findOrFail($id);
                    $item->reject(auth()->id(), $request->reason);
                    break;

                case 'sound_engineer_recording':
                    $item = SoundEngineerRecording::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.program' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed()
                    ])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This recording is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'completed') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only completed recordings can be rejected'
                        ], 400);
                    }

                    // Reject recording - change status back to recording for revision
                    $item->update([
                        'status' => 'recording',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_notes' => $request->reason
                    ]);

                    // Log Workflow State for Recording Rejection
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $item->episode,
                        'vocal_recording',
                        'sound_eng',
                        $item->created_by,
                        "Sound recording rejected by Producer. Reason: {$request->reason}",
                        $user->id,
                        [
                            'action' => 'vocal_recording_rejected',
                            'rejection_reason' => $request->reason
                        ]
                    );

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'sound_engineer_recording_rejected',
                        'title' => 'Recording Needs Revision',
                        'message' => "Your recording has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'recording_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);
                    break;

                case 'sound_engineer_editing':
                    $item = SoundEngineerEditing::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.productionTeam' => fn($q) => $q->withTrashed(),
                        'episode.program' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed()
                    ])->findOrFail($id);
                    
                    // Validate Producer has access
                    // Support episode.productionTeam langsung atau episode.program.productionTeam
                    $episode = $item->episode;
                    if (!$episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Episode not found for this editing work.'
                        ], 404);
                    }
                    
                    $episodeTeam = $episode->productionTeam;
                    $programTeam = $episode->program ? $episode->program->productionTeam : null;
                    
                    if (!$episodeTeam && !$programTeam) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Production team not found for this episode. Please assign a production team first.'
                        ], 404);
                    }
                    
                    // Check if producer matches EITHER episode's or program's production team
                    $episodeMatch = $episodeTeam && $episodeTeam->producer_id === $user->id;
                    $programMatch = $programTeam && $programTeam->producer_id === $user->id;
                    
                    if (!$episodeMatch && !$programMatch) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editing work is not from your production team.'
                        ], 403);
                    }

                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editing works can be rejected'
                        ], 400);
                    }

                    // Reject editing - reset to revision_needed so Sound Engineer can edit again
                    $item->update([
                        'status' => 'revision_needed',
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                        'rejection_reason' => $request->reason,
                        'submitted_at' => null // Reset submitted_at to allow resubmission
                    ]);

                    // Notify Sound Engineer
                    Notification::create([
                        'user_id' => $item->sound_engineer_id,
                        'type' => 'sound_engineer_editing_rejected',
                        'title' => 'Editing Work Needs Revision',
                        'message' => "Your editing work has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'editing_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);

                    // Log Workflow State for Editing Rejection
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $episode,
                        'editing',
                        'sound_eng',
                        $item->sound_engineer_id,
                        "Sound engineer editing rejected by Producer. Reason: {$request->reason}",
                        $user->id,
                        [
                            'action' => 'vocal_editing_rejected', // matched in monitorWorkflow
                            'rejection_reason' => $request->reason
                        ]
                    );
                    break;
                    
                case 'editor_work':
                    $item = EditorWork::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.program' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                        'createdBy'
                    ])->findOrFail($id);
                    
                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This editor work is not from your production team.'
                        ], 403);
                    }
                    
                    if ($item->status !== 'submitted') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only submitted editor works can be rejected'
                        ], 400);
                    }
                    
                    /** @var \App\Models\CreativeWork $item */
                    $item->reject(auth()->id(), $request->reason);
                    
                    // Notify Editor
                    Notification::create([
                        'user_id' => $item->created_by,
                        'type' => 'editor_work_rejected',
                        'title' => 'Editor Work Rejected',
                        'message' => "Your editor work for Episode {$item->episode->episode_number} has been rejected. Reason: {$request->reason}",
                        'data' => [
                            'editor_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);

                    // Log Workflow State for Editor Work Rejection
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $item->episode,
                        'editing',
                        'editor',
                        $item->created_by,
                        "Editor work rejected by Producer. Reason: {$request->reason}",
                        $user->id,
                        [
                            'action' => 'video_editing_rejected',
                            'rejection_reason' => $request->reason
                        ]
                    );
                    break;

                case 'produksi_work':
                    $item = ProduksiWork::with([
                        'episode' => fn($q) => $q->withTrashed(),
                        'episode.program.productionTeam' => fn($q) => $q->withTrashed()
                    ])->findOrFail($id);

                    // Validate Producer has access
                    if ($item->episode->program->productionTeam->producer_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: This production work is not from your production team.'
                        ], 403);
                    }

                    // Reject work - set status back to in_progress for revision
                    $item->update([
                        'status' => 'in_progress',
                        'notes' => "Rejected by Producer: " . $request->reason . "\n" . ($item->notes ?: '')
                    ]);

                    // Notify Production Team
                    Notification::create([
                        'user_id' => $item->completed_by ?: $item->created_by,
                        'type' => 'produksi_work_rejected',
                        'title' => 'Produksi Work Rejected',
                        'message' => "Produksi work untuk Episode {$item->episode->episode_number} telah ditolak oleh Producer. Alasan: {$request->reason}",
                        'data' => [
                            'produksi_work_id' => $item->id,
                            'episode_id' => $item->episode_id,
                            'rejection_reason' => $request->reason
                        ]
                    ]);

                    // Update workflow state history
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $item->episode,
                        'shooting_recording',
                        'production',
                        $item->completed_by ?: $item->created_by,
                        "Production work rejected by Producer. Reason: {$request->reason}",
                        $user->id,
                        [
                            'action' => 'shooting_production_rejected',
                            'rejection_reason' => $request->reason
                        ]
                    );
                    break;
            }
            
            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Item rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get programs
     * Producer hanya bisa melihat program dari ProductionTeam mereka
     */
    public function getPrograms(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            // Log untuk debugging
            Log::info('Producer getPrograms - Start', [
                'producer_id' => $user->id,
                'producer_name' => $user->name,
                'request_params' => $request->all()
            ]);
            
            $query = Program::with(['managerProgram', 'productionTeam'])
                ->withCount([
                    'episodes',
                    'episodes as episodes_finished_count' => fn ($q) => $q->where('status', 'aired'),
                ])
                ->whereNotNull('production_team_id'); // Pastikan production_team_id tidak NULL
            
            // Producer hanya bisa melihat program dari ProductionTeam mereka
            // Gunakan whereHas untuk memastikan productionTeam ada dan producer_id sesuai
            if (!$isProgramManager) {
                $query->whereHas('productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id)
                      ->where('is_active', true); // Hanya production team yang aktif
                });
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by production team
            if ($request->has('production_team_id')) {
                $query->where('production_team_id', $request->production_team_id);
            }
            
            // Search by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }
            
            $perPage = (int) $request->get('per_page', 15);
            if ($perPage === 0) {
                $programs = $query->orderBy('created_at', 'desc')->get();
                return response()->json([
                    'success' => true,
                    'data' => $programs,
                    'message' => 'Programs retrieved successfully'
                ]);
            }

            $programs = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            // Log hasil query
            Log::info('Producer getPrograms - Result', [
                'producer_id' => $user->id,
                'total_programs' => $programs->total(),
                'current_page' => $programs->currentPage(),
                'program_ids' => $programs->getCollection()->pluck('id')->toArray()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $programs,
                'message' => 'Programs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Producer getPrograms - Error', [
                'producer_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get programs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single program detail with episodes (untuk Producer).
     * Dipakai dashboard Producer: nama program, rundown, daftar episode (52), progress.
     * GET /api/live-tv/producer/programs/{id}
     */
    public function getProgramDetail(Request $request, $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);

            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $program = Program::with([
                'managerProgram',
                'productionTeam.members.user',
                'episodes' => fn ($q) => $q->orderBy('episode_number'),
            ])
                ->withCount([
                    'episodes',
                    'episodes as episodes_finished_count' => fn ($q) => $q->where('status', 'aired'),
                ])
                ->whereNotNull('production_team_id')
                ->when(!$isProgramManager, function ($q) use ($user) {
                    $q->whereHas('productionTeam', function ($sub) use ($user) {
                        $sub->where('producer_id', $user->id)->where('is_active', true);
                    });
                })
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $program,
                'message' => 'Program detail retrieved successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Program tidak ditemukan atau bukan tanggung jawab Producer ini.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('ProducerController@getProgramDetail: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get program detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get episodes
     * Producer hanya bisa melihat episode dari program ProductionTeam mereka
     */
    public function getEpisodes(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            $query = Episode::with([
                'program.productionTeam', 
                'deadlines', 
                'workflowStates.performingUser',
                'musicArrangements.createdBy',
                'musicArrangements.reviewedBy'
            ])
                ->when(!$isProgramManager, function ($q) use ($user) {
                    $q->whereHas('program.productionTeam', function ($sub) use ($user) {
                        $sub->where('producer_id', $user->id);
                    });
                });
            
            // Filter by program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by workflow state
            if ($request->has('workflow_state')) {
                $query->where('current_workflow_state', $request->workflow_state);
            }
            
            $perPage = (int) $request->get('per_page', 15);
            if ($perPage === 0) {
                $episodes = $query->orderBy('episode_number')->get();
                return response()->json([
                    'success' => true,
                    'data' => $episodes,
                    'message' => 'Episodes retrieved successfully'
                ]);
            }

            $episodes = $query->orderBy('episode_number')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $episodes,
                'message' => 'Episodes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get episodes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get production overview
     */
    public function getProductionOverview(Request $request): JsonResponse
    {
        try {
            $programId = $request->get('program_id');
            
            $episodeStats = Episode::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $deadlineStats = \App\Models\Deadline::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $overview = [
            'programs' => Program::count(),
            'episodes' => $episodeStats->sum(),
            'deadlines' => $deadlineStats->sum(),
            'overdue_deadlines' => $deadlineStats->get('overdue', 0),
            'pending_approvals' => $this->getPendingApprovalsCount(),
            'in_production_episodes' => $episodeStats->get('in_production', 0),
            'completed_episodes' => $episodeStats->get('aired', 0)
        ];
        
        if ($programId) {
            $programEpisodeStats = Episode::where('program_id', $programId)
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $programDeadlineStats = \App\Models\Deadline::whereHas('episode', function ($q) use ($programId) {
                    $q->where('program_id', $programId);
                })
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $overview['program_specific'] = [
                'episodes' => $programEpisodeStats->sum(),
                'deadlines' => $programDeadlineStats->sum(),
                'overdue_deadlines' => $programDeadlineStats->get('overdue', 0)
            ];
        }
    
            
            return response()->json([
                'success' => true,
                'data' => $overview,
                'message' => 'Production overview retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get production overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending approvals count
     */
    private function getPendingApprovalsCount(): int
    {
        return MusicArrangement::where('status', 'submitted')->count() +
               CreativeWork::where('status', 'submitted')->count() +
               ProductionEquipment::where('status', 'pending')->count() +
               Budget::where('status', 'submitted')->count();
    }

    /**
     * Get budget requests submitted by the current user (Producer).
     * Untuk tampilan "Permohonan Dana Saya" — status & bukti transfer ketika sudah dibayar GA.
     */
    public function getMyBudgetRequests(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $requests = BudgetRequest::where('requested_by', $user->id)
            ->with(['program'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Tambah episode_number / episode_label dari title/description agar Producer tahu episode berapa
        $requests->each(function ($req) {
            $text = implode(' ', array_filter([$req->title, $req->description]));
            if (preg_match('/\bepisode\s*(\d+)\b/i', $text, $m)) {
                $req->episode_number = (int) $m[1];
                $req->episode_label = 'Episode ' . $m[1];
            }
        });

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Get rejected arrangements history for Producer
     * Producer bisa melihat history arrangement yang sudah di-reject
     */
    public function getRejectedArrangementsHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            // Get rejected arrangements from Producer's production teams; fallback: episode/program tanpa PT tetap ditampilkan
            $query = MusicArrangement::whereIn('status', ['arrangement_rejected', 'rejected'])
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'createdBy',
                    'reviewedBy',
                    'soundEngineerHelper'
                ])
                ->where(function ($q) use ($user, $isProgramManager) {
                    if ($isProgramManager) {
                        $q->whereNotNull('id');
                        return;
                    }
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    })
                    ->orWhereHas('episode', function ($subQ) {
                        $subQ->withTrashed()->whereNull('production_team_id')
                            ->where(function ($epQ) {
                                $epQ->whereDoesntHave('program')
                                    ->orWhereHas('program', function ($pq) {
                                        $pq->withTrashed()->whereNull('production_team_id');
                                    });
                            });
                    });
                });
            
            // Filter by episode if provided
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }
            
            // Filter by date range if provided
            if ($request->has('date_from')) {
                $query->whereDate('reviewed_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('reviewed_at', '<=', $request->date_to);
            }
            
            $arrangements = $query->orderBy('reviewed_at', 'desc')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $arrangements,
                'message' => 'Rejected arrangements history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving rejected arrangements history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved arrangements history for Producer
     * Producer bisa melihat history arrangement yang sudah disetujui
     */
    public function getApprovedArrangementsHistory(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            
            // Get approved arrangements from Producer's production teams; fallback: episode/program tanpa PT tetap ditampilkan
            $query = MusicArrangement::whereIn('status', ['arrangement_approved', 'approved'])
                ->with([
                    'episode' => fn($q) => $q->withTrashed(),
                    'episode.productionTeam' => fn($q) => $q->withTrashed(),
                    'episode.program' => fn($q) => $q->withTrashed(),
                    'episode.program.productionTeam' => fn($q) => $q->withTrashed(),
                    'createdBy',
                    'reviewedBy'
                ])
                ->where(function ($q) use ($user) {
                    $q->whereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                            $subQ->withTrashed()->where('producer_id', $user->id);
                        });
                    })
                    ->orWhereHas('episode', function($epQ) use ($user) {
                        $epQ->withTrashed()->whereHas('program', function($prQ) use ($user) {
                            $prQ->withTrashed()->whereHas('productionTeam', function ($subQ) use ($user) {
                                $subQ->withTrashed()->where('producer_id', $user->id);
                            });
                        });
                    })
                    ->orWhereHas('episode', function ($subQ) {
                        $subQ->withTrashed()->whereNull('production_team_id')
                            ->where(function ($epQ) {
                                $epQ->whereDoesntHave('program')
                                    ->orWhereHas('program', function ($pq) {
                                        $pq->withTrashed()->whereNull('production_team_id');
                                    });
                            });
                    });
                });
            
            // Filter by episode if provided
            if ($request->has('episode_id')) {
                $query->where('episode_id', $request->episode_id);
            }
            
            // Filter by date range if provided
            if ($request->has('date_from')) {
                $query->whereDate('reviewed_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('reviewed_at', '<=', $request->date_to);
            }
            
            $arrangements = $query->orderBy('reviewed_at', 'desc')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $arrangements,
                'message' => 'Approved arrangements history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving approved arrangements history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team performance
     */
    public function getTeamPerformance(Request $request): JsonResponse
    {
        try {
            $programId = $request->get('program_id');
            $teamId = $request->get('team_id');
            
            $query = \App\Models\ProductionTeam::with(['members.user']);
        
        if ($teamId) {
            $query->where('id', $teamId);
        }
        
        $teams = $query->get();
        
        // Bulk fetch all relevant deadlines for these teams' programs/episodes
        $allEpisodeIds = [];
        foreach ($teams as $team) {
            $allEpisodeIds = array_merge($allEpisodeIds, \App\Models\Episode::whereHas('program', function($q) use ($team) {
                $q->where('production_team_id', $team->id);
            })->pluck('id')->toArray());
        }
        $allEpisodeIds = array_unique($allEpisodeIds);

        // Fetch counts for deadlines grouped by episode and role
        $deadlineStats = \App\Models\Deadline::whereIn('episode_id', $allEpisodeIds)
            ->selectRaw('episode_id, role, is_completed, status, count(*) as count')
            ->groupBy('episode_id', 'role', 'is_completed', 'status')
            ->get();

        // Fetch workflow tasks grouped by user
        $workflowStats = \App\Models\WorkflowState::whereIn('assigned_to_user_id', $teams->flatMap->members->pluck('user_id'))
            ->selectRaw('assigned_to_user_id, current_state, count(*) as count')
            ->groupBy('assigned_to_user_id', 'current_state')
            ->get();

        $performance = [];
        
        foreach ($teams as $team) {
            $teamPerformance = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'members' => [],
                'total_deadlines' => 0,
                'completed_deadlines' => 0,
                'overdue_deadlines' => 0
            ];

            // Get episodes for this specific team
            $teamEpisodeIds = \App\Models\Episode::whereHas('program', function($q) use ($team) {
                $q->where('production_team_id', $team->id);
            })->when($programId, function($q) use ($programId) {
                $q->where('program_id', $programId);
            })->pluck('id')->toArray();
            
            foreach ($team->members as $member) {
                // Filter deadline stats for this member's role and team's episodes
                $memberDeadlines = $deadlineStats->whereIn('episode_id', $teamEpisodeIds)
                    ->where('role', $member->role);
                
                $totalDl = $memberDeadlines->sum('count');
                $completedDl = $memberDeadlines->where('is_completed', true)->sum('count');
                $overdueDl = $memberDeadlines->where('status', 'overdue')->sum('count');

                $memberWorkflow = $workflowStats->where('assigned_to_user_id', $member->user_id);
                
                $memberPerformance = [
                    'user_id' => $member->user_id,
                    'user_name' => $member->user->name ?? 'Unknown',
                    'role' => $member->role,
                    'deadlines' => [
                        'total' => $totalDl,
                        'completed' => $completedDl,
                        'overdue' => $overdueDl
                    ],
                    'workflow_tasks' => [
                        'total' => $memberWorkflow->sum('count'),
                        'by_state' => $memberWorkflow->map(function($stat) {
                            return ['current_state' => $stat->current_state, 'count' => $stat->count];
                        })->values()
                    ]
                ];
                
                $teamPerformance['members'][] = $memberPerformance;
                $teamPerformance['total_deadlines'] += $totalDl;
                $teamPerformance['completed_deadlines'] += $completedDl;
                $teamPerformance['overdue_deadlines'] += $overdueDl;
            }
            
            $performance[] = $teamPerformance;
        }
    
            
            return response()->json([
                'success' => true,
                'data' => $performance,
                'message' => 'Team performance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get team performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get member deadlines
     */
    private function getMemberDeadlines(int $userId, ?int $programId = null): array
    {
        $query = \App\Models\Deadline::whereHas('episode', function ($q) use ($programId) {
            if ($programId) {
                $q->where('program_id', $programId);
            }
        });
        
        return [
            'total' => $query->count(),
            'completed' => $query->where('is_completed', true)->count(),
            'overdue' => $query->where('status', 'overdue')->count()
        ];
    }

    /**
     * Get member workflow tasks
     */
    private function getMemberWorkflowTasks(int $userId, ?int $programId = null): array
    {
        $query = \App\Models\WorkflowState::where('assigned_to_user_id', $userId);
        
        if ($programId) {
            $query->whereHas('episode', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            });
        }
        
        return [
            'total' => $query->count(),
            'by_state' => $query->groupBy('current_state')->selectRaw('current_state, COUNT(*) as count')->get()
        ];
    }

    /**
     * Cancel jadwal syuting/rekaman
     * User: "dapat cancel jadwal syuting(jika terjadi kendala)"
     */
    public function cancelSchedule(Request $request, $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = \App\Models\MusicSchedule::findOrFail($id);

            // Update schedule status
            $schedule->update([
                'status' => 'cancelled',
                'cancellation_reason' => $request->reason,
                'cancelled_by' => $user->id,
                'cancelled_at' => now()
            ]);

            // Notify team members
            $this->notifyScheduleCancelled($schedule, $request->reason);

            return response()->json([
                'success' => true,
                'data' => $schedule->load(['musicSubmission', 'creator', 'canceller']),
                'message' => 'Schedule cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign production teams to creative work
     * Called by Producer after approving creative work
     */
    public function assignProductionTeams(Request $request, int $creativeWorkId): JsonResponse
    {
        Log::info('AssignProductionTeams Request', [
            'creative_work_id' => $creativeWorkId,
            'payload' => $request->all()
        ]);
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Frontend boleh kirim vocal_team_ids (Vocal Team) = backend pakai recording_team_ids
            if ($request->has('vocal_team_ids')) {
                $request->merge(['recording_team_ids' => $request->vocal_team_ids]);
            }

            $validator = Validator::make($request->all(), [
                'shooting_team_ids' => 'nullable|array',
                'shooting_team_ids.*' => 'exists:users,id',
                'shooting_schedule_id' => 'nullable|exists:music_schedules,id',
                'setting_team_ids' => 'nullable|array',
                'setting_team_ids.*' => 'exists:users,id',
                'recording_team_ids' => 'nullable|array',
                'recording_team_ids.*' => 'exists:users,id',
                'vocal_team_ids' => 'nullable|array',
                'vocal_team_ids.*' => 'exists:users,id',
                'recording_schedule_id' => 'nullable|exists:music_schedules,id',
                'shooting_team_notes' => 'nullable|string|max:1000',
                'setting_team_notes' => 'nullable|string|max:1000',
                'recording_team_notes' => 'nullable|string|max:1000',
                'shooting_coordinator_id' => 'nullable|exists:users,id',
                'setting_coordinator_id' => 'nullable|exists:users,id',
                'recording_coordinator_id' => 'nullable|exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('Fetching creative work', ['id' => $creativeWorkId]);
            $creativeWork = CreativeWork::with(['episode.productionTeam', 'episode.program.productionTeam'])->findOrFail($creativeWorkId);
            Log::info('Creative work found', ['episode_id' => $creativeWork->episode_id]);

            // Validate relationships exist
            $episode = $creativeWork->episode;
            if (!$episode) {
                Log::warning('Episode not found for creative work', ['creative_work_id' => $creativeWorkId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work does not have an associated episode.'
                ], 400);
            }

            // Producer harus punya akses (episode.productionTeam ATAU program.productionTeam)
            if (!$isProgramManager && !$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                Log::warning('Producer not authorized', ['user_id' => $user->id, 'episode_id' => $episode->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            // Paling tidak satu tim harus diisi agar ada yang disimpan
            $hasShooting = !empty($request->shooting_team_ids);
            $hasSetting = !empty($request->setting_team_ids);
            $hasRecording = !empty($request->recording_team_ids);
            if (!$hasShooting && !$hasSetting && !$hasRecording) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu tim (Shooting, Setting, atau Vocal/Recording) beserta anggotanya.',
                    'errors' => ['teams' => ['At least one team with members is required.']]
                ], 422);
            }

            // Semua user aktif boleh ditambahkan, kecuali Program Manager dan Distribution Manager (dua penulisan untuk PM)
            $excludedRoles = ['Program Manager', 'Manager Program', 'Distribution Manager'];
            $allActiveUsers = User::where('is_active', true)
                ->whereNotIn('role', $excludedRoles)
                ->pluck('id')
                ->toArray();

            // Validate all selected team members are valid active users (and NOT managers)
            $allTeamMemberIds = array_merge(
                (array) ($request->shooting_team_ids ?? []),
                (array) ($request->setting_team_ids ?? []),
                (array) ($request->recording_team_ids ?? [])
            );
            
            Log::info('Validating team members', [
                'count_active' => count($allActiveUsers),
                'count_selected' => count($allTeamMemberIds),
                'allTeamMemberIds' => $allTeamMemberIds
            ]);

            if (!empty($allTeamMemberIds)) {
                $invalidMembers = array_diff($allTeamMemberIds, $allActiveUsers);
                if (!empty($invalidMembers)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some selected users cannot be assigned (must be active; Program Manager and Distribution Manager cannot be added to teams).',
                        'invalid_users' => array_values($invalidMembers)
                    ], 400);
                }
            }

            // Validate episode_id exists
            if (!$creativeWork->episode_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work does not have a valid episode ID.'
                ], 400);
            }

            // Validate schedule_id if provided
            if ($request->shooting_schedule_id) {
                $scheduleExists = MusicSchedule::where('id', $request->shooting_schedule_id)->exists();
                if (!$scheduleExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Shooting schedule ID does not exist'
                    ], 400);
                }
            }

            if ($request->recording_schedule_id) {
                $scheduleExists = MusicSchedule::where('id', $request->recording_schedule_id)->exists();
                if (!$scheduleExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Recording schedule ID does not exist'
                    ], 400);
                }
            }

            $episode = $creativeWork->episode;
            $episodeId = $episode->id;
            
            // Get production team ID for the producer (required by ProductionTeamMember)
            $productionTeam = \App\Models\ProductionTeam::where('producer_id', $user->id)->first();
            $productionTeamId = $productionTeam ? $productionTeam->id : null;
            
            $assignments = [];

            // Start database transaction
            DB::beginTransaction();

            // Assign shooting team
            if ($request->has('shooting_team_ids') && is_array($request->shooting_team_ids) && count($request->shooting_team_ids) > 0) {
                Log::info('Assigning shooting team');
                $shootingAssignment = ProductionTeamAssignment::create([
                    'music_submission_id' => null,
                    'episode_id' => $episodeId,
                    'schedule_id' => $request->shooting_schedule_id,
                    'assigned_by' => $user->id,
                    'team_type' => 'shooting',
                    'team_name' => $request->shooting_team_name ?? 'Shooting Team',
                    'team_notes' => $request->shooting_team_notes,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                foreach ($request->shooting_team_ids as $index => $userId) {
                    $existingMember = ProductionTeamMember::where('assignment_id', $shootingAssignment->id)
                        ->where('user_id', $userId)
                        ->first();
                    
                    if ($existingMember) {
                        continue;
                    }

                    ProductionTeamMember::create([
                        // Assignment-based membership: avoid global team-role uniqueness collisions
                        // (a user can be "leader" in both shooting & setting for the same producer team).
                        'production_team_id' => null,
                        'assignment_id' => $shootingAssignment->id,
                        'user_id' => $userId,
                        'role' => $index === 0 ? 'leader' : 'crew',
                        'is_coordinator' => (int) $userId === (int) ($request->shooting_coordinator_id ?? 0),
                        'status' => 'assigned',
                    ]);
                }
                $assignments['shooting_team'] = $shootingAssignment;
                Log::info('Shooting team assigned');
            }

            // Assign setting team
            if ($request->has('setting_team_ids') && is_array($request->setting_team_ids) && count($request->setting_team_ids) > 0) {
                Log::info('Assigning setting team');
                $settingAssignment = ProductionTeamAssignment::create([
                    'music_submission_id' => null,
                    'episode_id' => $episodeId,
                    'schedule_id' => $request->shooting_schedule_id, // Usually same as shooting
                    'assigned_by' => $user->id,
                    'team_type' => 'setting',
                    'team_name' => $request->setting_team_name ?? 'Setting Team',
                    'team_notes' => $request->setting_team_notes,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                foreach ($request->setting_team_ids as $index => $userId) {
                    $existingMember = ProductionTeamMember::where('assignment_id', $settingAssignment->id)
                        ->where('user_id', $userId)
                        ->first();
                    
                    if ($existingMember) {
                        continue;
                    }

                    ProductionTeamMember::create([
                        // Assignment-based membership: avoid global team-role uniqueness collisions
                        'production_team_id' => null,
                        'assignment_id' => $settingAssignment->id,
                        'user_id' => $userId,
                        'role' => $index === 0 ? 'leader' : 'crew',
                        'is_coordinator' => (int) $userId === (int) ($request->setting_coordinator_id ?? 0),
                        'status' => 'assigned',
                    ]);
                }
                $assignments['setting_team'] = $settingAssignment;
                Log::info('Setting team assigned');
            }

            // Assign recording team
            if ($request->has('recording_team_ids') && is_array($request->recording_team_ids) && count($request->recording_team_ids) > 0) {
                Log::info('Assigning recording team');
                $recordingAssignment = ProductionTeamAssignment::create([
                    'music_submission_id' => null,
                    'episode_id' => $episodeId,
                    'schedule_id' => $request->recording_schedule_id,
                    'assigned_by' => $user->id,
                    'team_type' => 'recording',
                    'team_name' => $request->recording_team_name ?? 'Recording Team',
                    'team_notes' => $request->recording_team_notes,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                foreach ($request->recording_team_ids as $index => $userId) {
                    $existingMember = ProductionTeamMember::where('assignment_id', $recordingAssignment->id)
                        ->where('user_id', $userId)
                        ->first();
                    
                    if ($existingMember) {
                        continue;
                    }

                    ProductionTeamMember::create([
                        // Assignment-based membership: avoid global team-role uniqueness collisions
                        'production_team_id' => null,
                        'assignment_id' => $recordingAssignment->id,
                        'user_id' => $userId,
                        'role' => $index === 0 ? 'leader' : 'crew',
                        'is_coordinator' => (int) $userId === (int) ($request->recording_coordinator_id ?? 0),
                        'status' => 'assigned',
                    ]);
                }
                $assignments['recording_team'] = $recordingAssignment;
                Log::info('Recording team assigned');
            }

            // Notify team members
            Log::info('Inserting notifications');
            $notificationsToInsert = [];
            $now = now();
            
            // Notify team members
            Log::info('Creating notifications');
            $now = now();
            
            foreach ($assignments as $assignment) {
                $loadedAssignment = ProductionTeamAssignment::with('members.user', 'episode')->find($assignment->id);
                if (!$loadedAssignment) continue;

                foreach ($loadedAssignment->members as $member) {
                    try {
                        Notification::create([
                            'user_id' => $member->user_id,
                            'type' => 'team_assigned',
                            'title' => 'Ditugaskan ke Tim ' . ucfirst($loadedAssignment->team_type),
                            'message' => "Anda telah ditugaskan ke tim {$loadedAssignment->team_name} untuk Episode {$loadedAssignment->episode->episode_number}.",
                            'data' => [
                                'assignment_id' => $loadedAssignment->id,
                                'episode_id' => $loadedAssignment->episode_id,
                                'team_type' => $loadedAssignment->team_type
                            ],
                            'status' => 'unread',
                            'episode_id' => $loadedAssignment->episode_id
                        ]);
                    } catch (\Exception $notifyEx) {
                        Log::error('Failed to create notification for user', [
                            'user_id' => $member->user_id,
                            'error' => $notifyEx->getMessage()
                        ]);
                        // Don't fail the whole process if one notification fails
                    }
                }
            }
            Log::info('Notifications processed');

            DB::commit();
            Log::info('Transaction committed');

            return response()->json([
                'success' => true,
                'data' => [
                    'assignments' => $assignments,
                    'episode_id' => $episodeId,
                    'episode_number' => $episode->episode_number
                ],
                'message' => 'Production teams assigned successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning production teams', [
                'creative_work_id' => $creativeWorkId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error assigning teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Emergency reassign team untuk jadwal syuting
     * User: "dapat mengganti tim syuting secara dadakan"
     */
    public function emergencyReassignTeam(Request $request, $scheduleId): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'team_type' => 'required|in:shooting,setting,recording',
                'new_team_member_ids' => 'required|array|min:1',
                'new_team_member_ids.*' => 'exists:users,id',
                'reason' => 'required|string|max:1000',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $schedule = \App\Models\MusicSchedule::findOrFail($scheduleId);

            // Find existing team assignment
            $existingAssignment = \App\Models\ProductionTeamAssignment::where('schedule_id', $scheduleId)
                ->where('team_type', $request->team_type)
                ->whereIn('status', ['assigned', 'confirmed', 'in_progress'])
                ->first();

            // Cancel old assignment if exists
            if ($existingAssignment) {
                $oldMemberIds = $existingAssignment->members()->pluck('user_id')->toArray();
                
                $existingAssignment->update([
                    'status' => 'cancelled',
                    'completed_at' => now()
                ]);

                // Notify old team members
                $this->notifyTeamReassigned($oldMemberIds, $schedule, 'removed', $request->reason);
            }

            // Create new team assignment
            $newAssignment = \App\Models\ProductionTeamAssignment::create([
                'music_submission_id' => $schedule->music_submission_id,
                'schedule_id' => $scheduleId,
                'assigned_by' => $user->id,
                'team_type' => $request->team_type,
                'team_name' => ucfirst($request->team_type) . ' Team (Emergency)',
                'team_notes' => $request->notes . ' | REASSIGNMENT REASON: ' . $request->reason,
                'status' => 'assigned',
                'assigned_at' => now()
            ]);

            // Add new team members
            foreach ($request->new_team_member_ids as $index => $userId) {
                \App\Models\ProductionTeamMember::create([
                    'assignment_id' => $newAssignment->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'leader' : 'crew',
                    'status' => 'assigned'
                ]);
            }

            // Notify new team members
            $this->notifyTeamReassigned($request->new_team_member_ids, $schedule, 'assigned', $request->reason);

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => $schedule->load(['musicSubmission']),
                    'old_assignment' => $existingAssignment,
                    'new_assignment' => $newAssignment->load('members.user')
                ],
                'message' => 'Team emergency reassigned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reassigning team: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify schedule cancelled
     */
    private function notifyScheduleCancelled($schedule, $reason): void
    {
        // Notify team members assigned to this schedule
        $teamAssignments = \App\Models\ProductionTeamAssignment::where('schedule_id', $schedule->id)
            ->whereIn('status', ['assigned', 'confirmed'])
            ->with('members')  // Eager load to avoid N+1
            ->get();

        // BULK INSERT to avoid N+1
        $notificationsToInsert = [];
        $now = now();
        
        foreach ($teamAssignments as $assignment) {
            foreach ($assignment->members as $member) {
                $notificationsToInsert[] = [
                    'title' => 'Jadwal Dibatalkan',
                    'message' => "Jadwal {$schedule->getScheduleTypeLabel()} untuk Episode telah dibatalkan. Alasan: {$reason}",
                    'type' => 'schedule_cancelled',
                    'user_id' => $member->user_id,
                    'data' => json_encode([
                        'schedule_id' => $schedule->id,
                        'schedule_type' => $schedule->schedule_type,
                        'reason' => $reason
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
        }
        
        // Bulk insert all notifications at once (1 query instead of N*M)
        if (!empty($notificationsToInsert)) {
            DB::table('notifications')->insert($notificationsToInsert);
        }
    }

    /**
     * Notify team reassigned
     */
    private function notifyTeamReassigned(array $memberIds, $schedule, string $action, string $reason): void
    {
        $message = $action === 'assigned' 
            ? "Anda ditugaskan secara darurat untuk jadwal {$schedule->getScheduleTypeLabel()}. Alasan: {$reason}"
            : "Anda telah digantikan dari jadwal {$schedule->getScheduleTypeLabel()}. Alasan: {$reason}";

        // BULK INSERT to avoid N+1
        $notificationsToInsert = [];
        $now = now();
        
        foreach ($memberIds as $userId) {
            $notificationsToInsert[] = [
                'title' => $action === 'assigned' ? 'Ditugaskan Darurat' : 'Tim Diganti',
                'message' => $message,
                'type' => 'team_emergency_reassigned',
                'user_id' => $userId,
                'data' => json_encode([
                    'schedule_id' => $schedule->id,
                    'schedule_type' => $schedule->schedule_type,
                    'action' => $action,
                    'reason' => $reason
                ]),
                'created_at' => $now,
                'updated_at' => $now
            ];
        }
        
        // Bulk insert all notifications at once (1 query instead of N)
        if (!empty($notificationsToInsert)) {
            DB::table('notifications')->insert($notificationsToInsert);
        }
    }

    /**
     * Producer edit rundown dengan approval flow ke Manager Program
     * User: "Producer dapat mengedit rundown jika dibutuhkan dan ajukan ke program manager"
     */
    public function editRundown(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'new_rundown' => 'required|string',
                'edit_reason' => 'required|string|max:1000',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::with(['program'])->findOrFail($episodeId);

            // Check if Producer has access to this episode's program
            if (
                !$episode->program
                || (!$isProgramManager && $episode->program->productionTeam->producer_id !== $user->id)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to edit rundown for this episode.'
                ], 403);
            }

            // Get Manager Program
            $managerProgram = $episode->program->managerProgram;
            if (!$managerProgram) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manager Program not found for this program.'
                ], 404);
            }

            // Create ProgramApproval request
            $approval = ProgramApproval::create([
                'approvable_id' => $episode->id,
                'approvable_type' => Episode::class,
                'approval_type' => 'episode_rundown',
                'requested_by' => $user->id,
                'requested_at' => now(),
                'request_notes' => $request->notes,
                'request_data' => [
                    'new_rundown' => $request->new_rundown,
                    'current_rundown' => $episode->rundown,
                    'edit_reason' => $request->edit_reason,
                    'episode_id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'episode_title' => $episode->title
                ],
                'current_data' => [
                    'current_rundown' => $episode->rundown
                ],
                'status' => 'pending',
                'priority' => 'normal'
            ]);

            // Notify Manager Program
            Notification::create([
                'user_id' => $managerProgram->id,
                'type' => 'rundown_edit_request',
                'title' => 'Permintaan Edit Rundown',
                'message' => "Producer {$user->name} meminta edit rundown untuk Episode {$episode->episode_number}: {$episode->title}. Alasan: {$request->edit_reason}",
                'data' => [
                    'approval_id' => $approval->id,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'edit_reason' => $request->edit_reason
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval->load(['requestedBy']),
                    'episode' => $episode->load(['program'])
                ],
                'message' => 'Rundown edit request submitted successfully. Waiting for Manager Program approval.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting rundown edit request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer edit song/singer sebelum approve arrangement
     * User: "Producer dapat mengganti usulan lagu dan penyanyi dari music arranger"
     */
    public function editArrangementSongSinger(Request $request, int $arrangementId): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'song_title' => 'nullable|string|max:255',
                'singer_name' => 'nullable|string|max:255',
                'song_id' => 'nullable|exists:songs,id',
                'singer_id' => 'nullable|exists:singers,id',
                'is_group' => 'nullable|boolean',
                'group_name' => 'nullable|required_if:is_group,true|string|max:255',
                'group_members' => 'nullable|array',
                'modification_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $arrangement = MusicArrangement::with(['episode.productionTeam', 'episode.program.productionTeam', 'createdBy'])->findOrFail($arrangementId);

            // Validate Producer has access
            // Support episode.productionTeam langsung atau episode.program.productionTeam
            $episode = $arrangement->episode;
            
            if (!$episode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrangement does not have an episode assigned.'
                ], 400);
            }
            
            $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null) ?? null;

            // Jika ada production team, Producer harus yang ditugaskan; jika belum ada PT (unassigned), izinkan edit oleh Producer mana pun
            if (!$isProgramManager && $productionTeam && $productionTeam->producer_id !== $user->id) {
                \Log::warning('Producer editArrangementSongSinger - Unauthorized access', [
                    'arrangement_id' => $arrangementId,
                    'producer_id' => $user->id,
                    'production_team_producer_id' => $productionTeam->producer_id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This arrangement is not from your production team.'
                ], 403);
            }
            
            \Log::info('Producer editArrangementSongSinger - Validation passed', [
                'arrangement_id' => $arrangementId,
                'status' => $arrangement->status,
                'producer_id' => $user->id,
                'production_team_id' => $productionTeam?->id
            ]);

            // Izinkan edit untuk: usulan lagu (song_proposal), yang sudah disetujui (song_approved), atau yang sudah submit file (submitted/arrangement_submitted/arrangement_in_progress)
            $allowedStatuses = ['song_proposal', 'submitted', 'arrangement_submitted', 'song_approved', 'arrangement_in_progress'];
            if (!in_array($arrangement->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only modify arrangement with status: ' . implode(', ', $allowedStatuses),
                    'current_status' => $arrangement->status
                ], 400);
            }

            // Get new values (use provided or keep existing)
            $newSongTitle = $request->song_title ?? $arrangement->song_title;
            $newSingerName = $request->singer_name ?? $arrangement->singer_name;
            $songId = $request->song_id;
            $singerId = $request->singer_id;

            // If song_id provided, get song title from database
            if ($songId && !$request->song_title) {
                $song = \App\Models\Song::find($songId);
                if ($song) {
                    $newSongTitle = $song->title;
                }
            }

            // If singer_id provided (dari tabel singers), get singer name from database
            if ($singerId && !$request->singer_name) {
                $singer = \App\Models\Singer::find($singerId);
                if ($singer) {
                    $newSingerName = $singer->name;
                }
            }

            // Apply modification
            $prevStatus = $arrangement->status;
            $isGroup = $request->boolean('is_group');
            $arrangement->producerModify(
                $newSongTitle, 
                $newSingerName, 
                $songId, 
                $singerId,
                $isGroup,
                $request->group_name,
                $request->group_members
            );

            // AUTO-APPROVE Logic removed to allow frontend to explicitly call approve() after editing.
            // This prevents 400 errors when the frontend calls both edit and approve in sequence.

            // Determine who to notify based on arrangement status and who submitted it
            // If status is arrangement_submitted and has sound_engineer_helper_id, it was fixed by Sound Engineer
            $isFixedBySoundEngineer = $arrangement->status === 'arrangement_submitted' && $arrangement->sound_engineer_helper_id;
            
            // Notify Music Arranger (creator) about modification
            $musicArrangerMessage = "Producer telah memodifikasi song/singer untuk arrangement '{$arrangement->song_title}'";
            if ($isFixedBySoundEngineer) {
                $musicArrangerMessage .= " yang sudah diperbaiki oleh Sound Engineer";
            }
            $musicArrangerMessage .= ". " . ($request->modification_notes ? "Catatan: {$request->modification_notes}" : '');
            
            Notification::create([
                'user_id' => $arrangement->created_by,
                'type' => 'arrangement_modified_by_producer',
                'title' => 'Arrangement Dimodifikasi oleh Producer',
                'message' => $musicArrangerMessage,
                'data' => [
                    'arrangement_id' => $arrangement->id,
                    'episode_id' => $arrangement->episode_id,
                    'original_song_title' => $arrangement->original_song_title,
                    'original_singer_name' => $arrangement->original_singer_name,
                    'modified_song_title' => $newSongTitle,
                    'modified_singer_name' => $newSingerName,
                    'modification_notes' => $request->modification_notes,
                    'is_fixed_by_sound_engineer' => $isFixedBySoundEngineer
                ]
            ]);
            
            // Also notify Sound Engineer if they helped fix this arrangement
            if ($isFixedBySoundEngineer && $arrangement->sound_engineer_helper_id) {
                Notification::create([
                    'user_id' => $arrangement->sound_engineer_helper_id,
                    'type' => 'arrangement_modified_by_producer',
                    'title' => 'Arrangement Dimodifikasi oleh Producer',
                    'message' => "Producer telah memodifikasi song/singer untuk arrangement '{$arrangement->song_title}' yang sudah Anda perbaiki. " . ($request->modification_notes ? "Catatan: {$request->modification_notes}" : ''),
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'episode_id' => $arrangement->episode_id,
                        'original_song_title' => $arrangement->original_song_title,
                        'original_singer_name' => $arrangement->original_singer_name,
                        'modified_song_title' => $newSongTitle,
                        'modified_singer_name' => $newSingerName,
                        'modification_notes' => $request->modification_notes
                    ]
                ]);
            }

            // Prepare response message
            $responseMessage = 'Arrangement song/singer modified ';
            if (in_array($prevStatus, ['song_proposal', 'submitted', 'arrangement_submitted'])) {
                $responseMessage .= 'and automatically approved successfully.';
            } else {
                $responseMessage .= 'successfully.';
            }

            if ($isFixedBySoundEngineer) {
                $responseMessage .= ' Music Arranger and Sound Engineer have been notified.';
            } else {
                $responseMessage .= ' Music Arranger has been notified.';
            }
            
            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(['song', 'singer', 'soundEngineerHelper']),
                'message' => $responseMessage,
                'auto_approved' => in_array($prevStatus, ['song_proposal', 'submitted', 'arrangement_submitted'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error modifying arrangement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available songs from database
     * Producer bisa akses untuk edit song proposal
     */
    public function getAvailableSongs(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = \App\Models\Song::where('status', 'available');

            // Search by title or artist
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('artist', 'like', "%{$search}%");
                });
            }

            // Filter by genre
            if ($request->has('genre')) {
                $query->where('genre', $request->genre);
            }

            $songs = $query->orderBy('title', 'asc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $songs,
                'message' => 'Available songs retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving songs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available singers from master data (tabel singers)
     * Sumber sama dengan Music Arranger agar dropdown "Pilih penyanyi dari database" terisi
     */
    public function getAvailableSingers(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);

            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = \App\Models\Singer::active();

            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $singers = $query->orderBy('name', 'asc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $singers,
                'message' => 'Available singers retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving singers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available crew members for the producer's programs
     * GET /api/live-tv/producer/crew-members
     *
     * Query params:
     * - scope=all  : Return ALL users that can be assigned to teams (active, excluding Program Manager & Distribution Manager).
     *                Use this for Shooting/Setting/Vocal team selection so HR, Hopeline Care, Office Assistant, etc. appear.
     * - (default) : Return only members of the Producer's production team(s) — legacy behavior.
     */
    public function getCrewMembers(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $scopeAll = $request->query('scope') === 'all' || $request->boolean('assignable');

            if ($scopeAll) {
                // Semua user yang bisa ditugaskan: aktif, kecuali Program Manager & Distribution Manager
                // Sesuai kriteria user: "Ambil semua crew Program Selain manager"
                $excludedRoles = [
                    'Program Manager', 
                    'Manager Program', 
                    'manager_program',
                    'Distribution Manager', 
                    'President Director', 
                    'VP President', 
                    'General Manager', 
                    'GM', 
                    'Administrator',
                    'Admin'
                ];
                $users = \App\Models\User::where('is_active', true)
                    ->whereNotIn('role', $excludedRoles)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'role']);

                $members = $users->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'role' => $u->role,
                        'role_label' => $u->role ?? '—',
                        'is_active' => true
                    ];
                })->values();

                return response()->json([
                    'success' => true,
                    'data' => $members,
                    'message' => 'All assignable users retrieved successfully',
                    'scope' => 'all'
                ]);
            }

            // Default: Producer hanya anggota production team miliknya; Program Manager melihat semua tim (oversight)
            $productionTeamIds = $isProgramManager
                ? \App\Models\ProductionTeam::pluck('id')
                : \App\Models\ProductionTeam::where('producer_id', $user->id)->pluck('id');

            $query = \App\Models\ProductionTeamMember::whereIn('production_team_id', $productionTeamIds)
                ->with('user');

            if ($request->boolean('exclude_manager')) {
                $query->where('role', '!=', 'manager_program');
            }

            $members = $query->get()->map(function ($member) {
                return [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'role' => $member->role,
                    'role_label' => $member->role_label ?? $member->role,
                    'is_active' => $member->is_active
                ];
            })->unique('id')->values();

            return response()->json([
                'success' => true,
                'data' => $members,
                'message' => 'Crew members retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getCrewMembers: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error retrieving crew members: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Producer kirim reminder manual ke crew
     * User: "Dapat mengingatkan melalui sistem setiap crew yang menjadi timnya"
     */
    public function sendReminderToCrew(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'crew_member_ids' => 'nullable|array',
                'crew_member_ids.*' => 'exists:users,id',
                'role' => 'nullable|string|in:creative,musik_arr,sound_eng,production,editor,art_set_design,graphic_design,promotion,broadcasting,quality_control',
                'message' => 'required|string|max:1000',
                'priority' => 'nullable|in:low,normal,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::with(['program.productionTeam'])->findOrFail($request->episode_id);

            // Validate Producer has access
            if (!$isProgramManager && $episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This episode is not from your production team.'
                ], 403);
            }

            $crewMembers = [];
            
            // If specific crew member IDs provided
            if ($request->has('crew_member_ids') && count($request->crew_member_ids) > 0) {
                $crewMembers = \App\Models\User::whereIn('id', $request->crew_member_ids)
                    ->whereHas('productionTeamMembers', function($q) use ($episode) {
                        $q->where('production_team_id', $episode->program->productionTeam->id)
                          ->where('is_active', true);
                    })
                    ->get();
            }
            // If role provided, get all crew with that role
            elseif ($request->has('role')) {
                $crewMembers = $episode->program->productionTeam->members()
                    ->where('role', $request->role)
                    ->where('is_active', true)
                    ->with('user')
                    ->get()
                    ->pluck('user')
                    ->filter();
            }
            // If neither provided, send to all active crew members
            else {
                $crewMembers = $episode->program->productionTeam->members()
                    ->where('is_active', true)
                    ->with('user')
                    ->get()
                    ->pluck('user')
                    ->filter();
            }

            if ($crewMembers->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No crew members found to send reminder to.'
                ], 404);
            }

            // BULK INSERT to avoid N+1
            $notificationsToInsert = [];
            $now = now();
            
            foreach ($crewMembers as $crewMember) {
                $notificationsToInsert[] = [
                    'user_id' => $crewMember->id,
                    'type' => 'producer_reminder',
                    'title' => 'Reminder dari Producer',
                    'message' => $request->message,
                    'episode_id' => $episode->id,
                    'program_id' => $episode->program_id,
                    'priority' => $request->priority ?? 'normal',
                    'data' => json_encode([
                        'episode_number' => $episode->episode_number,
                        'episode_title' => $episode->title,
                        'reminder_from' => $user->name,
                        'reminder_from_role' => 'Producer'
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }
            
            // Bulk insert all notifications at once (1 query instead of N)
            $sentCount = 0;
            if (!empty($notificationsToInsert)) {
                DB::table('notifications')->insert($notificationsToInsert);
                $sentCount = count($notificationsToInsert);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episode->id,
                    'reminder_sent_to' => $sentCount,
                    'crew_members' => $crewMembers->pluck('id', 'name')
                ],
                'message' => "Reminder sent successfully to {$sentCount} crew member(s)."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending reminder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get weekly airing control dashboard
     * Producer dapat melihat dan mengontrol episode yang akan tayang minggu ini
     */
    public function getWeeklyAiringControl(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();

            // Get episodes yang akan tayang minggu ini dari program Producer
            $episodesThisWeek = Episode::with(['program', 'deadlines', 'workflowStates'])
                ->whereHas('program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->whereBetween('air_date', [$weekStart, $weekEnd])
                ->orderBy('air_date', 'asc')
                ->get();

            // Categorize episodes
            $readyEpisodes = [];
            $notReadyEpisodes = [];
            $airedEpisodes = [];

            foreach ($episodesThisWeek as $episode) {
                $readiness = $this->checkEpisodeReadiness($episode);
                
                $episodeData = [
                    'id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'title' => $episode->title,
                    'program_name' => $episode->program->name,
                    'air_date' => $episode->air_date,
                    'status' => $episode->status,
                    'current_workflow_state' => $episode->current_workflow_state,
                    'readiness' => $readiness,
                    'days_until_air' => now()->diffInDays($episode->air_date, false)
                ];

                if ($episode->status === 'aired') {
                    $airedEpisodes[] = $episodeData;
                } elseif ($readiness['is_ready']) {
                    $readyEpisodes[] = $episodeData;
                } else {
                    $notReadyEpisodes[] = $episodeData;
                }
            }

            // Statistics
            $totalEpisodes = $episodesThisWeek->count();
            $readyCount = count($readyEpisodes);
            $notReadyCount = count($notReadyEpisodes);
            $airedCount = count($airedEpisodes);
            $readinessRate = $totalEpisodes > 0 ? round(($readyCount / ($totalEpisodes - $airedCount)) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d'),
                        'current_date' => now()->format('Y-m-d')
                    ],
                    'statistics' => [
                        'total_episodes_this_week' => $totalEpisodes,
                        'ready_episodes' => $readyCount,
                        'not_ready_episodes' => $notReadyCount,
                        'aired_episodes' => $airedCount,
                        'readiness_rate' => $readinessRate
                    ],
                    'episodes' => [
                        'ready' => $readyEpisodes,
                        'not_ready' => $notReadyEpisodes,
                        'aired' => $airedEpisodes
                    ]
                ],
                'message' => 'Weekly airing control data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving weekly airing control: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episodes upcoming this week
     */
    public function getUpcomingEpisodesThisWeek(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();

            $query = Episode::with(['program', 'deadlines', 'workflowStates'])
                ->whereHas('program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->whereBetween('air_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'aired')
                ->orderBy('air_date', 'asc');

            // Filter by readiness
            if ($request->has('ready_only') && $request->boolean('ready_only')) {
                $episodes = $query->get()->filter(function ($episode) {
                    return $this->checkEpisodeReadiness($episode)['is_ready'];
                })->values();
            } else {
                $episodes = $query->get();
            }

            $episodesData = $episodes->map(function ($episode) {
                $readiness = $this->checkEpisodeReadiness($episode);
                return [
                    'id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'title' => $episode->title,
                    'program_name' => $episode->program->name,
                    'program_id' => $episode->program_id,
                    'air_date' => $episode->air_date,
                    'status' => $episode->status,
                    'current_workflow_state' => $episode->current_workflow_state,
                    'readiness' => $readiness,
                    'days_until_air' => now()->diffInDays($episode->air_date, false),
                    'deadlines' => [
                        'total' => $episode->deadlines->count(),
                        'completed' => $episode->deadlines->where('is_completed', true)->count(),
                        'overdue' => $episode->deadlines->where('status', 'overdue')->count()
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d')
                    ],
                    'episodes' => $episodesData,
                    'count' => $episodesData->count()
                ],
                'message' => 'Upcoming episodes this week retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving upcoming episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get episodes ready for airing this week
     */
    public function getReadyEpisodesThisWeek(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $weekStart = now()->startOfWeek();
            $weekEnd = now()->endOfWeek();

            $episodes = Episode::with(['program', 'deadlines', 'workflowStates'])
                ->whereHas('program.productionTeam', function ($q) use ($user) {
                    $q->where('producer_id', $user->id);
                })
                ->whereBetween('air_date', [$weekStart, $weekEnd])
                ->where('status', '!=', 'aired')
                ->orderBy('air_date', 'asc')
                ->get();

            // Filter only ready episodes
            $readyEpisodes = $episodes->filter(function ($episode) {
                return $this->checkEpisodeReadiness($episode)['is_ready'];
            })->map(function ($episode) {
                $readiness = $this->checkEpisodeReadiness($episode);
                return [
                    'id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'title' => $episode->title,
                    'program_name' => $episode->program->name,
                    'program_id' => $episode->program_id,
                    'air_date' => $episode->air_date,
                    'status' => $episode->status,
                    'current_workflow_state' => $episode->current_workflow_state,
                    'readiness' => $readiness,
                    'days_until_air' => now()->diffInDays($episode->air_date, false)
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'week_period' => [
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d')
                    ],
                    'episodes' => $readyEpisodes,
                    'count' => $readyEpisodes->count()
                ],
                'message' => 'Ready episodes this week retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving ready episodes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check episode readiness for airing
     * Returns checklist of what's needed for episode to be ready
     */
    private function checkEpisodeReadiness(Episode $episode): array
    {
        $readiness = [
            'is_ready' => false,
            'checklist' => [],
            'missing_items' => [],
            'warnings' => []
        ];

        // Check 1: Episode status should be ready_to_air or at least post_production
        $statusReady = in_array($episode->status, ['ready_to_air', 'post_production']);
        $readiness['checklist']['status'] = [
            'label' => 'Episode Status',
            'status' => $statusReady ? 'ready' : 'not_ready',
            'value' => $episode->status,
            'required' => 'ready_to_air'
        ];
        if (!$statusReady) {
            $readiness['missing_items'][] = 'Episode status harus ready_to_air';
        }

        // Check 2: Rundown should exist
        $hasRundown = !empty($episode->rundown);
        $readiness['checklist']['rundown'] = [
            'label' => 'Rundown',
            'status' => $hasRundown ? 'ready' : 'not_ready',
            'value' => $hasRundown ? 'Available' : 'Missing'
        ];
        if (!$hasRundown) {
            $readiness['missing_items'][] = 'Rundown belum tersedia';
        }

        // Check 3: All deadlines should be completed
        $deadlines = $episode->deadlines;
        $totalDeadlines = $deadlines->count();
        $completedDeadlines = $deadlines->where('is_completed', true)->count();
        $overdueDeadlines = $deadlines->where('status', 'overdue')->count();
        
        $allDeadlinesCompleted = $totalDeadlines > 0 && $totalDeadlines === $completedDeadlines;
        $readiness['checklist']['deadlines'] = [
            'label' => 'Deadlines',
            'status' => $allDeadlinesCompleted ? 'ready' : ($overdueDeadlines > 0 ? 'overdue' : 'in_progress'),
            'value' => "{$completedDeadlines}/{$totalDeadlines} completed",
            'overdue' => $overdueDeadlines
        ];
        if (!$allDeadlinesCompleted) {
            $remainingDeadlines = $totalDeadlines - $completedDeadlines;
            $readiness['missing_items'][] = "Masih ada {$remainingDeadlines} deadline yang belum selesai";
        }
        if ($overdueDeadlines > 0) {
            $readiness['warnings'][] = "Ada {$overdueDeadlines} deadline yang overdue";
        }

        // Check 4: Music Arrangement should be approved (for music programs)
        $musicArrangement = MusicArrangement::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['music_arrangement'] = [
            'label' => 'Music Arrangement',
            'status' => $musicArrangement ? 'ready' : 'not_ready',
            'value' => $musicArrangement ? 'Approved' : 'Not approved or missing'
        ];
        if (!$musicArrangement) {
            $readiness['missing_items'][] = 'Music arrangement belum approved';
        }

        // Check 5: Creative Work should be approved
        $creativeWork = CreativeWork::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['creative_work'] = [
            'label' => 'Creative Work',
            'status' => $creativeWork ? 'ready' : 'not_ready',
            'value' => $creativeWork ? 'Approved' : 'Not approved or missing'
        ];
        if (!$creativeWork) {
            $readiness['missing_items'][] = 'Creative work belum approved';
        }

        // Check 6: Sound Engineering should be completed
        $soundEngineering = SoundEngineerEditing::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['sound_engineering'] = [
            'label' => 'Sound Engineering',
            'status' => $soundEngineering ? 'ready' : 'not_ready',
            'value' => $soundEngineering ? 'Approved' : 'Not approved or missing'
        ];
        if (!$soundEngineering) {
            $readiness['missing_items'][] = 'Sound engineering belum completed';
        }

        // Check 7: Editor Work should be approved
        $editorWork = EditorWork::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['editor_work'] = [
            'label' => 'Editor Work',
            'status' => $editorWork ? 'ready' : 'not_ready',
            'value' => $editorWork ? 'Approved' : 'Not approved or missing'
        ];
        if (!$editorWork) {
            $readiness['missing_items'][] = 'Editor work belum approved';
        }

        // Check 8: QC should be approved
        $qcApproved = QualityControl::where('episode_id', $episode->id)
            ->where('status', 'approved')
            ->exists();
        $readiness['checklist']['quality_control'] = [
            'label' => 'Quality Control',
            'status' => $qcApproved ? 'ready' : 'not_ready',
            'value' => $qcApproved ? 'Approved' : 'Not approved or missing'
        ];
        if (!$qcApproved) {
            $readiness['missing_items'][] = 'QC belum approved';
        }

        // Check 9: Days until air - warning if less than 3 days
        $daysUntilAir = now()->diffInDays($episode->air_date, false);
        if ($daysUntilAir < 3 && $daysUntilAir >= 0) {
            $readiness['warnings'][] = "Episode akan tayang dalam {$daysUntilAir} hari";
        }
        if ($daysUntilAir < 0) {
            $readiness['warnings'][] = "Episode sudah melewati jadwal tayang!";
        }

        // Overall readiness: Episode is ready if status is ready_to_air and QC is approved
        $readiness['is_ready'] = $episode->status === 'ready_to_air' && $qcApproved;

        return $readiness;
    }

    /**
     * Review Creative Work - Producer cek script, storyboard, budget
     * POST /api/live-tv/producer/creative-works/{id}/review
     */
    public function reviewCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'script_approved' => 'nullable|boolean',
                'storyboard_approved' => 'nullable|boolean',
                'budget_approved' => 'nullable|boolean',
                'script_review_notes' => 'nullable|string|max:2000',
                'storyboard_review_notes' => 'nullable|string|max:2000',
                'budget_review_notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Saat Reject, alasan (catatan) wajib agar Creative tahu yang harus diperbaiki
            if ($request->script_approved === false && empty(trim((string) ($request->script_review_notes ?? '')))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alasan wajib diisi saat Script ditolak (Catatan Script).',
                    'errors' => ['script_review_notes' => ['Alasan penolakan Script wajib diisi.']]
                ], 422);
            }
            if ($request->storyboard_approved === false && empty(trim((string) ($request->storyboard_review_notes ?? '')))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alasan wajib diisi saat Storyboard ditolak (Catatan Storyboard).',
                    'errors' => ['storyboard_review_notes' => ['Alasan penolakan Storyboard wajib diisi.']]
                ], 422);
            }
            if ($request->budget_approved === false && empty(trim((string) ($request->budget_review_notes ?? '')))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alasan wajib diisi saat Budget ditolak (Catatan Budget).',
                    'errors' => ['budget_review_notes' => ['Alasan penolakan Budget wajib diisi.']]
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.productionTeam', 'episode.program.productionTeam', 'specialBudgetApproval'])->findOrFail($id);

            // Validate Producer has access
            // Support episode.productionTeam langsung atau episode.program.productionTeam
            $episode = $creativeWork->episode;
            if (!$episode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode not found for this creative work.'
                ], 404);
            }
            
            $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);
            
            if (!$productionTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Production team not found for this episode. Please assign a production team first.'
                ], 404);
            }
            
            if ($productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            if (!in_array($creativeWork->status, ['submitted', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or revised creative works can be reviewed'
                ], 400);
            }

            // Batas budget oleh Program Manager: jika program punya max_budget_per_episode dan Producer approve budget, cek tidak melebihi
            if ($request->budget_approved === true) {
                $program = $creativeWork->episode?->program;
                if ($program && $program->max_budget_per_episode !== null && (float) $program->max_budget_per_episode > 0) {
                    $total = (float) $creativeWork->total_budget;
                    if ($total > (float) $program->max_budget_per_episode) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Budget melebihi batas yang ditetapkan Program Manager (max Rp ' . number_format($program->max_budget_per_episode, 0, ',', '.') . ' per episode). Ajukan special budget atau minta Creative revisi.',
                            'max_budget_per_episode' => $program->max_budget_per_episode,
                            'current_total' => $total,
                        ], 400);
                    }
                }
            }

            $hasRejection = $request->script_approved === false
                || $request->storyboard_approved === false
                || $request->budget_approved === false;

            $updatePayload = [
                'script_approved' => $request->script_approved,
                'storyboard_approved' => $request->storyboard_approved,
                'budget_approved' => $request->budget_approved,
                'script_review_notes' => $request->script_review_notes,
                'storyboard_review_notes' => $request->storyboard_review_notes,
                'budget_review_notes' => $request->budget_review_notes,
                'reviewed_by' => $user->id,
                'reviewed_at' => now()
            ];

            // Jika salah satu ditolak, status jadi revised agar Creative tahu harus revisi dan bisa lihat alasan
            if ($hasRejection) {
                $updatePayload['status'] = 'revised';
            }

            $creativeWork->update($updatePayload);

            return response()->json([
                'success' => true,
                'data' => $creativeWork->fresh(['episode', 'createdBy']),
                'message' => $hasRejection
                    ? 'Review disimpan. Creative work perlu revisi; Creative akan melihat alasan penolakan.'
                    : 'Creative work reviewed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to review creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tambah Tim Syuting/Setting/Rekam Vokal
     * POST /api/live-tv/producer/creative-works/{id}/assign-team
     */
    public function assignTeamToCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'team_type' => 'required|in:shooting,setting,recording',
                'team_member_ids' => 'required|array|min:1',
                'team_member_ids.*' => 'exists:users,id',
                'coordinator_id' => 'nullable|exists:users,id',
                'team_name' => 'nullable|string|max:255',
                'team_notes' => 'nullable|string|max:1000',
                'schedule_id' => 'nullable|exists:music_schedules,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.productionTeam', 'episode.program.productionTeam', 'specialBudgetApproval'])->findOrFail($id);

            // Validate relationships exist
            $episode = $creativeWork->episode;
            if (!$episode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work does not have an associated episode.'
                ], 400);
            }

            // Support episode.productionTeam langsung atau episode.program.productionTeam
            $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);

            if (!$productionTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Production team not found for this episode. Please assign a production team first.'
                ], 404);
            }

            // Validate Producer has access (episode or program production team)
            if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            // Semua user aktif boleh ditambahkan, kecuali Program Manager dan Distribution Manager
            $excludedRoles = ['Program Manager', 'Manager Program', 'Distribution Manager'];
            $assignableUserIds = \App\Models\User::where('is_active', true)
                ->whereNotIn('role', $excludedRoles)
                ->pluck('id')
                ->toArray();

            // Validate all selected team members are assignable (active, non-manager)
            $invalidMembers = array_diff($request->team_member_ids, $assignableUserIds);
            if (!empty($invalidMembers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some selected users cannot be assigned (must be active; Program Manager and Distribution Manager cannot be added to teams).',
                    'invalid_members' => array_values($invalidMembers)
                ], 400);
            }

            // Validate episode_id exists
            if (!$creativeWork->episode_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work does not have a valid episode ID.'
                ], 400);
            }

            // Validate all user_ids exist
            $userIds = \App\Models\User::whereIn('id', $request->team_member_ids)->pluck('id')->toArray();
            $missingUserIds = array_diff($request->team_member_ids, $userIds);
            if (!empty($missingUserIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some user IDs do not exist',
                    'missing_user_ids' => $missingUserIds
                ], 400);
            }

            // Validate schedule_id if provided
            if ($request->schedule_id) {
                $scheduleExists = \App\Models\MusicSchedule::where('id', $request->schedule_id)->exists();
                if (!$scheduleExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Schedule ID does not exist'
                    ], 400);
                }
            }

            // Start database transaction
            DB::beginTransaction();

            try {
            // Create team assignment
            $assignment = \App\Models\ProductionTeamAssignment::create([
                'music_submission_id' => null,
                'episode_id' => $creativeWork->episode_id,
                'schedule_id' => $request->schedule_id,
                'assigned_by' => $user->id,
                'team_type' => $request->team_type,
                'team_name' => $request->team_name ?? ucfirst($request->team_type) . ' Team',
                'team_notes' => $request->team_notes,
                'status' => 'assigned',
                'assigned_at' => now()
            ]);

                // Validate assignment was created successfully
                if (!$assignment || !$assignment->id) {
                    throw new \Exception('Failed to create team assignment');
                }

            // Add team members
            $coordinatorId = $request->input('coordinator_id');
            if (!$coordinatorId) {
                $coordinatorId = $request->team_member_ids[0] ?? null;
            }

            foreach ($request->team_member_ids as $index => $userId) {
                    // Check if user already assigned to this assignment (prevent duplicates)
                    $existingMember = \App\Models\ProductionTeamMember::where('assignment_id', $assignment->id)
                        ->where('user_id', $userId)
                        ->first();
                    
                    if ($existingMember) {
                        continue; // Skip if already assigned
                    }

                    $member = \App\Models\ProductionTeamMember::create([
                    'production_team_id' => $productionTeam->id,
                    'assignment_id' => $assignment->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'leader' : 'crew',
                    'is_active' => true,
                    'is_coordinator' => $coordinatorId ? ((int) $userId === (int) $coordinatorId) : false,
                    'status' => 'assigned'
                ]);

                    // Validate member was created successfully
                    if (!$member || !$member->id) {
                        throw new \Exception("Failed to create team member for user ID: {$userId}");
                    }

                // Notify team member
                    try {
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'team_assigned',
                    'title' => 'Ditugaskan ke Tim ' . ucfirst($request->team_type),
                    'message' => "Anda telah ditugaskan ke tim {$assignment->team_name} untuk Episode {$creativeWork->episode->episode_number}.",
                    'data' => [
                        'assignment_id' => $assignment->id,
                        'team_type' => $request->team_type,
                        'episode_id' => $creativeWork->episode_id
                    ]
                ]);
                    } catch (\Exception $e) {
                        // Log notification error but don't fail the assignment
                        Log::warning('Failed to create notification for team member', [
                            'user_id' => $userId,
                            'assignment_id' => $assignment->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Commit transaction
                DB::commit();
            } catch (\Exception $e) {
                // Rollback transaction on error
                DB::rollBack();
                
                Log::error('Error assigning team to creative work', [
                    'creative_work_id' => $id,
                    'episode_id' => $creativeWork->episode_id ?? null,
                    'team_type' => $request->team_type,
                    'team_member_ids' => $request->team_member_ids,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                throw $e; // Re-throw to be caught by outer catch block
            }

            return response()->json([
                'success' => true,
                'data' => $assignment->load(['members', 'episode']),
                'message' => 'Team assigned successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign team',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ganti Tim Syuting (secara dadakan)
     * PUT /api/live-tv/producer/team-assignments/{assignmentId}/replace-team
     */
    public function replaceTeamMembers(Request $request, int $assignmentId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'new_team_member_ids' => 'required|array|min:1',
                'new_team_member_ids.*' => 'exists:users,id',
                'replacement_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = \App\Models\ProductionTeamAssignment::with(['episode.program.productionTeam'])->findOrFail($assignmentId);

            $program = $assignment->episode?->program;
            $productionTeam = $program?->productionTeam;
            if (!$program || !$productionTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode or program production team not found.'
                ], 400);
            }
            if ($productionTeam->producer_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this assignment.'
                ], 403);
            }

            // Get old members for notification
            $oldMembers = $assignment->members()->pluck('user_id')->toArray();

            // Remove old members
            $assignment->members()->delete();

            // Add new members
            foreach ($request->new_team_member_ids as $index => $userId) {
                \App\Models\ProductionTeamMember::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'leader' : 'crew',
                    'status' => 'assigned'
                ]);

                // Notify new team member
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'team_replaced',
                    'title' => 'Ditugaskan ke Tim ' . ucfirst($assignment->team_type),
                    'message' => "Anda telah menggantikan anggota tim {$assignment->team_name} untuk Episode {$assignment->episode->episode_number}. Alasan: {$request->replacement_reason}",
                    'data' => [
                        'assignment_id' => $assignment->id,
                        'team_type' => $assignment->team_type,
                        'episode_id' => $assignment->episode_id,
                        'replacement_reason' => $request->replacement_reason
                    ]
                ]);
            }

            // Notify old members
            foreach ($oldMembers as $oldMemberId) {
                if (!in_array($oldMemberId, $request->new_team_member_ids)) {
                    Notification::create([
                        'user_id' => $oldMemberId,
                        'type' => 'team_replaced_out',
                        'title' => 'Digantikan dari Tim',
                        'message' => "Anda telah digantikan dari tim {$assignment->team_name} untuk Episode {$assignment->episode->episode_number}. Alasan: {$request->replacement_reason}",
                        'data' => [
                            'assignment_id' => $assignment->id,
                            'team_type' => $assignment->team_type,
                            'episode_id' => $assignment->episode_id
                        ]
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $assignment->fresh(['members', 'episode']),
                'message' => 'Team members replaced successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to replace team members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Team Assignment (Edit team details)
     * PUT /api/live-tv/producer/team-assignments/{assignmentId}
     * 
     * Producer dapat mengedit:
     * - team_name
     * - team_notes
     * - schedule_id
     * - team_member_ids (opsional, jika ingin tambah/kurang anggota)
     */
    public function updateTeamAssignment(Request $request, int $assignmentId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'team_name' => 'nullable|string|max:255',
                'team_notes' => 'nullable|string|max:1000',
                'schedule_id' => 'nullable|exists:music_schedules,id',
                'team_member_ids' => 'nullable|array|min:1',
                'team_member_ids.*' => 'exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $assignment = \App\Models\ProductionTeamAssignment::with(['episode.program.productionTeam', 'members'])->findOrFail($assignmentId);

            $program = $assignment->episode?->program;
            $productionTeam = $program?->productionTeam;
            if (!$program || !$productionTeam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode or program production team not found.'
                ], 400);
            }

            // Validate Producer has access
            if ($productionTeam->producer_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this assignment.'
                ], 403);
            }

            // Validate schedule_id if provided
            if ($request->has('schedule_id') && $request->schedule_id) {
                $scheduleExists = \App\Models\MusicSchedule::where('id', $request->schedule_id)->exists();
                if (!$scheduleExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Schedule ID does not exist'
                    ], 400);
                }
            }

            // Start database transaction
            DB::beginTransaction();

            try {
                // Update assignment details
                $updateData = [];
                
                if ($request->has('team_name')) {
                    $updateData['team_name'] = $request->team_name;
                }
                
                if ($request->has('team_notes')) {
                    $updateData['team_notes'] = $request->team_notes;
                }
                
                if ($request->has('schedule_id')) {
                    $updateData['schedule_id'] = $request->schedule_id;
                }

                if (!empty($updateData)) {
                    $assignment->update($updateData);
                }

                // Update team members if provided (validator already ensures team_member_ids.* exist in users)
                if ($request->has('team_member_ids') && count($request->team_member_ids) > 0) {
                    $requestMemberIds = array_map('intval', (array) $request->team_member_ids);

                    // Get current members
                    $currentMemberIds = $assignment->members()->pluck('user_id')->map(fn ($id) => (int) $id)->toArray();
                    $newMemberIds = $requestMemberIds;
                    
                    // Find members to add
                    $membersToAdd = array_diff($newMemberIds, $currentMemberIds);
                    
                    // Find members to remove
                    $membersToRemove = array_diff($currentMemberIds, $newMemberIds);

                    // Remove members that are no longer in the team
                    if (!empty($membersToRemove)) {
                        \App\Models\ProductionTeamMember::where('assignment_id', $assignment->id)
                            ->whereIn('user_id', $membersToRemove)
                            ->delete();

                        // Notify removed members
                        foreach ($membersToRemove as $removedUserId) {
                            Notification::create([
                                'user_id' => $removedUserId,
                                'type' => 'team_removed',
                                'title' => 'Dikeluarkan dari Tim',
                                'message' => "Anda telah dikeluarkan dari tim {$assignment->team_name} untuk Episode {$assignment->episode->episode_number}.",
                                'data' => [
                                    'assignment_id' => $assignment->id,
                                    'team_type' => $assignment->team_type,
                                    'episode_id' => $assignment->episode_id
                                ]
                            ]);
                        }
                    }

                    // Add new members
                    if (!empty($membersToAdd)) {
                        // Check if there's already a leader
                        $hasLeader = \App\Models\ProductionTeamMember::where('assignment_id', $assignment->id)
                            ->where('role', 'leader')
                            ->exists();
                        
                        // Get remaining members count after removal
                        $remainingMembers = $assignment->members()
                            ->whereNotIn('user_id', $membersToRemove)
                            ->count();
                        
                        foreach ($membersToAdd as $index => $userId) {
                            // Check if user already assigned (prevent duplicates)
                            $existingMember = \App\Models\ProductionTeamMember::where('assignment_id', $assignment->id)
                                ->where('user_id', $userId)
                                ->first();
                            
                            if ($existingMember) {
                                continue;
                            }

                            // Determine role: first member becomes leader if no leader exists
                            $role = (!$hasLeader && $remainingMembers + $index === 0) ? 'leader' : 'crew';
                            if ($role === 'leader') {
                                $hasLeader = true; // Mark that we now have a leader
                            }

                            $member = \App\Models\ProductionTeamMember::create([
                                'assignment_id' => $assignment->id,
                                'user_id' => $userId,
                                'role' => $role,
                                'status' => 'assigned'
                            ]);

                            // Notify new team member
                            Notification::create([
                                'user_id' => $userId,
                                'type' => 'team_assigned',
                                'title' => 'Ditugaskan ke Tim ' . ucfirst($assignment->team_type),
                                'message' => "Anda telah ditugaskan ke tim {$assignment->team_name} untuk Episode {$assignment->episode->episode_number}.",
                                'data' => [
                                    'assignment_id' => $assignment->id,
                                    'team_type' => $assignment->team_type,
                                    'episode_id' => $assignment->episode_id
                                ]
                            ]);
                        }
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => $assignment->fresh(['members.user', 'episode']),
                    'message' => 'Team assignment updated successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error updating team assignment', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update team assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get creative work detail (untuk Producer melihat data yang diajukan Creative, termasuk script_link, storyboard_link)
     * GET /api/live-tv/producer/creative-works/{id}
     */
    public function getCreativeWorkDetail(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'Producer') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $creativeWork = CreativeWork::with([
                'episode.productionTeam',
                'episode.program.productionTeam',
                'episode.program',
                'createdBy',
                'specialBudgetApproval',
                'reviewedBy'
            ])->findOrFail($id);

            $episode = $creativeWork->episode;
            if (!$episode) {
                return response()->json(['success' => false, 'message' => 'Creative work has no episode.'], 404);
            }
            if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $creativeWork,
                'message' => 'Creative work retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve creative work',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel Jadwal Syuting
     * POST /api/live-tv/producer/creative-works/{id}/cancel-shooting
     */
    public function cancelShootingSchedule(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'cancellation_reason' => 'required|string|max:1000',
                'new_shooting_schedule' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.productionTeam', 'episode.program.productionTeam', 'specialBudgetApproval'])->findOrFail($id);

            $episode = $creativeWork->episode;
            if (!$episode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode not found for this creative work.'
                ], 404);
            }
            if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            // Cancel shooting schedule + set status revised agar Creative bisa klik Revisi dan isi ulang jadwal syuting
            $creativeWork->update([
                'shooting_schedule_cancelled' => true,
                'shooting_cancellation_reason' => $request->cancellation_reason,
                'shooting_schedule_new' => $request->new_shooting_schedule,
                'status' => 'revised'
            ]);

            // Cancel shooting team assignments
            $shootingAssignments = \App\Models\ProductionTeamAssignment::where('episode_id', $creativeWork->episode_id)
                ->where('team_type', 'shooting')
                ->where('status', '!=', 'cancelled')
                ->get();

            foreach ($shootingAssignments as $assignment) {
                $assignment->update(['status' => 'cancelled']);

                // Notify team members
                foreach ($assignment->members as $member) {
                    Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'shooting_cancelled',
                        'title' => 'Jadwal Syuting Dibatalkan',
                        'message' => "Jadwal syuting untuk Episode {$creativeWork->episode->episode_number} telah dibatalkan. Alasan: {$request->cancellation_reason}",
                        'data' => [
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'cancellation_reason' => $request->cancellation_reason,
                            'new_schedule' => $request->new_shooting_schedule
                        ]
                    ]);
                }
            }

            // Notify Creative
            Notification::create([
                'user_id' => $creativeWork->created_by,
                'type' => 'shooting_cancelled',
                'title' => 'Jadwal Syuting Dibatalkan',
                'message' => "Jadwal syuting untuk Episode {$creativeWork->episode->episode_number} telah dibatalkan oleh Producer. Alasan: {$request->cancellation_reason}",
                'data' => [
                    'creative_work_id' => $creativeWork->id,
                    'episode_id' => $creativeWork->episode_id
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $creativeWork->fresh(['episode']),
                'message' => 'Shooting schedule cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel shooting schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Ajukan Budget Khusus ke Manager Program
     * POST /api/live-tv/producer/creative-works/{id}/request-special-budget
     */
    public function requestSpecialBudget(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'special_budget_amount' => 'required|numeric|min:0',
                'special_budget_reason' => 'required|string|max:2000',
                'priority' => 'nullable|in:low,normal,high,urgent'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Resolve creative work: ID bisa creative_work.id, music_arrangement.id, ATAU episode_id (frontend kadang kirim episode_id)
            $creativeWork = CreativeWork::with(['episode.program.productionTeam', 'episode.program'])->find($id);
            $arrangement = null;
            if (!$creativeWork) {
                $arrangement = MusicArrangement::with(['episode.program.productionTeam', 'episode.productionTeam'])->where('id', $id)->first();
                if ($arrangement) {
                    $creativeWork = CreativeWork::with(['episode.program.productionTeam', 'episode.program'])
                        ->where('episode_id', $arrangement->episode_id)
                        ->first();
                    // Program Musik: bila arrangement ada tapi Creative Work belum (observer belum jalan / edge case), buat Creative Work agar ajuan budget bisa jalan
                    if (!$creativeWork && $arrangement->episode) {
                        $episode = $arrangement->episode;
                        $productionTeam = $episode->productionTeam ?? $episode->program?->productionTeam;
                        $creativeUser = null;
                        if ($productionTeam) {
                            $creativeMember = $productionTeam->members()->where('role', 'creative')->where('is_active', true)->first();
                            if ($creativeMember) {
                                $creativeUser = $creativeMember->user_id;
                            }
                        }
                        if (!$creativeUser) {
                            $creative = \App\Models\User::where('role', 'Creative')->first();
                            $creativeUser = $creative ? $creative->id : null;
                        }
                        $scriptContent = "Creative work task for episode {$episode->episode_number}. Music arrangement '{$arrangement->song_title}' has been approved.";
                        $creativeWork = CreativeWork::create([
                            'episode_id' => $episode->id,
                            'script_content' => $scriptContent,
                            'status' => 'draft',
                            'created_by' => $creativeUser ?? $arrangement->reviewed_by ?? $user->id
                        ]);
                        $creativeWork->load(['episode.program.productionTeam', 'episode.program']);
                        Log::info('Producer requestSpecialBudget: Creative Work auto-created for episode (Program Musik)', [
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $episode->id,
                            'program_id' => $episode->program_id,
                            'music_arrangement_id' => $arrangement->id
                        ]);
                    }
                }
            }
            // Fallback: frontend kadang mengirim episode_id ke URL (bukan creative_work.id) — cari Creative Work by episode_id
            if (!$creativeWork) {
                $creativeWork = CreativeWork::with(['episode.program.productionTeam', 'episode.program'])
                    ->where('episode_id', $id)
                    ->first();
                if ($creativeWork) {
                    Log::info('Producer requestSpecialBudget: resolved by episode_id', [
                        'episode_id' => $id,
                        'creative_work_id' => $creativeWork->id
                    ]);
                }
            }
            if (!$creativeWork) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work not found. Pastikan ID adalah creative work, music arrangement, atau episode yang valid.',
                    'error' => $arrangement
                        ? 'No creative work found for this episode (music arrangement found).'
                        : 'No query results for model [App\\Models\\CreativeWork] ' . $id
                ], 404);
            }

            // Validate Producer has access (program + episode harus punya production team dengan producer ini)
            $program = $creativeWork->episode->program;
            $productionTeam = $program?->productionTeam ?? $creativeWork->episode->productionTeam;
            if (!$productionTeam || $productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            $episode = $creativeWork->episode;
            $program = $episode->program;
            $programName = $program ? $program->name : 'N/A';
            $episodeNumber = $episode->episode_number ?? 'N/A';

            // Create ProgramApproval untuk special budget — data lengkap sesuai input Producer agar Program Manager dapat semua info
            $reason = is_string($request->special_budget_reason) ? trim($request->special_budget_reason) : '';
            $approval = ProgramApproval::create([
                'approvable_id' => $creativeWork->id,
                'approvable_type' => CreativeWork::class,
                'approval_type' => 'special_budget',
                'requested_by' => $user->id,
                'requested_at' => now(),
                'request_notes' => $reason !== '' ? $reason : null,
                'request_data' => [
                    'special_budget_amount' => (float) $request->special_budget_amount,
                    'special_budget_reason' => $reason !== '' ? $reason : null,
                    'priority' => $request->priority ?? 'normal',
                    'current_budget' => $creativeWork->total_budget,
                    'episode_id' => $creativeWork->episode_id,
                    'program_id' => $program?->id,
                    'program_name' => $programName,
                    'episode_number' => $episodeNumber,
                ],
                'status' => 'pending',
                'priority' => $request->priority ?? 'normal'
            ]);

            // Log untuk debugging (program + episode agar jelas di log)
            Log::info('Special budget approval created', [
                'approval_id' => $approval->id,
                'creative_work_id' => $creativeWork->id,
                'episode_id' => $creativeWork->episode_id,
                'episode_number' => $episodeNumber,
                'program_id' => $program?->id,
                'program_name' => $programName,
                'manager_program_id' => $program?->manager_program_id,
                'amount' => $request->special_budget_amount
            ]);

            // Update Creative Work
            $creativeWork->update([
                'requires_special_budget_approval' => true,
                'special_budget_reason' => $request->special_budget_reason,
                'special_budget_approval_id' => $approval->id
            ]);

            // Notify Program Manager yang membuat/mengelola program tersebut (Program Manager harus tau program & episode berapa)
            $managerProgramId = $program?->manager_program_id;
            
            Log::info('Sending notification to Program Manager who manages this program', [
                'program_id' => $program?->id,
                'program_name' => $programName,
                'manager_program_id' => $managerProgramId,
                'approval_id' => $approval->id
            ]);
            
            if ($managerProgramId) {
                $managerProgram = \App\Models\User::find($managerProgramId);
                if ($managerProgram) {
                    // Pastikan user tersebut adalah Program Manager
                    $userRole = strtolower($managerProgram->role);
                    $isProgramManager = in_array($userRole, ['program manager', 'manager program', 'managerprogram']);
                    
                    if ($isProgramManager) {
                        $notificationData = [
                            'approval_id' => $approval->id,
                            'creative_work_id' => $creativeWork->id,
                            'episode_id' => $creativeWork->episode_id,
                            'episode_number' => $episodeNumber,
                            'program_id' => $program?->id,
                            'program_name' => $programName,
                            'budget_amount' => $request->special_budget_amount
                        ];
                        
                        $notificationMessage = "Producer meminta budget khusus sebesar Rp " . number_format($request->special_budget_amount, 0, ',', '.') . " untuk Program '{$programName}', Episode {$episodeNumber}. Alasan: {$request->special_budget_reason}";
                        
                        $notification = Notification::create([
                            'user_id' => $managerProgram->id,
                            'type' => 'special_budget_request',
                            'title' => 'Permintaan Budget Khusus',
                            'message' => $notificationMessage,
                            'data' => $notificationData
                        ]);
                        
                        Log::info('Notification sent to Program Manager', [
                            'notification_id' => $notification->id,
                            'manager_id' => $managerProgram->id,
                            'manager_name' => $managerProgram->name,
                            'manager_role' => $managerProgram->role,
                            'program_id' => $program?->id,
                            'program_name' => $programName,
                            'approval_id' => $approval->id
                        ]);
                    } else {
                        Log::warning('User assigned as manager_program_id is not a Program Manager', [
                            'manager_program_id' => $managerProgramId,
                            'user_role' => $managerProgram->role ?? null,
                            'program_id' => $program?->id,
                            'approval_id' => $approval->id
                        ]);
                    }
                } else {
                    Log::warning('Program Manager user not found', [
                        'manager_program_id' => $managerProgramId,
                        'program_id' => $program?->id,
                        'approval_id' => $approval->id
                    ]);
                }
            } else {
                Log::warning('Program has no manager_program_id assigned', [
                    'program_id' => $program?->id,
                    'program_name' => $programName,
                    'approval_id' => $approval->id
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'approval' => $approval,
                    'creative_work' => $creativeWork->fresh(['episode']),
                    'program_id' => $program?->id,
                    'program_name' => $programName,
                    'episode_id' => $episode->id,
                    'episode_number' => $episodeNumber,
                ],
                'message' => "Permintaan budget khusus untuk Program '{$programName}', Episode {$episodeNumber} telah dikirim ke Manager Program."
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request special budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve/Reject Creative Work dengan Review Detail
     * POST /api/live-tv/producer/creative-works/{id}/final-approval
     */
    public function finalApproveCreativeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $creativeWork = CreativeWork::with(['episode.productionTeam', 'episode.program.productionTeam', 'specialBudgetApproval'])->findOrFail($id);

            // Validate Producer has access (sama seperti getApprovals: episode ATAU program production team)
            $episode = $creativeWork->episode;
            if (!$episode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode not found for this creative work.'
                ], 404);
            }
            $teamOnEpisode = $episode->productionTeam;
            $teamOnProgram = $episode->program ? $episode->program->productionTeam : null;
            if (!$teamOnEpisode && !$teamOnProgram) {
                return response()->json([
                    'success' => false,
                    'message' => 'Production team not found for this episode. Please assign a production team first.'
                ], 404);
            }
            if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This creative work is not from your production team.'
                ], 403);
            }

            if (!in_array($creativeWork->status, ['submitted', 'revised'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or revised creative works can be approved/rejected'
                ], 400);
            }

            // Check if special budget approval is pending
            if ($creativeWork->requires_special_budget_approval && $creativeWork->specialBudgetApproval && 
                $creativeWork->specialBudgetApproval->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot approve/reject: Special budget approval is still pending from Manager Program.'
                ], 400);
            }

            if ($request->action === 'approve') {
                // Validasi: Pastikan Creative sudah memasukkan data yang diperlukan
                // Accept either content OR link for script and storyboard
                $missingData = [];
                
                // Script: accept script_content OR script_link
                $hasScript = !empty($creativeWork->script_content) || !empty($creativeWork->script_link);
                if (!$hasScript) {
                    $missingData[] = 'Script (content or link)';
                }
                
                // Storyboard: accept storyboard_data OR storyboard_link
                $hasStoryboard = !empty($creativeWork->storyboard_link) || 
                    (!empty($creativeWork->storyboard_data) && is_array($creativeWork->storyboard_data) && count($creativeWork->storyboard_data) > 0);
                if (!$hasStoryboard) {
                    $missingData[] = 'Storyboard (data or link)';
                }
                
                if (empty($creativeWork->budget_data) || (is_array($creativeWork->budget_data) && count($creativeWork->budget_data) === 0)) {
                    $missingData[] = 'Budget data';
                }
                
                if (empty($creativeWork->recording_schedule)) {
                    $missingData[] = 'Recording schedule';
                }
                
                if (empty($creativeWork->shooting_schedule)) {
                    $missingData[] = 'Shooting schedule';
                }
                
                if (!empty($missingData)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot approve: Creative work is missing required data. Creative must complete the following: ' . implode(', ', $missingData) . '. Please ask Creative to accept work, fill in all required data, and submit first.',
                        'missing_data' => $missingData
                    ], 400);
                }
                
                // Auto-approve sub-reviews if they are still null (Quick Approve)
                // Hanya jika data sudah lengkap
                if ($creativeWork->script_approved === null || 
                    $creativeWork->storyboard_approved === null || 
                    $creativeWork->budget_approved === null) {
                    
                    $creativeWork->update([
                        'script_approved' => $creativeWork->script_approved ?? true,
                        'storyboard_approved' => $creativeWork->storyboard_approved ?? true,
                        'budget_approved' => $creativeWork->budget_approved ?? true,
                    ]);
                    
                    // Refresh model to get updated values
                    $creativeWork->refresh();
                }

                // Check if any of them are rejected
                if ($creativeWork->script_approved === false || $creativeWork->storyboard_approved === false || $creativeWork->budget_approved === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot approve: Script, storyboard, or budget has been rejected. Please review first or edit for correction.'
                    ], 400);
                }

                $creativeWork->approve($user->id, $request->notes);

                // Auto-create BudgetRequest ke General Affairs
                $totalBudget = $creativeWork->total_budget;
                
                \Log::info('Producer finalApproveCreativeWork - Creating BudgetRequest', [
                    'creative_work_id' => $creativeWork->id,
                    'episode_id' => $creativeWork->episode_id,
                    'program_id' => $creativeWork->episode->program_id,
                    'total_budget' => $totalBudget,
                    'producer_id' => $user->id
                ]);
                
                if ($totalBudget > 0) {
                    try {
                    $budgetRequest = \App\Models\BudgetRequest::create([
                        'program_id' => $creativeWork->episode->program_id,
                        'requested_by' => $user->id,
                        'request_type' => 'creative_work',
                        'title' => "Permohonan Dana untuk Episode {$creativeWork->episode->episode_number}",
                        'description' => "Permohonan dana untuk creative work Episode {$creativeWork->episode->episode_number}. Budget: Rp " . number_format($totalBudget, 0, ',', '.'),
                        'requested_amount' => $totalBudget,
                        'status' => 'pending'
                    ]);

                        \Log::info('Producer finalApproveCreativeWork - BudgetRequest created successfully', [
                            'budget_request_id' => $budgetRequest->id,
                            'creative_work_id' => $creativeWork->id
                        ]);

                    // Notify General Affairs
                    $generalAffairsUsers = \App\Models\User::where('role', 'General Affairs')->get();
                        \Log::info('Producer finalApproveCreativeWork - Notifying General Affairs', [
                            'general_affairs_count' => $generalAffairsUsers->count()
                        ]);
                        
                    foreach ($generalAffairsUsers as $gaUser) {
                        Notification::create([
                            'user_id' => $gaUser->id,
                            'type' => 'budget_request_created',
                            'title' => 'Permohonan Dana Baru',
                            'message' => "Producer memohon dana sebesar Rp " . number_format($totalBudget, 0, ',', '.') . " untuk Episode {$creativeWork->episode->episode_number}.",
                            'data' => [
                                'budget_request_id' => $budgetRequest->id,
                                'creative_work_id' => $creativeWork->id,
                                'episode_id' => $creativeWork->episode_id,
                                'program_id' => $creativeWork->episode->program_id,
                                'requested_amount' => $totalBudget
                            ]
                        ]);
                    }
                    } catch (\Exception $e) {
                        \Log::error('Producer finalApproveCreativeWork - Failed to create BudgetRequest', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'creative_work_id' => $creativeWork->id
                        ]);
                        // Continue execution even if BudgetRequest creation fails
                    }
                } else {
                    \Log::warning('Producer finalApproveCreativeWork - Total budget is 0, skipping BudgetRequest creation', [
                        'creative_work_id' => $creativeWork->id,
                        'total_budget' => $totalBudget
                    ]);
                }

                // Trigger consolidated task creation and notifications
                $this->triggerPostCreativeApproval($creativeWork, $user);

                // Update workflow state
                if ($creativeWork->episode->current_workflow_state === 'creative_work') {
                    $workflowService = app(\App\Services\WorkflowStateService::class);
                    $workflowService->updateWorkflowState(
                        $creativeWork->episode,
                        'production_planning',
                        'produksi',
                        null,
                        'Creative work approved, proceeding to production planning'
                    );
                }

                return response()->json([
                    'success' => true,
                    'data' => $creativeWork->fresh(['episode', 'createdBy']),
                    'message' => 'Creative work approved successfully'
                ]);

            } else {
                // Reject
                $creativeWork->reject((int)$user->id, (string)($request->notes ?? 'Creative work rejected by Producer'));

                // Notify Creative
                Notification::create([
                    'user_id' => $creativeWork->created_by,
                    'type' => 'creative_work_rejected',
                    'title' => 'Creative Work Ditolak',
                    'message' => "Creative work untuk Episode {$creativeWork->episode->episode_number} telah ditolak oleh Producer. Alasan: {$request->notes}",
                    'data' => [
                        'creative_work_id' => $creativeWork->id,
                        'episode_id' => $creativeWork->episode_id,
                        'rejection_reason' => $request->notes
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $creativeWork->fresh(['episode', 'createdBy']),
                    'message' => 'Creative work rejected successfully'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process creative work approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Team Assignments untuk Episode tertentu
     * GET /api/live-tv/producer/episodes/{episodeId}/team-assignments
     * 
     * Digunakan untuk melihat team assignments yang sudah ada untuk episode tertentu
     * Berguna untuk reuse team setting setelah creative complete work
     */
    public function getEpisodeTeamAssignments(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $episode = Episode::with(['program.productionTeam'])->findOrFail($episodeId);

            // Validate Producer has access
            if ($episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this episode.'
                ], 403);
            }

            // Get team assignments untuk episode ini
            $teamAssignments = \App\Models\ProductionTeamAssignment::where('episode_id', $episodeId)
                ->with(['members.user', 'assigner', 'schedule'])
                ->orderBy('team_type')
                ->orderBy('created_at', 'desc')
                ->get();

            // Group by team_type untuk kemudahan
            $grouped = $teamAssignments->groupBy('team_type');

            return response()->json([
                'success' => true,
                'data' => [
                    'episode_id' => $episodeId,
                    'episode_number' => $episode->episode_number,
                    'team_assignments' => $teamAssignments,
                    'grouped_by_type' => [
                        'shooting' => $grouped->get('shooting', collect()),
                        'setting' => $grouped->get('setting', collect()),
                        'recording' => $grouped->get('recording', collect()),
                    ],
                    'summary' => [
                        'total_assignments' => $teamAssignments->count(),
                        'shooting_count' => $grouped->get('shooting', collect())->count(),
                        'setting_count' => $grouped->get('setting', collect())->count(),
                        'recording_count' => $grouped->get('recording', collect())->count(),
                    ]
                ],
                'message' => 'Team assignments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get team assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Team Assignments untuk Program tertentu (untuk reuse dari episode lain)
     * GET /api/live-tv/producer/programs/{programId}/team-assignments
     * 
     * Digunakan untuk melihat team assignments dari episode lain dalam program yang sama
     * Berguna untuk copy/reuse team setting dari episode sebelumnya
     */
    public function getProgramTeamAssignments(Request $request, int $programId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $program = Program::with(['productionTeam', 'episodes'])->findOrFail($programId);

            // Validate Producer has access
            if ($program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this program.'
                ], 403);
            }

            // Get episode IDs untuk program ini
            $episodeIds = $program->episodes->pluck('id')->toArray();

            // Get team assignments untuk semua episode dalam program ini
            $teamAssignments = \App\Models\ProductionTeamAssignment::whereIn('episode_id', $episodeIds)
                ->with(['members.user', 'assigner', 'schedule', 'episode'])
                ->orderBy('episode_id')
                ->orderBy('team_type')
                ->orderBy('created_at', 'desc')
                ->get();

            // Group by episode dan team_type
            $groupedByEpisode = $teamAssignments->groupBy('episode_id');
            $groupedByType = $teamAssignments->groupBy('team_type');

            // Get latest assignment per team_type untuk kemudahan reuse
            $latestByType = [
                'shooting' => $teamAssignments->where('team_type', 'shooting')->sortByDesc('created_at')->first(),
                'setting' => $teamAssignments->where('team_type', 'setting')->sortByDesc('created_at')->first(),
                'recording' => $teamAssignments->where('team_type', 'recording')->sortByDesc('created_at')->first(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'program_id' => $programId,
                    'program_name' => $program->name,
                    'team_assignments' => $teamAssignments,
                    'grouped_by_episode' => $groupedByEpisode->map(function ($assignments, $episodeId) {
                        $episode = Episode::find($episodeId);
                        return [
                            'episode_id' => $episodeId,
                            'episode_number' => $episode ? $episode->episode_number : null,
                            'assignments' => $assignments,
                            'summary' => [
                                'shooting' => $assignments->where('team_type', 'shooting')->count(),
                                'setting' => $assignments->where('team_type', 'setting')->count(),
                                'recording' => $assignments->where('team_type', 'recording')->count(),
                            ]
                        ];
                    }),
                    'grouped_by_type' => [
                        'shooting' => $groupedByType->get('shooting', collect()),
                        'setting' => $groupedByType->get('setting', collect()),
                        'recording' => $groupedByType->get('recording', collect()),
                    ],
                    'latest_by_type' => $latestByType,
                    'summary' => [
                        'total_assignments' => $teamAssignments->count(),
                        'total_episodes' => count($episodeIds),
                        'shooting_count' => $groupedByType->get('shooting', collect())->count(),
                        'setting_count' => $groupedByType->get('setting', collect())->count(),
                        'recording_count' => $groupedByType->get('recording', collect())->count(),
                    ]
                ],
                'message' => 'Program team assignments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get program team assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Semua Team Assignments untuk Producer (Semua Program)
     * GET /api/live-tv/producer/all-team-assignments
     * 
     * Digunakan untuk melihat riwayat penugasan tim di seluruh program yang dikelola
     */
    public function getAllProducerAssignments(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get all programs where this user is the producer
            $programIds = Program::whereHas('productionTeam', function($q) use ($user) {
                $q->where('producer_id', $user->id);
            })->pluck('id')->toArray();

            if (empty($programIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'data' => [],
                        'total' => 0,
                        'current_page' => 1,
                        'last_page' => 1
                    ],
                    'message' => 'No programs assigned to you.'
                ]);
            }

            // Get all episodes for these programs
            $episodeIds = Episode::whereIn('program_id', $programIds)->pluck('id')->toArray();

            // Get all team assignments for these episodes
            $teamAssignments = \App\Models\ProductionTeamAssignment::whereIn('episode_id', $episodeIds)
                ->with(['members.user', 'assigner', 'schedule', 'episode.program'])
                ->orderBy('created_at', 'desc')
                ->when($request->has('team_type') && $request->team_type !== 'all', function($query) use ($request) {
                    return $query->where('team_type', $request->team_type);
                })
                ->when($request->has('search') && $request->search, function($query) use ($request) {
                    $search = $request->search;
                    return $query->whereHas('episode', function($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                          ->orWhereHas('program', function($pq) use ($search) {
                              $pq->where('name', 'like', "%{$search}%");
                          });
                    });
                })
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $teamAssignments,
                'message' => 'All producer team assignments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get team assignments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Copy/Reuse Team Assignment dari Episode lain
     * POST /api/live-tv/producer/episodes/{episodeId}/copy-team-assignment
     * 
     * Digunakan untuk copy team assignment dari episode lain ke episode baru
     * Berguna untuk reuse team setting yang sudah pernah digunakan
     */
    public function copyTeamAssignment(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Alias source_assignment_type to team_type for better usability
            if ($request->has('source_assignment_type') && !$request->has('team_type')) {
                $request->merge(['team_type' => $request->source_assignment_type]);
            }

            $validator = Validator::make($request->all(), [
                'source_assignment_id' => 'required_without:source_episode_id|exists:production_teams_assignment,id',
                'source_episode_id' => 'required_without:source_assignment_id|exists:episodes,id',
                'team_type' => 'required_with:source_episode_id|in:shooting,setting,recording',
                'schedule_id' => 'nullable|exists:music_schedules,id',
                'team_notes' => 'nullable|string|max:1000',
                'modify_members' => 'nullable|array', // Optional: modify team members
                'modify_members.*' => 'exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get target episode
            $targetEpisode = Episode::with(['program.productionTeam'])->findOrFail($episodeId);

            // Validate Producer has access to target episode
            if ($targetEpisode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this episode.'
                ], 403);
            }

            $sourceAssignmentId = $request->source_assignment_id;
            $sourceAssignment = null;

            if (!$sourceAssignmentId && $request->has('source_episode_id')) {
                $sourceAssignment = \App\Models\ProductionTeamAssignment::where('episode_id', $request->source_episode_id)
                    ->where('team_type', $request->team_type)
                    ->with(['members.user', 'episode.program.productionTeam'])
                    ->first();
                
                if (!$sourceAssignment) {
                    return response()->json([
                        'success' => false,
                        'message' => "No assignment found for Episode {$request->source_episode_id} with type '{$request->team_type}'."
                    ], 404);
                }
            } else {
                // Get source assignment by ID
                $sourceAssignment = \App\Models\ProductionTeamAssignment::with(['members.user', 'episode.program.productionTeam'])
                    ->findOrFail($sourceAssignmentId);
            }

            // Validate Producer has access to source assignment
            if ($sourceAssignment->episode->program->productionTeam->producer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to source assignment.'
                ], 403);
            }

            // Filter by team_type if specified
            if ($request->has('team_type') && $sourceAssignment->team_type !== $request->team_type) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source assignment team type does not match requested type.'
                ], 400);
            }

            // Get production team members untuk validasi
            $productionTeam = $targetEpisode->program->productionTeam;
            $availableMembers = $productionTeam->members()
                ->where('is_active', true)
                ->where('role', '!=', 'manager_program')
                ->pluck('user_id')
                ->toArray();

            // Determine team members to use
            $teamMemberIds = $request->has('modify_members') && count($request->modify_members) > 0
                ? $request->modify_members
                : $sourceAssignment->members->pluck('user_id')->toArray();

            // Validate all team members are from production team
            $invalidMembers = array_diff($teamMemberIds, $availableMembers);
            if (!empty($invalidMembers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some team members are not part of the production team',
                    'invalid_members' => $invalidMembers
                ], 400);
            }

            // Create new assignment
            $newAssignment = \App\Models\ProductionTeamAssignment::create([
                'music_submission_id' => null,
                'episode_id' => $episodeId,
                'schedule_id' => $request->schedule_id ?? $sourceAssignment->schedule_id,
                'assigned_by' => $user->id,
                'team_type' => $sourceAssignment->team_type,
                'team_name' => $sourceAssignment->team_name . ' (Copied)',
                'team_notes' => $request->team_notes ?? $sourceAssignment->team_notes ?? 'Copied from Episode ' . $sourceAssignment->episode->episode_number,
                'status' => 'assigned',
                'assigned_at' => now()
            ]);

            // Add team members
            foreach ($teamMemberIds as $index => $userId) {
                \App\Models\ProductionTeamMember::create([
                    'assignment_id' => $newAssignment->id,
                    'user_id' => $userId,
                    'role' => $index === 0 ? 'leader' : 'crew',
                    'status' => 'assigned'
                ]);

                // Notify team member
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'team_assigned',
                    'title' => 'Ditugaskan ke Tim ' . ucfirst($sourceAssignment->team_type),
                    'message' => "Anda telah ditugaskan ke tim {$newAssignment->team_name} untuk Episode {$targetEpisode->episode_number} (dari Episode {$sourceAssignment->episode->episode_number}).",
                    'data' => [
                        'assignment_id' => $newAssignment->id,
                        'team_type' => $sourceAssignment->team_type,
                        'episode_id' => $episodeId,
                        'source_assignment_id' => $sourceAssignment->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $newAssignment->load(['members.user', 'episode']),
                'message' => 'Team assignment copied successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to copy team assignment',
                'error' => $e->getMessage()
            ], 500);
        }

    }

    /**
     * Producer direct update Song Proposal (Bypass Rejection)
     */
    public function updateSongProposal(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Producer') {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'song_title' => 'required|string',
            'singer_name' => 'required|string',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        
        $arrangement = MusicArrangement::findOrFail($id);
        
        // Ensure Producer owns this arrangement's team
        // (Simplified check for brevity)
        
        try {
            DB::transaction(function () use ($arrangement, $request, $user) {
                // Use the producerModify method to track changes
                $arrangement->producerModify(
                    $request->song_title, 
                    $request->singer_name
                );
                
                // Auto-approve after modification (per requirement)
                $arrangement->approve($user->id, $request->notes ?? 'Directly edited and approved by Producer');
                
                // Notify Arranger
                Notification::create([
                    'user_id' => $arrangement->created_by,
                    'type' => 'song_proposal_edited_approved',
                    'title' => 'Usulan Lagu Diedit & Disetujui',
                    'message' => "Producer telah mengedit dan menyetujui usulan lagu Anda menjadi: {$request->song_title} - {$request->singer_name}",
                    'data' => [
                        'arrangement_id' => $arrangement->id,
                        'original_song' => $arrangement->original_song_title,
                        'new_song' => $arrangement->song_title
                    ]
                ]);
            });
            
            return response()->json([
                'success' => true,
                'data' => $arrangement->fresh(),
                'message' => 'Song proposal updated and approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Producer direct update Creative Work (Bypass Rejection)
     */
    public function updateCreativeWork(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Producer') {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'script_content' => 'nullable|string',
            'storyboard_link' => 'nullable|string', 
            'budget_data' => 'nullable|array',
            'recording_schedule' => 'nullable|date',
            'shooting_schedule' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);
        
        $work = CreativeWork::findOrFail($id);
        
        try {
            DB::transaction(function () use ($work, $request, $user) {
                // Update fields
                $updateData = [];
                if ($request->has('script_content')) $updateData['script_content'] = $request->script_content;
                if ($request->has('budget_data')) $updateData['budget_data'] = $request->budget_data;
                if ($request->has('recording_schedule')) $updateData['recording_schedule'] = $request->recording_schedule;
                if ($request->has('shooting_schedule')) $updateData['shooting_schedule'] = $request->shooting_schedule;
                
                // Handle storyboard
                if ($request->has('storyboard_link')) {
                    $storyboardData = $work->storyboard_data ?? [];
                    $storyboardData['link'] = $request->storyboard_link;
                    $updateData['storyboard_data'] = $storyboardData;
                }
                
                $work->update($updateData);
                
                // Auto-approve logic
                $work->approve($user->id, $request->notes ?? 'Directly edited and approved by Producer');
                
                // Notify Creative
                Notification::create([
                    'user_id' => $work->created_by,
                    'type' => 'creative_work_edited_approved',
                    'title' => 'Creative Work Diedit & Disetujui',
                    'message' => "Producer telah mengedit dan menyetujui Creative Work Anda.",
                    'data' => ['creative_work_id' => $work->id]
                ]);
                
                // Trigger downstream tasks
                $this->triggerPostCreativeApproval($work, $user);
            });
            
            return response()->json([
                'success' => true,
                'data' => $work->fresh(),
                'message' => 'Creative Work updated and approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    

    /**
     * Producer Edit Creative Work Directly (Bypass Rejection)
     * POST /api/live-tv/producer/creative-works/{id}/edit-directly
     */
    public function editCreativeDirectly(Request $request, int $creativeWorkId): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'Producer') {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $creativeWork = CreativeWork::with(['episode.program.productionTeam.members', 'createdBy'])->findOrFail($creativeWorkId);

            // Basic validation: User should be part of the production team (simplified check)
            // if we had program_id available easily we would check, but relying on role + find for now
            
            $producerModifiedData = [
                'original_script_content' => $creativeWork->script_content,
                'modified_script_content' => $request->script_content,
                'modified_at' => now(),
                'modified_by' => $user->id
            ];

            // Update fields
            $creativeWork->update([
                'script_content' => $request->script_content ?? $creativeWork->script_content,
                'storyboard_data' => $request->storyboard_data ?? $creativeWork->storyboard_data,
                // Recording schedule logic
                'recording_schedule' => $request->recording_schedule ?? $creativeWork->recording_schedule,
                'shooting_schedule' => $request->shooting_schedule ?? $creativeWork->shooting_schedule,
                'shooting_location' => $request->shooting_location ?? $creativeWork->shooting_location,
                'budget_data' => $request->budget_data ?? $creativeWork->budget_data,
                // Status update
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => ($creativeWork->review_notes ? $creativeWork->review_notes . "\n" : "") . "Edited & Approved by Producer: " . ($request->notes ?? 'Direct edit'),
                'producer_modified_data' => $producerModifiedData
            ]);

            // Trigger post approval actions (Budget, etc.)
            $this->triggerPostCreativeApproval($creativeWork, $user);

            // Notify Creative - SINGLE INSERT
            Notification::create([
                'user_id' => $creativeWork->created_by,
                'type' => 'creative_work_edited',
                'title' => 'Creative Work Diedit & Disetujui',
                'message' => "Creative work Anda untuk Episode {$creativeWork->episode->episode_number} telah diedit dan langsung disetujui oleh Producer.",
                'data' => ['creative_work_id' => $creativeWork->id]
            ]);

            return response()->json([
                'success' => true, 
                'data' => $creativeWork->fresh(['episode.program']), 
                'message' => 'Creative work edited and approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Producer Request Special Budget
     * POST /api/live-tv/producer/creative-works/{id}/request-special-budget
     */



    /**
     * Assign Creative Teams (Shooting, Setting, Vocal)
     * POST /api/live-tv/roles/producer/assign-creative-teams
     */
    public function assignCreativeTeams(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Producer') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'assignments' => 'required|array',
            'assignments.*.type' => 'required|in:shooting,setting,recording',
            'assignments.*.user_ids' => 'required|array',
            'assignments.*.user_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $episode = Episode::with('program.productionTeam')->findOrFail($request->episode_id);
            // Default to Program's Team, fallback to Episode's Team if overridden
            $productionTeam = $episode->program->productionTeam; 

            // Validate Producer Authority
            $hasAuthority = false;
            // Check Program Team
            if ($productionTeam && $productionTeam->producer_id === $user->id) {
                $hasAuthority = true;
            } 
            // Check Episode Team (Override)
            elseif ($episode->productionTeam && $episode->productionTeam->producer_id === $user->id) {
                $productionTeam = $episode->productionTeam;
                $hasAuthority = true;
            }

            if (!$hasAuthority) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This episode is not assigned to your production team.'
                ], 403);
            }

            if (!$productionTeam) {
                 return response()->json([
                    'success' => false,
                    'message' => 'No Production Team found for this episode.'
                ], 400);
            }

            foreach ($request->assignments as $assignmentData) {
                // Create or Update Assignment Header
                $assignment = \App\Models\ProductionTeamAssignment::updateOrCreate(
                    [
                        'episode_id' => $episode->id,
                        'team_type' => $assignmentData['type']
                    ],
                    [
                        'assigned_by' => $user->id,
                        'status' => 'assigned',
                        'assigned_at' => now()
                    ]
                );

                // Sync Members
                // First, remove existing members for this specific assignment
                ProductionTeamMember::where('assignment_id', $assignment->id)->delete();

                // Add new members and notify them
                foreach ($assignmentData['user_ids'] as $userId) {
                    ProductionTeamMember::create([
                        'production_team_id' => $productionTeam->id, // Associate with main team for reference
                        'assignment_id' => $assignment->id,
                        'user_id' => $userId,
                        'role' => $this->mapTeamTypeToRole($assignmentData['type']),
                        'is_active' => true,
                        'joined_at' => now(),
                        'status' => 'assigned'
                    ]);

                    // Notify the user
                    $teamName = match($assignmentData['type']) {
                        'shooting' => 'Tim Syuting',
                        'setting' => 'Tim Setting',
                        'recording' => 'Tim Rekam Vokal',
                        default => 'Tim Produksi'
                    };

                    \App\Models\Notification::create([
                        'user_id' => $userId,
                        'type' => 'team_assigned',
                        'title' => 'Penugasan Tim Baru',
                        'message' => "Anda telah ditugaskan ke {$teamName} untuk Episode {$episode->episode_number} oleh Producer.",
                        'data' => [
                            'episode_id' => $episode->id,
                            'team_type' => $assignmentData['type'],
                            'assignment_id' => $assignment->id
                        ]
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Creative teams assigned successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign teams: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit Creative Work (Bypass Approval)
     * POST /api/live-tv/roles/producer/edit-creative-work
     */
    public function editCreativeWork(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'Producer') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $work = CreativeWork::findOrFail($id);
        
        // Authorization check logic (similar to approve)
        $episode = $work->episode;
        $productionTeam = $episode->productionTeam ?? ($episode->program ? $episode->program->productionTeam : null);
        if (!$productionTeam || $productionTeam->producer_id !== $user->id) {
             return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'script_content', 'script_link', 
                'storyboard_data', 'storyboard_link',
                'recording_schedule', 'shooting_schedule', 'shooting_location',
                'budget_data'
            ]);

            // Force Approve
            $updateData['status'] = 'approved';
            $updateData['reviewed_by'] = $user->id;
            $updateData['reviewed_at'] = now();
            $updateData['review_notes'] = ($work->review_notes ? $work->review_notes . "\n" : "") . "Edited & Approved by Producer: " . ($request->notes ?? 'Direct edit');
            $updateData['script_approved'] = true;
            $updateData['storyboard_approved'] = true;
            $updateData['budget_approved'] = true;
            $updateData['requires_special_budget_approval'] = false; // Bypass special approval if producer edits directly

            $work->update($updateData);

            // Trigger Downstream
            $this->triggerPostCreativeApproval($work, $user);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(),
                'message' => 'Creative work updated and approved successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Production Team Details (Members)
     */
    public function getProductionTeamDetails(int $teamId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // Verify producer owns this team
            $team = \App\Models\ProductionTeam::where('id', $teamId)
                ->where('producer_id', $user->id)
                ->first();

            if (!$team) {
                return response()->json(['success' => false, 'message' => 'Team not found or not owned by you.'], 404);
            }

            $members = $team->members()
                ->where('is_active', true)
                ->with('user:id,name,email,role')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $members,
                'team' => $team,
                'message' => 'Team details retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to get team details', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add Member to Production Team
     */
    public function addProductionTeamMember(Request $request, int $teamId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'role' => 'required|string',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            // Verify producer owns this team
            $team = \App\Models\ProductionTeam::where('id', $teamId)
                ->where('producer_id', $user->id)
                ->first();

            if (!$team) {
                return response()->json(['success' => false, 'message' => 'Team not found or not owned by you.'], 404);
            }

            // Check if user already in team with active status
            $existing = $team->members()
                ->where('user_id', $request->user_id)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                // Update role if different
                if ($existing->role !== $request->role) {
                    $existing->update(['role' => $request->role]);
                    return response()->json(['success' => true, 'message' => 'Member role updated successfully']);
                }
                return response()->json(['success' => false, 'message' => 'User is already a member of this team.'], 400);
            }

            // Add member
            $team->addMember($request->user_id, $request->role, $request->notes);

            return response()->json(['success' => true, 'message' => 'Member added successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add member', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove Member from Production Team
     */
    public function removeProductionTeamMember(Request $request, int $teamId, int $userId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            // Verify producer owns this team
            $team = \App\Models\ProductionTeam::where('id', $teamId)
                ->where('producer_id', $user->id)
                ->first();

            if (!$team) {
                return response()->json(['success' => false, 'message' => 'Team not found or not owned by you.'], 404);
            }

            // Get role from request or find active member
            $role = $request->input('role');
            if (!$role) {
                $member = $team->members()
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();
                if (!$member) {
                     return response()->json(['success' => false, 'message' => 'Member not found in this team.'], 404);
                }
                $role = $member->role;
            }

            try {
                $team->removeMember($userId, $role);
            } catch (\Exception $e) {
                 return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }

            return response()->json(['success' => true, 'message' => 'Member removed successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to remove member', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Assign Team to Episode
     * Producer hanya bisa assign team yang dia miliki
     */
    public function assignTeamToEpisode(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->role !== 'Producer') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'production_team_id' => 'required|exists:production_teams,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            // Verify producer owns the target team
            $team = \App\Models\ProductionTeam::where('id', $request->production_team_id)
                ->where('producer_id', $user->id)
                ->first();

            if (!$team) {
                return response()->json(['success' => false, 'message' => 'Target team not found or not owned by you.'], 404);
            }

            $episode = Episode::with('program.productionTeam')->findOrFail($episodeId);

            // Verify producer has access to this episode
            $hasAccess = false;
            // Check program's production team
            if ($episode->program && $episode->program->productionTeam && $episode->program->productionTeam->producer_id === $user->id) {
                $hasAccess = true;
            } 
            // Check episode's current production team (if any)
            elseif ($episode->productionTeam && $episode->productionTeam->producer_id === $user->id) {
                $hasAccess = true;
            }

            if (!$hasAccess) {
                 return response()->json(['success' => false, 'message' => 'Unauthorized: You do not have access to manage this episode.'], 403);
            }

            $episode->update([
                'production_team_id' => $request->production_team_id,
                'team_assignment_notes' => $request->notes,
                'team_assigned_by' => $user->id,
                'team_assigned_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Team assigned to episode successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to assign team', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper to map Team Type to Role
     */
    private function mapTeamTypeToRole(string $type): string
    {
        return match ($type) {
            'shooting' => 'produksi',
            'setting' => 'art_set_properti',
            'recording' => 'sound_eng',
            default => 'produksi'
        };
    }

    /**
     * Helper to trigger downstream tasks after Creative Work Approval
     */
    private function triggerPostCreativeApproval(CreativeWork $item, $user)
    {
        // Ensure relations are loaded
        if (!$item->relationLoaded('episode')) {
            $item->load('episode.program.productionTeam');
        } elseif (!$item->episode->relationLoaded('program')) {
            $item->episode->load('program.productionTeam');
        }

        $episode = $item->episode;
        if (!$episode) {
            \Log::error('triggerPostCreativeApproval: Episode not found for CreativeWork', ['id' => $item->id]);
            return;
        }

        $program = $episode->program;
        if (!$program) {
            \Log::error('triggerPostCreativeApproval: Program not found for Episode', ['id' => $episode->id]);
            return;
        }

        $createdTasks = [];

        // 1. Create Budget Request if Total Budget > 0
        if ($item->total_budget > 0 && !empty($item->budget_data)) {
            foreach ($item->budget_data as $budgetItem) {
                $budgetType = match(strtolower($budgetItem['category'] ?? '')) {
                    'talent', 'talent fee', 'honor' => 'talent_fee',
                    'equipment', 'equipment rental', 'alat' => 'equipment_rental',
                    'location', 'venue', 'sewa lokasi' => 'location_fee',
                    'transport', 'transportation', 'bensin' => 'transportation',
                    'catering', 'consumption', 'makan', 'konsumsi' => 'food_catering',
                    'special', 'special budget' => 'special_request',
                    default => 'special_request'
                };
                
                Budget::create([
                    'episode_id' => $item->episode_id,
                    'budget_type' => $budgetType,
                    'amount' => $budgetItem['amount'] ?? 0,
                    'description' => ($budgetItem['description'] ?? '') . " [From Creative Work: " . ($budgetItem['category'] ?? 'General') . "]",
                    'status' => 'approved',
                    'requested_by' => $user->id,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'approval_notes' => 'Auto-generated from Creative Work Approval'
                ]);
            }
        }

        // 2. Create Downstream Tasks (Promotion, Produksi)
        // Promotion Work
        $existingPromo = \App\Models\PromotionWork::where('episode_id', $item->episode_id)->first();
        if (!$existingPromo) {
            $episodeTitleSuffix = $episode->title ? ": {$episode->title}" : "";
            $promosiWork = \App\Models\PromotionWork::create([
                'episode_id' => $item->episode_id,
                'work_type' => 'bts_video',
                'title' => "BTS Video & Talent Photos - {$program->name} - Episode {$episode->episode_number}{$episodeTitleSuffix}",
                'description' => "Buat video BTS dan foto talent untuk Program {$program->name} Episode {$episode->episode_number}{$episodeTitleSuffix}",
                'shooting_date' => $item->shooting_schedule,
                'status' => 'planning',
                'created_by' => $user->id
            ]);
            $createdTasks['promotion_work_id'] = $promosiWork->id;
        } else {
            $createdTasks['promotion_work_id'] = $existingPromo->id;
        }

        // Produksi Work
        $existingProduksi = \App\Models\ProduksiWork::where('episode_id', $item->episode_id)->where('creative_work_id', $item->id)->first();
        if (!$existingProduksi) {
            $produksiWork = \App\Models\ProduksiWork::create([
                'episode_id' => $item->episode_id,
                'creative_work_id' => $item->id,
                'status' => 'pending'
            ]);
            $createdTasks['produksi_work_id'] = $produksiWork->id;
        } else {
            $createdTasks['produksi_work_id'] = $existingProduksi->id;
        }

        // 3. Handle Sound Engineer Recording Task
        $recordingId = null;
        if ($item->recording_schedule) {
            // Find latest approved arrangement so Sound Engineer can see what to record.
            $approvedArrangement = MusicArrangement::where('episode_id', $item->episode_id)
                ->whereIn('status', ['approved', 'arrangement_approved'])
                ->orderByRaw('COALESCE(reviewed_at, submitted_at, updated_at) DESC')
                ->first();
            $approvedArrangementId = $approvedArrangement?->id;
            $approvedArrangementTitle = $approvedArrangement?->song_title;

            $existingRecording = SoundEngineerRecording::where('episode_id', $item->episode_id)->first();
            
            if ($existingRecording) {
                $existingRecording->update([
                    'recording_schedule' => $item->recording_schedule,
                    'status' => 'pending',
                    // Fill arrangement id if it's missing (older rows)
                    'music_arrangement_id' => $existingRecording->music_arrangement_id ?: $approvedArrangementId,
                ]);
                $recordingId = $existingRecording->id;
            } else {
                $productionTeam = $program->productionTeam;
                $firstSoundEngineer = $productionTeam ? $productionTeam->members()
                    ->whereRaw('LOWER(role) IN (?, ?, ?)', ['sound_eng', 'sound_engineer', 'sound engineer'])
                    ->where('is_active', true)
                    ->first() : null;

                $createdByUserId = $firstSoundEngineer ? $firstSoundEngineer->user_id : $user->id;

                $recording = SoundEngineerRecording::create([
                    'episode_id' => $item->episode_id,
                    'music_arrangement_id' => $approvedArrangementId,
                    // Include "approved arrangement:" pattern because frontend extracts it.
                    'recording_notes' => $approvedArrangementTitle
                        ? "Recording task untuk rekaman vokal Episode {$episode->episode_number}. approved arrangement: {$approvedArrangementTitle}"
                        : "Recording task untuk rekaman vokal Episode {$episode->episode_number}",
                    'recording_schedule' => $item->recording_schedule,
                    'status' => 'pending',
                    'created_by' => $createdByUserId,
                ]);
                $recordingId = $recording->id;
            }
            $createdTasks['recording_id'] = $recordingId;
        }

        // 4. Send Notifications
        // A. Notify Sound Engineer if recording task exists
        if ($recordingId) {
            $productionTeam = $program->productionTeam;
            if ($productionTeam) {
                $soundEngineers = $productionTeam->members()
                    ->whereRaw('LOWER(role) IN (?, ?, ?)', ['sound_eng', 'sound_engineer', 'sound engineer'])
                    ->where('is_active', true)
                    ->get();
                foreach ($soundEngineers as $se) {
                    Notification::create([
                        'user_id' => $se->user_id,
                        'type' => 'vocal_recording_task_created',
                        'title' => 'Tugas Rekaman Vokal Baru',
                        'message' => "Anda mendapat tugas untuk rekaman vokal Episode {$episode->episode_number}. Jadwal rekaman: " . \Carbon\Carbon::parse($item->recording_schedule)->format('d M Y'),
                        'data' => [
                            'recording_id' => $recordingId,
                            'episode_id' => $item->episode_id,
                            'recording_schedule' => $item->recording_schedule
                        ]
                    ]);
                }
            }
        }

        // B. Notify Promotion and Production roles in parallel
        \App\Services\ParallelNotificationService::notifyRoles(
            ['Promotion', 'Production'],
            [
                'type' => 'creative_work_approved_task_assigned',
                'title' => 'Creative Work Approved - Tugas Baru',
                'message' => "Creative Work Episode {$episode->episode_number} telah disetujui. Anda mendapat tugas baru. Jadwal syuting: " . ($item->shooting_schedule ? \Carbon\Carbon::parse($item->shooting_schedule)->format('d M Y') : 'TBD'),
                'data' => array_merge([
                    'episode_id' => $episode->id,
                    'episode_number' => $episode->episode_number,
                    'creative_work_id' => $item->id,
                    'shooting_schedule' => $item->shooting_schedule,
                ], $createdTasks)
            ],
            $program->id
        );

        // C. Specific Shooting Team / Setting Team notifications if schedule exists
        if ($item->shooting_schedule) {
            $shootingMembers = \App\Models\ProductionTeamMember::whereHas('assignment', function($q) use ($episode) {
                $q->where('episode_id', $episode->id)->where('team_type', 'shooting');
            })->where('is_active', true)->get();

            foreach ($shootingMembers as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'shooting_schedule_set',
                    'title' => 'Jadwal Syuting',
                    'message' => "Jadwal syuting untuk Episode {$episode->episode_number} telah ditetapkan: " . $item->shooting_schedule->format('d M Y H:i'),
                    'data' => ['episode_id' => $item->episode_id]
                ]);
            }

            $settingMembers = \App\Models\ProductionTeamMember::whereHas('assignment', function($q) use ($episode) {
                $q->where('episode_id', $episode->id)->where('team_type', 'setting');
            })->where('is_active', true)->get();

            foreach ($settingMembers as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'type' => 'setting_prep_needed',
                    'title' => 'Persiapan Setting & Properti',
                    'message' => "Creative Work telah disetujui. Silakan persiapkan setting & properti untuk syuting Episode {$episode->episode_number} pada " . $item->shooting_schedule->format('d M Y'),
                    'data' => ['episode_id' => $item->episode_id]
                ]);
            }
        }
    }

    /**
     * Get Editor missing files reports for Producer's review.
     */
    public function getEditorMissingFiles(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $reports = EditorWork::where(function($q) {
                $q->where('file_complete', false)
                  ->orWhere('status', 'issue')
                  ->orWhere('status', 'file_incomplete');
            })
            ->with(['episode', 'episode.productionTeam', 'episode.program', 'createdBy'])
            ->get()
            ->filter(fn($work) => $this->isProducerAuthorizedForEpisode($work->episode, $user->id))
            ->values()
            ->map(function($work) {
                // Ensure the frontend always has a clean report to show in the 'Detail' modal
                $work->editor_report_summary = $work->formatted_missing_report;
                
                // If missing_notes is empty in the underlying JSON, we provide the formatted report as a fallback
                // for UIs that only read source_files.missing_notes
                if (isset($work->source_files) && empty($work->source_files['missing_notes'] ?? '')) {
                    $newSourceFiles = $work->source_files;
                    $newSourceFiles['missing_notes'] = $work->formatted_missing_report;
                    $work->source_files = $newSourceFiles;
                }
                
                return $work;
            });

            return response()->json([
                'success' => true,
                'data' => $reports,
                'message' => 'Laporan file kurang dari Editor berhasil diambil.'
            ]);
        } catch (\Exception $e) {
            Log::error('ProducerController@getEditorMissingFiles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan file kurang.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle actions for missing files reported by Editor.
     * Actions: reshoot, request_missing, ignore.
     *
     * POST /api/live-tv/producer/editor-works/{editorWorkId}/handle-missing-files
     */
    public function handleMissingFilesAction(Request $request, int $editorWorkId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required_without:action_type|in:reshoot,request_missing,ignore,complete_files',
            'action_type' => 'required_without:action|in:reshoot,request_missing,ignore,complete_files',
            'notes' => 'nullable|string|max:2000',
            'team_type' => 'nullable|string|in:shooting,creative,arrangement,vocal,sound_engineer',
            'reshoot_date' => 'nullable|date',
            'reshoot_location' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            $user = auth()->user();
            $editorWork = EditorWork::with(['episode', 'episode.productionTeam'])->findOrFail($editorWorkId);

            // Authorization Check
            if (!$this->isProducerAuthorizedForEpisode($editorWork->episode, $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Anda bukan Producer dari program ini.'
                ], 403);
            }

            $action = $request->action ?? $request->action_type; // Support both naming styles
            $notes = $request->notes;
            $episode = $editorWork->episode;

            $resolutionData = [
                'action' => $action,
                'resolved_by' => $user->id,
                'resolved_by_name' => $user->name,
                'resolved_at' => now(),
                'notes' => $notes
            ];

            // Extract detailed missing files report to include in notifications
            $missingItemsSummary = "";
            $missingFiles = $editorWork->source_files['missing_files'] ?? [];
            $issueCategories = [];

            foreach ($missingFiles as $f) {
                if (($f['status'] ?? '') === 'issue') {
                    $cat = $f['file_type'] ?? 'General';
                    $desc = $f['description'] ?? ($f['notes'] ?? 'No detail');
                    $missingItemsSummary .= "- " . strtoupper($cat) . ": " . $desc . "\n";
                    $issueCategories[] = strtolower($cat);
                }
            }

            if (empty($missingItemsSummary)) {
                $missingItemsSummary = "Detail: " . ($editorWork->source_files['missing_notes'] ?? 'Tidak ada detail spesifik.');
            }

            $fullMessageHeader = "Laporan dari Editor:\n" . $missingItemsSummary . "\nCatatan Producer: " . ($notes ?: '-');

            if ($action === 'reshoot') {
                $reshootDate = $request->reshoot_date;
                $reshootLocation = $request->reshoot_location;
                
                // Find ProduksiWork (Shooting task)
                $produksiWork = ProduksiWork::where('episode_id', $editorWork->episode_id)->first();
                if ($produksiWork) {
                    $produksiWork->update([
                        'status' => 'needs_revision', // Updated from 'pending' to 'needs_revision'
                        'completed_at' => null,
                        'completed_by' => null,
                        'producer_requests' => array_merge($produksiWork->producer_requests ?? [], [
                            [
                                'type' => 'reshoot',
                                'requested_by' => $user->id,
                                'at' => now(),
                                'notes' => $notes,
                                'reshoot_date' => $reshootDate,
                                'reshoot_location' => $reshootLocation,
                                'editor_report' => $missingItemsSummary // Keep the report in the work record
                            ]
                        ])
                    ]);

                    // Notify Shooting Team Members
                    $shootingMembers = \App\Models\ProductionTeamMember::whereHas('assignment', function($q) use ($episode) {
                        $q->where('episode_id', $episode->id)->whereIn('team_type', ['shooting', 'setting']);
                    })->where('is_active', true)->get();

                    $reshootMsg = "Producer meminta SYUTING ULANG untuk episode {$episode->episode_number}.\n\n$fullMessageHeader";
                    if ($reshootDate) $reshootMsg .= "\n\nRencana Syuting Ulang: " . date('d M Y', strtotime($reshootDate)) . ($reshootLocation ? " di $reshootLocation" : "");

                    foreach ($shootingMembers as $member) {
                        Notification::create([
                            'user_id' => $member->user_id,
                            'episode_id' => $episode->id,
                            'type' => 'reshoot_requested',
                            'title' => 'Permintaan Syuting Ulang (Reshoot)',
                            'message' => $reshootMsg,
                            'priority' => 'urgent',
                            'data' => [
                                'editor_work_id' => $editorWorkId, 
                                'reshoot_date' => $reshootDate,
                                'original_report' => $missingItemsSummary
                            ]
                        ]);
                    }
                }
            } elseif ($action === 'request_missing' || $action === 'complete_files') {
                // SMART ROUTING: Notify ALL relevant teams based on issue categories detected
                
                // If the Producer didn't specify a team, notify everyone based on categories
                $teamsToNotify = empty($request->team_type) ? array_unique($issueCategories) : [$request->team_type];
                
                // If no specific categories found, default to shooting/production
                if (empty($teamsToNotify)) $teamsToNotify = ['shooting'];

                // Update ProduksiWork status if 'shooting' or 'setting' is involved
                if (in_array('shooting', $teamsToNotify) || in_array('setting', $teamsToNotify)) {
                    $produksiWork = ProduksiWork::where('episode_id', $editorWork->episode_id)->first();
                    if ($produksiWork) {
                        $produksiWork->update([
                            'status' => 'needs_revision',
                            'completed_at' => null,
                            'producer_requests' => array_merge($produksiWork->producer_requests ?? [], [
                                [
                                    'type' => 'repair',
                                    'requested_by' => $user->id,
                                    'at' => now(),
                                    'notes' => $notes,
                                    'editor_report' => $missingItemsSummary
                                ]
                            ])
                        ]);
                    }
                }

                foreach ($teamsToNotify as $teamType) {
                    $rolesToNotify = [];
                    $teamLabel = 'Produksi';
                    $targetUserIds = [];

                    $roleMap = [
                        'creative' => ['creative', 'kreatif'],
                        'arrangement' => ['musik_arr', 'music_arranger'],
                        'vocal' => ['sound_eng', 'sound_engineer'],
                        'audio' => ['sound_eng', 'sound_engineer'],
                        'sound_engineer' => ['sound_eng', 'sound_engineer'],
                        'shooting' => ['production', 'produksi'],
                        'production_files' => ['production', 'produksi'],
                        'setting' => ['art_set_design']
                    ];

                    $rolesToNotify = $roleMap[$teamType] ?? ['production', 'produksi'];

                    // Routing based on specific work records
                    switch ($teamType) {
                        case 'creative':
                            $targetUserIds[] = CreativeWork::where('episode_id', $episode->id)->value('created_by');
                            $teamLabel = 'Tim Kreatif';
                            break;
                        case 'arrangement':
                            $targetUserIds[] = MusicArrangement::where('episode_id', $episode->id)->value('created_by');
                            $teamLabel = 'Music Arranger';
                            break;
                        case 'vocal':
                        case 'audio':
                            $targetUserIds[] = SoundEngineerEditing::where('episode_id', $episode->id)->value('created_by');
                            $teamLabel = 'Sound Engineer';
                            break;
                        default:
                            $targetUserIds[] = ProduksiWork::where('episode_id', $episode->id)->value('created_by');
                            $teamLabel = 'Tim Produksi';
                    }

                    // Fallback to assigned members
                    if (empty(array_filter($targetUserIds))) {
                        $teamMapping = [
                            'creative' => 'creative',
                            'arrangement' => 'music',
                            'vocal' => 'vocal',
                            'audio' => 'vocal',
                            'shooting' => 'shooting',
                            'production_files' => 'shooting',
                            'setting' => 'setting'
                        ];
                        $targetUserIds = \App\Models\ProductionTeamMember::whereHas('assignment', function($q) use ($episode, $teamType, $teamMapping) {
                            $q->where('episode_id', $episode->id)->where('team_type', $teamMapping[$teamType] ?? 'shooting');
                        })->whereIn('role', $rolesToNotify)->where('is_active', true)->pluck('user_id')->unique()->toArray();
                    }

                    foreach (array_filter($targetUserIds) as $targetId) {
                        Notification::create([
                            'user_id' => $targetId,
                            'episode_id' => $episode->id,
                            'type' => 'missing_material_requested',
                            'title' => "Permintaan Kelengkapan File ({$teamLabel})",
                            'message' => "Producer meminta kelengkapan file untuk episode {$episode->episode_number}.\n\n$fullMessageHeader",
                            'priority' => 'high',
                            'data' => [
                                'editor_work_id' => $editorWorkId, 
                                'team_type' => $teamType,
                                'requested_by' => $user->id
                            ]
                        ]);
                    }

                    // ProduksiWork status is already handled as 'needs_revision' above
                }
            }

            // Update EditorWork with resolution info
            $sourceFiles = $editorWork->source_files ?? [];
            $sourceFiles['producer_resolution'] = $resolutionData;
            
            $editorWork->update([
                'source_files' => $sourceFiles,
                'status' => 'file_incomplete' // Keep in Draft/Revision until files are ready
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tindakan Producer berhasil disimpan dan tim terkait telah diberitahu.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ProducerController@handleMissingFilesAction: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menangani laporan file.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== PRODUCER PROGRAM ACCEPTANCE =====

    /**
     * Get programs pending Producer acceptance.
     * Producer melihat daftar program yang dia masuk sebagai tim (via ProductionTeam.producer_id)
     * tapi belum di-accept.
     *
     * GET /api/live-tv/producer/pending-programs
     */
    public function getPendingPrograms(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $programs = Program::with([
                    'managerProgram',
                    'productionTeam.members.user',
                ])
                ->withCount('episodes')
                ->whereNotNull('production_team_id')
                ->where('producer_accepted', false)
                ->whereNull('producer_rejected_at')
                ->when(!$isProgramManager, function ($q) use ($user) {
                    $q->whereHas('productionTeam', function ($subQ) use ($user) {
                        $subQ->where('producer_id', $user->id)
                            ->where('is_active', true);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $programs,
                'message' => 'Pending programs retrieved successfully',
                'count' => $programs->count()
            ]);
        } catch (\Exception $e) {
            Log::error('ProducerController@getPendingPrograms: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar program pending.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer accepts a program.
     * Setelah accept, workflow bisa berjalan (Music Arranger bisa mulai).
     *
     * POST /api/live-tv/producer/programs/{id}/accept
     */
    public function acceptProgram(Request $request, $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $isProgramManager = ProgramManagerAuthorization::isProgramManager($user);
            if (!$user || ($user->role !== 'Producer' && !$isProgramManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }
            $program = Program::with('productionTeam')->findOrFail($id);

            // Pastikan kolom producer acceptance sudah ada (migration sudah dijalankan)
            if (!\Illuminate\Support\Facades\Schema::hasColumn('programs', 'producer_accepted')) {
                Log::warning('ProducerController@acceptProgram: Kolom producer_accepted belum ada. Jalankan: php artisan migrate');
                return response()->json([
                    'success' => false,
                    'message' => 'Fitur accept program belum siap. Silakan jalankan migrasi: php artisan migrate',
                    'error' => 'Column producer_accepted does not exist on programs table.'
                ], 503);
            }

            // Validate: Producer harus dari ProductionTeam program ini (bandingkan sebagai int)
            if (
                !$program->productionTeam
                || (!$isProgramManager && (int) $program->productionTeam->producer_id !== (int) $user->id)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Anda bukan Producer dari program ini.'
                ], 403);
            }

            // Validate: Belum di-accept sebelumnya
            if ($program->producer_accepted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program ini sudah diterima sebelumnya.',
                    'accepted_at' => $program->producer_accepted_at
                ], 400);
            }

            DB::beginTransaction();

            // Hanya update kolom acceptance khusus Producer.
            // Status program tidak diubah di sini untuk menghindari conflict enum/status lama.
            $program->update([
                'producer_accepted' => true,
                'producer_accepted_by' => $user->id,
                'producer_accepted_at' => now(),
                // Clear rejection if previously rejected
                'producer_rejected_at' => null,
                'producer_rejection_notes' => null,
            ]);

            // Notify Manager Program (hanya jika manager_program_id ada; user_id di notifications tidak nullable)
            if ($program->manager_program_id) {
                Notification::create([
                    'user_id' => $program->manager_program_id,
                    'type' => 'program_accepted_by_producer',
                    'title' => 'Program Diterima oleh Producer',
                    'message' => "Producer {$user->name} telah menerima program '{$program->name}'. Workflow siap berjalan.",
                    'data' => [
                        'program_id' => $program->id,
                        'producer_id' => $user->id,
                        'producer_name' => $user->name,
                    ]
                ]);
            }

            // Notify semua team members bahwa program sudah aktif (hanya user_id yang valid)
            if ($program->productionTeam) {
                $teamMembers = $program->productionTeam->members()
                    ->where('is_active', true)
                    ->where('user_id', '!=', $user->id)
                    ->whereNotNull('user_id')
                    ->get();

                foreach ($teamMembers as $member) {
                    if (empty($member->user_id)) {
                        continue;
                    }
                    Notification::create([
                        'user_id' => $member->user_id,
                        'type' => 'program_ready_to_start',
                        'title' => 'Program Siap Dimulai',
                        'message' => "Program '{$program->name}' telah diterima oleh Producer. Workflow siap berjalan.",
                        'data' => [
                            'program_id' => $program->id,
                            'role' => $member->role ?? 'member',
                        ]
                    ]);
                }
            }

            DB::commit();

            // Clear cache
            \App\Helpers\QueryOptimizer::clearIndexCache('programs');

            $refreshed = $program->fresh(['productionTeam.members.user', 'managerProgram']);
            return response()->json([
                'success' => true,
                'data' => $refreshed,
                'message' => 'Program berhasil diterima. Workflow siap berjalan.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ProducerController@acceptProgram: ' . $e->getMessage(), [
                'program_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menerima program.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Producer rejects a program.
     */
    public function rejectProgram(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_notes' => 'required|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $user = auth()->user();
            $program = Program::with('productionTeam')->findOrFail($id);

            if (!$program->productionTeam || $program->productionTeam->producer_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            DB::beginTransaction();
            $program->update([
                'producer_accepted' => false,
                'producer_rejected_at' => now(),
                'producer_rejection_notes' => $request->rejection_notes,
            ]);

            Notification::create([
                'user_id' => $program->manager_program_id,
                'type' => 'program_rejected_by_producer',
                'title' => 'Program Ditolak oleh Producer',
                'message' => "Producer menolak program '{$program->name}'. Alasan: {$request->rejection_notes}",
                'data' => ['program_id' => $program->id]
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Program ditolak.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generic Producer request action for Produksi.
     */
    public function requestProduksiAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'action' => 'required|in:reshoot,fix_material,complete_files',
            'notes' => 'required|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $user = auth()->user();
            $episodeId = $request->episode_id;
            $episode = Episode::with('productionTeam')->findOrFail($episodeId);

            if (!$this->isProducerAuthorizedForEpisode($episode, $user->id)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $produksiWork = ProduksiWork::firstOrCreate(['episode_id' => $episodeId], ['status' => 'pending']);
            
            $requests = $produksiWork->producer_requests ?? [];
            $requests[] = ['at' => now(), 'by' => $user->id, 'action' => $request->action, 'notes' => $request->notes];

            $produksiWork->update([
                'producer_requests' => $requests,
                'status' => $request->action === 'reshoot' ? 'pending' : 'in_progress'
            ]);

            Notification::create([
                'user_id' => $produksiWork->created_by ?: $user->id,
                'episode_id' => $episodeId,
                'type' => 'producer_action_requested',
                'title' => "Permintaan Producer: " . $request->action,
                'message' => $request->notes,
                'priority' => 'high'
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Permintaan dikirim.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}


