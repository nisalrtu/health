<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "Quiz Results";

$student_id = $_SESSION['student_id'];
$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if (!$attempt_id || !$module_id || !$course_id) {
    header('Location: courses.php');
    exit();
}

try {
    // Get quiz attempt details
    $stmt = $pdo->prepare("
        SELECT qa.*, q.title as quiz_title, q.pass_threshold, q.quiz_type,
               m.title as module_title, c.title as course_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN modules m ON q.module_id = m.id
        JOIN courses c ON m.course_id = c.id
        WHERE qa.id = ? AND qa.user_id = ? AND qa.completed_at IS NOT NULL
    ");
    $stmt->execute([$attempt_id, $student_id]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        header('Location: courses.php');
        exit();
    }
    
    // Get detailed answers for this attempt
    $stmt = $pdo->prepare("
        SELECT ua.*, q.question_text, q.points, qo.option_text, qo.is_correct as option_correct
        FROM user_answers ua
        JOIN questions q ON ua.question_id = q.id
        LEFT JOIN question_options qo ON ua.selected_option_id = qo.id
        WHERE ua.attempt_id = ?
        ORDER BY q.order_sequence ASC
    ");
    $stmt->execute([$attempt_id]);
    $answers = $stmt->fetchAll();
    
    // Get correct answers for comparison
    $stmt = $pdo->prepare("
        SELECT q.id as question_id, q.question_text, qo.option_text as correct_answer
        FROM questions q
        JOIN question_options qo ON q.id = qo.question_id
        WHERE q.quiz_id = ? AND qo.is_correct = 1
        ORDER BY q.order_sequence ASC
    ");
    $stmt->execute([$attempt['quiz_id']]);
    $correct_answers = $stmt->fetchAll();
    $correct_answers_map = [];
    foreach ($correct_answers as $correct) {
        $correct_answers_map[$correct['question_id']] = $correct['correct_answer'];
    }
    
    // Check if student needs to update progress
    if ($attempt['passed'] && $attempt['quiz_type'] == 'module') {
        // Update module completion in user_progress
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, course_id, module_id, lesson_id, status, completed_at) 
            VALUES (?, ?, ?, NULL, 'completed', NOW())
            ON DUPLICATE KEY UPDATE status = 'completed', completed_at = NOW()
        ");
        $stmt->execute([$student_id, $course_id, $module_id]);
    }
    
    // If this is a final quiz and passed, check if course is complete
    $course_completed = false;
    if ($attempt['passed'] && $attempt['quiz_type'] == 'final') {
        // Check if all modules are completed
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT m.id) as total_modules,
                COUNT(DISTINCT CASE WHEN up.status = 'completed' THEN m.id END) as completed_modules
            FROM modules m
            LEFT JOIN user_progress up ON m.id = up.module_id AND up.user_id = ?
            WHERE m.course_id = ? AND m.is_active = 1
        ");
        $stmt->execute([$student_id, $course_id]);
        $course_progress = $stmt->fetch();
        
        if ($course_progress['total_modules'] == $course_progress['completed_modules']) {
            $course_completed = true;
            
            // Update course completion
            $stmt = $pdo->prepare("
                INSERT INTO user_progress (user_id, course_id, module_id, lesson_id, status, completed_at) 
                VALUES (?, ?, NULL, NULL, 'completed', NOW())
                ON DUPLICATE KEY UPDATE status = 'completed', completed_at = NOW()
            ");
            $stmt->execute([$student_id, $course_id]);
            
            // Generate certificate if not exists
            $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$student_id, $course_id]);
            $existing_cert = $stmt->fetch();
            
            if (!$existing_cert) {
                $certificate_code = 'LM-' . strtoupper(uniqid());
                $verification_url = 'https://your-domain.com/verify-certificate.php?code=' . $certificate_code;
                
                $stmt = $pdo->prepare("
                    INSERT INTO certificates (user_id, course_id, certificate_code, verification_url) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $course_id, $certificate_code, $verification_url]);
            }
        }
    }
    
} catch(PDOException $e) {
    header('Location: courses.php');
    exit();
}

