<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionWork;
use App\Models\Notification;
use App\Models\CreativeWork;
use App\Models\DesignGrafisWork;
use App\Models\Episode;
use App\Models\InventoryItem;
use App\Models\ProductionEquipment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\PromotionActivityLog;
use App\Models\ProductionEquipmentTransfer;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use App\Helpers\FileUploadHelper;
use App\Helpers\ProgramManagerAuthorization;
use App\Helpers\MusicProgramAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PromosiController extends Controller
{
    /**
     * Get promotion works for current user
     * GET /api/live-tv/promosi/works
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Auto-mark BTS/talent Promotion Works as published bila episode sudah sampai QC/Broadcasting/Sharing (agar status "Sedang Dikerjakan" tidak tetap saat workflow sudah lanjut)
            $this->syncBtsPromotionWorkStatusWhenEpisodeProgressed();

            // Build cache key
            $cacheKey = 'promosi_index_' . md5(json_encode([
                'user_id' => $user->id,
                'status' => $request->get('status'),
                'page' => $request->get('page', 1)
            ]));

            // Use cache with very short TTL for testing/sync accuracy
            $works = QueryOptimizer::rememberForUser($cacheKey, $user->id, 5, function () use ($request, $user) {
                $query = PromotionWork::with(['episode.program.productionTeam', 'episode.creativeWork', 'createdBy', 'reviewedBy']);

                // Filter by status (Handle comma-separated multiple statuses)
                if ($request->has('status')) {
                    $statuses = explode(',', $request->status);
                    if (count($statuses) > 1) {
                        $query->whereIn('status', $statuses);
                    } else {
                        $query->where('status', $request->status);
                    }
                }
                
                // Prioritize works with episodes. Filter by program category can be added here if needed
                // but we keep it broad for now to ensure visibility.
                $query->whereHas('episode');

                // Filter by episode
                if ($request->has('episode_id')) {
                    $query->where('episode_id', $request->episode_id);
                }

                return $query->orderBy('created_at', 'desc')->paginate(15);
            });

            // Untuk sharing works: selalu isi social_media_links dari BroadcastingWork agar Link Website/YouTube terbaru tampil
            $sharingTypes = ['share_facebook', 'share_wa_group', 'story_ig', 'reels_facebook'];
            $episodeIds = collect($works->items())
                ->filter(fn ($w) => in_array($w->work_type ?? '', $sharingTypes, true))
                ->pluck('episode_id')
                ->unique()
                ->filter()
                ->values();
            $broadcastingByEpisode = [];
            if ($episodeIds->isNotEmpty()) {
                $broadcastingByEpisode = \App\Models\BroadcastingWork::whereIn('episode_id', $episodeIds)
                    ->get()
                    ->keyBy('episode_id');
            }
            collect($works->items())->each(function ($work) use ($sharingTypes, $broadcastingByEpisode) {
                if (!in_array($work->work_type ?? '', $sharingTypes, true)) {
                    return $work;
                }
                $bc = $broadcastingByEpisode->get($work->episode_id);
                if (!$bc) {
                    return $work;
                }
                $social = $work->social_media_links ?? [];
                if ($bc->youtube_url !== null) {
                    $social['youtube_url'] = $bc->youtube_url;
                }
                if ($bc->website_url !== null) {
                    $social['website_url'] = $bc->website_url;
                }
                if ($bc->thumbnail_path !== null) {
                    $social['thumbnail_path'] = $bc->thumbnail_path;
                }
                $work->social_media_links = $social;
                return $work;
            });

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Promotion works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving promotion works: ' . $e->getMessage()
            ], 500);
    }
    }

    /**
     * Jika episode sudah sampai QC/Broadcasting/Sharing, tandai Promotion Work BTS/talent untuk episode itu sebagai published
     * agar status tidak tetap "Sedang Dikerjakan" dan tombol Selesai tidak muncul lagi.
     */
    private function syncBtsPromotionWorkStatusWhenEpisodeProgressed(): void
    {
        // Only auto-publish metadata/resource types, NOT the main video work 
        // that Editor Promosi still needs to handle if there are revisions.
        $btsTypes = ['bts_photo']; // Removed bts_video to allow manual/explicit completion
        $sharingTypes = ['share_facebook', 'share_wa_group', 'story_ig', 'reels_facebook'];

        $btsWorks = PromotionWork::whereIn('work_type', $btsTypes)
            ->whereIn('status', ['shooting', 'editing'])
            ->get(['id', 'episode_id']);

        if ($btsWorks->isEmpty()) {
            return;
        }

        $episodeIds = $btsWorks->pluck('episode_id')->unique()->filter()->values();
        $broadcastingCompleted = \App\Models\BroadcastingWork::whereIn('episode_id', $episodeIds)
            ->whereIn('status', ['completed', 'published'])
            ->pluck('episode_id')
            ->unique();
        $hasSharingWorks = PromotionWork::whereIn('episode_id', $episodeIds)
            ->whereIn('work_type', $sharingTypes)
            ->pluck('episode_id')
            ->unique();

        $episodesProgressed = $broadcastingCompleted->merge($hasSharingWorks)->unique();

        $idsToPublish = $btsWorks->filter(function ($work) use ($episodesProgressed) {
            return $episodesProgressed->contains($work->episode_id);
        })->pluck('id');

        if ($idsToPublish->isNotEmpty()) {
            PromotionWork::whereIn('id', $idsToPublish)->update(['status' => 'published']);
            QueryOptimizer::clearAllIndexCaches();
        }
    }

    /**
     * Store new promotion work (optional, biasanya auto-create dari Producer)
     * POST /api/live-tv/promosi/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:bts_video,bts_photo,highlight_ig,highlight_facebook,highlight_tv,story_ig,reels_facebook,tiktok,website_content,whatsapp_story',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'shooting_date' => 'nullable|date',
                'shooting_time' => 'nullable|date_format:H:i',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
    }

            $work = PromotionWork::create([
                'episode_id' => $request->episode_id,
                'created_by' => $user->id,
                'work_type' => $request->work_type,
                'title' => $request->title,
                'description' => $request->description,
                'shooting_date' => $request->shooting_date,
                'shooting_time' => $request->shooting_time,
                'status' => 'planning'
            ]);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->load(['episode', 'createdBy']),
                'message' => 'Promotion work created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating promotion work: ' . $e->getMessage()
            ], 500);
    }
    }

    /**
     * Terima Jadwal Syuting
     * POST /api/live-tv/promosi/works/{id}/accept-schedule
     */
    public function acceptSchedule(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'shooting_date' => 'nullable|date',
                'shooting_time' => 'nullable|date_format:H:i',
                'location_data' => 'nullable|array',
                'shooting_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::with(['episode.program'])->findOrFail($id);

            // Get shooting schedule from Creative Work
            $creativeWork = CreativeWork::where('episode_id', $work->episode_id)
                ->where('status', 'approved')
                ->first();

            if ($creativeWork && $creativeWork->shooting_schedule) {
                $shootingSchedule = Carbon::parse($creativeWork->shooting_schedule);
                $updateData = [
                    'shooting_date' => $request->shooting_date ?? $shootingSchedule->format('Y-m-d'),
                    'shooting_time' => $request->shooting_time ?? $shootingSchedule->format('H:i'),
                    'location_data' => $request->location_data ?? [
                        'location' => $creativeWork->shooting_location ?? null
                    ],
                    'shooting_notes' => $request->shooting_notes
                ];
            } else {
                $updateData = [];
                if ($request->has('shooting_date')) $updateData['shooting_date'] = $request->shooting_date;
                if ($request->has('shooting_time')) $updateData['shooting_time'] = $request->shooting_time;
                if ($request->has('location_data')) $updateData['location_data'] = $request->location_data;
                if ($request->has('shooting_notes')) $updateData['shooting_notes'] = $request->shooting_notes;
            }

            $work->update($updateData);

            // Activity log
            $this->logActivity($work, $user, 'schedule_accepted', 'Jadwal syuting diterima', [
                'shooting_date' => $work->shooting_date,
                'shooting_time' => $work->shooting_time,
                'location_data' => $work->location_data
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('promosi_schedule_accepted', $work, [
                'shooting_date' => $work->shooting_date,
                'shooting_time' => $work->shooting_time,
                'location_data' => $work->location_data
            ], $request);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Shooting schedule accepted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting schedule: ' . $e->getMessage()
            ], 500);
    }
    }

    /**
     * Terima Pekerjaan
     * POST /api/live-tv/promosi/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::with(['episode.program'])->findOrFail($id);

            // Only allow accept if status is planning
            if ($work->status !== 'planning') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is planning',
                    'current_status' => $work->status
                ], 400);
            }

            $oldData = $work->toArray();
            
            // Update status to shooting and set created_by
            $work->update([
                'status' => 'shooting',
                'created_by' => $user->id
            ]);

            // Log Workflow State for Accept Work
            $workflowService = app(\App\Services\WorkflowStateService::class);
            $workflowService->updateWorkflowState(
                $work->episode,
                'promotion',
                'promotion',
                $user->id,
                "Promotion work accepted by {$user->name} ({$work->work_type})",
                $user->id,
                [
                    'action' => 'promosi_work_accepted',
                    'work_type' => $work->work_type,
                    'promotion_work_id' => $work->id
                ]
            );

            // Activity log
            $this->logActivity($work, $user, 'work_accepted', 'Pekerjaan diterima oleh ' . $user->name, [
                'old_status' => $oldData['status'],
                'new_status' => 'shooting',
                'work_type' => $work->work_type
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('promosi_work_accepted', $work, [
                'old_status' => $oldData['status'],
                'new_status' => 'shooting',
                'assigned_to' => $user->id
            ], $request);

            QueryOptimizer::clearAllIndexCaches();

            // Custom message berdasarkan work type
            $messages = [
                'share_facebook' => 'Work accepted successfully. You can now share the website link to Facebook and upload proof (file or link).',
                'share_wa_group' => 'Work accepted successfully. You can now share to WA group and upload proof (file or link).',
                'story_ig' => 'Work accepted successfully. You can now create and upload Story IG highlight with proof (file or link).',
                'whatsapp_story' => 'Work accepted successfully. You can now create and upload WhatsApp Story with proof (file or link/printscreen).',
                'reels_facebook' => 'Work accepted successfully. You can now create and upload Reels Facebook highlight with proof (file or link).',
                'bts_video' => 'Work accepted successfully. You can now upload BTS video and talent photos.',
                'bts_photo' => 'Work accepted successfully. You can now upload BTS video and talent photos.'
            ];
            
            $message = $messages[$work->work_type] ?? 'Work accepted successfully. You can now proceed with the task.';

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit BTS Video Link
     * POST /api/live-tv/roles/promosi/works/{id}/upload-bts-video
     */
    public function uploadBTSVideo(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_link' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            // RELAXED AUTHORIZATION: Allow any user with 'Promotion' role OR the creator to upload links
            $isPromotionStaff = MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion');
            if (!$isPromotionStaff && $work->created_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $fileLinks = $work->file_links ?? [];
            if (is_array($fileLinks) && isset($fileLinks['bts_file_links'])) {
                $fileLinks = array_merge(
                    array_filter($fileLinks['bts_file_links'] ?? [], fn($item) => is_array($item)),
                    array_filter($fileLinks['talent_photo_links'] ?? [], fn($item) => is_array($item))
                );
            }
            $fileLinks = is_array($fileLinks) ? array_values(array_filter($fileLinks, fn($item) => is_array($item) && (($item['type'] ?? '') !== 'bts_video'))) : [];
            $fileLinks[] = [
                'type' => 'bts_video',
                'file_link' => $request->file_link,
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $user->id
            ];

            $work->update(['file_links' => array_values($fileLinks)]);
            QueryOptimizer::clearAllIndexCaches();

            // Activity log
            $this->logActivity($work, $user, 'bts_video_uploaded', 'Link video BTS diunggah', [
                'file_link' => $request->file_link
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Link BTS video berhasil disimpan.',
                'data' => $work->fresh(['episode', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Submit Talent Photo Links
     * POST /api/live-tv/roles/promosi/works/{id}/upload-talent-photos
     */
    public function uploadTalentPhotos(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_links' => 'required_without:file_paths|array|min:1',
                'file_links.*' => 'required|url',
                'file_paths' => 'sometimes|array|min:1',
                'file_paths.*' => 'sometimes|file'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            // Relaxed authorization: Allow users with 'Promotion' role OR the creator to upload
            $isPromotionStaff = MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion');
            if (!$isPromotionStaff && $work->created_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $fileLinks = $work->file_links ?? [];
            if (!is_array($fileLinks)) {
                $fileLinks = json_decode(is_string($fileLinks) ? $fileLinks : '[]', true) ?: [];
            }
            if (is_array($fileLinks) && isset($fileLinks['bts_file_links'])) {
                $fileLinks = array_merge(
                    array_filter($fileLinks['bts_file_links'] ?? [], fn($item) => is_array($item)),
                    array_filter($fileLinks['talent_photo_links'] ?? [], fn($item) => is_array($item))
                );
            }
            $fileLinks = is_array($fileLinks) ? array_values(array_filter($fileLinks, fn($item) => is_array($item) && (($item['type'] ?? '') !== 'talent_photo'))) : [];

            if ($request->has('file_links')) {
                foreach ($request->file_links as $link) {
                    $fileLinks[] = [
                        'type' => 'talent_photo',
                        'file_link' => $link,
                        'uploaded_at' => now()->toDateTimeString(),
                        'uploaded_by' => $user->id
                    ];
                }
            }

            $filePaths = $work->file_paths ?? [];
            if (!is_array($filePaths)) {
                $filePaths = json_decode($filePaths, true) ?: [];
            }
            $filePaths = array_filter($filePaths, fn($item) => ($item['type'] ?? '') !== 'talent_photo');

            if ($request->hasFile('file_paths')) {
                foreach ($request->file('file_paths') as $uploaded) {
                    $stored = FileUploadHelper::uploadFile(
                        $uploaded,
                        'promosi/talent-photos',
                        ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'],
                        ['jpg', 'jpeg', 'png', 'webp'],
                        5 * 1024 * 1024,
                        false
                    );

                    $filePaths[] = [
                        'type' => 'talent_photo',
                        'file_path' => $stored['file_path'] ?? null,
                        'file_id' => $stored['id'] ?? null,
                        'original_name' => $stored['original_name'] ?? $uploaded->getClientOriginalName(),
                        'uploaded_at' => now()->toDateTimeString(),
                        'uploaded_by' => $user->id
                    ];
                }
            }

            $work->update([
                'file_links' => array_values($fileLinks),
                'file_paths' => array_values($filePaths),
            ]);
            QueryOptimizer::clearAllIndexCaches();

            // Activity log
            $this->logActivity(
                $work,
                $user,
                'talent_photos_uploaded',
                'Foto talent diunggah (' . (count($request->file_links ?? []) + count($request->file('file_paths') ?? [])) . ' bukti)',
                [
                    'link_count' => count($request->file_links ?? []),
                    'file_count' => count($request->file('file_paths') ?? []),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Bukti foto talent berhasil disimpan (file / link).',
                'data' => $work->fresh(['episode', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }



    /**
     * Upload BTS Content (Legacy/Alternative endpoint)
     * POST /api/live-tv/promosi/works/{id}/upload-bts
     *
     * Now accepts either file uploads or links and delegates to uploadTalentPhotos.
     */
    public function uploadBTSContent(Request $request, int $id): JsonResponse
    {
        if ($request->hasFile('file_paths') || $request->has('file_links')) {
            return $this->uploadTalentPhotos($request, $id);
        }

        return response()->json([
            'success' => false,
            'message' => 'Please provide either file_paths (files) or file_links (URLs) for BTS content.'
        ], 400);
    }

    /**
     * Selesaikan Pekerjaan
     * POST /api/live-tv/promosi/works/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::with(['episode.program.productionTeam'])->findOrFail($id);

            // Check if user has access
            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // Boleh complete: shooting → editing; editing/approved → published; published → no-op (sudah selesai)
            $allowedForComplete = ['shooting', 'editing', 'approved', 'published'];
            if (!in_array($work->status, $allowedForComplete, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is shooting, editing, approved, or published.',
                    'allowed_statuses' => $allowedForComplete
                ], 400);
            }

            // Validate: BTS video and talent photos when completing from shooting. When already editing, skip (hanya tandai selesai).
            $isBTSWork = in_array($work->work_type, ['bts_video', 'bts_photo']);
            $filePaths = $work->file_paths ?? [];
            $fileLinks = $work->file_links ?? [];
            $hasBTSVideo = false;
            $hasTalentPhotos = false;
            $hasAnyFile = false;

            if (is_array($filePaths)) {
                foreach ($filePaths as $file) {
                    if (!is_array($file)) {
                        continue;
                    }
                    if (isset($file['type'])) {
                        $hasAnyFile = true;
                        if ($file['type'] === 'bts_video') {
                            $hasBTSVideo = true;
                        }
                        if ($file['type'] === 'talent_photo') {
                            $hasTalentPhotos = true;
                        }
                    }
                }
            }
            if (is_array($fileLinks)) {
                foreach ($fileLinks as $link) {
                    if (!is_array($link)) {
                        continue;
                    }
                    if (isset($link['type'])) {
                        $hasAnyFile = true;
                        if ($link['type'] === 'bts_video') {
                            $hasBTSVideo = true;
                        }
                        if ($link['type'] === 'talent_photo') {
                            $hasTalentPhotos = true;
                        }
                    }
                }
            }

            if ($work->status === 'shooting') {
                if ($isBTSWork && (!$hasBTSVideo || !$hasTalentPhotos)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please upload both BTS video and talent photos before completing work.',
                        'missing' => [
                            'bts_video' => !$hasBTSVideo,
                            'talent_photos' => !$hasTalentPhotos
                        ]
                    ], 400);
                }
                if (!$isBTSWork && !$hasAnyFile && empty($request->completion_notes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please provide proof (upload file/link) or completion notes before completing work.'
                    ], 400);
                }
            }

            $oldData = $work->toArray();

            // shooting → editing; editing/approved → published; published → tetap published (no-op)
            $newStatus = $work->status === 'shooting' ? 'editing' : 'published';
            if ($work->status !== 'published') {
                $work->update([
                    'status' => $newStatus,
                    'shooting_notes' => ($work->shooting_notes ? $work->shooting_notes . "\n\n" : '') .
                        ($request->completion_notes ? "[Selesai] " . $request->completion_notes : '')
                ]);
            }

            // Log Workflow State for Complete Work
            $workflowService = app(\App\Services\WorkflowStateService::class);
            $workflowService->updateWorkflowState(
                $work->episode,
                'promotion',
                'promotion',
                $user->id,
                "Promotion work marked as {$newStatus} by {$user->name} ({$work->work_type})",
                $user->id,
                [
                    'action' => 'promosi_work_completed',
                    'new_status' => $newStatus,
                    'notes' => $request->completion_notes,
                    'work_type' => $work->work_type
                ]
            );

            $episode = $work->episode;
            $productionTeam = $episode->program?->productionTeam;
            $producer = $productionTeam?->producer;
            $episodeTitleSuffix = $episode && $episode->title ? ": {$episode->title}" : '';

            // Hanya jalankan notifikasi Producer + auto-create Design Grafis + Editor Promosi saat pertama selesai (shooting → editing)
            if ($newStatus === 'published') {
                if ($producer) {
                    \App\Models\Notification::create([
                        'user_id' => $producer->id,
                        'type' => 'promosi_work_completed',
                        'title' => 'Pekerjaan Promosi Ditandai Selesai',
                        'message' => "Promosi telah menandai pekerjaan BTS/foto untuk Episode {$episode->episode_number} sebagai selesai.",
                        'data' => [
                            'promotion_work_id' => $work->id,
                            'episode_id' => $work->episode_id,
                            'completion_notes' => $request->completion_notes
                        ]
                    ]);
                }
                $this->logActivity($work, $user, 'work_completed', 'Pekerjaan ditandai selesai (published)', [
                    'old_status' => $oldData['status'],
                    'new_status' => 'published'
                ]);
                ControllerSecurityHelper::logCrud('promosi_work_completed', $work, [
                    'old_status' => $oldData['status'],
                    'new_status' => 'published'
                ], $request);
                QueryOptimizer::clearAllIndexCaches();
                return response()->json([
                    'success' => true,
                    'message' => 'Work marked as completed.',
                    'data' => $work->fresh(['episode', 'createdBy'])
                ]);
            }

            // Notify Producer (shooting → editing)
            if ($producer) {
                \App\Models\Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'promosi_work_completed',
                    'title' => 'Pekerjaan Promosi Selesai',
                    'message' => "Promosi telah menyelesaikan pekerjaan untuk Episode {$episode->episode_number}. BTS video dan foto talent telah di-upload.",
                    'data' => [
                        'promotion_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'bts_video' => $hasBTSVideo,
                        'talent_photos' => $hasTalentPhotos,
                        'completion_notes' => $request->completion_notes
                    ]
                ]);
            }

            // Auto-create DesignGrafisWork untuk Thumbnail BTS (perlu foto talent dari Promosi)
            $existingThumbnailBTS = \App\Models\DesignGrafisWork::where('episode_id', $work->episode_id)
                ->where('work_type', 'thumbnail_bts')
                ->first();

            // Get BTS video and talent photos paths/links
            $btsVideoPath = null;
            $btsVideoLink = null;
            $talentPhotoPaths = [];
            $talentPhotoLinks = [];
            
            // Check file_paths (backward compatibility)
            if (!empty($work->file_paths)) {
                $filePaths = is_array($work->file_paths) ? $work->file_paths : json_decode($work->file_paths, true);
                foreach ($filePaths as $file) {
                    if (isset($file['type'])) {
                        if ($file['type'] === 'bts_video') {
                            $btsVideoPath = $file['file_path'] ?? $file;
                        } elseif ($file['type'] === 'talent_photo') {
                            $talentPhotoPaths[] = $file['file_path'] ?? $file;
                        }
                    }
                }
            }
            
            // Check file_links (new: external storage links)
            if (!empty($work->file_links)) {
                $fileLinks = is_array($work->file_links) ? $work->file_links : json_decode($work->file_links, true);
                foreach ($fileLinks as $link) {
                    if (isset($link['type'])) {
                        if ($link['type'] === 'bts_video') {
                            $btsVideoLink = $link['file_link'] ?? $link;
                        } elseif ($link['type'] === 'talent_photo') {
                            $talentPhotoLinks[] = $link['file_link'] ?? $link;
                        }
                    }
                }
            }

            // Use file_links if available, otherwise use file_paths (priority logic)
            $finalBTSVideo = $btsVideoLink ?? $btsVideoPath;
            $finalTalentPhotos = !empty($talentPhotoLinks) ? $talentPhotoLinks : $talentPhotoPaths;
            
            // 1. Thumbnail BTS
            if (!$existingThumbnailBTS && !empty($finalTalentPhotos)) {
                $designGrafisWork = \App\Models\DesignGrafisWork::create([
                    'episode_id' => $work->episode_id,
                    'work_type' => 'thumbnail_bts',
                    'title' => "Thumbnail BTS - Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}",
                    'description' => "Design thumbnail BTS untuk Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}. Foto talent dari Promosi sudah tersedia.",
                    'status' => 'draft',
                    'source_files' => [
                        'promotion_work_id' => $work->id,
                        'talent_photos' => $finalTalentPhotos, // Use file_links if available
                        'talent_photo_paths' => $talentPhotoPaths, // Backward compatibility
                        'talent_photo_links' => $talentPhotoLinks, // New: External storage links
                        'bts_video' => $finalBTSVideo, // Use file_link if available
                        'bts_video_path' => $btsVideoPath, // Backward compatibility
                        'bts_video_link' => $btsVideoLink, // New: External storage link
                        'available' => true,
                        'fetched_at' => now()->toDateTimeString()
                    ],
                    'created_by' => $user->id
                ]);

                // Notify Design Grafis
                $designGrafisUsers = \App\Models\User::where('role', 'Graphic Design')->get();
                $notificationsToInsert = [];
                $now = now();

                foreach ($designGrafisUsers as $designUser) {
                    $notificationsToInsert[] = [
                        'user_id' => $designUser->id,
                        'type' => 'promosi_files_available_for_design',
                        'title' => 'File Promosi Tersedia untuk Design BTS',
                        'message' => "Promosi telah mengupload foto talent untuk Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}. Design Grafis work untuk Thumbnail BTS sudah dibuat.",
                        'data' => json_encode([ // Encode data to JSON
                            'promotion_work_id' => $work->id,
                            'design_grafis_work_id' => $designGrafisWork->id,
                            'episode_id' => $work->episode_id,
                            'talent_photos_count' => count($finalTalentPhotos ?? []),
                            'bts_video_available' => !empty($finalBTSVideo)
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($notificationsToInsert)) {
                    \App\Models\Notification::insert($notificationsToInsert);
                }
            } elseif ($existingThumbnailBTS && !empty($finalTalentPhotos)) {
                // Update existing DesignGrafisWork dengan file terbaru dari Promosi
                $existingSourceFiles = $existingThumbnailBTS->source_files ?? [];
                $existingThumbnailBTS->update([
                    'source_files' => array_merge($existingSourceFiles, [
                        'promotion_work_id' => $work->id,
                        'talent_photos' => $finalTalentPhotos, // Use file_links if available
                        'talent_photo_paths' => $talentPhotoPaths, // Backward compatibility
                        'talent_photo_links' => $talentPhotoLinks, // New: External storage links
                        'bts_video' => $finalBTSVideo, // Use file_link if available
                        'bts_video_path' => $btsVideoPath, // Backward compatibility
                        'bts_video_link' => $btsVideoLink, // New: External storage link
                        'updated_at' => now()->toDateTimeString()
                    ]),
                    'status' => 'draft' // Reset status ke draft agar bisa di-accept lagi
                ]);

                // Notify Design Grafis
                $designGrafisUsers = \App\Models\User::where('role', 'Graphic Design')->get();
                $notificationsToInsert = [];
                $now = now();
                
                foreach ($designGrafisUsers as $designUser) {
                    $notificationsToInsert[] = [
                        'user_id' => $designUser->id,
                        'type' => 'promosi_files_updated_for_design',
                        'title' => 'File Promosi Diperbarui untuk Design BTS',
                        'message' => "Promosi telah mengupdate foto talent untuk Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}. Design Grafis work untuk Thumbnail BTS telah diperbarui.",
                        'data' => json_encode([
                            'promotion_work_id' => $work->id,
                            'design_grafis_work_id' => $existingThumbnailBTS->id,
                            'episode_id' => $work->episode_id,
                            'talent_photos_count' => count($finalTalentPhotos ?? [])
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($notificationsToInsert)) {
                    \App\Models\Notification::insert($notificationsToInsert);
                }
            }

            // 2. Thumbnail Youtube
            $existingThumbnailYoutube = \App\Models\DesignGrafisWork::where('episode_id', $work->episode_id)
                ->where('work_type', 'thumbnail_youtube')
                ->first();

            if (!$existingThumbnailYoutube && !empty($finalTalentPhotos)) {
                $designGrafisWork = \App\Models\DesignGrafisWork::create([
                    'episode_id' => $work->episode_id,
                    'work_type' => 'thumbnail_youtube',
                    'title' => "Thumbnail YouTube - Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}",
                    'description' => "Design thumbnail YouTube untuk Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}. Foto talent dari Promosi sudah tersedia.",
                    'status' => 'draft',
                    'source_files' => [
                        'promotion_work_id' => $work->id,
                        'talent_photos' => $finalTalentPhotos,
                        'talent_photo_paths' => $talentPhotoPaths,
                        'talent_photo_links' => $talentPhotoLinks,
                        'available' => true,
                        'fetched_at' => now()->toDateTimeString()
                    ],
                    'created_by' => $user->id
                ]);

                // Notify Design Grafis
                $designGrafisUsers = \App\Models\User::where('role', 'Graphic Design')->get();
                $notificationsToInsert = [];
                $now = now();

                foreach ($designGrafisUsers as $designUser) {
                    $notificationsToInsert[] = [
                        'user_id' => $designUser->id,
                        'type' => 'promosi_files_available_for_design_yt',
                        'title' => 'File Promosi Tersedia untuk Design YouTube',
                        'message' => "Promosi telah mengupload foto talent untuk Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}. Design Grafis work untuk Thumbnail YouTube sudah dibuat.",
                        'data' => json_encode([
                            'promotion_work_id' => $work->id,
                            'design_grafis_work_id' => $designGrafisWork->id,
                            'episode_id' => $work->episode_id,
                            'talent_photos_count' => count($finalTalentPhotos ?? [])
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($notificationsToInsert)) {
                    \App\Models\Notification::insert($notificationsToInsert);
                }
            } elseif ($existingThumbnailYoutube && !empty($finalTalentPhotos)) {
                // Update existing DesignGrafisWork
                $existingSourceFiles = $existingThumbnailYoutube->source_files ?? [];
                $existingThumbnailYoutube->update([
                    'source_files' => array_merge($existingSourceFiles, [
                        'promotion_work_id' => $work->id,
                        'talent_photos' => $finalTalentPhotos,
                        'talent_photo_paths' => $talentPhotoPaths,
                        'talent_photo_links' => $talentPhotoLinks,
                        'updated_at' => now()->toDateTimeString()
                    ]),
                    'status' => 'draft' // Reset status so it can be re-accepted
                ]);
            }

            // Auto-create PromotionWork untuk Editor Promosi (multiple work types)
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
                    // Get BTS file paths/links
                    $btsFiles = [];
                    $btsFileLinks = [];
                    
                    // Check file_paths (backward compatibility)
                    if (!empty($work->file_paths)) {
                        $filePaths = is_array($work->file_paths) ? $work->file_paths : json_decode($work->file_paths, true);
                        foreach ($filePaths as $file) {
                            if (is_array($file) && isset($file['type']) && $file['type'] === 'bts_video') {
                                $btsFiles[] = $file;
                            }
                        }
                    }
                    
                    // Check file_links (new: external storage links)
                    if (!empty($work->file_links)) {
                        $fileLinks = is_array($work->file_links) ? $work->file_links : json_decode($work->file_links, true);
                        foreach ($fileLinks as $link) {
                            if (is_array($link) && isset($link['type']) && $link['type'] === 'bts_video') {
                                $btsFileLinks[] = $link;
                            }
                        }
                    }

                    $promotionWork = \App\Models\PromotionWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => $workType,
                        'title' => "{$titlePrefix} - Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}",
                        'description' => "Editing task untuk {$titlePrefix}. File referensi dari Promosi (BTS) sudah tersedia untuk Episode " . ($episode->episode_number ?? '') . "{$episodeTitleSuffix}.",
                        'status' => 'editing', // Siap untuk diterima Editor Promosi
                        'file_paths' => [
                            'promotion_work_id' => $work->id,
                            'bts_files' => $btsFiles, // Backward compatibility
                            'talent_photos' => $finalTalentPhotos, // Use file_links if available
                            'talent_photo_paths' => $talentPhotoPaths, // Backward compatibility
                            'talent_photo_links' => $talentPhotoLinks, // New: External storage links
                            'available' => true,
                            'fetched_at' => now()->toDateTimeString()
                        ],
                        'file_links' => [
                            'promotion_work_id' => $work->id,
                            'bts_file_links' => $btsFileLinks, // New: External storage links
                            'talent_photo_links' => $talentPhotoLinks, // New: External storage links
                            'available' => true,
                            'fetched_at' => now()->toDateTimeString()
                        ],
                        'created_by' => $user->id // Assign ke Promosi yang submit (akan diganti saat Editor Promosi accept)
                    ]);
                    $createdPromotionWorks[] = $promotionWork;
                } else {
                    // Update existing PromotionWork dengan file terbaru dari Promosi
                    $existingFilePaths = $existingPromotionWork->file_paths ?? [];
                    $existingFileLinks = $existingPromotionWork->file_links ?? [];
                    $btsFiles = [];
                    $btsFileLinks = [];
                    
                    // Check file_paths (backward compatibility)
                    if (!empty($work->file_paths)) {
                        $filePaths = is_array($work->file_paths) ? $work->file_paths : json_decode($work->file_paths, true);
                        foreach ($filePaths as $file) {
                            if (is_array($file) && isset($file['type']) && $file['type'] === 'bts_video') {
                                $btsFiles[] = $file;
                            }
                        }
                    }
                    
                    // Check file_links (new: external storage links)
                    if (!empty($work->file_links)) {
                        $fileLinks = is_array($work->file_links) ? $work->file_links : json_decode($work->file_links, true);
                        foreach ($fileLinks as $link) {
                            if (is_array($link) && isset($link['type']) && $link['type'] === 'bts_video') {
                                $btsFileLinks[] = $link;
                            }
                        }
                    }

                    $existingPromotionWork->update([
                        'file_paths' => array_merge($existingFilePaths, [
                            'promotion_work_id' => $work->id,
                            'bts_files' => $btsFiles, // Backward compatibility
                            'talent_photos' => $finalTalentPhotos, // Use file_links if available
                            'talent_photo_paths' => $talentPhotoPaths, // Backward compatibility
                            'talent_photo_links' => $talentPhotoLinks, // New: External storage links
                            'updated_at' => now()->toDateTimeString()
                        ]),
                        'file_links' => array_merge($existingFileLinks, [
                            'promotion_work_id' => $work->id,
                            'bts_file_links' => $btsFileLinks, // New: External storage links
                            'talent_photo_links' => $talentPhotoLinks, // New: External storage links
                            'updated_at' => now()->toDateTimeString()
                        ])
                    ]);
                    $createdPromotionWorks[] = $existingPromotionWork;
                }
            }

            // Notify Editor Promosi - File dari Promosi sudah tersedia
            $editorPromosiUsers = \App\Models\User::where('role', 'Editor Promotion')->get();
            $notificationsToInsert = [];
            $now = now();
            
            foreach ($editorPromosiUsers as $editorPromosiUser) {
                $notificationsToInsert[] = [
                    'user_id' => $editorPromosiUser->id,
                    'type' => 'promosi_bts_files_available',
                    'title' => 'File BTS dari Promosi Tersedia',
                    'message' => "Promosi telah mengupload BTS video dan foto talent untuk Episode " . ($episode->episode_number ?? '') . ". PromotionWork untuk edit BTS, Highlight, dan Iklan TV sudah dibuat.",
                    'data' => json_encode([
                        'promotion_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'bts_files_available' => !empty($btsFiles) || !empty($btsFileLinks),
                        'bts_video_available' => !empty($finalBTSVideo),
                        'talent_photos_count' => count($finalTalentPhotos ?? []),
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

            if (!empty($notificationsToInsert)) {
                \App\Models\Notification::insert($notificationsToInsert);
            }

            // Activity log
            $this->logActivity($work, $user, 'work_completed', 'Pekerjaan diselesaikan dan dikirim ke tahap editing', [
                'old_status' => $oldData['status'],
                'new_status' => 'editing',
                'bts_video_uploaded' => $hasBTSVideo,
                'talent_photos_uploaded' => $hasTalentPhotos
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('promosi_work_completed', $work, [
                'old_status' => $oldData['status'],
                'new_status' => 'editing',
                'bts_video_uploaded' => $hasBTSVideo,
                'talent_photos_uploaded' => $hasTalentPhotos
            ], $request);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work completed successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
    }
    }

    /**
     * Create Social Media Post
     * POST /api/live-tv/promosi/social-media
     */
    public function createSocialMediaPost(Request $request): JsonResponse
    {
        // TODO: Implement jika diperlukan
        return response()->json(['success' => false, 'message' => 'Social media post feature is not yet implemented'], 501);
    }

    /**
     * Get Social Media Posts
     * GET /api/live-tv/promosi/social-media
     */
    public function getSocialMediaPosts(Request $request): JsonResponse
    {
        // TODO: Implement jika diperlukan
        return response()->json(['success' => true, 'message' => 'Social media posts feature is not yet implemented', 'data' => []], 200);
    }

    /**
     * Submit Social Proof
     * POST /api/live-tv/promosi/social-media/{id}/submit-proof
     *
     * Generic endpoint to attach either links or files as social proof.
     */
    public function submitSocialProof(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$user || (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment())) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'proof_links' => 'sometimes|array',
                'proof_links.*' => 'required|url',
                'files' => 'sometimes|array',
                'files.*' => 'sometimes|file|max:51200',
                'channel' => 'nullable|string|max:100', // e.g. facebook, instagram, wa_group
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::findOrFail($id);

            $socialProof = $work->social_media_proof ?? [];

            if ($request->has('proof_links')) {
                $socialProof['generic_links'] = [
                    'links' => $request->proof_links,
                    'channel' => $request->channel,
                    'notes' => $request->notes,
                    'submitted_at' => now()->toDateTimeString(),
                    'submitted_by' => $user->id,
                ];
            }

            if ($request->hasFile('files')) {
                $storedFiles = [];
                foreach ($request->file('files') as $uploaded) {
                    $stored = FileUploadHelper::uploadFile(
                        $uploaded,
                        'promosi/social-proof',
                        ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'application/pdf', 'video/mp4'],
                        ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'mp4'],
                        100 * 1024 * 1024,
                        false
                    );

                    $storedFiles[] = [
                        'file_path' => $stored['file_path'] ?? null,
                        'file_id' => $stored['id'] ?? null,
                        'original_name' => $stored['original_name'] ?? $uploaded->getClientOriginalName(),
                        'uploaded_at' => now()->toDateTimeString(),
                        'uploaded_by' => $user->id,
                    ];
                }

                $socialProof['generic_files'] = [
                    'files' => $storedFiles,
                    'channel' => $request->channel,
                    'notes' => $request->notes,
                ];
            }

            $work->update(['social_media_proof' => $socialProof]);
            QueryOptimizer::clearAllIndexCaches();

            $this->logActivity(
                $work,
                $user,
                'social_proof_submitted',
                'Social proof submitted (files/links)',
                [
                    'links' => $request->proof_links ?? [],
                    'files_uploaded' => isset($socialProof['generic_files']) ? count($socialProof['generic_files']['files']) : 0,
                    'channel' => $request->channel,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Social proof submitted successfully.',
                'data' => $work->fresh(['episode', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get Statistics
     * GET /api/live-tv/promosi/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $statusStats = PromotionWork::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $myStats = PromotionWork::where('created_by', $user->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $stats = [
            'total_works' => $statusStats->sum(),
            'planning_works' => $statusStats->get('planning', 0),
            'shooting_works' => $statusStats->get('shooting', 0),
            'editing_works' => $statusStats->get('editing', 0),
            'completed_works' => $statusStats->get('published', 0),
            'my_works' => $myStats->sum(),
            'my_completed' => $myStats->get('published', 0),
            'active_loans' => ProductionEquipment::where('requested_by', $user->id)
                ->whereIn('status', ['approved', 'in_use'])
                ->with(['episode.program'])
                ->get(),
            'incoming_handovers' => ProductionEquipmentTransfer::where('to_user_id', $user->id)
                ->where('status', 'pending_accept')
                ->with(['productionEquipment.episode.program', 'fromUser'])
                ->get()
                ->map(function($t) {
                    return [
                        'id' => $t->id,
                        'equipment_request_id' => $t->production_equipment_id,
                        'from_user_id' => $t->transferred_by,
                        'from_user_name' => $t->fromUser ? $t->fromUser->name : 'Unknown',
                        'equipment_list' => $t->productionEquipment ? $t->productionEquipment->equipment_list : [],
                        'notes' => $t->notes,
                        'transferred_at' => $t->transferred_at
                    ];
                })
        ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
    }
    }

    /**
     * Receive Links (from Broadcasting/QC)
     * POST /api/live-tv/promosi/episodes/{id}/receive-links
     *
     * Simple passthrough to attach final YouTube/website links to all related promotion works.
     */
    public function receiveLinks(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated.'], 401);
            }

            $validator = Validator::make($request->all(), [
                'youtube_link' => 'nullable|url',
                'website_link' => 'nullable|url',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $works = PromotionWork::where('episode_id', $id)->get();

            foreach ($works as $work) {
                $meta = $work->social_media_proof ?? [];
                $meta['final_links'] = [
                    'youtube_link' => $request->youtube_link,
                    'website_link' => $request->website_link,
                    'notes' => $request->notes,
                    'received_at' => now()->toDateTimeString(),
                    'received_by' => $user->id,
                ];
                $work->update(['social_media_proof' => $meta]);
            }

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'message' => 'Links received successfully for promotion works.',
                'data' => [
                    'episode_id' => $id,
                    'works_count' => $works->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Accept Promotion Work (alternative workflow)
     * POST /api/live-tv/promosi/works/{id}/accept-promotion-work
     */
    public function acceptPromotionWork(Request $request, int $id): JsonResponse
    {
        // Alias untuk acceptWork
        return $this->acceptWork($request, $id);
    }

    /**
     * Upload file bukti sharing (screenshot dll). Mengembalikan URL untuk dipakai sebagai proof_link.
     * POST /api/live-tv/promosi/works/{id}/upload-sharing-proof
     */
    public function uploadSharingProof(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$user || (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment())) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:20480', // 20MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::findOrFail($id);
            $file = $request->file('file');
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('promosi/sharing-proof', $work->id . '_' . time() . '_' . $safeName, 'public');
            $relativeUrl = Storage::disk('public')->url($path);
            $url = str_starts_with($relativeUrl, 'http') ? $relativeUrl : url($relativeUrl);

            return response()->json([
                'success' => true,
                'message' => 'File bukti berhasil diupload.',
                'url' => $url,
                'path' => $path
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Share Link Website ke Facebook (Strict Link Only)
     * POST /api/live-tv/promosi/works/{id}/share-facebook
     */
    public function shareFacebook(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$user || (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment())) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'proof_link' => 'required|string', // URL atau full URL dari upload
                'facebook_post_url' => 'nullable|url',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            $socialProof = $work->social_media_proof ?? [];
            $socialProof['facebook_share'] = [
                'proof_link' => $request->proof_link,
                'facebook_post_url' => $request->facebook_post_url,
                'shared_at' => now()->toDateTimeString(),
                'shared_by' => $user->id,
                'notes' => $request->notes
            ];

            $work->update([
                'social_media_proof' => $socialProof,
                'status' => 'published'
            ]);

            // Activity log
            $this->logActivity($work, $user, 'shared_facebook', 'Link website dibagikan ke Facebook', [
                'proof_link' => $request->proof_link,
                'facebook_post_url' => $request->facebook_post_url
            ]);

            return response()->json(['success' => true, 'message' => 'Facebook share proof link saved.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Submit IG Story (Strict Link Only)
     * POST /api/live-tv/promosi/works/{id}/upload-story-ig
     */
    public function uploadStoryIG(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'video_link' => 'required|url',
                'proof_link' => 'required|url',
                'story_url' => 'nullable|url',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            $socialProof = $work->social_media_proof ?? [];
            $socialProof['story_ig'] = [
                'video_link' => $request->video_link,
                'proof_link' => $request->proof_link,
                'story_url' => $request->story_url,
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $user->id,
                'notes' => $request->notes
            ];

            $work->update([
                'social_media_proof' => $socialProof,
                'status' => 'published'
            ]);

            // Activity log
            $this->logActivity($work, $user, 'story_ig_uploaded', 'Video highlight Story IG diunggah', [
                'video_link' => $request->video_link,
                'proof_link' => $request->proof_link
            ]);

            return response()->json(['success' => true, 'message' => 'Story IG links saved.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Submit Reels Facebook (Strict Link Only)
     * POST /api/live-tv/promosi/works/{id}/upload-reels-facebook
     */
    public function uploadReelsFacebook(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'video_link' => 'required|url',
                'proof_link' => 'required|url',
                'reels_url' => 'nullable|url',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            $socialProof = $work->social_media_proof ?? [];
            $socialProof['reels_facebook'] = [
                'video_link' => $request->video_link,
                'proof_link' => $request->proof_link,
                'reels_url' => $request->reels_url,
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $user->id,
                'notes' => $request->notes
            ];

            $work->update([
                'social_media_proof' => $socialProof,
                'status' => 'published'
            ]);

            // Activity log
            $this->logActivity($work, $user, 'reels_facebook_uploaded', 'Video highlight Reels Facebook diunggah', [
                'video_link' => $request->video_link,
                'proof_link' => $request->proof_link
            ]);

            return response()->json(['success' => true, 'message' => 'Reels Facebook links saved.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Share WA Group (Strict Link Only)
     * POST /api/live-tv/promosi/works/{id}/share-wa-group
     */
    public function shareWAGroup(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'proof_link' => 'required_without:groups|nullable|url',
                'group_name' => 'nullable|string',
                'notes' => 'nullable|string',
                'groups' => 'sometimes|array',
                'groups.*.proof_link' => 'required|url',
                'groups.*.group_name' => 'nullable|string',
                'groups.*.notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);
            $socialProof = $work->social_media_proof ?? [];
            if (!isset($socialProof['wa_group_shares']) || !is_array($socialProof['wa_group_shares'])) {
                $socialProof['wa_group_shares'] = [];
            }

            // If request has multiple groups (new format)
            if ($request->has('groups') && is_array($request->groups)) {
                foreach ($request->groups as $group) {
                    $socialProof['wa_group_shares'][] = [
                        'proof_link' => $group['proof_link'] ?? '',
                        'group_name' => $group['group_name'] ?? '',
                        'shared_at' => now()->toDateTimeString(),
                        'shared_by' => $user->id,
                        'notes' => $group['notes'] ?? $request->notes
                    ];
                }
            } else {
                // Backward compatibility (old format)
                $socialProof['wa_group_shares'][] = [
                    'proof_link' => $request->proof_link,
                    'group_name' => $request->group_name,
                    'shared_at' => now()->toDateTimeString(),
                    'shared_by' => $user->id,
                    'notes' => $request->notes
                ];
            }

            // Legacy field for backward compatibility
            $socialProof['wa_group_share'] = end($socialProof['wa_group_shares']);

            $work->update([
                'social_media_proof' => $socialProof,
                'status' => 'published'
            ]);

            // Activity log
            $this->logActivity($work, $user, 'shared_wa_group', 'Link dibagikan ke grup WA', [
                'proof_link' => $request->proof_link,
                'group_name' => $request->group_name
            ]);

            return response()->json(['success' => true, 'message' => 'WA Group proof link saved.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload WhatsApp Story Proof
     * POST /api/live-tv/promosi/works/{id}/upload-whatsapp-story
     */
    public function uploadWhatsAppStory(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'proof_link' => 'required|string', // URL printscreen
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            $socialProof = $work->social_media_proof ?? [];
            $socialProof['whatsapp_story'] = [
                'proof_link' => $request->proof_link,
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $user->id,
                'notes' => $request->notes
            ];

            $work->update([
                'social_media_proof' => $socialProof,
                'status' => 'published'
            ]);

            // Activity log
            $this->logActivity($work, $user, 'whatsapp_story_uploaded', 'Bukti WhatsApp Story (Printscreen) diunggah', [
                'proof_link' => $request->proof_link
            ]);

            return response()->json(['success' => true, 'message' => 'WhatsApp Story proof saved.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Alias untuk backward compatibility
     * POST /api/live-tv/promosi/works/{id}/create-ig-story
     */
    public function createIGStoryHighlight(Request $request, int $id): JsonResponse
    {
        return $this->uploadStoryIG($request, $id);
    }

    /**
     * Alias untuk backward compatibility
     * POST /api/live-tv/promosi/works/{id}/create-fb-reels
     */
    public function createFBReelsHighlight(Request $request, int $id): JsonResponse
    {
        return $this->uploadReelsFacebook($request, $id);
    }

    /**
     * Complete Promotion Work (alternative endpoint)
     * POST /api/live-tv/promosi/works/{id}/complete-promotion-work
     */
    public function completePromotionWork(Request $request, int $id): JsonResponse
    {
        // Alias untuk completeWork
        return $this->completeWork($request, $id);
    }

    /**
     * Get activity history for a promotion work
     * GET /api/live-tv/promosi/works/{id}/history
     */
    public function getHistory(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated.'], 401);
            }

            $work = PromotionWork::findOrFail($id);

            $history = PromotionActivityLog::with(['user:id,name,role'])
                ->where('promotion_work_id', $id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'description' => $log->description,
                        'changes' => $log->changes,
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                            'role' => $log->user->role
                        ] : null,
                        'created_at' => $log->created_at->toDateTimeString(),
                        'time_ago' => $log->created_at->diffForHumans()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'work_id' => $work->id,
                    'work_title' => $work->title,
                    'history' => $history
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Log an activity for a promotion work (private helper)
     */
    private function logActivity(PromotionWork $work, $user, string $action, string $description, ?array $changes = null): void
    {
        try {
            PromotionActivityLog::create([
                'promotion_work_id' => $work->id,
                'episode_id' => $work->episode_id,
                'user_id' => $user->id,
                'action' => $action,
                'description' => $description,
                'changes' => $changes
            ]);
        } catch (\Exception $e) {
            // Silent fail — logging should never break the main workflow
            \Log::warning('Failed to log promotion activity: ' . $e->getMessage());
        }
    }
    /**
     * Get available equipment for Promotion role
     */
    public function getAvailableEquipment(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
        if (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }
        
        // Return unique names with effective availability (available - reserved pending)
        $availableEquipment = InventoryItem::where('status', 'active') // InventoryItem uses 'active' status for available
            ->select(['id', 'equipment_id', 'name', 'category', 'available_quantity', 'total_quantity'])
            ->orderBy('name')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $availableEquipment,
            'message' => 'Available equipment retrieved successfully'
        ]);
    }

    /**
     * Request Equipment - Promotion request equipment ke Art & Set Properti
     * POST /api/live-tv/promosi/works/{id}/request-equipment
     */
    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.equipment_name' => 'required|string|max:255',
                'equipment_list.*.equipment_id' => 'nullable|integer|exists:equipment_inventory,id',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.return_date' => 'required|date|after_or_equal:today',
                'equipment_list.*.notes' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000',
                'request_group_id' => 'nullable|string|max:64',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::with(['episode'])->findOrFail($id);

            // Access check: only owner can request equipment for their work
            if ($work->created_by !== $user->id && !ProgramManagerAuthorization::isProgramManager($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have access to this work.'
                ], 403);
            }

            $equipmentRequestIds = [];
            $unavailableEquipment = [];
            $scheduleDt = $work->shooting_date ? Carbon::parse($work->shooting_date . ' ' . ($work->shooting_time ?? '00:00:00')) : null;

            // Normalize and aggregate requested quantities by equipment name
            $normalizedItems = [];
            foreach ($request->equipment_list as $equipment) {
                $equipmentName = $equipment['equipment_name'];

                if (!empty($equipment['equipment_id'])) {
                    $inventoryItem = InventoryItem::find($equipment['equipment_id']);
                    if ($inventoryItem) {
                        $equipmentName = $inventoryItem->name;
                    }
                }

                $qty = (int) ($equipment['quantity'] ?? 0);
                if ($qty < 1) continue;
                $normalizedItems[] = [
                    'name' => $equipmentName,
                    'quantity' => $qty,
                    'notes' => $equipment['notes'] ?? null,
                ];
            }

            $qtyByName = [];
            foreach ($normalizedItems as $it) {
                $qtyByName[$it['name']] = ($qtyByName[$it['name']] ?? 0) + (int) $it['quantity'];
            }

            // Check availability per name (total qty) in master inventory
            $inventoryCounts = InventoryItem::whereIn('name', array_keys($qtyByName))
                ->get()
                ->pluck('available_quantity', 'name');

            foreach ($qtyByName as $name => $qty) {
                $availableCount = $inventoryCounts->get($name, 0);

                if ($availableCount < $qty) {
                    $unavailableEquipment[] = [
                        'equipment_name' => $name,
                        'requested_quantity' => $qty,
                        'available_count' => $availableCount,
                        'reason' => 'Equipment tidak tersedia dalam jumlah yang cukup di stok pusat'
                    ];
                }
            }

            if (!empty($unavailableEquipment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some equipment is not available or currently in use',
                    'unavailable_equipment' => $unavailableEquipment
                ], 400);
            }

            // Create ONE pending request per (episode, requester).
            $equipmentList = [];
            foreach ($qtyByName as $name => $qty) {
                for ($i = 0; $i < $qty; $i++) $equipmentList[] = $name;
            }

            $notesLines = [];
            foreach ($normalizedItems as $it) {
                if (!empty($it['notes'])) {
                    $notesLines[] = "{$it['name']}: {$it['notes']}";
                }
            }
            if (!empty($request->request_notes)) {
                $notesLines[] = (string) $request->request_notes;
            }

            $existingPending = ProductionEquipment::where('episode_id', $work->episode_id)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($existingPending) {
                $existingList = is_array($existingPending->equipment_list)
                    ? $existingPending->equipment_list
                    : (json_decode($existingPending->equipment_list, true) ?? []);

                $mergedList = array_values(array_merge($existingList, $equipmentList));

                $appendNotes = !empty($notesLines) ? implode("\n", $notesLines) : null;
                $mergedNotes = $existingPending->request_notes;
                if (!empty($appendNotes)) {
                    $mergedNotes = trim((string) $mergedNotes);
                    $mergedNotes = $mergedNotes !== ''
                        ? ($mergedNotes . "\n" . $appendNotes)
                        : $appendNotes;
                }

                $existingQtyMap = is_array($existingPending->equipment_quantities)
                    ? $existingPending->equipment_quantities
                    : (json_decode($existingPending->equipment_quantities, true) ?? []);
                if (!is_array($existingQtyMap)) {
                    $existingQtyMap = [];
                }
                foreach ($qtyByName as $k => $v) {
                    $existingQtyMap[$k] = (int) ($existingQtyMap[$k] ?? 0) + (int) ($v ?? 0);
                }

                $existingPending->update([
                    'program_id' => $existingPending->program_id ?: ($work->episode ? $work->episode->program_id : null),
                    'request_group_id' => $existingPending->request_group_id ?: ($request->request_group_id ?: null),
                    'equipment_list' => $mergedList,
                    'equipment_quantities' => $existingQtyMap,
                    'request_notes' => $mergedNotes ?: null,
                    'scheduled_date' => $existingPending->scheduled_date ?: ($scheduleDt ? $scheduleDt->toDateString() : null),
                    'scheduled_time' => $existingPending->scheduled_time ?: ($scheduleDt ? $scheduleDt->format('H:i:s') : null),
                ]);

                $equipmentRequest = $existingPending->fresh();
            } else {
                $equipmentRequest = ProductionEquipment::create([
                    'episode_id' => $work->episode_id,
                    'program_id' => $work->episode ? $work->episode->program_id : null,
                    'request_group_id' => $request->request_group_id ?: null,
                    'equipment_list' => $equipmentList,
                    'equipment_quantities' => $qtyByName,
                    'request_notes' => !empty($notesLines) ? implode("\n", $notesLines) : null,
                    'scheduled_date' => $scheduleDt ? $scheduleDt->toDateString() : null,
                    'scheduled_time' => $scheduleDt ? $scheduleDt->format('H:i:s') : null,
                    'status' => 'pending',
                    'requested_by' => $user->id,
                    'requested_at' => now()
                ]);
            }

            $equipmentRequestIds[] = $equipmentRequest->id;

            // Notify Art & Set Properti
            $artSetUsers = User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => $existingPending ? 'equipment_request_updated' : 'equipment_request_created',
                    'title' => $existingPending ? 'Update Permintaan Alat' : 'Permintaan Alat Baru',
                    'message' => $existingPending
                        ? "Tim Promosi menambahkan item pada permintaan equipment Episode {$work->episode->episode_number}."
                        : "Tim Promosi meminta equipment untuk Episode {$work->episode->episode_number}.",
                    'data' => [
                        'equipment_request_ids' => $equipmentRequestIds,
                        'episode_id' => $work->episode_id,
                        'promotion_work_id' => $work->id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode']),
                    'equipment_requests' => ProductionEquipment::whereIn('id', $equipmentRequestIds)->get()
                ],
                'message' => 'Equipment requests created successfully. Art & Set Properti has been notified.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batalkan permintaan alat
     */
    public function cancelEquipmentRequest(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $equipment = ProductionEquipment::where('id', $id)
                ->where('requested_by', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$equipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan tidak ditemukan atau sudah tidak dapat dibatalkan.'
                ], 404);
            }

            $equipment->delete();
            return response()->json([
                'success' => true,
                'message' => 'Permintaan alat berhasil dibatalkan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify Art & Set Properti that equipment has been returned physically
     */
    public function notifyReturn(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $equipment = ProductionEquipment::find($id);
            
            if (!$equipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment request not found.'
                ], 404);
            }

            // Verify status
            if ($equipment->status !== 'in_use' && $equipment->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment must be in "approved" or "in_use" status to notify return.'
                ], 400);
            }

            // Update return notes
            $currentNotes = $equipment->return_notes ?? '';
            $timestamp = now()->format('Y-m-d H:i');
            $newNote = "[User Return Notification] Promosi {$user->name} reported equipment returned at {$timestamp}.";
            
            $equipment->update([
                'return_notes' => $currentNotes ? $currentNotes . "\n" . $newNote : $newNote
            ]);

            // Notify Art & Set Properti
            $artSetUsers = User::where('role', 'Art & Set Properti')->get();
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_return_notification',
                    'title' => 'Pengembalian Alat (Promosi Reported)',
                    'message' => "Tim Promosi ({$user->name}) melaporkan telah mengembalikan alat: ID {$equipment->id}. Harap cek fisik & konfirmasi return.",
                    'data' => [
                        'equipment_id' => $equipment->id,
                        'reported_by' => $user->name,
                        'role' => $user->role
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi pengembalian berhasil dikirim ke Art & Set Properti. Harap tunggu konfirmasi final mereka.',
                'data' => $equipment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending return notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return Equipment to Art & Set Properti
     */
    public function returnEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_request_ids' => 'required|array|min:1',
                'equipment_request_ids.*' => 'required|integer|exists:production_equipment,id',
                'return_condition' => 'required|array|min:1',
                'return_condition.*.equipment_request_id' => 'required|integer',
                'return_condition.*.condition' => 'required|in:good,damaged,lost',
                'return_condition.*.notes' => 'nullable|string|max:1000',
                'return_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::with(['episode'])->findOrFail($id);
            $equipmentRequestIds = $request->equipment_request_ids;
            $returnConditions = collect($request->return_condition)->keyBy('equipment_request_id');

            $returnedEquipment = [];
            $failedEquipment = [];

            foreach ($equipmentRequestIds as $equipmentRequestId) {
                $equipment = ProductionEquipment::find($equipmentRequestId);
                
                if (!$equipment) {
                    $failedEquipment[] = ['id' => $equipmentRequestId, 'reason' => 'Not found'];
                    continue;
                }

                if ($equipment->episode_id !== $work->episode_id) {
                    $failedEquipment[] = ['id' => $equipmentRequestId, 'reason' => 'Episode mismatch'];
                    continue;
                }

                if ($equipment->status !== 'approved' && $equipment->status !== 'in_use') {
                    $failedEquipment[] = ['id' => $equipmentRequestId, 'reason' => 'Invalid status: ' . $equipment->status];
                    continue;
                }

                $conditionData = $returnConditions->get($equipmentRequestId);
                if (!$conditionData) {
                    $failedEquipment[] = ['id' => $equipmentRequestId, 'reason' => 'No condition data'];
                    continue;
                }

                $equipment->update([
                    'status' => 'returned',
                    'return_condition' => $conditionData['condition'],
                    'return_notes' => ($conditionData['notes'] ?? '') . ($request->return_notes ? "\n" . $request->return_notes : ''),
                    'returned_at' => now(),
                    'returned_by' => $user->id
                ]);

                // NOTE: We no longer increment available_quantity here.
                // It will be handled by Art & Set Properti when they confirm the return.
                
                $returnedEquipment[] = $equipment->fresh();
            }

            // Notify Art & Set Properti
            if (!empty($returnedEquipment)) {
                $artSetUsers = User::where('role', 'Art & Set Properti')->get();
                foreach ($artSetUsers as $artSetUser) {
                    Notification::create([
                        'user_id' => $artSetUser->id,
                        'type' => 'equipment_returned',
                        'title' => 'Alat Dikembalikan oleh Tim Promosi',
                        'message' => "Tim Promosi ({$user->name}) telah mengembalikan alat untuk Episode {$work->episode->episode_number}.",
                        'data' => [
                            'episode_id' => $work->episode_id,
                            'returned_by' => $user->id
                        ]
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'returned_equipment' => $returnedEquipment,
                    'failed_equipment' => $failedEquipment
                ],
                'message' => 'Equipment return process completed.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error returning equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/live-tv/promosi/works/{id}/transfer-equipment
     * Lanjut Pakai alat dari episode/pekerjaan lain ke pekerjaan ini.
     */
    public function transferEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $targetWork = PromotionWork::with(['episode'])->findOrFail($id);

            $isPromotionRole = in_array($user->role, ['Promotion', 'Social Media']);
            if (!$isPromotionRole && !$user->hasAnyMusicTeamAssignment()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_request_id' => 'required|integer|exists:production_equipment,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $loan = ProductionEquipment::findOrFail($request->equipment_request_id);

            // Authorization
            $isBorrower = $loan->requested_by === $user->id;
            $isOversight = MusicProgramAuthorization::hasProducerAccess($user) || ProgramManagerAuthorization::isProgramManager($user);

            if (!$isBorrower && !$isOversight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Anda hanya dapat memindahkan alat yang Anda pinjam sendiri.'
                ], 403);
            }

            if ($loan->status !== 'approved' && $loan->status !== 'in_use') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya peminjaman dengan status "approved" atau "in_use" yang dapat dipindahkan.'
                ], 400);
            }

            if ($loan->episode_id === $targetWork->episode_id) {
                return response()->json(['success' => false, 'message' => 'Alat sudah berada di episode ini.'], 400);
            }

            $fromEpisodeId = $loan->episode_id;
            $toEpisodeId = $targetWork->episode_id;
            $toProgramId = $targetWork->episode ? $targetWork->episode->program_id : null;

            \DB::beginTransaction();
            try {
                ProductionEquipmentTransfer::create([
                    'production_equipment_id' => $loan->id,
                    'from_episode_id' => $fromEpisodeId,
                    'to_episode_id' => $toEpisodeId,
                    'transferred_by' => $user->id,
                    'transferred_at' => now(),
                    'notes' => $request->notes ?? 'Lanjut pakai dari Tim Promosi'
                ]);

                $loan->update([
                    'episode_id' => $toEpisodeId,
                    'program_id' => $toProgramId,
                    'status' => 'in_use',
                ]);

                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $targetWork->fresh(['episode']),
                    'equipment_request' => $loan->fresh(),
                ],
                'message' => 'Alat berhasil dipindahkan (Lanjut Pakai) ke episode ini.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/promosi/handover-equipment/{equipment_request_id}
     * Serah terima alat ke user lain.
     */
    public function handoverEquipment(Request $request, int $equipment_request_id): JsonResponse
    {
        try {
            $user = Auth::user();
            $loan = ProductionEquipment::with('episode.program')->findOrFail($equipment_request_id);

            $isBorrower = $loan->requested_by === $user->id;
            $isOversight = MusicProgramAuthorization::hasProducerAccess($user) || ProgramManagerAuthorization::isProgramManager($user);
            
            if (!$isBorrower && !$isOversight) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if (!in_array($loan->status, ['approved', 'in_use'])) {
                return response()->json(['success' => false, 'message' => 'Alat tidak dalam status dapat diserah-terimakan.'], 400);
            }

            $validator = Validator::make($request->all(), [
                'to_user_id' => 'required|exists:users,id',
                'to_episode_id' => 'required|exists:episodes,id',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $toUserId = (int) $request->to_user_id;
            if ($toUserId === $user->id) {
                return response()->json(['success' => false, 'message' => 'Tidak bisa serah terima ke diri sendiri. Gunakan "Lanjut Pakai" untuk pindah episode.'], 400);
            }

            \DB::beginTransaction();
            try {
                $transfer = ProductionEquipmentTransfer::create([
                    'production_equipment_id' => $loan->id,
                    'from_episode_id' => $loan->episode_id,
                    'to_episode_id' => $request->to_episode_id ?? $loan->episode_id,
                    'to_user_id' => $toUserId,
                    'transferred_by' => $user->id,
                    'transferred_at' => now(),
                    'notes' => $request->notes,
                    'status' => 'pending_accept'
                ]);

                Notification::create([
                    'user_id' => $toUserId,
                    'type' => 'equipment_handover_requested',
                    'title' => 'Serah Terima Alat (Equipment Handover)',
                    'message' => "Ada serah terima alat dari {$user->name} untuk Anda. Silakan konfirmasi terima di dashboard.",
                    'data' => [
                        'transfer_id' => $transfer->id,
                        'equipment_request_id' => $loan->id,
                        'from_user' => $user->name,
                    ]
                ]);

                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Permintaan serah terima telah dikirim (Promosi). Menunggu user tujuan menerima.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/live-tv/promosi/accept-handover/{transferId}
     */
    public function acceptHandover(int $transferId): JsonResponse
    {
        try {
            $user = Auth::user();
            $transfer = ProductionEquipmentTransfer::with('productionEquipment')->findOrFail($transferId);

            if ((int) $transfer->to_user_id !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            if ($transfer->status !== 'pending_accept') {
                return response()->json(['success' => false, 'message' => 'Serah terima ini sudah diproses.'], 400);
            }

            $isProducerRole = in_array($user->role, ['Producer', 'Program Manager', 'Production']);
            $isPromotionRole = in_array($user->role, ['Promotion', 'Editor Promotion']);
            
            if (!$isProducerRole && !$isPromotionRole) {
                return response()->json(['success' => false, 'message' => 'Hanya Produser, tim Produksi, atau tim Promosi yang berhak menerima alat ini.'], 403);
            }

            $loan = $transfer->productionEquipment;
            if (!$loan || !in_array($loan->status, ['approved', 'in_use', 'transferred'])) {
                return response()->json(['success' => false, 'message' => 'Peminjaman alat asli sudah tidak valid.'], 400);
            }

            \DB::beginTransaction();
            try {
                $transfer->update(['status' => 'accepted', 'accepted_at' => now()]);
                $loan->update([
                    'requested_by' => $user->id,
                    'episode_id' => $transfer->to_episode_id,
                    'status' => 'in_use',
                    'metadata' => array_merge($loan->metadata ?? [], [
                        'handover_from_user' => $transfer->transferred_by,
                        'handover_at' => now()->toDateTimeString()
                    ])
                ]);
                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Serah terima alat berhasil diterima.',
                'data' => $loan->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Confirm all promotion tasks for an episode (Bulk Submission)
     * POST /api/live-tv/promosi/episodes/{id}/bulk-confirm-promotion
     */
    public function bulkConfirmEpisodeTasks(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated.'], 401);
            }

            // Authorization: Ensure user belongs to Promotion or has assignment
            if (!MusicProgramAuthorization::canUserPerformTask($user, null, 'Promotion')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $episode = Episode::findOrFail($id);
            $works = PromotionWork::where('episode_id', $id)->get();

            if ($works->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No promotion tasks found for this episode.'], 404);
            }

            \DB::beginTransaction();
            try {
                // Determine if we have tasks data (mapping task ID to link/file)
                // tasks is an array of objects: { id: 123, proof_link: '...', proof_file: FILE }
                $tasksInputList = $request->input('tasks', []); 
                
                foreach ($works as $work) {
                    $taskInput = null;
                    if (is_array($tasksInputList)) {
                        $taskInput = collect($tasksInputList)->firstWhere('id', (string)$work->id) 
                                   ?? collect($tasksInputList)->firstWhere('id', (int)$work->id);
                    }

                    $socialProof = $work->social_media_proof ?? [];
                    // Use work_type explicitly as key
                    $typeKey = $work->work_type; 
                    
                    if ($taskInput || $request->hasFile("tasks.{$work->id}.proof_file")) {
                        $proofData = isset($socialProof[$typeKey]) && is_array($socialProof[$typeKey]) ? $socialProof[$typeKey] : [];
                        
                        // Handle File Upload if exists (Files are sent as tasks[id][proof_file])
                        $fileKey = "tasks.{$work->id}.proof_file";
                        if ($request->hasFile($fileKey)) {
                            $uploadedFile = $request->file($fileKey);
                            $stored = FileUploadHelper::uploadFile(
                                $uploadedFile, 
                                'promosi/bulk-proof',
                                ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'],
                                ['jpg', 'jpeg', 'png', 'webp'],
                                10 * 1024 * 1024
                            );
                            
                            $proofData['proof_link'] = Storage::url($stored['file_path']);
                            $proofData['file_path'] = $stored['file_path'];
                        } elseif ($taskInput && isset($taskInput['proof_link'])) {
                            // Link only
                            $proofData['proof_link'] = $taskInput['proof_link'];
                        }

                        // Add metadata
                        $proofData['uploaded_at'] = now()->toDateTimeString();
                        $proofData['uploaded_by'] = $user->id;
                        
                        $socialProof[$typeKey] = $proofData;
                    }

                    // Force status to published (Staff clicked "Confirm All", so we mark everything they submitted as published)
                    $work->update([
                        'social_media_proof' => $socialProof,
                        'status' => 'published'
                    ]);

                    $this->logActivity($work, $user, 'bulk_confirmed', "Pekerjaan dikonfirmasi dalam bulk submit oleh {$user->name}", [
                        'status' => 'published'
                    ]);
                }

                // Log Workflow State for Episode (Progress monitoring)
                $workflowService = app(\App\Services\WorkflowStateService::class);
                $workflowService->updateWorkflowState(
                    $episode,
                    'promotion',
                    'promotion',
                    $user->id,
                    "All promotion tasks for Episode {$episode->episode_number} confirmed in bulk by {$user->name}",
                    $user->id,
                    [
                        'action' => 'promosi_bulk_confirmed',
                        'episode_id' => $id
                    ]
                );

                \DB::commit();
                QueryOptimizer::clearAllIndexCaches();

                return response()->json([
                    'success' => true,
                    'message' => 'Semua pekerjaan promosi episode ini berhasil dikonfirmasi dan diajukan ke KPI.',
                    'episode_id' => $id
                ]);

            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
