<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Manage Question Options";

// Get question ID from URL
$question_id = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;

if ($question_id <= 0) {
    $_SESSION['error_message'] = "Invalid question ID.";
    header('Location: questions.php');
    exit();
}

// Get question information
try {
    $stmt = $pdo->prepare("
        SELECT q.*, 
               quiz.title as quiz_title, quiz.quiz_type, quiz.id as quiz_id,
               m.title as module_title,
               c.title as course_title
        FROM questions q
        JOIN quizzes quiz ON q.quiz_id = quiz.id
        JOIN modules m ON quiz.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        $_SESSION['error_message'] = "Question not found.";
        header('Location: questions.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error loading question: " . $e->getMessage();
    header('Location: questions.php');
    exit();
}

// Handle form submission for adding/editing options
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_option'])) {
            // Add new option
            $option_text = trim($_POST['new_option_text']);
            $is_correct = isset($_POST['new_is_correct']) ? 1 : 0;
            
            if (empty($option_text)) {
                $error_message = "Option text is required.";
            } else {
                // Get next order sequence
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_sequence), 0) + 1 as next_order FROM question_options WHERE question_id = ?");
                $stmt->execute([$question_id]);
                $order_sequence = $stmt->fetch()['next_order'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO question_options (question_id, option_text, is_correct, order_sequence) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$question_id, $option_text, $is_correct, $order_sequence]);
                
                $success_message = "Option added successfully.";
            }
            
        } elseif (isset($_POST['update_options'])) {
            // Update existing options
            $option_texts = $_POST['option_text'] ?? [];
            $correct_options = $_POST['correct_options'] ?? [];
            $option_orders = $_POST['option_order'] ?? [];
            
            $pdo->beginTransaction();
            
            try {
                foreach ($option_texts as $option_id => $option_text) {
                    $option_text = trim($option_text);
                    if (!empty($option_text)) {
                        $is_correct = in_array($option_id, $correct_options) ? 1 : 0;
                        $order_sequence = isset($option_orders[$option_id]) ? intval($option_orders[$option_id]) : 1;
                        
                        $stmt = $pdo->prepare("
                            UPDATE question_options 
                            SET option_text = ?, is_correct = ?, order_sequence = ? 
                            WHERE id = ? AND question_id = ?
                        ");
                        $stmt->execute([$option_text, $is_correct, $order_sequence, $option_id, $question_id]);
                    }
                }
                
                $pdo->commit();
                $success_message = "Options updated successfully.";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle option deletion
if (isset($_GET['delete_option']) && $_GET['delete_option']) {
    $option_id = intval($_GET['delete_option']);
    
    try {
        // Check if this option has user answers
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_answers WHERE selected_option_id = ?");
        $stmt->execute([$option_id]);
        $answer_count = $stmt->fetchColumn();
        
        if ($answer_count > 0) {
            $error_message = "Cannot delete option that has student answers.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM question_options WHERE id = ? AND question_id = ?");
            $stmt->execute([$option_id, $question_id]);
            $success_message = "Option deleted successfully.";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting option: " . $e->getMessage();
    }
}

// Get question options
try {
    $stmt = $pdo->prepare("
        SELECT qo.*, COUNT(ua.id) as answer_count
        FROM question_options qo
        LEFT JOIN user_answers ua ON qo.id = ua.selected_option_id
        WHERE qo.question_id = ?
        GROUP BY qo.id
        ORDER BY qo.order_sequence ASC
    ");
    $stmt->execute([$question_id]);
    $options = $stmt->fetchAll();
} catch (PDOException $e) {
    $options = [];
    $error_message = "Failed to load options: " . $e->getMessage();
}

// Get statistics
$stats = [
    'total_options' => count($options),
    'correct_options' => count(array_filter($options, function($opt) { return $opt['is_correct']; })),
    'total_answers' => array_sum(array_column($options, 'answer_count'))
];

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Manage Question Options</h1>
                <p class="text-emerald-100 text-lg">Configure answer options for your question</p>
                <div class="mt-3 space-y-1">
                    <div class="text-sm text-emerald-200">
                        <strong>Quiz:</strong> <?php echo htmlspecialchars($question['quiz_title']); ?>
                        <span class="mx-2">•</span>
                        <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
                    </div>
                    <div class="text-sm text-emerald-200">
                        <strong>Course:</strong> <?php echo htmlspecialchars($question['course_title']); ?>
                        <span class="mx-2">•</span>
                        <strong>Module:</strong> <?php echo htmlspecialchars($question['module_title']); ?>
                    </div>
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="questions.php?quiz_id=<?php echo $question['quiz_id']; ?>" 
                   class="bg-white text-emerald-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    ← Back to Questions
                </a>
                <a href="question-edit.php?id=<?php echo $question_id; ?>" 
                   class="bg-white/20 text-white px-4 py-3 rounded-lg hover:bg-white/30 transition duration-300">
                    Edit Question
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

<!-- Question Display -->
<div class="mb-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Question</h2>
    <div class="bg-gray-50 rounded-lg p-4">
        <p class="text-gray-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
        <div class="mt-3 flex items-center gap-4 text-sm text-gray-600">
            <span class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                <?php echo $question['points']; ?> point<?php echo $question['points'] > 1 ? 's' : ''; ?>
            </span>
            <span class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
                <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
            </span>
            <span class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Question #<?php echo $question['order_sequence']; ?>
            </span>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_options']; ?></div>
                <div class="text-gray-600">Total Options</div>
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
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['correct_options']; ?></div>
                <div class="text-gray-600">Correct Answers</div>
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
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_answers']; ?></div>
                <div class="text-gray-600">Student Answers</div>
            </div>
        </div>
    </div>
</div>

<!-- Current Options -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Current Options</h2>
        <p class="text-gray-600 mt-1">Manage the answer options for this question</p>
    </div>
    
    <?php if (!empty($options)): ?>
        <form method="POST" id="optionsForm">
            <div class="p-6">
                <div class="space-y-4">
                    <?php foreach ($options as $index => $option): ?>
                        <div class="flex items-start gap-4 p-4 border border-gray-200 rounded-lg <?php echo $option['is_correct'] ? 'bg-green-50 border-green-200' : ''; ?>">
                            <div class="flex items-center pt-1">
                                <input type="checkbox" 
                                       name="correct_options[]" 
                                       value="<?php echo $option['id']; ?>"
                                       <?php echo $option['is_correct'] ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                            </div>
                            
                            <div class="flex-1">
                                <textarea name="option_text[<?php echo $option['id']; ?>]" 
                                          rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                          placeholder="Enter option text..."><?php echo htmlspecialchars($option['option_text']); ?></textarea>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <input type="number" 
                                       name="option_order[<?php echo $option['id']; ?>]"
                                       value="<?php echo $option['order_sequence']; ?>"
                                       min="1" max="20"
                                       class="w-16 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-emerald-500">
                                
                                <div class="text-sm text-gray-500 font-medium">
                                    <?php echo chr(65 + $index); ?>
                                </div>
                                
                                <?php if ($option['answer_count'] == 0): ?>
                                    <a href="?question_id=<?php echo $question_id; ?>&delete_option=<?php echo $option['id']; ?>" 
                                       class="text-red-600 hover:text-red-900 p-1" 
                                       title="Delete Option"
                                       onclick="return confirm('Are you sure you want to delete this option?')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400 p-1" title="Cannot delete option with student answers">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($option['answer_count'] > 0): ?>
                                <div class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                    <?php echo $option['answer_count']; ?> answer<?php echo $option['answer_count'] > 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 flex justify-between items-center pt-4 border-t border-gray-200">
                    <div class="text-sm text-gray-600">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                            </svg>
                            Check the box next to correct answers
                        </span>
                    </div>
                    
                    <button type="submit" name="update_options" 
                            class="bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700 transition duration-300">
                        Update Options
                    </button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No options found</h3>
            <p class="mt-1 text-sm text-gray-500">This question doesn't have any answer options yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add New Option -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Add New Option</h2>
        <p class="text-gray-600 mt-1">Add another answer option to this question</p>
    </div>
    
    <form method="POST" class="p-6">
        <div class="flex items-start gap-4">
            <div class="flex items-center pt-3">
                <input type="checkbox" 
                       name="new_is_correct" 
                       class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
            </div>
            
            <div class="flex-1">
                <textarea name="new_option_text" 
                          rows="3"
                          placeholder="Enter the text for this new option..."
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
            </div>
            
            <div class="flex flex-col gap-2">
                <button type="submit" name="add_option" 
                        class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">
                    Add Option
                </button>
                
                <div class="text-xs text-gray-500 text-center">
                    Check if correct
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Quick Tips -->
<div class="mt-8 bg-emerald-50 border border-emerald-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-emerald-900 mb-3">💡 Option Management Tips</h3>
    <div class="grid md:grid-cols-2 gap-4 text-sm text-emerald-800">
        <div>
            <h4 class="font-semibold mb-2">Multiple Choice Guidelines</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>Provide 3-5 plausible options</li>
                <li>Make incorrect options believable</li>
                <li>Keep options similar in length</li>
                <li>Avoid "all of the above" options</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-2">True/False Guidelines</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>Only True and False options needed</li>
                <li>Select exactly one correct answer</li>
                <li>Avoid absolute terms like "always" or "never"</li>
                <li>Test important concepts clearly</li>
            </ul>
        </div>
    </div>
</div>

<!-- Option Statistics -->
<?php if (!empty($options) && $stats['total_answers'] > 0): ?>
<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Answer Statistics</h2>
        <p class="text-gray-600 mt-1">See how students have answered this question</p>
    </div>
    
    <div class="p-6">
        <div class="space-y-4">
            <?php foreach ($options as $index => $option): ?>
                <?php if ($option['answer_count'] > 0): ?>
                    <?php $percentage = round(($option['answer_count'] / $stats['total_answers']) * 100, 1); ?>
                    <div class="flex items-center gap-4">
                        <div class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded text-sm font-medium">
                            <?php echo chr(65 + $index); ?>
                        </div>
                        
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars(substr($option['option_text'], 0, 60)) . (strlen($option['option_text']) > 60 ? '...' : ''); ?>
                                    <?php if ($option['is_correct']): ?>
                                        <span class="ml-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Correct</span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-sm text-gray-600"><?php echo $option['answer_count']; ?> (<?php echo $percentage; ?>%)</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="<?php echo $option['is_correct'] ? 'bg-green-500' : 'bg-blue-500'; ?> h-2 rounded-full transition-all duration-300" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-resize textareas
    const textareas = document.querySelectorAll('textarea');
    
    textareas.forEach(textarea => {
        function autoResize() {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
        
        textarea.addEventListener('input', autoResize);
        autoResize(); // Initial resize
    });
    
    // Form validation for update options
    const optionsForm = document.getElementById('optionsForm');
    if (optionsForm) {
        optionsForm.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="correct_options[]"]');
            const textareas = document.querySelectorAll('textarea[name^="option_text"]');
            
            // Check if at least one option has text
            let hasValidOption = false;
            textareas.forEach(textarea => {
                if (textarea.value.trim().length > 0) {
                    hasValidOption = true;
                }
            });
            
            if (!hasValidOption) {
                e.preventDefault();
                alert('At least one option must have text.');
                return;
            }
            
            // Check if at least one correct answer is selected
            let hasCorrectAnswer = false;
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    hasCorrectAnswer = true;
                }
            });
            
            if (!hasCorrectAnswer) {
                e.preventDefault();
                alert('Please select at least one correct answer.');
                return;
            }
        });
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
    
    // Focus on new option textarea if it exists
    const newOptionTextarea = document.querySelector('textarea[name="new_option_text"]');
    if (newOptionTextarea && newOptionTextarea.value === '') {
        newOptionTextarea.focus();
    }
});
</script>

        </main>
    </div>

    <!-- Mobile menu toggle script -->
    <script>
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarClose = document.getElementById('sidebar-close');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const profileDropdownBtn = document.getElementById('profile-dropdown-btn');
        const profileDropdown = document.getElementById('profile-dropdown');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('hidden');
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarClose.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Profile dropdown toggle
        profileDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            profileDropdown.classList.remove('show');
        });

        profileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>

</body>
</html>
