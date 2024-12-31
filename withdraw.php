<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error_message = "";
$success_message = "";

$stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw'])) {
    $amount = $_POST['amount'];
    $bank_name = $_POST['bank_name'];
    $account_number = $_POST['account_number'];
    $pin = $_POST['pin'];

    if (empty($bank_name) || empty($account_number)) {
        $error_message = "Silakan pilih bank dan masukkan nomor rekening.";
    } elseif (!preg_match('/^\d+$/', $account_number)) {
        $error_message = "Nomor rekening harus berupa angka.";
    } elseif ($amount % 10000 !== 0) {
        $error_message = "Jumlah penarikan harus kelipatan 10.000 poin.";
    } elseif ($amount > $user['points']) {
        $error_message = "Poin Anda tidak cukup untuk melakukan penarikan.";
    } elseif ($pin !== $user['pin']) {
        $error_message = "PIN yang Anda masukkan salah.";
    } else {
        $new_points = $user['points'] - $amount;

        $stmt = $conn->prepare("UPDATE users SET points = :new_points WHERE id = :user_id");
        $stmt->bindParam(':new_points', $new_points, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, bank_name, account_number, status) VALUES (:user_id, :amount, :bank_name, :account_number, 'pending')");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
        $stmt->bindParam(':bank_name', $bank_name, PDO::PARAM_STR);
        $stmt->bindParam(':account_number', $account_number, PDO::PARAM_STR);
        $stmt->execute();

        $withdrawal_id = $conn->lastInsertId();
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = :withdrawal_id");
        $stmt->bindParam(':withdrawal_id', $withdrawal_id, PDO::PARAM_INT);
        $stmt->execute();
        $new_withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'new_points' => number_format($new_points),
            'success_message' => "Penarikan Anda sedang diproses.",
            'new_withdrawal' => $new_withdrawal
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'error_message' => $error_message
    ]);
    exit;
}

$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $per_page;

$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :start, :per_page");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':start', $start, PDO::PARAM_INT);
$stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_withdrawals = $stmt->fetchColumn();
$total_pages = ceil($total_withdrawals / $per_page);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Poin</title>
    <link rel="stylesheet" href="stylewithdraw.css">
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function validateAccountNumber() {
        var bankName = document.getElementById("bank_name").value;
        var accountNumber = document.getElementById("account_number").value;
        var errorMessage = "";

        if (bankName === "bca" && accountNumber.length !== 10) {
            errorMessage = "Nomor rekening BCA harus terdiri dari 10 digit.";
        } else if (bankName === "bri" && accountNumber.length !== 15) {
            errorMessage = "Nomor rekening BRI harus terdiri dari 15 digit.";
        } else if (bankName === "mandiri" && accountNumber.length !== 13) {
            errorMessage = "Nomor rekening Mandiri harus terdiri dari 13 digit.";
        } else if (bankName === "bni" && accountNumber.length !== 10) {
            errorMessage = "Nomor rekening BNI harus terdiri dari 10 digit.";
        }

        var errorElement = document.getElementById("account_number_error");
        if (errorMessage) {
            errorElement.textContent = errorMessage;
            return false;
        } else {
            errorElement.textContent = "";
            return true;
        }
    }
</script>
</head>
<body>
    <div class="navbar">
        <div class="navbar-container">
            <a href="withdraw.php" class="navbar-logo">Penarikan</a>
            <ul class="navbar-menu">
                <li><a href="home.php">Home</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <h1>Penarikan Poin</h1>

        <div class="user-info">
    <p><span id="points-box" class="points-box"><?php echo number_format($user['points']); ?></span></p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert-msg-cul"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-msg-cul"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

