<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrTalent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Constants\Role;

class PrTalentController extends Controller
{
    /**
     * Get list of talents
     * GET /api/pr/talents
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::CREATIVE, Role::PRODUCTION, Role::ART_SET_PROPERTI, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $query = PrTalent::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('expertise', 'like', "%{$search}%");
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Default order by name
        $query->orderBy('name', 'asc');

        $talents = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $talents
        ]);
    }

    /**
     * Store new talent
     * POST /api/pr/talents
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !Role::inArray($user->role, [Role::CREATIVE, Role::PRODUCTION, Role::ART_SET_PROPERTI, Role::PROGRAM_MANAGER, Role::PRODUCER])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:host,guest,other',
            'title' => 'nullable|string|max:255',
            'birth_place_date' => 'nullable|string|max:255',
            'expertise' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $talent = PrTalent::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Talent created successfully',
            'data' => $talent
        ], 201);
    }
}
