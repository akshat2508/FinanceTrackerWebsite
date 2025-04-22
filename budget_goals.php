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

$success_message = '';
$error_message = '';

// Remove expired budget goals
$stmt = $conn->prepare("DELETE FROM budget_goals WHERE user_id = ? AND end_date < CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $category = $_POST['category'];
                $amount = $_POST['amount'];
                $period_type = $_POST['period_type'];
                $start_date = $_POST['start_date'];
                
                // Calculate end date based on period type
                $end_date = date('Y-m-d', strtotime($start_date));
                switch ($period_type) {
                    case 'weekly':
                        $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
                        break;
                    case 'monthly':
                        $end_date = date('Y-m-d', strtotime($start_date . ' +1 month -1 day'));
                        break;
                    case 'yearly':
                        $end_date = date('Y-m-d', strtotime($start_date . ' +1 year -1 day'));
                        break;
                }
                
                $stmt = $conn->prepare("INSERT INTO budget_goals (
                    user_id, category, amount, period_type, start_date, end_date
                ) VALUES (?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("isdsss", 
                    $user_id, $category, $amount, $period_type, $start_date, $end_date
                );
                
                if ($stmt->execute()) {
                    $success_message = "Budget goal added successfully!";
                } else {
                    $error_message = "Error adding budget goal: " . $conn->error;
                }
                break;
                
            case 'delete':
                $goal_id = $_POST['goal_id'];
                $stmt = $conn->prepare("DELETE FROM budget_goals WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $goal_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Budget goal deleted successfully!";
                } else {
                    $error_message = "Error deleting budget goal: " . $conn->error;
                }
                break;
        }
    }
}

// Get all categories
$stmt = $conn->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = ? ORDER BY category");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result();

