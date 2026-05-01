<?php
echo "Testing DB connection...\n";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=laravel_media_pembelajaran', 'root', '');
    echo "DB Connected!\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "Users count: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
