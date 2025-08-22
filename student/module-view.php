<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Module Details";

$student_id = $_SESSION['student_id'];
$module_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$module_id || !$course_id) {
    header('Location: courses.php');
    exit();
}

try {
    // Get module and course information
    $stmt = $pdo->prepare("
        SELECT m.*, c.title as course_title, c.description as course_description
        FROM modules m
        JOIN courses c ON m.course_id = c.id
        WHERE m.id = ? AND m.course_id = ? AND m.is_active = 1 AND c.is_active = 1
    ");
    $stmt->execute([$module_id, $course_id]);
    $module = $stmt->fetch();
    
    if (!$module) {
        header('Location: course-view.php?id=' . $course_id);
        exit();
    }
    
    // Check if student has access to this module (sequential access)
    $stmt = $pdo->prepare("
        SELECT m.order_sequence,
               COUNT(CASE WHEN prev_m.order_sequence < m.order_sequence THEN 1 END) as previous_modules,
               COUNT(CASE WHEN prev_m.order_sequence < m.order_sequence 
                          AND prev_complete.module_completed = 1 THEN 1 END) as completed_previous
        FROM modules m
        LEFT JOIN modules prev_m ON prev_m.course_id = m.course_id AND prev_m.is_active = 1
        LEFT JOIN (
            SELECT m2.id, 
                   CASE WHEN (
                       COUNT(DISTINCT l.id) = COUNT(DISTINCT CASE WHEN up_l.status = 'completed' THEN up_l.lesson_id END)
                       AND COUNT(DISTINCT q.id) = COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.quiz_id END)
                   ) THEN 1 ELSE 0 END as module_completed
            FROM modules m2
            LEFT JOIN lessons l ON m2.id = l.module_id
            LEFT JOIN quizzes q ON m2.id = q.module_id AND q.is_active = 1
            LEFT JOIN user_progress up_l ON l.id = up_l.lesson_id AND up_l.user_id = ?
            LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = ? AND qa.passed = 1
            WHERE m2.course_id = ? AND m2.is_active = 1
            GROUP BY m2.id
        ) prev_complete ON prev_m.id = prev_complete.id
        WHERE m.id = ?
        GROUP BY m.id, m.order_sequence
    ");
    $stmt->execute([$student_id, $student_id, $course_id, $module_id]);
    $access_check = $stmt->fetch();
    
    $has_access = ($access_check['previous_modules'] == $access_check['completed_previous']);
    
    if (!$has_access) {
        header('Location: course-view.php?id=' . $course_id);
        exit();
    }
    
    // Get all lessons for this module in order
    $stmt = $pdo->prepare("
        SELECT l.*, 
               CASE WHEN up.status = 'completed' THEN 1 ELSE 0 END as is_completed,
               up.completed_at
        FROM lessons l
        LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
        WHERE l.module_id = ?
        ORDER BY l.order_sequence ASC
    ");
    $stmt->execute([$student_id, $module_id]);
    $lessons = $stmt->fetchAll();
    
    // Get all quizzes for this module
    $stmt = $pdo->prepare("
        SELECT q.*, 
               MAX(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as is_passed,
               COUNT(qa.id) as total_attempts,
               MAX(qa.score) as best_score,
               MAX(qa.completed_at) as last_attempt
        FROM quizzes q
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = ?
        WHERE q.module_id = ? AND q.is_active = 1
        GROUP BY q.id, q.title, q.quiz_type, q.pass_threshold, q.is_active
        ORDER BY q.quiz_type DESC, q.id ASC
    ");
    $stmt->execute([$student_id, $module_id]);
    $quizzes = $stmt->fetchAll();
    
    // Calculate module progress
    $total_lessons = count($lessons);
    $completed_lessons = array_sum(array_column($lessons, 'is_completed'));
    $total_quizzes = count($quizzes);
    $passed_quizzes = array_sum(array_column($quizzes, 'is_passed'));
    
    $lessons_progress = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 100;
    $quizzes_progress = $total_quizzes > 0 ? round(($passed_quizzes / $total_quizzes) * 100) : 100;
    $overall_progress = round((($completed_lessons + $passed_quizzes) / ($total_lessons + $total_quizzes)) * 100);
    
    // Check if module is completed
    $module_completed = ($completed_lessons == $total_lessons) && ($passed_quizzes == $total_quizzes);
    
    // Determine which lesson is currently available (sequential)
    $next_lesson_index = $completed_lessons; // Next available lesson index
    
    // Check if quizzes are unlocked (all lessons must be completed first)
    $quizzes_unlocked = ($completed_lessons == $total_lessons);
    
} catch(PDOException $e) {
    header('Location: course-view.php?id=' . $course_id);
    exit();
}

// Function to get lesson status
function getLessonStatus($lesson, $lesson_index, $next_available_index) {
    if ($lesson['is_completed']) {
        return 'completed';
    } elseif ($lesson_index <= $next_available_index) {
        return 'available';
    } else {
        return 'locked';
    }
}

// Include header
include '../includes/student-header.php';
?>

<!-- Module Header -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-4">
                    <a href="course-view.php?id=<?php echo $course_id; ?>" class="text-white hover:text-yellow-300 transition duration-300 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <span class="text-white opacity-75"><?php echo htmlspecialchars($module['course_title']); ?></span>
                    <span class="text-white opacity-50 mx-2">•</span>
                    <span class="text-white opacity-75">Module Details</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold mb-3 text-white">
                    <?php echo htmlspecialchars($module['title']); ?>
                </h1>
                <p class="text-white opacity-90 text-lg mb-4">
                    <?php echo htmlspecialchars($module['description']); ?>
                </p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white"><?php echo $total_lessons; ?> Lessons</span>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white"><?php echo $total_quizzes; ?> Quizzes</span>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white">Pass: <?php echo $module['pass_threshold']; ?>%</span>
                    </div>
                </div>
            </div>
            <div class="mt-6 md:mt-0 md:ml-8">
                <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                    <div class="text-3xl font-bold text-white mb-2"><?php echo $overall_progress; ?>%</div>
                    <div class="text-white opacity-90 text-sm mb-3">Module Progress</div>
                    <div class="w-full bg-white bg-opacity-30 rounded-full h-2">
                        <div class="bg-yellow-400 h-2 rounded-full transition-all duration-500" 
                             style="width: <?php echo $overall_progress; ?>%"></div>
                    </div>
                    <div class="text-white opacity-75 text-xs mt-2">
                        <?php echo $completed_lessons + $passed_quizzes; ?> of <?php echo $total_lessons + $total_quizzes; ?> items completed
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Module Completion Alert -->
<?php if ($module_completed): ?>
    <div class="mb-6">
        <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                </svg>
                <div>
                    <div class="font-semibold">Module Completed! 🎉</div>
                    <div>Great job! You have successfully completed this module.</div>
                </div>
                <div class="ml-auto">
                    <a href="course-view.php?id=<?php echo $course_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        Back to Course
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Progress Overview -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Lessons Progress -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900">Lessons Progress</h3>
                <p class="text-sm text-gray-600"><?php echo $completed_lessons; ?> of <?php echo $total_lessons; ?> completed</p>
            </div>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
            <div class="bg-blue-500 h-3 rounded-full transition-all duration-500" 
                 style="width: <?php echo $lessons_progress; ?>%"></div>
        </div>
        <div class="text-right text-sm font-medium text-blue-600"><?php echo $lessons_progress; ?>%</div>
    </div>

    <!-- Quizzes Progress -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900">Quizzes Progress</h3>
                <p class="text-sm text-gray-600"><?php echo $passed_quizzes; ?> of <?php echo $total_quizzes; ?> passed</p>
            </div>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
            <div class="bg-purple-500 h-3 rounded-full transition-all duration-500" 
                 style="width: <?php echo $quizzes_progress; ?>%"></div>
        </div>
        <div class="text-right text-sm font-medium text-purple-600"><?php echo $quizzes_progress; ?>%</div>
    </div>
</div>

<!-- Content Sections -->
<div class="space-y-8">
    <!-- Lessons Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2" style="color: #5fb3b4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Lessons (<?php echo $completed_lessons; ?>/<?php echo $total_lessons; ?>)
            </h2>
        </div>
        <div class="p-6">
            <?php if (empty($lessons)): ?>
                <div class="text-center py-8">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Lessons Available</h3>
                    <p class="text-gray-600">This module doesn't have any lessons yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <?php 
                        $lesson_status = getLessonStatus($lesson, $index, $next_lesson_index);
                        ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-300">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center flex-1">
                                    <!-- Lesson Status Icon -->
                                    <div class="mr-4">
                                        <?php if ($lesson_status == 'completed'): ?>
                                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                        <?php elseif ($lesson_status == 'available'): ?>
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background-color: #5fb3b4;">
                                                <span class="text-white font-semibold"><?php echo $index + 1; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Lesson Info -->
                                    <div class="flex-1">
                                        <div class="flex items-center mb-1">
                                            <h4 class="font-semibold text-gray-900 mr-2">
                                                Lesson <?php echo $index + 1; ?>: <?php echo htmlspecialchars($lesson['title']); ?>
                                            </h4>
                                            <?php if ($lesson_status == 'completed'): ?>
                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                                    Completed
                                                </span>
                                            <?php elseif ($lesson_status == 'locked'): ?>
                                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs font-medium">
                                                    Locked
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <?php if ($lesson['estimated_duration']): ?>
                                                <div class="flex items-center mr-4">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span><?php echo $lesson['estimated_duration']; ?> min</span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($lesson['completed_at']): ?>
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>Completed <?php echo date('M j, Y', strtotime($lesson['completed_at'])); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <div class="ml-4">
                                    <?php if ($lesson_status == 'completed'): ?>
                                        <a href="lesson-view.php?id=<?php echo $lesson['id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                                           class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300 text-sm">
                                            Review
                                        </a>
                                    <?php elseif ($lesson_status == 'available'): ?>
                                        <a href="lesson-view.php?id=<?php echo $lesson['id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                                           class="text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition duration-300 text-sm"
                                           style="background-color: #5fb3b4;">
                                            <?php echo $lesson['is_completed'] ? 'Review' : 'Start'; ?>
                                        </a>
                                    <?php else: ?>
                                        <button disabled class="bg-gray-300 text-gray-500 px-4 py-2 rounded-lg cursor-not-allowed text-sm">
                                            Locked
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quizzes Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2" style="color: #5fb3b4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Quizzes (<?php echo $passed_quizzes; ?>/<?php echo $total_quizzes; ?>)
                <?php if (!$quizzes_unlocked): ?>
                    <span class="ml-2 bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-medium">
                        Complete all lessons first
                    </span>
                <?php endif; ?>
            </h2>
        </div>
        <div class="p-6">
            <?php if (empty($quizzes)): ?>
                <div class="text-center py-8">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Quizzes Available</h3>
                    <p class="text-gray-600">This module doesn't have any quizzes yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-300">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center flex-1">
                                    <!-- Quiz Status Icon -->
                                    <div class="mr-4">
                                        <?php if ($quiz['is_passed']): ?>
                                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </div>
                                        <?php elseif ($quizzes_unlocked): ?>
                                            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Quiz Info -->
                                    <div class="flex-1">
                                        <div class="flex items-center mb-1">
                                            <h4 class="font-semibold text-gray-900 mr-2">
                                                <?php echo htmlspecialchars($quiz['title']); ?>
                                            </h4>
                                            <?php if ($quiz['quiz_type'] == 'final'): ?>
                                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium mr-2">
                                                    Final Quiz
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($quiz['is_passed']): ?>
                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                                    Passed
                                                </span>
                                            <?php elseif (!$quizzes_unlocked): ?>
                                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs font-medium">
                                                    Locked
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <div class="flex items-center mr-4">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                                                </svg>
                                                <span>Pass: <?php echo $quiz['pass_threshold']; ?>%</span>
                                            </div>
                                            <?php if ($quiz['total_attempts'] > 0): ?>
                                                <div class="flex items-center mr-4">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                    </svg>
                                                    <span><?php echo $quiz['total_attempts']; ?> attempts</span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($quiz['best_score'] !== null): ?>
                                                <div class="flex items-center mr-4">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                    </svg>
                                                    <span>Best: <?php echo $quiz['best_score']; ?>%</span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($quiz['last_attempt']): ?>
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <span>Last: <?php echo date('M j, Y', strtotime($quiz['last_attempt'])); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quiz Action Button -->
                                <div class="ml-4">
                                    <?php if ($quiz['is_passed']): ?>
                                        <a href="quiz.php?id=<?php echo $quiz['id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                                           class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300 text-sm">
                                            Review
                                        </a>
                                    <?php elseif ($quizzes_unlocked): ?>
                                        <a href="quiz.php?id=<?php echo $quiz['id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                                           class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition duration-300 text-sm">
                                            <?php echo $quiz['total_attempts'] > 0 ? 'Retake Quiz' : 'Take Quiz'; ?>
                                        </a>
                                    <?php else: ?>
                                        <button disabled class="bg-gray-300 text-gray-500 px-4 py-2 rounded-lg cursor-not-allowed text-sm">
                                            Complete Lessons First
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

    // Progress bar animations
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

    // Content section animations
    const contentSections = document.querySelectorAll('.bg-white');
    contentSections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            section.style.transition = 'all 0.6s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 150);
    });

    // Header animation
    const headerSection = document.querySelector('[style*="background: linear-gradient"]');
    if (headerSection) {
        headerSection.style.opacity = '0';
        headerSection.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            headerSection.style.transition = 'all 0.8s ease';
            headerSection.style.opacity = '1';
            headerSection.style.transform = 'translateY(0)';
        }, 200);
    }

    // Lesson/Quiz item hover effects
    const items = document.querySelectorAll('.border-gray-200');
    items.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });

    // Alert auto-hide
    const alerts = document.querySelectorAll('.bg-green-100, .bg-blue-100, .bg-yellow-100');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'all 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 7000); // Hide after 7 seconds for module completion alert
    });

    // Smooth scroll for navigation links
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

    // Add loading states to action buttons
    const actionButtons = document.querySelectorAll('a[href*="lesson-view"], a[href*="quiz.php"]');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (!button.disabled) {
                const originalText = this.textContent;
                this.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .border-gray-200 {
            transition: all 0.3s ease;
        }
    `;
    document.head.appendChild(style);
});
</script>
</body>
</html>