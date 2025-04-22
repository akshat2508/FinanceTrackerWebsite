<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if balance is already set
$stmt = $conn->prepare("SELECT username, balance_set FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user['balance_set']) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opening_balance = filter_input(INPUT_POST, 'opening_balance', FILTER_VALIDATE_FLOAT);
    
    if ($opening_balance === false) {
        $error = "Please enter a valid amount";
    } else {
        // Update user's opening balance
        $stmt = $conn->prepare("UPDATE users SET opening_balance = ?, balance_set = TRUE WHERE id = ?");
        $stmt->bind_param("di", $opening_balance, $user_id);
        
        if ($stmt->execute()) {
            // Add opening balance as a transaction
            $date = date('Y-m-d');
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, category, description, amount, date) VALUES (?, 'income', 'Opening Balance', 'Initial account balance', ?, ?)");
            $stmt->bind_param("ids", $user_id, $opening_balance, $date);
            
            if ($stmt->execute()) {
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Error recording opening balance transaction";
            }
        } else {
            $error = "Error updating opening balance";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Opening Balance - Finance Tracker</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        :root {
            --primary-color: #00A3FF;
            --primary-dark: #0077CC;
            --secondary-color: #1CE589;
            --dark-color: #0B1B35;
            --text-color: #4A5568;
            --border-color: #E2E8F0;
            --background-color: #F8FAFC;
        }

        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            margin: 0;
            background: var(--background-color);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            overflow: hidden;
        }

        .setup-container {
            display: flex;
            width: 900px;
            max-width: 100%;
            height: calc(100vh - 2rem);
            max-height: 600px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            position: relative;
        }

        .setup-form-section {
            flex: 1;
            padding: 3.5rem 2.5rem 2.5rem;
            display: flex;
            flex-direction: column;
        }

        .form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-subtitle {
            color: var(--text-color);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            height: 3.5rem;
            font-size: 0.95rem;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 163, 255, 0.1);
        }

        .btn-setup {
            background: var(--primary-color);
            color: white;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            height: 44px;
            transition: all 0.2s;
        }

        .btn-setup:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: none;
            font-size: 0.95rem;
            padding: 0.75rem 1rem;
        }

        .alert-danger {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .setup-image-section {
            display: none;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            width: 500px;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .setup-image-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/finance-pattern.svg') repeat;
            opacity: 0.1;
        }

        .login-image-content {
            position: relative;
            z-index: 1;
            color: white;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-image-content h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .login-image-content p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .currency-symbol {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-color);
            font-weight: 500;
        }

        .input-with-symbol {
            position: relative;
        }

        .input-with-symbol input {
            padding-left: 2rem;
        }

        .form-text {
            font-size: 0.875rem;
            color: var(--text-color);
            margin-top: 0.5rem;
        }

        @media (min-width: 768px) {
            .setup-image-section {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-form-section">
            <div class="form-container">
                <div class="logo">
                    <img src="assets/images/logo.svg" alt="Finance Tracker">
                </div>

                <h1 class="form-title">Welcome to Finance Tracker!</h1>
                <p class="form-subtitle">Let's set up your account by entering your current balance</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="form-floating">
                        <div class="input-with-symbol">
                            <span class="currency-symbol">₹</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control" 
                                   id="opening_balance" 
                                   name="opening_balance" 
                                   placeholder="Enter your current balance"
                                   required 
                                   autofocus>
                        </div>
                        <div class="form-text">
                            This will be your starting point for tracking your finances
                        </div>
                    </div>

                    <button type="submit" class="btn btn-setup">Continue to Dashboard</button>
                </form>
            </div>
        </div>

        <div class="setup-image-section">
            <div class="login-image-content">
                <h2>Take Control of Your Finances</h2>
                <p>Start your journey to better financial management with Finance Tracker.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
