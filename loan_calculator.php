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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker - Loan Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .wrapper {
            min-height: 100vh;
            width: 95%;
            margin:0px auto;
        }
        .main-content {
            flex: 1;
            min-width: 0;
            padding-left: 250px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 100% !important;
            padding: 0.75rem;
            margin: 0 auto;
        }
        .main-content {
            display: flex;
            justify-content: center;
            width: 100%;
            padding: 0 1rem;
        }
        .content-container {
            width: 100%;
            max-width: 1400px;
        }
        .form-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin: 0;
        }
        .schedule-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1.25rem;
            margin: 0;
        }
        .form-label {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #495057;
            font-weight: 500;
        }
        .invalid-feedback {
            font-size: 0.85rem;
            color: #dc3545;
            margin-top: 0.25rem;
        }
        .input-group {
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-right: none;
            color: #6c757d;
            font-size: 0.95rem;
        }
        .input-group .form-control {
            border: 1px solid #e9ecef;
            border-left: none;
            font-size: 0.95rem;
        }
        .input-group .form-control:focus {
            border-color: #e9ecef;
            box-shadow: none;
        }
        .input-group:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .input-group:focus-within {
            box-shadow: 0 0 0 2px rgba(13,110,253,.15);
        }
        .form-select {
            border: 1px solid #e9ecef;
            font-size: 0.95rem;
        }
        .form-select:focus {
            border-color: #e9ecef;
            box-shadow: 0 0 0 2px rgba(13,110,253,.15);
        }
        .form-control, .btn, .form-select {
            font-size: 0.9rem;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
            border-color: #0d6efd;
        }
        .input-group-text {
            font-size: 0.9rem;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-top: none;
            border-bottom-width: 1px;
            background-color: #f8f9fa;
            padding: 1rem;
        }
        .table td {
            vertical-align: middle;
            font-size: 0.9rem;
            color: #495057;
            border-color: #f0f0f0;
            padding: 1rem;
        }
        .loan-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 1.5rem;
            margin: 0 auto;
            padding: 1.5rem 0 0 0;
            max-width: 700px;
            width: 100%;
            justify-items: center;
        }
        @media (min-width: 992px) {
            .loan-summary {
                grid-template-columns: repeat(4, 1fr);
                max-width: 900px;
            }
        }
        @media (max-width: 600px) {
            .loan-summary {
                grid-template-columns: 1fr;
                max-width: 340px;
                gap: 1rem;
            }
        }
        .summary-item {
            border-radius: 14px;
            padding: 1.5rem 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-width: 0;
            border: 1px solid rgba(0,0,0,0.05);
            width: 100%;
            max-width: 250px;
            box-sizing: border-box;
            margin: 0 auto;
        }
        .summary-item:nth-child(1) {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15) 0%, rgba(2, 132, 199, 0.1) 100%);
            color: #0284c7;
        }
        .summary-item:nth-child(2) {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
            color: #059669;
        }
        .summary-item:nth-child(3) {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.1) 100%);
            color: #d97706;
        }
        .summary-item:nth-child(4) {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(79, 70, 229, 0.1) 100%);
            color: #4f46e5;
        }
        .summary-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }
        .summary-item:nth-child(1):hover {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.2) 0%, rgba(2, 132, 199, 0.15) 100%);
        }
        .summary-item:nth-child(2):hover {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.15) 100%);
        }
        .summary-item:nth-child(3):hover {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(217, 119, 6, 0.15) 100%);
        }
        .summary-item:nth-child(4):hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(79, 70, 229, 0.15) 100%);
        }
        .summary-item p {
            color: inherit;
            opacity: 0.85;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 1;
            min-height: 2.5em;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .summary-item h4 {
            color: inherit;
            font-size: clamp(1.25rem, 2vw, 1.5rem);
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
            word-break: break-word;
            line-height: 1.2;
        }
        .summary-item h4 span {
            font-weight: 700;
            display: block;
            overflow-wrap: break-word;
        }
        .summary-item h4 {
            margin: 0;
            color: #1a1a1a;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .summary-item p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
            transition: all 0.2s;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: auto;
            max-height: 400px;
            border: 1px solid #e9ecef;
            position: relative;
            scrollbar-width: thin;
        }
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #bbb;
        }
        .table-responsive thead {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 1;
        }
        .table-responsive th {
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
        }
        .table-responsive td {
            padding: 0.5rem 0.75rem;
            color: #495057;
            font-size: 0.9rem;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.01);
        }
        .btn {
            padding: 0.4rem 1.25rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.2s ease;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: linear-gradient(45deg, #0d6efd, #0b5ed7);
            border: none;
            box-shadow: 0 2px 4px rgba(13,110,253,.2);
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0b5ed7, #0a58ca);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(13,110,253,.2);
        }
        .btn-outline-secondary {
            border: 1px solid #dee2e6;
        }
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }
        /* New styles for the header icon */
        .header-icon-inline {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            color: #0d6efd;
        }
        .header-icon-inline i {
            font-size: 1.8rem; /* Increased from default size */
        }
        /* Adjust the schedule view dropdown width */
        #scheduleView {
            width: 160px; /* Increased width from auto to 160px */
        }
    </style>
