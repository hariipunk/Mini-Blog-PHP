<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $conn->prepare("SELECT is_banned FROM users WHERE id = :id");
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$isBanned = $stmt->fetch(PDO::FETCH_ASSOC)['is_banned'];

if ($isBanned) {
    $bannedMessage = "Anda telah dibanned dan tidak dapat membuat artikel.";
}

$stmt = $conn->prepare("SELECT * FROM categories");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['category'])) {
    $category = $_POST['category'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE name = :category");
    $stmt->bindParam(':category', $category);
    $stmt->execute();
    $category_exists = $stmt->fetchColumn();
    
    if (!$category_exists) {
        $error_message = "Kategori yang dipilih tidak valid.";
    }
}

$user_id = $_SESSION['user_id']; 

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set notifikasi
if ($user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $unread_notifications_count = $stmt->fetchColumn();
}

$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['login_notification_shown'])) {
    $_SESSION['login_notification_shown'] = true;
    $show_notification = true;
} else {
    $show_notification = false; 
}

$error_message = "";
$success_message = "";

$max_file_size = 2 * 1024 * 1024; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_article'])) {
    $article_id = $_POST['delete_article'];

    try {
        $stmt = $conn->prepare("DELETE FROM articles WHERE id = :article_id");
        $stmt->bindParam(':article_id', $article_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $success_message = "Artikel berhasil dihapus!";
    } catch (PDOException $e) {
        $error_message = "Terjadi kesalahan: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_article'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $user_id = $_SESSION['user_id'];
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
    $premium_points = isset($_POST['premium_points']) && $_POST['is_premium'] ? intval($_POST['premium_points']) : 0;
   
    $slug = generateSlug($title, $conn);

    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["thumbnail"]["name"]);

        if ($_FILES['thumbnail']['size'] > $max_file_size) {
            $error_message = "Ukuran file gambar terlalu besar. Maksimum 2MB.";
        } else {
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $check = getimagesize($_FILES["thumbnail"]["tmp_name"]);
            if ($check === false) {
                $error_message = "File yang diunggah bukan gambar.";
            } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error_message = "Format gambar yang diizinkan hanya JPG, JPEG, PNG, dan GIF.";
            } elseif (move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $target_file)) {
$stmt = $conn->prepare("INSERT INTO articles (user_id, title, content, thumbnail, category, slug, is_premium, premium_points, view_count, scheduled_at) 
VALUES (:user_id, :title, :content, :thumbnail, :category, :slug, :is_premium, :premium_points, 0, :scheduled_at)");

$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':title', $title);
$stmt->bindParam(':content', $content);
$stmt->bindParam(':thumbnail', $target_file);
$stmt->bindParam(':category', $category);
$stmt->bindParam(':slug', $slug);
$stmt->bindParam(':is_premium', $is_premium, PDO::PARAM_INT);
$stmt->bindParam(':scheduled_at', $scheduled_at, PDO::PARAM_STR); 
$stmt->bindParam(':premium_points', $premium_points, PDO::PARAM_INT);
                try {
                    $stmt->execute();
                    $article_id = $conn->lastInsertId(); 
                    
                    if ($scheduled_at) {
                        $stmt_schedule = $conn->prepare("INSERT INTO schedules (article_id, scheduled_at) VALUES (:article_id, :scheduled_at)");
                        $stmt_schedule->bindParam(':article_id', $article_id);
                        $stmt_schedule->bindParam(':scheduled_at', $scheduled_at);
                        $stmt_schedule->execute();
                    }

                    $success_message = "Artikel berhasil ditambahkan!";
                    header("Location: /" . $slug);
                    exit;
                } catch (PDOException $e) {
                    $error_message = "Terjadi kesalahan: " . $e->getMessage();
                }
            } else {
                $error_message = "Gagal mengunggah gambar.";
            }
        }
    } else {
        $error_message = "Harap unggah gambar untuk thumbnail.";
    }
}

