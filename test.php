<?php 

require_once 'Database.php';

try {
    $conn = Database::getConnection();
    echo "Connected successfully\n";
} catch (RuntimeException $e) {
    echo "Connection failed: " . $e->getMessage();
}