</head>
<body>
    <div class="wrapper d-flex">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="content-container">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex align-items-center gap-2 mt-5">
                        <span class="header-icon-inline"><i class='bx bx-calculator'></i></span>
                        <h4 class="page-title mb-0">Loan Calculator</h4>
                    </div>
                    <p class="page-desc mt-2">Calculate EMI and view amortization schedule</p>
                </div>
                <!-- Calculator Section -->
                <!-- Input Form -->
                <div class="form-card mb-4">
                    <div class="row">
                        <div class="col-12">
                            <div class="px-4">
                                <h5 class="fw-bold mb-2" style="color: #2c3e50; font-size: 1.1rem;">Loan Details</h5>
                                <form id="loanForm" class="needs-validation mb-between" novalidate>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="loanAmount" class="form-label fw-semibold">Loan Amount (₹)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class='bx bx-rupee'></i></span>
                                                <input type="number" class="form-control" id="loanAmount" required min="1000" placeholder="Enter loan amount">
                                                <div class="invalid-feedback">Please enter a valid loan amount.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="interestRate" class="form-label fw-semibold">Interest Rate (%)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class='bx bx-percentage'></i></span>
                                                <input type="number" class="form-control" id="interestRate" required min="1" max="100" step="0.1" placeholder="Enter interest rate">
                                                <div class="invalid-feedback">Please enter a valid interest rate.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="loanTerm" class="form-label fw-semibold">Loan Term</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class='bx bx-calendar'></i></span>
                                                <input type="number" class="form-control" id="loanTerm" required min="1" placeholder="Enter loan term">
                                                <select class="form-select" id="termType" style="max-width: 120px;">
                                                    <option value="years">Years</option>
                                                    <option value="months">Months</option>
                                                </select>
                                                <div class="invalid-feedback">Please enter a valid loan term.</div>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary flex-grow-1 py-2">
                                                    <i class='bx bx-calculator me-1'></i> Calculate
                                                </button>
                                                <button type="reset" class="btn btn-outline-secondary py-2">
                                                    <i class='bx bx-reset me-1'></i> Reset
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-8 w-100">
                            <!-- Summary Section -->
                            <div class="loan-summary mx-auto" id="summarySection" style="display: none;">
                                <div class="summary-item">
                                    <p class="text-muted mb-2">Monthly EMI</p>
                                    <h4>₹<span id="monthlyEMI">0</span></h4>
                                </div>
                                <div class="summary-item">
                                    <p class="text-muted mb-2">Principal Amount</p>
                                    <h4>₹<span id="principalAmount">0</span></h4>
                                </div>
                                <div class="summary-item">
                                    <p class="text-muted mb-2">Total Interest</p>
                                    <h4>₹<span id="totalInterest">0</span></h4>
                                </div>
                                <div class="summary-item">
                                    <p class="text-muted mb-2">Total Payment</p>
                                    <h4>₹<span id="totalPayment">0</span></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Amortization Schedule -->
                <div class="schedule-card">
                    <div class="row">
                        <div class="col-12">
                            <div class="px-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="fw-bold mb-0" style="color: #2c3e50; font-size: 1.1rem;">Amortization Schedule</h5>
                                    <select class="form-select" id="scheduleView" style="width: auto;">
                                        <option value="yearly">Yearly View</option>
                                        <option value="monthly">Monthly View</option>
                                    </select>
                                </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>EMI</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Balance</th>
                                </tr>
                            </thead>
                            <tbody id="amortizationSchedule"></tbody>
                        </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loanForm = document.getElementById('loanForm');
            const summarySection = document.getElementById('summarySection');
            const scheduleView = document.getElementById('scheduleView');
            const amortizationSchedule = document.getElementById('amortizationSchedule');

            // Format number with commas
            function formatNumber(num) {
                return num.toLocaleString('en-IN', {
                    maximumFractionDigits: 2,
                    minimumFractionDigits: 2
                });
            }

            // Calculate EMI
            function calculateEMI(principal, rate, term) {
                rate = rate / (12 * 100); // Monthly interest rate
                term = term * 12; // Total number of months
                return principal * rate * Math.pow(1 + rate, term) / (Math.pow(1 + rate, term) - 1);
            }

            // Generate payment schedule
            function generateSchedule(principal, rate, term, emi, view) {
                let schedule = [];
                let balance = principal;
                let totalInterest = 0;
                let monthlyRate = rate / (12 * 100);
                let totalMonths = term * 12;
                
                for (let month = 1; month <= totalMonths; month++) {
                    let interest = balance * monthlyRate;
                    let principalPaid = emi - interest;
                    balance = Math.max(0, balance - principalPaid);
                    totalInterest += interest;

                    if (view === 'yearly' && month % 12 === 0) {
                        schedule.push({
                            period: month / 12,
                            emi: emi * 12,
                            principal: principalPaid * 12,
                            interest: totalInterest,
                            balance: balance
                        });
                    } else if (view === 'monthly') {
                        schedule.push({
                            period: month,
                            emi: emi,
                            principal: principalPaid,
                            interest: interest,
                            balance: balance
                        });
                    }
                }

                return schedule;
            }

            // Update payment schedule table
            function updateSchedule(schedule, view) {
                amortizationSchedule.innerHTML = '';
                schedule.forEach(payment => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${view === 'yearly' ? 'Year ' + payment.period : 'Month ' + payment.period}</td>
                        <td>₹${formatNumber(payment.emi)}</td>
                        <td>₹${formatNumber(payment.principal)}</td>
                        <td>₹${formatNumber(payment.interest)}</td>
                        <td>₹${formatNumber(payment.balance)}</td>
                    `;
                    amortizationSchedule.appendChild(row);
                });
            }

            // Form submission handler
            loanForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const principal = parseFloat(document.getElementById('loanAmount').value);
                const rate = parseFloat(document.getElementById('interestRate').value);
                let term = parseFloat(document.getElementById('loanTerm').value);
                const termType = document.getElementById('termType').value;

                // Convert months to years if needed
                if (termType === 'months') {
                    term = term / 12;
                }

                // Calculate EMI
                const emi = calculateEMI(principal, rate, term);

                // Update summary
                document.getElementById('monthlyEMI').textContent = formatNumber(emi);
                document.getElementById('principalAmount').textContent = formatNumber(principal);
                document.getElementById('totalInterest').textContent = formatNumber(emi * term * 12 - principal);
                document.getElementById('totalPayment').textContent = formatNumber(emi * term * 12);

                // Show summary section
                summarySection.style.display = 'flex';

                // Generate and display schedule
                const schedule = generateSchedule(principal, rate, term, emi, scheduleView.value);
                updateSchedule(schedule, scheduleView.value);
            });

            // Schedule view change handler
            scheduleView.addEventListener('change', function() {
                const principal = parseFloat(document.getElementById('loanAmount').value);
                const rate = parseFloat(document.getElementById('interestRate').value);
                let term = parseFloat(document.getElementById('loanTerm').value);
                const termType = document.getElementById('termType').value;

                if (termType === 'months') {
                    term = term / 12;
                }

                const emi = calculateEMI(principal, rate, term);
                const schedule = generateSchedule(principal, rate, term, emi, this.value);
                updateSchedule(schedule, this.value);
            });

            // Reset form handler
            loanForm.addEventListener('reset', function() {
                summarySection.style.display = 'none';
                amortizationSchedule.innerHTML = '';
            });
        });
    </script>
</body>
</html>