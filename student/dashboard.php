<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Dashboard";

// Get student statistics
try {
    $student_id = $_SESSION['student_id'];
    
    // Total courses available
    $stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM courses WHERE is_active = 1");
    $total_courses = $stmt->fetch()['total_courses'];
    
    // Courses enrolled (started)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT course_id) as enrolled_courses FROM user_progress WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetch()['enrolled_courses'];
    
    // Completed courses (with certificates)
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_courses FROM certificates WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $completed_courses = $stmt->fetch()['completed_courses'];
    
    // Current progress - modules completed
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_modules 
        FROM user_progress 
        WHERE user_id = ? AND module_id IS NOT NULL AND status = 'completed'
    ");
    $stmt->execute([$student_id]);
    $completed_modules = $stmt->fetch()['completed_modules'];
    
    // Recent course progress - get last 3 courses worked on
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.title, c.description,
               COUNT(CASE WHEN up.status = 'completed' AND up.module_id IS NOT NULL THEN 1 END) as completed_modules,
               COUNT(CASE WHEN up.module_id IS NOT NULL THEN 1 END) as total_modules,
               MAX(up.completed_at) as last_activity,
               CASE WHEN EXISTS(SELECT 1 FROM certificates cert WHERE cert.course_id = c.id AND cert.user_id = ?) THEN 1 ELSE 0 END as has_certificate
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN user_progress up ON m.id = up.module_id AND up.user_id = ?
        WHERE c.is_active = 1 AND EXISTS (
            SELECT 1 FROM user_progress up2 WHERE up2.course_id = c.id AND up2.user_id = ?
        )
        GROUP BY c.id, c.title, c.description
        ORDER BY last_activity DESC
        LIMIT 3
    ");
    $stmt->execute([$student_id, $student_id, $student_id]);
    $recent_courses = $stmt->fetchAll();
    
    // Get available courses not yet started
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description,
               COUNT(m.id) as total_modules
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        WHERE c.is_active = 1 AND NOT EXISTS (
            SELECT 1 FROM user_progress up WHERE up.course_id = c.id AND up.user_id = ?
        )
        GROUP BY c.id, c.title, c.description
        ORDER BY c.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$student_id]);
    $available_courses = $stmt->fetchAll();
    
    // Recent certificates
    $stmt = $pdo->prepare("
        SELECT cert.*, c.title as course_title
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.id
        WHERE cert.user_id = ?
        ORDER BY cert.issued_at DESC
        LIMIT 3
    ");
    $stmt->execute([$student_id]);
    $recent_certificates = $stmt->fetchAll();
    
} catch(PDOException $e) {
    // Default values if database error
    $total_courses = 0;
    $enrolled_courses = 0;
    $completed_courses = 0;
    $completed_modules = 0;
    $recent_courses = [];
    $available_courses = [];
    $recent_certificates = [];
}

// Include header
include '../includes/student-header.php';
?>

