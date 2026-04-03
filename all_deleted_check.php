<?php
$host = '127.0.0.1';
$db   = 'hci';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "--- ALL SOFT-DELETED EMPLOYEES ---\n";
$result = $mysqli->query("SELECT id, nama_lengkap, deleted_at FROM employees WHERE deleted_at IS NOT NULL");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Nama: " . $row['nama_lengkap'] . " | Deleted At: " . $row['deleted_at'] . "\n";
    }
} else {
    echo "No soft-deleted employees found.\n";
}

$mysqli->close();
