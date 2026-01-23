<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrProgram;
use App\Models\PrProgramCrew;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Constants\Role;

class PrProgramCrewController extends Controller
{
    /**
     * Get list of crew members for a program
     */
    public function index($programId)
    {
        $program = PrProgram::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program tidak ditemukan'
            ], 404);
        }

        // Optional: Check permissions specific to viewing crew
        // For now, allow authenticated users to view

        $crews = PrProgramCrew::with([
            'user' => function ($query) {
                // Select only relevant user fields, avoiding profile_picture if it doesn't exist
                $query->select('id', 'name', 'email', 'role');
            }
        ])
            ->where('program_id', $programId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $crews
        ]);
    }

    /**
     * Add a crew member to a program
     */
    /**
     * Add crew members to a program (Bulk Support)
     */
    public function store(Request $request, $programId)
    {
        $program = PrProgram::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program tidak ditemukan'
            ], 404);
        }

        // Support both single entry (legacy) and bulk (new)
        $members = $request->input('members');
        
        // If 'members' is not present, assume single entry and normalize to array
        if (!$members) {
            $members = [[
                'user_id' => $request->user_id,
                'role' => $request->role
            ]];
        }

        $addedMembers = [];
        $errors = [];

        foreach ($members as $index => $memberData) {
            $validator = Validator::make($memberData, [
                'user_id' => 'required|exists:users,id',
                'role' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ];
                continue;
            }

            // CRITICAL: Check if ROLE already exists in this program
            // "Tidak boleh ada 2 role yang sama."
            $roleExists = PrProgramCrew::where('program_id', $programId)
                ->where('role', $memberData['role'])
                ->exists();

            if ($roleExists) {
                // If it's the exact same user AND role, just skip (idempotent)
                $exactMatch = PrProgramCrew::where('program_id', $programId)
                    ->where('role', $memberData['role'])
                    ->where('user_id', $memberData['user_id'])
                    ->exists();

                if ($exactMatch) {
                    continue; // Already exists, perfectly fine
                }

                // If role exists but handled by someone else (or even same person but caught by logic above)
                // Actually constraint is "No 2 same roles".
                $errors[] = [
                    'index' => $index,
                    'message' => "Posisi '{$memberData['role']}' sudah terisi di program ini."
                ];
                continue;
            }

            // Note: We DO NOT check if user_id exists anymore. 
            // "Kalau nama Boleh sama" means one user can have multiple roles.

            try {
                $crew = PrProgramCrew::create([
                    'program_id' => $programId,
                    'user_id' => $memberData['user_id'],
                    'role' => $memberData['role']
                ]);

                // Transform response to include user details
                $crew->load([
                    'user' => function ($query) {
                        $query->select('id', 'name', 'email', 'role');
                    }
                ]);

                $addedMembers[] = $crew;

            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'message' => 'Error database: ' . $e->getMessage()
                ];
            }
        }

        if (count($errors) > 0 && count($addedMembers) === 0) {
            // If all failed
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan anggota tim',
                'errors' => $errors
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menambahkan ' . count($addedMembers) . ' anggota tim',
            'data' => $addedMembers,
            'partial_errors' => count($errors) > 0 ? $errors : null
        ], 201);
    }

    /**
     * Remove a crew member from a program
     */
    public function destroy($programId, $memberId)
    {
        // memberId here is the ID of the pr_program_crews record (the "assignment" ID), not the user_id

        $crew = PrProgramCrew::where('program_id', $programId)
            ->where('id', $memberId)
            ->first();

        if (!$crew) {
            return response()->json([
                'success' => false,
                'message' => 'Data anggota tim tidak ditemukan di program ini'
            ], 404);
        }

        try {
            $crew->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus anggota tim'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota tim: ' . $e->getMessage()
            ], 500);
        }
    }
}
