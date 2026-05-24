<?php
session_start();
require 'config.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// VULNERABILITY 2: Stored XSS in Comments
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment'])) {
    $comment = $_POST['comment'];
    $username = $_SESSION['username'];
    $sql = "INSERT INTO comments (username, comment) VALUES ('$username', '$comment')";
    $conn->query($sql);
}

// Fetch comments
$result = $conn->query("SELECT * FROM comments");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Welcome, <?php echo $_SESSION['username']; ?>!</h2>
        <a href="tools.php">Go to Tools</a> | <a href="logout.php">Logout</a>
        <h3>Add Comment</h3>
        <form method="post">
            <textarea name="comment" placeholder="Your comment"></textarea><br>
            <input type="submit" value="Post Comment">
        </form>
        <h3>Comments</h3>
        <?php while ($row = $result->fetch_assoc()) { ?>
            <p><strong><?php echo $row['username']; ?>:</strong> <?php echo $row['comment']; ?></p>
        <?php } ?>
    </div>
</body>
</html>
