<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ArtInventoryController extends Controller
{
    // List all inventory items
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    // Create new inventory item
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_quantity' => 'required|integer|min:0',
            'status' => 'required|in:active,maintenance,lost,damaged,available',
            'photo_link' => 'nullable|url' // Strict Link Only
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->only(['name', 'description', 'total_quantity']);

            // Map frontend status to database enum if needed, or just use as is
            // Enum in DB: active, maintenance, lost. Frontend uses 'available' which maps to 'active' usually?
            // DB Migration says: ['active', 'maintenance', 'lost']. 
            // Let's assume 'available' -> 'active' for consistency or allow 'active' from FE.
            $status = $request->status;
            if ($status === 'available')
                $status = 'active';
            $data['status'] = $status;

            $data['available_quantity'] = $data['total_quantity']; // Initial diff is 0? No, available = total initially.
            $data['created_by'] = auth()->id();

            // Physical file upload disabled
            if ($request->hasFile('photo')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical file uploads are disabled. Please use photo_link.'
                ], 405);
            }

            if ($request->has('photo_link')) {
                $data['photo_url'] = $request->photo_link;
            }

            $item = InventoryItem::create($data);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Item created successfully',
                'data' => $item
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create item: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update inventory item
    public function update(Request $request, $id)
    {
        $item = InventoryItem::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'total_quantity' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,maintenance,lost,damaged,available',
            'photo_link' => 'nullable|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->only(['name', 'description', 'total_quantity', 'status']);

            // Handle status mapping
            if (isset($data['status']) && $data['status'] === 'available') {
                $data['status'] = 'active';
            }

            // Calculate available quantity adjustment if total_quantity changes
            if (isset($data['total_quantity'])) {
                $diff = $data['total_quantity'] - $item->total_quantity;
                $data['available_quantity'] = $item->available_quantity + $diff;
                if ($data['available_quantity'] < 0) {
                    // Should we block? or just set to 0? 
                    // Logic: If we reduce total, available reduces. If avail < 0, it means we have more loans than items now.
                    // For now, let's allow it but maybe warn? Or just clamp to 0?
                    // Let's allow negative for logic consistency, or clamp to 0 and act like it's overbooked.
                    // The migration schema is integer, signed by default.
                }
            }

            // Physical file upload disabled
            if ($request->hasFile('photo')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Physical file uploads are disabled. Please use photo_link.'
                ], 405);
            }

            if ($request->has('photo_link')) {
                $data['photo_url'] = $request->photo_link;
            }

            $item->update($data);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'data' => $item
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete inventory item
    public function destroy($id)
    {
        $item = InventoryItem::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        $item->delete();
        return response()->json(['success' => true, 'message' => 'Item deleted successfully']);
    }
}
