<?php
$raw = file_get_contents('diag_output.txt');
$json = json_decode($raw, true);
if ($json && isset($json['columns'])) {
    echo "=== COLUMNS ===\n";
    foreach ($json['columns'] as $col) {
        echo $col['Field'] . ' | ' . $col['Type'] . ' | Null=' . $col['Null'] . ' | Default=' . var_export($col['Default'], true) . "\n";
    }
    echo "\n=== TRIGGERS ===\n";
    foreach ($json['triggers'] as $t) {
        echo print_r($t, true) . "\n";
    }
} else {
    echo "JSON parse failed.\n";
    echo substr($raw, 0, 600) . "\n";
}
