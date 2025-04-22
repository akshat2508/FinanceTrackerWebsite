<?php
session_start();
require_once 'config/database.php';

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

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize variables with default empty values
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];
    // Convert category to lowercase for storage
    $category = strtolower($_POST['category']);
    $description = trim($_POST['description']);

    // Validate each field
    $errors = [];
    if (empty($type)) {
        $errors[] = "Please select a transaction type.";
    }
    if ($amount <= 0) {
        $errors[] = "Please enter a valid amount greater than 0.";
    }
    if (empty($category)) {
        $errors[] = "Please enter or select a category.";
    }
    if (empty($date)) {
        $errors[] = "Please select a date.";
    }

    if ($type === 'expense') {
        // Get current balance
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance
            FROM transactions 
            WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_balance = $result->fetch_assoc()['balance'];

        if ($amount > $current_balance) {
            $errors[] = "Insufficient balance. Your current balance is ₹" . number_format($current_balance, 2);
        }
    }

    // If there are no errors, proceed with insertion
    if (empty($errors)) {
        $sql = "INSERT INTO transactions (user_id, type, amount, category, date, description) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdsss", $user_id, $type, $amount, $category, $date, $description);
        
        if ($stmt->execute()) {
            $success_message = "Transaction added successfully!";
            // Clear form data after successful submission
            $_POST = array();
            // Redirect to transactions page after successful addition
            header("Location: transactions.php?success=1");
            exit();
        } else {
            $error_message = "Error adding transaction. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get distinct categories for autocomplete (with proper case formatting)
$stmt = $conn->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = ? ORDER BY category ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = array();
while ($row = $result->fetch_assoc()) {
    // Format category for display: first letter uppercase, rest lowercase
    $categories[] = ucfirst($row['category']);
}
$categories_json = json_encode($categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction - Finance Tracker</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .category-input {
            position: relative;
            width: 100%;
        }
        .category-suggestions {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 0.25rem;
        }
        .category-suggestion {
            padding: 0.5rem 1rem;
            cursor: pointer;
            text-align: left;
            font-size: 1rem;
            color: #212529;
            transition: background-color 0.15s ease-in-out;
        }
        .category-suggestion:hover {
            background-color: #f8f9fa;
        }
        #categoryInput {
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
        .type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .type-selector label {
            flex: 1;
            padding: 1.25rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #6b7280;
        }
        .type-selector input[type="radio"] {
            display: none;
        }
        .type-selector input[type="radio"]:checked + label.income {
            background-color: #dcfce7;
            border-color: #22c55e;
            color: #16a34a;
        }
        .type-selector input[type="radio"]:checked + label.expense {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #dc2626;
        }
        .type-selector label:hover {
            border-color: #9ca3af;
        }
        .type-selector i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-control, .form-select {
            padding: 0.625rem 1rem;
        }
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
            margin: 0 auto;
        }
        .summary-card {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
                        <h4 class="mb-0">Add Transaction</h4>
                        <a href="transactions.php" class="btn btn-outline-primary">
                            <i class='bx bx-arrow-back me-1'></i>Back To Transactions
                        </a>
                    </div>

                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Transaction Type</label>
                                    <div class="type-selector">
                                        <input type="radio" name="type" id="income" value="income" <?php echo isset($_POST['type']) && $_POST['type'] === 'income' ? 'checked' : ''; ?> required>
                                        <label for="income" class="income">
                                            <i class='bx bx-trending-up'></i>
                                            Income
                                        </label>
                                        
                                        <input type="radio" name="type" id="expense" value="expense" <?php echo isset($_POST['type']) && $_POST['type'] === 'expense' ? 'checked' : ''; ?>>
                                        <label for="expense" class="expense">
                                            <i class='bx bx-trending-down'></i>
                                            Expense
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" step="0.01" class="form-control" name="amount" required value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" placeholder="0.00">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" name="date" required value="<?php echo isset($_POST['date']) ? $_POST['date'] : date('Y-m-d'); ?>">
                                </div>
                            </div>

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
                                               value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>"
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
                            </div>

                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Description (Optional)</label>
                                    <textarea class="form-control" name="description" rows="2" placeholder="Enter description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="my-3">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class='bx bx-plus me-1'></i>Add Transaction
                                    </button>
                                    <a href="transactions.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                </div>
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
            const categoryInput = document.getElementById('categoryInput');
            const suggestions = document.getElementById('categorySuggestions');
            const suggestionItems = document.querySelectorAll('.category-suggestion');

            // Function to validate input
            function validateCategoryInput(input) {
                return input.replace(/[^A-Za-z\s\-&]/g, '');
            }

            categoryInput.addEventListener('input', (e) => {
                // Validate and clean the input
                const validatedValue = validateCategoryInput(e.target.value);
                if (validatedValue !== e.target.value) {
                    e.target.value = validatedValue;
                }
                
                // Filter suggestions based on input
                const searchTerm = validatedValue.toLowerCase();
                let hasVisibleSuggestions = false;
                suggestionItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'block';
                        hasVisibleSuggestions = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                suggestions.style.display = hasVisibleSuggestions ? 'block' : 'none';
            });

            categoryInput.addEventListener('focus', () => {
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
