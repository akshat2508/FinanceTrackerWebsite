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

// Get user's name
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_name = $user['username'];

// Get period from URL parameter (default to month)
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Get user's account creation date
$stmt = $conn->prepare("SELECT MIN(date) as start_date FROM transactions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$account_start = $result->fetch_assoc()['start_date'];
$account_start_date = new DateTime($account_start);

// Calculate date ranges based on period
$end_date = new DateTime();
$end_date->modify($offset . ' ' . $period);

switch ($period) {
    case 'week':
        // Get to current week's Monday
        $start_date = clone $end_date;
        $current_day = $start_date->format('N'); // 1 (Monday) through 7 (Sunday)
        if ($current_day != 1) {
            // If not Monday, move back to Monday
            $days_to_monday = $current_day - 1;
            $start_date->modify("-{$days_to_monday} days");
        }
        $end_date = clone $start_date;
        $end_date->modify('+6 days'); // End on Sunday
        $period_label = $start_date->format('M d') . ' - ' . $end_date->format('M d, Y');
        $group_by = 'DATE(date)';
        $interval = 'day';
        break;
    case 'month':
        // Start from 1st of the month
        $start_date = clone $end_date;
        $start_date->modify('first day of this month');
        $end_date->modify('last day of this month');
        $period_label = $end_date->format('F Y');
        $group_by = 'DATE(date)';
        $interval = 'day';
        break;
    case 'year':
        $start_date = clone $end_date;
        $start_date->modify('first day of January this year');
        $end_date->modify('last day of December this year');
        $period_label = $end_date->format('Y');
        $group_by = 'MONTH(date)';
        $interval = 'month';
        break;
}

// Calculate previous and next offsets
$prev_offset = $offset - 1;
$next_offset = $offset + 1;

// Get initial balance (all transactions before start date)
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance
    FROM transactions 
    WHERE user_id = ? 
    AND date < ?
");
$start_date_str = $start_date->format('Y-m-d');
$stmt->bind_param("is", $user_id, $start_date_str);
$stmt->execute();
$result = $stmt->get_result();
$initial_balance = $result->fetch_assoc()['balance'];

// Generate complete date range first
$date_range = array();
$daily_balances = array();
$current_balance = $initial_balance;

if ($period === 'year') {
    // For yearly view, generate all months
    for ($i = 1; $i <= 12; $i++) {
        $month_date = clone $start_date;
        $month_date->setDate($month_date->format('Y'), $i, 1);
        $date_range[] = $month_date->format('M');
        $daily_balances[$month_date->format('Y-m')] = $current_balance;
    }
} else if ($period === 'month') {
    // For month view, first get all transaction dates
    $stmt = $conn->prepare("
        SELECT date, type, amount
        FROM transactions 
        WHERE user_id = ? 
        AND date BETWEEN ? AND ?
        ORDER BY date ASC
    ");
    
    $end_date_formatted = $end_date->format('Y-m-d');
    $stmt->bind_param("iss", $user_id, $start_date_str, $end_date_formatted);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Track transactions by date and calculate running balance
    $transactions_by_date = array();
    $running_balance = $initial_balance;
    $dates_to_show = array();
    
    while ($row = $result->fetch_assoc()) {
        $date = substr($row['date'], 0, 10); // Get just the date part
        
        if (!isset($transactions_by_date[$date])) {
            $transactions_by_date[$date] = array(
                'transactions' => array(),
                'balance' => $running_balance
            );
        }
        
        $amount = floatval($row['amount']);
        $running_balance += ($row['type'] === 'income' ? $amount : -$amount);
        
        $transactions_by_date[$date]['transactions'][] = $row;
        $transactions_by_date[$date]['balance'] = $running_balance;
        
        // Mark this date for display
        $dates_to_show[$date] = true;
        
        // Add surrounding days
        $current_date = new DateTime($date);
        
        // Add 2 days before
        $before = clone $current_date;
        for ($i = 1; $i <= 2; $i++) {
            $before->modify('-1 day');
            $before_str = $before->format('Y-m-d');
            if ($before >= $start_date) {
                $dates_to_show[$before_str] = true;
            }
        }
        
        // Add 2 days after
        $after = clone $current_date;
        for ($i = 1; $i <= 2; $i++) {
            $after->modify('+1 day');
            $after_str = $after->format('Y-m-d');
            if ($after <= $end_date) {
                $dates_to_show[$after_str] = true;
            }
        }
    }
    
    if (count($dates_to_show) > 0) {
        // Sort dates
        $display_dates = array_keys($dates_to_show);
        sort($display_dates);
        
        // Generate the date range and balances
        $running_balance = $initial_balance;
        foreach ($display_dates as $date_str) {
            $current_date = new DateTime($date_str);
            $date_range[] = $current_date->format('M d');
            
            // If this date has transactions, use its final balance
            if (isset($transactions_by_date[$date_str])) {
                $running_balance = $transactions_by_date[$date_str]['balance'];
            }
            
            $daily_balances[$date_str] = $running_balance;
        }
    } else {
        // If no transactions, show just a week
        $current_date = clone $start_date;
        for ($i = 0; $i < 7; $i++) {
            $date_str = $current_date->format('Y-m-d');
            $date_range[] = $current_date->format('M d');
            $daily_balances[$date_str] = $initial_balance;
            $current_date->modify('+1 day');
        }
    }
} else {
    // For week view, show all 7 days
    $current_date = clone $start_date;
    while ($current_date <= $end_date) {
        $date_str = $current_date->format('Y-m-d');
        $date_range[] = $current_date->format('M d');
        
        // If this date is before account creation, use initial balance
        if ($current_date < $account_start_date) {
            $daily_balances[$date_str] = 0;
        } else {
            $daily_balances[$date_str] = $current_balance;
        }
        
        $current_date->modify('+1 day');
    }
}

// Get all transactions for the period to calculate running balance
$date_select = $period === 'year' ? "DATE_FORMAT(date, '%Y-%m')" : "date";
$stmt = $conn->prepare("
    SELECT 
        $date_select as date_group,
        type,
        amount
    FROM transactions 
    WHERE user_id = ? 
    AND date BETWEEN ? AND ?
    ORDER BY date ASC
");

$end_date_str = $end_date->format('Y-m-d');
$stmt->bind_param("iss", $user_id, $start_date_str, $end_date_str);
$stmt->execute();
$result = $stmt->get_result();

// Update balances with actual transactions
while ($row = $result->fetch_assoc()) {
    $amount = floatval($row['amount']);
    $date_str = $row['date_group'];
    
    // Update balance for this period and all future periods
    if (isset($daily_balances[$date_str])) {
        $current_balance += ($row['type'] === 'income' ? $amount : -$amount);
        $daily_balances[$date_str] = $current_balance;
        
        // Update all future dates with new balance
        if ($period === 'year') {
            $current_month = substr($date_str, -2);
            for ($i = intval($current_month) + 1; $i <= 12; $i++) {
                $next_month = sprintf("%s-%02d", substr($date_str, 0, 4), $i);
                if (isset($daily_balances[$next_month])) {
                    $daily_balances[$next_month] = $current_balance;
                }
            }
        } else {
            $temp_date = new DateTime($date_str);
            $temp_date->modify('+1 ' . $interval);
            $end_date_copy = clone $end_date;
            while ($temp_date <= $end_date_copy) {
                $temp_str = $temp_date->format('Y-m-d');
                if (isset($daily_balances[$temp_str])) {
                    $daily_balances[$temp_str] = $current_balance;
                }
                $temp_date->modify('+1 ' . $interval);
            }
        }
    }
}

// Get transactions grouped by date for income/expense chart
$group_select = $period === 'year' ? "MONTH(date)" : $group_by;
$stmt = $conn->prepare("
    SELECT 
        $group_select as date_group,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
    FROM transactions 
    WHERE user_id = ? 
    AND date BETWEEN ? AND ?
    GROUP BY $group_select
    ORDER BY date_group ASC
");

$stmt->bind_param("iss", $user_id, $start_date_str, $end_date_str);
$stmt->execute();
$result = $stmt->get_result();

$date_keys = array_keys($daily_balances);
$incomes = array_fill(0, count($date_range), 0);
$expenses = array_fill(0, count($date_range), 0);
$balances = array_values($daily_balances);

$total_income = 0;
$total_expense = 0;

while ($row = $result->fetch_assoc()) {
    $income = floatval($row['income']);
    $expense = floatval($row['expense']);
    
    if ($period === 'year') {
        $month_index = intval($row['date_group']) - 1; // Months are 1-based
        if ($month_index >= 0 && $month_index < 12) {
            $incomes[$month_index] = $income;
            $expenses[$month_index] = $expense;
        }
    } else {
        $date_index = array_search($row['date_group'], $date_keys);
        if ($date_index !== false) {
            $incomes[$date_index] = $income;
            $expenses[$date_index] = $expense;
        }
    }
    
    $total_income += $income;
    $total_expense += $expense;
}

$labels = $date_range;
$net_change = $total_income - $total_expense;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charts - Finance Tracker</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .period-nav {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: 400px;
        }
        .nav-pills .nav-link {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: #6b7280;
            font-weight: 500;
        }
        .nav-pills .nav-link.active {
            background-color: #4F46E5;
            color: white;
        }
        .period-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .period-selector .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        .period-label {
            font-weight: 600;
            color: #111827;
            font-size: 1.1rem;
            margin: 0;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        .stat-card h5 {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        .stat-card p {
            color: #111827;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php 
        require_once 'config/database.php';
        include 'includes/sidebar.php'; 
        ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1 bg-light">
            <div class="container-fluid py-4">
                <div class="period-nav">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <ul class="nav nav-pills">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $period === 'week' ? 'active' : ''; ?>" 
                                   href="?period=week">Weekly</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $period === 'month' ? 'active' : ''; ?>" 
                                   href="?period=month">Monthly</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $period === 'year' ? 'active' : ''; ?>" 
                                   href="?period=year">Yearly</a>
                            </li>
                        </ul>
                        <div class="period-selector">
                            <a href="?period=<?php echo $period; ?>&offset=<?php echo $prev_offset; ?>" 
                               class="btn btn-light">
                                <i class='bx bx-chevron-left'></i>
                            </a>
                            <h5 class="period-label"><?php echo $period_label; ?></h5>
                            <a href="?period=<?php echo $period; ?>&offset=<?php echo $next_offset; ?>" 
                               class="btn btn-light" <?php echo $next_offset >= 0 ? 'disabled' : ''; ?>>
                                <i class='bx bx-chevron-right'></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="stats-container">
                    <div class="stat-card">
                        <h5>Total Income</h5>
                        <p class="text-success">₹<?php echo number_format($total_income, 2); ?></p>
                    </div>
                    <div class="stat-card">
                        <h5>Total Expenses</h5>
                        <p class="text-danger">₹<?php echo number_format($total_expense, 2); ?></p>
                    </div>
                    <div class="stat-card">
                        <h5>Net Change</h5>
                        <p class="<?php echo $net_change >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $net_change >= 0 ? '+' : ''; ?>₹<?php echo number_format($net_change, 2); ?>
                        </p>
                    </div>
                </div>

                <div class="chart-container">
                    <canvas id="transactionChart"></canvas>
                </div>

                <div class="chart-container">
                    <canvas id="balanceChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Transaction Chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Income',
                    data: <?php echo json_encode($incomes); ?>,
                    backgroundColor: '#22c55e',
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }, {
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
                    },
                    title: {
                        display: true,
                        text: 'Income vs Expenses',
                        color: '#111827',
                        font: {
                            size: 16,
                            weight: '600'
                        },
                        padding: {
                            bottom: 24
                        }
                    }
                }
            }
        });

        // Balance Chart
        const balanceCtx = document.getElementById('balanceChart').getContext('2d');
        new Chart(balanceCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Balance',
                    data: <?php echo json_encode($balances); ?>,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#4F46E5'
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
                    },
                    title: {
                        display: true,
                        text: 'Balance Over Time',
                        color: '#111827',
                        font: {
                            size: 16,
                            weight: '600'
                        },
                        padding: {
                            bottom: 24
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
