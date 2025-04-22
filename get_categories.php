<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
if (!in_array($type, ['income', 'expense'])) {
    http_response_code(400);
    exit('Invalid type');
}

$sql = "SELECT id, name FROM categories WHERE type = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $type);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

header('Content-Type: application/json');
echo json_encode($categories);
