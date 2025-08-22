<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

// Set page title
$page_title = "Edit Course";

// Initialize variables
$success_message = '';
$error_message = '';

// Get course data
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        header('Location: courses.php');
        exit();
    }
    
    // Check if course has any student progress
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_progress WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $has_student_progress = $stmt->fetch()['count'] > 0;
    
    // Get course statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT m.id) as module_count,
            COUNT(DISTINCT l.id) as lesson_count,
            COUNT(DISTINCT q.id) as quiz_count,
            COUNT(DISTINCT up.user_id) as student_count,
            AVG(CASE WHEN qa.passed = 1 THEN qa.score ELSE NULL END) as avg_quiz_score
        FROM courses c
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN lessons l ON m.id = l.module_id
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN user_progress up ON c.id = up.course_id
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.passed = 1
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$course_id]);
    $course_stats = $stmt->fetch();
    
    // Get recent modules for this course
    $stmt = $pdo->prepare("
        SELECT id, title, description, order_sequence, is_active 
        FROM modules 
        WHERE course_id = ? 
        ORDER BY order_sequence ASC 
        LIMIT 5
    ");
    $stmt->execute([$course_id]);
    $recent_modules = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $course = false;
    $has_student_progress = false;
    $course_stats = ['module_count' => 0, 'lesson_count' => 0, 'quiz_count' => 0, 'student_count' => 0, 'avg_quiz_score' => null];
    $recent_modules = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $course) {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($title)) {
            $error_message = "Course title is required.";
        } elseif (strlen($title) > 200) {
            $error_message = "Course title must be 200 characters or less.";
        } elseif (empty($description)) {
            $error_message = "Course description is required.";
        } elseif (strlen($description) > 1000) {
            $error_message = "Course description must be 1000 characters or less.";
        } else {
            // Check if title is unique (excluding current course)
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE title = ? AND id != ?");
            $stmt->execute([$title, $course_id]);
            if ($stmt->fetch()) {
                $error_message = "A course with this title already exists.";
            } elseif ($is_active == 0 && $has_student_progress) {
                // Don't allow deactivating if students have progress
                $error_message = "Cannot deactivate course: Students have already started this course.";
            } else {
                // Update the course
                $stmt = $pdo->prepare("
                    UPDATE courses 
                    SET title = ?, description = ?, is_active = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$title, $description, $is_active, $course_id])) {
                    $success_message = "Course updated successfully!";
                    
                    // Refresh course data
                    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                } else {
                    $error_message = "Failed to update course. Please try again.";
                }
            }
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center text-purple-200 text-sm mb-2">
                    <a href="courses.php" class="hover:text-white transition duration-300">Courses</a>
                    <svg class="w-4 h-4 mx-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span>Edit Course</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Edit Course</h1>
                <p class="text-purple-100 text-lg">
                    <?php echo htmlspecialchars($course['title'] ?? 'Unknown Course'); ?>
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="courses.php" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    ← Back to Courses
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <div class="flex items-start">
            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <div class="flex items-start">
            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!$course): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">Course Not Found</h3>
        <p class="text-gray-600 mb-4">The requested course could not be found or you don't have permission to edit it.</p>
        <a href="courses.php" class="bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 transition duration-300 inline-block">
            Back to Courses
        </a>
    </div>
