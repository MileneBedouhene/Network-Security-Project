<?php
session_start();
session_destroy();
header("Location: index.php");
exit(); // Good practice to add exit() after redirect
?>
