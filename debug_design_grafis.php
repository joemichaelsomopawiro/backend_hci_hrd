<?php
$work = \App\Models\PrDesignGrafisWork::with(['productionWork', 'promotionWork'])->whereHas('promotionWork')->orderBy('id', 'desc')->first();

if ($work) {
    echo "Work ID: " . $work->id . "\n";
    if ($work->promotionWork) {
        $files = $work->promotionWork->file_paths;
        echo "Original file_paths (raw): " . var_export($files, true) . "\n";

        // Apply logic
        if (!empty($files)) {
            if (is_string($files)) {
                $decoded = json_decode($files, true);
                $files = is_array($decoded) ? $decoded : [$files];
            }
            $work->promotionWork->file_talent_photo = is_array($files) ? ($files[0] ?? null) : $files;
            echo "Mapped file_talent_photo: " . $work->promotionWork->file_talent_photo . "\n";

            // Check serialization
            $array = $work->promotionWork->toArray();
            echo "Serialized keys: " . implode(', ', array_keys($array)) . "\n";

            if (array_key_exists('file_talent_photo', $array)) {
                echo "SUCCESS: Present in array.\n";
            } else {
                echo "FAILURE: Not in array.\n";
            }
        } else {
            echo "file_paths is empty.\n";
        }
    }
} else {
    echo "No work with promotion work found.\n";
}
