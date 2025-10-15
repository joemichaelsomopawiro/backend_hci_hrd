<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Exception;

class AudioController extends BaseController
{
    /**
     * Stream audio file
     */
    public function stream($songId)
    {
        try {
            $song = Song::findOrFail($songId);

            if (!$song->audio_file_path) {
                abort(404, 'Audio file not found');
            }

            $filePath = storage_path('app/public/' . $song->audio_file_path);

            if (!file_exists($filePath)) {
                abort(404, 'Audio file not found on disk');
            }

            $fileSize = filesize($filePath);
            $mimeType = $song->mime_type ?: 'audio/mpeg';

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Accept-Ranges' => 'bytes'
            ]);

        } catch (Exception $e) {
            abort(500, 'Error streaming audio: ' . $e->getMessage());
        }
    }

    /**
     * Get audio info
     */
    public function info($songId): JsonResponse
    {
        try {
            $song = Song::findOrFail($songId);

            if (!$song->audio_file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audio file not found for this song.'
                ], 404);
            }

            $filePath = storage_path('app/public/' . $song->audio_file_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audio file not found on disk.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'song_id' => $song->id,
                    'title' => $song->title,
                    'artist' => $song->artist,
                    'duration' => $song->duration,
                    'file_name' => $song->audio_file_name,
                    'file_size' => $song->file_size,
                    'mime_type' => $song->mime_type,
                    'audio_url' => $song->audio_url
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving audio info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload audio file
     */
    public function upload(Request $request, $songId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Music Arranger', 'Producer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $song = Song::findOrFail($songId);

            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav,ogg|max:10240' // 10MB max
            ]);

            // Delete old file if exists
            if ($song->audio_file_path) {
                Storage::disk('public')->delete($song->audio_file_path);
            }

            $file = $request->file('audio');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('songs', $filename, 'public');

            $song->update([
                'audio_file_path' => $path,
                'audio_file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Audio file uploaded successfully.',
                'data' => [
                    'audio_url' => $song->fresh()->audio_url,
                    'file_name' => $song->audio_file_name,
                    'file_size' => $song->file_size,
                    'mime_type' => $song->mime_type
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading audio file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete audio file
     */
    public function delete($songId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!in_array($user->role, ['Music Arranger', 'Producer'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $song = Song::findOrFail($songId);

            if (!$song->audio_file_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'No audio file to delete.'
                ], 400);
            }

            // Delete file from storage
            Storage::disk('public')->delete($song->audio_file_path);

            // Update song record
            $song->update([
                'audio_file_path' => null,
                'audio_file_name' => null,
                'file_size' => null,
                'mime_type' => null,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Audio file deleted successfully.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting audio file: ' . $e->getMessage()
            ], 500);
        }
    }
}