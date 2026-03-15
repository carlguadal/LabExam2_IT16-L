<?php
session_start();
include("db.php");
include("security_functions.php");

// Check session timeout
if(!check_session_timeout(30)) {
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
    log_security_event('UNAUTHORIZED_ACCESS_ADD_STUDENT', $_SESSION['user'], "Role: " . ($_SESSION['role'] ?? 'unknown'));
    header("Location: dashboard.php?error=Unauthorized: Only admins can add students");
    exit();
}

// Verify CSRF token
ensure_csrf_token();

// Fetch courses from database
$courses_query = "SELECT id, course_name, course_description FROM courses ORDER BY course_name ASC";
$courses_result = mysqli_query($conn, $courses_query);
$courses = [];
if($courses_result) {
    while($course_row = mysqli_fetch_assoc($courses_result)) {
        $courses[] = $course_row;
    }
}

if(isset($_POST['add'])){
    // Verify CSRF token
    if(!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        // Input validation and sanitization
        $student_id = trim($_POST['student_id'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $course_id = trim($_POST['course_id'] ?? '');
        $course_description = trim($_POST['course_description'] ?? '');
        
        // Get the course ID for storage (already have it from form)
        $course_id_value = intval($course_id);

        // Validate inputs
        $errors = [];
        
        if(empty($student_id) || strlen($student_id) > 50){
            $errors[] = "Student ID is required and must be less than 50 characters";
        }
        
        if(empty($fullname) || strlen($fullname) > 100){
            $errors[] = "Full Name is required and must be less than 100 characters";
        }
        
        if(!is_valid_email($email)){
            $errors[] = "Valid email is required";
        }
        
        if(empty($course_id)){
            $errors[] = "Course selection is required";
        }
        
        if(strlen($course_description) > 255){
            $errors[] = "Course description must be less than 255 characters";
        }

        if(empty($errors)){
            // Use prepared statement to prevent SQL injection
            $query = "INSERT INTO students (student_id, fullname, email, course_id) 
                      VALUES (?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssi", $student_id, $fullname, $email, $course_id_value);
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_close($stmt);
                log_security_event('STUDENT_ADDED', $_SESSION['user'], "Student ID: $student_id, Email: $email");
                header("Location: dashboard.php?success=Student added successfully");
                exit();
            } else {
                $errors[] = "Error adding student. Please try again.";
                log_security_event('STUDENT_ADD_ERROR', $_SESSION['user'], "Error: " . mysqli_error($conn));
            }
        }
        
        if(!empty($errors)){
            $error_message = implode("<br>", $errors);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - Student Management System</title>
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
            padding: 20px;
        }

        .navbar {
            background: #2c3e50;
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-size: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            font-size: 24px;
            color: #3498db;
        }

        .navbar-menu a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .navbar-menu a.back {
            background: #95a5a6;
        }

        .navbar-menu a.back:hover {
            background: #7f8c8d;
        }

        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .form-header {
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 20px;
        }

        .form-header i {
            font-size: 40px;
            color: #3498db;
            display: block;
            margin-bottom: 10px;
        }

        .form-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .form-header p {
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

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.1);
        }

        .form-group input::placeholder {
            color: #bdc3c7;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid #f5c6cb;
        }

        .error-message i {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .error-message ul {
            list-style: none;
            margin: 0;
        }

        .error-message li {
            margin-bottom: 5px;
        }

        .error-message li:last-child {
            margin-bottom: 0;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-cancel {
            background: #95a5a6;
            color: white;
        }

        .btn-cancel:hover {
            background: #7f8c8d;
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

        @media (max-width: 600px) {
            .form-container {
                padding: 20px;
            }

            .form-header h1 {
                font-size: 24px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-plus-circle"></i>
            Add New Student
        </div>
        <div class="navbar-menu">
            <a href="dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-header">
            <i class="fas fa-user-plus"></i>
            <h1>Add New Student</h1>
            <p>Fill in the details below to register a new student</p>
        </div>

        <?php if(!empty($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Error:</strong>
                    <?php 
                    if(strpos($error_message, '<br>') !== false) {
                        echo '<ul>';
                        foreach(explode('<br>', $error_message) as $err) {
                            if(!empty($err)) echo '<li>' . htmlspecialchars($err) . '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo htmlspecialchars($error_message);
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-group">
                <label><i class="fas fa-id-card"></i> Student ID <span class="required">*</span></label>
                <input type="text" name="student_id" 
                       value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>" 
                       placeholder="e.g., STU001" required maxlength="50">
            </div>

            <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                <input type="text" name="fullname" 
                       value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" 
                       placeholder="e.g., John Doe" required maxlength="100">
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email <span class="required">*</span></label>
                <input type="email" name="email" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       placeholder="e.g., john@example.com" required maxlength="100">
            </div>

            <div class="form-group">
                <label><i class="fas fa-book"></i> Course <span class="required">*</span></label>
                <select name="course_id" required>
                    <option value="" selected>-- Select a Course --</option>
                    <?php foreach($courses as $course_option): ?>
                        <option value="<?php echo htmlspecialchars($course_option['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course_option['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course_option['course_name'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($course_option['course_description'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-file-alt"></i> Course Description</label>
                <input type="text" name="course_description" 
                       value="<?php echo htmlspecialchars($_POST['course_description'] ?? ''); ?>" 
                       placeholder="Optional additional details" maxlength="255">
            </div>

            <div class="form-actions">
                <button type="submit" name="add" class="btn btn-submit">
                    <i class="fas fa-check-circle"></i> Add Student
                </button>
                <a href="dashboard.php" class="btn btn-cancel">
                    <i class="fas fa-times-circle"></i> Cancel
                </a>
            </div>
        </form>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>Help:</strong> All fields marked with <span style="color: #e74c3c;">*</span> are required. Student ID and Email must be unique.
        </div>
    </div>

</body>
</html>
