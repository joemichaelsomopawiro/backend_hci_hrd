<?php
try {
    echo "Starting verification loop...\n";
    $controller = new \App\Http\Controllers\Api\Pr\PrDesignGrafisController();
    $request = new \Illuminate\Http\Request();

    $response = $controller->index($request);
    $data = $response->getData();

    if (isset($data->success) && $data->success) {
        $works = $data->data;
        echo "Retrieved " . count($works) . " works. Checking first 5...\n";

        $count = 0;
        foreach ($works as $work) {
            if ($count >= 5)
                break;
            $count++;

            echo "--------------------------------------------------\n";
            echo "Checking Work ID: " . $work->id . "\n";

            if (isset($work->promotion_work)) {
                echo "Promotion Work ID: " . $work->promotion_work->id . "\n";
                $paths = $work->promotion_work->file_paths ?? null;
                echo "Raw file_paths: " . (is_array($paths) || is_object($paths) ? json_encode($paths) : $paths) . "\n";

                if (isset($work->promotion_work->file_talent_photo)) {
                    echo "SUCCESS: file_talent_photo found: " . ($work->promotion_work->file_talent_photo ?? 'NULL') . "\n";
                } else {
                    echo "FAILURE: file_talent_photo MISSING.\n";
                }
            } else {
                echo "No Promotion Work linked.\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