$limit = 5; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare("SELECT * FROM articles 
                        WHERE user_id = :user_id 
                        ORDER BY COALESCE(scheduled_at, created_at) DESC 
                        LIMIT :limit OFFSET :offset");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$user_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) FROM articles WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_articles = $stmt->fetchColumn();
$total_pages = ceil($total_articles / $limit);

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM article_views
    WHERE article_id IN (SELECT id FROM articles WHERE user_id = :user_id)
    AND DATE(CONVERT_TZ(viewed_at, '+00:00', '+07:00')) = CURDATE()
");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_views_today = $stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM article_views
    WHERE article_id IN (SELECT id FROM articles WHERE user_id = :user_id)
    AND YEARWEEK(CONVERT_TZ(viewed_at, '+00:00', '+07:00'), 1) = YEARWEEK(CURDATE(), 1)
");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_views_week = $stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM article_views
    WHERE article_id IN (SELECT id FROM articles WHERE user_id = :user_id)
    AND MONTH(CONVERT_TZ(viewed_at, '+00:00', '+07:00')) = MONTH(CURDATE())
    AND YEAR(CONVERT_TZ(viewed_at, '+00:00', '+07:00')) = YEAR(CURDATE())
");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_views_month = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT SUM(view_count) AS total_views_all_time FROM articles WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_views_all_time = $stmt->fetchColumn();

function generateSlug($title, $conn) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM articles WHERE slug = :slug");
    $uniqueSlug = $slug;
    $counter = 1;
    do {
        $stmt->bindParam(':slug', $uniqueSlug, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $uniqueSlug = $slug . '-' . $counter;
            $counter++;
        }
    } while ($count > 0);

    return $uniqueSlug;
}

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT points FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $points = $user['points'];
} else {
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_all_notifications'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();

        $success_message = "Semua notifikasi berhasil dihapus!";
    } catch (PDOException $e) {
        $error_message = "Terjadi kesalahan saat menghapus notifikasi: " . $e->getMessage();
    }
}

$stmt = $conn->prepare("SELECT * FROM points_history WHERE user_id = :user_id ORDER BY change_date DESC");
$stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$all_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="stylehome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/normalize-whitespace/prism-normalize-whitespace.min.js"></script>
</head>
<body>

<!-- Navbar -->
<div class="navbar">
    <div class="navbar-container">
        <a href="home.php" class="navbar-logo">Dashboard</a>
        <ul class="navbar-menu">
<li class="dropdown">
    <a href="javascript:void(0)" class="dropbtn">Notif
        <?php if ($unread_notifications_count > 0): ?>
            <span class="badge"><?php echo $unread_notifications_count; ?></span>
        <?php endif; ?>
    </a>
    <div class="dropdown-content" id="notificationsDropdown">
    <div id="notificationsList" class="notifications-list"></div>
    <a href="#" id="seeAllNotifications" class="see-all-notifications">See All Notifications</a>
    <form method="POST" action="">
    <button type="submit" name="delete_all_notifications" class="btn-delete-all">Hapus Semua Notifikasi</button>
    </form>
<?php if ($success_message): ?>
    <div class="success-msg"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>
    </div>
</li>
            <li><a href="upload.php">FImage</a></li>
            <li class="dropdown">
                <a href="javascript:void(0)" class="dropbtn">Profile</a>
                <div class="dropdown-content">
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a href="admin.php">Admin</a>
                    <?php endif; ?>
                    <a href="topup_process.php">Topup</a>
                    <a href="withdraw.php">Withdraw</a>
                    <a href="editprofile.php">Edit Profil</a>
                    <a href="logout.php">Logout</a>
                </div>
            </li>
        </ul>
    </div>
</div>

<div class="container">
    <h1>Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>! (ID : <?php echo $_SESSION['user_id']; ?>)</h1>
    <p>
    Poin : <strong><?php echo htmlspecialchars($points); ?></strong> 
    <i class="fas fa-info-circle" id="poinBtn" style="cursor:pointer; color: #007bff;"></i>

        <button class="open-modal-btn" id="openModalBtn">Tampilkan Riwayat Poin</button>
        
