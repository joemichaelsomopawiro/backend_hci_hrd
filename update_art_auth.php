<?php

$filePath = 'c:/laragon/www/backend_hci_hrd/app/Http/Controllers/Api/ArtSetPropertiController.php';
$content = file_get_contents($filePath);

// Update index, store, update, destroy, getRequests, approveRequest, etc.
// Tim Setting members should be able to see and interact with their episode's equipment.

// 1. Index method: Allow if user is member of any setting team
$oldIndexAuth = "if (!\$user || \$user->role !== 'Art & Set Properti') {";
$newIndexAuth = "if (!\$user || (\$user->role !== 'Art & Set Properti' && !\\App\\Models\\ProductionTeamMember::where('user_id', \$user->id)->where('is_active', true)->whereHas('assignment', function(\$q) { \$q->where('team_type', 'setting'); })->exists())) {";

$content = str_replace($oldIndexAuth, $newIndexAuth, $content);

file_put_contents($filePath, $content);
echo "Updated ArtSetPropertiController index auth.";
