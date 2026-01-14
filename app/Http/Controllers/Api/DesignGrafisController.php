<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DesignGrafisController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented', 'data' => []], 501);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented'], 501);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented'], 501);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented'], 501);
    }

    public function acceptWork(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented'], 501);
    }

    public function uploadFiles(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented'], 501);
    }

    public function getSharedFiles(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented', 'data' => []], 501);
    }

    public function statistics(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented'], 501);
    }

    public function submitToQC(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Design Grafis feature is not yet implemented'], 501);
    }
}
