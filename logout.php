<?php
session_start();

// Log the logout action (optional - for audit purposes)
// You could add audit logging here

// Destroy all session data
session_destroy();

// Redirect to login page
header("Location: login.php?logout=success");
exit();
?>