// Include header
include '../includes/student-header.php';
?>

<!-- Results Header -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white <?php echo $attempt['passed'] ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-red-500 to-red-600'; ?>">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-4">
                    <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                       class="text-white hover:text-yellow-300 transition duration-300 mr-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <span class="text-white opacity-75"><?php echo htmlspecialchars($attempt['course_title']); ?></span>
                    <span class="text-white opacity-50 mx-2">•</span>
                    <span class="text-white opacity-75"><?php echo htmlspecialchars($attempt['module_title']); ?></span>
                    <span class="text-white opacity-50 mx-2">•</span>
                    <span class="text-white opacity-75">Quiz Results</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold mb-3 text-white">
                    <?php echo htmlspecialchars($attempt['quiz_title']); ?>
                </h1>
                <div class="flex items-center">
                    <?php if ($attempt['passed']): ?>
                        <svg class="w-8 h-8 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-2xl font-bold text-white">Congratulations! You Passed!</span>
                    <?php else: ?>
                        <svg class="w-8 h-8 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-2xl font-bold text-white">Keep Trying! You Can Do Better!</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-6 md:mt-0 md:ml-8">
                <div class="bg-white bg-opacity-20 rounded-xl p-6 text-center">
                    <div class="text-4xl font-bold text-white mb-2">
                        <?php echo $attempt['score']; ?>%
                    </div>
                    <div class="text-white opacity-90 text-sm mb-3">Final Score</div>
                    <div class="text-white opacity-75 text-xs">
                        Required: <?php echo $attempt['pass_threshold']; ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Score Summary -->
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-900">Quiz Summary</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $attempt['score']; ?>%</div>
                    <div class="text-sm text-gray-600">Final Score</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600 mb-2"><?php echo $attempt['correct_answers']; ?></div>
                    <div class="text-sm text-gray-600">Correct Answers</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-600 mb-2"><?php echo $attempt['total_questions']; ?></div>
                    <div class="text-sm text-gray-600">Total Questions</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold <?php echo $attempt['passed'] ? 'text-green-600' : 'text-red-600'; ?> mb-2">
                        <?php echo $attempt['passed'] ? 'PASSED' : 'FAILED'; ?>
                    </div>
                    <div class="text-sm text-gray-600">Result</div>
                </div>
            </div>
            
            <div class="mt-6">
                <div class="bg-gray-200 rounded-full h-4">
                    <div class="<?php echo $attempt['passed'] ? 'bg-green-500' : 'bg-red-500'; ?> h-4 rounded-full transition-all duration-1000" 
                         style="width: <?php echo $attempt['score']; ?>%"></div>
                </div>
                <div class="flex justify-between text-sm text-gray-600 mt-2">
                    <span>0%</span>
                    <span class="font-medium">Pass: <?php echo $attempt['pass_threshold']; ?>%</span>
                    <span>100%</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Question Review -->
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-900">Question Review</h2>
            <p class="text-gray-600 mt-2">Review your answers and see the correct solutions.</p>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($answers as $index => $answer): ?>
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium mr-3">
                                    Question <?php echo $index + 1; ?>
                                </span>
                                <span class="text-sm text-gray-600">
                                    <?php echo $answer['points']; ?> point<?php echo $answer['points'] != 1 ? 's' : ''; ?>
                                </span>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-3">
                                <?php echo htmlspecialchars($answer['question_text']); ?>
                            </h3>
                        </div>
                        <div class="ml-4">
                            <?php if ($answer['is_correct']): ?>
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            <?php else: ?>
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <!-- Your Answer -->
                        <div class="p-4 rounded-lg <?php echo $answer['is_correct'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                            <div class="flex items-center mb-2">
                                <span class="text-sm font-medium <?php echo $answer['is_correct'] ? 'text-green-800' : 'text-red-800'; ?>">
                                    Your Answer:
                                </span>
                                <?php if ($answer['is_correct']): ?>
                                    <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Correct</span>
                                <?php else: ?>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Incorrect</span>
                                <?php endif; ?>
                            </div>
                            <div class="<?php echo $answer['is_correct'] ? 'text-green-700' : 'text-red-700'; ?>">
                                <?php echo $answer['option_text'] ? htmlspecialchars($answer['option_text']) : htmlspecialchars($answer['answer_text']); ?>
                            </div>
                        </div>
                        
                        <!-- Correct Answer (if different) -->
                        <?php if (!$answer['is_correct'] && isset($correct_answers_map[$answer['question_id']])): ?>
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <span class="text-sm font-medium text-green-800">Correct Answer:</span>
                                </div>
                                <div class="text-green-700">
                                    <?php echo htmlspecialchars($correct_answers_map[$answer['question_id']]); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Points -->
                        <div class="text-sm text-gray-600">
                            Points earned: <span class="font-medium"><?php echo $answer['points_earned']; ?></span> out of <span class="font-medium"><?php echo $answer['points']; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Course Completion Message -->
