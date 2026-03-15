<?php
session_start();
include("db.php");
include("security_functions.php");

// Add security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Check session timeout
if(!check_session_timeout(30)) {
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

// Ensure CSRF token exists
ensure_csrf_token();

// Get student count
$count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
$count_row = mysqli_fetch_assoc($count_result);
$total_students = $count_row['total'];

// Get course count - handle both old and new schema
$courses_check = mysqli_query($conn, "SHOW TABLES LIKE 'courses'");
if(mysqli_num_rows($courses_check) > 0){
    $course_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM courses");
} else {
    // Fallback for old schema
    $course_result = mysqli_query($conn, "SELECT COUNT(DISTINCT course) as total FROM students");
}
$course_row = mysqli_fetch_assoc($course_result);
$total_courses = $course_row['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System - Dashboard</title>
    <link rel="stylesheet" href="style.css">
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand i {
            font-size: 28px;
            color: #3498db;
        }

        .navbar-menu a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 10px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .navbar-menu a:hover {
            background: #3498db;
            transform: translateY(-2px);
        }

        .navbar-menu a.logout {
            background: #e74c3c;
        }

        .navbar-menu a.logout:hover {
            background: #c0392b;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-badge.admin {
            background: #27ae60;
            color: white;
        }

        .role-badge.user {
            background: #3498db;
            color: white;
        }

        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .welcome-section h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-card i {
            font-size: 40px;
            color: #3498db;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #3498db;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 20px;
        }

        .table-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        .table-header h2 {
            color: #2c3e50;
            font-size: 24px;
        }

        .btn-add {
            background: #27ae60;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .table-responsive {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table-responsive thead {
            background: #34495e;
            color: white;
        }

        .table-responsive th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .table-responsive td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }

        .table-responsive tbody tr {
            transition: background 0.3s ease;
        }

        .table-responsive tbody tr:hover {
            background: #f8f9fa;
        }

        .table-responsive tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-delete:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 18px;
            margin-bottom: 20px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            color: white;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .navbar-menu {
                width: 100%;
                display: flex;
                justify-content: space-between;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                font-size: 12px;
            }

            .table-responsive th,
            .table-responsive td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-graduation-cap"></i>
            Student Management System
        </div>
        <div class="navbar-menu">
            <a href="#" title="Dashboard"><i class="fas fa-home"></i> Dashboard</a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="add_student.php" title="Add Student"><i class="fas fa-plus"></i> Add Student</a>
            <?php endif; ?>
            <span class="role-badge <?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') : 'user'; ?>">Role: <?php echo isset($_SESSION['role']) ? htmlspecialchars(ucfirst($_SESSION['role']), ENT_QUOTES, 'UTF-8') : 'User'; ?></span>
            <a href="logout.php" class="logout" title="Logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1><i class="fas fa-wave-hand"></i> Welcome, <?php echo htmlspecialchars($_SESSION['user'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
        <p>Manage students and courses efficiently with our modern system.</p>
    </div>

    <!-- Alerts -->
    <?php if(isset($_GET['success'])): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-users"></i>
            <h3>Total Students</h3>
            <div class="number"><?php echo $total_students; ?></div>
        </div>
        <div class="stat-card">
            <i class="fas fa-book"></i>
            <h3>Total Courses</h3>
            <div class="number"><?php echo $total_courses; ?></div>
        </div>
        <div class="stat-card">
            <i class="fas fa-calendar"></i>
            <h3>Current Year</h3>
            <div class="number"><?php echo date('Y'); ?></div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="table-section">
        <div class="table-header">
            <h2><i class="fas fa-list"></i> Students List</h2>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="add_student.php" class="btn-add">
                <i class="fas fa-plus-circle"></i> Add New Student
            </a>
            <?php endif; ?>
        </div>

        <?php
        // Query students with course information - join with courses table
        $query = "SELECT s.id, s.student_id, s.fullname, s.email, c.course_name, c.course_description 
                  FROM students s 
                  LEFT JOIN courses c ON s.course_id = c.id 
                  ORDER BY s.id DESC";
        
        $result = mysqli_query($conn, $query);
        $num_rows = mysqli_num_rows($result);

        if($num_rows > 0): ?>
            <table class="table-responsive">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['student_id'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php 
                                // Display course name from courses table
                                if(isset($row['course_name']) && !empty($row['course_name'])) {
                                    echo htmlspecialchars($row['course_name'], ENT_QUOTES, 'UTF-8');
                                } else {
                                    echo htmlspecialchars('Unassigned', ENT_QUOTES, 'UTF-8');
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge-date">-</span>
                            </td>
                            <td>
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="delete_student.php?id=<?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                   class="btn-delete"
                                   onclick="return confirm('Are you sure you want to delete this student?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <?php else: ?>
                                <span class="badge" style="background: #95a5a6; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px;">View Only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No students found. <a href="add_student.php" style="color: #3498db; text-decoration: underline;">Add your first student</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>&copy; 2026 Student Management System. All rights reserved. | Secured <i class="fas fa-lock"></i></p>
    </div>

</body>
</html>
