<?php

use Illuminate\Support\Facades\Route;
use App\Models\MusicWorkflowNotification;

// Test routes untuk debugging
Route::get('/test/notifications/{id}', function ($id) {
    try {
        $notification = MusicWorkflowNotification::find($id);
        
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
                'id' => $id
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $notification->id,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at,
                'notification_type' => $notification->notification_type,
                'title' => $notification->title,
                'message' => $notification->message
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get('/test/notifications/count', function () {
    try {
        $count = MusicWorkflowNotification::count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_notifications' => $count
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
