<?php if ($course_completed): ?>
    <div class="mb-8">
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl p-8 text-white text-center">
            <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
            <h2 class="text-3xl font-bold mb-4">Course Completed!</h2>
            <p class="text-xl mb-6 opacity-90">
                Congratulations! You have successfully completed the entire course. Your certificate has been generated.
            </p>
            <a href="profile.php" 
               class="inline-flex items-center px-6 py-3 bg-white text-purple-600 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
                View Certificate
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex flex-col md:flex-row gap-4 justify-center">
            <?php if (!$attempt['passed']): ?>
                <a href="quiz.php?id=<?php echo $attempt['quiz_id']; ?>&module_id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
                   class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-300 text-center font-medium">
                    Retake Quiz
                </a>
            <?php endif; ?>
            
            <a href="module-view.php?id=<?php echo $module_id; ?>&course_id=<?php echo $course_id; ?>" 
               class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-300 text-center font-medium">
                Back to Module
            </a>
            
            <a href="course-view.php?id=<?php echo $course_id; ?>" 
               class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition duration-300 text-center font-medium">
                Back to Course
            </a>
            
            <a href="dashboard.php" 
               class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition duration-300 text-center font-medium">
                Dashboard
            </a>
        </div>
    </div>
</div>

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

    // Animate progress bar
    const progressBar = document.querySelector('[style*="width:"]');
    if (progressBar) {
        const width = progressBar.style.width;
        progressBar.style.width = '0%';
        
        setTimeout(() => {
            progressBar.style.width = width;
        }, 500);
    }

    // Animate score numbers
    const scoreElement = document.querySelector('.text-4xl.font-bold');
    if (scoreElement) {
        const finalScore = parseInt(scoreElement.textContent);
        let currentScore = 0;
        const increment = finalScore / 30; // Animate over 30 frames
        
        scoreElement.textContent = '0%';
        
        const timer = setInterval(() => {
            currentScore += increment;
            if (currentScore >= finalScore) {
                currentScore = finalScore;
                clearInterval(timer);
            }
            scoreElement.textContent = Math.round(currentScore) + '%';
        }, 50);
    }

    // Animate question results
    const questionItems = document.querySelectorAll('.divide-y > div');
    questionItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 100 * index);
    });

    // Header animation
    const headerSection = document.querySelector('.rounded-xl.p-6.text-white');
    if (headerSection) {
        headerSection.style.opacity = '0';
        headerSection.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            headerSection.style.transition = 'all 0.8s ease';
            headerSection.style.opacity = '1';
            headerSection.style.transform = 'translateY(0)';
        }, 100);
    }

    // Summary cards animation
    const summaryCards = document.querySelectorAll('.grid.grid-cols-1.md\\:grid-cols-4 > div');
    summaryCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 200 + (100 * index));
    });
});
</script>
</body>
</html>
