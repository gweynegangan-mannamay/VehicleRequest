<?php
session_start();
require_once('../config/connection.php');

// Check if logged in AND if the role is Admin (1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    http_response_code(403);
    exit();
}

// Declare $conn for static analysis
/** @var mysqli $conn */

$request_id = $_GET['id'] ?? 0;

$query = "SELECT * FROM trip_requests WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $request = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($request);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found']);
}
