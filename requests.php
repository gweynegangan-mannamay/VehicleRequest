<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once('../config/connection.php');
require_once('../includes/notification_helper.php');

// Check if logged in AND if the role is Admin (1)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

// Declare $conn for static analysis
/** @var mysqli $conn */

// Handle delete request
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $delete_query = "DELETE FROM trip_requests WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $success_message = "Request deleted successfully!";
    } else {
        $error_message = "Error deleting request.";
    }
}

// Handle approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    // Fetch request details and user info
    $request_query = "SELECT tr.*, ui.id as user_id 
                      FROM trip_requests tr 
                      LEFT JOIN user_info ui ON tr.user_id = ui.id 
                      WHERE tr.id = ?";
    $stmt_fetch = $conn->prepare($request_query);
    $stmt_fetch->bind_param("i", $request_id);
    $stmt_fetch->execute();
    $request_data = $stmt_fetch->get_result()->fetch_assoc();
    
    if ($action == 'approve') {
        // ── Conflict detection ───────────────────────────────
        $dep_time = $request_data['departure_time'] ?? '00:00:00';
        $ret_time = $request_data['return_time']    ?: '23:59:59';
        $trip_date = $request_data['trip_date']     ?? '';

        $conflict_check = $conn->prepare("
            SELECT id, requester_name, destination, departure_time, return_time
            FROM trip_requests
            WHERE id        != ?
              AND trip_date  = ?
              AND status    IN ('approved', 'completed')
              AND departure_time < ?
              AND COALESCE(return_time, '23:59:59') > ?
            LIMIT 1
        ");
        $conflict_check->bind_param("isss", $request_id, $trip_date, $ret_time, $dep_time);
        $conflict_check->execute();
        $conflict_row = $conflict_check->get_result()->fetch_assoc();

        if ($conflict_row && !isset($_POST['force_approve'])) {
            $conflict_warning = sprintf(
                "⚠️ Scheduling conflict: Request #%d by %s to %s (%s – %s) overlaps this trip. " .
                "Approve anyway by checking the box below.",
                $conflict_row['id'],
                htmlspecialchars($conflict_row['requester_name']),
                htmlspecialchars($conflict_row['destination']),
                date('g:i A', strtotime($conflict_row['departure_time'])),
                $conflict_row['return_time']
                    ? date('g:i A', strtotime($conflict_row['return_time']))
                    : 'end of day'
            );
        } else {
            // No conflict (or admin forced approval) — proceed
            $update_query = "UPDATE trip_requests SET status = 'approved', approved_by = ?, approved_at = NOW(), admin_notes = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("isi", $_SESSION['user_id'], $admin_notes, $request_id);
            $stmt->execute();
            $success_message = "Trip request approved successfully!";

            if ($request_data) {
                notifyRequestApproved($conn, $request_data['user_id'], $request_id, $admin_notes);
            }
        } // end conflict check
    } elseif ($action == 'reject') {
        $update_query = "UPDATE trip_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), admin_notes = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("isi", $_SESSION['user_id'], $admin_notes, $request_id);
        $stmt->execute();
        $success_message = "Trip request rejected.";
        
        // Create notification for user
        if ($request_data) {
            notifyRequestRejected($conn, $request_data['user_id'], $request_id, $admin_notes);
        }
    }
} // end POST handler

// Fetch all trip requests
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_clause = "";
$where_conditions = [];

if ($filter == 'pending') {
    $where_conditions[] = "status = 'pending'";
} elseif ($filter == 'approved') {
    $where_conditions[] = "status = 'approved'";
} elseif ($filter == 'rejected') {
    $where_conditions[] = "status = 'rejected'";
}

if (!empty($search)) {
    $search_term = "%" . $conn->real_escape_string($search) . "%";
    $where_conditions[] = "(tr.requester_name LIKE '$search_term' OR tr.destination LIKE '$search_term' OR tr.department LIKE '$search_term' OR ui.username LIKE '$search_term')";
}

if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Check if trip_requests table exists
$table_check = $conn->query("SHOW TABLES LIKE 'trip_requests'");
if ($table_check->num_rows == 0) {
    die("Error: trip_requests table does not exist. Please run trip_requests_table.sql first.");
}

