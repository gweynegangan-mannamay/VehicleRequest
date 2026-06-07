<?php
// Root entry point - redirect based on session role
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

if ($_SESSION['role'] == 1) {
    header("Location: admin/index.php");
} else {
    header("Location: user/index.php");
}
exit();
