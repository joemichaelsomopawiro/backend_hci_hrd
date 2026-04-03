<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Program;
use App\Models\Deadline;
use App\Models\MusicArrangement;
use App\Models\EditorWork;
use App\Models\ProduksiWork;
use App\Models\SoundEngineerEditing;
use App\Models\BroadcastingWork;
use App\Models\PromotionWork;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MusicHistoryService
{
    /**
     * Get consolidated music production history.
     *
     * @param array $filters
     * @return Collection
     */
    public function getHistory(array $filters = []): Collection
    {
        $query = Episode::with([
            'program',
            'musicArrangements' => function ($q) {
                $q->with([
                    'createdBy' => fn($u) => $u->withTrashed(), 
                    'reviewedBy' => fn($u) => $u->withTrashed(), 
                    'singer', 
                    'song'
                ]);
            },
            'editorWorks' => function ($q) {
                $q->with(['createdBy' => fn($u) => $u->withTrashed()]);
            },
            'produksiWorks' => function ($q) {
                $q->with([
                    'completedBy' => fn($u) => $u->withTrashed(), 
                    'settingCompletedBy' => fn($u) => $u->withTrashed()
                ]);
            },
            'broadcastingWorks' => function ($q) {
                $q->with(['createdBy' => fn($u) => $u->withTrashed()]);
            },
            'promotionWorks' => function ($q) {
                $q->with(['createdBy' => fn($u) => $u->withTrashed()]);
            },
            'deadlines' => function ($q) {
                $q->with(['completedBy' => fn($u) => $u->withTrashed()]);
            },
            'soundEngineerEditings' => function ($q) {
                $q->with(['soundEngineer' => fn($u) => $u->withTrashed()]);
            }
        ])->whereHas('program', function ($q) {
            $q->where('category', 'musik');
        });

        // Apply filters
        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('air_date', $filters['year']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('air_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('air_date', '<=', $filters['end_date']);
        }

        $episodes = $query->orderBy('air_date', 'asc')->get();

        return $episodes->map(function ($episode) {
            // Get data from related models
            $arrangement = $episode->musicArrangements->first();
            $editorWork = $episode->editorWorks->where('work_type', 'main_episode')->first();
            $seEditing = $episode->soundEngineerEditings->where('status', 'approved')->first() 
                        ?? $episode->soundEngineerEditings->first();
            
            // Format dates
            $tglTayang = $episode->air_date ? $episode->air_date->format('j-M') : '-';
            $tglShooting = $episode->production_date ? $episode->production_date->format('j-M') : '-';
            $tglAudioFinal = ($seEditing && $seEditing->approved_at) ? $seEditing->approved_at->format('j-M') : '-';

            // Employee Names (Handles former employees via withTrashed)
            $editorName = $editorWork->createdBy->name ?? '-';
            $arrangerName = $arrangement->createdBy->name ?? '-';
            $mixingName = ($seEditing && $seEditing->soundEngineer) ? $seEditing->soundEngineer->name : '-';
            
            return [
                'id' => $episode->id,
                'program_name' => $episode->program->name ?? 'Hope Music',
                'tgl_tayang_youtube' => $tglTayang,
                'nama_penyanyi_pemusik' => ($arrangement && $arrangement->singer_name) ? $arrangement->singer_name : ($arrangement->singer->name ?? '-'),
                'judul_lagu' => ($arrangement && $arrangement->song_title) ? $arrangement->song_title : ($arrangement->song->title ?? '-'),
                'tipe' => ($episode->format_type && $episode->air_date) 
                        ? $episode->format_type . ' - ' . $episode->air_date->translatedFormat('l') 
                        : ($episode->format_type ?? '-'),
                'keterangan' => $arrangement->arrangement_notes ?? '-',
                'editor' => $editorName,
                'tgl_shooting' => $tglShooting,
                'tgl_audio_final' => $tglAudioFinal,
                'arranger' => $arrangerName,
                'mixing_mastering' => $mixingName,
                'link_youtube' => $episode->broadcastingWorks->where('status', 'published')->first()->youtube_url ?? '-',
                'link_arrangement' => $arrangement->file_link ?? '-',
                'group_members' => ($arrangement && $arrangement->is_group && $arrangement->group_members) 
                                    ? implode(', ', $arrangement->group_members) 
                                    : '-',
                'promotion_status' => $episode->promotionWorks->where('status', 'published')->first()->status 
                                     ?? ($episode->promotionWorks->first()->status ?? '-'),
                'promotion_links' => ($promo = $episode->promotionWorks->where('status', 'published')->first() ?? $episode->promotionWorks->first())
                                     ? implode(', ', (array)($promo->social_media_links ?? []))
                                     : '-',
                'file_name' => $this->generateFileName($episode, $arrangement),
                
                // Trackings Info for Frontend usage (not Excel)
                'full_air_date' => $episode->air_date ? $episode->air_date->format('Y-m-d') : null,
                'workflow_status' => $episode->current_workflow_state,
                'is_overdue' => $episode->deadlines->contains(function($d) {
                    return !$d->is_completed && $d->deadline_date < now();
                }),
                'deadlines' => $episode->deadlines->map(function($d) {
                    return [
                        'process_name' => $d->process_name,
                        'deadline_date' => $d->deadline_date ? $d->deadline_date->format('Y-m-d') : null,
                        'is_completed' => (bool)$d->is_completed,
                        'completed_at' => $d->completed_at ? $d->completed_at->format('Y-m-d') : null,
                        'completed_by' => $d->completedBy->name ?? null,
                        'on_time' => (bool)$d->on_time
                    ];
                })
            ];
        });
    }

    /**
     * Generate file name based on pattern: HM{year}_{num}_{singer}-{song}_{type}
     */
    private function generateFileName($episode, $arrangement): string
    {
        if (!$arrangement) return '-';

        $year = $episode->air_date ? $episode->air_date->format('Y') : date('Y');
        $num = str_pad($episode->episode_number ?? '0', 3, '0', STR_PAD_LEFT);
        $singer = $arrangement->singer_name ?? ($arrangement->singer->name ?? 'Unknown');
        $song = $arrangement->song_title ?? ($arrangement->song->title ?? 'Unknown');
        $type = $episode->format_type . ($episode->air_date ? ' - ' . $episode->air_date->translatedFormat('l') : '');

        return "HM{$year}_{$num}_{$singer}-{$song}_{$type}";
    }
}
