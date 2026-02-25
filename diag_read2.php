<?php
$raw = file_get_contents('diag_output.txt');
$json = json_decode($raw, true);
if ($json && isset($json['columns'])) {
    echo "=== ALL COLUMNS (Field | Type | Null | Default) ===\n";
    foreach ($json['columns'] as $col) {
        $default = $col['Default'] === null ? 'NULL' : $col['Default'];
        echo sprintf(
            "%-30s | %-20s | Null=%-3s | Default=%s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'],
            $default
        );
    }
    echo "\n=== NON-NULLABLE COLUMNS WITH NO DEFAULT ===\n";
    foreach ($json['columns'] as $col) {
        if ($col['Null'] === 'NO' && $col['Default'] === null && strpos(strtolower($col['Extra'] ?? ''), 'auto_increment') === false) {
            echo "  -> " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }
} else {
    echo "Failed to parse JSON: " . substr($raw, 0, 400) . "\n";
}
