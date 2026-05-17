<?php
session_start();
require_once('../config/connection.php');

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Declare $conn for static analysis
/** @var mysqli $conn */

// Get parameters
$driver_id = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0;
$departure_date = isset($_GET['departure_date']) ? $_GET['departure_date'] : '';
$arrival_date = isset($_GET['arrival_date']) ? $_GET['arrival_date'] : '';

if (!$driver_id || !$departure_date) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Convert datetime-local format to MySQL datetime format
$departure_datetime = date('Y-m-d H:i:s', strtotime($departure_date));
$arrival_datetime = $arrival_date ? date('Y-m-d H:i:s', strtotime($arrival_date)) : null;

// Extract just the date for checking
$trip_date = date('Y-m-d', strtotime($departure_date));

// Check for conflicts in trips table (completed/ongoing trips)
$conflict_query = "SELECT t.*, d.driver_name 
                   FROM trips t
                   JOIN drivers d ON t.driver_id = d.id
                   WHERE t.driver_id = ? 
                   AND DATE(t.departure_date) = ?";

$stmt = $conn->prepare($conflict_query);
$stmt->bind_param("is", $driver_id, $trip_date);
$stmt->execute();
$trips_result = $stmt->get_result();

if ($trips_result->num_rows > 0) {
    $conflict = $trips_result->fetch_assoc();
    $conflict_time = date('g:i A', strtotime($conflict['departure_date']));
    $conflict_destination = htmlspecialchars($conflict['place_visited']);
    
    echo json_encode([
        'available' => false,
        'conflict_message' => "Driver is already assigned to a trip on " . date('M j, Y', strtotime($trip_date)) . " at {$conflict_time} to {$conflict_destination}."
    ]);
    exit();
}

// Check for conflicts in trip_requests table (approved requests)
$request_conflict_query = "SELECT tr.*, d.driver_name 
                           FROM trip_requests tr
                           JOIN drivers d ON tr.assigned_driver_id = d.id
                           WHERE tr.assigned_driver_id = ? 
                           AND tr.status = 'approved'
                           AND tr.trip_date = ?";

$stmt2 = $conn->prepare($request_conflict_query);
$stmt2->bind_param("is", $driver_id, $trip_date);
$stmt2->execute();
$requests_result = $stmt2->get_result();

if ($requests_result->num_rows > 0) {
    $conflict = $requests_result->fetch_assoc();
    $conflict_time = date('g:i A', strtotime($conflict['departure_time']));
    $conflict_destination = htmlspecialchars($conflict['destination']);
    $requester_name = htmlspecialchars($conflict['requester_name']);
    
    echo json_encode([
        'available' => false,
        'conflict_message' => "Driver is already reserved by {$requester_name} on " . date('M j, Y', strtotime($trip_date)) . " at {$conflict_time} to {$conflict_destination}."
    ]);
    exit();
}

// Driver is available
echo json_encode([
    'available' => true
]);
