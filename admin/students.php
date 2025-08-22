<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Students Management";

// Handle individual student actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                $stmt->execute([$student_id]);
                $success_message = "Student account activated successfully.";
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                $stmt->execute([$student_id]);
                $success_message = "Student account deactivated successfully.";
                break;
                
            case 'delete':
                // Check if student has progress data
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ?");
                $stmt->execute([$student_id]);
                $progress_count = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?");
                $stmt->execute([$student_id]);
                $attempts_count = $stmt->fetchColumn();
                
                if ($progress_count > 0 || $attempts_count > 0) {
                    $error_message = "Cannot delete student with learning progress or quiz attempts. Consider deactivating instead.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $success_message = "Student account deleted successfully.";
                }
                break;
                
            case 'reset_password':
                // Generate a temporary password
                $temp_password = 'temp' . rand(1000, 9999);
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $student_id]);
                
                $success_message = "Password reset successfully. Temporary password: <strong>" . $temp_password . "</strong>";
                break;
        }
    } catch (PDOException $e) {
        $error_message = "Error performing action: " . $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_students = isset($_POST['selected_students']) ? $_POST['selected_students'] : [];
    
    if (!empty($selected_students) && in_array($action, ['activate', 'deactivate', 'delete'])) {
        try {
            $placeholders = str_repeat('?,', count($selected_students) - 1) . '?';
            
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($selected_students);
                    $success_message = count($selected_students) . " student(s) activated successfully.";
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
                    $stmt->execute($selected_students);
                    $success_message = count($selected_students) . " student(s) deactivated successfully.";
                    break;
                    
                case 'delete':
                    // Check if any students have progress data
                    $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM user_progress WHERE user_id IN ($placeholders)");
                    $stmt->execute($selected_students);
                    $students_with_progress = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM quiz_attempts WHERE user_id IN ($placeholders)");
                    $stmt->execute($selected_students);
                    $students_with_attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $students_with_data = array_unique(array_merge($students_with_progress, $students_with_attempts));
                    
                    if (!empty($students_with_data)) {
                        $error_message = "Cannot delete students with learning progress or quiz attempts. Consider deactivating instead.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                        $stmt->execute($selected_students);
                        $success_message = count($selected_students) . " student(s) deleted successfully.";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($course_filter > 0) {
    $where_conditions[] = "EXISTS (SELECT 1 FROM user_progress up WHERE up.user_id = u.id AND up.course_id = ?)";
    $params[] = $course_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(u.created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total students count for pagination
try {
    $count_sql = "SELECT COUNT(*) FROM users u $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_students = $stmt->fetchColumn();
} catch(PDOException $e) {
    $total_students = 0;
}

// Pagination
$students_per_page = 20;
$total_pages = ceil($total_students / $students_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $students_per_page;

// Get students with progress information
try {
    $sql = "
        SELECT u.*, 
               COUNT(DISTINCT up_course.course_id) as enrolled_courses,
               COUNT(DISTINCT CASE WHEN up_lesson.status = 'completed' THEN up_lesson.lesson_id END) as completed_lessons,
               COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.quiz_id END) as passed_quizzes,
               COUNT(DISTINCT cert.id) as certificates_earned,
               MAX(up_activity.completed_at) as last_activity
        FROM users u
        LEFT JOIN user_progress up_course ON u.id = up_course.user_id AND up_course.lesson_id IS NULL AND up_course.module_id IS NULL
        LEFT JOIN user_progress up_lesson ON u.id = up_lesson.user_id AND up_lesson.lesson_id IS NOT NULL
        LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
        LEFT JOIN certificates cert ON u.id = cert.user_id
        LEFT JOIN user_progress up_activity ON u.id = up_activity.user_id
        $where_clause
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT $students_per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch(PDOException $e) {
    $students = [];
    $error_message = "Failed to load students: " . $e->getMessage();
}

// Get courses for filter dropdown
try {
    $stmt = $pdo->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title ASC");
    $all_courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $all_courses = [];
}

// Get statistics
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            0 as active,
            0 as inactive,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_this_month,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
        FROM users
    ");
    $stats = $stmt->fetch();
} catch(PDOException $e) {
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'new_this_month' => 0, 'new_this_week' => 0];
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Students Management</h1>
                <p class="text-indigo-100 text-lg">Manage student accounts and track their learning progress</p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="student-add.php" 
                   class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    + Add New Student
                </a>
                <a href="students-export.php" 
                   class="bg-white/20 text-white px-4 py-3 rounded-lg hover:bg-white/30 transition duration-300">
                    📊 Export Data
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

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></div>
                <div class="text-gray-600">Total Students</div>
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
                <div class="text-gray-600">Active Students</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['inactive']; ?></div>
                <div class="text-gray-600">Inactive Students</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['new_this_month']; ?></div>
                <div class="text-gray-600">New This Month</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['new_this_week']; ?></div>
                <div class="text-gray-600">New This Week</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="page" value="1">
        
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Students</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Name, email, or username..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
            <select id="course_id" name="course_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All Courses</option>
                <?php foreach ($all_courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="date_filter" class="block text-sm font-medium text-gray-700 mb-2">Registered</label>
            <select id="date_filter" name="date_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">All Time</option>
                <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-300">
                Apply Filters
            </button>
            <a href="students.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition duration-300">
                Clear
            </a>
        </div>

        <div class="text-sm text-gray-600">
            Showing <?php echo count($students); ?> of <?php echo $total_students; ?> students
        </div>
    </form>
</div>

<!-- Students Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (!empty($students)): ?>
        <form method="POST" id="bulkForm">
            <!-- Table Header with Bulk Actions -->
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
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
                        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300 text-sm" onclick="return confirmBulkAction()">
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
                                <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" style="visibility: hidden;">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($students as $student): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="selected_students[]" value="<?php echo $student['id']; ?>" 
                                           class="student-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-indigo-600">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                @<?php echo htmlspecialchars(isset($student['username']) ? $student['username'] : 'user' . $student['id']); ?>
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                ID: <?php echo $student['id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email']); ?></div>
                                    <?php if (isset($student['phone']) && $student['phone']): ?>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['phone']); ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-400">
                                        Joined: <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Courses:</span>
                                            <span class="font-medium"><?php echo $student['enrolled_courses']; ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Lessons:</span>
                                            <span class="font-medium"><?php echo $student['completed_lessons']; ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Quizzes:</span>
                                            <span class="font-medium"><?php echo $student['passed_quizzes']; ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Certificates:</span>
                                            <span class="font-medium text-yellow-600"><?php echo $student['certificates_earned']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($student['last_activity']): ?>
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            $last_activity = strtotime($student['last_activity']);
                                            $days_ago = floor((time() - $last_activity) / (24 * 60 * 60));
                                            if ($days_ago == 0) {
                                                echo "Today";
                                            } elseif ($days_ago == 1) {
                                                echo "1 day ago";
                                            } elseif ($days_ago < 7) {
                                                echo "$days_ago days ago";
                                            } else {
                                                echo date('M j, Y', $last_activity);
                                            }
                                            ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('g:i A', $last_activity); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">No activity</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="student-view.php?id=<?php echo $student['id']; ?>" 
                                           class="text-indigo-600 hover:text-indigo-900" title="View Details">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <a href="student-edit.php?id=<?php echo $student['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Edit Student">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <a href="?action=reset_password&id=<?php echo $student['id']; ?>" 
                                           class="text-orange-600 hover:text-orange-900" title="Reset Password"
                                           onclick="return confirm('Are you sure you want to reset this student\'s password?')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                            </svg>
                                        </a>
                                        <?php if ($student['enrolled_courses'] == 0 && $student['completed_lessons'] == 0 && $student['passed_quizzes'] == 0): ?>
                                            <a href="?action=delete&id=<?php echo $student['id']; ?>" 
                                               class="text-red-600 hover:text-red-900" title="Delete Student"
                                               onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400" title="Cannot delete student with learning data">
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
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $students_per_page, $total_students); ?> of <?php echo $total_students; ?> students
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo ($current_page - 1); ?><?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }), '', '&'); ?>" 
                               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }), '', '&'); ?>" 
                               class="px-3 py-2 text-sm <?php echo $i == $current_page ? 'bg-indigo-600 text-white' : 'text-gray-600 bg-white hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo ($current_page + 1); ?><?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }), '', '&'); ?>" 
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No students found</h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if ($search || $course_filter || $date_filter): ?>
                    No students match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    No students have been registered yet.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="student-add.php" 
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add New Student
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    const selectedCountElement = document.getElementById('selectedCount');
    
    // Select/Deselect all functionality
    selectAllCheckbox.addEventListener('change', function() {
        studentCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Individual checkbox functionality
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateSelectedCount();
        });
    });
    
    function updateSelectAllState() {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        selectAllCheckbox.checked = checkedBoxes.length === studentCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < studentCheckboxes.length;
    }
    
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        selectedCountElement.textContent = `${checkedBoxes.length} selected`;
    }
    
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

function confirmBulkAction() {
    const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
    const action = document.querySelector('select[name="bulk_action"]').value;
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one student.');
        return false;
    }
    
    if (!action) {
        alert('Please select an action.');
        return false;
    }
    
    let message = '';
    switch(action) {
        case 'activate':
            message = `Are you sure you want to activate ${checkedBoxes.length} student(s)?`;
            break;
        case 'deactivate':
            message = `Are you sure you want to deactivate ${checkedBoxes.length} student(s)?`;
            break;
        case 'delete':
            message = `Are you sure you want to delete ${checkedBoxes.length} student(s)? This action cannot be undone.`;
            break;
    }
    
    return confirm(message);
}
</script>
