<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker - Smart Money Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css">
    <style>
        :root {
            --primary-color: #00ff9d;
            --dark-color: #121212;
            --text-dark: #333333;
            --text-light: #666666;
            --bg-light: #f8f9fa;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background-color: #ffffff;
            line-height: 1.6;
        }

        .navbar {
            background: var(--dark-color);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 700;
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .nav-link {
            color: #ffffff !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background: var(--primary-color);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link:hover::after {
            width: 80%;
        }

        .btn-get-started {
            background: var(--primary-color);
            color: var(--dark-color);
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 255, 157, 0.2);
        }

        .btn-get-started:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 255, 157, 0.3);
            background: var(--primary-color);
            color: var(--dark-color);
        }

        .hero {
            padding: 8rem 0 6rem;
            background: var(--dark-color);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: url('assets/images/pattern.svg') repeat;
            opacity: 0.1;
            animation: patternMove 20s linear infinite;
        }

        @keyframes patternMove {
            0% { background-position: 0 0; }
            100% { background-position: 100px 100px; }
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            color: #ffffff;
            animation: fadeInUp 1s ease;
        }

        .hero p {
            font-size: 1.2rem;
            color: #ffffff;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.2s;
        }

        .hero-image {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1;
            animation: float 6s ease-in-out infinite;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.15));
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
            100% {
                transform: translateY(0px);
            }
        }

        @media (max-width: 991.98px) {
            .hero-image {
                margin-top: 3rem;
            }
        }

        .features {
            padding: 6rem 0;
            background: #ffffff;
        }

        .feature-card {
            background: var(--dark-color);
            padding: 2.5rem;
            border-radius: 20px;
            height: 100%;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 255, 157, 0.1);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 255, 157, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary-color);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            background: rgba(0, 255, 157, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: var(--primary-color);
        }

        .feature-icon i {
            font-size: 2rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon i {
            color: var(--dark-color);
        }

        .feature-content {
            position: relative;
            z-index: 1;
        }

        .feature-title {
            color: #ffffff;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .feature-text {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1.5rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .feature-list li i {
            color: var(--primary-color);
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }

        .demo-section {
            padding: 6rem 0;
            background: var(--dark-color);
        }

        .demo-video {
            border-radius: 10px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .demo-video:hover {
            transform: scale(1.02);
        }

        .demo-content h2 {
            color: #ffffff;
            margin-bottom: 1.5rem;
        }

        .demo-content p {
            color: rgba(255, 255, 255, 0.9);
        }

        .demo-content .list-unstyled li {
            color: #ffffff;
            margin-bottom: 1rem;
        }

        .demo-content .bx-check {
            color: var(--primary-color);
        }

        .cta {
            padding: 6rem 0;
            background: var(--dark-color);
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: url('assets/images/pattern.svg') repeat;
            opacity: 0.05;
        }

        .cta h2 {
            color: #ffffff;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .cta p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2rem;
        }

        .py-6 {
            padding-top: 6rem;
            padding-bottom: 6rem;
        }

        /* Contact Section Styles */
        .contact {
            background-color: #ffffff;
            padding: 6rem 0;
        }

        .map-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: 600px;
            overflow: hidden;
        }

        .contact-info-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: 600px;
            padding: 2.5rem;
        }

        .contact-info-title {
            color: var(--text-dark);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .contact-info-grid {
            display: grid;
            gap: 1.2rem;
            height: calc(100% - 5rem);
        }

        .contact-info-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.2rem;
            background: var(--bg-light);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .icon-box {
            min-width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .icon-box i {
            font-size: 1.3rem;
            color: var(--primary-color);
        }

        .info-content h5 {
            color: var(--text-dark);
            margin-bottom: 0.3rem;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .info-content p {
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .contact-form-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .contact-form-card h3 {
            color: var(--text-dark);
            font-weight: 600;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .input-group {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }

        .input-group-text {
            background: var(--bg-light);
            border: none;
            color: var(--primary-color);
            padding: 0.8rem;
        }

        .form-control {
            border: none;
            padding: 0.8rem;
            background: #ffffff;
        }

        .form-control:focus {
            box-shadow: none;
            background: #ffffff;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.8rem 2rem;
            font-weight: 500;
            border-radius: 12px;
            color: var(--dark-color);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--dark-color);
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero-shape {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }

        .hero-shape-1 {
            background: url('assets/images/hero-shape-1.svg') no-repeat;
            background-size: cover;
            opacity: 0.1;
        }

        .hero-shape-2 {
            background: url('assets/images/hero-shape-2.svg') no-repeat;
            background-size: cover;
            opacity: 0.1;
            transform: translateY(-50%);
        }

        /* How It Works Section */
        .step-card {
            background: var(--dark-color);
            padding: 2rem;
            border-radius: 15px;
            height: 100%;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .step-card:hover {
            transform: translateY(-5px);
        }

        .step-icon {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: rgba(0, 255, 157, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .step-icon i {
            font-size: 2.5rem;
            color: var(--primary-color);
        }

        .step-number {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: var(--dark-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .step-card h3 {
            color: #ffffff;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .step-card p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0;
        }

        /* CTA Section */
        .cta {
            background: var(--dark-color);
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/images/pattern.svg') repeat;
            opacity: 0.1;
            animation: patternMove 20s linear infinite;
        }

        .cta h2 {
            color: #ffffff;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .cta p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.2rem;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Footer Styles */
        .footer {
            background: var(--dark-color);
            padding: 5rem 0 3rem;
            color: #fff;
        }

        .footer-brand {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-title {
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 1rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .footer-links a:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .footer-links a i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .social-links a:hover {
            color: var(--primary-color);
            background: rgba(0, 255, 157, 0.1);
            transform: translateY(-3px);
        }

        .newsletter-form .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            padding: 0.8rem 1rem;
        }

        .newsletter-form .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .newsletter-form .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            box-shadow: none;
        }

        .newsletter-form .btn {
            padding: 0.8rem 1.5rem;
        }

        .footer hr {
            border-color: rgba(255, 255, 255, 0.1);
            margin: 2rem 0;
        }

        .footer-bottom {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .footer-bottom a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-bottom a:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class='bx bx-wallet me-2'></i>Finance Tracker
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class='bx bx-menu' style="color: var(--primary-color); font-size: 1.8rem;"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#hero">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#process">Our Process</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a href="<?php echo ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'); ?>" class="btn btn-get-started ms-2">Dashboard</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-3">
                            <a href="login.php" class="btn btn-get-started">Get Started</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="hero" class="hero">
        <div class="hero-shape hero-shape-1"></div>
        <div class="hero-shape hero-shape-2"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1>Simple Budget Tracking for Everyone</h1>
                        <p>Take control of your finances with our easy-to-use expense tracking and budgeting tools.</p>
                        <!-- <a href="login.php" class="btn btn-get-started btn-lg">Get Started Now</a> -->
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <img src="assets/images/hero-illustration.svg" alt="Finance Management" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Everything You Need</h2>
                <p class="text-muted">Powerful features to help you manage your finances better</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class='bx bx-line-chart'></i>
                        </div>
                        <div class="feature-content">
                            <h3 class="feature-title">Expense Tracking</h3>
                            <p class="feature-text">Easily log and categorize your daily expenses with our intuitive interface.</p>
                            <ul class="feature-list">
                                <li><i class='bx bx-check'></i> Automatic categorization</li>
                                <li><i class='bx bx-check'></i> Receipt scanning</li>
                                <li><i class='bx bx-check'></i> Real-time updates</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class='bx bx-target-lock'></i>
                        </div>
                        <div class="feature-content">
                            <h3 class="feature-title">Budget Goals</h3>
                            <p class="feature-text">Set monthly budgets and track your progress towards your financial goals.</p>
                            <ul class="feature-list">
                                <li><i class='bx bx-check'></i> Custom budget categories</li>
                                <li><i class='bx bx-check'></i> Progress tracking</li>
                                <li><i class='bx bx-check'></i> Smart alerts</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class='bx bx-bar-chart-alt-2'></i>
                        </div>
                        <div class="feature-content">
                            <h3 class="feature-title">Visual Reports</h3>
                            <p class="feature-text">Get insights into your spending habits with beautiful charts and reports.</p>
                            <ul class="feature-list">
                                <li><i class='bx bx-check'></i> Interactive dashboards</li>
                                <li><i class='bx bx-check'></i> Trend analysis</li>
                                <li><i class='bx bx-check'></i> Export capabilities</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="process" class="process py-6">
        <div class="container">
            <div class="row mb-5 text-center">
                <div class="col-lg-6 offset-lg-3">
                    <h2 class="section-title">How It Works</h2>
                    <p class="text-muted">Get started with Finance Tracker in three simple steps</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="step-card text-center">
                        <div class="step-icon">
                            <i class='bx bx-user-plus'></i>
                            <span class="step-number">1</span>
                        </div>
                        <h3>Create Account</h3>
                        <p>Sign up for free and set up your profile in minutes</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card text-center">
                        <div class="step-icon">
                            <i class='bx bx-money'></i>
                            <span class="step-number">2</span>
                        </div>
                        <h3>Track Expenses</h3>
                        <p>Log your daily transactions and categorize them</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card text-center">
                        <div class="step-icon">
                            <i class='bx bx-chart'></i>
                            <span class="step-number">3</span>
                        </div>
                        <h3>Monitor Progress</h3>
                        <p>View insights and track your financial goals</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact py-6">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Get in Touch</h2>
                <p class="text-muted">Have questions? We'd love to hear from you.</p>
            </div>
            <div class="row g-4 mb-5">
                <div class="col-lg-5">
                    <div class="contact-info-card">
                        <h4 class="contact-info-title">Contact Information</h4>
                        <div class="contact-info-grid">
                            <div class="contact-info-item">
                                <div class="icon-box">
                                    <i class='bx bx-map'></i>
                                </div>
                                <div class="info-content">
                                    <h5>Our Location</h5>
                                    <p>Lovely Professional University<br>Jalandhar - Delhi, Grand Trunk Rd, Phagwara<br>Punjab</p>
                                </div>
                            </div>
                            <div class="contact-info-item">
                                <div class="icon-box">
                                    <i class='bx bx-phone'></i>
                                </div>
                                <div class="info-content">
                                    <h5>Phone Number</h5>
                                    <p>+91 836-064-5330</p>
                                </div>
                            </div>
                            <div class="contact-info-item">
                                <div class="icon-box">
                                    <i class='bx bx-envelope'></i>
                                </div>
                                <div class="info-content">
                                    <h5>Email Address</h5>
                                    <p>savvy.grewal13@gmail.com</p>
                                </div>
                            </div>
                            <div class="contact-info-item">
                                <div class="icon-box">
                                    <i class='bx bx-time'></i>
                                </div>
                                <div class="info-content">
                                    <h5>Working Hours</h5>
                                    <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 10:00 AM - 2:00 PM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="map-card">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m16!1m12!1m3!1d3410.8635726825587!2d75.70069967587719!3d31.252199824337826!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!2m1!1sLovely%20Professional%20University!5e0!3m2!1sen!2sin!4v1744865611545!5m2!1sen!2sin" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy">
                    </iframe>
                    </div>
                </div>
            </div>

            <!-- Alert Message Container -->
            <div id="alertContainer" class="mb-4">
                <?php
                error_reporting(0); // Hide deprecation warnings
                ini_set('display_errors', 0);

                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    try {
                        // Get form data with updated sanitization
                        $name = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
                        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
                        $subject = htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8');
                        $message = htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');

                        // Validate required fields
                        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
                            throw new Exception("All fields are required");
                        }

                        // Validate email
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Invalid email format");
                        }

                        // Email content
                        $email_content = "
                            <h2>Contact Form Submission</h2>
                            <p><strong>Name:</strong> {$name}</p>
                            <p><strong>Email:</strong> {$email}</p>
                            <p><strong>Subject:</strong> {$subject}</p>
                            <p><strong>Message:</strong><br>{$message}</p>
                        ";

                        // Headers
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        $headers .= "From: " . $email . "\r\n";
                        $headers .= "Reply-To: " . $email . "\r\n";
                        $headers .= "X-Mailer: PHP/" . phpversion();

                        // Send email
                        $to = "savvy.grewal13@gmail.com";
                        $email_subject = "New Contact Form Message: $subject";
                        
                        if (@mail($to, $email_subject, $email_content, $headers)) {
                            $alert = array(
                                'type' => 'success',
                                'message' => 'Thank you for your message! We will get back to you soon.'
                            );
                        } else {
                            $error = error_get_last()['message'];
                            throw new Exception("Failed to send email: " . $error);
                        }

                    } catch (Exception $e) {
                        $alert = array(
                            'type' => 'danger',
                            'message' => 'Error: ' . $e->getMessage()
                        );
                    }
                }
                ?>
                <?php if (isset($alert)): ?>
                <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $alert['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="contact-form-card">
                        <h3 class="text-center mb-4">Send us a Message</h3>
                        <form id="contactForm" class="needs-validation" novalidate>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="form-label">Full Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class='bx bx-user'></i></span>
                                            <input type="text" class="form-control" id="name" name="name" required>
                                            <div class="invalid-feedback">
                                                Please enter your name.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class='bx bx-envelope'></i></span>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                            <div class="invalid-feedback">
                                                Please enter a valid email address.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="subject" class="form-label">Subject</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class='bx bx-help-circle'></i></span>
                                            <input type="text" class="form-control" id="subject" name="subject" required>
                                            <div class="invalid-feedback">
                                                Please enter a subject.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="message" class="form-label">Message</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class='bx bx-message-detail'></i></span>
                                            <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
                                            <div class="invalid-feedback">
                                                Please enter your message.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                        <i class='bx bx-send me-2'></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta py-6">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="mb-4">Ready to Take Control of Your Finances?</h2>
                    <p class="mb-4">Join thousands of users who are already managing their finances smarter with Finance Tracker.</p>
                    
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="footer-brand">Finance Tracker</h5>
                    <p class="text-muted mb-4">Your trusted partner in personal finance management. Take control of your money and achieve your financial goals.</p>
                    <div class="social-links">
                        <a href="https://facebook.com" target="_blank" title="Facebook"><i class='bx bxl-facebook'></i></a>
                        <a href="https://twitter.com" target="_blank" title="Twitter"><i class='bx bxl-twitter'></i></a>
                        <a href="https://instagram.com" target="_blank" title="Instagram"><i class='bx bxl-instagram'></i></a>
                        <a href="https://linkedin.com" target="_blank" title="LinkedIn"><i class='bx bxl-linkedin'></i></a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#features"><i class='bx bx-chevron-right'></i>Features</a></li>
                        <li><a href="#contact"><i class='bx bx-chevron-right'></i>Contact Us</a></li>
                        <li><a href="login.php"><i class='bx bx-chevron-right'></i>Login</a></li>
                        <li><a href="register.php"><i class='bx bx-chevron-right'></i>Register</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="footer-title">Newsletter</h5>
                    <p class="text-muted mb-4">Subscribe to our newsletter for tips, updates, and exclusive offers.</p>
                    <form class="newsletter-form" id="newsletterForm" onsubmit="return handleNewsletter(event)">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Enter your email" required>
                            <button class="btn btn-get-started" type="submit">
                                <i class='bx bx-paper-plane'></i>Subscribe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <hr>
            <div class="row align-items-center footer-bottom">
                <div class="col-md-6">
                    <p class="mb-md-0">
                        <i class='bx bx-copyright'></i> 2025 Finance Tracker. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.padding = '0.5rem 0';
                navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            } else {
                navbar.style.padding = '1rem 0';
                navbar.style.boxShadow = 'none';
            }
        });

        // Animate elements on scroll
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card, .demo-video, .demo-content, .cta').forEach(el => {
            el.classList.add('animate-on-scroll');
            observer.observe(el);
        });

        // Feature cards hover effect
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
    <script>
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-2"></i>Sending...';
        submitBtn.disabled = true;

        // Get form data
        const formData = new FormData(form);

        // Send form data using fetch
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Create a temporary div to parse the response
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Find alert in the response
            const alert = tempDiv.querySelector('.alert');
            if (alert) {
                // Show the alert in the alert container
                const alertContainer = document.getElementById('alertContainer');
                const existingAlert = alertContainer.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
                alertContainer.appendChild(alert);
                
                // Scroll the alert into view smoothly
                alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // If success, clear the form
                if (alert.classList.contains('alert-success')) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
            }
        })
        .catch(error => {
            // Show error message
            const alertContainer = document.getElementById('alertContainer');
            const existingAlert = alertContainer.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            const errorAlert = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Error sending message. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            alertContainer.innerHTML = errorAlert;
            alertContainer.querySelector('.alert').scrollIntoView({ behavior: 'smooth', block: 'center' });
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    </script>
    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Newsletter form handling
        function handleNewsletter(event) {
            event.preventDefault();
            const form = event.target;
            const email = form.querySelector('input[type="email"]').value;
            
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>';
            submitBtn.disabled = true;

            // Simulate API call
            setTimeout(() => {
                // Create success alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
                alertDiv.innerHTML = `
                    Thank you for subscribing! We've sent a confirmation email to ${email}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Show alert
                form.appendChild(alertDiv);
                
                // Reset form and button
                form.reset();
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;

                // Auto-dismiss alert
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }, 1000);

            return false;
        }

        // Social media links handling
        document.querySelectorAll('.social-links a').forEach(link => {
            link.addEventListener('click', function(e) {
                const url = this.getAttribute('href');
                window.open(url, '_blank');
                e.preventDefault();
            });
        });
    </script>
</body>
</html>
