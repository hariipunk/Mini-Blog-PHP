<?php
session_start();
require_once "database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = $_POST['user_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($email)) {
        echo "Username dan email tidak boleh kosong.";
        exit;
    }

    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    } else {
        $hashedPassword = null;
    }

    try {
        if ($hashedPassword) {
            $query = $conn->prepare("UPDATE users SET username = :username, email = :email, password = :password WHERE id = :user_id");
            $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        } else {
            $query = $conn->prepare("UPDATE users SET username = :username, email = :email WHERE id = :user_id");
        }

        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        header("Location: admin.php");
        exit;
    } catch (PDOException $e) {
        echo "Terjadi kesalahan: " . $e->getMessage();
        exit;
    }
}
?>