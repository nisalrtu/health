<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Course Details";

$student_id = $_SESSION['student_id'];
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

try {
    // Get course information
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND is_active = 1");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        header('Location: courses.php');
        exit();
    }
    
    // Get all modules for this course in order
    $stmt = $pdo->prepare("
        SELECT m.*, 
               COUNT(DISTINCT l.id) as total_lessons,
               COUNT(DISTINCT q.id) as total_quizzes,
               COUNT(DISTINCT CASE WHEN up_lesson.status = 'completed' THEN up_lesson.lesson_id END) as completed_lessons,
               COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.quiz_id END) as passed_quizzes
        FROM modules m
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN user_progress up_lesson ON l.id = up_lesson.lesson_id AND up_lesson.user_id = ?
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = ? AND qa.passed = 1
        WHERE m.course_id = ? AND m.is_active = 1
        GROUP BY m.id, m.title, m.description, m.order_sequence, m.pass_threshold, m.is_active
        ORDER BY m.order_sequence ASC
    ");
    $stmt->execute([$student_id, $student_id, $course_id]);
    $modules = $stmt->fetchAll();
    
    // Check if student is enrolled in this course
    $stmt = $pdo->prepare("SELECT COUNT(*) as enrolled FROM user_progress WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    $is_enrolled = $stmt->fetch()['enrolled'] > 0;
    
    // Get overall course progress
    $total_modules = count($modules);
    $completed_modules = 0;
    $unlocked_module_index = 0;
    
    foreach ($modules as $index => $module) {
        $module_completed = ($module['completed_lessons'] == $module['total_lessons']) && 
                           ($module['passed_quizzes'] == $module['total_quizzes']);
        
        if ($module_completed) {
            $completed_modules++;
        } else if ($unlocked_module_index == $index) {
            $unlocked_module_index = $index + 1; // Next module will be unlocked
        }
    }
    
    // Check if course is completed (has certificate)
    $stmt = $pdo->prepare("SELECT COUNT(*) as has_certificate FROM certificates WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    $course_completed = $stmt->fetch()['has_certificate'] > 0;
    
    $overall_progress = $total_modules > 0 ? round(($completed_modules / $total_modules) * 100) : 0;
    
} catch(PDOException $e) {
    header('Location: courses.php');
    exit();
}

// Function to determine module status
function getModuleStatus($module, $module_index, $completed_modules) {
    $lessons_completed = $module['completed_lessons'] == $module['total_lessons'];
    $quizzes_passed = $module['passed_quizzes'] == $module['total_quizzes'];
    $module_completed = $lessons_completed && $quizzes_passed;
    
    if ($module_completed) {
        return 'completed';
    } else if ($module_index <= $completed_modules) {
        return 'available';
    } else {
        return 'locked';
    }
}

// Include header
include '../includes/student-header.php';
?>

<!-- Course Header -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-4">
                    <a href="courses.php" class="text-white hover:text-yellow-300 transition duration-300 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <span class="text-white opacity-75">Course Details</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold mb-3 text-white">
                    <?php echo htmlspecialchars($course['title']); ?>
                </h1>
                <p class="text-white opacity-90 text-lg mb-4">
                    <?php echo htmlspecialchars($course['description']); ?>
                </p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white"><?php echo $total_modules; ?> Modules</span>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white"><?php echo array_sum(array_column($modules, 'total_lessons')); ?> Lessons</span>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white"><?php echo array_sum(array_column($modules, 'total_quizzes')); ?> Quizzes</span>
                    </div>
                </div>
            </div>
            <div class="mt-6 md:mt-0 md:ml-8">
                <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                    <div class="text-3xl font-bold text-white mb-2"><?php echo $overall_progress; ?>%</div>
                    <div class="text-white opacity-90 text-sm mb-3">Course Progress</div>
                    <div class="w-full bg-white bg-opacity-30 rounded-full h-2">
                        <div class="bg-yellow-400 h-2 rounded-full transition-all duration-500" 
                             style="width: <?php echo $overall_progress; ?>%"></div>
                    </div>
                    <div class="text-white opacity-75 text-xs mt-2">
                        <?php echo $completed_modules; ?> of <?php echo $total_modules; ?> modules completed
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Course Status Alert -->
<?php if ($course_completed): ?>
    <div class="mb-6">
        <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
                <div>
                    <div class="font-semibold">Congratulations! 🎉</div>
                    <div>You have successfully completed this course and earned your certificate!</div>
                </div>
                <div class="ml-auto">
                    <a href="certificates.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300">
                        View Certificate
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$is_enrolled): ?>
    <div class="mb-6">
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-6 py-4 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <div class="font-semibold">Ready to start learning?</div>
                        <div>Enroll in this course to begin your learning journey.</div>
                    </div>
                </div>
                <button onclick="enrollCourse()" class="text-white px-6 py-2 rounded-lg font-medium hover:bg-opacity-90 transition duration-300" style="background-color: #5fb3b4;">
                    Enroll Now
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modules List -->
<div class="space-y-6">
    <?php foreach ($modules as $index => $module): ?>
        <?php 
        $module_status = getModuleStatus($module, $index, $completed_modules);
        $lessons_completed = $module['completed_lessons'];
        $total_lessons = $module['total_lessons'];
        $quizzes_passed = $module['passed_quizzes'];
        $total_quizzes = $module['total_quizzes'];
        $module_progress = $total_lessons > 0 ? round(($lessons_completed / $total_lessons) * 100) : 0;
        ?>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Module Header -->
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-start justify-between">
                    <div class="flex items-start">
                        <!-- Module Status Icon -->
                        <div class="mr-4 mt-1">
                            <?php if ($module_status == 'completed'): ?>
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            <?php elseif ($module_status == 'available'): ?>
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
                        
                        <!-- Module Info -->
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h3 class="text-xl font-semibold text-gray-900 mr-3">
                                    Module <?php echo $index + 1; ?>: <?php echo htmlspecialchars($module['title']); ?>
                                </h3>
                                <?php if ($module_status == 'completed'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                                        Completed
                                    </span>
                                <?php elseif ($module_status == 'locked'): ?>
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs font-medium">
                                        Locked
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-600 mb-4">
                                <?php echo htmlspecialchars($module['description']); ?>
                            </p>
                            
                            <!-- Module Statistics -->
                            <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                    </svg>
                                    <span><?php echo $total_lessons; ?> Lessons</span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span><?php echo $total_quizzes; ?> Quizzes</span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Pass: <?php echo $module['pass_threshold']; ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Button -->
                    <div class="ml-4">
                        <?php if ($module_status == 'completed'): ?>
                            <a href="module-view.php?id=<?php echo $module['id']; ?>&course_id=<?php echo $course_id; ?>" 
                               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                                Review Module
                            </a>
                        <?php elseif ($module_status == 'available'): ?>
                            <a href="module-view.php?id=<?php echo $module['id']; ?>&course_id=<?php echo $course_id; ?>" 
                               class="text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition duration-300"
                               style="background-color: #5fb3b4;">
                                <?php echo ($lessons_completed > 0 || $quizzes_passed > 0) ? 'Continue Module' : 'Start Module'; ?>
                            </a>
                        <?php else: ?>
                            <button disabled class="bg-gray-300 text-gray-500 px-4 py-2 rounded-lg cursor-not-allowed">
                                Locked
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Module Progress (if started) -->
            <?php if ($module_status != 'locked' && ($lessons_completed > 0 || $quizzes_passed > 0)): ?>
                <div class="p-6 bg-gray-50">
                    <div class="mb-3">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Module Progress</span>
                            <span class="font-medium" style="color: #5fb3b4;"><?php echo $module_progress; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all duration-500" 
                                 style="width: <?php echo $module_progress; ?>%; background-color: #5fb3b4;"></div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Lessons:</span>
                            <span class="font-medium text-gray-900">
                                <?php echo $lessons_completed; ?>/<?php echo $total_lessons; ?>
                                <?php if ($lessons_completed == $total_lessons): ?>
                                    <span class="text-green-600 ml-1">✓</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Quizzes:</span>
                            <span class="font-medium text-gray-900">
                                <?php echo $quizzes_passed; ?>/<?php echo $total_quizzes; ?>
                                <?php if ($quizzes_passed == $total_quizzes): ?>
                                    <span class="text-green-600 ml-1">✓</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Empty State -->
<?php if (empty($modules)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Modules Available</h3>
        <p class="text-gray-600 mb-4">This course doesn't have any modules yet. Please check back later.</p>
        <a href="courses.php" class="text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition duration-300 inline-block" style="background-color: #5fb3b4;">
            Browse Other Courses
        </a>
    </div>
<?php endif; ?>

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
    const progressBars = document.querySelectorAll('[style*="background-color: #5fb3b4"]');
    progressBars.forEach(bar => {
        if (bar.style.width) {
            const width = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.width = width;
            }, 800);
        }
    });

    // Module card animations
    const moduleCards = document.querySelectorAll('.bg-white');
    moduleCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
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
});

// Enroll in course function
function enrollCourse() {
    const courseId = <?php echo $course_id; ?>;
    
    fetch('enroll-course.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'course_id=' + courseId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload page to show enrolled state
            window.location.reload();
        } else {
            alert('Error enrolling in course: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while enrolling in the course.');
    });
}
</script>
</body>
</html>