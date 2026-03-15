<?php
/**
 * Security Functions Module
 * Provides enhanced security features for the application
 */

// ===== PASSWORD STRENGTH VALIDATION =====

/**
 * Validate password strength requirements
 * Requirements:
 * - Minimum 8 characters
 * - At least 1 uppercase letter (A-Z)
 * - At least 1 lowercase letter (a-z)
 * - At least 1 number (0-9)
 * - At least 1 special character (!@#$%^&*)
 */
function validate_password_strength($password) {
    $errors = [];
    
    if(strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if(!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter (A-Z)";
    }
    
    if(!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter (a-z)";
    }
    
    if(!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number (0-9)";
    }
    
    if(!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*)";
    }
    
    return $errors; // Returns empty array if password is valid
}

/**
 * Get password strength score (0-100)
 */
function get_password_strength($password) {
    $score = 0;
    
    // Length score
    if(strlen($password) >= 8) $score += 20;
    if(strlen($password) >= 12) $score += 10;
    if(strlen($password) >= 16) $score += 10;
    
    // Character variety
    if(preg_match('/[A-Z]/', $password)) $score += 20;
    if(preg_match('/[a-z]/', $password)) $score += 20;
    if(preg_match('/[0-9]/', $password)) $score += 10;
    if(preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) $score += 10;
    
    return min($score, 100);
}

/**
 * Get password strength label
 */
function get_password_strength_label($score) {
    if($score < 40) return 'Weak';
    if($score < 65) return 'Fair';
    if($score < 85) return 'Good';
    return 'Strong';
}

// ===== LOGIN SECURITY =====

/**
 * Track login attempts to prevent brute force
 * Store in session for this connection
 */
function record_login_attempt($username) {
    if(!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if(!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = [];
    }
    
    $_SESSION['login_attempts'][$username][] = time();
}

/**
 * Check if account is locked due to too many failed attempts
 */
function is_account_locked($username, $max_attempts = 5, $lockout_time = 900) {
    if(!isset($_SESSION['login_attempts'][$username])) {
        return false;
    }
    
    $attempts = $_SESSION['login_attempts'][$username];
    $recent_attempts = array_filter($attempts, function($time) use ($lockout_time) {
        return (time() - $time) < $lockout_time;
    });
    
    return count($recent_attempts) >= $max_attempts;
}

/**
 * Get remaining lockout time in seconds
 */
function get_lockout_remaining_time($username, $lockout_time = 900) {
    if(!isset($_SESSION['login_attempts'][$username])) {
        return 0;
    }
    
    $attempts = $_SESSION['login_attempts'][$username];
    if(empty($attempts)) return 0;
    
    $oldest_attempt = min($attempts);
    $remaining = $lockout_time - (time() - $oldest_attempt);
    
    return max(0, $remaining);
}

// ===== INPUT VALIDATION =====

/**
 * Validate email format and length
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100;
}

/**
 * Validate username format and length
 */
function is_valid_username($username) {
    return strlen($username) >= 3 && 
           strlen($username) <= 100 && 
           preg_match('/^[a-zA-Z0-9_.-]+$/', $username);
}

/**
 * Sanitize user input
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ===== SESSION SECURITY =====

/**
 * Regenerate session ID for security
 */
function secure_session_regenerate() {
    session_regenerate_id(true);
    // Update CSRF token as well
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validate session timeout (default 30 minutes)
 */
function check_session_timeout($timeout_minutes = 30) {
    if(isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if($inactive > ($timeout_minutes * 60)) {
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// ===== ACTIVITY LOGGING =====

/**
 * Log security events to file
 */
function log_security_event($event_type, $username = null, $details = null) {
    $log_file = __DIR__ . '/logs/security.log';
    
    // Create logs directory if it doesn't exist
    if(!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = get_client_ip();
    $log_entry = "[$timestamp] Event: $event_type | Username: " . ($username ?? 'N/A') . 
                 " | IP: $ip_address" . ($details ? " | Details: $details" : '') . "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Get client IP address (handles proxies)
 */
function get_client_ip() {
    if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
}

// ===== PASSWORD SECURITY =====

/**
 * Generate secure random password
 */
function generate_secure_password($length = 16) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()_+-=[]{}:;<>?,.';
    
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    $all_chars = $uppercase . $lowercase . $numbers . $special;
    for($i = 0; $i < $length - 4; $i++) {
        $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
    }
    
    return str_shuffle($password);
}

// ===== DATABASE SECURITY =====

/**
 * Safely escape database input (prepared statements preferred)
 */
function safe_db_input($conn, $input) {
    return mysqli_real_escape_string($conn, trim($input));
}

// ===== CSRF PROTECTION =====

/**
 * Generate CSRF token if not exists
 */
function ensure_csrf_token() {
    if(empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ===== RATE LIMITING =====

/**
 * Check if action is rate limited
 */
function is_rate_limited($action, $max_attempts = 10, $window_seconds = 60) {
    if(!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    if(!isset($_SESSION['rate_limit'][$action])) {
        $_SESSION['rate_limit'][$action] = [];
    }
    
    $now = time();
    $recent = array_filter($_SESSION['rate_limit'][$action], function($time) use ($window_seconds, $now) {
        return ($now - $time) < $window_seconds;
    });
    
    if(count($recent) >= $max_attempts) {
        return true;
    }
    
    $_SESSION['rate_limit'][$action][] = $now;
    return false;
}

?>
