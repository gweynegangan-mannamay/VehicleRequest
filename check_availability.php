<?php
session_start();
require_once('../config/connection.php');

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

/** @var mysqli $conn */

// Get parameters
$trip_date = $_GET['trip_date'] ?? '';
$departure_time = $_GET['departure_time'] ?? '';
$return_time = $_GET['return_time'] ?? '';

if (empty($trip_date) || empty($departure_time)) {
    echo json_encode([
        'success' => false,
        'error' => 'Trip date and departure time are required'
    ]);
    exit();
}

// Combine date and time for comparison
$requested_start = $trip_date . ' ' . $departure_time;
$requested_end = !empty($return_time) ? $trip_date . ' ' . $return_time : $trip_date . ' 23:59:59';

// Get all drivers
$drivers_query = "SELECT * FROM drivers WHERE status = 'active' OR status IS NULL ORDER BY driver_name ASC";
$drivers_result = $conn->query($drivers_query);

$availability = [];

while ($driver = $drivers_result->fetch_assoc()) {
    $driver_id = $driver['id'];
    
    // Check if driver has any conflicting trips from trips table
    $trips_conflict_query = "SELECT COUNT(*) as conflict_count 
                             FROM trips 
                             WHERE driver_id = ? 
                             AND (
                                 (departure_date <= ? AND arrival_date >= ?) OR
                                 (departure_date >= ? AND departure_date <= ?) OR
                                 (arrival_date >= ? AND arrival_date <= ?)
                             )";
    
    $stmt = $conn->prepare($trips_conflict_query);
    $stmt->bind_param("issssss", 
        $driver_id, 
        $requested_end, $requested_start,
        $requested_start, $requested_end,
        $requested_start, $requested_end
    );
    $stmt->execute();
    $trips_result = $stmt->get_result();
    $trips_data = $trips_result->fetch_assoc();
    $trips_conflicts = $trips_data['conflict_count'];
    
    // Check if driver has any conflicting approved trip requests
    $requests_conflict_query = "SELECT COUNT(*) as conflict_count,
                                 GROUP_CONCAT(CONCAT('#', id, ' - ', destination) SEPARATOR ', ') as conflicting_trips
                                 FROM trip_requests tr
                                 WHERE status IN ('approved', 'completed')
                                 AND trip_date = ?
                                 AND (
                                     (departure_time <= ? AND (return_time >= ? OR return_time IS NULL)) OR
                                     (departure_time >= ? AND departure_time <= ?)
                                 )";
    
    $stmt2 = $conn->prepare($requests_conflict_query);
    $return_time_value = $return_time ?: '23:59:59';
    $stmt2->bind_param("sssss", 
        $trip_date,
        $return_time_value, $departure_time,
        $departure_time, $return_time_value
    );
    $stmt2->execute();
    $requests_result = $stmt2->get_result();
    $requests_data = $requests_result->fetch_assoc();
    $requests_conflicts = $requests_data['conflict_count'];
    $conflicting_trips = $requests_data['conflicting_trips'];
    
    // Determine availability
    $total_conflicts = $trips_conflicts + $requests_conflicts;
    $is_available = $total_conflicts == 0;
    
    $availability[] = [
        'driver_id' => $driver['id'],
        'driver_name' => $driver['driver_name'],
        'vehicle_name' => $driver['vehicle_name'],
        'plate_number' => $driver['plate_number'],
        'is_available' => $is_available,
        'conflicts' => $total_conflicts,
        'conflicting_trips' => $conflicting_trips ?? null
    ];
}

// Get statistics
$total_drivers = count($availability);
$available_list = array_filter($availability, function($d) { return $d['is_available']; });
$available_drivers = count($available_list);
$unavailable_drivers = $total_drivers - $available_drivers;

echo json_encode([
    'success' => true,
    'requested_date' => $trip_date,
    'requested_time' => $departure_time . ' - ' . ($return_time ?: 'End of day'),
    'availability' => $availability,
    'statistics' => [
        'total' => $total_drivers,
        'available' => $available_drivers,
        'unavailable' => $unavailable_drivers
    ]
]);
