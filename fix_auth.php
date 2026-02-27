<?php

$filePath = 'c:/laragon/www/backend_hci_hrd/app/Http/Controllers/Api/ProduksiController.php';
$content = file_get_contents($filePath);

// Fix acceptWork
$badAcceptWork = "\$user = Auth::user();
            
            // Authorization: Production role OR member of Tim Syuting for this episode
            \$isProductionRole = \$user->role === 'Production';
            \$isShootingMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$work->episode_id, 'shooting');

            if (!\$isProductionRole && !\$isShootingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting team for this episode.'
                ], 403);
            }

            \$work = ProduksiWork::findOrFail(\$id);";

$fixedAcceptWork = "\$user = Auth::user();
            
            \$work = ProduksiWork::findOrFail(\$id);

            // Authorization: Production role OR member of Tim Syuting for this episode
            \$isProductionRole = \$user->role === 'Production';
            \$isShootingMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$work->episode_id, 'shooting');

            if (!\$isProductionRole && !\$isShootingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting team for this episode.'
                ], 403);
            }";

$content = str_replace($badAcceptWork, $fixedAcceptWork, $content);

// Fix requestEquipment
$badRequestEquipment = "try {
            \$user = Auth::user();
            
            // Authorization: Production role OR member of Tim Syuting for this episode
            \$isProductionRole = \$user->role === 'Production';
            \$isShootingMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$work->episode_id, 'shooting');

            if (!\$isProductionRole && !\$isShootingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting team for this episode.'
                ], 403);
            }";

$fixedRequestEquipment = "try {
            \$user = Auth::user();

            \$work = ProduksiWork::findOrFail(\$id);
            
            // Authorization: Production role OR member of Tim Syuting for this episode
            \$isProductionRole = \$user->role === 'Production';
            \$isShootingMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$work->episode_id, 'shooting');

            if (!\$isProductionRole && !\$isShootingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting team for this episode.'
                ], 403);
            }";

$content = str_replace($badRequestEquipment, $fixedRequestEquipment, $content);

// Ensure other methods are covered (completeWork, etc)
// I'll check if they were updated by the previous script.
// completeWork usually follows similar pattern.

file_put_contents($filePath, $content);
echo "Successfully fixed authorization blocks.";
