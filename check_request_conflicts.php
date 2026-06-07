<?php
session_start();
require_once('../config/connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

/** @var mysqli $conn */

$request_id = intval($_GET['id'] ?? 0);
if (!$request_id) {
    echo json_encode(['conflicts' => []]);
    exit();
}

// Fetch the request being checked
$stmt = $conn->prepare(
    "SELECT trip_date, departure_time, return_time FROM trip_requests WHERE id = ?"
);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['conflicts' => []]);
    exit();
}

$trip_date = $row['trip_date'];
$dep_time  = $row['departure_time'] ?: '00:00:00';
$ret_time  = $row['return_time']    ?: '23:59:59';

// Find approved/completed requests on the same date with overlapping time windows.
// Overlap condition: dep_A < ret_B  AND  ret_A > dep_B
// Using IFNULL instead of COALESCE for broader MySQL compatibility.
$conflict_stmt = $conn->prepare("
    SELECT id, requester_name, destination, trip_date,
           departure_time, return_time, status
    FROM trip_requests
    WHERE id       != ?
      AND trip_date = ?
      AND status   IN ('approved', 'completed')
      AND departure_time                        < IFNULL(?, '23:59:59')
      AND IFNULL(return_time, '23:59:59')       > ?
    ORDER BY departure_time ASC
");
$conflict_stmt->bind_param("isss", $request_id, $trip_date, $ret_time, $dep_time);
$conflict_stmt->execute();
$result = $conflict_stmt->get_result();

$conflicts = [];
while ($c = $result->fetch_assoc()) {
    $conflicts[] = [
        'id'             => (int) $c['id'],
        'requester_name' => $c['requester_name'],
        'destination'    => $c['destination'],
        'trip_date'      => date('M j, Y', strtotime($c['trip_date'])),
        'departure_time' => date('g:i A',  strtotime($c['departure_time'])),
        'return_time'    => $c['return_time']
                            ? date('g:i A', strtotime($c['return_time']))
                            : 'End of day',
        'status'         => $c['status'],
    ];
}

echo json_encode(['conflicts' => $conflicts]);
