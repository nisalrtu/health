<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Get module ID from URL
$module_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$module_id) {
    header('Location: modules.php');
    exit();
}

// Set page title
$page_title = "Edit Module";

// Initialize variables
$success_message = '';
$error_message = '';

// Get module data
try {
    $stmt = $pdo->prepare("
        SELECT m.*, c.title as course_title, c.id as course_id 
        FROM modules m 
        JOIN courses c ON m.course_id = c.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$module_id]);
    $module = $stmt->fetch();
    
    if (!$module) {
        header('Location: modules.php');
        exit();
    }
    
    // Get all active courses for the dropdown
    $stmt = $pdo->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title ASC");
    $courses = $stmt->fetchAll();
    
    // Check if module has any student progress
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_progress WHERE module_id = ?");
    $stmt->execute([$module_id]);
    $has_student_progress = $stmt->fetch()['count'] > 0;
    
    // Get module statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT l.id) as lesson_count,
            COUNT(DISTINCT q.id) as quiz_count,
            COUNT(DISTINCT up.user_id) as student_count,
            AVG(CASE WHEN qa.passed = 1 THEN qa.score ELSE NULL END) as avg_quiz_score
        FROM modules m
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN user_progress up ON m.id = up.module_id
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.passed = 1
        WHERE m.id = ?
        GROUP BY m.id
    ");
    $stmt->execute([$module_id]);
    $module_stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $module = false;
    $courses = [];
    $has_student_progress = false;
    $module_stats = ['lesson_count' => 0, 'quiz_count' => 0, 'student_count' => 0, 'avg_quiz_score' => null];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $module) {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $course_id = intval($_POST['course_id']);
        $order_sequence = intval($_POST['order_sequence']);
        $pass_threshold = intval($_POST['pass_threshold']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($title)) {
            $error_message = "Module title is required.";
        } elseif (strlen($title) > 200) {
            $error_message = "Module title must be 200 characters or less.";
        } elseif ($course_id <= 0) {
            $error_message = "Please select a valid course.";
        } elseif ($order_sequence <= 0) {
            $error_message = "Order sequence must be a positive number.";
        } elseif ($pass_threshold < 0 || $pass_threshold > 100) {
            $error_message = "Pass threshold must be between 0 and 100.";
        } else {
            // Check if course exists and is active
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND is_active = 1");
            $stmt->execute([$course_id]);
            if (!$stmt->fetch()) {
                $error_message = "Selected course is not available.";
            } else {
                // Check if changing course is allowed (only if no student progress)
                if ($course_id != $module['course_id'] && $has_student_progress) {
                    $error_message = "Cannot change course: Students have already started this module.";
                } else {
                    // Check for duplicate order sequence in the same course (excluding current module)
                    $stmt = $pdo->prepare("
                        SELECT id FROM modules 
                        WHERE course_id = ? AND order_sequence = ? AND id != ? AND is_active = 1
                    ");
                    $stmt->execute([$course_id, $order_sequence, $module_id]);
                    if ($stmt->fetch()) {
                        $error_message = "Another module already exists with order sequence $order_sequence in this course.";
                    } else {
                        // Update the module
                        $stmt = $pdo->prepare("
                            UPDATE modules 
                            SET title = ?, description = ?, course_id = ?, order_sequence = ?, 
                                pass_threshold = ?, is_active = ?
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([$title, $description, $course_id, $order_sequence, $pass_threshold, $is_active, $module_id])) {
                            $success_message = "Module updated successfully!";
                            
                            // Refresh module data
                            $stmt = $pdo->prepare("
                                SELECT m.*, c.title as course_title, c.id as course_id 
                                FROM modules m 
                                JOIN courses c ON m.course_id = c.id 
                                WHERE m.id = ?
                            ");
                            $stmt->execute([$module_id]);
                            $module = $stmt->fetch();
                        } else {
                            $error_message = "Failed to update module. Please try again.";
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center text-purple-200 text-sm mb-2">
                    <a href="modules.php" class="hover:text-white transition duration-300">Modules</a>
                    <svg class="w-4 h-4 mx-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span>Edit Module</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Edit Module</h1>
                <p class="text-purple-100 text-lg">
                    <?php echo htmlspecialchars($module['title'] ?? 'Module'); ?>
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex space-x-3">
                <a href="lessons.php?module_id=<?php echo $module_id; ?>" class="bg-white/20 text-white px-4 py-2 rounded-lg hover:bg-white/30 transition duration-300">
                    Manage Lessons
                </a>
                <a href="quizzes.php?module_id=<?php echo $module_id; ?>" class="bg-white/20 text-white px-4 py-2 rounded-lg hover:bg-white/30 transition duration-300">
                    Manage Quizzes
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!$module): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">Module Not Found</h3>
        <p class="text-gray-600 mb-4">The requested module could not be found or you don't have permission to edit it.</p>
        <a href="modules.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition duration-300 inline-block">
            Back to Modules
        </a>
    </div>
<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Form -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Module Information</h2>
                <p class="text-gray-600 mt-1">Update the module details and settings below.</p>
            </div>
            
            <form method="POST" class="p-6 space-y-6">
                <!-- Module Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        Module Title <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="title"
                        name="title" 
                        value="<?php echo htmlspecialchars($module['title']); ?>"
                        required 
                        maxlength="200"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
                        placeholder="Enter module title..."
                    >
                    <p class="text-sm text-gray-500 mt-1">Maximum 200 characters</p>
                </div>
                
                <!-- Module Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Module Description
                    </label>
                    <textarea 
                        id="description"
                        name="description" 
                        rows="4"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
                        placeholder="Describe what students will learn in this module..."
                    ><?php echo htmlspecialchars($module['description'] ?? ''); ?></textarea>
                </div>
                
                <!-- Course Selection -->
                <div>
                    <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Course <span class="text-red-500">*</span>
                    </label>
                    <select 
                        id="course_id"
                        name="course_id" 
                        required
                        <?php echo $has_student_progress ? 'disabled title="Cannot change course - students have started this module"' : ''; ?>
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300 <?php echo $has_student_progress ? 'bg-gray-100 cursor-not-allowed' : ''; ?>"
                    >
                        <option value="">Select a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $course['id'] == $module['course_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($has_student_progress): ?>
                        <input type="hidden" name="course_id" value="<?php echo $module['course_id']; ?>">
                        <p class="text-sm text-amber-600 mt-1">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Course cannot be changed because students have already started this module.
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Order Sequence -->
                    <div>
                        <label for="order_sequence" class="block text-sm font-medium text-gray-700 mb-2">
                            Order Sequence <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="order_sequence"
                            name="order_sequence" 
                            value="<?php echo $module['order_sequence']; ?>"
                            required 
                            min="1"
                            max="999"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
                            placeholder="1"
                        >
                        <p class="text-sm text-gray-500 mt-1">Order within the course (1, 2, 3...)</p>
                    </div>
                    
                    <!-- Pass Threshold -->
                    <div>
                        <label for="pass_threshold" class="block text-sm font-medium text-gray-700 mb-2">
                            Pass Threshold (%)
                        </label>
                        <input 
                            type="number" 
                            id="pass_threshold"
                            name="pass_threshold" 
                            value="<?php echo $module['pass_threshold']; ?>"
                            min="0" 
                            max="100"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
                            placeholder="70"
                        >
                        <p class="text-sm text-gray-500 mt-1">Minimum score to pass module quizzes</p>
                    </div>
                </div>
                
                <!-- Active Status -->
                <div>
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            id="is_active"
                            name="is_active" 
                            value="1"
                            <?php echo $module['is_active'] ? 'checked' : ''; ?>
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                        >
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-700">
                            Module is active
                        </label>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Inactive modules are hidden from students</p>
                </div>
                
                <!-- Form Actions -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                    <button 
                        type="submit" 
                        class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold flex items-center justify-center"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Update Module
                    </button>
                    
                    <a 
                        href="modules.php" 
                        class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition duration-300 font-semibold text-center"
                    >
                        Cancel
                    </a>
                    
                    <a 
                        href="modules.php?course_id=<?php echo $module['course_id']; ?>" 
                        class="bg-blue-100 text-blue-700 px-6 py-3 rounded-lg hover:bg-blue-200 transition duration-300 font-semibold text-center"
                    >
                        View Course Modules
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Sidebar Info -->
    <div class="space-y-6">
        <!-- Module Stats -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Module Statistics</h3>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Lessons</span>
                    <span class="font-semibold text-gray-900"><?php echo $module_stats['lesson_count']; ?></span>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Quizzes</span>
                    <span class="font-semibold text-gray-900"><?php echo $module_stats['quiz_count']; ?></span>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Students</span>
                    <span class="font-semibold text-gray-900"><?php echo $module_stats['student_count']; ?></span>
                </div>
                
                <?php if ($module_stats['avg_quiz_score']): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Avg Quiz Score</span>
                        <span class="font-semibold text-green-600"><?php echo round($module_stats['avg_quiz_score'], 1); ?>%</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Course Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Course Information</h3>
            
            <div class="space-y-3">
                <div>
                    <span class="text-sm text-gray-600">Current Course</span>
                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($module['course_title']); ?></p>
                </div>
                
                <div>
                    <span class="text-sm text-gray-600">Module Order</span>
                    <p class="font-semibold text-gray-900">#<?php echo $module['order_sequence']; ?></p>
                </div>
                
                <div>
                    <span class="text-sm text-gray-600">Pass Threshold</span>
                    <p class="font-semibold text-gray-900"><?php echo $module['pass_threshold']; ?>%</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
            
            <div class="space-y-3">
                <a href="lessons.php?module_id=<?php echo $module_id; ?>" 
                   class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300 group">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-purple-200 transition duration-300">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Manage Lessons</div>
                        <div class="text-sm text-gray-600"><?php echo $module_stats['lesson_count']; ?> lessons</div>
                    </div>
                </a>
                
                <a href="quizzes.php?module_id=<?php echo $module_id; ?>" 
                   class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300 group">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-200 transition duration-300">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Manage Quizzes</div>
                        <div class="text-sm text-gray-600"><?php echo $module_stats['quiz_count']; ?> quizzes</div>
                    </div>
                </a>
                
                <a href="courses.php" 
                   class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300 group">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3 group-hover:bg-blue-200 transition duration-300">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">All Courses</div>
                        <div class="text-sm text-gray-600">Manage courses</div>
                    </div>
                </a>
            </div>
        </div>
        
        <?php if ($has_student_progress): ?>
        <!-- Student Progress Warning -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-amber-800">
                        Active Module
                    </h3>
                    <div class="mt-2 text-sm text-amber-700">
                        <p>
                            This module has student progress and some features are restricted to maintain data integrity.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.querySelector('form');
    const titleInput = document.getElementById('title');
    const courseSelect = document.getElementById('course_id');
    const orderInput = document.getElementById('order_sequence');
    const thresholdInput = document.getElementById('pass_threshold');
    
    // Real-time validation
    titleInput.addEventListener('input', function() {
        validateTitle();
    });
    
    orderInput.addEventListener('input', function() {
        validateOrderSequence();
    });
    
    thresholdInput.addEventListener('input', function() {
        validateThreshold();
    });
    
    // Form submit validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        if (!validateTitle()) isValid = false;
        if (!validateCourse()) isValid = false;
        if (!validateOrderSequence()) isValid = false;
        if (!validateThreshold()) isValid = false;
        
        if (!isValid) {
            e.preventDefault();
            showError('Please fix the validation errors before submitting.');
        }
    });
    
    function validateTitle() {
        const value = titleInput.value.trim();
        const errorElement = getOrCreateErrorElement(titleInput);
        
        if (value.length === 0) {
            showFieldError(titleInput, errorElement, 'Module title is required.');
            return false;
        } else if (value.length > 200) {
            showFieldError(titleInput, errorElement, 'Module title must be 200 characters or less.');
            return false;
        } else {
            hideFieldError(titleInput, errorElement);
            return true;
        }
    }
    
    function validateCourse() {
        const value = courseSelect.value;
        const errorElement = getOrCreateErrorElement(courseSelect);
        
        if (!value || value === '') {
            showFieldError(courseSelect, errorElement, 'Please select a course.');
            return false;
        } else {
            hideFieldError(courseSelect, errorElement);
            return true;
        }
    }
    
    function validateOrderSequence() {
        const value = parseInt(orderInput.value);
        const errorElement = getOrCreateErrorElement(orderInput);
        
        if (isNaN(value) || value < 1) {
            showFieldError(orderInput, errorElement, 'Order sequence must be a positive number.');
            return false;
        } else if (value > 999) {
            showFieldError(orderInput, errorElement, 'Order sequence cannot exceed 999.');
            return false;
        } else {
            hideFieldError(orderInput, errorElement);
            return true;
        }
    }
    
    function validateThreshold() {
        const value = parseInt(thresholdInput.value);
        const errorElement = getOrCreateErrorElement(thresholdInput);
        
        if (isNaN(value) || value < 0 || value > 100) {
            showFieldError(thresholdInput, errorElement, 'Pass threshold must be between 0 and 100.');
            return false;
        } else {
            hideFieldError(thresholdInput, errorElement);
            return true;
        }
    }
    
    function getOrCreateErrorElement(input) {
        let errorElement = input.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('p');
            errorElement.className = 'field-error text-sm text-red-600 mt-1 hidden';
            input.parentNode.appendChild(errorElement);
        }
        return errorElement;
    }
    
    function showFieldError(input, errorElement, message) {
        input.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
        input.classList.remove('border-gray-300', 'focus:ring-indigo-500', 'focus:border-indigo-500');
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    }
    
    function hideFieldError(input, errorElement) {
        input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
        input.classList.add('border-gray-300', 'focus:ring-indigo-500', 'focus:border-indigo-500');
        errorElement.classList.add('hidden');
    }
    
    function showError(message) {
        // Create or update error alert
        let alertElement = document.querySelector('.form-error-alert');
        if (!alertElement) {
            alertElement = document.createElement('div');
            alertElement.className = 'form-error-alert mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg';
            alertElement.innerHTML = `
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span class="error-message"></span>
                </div>
            `;
            form.insertBefore(alertElement, form.firstChild);
        }
        
        alertElement.querySelector('.error-message').textContent = message;
        alertElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Character counter for title
    const titleCounter = document.createElement('div');
    titleCounter.className = 'text-sm text-gray-400 mt-1';
    titleCounter.textContent = `${titleInput.value.length}/200 characters`;
    titleInput.parentNode.insertBefore(titleCounter, titleInput.nextSibling.nextSibling);
    
    titleInput.addEventListener('input', function() {
        const length = this.value.length;
        titleCounter.textContent = `${length}/200 characters`;
        titleCounter.className = length > 200 ? 'text-sm text-red-500 mt-1' : 'text-sm text-gray-400 mt-1';
    });
    
    // Auto-save draft functionality (optional)
    let saveTimeout;
    const draftKey = 'module_edit_draft_<?php echo $module_id; ?>';
    
    function saveDraft() {
        const formData = {
            title: titleInput.value,
            description: document.getElementById('description').value,
            course_id: courseSelect.value,
            order_sequence: orderInput.value,
            pass_threshold: thresholdInput.value,
            is_active: document.getElementById('is_active').checked
        };
        
        localStorage.setItem(draftKey, JSON.stringify(formData));
    }
    
    function loadDraft() {
        const draft = localStorage.getItem(draftKey);
        if (draft) {
            try {
                const data = JSON.parse(draft);
                // Only load draft if form is empty or matches current values
                if (titleInput.value === data.title) {
                    document.getElementById('description').value = data.description || '';
                    if (!courseSelect.disabled) {
                        courseSelect.value = data.course_id || '';
                    }
                    orderInput.value = data.order_sequence || '';
                    thresholdInput.value = data.pass_threshold || '';
                    document.getElementById('is_active').checked = data.is_active || false;
                }
            } catch (e) {
                console.log('Failed to load draft:', e);
            }
        }
    }
    
    // Save draft on input changes
    [titleInput, document.getElementById('description'), courseSelect, orderInput, thresholdInput, document.getElementById('is_active')].forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveDraft, 1000); // Save after 1 second of inactivity
        });
        
        input.addEventListener('change', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveDraft, 1000);
        });
    });
    
    // Clear draft on successful submission
    form.addEventListener('submit', function() {
        localStorage.removeItem(draftKey);
    });
    
    // Load draft on page load
    // loadDraft(); // Uncomment if you want auto-load draft functionality
    
    // Confirmation dialog for navigation away with unsaved changes
    let hasUnsavedChanges = false;
    const originalValues = {
        title: titleInput.value,
        description: document.getElementById('description').value,
        course_id: courseSelect.value,
        order_sequence: orderInput.value,
        pass_threshold: thresholdInput.value,
        is_active: document.getElementById('is_active').checked
    };
    
    function checkForChanges() {
        const currentValues = {
            title: titleInput.value,
            description: document.getElementById('description').value,
            course_id: courseSelect.value,
            order_sequence: orderInput.value,
            pass_threshold: thresholdInput.value,
            is_active: document.getElementById('is_active').checked
        };
        
        hasUnsavedChanges = JSON.stringify(originalValues) !== JSON.stringify(currentValues);
    }
    
    [titleInput, document.getElementById('description'), courseSelect, orderInput, thresholdInput, document.getElementById('is_active')].forEach(input => {
        input.addEventListener('input', checkForChanges);
        input.addEventListener('change', checkForChanges);
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Reset unsaved changes flag on form submission
    form.addEventListener('submit', function() {
        hasUnsavedChanges = false;
    });
});
</script>

</body>
</html>