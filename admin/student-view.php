<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id <= 0) {
    header('Location: students.php');
    exit();
}

// Set page title
$page_title = "Student Details";

try {
    // Get student information
    $stmt = $pdo->prepare("
        SELECT u.*,
               COUNT(DISTINCT up_course.course_id) as enrolled_courses,
               COUNT(DISTINCT CASE WHEN up_lesson.status = 'completed' THEN up_lesson.lesson_id END) as completed_lessons,
               COUNT(DISTINCT up_lesson.lesson_id) as total_lessons_attempted,
               COUNT(DISTINCT qa.id) as total_quiz_attempts,
               COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.quiz_id END) as passed_quizzes,
               COUNT(DISTINCT cert.id) as certificates_earned,
               MAX(up_activity.completed_at) as last_activity,
               AVG(CASE WHEN qa.passed = 1 THEN qa.score END) as avg_quiz_score
        FROM users u
        LEFT JOIN user_progress up_course ON u.id = up_course.user_id AND up_course.lesson_id IS NULL AND up_course.module_id IS NULL
        LEFT JOIN user_progress up_lesson ON u.id = up_lesson.user_id AND up_lesson.lesson_id IS NOT NULL
        LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
        LEFT JOIN certificates cert ON u.id = cert.user_id
        LEFT JOIN user_progress up_activity ON u.id = up_activity.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        header('Location: students.php?error=Student not found');
        exit();
    }

    // Get course enrollment and progress details
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description,
               COUNT(DISTINCT m.id) as total_modules,
               COUNT(DISTINCT l.id) as total_lessons,
               COUNT(DISTINCT q.id) as total_quizzes,
               COUNT(DISTINCT CASE WHEN up_lesson.status = 'completed' THEN up_lesson.lesson_id END) as completed_lessons,
               COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.quiz_id END) as passed_quizzes,
               cert.id as certificate_id,
               cert.certificate_code,
               cert.issued_at as certificate_date,
               up_course.status as enrollment_status,
               MIN(up_course.completed_at) as enrolled_at
        FROM courses c
        INNER JOIN user_progress up_course ON c.id = up_course.course_id AND up_course.user_id = ? 
                   AND up_course.lesson_id IS NULL AND up_course.module_id IS NULL
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN user_progress up_lesson ON l.id = up_lesson.lesson_id AND up_lesson.user_id = ?
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = ?
        LEFT JOIN certificates cert ON c.id = cert.course_id AND cert.user_id = ?
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY enrolled_at DESC
    ");
    $stmt->execute([$student_id, $student_id, $student_id, $student_id]);
    $enrolled_courses = $stmt->fetchAll();

    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT 
            'lesson_completed' as activity_type,
            l.title as item_title,
            c.title as course_title,
            m.title as module_title,
            up.completed_at as activity_time
        FROM user_progress up
        JOIN lessons l ON up.lesson_id = l.id
        JOIN modules m ON l.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE up.user_id = ? AND up.status = 'completed' AND up.lesson_id IS NOT NULL
        
        UNION ALL
        
        SELECT 
            'quiz_attempted' as activity_type,
            q.title as item_title,
            c.title as course_title,
            m.title as module_title,
            qa.completed_at as activity_time
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE qa.user_id = ?
        
        UNION ALL
        
        SELECT 
            'certificate_earned' as activity_type,
            c.title as item_title,
            c.title as course_title,
            'Certificate' as module_title,
            cert.issued_at as activity_time
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        WHERE cert.user_id = ?
        
        ORDER BY activity_time DESC
        LIMIT 10
    ");
    $stmt->execute([$student_id, $student_id, $student_id]);
    $recent_activities = $stmt->fetchAll();

    // Get quiz performance details
    $stmt = $pdo->prepare("
        SELECT q.title as quiz_title,
               c.title as course_title,
               m.title as module_title,
               qa.attempt_number,
               qa.score,
               qa.total_questions,
               qa.correct_answers,
               qa.passed,
               qa.started_at,
               qa.completed_at
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE qa.user_id = ?
        ORDER BY qa.completed_at DESC
        LIMIT 20
    ");
    $stmt->execute([$student_id]);
    $quiz_attempts = $stmt->fetchAll();

    // Get certificates
    $stmt = $pdo->prepare("
        SELECT cert.*, c.title as course_title
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        WHERE cert.user_id = ?
        ORDER BY cert.issued_at DESC
    ");
    $stmt->execute([$student_id]);
    $certificates = $stmt->fetchAll();

} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $student = null;
    $enrolled_courses = [];
    $recent_activities = [];
    $quiz_attempts = [];
    $certificates = [];
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'reset_password':
                // Generate a temporary password
                $temp_password = 'temp' . rand(1000, 9999);
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $student_id]);
                
                $success_message = "Password reset successfully. Temporary password: <strong>" . $temp_password . "</strong>";
                break;
                
            case 'toggle_status':
                $new_status = isset($_POST['is_active']) ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $student_id]);
                
                $status_text = $new_status ? 'activated' : 'deactivated';
                $success_message = "Student account {$status_text} successfully.";
                
                // Update student data
                $student['is_active'] = $new_status;
                break;
                
            case 'add_note':
                // This would require a notes table - for now just show success
                $note_text = trim($_POST['note_text'] ?? '');
                if (!empty($note_text)) {
                    $success_message = "Note would be added: " . htmlspecialchars($note_text);
                }
                break;
        }
    } catch(PDOException $e) {
        $error_message = "Error performing action: " . $e->getMessage();
    }
}

