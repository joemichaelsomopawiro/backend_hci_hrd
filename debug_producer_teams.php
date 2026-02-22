<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ProductionTeam;
use App\Services\ProductionTeamService;
use Illuminate\Support\Facades\DB;

// 1. Find a producer
$producer = User::where('role', 'producer')->first();

if (!$producer) {
    echo "No producer found in database.\n";
    exit;
}

echo "Testing with Producer: {$producer->name} (ID: {$producer->id})\n";

$service = app(ProductionTeamService::class);

// 2. Check existing teams (Owner OR Member)
$requestMock = new \Illuminate\Http\Request();
$requestMock->replace(['producer_id' => $producer->id]);

// We need to call the method directly from service if possible, 
// but service method signature is getTeams(array $filters) ?
// Let's check the service method signature again.
// The snippet I viewed earlier was createTeam, addMember, removeMember.
// I need to check getTeams in ProductionTeamService.php

// Let's assume the controller calls:
// $teams = $this->productionTeamService->getTeams($request->all());

// Let's inspect ProductionTeamService::getTeams first.
// I'll assume it exists. If not, I'll fix the script.

try {
    // 3. Create a team where they are a MEMBER (not owner)
    // Find another user to be the owner
    $owner = User::where('role', 'program_manager')->first() ?? User::where('id', '!=', $producer->id)->first();
    
    if ($owner) {
        $teamName = "Debug Team Member " . time();
        $team = ProductionTeam::create([
            'name' => $teamName,
            'producer_id' => $owner->id,
            'program_id' => 1, // Assumptions
            'description' => 'Debug team for membership test',
            'is_active' => true,
            'created_by' => $owner->id
        ]);
        
        echo "Created Debug Team: {$team->name} (ID: {$team->id}) owned by {$owner->name}\n";
        
        // Add producer as member
        $team->members()->create([
            'user_id' => $producer->id,
            'role' => 'creative',
            'is_active' => true,
            'joined_at' => now()
        ]);
        
        echo "Added Producer {$producer->name} as member to team {$team->id}\n";
    }

    // 4. Test Query Logic manually (simulating Service/Controller)
    $teams = ProductionTeam::query()
        ->where(function($q) use ($producer) {
            $q->where('producer_id', $producer->id)
              ->orWhereHas('members', function ($sq) use ($producer) {
                  $sq->where('user_id', $producer->id)
                    ->where('is_active', true);
              });
        })
        ->get();

    echo "\nQuery Result:\n";
    echo "Total Teams Found: " . $teams->count() . "\n";
    foreach ($teams as $t) {
        $isOwner = $t->producer_id == $producer->id;
        $isMember = $t->members->contains('user_id', $producer->id);
        echo "- [{$t->id}] {$t->name} (Owner: " . ($isOwner ? 'YES' : 'NO') . ", Member: " . ($isMember ? 'YES' : 'NO') . ")\n";
    }
    
    // Clean up
    if (isset($team)) {
        $team->members()->where('user_id', $producer->id)->delete();
        $team->delete();
        echo "\nCleaned up debug team.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
