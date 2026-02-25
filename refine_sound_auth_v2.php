<?php

$filePath = 'c:/laragon/www/backend_hci_hrd/app/Http/Controllers/Api/SoundEngineerController.php';
$content = file_get_contents($filePath);

// 1. Update store() method
$oldStoreAccess = "if (!\$this->isSoundEngineer(\$user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . (\$user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            \$validator = Validator::make(\$request->all(), [";

$newStoreAccess = "\$isSoundEngineerRole = \$this->isSoundEngineer(\$user);
            \$isVocalMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$request->episode_id ?? 0, 'recording');

            if (!\$isSoundEngineerRole && !\$isVocalMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You do not have access to create recordings for this episode.'
                ], 403);
            }

            \$validator = Validator::make(\$request->all(), [";

$content = str_replace($oldStoreAccess, $newStoreAccess, $content);

// 2. Update show() method
$oldShowAccess = "if (!\$this->isSoundEngineer(\$user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . (\$user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            \$recording = SoundEngineerRecording::with([";

$newShowAccess = "\$recording = SoundEngineerRecording::with(["; // Just placeholder to find it

// Actually the show() method in stage 1 already has a check for creator or team.
// Let's check the current content of show() after update_sound_auth.php

// 3. Update update(), startRecording(), completeRecording(), review()
// They all follow a similar pattern: check isSoundEngineer, then check creator/team.

$methods = ['update', 'startRecording', 'completeRecording', 'review'];

foreach ($methods as $method) {
    $oldAuth = "if (!\$this->isSoundEngineer(\$user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . (\$user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            \$recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail(\$id);";
    
    $newAuth = "\$recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail(\$id);
            \$isSoundEngineerRole = \$this->isSoundEngineer(\$user);
            \$isVocalMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$recording->episode_id, 'recording');

            if (!\$isSoundEngineerRole && !\$isVocalMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the vocal recording team for this episode.'
                ], 403);
            }

            // Check if user has access (must be creator or role or team)
            if (\$recording->created_by !== \$user->id && !\$isSoundEngineerRole && !\$isVocalMember) {";
    
    // This replace might be risky if whitespaces differ.
    // I will use a more robust search and replace in the final script.
}

// I'll write the actual refinement script now.
file_put_contents($filePath, $content);
echo "Injected basic auth updates.";
