<?php
// Test script to check database connectivity and table structure
require_once '../config/db.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test basic connection
    echo "✓ Database connection successful<br>";
    
    // Check if required tables exist
    $tables = ['quiz_attempts', 'user_answers', 'questions', 'question_options', 'quizzes', 'modules', 'courses', 'users'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✓ Table '$table' exists<br>";
        } else {
            echo "✗ Table '$table' missing<br>";
        }
    }
    
    // Test sample data
    echo "<br><h3>Sample Data Check:</h3>";
    
    // Check for quiz data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM quizzes");
    $result = $stmt->fetch();
    echo "Quizzes in database: " . $result['count'] . "<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM questions");
    $result = $stmt->fetch();
    echo "Questions in database: " . $result['count'] . "<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM question_options");
    $result = $stmt->fetch();
    echo "Question options in database: " . $result['count'] . "<br>";
    
    // Check for active quiz attempts
    if (isset($_SESSION['student_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quiz_attempts WHERE user_id = ? AND completed_at IS NULL");
        $stmt->execute([$_SESSION['student_id']]);
        $result = $stmt->fetch();
        echo "Active quiz attempts for current user: " . $result['count'] . "<br>";
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage();
}
?>
