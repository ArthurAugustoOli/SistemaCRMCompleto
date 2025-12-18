<?php
// Database connection settings
$host = 'localhost';
$dbname = 'u566100020_sistema_erp';
$username = 'u566100020_rooot';
$password = 'Tavin7belo'; // Replace with your actual database password

/*
$username = 'u566100020_rooot';
$password = 'Tavin7belo'; // Replace with your actual database password
*/

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
