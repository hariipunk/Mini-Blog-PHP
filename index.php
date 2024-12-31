<?php
session_start();
require 'database.php';

$stmt = $conn->prepare("SELECT name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT * FROM articles 
                        WHERE (scheduled_at IS NULL OR scheduled_at <= NOW()) 
                        AND is_premium = 0
                        ORDER BY created_at DESC 
                        LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_articles = $conn->query("SELECT COUNT(*) FROM articles WHERE (scheduled_at IS NULL OR scheduled_at <= NOW()) AND is_premium = 0")->fetchColumn();
$total_pages = ceil($total_articles / $limit);

$premium_limit = 5;
$premium_page = isset($_GET['premium_page']) ? (int)$_GET['premium_page'] : 1;
$premium_offset = ($premium_page - 1) * $premium_limit;

$premium_stmt = $conn->prepare("SELECT * FROM articles 
                                WHERE is_premium = 1 
                                AND (scheduled_at IS NULL OR scheduled_at <= NOW()) 
                                ORDER BY created_at DESC 
                                LIMIT :limit OFFSET :offset");
$premium_stmt->bindParam(':limit', $premium_limit, PDO::PARAM_INT);
$premium_stmt->bindParam(':offset', $premium_offset, PDO::PARAM_INT);
$premium_stmt->execute();
$premium_articles = $premium_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_premium_articles = $conn->query("SELECT COUNT(*) FROM articles WHERE is_premium = 1 AND (scheduled_at IS NULL OR scheduled_at <= NOW())")->fetchColumn();
$total_premium_pages = ceil($total_premium_articles / $premium_limit);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Terbaru</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <div class="navbar-container">
        <a href="index.php" class="navbar-logo">Berita</a>
        <ul class="navbar-menu">
            <li><a href="<?php echo isset($_SESSION['user_id']) ? 'home.php' : 'index.php'; ?>">
                <?php echo isset($_SESSION['user_id']) ? 'Dashboard' : 'Beranda'; ?>
            </a></li>
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
    <h1>Berita Terbaru</h1>

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

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="index.php?page=<?php echo $page - 1; ?>">Prev</a>
        <?php endif; ?>

        <?php 
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="index.php?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="index.php?page=<?php echo $page + 1; ?>">Next</a>
        <?php endif; ?>
    </div>
</div>

<div class="container premium-section">
    <h1>Konten Premium</h1>

    <?php foreach ($premium_articles as $article): ?>
        <div class="article">
            <div class="thumbnail-container">
                <img src="<?php echo htmlspecialchars($article['thumbnail']); ?>" alt="Thumbnail" class="thumbnail">
            </div>
            <div class="article-content">
                <h2><?php echo htmlspecialchars($article['title']); ?></h2>
                <p><span class="premium-badge">Premium</span></p>
                <p><strong>Poin :</strong> <?php echo $article['premium_points']; ?></p>
                <p><strong>Kategori :</strong> <?php echo htmlspecialchars($article['category']); ?></p>
                <p><?php echo htmlspecialchars($article['snippet']); ?></p>
                <a href="/<?php echo urlencode($article['slug']); ?>">Baca Selengkapnya</a>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="pagination">
        <?php if ($premium_page > 1): ?>
            <a href="index.php?premium_page=<?php echo $premium_page - 1; ?>">Prev</a>
        <?php endif; ?>

        <?php 
        $start_premium_page = max(1, $premium_page - 2);
        $end_premium_page = min($total_premium_pages, $premium_page + 2);
        for ($i = $start_premium_page; $i <= $end_premium_page; $i++): ?>
            <a href="index.php?premium_page=<?php echo $i; ?>" class="<?php echo $i === $premium_page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($premium_page < $total_premium_pages): ?>
            <a href="index.php?premium_page=<?php echo $premium_page + 1; ?>">Next</a>
        <?php endif; ?>
    </div>
</div>

<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>
</body>
</html>