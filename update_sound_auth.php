<?php

$filePath = 'c:/laragon/www/backend_hci_hrd/app/Http/Controllers/Api/SoundEngineerController.php';
$content = file_get_contents($filePath);

// We need to allow Tim Rekam Vokal (recording team) in index, show, store, update, startRecording, completeRecording, etc.

// 1. Index method: Allow if user is member of any recording team
$oldIndexAuth = "if (!\$this->isSoundEngineer(\$user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . (\$user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }";

$newIndexAuth = "if (!\$this->isSoundEngineer(\$user) && !\\App\\Models\\ProductionTeamMember::where('user_id', \$user->id)->where('is_active', true)->whereHas('assignment', function(\$q) { \$q->where('team_type', 'recording'); })->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account role (' . (\$user->role ?? 'unknown') . ') does not have access to the music workflow system. Please contact your administrator if you believe this is an error.'
                ], 403);
            }";

$content = str_replace($oldIndexAuth, $newIndexAuth, $content);

// 2. Show method: Update access check
$oldShowAuth = "\$recording = SoundEngineerRecording::with([
            'episode.program.productionTeam.members',
            'musicArrangement',
            'createdBy',
            'reviewedBy'
        ])->findOrFail(\$id);
        
        // Check if user has access to this recording (must be creator or in same production team)
        if (\$recording->created_by !== \$user->id) {
            // Check if user is in the same production team
            \$productionTeam = \$recording->episode?->program?->productionTeam;
            if (\$productionTeam) {
                // Access members from eager-loaded collection
                \$hasAccess = \$productionTeam->members
                    ->where('user_id', \$user->id)
                    ->where('role', 'sound_eng')
                    ->where('is_active', true)
                    ->count() > 0;
                    
                    if (!\$hasAccess)";

$newShowAuth = "\$recording = SoundEngineerRecording::with([
            'episode.program.productionTeam.members',
            'musicArrangement',
            'createdBy',
            'reviewedBy'
        ])->findOrFail(\$id);
        
        // Authorization: Creator OR Sound Engineer role member OR Tim Rekam Vokal member
        \$isCreator = \$recording->created_by === \$user->id;
        \$isSoundEngineerRole = \$this->isSoundEngineer(\$user);
        \$isVocalMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$recording->episode_id, 'recording');

        if (!\$isCreator && !\$isSoundEngineerRole && !\$isVocalMember) {";

// Note: I need to be careful with the exact match. Let's try a simpler approach if possible.
// Actually, I'll use regex for show.

// 3. Store method: Update access check
$oldStoreAuth = "if (!\$this->isSoundEngineer(\$user)) {";
$newStoreAuth = "\$isSoundEngineerRole = \$this->isSoundEngineer(\$user);
            \$isVocalMember = \\App\\Models\\ProductionTeamMember::isMemberForEpisode(\$user->id, \$request->episode_id ?? 0, 'recording');

            if (!\$isSoundEngineerRole && !\$isVocalMember) {";

// Actually, I'll just write a script that does preg_replace or similar.
// But first, let's see if I can just use str_replace for common patterns.

file_put_contents($filePath, $content);
echo "Updated SoundEngineerController authorization.";
