<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ZoomLinkController extends Controller
{
    /**
     * Get current zoom link (public access)
     */
    public function getZoomLink()
    {
        try {
            $zoomLink = Setting::getValue('zoom_link');
            $meetingId = Setting::getValue('zoom_meeting_id');
            $passcode = Setting::getValue('zoom_passcode');

            return response()->json([
                'success' => true,
                'data' => [
                    'zoom_link' => $zoomLink,
                    'meeting_id' => $meetingId,
                    'passcode' => $passcode
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data link Zoom'
            ], 500);
        }
    }

    /**
     * Update zoom link (only for General Affairs)
     */
    public function updateZoomLink(Request $request)
    {
        // Validasi role - hanya GA yang bisa update
        if (!$this->canUpdateZoomLink()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengubah link Zoom'
            ], 403);
        }

        // Validasi input
        $validator = Validator::make($request->all(), [
            'zoom_link' => 'required|url',
            'meeting_id' => 'nullable|string|max:255',
            'passcode' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update zoom link
            Setting::setValue('zoom_link', $request->zoom_link);
            
            // Update meeting ID jika ada
            if ($request->meeting_id) {
                Setting::setValue('zoom_meeting_id', $request->meeting_id);
            }
            
            // Update passcode jika ada
            if ($request->passcode) {
                Setting::setValue('zoom_passcode', $request->passcode);
            }

            return response()->json([
                'success' => true,
                'message' => 'Link Zoom berhasil diperbarui',
                'data' => [
                    'zoom_link' => $request->zoom_link,
                    'meeting_id' => $request->meeting_id,
                    'passcode' => $request->passcode
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui link Zoom'
            ], 500);
        }
    }

    /**
     * Check if user can update zoom link
     */
    private function canUpdateZoomLink()
    {
        $user = auth()->user();
        if (!$user) return false;

        $allowedRoles = [
            'General Affairs',
            'HR',
            'Program Manager',
            'VP President',
            'President Director'
        ];

        $userRole = $user->role ?? '';
        return in_array($userRole, $allowedRoles);
    }
} 