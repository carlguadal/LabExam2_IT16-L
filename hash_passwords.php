<?php
/**
 * SECURITY FIX: Hash all plaintext passwords in database
 * RUN THIS ONCE, THEN DELETE THIS FILE
 */

include("db.php");

// Get all users with plaintext passwords
$query = "SELECT id, username, password FROM users";
$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) > 0) {
    echo "Hashing passwords...<br>";
    
    while($user = mysqli_fetch_assoc($result)) {
        $user_id = $user['id'];
        $plain_password = $user['password'];
        $username = $user['username'];
        
        // Check if already hashed (bcrypt hashes start with $2)
        if(substr($plain_password, 0, 1) === '$') {
            echo "✓ User '$username' (ID: $user_id) - Already hashed<br>";
            continue;
        }
        
        // Hash the plaintext password
        $hashed = password_hash($plain_password, PASSWORD_DEFAULT);
        
        // Update database with hashed password
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            echo "✓ User '$username' (ID: $user_id) - Password hashed successfully<br>";
        } else {
            echo "✗ User '$username' (ID: $user_id) - Error: " . mysqli_error($conn) . "<br>";
        }
        mysqli_stmt_close($stmt);
    }
    
    echo "<br><strong>All passwords have been securely hashed!</strong><br>";
    echo "<p style='color: red;'>SECURITY: This file should be deleted after running once.</p>";
} else {
    echo "No users found.";
}

mysqli_close($conn);
?>
