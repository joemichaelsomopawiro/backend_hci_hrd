<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionWork;
use App\Models\Notification;
use App\Models\CreativeWork;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use App\Helpers\FileUploadHelper;
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
            
            if ($user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Build cache key
            $cacheKey = 'promosi_index_' . md5(json_encode([
                'user_id' => $user->id,
                'status' => $request->get('status'),
                'page' => $request->get('page', 1)
            ]));

            // Use cache with 5 minutes TTL
            $works = QueryOptimizer::rememberForUser($cacheKey, $user->id, 300, function () use ($request, $user) {
                $query = PromotionWork::with(['episode.program.productionTeam', 'createdBy', 'reviewedBy']);

                // Filter by user (only works assigned to this user)
                // PromotionWork bisa di-assign ke user tertentu melalui created_by
                // Atau bisa juga semua Promotion users bisa lihat semua works
                // Untuk sekarang, kita filter berdasarkan notification atau all works
                // Karena auto-create tidak set created_by, kita ambil semua works dengan status planning
                
                // Filter by status
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }

                // Filter by episode
                if ($request->has('episode_id')) {
                    $query->where('episode_id', $request->episode_id);
                }

                return $query->orderBy('created_at', 'desc')->paginate(15);
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
     * Store new promotion work (optional, biasanya auto-create dari Producer)
     * POST /api/live-tv/promosi/works
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promotion') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'work_type' => 'required|in:bts_video,bts_photo,highlight_ig,highlight_facebook,highlight_tv,story_ig,reels_facebook,tiktok,website_content',
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
            
            if (!$user || $user->role !== 'Promotion') {
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
            
            if (!$user || $user->role !== 'Promotion') {
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
                    'message' => 'Work can only be accepted when status is planning'
                ], 400);
            }

            $oldData = $work->toArray();
            
            // Update status to shooting and set created_by
            // Untuk sharing tasks, status bisa langsung 'shooting' atau tetap 'planning' tergantung workflow
            // share_facebook dan share_wa_group bisa langsung ke 'shooting' karena tidak perlu shooting
            $newStatus = in_array($work->work_type, ['share_facebook', 'share_wa_group']) 
                ? 'shooting' // Langsung ke shooting karena tidak perlu persiapan shooting
                : 'shooting'; // Default untuk work types lain
            
            $work->update([
                'status' => $newStatus,
                'created_by' => $user->id
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
                'share_facebook' => 'Work accepted successfully. You can now share the website link to Facebook and upload proof.',
                'share_wa_group' => 'Work accepted successfully. You can now share to WA group and upload proof.',
                'story_ig' => 'Work accepted successfully. You can now create and upload Story IG highlight video with proof.',
                'reels_facebook' => 'Work accepted successfully. You can now create and upload Reels Facebook highlight video with proof.',
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
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_link' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            // Access check
            if ($work->created_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $fileLinks = $work->file_links ?? [];
            $fileLinks = array_filter($fileLinks, fn($item) => ($item['type'] ?? '') !== 'bts_video');
            
            $fileLinks[] = [
                'type' => 'bts_video',
                'file_link' => $request->file_link,
                'uploaded_at' => now()->toDateTimeString(),
                'uploaded_by' => $user->id
            ];

            $work->update(['file_links' => array_values($fileLinks)]);

            return response()->json(['success' => true, 'message' => 'BTS video link submitted successfully.']);

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
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_links' => 'required|array|min:1',
                'file_links.*' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $fileLinks = $work->file_links ?? [];
            $fileLinks = array_filter($fileLinks, fn($item) => ($item['type'] ?? '') !== 'talent_photo');
            
            foreach ($request->file_links as $link) {
                $fileLinks[] = [
                    'type' => 'talent_photo',
                    'file_link' => $link,
                    'uploaded_at' => now()->toDateTimeString(),
                    'uploaded_by' => $user->id
                ];
            }

            $work->update(['file_links' => array_values($fileLinks)]);

            return response()->json(['success' => true, 'message' => 'Talent photo links submitted successfully.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

            
            // file_links is now required via validator

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::with(['episode.program'])->findOrFail($id);

            // Check if user has access
            if ($work->created_by !== $user->id && $work->status !== 'shooting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you or not in shooting status.'
                ], 403);
            }

            // Get existing file_links or initialize
            $fileLinks = $work->file_links ?? [];
            
            // Remove existing talent photos link if any
            $fileLinks = array_filter($fileLinks, function($item) {
                return isset($item['type']) && $item['type'] !== 'talent_photo';
            });
            
            // Add new talent photo links
            foreach ($request->file_links as $link) {
                $fileLinks[] = [
                    'type' => 'talent_photo',
                    'file_link' => $link,
                    'uploaded_at' => now()->toDateTimeString(),
                    'uploaded_by' => $user->id
                ];
            }

            $work->update([
                'file_links' => array_values($fileLinks)
            ]);

            // Audit logging
            ControllerSecurityHelper::logCrud('promosi_talent_photos_link_submitted', $work, [
                'links_count' => count($request->file_links),
                'links' => $request->file_links
            ], $request);

            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Talent photo links submitted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading talent photos: ' . $e->getMessage()
            ], 500);
    }
    }

    /**
     * Upload BTS Content (Legacy/Alternative endpoint)
     * POST /api/live-tv/promosi/works/{id}/upload-bts
     */
    public function uploadBTSContent(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Physical file uploads are disabled. Please use the link submission endpoints (upload-bts-video or upload-talent-photos).'
        ], 405);
    }

    /**
     * Selesaikan Pekerjaan
     * POST /api/live-tv/promosi/works/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promotion') {
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

            // Only allow complete if status is shooting
            if ($work->status !== 'shooting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is shooting'
                ], 400);
            }

            // Validate: BTS video and talent photos must be uploaded (check both file_paths and file_links)
            // Only strictly enforce for bts_video and bts_photo work types
            $isBTSWork = in_array($work->work_type, ['bts_video', 'bts_photo']);
            
            $filePaths = $work->file_paths ?? [];
            $fileLinks = $work->file_links ?? [];
            $hasBTSVideo = false;
            $hasTalentPhotos = false;
            $hasAnyFile = false;

            // Check file_paths (backward compatibility)
            foreach ($filePaths as $file) {
                $hasAnyFile = true;
                if (isset($file['type']) && $file['type'] === 'bts_video') {
                    $hasBTSVideo = true;
                }
                if (isset($file['type']) && $file['type'] === 'talent_photo') {
                    $hasTalentPhotos = true;
                }
            }
            
            // Check file_links (new: external storage links)
            foreach ($fileLinks as $link) {
                $hasAnyFile = true;
                if (isset($link['type']) && $link['type'] === 'bts_video') {
                    $hasBTSVideo = true;
                }
                if (isset($link['type']) && $link['type'] === 'talent_photo') {
                    $hasTalentPhotos = true;
                }
            }

            if ($isBTSWork) {
                if (!$hasBTSVideo || !$hasTalentPhotos) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please upload both BTS video and talent photos before completing work.',
                        'missing' => [
                            'bts_video' => !$hasBTSVideo,
                            'talent_photos' => !$hasTalentPhotos
                        ]
                    ], 400);
                }
            } else {
                // For other works (sharing, story, etc), require at least one proof (file or link)
                if (!$hasAnyFile && empty($request->completion_notes)) {
                     return response()->json([
                        'success' => false,
                        'message' => 'Please provide proof (upload file/link) or completion notes before completing work.'
                    ], 400);
                }
            }

            $oldData = $work->toArray();
            
            // Update status to completed (or editing if needs review)
            $work->update([
                'status' => 'editing', // Set to editing, bisa diubah ke completed jika tidak perlu review
                'shooting_notes' => ($work->shooting_notes ? $work->shooting_notes . "\n\n" : '') . 
                    ($request->completion_notes ? "[Selesai] " . $request->completion_notes : '')
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
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
            
            if (!$existingThumbnailBTS && !empty($finalTalentPhotos)) {
                $designGrafisWork = \App\Models\DesignGrafisWork::create([
                    'episode_id' => $work->episode_id,
                    'work_type' => 'thumbnail_bts',
                    'title' => "Thumbnail BTS - Episode {$episode->episode_number}",
                    'description' => "Design thumbnail BTS untuk Episode {$episode->episode_number}. Foto talent dari Promosi sudah tersedia.",
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
                        'message' => "Promosi telah mengupload foto talent untuk Episode {$episode->episode_number}. Design Grafis work untuk Thumbnail BTS sudah dibuat.",
                        'data' => json_encode([ // Encode data to JSON
                            'promotion_work_id' => $work->id,
                            'design_grafis_work_id' => $designGrafisWork->id,
                            'episode_id' => $work->episode_id,
                            'talent_photos_count' => count($finalTalentPhotos),
                            'bts_video_available' => !empty($finalBTSVideo)
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($notificationsToInsert)) {
                    Notification::insert($notificationsToInsert);
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
                        'message' => "Promosi telah mengupdate foto talent untuk Episode {$episode->episode_number}. Design Grafis work untuk Thumbnail BTS telah diperbarui.",
                        'data' => json_encode([
                            'promotion_work_id' => $work->id,
                            'design_grafis_work_id' => $existingThumbnailBTS->id,
                            'episode_id' => $work->episode_id,
                            'talent_photos_count' => count($finalTalentPhotos)
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                }

                if (!empty($notificationsToInsert)) {
                    \App\Models\Notification::insert($notificationsToInsert);
                }
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
                        'title' => "{$titlePrefix} - Episode {$episode->episode_number}",
                        'description' => "Editing task untuk {$titlePrefix}. File referensi dari Promosi (BTS) sudah tersedia.",
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
                    'message' => "Promosi telah mengupload BTS video dan foto talent untuk Episode {$episode->episode_number}. PromotionWork untuk edit BTS, Highlight, dan Iklan TV sudah dibuat.",
                    'data' => json_encode([
                        'promotion_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'bts_files_available' => !empty($btsFiles) || !empty($btsFileLinks),
                        'bts_video_available' => !empty($finalBTSVideo),
                        'talent_photos_count' => count($finalTalentPhotos),
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
        return response()->json(['success' => false, 'message' => 'Social media posts feature is not yet implemented', 'data' => []], 501);
    }

    /**
     * Submit Social Proof
     * POST /api/live-tv/promosi/social-media/{id}/submit-proof
     */
    public function submitSocialProof(Request $request, int $id): JsonResponse
    {
        // TODO: Implement jika diperlukan
        return response()->json(['success' => false, 'message' => 'Social proof feature is not yet implemented'], 501);
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
            'my_completed' => $myStats->get('published', 0)
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
     */
    public function receiveLinks(Request $request, int $id): JsonResponse
    {
        // TODO: Implement jika diperlukan
        return response()->json(['success' => false, 'message' => 'Receive links feature is not yet implemented'], 501);
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
     * Share Link Website ke Facebook (Strict Link Only)
     * POST /api/live-tv/promosi/works/{id}/share-facebook
     */
    public function shareFacebook(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || $user->role !== 'Promotion') {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'proof_link' => 'required|url',
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
                'proof_link' => 'required|url',
                'group_name' => 'nullable|string',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $work = PromotionWork::findOrFail($id);

            $socialProof = $work->social_media_proof ?? [];
            $socialProof['wa_group_share'] = [
                'proof_link' => $request->proof_link,
                'group_name' => $request->group_name,
                'shared_at' => now()->toDateTimeString(),
                'shared_by' => $user->id,
                'notes' => $request->notes
            ];

            $work->update([
                'social_media_proof' => $socialProof,
                'status' => 'published'
            ]);

            return response()->json(['success' => true, 'message' => 'WA Group proof link saved.']);

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
}
