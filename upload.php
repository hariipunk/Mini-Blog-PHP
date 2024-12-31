<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$base_dir = "uploads/";
$user_dir = $base_dir . "user_" . $user_id . "/";

if (!file_exists($user_dir)) {
    mkdir($user_dir, 0777, true);
}

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $file_name = basename($_FILES["image"]["name"]);
    $target_file = $user_dir . $file_name;
    $max_file_size = 2 * 1024 * 1024;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    $file_mime_type = mime_content_type($_FILES["image"]["tmp_name"]);
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];

    if ($_FILES['image']['size'] > $max_file_size) {
        $error_message = "Ukuran file gambar terlalu besar. Maksimum 2MB.";
    } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
        $error_message = "Format gambar yang diizinkan hanya JPG, JPEG, PNG, dan GIF.";
    } elseif (!in_array($file_mime_type, $allowed_mime_types)) {
        $error_message = "Jenis file yang diizinkan hanya gambar.";
    } elseif (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        $success_message = "Gambar berhasil diunggah!";
    } else {
        $error_message = "Gagal mengunggah gambar.";
    }
}

if (isset($_POST['delete_image'])) {
    $image_to_delete = $_POST['image_path'];
    if (file_exists($image_to_delete)) {
        unlink($image_to_delete);
        $success_message = "Gambar berhasil dihapus!";
    } else {
        $error_message = "Gambar tidak ditemukan.";
    }
}

$uploaded_images = glob($user_dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);

$images_per_page = 9;
$total_images = count($uploaded_images);
$total_pages = ceil($total_images / $images_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, min($total_pages, $current_page));
$start_index = ($current_page - 1) * $images_per_page;
$paginated_images = array_slice($uploaded_images, $start_index, $images_per_page);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Gambar</title>
    <link rel="stylesheet" href="styleupload.css">
</head>
<body>
<div class="navbar">
    <div class="navbar-container">
        <a href="home.php" class="navbar-logo">Dashboard</a>
        <ul class="navbar-menu">
            <li><a href="upload.php">FImage</a></li>
            <li class="dropdown">
                <a href="javascript:void(0)" class="dropbtn">Profile</a>
                <div class="dropdown-content">
                    <a href="editprofile.php">Edit Profil</a>
                    <a href="logout.php">Logout</a>
                </div>
            </li>
         </ul>
    </div>
</div>

<div class="container">
    <h1>Upload Gambar</h1>

    <?php if ($error_message): ?>
        <div class="alert-msg"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/*" required>
        <button type="submit">Upload Gambar</button>
    </form>

    <h2>Gambar yang Telah Diunggah</h2>
    <div class="image-gallery">
        <?php foreach ($paginated_images as $image): ?>
            <div class="image-item">
                <img src="<?php echo $image; ?>" alt="User Image">
                <p>URL: <input type="text" value="<img src='https://anhar.xyz/<?php echo $image; ?>' class='thumbnail-article'/>" readonly></p>
                <p>
                <button class="delete-btn" onclick="openPopup('<?php echo $image; ?>')">Hapus</button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="popup" id="popup">
        <h2>Apakah Anda yakin ingin menghapus gambar ini?</h2>
        <form method="POST" id="delete-form">
            <input type="hidden" name="image_path" id="image_path">
            <button type="submit" name="delete_image">Ya</button>
            <button type="button" class="cancel-btn" onclick="closePopup()">Tidak</button>
        </form>
    </div>

    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="?page=<?php echo $current_page - 1; ?>">&laquo; Previous</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?php echo $current_page + 1; ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
</div>

<script>
    function openPopup(imagePath) {
        document.getElementById('image_path').value = imagePath;
        document.getElementById('overlay').style.display = 'block';
        document.getElementById('popup').style.display = 'flex';
    }

    function closePopup() {
        document.getElementById('overlay').style.display = 'none';
        document.getElementById('popup').style.display = 'none';
    }
</script>

</body>
<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>
</html>