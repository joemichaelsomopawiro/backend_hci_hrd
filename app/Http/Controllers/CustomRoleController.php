<?php

namespace App\Http\Controllers;

use App\Models\CustomRole;
use App\Services\RoleHierarchyService;
use App\Services\DatabaseEnumService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CustomRoleController extends Controller
{
    /**
     * Mendapatkan semua custom roles (hanya untuk HR)
     */
    public function index(): JsonResponse
    {
        try {
            $customRoles = CustomRole::with('creator:id,name')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $customRoles,
                'message' => 'Custom roles retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve custom roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Membuat custom role baru (hanya untuk HR)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'role_name' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:custom_roles,role_name',
                    function ($attribute, $value, $fail) {
                        // Cek apakah role name sudah ada di standard roles
                        $standardRoles = RoleHierarchyService::getAllAvailableRoles();
                        if (in_array($value, $standardRoles)) {
                            $fail('Role name already exists in standard roles.');
                        }
                    },
                ],
                'description' => 'nullable|string|max:1000',
                'access_level' => [
                    'nullable',
                    Rule::in(['employee', 'manager', 'hr_readonly', 'hr_full'])
                ]
            ]);

            $customRole = CustomRole::create([
                'role_name' => $validated['role_name'],
                'description' => $validated['description'] ?? null,
                'access_level' => $validated['access_level'] ?? 'employee', // Default ke employee
                'created_by' => auth()->id(),
                'is_active' => true
            ]);

            // Update database enum values to include new custom role
            DatabaseEnumService::updateRoleEnums();

            $customRole->load('creator:id,name');

            return response()->json([
                'success' => true,
                'data' => $customRole,
                'message' => 'Custom role created successfully'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create custom role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan detail custom role
     */
    public function show($id): JsonResponse
    {
        try {
            $customRole = CustomRole::with('creator:id,name')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $customRole,
                'message' => 'Custom role retrieved successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Custom role not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve custom role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update custom role
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $customRole = CustomRole::findOrFail($id);

            $validated = $request->validate([
                'role_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('custom_roles', 'role_name')->ignore($id),
                    function ($attribute, $value, $fail) use ($customRole) {
                        // Cek apakah role name sudah ada di standard roles (kecuali role saat ini)
                        if ($value !== $customRole->role_name) {
                            $standardRoles = RoleHierarchyService::getAllAvailableRoles();
                            if (in_array($value, $standardRoles)) {
                                $fail('Role name already exists in standard roles.');
                            }
                        }
                    },
                ],
                'description' => 'nullable|string|max:1000',
                'access_level' => [
                    'nullable',
                    Rule::in(['employee', 'manager', 'hr_readonly', 'hr_full'])
                ],
                'is_active' => 'boolean'
            ]);

            // Set default access_level jika tidak diberikan
            if (!isset($validated['access_level'])) {
                $validated['access_level'] = 'employee';
            }
            
            $customRole->update($validated);
            
            // Update database enum values in case role name changed
            DatabaseEnumService::updateRoleEnums();
            
            $customRole->load('creator:id,name');

            return response()->json([
                'success' => true,
                'data' => $customRole,
                'message' => 'Custom role updated successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Custom role not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update custom role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete custom role (set is_active = false)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $customRole = CustomRole::findOrFail($id);
            $customRole->update(['is_active' => false]);
            
            // Update database enum values to remove deactivated role
            DatabaseEnumService::updateRoleEnums();

            return response()->json([
                'success' => true,
                'message' => 'Custom role deactivated successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Custom role not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate custom role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan semua role yang tersedia (standard + custom)
     */
    public function getAllRoles(): JsonResponse
    {
        try {
            $standardRoles = [
                'managers' => RoleHierarchyService::getManagerRoles(),
                'employees' => RoleHierarchyService::getEmployeeRoles(),
                'readonly' => RoleHierarchyService::getReadOnlyRoles()
            ];

            $customRoles = CustomRole::active()->get()->groupBy('access_level');

            return response()->json([
                'success' => true,
                'data' => [
                    'standard_roles' => $standardRoles,
                    'custom_roles' => $customRoles
                ],
                'message' => 'All roles retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles: ' . $e->getMessage()
            ], 500);
        }
    }
}