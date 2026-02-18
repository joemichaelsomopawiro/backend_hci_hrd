<?php
$json = json_decode(file_get_contents('Postman_Collection_HCI_HRD_Complete_Flow.json'), true);

function searchPromosi($items, $path = '') {
    foreach ($items as $item) {
        $currentPath = $path . '/' . $item['name'];
        
        // If it's a folder (has 'item')
        if (isset($item['item'])) {
            // Check if this folder itself is relevant
            if (stripos($item['name'], 'Promosi') !== false || stripos($item['name'], 'Promotion') !== false) {
                echo "FOUND FOLDER: $currentPath\n";
                printRequests($item['item'], $currentPath);
            } else {
                // Determine if we should dive deeper
                // Dive deeper generally, but maybe optimization? No, just dive.
                searchPromosi($item['item'], $currentPath);
            }
        } 
        // If it's a request (has 'request')
        else if (isset($item['request'])) {
            // Check if request is relevant (keywords in name or url)
            $url = is_string($item['request']['url']) ? $item['request']['url'] : ($item['request']['url']['raw'] ?? '');
            
            if (stripos($item['name'], 'Promosi') !== false || stripos($item['name'], 'Promotion') !== false || stripos($url, 'promosi') !== false) {
                printRequestDetails($item, $currentPath);
            }
        }
    }
}

function printRequests($items, $path) {
    foreach ($items as $item) {
        $currentPath = $path . '/' . $item['name'];
        if (isset($item['item'])) {
            printRequests($item['item'], $currentPath);
        } else if (isset($item['request'])) {
            printRequestDetails($item, $currentPath);
        }
    }
}

function printRequestDetails($item, $path) {
    echo "--------------------------------------------------------------------------------\n";
    echo "PATH: $path\n";
    echo "NAME: " . $item['name'] . "\n";
    echo "METHOD: " . ($item['request']['method'] ?? 'GET') . "\n";
    
    $url = $item['request']['url'];
    if (is_array($url)) {
        echo "URL: " . ($url['raw'] ?? '') . "\n";
    } else {
        echo "URL: $url\n";
    }
    
    // Headers
    echo "HEADERS:\n";
    if (isset($item['request']['header'])) {
        foreach ($item['request']['header'] as $h) {
            echo "  - " . $h['key'] . ": " . $h['value'] . "\n";
        }
    }
    
    // Body
    echo "BODY MODE: " . ($item['request']['body']['mode'] ?? 'none') . "\n";
    if (isset($item['request']['body']['raw'])) {
        echo "BODY RAW:\n" . $item['request']['body']['raw'] . "\n";
    } elseif (isset($item['request']['body']['formdata'])) {
        echo "BODY FORMDATA:\n";
        foreach ($item['request']['body']['formdata'] as $fd) {
            echo "  - " . $fd['key'] . " (" . ($fd['type'] ?? 'text') . "): " . ($fd['value'] ?? '') . "\n";
        }
    }
    echo "\n";
}

searchPromosi($json['item']);
