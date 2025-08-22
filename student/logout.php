<?php
session_start();

// Store student name for goodbye message if needed
$student_name = $_SESSION['student_name'] ?? 'Student';

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with logout message
header('Location: ../student-login.php?logout=success');
exit();
?>
