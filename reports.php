<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get date range parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Check if export is requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_' . $start_date . '_to_' . $end_date . '.csv"');
    
    // Create output handle
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['Date', 'Category', 'Description', 'Type', 'Amount']);
    
    // Get transactions
    $sql = "SELECT date, category, description, type, amount 
            FROM transactions 
            WHERE user_id = ? AND date BETWEEN ? AND ? 
            ORDER BY date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Add transactions to CSV
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            date('Y-m-d', strtotime($row['date'])),
            $row['category'],
            $row['description'],
            $row['type'],
            $row['amount']
        ]);
    }
    
    fclose($output);
    exit();
}

// Get transactions for display
$sql = "SELECT date, category, description, type, amount 
        FROM transactions 
        WHERE user_id = ? AND date BETWEEN ? AND ? 
        ORDER BY date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$transactions = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker - Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
            width: calc(100% - 250px);
        }
        .summary-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }
        .form-control, .btn {
            font-size: 0.9rem;
            border-radius: 8px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-top: none;
            border-bottom-width: 1px;
        }
        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
            color: #495057;
            border-color: #f0f0f0;
        }
        .badge {
            font-weight: 600;
            font-size: 0.8rem;
            padding: 0.5em 0.8em;
        }
        .text-success {
            color: #10b981 !important;
        }
        .text-danger {
            color: #ef4444 !important;
        }
        .bg-success {
            background-color: #10b981 !important;
        }
        .bg-danger {
            background-color: #ef4444 !important;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-success {
            background-color: #10b981;
            border-color: #10b981;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
            transition: all 0.2s;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <div class="container py-4" style="max-width: 1200px;">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="mb-1 fw-bold">Generate Reports</h4>
                        <p class="text-muted mb-0">Export your transactions for selected date range</p>
                    </div>
                </div>

                <!-- Date Selection and Export -->
                <div class="summary-card mb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Date</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class='bx bx-calendar'></i>
                                </span>
                                <input type="date" name="start_date" class="form-control border-start-0 ps-0" 
                                       value="<?php echo htmlspecialchars($start_date); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">End Date</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class='bx bx-calendar'></i>
                                </span>
                                <input type="date" name="end_date" class="form-control border-start-0 ps-0" 
                                       value="<?php echo htmlspecialchars($end_date); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1 fw-semibold">
                                <i class='bx bx-show me-1'></i>View Report
                            </button>
                            <button type="submit" name="export" value="csv" class="btn btn-success flex-grow-1 fw-semibold">
                                <i class='bx bx-download me-1'></i>Export CSV
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Transactions Table -->
                <?php if ($transactions && $transactions->num_rows > 0): ?>
                <div class="summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title fw-bold mb-0">Transaction History</h5>
                        <div class="text-muted small">
                            <?php echo $transactions->num_rows; ?> transactions found
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($transaction = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-nowrap"><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <i class='bx bx-purchase-tag text-muted'></i>
                                            <?php echo htmlspecialchars($transaction['category']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $transaction['type'] === 'income' ? 'bg-success' : 'bg-danger'; ?>">
                                            <i class='bx <?php echo $transaction['type'] === 'income' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt'; ?> me-1'></i>
                                            <?php echo ucfirst($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold <?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?>₹<?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="summary-card text-center py-5">
                    <div class="mb-3">
                        <i class='bx bx-file text-muted' style="font-size: 48px;"></i>
                    </div>
                    <h5 class="fw-bold">No Transactions Found</h5>
                    <p class="text-muted mb-0">Try adjusting your date range to view transactions</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
