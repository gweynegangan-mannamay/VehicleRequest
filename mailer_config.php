<?php
/**
 * Mail Configuration for VehiQuest
 * Single source of truth for all email settings.
 * Used by includes/notification_helper.php and includes/email_helper.php
 */

// ── Gmail SMTP (used by PHPMailer in email_helper.php) ───────
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'gweynegangan@gmail.com');
define('MAIL_PASSWORD',   'qlnp dzan lexq zmpj');
define('MAIL_FROM_EMAIL', 'gweynegangan@gmail.com');
define('MAIL_FROM_NAME',  'VehiQuest - ISU Ilagan');

// ── PHP mail() sender (used by notification_helper.php) ──────
define('EMAIL_FROM_ADDRESS', 'gweynegangan@gmail.com');
define('EMAIL_FROM_NAME',    'VehiQuest - ISU Ilagan');
define('EMAIL_REPLY_TO',     'gweynegangan@gmail.com');

// ── Base URL for links inside emails ─────────────────────────
define('BASE_URL', 'http://localhost/VehicleRequest');

// ── Test mode ─────────────────────────────────────────────────
// true  → emails are written to logs/email_log.txt (no actual sending)
// false → emails are sent via PHP mail()
define('EMAIL_TEST_MODE', false);
define('EMAIL_LOG_FILE',  __DIR__ . '/../logs/email_log.txt');
