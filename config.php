<?php
// Database configuration for XAMPP default MySQL
// Adjust credentials if your MySQL setup differs.

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'sdaambolokandrinaa'; // Note: matches the DB name in sdaambolokandrina.sql

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
	die('Erreur de connexion MySQL: ' . $mysqli->connect_error);
}

// Ensure UTF-8
$mysqli->set_charset('utf8mb4');
?>


