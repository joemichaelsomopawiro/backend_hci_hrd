<?php
$raw = file_get_contents('verify_output.txt');
$json = json_decode($raw, true);
if ($json) {
    echo "JSON Response:\n";
    print_r($json);
} else {
    echo "Raw output:\n";
    echo $raw . "\n";
}
