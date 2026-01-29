<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * Display a listing of users with filtering options
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('employee');

        // Filter by role
        // Filter by role or employee jabatan (case-insensitive for robustness)
        if ($request->has('role') && !empty($request->input('role'))) {
            $roleInput = $request->input('role');

            // Map common aliases/Indonesian terms to DB equivalents
            $roleMap = [
                'kreatif' => 'Creative',
                'musik arr' => 'Music Arranger',
                'sound eng' => 'Sound Engineer',
                'produksi' => 'Production',
                'art & set design' => 'Art & Set Properti',
                'art & set' => 'Art & Set Properti',
                'manager distribusi' => 'Distribution Manager',
                'editor promosi' => 'Editor Promotion',
                'design grafis' => 'Graphic Design',
                'qc' => 'Quality Control',
                'promosi' => 'Promotion',
            ];

            $mappedRole = $roleMap[strtolower($roleInput)] ?? $roleInput;

            $query->where(function ($q) use ($mappedRole, $roleInput) {
                $q->where('role', $mappedRole)
                    ->orWhere('role', $roleInput)
                    ->orWhereHas('employee', function ($q2) use ($mappedRole, $roleInput) {
                        $q2->where('jabatan_saat_ini', $mappedRole)
                            ->orWhere('jabatan_saat_ini', $roleInput);
                    });
            });
        }

        // Filter by department
        if ($request->has('department')) {
            $department = $request->input('department');
            $query->whereHas('employee', function ($q) use ($department) {
                $q->where('department', $department);
            });
        }

        // Filter by access level (untuk custom roles)
        if ($request->has('access_level')) {
            $accessLevel = $request->input('access_level');
            // Cari custom roles dengan access level tertentu
            $customRoleNames = \App\Models\CustomRole::where('access_level', $accessLevel)
                ->where('is_active', true)
                ->pluck('role_name')
                ->toArray();

            if (!empty($customRoleNames)) {
                $query->whereIn('role', $customRoleNames);
            }
        }

        // Limit results
        if ($request->has('limit')) {
            $query->limit($request->input('limit'));
        }

        $users = $query->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Display the specified user
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with('employee')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }
}