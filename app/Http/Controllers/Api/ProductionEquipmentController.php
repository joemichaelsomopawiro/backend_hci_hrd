<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionEquipment;
use App\Models\EquipmentInventory;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductionEquipmentController extends Controller
{
    /**
     * Get equipment list for Production role
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $query = ProductionEquipment::with(['episode', 'requestedBy', 'approvedBy', 'assignedTo']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by episode
        if ($request->has('episode_id')) {
            $query->where('episode_id', $request->episode_id);
        }

        $equipment = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $equipment
        ]);
    }

    /**
     * Request equipment from Art & Set Properti
     */
    public function requestEquipment(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'episode_id' => 'required|exists:episodes,id',
            'equipment_type' => 'required|string|max:255',
            'equipment_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'return_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $equipment = ProductionEquipment::create([
            'episode_id' => $request->episode_id,
            'equipment_type' => $request->equipment_type,
            'equipment_name' => $request->equipment_name,
            'quantity' => $request->quantity,
            'return_date' => $request->return_date,
            'notes' => $request->notes,
            'status' => 'pending_approval',
            'requested_by' => $user->id
        ]);

        // Notifikasi ke Art & Set Properti
        $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
        foreach ($artSetUsers as $artSetUser) {
            Notification::create([
                'user_id' => $artSetUser->id,
                'type' => 'equipment_request',
                'title' => 'Permintaan Alat Baru',
                'message' => "Production meminta alat: {$request->equipment_name} x{$request->quantity}",
                'data' => [
                    'equipment_id' => $equipment->id,
                    'equipment_name' => $request->equipment_name,
                    'quantity' => $request->quantity,
                    'requested_by' => $user->name
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan alat berhasil diajukan',
            'data' => $equipment
        ]);
    }

    /**
     * Upload files after shooting
     */
    public function uploadFiles(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $equipment = ProductionEquipment::findOrFail($id);

        if ($equipment->requested_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this equipment.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:mp4,avi,mov,jpg,jpeg,png|max:102400', // 100MB max
            'shooting_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedFiles = [];
        foreach ($request->file('files') as $file) {
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('production_files', $filename, 'public');
            
            $uploadedFiles[] = [
                'filename' => $filename,
                'path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ];
        }

        $equipment->update([
            'status' => 'completed',
            'file_paths' => $uploadedFiles,
            'shooting_notes' => $request->shooting_notes,
            'completed_at' => now()
        ]);

        // Notifikasi ke Editor
        $editors = \App\Models\User::where('role', 'Editor')->get();
        foreach ($editors as $editor) {
            Notification::create([
                'user_id' => $editor->id,
                'type' => 'shooting_completed',
                'title' => 'Hasil Shooting Selesai',
                'message' => "Production telah menyelesaikan shooting untuk episode {$equipment->episode->title}",
                'data' => [
                    'equipment_id' => $equipment->id,
                    'episode_id' => $equipment->episode_id,
                    'file_count' => count($uploadedFiles)
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'File berhasil diupload',
            'data' => $equipment
        ]);
    }

    /**
     * Return equipment to Art & Set Properti
     */
    public function returnEquipment(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $equipment = ProductionEquipment::findOrFail($id);

        if ($equipment->requested_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this equipment.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'return_condition' => 'required|in:good,damaged,lost',
            'return_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $equipment->update([
            'status' => 'returned',
            'return_condition' => $request->return_condition,
            'return_notes' => $request->return_notes,
            'returned_at' => now()
        ]);

        // Notifikasi ke Art & Set Properti
        $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
        foreach ($artSetUsers as $artSetUser) {
            Notification::create([
                'user_id' => $artSetUser->id,
                'type' => 'equipment_returned',
                'title' => 'Alat Dikembalikan',
                'message' => "Production telah mengembalikan alat: {$equipment->equipment_name}",
                'data' => [
                    'equipment_id' => $equipment->id,
                    'equipment_name' => $equipment->equipment_name,
                    'return_condition' => $request->return_condition
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Alat berhasil dikembalikan',
            'data' => $equipment
        ]);
    }

    /**
     * Get statistics for Production
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if ($user->role !== 'Production') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $stats = [
            'total_requests' => ProductionEquipment::where('requested_by', $user->id)->count(),
            'pending_approval' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'pending_approval')->count(),
            'approved' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'approved')->count(),
            'completed' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'completed')->count(),
            'returned' => ProductionEquipment::where('requested_by', $user->id)->where('status', 'returned')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}













