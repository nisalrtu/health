<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Dashboard";

try {
    // Get platform statistics
    
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM users");
    $total_students = $stmt->fetch()['total_students'];
    
    // Total courses
    $stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM courses WHERE is_active = 1");
    $total_courses = $stmt->fetch()['total_courses'];
    
    // Total modules
    $stmt = $pdo->query("SELECT COUNT(*) as total_modules FROM modules WHERE is_active = 1");
    $total_modules = $stmt->fetch()['total_modules'];
    
    // Total lessons
    $stmt = $pdo->query("SELECT COUNT(*) as total_lessons FROM lessons");
    $total_lessons = $stmt->fetch()['total_lessons'];
    
    // Total quizzes
    $stmt = $pdo->query("SELECT COUNT(*) as total_quizzes FROM quizzes WHERE is_active = 1");
    $total_quizzes = $stmt->fetch()['total_quizzes'];
    
    // Total certificates issued
    $stmt = $pdo->query("SELECT COUNT(*) as total_certificates FROM certificates");
    $total_certificates = $stmt->fetch()['total_certificates'];
    
    // Active enrollments (students who have started courses)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as active_enrollments FROM user_progress");
    $active_enrollments = $stmt->fetch()['active_enrollments'];
    
    // Quiz attempts today
    $stmt = $pdo->query("SELECT COUNT(*) as quiz_attempts_today FROM quiz_attempts WHERE DATE(started_at) = CURDATE()");
    $quiz_attempts_today = $stmt->fetch()['quiz_attempts_today'];
    
    // Recent student registrations (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as new_students FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $new_students = $stmt->fetch()['new_students'];
    
    // Course completion rate
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT up.user_id) as enrolled_students,
            COUNT(DISTINCT c.user_id) as completed_students
        FROM user_progress up
        LEFT JOIN certificates c ON up.user_id = c.user_id AND up.course_id = c.course_id
    ");
    $completion_data = $stmt->fetch();
    $completion_rate = $completion_data['enrolled_students'] > 0 ? 
        round(($completion_data['completed_students'] / $completion_data['enrolled_students']) * 100) : 0;
    
    // Recent activity - last 10 activities
    $stmt = $pdo->query("
        SELECT 
            'lesson_completed' as activity_type,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            l.title as item_title,
            c.title as course_title,
            up.completed_at as activity_time
        FROM user_progress up
        JOIN users u ON up.user_id = u.id
        JOIN lessons l ON up.lesson_id = l.id
        JOIN modules m ON l.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE up.status = 'completed' AND up.lesson_id IS NOT NULL
        
        UNION ALL
        
        SELECT 
            'quiz_passed' as activity_type,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            q.title as item_title,
            c.title as course_title,
            qa.completed_at as activity_time
        FROM quiz_attempts qa
        JOIN users u ON qa.user_id = u.id
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE qa.passed = 1
        
        UNION ALL
        
        SELECT 
            'certificate_earned' as activity_type,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            c.title as item_title,
            c.title as course_title,
            cert.issued_at as activity_time
        FROM certificates cert
        JOIN users u ON cert.user_id = u.id
        JOIN courses c ON cert.course_id = c.id
        
        ORDER BY activity_time DESC
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Top performing courses
    $stmt = $pdo->query("
        SELECT 
            c.title,
            COUNT(DISTINCT up.user_id) as enrolled_students,
            COUNT(DISTINCT cert.user_id) as completed_students,
            CASE 
                WHEN COUNT(DISTINCT up.user_id) > 0 
                THEN ROUND((COUNT(DISTINCT cert.user_id) / COUNT(DISTINCT up.user_id)) * 100)
                ELSE 0 
            END as completion_rate
        FROM courses c
        LEFT JOIN user_progress up ON c.id = up.course_id
        LEFT JOIN certificates cert ON c.id = cert.course_id
        WHERE c.is_active = 1
        GROUP BY c.id, c.title
        HAVING enrolled_students > 0
        ORDER BY completion_rate DESC, enrolled_students DESC
        LIMIT 5
    ");
    $top_courses = $stmt->fetchAll();
    
    // Recent students
    $stmt = $pdo->query("
        SELECT 
            CONCAT(first_name, ' ', last_name) as name,
            email,
            institute_name,
            created_at,
            (SELECT COUNT(DISTINCT course_id) FROM user_progress WHERE user_id = users.id) as courses_enrolled
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_students = $stmt->fetchAll();
    
    // System alerts (courses with no modules, modules with no lessons, etc.)
    $alerts = [];
    
    // Check for courses without modules
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM courses c 
        WHERE c.is_active = 1 AND NOT EXISTS (
            SELECT 1 FROM modules m WHERE m.course_id = c.id AND m.is_active = 1
        )
    ");
    $courses_without_modules = $stmt->fetch()['count'];
    if ($courses_without_modules > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "$courses_without_modules course(s) have no modules",
            'action' => 'courses.php'
        ];
    }
    
    // Check for modules without lessons
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM modules m 
        WHERE m.is_active = 1 AND NOT EXISTS (
            SELECT 1 FROM lessons l WHERE l.module_id = m.id
        )
    ");
    $modules_without_lessons = $stmt->fetch()['count'];
    if ($modules_without_lessons > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "$modules_without_lessons module(s) have no lessons",
            'action' => 'modules.php'
        ];
    }
    
    // Check for modules without quizzes
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM modules m 
        WHERE m.is_active = 1 AND NOT EXISTS (
            SELECT 1 FROM quizzes q WHERE q.module_id = m.id AND q.is_active = 1
        )
    ");
    $modules_without_quizzes = $stmt->fetch()['count'];
    if ($modules_without_quizzes > 0) {
        $alerts[] = [
            'type' => 'info',
            'message' => "$modules_without_quizzes module(s) have no quizzes",
            'action' => 'quizzes.php'
        ];
    }
    
} catch(PDOException $e) {
    // Default values if database error
    $total_students = 0;
    $total_courses = 0;
    $total_modules = 0;
    $total_lessons = 0;
    $total_quizzes = 0;
    $total_certificates = 0;
    $active_enrollments = 0;
    $quiz_attempts_today = 0;
    $new_students = 0;
    $completion_rate = 0;
    $recent_activities = [];
    $top_courses = [];
    $recent_students = [];
    $alerts = [];
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Welcome Section -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">
                    Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['admin_name'])[0]); ?>! 👋
                </h1>
                <p class="text-purple-100 text-lg">
                    Here's what's happening with your learning platform today.
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="course-add.php" class="bg-white text-indigo-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition duration-300">
                        + Add Course
                    </a>
                    <a href="reports.php" class="border border-white text-white px-4 py-2 rounded-lg font-medium hover:bg-white hover:text-indigo-600 transition duration-300">
                        View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Alerts -->
