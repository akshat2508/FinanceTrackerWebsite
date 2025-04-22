<?php
if (!isset($user_name)) {
    // Get user's name if not already set
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $user_name = $user['username'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar p-3">
    <div class="d-flex align-items-center mb-4">
        <i class='bx bx-wallet fs-4 me-2'></i>
        <span class="fs-4">Finance Tracker</span>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class='bx bx-grid-alt'></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo in_array($current_page, ['transactions.php', 'add_transaction.php', 'edit_transaction.php']) ? 'active' : ''; ?>" href="transactions.php">
                <i class='bx bx-transfer'></i>
                <span>Transactions</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo in_array($current_page, ['budget_goals.php', 'add_budget_goal.php', 'edit_budget_goal.php']) ? 'active' : ''; ?>" href="budget_goals.php">
                <i class='bx bx-target-lock'></i>
                <span>Budget Goals</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'loan_calculator.php' ? 'active' : ''; ?>" href="loan_calculator.php">
                <i class='bx bx-calculator'></i>
                <span>Loan Calculator</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'charts.php' ? 'active' : ''; ?>" href="charts.php">
                <i class='bx bx-pie-chart-alt-2'></i>
                <span>Charts</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                <i class='bx bx-line-chart'></i>
                <span>Reports</span>
            </a>
        </li>
        <li class="nav-item mt-auto">
            <a class="nav-link" href="logout.php">
                <i class='bx bx-log-out'></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>
