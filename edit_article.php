<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = "";
$success_message = "";

$max_file_size = 2 * 1024 * 1024;

$stmt = $conn->prepare("SELECT * FROM articles WHERE id = :id AND user_id = :user_id");
$stmt->bindParam(':id', $article_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo "Artikel tidak ditemukan atau Anda tidak memiliki izin untuk mengedit artikel ini.";
    exit;
}

$stmt = $conn->prepare("SELECT DISTINCT category FROM articles");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

function createSlug($title) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    return $slug;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_article'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $premium_points = $is_premium ? (int)$_POST['premium_points'] : 0;

    if ($is_premium && (empty($premium_points) || $premium_points <= 0)) {
        $error_message = "Artikel premium harus memiliki jumlah poin yang valid.";
    }

    if (!$is_premium) {
        $premium_points = 0;
    }

    $thumbnail = $article['thumbnail'];
    $slug = createSlug($title);

    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["thumbnail"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $check = getimagesize($_FILES["thumbnail"]["tmp_name"]);

        if ($check && in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            if ($_FILES['thumbnail']['size'] > $max_file_size) {
                $error_message = "Ukuran file gambar terlalu besar. Maksimum 2MB.";
            } else {
                if (move_uploaded_file($_FILES["thumbnail"]["tmp_name"], $target_file)) {
                    $thumbnail = $target_file;
                } else {
                    $error_message = "Gagal mengunggah gambar.";
                }
            }
        } else {
            $error_message = "Format gambar tidak valid.";
        }
    }

    if (!$error_message) {
        $stmt = $conn->prepare("UPDATE articles SET title = :title, slug = :slug, content = :content, category = :category, thumbnail = :thumbnail, is_premium = :is_premium, premium_points = :premium_points WHERE id = :id AND user_id = :user_id");
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':thumbnail', $thumbnail);
        $stmt->bindParam(':is_premium', $is_premium, PDO::PARAM_INT);
        $stmt->bindParam(':premium_points', $premium_points, PDO::PARAM_INT);
        $stmt->bindParam(':id', $article_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);

        try {
            $stmt->execute();
            $success_message = "Artikel berhasil diperbarui!";
        } catch (PDOException $e) {
            $error_message = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>
	
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Artikel</title>
    <link rel="stylesheet" href="stylehome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<div class="navbar">
    <div class="navbar-container">
        <a href="home.php" class="navbar-logo">Dashboard</a>
        <ul class="navbar-menu">
            <li class="dropdown">
                <a href="javascript:void(0)" class="dropbtn">Profile</a>
                <div class="dropdown-content">
                    <a href="home.php">Home</a>
                    <a href="upload.php">Upload</a>
                    <a href="editprofile.php">Edit Profil</a>
                    <a href="logout.php">Logout</a>
                </div>
            </li>
        </ul>
    </div>
</div>

<div class="container">
    <h1>Edit Artikel</h1>

    <?php if ($error_message): ?>
        <div class="alert-msg-cul"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="success-msg-cul"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="update_article" value="1">
    <input type="text" name="title" placeholder="Judul Artikel" required value="<?php echo htmlspecialchars($article['title']); ?>">
    <input type="file" name="thumbnail" accept="image/*">

    <select name="category" required>
        <option value="">Pilih Kategori</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                    <?php echo ($article['category'] == $cat['category']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['category']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <textarea id="content" name="content" contenteditable="true" class="content-editable" placeholder="Konten Artikel" required><?php echo htmlspecialchars($article['content']); ?></textarea>

    <!-- Checkbox untuk Artikel Premium -->
    <input type="checkbox" name="is_premium" id="is_premium" value="1" <?php echo ($article['is_premium'] == 1) ? 'checked' : ''; ?>>
    <label for="is_premium" class="premium-label">
        <i class="fas fa-check-circle"></i> Artikel Premium
    </label>

    <div id="premium_points_container" style="display: <?php echo ($article['is_premium'] == 1) ? 'block' : 'none'; ?>;">
        <input type="number" name="premium_points" id="premium_points" min="0" value="<?php echo ($article['is_premium'] == 1) ? htmlspecialchars($article['premium_points']) : '0'; ?>" placeholder="Jumlah Poin Premium" <?php echo ($article['is_premium'] == 1) ? 'required' : ''; ?>>
    </div>

    <button type="submit" id="update_button">Perbarui Artikel</button>

</form>

</div>

<script>
    document.getElementById('is_premium').addEventListener('change', function() {
        const pointsContainer = document.getElementById('premium_points_container');
        const premiumPointsInput = document.getElementById('premium_points');

        if (this.checked) {
            pointsContainer.style.display = 'block';
            if (!premiumPointsInput) {
             
                const newInput = document.createElement('input');
                newInput.type = 'number';
                newInput.name = 'premium_points';
                newInput.id = 'premium_points';
                newInput.min = '1';
                newInput.placeholder = 'Jumlah Poin Premium';
                newInput.required = true; 
                pointsContainer.appendChild(newInput); 
            }
        } else {
            pointsContainer.style.display = 'none';
            
            if (premiumPointsInput) {
                premiumPointsInput.remove(); 
            }
        }
    });
    
    document.querySelector('form').addEventListener('submit', function() {
    var content = document.getElementById('content').innerHTML; 
    document.querySelector('input[name="content"]').value = content; 
});

</script>

</body>
<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>
</html>