<?php if (!empty($alerts)): ?>
<div class="mb-8">
    <div class="space-y-3">
        <?php foreach ($alerts as $alert): ?>
            <div class="<?php echo $alert['type'] == 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-800' : 'bg-blue-50 border-blue-200 text-blue-800'; ?> border rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium"><?php echo htmlspecialchars($alert['message']); ?></span>
                    </div>
                    <a href="<?php echo $alert['action']; ?>" class="text-sm font-medium hover:underline">
                        Fix Now →
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Key Metrics -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Students -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo number_format($total_students); ?></div>
            <div class="text-sm text-gray-600">Total Students</div>
            <?php if ($new_students > 0): ?>
                <div class="text-xs text-green-600 mt-1">+<?php echo $new_students; ?> this week</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Enrollments -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo number_format($active_enrollments); ?></div>
            <div class="text-sm text-gray-600">Active Enrollments</div>
            <div class="text-xs text-gray-500 mt-1">Students learning</div>
        </div>
    </div>

    <!-- Certificates Issued -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo number_format($total_certificates); ?></div>
            <div class="text-sm text-gray-600">Certificates Issued</div>
            <div class="text-xs text-purple-600 mt-1"><?php echo $completion_rate; ?>% completion rate</div>
        </div>
    </div>

    <!-- Quiz Attempts Today -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo number_format($quiz_attempts_today); ?></div>
            <div class="text-sm text-gray-600">Quiz Attempts Today</div>
            <div class="text-xs text-gray-500 mt-1">Current activity</div>
        </div>
    </div>
</div>