// Calculate progress percentages
function calculateProgress($completed, $total) {
    return $total > 0 ? round(($completed / $total) * 100) : 0;
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div class="flex items-center">
                <a href="students.php" class="mr-4 text-white hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">
                        Student Profile: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </h1>
                    <p class="text-indigo-100 text-lg">
                        Detailed view of student progress and activity
                    </p>
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <button onclick="openPasswordResetModal()" 
                        class="bg-white text-indigo-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition duration-300">
                    🔑 Reset Password
                </button>
                <a href="student-edit.php?id=<?php echo $student_id; ?>" 
                   class="bg-white/20 text-white px-4 py-2 rounded-lg hover:bg-white/30 transition duration-300">
                    ✏️ Edit Profile
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($success_message)): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <?php echo $success_message; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Student Information -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Basic Info Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Student Information</h3>
            </div>
            <div class="p-6">
                <div class="flex items-center mb-6">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                        <span class="text-xl font-bold text-indigo-600">
                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                        </span>
                    </div>
                    <div>
                        <h4 class="text-xl font-semibold text-gray-900">
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </h4>
                        <p class="text-gray-600">Student ID: #<?php echo $student['id']; ?></p>
                        
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-600">Email</label>
                        <div class="text-gray-900 break-all"><?php echo htmlspecialchars($student['email']); ?></div>
                    </div>
                    
                    <?php if (isset($student['institute_name']) && $student['institute_name']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Institute</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($student['institute_name']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($student['age']) && $student['age']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Age</label>
                        <div class="text-gray-900"><?php echo $student['age']; ?> years</div>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600">Joined</label>
                        <div class="text-gray-900"><?php echo date('F j, Y', strtotime($student['created_at'])); ?></div>
                    </div>
                    
                    <?php if ($student['last_activity']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600">Last Activity</label>
                        <div class="text-gray-900">
                            <?php echo date('F j, Y g:i A', strtotime($student['last_activity'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Progress Summary Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Progress Summary</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $student['enrolled_courses']; ?></div>
                        <div class="text-sm text-blue-800">Courses Enrolled</div>
                    </div>
                    
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600"><?php echo $student['completed_lessons']; ?></div>
                        <div class="text-sm text-green-800">Lessons Completed</div>
                    </div>
                    
                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600"><?php echo $student['passed_quizzes']; ?></div>
                        <div class="text-sm text-purple-800">Quizzes Passed</div>
                    </div>
                    
                    <div class="text-center p-4 bg-yellow-50 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-600"><?php echo $student['certificates_earned']; ?></div>
                        <div class="text-sm text-yellow-800">Certificates</div>
                    </div>
                </div>

                <?php if ($student['avg_quiz_score']): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900">Average Quiz Score</div>
                        <div class="text-3xl font-bold text-indigo-600 mt-2">
                            <?php echo round($student['avg_quiz_score']); ?>%
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                            <div class="bg-indigo-600 h-2 rounded-full" style="width: <?php echo round($student['avg_quiz_score']); ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-3">
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="toggle_status">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo (isset($student['is_active']) && $student['is_active']) ? 'checked' : ''; ?>
                               onchange="this.form.submit()"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">Active Account</span>
                    </label>
                </form>
                
                <button onclick="openPasswordResetModal()" 
                        class="w-full text-left p-3 rounded-lg hover:bg-gray-50 transition duration-300 flex items-center">
                    <svg class="w-5 h-5 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    <div>
                        <div class="font-medium text-gray-900">Reset Password</div>
                        <div class="text-sm text-gray-600">Generate temporary password</div>
                    </div>
                </button>
                
                <a href="student-edit.php?id=<?php echo $student_id; ?>" 
                   class="block w-full text-left p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        <div>
                            <div class="font-medium text-gray-900">Edit Profile</div>
                            <div class="text-sm text-gray-600">Update student information</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Course Progress -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Course Progress</h3>
            </div>
            <div class="p-6">
                <?php if (empty($enrolled_courses)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <p>Student is not enrolled in any courses</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="border border-gray-200 rounded-lg p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-900 mb-2">
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </h4>
                                        <p class="text-gray-600 text-sm">
                                            Enrolled: <?php echo $course['enrolled_at'] ? date('M j, Y', strtotime($course['enrolled_at'])) : 'Unknown'; ?>
                                        </p>
                                    </div>
                                    <?php if ($course['certificate_id']): ?>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                                🏆 Certified
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?php echo date('M j, Y', strtotime($course['certificate_date'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">
                                            <?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Lessons</div>
                                        <?php 
                                        $lesson_progress = calculateProgress($course['completed_lessons'], $course['total_lessons']);
                                        ?>
                                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $lesson_progress; ?>%"></div>
                                        </div>
                                        <div class="text-xs text-blue-600 mt-1"><?php echo $lesson_progress; ?>%</div>
                                    </div>

                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-purple-600">
                                            <?php echo $course['passed_quizzes']; ?>/<?php echo $course['total_quizzes']; ?>
                                        </div>
                                        <div class="text-sm text-gray-600">Quizzes</div>
                                        <?php 
                                        $quiz_progress = calculateProgress($course['passed_quizzes'], $course['total_quizzes']);
                                        ?>
                                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                            <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo $quiz_progress; ?>%"></div>
                                        </div>
                                        <div class="text-xs text-purple-600 mt-1"><?php echo $quiz_progress; ?>%</div>
                                    </div>

                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600">
                                            <?php 
                                            $overall_progress = calculateProgress(
                                                $course['completed_lessons'] + $course['passed_quizzes'],
                                                $course['total_lessons'] + $course['total_quizzes']
                                            );
                                            echo $overall_progress;
                                            ?>%
                                        </div>
                                        <div class="text-sm text-gray-600">Overall</div>
                                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                            <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $overall_progress; ?>%"></div>
                                        </div>
                                        <div class="text-xs text-green-600 mt-1">Progress</div>
                                    </div>
                                </div>

                                <?php if ($course['certificate_id']): ?>
                                    <div class="pt-4 border-t border-gray-200">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="text-sm text-gray-600">Certificate:</span>
                                                <span class="ml-2 font-mono text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($course['certificate_code']); ?>
                                                </span>
                                            </div>
                                            <a href="certificate.php?code=<?php echo urlencode($course['certificate_code']); ?>" 
                                               target="_blank"
                                               class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                                View Certificate →
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
            </div>
            <div class="p-6">
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p>No recent activity</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                                <div class="flex-shrink-0">
                                    <?php if ($activity['activity_type'] == 'lesson_completed'): ?>
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                            </svg>
                                        </div>
                                    <?php elseif ($activity['activity_type'] == 'quiz_attempted'): ?>
                                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900">
                                        <?php if ($activity['activity_type'] == 'lesson_completed'): ?>
                                            Completed lesson <span class="font-medium"><?php echo htmlspecialchars($activity['item_title']); ?></span>
                                        <?php elseif ($activity['activity_type'] == 'quiz_attempted'): ?>
                                            Attempted quiz <span class="font-medium"><?php echo htmlspecialchars($activity['item_title']); ?></span>
                                        <?php else: ?>
                                            Earned certificate for <span class="font-medium"><?php echo htmlspecialchars($activity['item_title']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($activity['course_title']); ?>
                                        <?php if ($activity['module_title'] != 'Certificate'): ?>
                                            • <?php echo htmlspecialchars($activity['module_title']); ?>
                                        <?php endif; ?>
                                        • <?php echo date('M j, Y g:i A', strtotime($activity['activity_time'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quiz Performance -->
        <?php if (!empty($quiz_attempts)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Quiz Performance</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($quiz_attempts as $attempt): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($attempt['quiz_title']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($attempt['course_title']); ?> • 
                                        <?php echo htmlspecialchars($attempt['module_title']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $attempt['score']; ?>%
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($attempt['passed']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Passed
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Failed
                                        </span>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Attempt #<?php echo $attempt['attempt_number']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($attempt['completed_at'])); ?>
                                    <div class="text-xs">
                                        <?php echo date('g:i A', strtotime($attempt['completed_at'])); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Certificates -->
        <?php if (!empty($certificates)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Certificates Earned</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($certificates as $certificate): ?>
                        <div class="border border-yellow-200 bg-yellow-50 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($certificate['course_title']); ?>
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        <?php echo date('F j, Y', strtotime($certificate['issued_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-sm text-gray-700">
                                    <?php echo htmlspecialchars($certificate['certificate_code']); ?>
                                </span>
                                <a href="certificate.php?code=<?php echo urlencode($certificate['certificate_code']); ?>" 
                                   target="_blank"
                                   class="text-yellow-700 hover:text-yellow-900 text-sm font-medium">
                                    View →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Password Reset Modal -->
<div id="passwordResetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Reset Password</h3>
                <button onclick="closePasswordResetModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <p class="text-sm text-gray-600 mb-4">
                This will generate a new temporary password for the student. The student will need to use this password to log in.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closePasswordResetModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-300">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-300">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success/error messages
    setTimeout(function() {
        const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
        messages.forEach(message => {
            message.style.transition = 'all 0.5s ease';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                message.remove();
            }, 500);
        });
    }, 5000);
});

function openPasswordResetModal() {
    document.getElementById('passwordResetModal').classList.remove('hidden');
}

function closePasswordResetModal() {
    document.getElementById('passwordResetModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('passwordResetModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePasswordResetModal();
    }
});
</script>

</main>
</div>
</body>
</html>