<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Form -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Course Information</h2>
                <p class="text-gray-600 mt-1">Update the course details and settings below.</p>
            </div>
            
            <form method="POST" class="p-6 space-y-6" id="courseEditForm">
                <!-- Course Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        Course Title <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="title"
                        name="title" 
                        value="<?php echo htmlspecialchars($course['title']); ?>"
                        required 
                        maxlength="200"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
                        placeholder="Enter course title..."
                    >
                    <div class="mt-2 flex justify-between items-center">
                        <p class="text-sm text-gray-500">A clear, descriptive title for your course</p>
                        <span class="text-xs text-gray-400" id="titleCounter">0/200</span>
                    </div>
                </div>
                
                <!-- Course Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Course Description <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        id="description"
                        name="description" 
                        rows="6"
                        required
                        maxlength="1000"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
                        placeholder="Describe the course objectives, content, and what students will learn..."
                    ><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                    <div class="mt-2 flex justify-between items-center">
                        <p class="text-sm text-gray-500">Provide a detailed overview of the course content and learning outcomes</p>
                        <span class="text-xs text-gray-400" id="descCounter">0/1000</span>
                    </div>
                </div>
                
                <!-- Course Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Course Status</label>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <label class="flex items-center cursor-pointer">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                name="is_active" 
                                value="1"
                                <?php echo $course['is_active'] ? 'checked' : ''; ?>
                                <?php echo $has_student_progress && !$course['is_active'] ? 'disabled title="Cannot deactivate - students have started this course"' : ''; ?>
                                class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 focus:ring-2 <?php echo $has_student_progress && !$course['is_active'] ? 'cursor-not-allowed' : ''; ?>"
                            >
                            <span class="ml-3">
                                <span class="text-sm font-medium text-gray-700">Active Course</span>
                                <span class="block text-xs text-gray-500 mt-1">
                                    <?php if ($has_student_progress): ?>
                                        ⚠️ Cannot deactivate - students have enrolled in this course
                                    <?php else: ?>
                                        Active courses are visible to students and can be enrolled in
                                    <?php endif; ?>
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex flex-col md:flex-row gap-4 pt-4 border-t border-gray-200">
                    <button 
                        type="submit" 
                        class="flex-1 bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-300 flex items-center justify-center"
                        id="submitBtn"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Update Course
                    </button>
                    
                    <a 
                        href="courses.php" 
                        class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-300 text-center"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Course Statistics -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Course Statistics</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Modules</span>
                    <span class="font-semibold text-gray-900"><?php echo $course_stats['module_count'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Lessons</span>
                    <span class="font-semibold text-gray-900"><?php echo $course_stats['lesson_count'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Quizzes</span>
                    <span class="font-semibold text-gray-900"><?php echo $course_stats['quiz_count'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Students Enrolled</span>
                    <span class="font-semibold text-gray-900"><?php echo $course_stats['student_count'] ?? 0; ?></span>
                </div>
                <?php if ($course_stats['avg_quiz_score']): ?>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Avg Quiz Score</span>
                    <span class="font-semibold text-gray-900"><?php echo round($course_stats['avg_quiz_score'], 1); ?>%</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-3">
                <a href="module-add.php?course_id=<?php echo $course_id; ?>" 
                   class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300 text-center block">
                    Add Module
                </a>
                <a href="modules.php?course_id=<?php echo $course_id; ?>" 
                   class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-300 text-center block">
                    View All Modules
                </a>
                <a href="courses.php" 
                   class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-300 text-center block">
                    All Courses
                </a>
            </div>
        </div>

        <!-- Recent Modules -->
        <?php if (!empty($recent_modules)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Recent Modules</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <?php foreach ($recent_modules as $module): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900 text-sm">
                                <?php echo htmlspecialchars($module['title']); ?>
                            </h4>
                            <p class="text-xs text-gray-500 mt-1">
                                Order: <?php echo $module['order_sequence']; ?>
                                <?php if (!$module['is_active']): ?>
                                    <span class="text-red-500">• Inactive</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="module-edit.php?id=<?php echo $module['id']; ?>" 
                           class="text-indigo-600 hover:text-indigo-800 text-sm">
                            Edit
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($recent_modules) >= 5): ?>
                <div class="mt-3 text-center">
                    <a href="modules.php?course_id=<?php echo $course_id; ?>" 
                       class="text-indigo-600 hover:text-indigo-800 text-sm">
                        View All Modules →
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Danger Zone -->
        <?php if (!$has_student_progress): ?>
        <div class="bg-white rounded-xl shadow-sm border border-red-200">
            <div class="p-6 border-b border-red-200">
                <h3 class="text-lg font-semibold text-red-900">Danger Zone</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-red-700 mb-4">
                    Once you delete a course, there is no going back. Please be certain.
                </p>
                <button 
                    type="button"
                    onclick="confirmDelete()"
                    class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-300"
                >
                    Delete Course
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('courseEditForm');
    const titleInput = document.getElementById('title');
    const descriptionTextarea = document.getElementById('description');
    const titleCounter = document.getElementById('titleCounter');
    const descCounter = document.getElementById('descCounter');
    const submitBtn = document.getElementById('submitBtn');
    
    // Character counters
    function updateTitleCounter() {
        const count = titleInput.value.length;
        titleCounter.textContent = `${count}/200`;
        titleCounter.className = count > 180 ? 'text-xs text-orange-500' : count > 200 ? 'text-xs text-red-500' : 'text-xs text-gray-400';
    }
    
    function updateDescCounter() {
        const count = descriptionTextarea.value.length;
        descCounter.textContent = `${count}/1000`;
        descCounter.className = count > 900 ? 'text-xs text-orange-500' : count > 1000 ? 'text-xs text-red-500' : 'text-xs text-gray-400';
    }
    
    titleInput.addEventListener('input', updateTitleCounter);
    descriptionTextarea.addEventListener('input', updateDescCounter);
    
    // Initial counter update
    updateTitleCounter();
    updateDescCounter();
    
    // Form validation
    if (form) {
        form.addEventListener('submit', function(e) {
            const title = titleInput.value.trim();
            const description = descriptionTextarea.value.trim();
            
            if (!title) {
                e.preventDefault();
                showError('Please enter a course title.');
                titleInput.focus();
                return;
            }
            
            if (title.length > 200) {
                e.preventDefault();
                showError('Course title must be 200 characters or less.');
                titleInput.focus();
                return;
            }
            
            if (!description) {
                e.preventDefault();
                showError('Please enter a course description.');
                descriptionTextarea.focus();
                return;
            }
            
            if (description.length > 1000) {
                e.preventDefault();
                showError('Course description must be 1000 characters or less.');
                descriptionTextarea.focus();
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Updating Course...
            `;
        });
    }
    
    // Helper functions
    function showError(message) {
        // Remove existing error alerts
        const existingAlert = document.querySelector('.error-alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        // Create new error alert
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-alert mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg';
        errorDiv.innerHTML = `
            <div class="flex items-start">
                <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <span>${message}</span>
            </div>
        `;
        
        // Insert before the form
        if (form) {
            form.parentNode.insertBefore(errorDiv, form);
        }
        
        // Scroll to error
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
});

// Delete confirmation
function confirmDelete() {
    if (confirm('Are you sure you want to delete this course? This action cannot be undone and will remove all associated modules, lessons, and quizzes.')) {
        // Create form to submit delete request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'course-delete.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'course_id';
        input.value = '<?php echo $course_id; ?>';
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>