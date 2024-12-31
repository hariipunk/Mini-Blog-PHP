<?php
require_once "database.php";

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId > 0) {
    $query = $conn->prepare("SELECT id, title, LEFT(content, 100) AS excerpt FROM articles WHERE user_id = :user_id");
    $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $query->execute();
    $articles = $query->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($articles); 
}
?>
