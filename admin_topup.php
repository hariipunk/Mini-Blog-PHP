<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT uth.*, u.username, uth.total_payment, uth.unique_id 
    FROM user_topup_history uth
    JOIN users u ON uth.user_id = u.id
    ORDER BY uth.created_at DESC
");
$stmt->execute();
$topups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve'])) {
    $topup_id = $_POST['topup_id'];
    $status = 'approved';
    $user_id = $_POST['user_id'];
    $amount = $_POST['amount'];
    $admin_id = $_SESSION['user_id']; 

    $stmt = $conn->prepare("UPDATE user_topup_history SET status = :status WHERE id = :topup_id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':topup_id', $topup_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE users SET points = points + :amount WHERE id = :user_id");
    $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE users SET points = points - :amount WHERE id = :admin_id");
    $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
    $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt->execute();

    $message = "Topup Anda sebesar " . number_format($amount) . " poin telah disetujui dan ditambahkan ke akun Anda.";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, article_id, message) VALUES (:user_id, NULL, :message)");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message, PDO::PARAM_STR);
    $stmt->execute();
    header("Location: admin_topup.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject'])) {
    $topup_id = $_POST['topup_id'];
    $status = 'rejected';
    $user_id = $_POST['user_id'];
    $amount = $_POST['amount'];

    $stmt = $conn->prepare("UPDATE user_topup_history SET status = :status WHERE id = :topup_id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':topup_id', $topup_id, PDO::PARAM_INT);
    $stmt->execute();

    $message = "Topup Anda sebesar " . number_format($amount) . " poin telah ditolak.";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, article_id, message) VALUES (:user_id, NULL, :message)");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message, PDO::PARAM_STR);
    $stmt->execute();
    
    header("Location: admin_topup.php");
    exit;
}
?>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topup Poin</title>
    <link rel="stylesheet" href="styleadmintopup.css">
</head>
    <div class="navbar">
        <div class="navbar-container">
            <a href="admin_topup.php" class="navbar-logo">Transaksi</a></a>
            <ul class="navbar-menu">
                <li><a href="admin.php">Panel Admin</a></li>
                
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
<body>
    <div class="container">
        <h2>Daftar Topup</h2>
        <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Jumlah Poin</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topups as $topup): ?>
                    <tr>
                        <td><?php echo $topup['username']; ?></td>
                        <td><?php echo number_format($topup['amount']); ?></td>
                        <td><?php echo ucfirst($topup['status']); ?></td>
                        <td>

    <form method="POST">
        <input type="hidden" name="topup_id" value="<?php echo $topup['id']; ?>">
        <input type="hidden" name="user_id" value="<?php echo $topup['user_id']; ?>">
        <input type="hidden" name="amount" value="<?php echo $topup['amount']; ?>">
        
        <?php if ($topup['status'] == 'pending'): ?>
            <button type="submit" name="approve">Setujui</button>
            <button type="submit" name="reject">Tolak</button>
        <?php elseif ($topup['status'] == 'approved'): ?>
            <button type="button" disabled>Sudah Disetujui</button>
            <button type="submit" name="reject" disabled>Tolak</button>
        <?php elseif ($topup['status'] == 'rejected'): ?>
            <button type="button" disabled>Sudah Ditolak</button>
            <button type="submit" name="approve" disabled>Setujui</button>
        <?php endif; ?>
        <button type="button" class="view-struk-button" data-topup='<?php echo json_encode($topup); ?>' onclick="showStruk(this)">Lihat Struk</button>
    </form>
 
                         </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<script>
function closeStruk() {
    document.getElementById('popup-struk').style.display = 'none'; 
}
// Fungsi untuk menampilkan popup struk
function showStruk(button) {
    var topup = JSON.parse(button.getAttribute('data-topup')); 

    if (topup.total_payment && topup.unique_id) {
        document.getElementById('popup-username').innerText = topup.username;
        document.getElementById('popup-amount').innerText = topup.amount + ' Poin';
        document.getElementById('popup-rekening').innerText = '<?php echo $_SESSION['admin_bank_account'] ?? 'Bank Admin Tidak Ditemukan'; ?>';
        document.getElementById('popup-total').innerText = topup.total_payment + ' Poin';
        document.getElementById('popup-unique').innerText = topup.unique_id;

        document.getElementById('popup-struk').style.display = 'flex'; 
    } else {
        alert("Data pembayaran tidak lengkap.");
    }
}
</script>
<div id="popup-struk" class="popup">
    <div class="popup-content">
        <span class="close-btn" onclick="closeStruk()">&times;</span>
        <h3><center>Struk Pembayaran</center></h3>
        <table>
            <tr>
                <th>Username</th>
                <td id="popup-username"></td>
            </tr>
            <tr>
                <th>Jumlah Poin</th>
                <td id="popup-amount"></td>
            </tr>
            <tr>
                <th>Nomor Rekening Admin</th>
                <td id="popup-rekening"></td>
            </tr>
            <tr>
                <th>Jumlah yang Harus Dibayar</th>
                <td id="popup-total"></td>
            </tr>
            <tr>
                <th>ID Unik Pembayaran</th>
                <td id="popup-unique"></td>
            </tr>
        </table>
        <h3><center>Mohon lakukan pembayaran sesuai jumlah yang harus dibayar</center></h3>
    </div>
   
</div>

</body>
    <div class="footer">
        <p>&copy; 2024 Admin Panel. All Rights Reserved.</p>
    </div>
</html>
