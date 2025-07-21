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
            $customRoles = CustomRole::with(['creator:id,name', 'supervisor:id,role_name'])
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
                    'required',
                    Rule::in(['employee', 'manager', 'hr_readonly', 'hr_full', 'director'])
                ],
                'department' => [
                    'required',
                    Rule::in(['hr', 'production', 'distribution', 'executive'])
                ],
                'supervisor_id' => [
                    'nullable',
                    // 'exists:custom_roles,id', // Sementara dinonaktifkan
                    // function ($attribute, $value, $fail) {
                    //     // Validasi hierarchy untuk mencegah circular reference
                    //     if ($value && !RoleHierarchyService::validateHierarchy(null, $value)) {
                    //         $fail('Invalid supervisor selection. Cannot create circular reference.');
                    //     }
                    // }
                ]
            ]);

            // Validasi supervisor sementara dinonaktifkan
            // TODO: Implementasi supervisor validation setelah data supervisor tersedia
            // if ($validated['access_level'] === 'employee' && !$validated['supervisor_id']) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Employee roles must have a supervisor'
            //     ], 422);
            // }

            $customRole = CustomRole::create([
                'role_name' => $validated['role_name'],
                'description' => $validated['description'] ?? null,
                'access_level' => $validated['access_level'],
                'department' => $validated['department'],
                'supervisor_id' => $validated['supervisor_id'],
                'created_by' => auth()->id(),
                'is_active' => true
            ]);

            // Update database enum values to include new custom role
            DatabaseEnumService::updateRoleEnums();

            $customRole->load(['creator:id,name', 'supervisor:id,role_name']);

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
            $customRole = CustomRole::with(['creator:id,name', 'supervisor:id,role_name'])->findOrFail($id);

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
                    'required',
                    Rule::in(['employee', 'manager', 'hr_readonly', 'hr_full', 'director'])
                ],
                'department' => [
                    'required',
                    Rule::in(['hr', 'production', 'distribution', 'executive'])
                ],
                'supervisor_id' => [
                    'nullable',
                    // 'exists:custom_roles,id', // Sementara dinonaktifkan
                    // function ($attribute, $value, $fail) use ($id) {
                    //     // Validasi hierarchy untuk mencegah circular reference
                    //     if ($value && !RoleHierarchyService::validateHierarchy($id, $value)) {
                    //         $fail('Invalid supervisor selection. Cannot create circular reference.');
                    //     }
                    // }
                ],
                'is_active' => 'boolean'
            ]);

            // Validasi supervisor sementara dinonaktifkan
            // TODO: Implementasi supervisor validation setelah data supervisor tersedia
            // if ($validated['access_level'] === 'employee' && !$validated['supervisor_id']) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Employee roles must have a supervisor'
            //     ], 422);
            // }
            
            $customRole->update($validated);
            
            // Update database enum values in case role name changed
            DatabaseEnumService::updateRoleEnums();
            
            $customRole->load(['creator:id,name', 'supervisor:id,role_name']);

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

            $customRoles = CustomRole::with('supervisor:id,role_name')
                ->active()
                ->get()
                ->groupBy('access_level');

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

    /**
     * Mendapatkan options untuk form (departments, access levels, supervisors)
     */
    public function getFormOptions(): JsonResponse
    {
        try {
            $options = [
                'departments' => CustomRole::getDepartmentOptions(),
                'access_levels' => CustomRole::getAccessLevelOptions(),
                'supervisors' => RoleHierarchyService::getAvailableManagers(),
                'hierarchy' => RoleHierarchyService::getFullHierarchy()
            ];

            return response()->json([
                'success' => true,
                'data' => $options,
                'message' => 'Form options retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve form options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan roles berdasarkan department
     */
    public function getRolesByDepartment($department): JsonResponse
    {
        try {
            $roles = RoleHierarchyService::getRolesByDepartment($department);

            return response()->json([
                'success' => true,
                'data' => $roles,
                'message' => 'Roles by department retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles by department: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan hierarchy untuk role tertentu
     */
    public function getRoleHierarchy($roleName): JsonResponse
    {
        try {
            $supervisor = RoleHierarchyService::getSupervisorForRole($roleName);
            $department = RoleHierarchyService::getDepartmentForRole($roleName);
            $subordinates = RoleHierarchyService::getAllSubordinates($roleName);

            return response()->json([
                'success' => true,
                'data' => [
                    'role_name' => $roleName,
                    'supervisor' => $supervisor,
                    'department' => $department,
                    'subordinates' => $subordinates
                ],
                'message' => 'Role hierarchy retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role hierarchy: ' . $e->getMessage()
            ], 500);
        }
    }
}