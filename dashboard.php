<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$current_month = date('Y-m');
$current_year = date('Y');

// Remove expired budget goals
$stmt = $conn->prepare("DELETE FROM budget_goals WHERE user_id = ? AND end_date < CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Get user's name
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_name = $user['username'];

// Get total expenses and income for all time
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE 
            WHEN type = 'expense' THEN ABS(amount)  -- Convert negative expenses to positive for totals
            ELSE 0 
        END) as total_expenses,
        SUM(CASE 
            WHEN type = 'income' THEN amount 
            ELSE 0 
        END) as total_income,
        SUM(CASE 
            WHEN type = 'income' THEN amount 
            WHEN type = 'expense' THEN amount -- Keep negative for balance
            ELSE 0 
        END) as current_balance
    FROM transactions 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$totals = $result->fetch_assoc();

$total_income = $totals['total_income'] ?? 0;
$total_expenses = $totals['total_expenses'] ?? 0;
$balance = $total_income - $total_expenses;

// Get recent transactions
$stmt = $conn->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY date DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$recent_transactions = array();
while ($row = $result->fetch_assoc()) {
    // Format category with first letter uppercase
    $row['category'] = ucfirst($row['category']);
    $recent_transactions[] = $row;
}

// Get budget goals progress for current month
$sql = "SELECT 
    bg.id,
    bg.category,
    bg.amount as budget_amount,
    COALESCE(SUM(t.amount), 0) as spent_amount,
    COALESCE((SUM(t.amount) / bg.amount) * 100, 0) as progress
FROM budget_goals bg
LEFT JOIN transactions t ON bg.category = t.category 
    AND t.user_id = bg.user_id 
    AND t.type = 'expense'
    AND DATE_FORMAT(t.date, '%Y-%m') = ?
WHERE bg.user_id = ?
GROUP BY bg.id, bg.category, bg.amount
ORDER BY progress DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $current_month, $user_id);
$stmt->execute();
$budgets = $stmt->get_result();

// Get daily expenses for the line chart
$stmt = $conn->prepare("
    SELECT 
        DATE(date) as date,
        SUM(CASE 
            WHEN type = 'expense' THEN ABS(amount)  -- Show expenses as positive in graph
            ELSE 0 
        END) as total_expenses,
        SUM(CASE 
            WHEN type = 'income' THEN amount 
            ELSE 0 
        END) as total_income
    FROM transactions
    WHERE user_id = ? 
    AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    GROUP BY DATE(date)
    ORDER BY date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$daily_transactions = $stmt->get_result();

$dates = [];
$incomes = [];
$expenses = [];

while ($row = $daily_transactions->fetch_assoc()) {
    $dates[] = date('M d', strtotime($row['date']));
    $incomes[] = $row['total_income'] ?? 0;
    $expenses[] = $row['total_expenses'] ?? 0;
}

// Get spending by category for current day
$sql = "SELECT 
        category, 
        CASE 
            WHEN category = 'debt' THEN 
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END)
            ELSE SUM(amount) 
        END as total
        FROM transactions 
        WHERE user_id = ? 
        AND ((type = 'expense' AND (category != 'debt' OR (category = 'debt' AND amount > 0)))
            OR (type = 'expense' AND category = 'debt' AND amount < 0))
        AND DATE(date) = CURDATE()
        AND LOWER(category) != 'opening balance'
        GROUP BY category 
        ORDER BY ABS(SUM(amount)) DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$category_spending = $stmt->get_result();
$total_spending = 0;
while ($row = $category_spending->fetch_assoc()) {
    $total_spending += $row['total'];
}
$category_spending->data_seek(0);

// Get category totals for pie chart
$sql = "SELECT 
        category,
        CASE 
            WHEN category = 'debt' THEN 
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END)
            ELSE SUM(amount) 
        END as total
        FROM transactions 
        WHERE user_id = ? 
        AND ((type = 'expense' AND (category != 'debt' OR (category = 'debt' AND amount > 0)))
            OR (type = 'expense' AND category = 'debt' AND amount < 0))
        AND MONTH(date) = MONTH(CURRENT_DATE())
        AND YEAR(date) = YEAR(CURRENT_DATE())
        AND LOWER(category) != 'opening balance'
        GROUP BY category 
        ORDER BY ABS(SUM(amount)) DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_categories = $stmt->get_result();

$categories = [];
$spending = [];
$total_spending = 0;
$colors = ['#4F46E5', '#7C3AED', '#EC4899', '#F59E0B', '#10B981'];

