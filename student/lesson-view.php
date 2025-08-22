<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Lesson";

$student_id = $_SESSION['student_id'];
$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$lesson_id || !$module_id || !$course_id) {
    header('Location: courses.php');
    exit();
}

$success_message = '';
$error_message = '';

try {
    // Get lesson, module, and course information
    $stmt = $pdo->prepare("
        SELECT l.*, m.title as module_title, m.order_sequence as module_order,
               c.title as course_title, c.description as course_description
        FROM lessons l
        JOIN modules m ON l.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE l.id = ? AND l.module_id = ? AND m.course_id = ? 
        AND m.is_active = 1 AND c.is_active = 1
    ");
    $stmt->execute([$lesson_id, $module_id, $course_id]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        header('Location: module-view.php?id=' . $module_id . '&course_id=' . $course_id);
        exit();
    }
    
    // Check if student has access to this lesson (sequential access)
    $stmt = $pdo->prepare("
        SELECT l.order_sequence,
               COUNT(CASE WHEN prev_l.order_sequence < l.order_sequence THEN 1 END) as previous_lessons,
               COUNT(CASE WHEN prev_l.order_sequence < l.order_sequence 
                          AND up_prev.status = 'completed' THEN 1 END) as completed_previous
        FROM lessons l
        LEFT JOIN lessons prev_l ON prev_l.module_id = l.module_id
        LEFT JOIN user_progress up_prev ON prev_l.id = up_prev.lesson_id AND up_prev.user_id = ?
        WHERE l.id = ?
        GROUP BY l.id, l.order_sequence
    ");
    $stmt->execute([$student_id, $lesson_id]);
    $access_check = $stmt->fetch();
    
    $has_access = ($access_check['previous_lessons'] == $access_check['completed_previous']);
    
    if (!$has_access) {
        header('Location: module-view.php?id=' . $module_id . '&course_id=' . $course_id);
        exit();
    }
    
    // Check current progress for this lesson
    $stmt = $pdo->prepare("
        SELECT * FROM user_progress 
        WHERE user_id = ? AND lesson_id = ?
    ");
    $stmt->execute([$student_id, $lesson_id]);
    $progress = $stmt->fetch();
    
    // If no progress record exists, create one with 'in_progress' status
    if (!$progress) {
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, course_id, module_id, lesson_id, status) 
            VALUES (?, ?, ?, ?, 'in_progress')
        ");
        $stmt->execute([$student_id, $course_id, $module_id, $lesson_id]);
        
        // Get the newly created progress record
        $stmt = $pdo->prepare("
            SELECT * FROM user_progress 
            WHERE user_id = ? AND lesson_id = ?
        ");
        $stmt->execute([$student_id, $lesson_id]);
        $progress = $stmt->fetch();
    } else {
        // If record exists but status is 'not_started', update to 'in_progress'
        if ($progress['status'] == 'not_started') {
            $stmt = $pdo->prepare("
                UPDATE user_progress 
                SET status = 'in_progress' 
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$student_id, $lesson_id]);
            $progress['status'] = 'in_progress';
        }
    }
    
    // Handle lesson completion
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_lesson'])) {
        try {
            // Mark lesson as completed
            $stmt = $pdo->prepare("
                UPDATE user_progress 
                SET status = 'completed', completed_at = NOW() 
                WHERE user_id = ? AND lesson_id = ?
            ");
            $stmt->execute([$student_id, $lesson_id]);
            
            $success_message = 'Lesson completed successfully! 🎉';
            $progress['status'] = 'completed';
            $progress['completed_at'] = date('Y-m-d H:i:s');
            
        } catch(PDOException $e) {
            $error_message = 'Error completing lesson. Please try again.';
        }
    }
    
    // Get next lesson in this module
    $stmt = $pdo->prepare("
        SELECT id, title FROM lessons 
        WHERE module_id = ? AND order_sequence > ? 
        ORDER BY order_sequence ASC 
        LIMIT 1
    ");
    $stmt->execute([$module_id, $lesson['order_sequence']]);
    $next_lesson = $stmt->fetch();
    
    // Get previous lesson in this module
    $stmt = $pdo->prepare("
        SELECT id, title FROM lessons 
        WHERE module_id = ? AND order_sequence < ? 
        ORDER BY order_sequence DESC 
        LIMIT 1
    ");
    $stmt->execute([$module_id, $lesson['order_sequence']]);
    $previous_lesson = $stmt->fetch();
    
    // Check if all lessons in this module are completed
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_lessons,
            COUNT(CASE WHEN up.status = 'completed' THEN 1 END) as completed_lessons
        FROM lessons l
        LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
        WHERE l.module_id = ?
    ");
    $stmt->execute([$student_id, $module_id]);
    $module_progress = $stmt->fetch();
    
    $all_lessons_completed = ($module_progress['total_lessons'] == $module_progress['completed_lessons']);
    
    // Get quizzes for this module (if all lessons are completed)
    $quizzes = [];
    if ($all_lessons_completed) {
        $stmt = $pdo->prepare("
            SELECT q.*, 
                   MAX(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as is_passed
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = ?
            WHERE q.module_id = ? AND q.is_active = 1
            GROUP BY q.id, q.title, q.quiz_type, q.pass_threshold
            ORDER BY q.quiz_type DESC, q.id ASC
        ");
        $stmt->execute([$student_id, $module_id]);
        $quizzes = $stmt->fetchAll();
    }
    
    // Get module progress for display
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_lessons,
            COUNT(CASE WHEN up.status = 'completed' THEN 1 END) as completed_lessons
        FROM lessons l
        LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
        WHERE l.module_id = ?
    ");
    $stmt->execute([$student_id, $module_id]);
    $lesson_stats = $stmt->fetch();
    
    $lesson_progress = $lesson_stats['total_lessons'] > 0 ? 
        round(($lesson_stats['completed_lessons'] / $lesson_stats['total_lessons']) * 100) : 0;
    
} catch(PDOException $e) {
    header('Location: module-view.php?id=' . $module_id . '&course_id=' . $course_id);
    exit();
}

// Include header
include '../includes/student-header.php';
?>

<!-- Lesson Header -->
<div class="mb-6 sm:mb-8">
    <div class="rounded-xl p-4 sm:p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-4">
                    <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                       class="text-white hover:text-yellow-300 transition duration-300 mr-3 sm:mr-4">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div class="flex flex-wrap items-center text-xs sm:text-sm">
                        <span class="text-white opacity-75"><?php echo htmlspecialchars($lesson['course_title']); ?></span>
                        <span class="text-white opacity-50 mx-1 sm:mx-2 hidden sm:inline">•</span>
                        <span class="text-white opacity-75 block sm:inline"><?php echo htmlspecialchars($lesson['module_title']); ?></span>
                        <span class="text-white opacity-50 mx-1 sm:mx-2 hidden sm:inline">•</span>
                        <span class="text-white opacity-75 block sm:inline">Lesson</span>
                    </div>
                </div>
                <h1 class="text-xl sm:text-2xl xl:text-3xl font-bold mb-3 text-white leading-tight">
                    <?php echo htmlspecialchars($lesson['title']); ?>
                </h1>
                <div class="flex flex-wrap gap-2 sm:gap-4 text-xs sm:text-sm">
                    <?php if ($lesson['estimated_duration']): ?>
                        <div class="bg-white bg-opacity-20 rounded-lg px-2 sm:px-3 py-1">
                            <span class="text-white"><?php echo $lesson['estimated_duration']; ?> min</span>
                        </div>
                    <?php endif; ?>
                    <div class="bg-white bg-opacity-20 rounded-lg px-2 sm:px-3 py-1">
                        <span class="text-white">Lesson <?php echo $lesson['order_sequence']; ?></span>
                    </div>
                    <?php if ($progress['status'] == 'completed'): ?>
                        <div class="bg-green-500 bg-opacity-80 rounded-lg px-2 sm:px-3 py-1">
                            <span class="text-white">✓ Completed</span>
                        </div>
                    <?php else: ?>
                        <div class="bg-yellow-500 bg-opacity-80 rounded-lg px-2 sm:px-3 py-1">
                            <span class="text-white">In Progress</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-6 xl:mt-0 xl:ml-8">
                <div class="bg-white bg-opacity-20 rounded-xl p-4 sm:p-6 text-center">
                    <div class="text-2xl sm:text-3xl font-bold text-white mb-2"><?php echo $lesson_progress; ?>%</div>
                    <div class="text-white opacity-90 text-xs sm:text-sm mb-3">Module Progress</div>
                    <div class="w-full bg-white bg-opacity-30 rounded-full h-2">
                        <div class="bg-yellow-400 h-2 rounded-full transition-all duration-500" 
                             style="width: <?php echo $lesson_progress; ?>%"></div>
                    </div>
                    <div class="text-white opacity-75 text-xs mt-2">
                        <?php echo $lesson_stats['completed_lessons']; ?> of <?php echo $lesson_stats['total_lessons']; ?> lessons
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($success_message): ?>
    <div class="mb-4 sm:mb-6">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 sm:px-6 py-4 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-3 flex-shrink-0 mt-0.5 sm:mt-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                </svg>
                <div class="font-semibold text-sm sm:text-base"><?php echo htmlspecialchars($success_message); ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="mb-4 sm:mb-6">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 sm:px-6 py-4 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 mr-3 flex-shrink-0 mt-0.5 sm:mt-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="font-semibold text-sm sm:text-base"><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content -->
<div class="grid grid-cols-1 xl:grid-cols-4 gap-6 lg:gap-8">
    <!-- Lesson Content -->
    <div class="xl:col-span-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Lesson Content Header -->
            <div class="p-4 sm:p-6 border-b border-gray-100">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 flex items-center">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 flex-shrink-0" style="color: #5fb3b4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Lesson Content
                </h2>
            </div>
            
            <!-- Lesson Content Body -->
            <div class="p-4 sm:p-6">
                <div class="prose max-w-none">
                    <!-- Display lesson content with proper formatting -->
                    <div class="text-gray-800 leading-relaxed space-y-4 text-sm sm:text-base">
                        <?php 
                        // Format lesson content - convert line breaks to paragraphs
                        $formatted_content = nl2br(htmlspecialchars($lesson['content']));
                        
                        // If content contains multiple paragraphs, wrap them properly
                        $paragraphs = explode("\n\n", $lesson['content']);
                        if (count($paragraphs) > 1) {
                            foreach ($paragraphs as $paragraph) {
                                if (trim($paragraph)) {
                                    echo '<p class="mb-4">' . nl2br(htmlspecialchars(trim($paragraph))) . '</p>';
                                }
                            }
                        } else {
                            echo '<div>' . $formatted_content . '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Lesson Actions -->
            <div class="p-4 sm:p-6 border-t border-gray-100 bg-gray-50">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- Navigation Buttons -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <?php if ($previous_lesson): ?>
                            <a href="lesson-view.php?id=<?php echo $previous_lesson['id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                               class="flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-300 text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                <span class="hidden sm:inline">Previous Lesson</span>
                                <span class="sm:hidden">Previous</span>
                            </a>
                        <?php else: ?>
                            <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                               class="flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-300 text-sm font-medium">
                                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                <span class="hidden sm:inline">Back to Module</span>
                                <span class="sm:hidden">Module</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Completion/Next Actions -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <?php if ($progress['status'] != 'completed'): ?>
                            <form method="POST" class="inline w-full sm:w-auto" id="complete-lesson-form">
                                <button type="button" id="complete-lesson-btn"
                                        class="w-full sm:w-auto flex items-center justify-center px-4 sm:px-6 py-3 text-white rounded-lg font-medium hover:bg-opacity-90 transition duration-300 text-sm"
                                        style="background-color: #5fb3b4;">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Complete Lesson
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($next_lesson && $progress['status'] == 'completed'): ?>
                            <a href="lesson-view.php?id=<?php echo $next_lesson['id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                               class="w-full sm:w-auto flex items-center justify-center px-4 sm:px-6 py-3 text-white rounded-lg font-medium hover:bg-opacity-90 transition duration-300 text-sm"
                               style="background-color: #5fb3b4;">
                                <span class="hidden sm:inline">Next Lesson</span>
                                <span class="sm:hidden">Next</span>
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        <?php elseif (!$next_lesson && $progress['status'] == 'completed' && $all_lessons_completed): ?>
                            <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>#quizzes" 
                               class="w-full sm:w-auto flex items-center justify-center px-4 sm:px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition duration-300 text-sm">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="hidden sm:inline">Take Module Quiz</span>
                                <span class="sm:hidden">Quiz</span>
                            </a>
                        <?php elseif (!$next_lesson && $progress['status'] == 'completed'): ?>
                            <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                               class="w-full sm:w-auto flex items-center justify-center px-4 sm:px-6 py-3 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700 transition duration-300 text-sm">
                                <span class="hidden sm:inline">Back to Module</span>
                                <span class="sm:hidden">Module</span>
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="xl:col-span-1">
        <div class="space-y-4 sm:space-y-6">
            <!-- Lesson Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Lesson Information</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 text-sm">Status:</span>
                        <?php if ($progress['status'] == 'completed'): ?>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                ✓ Completed
                            </span>
                        <?php else: ?>
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-medium">
                                In Progress
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($lesson['estimated_duration']): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 text-sm">Duration:</span>
                            <span class="text-gray-900 font-medium text-sm"><?php echo $lesson['estimated_duration']; ?> min</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600 text-sm">Order:</span>
                        <span class="text-gray-900 font-medium text-sm">Lesson <?php echo $lesson['order_sequence']; ?></span>
                    </div>
                    
                    <?php if ($progress['completed_at']): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600 text-sm">Completed:</span>
                            <span class="text-gray-900 font-medium text-sm">
                                <?php echo date('M j, Y', strtotime($progress['completed_at'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Module Progress -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Module Progress</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm mb-2">
                        <span class="text-gray-600">Lessons Completed</span>
                        <span class="font-medium" style="color: #5fb3b4;">
                            <?php echo $lesson_stats['completed_lessons']; ?>/<?php echo $lesson_stats['total_lessons']; ?>
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="h-3 rounded-full transition-all duration-500" 
                             style="width: <?php echo $lesson_progress; ?>%; background-color: #5fb3b4;"></div>
                    </div>
                    <div class="text-center text-sm font-medium" style="color: #5fb3b4;">
                        <?php echo $lesson_progress; ?>% Complete
                    </div>
                </div>
            </div>
            
            <!-- Next Steps -->
            <?php if ($all_lessons_completed && !empty($quizzes)): ?>
                <div class="bg-purple-50 rounded-xl border border-purple-200 p-4 sm:p-6">
                    <h3 class="text-base sm:text-lg font-semibold text-purple-900 mb-4 flex items-center">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Quizzes Available
                    </h3>
                    <p class="text-purple-700 text-sm mb-4">
                        Great! You've completed all lessons. Now you can take the module quizzes.
                    </p>
                    <div class="space-y-2">
                        <?php foreach ($quizzes as $quiz): ?>
                            <a href="quiz.php?id=<?php echo $quiz['id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                               class="block p-3 bg-white rounded-lg border border-purple-200 hover:border-purple-300 transition duration-300">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-purple-900 text-sm truncate"><?php echo htmlspecialchars($quiz['title']); ?></div>
                                        <div class="text-sm text-purple-600">Pass: <?php echo $quiz['pass_threshold']; ?>%</div>
                                    </div>
                                    <div class="ml-3 flex-shrink-0">
                                        <?php if ($quiz['is_passed']): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                                ✓ Passed
                                            </span>
                                        <?php else: ?>
                                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Navigation -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Quick Navigation</h3>
                <div class="space-y-3">
                    <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                       class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-gray-900 text-sm">Module Overview</div>
                            <div class="text-sm text-gray-600">All lessons & quizzes</div>
                        </div>
                    </a>
                    
                    <a href="course-view.php?id=<?php echo $course_id; ?>" 
                       class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-gray-900 text-sm">Course Overview</div>
                            <div class="text-sm text-gray-600">All modules</div>
                        </div>
                    </a>
                    
                    <a href="dashboard.php" 
                       class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v3H8V5z"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-gray-900 text-sm">Dashboard</div>
                            <div class="text-sm text-gray-600">Learning overview</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lesson Completion Confirmation Modal -->
<div id="completion-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
        <div class="p-4 sm:p-6">
            <!-- Modal Header -->
            <div class="flex items-center justify-center mb-6">
                <div class="w-12 h-12 sm:w-16 sm:h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
            
            <!-- Modal Content -->
            <div class="text-center mb-6">
                <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-3">Complete This Lesson?</h3>
                <p class="text-gray-600 leading-relaxed text-sm sm:text-base">
                    Are you sure you want to mark <strong>"<?php echo htmlspecialchars($lesson['title']); ?>"</strong> as completed? 
                    You can always come back to review it later.
                </p>
            </div>
            
            <!-- Modal Actions -->
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button" id="cancel-completion" 
                        class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition duration-300 text-sm">
                    Cancel
                </button>
                <button type="button" id="confirm-completion"
                        class="flex-1 px-4 py-3 text-white rounded-lg font-medium hover:bg-opacity-90 transition duration-300 text-sm"
                        style="background-color: #5fb3b4;">
                    <span class="completion-btn-text">Yes, Complete Lesson</span>
                    <span class="completion-btn-loading hidden">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 sm:h-5 sm:w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Completing...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar functionality (from header)
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarClose = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const profileDropdownBtn = document.getElementById('profile-dropdown-btn');
    const profileDropdown = document.getElementById('profile-dropdown');

    // Mobile sidebar toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.remove('sidebar-mobile');
            sidebar.classList.add('open');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }

    // Close sidebar
    function closeSidebar() {
        sidebar.classList.add('sidebar-mobile');
        sidebar.classList.remove('open');
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Profile dropdown toggle
    if (profileDropdownBtn && profileDropdown) {
        profileDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileDropdown.contains(e.target) && !profileDropdownBtn.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }

    // Progress bar animation
    const progressBars = document.querySelectorAll('[style*="width:"]');
    progressBars.forEach(bar => {
        if (bar.style.width && bar.style.width !== '0%') {
            const width = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.width = width;
            }, 800);
        }
    });

    // Content animations
    const mainContent = document.querySelector('.xl\\:col-span-3');
    const sidebarContent = document.querySelector('.xl\\:col-span-1');
    
    if (mainContent) {
        mainContent.style.opacity = '0';
        mainContent.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            mainContent.style.transition = 'all 0.6s ease';
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
        }, 200);
    }
    
    if (sidebarContent) {
        sidebarContent.style.opacity = '0';
        sidebarContent.style.transform = 'translateX(20px)';
        
        setTimeout(() => {
            sidebarContent.style.transition = 'all 0.6s ease';
            sidebarContent.style.opacity = '1';
            sidebarContent.style.transform = 'translateX(0)';
        }, 400);
    }

    // Header animation
    const headerSection = document.querySelector('[style*="background: linear-gradient"]');
    if (headerSection) {
        headerSection.style.opacity = '0';
        headerSection.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            headerSection.style.transition = 'all 0.8s ease';
            headerSection.style.opacity = '1';
            headerSection.style.transform = 'translateY(0)';
        }, 100);
    }

    // Auto-hide success/error messages
    const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'all 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // Confirm lesson completion
    const completeButton = document.getElementById('complete-lesson-btn');
    const completionModal = document.getElementById('completion-modal');
    const cancelCompletion = document.getElementById('cancel-completion');
    const confirmCompletion = document.getElementById('confirm-completion');
    const completeForm = document.getElementById('complete-lesson-form');
    
    if (completeButton && completionModal) {
        // Show modal when complete button is clicked
        completeButton.addEventListener('click', function(e) {
            e.preventDefault();
            completionModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Add entrance animation
            const modalContent = completionModal.querySelector('.bg-white');
            modalContent.style.transform = 'scale(0.9) translateY(20px)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modalContent.style.transition = 'all 0.3s ease';
                modalContent.style.transform = 'scale(1) translateY(0)';
                modalContent.style.opacity = '1';
            }, 10);
        });
        
        // Cancel completion
        cancelCompletion.addEventListener('click', function() {
            hideCompletionModal();
        });
        
        // Confirm completion
        confirmCompletion.addEventListener('click', function() {
            // Show loading state
            const btnText = this.querySelector('.completion-btn-text');
            const btnLoading = this.querySelector('.completion-btn-loading');
            
            btnText.classList.add('hidden');
            btnLoading.classList.remove('hidden');
            this.disabled = true;
            cancelCompletion.disabled = true;
            
            // Create hidden input for form submission
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'complete_lesson';
            hiddenInput.value = '1';
            completeForm.appendChild(hiddenInput);
            
            // Submit the form
            completeForm.submit();
        });
        
        // Close modal when clicking outside
        completionModal.addEventListener('click', function(e) {
            if (e.target === completionModal) {
                hideCompletionModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !completionModal.classList.contains('hidden')) {
                hideCompletionModal();
            }
        });
        
        function hideCompletionModal() {
            const modalContent = completionModal.querySelector('.bg-white');
            modalContent.style.transition = 'all 0.3s ease';
            modalContent.style.transform = 'scale(0.9) translateY(20px)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                completionModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
                
                // Reset button states
                const btnText = confirmCompletion.querySelector('.completion-btn-text');
                const btnLoading = confirmCompletion.querySelector('.completion-btn-loading');
                
                btnText.classList.remove('hidden');
                btnLoading.classList.add('hidden');
                confirmCompletion.disabled = false;
                cancelCompletion.disabled = false;
            }, 300);
        }
    }

    // Add loading states to navigation buttons
    const navigationButtons = document.querySelectorAll('a[href*="lesson-view"], a[href*="quiz.php"], a[href*="module-view"], a[href*="course-view"], a[href*="dashboard"]');
    navigationButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!this.href.includes('#')) {
                const originalHTML = this.innerHTML;
                this.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading...
                `;
                this.classList.add('opacity-75', 'cursor-not-allowed');
                this.style.pointerEvents = 'none';
            }
        });
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Reading progress indicator (optional)
    const lessonContent = document.querySelector('.prose');
    if (lessonContent) {
        let hasScrolled = false;
        let scrollTimer = null;
        
        window.addEventListener('scroll', function() {
            if (!hasScrolled) {
                hasScrolled = true;
                
                // Clear existing timer
                if (scrollTimer) {
                    clearTimeout(scrollTimer);
                }
                
                // Set timer to track reading progress
                scrollTimer = setTimeout(() => {
                    const scrollPosition = window.scrollY + window.innerHeight;
                    const documentHeight = document.documentElement.scrollHeight;
                    const scrollPercentage = (scrollPosition / documentHeight) * 100;
                    
                    // If user has scrolled through most of the content, consider it "read"
                    if (scrollPercentage > 80) {
                        console.log('Lesson content viewed');
                        // Could track this for analytics
                    }
                }, 1000);
            }
        });
    }

    // Add CSS for animations and mobile responsiveness
    const style = document.createElement('style');
    style.textContent = `
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .prose {
            line-height: 1.7;
        }
        
        .prose p {
            margin-bottom: 1rem;
        }
        
        .prose h1, .prose h2, .prose h3, .prose h4 {
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .prose ul, .prose ol {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }
        
        .prose li {
            margin-bottom: 0.25rem;
        }
        
        /* Mobile responsive improvements */
        @media (max-width: 640px) {
            .space-y-6 > * + * {
                margin-top: 1rem;
            }
            
            .space-y-4 > * + * {
                margin-top: 1rem;
            }
            
            /* Ensure buttons don't overflow on small screens */
            button, a[class*="bg-"], a[class*="border"] {
                word-wrap: break-word;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            /* Better modal sizing on mobile */
            #completion-modal .bg-white {
                margin: 1rem;
                max-height: calc(100vh - 2rem);
                overflow-y: auto;
            }
        }
        
        @media (max-width: 480px) {
            /* Extra small screens */
            .text-xl {
                font-size: 1.125rem;
            }
            
            .text-2xl {
                font-size: 1.25rem;
            }
            
            .px-6 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        
        /* Smooth transitions for all interactive elements */
        button, a {
            transition: all 0.3s ease;
        }
        
        /* Focus states for accessibility */
        button:focus, a:focus {
            outline: 2px solid #5fb3b4;
            outline-offset: 2px;
        }
        
        /* Touch-friendly hover states */
        @media (hover: hover) {
            .hover\\:bg-gray-50:hover {
                background-color: #f9fafb;
            }
            
            .hover\\:bg-gray-700:hover {
                background-color: #374151;
            }
            
            .hover\\:bg-purple-700:hover {
                background-color: #6d28d9;
            }
        }
        
        /* Ensure text doesn't overflow on small screens */
        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .min-w-0 {
            min-width: 0;
        }
        
        /* Better line height for mobile reading */
        @media (max-width: 640px) {
            .prose {
                line-height: 1.8;
            }
        }
    `;
    document.head.appendChild(style);
});
</script>
</body>
</html>