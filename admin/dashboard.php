<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First delete all transactions for this user
        $stmt = $conn->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Delete all budget goals for this user
$stmt = $conn->prepare("DELETE FROM budget_goals WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
        
        // Then delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            $success = "User and all their transactions have been deleted successfully";
        } else {
            $conn->rollback();
            $error = "Failed to delete user";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}
// Get total users
$sql = "SELECT COUNT(*) as total_users FROM users WHERE role = 'user'";
$total_users = $conn->query($sql)->fetch_assoc()['total_users'];

// Get total transactions
$sql = "SELECT COUNT(*) as total_transactions FROM transactions";
$total_transactions = $conn->query($sql)->fetch_assoc()['total_transactions'];

// Get recent users
$sql = "SELECT id, username, email, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 10";
$recent_users = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-radius: 10px;
            overflow: hidden;
        }
        .stat-icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        .btn-delete {
            transition: all 0.3s ease;
        }
        .btn-delete:hover {
            transform: scale(1.05);
        }
        .deleted-row {
            animation: fadeOut 0.5s ease forwards;
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; height: 0; padding: 0; margin: 0; border: 0; }
        }
        .navbar-brand {
            font-weight: bold;
        }
        .table thead th {
            background-color: #f8f9fa;
        }
        .alert {
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-chart-line me-2"></i>Admin Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
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

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white stat-card mb-3">
                    <div class="card-body d-flex justify-content-between align-items-center p-4">
                        <div>
                            <h5 class="card-title">Total Users</h5>
                            <h3 id="total-users"><?php echo $total_users; ?></h3>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white stat-card">
                    <div class="card-body d-flex justify-content-between align-items-center p-4">
                        <div>
                            <h5 class="card-title">Total Transactions</h5>
                            <h3><?php echo $total_transactions; ?></h3>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Recent Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="users-table-body">
                                    <?php while($user = $recent_users->fetch_assoc()): ?>
                                    <tr id="user-row-<?php echo $user['id']; ?>">
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo $user['username']; ?></td>
                                        <td><?php echo $user['email']; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-danger btn-delete" 
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                <i class="fas fa-trash-alt me-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete user <strong id="username-to-delete"></strong>? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Delete User</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all delete buttons
        const deleteButtons = document.querySelectorAll('.btn-delete');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const usernameToDelete = document.getElementById('username-to-delete');

        // Add click event to each delete button
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const username = this.getAttribute('data-username');
                
                // Set the delete URL
                confirmDeleteBtn.href = 'dashboard.php?action=delete&id=' + userId;
                
                // Show username in confirmation message
                usernameToDelete.textContent = username;
                
                // Show modal
                deleteModal.show();
            });
        });

        // Add animation when user is deleted (when returning from delete operation with success message)
        if (document.querySelector('.alert-success')) {
            const totalUsersElement = document.getElementById('total-users');
            if (totalUsersElement) {
                // Visual update to total users count
                totalUsersElement.style.transition = 'color 0.5s ease';
                totalUsersElement.style.color = '#1CE589';
                setTimeout(() => {
                    totalUsersElement.style.color = '';
                }, 1500);
            }
        }
    });
    </script>
</body>
</html>