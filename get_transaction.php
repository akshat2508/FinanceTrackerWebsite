<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT * FROM transactions WHERE id = $transaction_id AND user_id = $user_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $transaction = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($transaction);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Transaction not found']);
}
?>
