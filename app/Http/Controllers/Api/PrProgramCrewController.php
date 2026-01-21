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

        $crews = PrProgramCrew::with('user:id,name,email,role,profile_picture')
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
    public function store(Request $request, $programId)
    {
        $program = PrProgram::find($programId);

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Program tidak ditemukan'
            ], 404);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|max:100', // e.g. "Kreatif", "Editor"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is already assigned to this program
        $exists = PrProgramCrew::where('program_id', $programId)
            ->where('user_id', $request->user_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Staff ini sudah ada di dalam tim program ini'
            ], 400);
        }

        try {
            $crew = PrProgramCrew::create([
                'program_id' => $programId,
                'user_id' => $request->user_id,
                'role' => $request->role
            ]);

            // Transform response to include user details
            $crew->load('user:id,name,email,role,profile_picture');

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menambahkan anggota tim',
                'data' => $crew
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan anggota tim: ' . $e->getMessage()
            ], 500);
        }
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