$requests_query = "SELECT tr.*, ui.username 
                   FROM trip_requests tr 
                   LEFT JOIN user_info ui ON tr.user_id = ui.id 
                   $where_clause 
                   ORDER BY tr.created_at DESC";
$requests_result = $conn->query($requests_query);

if (!$requests_result) {
    die("Query error: " . $conn->error);
}

// Count statistics
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM trip_requests";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests - Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            padding: 25px 30px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-text h1 {
            color: white;
            font-size: 24px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            margin: 0;
            font-weight: 700;
        }

        .logo-text p {
            color: rgba(255,255,255,0.9);
            font-size: 12px;
            margin: 0;
            margin-top: 2px;
        }

        .nav-menu {
            display: flex;
            gap: 15px;
            padding-top: 20px;
            border-top: 2px solid rgba(255,255,255,0.2);
        }

        .nav-btn {
            padding: 12px 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .nav-btn.active {
            background: white;
            color: #1e7e34;
        }

        .logout-btn {
            padding: 12px 25px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
        }

        .container {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-title {
            color: #1e7e34;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #1e7e34;
            font-size: 24px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-conflict {
            background: #fff3cd;
            color: #856404;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #ffc107;
            font-size: 14px;
            font-weight: 500;
        }

        .conflict-banner {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            border-left: 4px solid #e0a800;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .conflict-banner strong { display: block; margin-bottom: 4px; font-size: 13px; }

        .conflict-item {
            padding: 4px 0;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            font-size: 12px;
        }

        .conflict-item:last-child { border-bottom: none; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            padding: 20px;
            border-radius: 15px;
            color: white;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 32px;
        }

        .stat-card.pending {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        }

        .stat-card.approved {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .stat-card.rejected {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border-radius: 25px;
            text-decoration: none;
            color: #555;
            font-weight: 600;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
        }

        .search-box {
            margin-left: auto;
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            width: 300px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1e7e34;
        }

        .search-box button {
            padding: 10px 20px;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-scroll {
            max-height: 480px;
            overflow-y: auto;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            scrollbar-width: thin;
            scrollbar-color: #1e7e34 #f0f0f0;
        }

        .table-scroll::-webkit-scrollbar {
            width: 8px;
        }

        .table-scroll::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 0 10px 10px 0;
        }

        .table-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #1e7e34, #f39c12);
            border-radius: 10px;
        }

        .table-scroll::-webkit-scrollbar-thumb:hover {
            background: #1e7e34;
        }

        /* Freeze the header row while scrolling */
        .table-scroll table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
        }

        table thead {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
        }

        table tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .view-btn {
            padding: 6px 15px;
            background: #1e7e34;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-right: 5px;
        }

        .delete-btn {
            padding: 6px 15px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 25px;
            border-radius: 15px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #1e7e34;
            flex-shrink: 0;
        }

        .modal-header h2 {
            color: #1e7e34;
            font-size: 20px;
            margin: 0;
        }

        .close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
            line-height: 1;
            transition: color 0.2s;
        }

        .close:hover {
            color: #333;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 15px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 160px 1fr;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            gap: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
        }

        .detail-value {
            color: #333;
            font-size: 14px;
        }

        .modal-footer {
            flex-shrink: 0;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }

        .modal-footer textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 12px;
            resize: vertical;
            min-height: 60px;
        }

        .modal-footer textarea:focus {
            outline: none;
            border-color: #1e7e34;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-approve, .btn-reject {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .btn-create-ticket {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-create-ticket:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 126, 52, 0.4);
        }

        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-top">
        <div class="logo">
            <div class="logo-icon">
                <img src="../assets/images/isu-logo.png" alt="ISU Logo">
            </div>
            <div class="logo-text">
                <h1>VehiQuest</h1>
                <p>Manage Requests</p>
            </div>
        </div>
        <a href="../auth/logout.php" class="logout-btn">Logout</a>
    </div>
    <div class="nav-menu">
        <a href="requests.php" class="nav-btn active">Manage Requests</a>
        <a href="manage_drivers.php" class="nav-btn">Manage Drivers</a>
    </div>
</div>

<div class="container">
    <?php if (isset($success_message)): ?>
        <div class="alert-success">✓ <?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (isset($conflict_warning)): ?>
        <div class="alert-conflict">
            <?php echo $conflict_warning; ?>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="request_id" value="<?php echo intval($_POST['request_id']); ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="admin_notes" value="<?php echo htmlspecialchars($_POST['admin_notes'] ?? ''); ?>">
                <input type="hidden" name="force_approve" value="1">
                <label style="font-size:13px;cursor:pointer;">
                    <input type="checkbox" onchange="this.form.submit()" style="margin-right:6px;">
                    I understand the conflict — approve anyway
                </label>
            </form>
        </div>
    <?php endif; ?>

    <h2 class="page-title">📋 Trip Request Management</h2>

    <div class="stats-row">
        <div class="stat-card">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total</p>
        </div>
        <div class="stat-card pending">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>Pending</p>
        </div>
        <div class="stat-card approved">
            <h3><?php echo $stats['approved']; ?></h3>
            <p>Approved</p>
        </div>
        <div class="stat-card rejected">
            <h3><?php echo $stats['rejected']; ?></h3>
            <p>Rejected</p>
        </div>
    </div>

    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">All</a>
        <a href="?filter=pending" class="filter-tab <?php echo $filter == 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?filter=approved" class="filter-tab <?php echo $filter == 'approved' ? 'active' : ''; ?>">Approved</a>
        <a href="?filter=rejected" class="filter-tab <?php echo $filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
        
        <div class="search-box">
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="text" name="search" placeholder="Search by name, destination, department..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>
        </div>
    </div>

    <?php if ($requests_result->num_rows > 0): ?>
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Requester</th>
                    <th>Department</th>
                    <th>Destination</th>
                    <th>Trip Date</th>
                    <th>Passengers</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($request = $requests_result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $request['id']; ?></td>
                        <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                        <td><?php echo htmlspecialchars($request['department']); ?></td>
                        <td><?php echo htmlspecialchars($request['destination']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($request['trip_date'])); ?></td>
                        <td><?php echo $request['number_of_passengers']; ?></td>
                        <td><span class="status-badge status-<?php echo $request['status']; ?>"><?php echo $request['status']; ?></span></td>
                        <td>
                            <button class="view-btn" onclick="viewRequest(<?php echo $request['id']; ?>)">View</button>
                            <button class="delete-btn" onclick="confirmDelete(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['requester_name']); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <p style="text-align: center; padding: 40px; color: #999;">No requests found.</p>
    <?php endif; ?>
</div>

<div id="requestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Request Details</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer" id="modalFooter"></div>
    </div>
</div>

<script>
function viewRequest(requestId) {
    // Run both fetches in parallel
    Promise.all([
        fetch('../api/get_request_details.php?id=' + requestId).then(r => r.json()),
        fetch('../api/check_request_conflicts.php?id=' + requestId).then(r => r.json())
    ]).then(([data, cd]) => {

        // ── Request details ───────────────────────────────────
        let bodyHtml = '';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Request ID:</div><div class="detail-value">#' + data.id + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Requester:</div><div class="detail-value">' + data.requester_name + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Position:</div><div class="detail-value">' + data.requester_position + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Department:</div><div class="detail-value">' + data.department + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Passengers:</div><div class="detail-value">' + formatPassengers(data.passenger_names) + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Destination:</div><div class="detail-value">' + data.destination + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Purpose:</div><div class="detail-value">' + data.purpose + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Trip Date:</div><div class="detail-value">' + data.trip_date + '</div></div>';
        bodyHtml += '<div class="detail-row"><div class="detail-label">Departure Time:</div><div class="detail-value">' + data.departure_time + '</div></div>';
        if (data.return_time) {
            bodyHtml += '<div class="detail-row"><div class="detail-label">Return Time:</div><div class="detail-value">' + data.return_time + '</div></div>';
        }
        bodyHtml += '<div class="detail-row"><div class="detail-label">Status:</div><div class="detail-value"><span class="status-badge status-' + data.status + '">' + data.status + '</span></div></div>';

        // ── Conflict banner (pending requests only) ───────────
        if (data.status === 'pending' && cd.conflicts && cd.conflicts.length > 0) {
            bodyHtml += '<div class="conflict-banner" style="margin-top:12px;">';
            bodyHtml += '<strong>⚠️ Scheduling conflict — ' + cd.conflicts.length + ' overlapping approved request' + (cd.conflicts.length > 1 ? 's' : '') + ' on this date/time:</strong>';
            cd.conflicts.forEach(c => {
                bodyHtml += '<div class="conflict-item">';
                bodyHtml += '• <strong>Request #' + c.id + '</strong> &nbsp;' + c.requester_name + ' → ' + c.destination;
                bodyHtml += ' &nbsp;|&nbsp; ' + c.trip_date + ' &nbsp;' + c.departure_time + ' – ' + c.return_time;
                bodyHtml += ' &nbsp;<span class="status-badge status-' + c.status + '">' + c.status + '</span>';
                bodyHtml += '</div>';
            });
            bodyHtml += '</div>';
        }

        document.getElementById('modalBody').innerHTML = bodyHtml;

        // ── Footer actions ────────────────────────────────────
        let footerHtml = '';
        if (data.status === 'pending') {
            footerHtml += '<form method="POST">';
            footerHtml += '<input type="hidden" name="request_id" value="' + data.id + '">';
            footerHtml += '<textarea name="admin_notes" placeholder="Admin notes (optional)"></textarea>';
            footerHtml += '<div class="action-buttons" style="margin-top:10px;">';
            footerHtml += '<button type="submit" name="action" value="approve" class="btn-approve">✓ Approve</button>';
            footerHtml += '<button type="submit" name="action" value="reject" class="btn-reject">✗ Reject</button>';
            footerHtml += '</div></form>';
        } else if (data.status === 'approved') {
            footerHtml += '<div class="action-buttons">';
            footerHtml += '<a href="create_ticket_from_request.php?request_id=' + data.id + '" class="btn-create-ticket">🎫 Create Trip Ticket</a>';
            footerHtml += '</div>';
        }

        document.getElementById('modalFooter').innerHTML = footerHtml;
        document.getElementById('requestModal').style.display = 'block';

    }).catch(err => console.error('viewRequest error:', err));
}

function closeModal() {
    document.getElementById('requestModal').style.display = 'none';
}

// Render [Designation] tags as coloured badges in the passenger list
function formatPassengers(raw) {
    if (!raw) return '—';
    return raw.split(',').map(p => {
        p = p.trim();
        const m = p.match(/^\[([^\]]+)\]\s*(.+)$/);
        if (m) {
            return '<span style="display:inline-block;margin:2px 4px 2px 0;">'
                 + '<span style="background:#e8f5e9;color:#1e7e34;padding:2px 8px;border-radius:10px;'
                 + 'font-size:11px;font-weight:700;text-transform:uppercase;margin-right:4px;">'
                 + escHtml(m[1]) + '</span>'
                 + escHtml(m[2])
                 + '</span>';
        }
        return escHtml(p);
    }).join('<br>');
}

function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function printRequest(requestId) {
    fetch('../api/get_request_details.php?id=' + requestId)
        .then(response => response.json())
        .then(data => {
            const statusColors = {
                pending:  '#856404',
                approved: '#155724',
                rejected: '#721c24',
                completed:'#0c5460'
            };
            const statusBg = {
                pending:  '#fff3cd',
                approved: '#d4edda',
                rejected: '#f8d7da',
                completed:'#d1ecf1'
            };
            const color = statusColors[data.status] || '#333';
            const bg    = statusBg[data.status]    || '#eee';

            const returnRow = data.return_time
                ? `<tr><td style="padding:8px 12px;font-weight:600;color:#555;width:180px;">Return Time</td><td style="padding:8px 12px;">${data.return_time}</td></tr>`
                : '';
            const notesRow = data.admin_notes
                ? `<tr><td style="padding:8px 12px;font-weight:600;color:#555;">Admin Notes</td><td style="padding:8px 12px;">${data.admin_notes}</td></tr>`
                : '';

            const html = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Trip Request #${data.id} - VehiQuest</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; font-size:13px; color:#222; padding:30px; }
        .header { display:flex; align-items:center; gap:16px; border-bottom:3px solid #1e7e34; padding-bottom:16px; margin-bottom:24px; }
        .header img { width:60px; height:60px; object-fit:contain; }
        .header-text h1 { font-size:22px; color:#1e7e34; }
        .header-text p { font-size:12px; color:#666; }
        .doc-title { font-size:18px; font-weight:700; margin-bottom:6px; }
        .status-badge { display:inline-block; padding:4px 14px; border-radius:12px; font-size:12px; font-weight:700;
                        background:${bg}; color:${color}; text-transform:uppercase; margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; margin-bottom:20px; }
        table tr { border-bottom:1px solid #e8e8e8; }
        table tr:last-child { border-bottom:none; }
        td { padding:8px 12px; vertical-align:top; }
        td:first-child { font-weight:600; color:#555; width:180px; white-space:nowrap; }
        .section-title { font-size:13px; font-weight:700; color:#1e7e34; text-transform:uppercase;
                         letter-spacing:.5px; padding:10px 12px; background:#f0f9f2; border-left:4px solid #1e7e34;
                         margin-bottom:4px; }
        .footer { margin-top:40px; border-top:1px solid #ddd; padding-top:16px; font-size:11px; color:#999; text-align:center; }
        @media print {
            body { padding:15px; }
            .footer { position:fixed; bottom:0; width:100%; }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="../assets/images/isu-logo.png" alt="ISU Logo">
        <div class="header-text">
            <h1>VehiQuest</h1>
            <p>Isabela State University — Ilagan Campus</p>
            <p>Vehicle Request Management System</p>
        </div>
    </div>

    <div class="doc-title">Trip Request #${data.id}</div>
    <div class="status-badge">${data.status}</div>

    <div class="section-title">Requester Information</div>
    <table>
        <tr><td>Name</td><td>${data.requester_name}</td></tr>
        <tr><td>Position / Title</td><td>${data.requester_position}</td></tr>
        <tr><td>Department / Office</td><td>${data.department}</td></tr>
    </table>

    <div class="section-title">Trip Details</div>
    <table>
        <tr><td>Destination</td><td>${data.destination}</td></tr>
        <tr><td>Purpose</td><td>${data.purpose}</td></tr>
        <tr><td>Trip Date</td><td>${data.trip_date}</td></tr>
        <tr><td>Departure Time</td><td>${data.departure_time}</td></tr>
        ${returnRow}
    </table>

    <div class="section-title">Passenger Information</div>
    <table>
        <tr><td>No. of Passengers</td><td>${data.number_of_passengers}</td></tr>
        <tr><td>Passenger Names</td><td>${data.passenger_names}</td></tr>
        ${data.special_requirements ? `<tr><td>Special Requirements</td><td>${data.special_requirements}</td></tr>` : ''}
    </table>

    <div class="section-title">Request Timeline</div>
    <table>
        <tr><td>Date Submitted</td><td>${data.created_at}</td></tr>
        ${data.approved_at ? `<tr><td>Date ${data.status.charAt(0).toUpperCase()+data.status.slice(1)}</td><td>${data.approved_at}</td></tr>` : ''}
        ${notesRow}
    </table>

    <div class="footer">
        Printed on ${new Date().toLocaleString()} &nbsp;|&nbsp; VehiQuest — ISU Ilagan Vehicle Request System
    </div>

    <script>window.onload = function(){ window.print(); }<\/script>
</body>
</html>`;

            const win = window.open('', '_blank', 'width=800,height=700');
            win.document.write(html);
            win.document.close();
        });
}

function confirmDelete(id, name) {
    if (confirm('Are you sure you want to delete the request from "' + name + '"?\n\nThis action cannot be undone.')) {
        window.location.href = 'requests.php?delete=' + id + '&filter=<?php echo $filter; ?>';
    }
}

window.onclick = function(event) {
    if (event.target == document.getElementById('requestModal')) {
        closeModal();
    }
}
</script>

</body>
</html>
