<?php
session_start();

// Add security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

include("db.php");
include("security_functions.php");

// Check session timeout
if(!check_session_timeout(30)) {
    header("Location: login.php?timeout=1");
    exit();
}

// Generate CSRF token for forms
ensure_csrf_token();

$error = null;
$lockout_message = null;

if(isset($_POST['login'])){
    // Rate limiting check
    if(is_rate_limited('login_attempt', 10, 60)) {
        $error = "Too many login attempts. Please try again in a few moments.";
        log_security_event('RATE_LIMIT_EXCEEDED');
    } else {
        // Validate inputs
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        $errors = [];
        
        // Check for empty fields
        if(empty($username)) {
            $errors[] = "Username is required";
        }
        
        if(empty($password)) {
            $errors[] = "Password is required";
        }
        
        // Check if account is locked
        if(is_account_locked($username)) {
            $lockout_message = "Account temporarily locked due to too many failed login attempts. Please try again in " . ceil(get_lockout_remaining_time($username) / 60) . " minutes.";
            log_security_event('ACCOUNT_LOCKED', $username);
            $error = $lockout_message;
        } elseif(empty($errors)) {
            // Query database for user
            $query = "SELECT * FROM users WHERE username=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if(mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                
                // Verify password (supports both hashed and plaintext during migration)
                $password_valid = false;
                
                // Try bcrypt verification first (for hashed passwords)
                if(substr($user['password'], 0, 1) === '$') {
                    $password_valid = password_verify($password, $user['password']);
                } else {
                    // Plaintext password (migration mode) - TEMPORARY
                    $password_valid = ($password === $user['password']);
                    // Log warning for plaintext password
                    if($password_valid) {
                        log_security_event('WARNING_PLAINTEXT_PASSWORD', $username);
                    }
                }
                
                if($password_valid) {
                    // Successful login
                    secure_session_regenerate();
                    $_SESSION['user'] = $username;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role']; // Store user role for access control
                    
                    // Clear login attempts on successful login
                    unset($_SESSION['login_attempts'][$username]);
                    
                    log_security_event('SUCCESSFUL_LOGIN', $username);
                    
                    mysqli_stmt_close($stmt);
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // Failed password
                    record_login_attempt($username);
                    $error = "Invalid credentials";
                    log_security_event('FAILED_LOGIN', $username, 'Invalid password');
                }
            } else {
                // User not found
                record_login_attempt($username);
                $error = "Invalid credentials";
                log_security_event('FAILED_LOGIN', $username, 'User not found');
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Please fill in all required fields";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 50px 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header i {
            font-size: 50px;
            color: #667eea;
            margin-bottom: 15px;
            display: block;
        }

        .login-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #bdc3c7;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #f5c6cb;
        }

        .error i {
            font-size: 18px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 25px;
            color: #7f8c8d;
            font-size: 12px;
        }

        .login-footer i {
            color: #27ae60;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin-top: 20px;
            border-radius: 4px;
            font-size: 12px;
            color: #1565c0;
        }

        .info-box i {
            margin-right: 8px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .login-header i {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-graduation-cap"></i>
            <h1>Admin Login</h1>
            <p>Student Management System</p>
        </div>

        <?php if(isset($_GET['logout'])): ?>
            <div class="success-message" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border: 1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i>
                You have been logged out successfully. Please login again.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['timeout'])): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                Your session has expired. Please login again.
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="info-box">
            <i class="fas fa-shield-alt"></i>
            <strong>Secure Login:</strong> Your credentials are protected with industry-standard encryption.
        </div>

        <div class="login-footer">
            <p><i class="fas fa-lock"></i> Secured & Encrypted Connection</p>
        </div>
    </div>

</body>
</html>
