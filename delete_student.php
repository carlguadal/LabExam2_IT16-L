<?php
session_start();
include("db.php");
include("security_functions.php");

// Check session timeout
if(!check_session_timeout(30)) {
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

// Check if user is authenticated
if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

// Check if user has admin role
if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    log_security_event('UNAUTHORIZED_ACCESS_DELETE_STUDENT', $_SESSION['user'], "Role: " . ($_SESSION['role'] ?? 'unknown'));
    header("Location: dashboard.php?error=Unauthorized: Only admins can delete students");
    exit();
}

// Get and validate ID parameter
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id <= 0){
    log_security_event('DELETE_ATTEMPT_INVALID_ID', $_SESSION['user']);
    header("Location: dashboard.php?error=Invalid student ID");
    exit();
}

// Get student details before deletion (for audit/user confirmation)
$student_query = "SELECT student_id, fullname FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);
$student_row = mysqli_fetch_assoc($student_result);
mysqli_stmt_close($stmt);

if(!$student_row){
    log_security_event('DELETE_ATTEMPT_NOT_FOUND', $_SESSION['user'], "ID: $id");
    header("Location: dashboard.php?error=Student not found");
    exit();
}

// Use prepared statement to prevent SQL injection
$query = "DELETE FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);

if(mysqli_stmt_execute($stmt)){
    mysqli_stmt_close($stmt);
    log_security_event('STUDENT_DELETED', $_SESSION['user'], "Student ID: " . $student_row['student_id'] . ", Name: " . $student_row['fullname']);
    header("Location: dashboard.php?success=Student '" . urlencode($student_row['fullname']) . "' has been deleted successfully");
} else {
    log_security_event('DELETE_ERROR', $_SESSION['user'], "ID: $id, Error: " . mysqli_error($conn));
    header("Location: dashboard.php?error=Error deleting student: " . urlencode(mysqli_error($conn)));
}
exit();
?>
