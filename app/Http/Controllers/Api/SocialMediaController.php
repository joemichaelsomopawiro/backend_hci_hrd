<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaPost;
use App\Models\Episode;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class SocialMediaController extends Controller
{
    /**
     * Upload to YouTube
     */
    public function uploadToYouTube(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, ['Broadcasting', 'Promotion'])) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'video_file' => 'required|file|mimes:mp4,mov,avi|max:500000',
            'title' => 'required|string|max:100',
            'description' => 'required|string|max:5000',
            'tags' => 'nullable|array',
            'thumbnail' => 'nullable|file|mimes:jpg,jpeg,png|max:10000',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload video file
            $videoFile = $request->file('video_file');
            $videoPath = $videoFile->storeAs('youtube/videos', time() . '_' . $videoFile->getClientOriginalName(), 'public');

            // Upload thumbnail if provided
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailFile = $request->file('thumbnail');
                $thumbnailPath = $thumbnailFile->storeAs('youtube/thumbnails', time() . '_' . $thumbnailFile->getClientOriginalName(), 'public');
            }

            // Create social media post record
            $post = SocialMediaPost::create([
                'episode_id' => $request->episode_id,
                'platform' => 'youtube',
                'title' => $request->title,
                'content' => $request->description,
                'tags' => $request->tags ? json_encode($request->tags) : null,
                'media_files' => json_encode([$videoPath]),
                'thumbnail_url' => $thumbnailPath,
                'status' => 'scheduled',
                'scheduled_at' => $request->scheduled_at,
                'created_by' => $user->id
            ]);

            // Simulate YouTube API call (replace with actual YouTube API)
            $youtubeResponse = $this->simulateYouTubeUpload($post);

            if ($youtubeResponse['success']) {
                $post->update([
                    'post_id' => $youtubeResponse['video_id'],
                    'post_url' => $youtubeResponse['video_url'],
                    'status' => 'published',
                    'published_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded to YouTube successfully',
                'data' => $post->load('episode')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload to YouTube: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share to Facebook
     */
    public function shareToFacebook(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, ['Broadcasting', 'Promotion'])) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'content' => 'required|string|max:2000',
            'link_url' => 'required|url',
            'media_files' => 'nullable|array',
            'media_files.*' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload media files if provided
            $mediaPaths = [];
            if ($request->hasFile('media_files')) {
                foreach ($request->file('media_files') as $file) {
                    $path = $file->storeAs('facebook/media', time() . '_' . $file->getClientOriginalName(), 'public');
                    $mediaPaths[] = $path;
                }
            }

            // Create social media post record
            $post = SocialMediaPost::create([
                'episode_id' => $request->episode_id,
                'platform' => 'facebook',
                'title' => 'Facebook Post',
                'content' => $request->content,
                'post_url' => $request->link_url,
                'media_files' => json_encode($mediaPaths),
                'status' => 'scheduled',
                'scheduled_at' => $request->scheduled_at,
                'created_by' => $user->id
            ]);

            // Simulate Facebook API call
            $facebookResponse = $this->simulateFacebookPost($post);

            if ($facebookResponse['success']) {
                $post->update([
                    'post_id' => $facebookResponse['post_id'],
                    'status' => 'published',
                    'published_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Content shared to Facebook successfully',
                'data' => $post->load('episode')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to share to Facebook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Instagram Story
     */
    public function createInstagramStory(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, ['Broadcasting', 'Promotion'])) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'media_file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov|max:50000',
            'content' => 'nullable|string|max:2200',
            'hashtags' => 'nullable|array',
            'mentions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload media file
            $mediaFile = $request->file('media_file');
            $mediaPath = $mediaFile->storeAs('instagram/stories', time() . '_' . $mediaFile->getClientOriginalName(), 'public');

            // Create social media post record
            $post = SocialMediaPost::create([
                'episode_id' => $request->episode_id,
                'platform' => 'instagram',
                'title' => 'Instagram Story',
                'content' => $request->content,
                'hashtags' => $request->hashtags ? json_encode($request->hashtags) : null,
                'mentions' => $request->mentions ? json_encode($request->mentions) : null,
                'media_files' => json_encode([$mediaPath]),
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $user->id
            ]);

            // Simulate Instagram API call
            $instagramResponse = $this->simulateInstagramStory($post);

            if ($instagramResponse['success']) {
                $post->update([
                    'post_id' => $instagramResponse['story_id'],
                    'post_url' => $instagramResponse['story_url']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Instagram story created successfully',
                'data' => $post->load('episode')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Instagram story: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share to WhatsApp Group
     */
    public function shareToWhatsAppGroup(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!in_array($user->role, ['Broadcasting', 'Promotion'])) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'message' => 'required|string|max:1000',
            'link_url' => 'required|url',
            'group_name' => 'required|string',
            'media_files' => 'nullable|array',
            'media_files.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,pdf|max:100000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload media files if provided
            $mediaPaths = [];
            if ($request->hasFile('media_files')) {
                foreach ($request->file('media_files') as $file) {
                    $path = $file->storeAs('whatsapp/media', time() . '_' . $file->getClientOriginalName(), 'public');
                    $mediaPaths[] = $path;
                }
            }

            // Create social media post record
            $post = SocialMediaPost::create([
                'episode_id' => $request->episode_id,
                'platform' => 'whatsapp',
                'title' => 'WhatsApp Group Share',
                'content' => $request->message,
                'post_url' => $request->link_url,
                'media_files' => json_encode($mediaPaths),
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $user->id,
                'notes' => "Shared to group: {$request->group_name}"
            ]);

            // Simulate WhatsApp API call
            $whatsappResponse = $this->simulateWhatsAppShare($post, $request->group_name);

            if ($whatsappResponse['success']) {
                $post->update([
                    'post_id' => $whatsappResponse['message_id']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Content shared to WhatsApp group successfully',
                'data' => $post->load('episode')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to share to WhatsApp: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get social media posts
     */
    public function getPosts(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = SocialMediaPost::with('episode');

        // Filter by platform
        if ($request->has('platform')) {
            $query->where('platform', $request->platform);
        }

        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $posts = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    /**
     * Get social media statistics
     */
    public function statistics(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $stats = [
            'total_posts' => SocialMediaPost::count(),
            'youtube_posts' => SocialMediaPost::where('platform', 'youtube')->count(),
            'facebook_posts' => SocialMediaPost::where('platform', 'facebook')->count(),
            'instagram_posts' => SocialMediaPost::where('platform', 'instagram')->count(),
            'whatsapp_posts' => SocialMediaPost::where('platform', 'whatsapp')->count(),
            'published_posts' => SocialMediaPost::where('status', 'published')->count(),
            'scheduled_posts' => SocialMediaPost::where('status', 'scheduled')->count(),
            'my_posts' => SocialMediaPost::where('created_by', $user->id)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Simulate YouTube upload (replace with actual YouTube API)
     */
    private function simulateYouTubeUpload($post)
    {
        // Simulate API delay
        sleep(1);
        
        return [
            'success' => true,
            'video_id' => 'yt_' . time() . '_' . rand(1000, 9999),
            'video_url' => 'https://youtube.com/watch?v=' . 'yt_' . time() . '_' . rand(1000, 9999)
        ];
    }

    /**
     * Simulate Facebook post (replace with actual Facebook API)
     */
    private function simulateFacebookPost($post)
    {
        // Simulate API delay
        sleep(1);
        
        return [
            'success' => true,
            'post_id' => 'fb_' . time() . '_' . rand(1000, 9999)
        ];
    }

    /**
     * Simulate Instagram story (replace with actual Instagram API)
     */
    private function simulateInstagramStory($post)
    {
        // Simulate API delay
        sleep(1);
        
        return [
            'success' => true,
            'story_id' => 'ig_' . time() . '_' . rand(1000, 9999),
            'story_url' => 'https://instagram.com/stories/' . 'ig_' . time() . '_' . rand(1000, 9999)
        ];
    }

    /**
     * Simulate WhatsApp share (replace with actual WhatsApp API)
     */
    private function simulateWhatsAppShare($post, $groupName)
    {
        // Simulate API delay
        sleep(1);
        
        return [
            'success' => true,
            'message_id' => 'wa_' . time() . '_' . rand(1000, 9999)
        ];
    }
}











