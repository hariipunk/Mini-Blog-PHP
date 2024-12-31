<?php
require 'database.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_all') {
    $userId = $_SESSION['user_id']; 
    $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
    $success = $stmt->execute([$userId]);
    
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>
