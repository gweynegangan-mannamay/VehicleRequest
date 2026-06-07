<?php
session_start();
require_once('../config/connection.php');
require_once('../includes/notification_helper.php');
require_once('../includes/email_helper.php');

// Check if logged in AND if the role is Admin (1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

/** @var mysqli $conn */

// ── Collect POST data ────────────────────────────────────────
$driver_id          = (int)   ($_POST['driver_id']          ?? 0);
$driver_name        = trim(    $_POST['driver_name']         ?? '');
$vehicle_name       = trim(    $_POST['vehicle_name']        ?? '');
$plate_number       = trim(    $_POST['plate_number']        ?? '');
$requester_name     = trim(    $_POST['requester_name']      ?? '');
$passenger_names    = trim(    $_POST['passenger_names']     ?? '');
$place_visited      = trim(    $_POST['place_visited']       ?? '');
$purpose            = trim(    $_POST['purpose']             ?? '');
$authorized_by      = trim(    $_POST['authorized_by']       ?? '');
$departure_date     = trim(    $_POST['departure_date']      ?? '');
$arrival_date       = trim(    $_POST['arrival_date']        ?? '');
$items_purchased    = trim(    $_POST['items_purchased']     ?? '');
$gasoline_issued    = (float)  ($_POST['gasoline_issued']    ?? 0);
$gasoline_purchased = (float)  ($_POST['gasoline_purchased'] ?? 0);
$oil_issued         = (float)  ($_POST['oil_issued']         ?? 0);
$gear_oil           = (float)  ($_POST['gear_oil']           ?? 0);
$grease_issued      = trim(    $_POST['grease_issued']       ?? '');
$speedometer_start  = (float)  ($_POST['speedometer_start']  ?? 0);
$speedometer_end    = (float)  ($_POST['speedometer_end']    ?? 0);
$distance_traveled  = (float)  ($_POST['distance_traveled']  ?? 0);
$remarks            = trim(    $_POST['remarks']             ?? '');
$created_by         = (int)    $_SESSION['user_id'];

// ── Insert trip ticket ───────────────────────────────────────
// Columns: driver_id, driver_name, vehicle_name, plate_number,
//          requester_name, passenger_names, passenger_name (legacy),
//          place_visited, purpose, authorized_by,
//          departure_date, arrival_date, items_purchased,
//          gasoline_issued, gasoline_purchased, oil_issued, gear_oil,
//          grease_issued, speedometer_start, speedometer_end,
//          distance_traveled, remarks, created_by
$insert_query = "INSERT INTO trips (
                    driver_id, driver_name, vehicle_name, plate_number,
                    requester_name, passenger_names, passenger_name,
                    place_visited, purpose, authorized_by,
                    departure_date, arrival_date, items_purchased,
                    gasoline_issued, gasoline_purchased, oil_issued, gear_oil,
                    grease_issued, speedometer_start, speedometer_end,
                    distance_traveled, remarks, created_by
                 ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?
                 )";

$stmt = $conn->prepare($insert_query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// i  = driver_id
// s  = driver_name, vehicle_name, plate_number
// s  = requester_name, passenger_names, passenger_name (legacy = same as requester)
// s  = place_visited, purpose, authorized_by
// s  = departure_date, arrival_date, items_purchased
// d  = gasoline_issued, gasoline_purchased, oil_issued, gear_oil
// s  = grease_issued
// d  = speedometer_start, speedometer_end, distance_traveled
// s  = remarks
// i  = created_by
// Total: i sss sss sss sss dddd s ddd s i  = 23 params
$stmt->bind_param(
    "issssssssssssddddsdddsi",
    $driver_id,
    $driver_name, $vehicle_name, $plate_number,
    $requester_name, $passenger_names, $requester_name,   // passenger_name = requester_name for legacy compat
    $place_visited, $purpose, $authorized_by,
    $departure_date, $arrival_date, $items_purchased,
    $gasoline_issued, $gasoline_purchased, $oil_issued, $gear_oil,
    $grease_issued,
    $speedometer_start, $speedometer_end, $distance_traveled,
    $remarks,
    $created_by
);

if (!$stmt->execute()) {
    die("Error saving trip ticket: " . $stmt->error);
}

$trip_id = $stmt->insert_id;

// ── Handle request linkage ───────────────────────────────────
$request_id = (int) ($_POST['request_id'] ?? 0);

if ($request_id > 0) {
    // Mark the originating request as completed
    $upd = $conn->prepare("UPDATE trip_requests SET status = 'completed' WHERE id = ?");
    $upd->bind_param("i", $request_id);
    $upd->execute();

    // Fetch the requester's user account for notifications
    $user_stmt = $conn->prepare(
        "SELECT tr.user_id, tr.requester_name, ui.email
         FROM trip_requests tr
         JOIN user_info ui ON ui.id = tr.user_id
         WHERE tr.id = ?"
    );
    $user_stmt->bind_param("i", $request_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();

    if ($user_data) {
        $formatted_date = date('F j, Y', strtotime($departure_date));

        // In-app notification
        notifyTicketProcessed(
            $conn,
            $user_data['user_id'],
            $trip_id,
            $place_visited,
            $formatted_date,
            $driver_name,
            $vehicle_name
        );

        // Email notification
        if (!empty($user_data['email'])) {
            sendTicketReadyEmail(
                $user_data['email'],
                $user_data['requester_name'],
                $place_visited,
                $formatted_date,
                $driver_name,
                $vehicle_name,
                $plate_number,
                $trip_id          // ← pass trip_id so email links directly to ticket
            );
        }
    }
}

// ── Redirect to printable ticket ────────────────────────────
header("Location: print_ticket.php?trip_id=" . $trip_id);
exit();
