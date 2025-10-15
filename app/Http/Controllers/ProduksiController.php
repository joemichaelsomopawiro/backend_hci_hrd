<?php

namespace App\Http\Controllers;

use App\Models\ProduksiEquipmentRequest;
use App\Models\ShootingRunSheet;
use App\Models\MusicSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProduksiController extends Controller
{
    /**
     * Get equipment requests for Produksi
     */
    public function getEquipmentRequests(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Produksi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $requests = ProduksiEquipmentRequest::with(['submission', 'approvedBy'])
                ->where('created_by', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving equipment requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create equipment request
     */
    public function createEquipmentRequest(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Produksi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|exists:music_submissions,id',
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.name' => 'required|string|max:255',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.notes' => 'nullable|string|max:500',
                'request_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $equipmentRequest = ProduksiEquipmentRequest::create([
                'submission_id' => $request->submission_id,
                'equipment_list' => $request->equipment_list,
                'request_notes' => $request->request_notes,
                'status' => 'pending',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Equipment request created successfully.',
                'data' => $equipmentRequest->load(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating equipment request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create run sheet
     */
    public function createRunSheet(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Produksi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|exists:music_submissions,id',
                'shooting_date' => 'required|date|after:today',
                'location' => 'required|string|max:255',
                'crew_list' => 'required|array|min:1',
                'crew_list.*.name' => 'required|string|max:255',
                'crew_list.*.role' => 'required|string|max:100',
                'crew_list.*.contact' => 'nullable|string|max:50',
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.name' => 'required|string|max:255',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'shooting_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $runSheet = ShootingRunSheet::create([
                'submission_id' => $request->submission_id,
                'shooting_date' => $request->shooting_date,
                'location' => $request->location,
                'crew_list' => $request->crew_list,
                'equipment_list' => $request->equipment_list,
                'shooting_notes' => $request->shooting_notes,
                'status' => 'planned',
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Run sheet created successfully.',
                'data' => $runSheet->load(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating run sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update run sheet
     */
    public function updateRunSheet($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Produksi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $runSheet = ShootingRunSheet::findOrFail($id);

            if ($runSheet->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this run sheet.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'shooting_date' => 'nullable|date|after:today',
                'location' => 'nullable|string|max:255',
                'crew_list' => 'nullable|array|min:1',
                'crew_list.*.name' => 'required_with:crew_list|string|max:255',
                'crew_list.*.role' => 'required_with:crew_list|string|max:100',
                'crew_list.*.contact' => 'nullable|string|max:50',
                'equipment_list' => 'nullable|array|min:1',
                'equipment_list.*.name' => 'required_with:equipment_list|string|max:255',
                'equipment_list.*.quantity' => 'required_with:equipment_list|integer|min:1',
                'shooting_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $runSheet->update($request->only([
                'shooting_date', 'location', 'crew_list', 
                'equipment_list', 'shooting_notes'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Run sheet updated successfully.',
                'data' => $runSheet->fresh(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating run sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete shooting
     */
    public function completeShooting($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Produksi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $runSheet = ShootingRunSheet::findOrFail($id);

            if ($runSheet->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this run sheet.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'uploaded_files' => 'required|array|min:1',
                'uploaded_files.*.filename' => 'required|string|max:255',
                'uploaded_files.*.path' => 'required|string|max:500',
                'uploaded_files.*.url' => 'required|string|max:500',
                'uploaded_files.*.size' => 'required|integer|min:0',
                'uploaded_files.*.mime_type' => 'required|string|max:100',
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $runSheet->completeShooting(
                $request->uploaded_files, 
                $request->completion_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Shooting completed successfully.',
                'data' => $runSheet->fresh(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing shooting: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start shooting
     */
    public function startShooting($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'Produksi') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $runSheet = ShootingRunSheet::findOrFail($id);

            if ($runSheet->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this run sheet.'
                ], 403);
            }

            if (!$runSheet->canBeStarted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shooting cannot be started.'
                ], 400);
            }

            $runSheet->startShooting();

            return response()->json([
                'success' => true,
                'message' => 'Shooting started successfully.',
                'data' => $runSheet->fresh(['submission'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting shooting: ' . $e->getMessage()
            ], 500);
        }
    }
}
