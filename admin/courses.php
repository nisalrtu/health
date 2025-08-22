<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Manage Courses";

$success_message = '';
$error_message = '';

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $course_id = intval($_POST['course_id']);
                    $new_status = $_POST['new_status'] == '1' ? 1 : 0;
                    
                    $stmt = $pdo->prepare("UPDATE courses SET is_active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $course_id]);
                    
                    $success_message = 'Course status updated successfully!';
                    break;
                    
                case 'delete_course':
                    $course_id = intval($_POST['course_id']);
                    
                    // Check if course has any enrollments
                    $stmt = $pdo->prepare("SELECT COUNT(*) as enrollments FROM user_progress WHERE course_id = ?");
                    $stmt->execute([$course_id]);
                    $enrollments = $stmt->fetch()['enrollments'];
                    
                    if ($enrollments > 0) {
                        $error_message = 'Cannot delete course with active enrollments. Deactivate it instead.';
                    } else {
                        // Delete course and related data
                        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                        $stmt->execute([$course_id]);
                        
                        $success_message = 'Course deleted successfully!';
                    }
                    break;
            }
        } catch(PDOException $e) {
            $error_message = 'Error processing request. Please try again.';
        }
    }
}

// Search and filter
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

try {
    // Build the query
    $where_conditions = [];
    $params = [];
    
    if (!empty($search_query)) {
        $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    if ($status_filter != 'all') {
        $where_conditions[] = "c.is_active = ?";
        $params[] = ($status_filter == 'active') ? 1 : 0;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get courses with statistics
    $stmt = $pdo->prepare("
        SELECT c.*,
               COUNT(DISTINCT m.id) as module_count,
               COUNT(DISTINCT l.id) as lesson_count,
               COUNT(DISTINCT q.id) as quiz_count,
               COUNT(DISTINCT up.user_id) as enrolled_students,
               COUNT(DISTINCT cert.user_id) as completed_students
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN user_progress up ON c.id = up.course_id
        LEFT JOIN certificates cert ON c.id = cert.course_id
        $where_clause
        GROUP BY c.id, c.title, c.description, c.is_active, c.created_at
        ORDER BY c.created_at DESC
    ");
    
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
    
    // Get summary statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_courses,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_courses,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_courses
        FROM courses
    ");
    $stats = $stmt->fetch();
    
} catch(PDOException $e) {
    $courses = [];
    $stats = ['total_courses' => 0, 'active_courses' => 0, 'inactive_courses' => 0];
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Course Management</h1>
                <p class="text-purple-100 text-lg">Manage all courses, modules, and content</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="course-add.php" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300 inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New Course
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($success_message): ?>
    <div class="mb-6">
        <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                </svg>
                <div class="font-semibold"><?php echo htmlspecialchars($success_message); ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="mb-6">
        <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="font-semibold"><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_courses']; ?></div>
            <div class="text-sm text-gray-600">Total Courses</div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $stats['active_courses']; ?></div>
            <div class="text-sm text-gray-600">Active Courses</div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-2xl font-bold text-gray-900"><?php echo $stats['inactive_courses']; ?></div>
            <div class="text-sm text-gray-600">Inactive Courses</div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
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
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
            </div>
        </div>
        <div>
            <select name="status" class="px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Courses</option>
                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active Only</option>
                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition duration-300">
                Search
            </button>
            <?php if (!empty($search_query) || $status_filter != 'all'): ?>
                <a href="courses.php" class="px-6 py-3 bg-gray-500 text-white rounded-lg font-medium hover:bg-gray-600 transition duration-300">
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Courses List -->
<?php if (empty($courses)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">
            <?php echo !empty($search_query) ? 'No courses found' : 'No courses created yet'; ?>
        </h3>
        <p class="text-gray-600 mb-4">
            <?php if (!empty($search_query)): ?>
                Try adjusting your search terms or filters.
            <?php else: ?>
                Get started by creating your first course.
            <?php endif; ?>
        </p>
        <?php if (empty($search_query)): ?>
            <a href="course-add.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition duration-300 inline-block">
                Create First Course
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Table Header -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="grid grid-cols-12 gap-4 text-sm font-medium text-gray-700">
                <div class="col-span-4">Course</div>
                <div class="col-span-2 text-center">Content</div>
                <div class="col-span-2 text-center">Students</div>
                <div class="col-span-2 text-center">Status</div>
                <div class="col-span-2 text-center">Actions</div>
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-200">
            <?php foreach ($courses as $course): ?>
                <div class="px-6 py-4 hover:bg-gray-50">
                    <div class="grid grid-cols-12 gap-4 items-center">
                        <!-- Course Info -->
                        <div class="col-span-4">
                            <h3 class="font-semibold text-gray-900 mb-1">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </h3>
                            <p class="text-sm text-gray-600 line-clamp-2">
                                <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?>
                            </p>
                            <div class="text-xs text-gray-500 mt-1">
                                Created: <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                            </div>
                        </div>
                        
                        <!-- Content Stats -->
                        <div class="col-span-2 text-center">
                            <div class="space-y-1">
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium"><?php echo $course['module_count']; ?></span> modules
                                </div>
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium"><?php echo $course['lesson_count']; ?></span> lessons
                                </div>
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium"><?php echo $course['quiz_count']; ?></span> quizzes
                                </div>
                            </div>
                        </div>
                        
                        <!-- Student Stats -->
                        <div class="col-span-2 text-center">
                            <div class="space-y-1">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo $course['enrolled_students']; ?> enrolled
                                </div>
                                <div class="text-sm text-green-600">
                                    <?php echo $course['completed_students']; ?> completed
                                </div>
                                <?php if ($course['enrolled_students'] > 0): ?>
                                    <div class="text-xs text-gray-500">
                                        <?php echo round(($course['completed_students'] / $course['enrolled_students']) * 100); ?>% completion
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-span-2 text-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $course['is_active'] ? '0' : '1'; ?>">
                                <button type="submit" 
                                        class="<?php echo $course['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> px-3 py-1 rounded-full text-sm font-medium hover:opacity-75 transition duration-300"
                                        onclick="return confirm('Are you sure you want to <?php echo $course['is_active'] ? 'deactivate' : 'activate'; ?> this course?')">
                                    <?php echo $course['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Actions -->
                        <div class="col-span-2 text-center">
                            <div class="flex justify-center space-x-2">
                                <a href="course-edit.php?id=<?php echo $course['id']; ?>" 
                                   class="bg-blue-100 text-blue-600 p-2 rounded-lg hover:bg-blue-200 transition duration-300" 
                                   title="Edit Course">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                
                                <a href="modules.php?course_id=<?php echo $course['id']; ?>" 
                                   class="bg-purple-100 text-purple-600 p-2 rounded-lg hover:bg-purple-200 transition duration-300" 
                                   title="Manage Modules">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                </a>
                                
                                <?php if ($course['enrolled_students'] == 0): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_course">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" 
                                                class="bg-red-100 text-red-600 p-2 rounded-lg hover:bg-red-200 transition duration-300" 
                                                title="Delete Course"
                                                onclick="return confirm('Are you sure you want to delete this course? This action cannot be undone.')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-400 p-2 rounded-lg cursor-not-allowed" title="Cannot delete course with enrollments">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Search Results Info -->
    <?php if (!empty($search_query) || $status_filter != 'all'): ?>
        <div class="mt-4 text-center text-gray-600">
            <p>
                Showing <?php echo count($courses); ?> course(s)
                <?php if (!empty($search_query)): ?>
                    for "<?php echo htmlspecialchars($search_query); ?>"
                <?php endif; ?>
                <?php if ($status_filter != 'all'): ?>
                    (<?php echo $status_filter; ?> only)
                <?php endif; ?>
            </p>
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
            }
        });
    }

    // Add loading states to action buttons
    const actionButtons = document.querySelectorAll('a[href*="course-edit"], a[href*="course-add"], a[href*="modules"]');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const icon = this.querySelector('svg');
            if (icon) {
                icon.style.animation = 'spin 1s linear infinite';
            }
            this.style.opacity = '0.7';
            this.style.pointerEvents = 'none';
        });
    });

    // Enhance status toggle buttons
    const statusButtons = document.querySelectorAll('button[onclick*="confirm"]');
    statusButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const isActive = this.textContent.trim() === 'Active';
            const action = isActive ? 'deactivate' : 'activate';
            const message = `Are you sure you want to ${action} this course?`;
            
            if (confirm(message)) {
                // Add loading state
                this.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-1 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                `;
                this.disabled = true;
                
                // Submit the form
                this.closest('form').submit();
            }
        });
    });

    // Enhance delete buttons
    const deleteButtons = document.querySelectorAll('button[onclick*="delete"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const message = 'Are you sure you want to delete this course? This action cannot be undone.';
            
            if (confirm(message)) {
                // Add loading state
                const icon = this.querySelector('svg');
                if (icon) {
                    icon.style.animation = 'spin 1s linear infinite';
                }
                this.style.opacity = '0.7';
                this.disabled = true;
                
                // Submit the form
                this.closest('form').submit();
            }
        });
    });

    // Hover effects for course rows
    const courseRows = document.querySelectorAll('.grid.grid-cols-12.gap-4.items-center');
    courseRows.forEach(row => {
        const parentDiv = row.closest('.px-6.py-4');
        if (parentDiv) {
            parentDiv.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(4px)';
                this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.05)';
            });
            
            parentDiv.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
                this.style.boxShadow = 'none';
            });
        }
    });

    // Add CSS for animations and transitions
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .px-6.py-4 {
            transition: all 0.3s ease;
        }
        
        .hover\\:bg-gray-50:hover {
            background-color: #f9fafb;
        }
        
        .hover\\:opacity-75:hover {
            opacity: 0.75;
        }
        
        .hover\\:bg-blue-200:hover {
            background-color: #dbeafe;
        }
        
        .hover\\:bg-purple-200:hover {
            background-color: #e9d5ff;
        }
        
        .hover\\:bg-red-200:hover {
            background-color: #fecaca;
        }
    `;
    document.head.appendChild(style);
});
</script>
</body>
</html>