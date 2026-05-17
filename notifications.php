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

// Mark notification as read if requested
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notif_id = $_GET['id'];
    $update_query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $notif_id, $_SESSION['user_id']);
    $stmt->execute();
    header("Location: notifications.php");
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    header("Location: notifications.php");
    exit();
}

// Fetch notifications
$notifications_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$notifications_result = $stmt->get_result();

// Count unread
$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $_SESSION['user_id']);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_data = $unread_result->fetch_assoc();
$unread_count = $unread_data['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
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
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: white;
            font-size: 28px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .back-btn {
            padding: 12px 25px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            border: 2px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .notif-header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .notif-header h2 {
            color: #1e7e34;
        }

        .mark-all-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .mark-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 126, 52, 0.4);
        }

        .notif-item {
            background: white;
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #1e7e34;
        }

        .notif-item:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .notif-item.unread {
            background: linear-gradient(to right, #f0f4ff, white);
            border-left-color: #ff4757;
        }

        .notif-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .notif-title {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .notif-time {
            font-size: 12px;
            color: #999;
        }

        .notif-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .notif-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-approved {
            background: #d4edda;
            color: #155724;
        }

        .type-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .type-submitted {
            background: #d1ecf1;
            color: #0c5460;
        }

        .type-reminder {
            background: #fff3cd;
            color: #856404;
        }

        .type-general {
            background: #e7f3ff;
            color: #004085;
        }

        .empty-state {
            background: white;
            padding: 60px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #1e7e34;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>🔔 Notifications</h1>
    <a href="<?php echo $_SESSION['role'] == 0 ? 'index.php' : '../admin/requests.php'; ?>" class="back-btn">← Back</a>
</div>

<div class="container">
    <div class="notif-header">
        <h2><?php echo $unread_count; ?> Unread Notification<?php echo $unread_count != 1 ? 's' : ''; ?></h2>
        <?php if ($unread_count > 0): ?>
            <a href="?mark_all_read=1" class="mark-all-btn">Mark All as Read</a>
        <?php endif; ?>
    </div>

    <?php if ($notifications_result->num_rows > 0): ?>
        <?php while ($notif = $notifications_result->fetch_assoc()): ?>
            <div class="notif-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                <div class="notif-header-row">
                    <div>
                        <span class="notif-type type-<?php echo str_replace('_', '-', $notif['type']); ?>">
                            <?php echo str_replace('_', ' ', $notif['type']); ?>
                        </span>
                    </div>
                    <span class="notif-time"><?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?></span>
                </div>
                <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                <?php if ($notif['is_read'] == 0): ?>
                    <a href="?mark_read=1&id=<?php echo $notif['id']; ?>" style="color: #1e7e34; font-size: 13px; text-decoration: none;">Mark as read</a>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <h3>No Notifications Yet</h3>
            <p>You'll see notifications here when there are updates on your trip requests.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
