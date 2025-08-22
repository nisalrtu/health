<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Questions Management";

// Get quiz filter if provided
$quiz_filter = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Handle individual question deletion
if (isset($_GET['delete']) && $_GET['delete']) {
    $question_id = intval($_GET['delete']);
    
    try {
        // Check if question has user answers
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_answers WHERE question_id = ?");
        $stmt->execute([$question_id]);
        $answer_count = $stmt->fetchColumn();
        
        if ($answer_count > 0) {
            $error_message = "Cannot delete question with student answers. Consider editing instead.";
        } else {
            // Delete question options first
            $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
            $stmt->execute([$question_id]);
            
            // Delete the question
            $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$question_id]);
            
            $success_message = "Question deleted successfully.";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting question: " . $e->getMessage();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_questions = isset($_POST['selected_questions']) ? $_POST['selected_questions'] : [];
    
    if (!empty($selected_questions) && in_array($action, ['delete', 'reorder'])) {
        try {
            $placeholders = str_repeat('?,', count($selected_questions) - 1) . '?';
            
            switch ($action) {
                case 'delete':
                    // Check if any questions have answers
                    $stmt = $pdo->prepare("SELECT DISTINCT question_id FROM user_answers WHERE question_id IN ($placeholders)");
                    $stmt->execute($selected_questions);
                    $questions_with_answers = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!empty($questions_with_answers)) {
                        $error_message = "Cannot delete questions that have student answers. Consider editing instead.";
                    } else {
                        // Delete question options first
                        $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id IN ($placeholders)");
                        $stmt->execute($selected_questions);
                        
                        // Delete questions
                        $stmt = $pdo->prepare("DELETE FROM questions WHERE id IN ($placeholders)");
                        $stmt->execute($selected_questions);
                        
                        $success_message = count($selected_questions) . " question(s) deleted successfully.";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

// Handle order update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order'])) {
    $question_orders = $_POST['question_order'] ?? [];
    
    try {
        foreach ($question_orders as $question_id => $order) {
            $stmt = $pdo->prepare("UPDATE questions SET order_sequence = ? WHERE id = ?");
            $stmt->execute([intval($order), intval($question_id)]);
        }
        $success_message = "Question order updated successfully.";
    } catch (PDOException $e) {
        $error_message = "Error updating question order: " . $e->getMessage();
    }
}

// Get quiz information if filter is set
$quiz_info = null;
if ($quiz_filter > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, m.title as module_title, c.title as course_title 
            FROM quizzes q
            JOIN modules m ON q.module_id = m.id
            JOIN courses c ON m.course_id = c.id
            WHERE q.id = ?
        ");
        $stmt->execute([$quiz_filter]);
        $quiz_info = $stmt->fetch();
    } catch (PDOException $e) {
        $quiz_info = null;
    }
}

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if ($quiz_filter > 0) {
    $where_conditions[] = "q.quiz_id = ?";
    $params[] = $quiz_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total questions count for pagination
try {
    $count_sql = "
        SELECT COUNT(*) FROM questions q
        JOIN quizzes quiz ON q.quiz_id = quiz.id
        JOIN modules m ON quiz.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_questions = $stmt->fetchColumn();
} catch(PDOException $e) {
    $total_questions = 0;
}

// Pagination
$questions_per_page = 20;
$total_pages = ceil($total_questions / $questions_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $questions_per_page;

// Get questions with quiz, module, and course information
try {
    $sql = "
        SELECT q.*, 
               quiz.title as quiz_title, quiz.quiz_type,
               m.title as module_title,
               c.title as course_title,
               COUNT(DISTINCT qo.id) as option_count,
               COUNT(DISTINCT ua.id) as answer_count
        FROM questions q
        JOIN quizzes quiz ON q.quiz_id = quiz.id
        JOIN modules m ON quiz.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        LEFT JOIN question_options qo ON q.id = qo.question_id
        LEFT JOIN user_answers ua ON q.id = ua.question_id
        $where_clause
        GROUP BY q.id
        ORDER BY c.title, m.title, quiz.title, q.order_sequence ASC
        LIMIT $questions_per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll();
} catch(PDOException $e) {
    $questions = [];
    $error_message = "Failed to load questions: " . $e->getMessage();
}

// Get all quizzes for filter dropdown
try {
    $stmt = $pdo->query("
        SELECT q.id, q.title, m.title as module_title, c.title as course_title 
        FROM quizzes q
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        ORDER BY c.title, m.title, q.title
    ");
    $all_quizzes = $stmt->fetchAll();
} catch(PDOException $e) {
    $all_quizzes = [];
}

// Get statistics
try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as multiple_choice,
            COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as true_false,
            COUNT(CASE WHEN question_type = 'short_answer' THEN 1 END) as short_answer,
            AVG(points) as avg_points
        FROM questions q
        " . ($quiz_filter > 0 ? "WHERE q.quiz_id = ?" : "");
    
    $stmt = $pdo->prepare($stats_sql);
    if ($quiz_filter > 0) {
        $stmt->execute([$quiz_filter]);
    } else {
        $stmt->execute();
    }
    $stats = $stmt->fetch();
} catch(PDOException $e) {
    $stats = ['total' => 0, 'multiple_choice' => 0, 'true_false' => 0, 'short_answer' => 0, 'avg_points' => 0];
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Questions Management</h1>
                <p class="text-blue-100 text-lg">
                    <?php if ($quiz_info): ?>
                        Managing questions for: <strong><?php echo htmlspecialchars($quiz_info['title']); ?></strong>
                    <?php else: ?>
                        Manage quiz questions and answer options
                    <?php endif; ?>
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="question-add.php<?php echo $quiz_filter ? '?quiz_id=' . $quiz_filter : ''; ?>" 
                   class="bg-white text-blue-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    + Add New Question
                </a>
                <?php if ($quiz_filter): ?>
                    <a href="quizzes.php" class="bg-white/20 text-white px-4 py-3 rounded-lg hover:bg-white/30 transition duration-300">
                        ← Back to Quizzes
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
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total']; ?></div>
                <div class="text-gray-600">Total Questions</div>
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
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['multiple_choice']; ?></div>
                <div class="text-gray-600">Multiple Choice</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['true_false']; ?></div>
                <div class="text-gray-600">True/False</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['short_answer']; ?></div>
                <div class="text-gray-600">Short Answer</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo round($stats['avg_points'], 1); ?></div>
                <div class="text-gray-600">Avg Points</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<?php if (!$quiz_filter): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <input type="hidden" name="page" value="1">
        
        <div>
            <label for="quiz_filter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Quiz</label>
            <select id="quiz_filter" name="quiz_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Quizzes</option>
                <?php foreach ($all_quizzes as $quiz): ?>
                    <option value="<?php echo $quiz['id']; ?>" <?php echo $quiz_filter == $quiz['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($quiz['course_title'] . ' - ' . $quiz['module_title'] . ' - ' . $quiz['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-300">
                Apply Filter
            </button>
            <a href="questions.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition duration-300">
                Clear
            </a>
        </div>

        <div class="text-sm text-gray-600">
            Showing <?php echo count($questions); ?> of <?php echo $total_questions; ?> questions
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Questions Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (!empty($questions)): ?>
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
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300 text-sm" onclick="return confirmBulkAction()">
                            Apply
                        </button>
                        <?php if ($quiz_filter): ?>
                            <button type="submit" name="update_order" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-300 text-sm">
                                Update Order
                            </button>
                        <?php endif; ?>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question</th>
                            <?php if (!$quiz_filter): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz/Course</th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Options</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Answers</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($questions as $question): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="selected_questions[]" value="<?php echo $question['id']; ?>" 
                                           class="question-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-start">
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars(substr($question['question_text'], 0, 100)) . (strlen($question['question_text']) > 100 ? '...' : ''); ?>
                                            </div>
                                            <?php if (strlen($question['question_text']) > 100): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <span class="cursor-pointer text-blue-600 hover:text-blue-800" onclick="toggleFullText(<?php echo $question['id']; ?>)">
                                                        Show full question
                                                    </span>
                                                </div>
                                                <div id="full-text-<?php echo $question['id']; ?>" class="hidden text-sm text-gray-700 mt-2 p-3 bg-gray-50 rounded">
                                                    <?php echo htmlspecialchars($question['question_text']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <?php if (!$quiz_filter): ?>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($question['quiz_title']); ?></div>
                                        <div class="text-gray-500"><?php echo htmlspecialchars($question['course_title']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($question['module_title']); ?></div>
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $type_colors = [
                                        'multiple_choice' => 'bg-blue-100 text-blue-800',
                                        'true_false' => 'bg-yellow-100 text-yellow-800',
                                        'short_answer' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $type_labels = [
                                        'multiple_choice' => 'Multiple Choice',
                                        'true_false' => 'True/False',
                                        'short_answer' => 'Short Answer'
                                    ];
                                    $color_class = $type_colors[$question['question_type']] ?? 'bg-gray-100 text-gray-800';
                                    $type_label = $type_labels[$question['question_type']] ?? ucfirst(str_replace('_', ' ', $question['question_type']));
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold <?php echo $color_class; ?> rounded-full">
                                        <?php echo $type_label; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($quiz_filter): ?>
                                        <input type="number" name="question_order[<?php echo $question['id']; ?>]" 
                                               value="<?php echo $question['order_sequence']; ?>" 
                                               min="1" max="100"
                                               class="w-16 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    <?php else: ?>
                                        <span class="text-sm text-gray-900"><?php echo $question['order_sequence']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900"><?php echo $question['points']; ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <div class="font-medium"><?php echo $question['option_count']; ?> options</div>
                                        <?php if ($question['option_count'] > 0): ?>
                                            <a href="question-options.php?question_id=<?php echo $question['id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                                Manage Options →
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-red-600">No options</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php if ($question['answer_count'] > 0): ?>
                                            <div class="font-medium"><?php echo $question['answer_count']; ?> answers</div>
                                            <span class="text-xs text-gray-500">Student responses</span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">No answers yet</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <a href="question-edit.php?id=<?php echo $question['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Edit Question">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <a href="question-options.php?question_id=<?php echo $question['id']; ?>" 
                                           class="text-green-600 hover:text-green-900" title="Manage Options">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                            </svg>
                                        </a>
                                        <a href="question-duplicate.php?id=<?php echo $question['id']; ?>" 
                                           class="text-purple-600 hover:text-purple-900" title="Duplicate Question">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </a>
                                        <?php if ($question['answer_count'] == 0): ?>
                                            <a href="?delete=<?php echo $question['id']; ?>&page=<?php echo $current_page; ?><?php echo $quiz_filter ? '&quiz_id=' . $quiz_filter : ''; ?>" 
                                               class="text-red-600 hover:text-red-900" title="Delete Question"
                                               onclick="return confirm('Are you sure you want to delete this question? This will also delete all its options.')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400" title="Cannot delete question with student answers">
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
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $questions_per_page, $total_questions); ?> of <?php echo $total_questions; ?> questions
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo ($current_page - 1); ?><?php echo $quiz_filter ? '&quiz_id=' . $quiz_filter : ''; ?>" 
                               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $quiz_filter ? '&quiz_id=' . $quiz_filter : ''; ?>" 
                               class="px-3 py-2 text-sm <?php echo $i == $current_page ? 'bg-blue-600 text-white' : 'text-gray-600 bg-white hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo ($current_page + 1); ?><?php echo $quiz_filter ? '&quiz_id=' . $quiz_filter : ''; ?>" 
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No questions found</h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if ($quiz_filter): ?>
                    No questions exist for this quiz yet.
                <?php else: ?>
                    No questions match your current filters.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="question-add.php<?php echo $quiz_filter ? '?quiz_id=' . $quiz_filter : ''; ?>" 
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add New Question
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Help -->
<div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-blue-900 mb-3">💡 Question Management Tips</h3>
    <div class="grid md:grid-cols-2 gap-4 text-sm text-blue-800">
        <div>
            <h4 class="font-semibold mb-2">Question Types</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li><strong>Multiple Choice:</strong> Best for testing specific knowledge</li>
                <li><strong>True/False:</strong> Quick yes/no evaluations</li>
                <li><strong>Short Answer:</strong> Open-ended responses</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-2">Best Practices</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>Write clear, concise questions</li>
                <li>Order questions logically</li>
                <li>Set appropriate point values</li>
                <li>Test questions before publishing</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const questionCheckboxes = document.querySelectorAll('.question-checkbox');
    const selectedCountElement = document.getElementById('selectedCount');
    
    // Select/Deselect all functionality
    selectAllCheckbox.addEventListener('change', function() {
        questionCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
    
    // Individual checkbox functionality
    questionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateSelectedCount();
        });
    });
    
    function updateSelectAllState() {
        const checkedBoxes = document.querySelectorAll('.question-checkbox:checked');
        selectAllCheckbox.checked = checkedBoxes.length === questionCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < questionCheckboxes.length;
    }
    
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.question-checkbox:checked');
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
    const checkedBoxes = document.querySelectorAll('.question-checkbox:checked');
    const action = document.querySelector('select[name="bulk_action"]').value;
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one question.');
        return false;
    }
    
    if (!action) {
        alert('Please select an action.');
        return false;
    }
    
    let message = '';
    switch(action) {
        case 'delete':
            message = `Are you sure you want to delete ${checkedBoxes.length} question(s)? This will also delete all their options. This action cannot be undone.`;
            break;
    }
    
    return confirm(message);
}

function toggleFullText(questionId) {
    const element = document.getElementById('full-text-' + questionId);
    const button = element.previousElementSibling.querySelector('span');
    
    if (element.classList.contains('hidden')) {
        element.classList.remove('hidden');
        button.textContent = 'Hide full question';
    } else {
        element.classList.add('hidden');
        button.textContent = 'Show full question';
    }
}
</script>