<?php

require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

/*
Check if database exists
*/
$db_exists = false;

$db_name_escaped = $conn->real_escape_string($DB_NAME);

$res = $conn->query("SHOW DATABASES LIKE '{$db_name_escaped}'");

if ($res && $res->num_rows > 0) {
    $db_exists = true;
    $conn->select_db($DB_NAME);
}

?>