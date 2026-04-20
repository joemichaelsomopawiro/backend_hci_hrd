<?php
$path = 'c:\laragon\www\backend_hci_hrd\app\Services\KpiService.php';
$lines = file($path);

// We want to remove lines 1772, 1773, 1774, 1775 (0-indexed: 1771-1774)
// Current lines are:
// 1771:                          }
// 1772:                              $deadlineDate = $shootingDate;
// 1773:                          } else if ($episode->air_date) {
// 1774:                              $deadlineDate = $episode->air_date->copy()->subDays(7);
// 1775:                          }

// Look for the specific broken pattern around those lines
$found = false;
for ($i = 1760; $i < 1780; $i++) {
    if (isset($lines[$i]) && strpos($lines[$i], '$deadlineDate = $shootingDate;') !== false && strpos($lines[$i-1], '}') !== false) {
        echo "Found target at line " . ($i + 1) . "\n";
        // Remove the 4 extra lines (1772-1775)
        unset($lines[$i]);
        unset($lines[$i+1]);
        unset($lines[$i+2]);
        unset($lines[$i+3]);
        $found = true;
        break;
    }
}

if ($found) {
    file_put_contents($path, implode('', $lines));
    echo "Syntax Error Fixed.\n";
} else {
    echo "Could not find the syntax error pattern.\n";
    // Check line contents for manual search
    echo "Line 1772: " . $lines[1771];
    echo "Line 1773: " . $lines[1772];
}
