<?php
require_once "database.php";

if (isset($_POST['user_id'])) {
    $userId = $_POST['user_id'];
    
    $stmt = $conn->prepare("SELECT is_banned FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $isBanned = $stmt->fetch(PDO::FETCH_ASSOC)['is_banned'];

    $newStatus = $isBanned ? 0 : 1;
    $stmt = $conn->prepare("UPDATE users SET is_banned = :newStatus WHERE id = :id");
    $stmt->bindParam(':newStatus', $newStatus);
    $stmt->bindParam(':id', $userId);
    $stmt->execute();

    echo $newStatus ? 'banned' : 'unbanned';

    header('Location: index.php');
    exit();
}
?>