<div class="modal-riwayat-point" id="modalRiwayatPoint">
    <div class="modal-content">
        <h3>Riwayat Poin</h3>
        <?php if ($all_history): ?>
            <?php foreach ($all_history as $history): ?>
                <?php
                    $change_type = $history['change_type'] == 'added' ? 'Ditambahkan' : 'Dikurangi';
                    $stmt_article = $conn->prepare("SELECT title FROM articles WHERE id = :article_id");
                    $stmt_article->bindParam(':article_id', $history['article_id'], PDO::PARAM_INT);
                    $stmt_article->execute();
                    $article = $stmt_article->fetch(PDO::FETCH_ASSOC);
                    $article_name = $article ? $article['title'] : 'Artikel tidak ditemukan';
                ?>
                <div class="premium_points_container">
                    <p><strong>Tipe Perubahan:</strong> <?= $change_type ?></p>
                    <p><strong>Poin:</strong> <?= $history['points_changed'] ?> poin</p>
                    <p><strong>Artikel:</strong> <?= $article_name ?></p> 
                    <p><strong>Tanggal Perubahan:</strong> <?= $history['change_date'] ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Tidak ada riwayat poin</p>
        <?php endif; ?>
        <button class="close-modal-btn" id="closeModalBtn">Tutup Modal</button>
        <button class="delete-all-btn" id="deleteAllBtn">Hapus Semua Riwayat</button>
    </div>
</div>

<div class="popup-konfirmasi" id="popupKonfirmasi">
    <div class="popup-content">
        <h3>Apakah Anda yakin ingin menghapus semua riwayat poin?</h3>
        <button id="confirmDeleteBtn">Ya, Hapus</button>
        <button id="cancelDeleteBtn">Tidak, Batalkan</button>
    </div>
</div>

<div id="successModal" class="modal-riwayat-point" style="display: none;">
    <div class="modal-content-success">
        <h3>Riwayat Poin Berhasil Dihapus!</h3>
        <p>Semua riwayat poin telah berhasil dihapus.</p>
        <button id="closeSuccessModal" class="close-modal-btn">Tutup</button>
    </div>
</div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('openModalBtn').onclick = function() {
        document.getElementById('modalRiwayatPoint').style.display = 'flex';
    };
    document.getElementById('closeModalBtn').onclick = function() {
        document.getElementById('modalRiwayatPoint').style.display = 'none';
    };
    window.onclick = function(event) {
        if (event.target === document.getElementById('modalRiwayatPoint')) {
            document.getElementById('modalRiwayatPoint').style.display = 'none';
        }
    };
    document.getElementById('deleteAllBtn').onclick = function() {
        // Tampilkan popup konfirmasi
        document.getElementById('popupKonfirmasi').style.display = 'flex';
    };
    document.getElementById('confirmDeleteBtn').onclick = function() {
        fetch('hapus_riwayat.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'delete_all' }), 
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {           
                document.getElementById('successModal').style.display = 'flex';

                document.getElementById('popupKonfirmasi').style.display = 'none';
                document.getElementById('modalRiwayatPoint').style.display = 'none';
            } else {
                alert('Terjadi kesalahan saat menghapus riwayat.');
            }
        })
        .catch(error => alert('Terjadi kesalahan: ' + error));
    };

    document.getElementById('cancelDeleteBtn').onclick = function() {
        document.getElementById('popupKonfirmasi').style.display = 'none';
    };

    document.getElementById('closeSuccessModal').onclick = function() {
        document.getElementById('successModal').style.display = 'none';
    };
});
    </script>

    <div class="popup-poin" id="popupPoin">
    <h2>Informasi Poin</h2>
    <p>Poin merupakan saldo sementara yang kamu peroleh dari Premium Artikel. Poin didapatkan ketika Pembaca melihat Artikel Premium yang Kamu buat.</p>
    <button id="closePopupBtn">Tutup</button>
    </div>
    
