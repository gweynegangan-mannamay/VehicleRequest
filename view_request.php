<?php
session_start();
require_once('../config/connection.php');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Declare $conn for static analysis
/** @var mysqli $conn */

// Get request ID
$request_id = $_GET['id'] ?? 0;

// Fetch request details
$query = "SELECT * FROM trip_requests WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$request = $result->fetch_assoc();

// Check if user owns this request (for regular users)
if ($_SESSION['role'] == 0 && $request['user_id'] != $_SESSION['user_id']) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request #<?php echo $request['id']; ?> - VehiQuest</title>
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
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 24px 32px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            color: #1a202c;
        }

        .back-btn {
            padding: 10px 20px;
            background: #e2e8f0;
            color: #2d3748;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #cbd5e0;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 24px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a202c;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
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

        .card-body {
            padding: 32px;
        }

        .section {
            margin-bottom: 32px;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 15px;
            color: #1a202c;
            font-weight: 500;
        }

        .info-value.empty {
            color: #a0aec0;
            font-style: italic;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .timeline-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .timeline-icon {
            width: 32px;
            height: 32px;
            background: #edf2f7;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
        }

        .timeline-value {
            font-size: 14px;
            color: #718096;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-info {
            background: #ebf8ff;
            color: #2c5282;
            border-left: 4px solid #4299e1;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0;
            }

            .header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>Trip Request Details</h1>
        <a href="<?php echo $_SESSION['role'] == 0 ? 'index.php' : '../admin/requests.php'; ?>" class="back-btn">← Back</a>
    </div>

    <!-- Request Info Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Request #<?php echo $request['id']; ?></div>
            <span class="status-badge status-<?php echo $request['status']; ?>">
                <?php echo ucfirst($request['status']); ?>
            </span>
        </div>

        <div class="card-body">
            <!-- Requester Information -->
            <div class="section">
                <div class="section-title">👤 Requester Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['requester_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Position/Title</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['requester_position']); ?></div>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Department/Office</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['department']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Trip Details -->
            <div class="section">
                <div class="section-title">🚗 Trip Details</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Destination</div>
                        <div class="info-value"><?php echo htmlspecialchars($request['destination']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Trip Date</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($request['trip_date'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Departure Time</div>
                        <div class="info-value"><?php echo date('g:i A', strtotime($request['departure_time'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Return Time</div>
                        <div class="info-value <?php echo empty($request['return_time']) ? 'empty' : ''; ?>">
                            <?php echo !empty($request['return_time']) ? date('g:i A', strtotime($request['return_time'])) : 'Not specified'; ?>
                        </div>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Purpose of Trip</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($request['purpose'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Passenger Information -->
            <div class="section">
                <div class="section-title">👥 Passenger Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Number of Passengers</div>
                        <div class="info-value"><?php echo $request['number_of_passengers']; ?> passenger(s)</div>
                    </div>
                    <div class="info-item full-width">
                        <div class="info-label">Passenger Names</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($request['passenger_names'])); ?></div>
                    </div>
                    <?php if (!empty($request['special_requirements'])): ?>
                    <div class="info-item full-width">
                        <div class="info-label">Special Requirements</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($request['special_requirements'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline -->
            <div class="section">
                <div class="section-title">📅 Timeline</div>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-icon">📝</div>
                        <div class="timeline-content">
                            <div class="timeline-label">Submitted</div>
                            <div class="timeline-value"><?php echo date('F j, Y \a\t g:i A', strtotime($request['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php if ($request['approved_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon"><?php echo $request['status'] == 'approved' ? '✅' : '❌'; ?></div>
                        <div class="timeline-content">
                            <div class="timeline-label"><?php echo ucfirst($request['status']); ?></div>
                            <div class="timeline-value"><?php echo date('F j, Y \a\t g:i A', strtotime($request['approved_at'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($request['admin_notes'])): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">💬</div>
                        <div class="timeline-content">
                            <div class="timeline-label">Admin Notes</div>
                            <div class="timeline-value"><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
