<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get filter parameters
$type = $_GET['type'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$sort = $_GET['sort'] ?? 'date_desc';

// Build the base query
$sql = "SELECT *, DATE_FORMAT(date, '%M %d, %Y') as formatted_date 
        FROM transactions 
        WHERE user_id = ?";
$params = [$user_id];
$types = "i";

// Apply filters
if ($type !== 'all') {
    $sql .= " AND type = ?";
    $params[] = $type;
    $types .= "s";
}

if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " AND date BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;
$types .= "ss";

// Add sorting
switch ($sort) {
    case 'amount_asc':
        $sql .= " ORDER BY amount ASC";
        break;
    case 'amount_desc':
        $sql .= " ORDER BY amount DESC";
        break;
    case 'date_asc':
        $sql .= " ORDER BY date ASC, id ASC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY date DESC, id DESC";
}

if (isset($_GET['delete'])) {
    $transaction_id = $_GET['delete'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, check if this is a debt-related transaction
        $stmt = $conn->prepare("SELECT category, type, amount, description, date FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $transaction_id, $user_id);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();

        if ($transaction) {
            // If this is a debt taken transaction (recorded as income)
            if ($transaction['type'] === 'income' && $transaction['category'] === 'debt') {
                // Delete all related debt payments and their transactions
                $stmt = $conn->prepare("
                    SELECT dp.transaction_id 
                    FROM debts d
                    LEFT JOIN debt_payments dp ON d.id = dp.debt_id
                    WHERE d.user_id = ? 
                    AND d.amount = ? 
                    AND d.description = ?");
                $stmt->bind_param("ids", $user_id, $transaction['amount'], $transaction['description']);
                $stmt->execute();
                $payment_result = $stmt->get_result();
                
                $payment_transaction_ids = [];
                while ($row = $payment_result->fetch_assoc()) {
                    if ($row['transaction_id']) {
                        $payment_transaction_ids[] = $row['transaction_id'];
                    }
                }
                
                // Delete the debt payments first
                $stmt = $conn->prepare("
                    DELETE dp FROM debt_payments dp
                    INNER JOIN debts d ON dp.debt_id = d.id
                    WHERE d.user_id = ? 
                    AND d.amount = ? 
                    AND d.description = ?");
                $stmt->bind_param("ids", $user_id, $transaction['amount'], $transaction['description']);
                $stmt->execute();
                
                // Delete payment transactions
                if (!empty($payment_transaction_ids)) {
                    $placeholders = str_repeat('?,', count($payment_transaction_ids) - 1) . '?';
                    $stmt = $conn->prepare("DELETE FROM transactions WHERE id IN ($placeholders)");
                    $stmt->bind_param(str_repeat('i', count($payment_transaction_ids)), ...$payment_transaction_ids);
                    $stmt->execute();
                }
                
                // Finally delete the debt record
                $stmt = $conn->prepare("
                    DELETE FROM debts 
                    WHERE user_id = ? 
                    AND amount = ? 
                    AND description = ?");
                $stmt->bind_param("ids", $user_id, $transaction['amount'], $transaction['description']);
                $stmt->execute();
            }
            // If this is a debt payment transaction
            else if ($transaction['type'] === 'expense' && $transaction['category'] === 'debt_payment') {
                // Check if this transaction is linked to a debt payment
                $stmt = $conn->prepare("SELECT debt_id FROM debt_payments WHERE transaction_id = ?");
                $stmt->bind_param("i", $transaction_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Delete the debt payment first
                    $stmt = $conn->prepare("DELETE FROM debt_payments WHERE transaction_id = ?");
                    $stmt->bind_param("i", $transaction_id);
                    $stmt->execute();
                    
                    // Update debt status if needed
                    $debt_id = $result->fetch_assoc()['debt_id'];
                    
                    // Get remaining payments for this debt
                    $stmt = $conn->prepare("SELECT SUM(amount) as total_paid FROM debt_payments WHERE debt_id = ?");
                    $stmt->bind_param("i", $debt_id);
                    $stmt->execute();
                    $total_paid = $stmt->get_result()->fetch_assoc()['total_paid'] ?? 0;
                    
                    // Update debt status based on remaining payments
                    $status = $total_paid > 0 ? 'partial' : 'pending';
                    $stmt = $conn->prepare("UPDATE debts SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $debt_id);
                    $stmt->execute();
                }
            }
        }
        
        // Finally, delete the transaction
        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $transaction_id, $user_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        header("Location: transactions.php?success=Transaction deleted successfully");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        header("Location: transactions.php?error=Error deleting transaction: " . $e->getMessage());
        exit();
    }
}

// Get transactions
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$transactions = array();
while ($row = $result->fetch_assoc()) {
    // Format category with first letter uppercase
    $row['category'] = ucfirst($row['category']);
    $transactions[] = $row;
}

// Get unique categories for filter
$sql = "SELECT DISTINCT category FROM transactions WHERE user_id = ? ORDER BY category";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories_result = $stmt->get_result();

$categories = array();
while ($row = $categories_result->fetch_assoc()) {
    // Format category with first letter uppercase
    $categories[] = ucfirst($row['category']);
}

// Calculate totals for filtered results
$sql = "SELECT 
        SUM(CASE 
            WHEN type = 'income' OR (type = 'expense' AND category = 'debt' AND amount < 0) THEN ABS(amount) 
            ELSE 0 
        END) as total_income,
        SUM(CASE 
            WHEN type = 'expense' AND (category != 'debt' OR (category = 'debt' AND amount > 0)) THEN ABS(amount) 
            ELSE 0 
        END) as total_expenses
        FROM transactions 
        WHERE user_id = ?";

$params = [$user_id];
$types = "i";

if ($type !== 'all') {
    $sql .= " AND type = ?";
    $params[] = $type;
    $types .= "s";
}

if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " AND date BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;
$types .= "ss";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();

$total_income = $totals['total_income'] ?? 0;
$total_expenses = $totals['total_expenses'] ?? 0;
$transaction_count = count($transactions);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker - Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .transaction-description {
            max-width: 300px;
            word-wrap: break-word;
            white-space: normal;
            line-height: 1.5;
        }
        .transaction-date {
            white-space: nowrap;
            min-width: 120px;
        }
        .transaction-category {
            white-space: nowrap;
            min-width: 100px;
        }
        .transaction-amount {
            white-space: nowrap;
            min-width: 100px;
            text-align: right;
        }
        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 0;
        }
        .container-fluid {
            max-width: 1800px;
            width: 95%;
            margin: 0 auto;
            padding: 2rem;
        }
        .summary-card {
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid py-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1">Transactions</h4>
                        <div class="text-muted">
                            Showing <?php echo $transaction_count; ?> transaction<?php echo $transaction_count !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                    <a href="add_transaction.php" class="btn btn-primary">
                        <i class='bx bx-plus me-1'></i>Add Transaction
                    </a>
                </div>

                <!-- Filters -->
                <div class="summary-card mb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Income</option>
                                <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo strtolower($cat); ?>" 
                                        <?php echo $category === strtolower($cat) ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Date (Newest)</option>
                                <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Date (Oldest)</option>
                                <option value="amount_desc" <?php echo $sort === 'amount_desc' ? 'selected' : ''; ?>>Amount (High to Low)</option>
                                <option value="amount_asc" <?php echo $sort === 'amount_asc' ? 'selected' : ''; ?>>Amount (Low to High)</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class='bx bx-filter-alt me-1'></i>Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="summary-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Total Income</h6>
                                <i class='bx bx-up-arrow-alt text-success fs-4'></i>
                            </div>
                            <h3 class="mb-0 text-success">₹<?php echo number_format($total_income, 2); ?></h3>
                            <div class="text-muted small">For selected period</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Total Expenses</h6>
                                <i class='bx bx-down-arrow-alt text-danger fs-4'></i>
                            </div>
                            <h3 class="mb-0 text-danger">₹<?php echo number_format($total_expenses, 2); ?></h3>
                            <div class="text-muted small">For selected period</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Net Balance</h6>
                                <i class='bx bx-wallet text-primary fs-4'></i>
                            </div>
                            <h3 class="mb-0 <?php echo ($total_income - $total_expenses) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                ₹<?php echo number_format($total_income - $total_expenses, 2); ?>
                            </h3>
                            <div class="text-muted small">For selected period</div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="summary-card">
                    <?php if (count($transactions) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td class="transaction-date"><?php echo $transaction['formatted_date']; ?></td>
                                    <td class="transaction-category"><?php echo $transaction['category']; ?></td>
                                    <td class="transaction-description" style="max-width: 300px; word-wrap: break-word;"><?php echo htmlspecialchars($transaction['description'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge <?php echo $transaction['type'] === 'income' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ucfirst($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td class="<?php echo $transaction['type'] === 'expense' ? 'text-danger' : 'text-success'; ?>">
                                        <?php 
                                        // For expenses, amount is already negative, so just display it
                                        // For income, add the + sign
                                        echo $transaction['type'] === 'income' ? '+' : ''; 
                                        echo '₹' . number_format(abs($transaction['amount']), 2); 
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary me-1">
                                            <i class='bx bx-edit-alt'></i>
                                        </a>
                                        <a href="delete_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this transaction?')">
                                            <i class='bx bx-trash'></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class='bx bx-file text-muted' style="font-size: 48px;"></i>
                        </div>
                        <h5>No Transactions Found</h5>
                        <p class="text-muted">Try adjusting your filters or add a new transaction</p>
                        <a href="add_transaction.php" class="btn btn-primary">
                            <i class='bx bx-plus me-1'></i>Add Transaction
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
