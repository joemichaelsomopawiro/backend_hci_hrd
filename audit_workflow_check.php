<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ============================================================
// STEP 1: Collect all live-tv routes grouped by prefix
// ============================================================
$routes = Illuminate\Support\Facades\Route::getRoutes();
$apiRoutes = [];

foreach ($routes as $route) {
    $uri = $route->uri();
    if (!str_contains($uri, 'live-tv')) continue;
    
    $methods = array_filter($route->methods(), fn($m) => $m !== 'HEAD');
    $action = $route->getAction();
    $controller = $action['controller'] ?? 'Closure';
    
    foreach ($methods as $method) {
        $apiRoutes[] = [
            'method' => $method,
            'uri' => $uri,
            'controller' => $controller,
        ];
    }
}

// ============================================================
// STEP 2: Parse Postman collection endpoints
// ============================================================
$postman = json_decode(file_get_contents(__DIR__ . '/Postman_Collection_HCI_HRD_Complete_Flow.json'), true);

function extractPostmanRequests($items, $folder = '', &$results = []) {
    foreach ($items as $item) {
        $name = $item['name'] ?? 'Unnamed';
        $path = $folder ? "$folder > $name" : $name;
        
        if (isset($item['item'])) {
            extractPostmanRequests($item['item'], $path, $results);
        } else if (isset($item['request'])) {
            $method = $item['request']['method'] ?? 'GET';
            $url = $item['request']['url']['raw'] ?? '';
            $hasBody = !empty($item['request']['body']['raw'] ?? '');
            $hasContentType = false;
            foreach (($item['request']['header'] ?? []) as $h) {
                if (strtolower($h['key'] ?? '') === 'content-type') $hasContentType = true;
            }
            $results[] = [
                'folder' => $folder,
                'name' => $name,
                'method' => $method,
                'url' => $url,
                'has_body' => $hasBody,
                'has_content_type' => $hasContentType,
            ];
        }
    }
    return $results;
}

$postmanRequests = extractPostmanRequests($postman['item']);

