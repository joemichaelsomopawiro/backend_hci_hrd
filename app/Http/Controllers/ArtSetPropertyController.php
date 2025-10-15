<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ArtSetProperty;
use App\Models\MusicSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ArtSetPropertyController extends Controller
{
    /**
     * Get all property requests
     */
    public function getPropertyRequests(Request $request)
    {
        try {
            $requests = ArtSetProperty::with(['submission', 'requestedBy', 'approvedBy'])
                ->when($request->status, function($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($request->category, function($query, $category) {
                    return $query->where('category', $category);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Property requests retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving property requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new property request
     */
    public function createPropertyRequest(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'submission_id' => 'required|exists:music_submissions,id',
                'property_name' => 'required|string|max:255',
                'description' => 'required|string',
                'category' => 'required|in:furniture,decoration,lighting,props,costume,other',
                'cost' => 'nullable|numeric|min:0',
                'supplier' => 'nullable|string|max:255',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $property = ArtSetProperty::create([
                'submission_id' => $request->submission_id,
                'property_name' => $request->property_name,
                'description' => $request->description,
                'category' => $request->category,
                'cost' => $request->cost,
                'supplier' => $request->supplier,
                'notes' => $request->notes,
                'requested_by' => Auth::id(),
                'status' => 'requested'
            ]);

            return response()->json([
                'success' => true,
                'data' => $property->load(['submission', 'requestedBy']),
                'message' => 'Property request created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating property request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get property request detail
     */
    public function getPropertyRequestDetail($id)
    {
        try {
            $property = ArtSetProperty::with(['submission', 'requestedBy', 'approvedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $property,
                'message' => 'Property request detail retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving property request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update property request
     */
    public function updatePropertyRequest(Request $request, $id)
    {
        try {
            $property = ArtSetProperty::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'property_name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'category' => 'sometimes|in:furniture,decoration,lighting,props,costume,other',
                'cost' => 'nullable|numeric|min:0',
                'supplier' => 'nullable|string|max:255',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $property->update($request->only([
                'property_name', 'description', 'category', 'cost', 'supplier', 'notes'
            ]));

            return response()->json([
                'success' => true,
                'data' => $property->load(['submission', 'requestedBy', 'approvedBy']),
                'message' => 'Property request updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating property request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve property request
     */
    public function approvePropertyRequest(Request $request, $id)
    {
        try {
            $property = ArtSetProperty::findOrFail($id);
            
            $property->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $property->load(['submission', 'requestedBy', 'approvedBy']),
                'message' => 'Property request approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving property request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject property request
     */
    public function rejectPropertyRequest(Request $request, $id)
    {
        try {
            $property = ArtSetProperty::findOrFail($id);
            
            $property->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $property->load(['submission', 'requestedBy', 'approvedBy']),
                'message' => 'Property request rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting property request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(Request $request, $id)
    {
        try {
            $property = ArtSetProperty::findOrFail($id);
            
            $property->update([
                'status' => 'delivered',
                'delivered_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'data' => $property->load(['submission', 'requestedBy', 'approvedBy']),
                'message' => 'Property marked as delivered successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking property as delivered: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark as returned
     */
    public function markAsReturned(Request $request, $id)
    {
        try {
            $property = ArtSetProperty::findOrFail($id);
            
            $property->update([
                'status' => 'returned'
            ]);

            return response()->json([
                'success' => true,
                'data' => $property->load(['submission', 'requestedBy', 'approvedBy']),
                'message' => 'Property marked as returned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking property as returned: ' . $e->getMessage()
            ], 500);
        }
    }
}
