<?php
require 'c:/laragon/www/backend_hci_hrd/vendor/autoload.php';
$app = require_once 'c:/laragon/www/backend_hci_hrd/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Episode;

$epNumbers = [14, 15, 16, 17, 18];
$results = [];

$episodes = Episode::whereIn('episode_number', $epNumbers)
    ->whereHas('program', function($q) {
        $q->where('category', 'Music');
    })
    ->get();

foreach ($episodes as $ep) {
    $cw = $ep->creativeWork;
    $deadline = $ep->deadlines()->where('role', 'promotion_shooting')->first();
    $promotionWork = $ep->promotionWorks()->where('work_type', 'highlight_ig')->first() 
                   ?? $ep->promotionWorks()->first();
    
    $results[] = [
        'episode' => $ep->episode_number,
        'program' => $ep->program->name,
        'air_date' => $ep->air_date ? $ep->air_date->format('Y-m-d') : 'N/A',
        'shooting_schedule' => $cw ? ($cw->shooting_schedule ? $cw->shooting_schedule->format('Y-m-d H:i') : 'N/A') : 'No CreativeWork',
        'promotion_deadline' => $deadline ? $deadline->deadline_date->format('Y-m-d H:i') : 'N/A',
        'promotion_completed_at' => $promotionWork ? $promotionWork->updated_at->format('Y-m-d H:i') : 'Not Completed',
        'promotion_status' => $promotionWork ? $promotionWork->status : 'N/A'
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT);
