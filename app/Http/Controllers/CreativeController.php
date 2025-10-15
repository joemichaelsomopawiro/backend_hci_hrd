<?php

namespace App\Http\Controllers;

use App\Models\MusicSubmission;
use App\Services\CreativeWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CreativeController extends Controller
{
    protected $workflowService;

    public function __construct(CreativeWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get creative work for a submission
     * GET /api/music/creative/submissions/{id}/creative-work
     */
    public function getCreativeWork($id)
    {
        try {
            $submission = MusicSubmission::with([
                'creativeWork',
                'budget',
                'schedules',
                'song',
                'musicArranger'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'submission' => $submission,
                    'creative_work' => $submission->creativeWork,
                    'budget' => $submission->budget,
                    'schedules' => $submission->schedules,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get creative work: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit creative work (script, storyboard, budget, schedules)
     * POST /api/music/creative/submissions/{id}/submit-creative-work
     */
    public function submitCreativeWork(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'script_content' => 'nullable|string',
            'storyboard_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB
            'creative_notes' => 'nullable|string|max:1000',
            
            // Budget
            'talent_budget' => 'nullable|numeric|min:0',
            'production_budget' => 'nullable|numeric|min:0',
            'other_budget' => 'nullable|numeric|min:0',
            'talent_budget_notes' => 'nullable|string|max:500',
            'production_budget_notes' => 'nullable|string|max:500',
            'other_budget_notes' => 'nullable|string|max:500',
            'budget_notes' => 'nullable|string|max:1000',
            
            // Recording Schedule
            'recording_datetime' => 'nullable|date|after_or_equal:today',
            'recording_location' => 'nullable|string|max:255',
            'recording_location_address' => 'nullable|string|max:500',
            'recording_notes' => 'nullable|string|max:500',
            
            // Shooting Schedule
            'shooting_datetime' => 'nullable|date|after_or_equal:today',
            'shooting_location' => 'nullable|string|max:255',
            'shooting_location_address' => 'nullable|string|max:500',
            'shooting_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();
        $files = [];

        if ($request->hasFile('storyboard_file')) {
            $files['storyboard_file'] = $request->file('storyboard_file');
        }

        $result = $this->workflowService->submitCreativeWork($id, $data, $files);

        if ($result['success']) {
            return response()->json($result, 201);
        } else {
            return response()->json($result, 500);
        }
    }

    /**
     * Update creative work (for revision)
     * PATCH /api/music/creative/submissions/{id}/creative-work
     */
    public function updateCreativeWork(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'script_content' => 'nullable|string',
            'storyboard_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'creative_notes' => 'nullable|string|max:1000',
            'talent_budget' => 'nullable|numeric|min:0',
            'production_budget' => 'nullable|numeric|min:0',
            'other_budget' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $submission = MusicSubmission::findOrFail($id);
            $creativeWork = $submission->creativeWork;
            $budget = $submission->budget;

            if (!$creativeWork || !$budget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creative work not found. Please submit creative work first.',
                ], 404);
            }

            // Update creative work
            if ($request->has('script_content')) {
                $creativeWork->update(['script_content' => $request->script_content]);
            }

            if ($request->has('creative_notes')) {
                $creativeWork->update(['creative_notes' => $request->creative_notes]);
            }

            if ($request->hasFile('storyboard_file')) {
                $file = $request->file('storyboard_file');
                $path = $file->store('music/storyboards', 'public');
                
                $creativeWork->update([
                    'storyboard_file_path' => $path,
                    'storyboard_file_name' => $file->getClientOriginalName(),
                    'storyboard_file_size' => $file->getSize(),
                ]);
            }

            // Reset status to submitted for re-review
            $creativeWork->update(['status' => 'submitted']);

            // Update budget if provided
            $budgetUpdated = false;
            if ($request->has('talent_budget')) {
                $budget->talent_budget = $request->talent_budget;
                $budgetUpdated = true;
            }
            if ($request->has('production_budget')) {
                $budget->production_budget = $request->production_budget;
                $budgetUpdated = true;
            }
            if ($request->has('other_budget')) {
                $budget->other_budget = $request->other_budget;
                $budgetUpdated = true;
            }

            if ($budgetUpdated) {
                $budget->calculateTotal();
                $budget->status = 'submitted';
                $budget->save();
            }

            // Update submission state back to creative_review
            $submission->update(['current_state' => 'creative_review']);

            return response()->json([
                'success' => true,
                'message' => 'Creative work updated successfully',
                'data' => [
                    'creative_work' => $creativeWork->fresh(),
                    'budget' => $budget->fresh(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update creative work: ' . $e->getMessage(),
            ], 500);
        }
    }
}






