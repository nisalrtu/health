<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Add New Module";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $course_id = intval($_POST['course_id']);
        $pass_threshold = intval($_POST['pass_threshold']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Module title is required.";
        }
        
        if (empty($description)) {
            $errors[] = "Module description is required.";
        }
        
        if ($course_id <= 0) {
            $errors[] = "Please select a valid course.";
        }
        
        if ($pass_threshold < 0 || $pass_threshold > 100) {
            $errors[] = "Pass threshold must be between 0 and 100.";
        }
        
        // Check if course exists and is active
        if ($course_id > 0) {
            $stmt = $pdo->prepare("SELECT id, is_active FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();
            
            if (!$course) {
                $errors[] = "Selected course does not exist.";
            } elseif (!$course['is_active']) {
                $errors[] = "Cannot add module to inactive course.";
            }
        }
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            // Get the next order sequence for this course
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_sequence), 0) + 1 as next_order FROM modules WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $order_sequence = $stmt->fetch()['next_order'];
            
            // Insert the new module
            $stmt = $pdo->prepare("
                INSERT INTO modules (title, description, course_id, order_sequence, pass_threshold, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $description,
                $course_id,
                $order_sequence,
                $pass_threshold,
                $is_active
            ]);
            
            $module_id = $pdo->lastInsertId();
            
            // Redirect to modules page with success message
            $_SESSION['success_message'] = "Module '{$title}' has been created successfully.";
            header('Location: modules.php');
            exit();
        }
        
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Get all active courses for the dropdown
try {
    $stmt = $pdo->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title ASC");
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $courses = [];
    $error_message = "Failed to load courses: " . $e->getMessage();
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Add New Module</h1>
                <p class="text-purple-100 text-lg">Create a new learning module for your course</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="modules.php" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    ← Back to Modules
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Module Add Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Module Information</h2>
        <p class="text-gray-600 mt-1">Fill in the details for your new module</p>
    </div>
    
    <form method="POST" class="p-6 space-y-6">
        <!-- Course Selection -->
        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">
                Course <span class="text-red-500">*</span>
            </label>
            <select 
                id="course_id" 
                name="course_id" 
                required
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
                <option value="">Select a course...</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($courses)): ?>
                <p class="mt-2 text-sm text-red-600">
                    No active courses available. <a href="course-add.php" class="underline">Create a course first</a>.
                </p>
            <?php endif; ?>
        </div>

        <!-- Module Title -->
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                Module Title <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                id="title" 
                name="title" 
                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                required
                placeholder="Enter module title..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            >
            <p class="mt-1 text-sm text-gray-500">A clear, descriptive title for this module</p>
        </div>

        <!-- Module Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                Description <span class="text-red-500">*</span>
            </label>
            <textarea 
                id="description" 
                name="description" 
                rows="4"
                required
                placeholder="Describe what students will learn in this module..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            <p class="mt-1 text-sm text-gray-500">Explain the learning objectives and content of this module</p>
        </div>

        <!-- Pass Threshold -->
        <div>
            <label for="pass_threshold" class="block text-sm font-medium text-gray-700 mb-2">
                Pass Threshold (%) <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <input 
                    type="number" 
                    id="pass_threshold" 
                    name="pass_threshold" 
                    value="<?php echo htmlspecialchars($_POST['pass_threshold'] ?? '70'); ?>"
                    min="0" 
                    max="100"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                    <span class="text-gray-500 text-sm">%</span>
                </div>
            </div>
            <p class="mt-1 text-sm text-gray-500">Minimum percentage required to pass quizzes in this module</p>
        </div>

        <!-- Module Status -->
        <div class="flex items-start">
            <div class="flex items-center h-5">
                <input 
                    id="is_active" 
                    name="is_active" 
                    type="checkbox" 
                    <?php echo (isset($_POST['is_active']) || !isset($_POST['title'])) ? 'checked' : ''; ?>
                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                >
            </div>
            <div class="ml-3">
                <label for="is_active" class="text-sm font-medium text-gray-700">
                    Active Module
                </label>
                <p class="text-sm text-gray-500">
                    Active modules are visible to students and can be accessed for learning
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
            <button 
                type="submit" 
                class="flex-1 bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-300"
                <?php echo empty($courses) ? 'disabled' : ''; ?>
            >
                <span class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create Module
                </span>
            </button>
            
            <a 
                href="modules.php" 
                class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-300 text-center"
            >
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- Tips Section -->
<div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-blue-900 mb-3">💡 Module Creation Tips</h3>
    <div class="grid md:grid-cols-2 gap-4 text-sm text-blue-800">
        <div>
            <h4 class="font-semibold mb-2">Structure Your Content</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>Break down complex topics into digestible modules</li>
                <li>Each module should have a clear learning objective</li>
                <li>Consider the logical flow of information</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-2">Set Appropriate Thresholds</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>70-80% is typical for most academic content</li>
                <li>Lower thresholds for introductory material</li>
                <li>Higher thresholds for critical safety content</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitButton = form.querySelector('button[type="submit"]');
    const titleInput = document.getElementById('title');
    const courseSelect = document.getElementById('course_id');
    
    // Auto-focus on title input
    titleInput.focus();
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const course = courseSelect.value;
        const title = titleInput.value.trim();
        
        if (!course) {
            e.preventDefault();
            alert('Please select a course for this module.');
            courseSelect.focus();
            return;
        }
        
        if (!title) {
            e.preventDefault();
            alert('Please enter a module title.');
            titleInput.focus();
            return;
        }
        
        // Show loading state
        submitButton.disabled = true;
        submitButton.innerHTML = `
            <span class="flex items-center justify-center">
                <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Creating Module...
            </span>
        `;
    });
    
    // Character counter for description
    const descriptionTextarea = document.getElementById('description');
    const maxLength = 1000; // You can adjust this
    
    // Create character counter element
    const counterDiv = document.createElement('div');
    counterDiv.className = 'text-sm text-gray-500 mt-1';
    counterDiv.textContent = `0/${maxLength} characters`;
    descriptionTextarea.parentNode.appendChild(counterDiv);
    
    descriptionTextarea.addEventListener('input', function() {
        const currentLength = this.value.length;
        counterDiv.textContent = `${currentLength}/${maxLength} characters`;
        
        if (currentLength > maxLength * 0.9) {
            counterDiv.className = 'text-sm text-amber-600 mt-1';
        } else {
            counterDiv.className = 'text-sm text-gray-500 mt-1';
        }
    });
    
    // Pass threshold slider visual feedback
    const thresholdInput = document.getElementById('pass_threshold');
    const thresholdDisplay = document.createElement('div');
    thresholdDisplay.className = 'text-sm font-medium mt-2';
    thresholdInput.parentNode.appendChild(thresholdDisplay);
    
    function updateThresholdDisplay() {
        const value = parseInt(thresholdInput.value);
        let color = 'text-green-600';
        let description = 'Easy';
        
        if (value >= 80) {
            color = 'text-red-600';
            description = 'Challenging';
        } else if (value >= 70) {
            color = 'text-yellow-600';
            description = 'Moderate';
        }
        
        thresholdDisplay.className = `text-sm font-medium mt-2 ${color}`;
        thresholdDisplay.textContent = `${value}% - ${description}`;
    }
    
    thresholdInput.addEventListener('input', updateThresholdDisplay);
    updateThresholdDisplay(); // Initial display
    
    // Auto-hide any error messages after 10 seconds
    const errorMessages = document.querySelectorAll('.bg-red-100');
    errorMessages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'all 0.5s ease';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                message.remove();
            }, 500);
        }, 10000);
    });
});
</script>

<?php include '../includes/admin-footer.php'; ?>