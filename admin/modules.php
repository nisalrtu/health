<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Module Management";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $module_id = intval($_POST['module_id']);
                    $new_status = intval($_POST['new_status']);
                    
                    $stmt = $pdo->prepare("UPDATE modules SET is_active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $module_id]);
                    
                    $success_message = "Module status updated successfully.";
                    break;
                    
                case 'delete_module':
                    $module_id = intval($_POST['module_id']);
                    
                    // Check if module has any user progress
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_progress WHERE module_id = ?");
                    $stmt->execute([$module_id]);
                    $progress_count = $stmt->fetch()['count'];
                    
                    if ($progress_count > 0) {
                        $error_message = "Cannot delete module: Students have already started this module.";
                    } else {
                        // Delete the module (will cascade to lessons and quizzes)
                        $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
                        $stmt->execute([$module_id]);
                        
                        $success_message = "Module deleted successfully.";
                    }
                    break;
                    
                case 'reorder_modules':
                    $course_id = intval($_POST['course_id']);
                    $module_orders = $_POST['module_orders'];
                    
                    foreach ($module_orders as $module_id => $order) {
                        $stmt = $pdo->prepare("UPDATE modules SET order_sequence = ? WHERE id = ? AND course_id = ?");
                        $stmt->execute([intval($order), intval($module_id), $course_id]);
                    }
                    
                    $success_message = "Module order updated successfully.";
                    break;
            }
        }
    } catch (PDOException $e) {
        $error_message = "An error occurred: " . $e->getMessage();
    }
}

// Get search and filter parameters
$search_query = trim($_GET['search'] ?? '');
$course_filter = isset($_GET['course_id']) ? intval($_GET['course_id']) : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

