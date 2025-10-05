<?php

namespace App\Http\Controllers;

use App\Models\ProductionEquipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductionEquipment::with(['assignedUser', 'program', 'episode']);

            // Filter berdasarkan status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter berdasarkan category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter berdasarkan assigned user
            if ($request->has('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            }

            // Filter berdasarkan program
            if ($request->has('program_id')) {
                $query->where('program_id', $request->program_id);
            }

            // Search
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $equipment = $query->orderBy('name')->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Production equipment retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'required|string|max:100',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'status' => 'required|in:available,in_use,maintenance,retired',
                'assigned_to' => 'nullable|exists:users,id',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'last_maintenance' => 'nullable|date',
                'next_maintenance' => 'nullable|date',
                'notes' => 'nullable|string',
                'specifications' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment = ProductionEquipment::create($request->all());
            $equipment->load(['assignedUser', 'program', 'episode']);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Production equipment created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating production equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::with(['assignedUser', 'program', 'episode'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Production equipment retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving production equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'sometimes|required|string|max:100',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'status' => 'sometimes|required|in:available,in_use,maintenance,retired',
                'assigned_to' => 'nullable|exists:users,id',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'last_maintenance' => 'nullable|date',
                'next_maintenance' => 'nullable|date',
                'notes' => 'nullable|string',
                'specifications' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipment->update($request->all());
            $equipment->load(['assignedUser', 'program', 'episode']);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Production equipment updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating production equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);
            $equipment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Production equipment deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting production equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign equipment to user
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'assigned_to' => 'required|exists:users,id',
                'program_id' => 'nullable|exists:programs,id',
                'episode_id' => 'nullable|exists:episodes,id',
                'return_date' => 'nullable|date|after:now'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($equipment->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => 'Equipment is not available for assignment'
                ], 400);
            }

            $equipment->update([
                'assigned_to' => $request->assigned_to,
                'program_id' => $request->program_id,
                'episode_id' => $request->episode_id,
                'status' => 'in_use',
                'assigned_at' => now(),
                'return_date' => $request->return_date
            ]);

            $equipment->load(['assignedUser', 'program', 'episode']);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Equipment assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unassign equipment
     */
    public function unassign(Request $request, string $id): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::findOrFail($id);

            $equipment->update([
                'assigned_to' => null,
                'program_id' => null,
                'episode_id' => null,
                'status' => 'available',
                'assigned_at' => null,
                'return_date' => null
            ]);

            $equipment->load(['assignedUser', 'program', 'episode']);

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Equipment unassigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unassigning equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available equipment
     */
    public function getAvailable(Request $request): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::with(['assignedUser', 'program', 'episode'])
                ->where('status', 'available')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Available equipment retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get equipment needing maintenance
     */
    public function getNeedingMaintenance(Request $request): JsonResponse
    {
        try {
            $equipment = ProductionEquipment::with(['assignedUser', 'program', 'episode'])
                ->where('next_maintenance', '<=', now())
                ->where('status', '!=', 'retired')
                ->orderBy('next_maintenance')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $equipment,
                'message' => 'Equipment needing maintenance retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving equipment needing maintenance: ' . $e->getMessage()
            ], 500);
        }
    }
}
