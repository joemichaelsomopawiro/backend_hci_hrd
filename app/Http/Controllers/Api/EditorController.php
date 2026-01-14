<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EditorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented', 'data' => []], 501);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented'], 501);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented'], 501);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented'], 501);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented'], 501);
    }

    public function reportMissingFiles(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented'], 501);
    }

    public function getApprovedAudioFiles(Request $request, int $episodeId): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented'], 501);
    }

    public function getRunSheet(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Editor feature is not yet implemented'], 501);
    }
}
