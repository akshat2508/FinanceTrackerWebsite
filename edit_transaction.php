<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_GET['id'] ?? 0;

// Initialize message variables
$success_message = '';
$error_message = '';

if ($transaction_id <= 0) {
    $_SESSION['error'] = "Invalid transaction ID";
    header('Location: transactions.php');
    exit();
}

// Get transaction details
$sql = "SELECT * FROM transactions WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $transaction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: transactions.php');
    exit();
}

$transaction = $result->fetch_assoc();
// Format category for display: first letter uppercase, rest lowercase
$transaction['category'] = ucfirst($transaction['category']);

// Get recent categories for suggestions
$sql = "SELECT DISTINCT category FROM transactions WHERE user_id = ? ORDER BY category";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = [];
while ($row = $result->fetch_assoc()) {
    // Format category for display: first letter uppercase, rest lowercase
    $categories[] = ucfirst($row['category']);
}

// Remove duplicates from categories array
$categories = array_unique($categories);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    // Convert category to lowercase for storage
    $category = strtolower(isset($_POST['category']) ? trim($_POST['category']) : '');
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

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
        // Get current balance excluding this transaction
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(
                    CASE 
                        WHEN id != ? THEN
                            CASE WHEN type = 'income' THEN amount ELSE -amount END
                        ELSE 0
                    END
                ), 0) as balance
            FROM transactions 
            WHERE user_id = ?
        ");
        $stmt->bind_param("ii", $transaction_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_balance = $result->fetch_assoc()['balance'];

        if ($amount > $current_balance) {
            $errors[] = "Insufficient balance. Your current balance (excluding this transaction) is ₹" . number_format($current_balance, 2);
        }
    }

    // If there are no errors, proceed with update
    if (empty($errors)) {
        $sql = "UPDATE transactions SET type = ?, amount = ?, category = ?, date = ?, description = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdsssis", $type, $amount, $category, $date, $description, $transaction_id, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Transaction updated successfully!";
        } else {
            $error_message = "Error updating transaction. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaction - Finance Tracker</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        .ui-autocomplete-input {
            text-align: left !important;
        }
        .ui-menu-item {
            text-align: left !important;
        }
        .ui-menu .ui-menu-item-wrapper {
            text-align: left !important;
            padding: 5px 10px;
        }
        #category {
            text-align: left !important;
        }
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
        .type-selector {
            display: flex;
            gap: 1rem;
        }
        .type-selector label {
            flex: 1;
            text-align: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
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
                        <h4 class="mb-0">Edit Transaction</h4>
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

                    <form action="edit_transaction.php?id=<?php echo $transaction_id; ?>" method="POST" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="form-label">Transaction Type</label>
                                    <div class="type-selector d-flex gap-3">
                                        <input type="radio" class="btn-check" name="type" id="type-income" value="income" <?php echo $transaction['type'] === 'income' ? 'checked' : ''; ?> required>
                                        <label class="btn flex-grow-1 d-flex flex-column align-items-center income" for="type-income">
                                            <i class='bx bx-trending-up'></i>
                                            Income
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="type" id="type-expense" value="expense" <?php echo $transaction['type'] === 'expense' ? 'checked' : ''; ?> required>
                                        <label class="btn flex-grow-1 d-flex flex-column align-items-center expense" for="type-expense">
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
                                        <input type="number" step="0.01" class="form-control" name="amount" required value="<?php echo htmlspecialchars($transaction['amount']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-control" name="date" required value="<?php echo htmlspecialchars($transaction['date']); ?>">
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
                                               value="<?php echo htmlspecialchars($transaction['category']); ?>"
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
                                    <textarea class="form-control" name="description" rows="2" placeholder="Enter description"><?php echo htmlspecialchars($transaction['description']); ?></textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="my-3">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class='bx bx-save me-1'></i>Update Transaction
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
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
