<?php



$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'noor_handmade_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$charset = 'utf8mb4';         



$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";


$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,      
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,            
    PDO::ATTR_EMULATE_PREPARES   => false,
];


try {
    
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    
    
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
