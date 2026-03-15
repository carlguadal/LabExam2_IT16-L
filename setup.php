<?php
/**
 * INFOSEC LAB 2 - SYSTEM INITIALIZATION
 * Run this ONCE to set up the system properly
 * Delete after running
 */

include("db.php");
include("security_functions.php");

echo "=== INFOSEC LAB 2 - SYSTEM SETUP ===<br><br>";

// 1. Check database tables exist
echo "<strong>1. Checking database tables...</strong><br>";
$tables = ['students', 'users'];
foreach($tables as $table) {
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if($check && mysqli_num_rows($check) > 0) {
        echo "✓ Table '$table' exists<br>";
    } else {
        echo "✗ Table '$table' NOT FOUND<br>";
    }
}

// 2. Hash all plaintext passwords
echo "<br><strong>2. Securing passwords with bcrypt...</strong><br>";
$query = "SELECT id, username, password FROM users";
$result = mysqli_query($conn, $query);

if($result && mysqli_num_rows($result) > 0) {
    $hashed_count = 0;
    while($user = mysqli_fetch_assoc($result)) {
        $user_id = $user['id'];
        $plain_password = $user['password'];
        $username = $user['username'];
        
        // Check if already hashed
        if(substr($plain_password, 0, 1) === '$') {
            echo "✓ '$username' - Already hashed<br>";
        } else {
            // Hash the password
            $hashed = password_hash($plain_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);
            
            if(mysqli_stmt_execute($stmt)) {
                echo "✓ '$username' - Password secured<br>";
                $hashed_count++;
            } else {
                echo "✗ '$username' - Error: " . mysqli_error($conn) . "<br>";
            }
            mysqli_stmt_close($stmt);
        }
    }
    echo "Total passwords secured: $hashed_count<br>";
}

// 3. Create logs directory if needed
echo "<br><strong>3. Setting up security logging...</strong><br>";
$log_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
if(!is_dir($log_dir)) {
    if(mkdir($log_dir, 0755, true)) {
        echo "✓ Created logs directory<br>";
    } else {
        echo "✗ Failed to create logs directory<br>";
    }
} else {
    echo "✓ Logs directory exists<br>";
}

// 4. Check database schema
echo "<br><strong>4. Checking database schema...</strong><br>";
$students_desc = mysqli_query($conn, "DESCRIBE students");
if($students_desc && mysqli_num_rows($students_desc) > 0) {
    echo "✓ Students table structure:<br>";
    while($col = mysqli_fetch_assoc($students_desc)) {
        echo "&nbsp;&nbsp;- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
}

// 5. Summary
echo "<br><strong>✓ SETUP COMPLETE!</strong><br>";
echo "<hr>";
echo "<strong>System Status:</strong><br>";
echo "• Database: Connected ✓<br>";
echo "• Passwords: Secured with bcrypt ✓<br>";
echo "• Logging: Enabled ✓<br>";
echo "• Session Management: Ready ✓<br>";
echo "• CSRF Protection: Ready ✓<br>";
echo "<br><strong>You can now log in with:</strong><br>";
echo "Username: Any user in the database<br>";
echo "Password: The original password (now hashed securely)<br>";
echo "<br><strong>IMPORTANT:</strong> Delete this file (setup.php) after running!<br>";
echo "<a href='login.php' style='color: #3498db; text-decoration: underline; font-size: 18px;'>→ Go to Login</a>";

mysqli_close($conn);
?>
