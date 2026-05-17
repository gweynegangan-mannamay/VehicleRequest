<?php
session_start();
require_once('../config/connection.php');

// Declare $conn for static analysis (defined in connection.php)
/** @var mysqli $conn */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $role = 0; // Default role is User (0)
    
    // Validation
    if (empty($username) || empty($password) || empty($email)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if username already exists
        $check_query = "SELECT id FROM user_info WHERE username = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username already exists. Please choose another.";
        } else {
            // Check if email already exists
            $check_email_query = "SELECT id FROM user_info WHERE email = ?";
            $check_email_stmt = $conn->prepare($check_email_query);
            $check_email_stmt->bind_param("s", $email);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            
            if ($check_email_result->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $insert_query = "INSERT INTO user_info (username, password, email, role, created_at) VALUES (?, ?, ?, ?, NOW())";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("sssi", $username, $hashed_password, $email, $role);
                
                if ($insert_stmt->execute()) {
                    // Show success message and redirect to login
                    $success = "Account created successfully! Redirecting to login...";
                    header("refresh:2;url=login.php");
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .signup-card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            font-size: 32px;
            color: #333;
        }

        p {
            color: #666;
            margin-bottom: 2rem;
        }

        input {
            width: 100%;
            padding: 14px;
            margin: 12px 0;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: #f8f9fa;
            font-size: 15px;
        }

        input:focus {
            border-color: #1e7e34;
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(30, 126, 52, 0.1);
            transform: translateY(-2px);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e7e34 0%, #f39c12 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(30, 126, 52, 0.4);
            margin-top: 15px;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(30, 126, 52, 0.6);
        }

        button:active {
            transform: translateY(-1px);
        }

        .error-msg {
            color: #d9534f;
            font-size: 0.9rem;
            margin-bottom: 15px;
            padding: 12px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            animation: shake 0.4s ease-in-out;
        }

        .success-msg {
            color: #5cb85c;
            font-size: 0.9rem;
            margin-bottom: 10px;
            padding: 12px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 6px;
            animation: slideDown 0.5s ease-out, pulse 1.5s ease-in-out infinite;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-link {
            margin-top: 1rem;
            color: #666;
            font-size: 0.9rem;
        }

        .login-link a {
            color: #1e7e34;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: #27ae60;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="signup-card">
    <h2>Create Account</h2>
    <p>Sign up to get started</p>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if(isset($success)): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required 
               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        
        <input type="email" name="email" placeholder="Email Address (for notifications)" required
               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
               title="You'll receive email notifications about your trip requests">
        
        <input type="password" name="password" placeholder="Password (min 6 characters)" required>
        
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        
        <button type="submit">Sign Up</button>
    </form>

    <div style="margin-top: 15px; padding: 12px; background: #e7f3ff; border-radius: 8px; font-size: 13px; color: #004085; text-align: left;">
        <strong>📧 Email Notifications:</strong><br>
        You'll receive email updates when your trip requests are approved and tickets are ready.
    </div>

    <div class="login-link">
        Already have an account? <a href="login.php">Log in here</a>
    </div>
</div>

</body>
</html>

