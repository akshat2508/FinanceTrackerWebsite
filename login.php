<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit();
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Prepare SQL statement to prevent SQL injection
        $sql = "SELECT id, username, password, role FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Finance Tracker</title>
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
            overflow: hidden;
        }

        .login-container {
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

        .login-form-section {
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
            max-width: 360px;
            margin: 0 auto;
            width: 100%;
        }

        .login-image-section {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: none;
            position: relative;
            overflow: hidden;
            padding: 2.5rem;
        }

        .login-image-section::before {
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
            margin: 0;
        }

        @media (min-width: 768px) {
            .login-image-section {
                display: block;
            }
        }

        .logo {
            margin-bottom: 2rem;
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
            margin-bottom: 1rem;
        }

        .form-floating > .form-control {
            padding: 1rem;
            height: calc(3.5rem + 2px);
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .form-floating > label {
            padding: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 163, 255, 0.1);
        }

        .btn-login {
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

        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .forgot-password {
            text-align: right;
            margin: -0.5rem 0 1rem;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
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

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            transform: scale(.85) translateY(-1rem) translateX(.15rem);
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="index.php" class="back-button">
            <i class='bx bx-arrow-back'></i>Back to Home
        </a>
        <div class="login-form-section">
            <div class="form-container">
                <div class="logo">
                    <img src="assets/images/logo.svg" alt="Finance Tracker">
                </div>

                <h1 class="form-title">Welcome back</h1>
                <p class="form-subtitle">Enter your credentials to access your account</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="needs-validation" novalidate>
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Email address" value="<?php echo htmlspecialchars($username); ?>" required>
                        <label for="username">Email address</label>
                    </div>

                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">Password</label>
                    </div>

                    <button type="submit" class="btn btn-login">Sign in to your account</button>
                </form>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Create one now</a>
                </div>
            </div>
        </div>
        <div class="login-image-section">
            <div class="login-image-content">
                <h2>Take Control of Your Finances</h2>
                <p>Join thousands of users who trust Finance Tracker to manage their personal and business finances with ease.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
