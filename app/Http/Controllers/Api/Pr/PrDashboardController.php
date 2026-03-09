<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PrProductionSchedule;
use App\Models\PrEpisode;
use App\Models\PrCalendarEvent;
use App\Constants\Role;
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
            // A. From Production Schedules (Official/Final)
            $shootingSchedules = PrProductionSchedule::with(['program', 'episode.creativeWork'])
                ->whereNotIn('status', ['cancelled'])
                ->orderBy('scheduled_date', 'asc')
                ->get();

            // B. From Creative Works (Proposed/Planned by creative)
            $creativeShootingSchedules = \App\Models\PrCreativeWork::with(['episode.program', 'episode.productionSchedules'])
                ->whereNotNull('shooting_schedule')
                ->get();

            // 2. Get Broadcast Schedules
            $broadcastSchedules = PrEpisode::with(['program'])
                ->whereNotNull('air_date')
                ->orderBy('air_date', 'asc')
                ->get();

            // 3. Get Custom Events (Personal Reminders OR Public Schedules)
            $userEvents = PrCalendarEvent::where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhere('is_public', true);
            })
                ->orderBy('event_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'shooting_schedules' => $shootingSchedules,
                    'creative_shooting_schedules' => $creativeShootingSchedules,
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
                'is_public' => 'nullable|boolean',
            ]);

            $isPublic = $request->boolean('is_public', false);

            // Only Program Manager, Distribution Manager, and Producer can make it public
            $allowedRoles = [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER];
            if ($isPublic && !Role::inArray(auth()->user()->role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Managers and Producers can create public schedules.'
                ], 403);
            }

            $event = PrCalendarEvent::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'description' => $request->description,
                'event_date' => $request->event_date,
                'color' => $request->color ?? '#3b82f6',
                'reminder_time' => $request->reminder_time,
                'is_public' => $isPublic,
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
            $event = PrCalendarEvent::findOrFail($id);

            // Access Control: Owner OR (Event is public AND User is Manager/Producer)
            $isOwner = $event->user_id === auth()->id();
            $allowedRoles = [Role::PROGRAM_MANAGER, Role::DISTRIBUTION_MANAGER, Role::PRODUCER];
            $isManager = Role::inArray(auth()->user()->role, $allowedRoles);

            if (!$isOwner && !($event->is_public && $isManager)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized deletion'
                ], 403);
            }

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