<!-- Welcome Section -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2 text-white">
                    Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['student_name'])[0]); ?>! 👋
                </h1>
                <p class="text-white opacity-90 text-lg">
                    Ready to continue your health education journey?
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="courses.php" class="bg-white text-gray-800 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 inline-block">
                    Browse Courses
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Available Courses -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $total_courses; ?></div>
            <div class="text-sm text-gray-600">Available Courses</div>
        </div>
    </div>

    <!-- Enrolled Courses -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $enrolled_courses; ?></div>
            <div class="text-sm text-gray-600">Courses Started</div>
        </div>
    </div>

    <!-- Completed Modules -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $completed_modules; ?></div>
            <div class="text-sm text-gray-600">Modules Completed</div>
        </div>
    </div>

    <!-- Certificates -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $completed_courses; ?></div>
            <div class="text-sm text-gray-600">Certificates Earned</div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Continue Learning Section -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-saffron-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Continue Learning
                </h2>
            </div>
            <div class="p-6">
                <?php if (empty($recent_courses)): ?>
                    <div class="text-center py-8">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No courses started yet</h3>
                        <p class="text-gray-600 mb-4">Start your first course to begin your health education journey!</p>
                        <a href="courses.php" class="bg-saffron-teal text-white px-6 py-2 rounded-lg hover:bg-opacity-90">
                            Browse Courses
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_courses as $course): ?>
                            <?php 
                            $progress_percentage = $course['total_modules'] > 0 ? 
                                round(($course['completed_modules'] / $course['total_modules']) * 100) : 0;
                            $is_completed = $course['has_certificate'] == 1;
                            ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md <?php echo $is_completed ? 'border-green-200 bg-green-50' : ''; ?>">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-1">
                                            <h3 class="font-semibold text-gray-900 mr-2">
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </h3>
                                            <?php if ($is_completed): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    Completed
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3">
                                            <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="mb-3">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">Progress</span>
                                        <span class="<?php echo $is_completed ? 'text-green-600' : 'text-saffron-teal'; ?> font-medium"><?php echo $progress_percentage; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $is_completed ? 'bg-green-500' : 'bg-saffron-teal'; ?> h-2 rounded-full" 
                                             style="width: <?php echo $progress_percentage; ?>%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span><?php echo $course['completed_modules']; ?> of <?php echo $course['total_modules']; ?> modules</span>
                                        <?php if ($course['last_activity']): ?>
                                            <span>Last activity: <?php echo date('M j', strtotime($course['last_activity'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <?php if ($is_completed): ?>
                                        <a href="certificate.php?course_id=<?php echo $course['id']; ?>" 
                                           class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700 mr-2">
                                            🏆 View Certificate
                                        </a>
                                        <a href="course-view.php?id=<?php echo $course['id']; ?>" 
                                           class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-600">
                                            📚 Review Course
                                        </a>
                                    <?php else: ?>
                                        <a href="course-view.php?id=<?php echo $course['id']; ?>" 
                                           class="bg-saffron-teal text-white px-4 py-2 rounded-lg text-sm hover:bg-opacity-90">
                                            Continue Learning
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center pt-4">
                        <a href="progress.php" class="text-saffron-teal hover:text-saffron-dark font-medium">
                            View All Progress →
                        </a>
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
                <a href="courses.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Browse Courses</div>
                        <div class="text-sm text-gray-600">Discover new learning opportunities</div>
                    </div>
                </a>
                
                <a href="progress.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Track Progress</div>
                        <div class="text-sm text-gray-600">Monitor your learning journey</div>
                    </div>
                </a>
                
                <a href="certificates.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">My Certificates</div>
                        <div class="text-sm text-gray-600">View earned certificates</div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Certificates -->
        <?php if (!empty($recent_certificates)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    Recent Achievements
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach ($recent_certificates as $cert): ?>
                        <div class="flex items-center p-3 bg-yellow-50 rounded-lg">
                            <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 text-sm">
                                    <?php echo htmlspecialchars($cert['course_title']); ?>
                                </div>
                                <div class="text-xs text-gray-600">
                                    Earned <?php echo date('M j, Y', strtotime($cert['issued_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center pt-4">
                    <a href="certificates.php" class="text-saffron-teal hover:text-saffron-dark font-medium text-sm">
                        View All Certificates →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Available Courses Preview -->
        <?php if (!empty($available_courses)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Start New Course</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach (array_slice($available_courses, 0, 2) as $course): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md">
                            <h4 class="font-medium text-gray-900 mb-2">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h4>
                            <p class="text-sm text-gray-600 mb-3">
                                <?php echo htmlspecialchars(substr($course['description'], 0, 80)) . '...'; ?>
                            </p>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-500">
                                    <?php echo $course['total_modules']; ?> modules
                                </span>
                                <a href="course-view.php?id=<?php echo $course['id']; ?>" 
                                   class="text-saffron-teal hover:text-saffron-dark text-sm font-medium">
                                    Start Course →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center pt-4">
                    <a href="courses.php" class="text-saffron-teal hover:text-saffron-dark font-medium text-sm">
                        View All Courses →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide notifications or alerts after 5 seconds (no animation)
    const alerts = document.querySelectorAll('.alert, .notification');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.remove();
        }, 5000);
    });
});
</script>
</body>
</html>