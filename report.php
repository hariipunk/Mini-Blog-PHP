<?php
session_start();
require_once "database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

$limit = 10;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$totalReportsStmt = $conn->prepare("SELECT COUNT(*) FROM reports");
$totalReportsStmt->execute();
$totalReports = $totalReportsStmt->fetchColumn();
$totalPages = ceil($totalReports / $limit);

$stmt = $conn->prepare("
    SELECT r.*, a.title AS article_title, u.full_name AS owner_name
    FROM reports r
    JOIN articles a ON r.article_id = a.id
    JOIN users u ON a.user_id = u.id
    ORDER BY r.created_at DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$showSuccessModal = isset($_GET['success']) && $_GET['success'] == 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Laporan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="stylereport.css">
    <script>
        function showConfirmationModal(reportId) {
            document.getElementById('confirmation-modal').style.display = 'flex';
            document.getElementById('confirm-delete').href = `delete_report.php?id=${reportId}`;
        }

        function closeConfirmationModal() {
            document.getElementById('confirmation-modal').style.display = 'none';
        }

        function closeSuccessModal() {
            document.getElementById('success-modal').style.display = 'none';
        }
    </script>
    <div class="navbar">
        <div class="navbar-container">
            <a href="report.php" class="navbar-logo">Laporan</a>
            <ul class="navbar-menu">
                <li><a href="admin.php">Admin Panel</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</head>
<body>
<div class="container">
    <h2>Daftar Laporan Artikel</h2>
    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Nama Artikel</th>
                <th>Nama Pemilik Artikel</th>
                <th>Nama Pelapor</th>
                <th>Alasan</th>
                <th>Tanggal</th>
                <th>Tindakan</th>
            </tr>
        </thead>
       <tbody>
    <?php foreach ($reports as $report): ?>
        <tr>
            <td><?php echo htmlspecialchars($report['article_title']); ?></td>
            <td><?php echo htmlspecialchars($report['owner_name']); ?></td>
            <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
            <td><?php echo htmlspecialchars($report['reason']); ?></td>
            <td><?php echo htmlspecialchars($report['created_at']); ?></td>
            <td>
                <a href="javascript:void(0);" onclick="showConfirmationModal(<?php echo $report['id']; ?>)">Hapus</a>
            </td>
        </tr>
    <?php endforeach; ?>
        </tbody>

    </table>
    </div>

   <div class="pagination">
    <?php if ($page > 1): ?>
        <a href="report.php?page=<?php echo $page - 1; ?>" class="prev">Prev</a>
    <?php else: ?>
        <a href="#" class="prev disabled">Prev</a>
    <?php endif; ?>

    <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

    <?php if ($page < $totalPages): ?>
        <a href="report.php?page=<?php echo $page + 1; ?>" class="next">Next</a>
    <?php else: ?>
        <a href="#" class="next disabled">Next</a>
    <?php endif; ?>
    </div>


</div>

<div id="confirmation-modal" class="modal-confirmation">
    <div class="modal-confirmation-content">
        <h2>Apakah Anda yakin ingin menghapus laporan ini?</h2>
        <a id="confirm-delete" class="modal-btn confirm">Ya, Hapus</a>
        <button class="modal-btn cancel" onclick="closeConfirmationModal()">Batal</button>
    </div>
</div>

<?php if ($showSuccessModal): ?>
    <div id="success-modal" class="modal-success">
        <div class="modal-success-content">
            <h2>Laporan berhasil dihapus!</h2>
            <button class="modal-btn cancel" onclick="closeSuccessModal()">Tutup</button>
        </div>
    </div>
<?php endif; ?>

</body>
<div class="footer">
    <p>&copy; 2024 Admin Panel. All Rights Reserved.</p>
</div>
</html>