<?php
// db.php

$host = getenv('DB_HOST');
$db   = getenv('DB_DATABASE');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

// Establish the database connection.
$conn = new mysqli($host, $user, $pass, $db);

// Check for connection errors. This is more robust than using the @ operator.
if ($conn->connect_error) {
    // In a production environment, you might log this error instead of displaying it.
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set the character set and timezone for the successful connection.
$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = 'America/Chicago'");
?>
