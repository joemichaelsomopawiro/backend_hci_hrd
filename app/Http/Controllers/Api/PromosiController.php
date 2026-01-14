<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromosiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented', 'data' => []], 501);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function uploadBTSContent(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function acceptSchedule(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function acceptWork(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function uploadBTSVideo(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function uploadTalentPhotos(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function completeWork(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function createSocialMediaPost(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function getSocialMediaPosts(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented', 'data' => []], 501);
    }

    public function submitSocialProof(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function statistics(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function receiveLinks(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function acceptPromotionWork(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function shareFacebook(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function createIGStoryHighlight(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function createFBReelsHighlight(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function shareWAGroup(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }

    public function completePromotionWork(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Promosi feature is not yet implemented'], 501);
    }
}
