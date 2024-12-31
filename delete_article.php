<?php
require_once "database.php";  
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $articleId = (int)$_GET['id'];

    $query = $conn->prepare("DELETE FROM articles WHERE id = :id");
    $query->bindParam(':id', $articleId, PDO::PARAM_INT);
    
    if ($query->execute()) {
        header("Location: admin.php"); 
        exit;
    } else {
        echo "Terjadi kesalahan saat menghapus artikel.";
    }
} else {
    echo "ID artikel tidak valid.";
}
?>
