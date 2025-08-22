<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Add New Quiz";

// Get module ID from URL if provided
$preselected_module = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $module_id = intval($_POST['module_id']);
        $quiz_type = $_POST['quiz_type'];
        $pass_threshold = intval($_POST['pass_threshold']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Quiz title is required.";
        }
        
        if ($module_id <= 0) {
            $errors[] = "Please select a valid module.";
        }
        
        if (!in_array($quiz_type, ['module', 'final'])) {
            $errors[] = "Please select a valid quiz type.";
        }
        
        if ($pass_threshold < 1 || $pass_threshold > 100) {
            $errors[] = "Pass threshold must be between 1 and 100.";
        }
        
        // Check if module exists and is active
        if ($module_id > 0) {
            $stmt = $pdo->prepare("
                SELECT m.id, m.title, m.is_active, c.title as course_title, c.is_active as course_active 
                FROM modules m 
                JOIN courses c ON m.course_id = c.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$module_id]);
            $module = $stmt->fetch();
            
            if (!$module) {
                $errors[] = "Selected module does not exist.";
            } elseif (!$module['is_active']) {
                $errors[] = "Cannot add quiz to inactive module.";
            } elseif (!$module['course_active']) {
                $errors[] = "Cannot add quiz to module in inactive course.";
            }
        }
        
        // Check if final quiz already exists for this module
        if ($quiz_type == 'final' && $module_id > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE module_id = ? AND quiz_type = 'final'");
            $stmt->execute([$module_id]);
            $final_quiz_count = $stmt->fetchColumn();
            
            if ($final_quiz_count > 0) {
                $errors[] = "A final exam already exists for this module. Only one final exam per module is allowed.";
            }
        }
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            // Insert the new quiz
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (module_id, title, quiz_type, pass_threshold, is_active) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $module_id,
                $title,
                $quiz_type,
                $pass_threshold,
                $is_active
            ]);
            
            $quiz_id = $pdo->lastInsertId();
            
            // Redirect based on user choice
            if (isset($_POST['action']) && $_POST['action'] == 'add_questions') {
                // Redirect to add questions
                $_SESSION['success_message'] = "Quiz '{$title}' has been created successfully. Now add questions to complete your quiz.";
                header('Location: question-add.php?quiz_id=' . $quiz_id);
            } else {
                // Redirect to quizzes page
                $_SESSION['success_message'] = "Quiz '{$title}' has been created successfully.";
                header('Location: quizzes.php' . ($preselected_module ? '?module_id=' . $preselected_module : ''));
            }
            exit();
        }
        
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Get all modules with their course information for the dropdown
try {
    $stmt = $pdo->query("
        SELECT m.id, m.title, m.is_active, c.title as course_title, c.is_active as course_active,
               COUNT(q.id) as quiz_count,
               COUNT(CASE WHEN q.quiz_type = 'final' THEN 1 END) as final_quiz_count
        FROM modules m 
        JOIN courses c ON m.course_id = c.id 
        LEFT JOIN quizzes q ON m.id = q.module_id
        WHERE m.is_active = 1 AND c.is_active = 1
        GROUP BY m.id
        ORDER BY c.title, m.order_sequence
    ");
    $modules = $stmt->fetchAll();
} catch(PDOException $e) {
    $modules = [];
    $error_message = "Failed to load modules: " . $e->getMessage();
}

// Get preselected module details if available
$preselected_module_info = null;
if ($preselected_module > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.id, m.title, c.title as course_title,
                   COUNT(q.id) as quiz_count,
                   COUNT(CASE WHEN q.quiz_type = 'final' THEN 1 END) as final_quiz_count
            FROM modules m 
            JOIN courses c ON m.course_id = c.id 
            LEFT JOIN quizzes q ON m.id = q.module_id
            WHERE m.id = ? AND m.is_active = 1 AND c.is_active = 1
            GROUP BY m.id
        ");
        $stmt->execute([$preselected_module]);
        $preselected_module_info = $stmt->fetch();
    } catch(PDOException $e) {
        $preselected_module = 0;
    }
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Add New Quiz</h1>
                <p class="text-purple-100 text-lg">Create assessments to test student knowledge</p>
                <?php if ($preselected_module_info): ?>
                    <div class="mt-3 inline-flex items-center bg-white/20 rounded-lg px-3 py-1">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <span class="text-sm">
                            Adding to: <?php echo htmlspecialchars($preselected_module_info['course_title'] . ' → ' . $preselected_module_info['title']); ?>
                        </span>
                    </div>
                    <?php if ($preselected_module_info['quiz_count'] > 0): ?>
                        <div class="mt-2 text-sm text-purple-200">
                            📊 Current quizzes: <?php echo $preselected_module_info['quiz_count']; ?> 
                            (<?php echo $preselected_module_info['final_quiz_count']; ?> final exam)
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="quizzes.php<?php echo $preselected_module ? '?module_id=' . $preselected_module : ''; ?>" 
                   class="bg-white text-purple-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    ← Back to Quizzes
                </a>
                <?php if ($preselected_module): ?>
                    <a href="modules.php" class="bg-white/20 text-white px-4 py-3 rounded-lg hover:bg-white/30 transition duration-300">
                        View All Modules
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <div class="flex items-center mb-2">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <strong>Please fix the following errors:</strong>
        </div>
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
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

<!-- Quiz Add Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Quiz Information</h2>
        <p class="text-gray-600 mt-1">Create a quiz to assess student understanding</p>
    </div>
    
    <form method="POST" class="p-6 space-y-6" id="quizForm">
        <!-- Module Selection -->
        <div>
            <label for="module_id" class="block text-sm font-medium text-gray-700 mb-2">
                Module <span class="text-red-500">*</span>
            </label>
            <select 
                id="module_id" 
                name="module_id" 
                required
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                <?php echo $preselected_module_info ? 'disabled' : ''; ?>
            >
                <option value="">Select a module...</option>
                <?php 
                $current_course = '';
                foreach ($modules as $module): 
                    if ($module['course_title'] !== $current_course) {
                        if ($current_course !== '') echo '</optgroup>';
                        echo '<optgroup label="' . htmlspecialchars($module['course_title']) . '">';
                        $current_course = $module['course_title'];
                    }
                ?>
                    <option value="<?php echo $module['id']; ?>" 
                        data-quiz-count="<?php echo $module['quiz_count']; ?>"
                        data-final-count="<?php echo $module['final_quiz_count']; ?>"
                        <?php echo (isset($_POST['module_id']) && $_POST['module_id'] == $module['id']) || ($preselected_module == $module['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($module['title']); ?>
                        <?php if ($module['quiz_count'] > 0): ?>
                            (<?php echo $module['quiz_count']; ?> quiz<?php echo $module['quiz_count'] > 1 ? 'es' : ''; ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($current_course !== '') echo '</optgroup>'; ?>
            </select>
            
            <?php if ($preselected_module_info): ?>
                <input type="hidden" name="module_id" value="<?php echo $preselected_module; ?>">
                <p class="mt-2 text-sm text-purple-600">
                    ✓ Quiz will be added to: <strong><?php echo htmlspecialchars($preselected_module_info['course_title'] . ' → ' . $preselected_module_info['title']); ?></strong>
                </p>
            <?php endif; ?>
            
            <?php if (empty($modules)): ?>
                <p class="mt-2 text-sm text-red-600">
                    No active modules available. <a href="module-add.php" class="underline">Create a module first</a>.
                </p>
            <?php endif; ?>
            
            <!-- Module Info Display -->
            <div id="moduleInfo" class="mt-2 text-sm text-gray-600 hidden">
                <div id="moduleQuizInfo"></div>
            </div>
        </div>

        <!-- Quiz Title -->
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                Quiz Title <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                id="title" 
                name="title" 
                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                required
                maxlength="200"
                placeholder="Enter a clear, descriptive quiz title..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
            >
            <div class="flex justify-between mt-1">
                <p class="text-sm text-gray-500">A clear title that describes what this quiz assesses</p>
                <span class="text-sm text-gray-400" id="titleCounter">0/200</span>
            </div>
        </div>

        <!-- Quiz Type -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">
                Quiz Type <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Module Quiz -->
                <div class="relative">
                    <input 
                        type="radio" 
                        id="quiz_type_module" 
                        name="quiz_type" 
                        value="module"
                        <?php echo (isset($_POST['quiz_type']) && $_POST['quiz_type'] == 'module') || !isset($_POST['quiz_type']) ? 'checked' : ''; ?>
                        class="sr-only peer"
                        required
                    >
                    <label for="quiz_type_module" class="flex flex-col p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-purple-50 peer-checked:border-purple-500 peer-checked:bg-purple-50 transition duration-300">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <span class="font-medium text-gray-900">Module Quiz</span>
                        </div>
                        <p class="text-sm text-gray-600">
                            A quiz that tests understanding of specific module content. 
                            Students can typically take these multiple times.
                        </p>
                    </label>
                </div>

                <!-- Final Exam -->
                <div class="relative">
                    <input 
                        type="radio" 
                        id="quiz_type_final" 
                        name="quiz_type" 
                        value="final"
                        <?php echo (isset($_POST['quiz_type']) && $_POST['quiz_type'] == 'final') ? 'checked' : ''; ?>
                        class="sr-only peer"
                        required
                    >
                    <label for="quiz_type_final" class="flex flex-col p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-yellow-50 peer-checked:border-yellow-500 peer-checked:bg-yellow-50 transition duration-300">
                        <div class="flex items-center mb-2">
                            <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                            </svg>
                            <span class="font-medium text-gray-900">Final Exam</span>
                        </div>
                        <p class="text-sm text-gray-600">
                            A comprehensive exam covering the entire module. 
                            Usually more challenging and may have limited attempts.
                        </p>
                        <div class="mt-2 text-xs text-yellow-700 bg-yellow-100 rounded px-2 py-1">
                            ⚠️ Only one final exam per module allowed
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Pass Threshold -->
        <div>
            <label for="pass_threshold" class="block text-sm font-medium text-gray-700 mb-2">
                Pass Threshold (%) <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input 
                    type="range" 
                    id="pass_threshold_range" 
                    min="1" 
                    max="100"
                    value="<?php echo $_POST['pass_threshold'] ?? '70'; ?>"
                    class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer mb-3"
                    oninput="updateThresholdInput(this.value)"
                >
                <input 
                    type="number" 
                    id="pass_threshold" 
                    name="pass_threshold" 
                    value="<?php echo $_POST['pass_threshold'] ?? '70'; ?>"
                    min="1" 
                    max="100"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                    oninput="updateThresholdRange(this.value)"
                >
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 top-12">
                    <span class="text-gray-500 text-sm">%</span>
                </div>
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>1% (Very Easy)</span>
                <span id="thresholdFeedback" class="font-medium"></span>
                <span>100% (Perfect Score Required)</span>
            </div>
            <p class="mt-2 text-sm text-gray-500">Minimum percentage required for students to pass this quiz</p>
        </div>

        <!-- Quiz Status -->
        <div class="flex items-start">
            <div class="flex items-center h-5">
                <input 
                    id="is_active" 
                    name="is_active" 
                    type="checkbox" 
                    <?php echo (isset($_POST['is_active']) || !isset($_POST['title'])) ? 'checked' : ''; ?>
                    class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                >
            </div>
            <div class="ml-3">
                <label for="is_active" class="text-sm font-medium text-gray-700">
                    Active Quiz
                </label>
                <p class="text-sm text-gray-500">
                    Active quizzes are visible to students and can be taken. Inactive quizzes are hidden.
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
            <button 
                type="submit" 
                name="action"
                value="save_only"
                id="saveBtn"
                class="flex-1 bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition duration-300"
                <?php echo empty($modules) ? 'disabled' : ''; ?>
            >
                <span class="flex items-center justify-center" id="saveBtnContent">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create Quiz
                </span>
            </button>
            
            <button 
                type="submit" 
                name="action"
                value="add_questions"
                class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-300"
                <?php echo empty($modules) ? 'disabled' : ''; ?>
            >
                <span class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Create & Add Questions
                </span>
            </button>
            
            <a 
                href="quizzes.php<?php echo $preselected_module ? '?module_id=' . $preselected_module : ''; ?>" 
                class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-300 text-center"
            >
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- Quiz Guidelines Section -->
<div class="mt-8 bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-purple-900 mb-4">🎯 Quiz Creation Best Practices</h3>
    <div class="grid md:grid-cols-3 gap-6 text-sm text-purple-800">
        <div>
            <h4 class="font-semibold mb-3 flex items-center">
                <span class="w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs mr-2">1</span>
                Plan Your Assessment
            </h4>
            <ul class="space-y-2 list-disc list-inside">
                <li>Define clear learning objectives</li>
                <li>Align questions with lesson content</li>
                <li>Balance difficulty levels appropriately</li>
                <li>Consider time requirements</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-3 flex items-center">
                <span class="w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs mr-2">2</span>
                Choose Quiz Type
            </h4>
            <ul class="space-y-2 list-disc list-inside">
                <li><strong>Module Quiz:</strong> Check understanding</li>
                <li><strong>Final Exam:</strong> Comprehensive assessment</li>
                <li>Set appropriate pass thresholds</li>
                <li>Consider multiple attempts vs. one-time</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-3 flex items-center">
                <span class="w-6 h-6 bg-purple-600 text-white rounded-full flex items-center justify-center text-xs mr-2">3</span>
                Add Quality Questions
            </h4>
            <ul class="space-y-2 list-disc list-inside">
                <li>Write clear, unambiguous questions</li>
                <li>Provide meaningful distractors</li>
                <li>Include explanations for answers</li>
                <li>Test knowledge, not trick students</li>
            </ul>
        </div>
    </div>
</div>

<!-- Pass Threshold Guidelines -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-6">
    <h4 class="font-semibold text-blue-900 mb-3">📊 Pass Threshold Guidelines</h4>
    <div class="grid md:grid-cols-4 gap-4 text-sm text-blue-800">
        <div class="text-center">
            <div class="font-semibold text-lg text-green-600">50-60%</div>
            <div class="font-medium">Introductory</div>
            <div class="text-xs">Basic understanding</div>
        </div>
        <div class="text-center">
            <div class="font-semibold text-lg text-blue-600">65-75%</div>
            <div class="font-medium">Standard</div>
            <div class="text-xs">Good comprehension</div>
        </div>
        <div class="text-center">
            <div class="font-semibold text-lg text-yellow-600">80-85%</div>
            <div class="font-medium">Advanced</div>
            <div class="text-xs">Mastery level</div>
        </div>
        <div class="text-center">
            <div class="font-semibold text-lg text-red-600">90-100%</div>
            <div class="font-medium">Expert</div>
            <div class="text-xs">Critical knowledge</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('quizForm');
    const saveBtn = document.getElementById('saveBtn');
    const saveBtnContent = document.getElementById('saveBtnContent');
    const titleInput = document.getElementById('title');
    const moduleSelect = document.getElementById('module_id');
    const moduleInfo = document.getElementById('moduleInfo');
    const moduleQuizInfo = document.getElementById('moduleQuizInfo');
    const finalQuizRadio = document.getElementById('quiz_type_final');
    
    // Auto-focus on title input
    titleInput.focus();
    
    // Character counter for title
    const titleCounter = document.getElementById('titleCounter');
    titleInput.addEventListener('input', function() {
        const length = this.value.length;
        titleCounter.textContent = `${length}/200`;
        
        if (length > 180) {
            titleCounter.className = 'text-sm text-red-500';
        } else if (length > 150) {
            titleCounter.className = 'text-sm text-yellow-600';
        } else {
            titleCounter.className = 'text-sm text-gray-400';
        }
    });
    
    // Initial counter update
    titleInput.dispatchEvent(new Event('input'));
    
    // Module selection change handler
    moduleSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (this.value) {
            const quizCount = selectedOption.getAttribute('data-quiz-count') || 0;
            const finalCount = selectedOption.getAttribute('data-final-count') || 0;
            
            moduleQuizInfo.innerHTML = `
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 mr-2">
                    ${quizCount} existing quiz${quizCount != 1 ? 'es' : ''}
                </span>
                ${finalCount > 0 ? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-800">Final exam exists</span>' : '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">No final exam yet</span>'}
            `;
            moduleInfo.classList.remove('hidden');
            
            // Disable final exam option if one already exists
            if (finalCount > 0) {
                finalQuizRadio.disabled = true;
                finalQuizRadio.parentElement.classList.add('opacity-50', 'cursor-not-allowed');
                // Switch to module quiz if final was selected
                if (finalQuizRadio.checked) {
                    document.getElementById('quiz_type_module').checked = true;
                }
            } else {
                finalQuizRadio.disabled = false;
                finalQuizRadio.parentElement.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        } else {
            moduleInfo.classList.add('hidden');
            finalQuizRadio.disabled = false;
            finalQuizRadio.parentElement.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    });
    
    // Initial module selection trigger
    if (moduleSelect.value) {
        moduleSelect.dispatchEvent(new Event('change'));
    }
    
    // Pass threshold synchronization
    window.updateThresholdInput = function(value) {
        document.getElementById('pass_threshold').value = value;
        updateThresholdFeedback(value);
    };
    
    window.updateThresholdRange = function(value) {
        document.getElementById('pass_threshold_range').value = value;
        updateThresholdFeedback(value);
    };
    
    function updateThresholdFeedback(value) {
        const feedback = document.getElementById('thresholdFeedback');
        let text = '';
        let colorClass = '';
        
        if (value < 50) {
            text = 'Very Easy';
            colorClass = 'text-green-600';
        } else if (value < 70) {
            text = 'Easy';
            colorClass = 'text-blue-600';
        } else if (value < 80) {
            text = 'Moderate';
            colorClass = 'text-yellow-600';
        } else if (value < 90) {
            text = 'Challenging';
            colorClass = 'text-orange-600';
        } else {
            text = 'Very Hard';
            colorClass = 'text-red-600';
        }
        
        feedback.textContent = `${value}% - ${text}`;
        feedback.className = `font-medium ${colorClass}`;
    }
    
    // Initial threshold feedback
    updateThresholdFeedback(document.getElementById('pass_threshold').value);
    
    // Form validation and submission
    form.addEventListener('submit', function(e) {
        const module = moduleSelect.value;
        const title = titleInput.value.trim();
        const quizType = document.querySelector('input[name="quiz_type"]:checked');
        
        // Validation
        if (!module) {
            e.preventDefault();
            alert('Please select a module for this quiz.');
            moduleSelect.focus();
            return;
        }
        
        if (!title) {
            e.preventDefault();
            alert('Please enter a quiz title.');
            titleInput.focus();
            return;
        }
        
        if (!quizType) {
            e.preventDefault();
            alert('Please select a quiz type.');
            return;
        }
        
        // Show loading state
        const clickedButton = e.submitter;
        clickedButton.disabled = true;
        
        if (clickedButton === saveBtn) {
            saveBtnContent.innerHTML = `
                <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Creating Quiz...
            `;
        } else {
            clickedButton.innerHTML = `
                <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Creating Quiz...
            `;
        }
    });
    
    // Auto-hide error messages after 15 seconds
    const errorMessages = document.querySelectorAll('.bg-red-100');
    errorMessages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'all 0.5s ease';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                message.remove();
            }, 500);
        }, 15000);
    });
    
    // Quiz type selection visual feedback
    const quizTypeInputs = document.querySelectorAll('input[name="quiz_type"]');
    quizTypeInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Update pass threshold recommendations based on quiz type
            const thresholdInput = document.getElementById('pass_threshold');
            const currentValue = parseInt(thresholdInput.value);
            
            if (this.value === 'final' && currentValue < 80) {
                // Suggest higher threshold for final exams
                thresholdInput.value = 80;
                document.getElementById('pass_threshold_range').value = 80;
                updateThresholdFeedback(80);
                
                // Show suggestion
                showTempMessage('Final exams typically require higher pass thresholds (80%+)', 'info');
            } else if (this.value === 'module' && currentValue > 85) {
                // Suggest moderate threshold for module quizzes
                thresholdInput.value = 70;
                document.getElementById('pass_threshold_range').value = 70;
                updateThresholdFeedback(70);
                
                showTempMessage('Module quizzes often use moderate thresholds (70-80%)', 'info');
            }
        });
    });
    
    function showTempMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
            type === 'info' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 
            type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
            'bg-yellow-100 text-yellow-800 border border-yellow-200'
        }`;
        messageDiv.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                ${message}
            </div>
        `;
        
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            messageDiv.style.transition = 'all 0.5s ease';
            messageDiv.style.opacity = '0';
            messageDiv.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                messageDiv.remove();
            }, 500);
        }, 4000);
    }
    
    // Save draft functionality
    let draftTimer;
    
    function saveDraft() {
        const draftData = {
            title: titleInput.value,
            module_id: moduleSelect.value,
            quiz_type: document.querySelector('input[name="quiz_type"]:checked')?.value,
            pass_threshold: document.getElementById('pass_threshold').value,
            timestamp: Date.now()
        };
        
        localStorage.setItem('quiz_draft', JSON.stringify(draftData));
    }
    
    function autoSaveDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 2000);
    }
    
    [titleInput, moduleSelect].forEach(input => {
        input.addEventListener('input', autoSaveDraft);
        input.addEventListener('change', autoSaveDraft);
    });
    
    quizTypeInputs.forEach(input => {
        input.addEventListener('change', autoSaveDraft);
    });
    
    // Load draft on page load
    const savedDraft = localStorage.getItem('quiz_draft');
    if (savedDraft && !titleInput.value) {
        try {
            const draft = JSON.parse(savedDraft);
            const age = Date.now() - draft.timestamp;
            
            // Only load drafts less than 24 hours old
            if (age < 24 * 60 * 60 * 1000) {
                if (confirm('Found a saved draft from earlier. Would you like to restore it?')) {
                    titleInput.value = draft.title || '';
                    if (draft.module_id && !moduleSelect.disabled) {
                        moduleSelect.value = draft.module_id;
                        moduleSelect.dispatchEvent(new Event('change'));
                    }
                    if (draft.quiz_type) {
                        const quizTypeRadio = document.getElementById(`quiz_type_${draft.quiz_type}`);
                        if (quizTypeRadio && !quizTypeRadio.disabled) {
                            quizTypeRadio.checked = true;
                        }
                    }
                    if (draft.pass_threshold) {
                        document.getElementById('pass_threshold').value = draft.pass_threshold;
                        document.getElementById('pass_threshold_range').value = draft.pass_threshold;
                        updateThresholdFeedback(draft.pass_threshold);
                    }
                    
                    // Update counters
                    titleInput.dispatchEvent(new Event('input'));
                }
            }
        } catch (e) {
            // Invalid draft data, ignore
        }
    }
    
    // Clear draft on successful submission
    form.addEventListener('submit', function() {
        localStorage.removeItem('quiz_draft');
    });
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
