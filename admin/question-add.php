<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Add New Question";

// Get quiz filter if provided
$quiz_filter = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Initialize variables
$success_message = '';
$error_message = '';
$quiz_id = $quiz_filter;
$question_text = '';
$question_type = 'multiple_choice';
$points = 1;
$order_sequence = 1;
$options = [
    ['text' => '', 'is_correct' => false],
    ['text' => '', 'is_correct' => false],
    ['text' => '', 'is_correct' => false],
    ['text' => '', 'is_correct' => false]
];

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
        
        if (!$quiz_info) {
            $error_message = "Quiz not found.";
            $quiz_filter = 0;
        }
    } catch (PDOException $e) {
        $quiz_info = null;
        $quiz_filter = 0;
    }
}

// Get next order sequence for the selected quiz
if ($quiz_filter > 0) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_sequence), 0) + 1 as next_order FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quiz_filter]);
        $order_sequence = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $order_sequence = 1;
    }
}

// Get all quizzes for dropdown
try {
    $stmt = $pdo->query("
        SELECT q.id, q.title, m.title as module_title, c.title as course_title 
        FROM quizzes q
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        ORDER BY c.title, m.title, q.title
    ");
    $all_quizzes = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_quizzes = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $quiz_id = intval($_POST['quiz_id']);
        $question_text = trim($_POST['question_text']);
        $question_type = $_POST['question_type'];
        $points = intval($_POST['points']);
        $order_sequence = intval($_POST['order_sequence']);
        
        // Validation
        $errors = [];
        
        if (empty($question_text)) {
            $errors[] = "Question text is required.";
        }
        
        if ($quiz_id <= 0) {
            $errors[] = "Please select a quiz.";
        }
        
        if ($points < 1 || $points > 100) {
            $errors[] = "Points must be between 1 and 100.";
        }
        
        if ($order_sequence < 1 || $order_sequence > 999) {
            $errors[] = "Order sequence must be between 1 and 999.";
        }
        
        // Validate quiz exists
        if ($quiz_id > 0) {
            $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ?");
            $stmt->execute([$quiz_id]);
            if (!$stmt->fetch()) {
                $errors[] = "Selected quiz does not exist.";
            }
        }
        
        // Handle options based on question type
        $question_options = [];
        if ($question_type == 'multiple_choice') {
            $correct_option_found = false;
            $option_count = 0;
            
            for ($i = 1; $i <= 10; $i++) {
                $option_text = trim($_POST["option_{$i}_text"] ?? '');
                $is_correct = isset($_POST["option_{$i}_correct"]) ? 1 : 0;
                
                if (!empty($option_text)) {
                    $question_options[] = [
                        'text' => $option_text,
                        'is_correct' => $is_correct,
                        'order_sequence' => $i
                    ];
                    $option_count++;
                    
                    if ($is_correct) {
                        $correct_option_found = true;
                    }
                }
            }
            
            if ($option_count < 2) {
                $errors[] = "Multiple choice questions must have at least 2 options.";
            }
            
            if (!$correct_option_found) {
                $errors[] = "Please mark at least one option as correct.";
            }
            
        } elseif ($question_type == 'true_false') {
            $correct_answer = $_POST['true_false_answer'] ?? '';
            
            if (!in_array($correct_answer, ['true', 'false'])) {
                $errors[] = "Please select the correct answer for True/False question.";
            }
            
            $question_options = [
                ['text' => 'True', 'is_correct' => ($correct_answer == 'true' ? 1 : 0), 'order_sequence' => 1],
                ['text' => 'False', 'is_correct' => ($correct_answer == 'false' ? 1 : 0), 'order_sequence' => 2]
            ];
        }
        // Short answer questions don't need options
        
        if (empty($errors)) {
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Insert question
                $stmt = $pdo->prepare("
                    INSERT INTO questions (quiz_id, question_text, question_type, points, order_sequence) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$quiz_id, $question_text, $question_type, $points, $order_sequence]);
                $question_id = $pdo->lastInsertId();
                
                // Insert options if needed
                if (!empty($question_options)) {
                    $option_stmt = $pdo->prepare("
                        INSERT INTO question_options (question_id, option_text, is_correct, order_sequence) 
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    foreach ($question_options as $option) {
                        $option_stmt->execute([
                            $question_id,
                            $option['text'],
                            $option['is_correct'],
                            $option['order_sequence']
                        ]);
                    }
                }
                
                // Commit transaction
                $pdo->commit();
                
                $success_message = "Question added successfully!";
                
                // Redirect to manage options or back to questions list
                if (isset($_POST['save_and_add_another'])) {
                    // Reset form for another question
                    $question_text = '';
                    $points = 1;
                    $order_sequence++;
                    $options = [
                        ['text' => '', 'is_correct' => false],
                        ['text' => '', 'is_correct' => false],
                        ['text' => '', 'is_correct' => false],
                        ['text' => '', 'is_correct' => false]
                    ];
                } elseif (isset($_POST['save_and_manage_options'])) {
                    header('Location: question-options.php?question_id=' . $question_id);
                    exit();
                } else {
                    header('Location: questions.php' . ($quiz_filter ? '?quiz_id=' . $quiz_filter : ''));
                    exit();
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Database error: " . $e->getMessage();
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-green-600 to-blue-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Add New Question</h1>
                <p class="text-green-100 text-lg">
                    <?php if ($quiz_info): ?>
                        Adding to: <strong><?php echo htmlspecialchars($quiz_info['title']); ?></strong>
                    <?php else: ?>
                        Create a new question for your quiz
                    <?php endif; ?>
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="questions.php<?php echo $quiz_filter ? '?quiz_id=' . $quiz_filter : ''; ?>" 
                   class="bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    ← Back to Questions
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <?php echo $success_message; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <?php echo $error_message; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Question Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <form method="POST" id="questionForm">
        <!-- Form Header -->
        <div class="p-6 border-b border-gray-200 bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-900">Question Details</h2>
            <p class="text-gray-600 mt-1">Fill in the information below to create a new question.</p>
        </div>
        
        <!-- Form Content -->
        <div class="p-6 space-y-6">
            <!-- Quiz Selection -->
            <div>
                <label for="quiz_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Quiz <span class="text-red-500">*</span>
                </label>
                <select id="quiz_id" name="quiz_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <option value="">Select a quiz...</option>
                    <?php foreach ($all_quizzes as $quiz): ?>
                        <option value="<?php echo $quiz['id']; ?>" 
                                <?php echo $quiz_id == $quiz['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($quiz['course_title'] . ' - ' . $quiz['module_title'] . ' - ' . $quiz['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-sm text-gray-500 mt-1">Select the quiz this question belongs to.</p>
            </div>
            
            <!-- Question Text -->
            <div>
                <label for="question_text" class="block text-sm font-medium text-gray-700 mb-2">
                    Question Text <span class="text-red-500">*</span>
                </label>
                <textarea id="question_text" name="question_text" rows="4" required
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                          placeholder="Enter your question here..."><?php echo htmlspecialchars($question_text); ?></textarea>
                <p class="text-sm text-gray-500 mt-1">Write a clear and concise question.</p>
            </div>
            
            <!-- Question Type and Settings Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Question Type -->
                <div>
                    <label for="question_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Question Type <span class="text-red-500">*</span>
                    </label>
                    <select id="question_type" name="question_type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            onchange="toggleQuestionType()">
                        <option value="multiple_choice" <?php echo $question_type == 'multiple_choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                        <option value="true_false" <?php echo $question_type == 'true_false' ? 'selected' : ''; ?>>True/False</option>
                        <option value="short_answer" <?php echo $question_type == 'short_answer' ? 'selected' : ''; ?>>Short Answer</option>
                    </select>
                </div>
                
                <!-- Points -->
                <div>
                    <label for="points" class="block text-sm font-medium text-gray-700 mb-2">
                        Points <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="points" name="points" min="1" max="100" value="<?php echo $points; ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="text-sm text-gray-500 mt-1">1-100 points</p>
                </div>
                
                <!-- Order Sequence -->
                <div>
                    <label for="order_sequence" class="block text-sm font-medium text-gray-700 mb-2">
                        Order <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="order_sequence" name="order_sequence" min="1" max="999" value="<?php echo $order_sequence; ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <p class="text-sm text-gray-500 mt-1">Question order</p>
                </div>
            </div>
            
            <!-- Multiple Choice Options -->
            <div id="multiple_choice_options" class="<?php echo $question_type != 'multiple_choice' ? 'hidden' : ''; ?>">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Answer Options</h3>
                <div id="options_container" class="space-y-3">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <div class="option-row flex items-center gap-3 p-3 border border-gray-200 rounded-lg" data-option="<?php echo $i; ?>">
                            <div class="flex items-center">
                                <input type="checkbox" id="option_<?php echo $i; ?>_correct" name="option_<?php echo $i; ?>_correct" 
                                       class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                <label for="option_<?php echo $i; ?>_correct" class="ml-2 text-sm text-gray-600">Correct</label>
                            </div>
                            <div class="flex-1">
                                <input type="text" id="option_<?php echo $i; ?>_text" name="option_<?php echo $i; ?>_text" 
                                       placeholder="Option <?php echo $i; ?> text..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            </div>
                            <?php if ($i > 4): ?>
                                <button type="button" onclick="removeOption(<?php echo $i; ?>)" 
                                        class="text-red-600 hover:text-red-800 <?php echo $i <= 4 ? 'hidden' : ''; ?>">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <button type="button" id="add_option_btn" onclick="addOption()" 
                        class="mt-3 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition duration-300">
                    + Add Option
                </button>
                <p class="text-sm text-gray-500 mt-2">
                    Check the box next to the correct answer(s). You can have multiple correct answers.
                </p>
            </div>
            
            <!-- True/False Options -->
            <div id="true_false_options" class="<?php echo $question_type != 'true_false' ? 'hidden' : ''; ?>">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Correct Answer</h3>
                <div class="space-y-3">
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="true_false_answer" value="true" 
                               class="text-green-600 focus:ring-green-500">
                        <span class="ml-3 text-gray-900">True</span>
                    </label>
                    <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="true_false_answer" value="false" 
                               class="text-green-600 focus:ring-green-500">
                        <span class="ml-3 text-gray-900">False</span>
                    </label>
                </div>
            </div>
            
            <!-- Short Answer Note -->
            <div id="short_answer_note" class="<?php echo $question_type != 'short_answer' ? 'hidden' : ''; ?>">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-blue-900">Short Answer Question</h4>
                            <p class="text-sm text-blue-800 mt-1">
                                Short answer questions require manual grading. Students will type their responses,
                                and you'll need to review and score them individually.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div class="flex flex-col md:flex-row gap-3 justify-between">
                <div class="flex flex-col md:flex-row gap-3">
                    <button type="submit" 
                            class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-300 font-semibold">
                        Save Question
                    </button>
                    <button type="submit" name="save_and_add_another"
                            class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300 font-semibold">
                        Save & Add Another
                    </button>
                    <button type="submit" name="save_and_manage_options" id="manage_options_btn"
                            class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition duration-300 font-semibold <?php echo $question_type == 'short_answer' ? 'hidden' : ''; ?>">
                        Save & Manage Options
                    </button>
                </div>
                <a href="questions.php<?php echo $quiz_filter ? '?quiz_id=' . $quiz_filter : ''; ?>" 
                   class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition duration-300 text-center">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Quick Tips -->
<div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-yellow-900 mb-3">💡 Question Writing Tips</h3>
    <div class="grid md:grid-cols-2 gap-4 text-sm text-yellow-800">
        <div>
            <h4 class="font-semibold mb-2">Best Practices</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>Use clear, simple language</li>
                <li>Avoid double negatives</li>
                <li>Make sure there's only one correct answer</li>
                <li>Test your questions before publishing</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-2">Question Types</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li><strong>Multiple Choice:</strong> Best for testing specific facts</li>
                <li><strong>True/False:</strong> Good for quick assessments</li>
                <li><strong>Short Answer:</strong> Allows for detailed responses</li>
            </ul>
        </div>
    </div>
</div>

<script>
let optionCount = 4;
const maxOptions = 10;

function toggleQuestionType() {
    const questionType = document.getElementById('question_type').value;
    const multipleChoiceDiv = document.getElementById('multiple_choice_options');
    const trueFalseDiv = document.getElementById('true_false_options');
    const shortAnswerDiv = document.getElementById('short_answer_note');
    const manageOptionsBtn = document.getElementById('manage_options_btn');
    
    // Hide all option divs
    multipleChoiceDiv.classList.add('hidden');
    trueFalseDiv.classList.add('hidden');
    shortAnswerDiv.classList.add('hidden');
    
    // Show relevant div
    if (questionType === 'multiple_choice') {
        multipleChoiceDiv.classList.remove('hidden');
        manageOptionsBtn.classList.remove('hidden');
    } else if (questionType === 'true_false') {
        trueFalseDiv.classList.remove('hidden');
        manageOptionsBtn.classList.remove('hidden');
    } else if (questionType === 'short_answer') {
        shortAnswerDiv.classList.remove('hidden');
        manageOptionsBtn.classList.add('hidden');
    }
}

function addOption() {
    if (optionCount >= maxOptions) {
        alert('Maximum ' + maxOptions + ' options allowed.');
        return;
    }
    
    optionCount++;
    const optionRow = document.querySelector(`[data-option="${optionCount}"]`);
    if (optionRow) {
        optionRow.classList.remove('hidden');
        optionRow.style.display = 'flex';
    }
    
    // Hide add button if we've reached the maximum
    if (optionCount >= maxOptions) {
        document.getElementById('add_option_btn').style.display = 'none';
    }
}

function removeOption(optionNumber) {
    if (optionNumber <= 4) {
        return; // Don't remove first 4 options
    }
    
    const optionRow = document.querySelector(`[data-option="${optionNumber}"]`);
    if (optionRow) {
        optionRow.style.display = 'none';
        // Clear the input values
        optionRow.querySelector(`input[name="option_${optionNumber}_text"]`).value = '';
        optionRow.querySelector(`input[name="option_${optionNumber}_correct"]`).checked = false;
    }
    
    // Show add button if we're below maximum
    if (optionCount >= maxOptions) {
        document.getElementById('add_option_btn').style.display = 'block';
    }
}

// Initialize the form
document.addEventListener('DOMContentLoaded', function() {
    toggleQuestionType();
    
    // Hide extra option rows initially
    for (let i = 5; i <= maxOptions; i++) {
        const optionRow = document.querySelector(`[data-option="${i}"]`);
        if (optionRow) {
            optionRow.style.display = 'none';
        }
    }
    
    // Form validation
    document.getElementById('questionForm').addEventListener('submit', function(e) {
        const questionType = document.getElementById('question_type').value;
        
        if (questionType === 'multiple_choice') {
            // Check if at least 2 options are filled and at least one is marked correct
            let filledOptions = 0;
            let correctOptions = 0;
            
            for (let i = 1; i <= maxOptions; i++) {
                const textInput = document.querySelector(`input[name="option_${i}_text"]`);
                const correctInput = document.querySelector(`input[name="option_${i}_correct"]`);
                
                if (textInput && textInput.value.trim() !== '') {
                    filledOptions++;
                    if (correctInput && correctInput.checked) {
                        correctOptions++;
                    }
                }
            }
            
            if (filledOptions < 2) {
                e.preventDefault();
                alert('Please provide at least 2 answer options for multiple choice questions.');
                return;
            }
            
            if (correctOptions === 0) {
                e.preventDefault();
                alert('Please mark at least one option as correct.');
                return;
            }
        }
        
        if (questionType === 'true_false') {
            const trueFalseAnswer = document.querySelector('input[name="true_false_answer"]:checked');
            if (!trueFalseAnswer) {
                e.preventDefault();
                alert('Please select the correct answer for the True/False question.');
                return;
            }
        }
    });
    
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
</script>
