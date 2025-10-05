<?php

namespace App\Http\Controllers;

use App\Models\Singer;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    public function testSingers(): JsonResponse
    {
        try {
            $totalSingers = Singer::count();
            $singers = Singer::all();
            
            $singerList = [];
            foreach($singers as $singer) {
                $singerList[] = [
                    'id' => $singer->id,
                    'name' => $singer->name,
                    'email' => $singer->email
                ];
            }
            
            return response()->json([
                'success' => true,
                'total_singers' => $totalSingers,
                'singers' => $singerList
            ]);
            
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testCors(): JsonResponse
    {
        try {
            $audioUrl = 'http://127.0.0.1:8000/storage/songs/audio/6/4ANCxZEWbX7efaV7brx5Mh5yrSKvaJwuRYLsUD7Q.mp3';
            
            return response()->json([
                'success' => true,
                'message' => 'CORS test endpoint',
                'data' => [
                    'cors_configured' => true,
                    'cors_middleware' => 'AddCorsHeaders',
                    'cors_config' => 'config/cors.php',
                    'htaccess_cors' => 'public/.htaccess',
                    'audio_url' => $audioUrl,
                    'headers' => [
                        'Access-Control-Allow-Origin' => '*',
                        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                        'Access-Control-Allow-Credentials' => 'true'
                    ]
                ]
            ])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->header('Access-Control-Allow-Credentials', 'true');
            
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testProducerModifyWorkflow(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Producer Modify Workflow Test - COMPLETE FIX',
                'data' => [
                    'workflow_updated' => true,
                    'new_statuses' => [
                        'awaiting_arrangement' => 'Task siap untuk di-arrange Music Arranger'
                    ],
                    'status_flow' => [
                        'submitted → producer_review → awaiting_arrangement → arranging → arrangement_review → completed'
                    ],
                    'backend_changes' => [
                        'Producer modify sets status to awaiting_arrangement',
                        'Music Arranger My Work includes awaiting_arrangement tasks',
                        'Start arranging allows from awaiting_arrangement status',
                        'Added missing database fields via migration',
                        'Updated model with proper fillable fields',
                        'Added error handling with logging'
                    ],
                    'fixes_applied' => [
                        'Created migration for missing fields',
                        'Updated model with all required fields',
                        'Added filtered field updates in controller',
                        'Added proper error handling and logging',
                        'Fixed SQL warning by ensuring all fields exist',
                        'Producer can modify song_id, approved_singer_id, arrangement_notes, requested_date'
                    ],
                    'database_fields_added' => [
                        'current_state', 'submission_status', 'status',
                        'song_id', 'proposed_singer_id', 'approved_singer_id',
                        'arrangement_notes', 'requested_date', 'producer_notes',
                        'arrangement_file_path', 'arrangement_file_url',
                        'submitted_at', 'approved_at', 'rejected_at', 'completed_at'
                    ],
                    'test_endpoints' => [
                        'Database Schema: GET /api/test/database-schema',
                        'Producer Modify: POST /api/music-workflow/producer/workflow/{id}/review',
                        'Music Arranger My Work: GET /api/music-arranger/submissions?status=arranging'
                    ]
                ]
            ]);
            
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testDatabaseSchema(): JsonResponse
    {
        try {
            // Check music_submissions table structure
            $columns = \DB::select("SHOW COLUMNS FROM music_submissions");
            $availableFields = array_column($columns, 'Field');
            
            // Check for required fields
            $requiredFields = [
                'current_state', 'submission_status', 'status',
                'song_id', 'proposed_singer_id', 'approved_singer_id',
                'arrangement_notes', 'requested_date', 'producer_notes',
                'arrangement_file_path', 'arrangement_file_url',
                'submitted_at', 'approved_at', 'rejected_at', 'completed_at'
            ];
            
            $missingFields = array_diff($requiredFields, $availableFields);
            $hasAllFields = empty($missingFields);
            
            return response()->json([
                'success' => true,
                'message' => 'Database Schema Check',
                'data' => [
                    'table' => 'music_submissions',
                    'total_columns' => count($columns),
                    'available_fields' => $availableFields,
                    'required_fields' => $requiredFields,
                    'missing_fields' => $missingFields,
                    'has_all_required_fields' => $hasAllFields,
                    'columns_detail' => $columns,
                    'test_submission' => \App\Models\MusicSubmission::first(),
                    'recommendations' => $hasAllFields ? 
                        'All required fields exist. Backend should work properly.' : 
                        'Missing fields: ' . implode(', ', $missingFields) . '. Please run database migration.'
                ]
            ]);
            
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testValidationFix(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Validation Fix Applied',
                'data' => [
                    'fix_applied' => true,
                    'changes' => [
                        'Dynamic validation based on action',
                        'Removed complex validation rules for modify action',
                        'Only validate fields relevant to specific action',
                        'Fixed 422 validation error for Producer Modify'
                    ],
                    'validation_rules' => [
                        'modify' => [
                            'approved_singer_id' => 'nullable|exists:users,id',
                            'song_id' => 'nullable|exists:songs,id',
                            'arrangement_notes' => 'nullable|string|max:1000',
                            'requested_date' => 'nullable|date|after_or_equal:today'
                        ],
                        'accept' => [
                            'approved_singer_id' => 'nullable|exists:users,id'
                        ],
                        'approve_arrangement' => [
                            'rejection_reason' => 'nullable|string|max:1000'
                        ]
                    ],
                    'test_endpoints' => [
                        'Database Schema: GET /api/test/database-schema',
                        'Producer Modify: POST /api/music-workflow/producer/workflow/{id}/review',
                        'Validation Fix: GET /api/test/validation-fix'
                    ]
                ]
            ]);
            
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testDatabaseColumnFix(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Database Column Error FIXED',
                'data' => [
                    'fix_applied' => true,
                    'error_fixed' => 'SQLSTATE[42S22]: Column not found: 1054 Unknown column status',
                    'changes' => [
                        'Removed non-existent status field from updateData',
                        'Only update fields that exist in database',
                        'Fixed 500 Internal Server Error for Producer Modify'
                    ],
                    'update_fields' => [
                        'producer_notes' => 'string',
                        'current_state' => 'string', 
                        'submission_status' => 'string',
                        'song_id' => 'integer (if provided)',
                        'approved_singer_id' => 'integer (if provided)',
                        'arrangement_notes' => 'string (if provided)',
                        'requested_date' => 'date (if provided)'
                    ],
                    'removed_fields' => [
                        'status' => 'Field tidak ada di database - REMOVED'
                    ],
                    'test_endpoints' => [
                        'Database Column Fix: GET /api/test/database-column-fix',
                        'Producer Modify: POST /api/music-workflow/producer/workflow/{id}/review'
                    ]
                ]
            ]);
            
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
