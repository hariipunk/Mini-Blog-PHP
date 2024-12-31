<?php
session_start();
require 'database.php';

$user_id = $_SESSION['user_id'];

if (isset($_POST['notification_id'])) {
    $notification_id = $_POST['notification_id'];

    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = :notification_id AND user_id = :user_id");
    $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
}
?>
