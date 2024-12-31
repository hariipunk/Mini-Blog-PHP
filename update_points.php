<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    echo "0";
    exit;
}

$user_id = $_SESSION['user_id'];
$amount = isset($_POST['amount']) ? (int)$_POST['amount'] : 0;

$stmt = $conn->prepare("SELECT points FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($amount <= $user['points']) {
    $new_points = $user['points'] - $amount;

    $stmt = $conn->prepare("UPDATE users SET points = :new_points WHERE id = :user_id");
    $stmt->bindParam(':new_points', $new_points, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    echo number_format($new_points);
} else {
    echo number_format($user['points']);
}
?>