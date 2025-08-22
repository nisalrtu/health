<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Quiz";

$student_id = $_SESSION['student_id'];
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$quiz_id || !$module_id || !$course_id) {
    header('Location: courses.php');
    exit();
}

$success_message = '';
$error_message = '';
$current_question = isset($_GET['q']) ? intval($_GET['q']) : 1;

try {
    // Get quiz, module, and course information
    $stmt = $pdo->prepare("
        SELECT q.*, m.title as module_title, m.order_sequence as module_order,
               c.title as course_title, c.description as course_description
        FROM quizzes q
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE q.id = ? AND q.module_id = ? AND m.course_id = ? 
        AND q.is_active = 1 AND m.is_active = 1 AND c.is_active = 1
    ");
    $stmt->execute([$quiz_id, $module_id, $course_id]);
    $quiz = $stmt->fetch();
    
    if (!$quiz) {
        header('Location: module-view.php?id=' . $module_id . '&course_id=' . $course_id);
        exit();
    }
    
    // Check if all lessons in this module are completed (required for quiz access)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_lessons,
            COUNT(CASE WHEN up.status = 'completed' THEN 1 END) as completed_lessons
        FROM lessons l
        LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
        WHERE l.module_id = ?
    ");
    $stmt->execute([$student_id, $module_id]);
    $lesson_check = $stmt->fetch();
    
    $lessons_completed = ($lesson_check['total_lessons'] == $lesson_check['completed_lessons']);
    
    if (!$lessons_completed) {
        header('Location: module-view.php?id=' . $module_id . '&course_id=' . $course_id);
        exit();
    }
    
    // Get all questions for this quiz
    $stmt = $pdo->prepare("
        SELECT * FROM questions 
        WHERE quiz_id = ? 
        ORDER BY order_sequence ASC
    ");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll();
    
    if (empty($questions)) {
        $error_message = 'This quiz has no questions available.';
    }
    
    $total_questions = count($questions);
    
    // Validate current question number
    if ($current_question < 1 || $current_question > $total_questions) {
        $current_question = 1;
    }
    
    // Get current question
    $question = isset($questions[$current_question - 1]) ? $questions[$current_question - 1] : null;
    
    // Get options for current question
    $question_options = [];
    if ($question) {
        $stmt = $pdo->prepare("
            SELECT * FROM question_options 
            WHERE question_id = ? 
            ORDER BY order_sequence ASC
        ");
        $stmt->execute([$question['id']]);
        $question_options = $stmt->fetchAll();
    }
    
    // Check if there's an active quiz attempt
    $stmt = $pdo->prepare("
        SELECT * FROM quiz_attempts 
        WHERE user_id = ? AND quiz_id = ? AND completed_at IS NULL 
        ORDER BY started_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$student_id, $quiz_id]);
    $active_attempt = $stmt->fetch();
    
    // Get previous attempts for display
    $stmt = $pdo->prepare("
        SELECT *, 
               CASE WHEN passed = 1 THEN 'Passed' ELSE 'Failed' END as result
        FROM quiz_attempts 
        WHERE user_id = ? AND quiz_id = ? AND completed_at IS NOT NULL
        ORDER BY completed_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$student_id, $quiz_id]);
    $previous_attempts = $stmt->fetchAll();
    
    // Handle quiz start
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['start_quiz'])) {
        try {
            // End any existing incomplete attempts
            $stmt = $pdo->prepare("
                UPDATE quiz_attempts 
                SET completed_at = NOW(), score = 0, correct_answers = 0, passed = 0 
                WHERE user_id = ? AND quiz_id = ? AND completed_at IS NULL
            ");
            $stmt->execute([$student_id, $quiz_id]);
            
            // Create new attempt
            $stmt = $pdo->prepare("
                INSERT INTO quiz_attempts (user_id, quiz_id, attempt_number, score, total_questions, correct_answers, passed, started_at) 
                VALUES (?, ?, 
                    (SELECT COALESCE(MAX(attempt_number), 0) + 1 FROM quiz_attempts qa WHERE qa.user_id = ? AND qa.quiz_id = ?),
                    0, ?, 0, 0, NOW())
            ");
            $stmt->execute([$student_id, $quiz_id, $student_id, $quiz_id, $total_questions]);
            
            // Get the new attempt
            $stmt = $pdo->prepare("
                SELECT * FROM quiz_attempts 
                WHERE user_id = ? AND quiz_id = ? AND completed_at IS NULL 
                ORDER BY started_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$student_id, $quiz_id]);
            $active_attempt = $stmt->fetch();
            
            // Redirect to first question
            header('Location: quiz.php?id=' . $quiz_id . '&module_id=' . $module_id . '&course_id=' . $course_id . '&q=1');
            exit();
            
        } catch(PDOException $e) {
            $error_message = 'Error starting quiz. Please try again.';
        }
    }
    
    // Handle answer submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['next_question']) || isset($_POST['previous_question'])) && $active_attempt) {
        $selected_option_id = isset($_POST['selected_option']) ? intval($_POST['selected_option']) : 0;
        $answer_text = isset($_POST['answer_text']) ? trim($_POST['answer_text']) : '';
        
        if ($selected_option_id > 0 || !empty($answer_text)) {
            try {
                // Check if answer already exists for this question in this attempt
                $stmt = $pdo->prepare("
                    SELECT id FROM user_answers 
                    WHERE attempt_id = ? AND question_id = ?
                ");
                $stmt->execute([$active_attempt['id'], $question['id']]);
                $existing_answer = $stmt->fetch();
                
                // Get correct answer and check if selected answer is correct
                $is_correct = false;
                $points_earned = 0;
                
                if ($selected_option_id > 0) {
                    $stmt = $pdo->prepare("
                        SELECT is_correct FROM question_options 
                        WHERE id = ? AND question_id = ?
                    ");
                    $stmt->execute([$selected_option_id, $question['id']]);
                    $option = $stmt->fetch();
                    
                    if ($option && $option['is_correct']) {
                        $is_correct = true;
                        $points_earned = $question['points'];
                    }
                }
                
                if ($existing_answer) {
                    // Update existing answer
                    $stmt = $pdo->prepare("
                        UPDATE user_answers 
                        SET selected_option_id = ?, answer_text = ?, is_correct = ?, points_earned = ?
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([$selected_option_id, $answer_text, $is_correct ? 1 : 0, $points_earned, $existing_answer['id']]);
                } else {
                    // Insert new answer
                    $stmt = $pdo->prepare("
                        INSERT INTO user_answers (attempt_id, question_id, selected_option_id, answer_text, is_correct, points_earned) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $result = $stmt->execute([$active_attempt['id'], $question['id'], $selected_option_id, $answer_text, $is_correct ? 1 : 0, $points_earned]);
                }
                
                if ($result) {
                    // Determine next action
                    if (isset($_POST['next_question'])) {
                        // Go to next question
                        $next_q = $current_question + 1;
                        if ($next_q <= $total_questions) {
                            header('Location: quiz.php?id=' . $quiz_id . '&module_id=' . $module_id . '&course_id=' . $course_id . '&q=' . $next_q);
                            exit();
                        } else {
                            // Last question, go to review
                            header('Location: quiz.php?id=' . $quiz_id . '&module_id=' . $module_id . '&course_id=' . $course_id . '&review=1');
                            exit();
                        }
                    } elseif (isset($_POST['previous_question'])) {
                        // Go to previous question
                        $prev_q = $current_question - 1;
                        if ($prev_q >= 1) {
                            header('Location: quiz.php?id=' . $quiz_id . '&module_id=' . $module_id . '&course_id=' . $course_id . '&q=' . $prev_q);
                            exit();
                        }
                    }
                } else {
                    $error_message = 'Error saving answer. Database operation failed.';
                }
                
            } catch(PDOException $e) {
                $error_message = 'Database error saving answer: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Please select an answer before continuing.';
        }
    }
    
    // Handle quiz submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_quiz']) && $active_attempt) {
        try {
            // Calculate final score
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_answers,
                    SUM(points_earned) as total_points,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count
                FROM user_answers ua
                JOIN questions q ON ua.question_id = q.id
                WHERE ua.attempt_id = ?
            ");
            $stmt->execute([$active_attempt['id']]);
            $score_data = $stmt->fetch();
            
            // Check if we have all required data
            if (!$score_data || $score_data['total_answers'] == 0) {
                $error_message = 'No answers found. Please answer at least one question before submitting.';
            } else {
                $total_possible_points = array_sum(array_column($questions, 'points'));
                $final_score = $total_possible_points > 0 ? round(($score_data['total_points'] / $total_possible_points) * 100) : 0;
                $passed = $final_score >= $quiz['pass_threshold'];
                
                // Update attempt with final results
                $stmt = $pdo->prepare("
                    UPDATE quiz_attempts 
                    SET score = ?, correct_answers = ?, passed = ?, completed_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$final_score, $score_data['correct_count'], $passed ? 1 : 0, $active_attempt['id']]);
                
                if ($result) {
                    // Redirect to results
                    header('Location: quiz-result.php?attempt_id=' . $active_attempt['id'] . '&module_id=' . $module_id . '&course_id=' . $course_id);
                    exit();
                } else {
                    $error_message = 'Error updating quiz results. Please try again.';
                }
            }
            
        } catch(PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage() . '. Please try again.';
        }
    }
    
    // Get user's current answer for this question (if any)
    $current_answer = null;
    if ($active_attempt && $question) {
        $stmt = $pdo->prepare("
            SELECT * FROM user_answers 
            WHERE attempt_id = ? AND question_id = ?
        ");
        $stmt->execute([$active_attempt['id'], $question['id']]);
        $current_answer = $stmt->fetch();
    }
    
    // Check if reviewing answers
    $is_review = isset($_GET['review']) && $active_attempt;
    
} catch(PDOException $e) {
    header('Location: module-view.php?id=' . $module_id . '&course_id=' . $course_id);
    exit();
}

// Include header
include '../includes/student-header.php';
?>

<!-- Quiz Header -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-4">
                    <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                       class="text-white hover:text-yellow-300 transition duration-300 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <span class="text-white opacity-75"><?php echo htmlspecialchars($quiz['course_title']); ?></span>
                    <span class="text-white opacity-50 mx-2">•</span>
                    <span class="text-white opacity-75"><?php echo htmlspecialchars($quiz['module_title']); ?></span>
                    <span class="text-white opacity-50 mx-2">•</span>
                    <span class="text-white opacity-75">Quiz</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold mb-3 text-white">
                    <?php echo htmlspecialchars($quiz['title']); ?>
                </h1>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white"><?php echo $total_questions; ?> Questions</span>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg px-3 py-1">
                        <span class="text-white">Pass: <?php echo $quiz['pass_threshold']; ?>%</span>
                    </div>
                    <?php if ($quiz['quiz_type'] == 'final'): ?>
                        <div class="bg-yellow-500 bg-opacity-80 rounded-lg px-3 py-1">
                            <span class="text-white">Final Quiz</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($active_attempt && !$is_review): ?>
                <div class="mt-6 md:mt-0 md:ml-8">
                    <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                        <div class="text-3xl font-bold text-white mb-2">
                            <?php echo $current_question; ?>/<?php echo $total_questions; ?>
                        </div>
                        <div class="text-white opacity-90 text-sm mb-3">Question Progress</div>
                        <div class="w-full bg-white bg-opacity-30 rounded-full h-2">
                            <div class="bg-yellow-400 h-2 rounded-full transition-all duration-500" 
                                 style="width: <?php echo round(($current_question / $total_questions) * 100); ?>%"></div>
                        </div>
                        <div class="text-white opacity-75 text-xs mt-2">
                            <?php echo round(($current_question / $total_questions) * 100); ?>% Complete
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($success_message): ?>
    <div class="mb-6">
        <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg">
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
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

<!-- Main Content -->
<?php if (!$active_attempt && !$is_review): ?>
    <!-- Quiz Start Screen -->
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-8 text-center">
                <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Ready to Take the Quiz?</h2>
                <p class="text-gray-600 mb-8 max-w-2xl mx-auto">
                    This quiz contains <?php echo $total_questions; ?> questions. You need to score at least <?php echo $quiz['pass_threshold']; ?>% to pass. 
                    Take your time and read each question carefully.
                </p>
                
                <!-- Quiz Instructions -->
                <div class="bg-blue-50 rounded-lg p-6 mb-8 text-left max-w-2xl mx-auto">
                    <h3 class="font-semibold text-blue-900 mb-3">Quiz Instructions:</h3>
                    <ul class="space-y-2 text-blue-800">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Questions are presented one at a time
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            You can navigate back and forth between questions
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            You can change your answers before final submission
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Review your answers before final submission
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Minimum passing score: <?php echo $quiz['pass_threshold']; ?>%
                        </li>
                    </ul>
                </div>
                
                <!-- Previous Attempts -->
                <?php if (!empty($previous_attempts)): ?>
                    <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left max-w-2xl mx-auto">
                        <h3 class="font-semibold text-gray-900 mb-3">Previous Attempts:</h3>
                        <div class="space-y-2">
                            <?php foreach ($previous_attempts as $attempt): ?>
                                <div class="flex items-center justify-between py-2 border-b border-gray-200 last:border-b-0">
                                    <div>
                                        <span class="text-sm text-gray-600">
                                            Attempt <?php echo $attempt['attempt_number']; ?> - 
                                            <?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-lg font-medium mr-2
                                            <?php echo $attempt['passed'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $attempt['score']; ?>%
                                        </span>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?php echo $attempt['passed'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $attempt['result']; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Start Quiz Button -->
                <form method="POST">
                    <button type="submit" name="start_quiz" 
                            class="bg-purple-600 text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-purple-700 transition duration-300">
                        Start Quiz
                    </button>
                </form>
            </div>
        </div>
    </div>

<?php elseif ($is_review && $active_attempt): ?>
    <!-- Quiz Review Screen -->
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900">Review Your Answers</h2>
                <p class="text-gray-600 mt-2">Please review your answers before submitting the quiz.</p>
            </div>
            
            <div class="p-6">
                <!-- Question Summary -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <?php 
                    // Get all user answers for this attempt
                    $stmt = $pdo->prepare("
                        SELECT ua.*, q.order_sequence 
                        FROM user_answers ua
                        JOIN questions q ON ua.question_id = q.id
                        WHERE ua.attempt_id = ?
                        ORDER BY q.order_sequence ASC
                    ");
                    $stmt->execute([$active_attempt['id']]);
                    $user_answers = $stmt->fetchAll();
                    $answered_questions = array_column($user_answers, 'question_id');
                    
                    for ($i = 1; $i <= $total_questions; $i++): 
                        $question_id = $questions[$i-1]['id'];
                        $is_answered = in_array($question_id, $answered_questions);
                    ?>
                        <a href="quiz.php?id=<?php echo $quiz_id; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>&q=<?php echo $i; ?>" 
                           class="flex items-center justify-center p-3 rounded-lg border-2 transition duration-300
                               <?php echo $is_answered ? 'border-green-500 bg-green-50 text-green-700' : 'border-red-500 bg-red-50 text-red-700'; ?>">
                            <span class="font-medium">Q<?php echo $i; ?></span>
                            <?php if ($is_answered): ?>
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            <?php else: ?>
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            <?php endif; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <!-- Progress Stats -->
                <div class="bg-blue-50 rounded-lg p-6 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?php echo count($user_answers); ?></div>
                            <div class="text-sm text-blue-800">Questions Answered</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?php echo $total_questions - count($user_answers); ?></div>
                            <div class="text-sm text-blue-800">Questions Remaining</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-blue-600"><?php echo round((count($user_answers) / $total_questions) * 100); ?>%</div>
                            <div class="text-sm text-blue-800">Completion</div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col md:flex-row gap-4 justify-center">
                    <?php if (count($user_answers) < $total_questions): ?>
                        <a href="quiz.php?id=<?php echo $quiz_id; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>&q=1" 
                           class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-300 text-center">
                            Continue Answering Questions
                        </a>
                    <?php endif; ?>
                    
                    <?php if (count($user_answers) == $total_questions): ?>
                        <form method="POST" class="inline">
                            <button type="submit" name="submit_quiz" 
                                    class="px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-300 font-semibold"
                                    onclick="return confirm('Are you sure you want to submit your quiz? You cannot change your answers after submission.')">
                                Submit Quiz
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="text-red-600 font-medium">Please answer all questions before submitting the quiz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($active_attempt && $question): ?>
    <!-- Quiz Question Screen -->
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Question Header -->
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-medium mr-3">
                            Question <?php echo $current_question; ?> of <?php echo $total_questions; ?>
                        </span>
                        <span class="text-gray-600 text-sm">
                            <?php echo $question['points']; ?> point<?php echo $question['points'] != 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
                    </div>
                </div>
                
                <h3 class="text-xl font-semibold text-gray-900">
                    <?php echo htmlspecialchars($question['question_text']); ?>
                </h3>
            </div>
            
            <!-- Question Content -->
            <div class="p-6">
                <form method="POST">
                    <?php if ($question['question_type'] == 'multiple_choice' || $question['question_type'] == 'true_false'): ?>
                        <div class="space-y-4">
                            <?php foreach ($question_options as $option): ?>
                                <label class="flex items-start p-4 border border-gray-200 rounded-lg cursor-pointer hover:border-purple-300 hover:bg-purple-50 transition duration-300">
                                    <input type="radio" 
                                           name="selected_option" 
                                           value="<?php echo $option['id']; ?>"
                                           class="mt-1 text-purple-600 focus:ring-purple-500"
                                           <?php echo ($current_answer && $current_answer['selected_option_id'] == $option['id']) ? 'checked' : ''; ?>>
                                    <span class="ml-3 text-gray-900 flex-1">
                                        <?php echo htmlspecialchars($option['option_text']); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- For other question types (future implementation) -->
                        <div>
                            <label for="answer_text" class="block text-sm font-medium text-gray-700 mb-2">
                                Your Answer:
                            </label>
                            <textarea id="answer_text" 
                                      name="answer_text" 
                                      rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                      placeholder="Enter your answer here..."><?php echo $current_answer ? htmlspecialchars($current_answer['answer_text']) : ''; ?></textarea>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Navigation Buttons -->
                    <div class="flex flex-col md:flex-row justify-between items-center mt-8 gap-4">
                        <div class="flex items-center gap-3">
                            <?php if ($current_question > 1): ?>
                                <button type="submit" name="previous_question" 
                                        class="flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-300">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                    Previous
                                </button>
                            <?php endif; ?>
                            
                            <a href="quiz.php?id=<?php echo $quiz_id; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>&review=1" 
                               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition duration-300">
                                Review Answers
                            </a>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <?php if ($current_question < $total_questions): ?>
                                <button type="submit" name="next_question" 
                                        class="flex items-center px-6 py-3 text-white rounded-lg font-medium hover:bg-opacity-90 transition duration-300"
                                        style="background-color: #5fb3b4;">
                                    Save & Next
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            <?php else: ?>
                                <button type="submit" name="next_question" 
                                        class="flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 transition duration-300">
                                    Save & Review
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Question Navigation Sidebar -->
        <div class="mt-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h4 class="font-semibold text-gray-900 mb-4">Question Navigation</h4>
                <div class="grid grid-cols-5 md:grid-cols-10 gap-2">
                    <?php for ($i = 1; $i <= $total_questions; $i++): 
                        $q_id = $questions[$i-1]['id'];
                        $is_answered = false;
                        
                        if ($active_attempt) {
                            $stmt = $pdo->prepare("SELECT id FROM user_answers WHERE attempt_id = ? AND question_id = ?");
                            $stmt->execute([$active_attempt['id'], $q_id]);
                            $is_answered = $stmt->fetch() ? true : false;
                        }
                    ?>
                        <a href="quiz.php?id=<?php echo $quiz_id; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>&q=<?php echo $i; ?>" 
                           class="w-10 h-10 flex items-center justify-center text-sm font-medium rounded-lg transition duration-300
                               <?php if ($i == $current_question): ?>
                                   bg-purple-600 text-white
                               <?php elseif ($is_answered): ?>
                                   bg-green-100 text-green-800 hover:bg-green-200
                               <?php else: ?>
                                   bg-gray-100 text-gray-600 hover:bg-gray-200
                               <?php endif; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <div class="mt-4 text-xs text-gray-600">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-purple-600 rounded mr-2"></div>
                            <span>Current</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-100 border border-green-300 rounded mr-2"></div>
                            <span>Answered</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-gray-100 border border-gray-300 rounded mr-2"></div>
                            <span>Not Answered</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

    // Progress bar animation
    const progressBars = document.querySelectorAll('[style*="width:"]');
    progressBars.forEach(bar => {
        if (bar.style.width && bar.style.width !== '0%') {
            const width = bar.style.width;
            bar.style.width = '0%';
            
            setTimeout(() => {
                bar.style.width = width;
            }, 800);
        }
    });

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

    // Content animations
    const mainContent = document.querySelector('.max-w-4xl');
    if (mainContent) {
        mainContent.style.opacity = '0';
        mainContent.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            mainContent.style.transition = 'all 0.6s ease';
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
        }, 200);
    }

    // Header animation
    const headerSection = document.querySelector('[style*="background: linear-gradient"]');
    if (headerSection) {
        headerSection.style.opacity = '0';
        headerSection.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            headerSection.style.transition = 'all 0.8s ease';
            headerSection.style.opacity = '1';
            headerSection.style.transform = 'translateY(0)';
        }, 100);
    }

    // Radio button enhancement
    const radioButtons = document.querySelectorAll('input[type="radio"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove active class from all labels
            const allLabels = document.querySelectorAll('label');
            allLabels.forEach(label => {
                label.classList.remove('border-purple-500', 'bg-purple-50');
                label.classList.add('border-gray-200');
            });
            
            // Add active class to selected label
            const selectedLabel = this.closest('label');
            if (selectedLabel) {
                selectedLabel.classList.remove('border-gray-200');
                selectedLabel.classList.add('border-purple-500', 'bg-purple-50');
            }
        });
        
        // Set initial state
        if (radio.checked) {
            const selectedLabel = radio.closest('label');
            if (selectedLabel) {
                selectedLabel.classList.remove('border-gray-200');
                selectedLabel.classList.add('border-purple-500', 'bg-purple-50');
            }
        }
    });

    // Form submission loading states
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // For quiz submission, show confirmation
            if (this.name === 'submit_quiz') {
                if (!confirm('Are you sure you want to submit your quiz? You cannot change your answers after submission.')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // For answer submissions, check if an option is selected
            if (this.name === 'next_question' || this.name === 'previous_question') {
                const form = this.closest('form');
                const radioButtons = form.querySelectorAll('input[type="radio"]');
                const textArea = form.querySelector('textarea[name="answer_text"]');
                
                let hasAnswer = false;
                radioButtons.forEach(radio => {
                    if (radio.checked) hasAnswer = true;
                });
                
                if (textArea && textArea.value.trim()) hasAnswer = true;
                
                if (!hasAnswer && this.name === 'next_question') {
                    alert('Please select an answer before continuing.');
                    e.preventDefault();
                    return false;
                }
            }
            
            // Show loading state
            const originalText = this.innerHTML;
            const buttonSelf = this;
            
            setTimeout(() => {
                buttonSelf.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                `;
                buttonSelf.disabled = true;
            }, 100);
            
            // Restore button after 10 seconds if still on page (in case of errors)
            setTimeout(() => {
                if (buttonSelf.disabled) {
                    buttonSelf.innerHTML = originalText;
                    buttonSelf.disabled = false;
                }
            }, 10000);
        });
    });

    // Warning for page navigation during quiz
    const activeAttempt = <?php echo $active_attempt ? 'true' : 'false'; ?>;
    if (activeAttempt) {
        window.addEventListener('beforeunload', function(e) {
            // Only show warning if not navigating within the quiz
            if (!e.target.activeElement || !e.target.activeElement.closest('form')) {
                e.preventDefault();
                e.returnValue = '';
                return 'You have an active quiz. Are you sure you want to leave this page?';
            }
        });
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) return; // Ignore ctrl/cmd combinations
        
        <?php if ($active_attempt && $question && !$is_review): ?>
            if (e.key === 'ArrowLeft' && <?php echo $current_question; ?> > 1) {
                window.location.href = 'quiz.php?id=<?php echo $quiz_id; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>&q=<?php echo $current_question - 1; ?>';
            } else if (e.key === 'ArrowRight' && <?php echo $current_question; ?> < <?php echo $total_questions; ?>) {
                window.location.href = 'quiz.php?id=<?php echo $quiz_id; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>&q=<?php echo $current_question + 1; ?>';
            }
        <?php endif; ?>
    });

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .transition-all {
            transition-property: all;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 300ms;
        }
    `;
    document.head.appendChild(style);
});
</script>
</body>
</html>