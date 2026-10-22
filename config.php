<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

try {
    $db = new PDO(
        'mysql:host=localhost;port=3307;dbname=sms;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'Database connection failed. Check XAMPP MySQL port and import sql/schema.sql.']));
}