<div id="login-notification" class="notification" style="display: none;">
    Anda berhasil login!
</div>
    <?php if ($error_message): ?>
        <div class="alert-msg-cul" style="display: block;"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="success-msg-cul" style="display: block;"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
<h2>Statistik Total View</h2>
<canvas id="viewsChart" width="400" height="200"></canvas>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('viewsChart').getContext('2d');
    const viewsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Hari Ini', 'Minggu Ini', 'Bulan Ini', 'Sepanjang Masa'],
            datasets: [{
                label: 'Total Views',
                data: [
                    <?php echo $total_views_today ?: 0; ?>,
                    <?php echo $total_views_week ?: 0; ?>,
                    <?php echo $total_views_month ?: 0; ?>,
                    <?php echo $total_views_all_time ?: 0; ?>
                ],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(153, 102, 255, 0.2)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>
    <h2>Buat Artikel Baru</h2>
    <?php if (isset($bannedMessage)): ?>
    <p><?php echo $bannedMessage; ?></p>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="create_article" value="1">
    <input type="text" name="title" placeholder="Judul Artikel" required>
    <input type="file" name="thumbnail" accept="image/*" required>
    <select name="category" required>
        <option value="">Pilih Kategori</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
        <?php endforeach; ?>
    </select>

    <input type="datetime-local" name="scheduled_at">
    
    <textarea name="content" id="content" contenteditable="true" class="content-editable" placeholder="Konten Artikel" required></textarea>
<input type="checkbox" name="is_premium" id="is_premium" value="1" onclick="togglePremiumPoints(this)">
 <label for="is_premium" class="premium-label">
 <i class="fas fa-check-circle"></i> Artikel Premium
 </label>
<div id="premium_points_container" style="display: none;">
    <label for="premium_points">Jumlah Poin :</label>
    <input type="number" name="premium_points" id="premium_points" min="1">
</div>

<script>
    function togglePremiumPoints(checkbox) {
        const pointsContainer = document.getElementById('premium_points_container');
        pointsContainer.style.display = checkbox.checked ? 'block' : 'none';
    }
</script>

    <button type="submit">Tambah Artikel</button>
    </form>
    <?php endif; ?>
    <h2>Artikel Anda</h2>
<?php foreach ($user_articles as $article): ?>
    <div class="article">
    <h3 class="<?php echo ($article['is_premium'] == 1) ? 'premium-title' : ''; ?>">
        <?php echo htmlspecialchars($article['title']); ?>
    </h3>

    <img src="<?php echo htmlspecialchars($article['thumbnail']); ?>" alt="Thumbnail" style="width:150px; height:150px;">
    <strong>Kategori :</strong> <?php echo htmlspecialchars($article['category']); ?><br>
    <i><?php echo htmlspecialchars(substr($article['content'], 0, 100)) . '...'; ?></i><br>
    <div class="views-points">
        <strong>View :</strong> <?php echo $article['view_count']; ?> &nbsp;&nbsp;
        <?php if ($article['is_premium'] == 1): ?>
            <strong>Poin :</strong> <?php echo $article['premium_points']; ?>
        <?php endif; ?>
    </div>
    <?php if ($article['scheduled_at']): ?>
        <?php 
            $current_time = new DateTime();
            $scheduled_time = new DateTime($article['scheduled_at']);
        ?>

        <?php if ($scheduled_time > $current_time): ?>
            <span style="color: green;">Penjadwalan: <?php echo date("d M Y H:i", strtotime($article['scheduled_at'])); ?> </span>
        <?php else: ?>
            <span style="color: blue;">Telah Dipublikasikan</span>
        <?php endif; ?>
    <?php endif; ?>
    	
    <div class="action-buttons">
        <a href="edit_article.php?id=<?php echo $article['id']; ?>" class="edit-button">Edit</a>
        <button type="button" onclick="showDeletePopup(<?php echo $article['id']; ?>)" class="delete-button">Hapus</button>
    </div>
    </div>
<?php endforeach; ?>

 <div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>">Prev</a>
    <?php endif; ?>
    <?php
    $max_page_links = 4; 
    $start_page = max(1, $page - floor($max_page_links / 2));
    $end_page = min($total_pages, $start_page + $max_page_links - 1);
    
    for ($i = $start_page; $i <= $end_page; $i++):
    ?>
        <a href="?page=<?php echo $i; ?>"<?php if ($i == $page) echo ' class="active"'; ?>><?php echo $i; ?></a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?>">Next</a>
    <?php endif; ?>
 </div>
</div>

<div id="popup" class="popup" style="display: none;">
    <h2>Apakah Anda yakin ingin menghapus artikel ini?</h2>
    <form id="delete-form" method="POST">
        <input type="hidden" name="delete_article" id="delete-article-id" value=""><button type="submit">Hapus</button>
        <button type="button" class="cancel-btn" onclick="closePopup()">Batal</button>
    </form>
</div>

<div id="notificationModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Semua Notifikasi</h2>
        <div id="notificationsList">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <small><?php echo htmlspecialchars($notification['created_at']); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Anda tidak memiliki notifikasi.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function() {
    var content = document.getElementById('content').innerHTML; 
    document.querySelector('input[name="content"]').value = content;
});
    window.onload = function() {
        // Menampilkan notifikasi login hanya jika session belum diset
        <?php if ($show_notification): ?>
            document.getElementById("login-notification").style.display = "block";
            setTimeout(function() {
                document.getElementById("login-notification").style.display = "none";
            }, 5000);
        <?php endif; ?>
    };

    function showDeletePopup(articleId) {
        document.getElementById('popup').style.display = 'block';
        document.getElementById('delete-article-id').value = articleId;
    }

    function closePopup() {
        document.getElementById('popup').style.display = 'none';
    }
</script>
<script>
function loadNotifications() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'load_notifications.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            document.getElementById('notificationsList').innerHTML = xhr.responseText;
        }
    };
    xhr.send();
}

