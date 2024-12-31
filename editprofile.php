<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$isAdmin = $user['role'] === 'admin';

function checkChangeLimit($user_id, $change_type, $limit) {
    global $conn;
    $sql = "SELECT COUNT(*) FROM change_history WHERE user_id = :user_id AND change_type = :change_type AND change_date >= CURDATE() - INTERVAL 1 MONTH";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':change_type', $change_type, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    return $count < $limit;
}

$username_disabled = !checkChangeLimit($user_id, 'username', 3);
$email_disabled = !checkChangeLimit($user_id, 'email', 1);

$max_file_size = 2 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $response = [];
    
    $full_name = $_POST['full_name'];
    $phone_number = $_POST['phone_number'];
    $address = $_POST['address'];
    $bio = $_POST['bio'];
    $pin = $_POST['pin'];
    $username = $_POST['username'];
    $email = $_POST['email'];

    $changesMade = false;
    if (isset($_POST['pin']) && !empty($_POST['pin'])) {
    $new_pin = password_hash($_POST['pin'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE users SET pin = :new_pin WHERE id = :user_id");
    $stmt->bindParam(':new_pin', $new_pin);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $changesMade = true;
    }
    if ($isAdmin && isset($_POST['new_password']) && !empty($_POST['new_password'])) {
        $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = :new_password WHERE id = :user_id");
        $stmt->bindParam(':new_password', $new_password);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $changesMade = true;
    }

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $target_dir = "uploads/";
        $profile_picture = basename($_FILES['profile_picture']['name']);
        $target_file = $target_dir . $profile_picture;
        
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            if ($_FILES['profile_picture']['size'] > $max_file_size) {
                $response['status'] = 'error';
                $response['message'] = "Ukuran file terlalu besar. Maksimum 2MB.";
                echo json_encode($response);
                exit;
            } else {
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    if ($user['profile_picture']) {
                        unlink($target_dir . $user['profile_picture']);
                    }
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :user_id");
                    $stmt->bindParam(':profile_picture', $profile_picture);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                    $changesMade = true;
                }
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = "Format file tidak valid. Hanya JPG, JPEG, PNG, dan GIF yang diperbolehkan.";
            echo json_encode($response);
            exit;
        }
    }

    try {
        if ($full_name != $user['full_name'] || $phone_number != $user['phone_number'] || 
            $address != $user['address'] || $bio != $user['bio'] || $username != $user['username'] || $pin != $user['pin'] ||
            (!$email_disabled && $email != $user['email'])) {
            $stmt = $conn->prepare("UPDATE users SET full_name = :full_name, phone_number = :phone_number, address = :address, bio = :bio,  pin = :pin, username = :username" . 
                                   ($email_disabled ? "" : ", email = :email") . 
                                   " WHERE id = :user_id");

            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':bio', $bio);
            $stmt->bindParam(':pin', $pin);
            $stmt->bindParam(':username', $username);
            if (!$email_disabled) {
                $stmt->bindParam(':email', $email);
            }
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            $changesMade = true;
        }

        if ($changesMade) {
            $response['status'] = 'success';
            $response['message'] = "Profil berhasil diperbarui!";
        } else {
            $response['status'] = 'info';
            $response['message'] = "Tidak ada perubahan yang dilakukan.";
        }
    } catch (Exception $e) {
        $response['status'] = 'error';
        $response['message'] = "Terjadi kesalahan: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

if ($changesMade) {
    $response['status'] = 'success';
    $response['message'] = "Profil dan PIN berhasil diperbarui!";
} else {
    $response['status'] = 'info';
    $response['message'] = "Tidak ada perubahan yang dilakukan.";
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil</title>
    <link rel="stylesheet" href="stylehome.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
<div class="navbar">
    <div class="navbar-container">
        <a href="home.php" class="navbar-logo">Dashboard</a>
        <ul class="navbar-menu">
            <li><a href="editprofile.php">Edit Profil</a></li>
            <li class="dropdown">
                <a href="javascript:void(0)" class="dropbtn">Profile</a>
                <div class="dropdown-content">
                    <a href="home.php">Home</a>
                    <a href="upload.php">FImage</a>
                    <a href="logout.php">Logout</a>
                </div>
            </li>
        </ul>
    </div>
</div>

<div class="container">
    <h1>Edit Profil</h1>
    
    <div id="responseMessage"></div>

    <form id="editProfileForm" enctype="multipart/form-data">
        <label for="username">Username :</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" <?php echo $username_disabled ? 'disabled' : ''; ?>>
        
        <label for="email">Email :</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" <?php echo $email_disabled ? 'disabled' : ''; ?>>
        
        <?php if ($isAdmin): ?>
            <label for="new_password">Password Baru :</label>
            <input type="password" id="new_password" name="new_password" placeholder="Masukkan password baru">
        <?php endif; ?>
        <label for="full_name">Nama Lengkap :</label>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>

        <label for="phone_number">Nomor HP :</label>
        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>

        <label for="address">Alamat :</label>
        <textarea id="address" name="address" required><?php echo htmlspecialchars($user['address']); ?></textarea>

        <label for="bio">Biodata :</label>
        <textarea id="bio" name="bio"><?php echo htmlspecialchars($user['bio']); ?></textarea>
        <label for="pin">PIN Baru:</label>
        <input type="password" id="pin" name="pin" placeholder="Masukkan PIN baru" required>

        <label for="profile_picture">Foto Profil :</label>
        <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
        <?php if ($user['profile_picture']): ?>
            <img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Foto Profil" class="profile-picture">
        <?php endif; ?>

        <button type="button" id="saveButton">Simpan Perubahan</button>
    </form>
</div>
<script>
$(document).ready(function() {
    $('#saveButton').click(function() {
        var formData = new FormData(document.getElementById('editProfileForm'));

        $.ajax({
            url: 'editprofile.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                console.log(response);

                if (response.status === 'success') {
                    $('#responseMessage').html('<p class="success-msg">' + response.message + '</p>');
                } else {
                    $('#responseMessage').html('<p class="alert-msg">' + response.message + '</p>');
                }

                setTimeout(function() {
                    $('.alert-msg, .success-msg').addClass('fade-out');
                }, 3000);
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
                $('#responseMessage').html('<p class="alert-msg">Terjadi kesalahan: ' + error + '</p>');

                setTimeout(function() {
                    $('.alert-msg').addClass('fade-out');
                }, 3000);
            }
        });
    });
});
</script>
</body>
<footer class="footer">
    <p>&copy; 2024 Berita Terbaru. Semua Hak Dilindungi.</p>
</footer>
</html>
