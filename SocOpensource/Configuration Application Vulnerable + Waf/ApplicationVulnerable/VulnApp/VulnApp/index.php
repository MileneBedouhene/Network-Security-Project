<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        if ($_POST['action'] == 'login') {
            // LOGIN FUNCTIONALITY
            // VULNERABILITY 1: SQL Injection
            // No input sanitization, allowing queries like: ' OR '1'='1
            $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                $_SESSION['username'] = $username;
                header("Location: home.php");
            } else {
                $error_message = "Invalid credentials!";
            }
        } elseif ($_POST['action'] == 'register') {
            // REGISTRATION FUNCTIONALITY
            
            // Check if username already exists
            $check_sql = "SELECT * FROM users WHERE username = '$username'";
            $check_result = $conn->query($check_sql);
            
            if ($check_result->num_rows > 0) {
                $error_message = "Username already exists!";
            } else {
                // Insert new user
                // VULNERABILITY 2: SQL Injection in registration
                // VULNERABILITY 3: Password stored in plain text
                $insert_sql = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
                
                if ($conn->query($insert_sql) === TRUE) {
                    $success_message = "Registration successful! You can now login.";
                } else {
                    $error_message = "Error: " . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login & Register</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            display: none;
        }
        .form-container.active {
            display: block;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        .tab {
            flex: 1;
            padding: 10px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            cursor: pointer;
            text-align: center;
        }
        .tab.active {
            background: #007bff;
            color: white;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="showForm('login')">Login</div>
            <div class="tab" onclick="showForm('register')">Register</div>
        </div>
        
        <!-- Login Form -->
        <div id="login-form" class="form-container active">
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="text" name="username" placeholder="Username" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <input type="submit" value="Login">
            </form>
        </div>
        
        <!-- Registration Form -->
        <div id="register-form" class="form-container">
            <form method="post">
                <input type="hidden" name="action" value="register">
                <input type="text" name="username" placeholder="Username" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <input type="submit" value="Register">
            </form>
        </div>
    </div>
    
    <script>
        function showForm(formType) {
            // Hide all forms
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.remove('active');
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected form and activate tab
            document.getElementById(formType + '-form').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