while ($row = $category_spending->fetch_assoc()) {
    $categories[] = ucfirst($row['category']);
    $spending[] = $row['total'];
    $total_spending += $row['total'];
}

// Convert categories and spending to JSON for chart
$categories_json = json_encode($categories);
$expense_amounts_json = json_encode($spending);
$colors_json = json_encode($colors);

// Get today's transactions by category (excluding opening balance)
$stmt = $conn->prepare("
    SELECT 
        category,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense_amount,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income_amount,
        COUNT(*) as transaction_count
    FROM transactions 
    WHERE user_id = ? 
    AND DATE(date) = CURDATE()
    AND LOWER(category) != 'opening balance'
    GROUP BY category
    ORDER BY expense_amount DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$categories = array();
$expense_amounts = array();
$income_amounts = array();
$transaction_counts = array();

while ($row = $result->fetch_assoc()) {
    // Format category with first letter uppercase
    $categories[] = ucfirst($row['category']);
    $expense_amounts[] = $row['expense_amount'];
    $income_amounts[] = $row['income_amount'];
    $transaction_counts[] = $row['transaction_count'];
}

$categories_json = json_encode($categories);
$expense_amounts_json = json_encode($expense_amounts);
$income_amounts_json = json_encode($income_amounts);
$transaction_counts_json = json_encode($transaction_counts);

// Get total income and expenses for each day in the current month
$sql = "SELECT 
        DATE(date) as date,
        SUM(CASE 
            WHEN type = 'income' OR (type = 'expense' AND category = 'debt' AND amount < 0) THEN ABS(amount) 
            ELSE 0 
        END) as total_income,
        SUM(CASE 
            WHEN type = 'expense' AND (category != 'debt' OR (category = 'debt' AND amount > 0)) THEN ABS(amount) 
            ELSE 0 
        END) as total_expenses
        FROM transactions 
        WHERE user_id = ? 
        AND MONTH(date) = MONTH(CURRENT_DATE())
        AND YEAR(date) = YEAR(CURRENT_DATE())
        GROUP BY DATE(date)
        ORDER BY date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get today's transactions summary
$sql = "SELECT 
    COUNT(*) as total_transactions,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as today_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as today_expenses
FROM transactions 
WHERE user_id = ? AND DATE(date) = CURDATE()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today = $stmt->get_result()->fetch_assoc();

$today_transactions = $today['total_transactions'];
$today_income = $today['today_income'] ?? 0;
$today_expenses = $today['today_expenses'] ?? 0;
$today_net = $today_income - $today_expenses;

// Get current week's transactions for graph
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$sql = "SELECT 
    DATE_FORMAT(date, '%a') as day,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
FROM transactions 
WHERE user_id = ? 
AND date BETWEEN ? AND ?
GROUP BY date
ORDER BY date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $week_start, $week_end);
$stmt->execute();
$week_data = $stmt->get_result();

$days = [];
$incomes = [];
$expenses = [];

