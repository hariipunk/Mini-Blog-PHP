<?php
session_start();
require 'database.php';

if (!isset($_POST['comment_id']) || !isset($_POST['comment_text']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$comment_id = $_POST['comment_id'];
$comment_text = $_POST['comment_text'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = :comment_id");
$stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
$stmt->execute();
$comment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($comment && ($comment['user_id'] == $user_id)) {
    $stmt = $conn->prepare("UPDATE comments SET comment_text = :comment_text WHERE id = :comment_id");
    $stmt->bindParam(':comment_text', $comment_text, PDO::PARAM_STR);
    $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this comment']);
}
?>