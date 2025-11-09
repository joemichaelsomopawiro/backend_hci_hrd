<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionWork;
use App\Models\SocialMediaPost;
use App\Models\Episode;
use App\Models\MediaFile;
use App\Models\Notification;
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
