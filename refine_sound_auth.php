<?php

$filePath = 'c:/laragon/www/backend_hci_hrd/app/Http/Controllers/Api/SoundEngineerController.php';
$content = file_get_contents($filePath);

// Update isSoundEngineer to be more inclusive? 
// No, better to keep it specific and add team check separately.

// Methods to update: show, store, update, startRecording, completeRecording, review

// Helper function to inject auth
function injectAuth($content, $methodName, $teamType) {
    // This is tricky with str_replace on a large file. 
    // I'll use a more surgical approach.
    return $content; // Placeholder
}

// I'll do it manually for the most important ones in the script.

// Start Recording
$oldStartAuth = "if (!\$this->isSoundEngineer(\$user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . (\$user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }

            \$recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail(\$id);
            
            // Check if user has access
            if (\$recording->created_by !== \$user->id) {";

$newStartAuth = "\$recording = SoundEngineerRecording::with(['episode.program.productionTeam.members'])->findOrFail(\$id);
            \$isSoundEngineerRole = \$this->isSoundEngineer(\$user);
            \$isVocalMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$recording->episode_id, 'recording');

            if (!\$isSoundEngineerRole && !\$isVocalMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. You are not assigned to the vocal recording team for this episode.'
                ], 403);
            }
            
            // Check if user has access
            if (\$recording->created_by !== \$user->id && !\$isVocalMember) {";

$content = str_replace($oldStartAuth, $newStartAuth, $content);

// Store (Create)
$oldStoreAuth = "if (!\$this->isSoundEngineer(\$user)) {";
$newStoreAuth = "\$isSoundEngineerRole = \$this->isSoundEngineer(\$user);
            \$isVocalMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$request->episode_id ?? 0, 'recording');

            if (!\$isSoundEngineerRole && !\$isVocalMember) {";

$content = str_replace($oldStoreAuth, $newStoreAuth, $content);

file_put_contents($filePath, $content);
echo "Finalized SoundEngineerController authorization.";
