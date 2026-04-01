<?php
$host = '127.0.0.1';
$db   = 'hci';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ATTR_ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::ATTR_DEFAULT_FETCH_MODE_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     echo "--- SQL CHECK FOR EMPLOYEE ID 20 ---\n";
     $stmt = $pdo->prepare("SELECT id, nama_lengkap, deleted_at FROM employees WHERE id = ?");
     $stmt->execute([20]);
     $row = $stmt->fetch();
     
     if ($row) {
         echo "ID: " . $row['id'] . "\n";
         echo "Nama: " . $row['nama_lengkap'] . "\n";
         echo "Deleted At: " . ($row['deleted_at'] ?: 'NULL') . "\n";
     } else {
         echo "Employee ID 20 NOT FOUND in employees table.\n";
     }
     
     echo "\n--- SQL CHECK FOR USER WITH employee_id = 20 ---\n";
     $stmt = $pdo->prepare("SELECT id, name, email, is_active FROM users WHERE employee_id = ?");
     $stmt->execute([20]);
     $row = $stmt->fetch();
     
     if ($row) {
         echo "User ID: " . $row['id'] . "\n";
         echo "Name: " . $row['name'] . "\n";
         echo "Email: " . $row['email'] . "\n";
         echo "Is Active: " . ($row['is_active'] ? 'YES' : 'NO') . "\n";
     } else {
         echo "No user found with employee_id = 20.\n";
     }

} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
