<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "My Progress";

// Get student statistics and progress
try {
    $student_id = $_SESSION['student_id'];
    
    // Overall statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as total_available_courses,
            COUNT(DISTINCT up_course.course_id) as enrolled_courses,
            COUNT(DISTINCT cert.course_id) as completed_courses,
            COUNT(DISTINCT CASE WHEN up_module.status = 'completed' THEN up_module.module_id END) as completed_modules,
            COUNT(DISTINCT CASE WHEN up_lesson.status = 'completed' THEN up_lesson.lesson_id END) as completed_lessons,
            COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.quiz_id END) as passed_quizzes,
            COALESCE(AVG(CASE WHEN qa.passed = 1 THEN qa.score END), 0) as avg_quiz_score
        FROM courses c
        LEFT JOIN user_progress up_course ON c.id = up_course.course_id AND up_course.user_id = ? AND up_course.module_id IS NULL AND up_course.lesson_id IS NULL
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN user_progress up_module ON m.id = up_module.module_id AND up_module.user_id = ?
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN user_progress up_lesson ON l.id = up_lesson.lesson_id AND up_lesson.user_id = ?
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = ? AND qa.passed = 1
        LEFT JOIN certificates cert ON c.id = cert.course_id AND cert.user_id = ?
        WHERE c.is_active = 1
    ");
    $stmt->execute([$student_id, $student_id, $student_id, $student_id, $student_id]);
    $overall_stats = $stmt->fetch();
    
    // Course-wise progress details
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            c.description as course_description,
            COUNT(DISTINCT m.id) as total_modules,
            COUNT(DISTINCT l.id) as total_lessons,
            COUNT(DISTINCT q.id) as total_quizzes,
            COUNT(DISTINCT CASE WHEN up_module.status = 'completed' THEN up_module.module_id END) as completed_modules,
            COUNT(DISTINCT CASE WHEN up_lesson.status = 'completed' THEN up_lesson.lesson_id END) as completed_lessons,
            COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.quiz_id END) as passed_quizzes,
            COALESCE(AVG(CASE WHEN qa.passed = 1 THEN qa.score END), 0) as avg_course_score,
            cert.id as certificate_id,
            cert.certificate_code,
            cert.issued_at as certificate_date,
            MIN(up_any.completed_at) as started_at,
            MAX(up_any.completed_at) as last_activity
        FROM courses c
        INNER JOIN user_progress up_enrollment ON c.id = up_enrollment.course_id AND up_enrollment.user_id = ?
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN user_progress up_module ON m.id = up_module.module_id AND up_module.user_id = ?
        LEFT JOIN user_progress up_lesson ON l.id = up_lesson.lesson_id AND up_lesson.user_id = ?
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = ? AND qa.passed = 1
        LEFT JOIN certificates cert ON c.id = cert.course_id AND cert.user_id = ?
        LEFT JOIN user_progress up_any ON c.id = up_any.course_id AND up_any.user_id = ?
        WHERE c.is_active = 1
        GROUP BY c.id, c.title, c.description, cert.id, cert.certificate_code, cert.issued_at
        ORDER BY last_activity DESC
    ");
    $stmt->execute([$student_id, $student_id, $student_id, $student_id, $student_id, $student_id]);
    $course_progress = $stmt->fetchAll();
    
    // Recent learning activity (last 10 activities)
    $stmt = $pdo->prepare("
        SELECT 
            'lesson' as activity_type,
            l.title as activity_title,
            c.title as course_title,
            m.title as module_title,
            up.completed_at as activity_date,
            'Completed lesson' as activity_description
        FROM user_progress up
        JOIN lessons l ON up.lesson_id = l.id
        JOIN modules m ON l.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE up.user_id = ? AND up.status = 'completed' AND up.lesson_id IS NOT NULL
        
        UNION ALL
        
        SELECT 
            'quiz' as activity_type,
            q.title as activity_title,
            c.title as course_title,
            m.title as module_title,
            qa.completed_at as activity_date,
            CONCAT('Passed quiz with ', qa.score, '% score') as activity_description
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE qa.user_id = ? AND qa.passed = 1
        
        UNION ALL
        
        SELECT 
            'certificate' as activity_type,
            CONCAT('Certificate for ', c.title) as activity_title,
            c.title as course_title,
            '' as module_title,
            cert.issued_at as activity_date,
            'Earned course certificate' as activity_description
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        WHERE cert.user_id = ?
        
        ORDER BY activity_date DESC
        LIMIT 10
    ");
    $stmt->execute([$student_id, $student_id, $student_id]);
    $recent_activities = $stmt->fetchAll();
    
    // Monthly progress statistics for chart
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(activity_date, '%Y-%m') as month,
            COUNT(*) as activities_count
        FROM (
            SELECT up.completed_at as activity_date
            FROM user_progress up
            WHERE up.user_id = ? AND up.status = 'completed' AND up.completed_at IS NOT NULL
            
            UNION ALL
            
            SELECT qa.completed_at as activity_date
            FROM quiz_attempts qa
            WHERE qa.user_id = ? AND qa.passed = 1 AND qa.completed_at IS NOT NULL
            
            UNION ALL
            
            SELECT cert.issued_at as activity_date
            FROM certificates cert
            WHERE cert.user_id = ? AND cert.issued_at IS NOT NULL
        ) as all_activities
        WHERE activity_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month DESC
        LIMIT 6
    ");
    $stmt->execute([$student_id, $student_id, $student_id]);
    $monthly_progress = $stmt->fetchAll();
    
} catch(PDOException $e) {
    // Default values if database error
    $overall_stats = [
        'total_available_courses' => 0,
        'enrolled_courses' => 0,
        'completed_courses' => 0,
        'completed_modules' => 0,
        'completed_lessons' => 0,
        'passed_quizzes' => 0,
        'avg_quiz_score' => 0
    ];
    $course_progress = [];
    $recent_activities = [];
    $monthly_progress = [];
}

