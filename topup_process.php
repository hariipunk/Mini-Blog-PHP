<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt_history = $conn->prepare("SELECT * FROM user_topup_history WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt_history->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_history->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt_history->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt_history->execute();
$topup_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

$stmt_count = $conn->prepare("SELECT COUNT(*) FROM user_topup_history WHERE user_id = :user_id");
$stmt_count->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_count->execute();
$total_history = $stmt_count->fetchColumn();
$total_pages = ceil($total_history / $limit);

$stmt_history = $conn->prepare("SELECT amount, unique_id, total_payment, status, created_at 
                                FROM user_topup_history 
                                WHERE user_id = :user_id 
                                ORDER BY created_at DESC 
                                LIMIT :limit OFFSET :offset");
$stmt_history->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_history->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt_history->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt_history->execute();
$topup_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topup Poin</title>
    <link rel="stylesheet" href="styletopup.css">
</head>
<!-- Navbar -->
    <div class="navbar">
        <div class="navbar-container">
            <a href="topup_process.php" class="navbar-logo">Topup</a>
            <ul class="navbar-menu">
                <li><a href="home.php">Home</a></li>
                
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
<body>
    <div class="container">
        <h1>Proses Topup Poin</h1>
        <p>Halo, <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>). Silakan masukkan jumlah poin yang ingin Anda topup. Jumlah minimal adalah 10.000 poin.</p>
        
        <form method="POST" action="">

            <label for="topup_amount">Jumlah Poin yang ingin di-topup:</label>
            <input type="number" id="topup_amount" name="topup_amount" required min="10000" placeholder="Minimal 10.000 poin" />

            <button type="submit" name="topup-submit">Proses Topup</button>
        </form>

        
   <?php
if (isset($_POST['topup-submit'])) {
    $topup_amount = (int)$_POST['topup_amount'];

    if ($topup_amount < 10000) {
        echo "<p style='color: red;'>Jumlah topup minimal adalah 10.000 poin.</p>";
        exit;
    }

    $unique_id = mt_rand(100, 999); 
    $total_payment = $topup_amount + $unique_id;

$stmt = $conn->prepare("SELECT id, bank_account FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    $_SESSION['admin_bank_account'] = $admin['bank_account'];
} else {
    echo "<p style='color: red;'>Nomor rekening admin tidak ditemukan. Silakan hubungi admin.</p>";
}

    try {
        $conn->beginTransaction();
        
$stmt = $conn->prepare("INSERT INTO user_topup_history (user_id, amount, unique_id, total_payment, status, admin_id) 
                        VALUES (:user_id, :amount, :unique_id, :total_payment, 'pending', :admin_id)");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':amount', $topup_amount, PDO::PARAM_INT);
$stmt->bindParam(':unique_id', $unique_id, PDO::PARAM_INT);
$stmt->bindParam(':total_payment', $total_payment, PDO::PARAM_INT);
$stmt->bindParam(':admin_id', $admin['id'], PDO::PARAM_INT);
$stmt->execute();
        $conn->commit();

        echo "<p style='color: green;'>Topup Anda telah diproses. Silakan cek history untuk melihat struk pembayaran.</p>";
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<p style='color: red;'>Terjadi kesalahan saat memproses transaksi.</p>";
    }
}
?>
        
        <h2>History Topup Anda</h2>
        <?php if (count($topup_history) > 0): ?>
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Jumlah Poin</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Struk</th>
                    </tr>
                </thead>
                <tbody>
               <?php foreach ($topup_history as $history): ?>
<tr>
    <td><?php echo number_format($history['amount']); ?> Poin</td>
    <td>
        <div class="status-box <?php echo $history['status'] == 'approved' ? 'status-approved' : ($history['status'] == 'rejected' ? 'status-canceled' : 'status-pending'); ?>">
            <?php echo ucfirst($history['status']); ?>
        </div>
    </td>
    <td><?php echo date("d-m-Y", strtotime($history['created_at'])); ?></td>
    <td>
        <button class="view-struk-button" onclick="showStruk(<?php echo htmlspecialchars(json_encode(array_merge($history, ['username' => $user['username']])), ENT_QUOTES, 'UTF-8'); ?>)">Lihat Struk</button>
    </td>
</tr>
<?php endforeach; ?>

                </tbody>
            </table>
            </div>

            <div class="pagination">
                <?php
                for ($i = 1; $i <= $total_pages; $i++) {
                    echo "<a href='?page=$i' class='" . ($i == $page ? 'active' : '') . "'>$i</a> ";
                }
                ?>
            </div>
        <?php else: ?>
            <p>Anda belum memiliki history topup.</p>
        <?php endif; ?>
    </div>
</body>
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
<script>
const adminBankAccount = '<?php echo isset($_SESSION['admin_bank_account']) ? htmlspecialchars($_SESSION['admin_bank_account'], ENT_QUOTES, 'UTF-8') : "Data rekening tidak tersedia"; ?>';

function showStruk(history) {
    document.getElementById('popup-username').innerText = history.username;
    document.getElementById('popup-amount').innerText = history.amount + ' Poin';
    document.getElementById('popup-rekening').innerText = adminBankAccount;
    document.getElementById('popup-total').innerText = history.total_payment + ' Poin';
    document.getElementById('popup-unique').innerText = history.unique_id;

    document.getElementById('popup-struk').style.display = 'flex';
}

function closeStruk() {
    document.getElementById('popup-struk').style.display = 'none';
}
</script>

<div class="footer">
    <p>&copy; 2024 Admin Panel. All Rights Reserved.</p>
</div>
</html>

