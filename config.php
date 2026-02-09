<?php
// session_start(); // REMOVED - sessions should start in main files only

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'mealplan_system';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>