<?php
session_start();
require_once "database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id'])) {
    $report_id = $_GET['id'];

    $stmt = $conn->prepare("SELECT r.article_id, a.title, a.user_id FROM reports r JOIN articles a ON r.article_id = a.id WHERE r.id = :id");
    $stmt->bindParam(':id', $report_id, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        $article_id = $report['article_id'];
        $article_title = $report['title'];
        $owner_id = $report['user_id'];

        $stmt_delete_report = $conn->prepare("DELETE FROM reports WHERE id = :id");
        $stmt_delete_report->bindParam(':id', $report_id, PDO::PARAM_INT);
        $stmt_delete_report->execute();

        $stmt_delete_article = $conn->prepare("DELETE FROM articles WHERE id = :id");
        $stmt_delete_article->bindParam(':id', $article_id, PDO::PARAM_INT);
        $stmt_delete_article->execute();
        $message = "Artikel Anda \"$article_title\" telah dihapus karena melanggar kebijakan.";
        $stmt_notify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $stmt_notify->bindParam(':user_id', $owner_id, PDO::PARAM_INT);
        $stmt_notify->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt_notify->execute();
        header("Location: report.php?success=1");
        exit;
    } else {
        echo "Laporan tidak ditemukan.";
    }
}
?>
