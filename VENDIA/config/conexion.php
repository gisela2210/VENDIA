<?php
// Vendia usa exclusivamente la base de datos `Vendia`.
// No crea ni borra bases automáticamente. Conserva la contraseña MySQL que uses en XAMPP.
$DB_HOST = getenv('VENDIA_DB_HOST') ?: 'localhost';
$DB_NAME = getenv('VENDIA_DB_NAME') ?: 'Vendia';
$DB_USER = getenv('VENDIA_DB_USER') ?: 'root';
$DB_PASS = getenv('VENDIA_DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    error_log('Vendia, error de conexión: '.$e->getMessage());
    http_response_code(503);
    exit('No se pudo conectar a la base Vendia. Verifica config/conexion.php y MySQL en XAMPP.');
}
