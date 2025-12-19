<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionWork;
use App\Models\SocialMediaPost;
use App\Models\Episode;
use App\Models\MediaFile;
use App\Models\Notification;
use App\Models\DesignGrafisWork;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PromosiController extends Controller
{
    /**
     * Get promotion works for current user
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
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = PromotionWork::with(['episode', 'createdBy'])
                ->where('created_by', $user->id);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by work type
            if ($request->has('work_type')) {
                $query->where('work_type', $request->work_type);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

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
     * Create new promotion work
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Promosi') {
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
                'content_plan' => 'nullable|string',
                'talent_data' => 'nullable|array',
                'location_data' => 'nullable|array',
                'equipment_needed' => 'nullable|array',
                'shooting_date' => 'nullable|date',
                'shooting_time' => 'nullable|date_format:H:i',
                'shooting_notes' => 'nullable|string'
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
                'content_plan' => $request->content_plan,
                'talent_data' => $request->talent_data,
                'location_data' => $request->location_data,
                'equipment_needed' => $request->equipment_needed,
                'shooting_date' => $request->shooting_date,
                'shooting_time' => $request->shooting_time,
                'shooting_notes' => $request->shooting_notes,
                'status' => 'planning'
            ]);

            // Notify related roles
            $this->notifyRelatedRoles($work, 'created');

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
     * Upload BTS content files
     */
    public function uploadBTSContent(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|mimes:mp4,avi,mov,jpg,jpeg,png|max:102400', // 100MB max
                'content_type' => 'required|in:video,photo',
                'description' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            $uploadedFiles = [];

            foreach ($request->file('files') as $file) {
                $filePath = $file->store('promotion/bts-content', 'public');
                
                $mediaFile = MediaFile::create([
                    'episode_id' => $work->episode_id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'file_type' => 'promotion_bts',
                    'uploaded_by' => $user->id,
                    'metadata' => [
                        'promotion_work_id' => $work->id,
                        'content_type' => $request->content_type,
                        'description' => $request->description,
                        'uploaded_at' => now()
                    ]
                ]);

                $uploadedFiles[] = $mediaFile;
            }

            // Update work status
            $work->update([
                'status' => 'shooting',
                'file_paths' => collect($uploadedFiles)->pluck('file_path')->toArray()
            ]);

            // Notify Design Grafis about new BTS content
            $this->notifyDesignGrafis($work, $uploadedFiles);

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->load(['episode', 'createdBy']),
                    'uploaded_files' => $uploadedFiles
                ],
                'message' => 'BTS content uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading BTS content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create social media post
     */
    public function createSocialMediaPost(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'episode_id' => 'required|exists:episodes,id',
                'platform' => 'required|in:facebook,instagram,tiktok,website',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'hashtags' => 'nullable|array',
                'scheduled_at' => 'nullable|date|after:now',
                'media_files' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $post = SocialMediaPost::create([
                'episode_id' => $request->episode_id,
                'platform' => $request->platform,
                'title' => $request->title,
                'content' => $request->content,
                'hashtags' => $request->hashtags,
                'scheduled_at' => $request->scheduled_at,
                'media_files' => $request->media_files,
                'status' => $request->scheduled_at ? 'scheduled' : 'draft',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $post->load(['episode', 'createdBy']),
                'message' => 'Social media post created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating social media post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get social media posts
     */
    public function getSocialMediaPosts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = SocialMediaPost::with(['episode', 'createdBy']);

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->platform);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $posts = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $posts,
                'message' => 'Social media posts retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving social media posts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit social media proof/bukti
     * User: "masukkan bukti ke sistem" untuk share Facebook, Instagram, TikTok, WA
     */
    public function submitSocialProof(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
                'proof_type' => 'required|in:screenshot,link_proof,file_proof',
                'proof_link' => 'nullable|url|required_if:proof_type,link_proof',
                'proof_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find social media post
            $post = SocialMediaPost::findOrFail($id);

            if ($post->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this post'
                ], 403);
            }

            $proofFilePath = null;
            $proofFileName = null;

            // Handle file upload if proof_type is screenshot or file_proof
            if (in_array($request->proof_type, ['screenshot', 'file_proof']) && $request->hasFile('proof_file')) {
                $file = $request->file('proof_file');
                $proofFilePath = $file->store('promotion/social-proof', 'public');
                $proofFileName = $file->getClientOriginalName();
            }

            // Handle link proof
            if ($request->proof_type === 'link_proof' && $request->proof_link) {
                $proofFilePath = $request->proof_link;
                $proofFileName = 'Proof Link';
            }

            // Update post with proof
            $post->update([
                'proof_file_path' => $proofFilePath,
                'proof_file_name' => $proofFileName,
                'proof_type' => $request->proof_type,
                'proof_notes' => $request->proof_notes,
                'proof_submitted_at' => now()
            ]);

            // Notify related roles (optional)
            $this->notifyProofSubmitted($post);

            return response()->json([
                'success' => true,
                'data' => $post->load(['episode', 'createdBy']),
                'message' => 'Social media proof submitted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting proof: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify about proof submission
     */
    private function notifyProofSubmitted($post): void
    {
        // Notify Producer about proof submission
        $producerUsers = \App\Models\User::where('role', 'Producer')->get();
        
        foreach ($producerUsers as $user) {
            Notification::create([
                'title' => 'Bukti Social Media Dikirim',
                'message' => "Bukti posting {$post->platform} untuk episode {$post->episode->episode_number} telah dikirim",
                'type' => 'social_media_proof_submitted',
                'user_id' => $user->id,
                'episode_id' => $post->episode_id,
                'data' => [
                    'post_id' => $post->id,
                    'platform' => $post->platform,
                    'proof_type' => $post->proof_type
                ]
            ]);
        }
    }

    /**
     * Get promotion statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $stats = [
                'total_works' => PromotionWork::where('created_by', $user->id)->count(),
                'planning_works' => PromotionWork::where('created_by', $user->id)->where('status', 'planning')->count(),
                'shooting_works' => PromotionWork::where('created_by', $user->id)->where('status', 'shooting')->count(),
                'completed_works' => PromotionWork::where('created_by', $user->id)->where('status', 'completed')->count(),
                'total_posts' => SocialMediaPost::where('created_by', $user->id)->count(),
                'scheduled_posts' => SocialMediaPost::where('created_by', $user->id)->where('status', 'scheduled')->count(),
                'published_posts' => SocialMediaPost::where('created_by', $user->id)->where('status', 'published')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Promotion statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify related roles about promotion work
     */
    private function notifyRelatedRoles($work, string $action): void
    {
        $messages = [
            'created' => "New promotion work '{$work->title}' has been created for episode {$work->episode->episode_number}",
            'uploaded' => "BTS content has been uploaded for promotion work '{$work->title}'"
        ];

        // Notify Design Grafis
        $designGrafisUsers = \App\Models\User::where('role', 'Design Grafis')->get();
        foreach ($designGrafisUsers as $user) {
            Notification::create([
                'title' => 'New Promotion Work ' . ucfirst($action),
                'message' => $messages[$action] ?? "Promotion work {$action}",
                'type' => 'promotion_' . $action,
                'user_id' => $user->id,
                'episode_id' => $work->episode_id
            ]);
        }
    }

    /**
     * Terima Jadwal Syuting - Promosi terima jadwal syuting dari Creative Work
     * POST /api/live-tv/roles/promosi/works/{id}/accept-schedule
     */
    public function acceptSchedule(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::with(['episode.creativeWork'])->findOrFail($id);

            // Get shooting schedule from Creative Work
            $creativeWork = $work->episode->creativeWork;
            if (!$creativeWork || !$creativeWork->shooting_schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shooting schedule not found in Creative Work'
                ], 400);
            }

            // Update work with shooting schedule
            $work->update([
                'shooting_date' => $creativeWork->shooting_schedule,
                'shooting_time' => $creativeWork->shooting_schedule ? \Carbon\Carbon::parse($creativeWork->shooting_schedule)->format('H:i') : null,
                'location_data' => [
                    'shooting_location' => $creativeWork->shooting_location ?? null,
                    'accepted_at' => now()->toDateTimeString(),
                    'accepted_by' => $user->id
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Shooting schedule accepted successfully. Date: ' . \Carbon\Carbon::parse($creativeWork->shooting_schedule)->format('d M Y') . 
                            ($creativeWork->shooting_location ? ', Location: ' . $creativeWork->shooting_location : '')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting shooting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Promosi terima pekerjaan setelah Producer approve Creative Work
     * POST /api/live-tv/roles/promosi/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::findOrFail($id);

            if ($work->status !== 'planning') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is planning'
                ], 400);
            }

            $work->update([
                'status' => 'shooting',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now proceed with shooting.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload BTS Video
     * POST /api/live-tv/roles/promosi/works/{id}/upload-bts-video
     */
    public function uploadBTSVideo(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'bts_video' => 'required|file|mimes:mp4,avi,mov|max:102400' // 100MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            $file = $request->file('bts_video');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('promosi/bts_videos', $filename, 'public');

            $filePaths = $work->file_paths ?? [];
            $filePaths[] = [
                'type' => 'bts_video',
                'filename' => $filename,
                'path' => $path,
                'url' => asset('storage/' . $path),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_at' => now()->toDateTimeString()
            ];

            $work->update([
                'file_paths' => $filePaths
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode']),
                    'bts_video_path' => $path,
                    'bts_video_url' => asset('storage/' . $path)
                ],
                'message' => 'BTS video uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading BTS video: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Talent Photos
     * POST /api/live-tv/roles/promosi/works/{id}/upload-talent-photos
     */
    public function uploadTalentPhotos(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'talent_photos' => 'required|array|min:1',
                'talent_photos.*' => 'file|mimes:jpg,jpeg,png|max:10240' // 10MB max per photo
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = PromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            $filePaths = $work->file_paths ?? [];
            foreach ($request->file('talent_photos') as $index => $photo) {
                $filename = time() . '_' . $index . '_' . $photo->getClientOriginalName();
                $path = $photo->storeAs('promosi/talent_photos', $filename, 'public');
                
                $filePaths[] = [
                    'type' => 'talent_photo',
                    'filename' => $filename,
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'size' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType(),
                    'uploaded_at' => now()->toDateTimeString()
                ];
            }

            $work->update([
                'file_paths' => $filePaths
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode']),
                'message' => 'Talent photos uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading talent photos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan - Promosi selesaikan setelah upload semua file
     * POST /api/live-tv/roles/promosi/works/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'shooting') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is shooting'
                ], 400);
            }

            // Validate files uploaded
            $filePaths = $work->file_paths ?? [];
            $hasBTSVideo = collect($filePaths)->contains('type', 'bts_video');
            $hasTalentPhotos = collect($filePaths)->where('type', 'talent_photo')->isNotEmpty();

            if (!$hasBTSVideo || !$hasTalentPhotos) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please upload BTS video and talent photos before completing work'
                ], 400);
            }

            $work->update([
                'status' => 'published'
            ]);

            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            // Notify Producer
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'promosi_work_completed',
                    'title' => 'Promosi Work Selesai',
                    'message' => "Promosi telah menyelesaikan BTS video dan foto talent untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'promotion_work_id' => $work->id,
                        'episode_id' => $work->episode_id
                    ]
                ]);
            }

            // Auto-create Design Grafis work setelah Promosi selesai
            $designGrafisUsers = \App\Models\User::where('role', 'Design Grafis')->get();
            if ($designGrafisUsers->isNotEmpty()) {
                // Cek apakah sudah ada Design Grafis work untuk episode ini
                $existingDesignWork = \App\Models\DesignGrafisWork::where('episode_id', $work->episode_id)
                    ->whereIn('work_type', ['thumbnail_youtube', 'thumbnail_bts'])
                    ->first();

                if (!$existingDesignWork) {
                    // Create Design Grafis work untuk thumbnail YouTube
                    $designGrafisWorkYT = \App\Models\DesignGrafisWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => 'thumbnail_youtube',
                        'title' => "Thumbnail YouTube - Episode {$episode->episode_number}",
                        'description' => "Buat thumbnail YouTube untuk Episode {$episode->episode_number}",
                        'status' => 'draft',
                        'created_by' => $designGrafisUsers->first()->id
                    ]);

                    // Create Design Grafis work untuk thumbnail BTS
                    $designGrafisWorkBTS = \App\Models\DesignGrafisWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => 'thumbnail_bts',
                        'title' => "Thumbnail BTS - Episode {$episode->episode_number}",
                        'description' => "Buat thumbnail BTS untuk Episode {$episode->episode_number}",
                        'status' => 'draft',
                        'created_by' => $designGrafisUsers->first()->id
                    ]);

                    // Notify Design Grafis users
                    foreach ($designGrafisUsers as $designUser) {
                        Notification::create([
                            'user_id' => $designUser->id,
                            'type' => 'design_grafis_work_created',
                            'title' => 'Design Grafis Work Dibuat',
                            'message' => "Pekerjaan Design Grafis telah dibuat untuk Episode {$episode->episode_number}. Silakan buat thumbnail YouTube dan BTS.",
                            'data' => [
                                'episode_id' => $work->episode_id,
                                'design_grafis_work_yt_id' => $designGrafisWorkYT->id,
                                'design_grafis_work_bts_id' => $designGrafisWorkBTS->id,
                                'promotion_work_id' => $work->id
                            ]
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work completed successfully. Producer and Design Grafis have been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Link YouTube dan Website - Promosi terima link dari Broadcasting
     * POST /api/live-tv/promosi/episodes/{id}/receive-links
     */
    public function receiveLinks(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'youtube_url' => 'required|url',
                'website_url' => 'required|url'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::findOrFail($id);

            // Update episode with links
            $episode->update([
                'youtube_url' => $request->youtube_url,
                'website_url' => $request->website_url
            ]);

            return response()->json([
                'success' => true,
                'data' => $episode->fresh(),
                'message' => 'Links received successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error receiving links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Promosi terima pekerjaan setelah QC/Broadcasting
     * POST /api/live-tv/promosi/works/{id}/accept-work
     */
    public function acceptPromotionWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::findOrFail($id);

            if ($work->status !== 'planning' && $work->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is planning or pending'
                ], 400);
            }

            $work->update([
                'status' => 'in_progress',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Work accepted successfully. You can now proceed with promotion tasks.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share Link Website ke Facebook dengan Bukti
     * POST /api/live-tv/promosi/episodes/{id}/share-facebook
     */
    public function shareFacebook(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'website_url' => 'required|url',
                'proof_file' => 'required|file|mimes:jpg,jpeg,png|max:5120',
                'post_url' => 'nullable|url',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::findOrFail($id);

            // Upload proof file
            $proofFile = $request->file('proof_file');
            $proofPath = $proofFile->storeAs('promosi/facebook_proofs', time() . '_' . $proofFile->getClientOriginalName(), 'public');
            $proofUrl = Storage::url($proofPath);

            // Store sharing info
            $shares = $episode->promosi_social_shares ?? [];
            $shares[] = [
                'platform' => 'facebook',
                'type' => 'website_link_share',
                'website_url' => $request->website_url,
                'post_url' => $request->post_url,
                'proof_url' => $proofUrl,
                'proof_path' => $proofPath,
                'notes' => $request->notes,
                'shared_at' => now()->toDateTimeString(),
                'shared_by' => $user->id
            ];

            $episode->update([
                'promosi_social_shares' => $shares
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode->fresh(),
                    'share_info' => end($shares)
                ],
                'message' => 'Facebook share recorded successfully with proof'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sharing to Facebook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buat Video Highlight untuk Story IG dengan Bukti
     * POST /api/live-tv/promosi/episodes/{id}/create-ig-story-highlight
     */
    public function createIGStoryHighlight(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'video_file' => 'required|file|mimes:mp4,mov|max:100000',
                'proof_file' => 'required|file|mimes:jpg,jpeg,png|max:5120',
                'story_url' => 'nullable|url',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::findOrFail($id);

            // Upload video file
            $videoFile = $request->file('video_file');
            $videoPath = $videoFile->storeAs('promosi/ig_story_highlights', time() . '_' . $videoFile->getClientOriginalName(), 'public');
            $videoUrl = Storage::url($videoPath);

            // Upload proof file
            $proofFile = $request->file('proof_file');
            $proofPath = $proofFile->storeAs('promosi/ig_story_proofs', time() . '_' . $proofFile->getClientOriginalName(), 'public');
            $proofUrl = Storage::url($proofPath);

            // Store highlight info
            $highlights = $episode->promosi_ig_story_urls ?? [];
            $highlights[] = [
                'video_url' => $videoUrl,
                'video_path' => $videoPath,
                'story_url' => $request->story_url,
                'proof_url' => $proofUrl,
                'proof_path' => $proofPath,
                'notes' => $request->notes,
                'created_at' => now()->toDateTimeString(),
                'created_by' => $user->id
            ];

            $episode->update([
                'promosi_ig_story_urls' => $highlights
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode->fresh(),
                    'highlight' => end($highlights)
                ],
                'message' => 'IG Story highlight created successfully with proof'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating IG Story highlight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buat Video Highlight untuk Reels Facebook dengan Bukti
     * POST /api/live-tv/promosi/episodes/{id}/create-fb-reels-highlight
     */
    public function createFBReelsHighlight(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'video_file' => 'required|file|mimes:mp4,mov|max:100000',
                'proof_file' => 'required|file|mimes:jpg,jpeg,png|max:5120',
                'reels_url' => 'nullable|url',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::findOrFail($id);

            // Upload video file
            $videoFile = $request->file('video_file');
            $videoPath = $videoFile->storeAs('promosi/fb_reels_highlights', time() . '_' . $videoFile->getClientOriginalName(), 'public');
            $videoUrl = Storage::url($videoPath);

            // Upload proof file
            $proofFile = $request->file('proof_file');
            $proofPath = $proofFile->storeAs('promosi/fb_reels_proofs', time() . '_' . $proofFile->getClientOriginalName(), 'public');
            $proofUrl = Storage::url($proofPath);

            // Store highlight info
            $highlights = $episode->promosi_fb_reel_urls ?? [];
            $highlights[] = [
                'video_url' => $videoUrl,
                'video_path' => $videoPath,
                'reels_url' => $request->reels_url,
                'proof_url' => $proofUrl,
                'proof_path' => $proofPath,
                'notes' => $request->notes,
                'created_at' => now()->toDateTimeString(),
                'created_by' => $user->id
            ];

            $episode->update([
                'promosi_fb_reel_urls' => $highlights
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode->fresh(),
                    'highlight' => end($highlights)
                ],
                'message' => 'Facebook Reels highlight created successfully with proof'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating Facebook Reels highlight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share ke Grup Promosi WA dengan Bukti
     * POST /api/live-tv/promosi/episodes/{id}/share-wa-group
     */
    public function shareWAGroup(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'group_name' => 'required|string|max:255',
                'proof_file' => 'required|file|mimes:jpg,jpeg,png|max:5120',
                'message' => 'nullable|string',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $episode = Episode::findOrFail($id);

            // Upload proof file
            $proofFile = $request->file('proof_file');
            $proofPath = $proofFile->storeAs('promosi/wa_group_proofs', time() . '_' . $proofFile->getClientOriginalName(), 'public');
            $proofUrl = Storage::url($proofPath);

            // Store sharing info
            $shares = $episode->promosi_social_shares ?? [];
            $shares[] = [
                'platform' => 'whatsapp',
                'type' => 'group_share',
                'group_name' => $request->group_name,
                'message' => $request->message,
                'proof_url' => $proofUrl,
                'proof_path' => $proofPath,
                'notes' => $request->notes,
                'shared_at' => now()->toDateTimeString(),
                'shared_by' => $user->id
            ];

            $episode->update([
                'promosi_social_shares' => $shares
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'episode' => $episode->fresh(),
                    'share_info' => end($shares)
                ],
                'message' => 'WhatsApp group share recorded successfully with proof'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sharing to WhatsApp group: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan Promosi
     * POST /api/live-tv/promosi/works/{id}/complete-promotion-work
     */
    public function completePromotionWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Promosi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = PromotionWork::with(['episode'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this work'
                ], 403);
            }

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is in_progress'
                ], 400);
            }

            $work->update([
                'status' => 'published',
                'reviewed_at' => now()
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam ?? null;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'promotion_work_completed',
                    'title' => 'Promosi Work Selesai',
                    'message' => "Promosi telah menyelesaikan pekerjaan untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'promotion_work_id' => $work->id,
                        'episode_id' => $work->episode_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'createdBy']),
                'message' => 'Promotion work completed successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing promotion work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify Design Grafis about new BTS content
     */
    private function notifyDesignGrafis($work, $files): void
    {
        $designGrafisUsers = \App\Models\User::where('role', 'Design Grafis')->get();
        
        foreach ($designGrafisUsers as $user) {
            Notification::create([
                'title' => 'New BTS Content Available',
                'message' => "New BTS content has been uploaded for episode {$work->episode->episode_number}. Please create thumbnails and graphics.",
                'type' => 'bts_content_uploaded',
                'user_id' => $user->id,
                'episode_id' => $work->episode_id,
                'data' => [
                    'promotion_work_id' => $work->id,
                    'file_count' => count($files)
                ]
            ]);
        }
    }
}
