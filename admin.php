<?php
session_start();
require_once "database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

if (isset($_GET['toggle_ban'])) {
    $userId = $_GET['toggle_ban'];
    
    $stmt = $conn->prepare("SELECT is_banned FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $isBanned = $stmt->fetch(PDO::FETCH_ASSOC)['is_banned'];

    $newStatus = $isBanned ? 0 : 1;
    $stmt = $conn->prepare("UPDATE users SET is_banned = :newStatus WHERE id = :id");
    $stmt->bindParam(':newStatus', $newStatus);
    $stmt->bindParam(':id', $userId);
    $stmt->execute();

    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name']);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE name = :name");
    $stmt->bindParam(':name', $categoryName);
    $stmt->execute();
    $existingCategoryCount = $stmt->fetchColumn();

    if ($existingCategoryCount > 0) {
        $status = 'Kategori sudah ada';
        $statusClass = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (:name)");
        $stmt->bindParam(':name', $categoryName);
        $stmt->execute();

        $status = 'Kategori berhasil ditambahkan';
        $statusClass = 'success';
    }
}

if (isset($_GET['delete_category'])) {
    $categoryId = $_GET['delete_category'];

    $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
    $stmt->bindParam(':id', $categoryId);
    $stmt->execute();

    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_registration'])) {
    $newStatus = $_POST['toggle_registration'] === '1' ? '0' : '1';
    $stmt = $conn->prepare("UPDATE settings SET setting_value = :newStatus WHERE setting_name = 'registration_enabled'");
    $stmt->bindParam(':newStatus', $newStatus);
    $stmt->execute();
}

$registrationStatusQuery = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = 'registration_enabled'");
$registrationStatusQuery->execute();
$registrationStatus = $registrationStatusQuery->fetch(PDO::FETCH_ASSOC)['setting_value'];

$categoriesPerPage = 4;
$categoryPage = isset($_GET['category_page']) ? (int)$_GET['category_page'] : 1;
$categoryStart = ($categoryPage > 1) ? ($categoryPage * $categoriesPerPage) - $categoriesPerPage : 0;

$totalCategoriesQuery = $conn->prepare("SELECT COUNT(*) as total FROM categories");
$totalCategoriesQuery->execute();
$totalCategories = $totalCategoriesQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalCategoryPages = ceil($totalCategories / $categoriesPerPage);

$categoryQuery = $conn->prepare("SELECT * FROM categories LIMIT :start, :categoriesPerPage");
$categoryQuery->bindParam(':start', $categoryStart, PDO::PARAM_INT);
$categoryQuery->bindParam(':categoriesPerPage', $categoriesPerPage, PDO::PARAM_INT);
$categoryQuery->execute();
$categories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);

$usersPerPage = 4;
$userPage = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
$userStart = ($userPage > 1) ? ($userPage * $usersPerPage) - $usersPerPage : 0;

$totalUsersQuery = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$totalUsersQuery->execute();
$totalUsers = $totalUsersQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalUserPages = ceil($totalUsers / $usersPerPage);

$query = $conn->prepare("SELECT id, username, email, profile_picture, is_banned FROM users WHERE role = 'user' LIMIT :start, :usersPerPage");
$query->bindParam(':start', $userStart, PDO::PARAM_INT);
$query->bindParam(':usersPerPage', $usersPerPage, PDO::PARAM_INT);
$query->execute();
$users = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="styleadmin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-container">
        <a href="admin.php" class="navbar-logo">Panel</a>
        <ul class="navbar-menu">
            <li class="dropdown">
                <a href="javascript:void(0)" class="dropbtn">Admin</a>
                <div class="dropdown-content">
                    <a href="home.php">Dashboard</a>
                    <a href="editprofile.php">Edit Profil</a>
                    <a href="register_user.php">Registrasi</a>
                    <a href="admin_withdraw.php">Admin Withdraw</a>
                    <a href="admin_topup.php">Admin Topup</a>
                    <a href="report.php">Laporan</a>
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

