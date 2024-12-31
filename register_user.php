<?php
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = "Semua kolom harus diisi!";
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                $error = "Username sudah terdaftar!";
            } else {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $password, $role])) {
                    $success = "Pengguna berhasil didaftarkan!";
                } else {
                    $error = "Terjadi kesalahan saat mendaftarkan pengguna!";
                }
            }
        } catch (PDOException $e) {
            $error = "Error database: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Pendaftaran Pengguna</title>
    <link rel="stylesheet" href="stylereadmin.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <a href="admin.php" class="navbar-logo">Registrasi</a></a>
            <ul class="navbar-menu">
                <li><a href="admin.php">Admin Panel</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <h1>Daftar Pengguna Baru</h1>

        <?php if (isset($error)): ?>
            <div class="alert-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="success-msg"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="register_user.php" method="POST">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>

            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>

            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>

            <label for="role">Role:</label>
            <select name="role" id="role" required>
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>

            <button type="submit">Daftarkan Pengguna</button>
        </form>
    </div>

    <div class="footer">
        <p>&copy; 2024 Admin Panel. All Rights Reserved.</p>
    </div>
</body>
</html>