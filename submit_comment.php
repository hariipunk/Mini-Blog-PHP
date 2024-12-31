<?php
session_start();
require 'database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $article_id = (int)$_POST['article_id'];
    $slug = $_POST['slug'];
    $comment_text = $_POST['comment_text'];

    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];
    } else {
        $user_id = 0;
        $username = 'Anonymous';
    }

    $stmt = $conn->prepare("INSERT INTO comments (article_id, user_id, username, comment_text, created_at) 
                            VALUES (:article_id, :user_id, :username, :comment_text, NOW())");
    $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':comment_text', $comment_text, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $conn->prepare("SELECT user_id FROM articles WHERE id = :article_id");
    $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
    $stmt->execute();
    $article_owner_id = $stmt->fetchColumn();

    if ($article_owner_id && $article_owner_id != $user_id) {
        $message = "komentar";
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, article_id, message) 
                                VALUES (:user_id, :article_id, :message)");
        $stmt->bindParam(':user_id', $article_owner_id, PDO::PARAM_INT);
        $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->execute();
    }

    header("Location: /$slug");
    exit;
}
?>