// ============================================================
// STEP 3: Define expected workflow per the documentation
// ============================================================
$workflow = [
    'A. Manager Program - Tim & Program' => [
        'A1. Buat Tim (Production Team)' => [
            ['POST', 'api/live-tv/manager-program/production-teams', 'Buat tim kerja'],
            ['POST', 'api/live-tv/manager-program/production-teams/{id}/members', 'Tambah anggota tim'],
        ],
        'A2. Buat Program & Episode' => [
            ['POST', 'api/live-tv/manager-program/programs', 'Buat program baru'],
            ['POST', 'api/live-tv/manager-program/programs/{id}/submit', 'Submit program'],
            ['POST', 'api/live-tv/manager-program/programs/{id}/generate-next-year', 'Generate episode tahun berikutnya'],
            ['GET',  'api/live-tv/programs', 'Lihat semua program'],
            ['GET',  'api/live-tv/programs/{id}/episodes', 'Lihat episode program'],
        ],
        'A3. Jadwal & Kontrol' => [
            ['GET',  'api/live-tv/manager-program/schedules', 'Lihat semua jadwal'],
            ['POST', 'api/live-tv/manager-program/schedules/{id}/cancel', 'Cancel jadwal'],
            ['POST', 'api/live-tv/manager-program/schedules/{id}/reschedule', 'Reschedule jadwal'],
            ['POST', 'api/live-tv/manager-program/programs/{id}/close', 'Tutup program'],
            ['POST', 'api/live-tv/manager-program/evaluate-all-programs', 'Evaluasi semua program'],
            ['PUT',  'api/live-tv/manager-program/programs/{id}/target-views', 'Set target views'],
            ['GET',  'api/live-tv/manager-program/programs/{id}/performance', 'Lihat performa program'],
            ['GET',  'api/live-tv/manager-program/programs/underperforming', 'Program kurang berkembang'],
        ],
        'A4. Approval & Override' => [
            ['GET',  'api/live-tv/manager-program/approvals', 'Lihat semua approval'],
            ['POST', 'api/live-tv/manager-program/approvals/{id}/override', 'Override approval'],
            ['GET',  'api/live-tv/manager-program/special-budget-approvals', 'Lihat budget khusus'],
            ['POST', 'api/live-tv/manager-program/special-budget-approvals/{id}/approve', 'ACC budget khusus'],
            ['POST', 'api/live-tv/manager-program/special-budget-approvals/{id}/reject', 'Tolak budget khusus'],
            ['GET',  'api/live-tv/manager-program/rundown-edit-requests', 'Lihat edit rundown'],
            ['POST', 'api/live-tv/manager-program/rundown-edit-requests/{id}/approve', 'ACC edit rundown'],
            ['POST', 'api/live-tv/manager-program/rundown-edit-requests/{id}/reject', 'Tolak edit rundown'],
        ],
    ],
    'B. Manager Broadcasting / Distribution Manager' => [
        'Schedule Options' => [
            ['GET',  'api/live-tv/roles/distribution-manager/schedule-options', 'Lihat opsi jadwal'],
            ['POST', 'api/live-tv/roles/distribution-manager/schedule-options/{id}/approve', 'ACC opsi jadwal'],
            ['POST', 'api/live-tv/roles/distribution-manager/schedule-options/{id}/reject', 'Tolak opsi jadwal'],
            ['POST', 'api/live-tv/roles/distribution-manager/schedule-options/{id}/revise', 'Revisi opsi jadwal'],
        ],
        'Broadcasting Approval' => [
            ['GET',  'api/live-tv/roles/manager-broadcasting/schedules', 'Lihat jadwal broadcasting'],
            ['GET',  'api/live-tv/roles/manager-broadcasting/works', 'Lihat kerja broadcasting'],
            ['POST', 'api/live-tv/roles/manager-broadcasting/schedules/{id}/approve', 'ACC jadwal'],
            ['POST', 'api/live-tv/roles/manager-broadcasting/schedules/{id}/reject', 'Tolak jadwal'],
            ['POST', 'api/live-tv/roles/manager-broadcasting/works/{id}/approve', 'ACC kerja broadcasting'],
        ],
        'QC Editor (oleh Manager Broadcasting)' => [
            ['GET',  'api/live-tv/roles/quality-control/works', 'Lihat QC works'],
            ['POST', 'api/live-tv/roles/quality-control/works/{id}/accept-work', 'Terima QC'],
            ['POST', 'api/live-tv/roles/quality-control/works/{id}/submit-qc-form', 'Isi form QC'],
            ['POST', 'api/live-tv/roles/quality-control/works/{id}/finalize', 'Finalize QC'],
        ],
        'Distribusi Kerja' => [
            ['GET',  'api/live-tv/roles/distribution/dashboard', 'Dashboard distribusi'],
            ['GET',  'api/live-tv/roles/distribution/available-workers/{role}', 'Worker tersedia per role'],
            ['POST', 'api/live-tv/roles/distribution/episodes/{id}/assign-work', 'Assign kerja ke episode'],
        ],
    ],
    'C. Producer' => [
        'Approval Lagu & Arrangement' => [
            ['GET',  'api/live-tv/producer/approvals', 'Lihat approval masuk'],
            ['POST', 'api/live-tv/producer/approvals/{id}/approve', 'Approve'],
            ['POST', 'api/live-tv/producer/approvals/{id}/reject', 'Reject'],
            ['GET',  'api/live-tv/producer/songs', 'Lihat lagu'],
            ['GET',  'api/live-tv/producer/singers', 'Lihat penyanyi'],
            ['PUT',  'api/live-tv/producer/arrangements/{id}/edit-song-singer', 'Edit lagu/penyanyi'],
        ],
        'Creative Work Review' => [
            ['POST', 'api/live-tv/producer/creative-works/{id}/review', 'Review creative work'],
            ['PUT',  'api/live-tv/producer/creative-works/{id}/edit', 'Edit langsung creative work'],
            ['POST', 'api/live-tv/producer/creative-works/{id}/final-approval', 'Final approve/reject'],
            ['POST', 'api/live-tv/producer/creative-works/{id}/assign-team', 'Assign tim (syuting/setting/rekaman)'],
            ['POST', 'api/live-tv/producer/creative-works/{id}/cancel-shooting', 'Cancel syuting'],
            ['POST', 'api/live-tv/producer/creative-works/{id}/request-special-budget', 'Ajukan budget khusus'],
        ],
        'Team & Monitoring' => [
            ['GET',  'api/live-tv/producer/episodes/{id}/team-assignments', 'Lihat tim per episode'],
            ['PUT',  'api/live-tv/producer/team-assignments/{id}', 'Update assignment'],
            ['PUT',  'api/live-tv/producer/team-assignments/{id}/replace-team', 'Ganti tim darurat'],
            ['POST', 'api/live-tv/producer/episodes/{id}/copy-team-assignment', 'Copy tim ke episode lain'],
            ['POST', 'api/live-tv/producer/send-reminder-to-crew', 'Kirim reminder ke crew'],
        ],
    ],
    'D1. Music Arranger' => [
        'Pilih Lagu & Penyanyi' => [
            ['GET',  'api/live-tv/roles/music-arranger/songs', 'Lihat lagu tersedia'],
            ['GET',  'api/live-tv/roles/music-arranger/singers', 'Lihat penyanyi tersedia'],
            ['GET',  'api/live-tv/roles/music-arranger/arrangements', 'Lihat arrangement'],
            ['POST', 'api/live-tv/roles/music-arranger/arrangements', 'Buat arrangement (pilih lagu+penyanyi)'],
            ['POST', 'api/live-tv/roles/music-arranger/arrangements/{id}/submit-song-proposal', 'Ajukan proposal lagu'],
        ],
        'Arrangement Work' => [
            ['POST', 'api/live-tv/roles/music-arranger/arrangements/{id}/accept-work', 'Terima kerjaan'],
            ['PUT',  'api/live-tv/roles/music-arranger/arrangements/{id}', 'Upload link arr lagu'],
            ['POST', 'api/live-tv/roles/music-arranger/arrangements/{id}/submit', 'Ajukan arr ke Producer'],
            ['POST', 'api/live-tv/roles/music-arranger/arrangements/{id}/complete-work', 'Selesaikan kerjaan'],
        ],
    ],
    'D3. Creative' => [
        'Creative Work' => [
            ['GET',  'api/live-tv/roles/creative/works', 'Lihat kerjaan'],
            ['GET',  'api/live-tv/roles/creative/works/{id}', 'Detail kerjaan'],
            ['POST', 'api/live-tv/roles/creative/works/{id}/accept-work', 'Terima kerjaan'],
            ['PUT',  'api/live-tv/roles/creative/works/{id}', 'Update (script, storyboard, jadwal, budget)'],
            ['POST', 'api/live-tv/roles/creative/works/{id}/submit', 'Ajukan ke Producer'],
            ['POST', 'api/live-tv/roles/creative/works/{id}/resubmit', 'Ajukan ulang setelah revisi'],
            ['PUT',  'api/live-tv/roles/creative/works/{id}/revise', 'Revisi setelah reject'],
        ],
    ],
    'D5.2 Sound Engineer (Recording)' => [
        'Recording' => [
            ['GET',  'api/live-tv/roles/sound-engineer/recordings', 'Lihat recordings'],
            ['POST', 'api/live-tv/roles/sound-engineer/recordings/{id}/accept-work', 'Terima kerjaan'],
            ['POST', 'api/live-tv/roles/sound-engineer/recordings/{id}/accept-schedule', 'Terima jadwal rekaman'],
            ['POST', 'api/live-tv/roles/sound-engineer/recordings/{id}/request-equipment', 'Request alat ke Art Set'],
            ['POST', 'api/live-tv/roles/sound-engineer/recordings/{id}/start', 'Mulai rekaman'],
            ['POST', 'api/live-tv/roles/sound-engineer/recordings/{id}/complete', 'Selesai rekaman + kirim link'],
            ['POST', 'api/live-tv/roles/sound-engineer/equipment/{id}/notify-return', 'Kembalikan alat'],
        ],
    ],
    'D5.2 Sound Engineer (Editing)' => [
        'Editing Vocal' => [
            ['GET',  'api/live-tv/roles/sound-engineer-editing/works', 'Lihat kerjaan editing'],
            ['POST', 'api/live-tv/roles/sound-engineer-editing/works/{id}/accept-work', 'Terima kerjaan'],
            ['POST', 'api/live-tv/roles/sound-engineer-editing/input-vocal-link', 'Input link vocal'],
            ['POST', 'api/live-tv/roles/sound-engineer-editing/works/{id}/submit', 'Submit ke QC Producer'],
        ],
    ],
    'D5.3 Promosi' => [
        'Workflow' => [
            ['GET',  'api/live-tv/roles/promosi/works', 'Lihat kerjaan'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/accept-work', 'Terima kerjaan'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/upload-bts-video', 'Upload BTS video (link)'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/upload-talent-photos', 'Upload foto talent (link)'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/complete-work', 'Selesai kerjaan'],
        ],
        'Promosi Final (setelah QC & Broadcasting)' => [
            ['POST', 'api/live-tv/roles/promosi/works/{id}/share-facebook', 'Share ke Facebook'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/upload-story-ig', 'Upload story IG'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/upload-reels-facebook', 'Upload reels Facebook'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/share-wa-group', 'Share ke WA Group'],
            ['POST', 'api/live-tv/roles/promosi/works/{id}/complete-promotion-work', 'Selesai promosi akhir'],
        ],
    ],
    'D5.4 Produksi' => [
        'Workflow' => [
            ['GET',  'api/live-tv/roles/produksi/works', 'Lihat kerjaan'],
            ['POST', 'api/live-tv/roles/produksi/works/{id}/accept-work', 'Terima kerjaan'],
            ['POST', 'api/live-tv/roles/produksi/works/{id}/request-equipment', 'Request alat'],
            ['POST', 'api/live-tv/roles/produksi/works/{id}/request-needs', 'Ajukan kebutuhan'],
            ['POST', 'api/live-tv/roles/produksi/works/{id}/create-run-sheet', 'Buat run sheet'],
            ['POST', 'api/live-tv/roles/produksi/works/{id}/input-file-links', 'Input link file syuting'],
            ['POST', 'api/live-tv/roles/produksi/equipment/{id}/notify-return', 'Kembalikan alat'],
            ['POST', 'api/live-tv/roles/produksi/works/{id}/complete-work', 'Selesai kerjaan'],
        ],
    ],
    'D6. Art Set Properti' => [
        'Equipment' => [
            ['GET',  'api/live-tv/roles/art-set-properti/requests', 'Lihat request alat'],
            ['POST', 'api/live-tv/roles/art-set-properti/requests/{id}/approve', 'ACC alat'],
            ['GET',  'api/live-tv/roles/art-set-properti/equipment', 'Inventory alat'],
            ['POST', 'api/live-tv/roles/art-set-properti/equipment', 'Buat alat baru'],
            ['PUT',  'api/live-tv/roles/art-set-properti/equipment/{id}', 'Update alat'],
            ['POST', 'api/live-tv/roles/art-set-properti/equipment/{id}/return', 'ACC pengembalian alat'],
        ],
    ],
    'D9. Editor' => [
        'Workflow' => [
            ['GET',  'api/live-tv/roles/editor/works', 'Lihat kerjaan'],
            ['POST', 'api/live-tv/roles/editor/works/{id}/accept-work', 'Terima kerjaan'],
            ['GET',  'api/live-tv/roles/editor/episodes/{id}/run-sheet', 'Lihat run sheet'],
            ['GET',  'api/live-tv/roles/editor/episodes/{id}/approved-audio', 'Lihat audio approved'],
            ['POST', 'api/live-tv/roles/editor/works/{id}/report-missing-files', 'Lapor file kurang'],
            ['PUT',  'api/live-tv/roles/editor/works/{id}', 'Update kerjaan (input link)'],
            ['POST', 'api/live-tv/roles/editor/works/{id}/submit', 'Submit ke QC'],
        ],
    ],
    'E2. Design Grafis' => [
        'Workflow' => [
            ['GET',  'api/live-tv/roles/design-grafis/works', 'Lihat kerjaan'],
            ['GET',  'api/live-tv/roles/design-grafis/shared-files', 'Lihat file dari produksi/promosi'],
            ['POST', 'api/live-tv/roles/design-grafis/works/{id}/upload-thumbnail-youtube', 'Upload thumbnail YT (link)'],
            ['POST', 'api/live-tv/roles/design-grafis/works/{id}/upload-thumbnail-bts', 'Upload thumbnail BTS (link)'],
            ['POST', 'api/live-tv/roles/design-grafis/works/{id}/complete-work', 'Selesai kerjaan'],
            ['POST', 'api/live-tv/roles/design-grafis/works/{id}/submit-to-qc', 'Submit ke QC'],
        ],
    ],
    'E3. Editor Promosi' => [
        'Workflow' => [
            ['GET',  'api/live-tv/roles/editor-promosi/works', 'Lihat kerjaan'],
            ['GET',  'api/live-tv/roles/editor-promosi/source-files', 'Lihat source files'],
            ['POST', 'api/live-tv/roles/editor-promosi/works/{id}/upload', 'Upload file (link)'],
            ['POST', 'api/live-tv/roles/editor-promosi/works/{id}/submit-to-qc', 'Submit ke QC'],
        ],
    ],
    'E4. Quality Control' => [
        'Workflow' => [
            ['GET',  'api/live-tv/roles/quality-control/works', 'Lihat QC works'],
            ['POST', 'api/live-tv/roles/quality-control/works/{id}/accept-work', 'Terima kerjaan QC'],
            ['POST', 'api/live-tv/roles/quality-control/works/{id}/submit-qc-form', 'Isi form QC'],
            ['POST', 'api/live-tv/roles/quality-control/works/{id}/finalize', 'Finalize (approve/reject)'],
        ],
    ],
    'F1. Broadcasting' => [
        'Workflow' => [
            ['GET',  'api/live-tv/roles/broadcasting/works', 'Lihat kerjaan'],
            ['POST', 'api/live-tv/roles/broadcasting/works/{id}/accept-work', 'Terima kerjaan'],
            ['POST', 'api/live-tv/roles/broadcasting/works/{id}/schedule-work-playlist', 'Masukkan jadwal playlist'],
            ['POST', 'api/live-tv/roles/broadcasting/works/{id}/upload-youtube', 'Upload ke YouTube'],
            ['POST', 'api/live-tv/roles/broadcasting/works/{id}/upload-website', 'Upload ke website'],
            ['POST', 'api/live-tv/roles/broadcasting/works/{id}/input-youtube-link', 'Input link YT'],
            ['POST', 'api/live-tv/roles/broadcasting/works/{id}/complete-work', 'Selesai kerjaan'],
        ],
    ],
    'General' => [
        'Notifications' => [
            ['GET',  'api/live-tv/notifications', 'Lihat notifikasi'],
            ['POST', 'api/live-tv/notifications/{id}/mark-as-read', 'Tandai sudah dibaca'],
        ],
        'Workflow Monitoring' => [
            ['GET',  'api/live-tv/episodes/{id}/progress', 'Progress episode'],
            ['GET',  'api/live-tv/episodes/{id}/monitor-workflow', 'Monitor workflow'],
            ['GET',  'api/live-tv/episodes/{id}/workflow-history', 'Workflow history'],
        ],
        'General Affairs' => [
            ['GET',  'api/live-tv/roles/general-affairs/budget-requests', 'Lihat permohonan dana'],
            ['POST', 'api/live-tv/roles/general-affairs/budget-requests/{id}/approve', 'ACC dana'],
            ['POST', 'api/live-tv/roles/general-affairs/budget-requests/{id}/reject', 'Tolak dana'],
            ['POST', 'api/live-tv/roles/general-affairs/budget-requests/{id}/process-payment', 'Proses pembayaran'],
        ],
    ],
];

// ============================================================
// STEP 4: Cross-reference
// ============================================================
echo "=== WORKFLOW vs BACKEND ROUTES CROSS-CHECK ===\n\n";

$totalExpected = 0;
$totalFound = 0;
$totalMissing = 0;
$missingRoutes = [];

foreach ($workflow as $section => $subsections) {
    echo "📋 {$section}\n";
    foreach ($subsections as $subName => $endpoints) {
        echo "  📁 {$subName}\n";
        foreach ($endpoints as $ep) {
            $totalExpected++;
            $expectedMethod = $ep[0];
            $expectedUri = $ep[1];
            $desc = $ep[2];
            
            // Try to match route
            $found = false;
            foreach ($apiRoutes as $route) {
                if ($route['method'] !== $expectedMethod) continue;
                $routePattern = preg_replace('/\{[^}]+\}/', '{id}', $route['uri']);
                $expectedPattern = preg_replace('/\{[^}]+\}/', '{id}', $expectedUri);
                if ($routePattern === $expectedPattern) {
                    $found = true;
                    break;
                }
            }
            
            // Check Postman
            $inPostman = false;
            foreach ($postmanRequests as $pr) {
                if ($pr['method'] !== $expectedMethod) continue;
                $normalizedUrl = preg_replace('/\{\{base_url\}\}\//', '', $pr['url']);
                $normalizedUrl = preg_replace('/\{\{[^}]+\}\}/', '{id}', $normalizedUrl);
                $normalizedUrl = preg_replace('/\?.*$/', '', $normalizedUrl);
                $expectedPattern = preg_replace('/\{[^}]+\}/', '{id}', $expectedUri);
                if ($normalizedUrl === $expectedPattern) {
                    $inPostman = true;
                    break;
                }
            }
            
            $routeStatus = $found ? '✅' : '❌';
            $postmanStatus = $inPostman ? '✅' : '❌';
            
            if ($found) $totalFound++;
            if (!$found) {
                $totalMissing++;
                $missingRoutes[] = "{$expectedMethod} {$expectedUri} ({$desc})";
            }
            
            if (!$found || !$inPostman) {
                echo "    {$routeStatus} Route | {$postmanStatus} Postman | [{$expectedMethod}] {$expectedUri}\n";
                echo "       → {$desc}\n";
            }
        }
    }
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Expected endpoints: {$totalExpected}\n";
echo "Found in routes:    {$totalFound} ✅\n";
echo "Missing routes:     {$totalMissing} ❌\n";
echo "Total API routes (live-tv): " . count($apiRoutes) . "\n";
echo "Total Postman requests: " . count($postmanRequests) . "\n";

if (!empty($missingRoutes)) {
    echo "\n❌ MISSING ROUTES (not in backend):\n";
    foreach ($missingRoutes as $i => $mr) {
        echo ($i+1) . ". {$mr}\n";
    }
}
