<?php
session_start();
require_once('../config/connection.php');
require_once('../includes/notification_helper.php');

// Check if logged in AND if the role is User (0)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 0) {
    header("Location: ../auth/login.php");
    exit();
}

// Declare $conn for static analysis
/** @var mysqli $conn */

// Get unread notification count
$notif_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $_SESSION['user_id']);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$notif_data = $notif_result->fetch_assoc();
$unread_count = $notif_data['unread_count'];

// Get dashboard statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests
    FROM trip_requests WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $_SESSION['user_id']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_request'])) {
    $requester_name = $_POST['requester_name'];
    $requester_position = $_POST['requester_position'];
    $department = $_POST['department'];
    $passenger_names = $_POST['passenger_names'];
    $destination = $_POST['destination'];
    $purpose = $_POST['purpose'];
    $trip_date = $_POST['trip_date'];
    $departure_time = $_POST['departure_time'];
    $return_time = $_POST['return_time'];
    $number_of_passengers = $_POST['number_of_passengers'];
    $special_requirements = $_POST['special_requirements'];
    
    $insert_query = "INSERT INTO trip_requests (user_id, requester_name, requester_position, department, 
                     passenger_names, destination, purpose, trip_date, departure_time, return_time, 
                     number_of_passengers, special_requirements, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isssssssssss", 
        $_SESSION['user_id'], 
        $requester_name, 
        $requester_position, 
        $department,
        $passenger_names, 
        $destination, 
        $purpose, 
        $trip_date, 
        $departure_time, 
        $return_time,
        $number_of_passengers, 
        $special_requirements
    );
    
    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        
        // Create notification for user
        notifyRequestSubmitted($conn, $_SESSION['user_id'], $request_id);
        
        // Notify all admins about new request
        notifyAdminNewRequest($conn, $requester_name, $request_id, $destination);
        
        header("Location: index.php?success=1");
        exit();
    } else {
        header("Location: index.php?error=1");
        exit();
    }
}

// Check for success/error messages
if (isset($_GET['success'])) {
    $success_message = "Trip request submitted successfully! Waiting for admin approval.";
}
if (isset($_GET['error'])) {
    $error_message = "Error submitting request. Please try again.";
}

// Fetch user's trip requests
$requests_query = "SELECT * FROM trip_requests WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$requests_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VehiQuest - User Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .logo-text h1 {
            font-size: 20px;
            color: #1a202c;
            font-weight: 700;
        }

        .logo-text p {
            font-size: 12px;
            color: #718096;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .logout-btn {
            padding: 8px 20px;
            background: #1a202c;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            position: relative;
        }

        .logout-btn:hover {
            background: #2d3748;
            transform: translateY(-1px);
        }

        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #f56565;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .page-header h2 {
            font-size: 24px;
            color: #1a202c;
            margin-bottom: 4px;
        }

        .page-header p {
            color: #718096;
            font-size: 14px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: all 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .stat-card.total { border-left-color: #1e7e34; }
        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.approved { border-left-color: #1e7e34; }
        .stat-card.completed { border-left-color: #27ae60; }

        .stat-label {
            font-size: 12px;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
            background: white;
            padding: 0 20px;
            border-radius: 10px 10px 0 0;
        }

        .tab {
            padding: 10px 20px;
            background: none;
            border: none;
            color: #718096;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab:hover {
            color: #1e7e34;
        }

        .tab.active {
            color: #1e7e34;
            border-bottom-color: #1e7e34;
        }

        /* Content Sections */
        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Card */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
        }

        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }

        .alert-error {
            background: #fff5f5;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 6px;
        }

        .form-label .required {
            color: #f56565;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #1e7e34;
            box-shadow: 0 0 0 3px rgba(30, 126, 52, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 70px;
        }

        .btn-primary {
            padding: 12px 28px;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 126, 52, 0.3);
        }

        /* Table */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            background: #f7fafc;
            font-size: 12px;
            font-weight: 700;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background: #f7fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fffaf0;
            color: #c05621;
        }

        .status-approved {
            background: #f0fff4;
            color: #22543d;
        }

        .status-rejected {
            background: #fff5f5;
            color: #742a2a;
        }

        .status-completed {
            background: #ebf8ff;
            color: #2c5282;
        }

        .btn-view {
            padding: 6px 16px;
            background: #1e7e34;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-view:hover {
            background: #27ae60;
            transform: translateY(-1px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #718096;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                overflow-x: auto;
            }

            .availability-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .driver-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-content">
        <div class="logo">
            <div class="logo-icon">
                <img src="../assets/images/isu-logo.png" alt="Isabela State University">
            </div>
            <div class="logo-text">
                <h1>VehiQuest</h1>
                <p>Vehicle Request System</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="notifications.php" class="logout-btn" style="margin-right: 10px;">
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</div>

<!-- Main Container -->
<div class="container">
    <!-- Page Header -->
    <div class="page-header">
        <h2>Dashboard</h2>
        <p>Manage your vehicle trip requests</p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Requests</div>
            <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
        </div>

        <div class="stat-card pending">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
        </div>

        <div class="stat-card approved">
            <div class="stat-label">Approved</div>
            <div class="stat-value"><?php echo $stats['approved_requests']; ?></div>
        </div>

        <div class="stat-card completed">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?php echo $stats['completed_requests']; ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="showTab('new-request')">New Request</button>
        <button class="tab" onclick="showTab('my-requests')">My Requests</button>
    </div>

    <!-- New Request Section -->
    <div class="content-section active" id="new-request-section">
        <div class="card">
            <h3 class="card-title">Submit New Trip Request</h3>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Your Name <span class="required">*</span></label>
                        <input type="text" name="requester_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Position/Title <span class="required">*</span></label>
                        <input type="text" name="requester_position" class="form-input" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Department/Office <span class="required">*</span></label>
                        <input type="text" name="department" class="form-input" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Names of Passengers <span class="required">*</span></label>
                        <textarea name="passenger_names" class="form-textarea" required placeholder="Separate multiple names with commas"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Destination <span class="required">*</span></label>
                        <input type="text" name="destination" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Number of Passengers <span class="required">*</span></label>
                        <input type="number" name="number_of_passengers" class="form-input" min="1" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Purpose of Trip <span class="required">*</span></label>
                        <textarea name="purpose" class="form-textarea" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Trip Date <span class="required">*</span></label>
                        <input type="date" name="trip_date" id="trip_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Departure Time <span class="required">*</span></label>
                        <input type="time" name="departure_time" id="departure_time" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expected Return Time</label>
                        <input type="time" name="return_time" id="return_time" class="form-input">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Special Requirements/Notes</label>
                        <textarea name="special_requirements" class="form-textarea" placeholder="Any special arrangements, equipment needed, etc."></textarea>
                    </div>
                </div>

                <button type="submit" name="submit_request" class="btn-primary">Submit Request</button>
            </form>
        </div>
    </div>

    <!-- My Requests Section -->
    <div class="content-section" id="my-requests-section">
        <div class="card">
            <h3 class="card-title">My Trip Requests</h3>
            
            <?php if ($requests_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Destination</th>
                                <th>Trip Date</th>
                                <th>Passengers</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $requests_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $request['id']; ?></td>
                                    <td><?php echo htmlspecialchars($request['destination']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($request['trip_date'])); ?></td>
                                    <td><?php echo $request['number_of_passengers']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php echo $request['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn-view">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No requests yet</h3>
                    <p>Submit your first trip request to get started</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById(tabName + '-section').classList.add('active');
    
    // Add active class to clicked tab
    event.target.classList.add('active');
}
</script>

</body>
</html>
