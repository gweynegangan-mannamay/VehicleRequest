<?php
session_start();
require_once('../config/connection.php');

// Declare $conn for static analysis (defined in connection.php)
/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = trim($_POST['username']);
    $password = $_POST['password'];

    // Check if user exists by username OR email
    $query = "SELECT * FROM user_info WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $username_or_email, $username_or_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Check if password is hashed or plain text
        if (password_verify($password, $user['password'])) {
            // Password is hashed and correct
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect based on role
            header("Location: " . ($user['role'] == 1 ? "../admin/index.php" : "../user/index.php"));
            exit();
        } elseif ($password === $user['password']) {
            // Plain text password match (insecure, but for debugging)
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect based on role
            header("Location: " . ($user['role'] == 1 ? "../admin/index.php" : "../user/index.php"));
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Username or email not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VehiQuest - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: url('../assets/images/isu-background.png') no-repeat center center;
            background-size: 100% 100%;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
        }

        /* Animated gradient overlay */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(30, 126, 52, 0.85) 0%, rgba(243, 156, 18, 0.85) 100%);
            z-index: 0;
            animation: gradientShift 10s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { opacity: 0.85; }
            50% { opacity: 0.7; }
        }

        /* Login Container */
        .login-container {
            position: relative;
            z-index: 1;
            display: flex;
            background: white;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
            overflow: hidden;
            max-width: 900px;
            width: 90%;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(50px) scale(0.95);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1);
            }
        }

        /* Left Side - Branding */
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-brand::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
            animation: float 6s ease-in-out infinite;
        }

        .login-brand::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .brand-logo {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }

        .brand-logo img {
            width: 90%;
            height: 90%;
            object-fit: contain;
        }

        .brand-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 12px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            position: relative;
            z-index: 1;
        }

        .brand-subtitle {
            font-size: 16px;
            opacity: 0.95;
            text-align: center;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        /* Right Side - Form */
        .login-form-section {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 40px;
        }

        .form-header h2 {
            font-size: 32px;
            color: #1a202c;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .form-header p {
            color: #718096;
            font-size: 15px;
        }

        .input-group {
            margin-bottom: 24px;
            position: relative;
        }

        .input-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #a0aec0;
        }

        input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f7fafc;
        }

        input:focus {
            outline: none;
            border-color: #1e7e34;
            background: white;
            box-shadow: 0 0 0 4px rgba(30, 126, 52, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(30, 126, 52, 0.3);
            margin-top: 8px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(30, 126, 52, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-msg {
            background: #fff5f5;
            border-left: 4px solid #f56565;
            color: #742a2a;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            animation: shake 0.4s ease-in-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .signup-link {
            text-align: center;
            margin-top: 24px;
            color: #718096;
            font-size: 14px;
        }

        .signup-link a {
            color: #1e7e34;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .signup-link a:hover {
            color: #27ae60;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 450px;
            }

            .login-brand {
                padding: 40px 30px;
            }

            .brand-logo {
                width: 80px;
                height: 80px;
            }

            .brand-title {
                font-size: 28px;
            }

            .login-form-section {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <!-- Left Side - Branding -->
    <div class="login-brand">
        <div class="brand-logo">
            <img src="../assets/images/isu-logo.png" alt="ISU Logo">
        </div>
        <h1 class="brand-title">VehiQuest</h1>
        <p class="brand-subtitle">Isabela State University<br>Vehicle Request Management System</p>
    </div>

    <!-- Right Side - Login Form -->
    <div class="login-form-section">
        <div class="form-header">
            <h2>Welcome Back!</h2>
            <p>Please login to continue</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="error-msg">
                <span>⚠️</span>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Username or Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input type="text" name="username" placeholder="Enter your username or email" required>
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="btn-login">Log in</button>
        </form>

        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up here</a>
        </div>
    </div>
</div>

</body>
</html>

