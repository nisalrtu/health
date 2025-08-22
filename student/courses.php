<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "All Courses";

// Get student ID
$student_id = $_SESSION['student_id'];

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Build the main query for courses
    $where_clause = "WHERE c.is_active = 1";
    $params = [];
    
    if (!empty($search_query)) {
        $where_clause .= " AND (c.title LIKE ? OR c.description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    // Get all courses with student progress information
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.created_at,
               COUNT(DISTINCT m.id) as total_modules,
               COUNT(DISTINCT CASE WHEN up.status = 'completed' AND up.module_id IS NOT NULL THEN up.module_id END) as completed_modules,
               CASE WHEN EXISTS(SELECT 1 FROM user_progress up2 WHERE up2.course_id = c.id AND up2.user_id = ?) THEN 1 ELSE 0 END as is_enrolled,
               CASE WHEN EXISTS(SELECT 1 FROM certificates cert WHERE cert.course_id = c.id AND cert.user_id = ?) THEN 1 ELSE 0 END as is_completed,
               MAX(up.completed_at) as last_activity
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN user_progress up ON m.id = up.module_id AND up.user_id = ?
        $where_clause
        GROUP BY c.id, c.title, c.description, c.created_at
        ORDER BY is_enrolled DESC, c.created_at DESC
    ");
    
    $execute_params = [$student_id, $student_id, $student_id];
    if (!empty($params)) {
        $execute_params = array_merge($execute_params, $params);
    }
    
    $stmt->execute($execute_params);
    $courses = $stmt->fetchAll();
    
    // Get student statistics for the header
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT course_id) as enrolled_count FROM user_progress WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $enrolled_count = $stmt->fetch()['enrolled_count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_count FROM certificates WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $completed_count = $stmt->fetch()['completed_count'];
    
} catch(PDOException $e) {
    $courses = [];
    $enrolled_count = 0;
    $completed_count = 0;
}

// Include header
include '../includes/student-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2 text-white">
                    Explore Our Courses 📚
                </h1>
                <p class="text-white opacity-90 text-lg">
                    Discover comprehensive lifestyle medicine education programs
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="flex flex-col sm:flex-row gap-4 text-center">
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <div class="text-2xl font-bold text-white"><?php echo $enrolled_count; ?></div>
                        <div class="text-sm text-white opacity-90">Enrolled</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <div class="text-2xl font-bold text-white"><?php echo $completed_count; ?></div>
                        <div class="text-sm text-white opacity-90">Completed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input 
                        type="text" 
                        name="search"
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        placeholder="Search courses by title or description..."
                        class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-6 py-3 text-white rounded-lg font-medium hover:bg-opacity-90 transition duration-300" style="background-color: #5fb3b4;">
                    Search
                </button>
                <?php if (!empty($search_query)): ?>
                    <a href="courses.php" class="px-6 py-3 bg-gray-500 text-white rounded-lg font-medium hover:bg-gray-600 transition duration-300">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Quick Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo count($courses); ?></div>
            <div class="text-sm text-gray-600">Available Courses</div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $enrolled_count; ?></div>
            <div class="text-sm text-gray-600">Courses Enrolled</div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900">
                <?php echo array_reduce($courses, function($carry, $course) { 
                    return $carry + ($course['is_enrolled'] && !$course['is_completed'] ? 1 : 0); 
                }, 0); ?>
            </div>
            <div class="text-sm text-gray-600">In Progress</div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $completed_count; ?></div>
            <div class="text-sm text-gray-600">Completed</div>
        </div>
    </div>
</div>

<!-- Courses Grid -->
<?php if (empty($courses)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">
            <?php echo !empty($search_query) ? 'No courses found' : 'No courses available'; ?>
        </h3>
        <p class="text-gray-600 mb-4">
            <?php if (!empty($search_query)): ?>
                Try adjusting your search terms or browse all available courses.
            <?php else: ?>
                New courses will appear here when they become available.
            <?php endif; ?>
        </p>
        <?php if (!empty($search_query)): ?>
            <a href="courses.php" class="text-white px-6 py-2 rounded-lg hover:bg-opacity-90 transition duration-300 inline-block" style="background-color: #5fb3b4;">
                View All Courses
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($courses as $course): ?>
            <?php 
            $progress_percentage = $course['total_modules'] > 0 ? 
                round(($course['completed_modules'] / $course['total_modules']) * 100) : 0;
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition duration-300 course-card">
                <!-- Course Status Badge -->
                <div class="relative">
                    <div class="h-2" style="background-color: #5fb3b4;"></div>
                    <?php if ($course['is_completed']): ?>
                        <div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                            ✓ Completed
                        </div>
                    <?php elseif ($course['is_enrolled']): ?>
                        <div class="absolute top-4 right-4 bg-blue-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                            In Progress
                        </div>
                    <?php else: ?>
                        <div class="absolute top-4 right-4 bg-gray-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                            Not Started
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-6">
                    <!-- Course Title and Description -->
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h3>
                    <p class="text-gray-600 mb-4 line-clamp-3">
                        <?php echo htmlspecialchars(substr($course['description'], 0, 150)) . (strlen($course['description']) > 150 ? '...' : ''); ?>
                    </p>

                    <!-- Course Info -->
                    <div class="flex items-center text-sm text-gray-500 mb-4">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <span><?php echo $course['total_modules']; ?> modules</span>
                        
                        <?php if ($course['is_enrolled'] && $course['last_activity']): ?>
                            <span class="mx-2">•</span>
                            <span>Last activity: <?php echo date('M j', strtotime($course['last_activity'])); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Progress Bar (if enrolled) -->
                    <?php if ($course['is_enrolled']): ?>
                        <div class="mb-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Progress</span>
                                <span class="font-medium" style="color: #5fb3b4;"><?php echo $progress_percentage; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-500" 
                                     style="width: <?php echo $progress_percentage; ?>%; background-color: #5fb3b4;"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo $course['completed_modules']; ?> of <?php echo $course['total_modules']; ?> modules completed
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Button -->
                    <div class="flex justify-between items-center">
                        <div class="text-xs text-gray-500">
                            Added <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                        </div>
                        <a href="course-view.php?id=<?php echo $course['id']; ?>" 
                           class="text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-90 transition duration-300 action-btn"
                           style="background-color: #5fb3b4;">
                            <?php if ($course['is_completed']): ?>
                                Review Course
                            <?php elseif ($course['is_enrolled']): ?>
                                Continue Learning
                            <?php else: ?>
                                Start Course
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Search Results Info -->
    <?php if (!empty($search_query)): ?>
        <div class="mt-8 text-center">
            <p class="text-gray-600">
                Showing <?php echo count($courses); ?> course(s) for "<?php echo htmlspecialchars($search_query); ?>"
            </p>
            <a href="courses.php" class="text-blue-600 hover:text-blue-800 font-medium mt-2 inline-block">
                View All Courses
            </a>
        </div>
    <?php endif; ?>
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

    // Animation for course cards
    const courseCards = document.querySelectorAll('.course-card');
    courseCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Progress bar animation
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

    // Loading state for action buttons
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
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
        });
    });

    // Search form enhancements
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        // Auto-focus search if there's a query
        if (searchInput.value) {
            searchInput.focus();
        }
        
        // Clear search with Escape key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.form.submit();
            }
        });
    }

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

    // Add custom CSS for animations and line clamping
    const style = document.createElement('style');
    style.textContent = `
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .course-card:hover {
            transform: translateY(-2px);
        }
        
        .course-card {
            transition: all 0.3s ease;
        }
    `;
    document.head.appendChild(style);
});
</script>
</body>
</html>