<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgramMusicScheduleController extends Controller
{
    public function getShootingSchedules(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Program Music Schedule feature is not yet implemented', 'data' => []], 501);
    }

    public function getAirSchedules(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Program Music Schedule feature is not yet implemented', 'data' => []], 501);
    }

    public function getCalendar(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Program Music Schedule feature is not yet implemented', 'data' => []], 501);
    }

    public function getTodaySchedules(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Program Music Schedule feature is not yet implemented', 'data' => []], 501);
    }

    public function getWeekSchedules(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Program Music Schedule feature is not yet implemented', 'data' => []], 501);
    }
}