<!-- Konten Utama -->
<div class="container">
    <h2>Daftar Pengguna</h2>
    <p>Total Pengguna: <?php echo $totalUsers; ?></p>

    <!-- Daftar Pengguna -->
    <div class="user-list">
        <?php foreach ($users as $user): ?>
            <div class="user-card">
                <img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Foto Profil" class="thumbnail">
                <p>
                    <?php echo htmlspecialchars($user['username']); ?>
                    
                </p>
                <div class="button-container">
                    <button class="edit-btn" onclick="openEditPopup(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', <?php echo $user['is_banned']; ?>)">👤</button>
                    <button class="edit-btn" onclick="openArticlePopup(<?php echo $user['id']; ?>)">📝</button>
                    <button class="delete-btn" onclick="confirmDelete(<?php echo $user['id']; ?>)">🗑️</button>
                        
                </div>
            </div>
        <?php endforeach; ?>
    </div>

        <div class="pagination">
    <?php for ($i = 1; $i <= $totalUserPages; $i++): ?>
        <a href="?user_page=<?php echo $i; ?>" class="<?php if ($i == $userPage) echo 'active'; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
        </div>
    
    <div class="add-category">
    <h2>Tambah Kategori</h2>
    <form method="POST">
        <label for="category-name">Nama Kategori:</label>
        <input type="text" id="category-name" name="category_name" required>
        <button type="submit" name="add_category">Tambah Kategori</button>
    </form>
    </div>
<?php if (isset($status)): ?>
    <div class="popup-notification <?php echo $statusClass; ?>" id="category-popup">
        <div class="popup-content">
            <span class="close-btn" onclick="closeCategoryPopup()">×</span>
            <p><?php echo htmlspecialchars($status); ?></p>
        </div>
    </div>
<?php endif; ?>
	
    <div class="category-list">
    <h2>Daftar Kategori</h2>
    <ul>
        <?php foreach ($categories as $category): ?>
            <li>
                <?php echo htmlspecialchars($category['name']); ?>
                <a href="admin.php?delete_category=<?php echo $category['id']; ?>" class="delete-category-btn">Hapus</a>
            </li>
        <?php endforeach; ?>
    </ul>
     <div class="pagination">
    <?php for ($i = 1; $i <= $totalCategoryPages; $i++): ?>
        <a href="?category_page=<?php echo $i; ?>" class="<?php if ($i == $categoryPage) echo 'active'; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
     </div>

    </div>

    <div class="registration-toggle">
    <h2>Pengaturan Registrasi</h2>
    <form method="POST">
        <input type="hidden" name="toggle_registration" value="<?php echo $registrationStatus; ?>">
        <button type="submit">
            <?php echo $registrationStatus == '1' ? 'Nonaktifkan Registrasi' : 'Aktifkan Registrasi'; ?>
        </button>
    </form>
    </div>
   </div>
   
</div>

<!-- Popup Artikel Pengguna -->
<div class="popup" id="article-popup">
    <div class="popup-content">
        <span class="close-btn" onclick="closeArticlePopup()">×</span>
        <center><h2>Artikel Pengguna</h2></center>
        <p id="article-count"></p>
        
        <!-- Slider Kontainer -->
        <div class="slider-container">
            <button class="prev-btn" onclick="changeSlide(-1)">&#10094;</button>

            <div class="slider-content" id="article-slider">
                <!-- Artikel akan dimuat di sini -->
            </div>
             
            <button class="next-btn" onclick="changeSlide(1)">&#10095;</button>
        </div>
    </div>
</div>


<!-- Popup Konfirmasi Hapus -->
<div class="popup" id="delete-popup">
    <h2>Apakah Anda yakin ingin menghapus pengguna ini?</h2>
     <div class="button-container">
    <button id="confirm-delete">Hapus</button>
    <button id="cancel-delete" class="cancel-btn">Batal</button>
     </div>
