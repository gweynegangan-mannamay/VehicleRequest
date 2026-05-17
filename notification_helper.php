<?php
// Notification Helper Functions
// Creates in-app notifications and sends email notifications

// Load email configuration
require_once(__DIR__ . '/../config/mailer_config.php');

/**
 * Send email notification
 */
function sendEmail($to_email, $subject, $message, $from_name = null) {
    if ($from_name === null) {
        $from_name = EMAIL_FROM_NAME;
    }
    
    $from_email = EMAIL_FROM_ADDRESS;
    
    // Email testing mode - log instead of sending
    if (EMAIL_TEST_MODE) {
        $log_message = "\n" . str_repeat("=", 80) . "\n";
        $log_message .= "Email Log - " . date('Y-m-d H:i:s') . "\n";
        $log_message .= str_repeat("=", 80) . "\n";
        $log_message .= "To: $to_email\n";
        $log_message .= "Subject: $subject\n";
        $log_message .= "From: $from_name <$from_email>\n";
        $log_message .= str_repeat("-", 80) . "\n";
        $log_message .= strip_tags($message) . "\n";
        $log_message .= str_repeat("=", 80) . "\n\n";
        
        // Create logs directory if it doesn't exist
        $log_dir = dirname(EMAIL_LOG_FILE);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents(EMAIL_LOG_FILE, $log_message, FILE_APPEND);
        return true; // Simulate success
    }
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from_name <$from_email>" . "\r\n";
    $headers .= "Reply-To: " . EMAIL_REPLY_TO . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email
    return mail($to_email, $subject, $message, $headers);
}

/**
 * Create HTML email template
 */
function createEmailTemplate($title, $content, $footer_text = "") {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { background: white; padding: 30px; border: 1px solid #e0e0e0; }
            .content h2 { color: #1e7e34; margin-top: 0; }
            .info-box { background: #f8f9fa; padding: 15px; border-left: 4px solid #1e7e34; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🚗 VehiQuest - ISU Ilagan</h1>
                <p>Vehicle Request System</p>
            </div>
            <div class="content">
                <h2>' . $title . '</h2>
                ' . $content . '
            </div>
            <div class="footer">
                ' . ($footer_text ?: 'This is an automated message from VehiQuest. Please do not reply to this email.') . '
                <br><br>
                <strong>Isabela State University - Ilagan Campus</strong>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Create a notification in the database
 */
function createNotification($conn, $user_id, $type, $title, $message) {
    $insert_query = "INSERT INTO notifications (user_id, type, title, message, is_read, created_at) 
                     VALUES (?, ?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isss", $user_id, $type, $title, $message);
    return $stmt->execute();
}

/**
 * Notification: Request Submitted
 */
function notifyRequestSubmitted($conn, $user_id, $request_id) {
    $title = "Trip Request Submitted";
    $message = "Your trip request (#$request_id) has been successfully submitted and is pending admin approval.";
    return createNotification($conn, $user_id, 'submitted', $title, $message);
}

/**
 * Notification: Request Approved
 */
function notifyRequestApproved($conn, $user_id, $request_id, $admin_notes = '') {
    $title = "Trip Request Approved";
    $message = "Great news! Your trip request (#$request_id) has been APPROVED.";
    if (!empty($admin_notes)) {
        $message .= " Admin notes: " . $admin_notes;
    }
    return createNotification($conn, $user_id, 'approved', $title, $message);
}

/**
 * Notification: Request Rejected
 */
function notifyRequestRejected($conn, $user_id, $request_id, $admin_notes = '') {
    $title = "Trip Request Rejected";
    $message = "Your trip request (#$request_id) has been rejected.";
    if (!empty($admin_notes)) {
        $message .= " Reason: " . $admin_notes;
    }
    return createNotification($conn, $user_id, 'rejected', $title, $message);
}

/**
 * Notification: Ticket Processed (with Email)
 */
function notifyTicketProcessed($conn, $user_id, $trip_id, $destination, $trip_date, $driver_name, $vehicle_name) {
    // Create in-app notification
    $title = "Trip Ticket Processed";
    $message = "Your trip ticket (#$trip_id) has been processed and is ready! ";
    $message .= "Destination: $destination | Date: $trip_date | Driver: $driver_name | Vehicle: $vehicle_name";
    $notification_created = createNotification($conn, $user_id, 'general', $title, $message);
    
    // Get user email
    $user_query = "SELECT email, username FROM user_info WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && !empty($user['email'])) {
        // Create email content
        $email_content = '
            <p>Dear ' . htmlspecialchars($user['username']) . ',</p>
            <p>Great news! Your trip request has been <strong>approved</strong> and your trip ticket is now ready.</p>
            
            <div class="info-box">
                <strong>Trip Details:</strong><br>
                <strong>Ticket ID:</strong> #' . $trip_id . '<br>
                <strong>Destination:</strong> ' . htmlspecialchars($destination) . '<br>
                <strong>Trip Date:</strong> ' . htmlspecialchars($trip_date) . '<br>
                <strong>Driver:</strong> ' . htmlspecialchars($driver_name) . '<br>
                <strong>Vehicle:</strong> ' . htmlspecialchars($vehicle_name) . '
            </div>
            
            <p>Your trip ticket has been generated and is ready for your trip. Please coordinate with the assigned driver for the final arrangements.</p>
            
            <p><a href="' . BASE_URL . '/user/index.php" class="button">View My Requests</a></p>
            
            <p>If you have any questions or concerns, please contact the admin office.</p>
            
            <p>Safe travels!<br>
            <strong>VehiQuest Team</strong></p>
        ';
        
        $email_html = createEmailTemplate("Trip Ticket Ready - Approved!", $email_content);
        
        // Send email
        $email_subject = "Trip Ticket Ready - Request #$trip_id Approved";
        sendEmail($user['email'], $email_subject, $email_html);
    }
    
    return $notification_created;
}

/**
 * Notification: New Request for Admin
 */
function notifyAdminNewRequest($conn, $requester_name, $request_id, $destination) {
    // Get all admin users (role = 1)
    $admin_query = "SELECT id FROM user_info WHERE role = 1";
    $result = $conn->query($admin_query);
    
    $title = "New Trip Request";
    $message = "A new trip request (#$request_id) has been submitted by $requester_name to $destination.";
    
    $success = true;
    while ($admin = $result->fetch_assoc()) {
        $success = $success && createNotification($conn, $admin['id'], 'general', $title, $message);
    }
    
    return $success;
}
?>
