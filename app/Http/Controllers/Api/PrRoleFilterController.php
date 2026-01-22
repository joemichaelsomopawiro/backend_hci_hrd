<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RoleHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PrRoleFilterController extends Controller
{
    /**
     * Get list of roles that current user can filter by
     * 
     * Endpoint: GET /api/pr/accessible-roles
     * 
     * Returns list of roles that the authenticated user has access to view.
     * Used for role filtering dropdown in Program Regular dashboard.
     * 
     * @return JsonResponse
     */
    public function getAccessibleRoles(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $userRole = $user->role;

            // Get roles that this user can access
            $accessibleRoles = RoleHierarchyService::getRolesAccessibleByUser($userRole);

            // Format for dropdown
            $formattedRoles = [];

            // Only add "All Roles" option if user has subordinates
            if (!empty($accessibleRoles)) {
                $formattedRoles[] = [
                    'value' => 'all',
                    'label' => 'Semua Role'
                ];

                // Add each accessible role
                foreach ($accessibleRoles as $role) {
                    $formattedRoles[] = [
                        'value' => $role,
                        'label' => $role
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user_role' => $userRole,
                    'roles' => $formattedRoles,
                    'has_subordinates' => !empty($accessibleRoles)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accessible roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate if user can access specific role data
     * 
     * Endpoint: GET /api/pr/validate-role-access/{targetRole}
     * 
     * @param string $targetRole
     * @return JsonResponse
     */
    public function validateRoleAccess(string $targetRole): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $canAccess = RoleHierarchyService::canAccessRoleData($user->role, $targetRole);

            return response()->json([
                'success' => true,
                'data' => [
                    'can_access' => $canAccess,
                    'user_role' => $user->role,
                    'target_role' => $targetRole
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate role access',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
