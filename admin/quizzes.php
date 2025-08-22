<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Quizzes Management";

// Get module filter if provided
$module_filter = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$course_filter = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$quiz_type_filter = isset($_GET['quiz_type']) ? $_GET['quiz_type'] : '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_quizzes = isset($_POST['selected_quizzes']) ? $_POST['selected_quizzes'] : [];
    
    if (!empty($selected_quizzes) && in_array($action, ['activate', 'deactivate', 'delete'])) {
        try {
            $placeholders = str_repeat('?,', count($selected_quizzes) - 1) . '?';
            
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE quizzes SET is_active = 1 WHERE id IN ($placeholders)");
                    $stmt->execute($selected_quizzes);
                    $success_message = count($selected_quizzes) . " quiz(es) activated successfully.";
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE quizzes SET is_active = 0 WHERE id IN ($placeholders)");
                    $stmt->execute($selected_quizzes);
                    $success_message = count($selected_quizzes) . " quiz(es) deactivated successfully.";
                    break;
                    
                case 'delete':
                    // Check if any quizzes have attempts
                    $stmt = $pdo->prepare("SELECT DISTINCT quiz_id FROM quiz_attempts WHERE quiz_id IN ($placeholders)");
                    $stmt->execute($selected_quizzes);
                    $quizzes_with_attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($quizzes_with_attempts)) {
                        $error_message = "Cannot delete quizzes that have student attempts. Consider deactivating instead.";
                    } else {
                        // Delete questions and options first (cascade)
                        $stmt = $pdo->prepare("DELETE qo FROM question_options qo JOIN questions q ON qo.question_id = q.id WHERE q.quiz_id IN ($placeholders)");
                        $stmt->execute($selected_quizzes);
                        
                        $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id IN ($placeholders)");
                        $stmt->execute($selected_quizzes);
                        
                        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id IN ($placeholders)");
                        $stmt->execute($selected_quizzes);
                        
                        $success_message = count($selected_quizzes) . " quiz(es) deleted successfully.";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

// Handle individual quiz deletion
if (isset($_GET['delete']) && $_GET['delete']) {
    $quiz_id = intval($_GET['delete']);
    
    try {
        // Check if quiz has attempts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        $attempt_count = $stmt->fetchColumn();
        
        if ($attempt_count > 0) {
            $error_message = "Cannot delete quiz with student attempts. Consider deactivating instead.";
        } else {
            // Delete questions and options first
            $stmt = $pdo->prepare("DELETE qo FROM question_options qo JOIN questions q ON qo.question_id = q.id WHERE q.quiz_id = ?");
            $stmt->execute([$quiz_id]);
            
            $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
            $stmt->execute([$quiz_id]);
            
            $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
            $stmt->execute([$quiz_id]);
            
            $success_message = "Quiz deleted successfully.";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting quiz: " . $e->getMessage();
    }
}

// Handle quiz status toggle
if (isset($_GET['toggle']) && $_GET['toggle']) {
    $quiz_id = intval($_GET['toggle']);
    
    try {
        $stmt = $pdo->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$quiz_id]);
        $success_message = "Quiz status updated successfully.";
    } catch (PDOException $e) {
        $error_message = "Error updating quiz status: " . $e->getMessage();
    }
}

// Build query with filters
$where_conditions = [];
$params = [];

if ($module_filter > 0) {
    $where_conditions[] = "q.module_id = ?";
    $params[] = $module_filter;
}

if ($course_filter > 0) {
    $where_conditions[] = "m.course_id = ?";
    $params[] = $course_filter;
}

if ($quiz_type_filter) {
    $where_conditions[] = "q.quiz_type = ?";
    $params[] = $quiz_type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total quizzes count for pagination
try {
    $count_sql = "
        SELECT COUNT(*) FROM quizzes q
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_quizzes = $stmt->fetchColumn();
} catch(PDOException $e) {
    $total_quizzes = 0;
}

// Pagination
$quizzes_per_page = 15;
$total_pages = ceil($total_quizzes / $quizzes_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $quizzes_per_page;

// Get quizzes with course and module information - FIXED SQL
try {
    $sql = "
        SELECT q.*, 
               m.title as module_title, m.order_sequence as module_order,
               c.title as course_title, c.id as course_id,
               COUNT(DISTINCT questions.id) as question_count,
               COUNT(DISTINCT qa.user_id) as student_count,
               COUNT(CASE WHEN qa.passed = 1 THEN 1 END) as passed_count,
               AVG(qa.score) as avg_score
        FROM quizzes q
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        LEFT JOIN questions ON q.id = questions.quiz_id
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.completed_at IS NOT NULL
        $where_clause
        GROUP BY q.id
        ORDER BY c.title, m.order_sequence, q.quiz_type DESC, q.id DESC
        LIMIT $quizzes_per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $quizzes = $stmt->fetchAll();
} catch(PDOException $e) {
    $quizzes = [];
    $error_message = "Failed to load quizzes: " . $e->getMessage();
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
            COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive,
            COUNT(CASE WHEN quiz_type = 'module' THEN 1 END) as module_quizzes,
            COUNT(CASE WHEN quiz_type = 'final' THEN 1 END) as final_quizzes
        FROM quizzes
    ");
    $stats = $stmt->fetch();
} catch(PDOException $e) {
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'module_quizzes' => 0, 'final_quizzes' => 0];
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Quizzes Management</h1>
                <p class="text-purple-100 text-lg">Manage quizzes, questions, and student assessments</p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="quiz-add.php<?php echo $module_filter ? '?module_id=' . $module_filter : ''; ?>" 
                   class="bg-white text-purple-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    + Add New Quiz
                </a>
                <?php if ($module_filter): ?>
                    <a href="modules.php" class="bg-white/20 text-white px-4 py-3 rounded-lg hover:bg-white/30 transition duration-300">
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
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></div>
                <div class="text-gray-600">Total Quizzes</div>
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
                <div class="text-gray-600">Active Quizzes</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['module_quizzes']; ?></div>
                <div class="text-gray-600">Module Quizzes</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['final_quizzes']; ?></div>
                <div class="text-gray-600">Final Exams</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format(array_sum(array_column($quizzes, 'student_count'))); ?></div>
                <div class="text-gray-600">Total Attempts</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="page" value="1">
        
        <div>
            <label for="course_filter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Course</label>
            <select id="course_filter" name="course_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
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
            <select id="module_filter" name="module_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <option value="">All Modules</option>
                <?php foreach ($all_modules as $module): ?>
                    <option value="<?php echo $module['id']; ?>" <?php echo $module_filter == $module['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($module['course_title'] . ' - ' . $module['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="quiz_type_filter" class="block text-sm font-medium text-gray-700 mb-2">Quiz Type</label>
            <select id="quiz_type_filter" name="quiz_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <option value="">All Types</option>
                <option value="module" <?php echo $quiz_type_filter == 'module' ? 'selected' : ''; ?>>Module Quiz</option>
                <option value="final" <?php echo $quiz_type_filter == 'final' ? 'selected' : ''; ?>>Final Exam</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition duration-300">
                Apply Filters
            </button>
            <a href="quizzes.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition duration-300">
                Clear
            </a>
        </div>

        <div class="text-sm text-gray-600">
            Showing <?php echo count($quizzes); ?> of <?php echo $total_quizzes; ?> quizzes
        </div>
    </form>
</div>

<!-- Quizzes Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (!empty($quizzes)): ?>
        <form method="POST" id="bulkForm">
            <!-- Table Header with Bulk Actions -->
            <div class="p-6 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
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
                                <input type="checkbox" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" style="visibility: hidden;">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course/Module</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statistics</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($quizzes as $quiz): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="selected_quizzes[]" value="<?php echo $quiz['id']; ?>" 
                                           class="quiz-checkbox rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-start">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($quiz['title']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <?php 
                                                $description = isset($quiz['description']) ? $quiz['description'] : '';
                                                echo htmlspecialchars(substr($description, 0, 100)) . (strlen($description) > 100 ? '...' : ''); 
                                                ?>
                                            </div>
                                            <div class="text-xs text-gray-400 mt-1">
                                                Pass: <?php echo $quiz['pass_threshold']; ?>%
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($quiz['course_title']); ?></div>
                                        <div class="text-gray-500"><?php echo htmlspecialchars($quiz['module_title']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($quiz['quiz_type'] == 'final'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded-full">Final Exam</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800 rounded-full">Module Quiz</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <div class="font-medium"><?php echo $quiz['question_count']; ?> questions</div>
                                        <?php if ($quiz['question_count'] > 0): ?>
                                            <a href="questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="text-xs text-purple-600 hover:text-purple-800">
                                                Manage Questions →
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-red-600">No questions</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm">
                                        <div class="text-gray-900 font-medium"><?php echo $quiz['student_count']; ?> attempts</div>
                                        <div class="text-gray-500"><?php echo $quiz['passed_count']; ?> passed</div>
                                        <?php if ($quiz['avg_score'] !== null): ?>
                                            <div class="text-xs text-gray-400">Avg: <?php echo round($quiz['avg_score'], 1); ?>%</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($quiz['is_active']): ?>
                                        <span class="px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Active</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 rounded-full">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="quiz-edit.php?id=<?php echo $quiz['id']; ?>" 
                                           class="text-purple-600 hover:text-purple-900" title="Edit Quiz">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <a href="questions.php?quiz_id=<?php echo $quiz['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Manage Questions">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </a>
                                        <a href="quiz-results.php?quiz_id=<?php echo $quiz['id']; ?>" 
                                           class="text-green-600 hover:text-green-900" title="View Results">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                            </svg>
                                        </a>
                                        <a href="?toggle=<?php echo $quiz['id']; ?>&page=<?php echo $current_page; ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?><?php echo $quiz_type_filter ? '&quiz_type=' . $quiz_type_filter : ''; ?>" 
                                           class="text-yellow-600 hover:text-yellow-900" title="Toggle Status">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                                            </svg>
                                        </a>
                                        <?php if ($quiz['student_count'] == 0): ?>
                                            <a href="?delete=<?php echo $quiz['id']; ?>&page=<?php echo $current_page; ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?><?php echo $quiz_type_filter ? '&quiz_type=' . $quiz_type_filter : ''; ?>" 
                                               class="text-red-600 hover:text-red-900" title="Delete Quiz"
                                               onclick="return confirm('Are you sure you want to delete this quiz? This will also delete all questions and options.')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400" title="Cannot delete quiz with student attempts">
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
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $quizzes_per_page, $total_quizzes); ?> of <?php echo $total_quizzes; ?> quizzes
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo ($current_page - 1); ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?><?php echo $quiz_type_filter ? '&quiz_type=' . $quiz_type_filter : ''; ?>" 
                               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?><?php echo $quiz_type_filter ? '&quiz_type=' . $quiz_type_filter : ''; ?>" 
                               class="px-3 py-2 text-sm <?php echo $i == $current_page ? 'bg-purple-600 text-white' : 'text-gray-600 bg-white hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo ($current_page + 1); ?><?php echo $module_filter ? '&module_id=' . $module_filter : ''; ?><?php echo $course_filter ? '&course_id=' . $course_filter : ''; ?><?php echo $quiz_type_filter ? '&quiz_type=' . $quiz_type_filter : ''; ?>" 
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No quizzes found</h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if ($module_filter || $course_filter || $quiz_type_filter): ?>
                    No quizzes match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    Get started by creating your first quiz.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="quiz-add.php<?php echo $module_filter ? '?module_id=' . $module_filter : ''; ?>" 
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add New Quiz
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const quizCheckboxes = document.querySelectorAll('.quiz-checkbox');
    const selectedCountElement = document.getElementById('selectedCount');
    
    // Select/Deselect all functionality
    selectAllCheckbox.addEventListener('change', function() {
        quizCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Individual checkbox functionality
    quizCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateSelectedCount();
        });
    });
    
    function updateSelectAllState() {
        const checkedBoxes = document.querySelectorAll('.quiz-checkbox:checked');
        selectAllCheckbox.checked = checkedBoxes.length === quizCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < quizCheckboxes.length;
    }
    
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.quiz-checkbox:checked');
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
    const checkedBoxes = document.querySelectorAll('.quiz-checkbox:checked');
    const action = document.querySelector('select[name="bulk_action"]').value;
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one quiz.');
        return false;
    }
    
    if (!action) {
        alert('Please select an action.');
        return false;
    }
    
    let message = '';
    switch(action) {
        case 'activate':
            message = `Are you sure you want to activate ${checkedBoxes.length} quiz(es)?`;
            break;
        case 'deactivate':
            message = `Are you sure you want to deactivate ${checkedBoxes.length} quiz(es)?`;
            break;
        case 'delete':
            message = `Are you sure you want to delete ${checkedBoxes.length} quiz(es)? This will also delete all questions and options. This action cannot be undone.`;
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
        
        // This is a simple implementation - you might want to use data attributes for better filtering
        option.style.display = 'block';
    });
});
</script>
