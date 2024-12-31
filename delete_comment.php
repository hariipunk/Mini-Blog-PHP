<?php
session_start();
require 'database.php';

if (!isset($_POST['comment_id']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$comment_id = $_POST['comment_id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT comments.user_id AS comment_user_id, articles.user_id AS article_user_id 
                        FROM comments 
                        JOIN articles ON comments.article_id = articles.id 
                        WHERE comments.id = :comment_id");
$stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    if ($result['comment_user_id'] == $user_id || 
       ($result['comment_user_id'] == 0 && $result['article_user_id'] == $user_id)) {
        $stmt = $conn->prepare("DELETE FROM comments WHERE id = :comment_id");
        $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus komentar ini']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Komentar tidak ditemukan']);
}
?>
