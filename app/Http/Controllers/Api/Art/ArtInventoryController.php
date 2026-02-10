<?php

namespace App\Http\Controllers\Api\Art;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ArtInventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = InventoryItem::query();

            // Filter by name
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $items = $query->orderBy('name', 'asc')->get();

            return response()->json(['success' => true, 'data' => $items, 'message' => 'Inventory items retrieved successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'total_quantity' => 'required|integer|min:0',
                'status' => 'required|in:active,maintenance,lost',
                'photo' => 'nullable|image|max:5120', // 5MB Max
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $data = $request->except('photo');
            $data['created_by'] = $user->id;
            $data['available_quantity'] = $data['total_quantity']; // Initially available = total

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('inventory_photos', 'public');
                $data['photo_url'] = Storage::url($path);
            }

            $item = InventoryItem::create($data);

            return response()->json(['success' => true, 'data' => $item, 'message' => 'Inventory item created successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $item = InventoryItem::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'total_quantity' => 'sometimes|required|integer|min:0',
                'status' => 'sometimes|required|in:active,maintenance,lost',
                'photo' => 'nullable|image|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }

            $data = $request->except('photo');

            // Adjust available quantity if total quantity changes
            if (isset($data['total_quantity'])) {
                $diff = $data['total_quantity'] - $item->total_quantity;
                $data['available_quantity'] = $item->available_quantity + $diff;
                if ($data['available_quantity'] < 0) {
                    return response()->json(['success' => false, 'message' => 'Cannot reduce total quantity below currently borrowed amount.'], 400);
                }
            }

            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($item->photo_url) {
                    $oldPath = str_replace('/storage/', '', $item->photo_url);
                    Storage::disk('public')->delete($oldPath);
                }

                $path = $request->file('photo')->store('inventory_photos', 'public');
                $data['photo_url'] = Storage::url($path);
            }

            $item->update($data);

            return response()->json(['success' => true, 'data' => $item, 'message' => 'Inventory item updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $item = InventoryItem::findOrFail($id);
            $item->delete();
            return response()->json(['success' => true, 'message' => 'Inventory item deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
