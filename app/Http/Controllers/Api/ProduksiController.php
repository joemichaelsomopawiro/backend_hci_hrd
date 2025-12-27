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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN) untuk fleksibilitas data
            if (!in_array($user->role, ['Produksi', 'Production'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }

            $query = ProduksiWork::with(['episode', 'creativeWork', 'createdBy']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $works = $query->orderBy('created_at', 'desc')->paginate(15);

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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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

            $work->acceptWork($user->id);

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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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

            $work->completeWork($user->id, $request->notes);

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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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

            // Update run sheet if exists
            if ($work->runSheet) {
                $work->runSheet->update([
                    'uploaded_files' => $uploadedFiles,
                    'completion_notes' => $request->completion_notes,
                    'status' => 'completed'
                ]);
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
                        'file_count' => count($uploadedFiles)
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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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
            
            // Terima role 'Produksi' (ID) dan 'Production' (EN)
            if (!$user || !in_array($user->role, ['Produksi', 'Production'])) {
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
}

