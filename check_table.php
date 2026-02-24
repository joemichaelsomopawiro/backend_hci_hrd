<?php
try {
    $model = new \App\Models\PrEditorPromosiWork();
    echo "Table: " . $model->getTable() . "\n";
    \App\Models\PrEditorPromosiWork::first();
    echo "Query OK\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

try {
    $modelDesign = new \App\Models\PrDesignGrafisWork();
    echo "Design Table: " . $modelDesign->getTable() . "\n";
} catch (\Exception $e) {
    echo "Design Error: " . $e->getMessage() . "\n";
}
