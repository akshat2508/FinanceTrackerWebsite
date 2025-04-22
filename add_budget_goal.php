<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's name
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_name = $user['username'];

$error_message = '';
$success_message = '';

// Get distinct categories for suggestions
$stmt = $conn->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = ? AND type = 'expense' ORDER BY category ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = array();
while ($row = $result->fetch_assoc()) {
    // Format category with first letter uppercase
    $categories[] = ucfirst($row['category']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Convert category to lowercase for storage
    $category = strtolower(trim($_POST['category']));
    $amount = $_POST['amount'];
    $period_type = $_POST['period_type'];
    $start_date = $_POST['start_date'];

    // Get user's account creation date
    $stmt = $conn->prepare("SELECT DATE(created_at) as created_date FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if (strtotime($start_date) < strtotime($user_data['created_date'])) {
        $error_message = "Cannot create budget goals before your account creation date (" . date('M d, Y', strtotime($user_data['created_date'])) . ").";
    } else {
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
            AND (
                (? BETWEEN start_date AND end_date) OR
                (? BETWEEN start_date AND end_date)
            )
        ");
        $stmt->bind_param("issss", $user_id, $category, $period_type, $start_date, $end_date);
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
                $stmt = $conn->prepare("INSERT INTO budget_goals (user_id, category, amount, period_type, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdsss", $user_id, $category, $amount, $period_type, $start_date, $end_date);

                if ($stmt->execute()) {
                    $success_message = "Budget goal added successfully!";
                    // Clear form data
                    $_POST = array();
                } else {
                    $error_message = "Error adding budget goal. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Budget Goal - Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css">
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
                        <h4 class="mb-0">Add Budget Goal</h4>
                    </div>

                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <div class="col-12">
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
                                               required>
                                        <div class="category-suggestions" id="categorySuggestions">
                                            <?php foreach ($categories as $category): ?>
                                                <div class="category-suggestion" data-value="<?php echo htmlspecialchars($category); ?>">
                                                    <?php echo htmlspecialchars($category); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Budget Amount (₹)</label>
                                    <input type="number" class="form-control" name="amount" step="0.01" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" required
                                           value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Period Type</label>
                                    <div class="period-selector btn-group w-100">
                                        <button type="button" class="active" data-period="weekly">
                                            <i class='bx bx-calendar-week'></i>Weekly
                                        </button>
                                        <button type="button" data-period="monthly">
                                            <i class='bx bx-calendar'></i>Monthly
                                        </button>
                                        <button type="button" data-period="yearly">
                                            <i class='bx bx-calendar-star'></i>Yearly
                                        </button>
                                    </div>
                                    <input type="hidden" name="period_type" id="period_type" value="weekly">
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-between mt-4">
                                <a href="budget_goals.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </a>
                                <button type="submit" class="btn btn-primary">Add Budget Goal</button>
                            </div>
                        </div>
                    </form>
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
                // Validate and clean the input
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
