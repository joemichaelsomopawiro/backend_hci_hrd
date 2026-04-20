<?php
require 'c:/laragon/www/backend_hci_hrd/vendor/autoload.php';
$app = require_once 'c:/laragon/www/backend_hci_hrd/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Episode;

$ep = Episode::where('episode_number', 18)->whereHas('program', fn($q) => $q->where('name', 'like', '%test 2%'))->first();

if ($ep) {
    echo "Episode 18 found.\n";
    $cw = $ep->creativeWork;
    if ($cw) {
        echo "Shooting Schedule: " . ($cw->shooting_schedule ? $cw->shooting_schedule->format('Y-m-d H:i') : 'N/A') . "\n";
        
        // Trigger re-generation of deadlines
        $ep->generateDeadlines();
        echo "Deadlines re-generated.\n";
        
        $deadline = $ep->deadlines()->where('role', 'promotion_shooting')->first();
        if ($deadline) {
            echo "New Promotion Shooting Deadline: " . $deadline->deadline_date->format('Y-m-d H:i') . "\n";
        } else {
            echo "Promotion Shooting Deadline NOT found!\n";
        }

        $timSyuting = $ep->deadlines()->where('role', 'tim_syuting_coord')->first();
        if ($timSyuting) {
            echo "New Tim Syuting Deadline: " . $timSyuting->deadline_date->format('Y-m-d H:i') . "\n";
        }
    } else {
        echo "CreativeWork NOT found for Ep 18.\n";
    }
} else {
    echo "Episode 18 NOT found.\n";
}