// Calculate completion percentages
$course_completion_rate = $overall_stats['enrolled_courses'] > 0 ? 
    round(($overall_stats['completed_courses'] / $overall_stats['enrolled_courses']) * 100) : 0;

// Include header
include '../includes/student-header.php';
?>

<!-- Progress Overview Section -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">My Learning Progress</h1>
                <p class="text-blue-100">Track your journey and achievements</p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="text-center">
                    <div class="text-4xl font-bold"><?php echo $course_completion_rate; ?>%</div>
                    <div class="text-sm text-blue-100">Course Completion Rate</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Enrolled Courses -->
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Enrolled Courses</p>
                <p class="text-2xl font-semibold text-gray-900"><?php echo $overall_stats['enrolled_courses']; ?></p>
            </div>
        </div>
    </div>

    <!-- Completed Modules -->
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Completed Modules</p>
                <p class="text-2xl font-semibold text-gray-900"><?php echo $overall_stats['completed_modules']; ?></p>
            </div>
        </div>
    </div>

    <!-- Completed Lessons -->
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Completed Lessons</p>
                <p class="text-2xl font-semibold text-gray-900"><?php echo $overall_stats['completed_lessons']; ?></p>
            </div>
        </div>
    </div>

    <!-- Average Quiz Score -->
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Average Quiz Score</p>
                <p class="text-2xl font-semibold text-gray-900"><?php echo round($overall_stats['avg_quiz_score']); ?>%</p>
            </div>
        </div>
    </div>
</div>

