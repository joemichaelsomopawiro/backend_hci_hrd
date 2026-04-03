<?php

namespace App\Http\Controllers\Api\Music;

use App\Http\Controllers\Controller;
use App\Models\EquipmentLoan;
use App\Models\EquipmentLoanItem;
use App\Models\InventoryItem;
use App\Models\SoundEngineerRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EquipmentLoanController extends Controller
{
    /**
     * Store a new equipment loan request for one or more Vocal Recording sessions.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sound_engineer_recording_ids' => 'required|array|min:1',
            'sound_engineer_recording_ids.*' => 'exists:sound_engineer_recordings,id',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'loan_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:loan_date',
            'request_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated.'], 401);
            }

            // Global role check (Producer/PM/Production)
            $isGlobalRole = \App\Helpers\MusicProgramAuthorization::hasProducerAccess($user) || $user->role === 'Production';

            // Check if user is coordinator for ALL requested recordings (if not global role)
            if (!$isGlobalRole) {
                $recordings = SoundEngineerRecording::whereIn('id', $request->sound_engineer_recording_ids)->get();
                foreach ($recordings as $rec) {
                    $isCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $rec->episode_id, 'recording');
                    if (!$isCoordinator) {
                        return response()->json([
                            'success' => false, 
                            'message' => "Unauthorized. Anda bukan Koordinator Rekam untuk Episode " . ($rec->episode->episode_number ?? $rec->id) . "."
                        ], 403);
                    }
                }
            }

            $loan = EquipmentLoan::create([
                'borrower_id' => $user->id,
                'status' => 'pending',
                'loan_date' => $request->loan_date,
                'return_date' => $request->return_date,
                'request_notes' => $request->request_notes,
            ]);

            foreach ($request->items as $item) {
                // Check availability
                $inventory = InventoryItem::find($item['inventory_item_id']);
                if ($inventory->available_quantity < $item['quantity']) {
                    throw new \Exception("Stok tidak mencukupi untuk item: " . $inventory->name);
                }

                EquipmentLoanItem::create([
                    'equipment_loan_id' => $loan->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity' => $item['quantity']
                ]);
            }

            // Link to vocal recordings via pivot
            $loan->vocalRecordings()->sync($request->sound_engineer_recording_ids);

            // Update Vocal Recording statuses if they were draft/pending
            $recordings = SoundEngineerRecording::whereIn('id', $request->sound_engineer_recording_ids)->get();
            foreach ($recordings as $recording) {
                if (in_array($recording->status, ['draft', 'pending'])) {
                    $recording->status = 'pending'; // Waiting for equipment
                    $recording->save();
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Permintaan peminjaman alat berhasil diajukan.',
                'data' => $loan->load(['loanItems.inventoryItem', 'vocalRecordings'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Initiate equipment return for a loan.
     */
    public function returnLoan(Request $request, $id)
    {
        try {
            $loan = EquipmentLoan::with('vocalRecordings')->findOrFail($id);

            // Check if borrower or coordinator
            $user = Auth::user();
            $isBorrower = $loan->borrower_id === $user->id;
            
            // For simplicity in this specialized controller, we allow the borrower 
            // to initiate return.
            if (!$isBorrower) {
                 return response()->json(['success' => false, 'message' => 'Unauthorized: Only the borrower can initiate return.'], 403);
            }

            if ($loan->status !== 'active') {
                return response()->json(['success' => false, 'message' => 'Only active loans can be returned.'], 400);
            }

            // Update status to 'returned' (waiting for Art/Prop to confirm)
            // Or use a 'returning' status to distinguish. Let's stick to 'returned' 
            // but Art/Prop will use 'markAsReturned' to finalize.
            
            $loan->update([
                'status' => 'returned',
                'return_notes' => $request->return_notes ?? $loan->return_notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengembalian alat telah diajukan. Harap serahkan alat ke Art & Set Properti.',
                'data' => $loan
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Initiate equipment return for a recording (resolves the active loan automatically).
     */
    public function returnByRecording(Request $request, $recordingId)
    {
        try {
            $recording = SoundEngineerRecording::findOrFail($recordingId);
            
            // Find the active loan for this recording
            $loan = $recording->equipmentLoans()
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$loan) {
                return response()->json(['success' => false, 'message' => 'No active equipment loan found for this recording.'], 404);
            }

            // Check if borrower or coordinator
            $user = Auth::user();
            if ($loan->borrower_id !== $user->id) {
                 // Check if it's the coordinator
                 $isCoordinator = \App\Models\ProductionTeamMember::isCoordinatorForEpisode($user->id, $recording->episode_id, 'recording');
                 if (!$isCoordinator) {
                     return response()->json(['success' => false, 'message' => 'Unauthorized: Only the borrower or coordinator can initiate return.'], 403);
                 }
            }

            $loan->update([
                'status' => 'returned',
                'return_notes' => $request->return_notes ?? "Returned via Vocal Recording Dashboard."
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengembalian alat telah diajukan. Harap serahkan alat ke Art & Set Properti.',
                'data' => $loan
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
