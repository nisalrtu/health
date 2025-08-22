<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Lessons Management";

// Get module filter if provided
$module_filter = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$course_filter = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_lessons = isset($_POST['selected_lessons']) ? $_POST['selected_lessons'] : [];
    
    if (!empty($selected_lessons) && in_array($action, ['activate', 'deactivate', 'delete'])) {
        try {
            $placeholders = str_repeat('?,', count($selected_lessons) - 1) . '?';
            
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE lessons SET is_active = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($selected_lessons);
                    $success_message = count($selected_lessons) . " lesson(s) activated successfully.";
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE lessons SET is_active = 0 WHERE id IN ($placeholders)");
                    $stmt->execute($selected_lessons);
                    $success_message = count($selected_lessons) . " lesson(s) deactivated successfully.";
                    break;
                    
                case 'delete':
                    // Check if any lessons have user progress
                    $stmt = $pdo->prepare("SELECT lesson_id FROM user_progress WHERE lesson_id IN ($placeholders)");
                    $stmt->execute($selected_lessons);
                    $lessons_with_progress = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($lessons_with_progress)) {
                        $error_message = "Cannot delete lessons that have student progress. Consider deactivating instead.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id IN ($placeholders)");
                        $stmt->execute($selected_lessons);
                        $success_message = count($selected_lessons) . " lesson(s) deleted successfully.";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

// Handle individual lesson deletion
if (isset($_GET['delete']) && $_GET['delete']) {
    $lesson_id = intval($_GET['delete']);
    
    try {
        // Check if lesson has user progress
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE lesson_id = ?");
        $stmt->execute([$lesson_id]);
        $progress_count = $stmt->fetchColumn();
        
        if ($progress_count > 0) {
            $error_message = "Cannot delete lesson with student progress. Consider deactivating instead.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
            $stmt->execute([$lesson_id]);
            $success_message = "Lesson deleted successfully.";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting lesson: " . $e->getMessage();
    }
}

// Handle lesson status toggle
if (isset($_GET['toggle']) && $_GET['toggle']) {
    $lesson_id = intval($_GET['toggle']);
    
    try {
        $stmt = $pdo->prepare("UPDATE lessons SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$lesson_id]);
        $success_message = "Lesson status updated successfully.";
    } catch (PDOException $e) {
        $error_message = "Error updating lesson status: " . $e->getMessage();
    }
}

// Build query with filters
$where_conditions = [];
$params = [];

if ($module_filter > 0) {
    $where_conditions[] = "l.module_id = ?";
    $params[] = $module_filter;
}

if ($course_filter > 0) {
    $where_conditions[] = "m.course_id = ?";
    $params[] = $course_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total lessons count for pagination
try {
    $count_sql = "
        SELECT COUNT(*) FROM lessons l
        JOIN modules m ON l.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_lessons = $stmt->fetchColumn();
} catch(PDOException $e) {
    $total_lessons = 0;
}

// Pagination
$lessons_per_page = 15;
$total_pages = ceil($total_lessons / $lessons_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $lessons_per_page;

// Get lessons with course and module information
try {
    $sql = "
        SELECT l.*, 
               m.title as module_title, m.order_sequence as module_order,
               c.title as course_title, c.id as course_id,
               COUNT(DISTINCT up.user_id) as student_count,
               COUNT(CASE WHEN up.status = 'completed' THEN 1 END) as completed_count
        FROM lessons l
        JOIN modules m ON l.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        LEFT JOIN user_progress up ON l.id = up.lesson_id
        $where_clause
        GROUP BY l.id
        ORDER BY c.title, m.order_sequence, l.order_sequence
        LIMIT $lessons_per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lessons = $stmt->fetchAll();
} catch(PDOException $e) {
    $lessons = [];
    $error_message = "Failed to load lessons: " . $e->getMessage();
}

// Get courses for filter dropdown
try {
    $stmt = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC");
    $all_courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $all_courses = [];
}

// Get modules for filter dropdown
try {
    $module_sql = "SELECT m.id, m.title, c.title as course_title FROM modules m JOIN courses c ON m.course_id = c.id ORDER BY c.title, m.order_sequence";
    $stmt = $pdo->query($module_sql);
    $all_modules = $stmt->fetchAll();
} catch(PDOException $e) {
    $all_modules = [];
}

// Get statistics
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive
        FROM lessons
    ");
    $stats = $stmt->fetch();
} catch(PDOException $e) {
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Lessons Management</h1>
                <p class="text-blue-100 text-lg">Manage course lessons and learning content</p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="lesson-add.php<?php echo $module_filter ? '?module_id=' . $module_filter : ''; ?>" 
                   class="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100">
                    + Add New Lesson
                </a>
                <?php if ($module_filter): ?>
                    <a href="modules.php" class="bg-white/20 text-white px-4 py-3 rounded-lg hover:bg-white/30">
                        ← Back to Modules
                    </a>
                <?php endif; ?>
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
            <?php echo htmlspecialchars($success_message); ?>
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

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></div>
                <div class="text-gray-600">Total Lessons</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['active']; ?></div>
                <div class="text-gray-600">Active Lessons</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['inactive']; ?></div>
                <div class="text-gray-600">Inactive Lessons</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format(array_sum(array_column($lessons, 'student_count'))); ?></div>
                <div class="text-gray-600">Total Enrollments</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="page" value="1">
        
        <div>
            <label for="course_filter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Course</label>
            <select id="course_filter" name="course_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Courses</option>
                <?php foreach ($all_courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="module_filter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Module</label>
            <select id="module_filter" name="module_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Modules</option>
                <?php foreach ($all_modules as $module): ?>
                    <option value="<?php echo $module['id']; ?>" <?php echo $module_filter == $module['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($module['course_title'] . ' - ' . $module['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Apply Filters
            </button>
            <a href="lessons.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                Clear
            </a>
        </div>

        <div class="text-sm text-gray-600">
            Showing <?php echo count($lessons); ?> of <?php echo $total_lessons; ?> lessons
        </div>
    </form>
</div>

<!-- Lessons Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (!empty($lessons)): ?>
        <form method="POST" id="bulkForm">
            <!-- Table Header with Bulk Actions -->
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-600">Select All</span>
                        </label>
                        <span class="text-sm text-gray-600" id="selectedCount">0 selected</span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <select name="bulk_action" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 text-sm" onclick="return confirmBulkAction()">
                            Apply
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" style="visibility: hidden;">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lesson</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course/Module</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($lessons as $lesson): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="selected_lessons[]" value="<?php echo $lesson['id']; ?>" 
                                           class="lesson-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-start">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($lesson['title']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <?php echo htmlspecialchars(substr($lesson['content'], 0, 100)) . (strlen($lesson['content']) > 100 ? '...' : ''); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($lesson['course_title']); ?></div>
                                        <div class="text-gray-500"><?php echo htmlspecialchars($lesson['module_title']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <div class="font-medium">Lesson <?php echo $lesson['order_sequence']; ?></div>
                                        <div class="text-xs text-gray-500">Module <?php echo $lesson['module_order']; ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $lesson['estimated_duration'] ? $lesson['estimated_duration'] . ' min' : '—'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm">
                                        <div class="text-gray-900 font-medium"><?php echo $lesson['student_count']; ?> enrolled</div>
                                        <div class="text-gray-500"><?php echo $lesson['completed_count']; ?> completed</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="lesson-edit.php?id=<?php echo $lesson['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <a href="?toggle=<?php echo $lesson['id']; ?>&page=<?php echo $current_page; ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?>" 
                                           class="text-yellow-600 hover:text-yellow-900" title="Toggle Status">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                                            </svg>
                                        </a>
                                        <?php if ($lesson['student_count'] == 0): ?>
                                            <a href="?delete=<?php echo $lesson['id']; ?>&page=<?php echo $current_page; ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?>" 
                                               class="text-red-600 hover:text-red-900" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this lesson?')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400" title="Cannot delete lesson with enrolled students">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $lessons_per_page, $total_lessons); ?> of <?php echo $total_lessons; ?> lessons
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo ($current_page - 1); ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?>" 
                               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?>" 
                               class="px-3 py-2 text-sm <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'text-gray-600 bg-white hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo ($current_page + 1); ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?>" 
                               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No lessons found</h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if ($module_filter || $course_filter): ?>
                    No lessons match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    Get started by creating your first lesson.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="lesson-add.php<?php echo $module_filter ? '?module_id=' . $module_filter : ''; ?>" 
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add New Lesson
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const lessonCheckboxes = document.querySelectorAll('.lesson-checkbox');
    const selectedCountElement = document.getElementById('selectedCount');
    
    // Select/Deselect all functionality
    selectAllCheckbox.addEventListener('change', function() {
        lessonCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Individual checkbox functionality
    lessonCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateSelectedCount();
        });
    });
    
    function updateSelectAllState() {
        const checkedBoxes = document.querySelectorAll('.lesson-checkbox:checked');
        selectAllCheckbox.checked = checkedBoxes.length === lessonCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < lessonCheckboxes.length;
    }
    
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.lesson-checkbox:checked');
        selectedCountElement.textContent = `${checkedBoxes.length} selected`;
    }
});

function confirmBulkAction() {
    const checkedBoxes = document.querySelectorAll('.lesson-checkbox:checked');
    const action = document.querySelector('select[name="bulk_action"]').value;
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one lesson.');
        return false;
    }
    
    if (!action) {
        alert('Please select an action.');
        return false;
    }
    
    let message = '';
    switch(action) {
        case 'activate':
            message = `Are you sure you want to activate ${checkedBoxes.length} lesson(s)?`;
            break;
        case 'deactivate':
            message = `Are you sure you want to deactivate ${checkedBoxes.length} lesson(s)?`;
            break;
        case 'delete':
            message = `Are you sure you want to delete ${checkedBoxes.length} lesson(s)? This action cannot be undone.`;
            break;
    }
    
    return confirm(message);
}

// Course filter change updates module filter
document.getElementById('course_filter').addEventListener('change', function() {
    const courseId = this.value;
    const moduleSelect = document.getElementById('module_filter');
    
    // Reset module selection
    moduleSelect.value = '';
    
    // Show/hide module options based on selected course
    const moduleOptions = moduleSelect.querySelectorAll('option');
    moduleOptions.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }
        
        const optionText = option.textContent;
        if (courseId === '') {
            option.style.display = 'block';
        } else {
            // This is a simple implementation - you might want to use data attributes for better filtering
            option.style.display = 'block';
        }
    });
});
</script>