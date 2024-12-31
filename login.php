<?php
session_start();
require 'database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    if (!empty($_GET['redirect'])) {
        $redirect_url = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
        header("Location: " . $redirect_url);
    } else {
        if ($user['role'] == 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: home.php");
        }
    }
    exit;
    } else {
    $error_message = "Username atau password salah!";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = 'user';
    $points = 1000;

    $user_id = rand(1000, 9999);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, points, id) VALUES (:username, :email, :password, :role, :points, :user_id)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':points', $points);
    $stmt->bindParam(':user_id', $user_id);

    try {
        $stmt->execute();
        $error_message = "Registrasi berhasil! Silakan login.";
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $error_message = "Username atau email sudah terdaftar!";
        } else {
            $error_message = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

$registrationStatusQuery = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'registration_enabled'");
$registrationStatusQuery->execute();
$registrationEnabled = $registrationStatusQuery->fetch(PDO::FETCH_ASSOC)['setting_value'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login dan Registrasi</title>
    <link rel="stylesheet" href="stylelogin.css">
</head>
<body>

<div class="navbar">
    <div class="navbar-container">
        <a href="#" class="navbar-logo">Login</a>
        <ul class="navbar-menu">
            <li><a href="index.php">Beranda</a></li>
        </ul>
    </div>
</div>

<div class="container">
    <div id="login-form">
        <h2>Login</h2>
        <form method="POST">
            <input type="hidden" name="login" value="1">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="switch">
            <?php if ($registrationEnabled == '1'): ?>
                <p>Belum punya akun? <a href="#" onclick="showRegisterForm()">Daftar di sini</a></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($registrationEnabled == '1'): ?>
        <div id="register-form" style="display: none;">
            <h2>Registrasi</h2>
            <form method="POST">
                <input type="hidden" name="register" value="1">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Register</button>
            </form>
            <div class="switch">
                <p>Sudah punya akun? <a href="#" onclick="showLoginForm()">Login di sini</a></p>
            </div>
        </div>
    <?php else: ?>
        <p>Registrasi saat ini tidak tersedia.</p>
    <?php endif; ?>

</div>

<div class="overlay" id="error-overlay" style="display: none;">
    <div class="popup">
        <h2 id="popup-message"><?php echo htmlspecialchars($error_message); ?></h2>
        <button onclick="closePopup()">Tutup</button>
    </div>
</div>

<script>
    function showRegisterForm() {
        document.getElementById('login-form').style.display = 'none';
        document.getElementById('register-form').style.display = 'block';
    }

    function showLoginForm() {
        document.getElementById('register-form').style.display = 'none';
        document.getElementById('login-form').style.display = 'block';
    }

    function closePopup() {
        document.getElementById('error-overlay').style.display = 'none';
    }

    <?php if ($error_message): ?>
    document.getElementById('error-overlay').style.display = 'block';
    <?php endif; ?>
</script>

</body>
<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>
</html>