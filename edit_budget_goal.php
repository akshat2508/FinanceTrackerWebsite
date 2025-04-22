<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get distinct categories for suggestions
$stmt = $conn->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = ? ORDER BY category ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = array();
while ($row = $result->fetch_assoc()) {
    // Format category with first letter uppercase
    $categories[] = ucfirst($row['category']);
}

// Get budget goal details
if (isset($_GET['id'])) {
    $goal_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM budget_goals WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $goal_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: budget_goals.php');
        exit();
    }
    
    $goal = $result->fetch_assoc();
    
    // Ensure all required fields exist
    if (!isset($goal['category']) || !isset($goal['amount']) || !isset($goal['period_type']) || !isset($goal['start_date']) || !isset($goal['end_date'])) {
        header('Location: budget_goals.php');
        exit();
    }
} else {
    header('Location: budget_goals.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = strtolower(trim($_POST['category']));
    $amount = $_POST['amount'];
    $period_type = $_POST['period_type'];
    $start_date = $_POST['start_date'];
    
    // Calculate end date based on period type
    $end_date = date('Y-m-d', strtotime($start_date));
    switch($period_type) {
        case 'weekly':
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 week -1 day'));
            break;
        case 'monthly':
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 month -1 day'));
            break;
        case 'yearly':
            $end_date = date('Y-m-d', strtotime($start_date . ' +1 year -1 day'));
            break;
    }

    // Check for existing goals in the same category and overlapping period
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM budget_goals 
        WHERE user_id = ? 
        AND category = ? 
        AND period_type = ?
        AND id != ?
        AND (
            (? BETWEEN start_date AND end_date) OR
            (? BETWEEN start_date AND end_date)
        )
    ");
    $stmt->bind_param("ississ", $user_id, $category, $period_type, $goal_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();

    if ($existing['count'] > 0) {
        $error_message = "A budget goal for '{$category}' already exists in this {$period_type} period.";
    } else {
        // Validate inputs
        $errors = [];
        if (empty($category)) {
            $errors[] = "Please enter a category.";
        }
        if ($amount <= 0) {
            $errors[] = "Please enter a valid amount greater than 0.";
        }
        if (empty($period_type)) {
            $errors[] = "Please select a period type.";
        }
        if (empty($start_date)) {
            $errors[] = "Please select a start date.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE budget_goals SET category = ?, amount = ?, period_type = ?, start_date = ?, end_date = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sdsssii", $category, $amount, $period_type, $start_date, $end_date, $goal_id, $user_id);

            if ($stmt->execute()) {
                header('Location: budget_goals.php');
                exit();
            } else {
                $error_message = "Error updating budget goal: " . $conn->error;
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Budget Goal - Finance Tracker</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .content-wrapper {
            width: 100%;
            max-width: 800px;
        }
        .summary-card {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
            color: #6b7280;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .period-selector button:hover {
            border-color: #6366f1;
            color: #6366f1;
        }
        .period-selector button.active {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
        }
        .period-selector button i {
            font-size: 1.25rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-size: 0.875rem;
        }
        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .category-input {
            position: relative;
        }
        .category-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: none;
        }
        .category-suggestions.show {
            display: block;
        }
        .category-suggestion {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        .category-suggestion:hover {
            background-color: #f8f9fa;
        }
            width: 100%;
            padding: 0.625rem 1rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        #categoryInput:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            outline: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="summary-card">
                        <h2 class="mb-4">Edit Budget Goal</h2>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <div class="category-input">
                                    <input type="text" 
                                           class="form-control" 
                                           id="categoryInput"
                                           name="category"
                                           pattern="[A-Za-z\s\-&]+"
                                           title="Only letters, spaces, hyphens and ampersands are allowed"
                                           placeholder="Enter or select category"
                                           autocomplete="off"
                                           value="<?php echo ucfirst($goal['category']); ?>"
                                           required>
                                    <div class="category-suggestions" id="categorySuggestions">
                                        <?php foreach ($categories as $cat): ?>
                                            <div class="category-suggestion" data-value="<?php echo htmlspecialchars($cat); ?>">
                                                <?php echo htmlspecialchars($cat); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Amount</label>
                                <input type="number" class="form-control" name="amount" step="0.01" value="<?php echo $goal['amount']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Period Type</label>
                                <div class="period-selector">
                                    <button type="button" data-period="weekly" <?php echo $goal['period_type'] === 'weekly' ? 'class="active"' : ''; ?>>
                                        <i class='bx bx-calendar-week'></i>Weekly
                                    </button>
                                    <button type="button" data-period="monthly" <?php echo $goal['period_type'] === 'monthly' ? 'class="active"' : ''; ?>>
                                        <i class='bx bx-calendar'></i>Monthly
                                    </button>
                                    <button type="button" data-period="yearly" <?php echo $goal['period_type'] === 'yearly' ? 'class="active"' : ''; ?>>
                                        <i class='bx bx-calendar-star'></i>Yearly
                                    </button>
                                </div>
                                <input type="hidden" name="period_type" id="period_type" value="<?php echo $goal['period_type']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $goal['start_date']; ?>" required>
                            </div>
                            <div class="col-12 d-flex justify-content-between mt-4">
                                <a href="budget_goals.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </a>
                                <button type="submit" class="btn btn-primary">Update Budget Goal</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const periodButtons = document.querySelectorAll('.period-selector button');
            const periodInput = document.getElementById('period_type');

            periodButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all buttons
                    periodButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    button.classList.add('active');
                    // Update hidden input
                    periodInput.value = button.dataset.period;
                });
            });

            const categoryInput = document.getElementById('categoryInput');
            const suggestions = document.getElementById('categorySuggestions');
            const suggestionItems = document.querySelectorAll('.category-suggestion');

            function filterSuggestions(value) {
                const searchTerm = value.toLowerCase();
                suggestionItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? 'block' : 'none';
                });
            }

            function validateCategoryInput(input) {
                return input.replace(/[^A-Za-z\s\-&]/g, '');
            }

            categoryInput.addEventListener('input', (e) => {
                const validatedValue = validateCategoryInput(e.target.value);
                if (validatedValue !== e.target.value) {
                    e.target.value = validatedValue;
                }
                
                filterSuggestions(validatedValue);
                suggestions.classList.add('show');
            });

            categoryInput.addEventListener('focus', () => {
                // Show all suggestions on first focus
                suggestionItems.forEach(item => {
                    item.style.display = 'block';
                });
                suggestions.style.display = 'block';
            });

            document.addEventListener('click', (e) => {
                if (!categoryInput.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.classList.remove('show');
                    suggestions.style.display = 'none';
                }
            });

            suggestionItems.forEach(item => {
                item.addEventListener('click', () => {
                    categoryInput.value = item.textContent.trim();
                    suggestions.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>
