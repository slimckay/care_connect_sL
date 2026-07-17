<?php
/**
 * Transaction History - Care Connect SL
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'payment_helper.php';

$user_id = $_SESSION['user_id'];
$payment = new PaymentSystem($conn);

// Get all transactions with pagination
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    $stmt = $conn->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $transactions = $stmt->fetchAll();
    
    // Get total count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
    $countStmt->execute([$user_id]);
    $total = $countStmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $limit);
    
} catch (PDOException $e) {
    error_log("Transaction history error: " . $e->getMessage());
    $transactions = [];
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .tx-table {
            width: 100%;
            border-collapse: collapse;
        }
        .tx-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--light);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }
        .tx-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }
        .tx-table tr:hover td { background: #f8fafc; }
        .pagination { display: flex; gap: 8px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--dark);
        }
        .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination a:hover { background: var(--light); }
    </style>
</head>
<body>
<header role="banner">
    <div class="nav-inner">
        <a href="index.html" class="logo">❤️ Care<span class="accent">Connect</span> SL</a>
    </div>
</header>

<main class="page-content">
    <section class="page-hero">
        <h1>📊 Transaction History</h1>
        <p>View all your financial transactions.</p>
        <a href="wallet.php" class="btn-ghost" style="margin-top: 12px;">← Back to Wallet</a>
    </section>

    <div class="card">
        <?php if (!empty($transactions)): ?>
            <table class="tx-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?php echo date('M d, Y h:i A', strtotime($tx['created_at'])); ?></td>
                            <td><span class="badge <?php echo $tx['type']; ?>"><?php echo ucfirst($tx['type']); ?></span></td>
                            <td><?php echo htmlspecialchars($tx['description']); ?></td>
                            <td style="font-weight: 600; color: <?php echo $tx['type'] === 'deposit' || $tx['type'] === 'refund' ? 'var(--success)' : 'var(--danger)'; ?>">
                                <?php echo $tx['type'] === 'deposit' || $tx['type'] === 'refund' ? '+' : '-'; ?>
                                SLL <?php echo number_format($tx['amount'], 0); ?>
                            </td>
                            <td><span class="badge <?php echo $tx['status']; ?>"><?php echo ucfirst($tx['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <p style="color: var(--muted);">No transactions found.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer class="site-footer">
    <p class="footer-note">&copy; 2026 Care Connect SL. All rights reserved.</p>
</footer>

<script src="js/main.js"></script>
</body>
</html>