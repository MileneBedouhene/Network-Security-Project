# Application Setup

##  1. System Update

```bash
sudo apt update
sudo apt upgrade -y
```

---

##  2. Install Required Packages

```bash
sudo apt install apache2 php libapache2-mod-php php-mysql mariadb-server -y
```

---

##  3. Manage Services

### Start services
```bash
sudo systemctl start apache2
sudo systemctl start mariadb
```

### Enable services at boot
```bash
sudo systemctl enable apache2
sudo systemctl enable mariadb
```

### Stop services (if needed)
```bash
sudo systemctl stop apache2
sudo systemctl stop mariadb
```

### Disable services
```bash
sudo systemctl disable apache2
sudo systemctl disable mariadb
```

---

##  4. Deploy Application Files

```bash
sudo cp -r your_app_folder/* /var/www/html/
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

---

## 5. Database Configuration

```bash
sudo mysql
```

```sql
CREATE DATABASE vulnapp;

CREATE USER 'vulnuser'@'localhost' IDENTIFIED BY 'vulnpass';

GRANT ALL PRIVILEGES ON vulnapp.* TO 'vulnuser'@'localhost';

FLUSH PRIVILEGES;
```

---

##  6. Create Database Schema

```sql
USE vulnapp;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL
);

INSERT INTO users (username, password)
VALUES 
('admin', 'admin123'),
('user1', 'pass123');

CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  comment TEXT NOT NULL
);

EXIT;
```

---

##  7. Configure Database Connection (PHP)

```php
<?php
$host = "localhost";
$user = "vulnuser";
$pass = "vulnpass";
$db   = "vulnapp";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
```

---

##  8. Access the Application

### On the VM
http://localhost

### From another machine

```bash
ip a
```

Then open:
http://YOUR_IP

---

## 🔥 9. Configure Firewall (Optional)

```bash
sudo ufw allow 80
sudo ufw enable
```

---

