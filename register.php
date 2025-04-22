<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$formData = [
    'username' => '',
    'email' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username'] = trim($_POST['username']);
    $formData['email'] = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($formData['username']) || empty($formData['email']) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $formData['username']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $formData['email']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, email, password, balance_set, role) VALUES (?, ?, ?, FALSE, 'user')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $formData['username'], $formData['email'], $hashed_password);
                
                if ($stmt->execute()) {
                    // Log the user in
                    $_SESSION['user_id'] = $conn->insert_id;
                    $_SESSION['username'] = $formData['username'];
                    $_SESSION['role'] = 'user';
                    
                    // Redirect to setup balance
                    header('Location: setup_balance.php');
                    exit();
                } else {
                    $error = "Registration failed. Please try again.";
                }
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
    <title>Register - Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css">
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
        }

        .register-container {
            display: flex;
            width: 900px;
            max-width: 100%;
            height: calc(100vh - 2rem);
            max-height: 800px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            position: relative;
        }

        .register-form-section {
            flex: 1;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-y: hidden;
        }

        .register-form-section.has-alert {
            overflow-y: auto;
            padding-right: calc(2.5rem - 4px); /* Compensate for scrollbar width */
        }

        .register-form-section::-webkit-scrollbar {
            width: 4px;
            display: none;
        }

        .register-form-section.has-alert::-webkit-scrollbar {
            display: block;
        }

        .register-form-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .register-form-section::-webkit-scrollbar-thumb {
            background-color: var(--border-color);
            border-radius: 2px;
        }

        .back-button {
            position: absolute;
            top: 1rem;
            left: 1rem;
            color: var(--text-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-button:hover {
            background: var(--background-color);
            color: var(--primary-color);
        }

        .back-button i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }

        .form-container {
            max-width: 360px;
            margin: 0 auto;
            width: 100%;
            padding-top: 2rem;
        }

        .logo {
            margin-bottom: 1.5rem;
        }

        .logo img {
            height: 32px;
        }

        .form-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: var(--text-color);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .form-floating {
            margin-bottom: 0.875rem;
        }

        .form-floating > .form-control {
            padding: 0.875rem;
            height: calc(3.25rem + 2px);
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .form-floating > label {
            padding: 0.875rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 163, 255, 0.1);
        }

        .btn-register {
            width: 100%;
            padding: 0.8rem;
            font-size: 0.95rem;
            font-weight: 600;
            background: var(--primary-color);
            border: none;
            color: white;
            border-radius: 8px;
            margin-top: 1rem;
            height: 44px;
            transition: all 0.2s;
        }

        .btn-register:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .register-image-section {
            flex: 1;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-image-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/finance-pattern.svg') repeat;
            opacity: 0.1;
            transform: rotate(180deg);
        }

        .register-image-content {
            position: relative;
            z-index: 1;
            color: white;
            text-align: center;
            max-width: 360px;
        }

        .register-image-content h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .register-image-content p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 768px) {
            .register-image-section {
                display: none;
            }
        }

        .alert {
            border: none;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            padding: 0.75rem 1rem;
            position: relative;
        }

        .alert-danger {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .alert-success {
            background-color: #DCFCE7;
            color: #166534;
        }

        .alert .btn-close {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            padding: 0.5rem;
            font-size: 0.75rem;
        }

        .password-requirements {
            margin: 1rem 0;
            padding: 0.75rem;
            background: var(--background-color);
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .password-requirements h3 {
            font-size: 0.875rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .requirement-list {
            list-style: none;
            padding: 0;
            margin: 0;
            color: var(--text-color);
        }

        .requirement-list li {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }

        .requirement-list li i {
            margin-right: 0.5rem;
            font-size: 1rem;
        }

        .requirement-list li.valid {
            color: #059669;
        }

        .requirement-list li.invalid {
            color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-form-section<?php echo ($error || $success) ? ' has-alert' : ''; ?>">
            <a href="index.php" class="back-button">
                <i class='bx bx-arrow-back'></i>Back to Home
            </a>
            <div class="form-container">
                <div class="logo">
                    <img src="assets/images/logo.svg" alt="Finance Tracker">
                </div>

                <h1 class="form-title">Create Account</h1>
                <p class="form-subtitle">Start your financial journey with us</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?php echo htmlspecialchars($formData['username']); ?>" required>
                        <label for="username">Username</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email address" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                        <label for="email">Email address</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">Password</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                        <label for="confirm_password">Confirm password</label>
                    </div>

                    <div class="password-requirements">
                        <h3>Password Requirements</h3>
                        <ul class="requirement-list">
                            <li class="invalid"><i class='bx bx-x'></i>At least 8 characters long</li>
                            <li class="invalid"><i class='bx bx-x'></i>Contains uppercase letter</li>
                            <li class="invalid"><i class='bx bx-x'></i>Contains number</li>
                            <li class="invalid"><i class='bx bx-x'></i>Contains special character</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-register">Create your account</button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in</a>
                </div>
            </div>
        </div>
        <div class="register-image-section">
            <div class="register-image-content">
                <h2>Smart Financial Management</h2>
                <p>Get started with Finance Tracker today and experience the power of intelligent budget tracking and expense management.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password validation
        const password = document.getElementById('password');
        const requirements = document.querySelectorAll('.requirement-list li');

        password.addEventListener('input', function() {
            const value = this.value;
            
            // Length check
            if(value.length >= 8) {
                requirements[0].classList.remove('invalid');
                requirements[0].classList.add('valid');
                requirements[0].querySelector('i').classList.replace('bx-x', 'bx-check');
            } else {
                requirements[0].classList.remove('valid');
                requirements[0].classList.add('invalid');
                requirements[0].querySelector('i').classList.replace('bx-check', 'bx-x');
            }

            // Uppercase check
            if(/[A-Z]/.test(value)) {
                requirements[1].classList.remove('invalid');
                requirements[1].classList.add('valid');
                requirements[1].querySelector('i').classList.replace('bx-x', 'bx-check');
            } else {
                requirements[1].classList.remove('valid');
                requirements[1].classList.add('invalid');
                requirements[1].querySelector('i').classList.replace('bx-check', 'bx-x');
            }

            // Number check
            if(/[0-9]/.test(value)) {
                requirements[2].classList.remove('invalid');
                requirements[2].classList.add('valid');
                requirements[2].querySelector('i').classList.replace('bx-x', 'bx-check');
            } else {
                requirements[2].classList.remove('valid');
                requirements[2].classList.add('invalid');
                requirements[2].querySelector('i').classList.replace('bx-check', 'bx-x');
            }

            // Special character check
            if(/[!@#$%^&*]/.test(value)) {
                requirements[3].classList.remove('invalid');
                requirements[3].classList.add('valid');
                requirements[3].querySelector('i').classList.replace('bx-x', 'bx-check');
            } else {
                requirements[3].classList.remove('valid');
                requirements[3].classList.add('invalid');
                requirements[3].querySelector('i').classList.replace('bx-check', 'bx-x');
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formSection = document.querySelector('.register-form-section');
            const alerts = document.querySelectorAll('.alert');

            // Function to update form section class based on alerts
            function updateFormSectionClass() {
                const hasVisibleAlerts = document.querySelectorAll('.alert:not(.hide)').length > 0;
                if (hasVisibleAlerts) {
                    formSection.classList.add('has-alert');
                } else {
                    formSection.classList.remove('has-alert');
                }
            }

            // Add event listeners to all alert close buttons
            alerts.forEach(alert => {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        setTimeout(updateFormSectionClass, 200); // Wait for Bootstrap animation
                    });
                }
            });
        });
    </script>
</body>
</html>
