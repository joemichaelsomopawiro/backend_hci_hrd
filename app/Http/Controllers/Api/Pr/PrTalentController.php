<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrTalent;
use App\Models\PrProgram;
use App\Models\PrCreativeWork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Constants\Role;

class PrTalentController extends Controller
{
    /**
     * Get list of talents
     * GET /program-regular/talents
     */
    public function index(Request $request)
    {
        $query = PrTalent::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('expertise', 'like', "%{$search}%");
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $query->orderBy('name', 'asc');
        $talents = $query->get();

        return response()->json([
            'success' => true,
            'data' => $talents
        ]);
    }

    /**
     * Store new talent
     * POST /program-regular/talents
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:host,guest,other',
            'title' => 'nullable|string|max:255',
            'birth_place_date' => 'nullable|string|max:255',
            'expertise' => 'nullable|string',
            'social_media' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $talent = PrTalent::create([
            'name' => $request->name,
            'type' => $request->type ?? 'other',
            'title' => $request->title,
            'birth_place_date' => $request->birth_place_date,
            'expertise' => $request->expertise,
            'social_media' => $request->social_media,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Talent created successfully',
            'data' => $talent
        ], 201);
    }

    /**
     * Update a talent
     * PUT /program-regular/talents/{id}
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'birth_place_date' => 'nullable|string|max:255',
            'expertise' => 'nullable|string',
            'type' => 'nullable|in:host,guest,other',
            'social_media' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $talent = PrTalent::findOrFail($id);
            $talent->update($request->only([
                'name', 'title', 'birth_place_date', 'expertise', 'type', 'social_media', 'phone'
            ]));

            return response()->json([
                'success' => true,
                'data' => $talent->fresh(),
                'message' => 'Talent updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update talent: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a talent (soft delete)
     * DELETE /program-regular/talents/{id}
     */
    public function destroy($id)
    {
        try {
            $talent = PrTalent::findOrFail($id);
            $talent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Talent deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete talent: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Talent Database dashboard data
     * Returns all talents with episode counts per program (host & guest)
     * GET /program-regular/talent-database
     */
    public function dashboard(Request $request)
    {
        try {
            // 1. Get all registered talents
            $talents = PrTalent::orderBy('name')->get();

            // 2. Get all programs (all statuses)
            $programs = PrProgram::orderBy('name')
                ->get(['id', 'name']);

            // 3. Get all creative works that have talent_data
            $creativeWorks = PrCreativeWork::whereNotNull('talent_data')
                ->with(['episode' => function ($q) {
                    $q->select('id', 'program_id');
                }])
                ->get(['id', 'pr_episode_id', 'talent_data']);

            // 4. Build episode count map: talent_name -> program_id -> role -> count
            $episodeCounts = [];
            $allNamesFromCreative = [];

            foreach ($creativeWorks as $cw) {
                if (!$cw->episode) continue;
                $programId = $cw->episode->program_id;
                $talentData = $cw->talent_data;

                if (!is_array($talentData)) continue;

                // Process hosts
                $hosts = $talentData['host'] ?? [];
                if (!is_array($hosts)) $hosts = $hosts ? [$hosts] : [];
                foreach ($hosts as $name) {
                    $name = trim($name);
                    if (!$name) continue;
                    $allNamesFromCreative[$name] = true;
                    if (!isset($episodeCounts[$name][$programId])) {
                        $episodeCounts[$name][$programId] = ['host' => 0, 'guest' => 0];
                    }
                    $episodeCounts[$name][$programId]['host']++;
                }

                // Process guests
                $guests = $talentData['guest'] ?? [];
                if (!is_array($guests)) $guests = $guests ? [$guests] : [];
                foreach ($guests as $name) {
                    $name = trim($name);
                    if (!$name) continue;
                    $allNamesFromCreative[$name] = true;
                    if (!isset($episodeCounts[$name][$programId])) {
                        $episodeCounts[$name][$programId] = ['host' => 0, 'guest' => 0];
                    }
                    $episodeCounts[$name][$programId]['guest']++;
                }
            }

            // 5. Build unified talent list
            $talentMap = [];

            // Add registered talents
            foreach ($talents as $t) {
                $talentMap[$t->name] = [
                    'id' => $t->id,
                    'name' => $t->name,
                    'title' => $t->title,
                    'birth_place_date' => $t->birth_place_date,
                    'expertise' => $t->expertise,
                    'social_media' => $t->social_media,
                    'phone' => $t->phone,
                    'type' => $t->type,
                    'episode_counts' => [],
                    'total_episodes' => 0,
                ];
            }

            // Add/merge names from creative works
            foreach ($allNamesFromCreative as $name => $_) {
                if (!isset($talentMap[$name])) {
                    $talentMap[$name] = [
                        'id' => null,
                        'name' => $name,
                        'title' => null,
                        'birth_place_date' => null,
                        'expertise' => null,
                        'social_media' => null,
                        'phone' => null,
                        'type' => 'other',
                        'episode_counts' => [],
                        'total_episodes' => 0,
                    ];
                }
                // Always update episode_counts from creative works data
                $talentMap[$name]['episode_counts'] = $episodeCounts[$name] ?? [];
            }

            // 6. Calculate total episodes for each talent
            foreach ($talentMap as $name => &$talent) {
                $total = 0;
                foreach ($talent['episode_counts'] as $programCounts) {
                    $total += ($programCounts['host'] ?? 0) + ($programCounts['guest'] ?? 0);
                }
                $talent['total_episodes'] = $total;
            }
            unset($talent);

            // 7. Sort by name
            $talentList = array_values($talentMap);
            usort($talentList, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            // 8. Apply search filter if provided
            if ($request->has('search') && $request->search) {
                $search = strtolower($request->search);
                $talentList = array_values(array_filter($talentList, function ($t) use ($search) {
                    return str_contains(strtolower($t['name']), $search);
                }));
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'talents' => $talentList,
                    'programs' => $programs,
                    'total_talents' => count($talentList),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch talent database: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Export Talents to CSV
     * GET /program-regular/talents/export
     */
    public function exportCsv(Request $request)
    {
        $talents = PrTalent::orderBy('name')->get();
        $filename = 'talents_export_' . date('Ymd_His') . '.csv';

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Name', 'Title/Degree', 'Date of Birth', 'Expertise', 'Social Media', 'Phone', 'Type'];

        $callback = function() use($talents, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($talents as $t) {
                $row = [
                    $t->name,
                    $t->title,
                    $t->birth_place_date,
                    $t->expertise,
                    $t->social_media,
                    $t->phone,
                    $t->type
                ];
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import Talents from CSV
     * POST /program-regular/talents/import
     */
    public function importCsv(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,txt|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first('file')], 400);
        }

        $file = $request->file('file');
        $path = $file->getRealPath();
        
        $data = array_map('str_getcsv', file($path));
        if (count($data) <= 1) {
            return response()->json(['success' => false, 'message' => 'File is empty or contains only headers']);
        }

        $headers = array_shift($data);
        // Ensure minimum columns match our sample
        // Expected: Name, Title/Degree, Date of Birth, Expertise, Social Media, Phone, Type
        
        DB::beginTransaction();
        try {
            $importedCount = 0;
            foreach ($data as $row) {
                if (!isset($row[0]) || trim($row[0]) === '') continue; // Skip empty names

                $name = trim($row[0]);
                $title = isset($row[1]) ? trim($row[1]) : null;
                $dob = isset($row[2]) ? trim($row[2]) : null;
                $expertise = isset($row[3]) ? trim($row[3]) : null;
                $social = isset($row[4]) ? trim($row[4]) : null;
                $phone = isset($row[5]) ? trim($row[5]) : null;
                
                $type = 'other';
                if (isset($row[6])) {
                    $t = strtolower(trim($row[6]));
                    if (in_array($t, ['host', 'guest', 'other'])) {
                        $type = $t;
                    }
                }

                PrTalent::updateOrCreate(
                    ['name' => $name],
                    [
                        'title' => $title,
                        'birth_place_date' => $dob,
                        'expertise' => $expertise,
                        'social_media' => $social,
                        'phone' => $phone,
                        'type' => $type
                    ]
                );
                $importedCount++;
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully imported/updated $importedCount talents."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error importing data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**

     * Import Talents from Google Sheet
     * POST /program-regular/talent-database/import-sheet
     */
    public function importFromSheet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first('url')], 400);
        }

        $url = $request->input('url');
        
        // Convert Google Sheet URL to direct CSV export URL if it's a typical sharing link
        // Example: https://docs.google.com/spreadsheets/d/ID/edit?usp=sharing -> https://docs.google.com/spreadsheets/d/ID/export?format=csv
        if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            $id = $matches[1];
            $csvUrl = "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv";
            
            try {
                $content = file_get_contents($csvUrl);
                if ($content === false) {
                    throw new \Exception("Could not fetch CSV from Google Sheet. Ensure the sheet is accessible 'Anyone with the link'.");
                }

                $tempFile = tempnam(sys_get_temp_dir(), 'talent_import');
                file_put_contents($tempFile, $content);
                
                $data = array_map('str_getcsv', file($tempFile));
                unlink($tempFile);
                
                if (count($data) <= 1) {
                    return response()->json(['success' => false, 'message' => 'Sheet is empty or contains only headers']);
                }

                array_shift($data); // Remove headers

                DB::beginTransaction();
                $importedCount = 0;
                foreach ($data as $row) {
                    if (!isset($row[0]) || trim($row[0]) === '') continue;

                    $name = trim($row[0]);
                    $title = isset($row[1]) ? trim($row[1]) : null;
                    $dob = isset($row[2]) ? trim($row[2]) : null;
                    $expertise = isset($row[3]) ? trim($row[3]) : null;
                    $social = isset($row[4]) ? trim($row[4]) : null;
                    $phone = isset($row[5]) ? trim($row[5]) : null;
                    
                    $type = 'other';
                    if (isset($row[6])) {
                        $t = strtolower(trim($row[6]));
                        if (in_array($t, ['host', 'guest', 'other'])) {
                            $type = $t;
                        }
                    }

                    PrTalent::updateOrCreate(
                        ['name' => $name],
                        [
                            'title' => $title,
                            'birth_place_date' => $dob,
                            'expertise' => $expertise,
                            'social_media' => $social,
                            'phone' => $phone,
                            'type' => $type
                        ]
                    );
                    $importedCount++;
                }
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported/updated $importedCount talents from Google Sheet."
                ]);

            } catch (\Exception $e) {
                if (DB::transactionLevel() > 0) DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error importing from sheet: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json(['success' => false, 'message' => 'Invalid Google Sheet URL format'], 400);
    }
}
