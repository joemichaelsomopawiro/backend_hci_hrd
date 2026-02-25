<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\PrQualityControlWork;
use App\Models\PrDesignGrafisWork;
use App\Models\PrEditorPromosiWork;

echo "Fixing Data Inconsistency...\n";

$qcWorks = PrQualityControlWork::orderBy('id', 'desc')->take(20)->get();

$editorKeys = ['video_bts', 'iklan_tv', 'highlight_ig', 'highlight_tv', 'highlight_face', 'bts_video', 'tv_ad', 'ig_highlight', 'tv_highlight', 'fb_highlight'];
$designKeys = ['thumbnail_yt', 'thumbnail_bts', 'youtube_thumbnail', 'bts_thumbnail', 'tumneil_yt', 'tumneil_bts'];

foreach ($qcWorks as $qc) {
    echo "Checking QC Work ID: {$qc->id} (Episode {$qc->pr_episode_id})\n";
    $checklist = $qc->qc_checklist ?? [];

    $needsRevisionDesign = false;
    $needsRevisionEditor = false;
    $designNotes = [];
    $editorNotes = [];

    foreach ($checklist as $key => $item) {
        if (($item['status'] ?? '') === 'revision') {
            $note = $item['note'] ?? '';
            $formattedNote = "[QC Revision: $key] $note";

            if (in_array($key, $designKeys)) {
                $needsRevisionDesign = true;
                $designNotes[] = $formattedNote;
            } elseif (in_array($key, $editorKeys)) {
                $needsRevisionEditor = true;
                $editorNotes[] = $formattedNote;
            }
        }
    }

    if ($needsRevisionDesign) {
        $designWork = PrDesignGrafisWork::where('pr_episode_id', $qc->pr_episode_id)->first();
        if ($designWork && $designWork->status !== 'needs_revision') {
            echo "  -> FIXING Design Work ID {$designWork->id}. Status was '{$designWork->status}'\n";
            $currentNotes = $designWork->notes ? $designWork->notes . "\n" : "";
            // Append new notes only if not already present (checking simple containment)
            $notesToAdd = "";
            foreach ($designNotes as $n) {
                if (!str_contains($currentNotes, $n)) {
                    $notesToAdd .= $n . "\n";
                }
            }

            $designWork->update([
                'status' => 'needs_revision',
                'notes' => $currentNotes . trim($notesToAdd)
            ]);
            echo "  -> Updated status to needs_revision and notes.\n";
        } elseif ($designWork) {
            echo "  -> Design Work Status OK ({$designWork->status})\n";
        }
    }

    if ($needsRevisionEditor) {
        $editorWork = PrEditorPromosiWork::where('pr_episode_id', $qc->pr_episode_id)->first();
        if ($editorWork && $editorWork->status !== 'needs_revision') {
            echo "  -> FIXING Editor Work ID {$editorWork->id}. Status was '{$editorWork->status}'\n";
            $currentNotes = $editorWork->notes ? $editorWork->notes . "\n" : "";
            $notesToAdd = "";
            foreach ($editorNotes as $n) {
                if (!str_contains($currentNotes, $n)) {
                    $notesToAdd .= $n . "\n";
                }
            }

            $editorWork->update([
                'status' => 'needs_revision',
                'notes' => $currentNotes . trim($notesToAdd)
            ]);
            echo "  -> Updated status to needs_revision and notes.\n";
        } elseif ($editorWork) {
            echo "  -> Editor Work Status OK ({$editorWork->status})\n";
        }
    }
}

echo "Done.\n";
