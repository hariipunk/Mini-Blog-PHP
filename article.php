<?php
session_start();
require 'database.php';

if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    
    $stmt = $conn->prepare("SELECT articles.*, users.full_name, users.bio, users.profile_picture, articles.category 
                            FROM articles 
                            JOIN users ON articles.user_id = users.id 
                            WHERE articles.slug = :slug");
    $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        echo "Artikel tidak ditemukan.";
        exit;
    }

    $article_id = $article['id'];
} else {
    echo "Slug tidak ada dalam URL.";
    exit;
}

$visitor_ip = $_SERVER['REMOTE_ADDR'];

$stmt = $conn->prepare("SELECT * FROM article_views WHERE article_id = :article_id AND ip_address = :ip_address");
$stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
$stmt->bindParam(':ip_address', $visitor_ip, PDO::PARAM_STR);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    $stmt = $conn->prepare("INSERT INTO article_views (article_id, ip_address) VALUES (:article_id, :ip_address)");
    $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
    $stmt->bindParam(':ip_address', $visitor_ip, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = :article_id");
    $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
    $stmt->execute();
}

$stmt = $conn->prepare("SELECT name FROM categories WHERE name = :category");
$stmt->bindParam(':category', $article['category'], PDO::PARAM_STR);
$stmt->execute();
$category = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT name FROM categories");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT comments.*, users.profile_picture 
                        FROM comments 
                        LEFT JOIN users ON comments.user_id = users.id 
                        WHERE comments.article_id = :article_id 
                        ORDER BY comments.created_at DESC");
$stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($article['is_premium'] && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT points FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $conn->prepare("SELECT * FROM articles 
                        WHERE category = :category AND id != :article_id
                        ORDER BY RAND() LIMIT 5");
$stmt->bindParam(':category', $article['category'], PDO::PARAM_STR);
$stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
$stmt->execute();
$related_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$limit = 5;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT comments.*, users.profile_picture 
                        FROM comments 
                        LEFT JOIN users ON comments.user_id = users.id 
                        WHERE comments.article_id = :article_id 
                        ORDER BY comments.created_at DESC 
                        LIMIT :limit OFFSET :offset");
$stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE article_id = :article_id");
$stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
$stmt->execute();
$total_comments = $stmt->fetchColumn();

$total_pages = ceil($total_comments / $limit);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?></title>
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
      <?php if (is_array($categories)): ?>
    <?php foreach ($categories as $category): ?>
        <a href="kategori.php?kategori=<?php echo urlencode($category['name']); ?>">
            <?php echo htmlspecialchars($category['name']); ?>
        </a>
    <?php endforeach; ?>
    <?php else: ?>
        <p>Belum ada kategori ditemukan.</p>
    <?php endif; ?>

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
    <h1><?php echo htmlspecialchars($article['title']); ?></h1>
    <div class="author-profile">
        <div class="bio-column">
            <p><strong>Kategori :</strong> <?php echo htmlspecialchars($article['category']); ?></p>
            <p><strong>Tanggal Publikasi :</strong> <?php echo date("d M Y", strtotime($article['created_at'])); ?></p>
            <p><strong>Dilihat :</strong> <?php echo htmlspecialchars($article['view_count']); ?> kali</p>
        </div>
    </div>
        <div class="container-articles">
    <div class="content">
          <img src="<?php echo htmlspecialchars($article['thumbnail']); ?>" alt="Thumbnail" class="thumbnail-article-konten">
<?php

if ($article['is_premium']) {
    if (isset($_SESSION['user_id'])) {
        if ($_SESSION['user_id'] == $article['user_id']) {
            echo nl2br($article['content']);
        } else {
            $stmt = $conn->prepare("SELECT points FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $required_points = $article['premium_points'];

            if ($user['points'] >= $required_points) {
                echo nl2br($article['content']);
                $stmt = $conn->prepare("UPDATE users SET points = points - :required_points WHERE id = :user_id");
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':required_points', $required_points, PDO::PARAM_INT);
                $stmt->execute();
                
                $stmt = $conn->prepare("INSERT INTO points_history (user_id, article_id, points_changed, change_type) 
                                        VALUES (:user_id, :article_id, :points_changed, 'deducted')");
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
                $stmt->bindParam(':points_changed', $required_points, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($_SESSION['user_id'] != $article['user_id']) {
                    $stmt = $conn->prepare("UPDATE users SET points = points + :points WHERE id = :owner_id");
                    $stmt->bindParam(':points', $article['premium_points'], PDO::PARAM_INT);
                    $stmt->bindParam(':owner_id', $article['user_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $stmt = $conn->prepare("INSERT INTO points_history (user_id, article_id, points_changed, change_type) 
                                            VALUES (:user_id, :article_id, :points_changed, 'added')");
                    $stmt->bindParam(':user_id', $article['user_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
                    $stmt->bindParam(':points_changed', $article['premium_points'], PDO::PARAM_INT);
                    $stmt->execute();
                }
            } else {
                $content_preview = nl2br(substr($article['content'], 0, 500)); 
                echo $content_preview;
                echo "<center><p><button class='button-modal-popup' onclick=\"window.location.href='index.php'\">Anda tidak memiliki cukup poin untuk membaca artikel ini.</button></p></center>";
            }
        }
    } else {
        $content_preview = nl2br(substr($article['content'], 0, 500));
        echo $content_preview;
        echo "<center><p><button class='button-modal-popup' onclick=\"window.location.href='login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']) . "'\">Login untuk melanjutkan membaca artikel ini.</button></p></center>";
    }
} else {
    echo nl2br($article['content']);
}

?>
    </div>


        <div class="related-articles">
    <h3>Artikel Terkait</h3>
    <ul>
<?php
if ($related_articles) {
    foreach ($related_articles as $related) {
        echo '<li class="related-article-item">';
        echo '<a href="/' . urlencode($related['slug']) . '">';
        if (!empty($related['thumbnail'])) {
            echo '<center><img src="' . htmlspecialchars($related['thumbnail']) . '" alt="Thumbnail Artikel Terkait" class="related-article-thumbnail"></center>';
        }
        echo '<h4>' . htmlspecialchars($related['title']);
        if ($related['is_premium']) {
            echo ' <span class="badge-premium">Premium</span>';
        }
        echo '</h4>';
        $short_content = substr(strip_tags($related['content']), 0, 100);
        echo '<p>' . htmlspecialchars($short_content) . '...</p>';
        echo '</a>';
        echo '</li>';
    }
} else {
    echo '<li>Tidak ada artikel terkait.</li>';
}
?>
    </ul>
        </div>
        </div>
    <div style="display: flex; justify-content: flex-end;">
    <button class="button-modal-popup" onclick="openReportModal()">Report</button>
    </div>
    <div id="report-modal-popup" class="modal-popup" style="display: none;">
        <div class="modal-popup-content">
            <span class="close-btn-modal-popup" onclick="closeReportModal()">&times;</span>
            <h2>Laporkan Artikel</h2>
            <form action="report_handler.php" method="POST">
                <input type="hidden" name="article_id" value="<?php echo $article_id; ?>">
                <label for="reporter_name">Nama Anda:</label>
                <input type="text" id="reporter_name" name="reporter_name" required>
                <label for="reason">Alasan:</label>
                <textarea id="reason" name="reason" required oninput="countWords()"></textarea>
                <p id="word-count">0/500 kata</p>
                <button type="submit">Kirim Laporan</button>
            </form>
        </div>
    </div>


    <div class="pagination">
        <center><a href="index.php" class="back-link">Kembali ke Beranda</a></center>
    </div>
    <div class="author-profile">
        <div class="profile-column">
            <?php if ($article['profile_picture']): ?>
                <img src="uploads/<?php echo htmlspecialchars($article['profile_picture']); ?>" alt="Foto Profil Penulis" class="profile-picture">
            <?php endif; ?>
        </div>

        <div class="bio-column">
            <h3><?php echo htmlspecialchars($article['full_name']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($article['bio'])); ?></p>
        </div>
    </div>
    <div class="comments-section">
        <h3>Komentar</h3>
        <form class="comment-form" action="submit_comment.php" method="post">
    <input type="hidden" name="article_id" value="<?php echo $article_id; ?>">
    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($article['slug']); ?>">  <!-- Menambahkan slug -->
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($logged_in_user ? $logged_in_user['username'] : 'Anonymous'); ?>">
    <textarea name="comment_text" placeholder="Tulis komentar Anda..." required></textarea>
    <button type="submit">Kirim Komentar</button>
        </form>
<div class="comments-list">
    <?php if ($comments): ?>
        <?php foreach ($comments as $comment): ?>
            <div class="comment">
                <img src="uploads/<?php echo htmlspecialchars($comment['profile_picture'] ?: 'anonymous.png'); ?>" alt="Foto Profil" class="comment-avatar">
                <div class="comment-content">
                    <p class="comment-username"><?php echo htmlspecialchars($comment['username']); ?></p>
                    <p class="comment-text"><?php echo htmlspecialchars($comment['comment_text']); ?></p>
                    <p class="comment-time"><?php echo date("d M Y H:i", strtotime($comment['created_at'])); ?></p>
                    <div class="comment-action-buttons">
                        <?php
                        $is_comment_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id'];
                        $is_article_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $article['user_id'];
                        ?>
                        <?php if ($is_comment_owner): ?>
                            <button class="comment-action-buttons edit-btn" onclick="openEditPopup(<?php echo $comment['id']; ?>, '<?php echo htmlspecialchars($comment['comment_text']); ?>')">Edit</button>
                        <?php endif; ?>
                        <?php if ($is_comment_owner || $is_article_owner): ?>
                            <button class="comment-action-buttons delete-btn" onclick="openDeletePopup(<?php echo $comment['id']; ?>)">Hapus</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Belum ada komentar.</p>
    <?php endif; ?>
</div>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?slug=<?php echo htmlspecialchars($slug); ?>&page=<?php echo $page - 1; ?>" class="prev-page">Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?slug=<?php echo htmlspecialchars($slug); ?>&page=<?php echo $i; ?>" class="page-number <?php echo ($i == $page) ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
        <a href="?slug=<?php echo htmlspecialchars($slug); ?>&page=<?php echo $page + 1; ?>" class="next-page">Next</a>
    <?php endif; ?>
</div>

    </div>
</div>

<footer class="footer">
    <p>&copy; <?php echo date("Y"); ?> Berita. All Rights Reserved.</p>
</footer>
<div id="editPopup" class="overlay-popup-comment">
    <div class="popup-comment">
        <span class="close-btn-comment" onclick="closeEditPopup()">×</span>
        <h3>Edit Komentar</h3>
        <form class="comment-form" id="editForm">
            <textarea id="editCommentText" name="comment_text" required></textarea>
            <input type="hidden" id="commentId" name="comment_id">
            <button type="submit">Simpan</button>
        </form>
        <p id="editNotification"></p>
    </div>
</div>
<div id="deletePopup" class="overlay-popup-comment">
    <div class="popup-comment">
        <span class="close-btn-comment" onclick="closeDeletePopup()">×</span>
        <h3>Apakah Anda yakin ingin menghapus komentar ini?</h3>
        <button id="confirmDeleteBtn" class="popup-button delete-btn">Ya, Hapus</button>
        <button onclick="closeDeletePopup()" class="popup-button save-btn">Batal</button>
    </div>
</div>

<script>
function countWords() {
    const textarea = document.getElementById('reason');
    const wordCountDisplay = document.getElementById('word-count');
    const inputText = textarea.value.trim();
    const charCount = inputText.length;
    wordCountDisplay.innerText = `${charCount}/500 karakter`;

    if (charCount > 500) {
        textarea.value = inputText.substring(0, 500);  
        wordCountDisplay.innerText = "500/500 karakter"; 
    }
}

function openEditPopup(commentId, commentText) {
    document.getElementById('commentId').value = commentId;
    document.getElementById('editCommentText').value = commentText;
    document.getElementById('editPopup').style.display = 'block';
}

function closeEditPopup() {
    document.getElementById('editPopup').style.display = 'none';
}

function openDeletePopup(commentId) {
    document.getElementById('deletePopup').style.display = 'block';
    document.getElementById('confirmDeleteBtn').onclick = function() {
        deleteComment(commentId);
    };
}

function closeDeletePopup() {
    document.getElementById('deletePopup').style.display = 'none';
}

document.getElementById('editForm').onsubmit = function(e) {
    e.preventDefault();
    const commentId = document.getElementById('commentId').value;
    const commentText = document.getElementById('editCommentText').value;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'edit_comment.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            const notification = document.getElementById('editNotification');
            if (response.success) {
                notification.textContent = 'Komentar berhasil diperbarui!';
                notification.style.color = 'green';
                setTimeout(() => closeEditPopup(), 2000);
            } else {
                notification.textContent = response.message;
                notification.style.color = 'red';
            }
        }
    };
    xhr.send('comment_id=' + commentId + '&comment_text=' + encodeURIComponent(commentText));
};

function deleteComment(commentId) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'delete_comment.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                location.reload(); 
            } else {
                alert(response.message);
            }
        }
    };
    xhr.send('comment_id=' + commentId);
}
</script>

</body>
<script>
function openReportModal() {
    console.log("Open modal called");
    document.getElementById('report-modal-popup').style.display = 'flex';
}

function closeReportModal() {
    document.getElementById('report-modal-popup').style.display = 'none';
}
</script>
</html>