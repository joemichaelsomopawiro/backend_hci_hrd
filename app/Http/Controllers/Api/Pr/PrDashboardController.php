<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrProductionSchedule;
use App\Models\PrEpisode;
use App\Models\PrCalendarEvent;
use Illuminate\Support\Facades\Log;

class PrDashboardController extends Controller
{
    /**
     * Get schedules for the main Program Regular dashboard
     * 
     * Includes:
     * 1. Shooting schedules (approved by producer)
     * 2. Broadcast schedules (episodes with air_date)
     */
    public function getSchedules(Request $request)
    {
        try {
            // 1. Get Shooting Schedules
            // We consider a schedule "approved" if it has been created and is not essentially just a draft/cancelled state.
            // Production schedules are created by/for producers.
            $shootingSchedules = PrProductionSchedule::with(['program', 'episode.creativeWork'])
                ->whereNotIn('status', ['cancelled']) // Assuming we want everything that's actively planned or done
                ->orderBy('scheduled_date', 'asc')
                ->get();

            // 2. Get Broadcast Schedules
            // We get episodes that have an air_date set
            $broadcastSchedules = PrEpisode::with(['program'])
                ->whereNotNull('air_date')
                ->orderBy('air_date', 'asc')
                ->get();

            // 3. Get Custom Events for the logged-in user
            $userEvents = PrCalendarEvent::where('user_id', auth()->id())
                ->orderBy('event_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'shooting_schedules' => $shootingSchedules,
                    'broadcast_schedules' => $broadcastSchedules,
                    'custom_events' => $userEvents
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching PR dashboard schedules: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveCalendarEvent(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'event_date' => 'required|date',
                'color' => 'nullable|string|max:20',
                'reminder_time' => 'nullable|date_format:H:i',
            ]);

            $event = PrCalendarEvent::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'description' => $request->description,
                'event_date' => $request->event_date,
                'color' => $request->color ?? '#3b82f6',
                'reminder_time' => $request->reminder_time,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event saved successfully',
                'data' => $event
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving calendar event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteCalendarEvent($id)
    {
        try {
            $event = PrCalendarEvent::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting calendar event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