<!-- Course Progress Details -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Course Progress -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Course Progress</h2>
            <p class="text-sm text-gray-600">Your progress in enrolled courses</p>
        </div>
        <div class="p-6">
            <?php if (empty($course_progress)): ?>
                <div class="text-center py-8">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <p class="text-gray-500 text-lg">No courses enrolled yet</p>
                    <p class="text-gray-400 text-sm mb-4">Start your learning journey by enrolling in a course</p>
                    <a href="courses.php" class="inline-flex items-center px-4 py-2 bg-saffron-teal text-white rounded-lg hover:bg-opacity-90 transition duration-300">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Browse Courses
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($course_progress as $course): ?>
                        <?php 
                        $module_progress = $course['total_modules'] > 0 ? round(($course['completed_modules'] / $course['total_modules']) * 100) : 0;
                        $lesson_progress = $course['total_lessons'] > 0 ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) : 0;
                        $quiz_progress = $course['total_quizzes'] > 0 ? round(($course['passed_quizzes'] / $course['total_quizzes']) * 100) : 0;
                        $overall_progress = round(($module_progress + $lesson_progress + $quiz_progress) / 3);
                        ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($course['course_title']); ?></h3>
                                    <?php if ($course['certificate_id']): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Completed
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-saffron-teal"><?php echo $overall_progress; ?>%</div>
                                    <div class="text-xs text-gray-500">Overall</div>
                                </div>
                            </div>
                            
                            <!-- Progress Bars -->
                            <div class="space-y-2">
                                <!-- Modules -->
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">Modules</span>
                                    <span class="text-gray-500"><?php echo $course['completed_modules']; ?>/<?php echo $course['total_modules']; ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $module_progress; ?>%"></div>
                                </div>
                                
                                <!-- Lessons -->
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">Lessons</span>
                                    <span class="text-gray-500"><?php echo $course['completed_lessons']; ?>/<?php echo $course['total_lessons']; ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $lesson_progress; ?>%"></div>
                                </div>
                                
                                <!-- Quizzes -->
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600">Quizzes</span>
                                    <span class="text-gray-500"><?php echo $course['passed_quizzes']; ?>/<?php echo $course['total_quizzes']; ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full transition-all duration-500" style="width: <?php echo $quiz_progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                                <div class="text-sm text-gray-500">
                                    <?php if ($course['avg_course_score'] > 0): ?>
                                        Avg. Score: <?php echo round($course['avg_course_score']); ?>%
                                    <?php endif; ?>
                                </div>
                                <a href="course-view.php?id=<?php echo $course['course_id']; ?>" class="text-saffron-teal hover:text-saffron-dark text-sm font-medium">
                                    Continue Learning →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Recent Activity</h2>
            <p class="text-sm text-gray-600">Your latest learning achievements</p>
        </div>
        <div class="p-6">
            <?php if (empty($recent_activities)): ?>
                <div class="text-center py-8">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-500 text-lg">No recent activity</p>
                    <p class="text-gray-400 text-sm">Start learning to see your progress here</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-start space-x-3">
                            <!-- Activity Icon -->
                            <div class="flex-shrink-0">
                                <?php if ($activity['activity_type'] == 'lesson'): ?>
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </div>
                                <?php elseif ($activity['activity_type'] == 'quiz'): ?>
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Activity Details -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars($activity['activity_title']); ?>
                                </p>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($activity['activity_description']); ?>
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?php echo htmlspecialchars($activity['course_title']); ?>
                                    <?php if (!empty($activity['module_title'])): ?>
                                        • <?php echo htmlspecialchars($activity['module_title']); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['activity_date'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Monthly Progress Chart -->
<?php if (!empty($monthly_progress)): ?>
<div class="bg-white rounded-lg shadow-sm border mb-8">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Learning Activity Trend</h2>
        <p class="text-sm text-gray-600">Your learning activities over the past 6 months</p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-6 gap-4">
            <?php 
            $max_activities = max(array_column($monthly_progress, 'activities_count'));
            foreach (array_reverse($monthly_progress) as $month_data): 
                $percentage = $max_activities > 0 ? ($month_data['activities_count'] / $max_activities) * 100 : 0;
                $month_name = date('M Y', strtotime($month_data['month'] . '-01'));
            ?>
                <div class="text-center">
                    <div class="bg-gray-200 rounded-lg h-32 flex items-end justify-center mb-2">
                        <div class="bg-saffron-teal rounded-lg w-full transition-all duration-500" style="height: <?php echo max(8, $percentage); ?>%"></div>
                    </div>
                    <div class="text-sm font-medium text-gray-900"><?php echo $month_data['activities_count']; ?></div>
                    <div class="text-xs text-gray-500"><?php echo $month_name; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Achievement Summary -->
<div class="bg-white rounded-lg shadow-sm border">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Achievement Summary</h2>
        <p class="text-sm text-gray-600">Your learning milestones and accomplishments</p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Certificates Earned -->
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo $overall_stats['completed_courses']; ?></h3>
                <p class="text-sm text-gray-600">Certificates Earned</p>
                <?php if ($overall_stats['completed_courses'] > 0): ?>
                    <a href="certificates.php" class="text-xs text-yellow-600 hover:text-yellow-700 font-medium">View All →</a>
                <?php endif; ?>
            </div>

            <!-- Quiz Performance -->
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo round($overall_stats['avg_quiz_score']); ?>%</h3>
                <p class="text-sm text-gray-600">Average Quiz Score</p>
                <p class="text-xs text-gray-500"><?php echo $overall_stats['passed_quizzes']; ?> quizzes passed</p>
            </div>

            <!-- Learning Consistency -->
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo count($recent_activities); ?></h3>
                <p class="text-sm text-gray-600">Recent Activities</p>
                <p class="text-xs text-gray-500">Last 10 achievements</p>
            </div>
        </div>
    </div>
</div>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar functionality
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

    // Animate progress bars
    const progressBars = document.querySelectorAll('[style*="width:"]');
    progressBars.forEach(bar => {
        if (bar.style.width && bar.style.width !== '0%') {
            const width = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.transition = 'width 1s ease-in-out';
                bar.style.width = width;
            }, 500);
        }
    });

    // Animate chart bars
    const chartBars = document.querySelectorAll('.bg-saffron-teal');
    chartBars.forEach((bar, index) => {
        if (bar.style.height && bar.style.height !== '8%') {
            const height = bar.style.height;
            bar.style.height = '8%';
            
            setTimeout(() => {
                bar.style.transition = 'height 0.8s ease-in-out';
                bar.style.height = height;
            }, 600 + (index * 100));
        }
    });

    // Add hover effects to course progress cards
    const courseCards = document.querySelectorAll('.border.border-gray-200.rounded-lg');
    courseCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
            this.style.transition = 'all 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });

    // Add counter animation for statistics
    const counters = document.querySelectorAll('.text-2xl.font-bold, .text-4xl.font-bold');
    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        if (!isNaN(target)) {
            let current = 0;
            const increment = target / 30;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target + (counter.textContent.includes('%') ? '%' : '');
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current) + (counter.textContent.includes('%') ? '%' : '');
                }
            }, 50);
        }
    });

    // Add fade-in animation for activity items
    const activityItems = document.querySelectorAll('.space-y-4 > div');
    activityItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 300 + (index * 100));
    });
});
</script>

</body>
</html>
