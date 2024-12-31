<?php
session_start();
require_once "database.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['article_id'], $_POST['reporter_name'], $_POST['reason'])) {
    $articleId = $_POST['article_id'];
    $reporterName = $_POST['reporter_name'];
    $reason = $_POST['reason'];

    $stmt = $conn->prepare("SELECT slug FROM articles WHERE id = :id");
    $stmt->bindParam(':id', $articleId);
    $stmt->execute();
    $article = $stmt->fetch();

    if ($article) {
        $slug = $article['slug'];

        $stmt = $conn->prepare("INSERT INTO reports (article_id, reporter_name, reason) VALUES (:article_id, :reporter_name, :reason)");
        $stmt->bindParam(':article_id', $articleId);
        $stmt->bindParam(':reporter_name', $reporterName);
        $stmt->bindParam(':reason', $reason);
        $stmt->execute();

        header("Location: /$slug");
        exit;
    } else {
        header("Location: /error.php?message=Artikel tidak ditemukan");
        exit;
    }
}
?>