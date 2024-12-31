<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$per_page = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $per_page;

$stmt = $conn->prepare("SELECT * FROM withdrawals ORDER BY created_at DESC LIMIT :start, :per_page");
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) FROM withdrawals");
$stmt->execute();
$total_withdrawals = $stmt->fetchColumn();
$total_pages = ceil($total_withdrawals / $per_page);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $withdrawal_id = (int) $_POST['withdrawal_id'];
    $status = $_POST['status'];

    $allowed_status = ['completed', 'cancelled'];
    if (!in_array($status, $allowed_status)) {
        die("Error: Status tidak valid.");
    }

    $stmt = $conn->prepare("SELECT user_id, amount FROM withdrawals WHERE id = :id");
    $stmt->bindParam(':id', $withdrawal_id, PDO::PARAM_INT);
    $stmt->execute();
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$withdrawal) {
        die("Error: Withdrawal tidak ditemukan.");
    }

    $user_id = $withdrawal['user_id'];
    $amount = $withdrawal['amount'];

    if ($status == 'cancelled') {
        $stmt = $conn->prepare("UPDATE users SET points = points + :amount WHERE id = :user_id");
        $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            die("Error: Gagal mengembalikan saldo.");
        }
    }
    
    $stmt = $conn->prepare("UPDATE withdrawals SET status = :status WHERE id = :id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $withdrawal_id);
    if (!$stmt->execute()) {
        die("Error: Gagal memperbarui status withdrawal.");
    }

    $message = ($status == 'completed')
        ? "Penarikan Anda sebesar " . number_format($amount) . " poin disetujui."
        : "Penarikan Anda sebesar " . number_format($amount) . " poin dibatalkan dan saldo telah dikembalikan.";

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, article_id, message) VALUES (:user_id, NULL, :message)");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':message', $message, PDO::PARAM_STR);
    if (!$stmt->execute()) {
        die("Error: Gagal menyimpan notifikasi.");
    }
    header("Location: admin_withdraw.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Penarikan Poin</title>
    <link rel="stylesheet" href="styleadminwithdraw.css">
</head>
<body>
<div class="navbar">
    <div class="navbar-container">
        <a href="admin_withdraw.php" class="navbar-logo">Penarikan</a>
        <ul class="navbar-menu">
            <li><a href="admin.php">Admin Panel</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
</div>

<div class="container">
    <h1>Kelola Penarikan Poin</h1>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Jumlah Poin</th>
                    <th>Bank</th>
                    <th>Nomor Rekening</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($withdrawal['id']); ?></td>
                        <td><?php echo number_format(htmlspecialchars($withdrawal['amount'])); ?></td>
                        <td><?php echo htmlspecialchars($withdrawal['bank_name']); ?></td>
                        <td><?php echo htmlspecialchars($withdrawal['account_number']); ?></td>
                        <td>
                            <?php 
                                $status = strtolower($withdrawal['status']);
                                if ($status == 'pending') {
                                    echo '<span class="status-pending">Pending</span>';
                                } elseif ($status == 'cancelled') {
                                    echo '<span class="status-cancelled">Cancelled</span>';
                                } elseif ($status == 'completed') {
                                    echo '<span class="status-completed">Completed</span>';
                                }
                            ?>
                        </td>
                        <td>
                            <?php if ($withdrawal['status'] == 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">
                                    <select name="status">
                                        <option value="completed">Setujui</option>
                                        <option value="cancelled">Batalkan</option>
                                    </select>
                                    <button type="submit" name="update_status">Perbarui</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>">&laquo; Sebelumnya</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>">Berikutnya &raquo;</a>
        <?php endif; ?>
    </div>
</div>
<div class="footer">
    <p>&copy; 2024 Admin Panel. All Rights Reserved.</p>
</div>
</body>
</html>