// Function to get budget goals for a specific period type
function getBudgetGoals($conn, $user_id, $period_type) {
    $stmt = $conn->prepare("
        SELECT 
            bg.*,
            COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as spent_amount
        FROM budget_goals bg
        LEFT JOIN transactions t ON bg.category = t.category 
            AND t.user_id = bg.user_id 
            AND t.type = 'expense'
            AND t.date BETWEEN bg.start_date AND bg.end_date
        WHERE bg.user_id = ? 
        AND bg.period_type = ?
        AND bg.end_date >= CURDATE()
        GROUP BY bg.id
        ORDER BY bg.start_date ASC, bg.category
    ");
    
    $stmt->bind_param("is", $user_id, $period_type);
    $stmt->execute();
    return $stmt->get_result();
}

// Get budget goals for each period type
$weekly_goals = getBudgetGoals($conn, $user_id, 'weekly');
$monthly_goals = getBudgetGoals($conn, $user_id, 'monthly');
$yearly_goals = getBudgetGoals($conn, $user_id, 'yearly');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Goals - Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            min-height: 100vh;
            padding: 2rem;
        }
        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }
        .summary-card {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .budget-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .budget-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .category-badge {
            background: #f3f4f6;
            color: #4b5563;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .alert-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
        }
        .alert-warning {
            background: #fef3c7;
            color: #d97706;
        }
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .alert-warning-dark {
            background: #FED7AA;
            color: #C2410C;
        }
        .budget-details {
            margin-top: 0.5rem;
        }
        .amount-row {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .main-amount {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
        }
        .spent-details {
            display: flex;
            gap: 1.5rem;
            font-size: 0.875rem;
        }
        .date-badge {
            background: #f3f4f6;
            color: #6b7280;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
        }
        .progress-section {
            margin-top: 1rem;
        }
        .progress {
            height: 0.5rem;
            border-radius: 1rem;
            background: #f3f4f6;
            margin-bottom: 0.5rem;
        }
        .progress-bar {
            border-radius: 1rem;
            transition: width 0.3s ease;
        }
        .progress-label {
            font-size: 0.75rem;
            font-weight: 500;
            text-align: right;
        }
        .period-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .period-selector button {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: 0.5rem;
            font-weight: 500;
            color: #4b5563;
            transition: all 0.2s ease;
        }
        .period-selector button.active {
            background-color: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
        }
        .period-selector button:hover:not(.active) {
            background-color: #f3f4f6;
        }
        .add-goal-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            background-color: #3b82f6;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
        }
        .add-goal-btn:hover {
            transform: scale(1.1);
            background-color: #2563eb;
        }
        .modal-content {
            border-radius: 1rem;
            border: none;
        }
        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid #e5e7eb;
            padding: 1.5rem;
        }
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }
        .budget-stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .budget-stat-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .stat-icon {
            font-size: 2rem;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 10px;
        }
        .stat-label {
            color: #4b5563;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        .budget-info-section {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1.5rem;
        }
        .info-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            height: 100%;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .info-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .info-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .info-list li {
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #4b5563;
        }
        .info-list li:last-child {
            margin-bottom: 0;
        }
        .category-stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            height: 100%;
        }
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .active-badge {
            background: #dcfce7;
            color: #16a34a;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .category-stats {
            display: grid;
            gap: 1rem;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .health-card {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        .health-score {
            text-align: center;
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 8px solid currentColor;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .score-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }
        .score-label {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        .health-stats {
            flex: 1;
            display: grid;
            gap: 1.5rem;
        }
        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        .bg-warning-dark {
            background-color: #F97316;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-wrapper">
                <div class="summary-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">Budget Goals</h4>
                        <a href="add_budget_goal.php" class="btn btn-primary">
                            <i class='bx bx-plus me-1'></i>Add New Goal
                        </a>
                    </div>

                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="period-selector">
                        <button class="active" data-period="weekly">Weekly</button>
                        <button data-period="monthly">Monthly</button>
                        <button data-period="yearly">Yearly</button>
                    </div>

                    <div id="weekly-goals" class="budget-period active">
                        <?php while ($goal = $weekly_goals->fetch_assoc()): 
                            // Initialize variables
                            $progress = 0;
                            $spent_percentage = 0;
                            $residual = $goal['amount'] - $goal['spent_amount'];

                            // Safe calculation of progress
                            if ($goal['amount'] > 0) {
                                $progress = ($goal['spent_amount'] / $goal['amount']) * 100;
                                $spent_percentage = $progress;
                            }
                            
                            // Determine progress class and message based on specific conditions
                            if ($progress > 100) {
                                $progress_class = 'danger';
                                $alert_message = 'Over budget! Consider adjusting spending.';
                                $icon_class = 'bx-error-circle';
                                $color = '#EF4444';
                            } elseif ($progress == 100) {
                                $progress_class = 'warning-dark';
                                $alert_message = 'Budget limit reached! No more spending recommended.';
                                $icon_class = 'bx-error';
                                $color = '#F97316';
                            } elseif ($progress >= 80) {
                                $progress_class = 'warning-dark';
                                $alert_message = 'Very close to budget limit!';
                                $icon_class = 'bx-error';
                                $color = '#F97316';
                            } elseif ($progress >= 50) {
                                $progress_class = 'warning';
                                $alert_message = 'Approaching budget limit.';
                                $icon_class = 'bx-info-circle';
                                $color = '#F59E0B';
                            } else {
                                $progress_class = 'success';
                                $alert_message = 'On track with budget.';
                                $icon_class = 'bx-check-circle';
                                $color = '#10B981';
                            }
                        ?>
                        <div class="budget-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="category-badge"><?php echo ucfirst($goal['category']); ?></span>
                                        <span class="alert-badge alert-<?php echo $progress_class; ?>">
                                            <i class='bx <?php echo $icon_class; ?> me-1'></i><?php echo $alert_message; ?>
                                        </span>
                                    </div>
                                    <div class="budget-details">
                                        <div class="amount-row">
                                            <div class="budget-amount">₹<?php echo number_format($goal['amount'], 2); ?></div>
                                            <div class="spent-details">
                                                <div class="spent" style="color: <?php echo $color; ?>">
                                                    <i class='bx bx-chart me-1'></i>
                                                    Spent: ₹<?php echo number_format($goal['spent_amount'], 2); ?>
                                                </div>
                                                <div class="remaining">
                                                    <i class='bx bx-wallet me-1'></i>
                                                    Remaining: ₹<?php echo number_format($residual, 2); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end">
                                    <div class="btn-group">
                                        <a href="edit_budget_goal.php?id=<?php echo $goal['id']; ?>" class="btn btn-link text-primary p-0 me-2">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <button class="btn btn-link text-danger p-0" onclick="deleteGoal(<?php echo $goal['id']; ?>)">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                    <div class="date-badge mt-2">
                                        <i class='bx bx-calendar me-1'></i>
                                        <?php echo date('M d', strtotime($goal['start_date'])); ?> - 
                                        <?php echo date('M d', strtotime($goal['end_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="progress-section">
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $progress_class; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min($progress, 100); ?>%; background-color: <?php echo $color; ?>" 
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <div class="progress-label" style="color: <?php echo $color; ?>">
                                    <?php echo number_format($progress, 1); ?>% Used
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <div id="monthly-goals" class="budget-period d-none">
                        <?php while ($goal = $monthly_goals->fetch_assoc()): 
                            // Initialize variables
                            $progress = 0;
                            $spent_percentage = 0;
                            $residual = $goal['amount'] - $goal['spent_amount'];

                            // Safe calculation of progress
                            if ($goal['amount'] > 0) {
                                $progress = ($goal['spent_amount'] / $goal['amount']) * 100;
                                $spent_percentage = $progress;
                            }
                            
                            // Determine progress class and message based on specific conditions
                            if ($progress > 100) {
                                $progress_class = 'danger';
                                $alert_message = 'Over budget! Consider adjusting spending.';
                                $icon_class = 'bx-error-circle';
                                $color = '#EF4444';
                            } elseif ($progress == 100) {
                                $progress_class = 'warning-dark';
                                $alert_message = 'Budget limit reached! No more spending recommended.';
                                $icon_class = 'bx-error';
                                $color = '#F97316';
                            } elseif ($progress >= 80) {
                                $progress_class = 'warning-dark';
                                $alert_message = 'Very close to budget limit!';
                                $icon_class = 'bx-error';
                                $color = '#F97316';
                            } elseif ($progress >= 50) {
                                $progress_class = 'warning';
                                $alert_message = 'Approaching budget limit.';
                                $icon_class = 'bx-info-circle';
                                $color = '#F59E0B';
                            } else {
                                $progress_class = 'success';
                                $alert_message = 'On track with budget.';
                                $icon_class = 'bx-check-circle';
                                $color = '#10B981';
                            }
                        ?>
                        <div class="budget-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="category-badge"><?php echo ucfirst($goal['category']); ?></span>
                                        <span class="alert-badge alert-<?php echo $progress_class; ?>">
                                            <i class='bx <?php echo $icon_class; ?> me-1'></i><?php echo $alert_message; ?>
                                        </span>
                                    </div>
                                    <div class="budget-details">
                                        <div class="amount-row">
                                            <div class="budget-amount">₹<?php echo number_format($goal['amount'], 2); ?></div>
                                            <div class="spent-details">
                                                <div class="spent" style="color: <?php echo $color; ?>">
                                                    <i class='bx bx-chart me-1'></i>
                                                    Spent: ₹<?php echo number_format($goal['spent_amount'], 2); ?>
                                                </div>
                                                <div class="remaining">
                                                    <i class='bx bx-wallet me-1'></i>
                                                    Remaining: ₹<?php echo number_format($residual, 2); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end">
                                    <div class="btn-group">
                                        <a href="edit_budget_goal.php?id=<?php echo $goal['id']; ?>" class="btn btn-link text-primary p-0 me-2">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <button class="btn btn-link text-danger p-0" onclick="deleteGoal(<?php echo $goal['id']; ?>)">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                    <div class="date-badge mt-2">
                                        <i class='bx bx-calendar me-1'></i>
                                        <?php echo date('F Y', strtotime($goal['start_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="progress-section">
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $progress_class; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min($progress, 100); ?>%; background-color: <?php echo $color; ?>" 
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <div class="progress-label" style="color: <?php echo $color; ?>">
                                    <?php echo number_format($progress, 1); ?>% Used
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <div id="yearly-goals" class="budget-period d-none">
                        <?php while ($goal = $yearly_goals->fetch_assoc()): 
                            // Initialize variables
                            $progress = 0;
                            $spent_percentage = 0;
                            $residual = $goal['amount'] - $goal['spent_amount'];

                            // Safe calculation of progress
                            if ($goal['amount'] > 0) {
                                $progress = ($goal['spent_amount'] / $goal['amount']) * 100;
                                $spent_percentage = $progress;
                            }
                            
                            // Determine progress class and message based on specific conditions
                            if ($progress > 100) {
                                $progress_class = 'danger';
                                $alert_message = 'Over budget! Consider adjusting spending.';
                                $icon_class = 'bx-error-circle';
                                $color = '#EF4444';
                            } elseif ($progress == 100) {
                                $progress_class = 'warning-dark';
                                $alert_message = 'Budget limit reached! No more spending recommended.';
                                $icon_class = 'bx-error';
                                $color = '#F97316';
                            } elseif ($progress >= 80) {
                                $progress_class = 'warning-dark';
                                $alert_message = 'Very close to budget limit!';
                                $icon_class = 'bx-error';
                                $color = '#F97316';
                            } elseif ($progress >= 50) {
                                $progress_class = 'warning';
                                $alert_message = 'Approaching budget limit.';
                                $icon_class = 'bx-info-circle';
                                $color = '#F59E0B';
                            } else {
                                $progress_class = 'success';
                                $alert_message = 'On track with budget.';
                                $icon_class = 'bx-check-circle';
                                $color = '#10B981';
                            }
                        ?>
                        <div class="budget-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="category-badge"><?php echo ucfirst($goal['category']); ?></span>
                                        <span class="alert-badge alert-<?php echo $progress_class; ?>">
                                            <i class='bx <?php echo $icon_class; ?> me-1'></i><?php echo $alert_message; ?>
                                        </span>
                                    </div>
                                    <div class="budget-details">
                                        <div class="amount-row">
                                            <div class="budget-amount">₹<?php echo number_format($goal['amount'], 2); ?></div>
                                            <div class="spent-details">
                                                <div class="spent" style="color: <?php echo $color; ?>">
                                                    <i class='bx bx-chart me-1'></i>
                                                    Spent: ₹<?php echo number_format($goal['spent_amount'], 2); ?>
                                                </div>
                                                <div class="remaining">
                                                    <i class='bx bx-wallet me-1'></i>
                                                    Remaining: ₹<?php echo number_format($residual, 2); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end">
                                    <div class="btn-group">
                                        <a href="edit_budget_goal.php?id=<?php echo $goal['id']; ?>" class="btn btn-link text-primary p-0 me-2">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <button class="btn btn-link text-danger p-0" onclick="deleteGoal(<?php echo $goal['id']; ?>)">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                    <div class="date-badge mt-2">
                                        <i class='bx bx-calendar me-1'></i>
                                        <?php echo date('Y', strtotime($goal['start_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="progress-section">
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $progress_class; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min($progress, 100); ?>%; background-color: <?php echo $color; ?>" 
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <div class="progress-label" style="color: <?php echo $color; ?>">
                                    <?php echo number_format($progress, 1); ?>% Used
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Period selector functionality
            const periodButtons = document.querySelectorAll('.period-selector button');
            const periodSections = document.querySelectorAll('.budget-period');

            periodButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Update active button
                    periodButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');

                    // Show selected period's goals
                    const period = button.dataset.period;
                    periodSections.forEach(section => {
                        if (section.id === `${period}-goals`) {
                            section.classList.remove('d-none');
                        } else {
                            section.classList.add('d-none');
                        }
                    });
                });
            });

            // Delete confirmation
            window.deleteGoal = function(goalId) {
                if (confirm('Are you sure you want to delete this budget goal?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="goal_id" value="${goalId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            };
        });
    </script>
</body>
</html>
