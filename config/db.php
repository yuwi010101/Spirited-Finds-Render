<?php
// Compatible with local XAMPP and remote MySQL on Render.
// Set credentials in Render Environment, never in GitHub.
$remoteRequired = getenv('DB_REQUIRE_REMOTE') === '1';
$hostValue = getenv('DB_HOST');
$userValue = getenv('DB_USER');
$passValue = getenv('DB_PASSWORD');
$nameValue = getenv('DB_NAME');

if ($remoteRequired && (
    $hostValue === false || trim($hostValue) === '' || strtolower(trim($hostValue)) === 'localhost'
    || $userValue === false || trim($userValue) === ''
    || $passValue === false
    || $nameValue === false || trim($nameValue) === ''
)) {
    error_log('Render deployment is missing remote MySQL credentials.');
    http_response_code(503);
    exit('Database is not configured. Set DB_HOST, DB_NAME, DB_USER and DB_PASSWORD in Render.');
}

$host = ($hostValue !== false && $hostValue !== '') ? $hostValue : 'localhost';
$user = ($userValue !== false && $userValue !== '') ? $userValue : 'root';
$password = ($passValue !== false) ? $passValue : '';
$dbname = ($nameValue !== false && $nameValue !== '') ? $nameValue : 'spirited_finds_db';
$port = filter_var(getenv('DB_PORT') ?: '3306', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535]
]);

if ($port === false) {
    http_response_code(500);
    exit('Invalid database port configuration.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $user, $password, $dbname, $port);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Could not connect to the database. Check your Render database settings.');
}
