<?php
session_start();
require_once "database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id'])) {
    $userId = (int)$_GET['id'];

    try {
    $stmt = $conn->prepare("DELETE FROM user_topup_history WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            header("Location: admin.php?msg=Pengguna berhasil dihapus.");
        } else {
            echo "Terjadi kesalahan. Tidak bisa menghapus pengguna.";
        }
    } catch (PDOException $e) {
        echo "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>
