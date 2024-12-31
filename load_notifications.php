<?php
session_start();
require 'database.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($notifications):
    foreach ($notifications as $notification):
        $article_id = $notification['article_id'];
        
        $stmt_article = $conn->prepare("SELECT title, slug FROM articles WHERE id = :article_id");
        $stmt_article->bindParam(':article_id', $article_id, PDO::PARAM_INT);
        $stmt_article->execute();
        $article = $stmt_article->fetch(PDO::FETCH_ASSOC);

        echo "<div class='notification-item'>";
        echo "<a href='https://yourwebsite.xyz/" . htmlspecialchars($article['slug']) . "' onclick='markNotificationAsRead(" . $notification['id'] . ")'>";
        echo htmlspecialchars($notification['message']) . " <strong>" . htmlspecialchars($article['title']) . "</strong>";
        echo " <small>(" . date("d M Y H:i", strtotime($notification['created_at'])) . ")</small>";
        echo "</a>";
        echo "</div>";
    endforeach;
else:
    echo "<p>Tidak ada notifikasi</p>";
endif;

?>