<!-- Content Overview -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Courses -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="text-center">
            <div class="text-3xl font-bold text-indigo-600 mb-2"><?php echo $total_courses; ?></div>
            <div class="text-gray-600 font-medium">Courses</div>
            <a href="courses.php" class="text-sm text-indigo-600 hover:text-indigo-800 mt-2 inline-block">Manage →</a>
        </div>
    </div>

    <!-- Modules -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="text-center">
            <div class="text-3xl font-bold text-green-600 mb-2"><?php echo $total_modules; ?></div>
            <div class="text-gray-600 font-medium">Modules</div>
            <a href="modules.php" class="text-sm text-green-600 hover:text-green-800 mt-2 inline-block">Manage →</a>
        </div>
    </div>

    <!-- Lessons -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="text-center">
            <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $total_lessons; ?></div>
            <div class="text-gray-600 font-medium">Lessons</div>
            <a href="lessons.php" class="text-sm text-blue-600 hover:text-blue-800 mt-2 inline-block">Manage →</a>
        </div>
    </div>

    <!-- Quizzes -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="text-center">
            <div class="text-3xl font-bold text-purple-600 mb-2"><?php echo $total_quizzes; ?></div>
            <div class="text-gray-600 font-medium">Quizzes</div>
            <a href="quizzes.php" class="text-sm text-purple-600 hover:text-purple-800 mt-2 inline-block">Manage →</a>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Recent Activity -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900">Recent Activity</h2>
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
                                    <?php elseif ($activity['activity_type'] == 'quiz_passed'): ?>
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                        <span class="font-medium"><?php echo htmlspecialchars($activity['student_name']); ?></span>
                                        <?php if ($activity['activity_type'] == 'lesson_completed'): ?>
                                            completed lesson <span class="font-medium"><?php echo htmlspecialchars($activity['item_title']); ?></span>
                                        <?php elseif ($activity['activity_type'] == 'quiz_passed'): ?>
                                            passed quiz <span class="font-medium"><?php echo htmlspecialchars($activity['item_title']); ?></span>
                                        <?php else: ?>
                                            earned certificate for <span class="font-medium"><?php echo htmlspecialchars($activity['item_title']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($activity['course_title']); ?> • 
                                        <?php echo date('M j, Y g:i A', strtotime($activity['activity_time'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-3">
                <a href="course-add.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Add New Course</div>
                        <div class="text-sm text-gray-600">Create a new learning course</div>
                    </div>
                </a>
                
                <a href="students.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">View Students</div>
                        <div class="text-sm text-gray-600">Manage student accounts</div>
                    </div>
                </a>
                
                <a href="reports.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">View Reports</div>
                        <div class="text-sm text-gray-600">Analytics & insights</div>
                    </div>
                </a>
                
                <a href="certificates.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Manage Certificates</div>
                        <div class="text-sm text-gray-600">View issued certificates</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Top Performing Courses -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Top Performing Courses</h3>
            </div>
            <div class="p-6">
                <?php if (empty($top_courses)): ?>
                    <div class="text-center py-4 text-gray-500">
                        <p class="text-sm">No course data available</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($top_courses as $course): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </h4>
                                <div class="flex justify-between text-sm text-gray-600 mb-2">
                                    <span><?php echo $course['enrolled_students']; ?> enrolled</span>
                                    <span><?php echo $course['completed_students']; ?> completed</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $course['completion_rate']; ?>%"></div>
                                </div>
                                <div class="text-right text-sm font-medium text-green-600 mt-1">
                                    <?php echo $course['completion_rate']; ?>% completion
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Students -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Recent Students</h3>
            </div>
            <div class="p-6">
                <?php if (empty($recent_students)): ?>
                    <div class="text-center py-4 text-gray-500">
                        <p class="text-sm">No students registered yet</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach (array_slice($recent_students, 0, 5) as $student): ?>
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-sm font-medium text-indigo-600">
                                            <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($student['name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $student['institute_name'] ? htmlspecialchars($student['institute_name']) : 'No institute'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('M j', strtotime($student['created_at'])); ?>
                                    </div>
                                    <div class="text-xs text-indigo-600">
                                        <?php echo $student['courses_enrolled']; ?> courses
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center pt-4">
                        <a href="students.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            View All Students →
                        </a>
                    </div>
                <?php endif; ?>
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

    // Auto-refresh dashboard data every 5 minutes
    setInterval(function() {
        // You can implement AJAX refresh here if needed
        console.log('Dashboard data refresh check');
    }, 300000);

    // Add loading states to quick action buttons
    const quickActionButtons = document.querySelectorAll('a[href*="course-add"], a[href*="students"], a[href*="reports"], a[href*="certificates"]');
    quickActionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const icon = this.querySelector('svg');
            if (icon) {
                icon.style.animation = 'spin 1s linear infinite';
            }
        });
    });

    // Add hover effects to metric cards
    const metricCards = document.querySelectorAll('.grid > div');
    metricCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .grid > div {
            transition: all 0.3s ease;
        }
        
        .hover\\:bg-gray-50:hover {
            background-color: #f9fafb;
        }
    `;
    document.head.appendChild(style);
});
</script>
</body>
</html>