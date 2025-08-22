<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Add New Course";

// Initialize variables
$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($title)) {
            $errors[] = "Course title is required.";
        } elseif (strlen($title) > 200) {
            $errors[] = "Course title must be 200 characters or less.";
        }
        
        if (empty($description)) {
            $errors[] = "Course description is required.";
        } elseif (strlen($description) > 1000) {
            $errors[] = "Course description must be 1000 characters or less.";
        }
        
        // Check if course title already exists
        if (!empty($title)) {
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE title = ?");
            $stmt->execute([$title]);
            if ($stmt->fetch()) {
                $errors[] = "A course with this title already exists.";
            }
        }
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO courses (title, description, is_active) 
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $description,
                $is_active
            ]);
            
            $course_id = $pdo->lastInsertId();
            
            // Redirect to courses page with success message
            $_SESSION['success_message'] = "Course '{$title}' has been created successfully.";
            header('Location: courses.php');
            exit();
        }
        
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
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
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Add New Course</h1>
                <p class="text-purple-100 text-lg">Create a new course for your lifestyle medicine curriculum</p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="courses.php" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    ← Back to Courses
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <div class="flex items-start">
            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <h3 class="font-semibold mb-2">Please fix the following errors:</h3>
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Course Add Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Course Information</h2>
        <p class="text-gray-600 mt-1">Fill in the details for your new course</p>
    </div>
    
    <form method="POST" class="p-6 space-y-6" id="courseForm">
        <!-- Course Title -->
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                Course Title <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                id="title" 
                name="title" 
                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                required
                maxlength="200"
                placeholder="Enter course title..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
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
                placeholder="Describe the course objectives, content, and what students will learn..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-300"
            ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
                        <?php echo (isset($_POST['is_active']) || !isset($_POST['title'])) ? 'checked' : ''; ?>
                        class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 focus:ring-2"
                    >
                    <span class="ml-3">
                        <span class="text-sm font-medium text-gray-700">Active Course</span>
                        <span class="block text-xs text-gray-500 mt-1">
                            Active courses are visible to students and can be enrolled in
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Create Course
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

<!-- Tips Section -->
<div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-blue-900 mb-3">💡 Course Creation Tips</h3>
    <div class="grid md:grid-cols-2 gap-6 text-sm text-blue-800">
        <div>
            <h4 class="font-semibold mb-2">Course Structure</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>Start with a clear learning objective</li>
                <li>Break content into logical modules</li>
                <li>Include interactive assessments</li>
                <li>Consider prerequisite knowledge</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-2">Best Practices</h4>
            <ul class="space-y-1 list-disc list-inside">
                <li>Use descriptive, engaging titles</li>
                <li>Write clear course descriptions</li>
                <li>Set realistic completion timeframes</li>
                <li>Test your course before publishing</li>
            </ul>
        </div>
    </div>
</div>

<!-- Next Steps Section -->
<div class="mt-6 bg-green-50 border border-green-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-green-900 mb-3">🚀 What's Next?</h3>
    <p class="text-green-800 mb-3">After creating your course, you'll be able to:</p>
    <div class="grid md:grid-cols-3 gap-4 text-sm text-green-700">
        <div class="flex items-start">
            <span class="bg-green-200 text-green-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mr-3 mt-0.5">1</span>
            <span>Add modules to organize your content</span>
        </div>
        <div class="flex items-start">
            <span class="bg-green-200 text-green-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mr-3 mt-0.5">2</span>
            <span>Create lessons with rich content</span>
        </div>
        <div class="flex items-start">
            <span class="bg-green-200 text-green-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold mr-3 mt-0.5">3</span>
            <span>Build quizzes and assessments</span>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('courseForm');
    const titleInput = document.getElementById('title');
    const descriptionTextarea = document.getElementById('description');
    const titleCounter = document.getElementById('titleCounter');
    const descCounter = document.getElementById('descCounter');
    const submitBtn = document.getElementById('submitBtn');
    
    // Auto-focus on title input
    titleInput.focus();
    
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
            Creating Course...
        `;
    });
    
    // Real-time validation feedback
    titleInput.addEventListener('blur', function() {
        const title = this.value.trim();
        if (!title) {
            showFieldError(this, 'Course title is required.');
        } else if (title.length > 200) {
            showFieldError(this, 'Course title must be 200 characters or less.');
        } else {
            hideFieldError(this);
        }
    });
    
    descriptionTextarea.addEventListener('blur', function() {
        const description = this.value.trim();
        if (!description) {
            showFieldError(this, 'Course description is required.');
        } else if (description.length > 1000) {
            showFieldError(this, 'Course description must be 1000 characters or less.');
        } else {
            hideFieldError(this);
        }
    });
    
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
        form.parentNode.insertBefore(errorDiv, form);
        
        // Scroll to error
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
    
    function showFieldError(field, message) {
        hideFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error mt-1 text-sm text-red-600';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
        field.classList.add('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
    }
    
    function hideFieldError(field) {
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
        field.classList.remove('border-red-300', 'focus:border-red-500', 'focus:ring-red-500');
    }
});
</script>

<?php include '../includes/admin-footer.php'; ?>