<form id="withdraw-form">
    <label for="amount">Jumlah Poin (kelipatan 10.000):</label>
    <input type="number" name="amount" id="amount" min="10000" step="10000" required>

    <label for="bank_name">Pilih Bank:</label>
    <select id="bank_name" name="bank_name" required onchange="validateAccountNumber();">
        <option value="">Pilih Bank</option>
        <option value="bca">BCA</option>
        <option value="mandiri">Mandiri</option>
        <option value="bni">BNI</option>
        <option value="bri">BRI</option>
    </select>

    <label for="account_number">Nomor Rekening:</label>
    <input type="text" name="account_number" id="account_number" required oninput="validateAccountNumber();">

    <div id="account_number_error" style="color: red;"></div>
    <label for="pin">Masukkan PIN:</label>
    <input type="password" name="pin" id="pin" required>
    <button type="submit">Tarik Poin</button>
</form>



        <h1>Riwayat Penarikan Poin</h1>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Jumlah Poin</th>
                        <th>Bank</th>
                        <th>Nomor Rekening</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($withdrawal['id']); ?></td>
                            <td><?php echo htmlspecialchars($withdrawal['amount']); ?></td>
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
                            <td><?php echo date("d M Y H:i", strtotime($withdrawal['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1">&laquo; Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $total_pages; ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
</body>
<div id="success-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2>Success!</h2>
        <p id="success-message"></p>
    </div>
</div>

<!-- Modal Error -->
<div id="error-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2>Error!</h2>
        <p id="error-message"></p>
    </div>
</div>

<script>
    var initialPoints = <?php echo $user['points']; ?>;

    function updatePoints() {
        var amount = document.getElementById("amount").value;
        var pointsBox = document.getElementById("points-box");

        // Validate the amount entered is not more than the balance
        if (amount && parseInt(amount) > 0) {
            var updatedPoints = initialPoints - parseInt(amount);

            if (updatedPoints < 0) {
                updatedPoints = initialPoints;
            }

            pointsBox.innerHTML = numberWithCommas(updatedPoints);
        } else {
            pointsBox.innerHTML = numberWithCommas(initialPoints);
        }
    }

    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    document.getElementById("amount").addEventListener("input", updatePoints);
</script>
<script>
$(document).ready(function () {
    $("#withdraw-form").submit(function (event) {
        event.preventDefault();

        var amount = $("#amount").val();
        var bank_name = $("#bank_name").val();
        var account_number = $("#account_number").val();
        var pin = $("#pin").val();

        if (amount % 10000 !== 0) {
            showErrorModal("Jumlah penarikan harus kelipatan 10.000 poin.");
            return;
        }

        if (!validateAccountNumber()) {
            return;
        }

        $.ajax({
            url: "withdraw.php",
            method: "POST",
            data: {
                withdraw: true,
                amount: amount,
                bank_name: bank_name,
                account_number: account_number,
                pin: pin
            },
            success: function (response) {
                var data = JSON.parse(response);

                if (data.success) {
                    $("#points-box").text(data.new_points);

                    var newTransactionRow = `
                    <tr>
                    <td>${data.new_withdrawal.id}</td>
                    <td>${data.new_withdrawal.amount}</td>
                    <td>${data.new_withdrawal.bank_name}</td>
                    <td>${data.new_withdrawal.account_number}</td>
                    <td>
                    <span class="status-pending">Pending</span>
                    </td>
                    <td>${new Date(data.new_withdrawal.created_at).toLocaleString()}</td>
                    </tr>
                    `;
                    $("table tbody").prepend(newTransactionRow);

                    showSuccessModal(data.success_message);
                } else {
                    showErrorModal(data.error_message);
                }
            },
            error: function () {
                showErrorModal("Terjadi kesalahan, coba lagi.");
            }
        });
    });
});
</script>
<script>
    function showSuccessModal(message) {
        $("#success-message").text(message);
        $("#success-modal").fadeIn().addClass("show").removeClass("hide");
    }

    function showErrorModal(message) {
        $("#error-message").text(message);
        $("#error-modal").fadeIn().addClass("show").removeClass("hide");
    }

    $(".close-btn").click(function() {
        $(".modal").fadeOut().addClass("hide").removeClass("show");
    });

    $(window).click(function(event) {
        if ($(event.target).hasClass('modal')) {
            $(".modal").fadeOut().addClass("hide").removeClass("show");
        }
    });

</script>

<div class="footer">
    <p>&copy; 2024 Admin Panel. All Rights Reserved.</p>
</div>
</html>

