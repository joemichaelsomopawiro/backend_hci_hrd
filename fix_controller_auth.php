<?php

$file = 'c:\laragon\www\backend_hci_hrd\app\Http\Controllers\Api\EditorPromosiController.php';
$content = file_get_contents($file);

// 1. Expand allowedRoles
$oldRoles = "\$allowedRoles = ['editor promotion', 'editor promosi', 'promotion editor'];";
$newRoles = "\$allowedRoles = ['editor promotion', 'editor promosi', 'promotion editor', 'promotion', 'promosi'];";
$content = str_replace($oldRoles, $newRoles, $content);

// 2. Expand canUserPerformTask check
$oldCheck = "if (!\$user || !MusicProgramAuthorization::canUserPerformTask(\$user, null, 'Editor Promotion')) {";
$newCheck = "\$isAuthorized = MusicProgramAuthorization::canUserPerformTask(\$user, null, 'Editor Promotion') || MusicProgramAuthorization::canUserPerformTask(\$user, null, 'Promotion');\n            if (!\$user || !\$isAuthorized) {";
$content = str_replace($oldCheck, $newCheck, $content);

file_put_contents($file, $content);
echo "Controller fixed successfully.\n";
