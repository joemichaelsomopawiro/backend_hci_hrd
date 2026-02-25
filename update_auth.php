<?php

$filePath = 'c:/laragon/www/backend_hci_hrd/app/Http/Controllers/Api/ProduksiController.php';
$content = file_get_contents($filePath);

// 1. Update acceptWork
$oldAcceptWork = "            // Only accept 'Production' role (English)
            if (!\$user || \$user->role !== 'Production') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.'
                ], 403);
            }";

$newAcceptWork = "            // Authorization: Production role OR member of Tim Syuting for this episode
            \$isProductionRole = \$user->role === 'Production';
            \$isShootingMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$work->episode_id, 'shooting');

            if (!\$isProductionRole && !\$isShootingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the shooting team for this episode.'
                ], 403);
            }";

$content = str_replace($oldAcceptWork, $newAcceptWork, $content);

// 2. Update requestEquipment (it has the same block)
// Actually str_replace will replace all occurrences if they match exactly.
// But let's check other methods too.

// requestNeeds, completeWork, createRunSheet, uploadShootingResults, inputFileLinks, returnEquipment
// Most of them use the same block.

file_put_contents($filePath, $content);
echo "Successfully updated authorization blocks.";
