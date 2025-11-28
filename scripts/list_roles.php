<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$db = $_ENV['DB_DATABASE'] ?? 'tandil';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query('SELECT id, name, guard_name FROM roles LIMIT 50');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!count($rows)) {
        echo "No roles found\n";
        exit;
    }
    foreach ($rows as $r) {
        echo $r['id'] . ' | ' . $r['name'] . ' | ' . $r['guard_name'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
