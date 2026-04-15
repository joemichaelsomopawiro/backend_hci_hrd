<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Helpers\ProgramManagerAuthorization;

class DeadlineAssignmentController extends Controller
{
    /**
     * Reassign a specific deadline to a backup user
     */
    public function reassign(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $isPM = ProgramManagerAuthorization::isProgramManager($user);
            
            // Authorization check: Only Producer or Program Manager
            if ($user->role !== 'Producer' && !$isPM) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Producer atau Program Manager yang dapat mengubah penugasan.'
                ], 403);
            }

            $deadline = Deadline::with('episode.program')->findOrFail($id);
            
            // If producer, check if they manage this program
            if ($user->role === 'Producer' && !$isPM) {
                $isProducerOfProgram = $deadline->episode->program->productionTeam && 
                                      (int)$deadline->episode->program->productionTeam->producer_id === (int)$user->id;
                
                if (!$isProducerOfProgram) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak berwenang mengelola program ini.'
                    ], 403);
                }
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $targetUser = $request->user_id ? User::find($request->user_id) : null;
            
            $deadline->update([
                'assigned_user_id' => $request->user_id
            ]);

            // Create Notification for the backup user
            if ($targetUser) {
                \App\Models\Notification::create([
                    'user_id' => $targetUser->id,
                    'type' => 'backup_assignment',
                    'title' => 'Penugasan Backup Baru',
                    'message' => "Anda ditunjuk sebagai backup/pengganti untuk tugas " . 
                                $deadline->role_label . " di Episode " . 
                                $deadline->episode->episode_number . " (" . $deadline->episode->program->name . ").",
                    'data' => [
                        'deadline_id' => $deadline->id,
                        'episode_id' => $deadline->episode_id,
                        'role' => $deadline->role
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $targetUser 
                    ? "Berhasil menunjuk {$targetUser->name} sebagai backup." 
                    : "Berhasil mengembalikan penugasan ke tim utama.",
                'data' => $deadline->fresh('assignee')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah penugasan: ' . $e->getMessage()
            ], 500);
        }
    }
}
