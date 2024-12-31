<?php
session_start();
require 'database.php'; 

$stmt = $conn->prepare("SELECT name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_GET['kategori']) || empty($_GET['kategori'])) {
    header("Location: index.php");
    exit;
}

$kategori = $_GET['kategori'];

$articlesPerPage = 5;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $articlesPerPage;

$stmt = $conn->prepare("SELECT COUNT(*) FROM articles WHERE category = :kategori");
$stmt->bindParam(':kategori', $kategori, PDO::PARAM_STR);
$stmt->execute();
$totalArticles = $stmt->fetchColumn();

$totalPages = ceil($totalArticles / $articlesPerPage);

$stmt = $conn->prepare("SELECT * FROM articles WHERE category = :kategori ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':kategori', $kategori, PDO::PARAM_STR);
$stmt->bindParam(':limit', $articlesPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel Kategori <?php echo htmlspecialchars($kategori); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <div class="navbar-container">
        <a href="index.php" class="navbar-logo">Berita</a>
        <ul class="navbar-menu">
            <li><a href="index.php">Beranda</a></li>
            <li class="dropdown">
                <a href="javascript:void(0)" class="dropbtn">Kategori</a>
                <div class="dropdown-content">
                    <?php foreach ($categories as $category): ?>
                        <a href="kategori.php?kategori=<?php echo urlencode($category['name']); ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<div class="container">
    <h1>Artikel Kategori: <?php echo htmlspecialchars($kategori); ?></h1>

    <?php if ($articles): ?>
        <?php foreach ($articles as $article): ?>
            <div class="article">
                <div class="thumbnail-container">
                    <img src="<?php echo htmlspecialchars($article['thumbnail']); ?>" alt="Thumbnail" class="thumbnail">
                </div>
                <div class="article-content">
                    <h2><?php echo htmlspecialchars($article['title']); ?></h2>
                    <p><strong>Kategori :</strong> <?php echo htmlspecialchars($article['category']); ?></p>
                    <p><?php echo htmlspecialchars($article['snippet']); ?></p>
                    <a href="/<?php echo urlencode($article['slug']); ?>">Baca Selengkapnya</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Tidak ada artikel dalam kategori ini.</p>
    <?php endif; ?>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="kategori.php?kategori=<?php echo urlencode($kategori); ?>&page=<?php echo $page - 1; ?>">&laquo; Prev</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="kategori.php?kategori=<?php echo urlencode($kategori); ?>&page=<?php echo $i; ?>"<?php if ($i == $page) echo ' class="active"'; ?>>
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="kategori.php?kategori=<?php echo urlencode($kategori); ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
</div>
<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>

</body>
</html>