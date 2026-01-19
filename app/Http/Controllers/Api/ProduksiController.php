<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProduksiWork;
use App\Models\ProductionEquipment;
use App\Models\EquipmentInventory;
use App\Models\ShootingRunSheet;
use App\Models\MediaFile;
use App\Models\Notification;
use App\Models\QualityControlWork;
use App\Helpers\ControllerSecurityHelper;
use App\Helpers\QueryOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class ProduksiController extends Controller
{
    /**
     * Get produksi works for current user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            // Only accept 'Production' role (English)
            if ($user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Build cache key based on request parameters
            $cacheKey = 'produksi_index_' . md5(json_encode([
                'user_id' => $user->id,
                'status' => $request->get('status'),
                'page' => $request->get('page', 1)
            ]));

            // Use cache with 5 minutes TTL
            $works = QueryOptimizer::rememberForUser($cacheKey, $user->id, 300, function () use ($request) {
                $query = ProduksiWork::with(['episode', 'creativeWork', 'createdBy']);

                // Filter by status
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }

                return $query->orderBy('created_at', 'desc')->paginate(15);
            });

            return response()->json([
                'success' => true,
                'data' => $works,
                'message' => 'Produksi works retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving produksi works: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terima Pekerjaan - Produksi terima pekerjaan setelah Producer approve Creative Work
     * POST /api/live-tv/roles/produksi/works/{id}/accept-work
     */
    public function acceptWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be accepted when status is pending'
                ], 400);
            }

            $oldData = $work->toArray();
            $work->acceptWork($user->id);

            // Audit logging
            ControllerSecurityHelper::logCrud('produksi_work_accepted', $work, [
                'old_status' => $oldData['status'],
                'new_status' => 'in_progress',
                'assigned_to' => $user->id
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork', 'createdBy']),
                'message' => 'Work accepted successfully. You can now input equipment list and needs.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input List Alat dan Ajukan ke Art & Set Properti
     * POST /api/live-tv/roles/produksi/works/{id}/request-equipment
     */
    public function requestEquipment(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.equipment_name' => 'required|string|max:255',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'equipment_list.*.return_date' => 'required|date|after:today',
                'equipment_list.*.notes' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to request equipment'
                ], 400);
            }

            $equipmentRequests = [];
            $unavailableEquipment = [];

            // Check each equipment availability
            foreach ($request->equipment_list as $equipment) {
                $equipmentName = $equipment['equipment_name'];
                $quantity = $equipment['quantity'];

                // Check if equipment is available (not in_use or assigned)
                // Use 'name' column (from old migration) - if table has 'equipment_name', it will also work
                if (Schema::hasColumn('equipment_inventory', 'equipment_name')) {
                    // New migration structure
                    $availableCount = EquipmentInventory::where('equipment_name', $equipmentName)
                        ->whereIn('status', ['available'])
                        ->count();
                } else {
                    // Old migration structure - use 'name' column
                    $availableCount = EquipmentInventory::where('name', $equipmentName)
                        ->whereIn('status', ['available'])
                        ->count();
                }

                // Also check ProductionEquipment for in_use status
                $inUseCount = ProductionEquipment::where('equipment_list', 'like', '%' . $equipmentName . '%')
                    ->whereIn('status', ['approved', 'in_use'])
                    ->count();

                if ($availableCount < $quantity || $inUseCount > 0) {
                    $unavailableEquipment[] = [
                        'equipment_name' => $equipmentName,
                        'requested_quantity' => $quantity,
                        'available_count' => $availableCount,
                        'in_use_count' => $inUseCount,
                        'reason' => $inUseCount > 0 ? 'Equipment sedang dipakai' : 'Equipment tidak tersedia dalam jumlah yang diminta'
                    ];
                    continue;
                }

                // Create equipment request
                $equipmentRequest = ProductionEquipment::create([
                    'episode_id' => $work->episode_id,
                    'equipment_list' => [$equipmentName],
                    'request_notes' => ($equipment['notes'] ?? '') . ($request->request_notes ? "\n" . $request->request_notes : ''),
                    'status' => 'pending',
                    'requested_by' => $user->id,
                    'requested_at' => now()
                ]);

                $equipmentRequests[] = $equipmentRequest->id;
            }

            if (!empty($unavailableEquipment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some equipment is not available or currently in use',
                    'unavailable_equipment' => $unavailableEquipment,
                    'available_requests' => $equipmentRequests
                ], 400);
            }

            // Update work
            $work->update([
                'equipment_list' => $request->equipment_list,
                'equipment_requests' => array_merge($work->equipment_requests ?? [], $equipmentRequests)
            ]);

            // Notify Art & Set Properti
            $artSetUsers = \App\Models\User::where('role', 'Art & Set Properti')->get();
            $equipmentCount = count($request->equipment_list);
            foreach ($artSetUsers as $artSetUser) {
                Notification::create([
                    'user_id' => $artSetUser->id,
                    'type' => 'equipment_request_created',
                    'title' => 'Permintaan Alat Baru',
                    'message' => "Produksi meminta {$equipmentCount} item equipment untuk Episode {$work->episode->episode_number}.",
                    'data' => [
                        'equipment_request_ids' => $equipmentRequests,
                        'episode_id' => $work->episode_id,
                        'produksi_work_id' => $work->id
                    ]
                ]);
            }

            // Audit logging
            ControllerSecurityHelper::logCrud('produksi_equipment_requested', $work, [
                'equipment_count' => count($request->equipment_list),
                'equipment_request_ids' => $equipmentRequests
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'creativeWork']),
                    'equipment_requests' => ProductionEquipment::whereIn('id', $equipmentRequests)->get()
                ],
                'message' => 'Equipment requests created successfully. Art & Set Properti has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting equipment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajukan Kebutuhan
     * POST /api/live-tv/roles/produksi/works/{id}/request-needs
     */
    public function requestNeeds(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'needs_list' => 'required|array|min:1',
                'needs_list.*.item_name' => 'required|string|max:255',
                'needs_list.*.quantity' => 'required|integer|min:1',
                'needs_list.*.description' => 'nullable|string|max:1000',
                'request_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to request needs'
                ], 400);
            }

            // Update work
            $work->update([
                'needs_list' => $request->needs_list,
                'needs_requests' => array_merge($work->needs_requests ?? [], [
                    'requested_at' => now()->toDateTimeString(),
                    'requested_by' => $user->id,
                    'notes' => $request->request_notes
                ])
            ]);

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'produksi_needs_requested',
                    'title' => 'Permintaan Kebutuhan Produksi',
                    'message' => "Produksi telah mengajukan kebutuhan untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'produksi_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'needs_list' => $request->needs_list
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork']),
                'message' => 'Needs request submitted successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error requesting needs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Selesaikan Pekerjaan - Produksi selesaikan setelah input list alat dan kebutuhan
     * POST /api/live-tv/roles/produksi/works/{id}/complete-work
     */
    public function completeWork(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work can only be completed when status is in_progress'
                ], 400);
            }

            $oldData = $work->toArray();
            $work->completeWork($user->id, $request->notes);

            // Audit logging
            ControllerSecurityHelper::logCrud('produksi_work_completed', $work, [
                'old_status' => $oldData['status'],
                'new_status' => 'completed',
                'completed_by' => $user->id,
                'notes' => $request->notes
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            // Notify Producer
            $episode = $work->episode;
            $productionTeam = $episode->program->productionTeam;
            $producer = $productionTeam ? $productionTeam->producer : null;
            
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'produksi_work_completed',
                    'title' => 'Produksi Work Selesai',
                    'message' => "Produksi telah menyelesaikan pekerjaan untuk Episode {$episode->episode_number}.",
                    'data' => [
                        'produksi_work_id' => $work->id,
                        'episode_id' => $work->episode_id
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork', 'completedBy']),
                'message' => 'Work completed successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing work: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input Form Catatan Syuting (Run Sheet)
     * POST /api/live-tv/roles/produksi/works/{id}/create-run-sheet
     */
    public function createRunSheet(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'shooting_date' => 'required|date|after:today',
                'location' => 'required|string|max:255',
                'crew_list' => 'required|array|min:1',
                'crew_list.*.name' => 'required|string|max:255',
                'crew_list.*.role' => 'required|string|max:100',
                'crew_list.*.contact' => 'nullable|string|max:50',
                'equipment_list' => 'required|array|min:1',
                'equipment_list.*.name' => 'required|string|max:255',
                'equipment_list.*.quantity' => 'required|integer|min:1',
                'shooting_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to create run sheet'
                ], 400);
            }

            // Create run sheet
            $runSheet = ShootingRunSheet::create([
                'episode_id' => $work->episode_id,
                'produksi_work_id' => $work->id,
                'shooting_date' => $request->shooting_date,
                'location' => $request->location,
                'crew_list' => $request->crew_list,
                'equipment_list' => $request->equipment_list,
                'shooting_notes' => $request->shooting_notes,
                'status' => 'planned',
                'created_by' => $user->id
            ]);

            // Update work with run sheet ID
            $work->update([
                'run_sheet_id' => $runSheet->id
            ]);

            // Audit logging
            ControllerSecurityHelper::logCreate($runSheet, [
                'produksi_work_id' => $work->id,
                'episode_id' => $work->episode_id,
                'shooting_date' => $request->shooting_date
            ], $request);

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'creativeWork', 'runSheet']),
                    'run_sheet' => $runSheet
                ],
                'message' => 'Run sheet created successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating run sheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload Hasil Syuting ke Storage
     * POST /api/live-tv/roles/produksi/works/{id}/upload-shooting-results
     */
    public function uploadShootingResults(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'files' => 'required|array|min:1',
                'files.*' => 'required|file|mimes:mp4,avi,mov,mkv|max:1024000', // Max 1GB per file
                'completion_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::with(['episode', 'runSheet'])->findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            if ($work->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Work must be in progress to upload shooting results'
                ], 400);
            }

            $uploadedFiles = [];
            $filePaths = [];

            foreach ($request->file('files') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs("produksi/shooting_results/{$work->id}", $fileName, 'public');
                
                $uploadedFiles[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'url' => asset('storage/' . $filePath),
                    'uploaded_at' => now()->toDateTimeString()
                ];

                $filePaths[] = $filePath;

                // Create MediaFile record
                // NOTE: file_type must match enum in media_files migration
                // Using 'video' for production shooting results
                MediaFile::create([
                    'episode_id' => $work->episode_id,
                    'file_type' => 'video', // enum: audio, video, image, document, thumbnail, bts, highlight, advertisement
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                    'file_extension' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    // storage_disk, status, uploaded_at will use defaults from migration
                    'uploaded_by' => $user->id,
                    'metadata' => [
                        'produksi_work_id' => $work->id,
                        'original_name' => $file->getClientOriginalName()
                    ]
                ]);
            }

            // Update work with shooting files
            $work->update([
                'shooting_files' => $uploadedFiles,
                'shooting_file_links' => implode(',', $filePaths)
            ]);

            // Audit logging for file uploads
            foreach ($request->file('files') as $file) {
                ControllerSecurityHelper::logFileOperation(
                    'upload',
                    $file->getMimeType(),
                    $file->getClientOriginalName(),
                    $file->getSize(),
                    $work,
                    $request
                );
            }

            // Clear cache
            QueryOptimizer::clearAllIndexCaches();

            // Update run sheet if exists
            if ($work->runSheet) {
                $work->runSheet->update([
                    'uploaded_files' => $uploadedFiles,
                    'completion_notes' => $request->completion_notes,
                    'status' => 'completed'
                ]);
            }

            // Auto-create EditorWork
            $existingEditorWork = \App\Models\EditorWork::where('episode_id', $work->episode_id)
                ->where('work_type', 'main_episode')
                ->first();

            if (!$existingEditorWork) {
                $editorWork = \App\Models\EditorWork::create([
                    'episode_id' => $work->episode_id,
                    'work_type' => 'main_episode',
                    'status' => 'draft',
                    'source_files' => [
                        'produksi_work_id' => $work->id,
                        'shooting_files' => $uploadedFiles,
                        'shooting_file_links' => $filePaths
                    ],
                    'file_complete' => false, // Editor perlu cek kelengkapan file
                    'created_by' => $user->id
                ]);
            } else {
                // Update existing EditorWork dengan file terbaru
                $existingSourceFiles = $existingEditorWork->source_files ?? [];
                $existingEditorWork->update([
                    'source_files' => array_merge($existingSourceFiles, [
                        'produksi_work_id' => $work->id,
                        'shooting_files' => $uploadedFiles,
                        'shooting_file_links' => $filePaths,
                        'updated_at' => now()->toDateTimeString()
                    ]),
                    'file_complete' => false // Reset status karena ada file baru
                ]);
                $editorWork = $existingEditorWork;
            }

            // Notify Editor
            $editorUsers = \App\Models\User::where('role', 'Editor')->get();
            foreach ($editorUsers as $editorUser) {
                Notification::create([
                    'user_id' => $editorUser->id,
                    'type' => 'produksi_shooting_completed',
                    'title' => 'Hasil Syuting Tersedia',
                    'message' => "Produksi telah mengupload hasil syuting untuk Episode {$work->episode->episode_number}. Siap untuk editing.",
                    'data' => [
                        'produksi_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'editor_work_id' => $editorWork->id
                    ]
                ]);
            }

            // Auto-create DesignGrafisWork untuk Thumbnail YouTube dan BTS
            $designGrafisWorkTypes = ['thumbnail_youtube', 'thumbnail_bts'];
            $createdDesignGrafisWorks = [];

            foreach ($designGrafisWorkTypes as $workType) {
                $existingDesignGrafisWork = \App\Models\DesignGrafisWork::where('episode_id', $work->episode_id)
                    ->where('work_type', $workType)
                    ->first();

                if (!$existingDesignGrafisWork) {
                    $designGrafisWork = \App\Models\DesignGrafisWork::create([
                        'episode_id' => $work->episode_id,
                        'work_type' => $workType,
                        'title' => $workType === 'thumbnail_youtube' 
                            ? "Thumbnail YouTube - Episode {$work->episode->episode_number}"
                            : "Thumbnail BTS - Episode {$work->episode->episode_number}",
                        'description' => "Design thumbnail untuk Episode {$work->episode->episode_number}. File referensi dari Produksi sudah tersedia.",
                        'status' => 'draft',
                        'source_files' => [
                            'produksi_work_id' => $work->id,
                            'produksi_files' => [
                                'files' => $uploadedFiles,
                                'file_links' => $filePaths
                            ],
                            'available' => true,
                            'fetched_at' => now()->toDateTimeString()
                        ],
                        'created_by' => $user->id
                    ]);
                    $createdDesignGrafisWorks[] = $designGrafisWork;
                } else {
                    // Update existing DesignGrafisWork dengan file terbaru
                    $existingSourceFiles = $existingDesignGrafisWork->source_files ?? [];
                    $existingDesignGrafisWork->update([
                        'source_files' => array_merge($existingSourceFiles, [
                            'produksi_work_id' => $work->id,
                            'produksi_files' => [
                                'files' => $uploadedFiles,
                                'file_links' => $filePaths,
                                'updated_at' => now()->toDateTimeString()
                            ]
                        ])
                    ]);
                    $createdDesignGrafisWorks[] = $existingDesignGrafisWork;
                }
            }

            // Notify Design Grafis - File dari Produksi sudah tersedia dan work sudah dibuat
            $designGrafisUsers = \App\Models\User::where('role', 'Graphic Design')->get();
            foreach ($designGrafisUsers as $designUser) {
                Notification::create([
                    'user_id' => $designUser->id,
                    'type' => 'produksi_files_available',
                    'title' => 'File Produksi Tersedia',
                    'message' => "Produksi telah mengupload file hasil syuting untuk Episode {$work->episode->episode_number}. Design Grafis work untuk Thumbnail YouTube dan BTS sudah dibuat.",
                    'data' => [
                        'produksi_work_id' => $work->id,
                        'episode_id' => $work->episode_id,
                        'file_count' => count($uploadedFiles),
                        'design_grafis_works' => array_map(function($dgWork) {
                            return [
                                'id' => $dgWork->id,
                                'work_type' => $dgWork->work_type,
                                'title' => $dgWork->title
                            ];
                        }, $createdDesignGrafisWorks)
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'work' => $work->fresh(['episode', 'creativeWork', 'runSheet']),
                    'uploaded_files' => $uploadedFiles
                ],
                'message' => 'Shooting results uploaded successfully. Editor has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading shooting results: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Input Link File di Sistem Alamat Storage
     * POST /api/live-tv/roles/produksi/works/{id}/input-file-links
     */
    public function inputFileLinks(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file_links' => 'required|array|min:1',
                'file_links.*.url' => 'required|url',
                'file_links.*.file_name' => 'required|string|max:255',
                'file_links.*.file_size' => 'nullable|integer',
                'file_links.*.mime_type' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $work = ProduksiWork::findOrFail($id);

            if ($work->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            // Update work with file links
            $work->update([
                'shooting_files' => $request->file_links,
                'shooting_file_links' => implode(',', array_column($request->file_links, 'url'))
            ]);

            return response()->json([
                'success' => true,
                'data' => $work->fresh(['episode', 'creativeWork']),
                'message' => 'File links input successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error inputting file links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Baca Hasil QC
     * GET /api/live-tv/roles/produksi/qc-results/{episode_id}
     */
    public function getQCResults(Request $request, int $episodeId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only accept 'Production' role (English)
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            // Get QC results for this episode
            $qcWorks = QualityControlWork::where('episode_id', $episodeId)
                ->whereIn('status', ['approved', 'revision_needed', 'failed'])
                ->with(['episode', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Also get EpisodeQC if exists
            $episodeQC = \App\Models\EpisodeQC::where('program_episode_id', $episodeId)
                ->with(['qcBy'])
                ->orderBy('reviewed_at', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'qc_works' => $qcWorks,
                    'episode_qc' => $episodeQC,
                    'episode_id' => $episodeId
                ],
                'message' => 'QC results retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving QC results: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Producer requests
     * GET /api/live-tv/roles/produksi/producer-requests
     */
    public function getProducerRequests(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $produksiWorks = ProduksiWork::with(['episode.program.productionTeam'])
                ->whereHas('episode.program.productionTeam')
                ->whereNotNull('producer_requests')
                ->get();

            $requests = [];
            foreach ($produksiWorks as $work) {
                $producerRequests = $work->producer_requests ?? [];
                foreach ($producerRequests as $req) {
                    if (isset($req['status']) && $req['status'] === 'pending') {
                        $requests[] = [
                            'request_id' => $req['id'] ?? null,
                            'produksi_work_id' => $work->id,
                            'episode_id' => $work->episode_id,
                            'episode_number' => $work->episode->episode_number,
                            'request_type' => $req['request_type'] ?? null,
                            'reason' => $req['reason'] ?? null,
                            'missing_files' => $req['missing_files'] ?? [],
                            'shooting_schedule' => $req['shooting_schedule'] ?? null,
                            'requested_by' => $req['requested_by_name'] ?? null,
                            'requested_at' => $req['requested_at'] ?? null,
                            'work' => $work
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $requests,
                'message' => 'Producer requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving producer requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept Producer request and proceed
     * POST /api/live-tv/roles/produksi/producer-requests/{produksi_work_id}/accept
     */
    public function acceptProducerRequest(Request $request, int $produksiWorkId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'request_id' => 'required|string',
                'action' => 'nullable|in:accept,reject',
                'notes' => 'nullable|string|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $produksiWork = ProduksiWork::with(['episode.program.productionTeam'])->findOrFail($produksiWorkId);

            if ($produksiWork->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: This work is not assigned to you.'
                ], 403);
            }

            $producerRequests = $produksiWork->producer_requests ?? [];
            $requestFound = false;

            foreach ($producerRequests as &$req) {
                if (isset($req['id']) && $req['id'] === $request->request_id) {
                    $requestFound = true;
                    $req['status'] = $request->action === 'accept' ? 'accepted' : 'rejected';
                    $req['accepted_by'] = $user->id;
                    $req['accepted_by_name'] = $user->name;
                    $req['accepted_at'] = now()->toDateTimeString();
                    $req['notes'] = $request->notes;

                    // If accepted and request type is reshoot, reset shooting files
                    if ($req['status'] === 'accepted' && $req['request_type'] === 'reshoot') {
                        $produksiWork->update([
                            'shooting_files' => null,
                            'shooting_file_links' => null,
                            'status' => 'in_progress'
                        ]);
                    }

                    break;
                }
            }

            if (!$requestFound) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found'
                ], 404);
            }

            $produksiWork->update(['producer_requests' => $producerRequests]);

            // Notify Producer
            $producer = $produksiWork->episode->program->productionTeam->producer ?? null;
            if ($producer) {
                Notification::create([
                    'user_id' => $producer->id,
                    'type' => 'produksi_accepted_producer_request',
                    'title' => 'Produksi Menerima Permintaan',
                    'message' => "Produksi {$user->name} telah " . 
                        ($request->action === 'accept' ? 'menerima' : 'menolak') . 
                        " permintaan untuk Episode {$produksiWork->episode->episode_number}.",
                    'data' => [
                        'produksi_work_id' => $produksiWork->id,
                        'episode_id' => $produksiWork->episode_id,
                        'request_id' => $request->request_id,
                        'action' => $request->action,
                        'notes' => $request->notes
                    ]
                ]);
            }

            // If accepted, update work status back to in_progress
            if ($request->action === 'accept' && $produksiWork->status === 'completed') {
                $produksiWork->update(['status' => 'in_progress']);
            }

            return response()->json([
                'success' => true,
                'data' => $produksiWork->fresh(['episode']),
                'message' => 'Producer request ' . ($request->action === 'accept' ? 'accepted' : 'rejected') . ' successfully. Producer has been notified.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error accepting producer request: ' . $e->getMessage()
            ], 500);
        }
    }
}

