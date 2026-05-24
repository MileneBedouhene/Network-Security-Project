<?php
session_start();
require 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// VULNERABILITY 3: Insecure File Upload (Reverse Shell)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['file'])) {
    $uploadDir = "uploads/";
    $uploadFile = $uploadDir . basename($_FILES['file']['name']);
    move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile);
    echo "File uploaded: <a href='$uploadFile'>$uploadFile</a>";
}

// VULNERABILITY 4: Path Traversal
if (isset($_GET['file'])) {
    $file = $_GET['file'];
    $filePath = "uploads/" . $file;
    if (file_exists($filePath)) {
        header('Content-Type: application/octet-stream');
        readfile($filePath);
    } else {
        echo "File not found!";
    }
}

// VULNERABILITY 5: Insecure Direct Object Reference (IDOR)
if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $sql = "SELECT * FROM users WHERE id = $user_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<p>User: " . $user['username'] . ", Password: " . $user['password'] . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tools</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Tools</h2>
        <a href="home.php">Back to Home</a>
        <h3>Download File</h3>
        <form method="get">
            <input type="text" name="file" placeholder="File name">
            <input type="submit" value="Download">
        </form>
        <h3>Upload File</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="file">
            <input type="submit" value="Upload">
        </form>
        <h3>View User Data</h3>
        <form method="get">
            <input type="number" name="user_id" placeholder="User ID">
            <input type="submit" value="View">
        </form>
    </div>
</body>
</html>
