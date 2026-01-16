<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Episode;
use App\Models\ProductionTeam;
use App\Models\Notification;
use App\Services\ProgramWorkflowService;
use App\Services\AnalyticsService;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProgramController extends Controller
{
    protected $programWorkflowService;

    public function __construct(ProgramWorkflowService $programWorkflowService)
    {
        $this->programWorkflowService = $programWorkflowService;
    }

    /**
     * Get all programs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // Log untuk debugging
            \Log::info('ProgramController index - Start', [
                'user_id' => $user?->id,
                'user_role' => $user?->role,
                'request_params' => $request->all()
            ]);
            
            // Build cache key based on request parameters
            $cacheKey = 'programs_index_' . md5(json_encode([
                'user_role' => $user?->role,
                'user_id' => $user?->id, // Tambahkan user_id untuk Producer agar cache berbeda per user
                'status' => $request->get('status'),
                'category' => $request->get('category'),
                'manager_id' => $request->get('manager_id'),
                'search' => $request->get('search'),
                'page' => $request->get('page', 1)
            ]));
            
            // Use cache with 5 minutes TTL
            $programs = \App\Helpers\QueryOptimizer::remember($cacheKey, 300, function () use ($request, $user) {
                // Optimize eager loading - jangan load semua episodes, hanya count
                $query = Program::with([
                    'managerProgram',
                    'productionTeam.members.user', // Fix N+1 problem
                    'episodes' => function ($q) {
                        $q->select('id', 'program_id', 'episode_number', 'title', 'status')
                          ->orderBy('episode_number', 'desc')
                          ->limit(5); // Hanya load 5 episodes terbaru untuk preview
                    }
                ]);
                
                // Filter: HR tidak boleh melihat program musik
                // Program musik adalah program yang memiliki production team dengan member role 'musik_arr'
                if ($user && $user->role === 'HR') {
                    $query->whereDoesntHave('productionTeam.members', function ($q) {
                        $q->where('role', 'musik_arr')
                          ->where('is_active', true);
                    });
                }
                
                // Filter: Producer hanya bisa melihat program dari ProductionTeam mereka
                if ($user && $user->role === 'Producer') {
                    \Log::info('ProgramController index - Applying Producer filter', [
                        'producer_id' => $user->id,
                        'producer_name' => $user->name
                    ]);
                    
                    $query->whereNotNull('production_team_id')
                        ->whereHas('productionTeam', function ($q) use ($user) {
                            $q->where('producer_id', $user->id)
                              ->where('is_active', true);
                        });
                }
                
                // Filter by status
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }
                
                // Filter by category
                if ($request->has('category')) {
                    $query->where('category', $request->category);
                }
                
                // Filter by manager
                if ($request->has('manager_id')) {
                    $query->where('manager_program_id', $request->manager_id);
                }
                
                // Search
                if ($request->has('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                    });
                }
                
                $result = $query->orderBy('created_at', 'desc')->paginate(15);
                
                // Log hasil query untuk Producer
                if ($user && $user->role === 'Producer') {
                    \Log::info('ProgramController index - Producer query result', [
                        'producer_id' => $user->id,
                        'total_programs' => $result->total(),
                        'current_page' => $result->currentPage(),
                        'program_ids' => $result->pluck('id')->toArray()
                    ]);
                }
                
                return $result;
            });
            
            // Add episode count to each program (tanpa load semua episodes)
            $programs->getCollection()->transform(function ($program) {
                $program->episode_count = $program->episodes()->count();
                return $program;
            });
            
            return response()->json([
                'success' => true,
                'data' => $programs,
                'message' => 'Programs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error retrieving programs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving programs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program by ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $program = Program::with([
                'managerProgram',
                'productionTeam.members.user'
            ])->findOrFail($id);
            
            // Load episodes secara terpisah untuk memastikan tidak ada masalah dengan relasi
            $episodes = Episode::where('program_id', $id)
                ->whereNull('deleted_at')
                ->with(['deadlines', 'workflowStates'])
                ->orderBy('episode_number')
                ->get();
            
            // Attach episodes ke program object
            $program->setRelation('episodes', $episodes);
            
            $analytics = null; // Removed analytics service dependency
            
            return response()->json([
                'success' => true,
                'data' => [
                    'program' => $program,
                    'analytics' => $analytics
                ],
                'message' => 'Program retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving program: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new program
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|in:musik,live_tv,regular,special,other',
            'manager_program_id' => 'required|exists:users,id',
            'production_team_id' => 'nullable|exists:production_teams,id',
            'start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    // Validasi: start_date untuk planning Episode 1
                    // Boleh di masa lalu (program yang sudah berjalan) atau masa depan (planning)
                    // Tapi tahun harus masuk akal (antara tahun sekarang - 1 sampai tahun sekarang + 5)
                    $startDate = Carbon::parse($value);
                    $currentYear = Carbon::now()->year;
                    $year = $startDate->year;
                    
                    if ($year < ($currentYear - 1)) {
                        $fail('The start_date year cannot be more than 1 year in the past. Start date is used for planning Episode 1.');
                    }
                    
                    if ($year > ($currentYear + 5)) {
                        $fail('The start_date year cannot be more than 5 years in the future. Please select a reasonable planning date.');
                    }
                },
            ],
            'air_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'broadcast_channel' => 'nullable|string|max:255',
            'target_views_per_episode' => 'nullable|integer|min:0',
            'proposal_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240' // Max 10MB
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $user = auth()->user();
            
            // Validate: User harus Manager Program atau manager_program_id harus sesuai dengan user login
            if ($user->role !== 'Manager Program' && $user->role !== 'Program Manager' && $user->id != $request->manager_program_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Manager Program can create programs or manager_program_id must match logged in user'
                ], 403);
            }
            
            // Handle proposal file upload
            $proposalFilePath = null;
            $proposalFileName = null;
            $proposalFileSize = null;
            $proposalFileMimeType = null;
            
            if ($request->hasFile('proposal_file')) {
                $file = $request->file('proposal_file');
                $proposalFilePath = $file->store('programs/proposals', 'public');
                $proposalFileName = $file->getClientOriginalName();
                $proposalFileSize = $file->getSize();
                $proposalFileMimeType = $file->getMimeType();
            }
            
            // Create program dengan data yang sudah divalidasi
            $programData = $request->only([
                'name',
                'description',
                'category',
                'manager_program_id',
                'production_team_id',
                'start_date',
                'air_time',
                'duration_minutes',
                'broadcast_channel',
                'target_views_per_episode'
            ]);
            
            // Filter out null values dan kolom yang tidak ada di database
            $programData = array_filter($programData, function($value) {
                return $value !== null;
            });
            
            // Set default category jika tidak ada dan kolom category ada di database
            if (!isset($programData['category']) && Schema::hasColumn('programs', 'category')) {
                $programData['category'] = 'regular';
            } elseif (!Schema::hasColumn('programs', 'category')) {
                // Hapus category dari data jika kolom tidak ada
                unset($programData['category']);
            }
            
            // Add proposal file data hanya jika kolom ada di database
            if ($proposalFilePath) {
                if (Schema::hasColumn('programs', 'proposal_file_path')) {
                    $programData['proposal_file_path'] = $proposalFilePath;
                }
                if (Schema::hasColumn('programs', 'proposal_file_name')) {
                    $programData['proposal_file_name'] = $proposalFileName;
                }
                if (Schema::hasColumn('programs', 'proposal_file_size')) {
                    $programData['proposal_file_size'] = $proposalFileSize;
                }
                if (Schema::hasColumn('programs', 'proposal_file_mime_type')) {
                    $programData['proposal_file_mime_type'] = $proposalFileMimeType;
                }
            }
            
            // Set default status jika tidak ada
            if (!isset($programData['status'])) {
                $programData['status'] = 'draft';
            }
            
            // Hapus kolom yang tidak ada di database sebelum insert
            $existingColumns = Schema::getColumnListing('programs');
            $programData = array_intersect_key($programData, array_flip($existingColumns));
            
            // Pastikan kolom required ada
            if (!isset($programData['status'])) {
                $programData['status'] = 'draft';
            }
            
            // Log untuk debugging
            Log::info('Creating program with data:', [
                'columns' => array_keys($programData),
                'existing_columns' => $existingColumns
            ]);
            
            // Use ProgramWorkflowService to create program (auto-generate episodes, notifications, workflow states)
            $program = $this->programWorkflowService->createProgram($programData);
            
            // Clear cache setelah create
            QueryOptimizer::clearIndexCache('programs');
            QueryOptimizer::clearIndexCache('episodes'); // Episodes juga perlu di-clear karena terkait program
            
            return response()->json([
                'success' => true,
                'data' => $program->load(['episodes', 'managerProgram', 'productionTeam']),
                'message' => 'Program created successfully with 52 episodes generated'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update program
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $program = Program::findOrFail($id);
        
        // Validate: Only Manager Program of this program can update
        if ($user->role !== 'Manager Program' && $user->role !== 'Program Manager' && $user->id != $program->manager_program_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Manager Program of this program can update'
            ], 403);
        }
        
        // Cannot update if program is already approved or in production
        if (in_array($program->status, ['approved', 'in_production', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update program that is already approved or in production'
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'production_team_id' => 'nullable|exists:production_teams,id',
            'start_date' => [
                'sometimes',
                'date',
                function ($attribute, $value, $fail) {
                    // Validasi: start_date untuk planning Episode 1
                    // Boleh di masa lalu (program yang sudah berjalan) atau masa depan (planning)
                    // Tapi tahun harus masuk akal (antara tahun sekarang - 1 sampai tahun sekarang + 5)
                    $startDate = Carbon::parse($value);
                    $currentYear = Carbon::now()->year;
                    $year = $startDate->year;
                    
                    if ($year < ($currentYear - 1)) {
                        $fail('The start_date year cannot be more than 1 year in the past. Start date is used for planning Episode 1.');
                    }
                    
                    if ($year > ($currentYear + 5)) {
                        $fail('The start_date year cannot be more than 5 years in the future. Please select a reasonable planning date.');
                    }
                },
            ],
            'air_time' => 'sometimes|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'broadcast_channel' => 'nullable|string|max:255',
            'target_views_per_episode' => 'nullable|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $oldTeamId = $program->production_team_id;
            $program->update($request->all());
            
            // Jika production_team_id di-update, auto-assign ke semua episode yang belum punya team sendiri
            if ($request->has('production_team_id') && $request->production_team_id != $oldTeamId && $request->production_team_id) {
                $newTeamId = $request->production_team_id;
                $team = ProductionTeam::findOrFail($newTeamId);
                
                // Get semua episode yang belum punya team sendiri (production_team_id = null)
                $episodesWithoutTeam = $program->episodes()
                    ->whereNull('production_team_id')
                    ->get();
                
                // Auto-assign team ke semua episode yang belum punya team
                foreach ($episodesWithoutTeam as $episode) {
                    $episode->update([
                        'production_team_id' => $newTeamId,
                        'team_assigned_by' => $user->id,
                        'team_assigned_at' => now(),
                        'team_assignment_notes' => "Auto-assigned dari Program team"
                    ]);
                    
                    // Notify team members untuk setiap episode
                    $teamMembers = $team->members()->where('is_active', true)->get();
                    foreach ($teamMembers as $member) {
                        \App\Models\Notification::create([
                            'user_id' => $member->user_id,
                            'type' => 'team_assigned',
                            'title' => 'Ditugaskan ke Episode',
                            'message' => "Anda ditugaskan untuk Episode {$episode->episode_number} - {$episode->title} (Auto-assigned dari Program)",
                            'data' => [
                                'episode_id' => $episode->id,
                                'program_id' => $program->id,
                                'notes' => 'Auto-assigned dari Program team'
                            ]
                        ]);
                    }
                }
            }
            
            // Clear cache setelah update
            QueryOptimizer::clearIndexCache('programs');
            QueryOptimizer::clearIndexCache('episodes'); // Episodes juga perlu di-clear karena terkait program
            
            $message = 'Program updated successfully';
            if ($request->has('production_team_id') && $request->production_team_id != $oldTeamId && $request->production_team_id) {
                $episodesCount = $episodesWithoutTeam->count();
                if ($episodesCount > 0) {
                    $message .= ". Team telah di-assign ke {$episodesCount} episode yang belum punya team.";
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $program->fresh(['episodes', 'productionTeam']),
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit program for approval
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $program = Program::findOrFail($id);
        
        // Validate: Only Manager Program of this program can submit
        if ($user->role !== 'Manager Program' && $user->role !== 'Program Manager' && $user->id != $program->manager_program_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Manager Program of this program can submit for approval'
            ], 403);
        }
        
        if ($program->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Program can only be submitted from draft status'
            ], 400);
        }
        
        try {
            $program = $this->programWorkflowService->submitProgram($program, $user->id);
            
            // Clear cache setelah submit program (programs dan episodes cache perlu di-clear)
            QueryOptimizer::clearAllIndexCaches();
            
            return response()->json([
                'success' => true,
                'data' => $program->load(['episodes', 'managerProgram', 'productionTeam']),
                'message' => 'Program submitted for approval successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve program
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $program = Program::findOrFail($id);
        
        // Validate: Only Distribution Manager can approve
        if ($user->role !== 'Distribution Manager') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Distribution Manager can approve programs'
            ], 403);
        }
        
        // Validasi: Program harus dalam status pending_approval
        if ($program->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Program sudah di-approve sebelumnya. Tidak bisa approve ulang.',
                'current_status' => $program->status,
                'approved_by' => $program->approved_by,
                'approved_at' => $program->approved_at
            ], 400);
        }
        
        if ($program->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Program can only be approved from pending_approval status',
                'current_status' => $program->status
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'approval_notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = $this->programWorkflowService->approveProgram(
                $program, 
                $user->id, 
                $request->approval_notes
            );
            
            // Clear cache setelah approve program (programs dan episodes cache perlu di-clear)
            QueryOptimizer::clearAllIndexCaches();
            
            return response()->json([
                'success' => true,
                'data' => $program->load(['episodes', 'managerProgram', 'productionTeam']),
                'message' => 'Program approved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject program
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $program = Program::findOrFail($id);
        
        // Validate: Only Distribution Manager can reject
        if ($user->role !== 'Distribution Manager') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Distribution Manager can reject programs'
            ], 403);
        }
        
        // Validasi: Program harus dalam status pending_approval
        if ($program->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Program sudah di-approve. Tidak bisa di-reject.',
                'current_status' => $program->status,
                'approved_by' => $program->approved_by,
                'approved_at' => $program->approved_at
            ], 400);
        }
        
        if ($program->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Program can only be rejected from pending_approval status',
                'current_status' => $program->status
            ], 400);
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_notes' => 'required|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $program = $this->programWorkflowService->rejectProgram(
                $program, 
                $user->id, 
                $request->rejection_notes
            );
            
            // Clear cache setelah reject program (programs dan episodes cache perlu di-clear)
            QueryOptimizer::clearAllIndexCaches();
            
            return response()->json([
                'success' => true,
                'data' => $program->load(['episodes', 'managerProgram', 'productionTeam']),
                'message' => 'Program rejected successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete program
     */
    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        $program = Program::findOrFail($id);
        
        // Validate: Only Manager Program of this program can delete
        if ($user->role !== 'Manager Program' && $user->role !== 'Program Manager' && $user->id != $program->manager_program_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only Manager Program of this program can delete'
            ], 403);
        }
        
        if ($program->status === 'in_production') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete program that is in production'
            ], 400);
        }
        
        try {
            $program->delete();
            
            // Clear cache setelah delete
            QueryOptimizer::clearIndexCache('programs');
            QueryOptimizer::clearIndexCache('episodes'); // Episodes juga perlu di-clear karena terkait program
            
            return response()->json([
                'success' => true,
                'message' => 'Program deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program analytics
     */
    public function analytics(int $id): JsonResponse
    {
        try {
            $analytics = null; // Removed analytics service dependency
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Program analytics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get program analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve budget request from Producer
     */
    public function approveBudget(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'budget_amount' => 'required|numeric|min:0',
            'budget_notes' => 'nullable|string|max:1000',
            'status' => 'required|in:approved,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $program = Program::with('productionTeam')->findOrFail($id);
        
        $program->update([
            'budget_approved' => $request->status === 'approved',
            'budget_amount' => $request->budget_amount,
            'budget_notes' => $request->budget_notes,
            'budget_approved_by' => $user->id,
            'budget_approved_at' => now()
        ]);

        // Notifikasi ke Producer (if production team has producer)
        if ($program->productionTeam && $program->productionTeam->producer_id) {
            \App\Models\Notification::create([
                'user_id' => $program->productionTeam->producer_id,
                'type' => 'budget_approved',
                'title' => 'Budget Disetujui',
                'message' => "Budget program '{$program->name}' telah disetujui: Rp " . number_format($request->budget_amount),
                'data' => [
                    'program_id' => $program->id,
                    'budget_amount' => $request->budget_amount,
                    'status' => $request->status
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Budget berhasil diproses',
            'data' => $program
        ]);
    }

    /**
     * Get budget requests for Manager Program
     */
    public function getBudgetRequests(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!in_array($user->role, ['Manager Program', 'Program Manager', 'managerprogram'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Cek apakah tabel budget_requests ada dan gunakan model BudgetRequest
            if (class_exists(\App\Models\BudgetRequest::class) && 
                Schema::hasTable('budget_requests')) {
                try {
                    // Gunakan Eloquent query
                    $query = \App\Models\BudgetRequest::with([
                        'program.managerProgram',
                        'program.productionTeam',
                        'requestedBy',
                        'approvedBy'
                    ]);
                    
                    // Hanya filter deleted_at jika kolom ada
                    if (Schema::hasColumn('budget_requests', 'deleted_at')) {
                        $query->whereNull('deleted_at');
                    }
                    
                    $budgetRequests = $query->orderBy('created_at', 'desc')->get();
                } catch (\Exception $e) {
                    // Jika ada error, fallback ke Program
                    Log::warning('Error loading BudgetRequest, falling back to Program: ' . $e->getMessage());
                    $budgetRequests = Program::where('budget_approved', false)
                        ->whereNotNull('budget_amount')
                        ->with(['managerProgram', 'productionTeam'])
                        ->orderBy('created_at', 'desc')
                        ->get();
                }
            } else {
                // Fallback: gunakan Program jika BudgetRequest tidak ada
                $budgetRequests = Program::where('budget_approved', false)
                    ->whereNotNull('budget_amount')
                    ->with(['managerProgram', 'productionTeam'])
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $budgetRequests
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving budget requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get program episodes
     */
    public function episodes(int $id, Request $request): JsonResponse
    {
        $program = Program::findOrFail($id);
        
        // Query episodes dengan filter tahun (jika ada)
        $query = Episode::where('program_id', $id)
            ->whereNull('deleted_at')
            ->with(['deadlines', 'workflowStates']);
        
        // Filter by year (jika ada)
        if ($request->has('year')) {
            $year = $request->get('year');
            $yearStart = \Carbon\Carbon::createFromDate($year, 1, 1, 'UTC')->setTime(0, 0, 0);
            $yearEnd = \Carbon\Carbon::createFromDate($year, 12, 31, 'UTC')->setTime(23, 59, 59);
            $query->whereBetween('air_date', [$yearStart, $yearEnd]);
        }
        
        // Filter by status (jika ada)
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        
        $episodes = $query->orderBy('air_date', 'asc')
            ->orderBy('episode_number', 'asc')
            ->get();
        
        // Group by year untuk response yang lebih terstruktur
        $groupedByYear = $episodes->groupBy(function ($episode) {
            return \Carbon\Carbon::parse($episode->air_date)->year;
        })->map(function ($yearEpisodes, $year) {
            return [
                'year' => (int)$year,
                'episodes' => $yearEpisodes,
                'count' => $yearEpisodes->count(),
                'first_episode_number' => $yearEpisodes->min('episode_number'),
                'last_episode_number' => $yearEpisodes->max('episode_number')
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'program_id' => $id,
                'program_name' => $program->name,
                'episodes' => $episodes,
                'grouped_by_year' => $groupedByYear,
                'total_episodes' => $episodes->count(),
                'years_available' => $groupedByYear->keys()->sort()->values()
            ],
            'message' => 'Program episodes retrieved successfully'
        ]);
    }
}

