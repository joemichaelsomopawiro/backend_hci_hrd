<?php
$conn = new mysqli('localhost', 'root', '', 'hci_hrd_db');
echo "=== editor_promosi_works columns ===\n";
$result = $conn->query('SHOW COLUMNS FROM editor_promosi_works');
while ($row = $result->fetch_assoc())
    echo $row['Field'] . ' | ' . $row['Type'] . ' | NULL:' . $row['Null'] . ' | Default:' . $row['Default'] . "\n";

echo "\n=== pr_editor_works columns ===\n";
$result2 = $conn->query('SHOW COLUMNS FROM pr_editor_works');
while ($row = $result2->fetch_assoc())
    echo $row['Field'] . ' | ' . $row['Type'] . ' | NULL:' . $row['Null'] . ' | Default:' . $row['Default'] . "\n";
