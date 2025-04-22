<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($transaction_id <= 0) {
    $_SESSION['error'] = "Invalid transaction ID";
    header('Location: transactions.php');
    exit();
}

// First verify the transaction belongs to the user
$sql = "SELECT id FROM transactions WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $transaction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Transaction not found";
    header('Location: transactions.php');
    exit();
}

// Delete the transaction
$sql = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $transaction_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success'] = "Transaction deleted successfully";
} else {
    $_SESSION['error'] = "Failed to delete transaction";
}

header('Location: transactions.php');
?>