while ($row = $week_data->fetch_assoc()) {
    $days[] = $row['day'];
    $incomes[] = $row['income'];
    $expenses[] = $row['expense'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Finance Tracker</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-info h4 {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        .stat-info p {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .budget-progress {
            background: #f3f4f6;
            border-radius: 8px;
            height: 8px;
            overflow: hidden;
        }
        .budget-progress .progress-bar {
            height: 100%;
            border-radius: 8px;
            transition: width 0.5s ease;
        }
        .chart-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        .recent-transactions {
            margin-top: 0.5rem;
        }
        .recent-transactions .table {
            margin: 0;
        }
        .recent-transactions th {
            font-weight: 500;
            color: #6b7280;
            border-bottom-width: 1px;
        }
        .recent-transactions td {
            vertical-align: middle;
            color: #111827;
            font-weight: 500;
        }
        .transaction-amount {
            font-weight: 600;
        }
        .transaction-amount.income {
            color: #22c55e;
        }
        .transaction-amount.expense {
            color: #ef4444;
        }
        .welcome-section {
            margin-bottom: 2rem;
        }
        .welcome-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        .welcome-subtext {
            color: #6b7280;
            margin: 0;
        }
        .budget-item {
            background: #ffffff;
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 0.75rem;
        }
        .budget-item:last-child {
            margin-bottom: 0.75rem;
        }
        .budget-item h6 {
            font-size: 0.9rem;
        }
        .budget-meta {
            font-size: 0.8rem;
        }
        .budget-progress {
            margin: 0.5rem 0;
        }
        .budget-goals-wrapper {
            height: 300px;
        }
        .budget-goals {
            height: 100%;
            overflow-y: auto;
            padding-right: 8px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }
        .budget-goals::-webkit-scrollbar {
            width: 4px;
        }
        .budget-goals::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .budget-goals::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .budget-goals::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .budget-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .budget-info small {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .budget-info i {
            font-size: 1rem;
        }
        @keyframes progressAnimation {
            from { width: 0; }
            to { width: var(--progress); }
        }
        .budget-progress .progress-bar {
            animation: progressAnimation 1.5s ease-out;
        }
        .category-list {
            max-height: 200px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .category-item {
            padding: 0.5rem;
            border-radius: 8px;
            background: #f8fafc;
        }
        .category-name {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .category-amount {
            font-weight: 600;
            color: #64748b;
        }
        .category-items {
            margin-top: 0.25rem;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .table {
            margin: 0;
        }
        .table th {
            background: #f8fafc;
            font-weight: 500;
            color: #64748b;
            padding: 1rem;
        }
        .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        .table tr:hover {
            background: #f8fafc;
        }
        .budget-goals-wrapper {
            height: 220px;
        }
        .budget-goals {
            height: 100%;
            overflow-y: auto;
            padding-right: 8px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }
        .budget-goals::-webkit-scrollbar {
            width: 4px;
        }
        .budget-goals::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .budget-goals::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .budget-goals::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .budget-item {
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 0.75rem;
        }
        .budget-item:last-child {
            margin-bottom: 0.75rem;
        }
        .budget-item h6 {
            font-size: 1rem;
        }
        .budget-meta {
            font-size: 0.9rem;
        }
        .budget-progress {
            margin: 0.75rem 0;
            height: 8px;
            background: #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .budget-progress .progress-bar {
            height: 100%;
            border-radius: 8px;
            transition: width 0.3s ease;
        }
        .budget-goals-wrapper {
            height: 300px;
        }
        .budget-goals {
            height: 100%;
            overflow-y: auto;
            padding-right: 8px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }
        .budget-goals::-webkit-scrollbar {
            width: 4px;
        }
        .budget-goals::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .budget-goals::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .budget-goals::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .budget-item {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.25rem;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .budget-item:last-child {
            margin-bottom: 1rem;
        }
        .budget-item h6 {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        .budget-meta {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .budget-progress {
            margin: 1rem 0;
            height: 10px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .budget-progress .progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .chart-container.category-chart {
            height: 250px;
            padding: 0;
            background: none;
            border: none;
            box-shadow: none;
        }
        .chart-legend-wrapper {
            margin-top: 1rem;
            width: 100%;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
            padding-bottom: 8px;
        }
        .chart-legend-wrapper::-webkit-scrollbar {
            height: 4px;
        }
        .chart-legend-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .chart-legend-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .chart-legend-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .chart-legend {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.75rem;
            padding: 0.5rem;
            width: max-content;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
            padding: 0.25rem 0.75rem;
            background: #f8fafc;
            border-radius: 6px;
            white-space: nowrap;
        }
        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 4px;
            display: inline-block;
            flex-shrink: 0;
        }
        .legend-label {
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1 bg-light">
            <div class="container-fluid py-4">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h2 class="welcome-text">Welcome back, <?php echo htmlspecialchars($user_name); ?>! </h2>
                    <p class="welcome-subtext">Here's what's happening with your finances today.</p>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                <i class='bx bx-trending-up'></i>
                            </div>
                            <div class="stat-info">
                                <h4>Total Income</h4>
                                <p class="text-success">₹<?php echo number_format($total_income, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                <i class='bx bx-trending-down'></i>
                            </div>
                            <div class="stat-info">
                                <h4>Total Expenses</h4>
                                <p class="text-danger">₹<?php echo number_format($total_expenses, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                                <i class='bx bx-wallet'></i>
                            </div>
                            <div class="stat-info">
                                <h4>Current Balance</h4>
                                <p class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    ₹<?php echo number_format(abs($balance), 2); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="chart-section">
                            <div class="chart-header">
                                <h5 class="chart-title">This Week's Overview</h5>
                                <a href="charts.php" class="btn btn-primary btn-sm">
                                    <i class='bx bx-line-chart'></i>
                                    View Detailed Charts
                                </a>
                            </div>
                            <div style="height: 300px;">
                                <canvas id="transactionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Budget Goals and Categories -->
                <div class="row g-4">
                    <!-- Budget Goals -->
                    <div class="col-md-6">
                        <div class="summary-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Budget Goals</h5>
                                <a href="budget_goals.php" class="btn btn-outline-primary btn-sm">View All</a>
                            </div>
                            <div class="budget-goals-wrapper">
                                <div class="budget-goals">
                                    <?php
                                    $sql = "SELECT 
                                        bg.id,
                                        bg.category,
                                        bg.amount as budget_amount,
                                        COALESCE(SUM(t.amount), 0) as spent_amount,
                                        COALESCE((SUM(t.amount) / bg.amount) * 100, 0) as progress,
                                        bg.start_date,
                                        bg.end_date,
                                        bg.period_type
                                    FROM budget_goals bg
                                    LEFT JOIN transactions t ON bg.category = t.category 
                                        AND t.user_id = bg.user_id 
                                        AND t.type = 'expense'
                                        AND t.date BETWEEN bg.start_date AND bg.end_date
                                    WHERE bg.user_id = ?
                                        AND bg.end_date >= CURDATE()
                                    GROUP BY bg.id, bg.category, bg.amount
                                    ORDER BY progress DESC
                                    LIMIT 3";
                                    
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("i", $user_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $actual_progress = round($row['progress'], 1);
                                            $display_progress = min(100, $actual_progress);
                                            // More granular color changes based on progress
                                            $progressColor = 
                                                $actual_progress > 100 ? '#EF4444' :    // Red for over budget
                                                ($actual_progress == 100 ? '#F97316' :  // Orange for at limit
                                                ($actual_progress >= 80 ? '#F97316' :   // Orange for very close
                                                ($actual_progress >= 50 ? '#F59E0B' :   // Amber for getting close
                                                '#10B981')));                           // Green for safe
                                            $iconClass = 
                                                $actual_progress > 100 ? 'bx-error-circle' :
                                                ($actual_progress == 100 ? 'bx-error' :
                                                ($actual_progress >= 80 ? 'bx-error' :
                                                ($actual_progress >= 50 ? 'bx-info-circle' :
                                                'bx-check-circle')));
                                    ?>
                                    <div class="budget-item mb-4" data-progress="<?php echo $actual_progress; ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="budget-icon">
                                                    <i class='bx <?php echo $iconClass; ?>' style="color: <?php echo $progressColor; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo ucfirst($row['category']); ?></h6>
                                                    <div class="budget-meta">
                                                        <span class="spent">₹<?php echo number_format($row['spent_amount'], 2); ?></span>
                                                        <span class="separator">/</span>
                                                        <span class="total">₹<?php echo number_format($row['budget_amount'], 2); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="budget-status" style="color: <?php echo $progressColor; ?>">
                                                <span class="progress-text"><?php echo $display_progress; ?>%</span>
                                            </div>
                                        </div>
                                        <div class="budget-progress-container">
                                            <div class="budget-progress">
                                                <div class="progress-bar" 
                                                     style="width: <?php echo $display_progress; ?>%; background-color: <?php echo $progressColor; ?>"
                                                     data-progress="<?php echo $actual_progress; ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="budget-info mt-2">
                                            <?php if ($actual_progress > 100): ?>
                                                <small class="text-danger">
                                                    <i class='bx bx-error'></i> Over budget! Consider adjusting spending.
                                                </small>
                                            <?php elseif ($actual_progress == 100): ?>
                                                <small class="text-orange" style="color: #F97316">
                                                    <i class='bx bx-error'></i> Budget limit reached! No more spending recommended.
                                                </small>
                                            <?php elseif ($actual_progress >= 80): ?>
                                                <small class="text-orange" style="color: #F97316">
                                                    <i class='bx bx-error'></i> Very close to budget limit!
                                                </small>
                                            <?php elseif ($actual_progress >= 50): ?>
                                                <small class="text-warning">
                                                    <i class='bx bx-info-circle'></i> Approaching budget limit.
                                                </small>
                                            <?php else: ?>
                                                <small class="text-success">
                                                    <i class='bx bx-check-circle'></i> On track with budget.
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php
                                        }
                                    } else {
                                    ?>
                                    <div class="text-center py-4">
                                        <i class='bx bx-target-lock fs-1 text-muted'></i>
                                        <p class="text-muted mb-0 mt-2">No budget goals set yet</p>
                                        <a href="budget_goals.php" class="btn btn-primary btn-sm mt-3">
                                            <i class='bx bx-plus-circle'></i> Set Budget Goals
                                        </a>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="col-md-6">
                        <div class="summary-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Today's Expenses by Category</h5>
                                <div class="chart-legend">
                                    <span class="badge bg-light text-dark">
                                        Total: ₹<?php echo number_format($total_spending, 2); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($total_spending > 0): ?>
                                <div class="category-list">
                                    <?php while ($category = $category_spending->fetch_assoc()): ?>
                                        <div class="category-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="category-name"><?php echo ucwords(str_replace('_', ' ', $category['category'])); ?></div>
                                                <div class="category-amount">₹<?php echo number_format(abs($category['total']), 2); ?></div>
                                            </div>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo (abs($category['total']) / $total_spending * 100); ?>%" 
                                                     aria-valuenow="<?php echo (abs($category['total']) / $total_spending * 100); ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No expenses recorded today.</p>
                            <?php endif; ?>
                            <?php if ($total_spending > 0): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="chart-container category-chart">
                                            <canvas id="categoryChart"></canvas>
                                            <div class="chart-legend-wrapper">
                                                <div class="chart-legend">
                                                    <?php 
                                                    $colors = ['#4F46E5', '#7C3AED', '#EC4899', '#F59E0B', '#10B981'];
                                                    for($i = 0; $i < count($categories); $i++): ?>
                                                        <div class="legend-item">
                                                            <span class="legend-color" style="background-color: <?php echo $colors[$i % count($colors)]; ?>"></span>
                                                            <span class="legend-label"><?php echo ucfirst($categories[$i]); ?></span>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="category-list">
                                            <?php foreach ($categories as $index => $category): ?>
                                                <div class="category-item mb-3">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="category-name">
                                                            <i class="bx bx-circle" style="color: <?php echo $colors[$index % count($colors)]; ?>"></i>
                                                            <?php echo htmlspecialchars($category); ?>
                                                        </span>
                                                        <span class="category-amount">₹<?php echo number_format($spending[$index], 2); ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class='bx bx-pie-chart-alt-2 fs-1 text-muted'></i>
                                    <p class="text-muted mb-0 mt-2">No expenses recorded today.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="summary-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Recent Transactions</h5>
                                <a href="transactions.php" class="btn btn-outline-primary btn-sm">View All</a>
                            </div>
                            <div class="recent-transactions">
                                <?php if (count($recent_transactions) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Category</th>
                                                    <th>Description</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_transactions as $transaction): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <i class='bx <?php echo $transaction['type'] === 'income' ? 'bx-up-arrow-circle text-success' : 'bx-down-arrow-circle text-danger'; ?> me-2'></i>
                                                                <?php echo date('M d, Y', strtotime($transaction['date'])); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark">
                                                                <?php echo htmlspecialchars($transaction['category']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                        <td class="text-end">
                                                            <span class="transaction-amount fw-medium <?php echo $transaction['type']; ?>">
                                                                <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?>₹<?php echo number_format($transaction['amount'], 2); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class='bx bx-receipt fs-1 text-muted'></i>
                                        <p class="text-muted mb-0 mt-2">No recent transactions found.</p>
                                        <a href="add_transaction.php" class="btn btn-primary btn-sm mt-3">Add Transaction</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <a href="add_transaction.php" class="floating-button">
        <i class='bx bx-plus'></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Transaction Chart
    const ctx = document.getElementById('transactionChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [{
                label: 'Income',
                data: <?php echo json_encode($incomes); ?>,
                backgroundColor: '#22c55e',
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            },
            {
                label: 'Expenses',
                data: <?php echo json_encode($expenses); ?>,
                backgroundColor: '#ef4444',
                borderRadius: 6,
                barPercentage: 0.6,
                categoryPercentage: 0.8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    grid: {
                        borderDash: [2, 2]
                    },
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });

    <?php if ($total_spending > 0): ?>
    const pieCtx = document.getElementById('categoryChart').getContext('2d');
    const categories = <?php echo $categories_json; ?>;
    const expenseAmounts = <?php echo $expense_amounts_json; ?>;
    const chartColors = <?php echo $colors_json; ?>;
    
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: categories,
            datasets: [{
                data: expenseAmounts,
                backgroundColor: chartColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '65%'
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>