</div>
<!-- Popup Edit Pengguna -->
<div class="popup" id="edit-popup">
    <h2>Edit Pengguna</h2>
    <form id="edit-form" method="POST" action="edit_user.php">
        <input type="hidden" id="edit-user-id" name="user_id">
        <label for="edit-username">Username:</label>
        <input type="text" id="edit-username" name="username" required>
        <label for="edit-email">Email:</label>
        <input type="email" id="edit-email" name="email" required>
        <label for="edit-password">Password Baru:</label>
        <input type="password" id="edit-password" name="password">
        
        <!-- Tombol Ban/Unban -->
        <button type="button" id="ban-unban-btn" onclick="toggleBanFromPopup()">Ban</button>
        
        <button type="submit">Simpan Perubahan</button>
        <button type="button" class="cancel-btn" onclick="closeEditPopup()">Batal</button>
    </form>
</div>

<!-- Footer -->
<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>
<script>
let currentSlide = 0;
let articles = [];

function openArticlePopup(userId) {
    fetch('get_articles.php?user_id=' + userId)
        .then(response => response.json())
        .then(data => {
            articles = data;
            currentSlide = 0;
            document.getElementById('article-count').textContent = "Jumlah Artikel: " + articles.length;
            renderArticles();
            document.getElementById('article-popup').style.display = 'flex';
        });
}

function closeArticlePopup() {
    document.getElementById('article-popup').style.display = 'none';
}

function renderArticles() {
    const sliderContent = document.getElementById('article-slider');
    sliderContent.innerHTML = '';
    if (articles.length > 0) {
        const article = articles[currentSlide];
        const articleElement = document.createElement('div');
        articleElement.classList.add('article-item');
        articleElement.innerHTML = `
            <h3>${article.title}</h3>
            <p>${article.excerpt}</p>
            <button onclick="deleteArticle(${article.id})">Hapus</button>
        `;
        sliderContent.appendChild(articleElement);
    }
}

function changeSlide(direction) {
    const totalSlides = articles.length;
    currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
    renderArticles();
}

function deleteArticle(articleId) {
    if (confirm('Apakah Anda yakin ingin menghapus artikel ini?')) {
        window.location.href = 'delete_article.php?id=' + articleId;
    }
}

function confirmDelete(userId) {
    deleteUserId = userId;
    document.getElementById('delete-popup').style.display = 'flex';
}

function openEditPopup(userId, username, email, isBanned) {
    document.getElementById('edit-user-id').value = userId;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-email').value = email;

    const banButton = document.getElementById('ban-unban-btn');
    banButton.textContent = isBanned ? 'Unban' : 'Ban';
    
    document.getElementById('edit-popup').style.display = 'flex';
}

function closeEditPopup() {
    document.getElementById('edit-popup').style.display = 'none';
}

document.getElementById('confirm-delete').addEventListener('click', function() {
    if (deleteUserId !== null) {
        window.location.href = "delete_user.php?id=" + deleteUserId;
    }
});

document.getElementById('cancel-delete').addEventListener('click', function() {
    document.getElementById('delete-popup').style.display = 'none';
});

function closeCategoryPopup() {
    document.getElementById('category-popup').style.display = 'none';
}

<?php if (isset($status)): ?>
    document.getElementById('category-popup').style.display = 'flex';
<?php endif; ?>

function toggleBanFromPopup() {
    const userId = document.getElementById('edit-user-id').value;
    
    $.ajax({
        url: 'toggle_ban.php',
        type: 'POST',
        data: { user_id: userId },
        success: function(response) {
            const banButton = document.getElementById('ban-unban-btn');
            if (response === 'banned') {
                banButton.textContent = 'Unban';
            } else if (response === 'unbanned') {
                banButton.textContent = 'Ban';
            }
            window.location.reload();
        }
    });
}
</script>
</body>
</html>