try {
    // Build the query
    $where_conditions = ["1=1"];
    $params = [];
    
    if (!empty($search_query)) {
        $where_conditions[] = "(m.title LIKE ? OR m.description LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    if ($course_filter !== 'all') {
        $where_conditions[] = "m.course_id = ?";
        $params[] = $course_filter;
    }
    
    if ($status_filter != 'all') {
        $where_conditions[] = "m.is_active = ?";
        $params[] = ($status_filter == 'active') ? 1 : 0;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get modules with statistics
    $stmt = $pdo->prepare("
        SELECT m.*,
               c.title as course_title,
               c.is_active as course_active,
               COUNT(DISTINCT l.id) as lesson_count,
               COUNT(DISTINCT q.id) as quiz_count,
               COUNT(DISTINCT up.user_id) as student_progress_count,
               AVG(CASE WHEN qa.passed = 1 THEN qa.score ELSE NULL END) as avg_quiz_score
        FROM modules m
        JOIN courses c ON m.course_id = c.id
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN user_progress up ON m.id = up.module_id
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.passed = 1
        $where_clause
        GROUP BY m.id, m.title, m.description, m.order_sequence, m.pass_threshold, m.is_active, m.course_id,
                 c.title, c.is_active
        ORDER BY c.title ASC, m.order_sequence ASC
    ");
    
    $stmt->execute($params);
    $modules = $stmt->fetchAll();
    
    // Get all courses for filter dropdown
    $stmt = $pdo->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title ASC");
    $all_courses = $stmt->fetchAll();
    
    // Get summary statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_modules,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_modules,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_modules
        FROM modules
    ");
    $stats = $stmt->fetch();
    
} catch(PDOException $e) {
    $modules = [];
    $all_courses = [];
    $stats = ['total_modules' => 0, 'active_modules' => 0, 'inactive_modules' => 0];
    $error_message = "Database error: " . $e->getMessage();
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Module Management</h1>
                <p class="text-purple-100 text-lg">Organize course content into structured learning modules</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="module-add.php" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    + Add Module
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($success_message)): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Modules</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_modules']); ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Active Modules</p>
                <p class="text-3xl font-bold text-green-600"><?php echo number_format($stats['active_modules']); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Inactive Modules</p>
                <p class="text-3xl font-bold text-red-600"><?php echo number_format($stats['inactive_modules']); ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Controls -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
    <form method="GET" class="space-y-4 md:space-y-0 md:flex md:items-end md:space-x-4">
        <!-- Search -->
        <div class="flex-1">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                Search Modules
            </label>
            <input 
                type="text" 
                id="search"
                name="search" 
                value="<?php echo htmlspecialchars($search_query); ?>" 
                placeholder="Search by module title or description..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
        </div>
        
        <!-- Course Filter -->
        <div class="md:w-48">
            <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">
                Filter by Course
            </label>
            <select 
                id="course_id"
                name="course_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="all" <?php echo $course_filter === 'all' ? 'selected' : ''; ?>>All Courses</option>
                <?php foreach ($all_courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Status Filter -->
        <div class="md:w-32">
            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                Status
            </label>
            <select 
                id="status"
                name="status" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        
        <!-- Buttons -->
        <div class="md:w-auto flex space-x-2">
            <button 
                type="submit" 
                class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-300"
            >
                Filter
            </button>
            <a 
                href="modules.php" 
                class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-300"
            >
                Clear
            </a>
        </div>
    </form>
</div>

<!-- Modules List -->
<?php if (empty($modules)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">
            <?php echo !empty($search_query) ? 'No modules found' : 'No modules created yet'; ?>
        </h3>
        <p class="text-gray-600 mb-4">
            <?php if (!empty($search_query)): ?>
                Try adjusting your search terms or filters.
            <?php else: ?>
                Get started by creating your first module.
            <?php endif; ?>
        </p>
        <?php if (empty($search_query)): ?>
            <a href="module-add.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition duration-300 inline-block">
                Create First Module
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Table Header -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="grid grid-cols-12 gap-4 text-sm font-medium text-gray-700">
                <div class="col-span-1">Order</div>
                <div class="col-span-4">Module</div>
                <div class="col-span-2">Course</div>
                <div class="col-span-2 text-center">Content</div>
                <div class="col-span-1 text-center">Students</div>
                <div class="col-span-1 text-center">Status</div>
                <div class="col-span-1 text-center">Actions</div>
            </div>
        </div>
        
        <!-- Table Body -->
        <div class="divide-y divide-gray-200">
            <?php 
            $current_course = null;
            foreach ($modules as $module): 
                $show_course_header = ($current_course !== $module['course_id']);
                $current_course = $module['course_id'];
            ?>
                
                <?php if ($show_course_header): ?>
                    <div class="px-6 py-3 bg-blue-50 border-b border-blue-200">
                        <h4 class="font-semibold text-blue-900">
                            <?php echo htmlspecialchars($module['course_title']); ?>
                            <?php if (!$module['course_active']): ?>
                                <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Course Inactive</span>
                            <?php endif; ?>
                        </h4>
                    </div>
                <?php endif; ?>
                
                <div class="px-6 py-4 hover:bg-gray-50">
                    <div class="grid grid-cols-12 gap-4 items-center">
                        <!-- Order -->
                        <div class="col-span-1">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-gray-100 text-gray-700 text-sm font-medium rounded-full">
                                <?php echo $module['order_sequence']; ?>
                            </span>
                        </div>
                        
                        <!-- Module Info -->
                        <div class="col-span-4">
                            <h3 class="font-semibold text-gray-900 mb-1">
                                <?php echo htmlspecialchars($module['title']); ?>
                            </h3>
                            <p class="text-sm text-gray-600 line-clamp-2">
                                <?php echo htmlspecialchars(substr($module['description'] ?? '', 0, 100)) . (strlen($module['description'] ?? '') > 100 ? '...' : ''); ?>
                            </p>
                            <div class="mt-2 text-xs text-gray-500">
                                Pass Threshold: <?php echo $module['pass_threshold']; ?>%
                            </div>
                        </div>
                        
                        <!-- Course -->
                        <div class="col-span-2">
                            <span class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($module['course_title']); ?>
                            </span>
                        </div>
                        
                        <!-- Content Stats -->
                        <div class="col-span-2 text-center">
                            <div class="space-y-1">
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium"><?php echo $module['lesson_count']; ?></span> lessons
                                </div>
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium"><?php echo $module['quiz_count']; ?></span> quizzes
                                </div>
                                <?php if ($module['avg_quiz_score']): ?>
                                    <div class="text-xs text-green-600">
                                        Avg: <?php echo round($module['avg_quiz_score'], 1); ?>%
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Student Progress -->
                        <div class="col-span-1 text-center">
                            <span class="text-sm font-medium text-gray-900">
                                <?php echo $module['student_progress_count']; ?>
                            </span>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-span-1 text-center">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $module['is_active'] ? '0' : '1'; ?>">
                                <button type="submit" 
                                        class="<?php echo $module['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> px-3 py-1 rounded-full text-sm font-medium hover:opacity-75 transition duration-300"
                                        onclick="return confirm('Are you sure you want to <?php echo $module['is_active'] ? 'deactivate' : 'activate'; ?> this module?')">
                                    <?php echo $module['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Actions -->
                        <div class="col-span-1 text-center">
                            <div class="flex justify-center space-x-1">
                                <a href="module-edit.php?id=<?php echo $module['id']; ?>" 
                                   class="bg-blue-100 text-blue-600 p-2 rounded-lg hover:bg-blue-200 transition duration-300" 
                                   title="Edit Module">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                
                                <a href="lessons.php?module_id=<?php echo $module['id']; ?>" 
                                   class="bg-purple-100 text-purple-600 p-2 rounded-lg hover:bg-purple-200 transition duration-300" 
                                   title="Manage Lessons">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>
                                
                                <a href="quizzes.php?module_id=<?php echo $module['id']; ?>" 
                                   class="bg-green-100 text-green-600 p-2 rounded-lg hover:bg-green-200 transition duration-300" 
                                   title="Manage Quizzes">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>
                                
                                <?php if ($module['student_progress_count'] == 0): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="delete_module">
                                        <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                        <button type="submit" 
                                                class="bg-red-100 text-red-600 p-2 rounded-lg hover:bg-red-200 transition duration-300" 
                                                title="Delete Module"
                                                onclick="return confirm('Are you sure you want to delete this module? This action cannot be undone.')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    const actionButtons = document.querySelectorAll('a[href*="module-edit"], a[href*="module-add"], a[href*="lessons"], a[href*="quizzes"]');
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
            const message = `Are you sure you want to ${action} this module?`;
            
            if (confirm(message)) {
                this.closest('form').submit();
            }
        });
    });

    // Add hover effects for course headers
    const courseHeaders = document.querySelectorAll('.bg-blue-50');
    courseHeaders.forEach(header => {
        header.addEventListener('mouseenter', function() {
            this.classList.add('bg-blue-100');
        });
        
        header.addEventListener('mouseleave', function() {
            this.classList.remove('bg-blue-100');
            this.classList.add('bg-blue-50');
        });
    });
});

// CSS for loading animation
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
`;
document.head.appendChild(style);
</script>