function markNotificationAsRead(notificationId) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'mark_notifications_read.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('notification_id=' + notificationId);

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            document.querySelector(`a[onclick="markNotificationAsRead(${notificationId})"]`).closest('.notification-item').remove();
            if (document.querySelectorAll('.notification-item').length === 0) {
                hideBadge();
            }
        }
    };
}

function hideBadge() {
    const badge = document.querySelector('.badge');
    if (badge) {
        badge.style.display = 'none'; 
    }
}

document.querySelector('.dropbtn').addEventListener('click', loadNotifications);

</script>
<script>
document.querySelector('input[name="scheduled_at"]').addEventListener('change', function() {
    const submitButton = document.querySelector('button[type="submit"]');
    if (this.value) {
        submitButton.textContent = 'Jadwalkan Artikel';
    } else {
        submitButton.textContent = 'Tambah Artikel';
    }
});
</script>
<script>
var poinBtn = document.getElementById("poinBtn");
var popupPoin = document.getElementById("popupPoin");
var closePopupBtn = document.getElementById("closePopupBtn");

poinBtn.addEventListener("click", function() {
    popupPoin.style.display = "block"; 
});

closePopupBtn.addEventListener("click", function() {
    popupPoin.style.display = "none";
});

</script>
<script>
document.getElementById('seeAllNotifications').addEventListener('click', function() {
    document.getElementById('notificationModal').style.display = 'block';
});

document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('notificationModal').style.display = 'none';
});

window.onclick = function(event) {
    if (event.target == document.getElementById('notificationModal')) {
        document.getElementById('notificationModal').style.display = 'none';
    }
}
</script>

</body>
<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>

</html>