<?php
$servername = "localhost";
$username = "vulnuser";
$password = "vulnpass";
$dbname = "vulnapp";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
