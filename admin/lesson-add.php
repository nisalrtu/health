<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Add New Lesson";

// Get module ID from URL if provided
$preselected_module = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $module_id = intval($_POST['module_id']);
        $estimated_duration = !empty($_POST['estimated_duration']) ? intval($_POST['estimated_duration']) : null;
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Lesson title is required.";
        }
        
        if (empty($content)) {
            $errors[] = "Lesson content is required.";
        }
        
        if ($module_id <= 0) {
            $errors[] = "Please select a valid module.";
        }
        
        if (!empty($_POST['estimated_duration']) && $estimated_duration <= 0) {
            $errors[] = "Estimated duration must be a positive number.";
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
                $errors[] = "Cannot add lesson to inactive module.";
            } elseif (!$module['course_active']) {
                $errors[] = "Cannot add lesson to module in inactive course.";
            }
        }
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            // Get the next order sequence for this module
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_sequence), 0) + 1 as next_order FROM lessons WHERE module_id = ?");
            $stmt->execute([$module_id]);
            $order_sequence = $stmt->fetch()['next_order'];
            
            // Insert the new lesson
            $stmt = $pdo->prepare("
                INSERT INTO lessons (module_id, title, content, order_sequence, estimated_duration) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $module_id,
                $title,
                $content,
                $order_sequence,
                $estimated_duration
            ]);
            
            $lesson_id = $pdo->lastInsertId();
            
            // Redirect to lessons page with success message
            $_SESSION['success_message'] = "Lesson '{$title}' has been created successfully.";
            header('Location: lessons.php' . ($preselected_module ? '?module_id=' . $preselected_module : ''));
            exit();
        }
        
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    }
}

// Get all modules with their course information for the dropdown
try {
    $stmt = $pdo->query("
        SELECT m.id, m.title, m.is_active, c.title as course_title, c.is_active as course_active
        FROM modules m 
        JOIN courses c ON m.course_id = c.id 
        WHERE m.is_active = 1 AND c.is_active = 1
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
            SELECT m.id, m.title, c.title as course_title
            FROM modules m 
            JOIN courses c ON m.course_id = c.id 
            WHERE m.id = ? AND m.is_active = 1 AND c.is_active = 1
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
    <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Add New Lesson</h1>
                <p class="text-green-100 text-lg">Create engaging learning content for your students</p>
                <?php if ($preselected_module_info): ?>
                    <div class="mt-3 inline-flex items-center bg-white/20 rounded-lg px-3 py-1">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <span class="text-sm">
                            Adding to: <?php echo htmlspecialchars($preselected_module_info['course_title'] . ' → ' . $preselected_module_info['title']); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                <a href="lessons.php<?php echo $preselected_module ? '?module_id=' . $preselected_module : ''; ?>" 
                   class="bg-white text-green-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    ← Back to Lessons
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

<!-- Lesson Add Form -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Lesson Information</h2>
        <p class="text-gray-600 mt-1">Fill in the details for your new lesson</p>
    </div>
    
    <form method="POST" class="p-6 space-y-6" id="lessonForm">
        <!-- Module Selection -->
        <div>
            <label for="module_id" class="block text-sm font-medium text-gray-700 mb-2">
                Module <span class="text-red-500">*</span>
            </label>
            <select 
                id="module_id" 
                name="module_id" 
                required
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
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
                        <?php echo (isset($_POST['module_id']) && $_POST['module_id'] == $module['id']) || ($preselected_module == $module['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($module['title']); ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($current_course !== '') echo '</optgroup>'; ?>
            </select>
            
            <?php if ($preselected_module_info): ?>
                <input type="hidden" name="module_id" value="<?php echo $preselected_module; ?>">
                <p class="mt-2 text-sm text-green-600">
                    ✓ Lesson will be added to: <strong><?php echo htmlspecialchars($preselected_module_info['course_title'] . ' → ' . $preselected_module_info['title']); ?></strong>
                </p>
            <?php endif; ?>
            
            <?php if (empty($modules)): ?>
                <p class="mt-2 text-sm text-red-600">
                    No active modules available. <a href="module-add.php" class="underline">Create a module first</a>.
                </p>
            <?php endif; ?>
        </div>

        <!-- Lesson Title -->
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                Lesson Title <span class="text-red-500">*</span>
            </label>
            <input 
                type="text" 
                id="title" 
                name="title" 
                value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                required
                maxlength="200"
                placeholder="Enter a clear, descriptive lesson title..."
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
            >
            <div class="flex justify-between mt-1">
                <p class="text-sm text-gray-500">A clear, engaging title that describes the lesson content</p>
                <span class="text-sm text-gray-400" id="titleCounter">0/200</span>
            </div>
        </div>

        <!-- Estimated Duration -->
        <div>
            <label for="estimated_duration" class="block text-sm font-medium text-gray-700 mb-2">
                Estimated Duration (minutes)
            </label>
            <div class="relative">
                <input 
                    type="number" 
                    id="estimated_duration" 
                    name="estimated_duration" 
                    value="<?php echo htmlspecialchars($_POST['estimated_duration'] ?? ''); ?>"
                    min="1" 
                    max="300"
                    placeholder="e.g., 15"
                    class="w-full px-4 py-3 pr-20 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                >
                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                    <span class="text-gray-500 text-sm">minutes</span>
                </div>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                Estimated time for students to complete this lesson (optional)
            </p>
        </div>

        <!-- Lesson Content -->
        <div>
            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                Lesson Content <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <textarea 
                    id="content" 
                    name="content" 
                    rows="12"
                    required
                    placeholder="Write your lesson content here. You can include:&#10;&#10;• Learning objectives&#10;• Key concepts and explanations&#10;• Examples and case studies&#10;• Instructions and activities&#10;• Resources and references&#10;&#10;Use clear headings and bullet points to organize your content..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 font-mono text-sm"
                ><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                <div class="absolute bottom-3 right-3 text-xs text-gray-400" id="contentCounter">
                    0 characters
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                <span>💡 Tip: Structure your content with clear headings</span>
                <span>📝 Use bullet points for better readability</span>
                <span>🔗 Include relevant examples</span>
            </div>
        </div>

        <!-- Content Formatting Help -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-semibold text-blue-900 mb-2">📋 Content Formatting Tips</h4>
            <div class="grid md:grid-cols-2 gap-3 text-sm text-blue-800">
                <div>
                    <h5 class="font-medium mb-1">Structure Your Lesson:</h5>
                    <ul class="space-y-1 list-disc list-inside text-xs">
                        <li>Start with learning objectives</li>
                        <li>Break content into logical sections</li>
                        <li>Use headings and subheadings</li>
                        <li>Include practical examples</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-medium mb-1">Enhance Readability:</h5>
                    <ul class="space-y-1 list-disc list-inside text-xs">
                        <li>Use bullet points for lists</li>
                        <li>Keep paragraphs concise</li>
                        <li>Highlight key terms</li>
                        <li>Add summary points</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
            <button 
                type="submit" 
                id="submitBtn"
                class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-300"
                <?php echo empty($modules) ? 'disabled' : ''; ?>
            >
                <span class="flex items-center justify-center" id="submitBtnContent">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create Lesson
                </span>
            </button>
            
            <a 
                href="lessons.php<?php echo $preselected_module ? '?module_id=' . $preselected_module : ''; ?>" 
                class="flex-1 bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-300 text-center"
            >
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- Content Guidelines Section -->
<div class="mt-8 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-emerald-900 mb-4">✨ Creating Effective Lessons</h3>
    <div class="grid md:grid-cols-3 gap-6 text-sm text-emerald-800">
        <div>
            <h4 class="font-semibold mb-3 flex items-center">
                <span class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs mr-2">1</span>
                Start Strong
            </h4>
            <ul class="space-y-2 list-disc list-inside">
                <li>Begin with clear learning objectives</li>
                <li>Explain why the topic matters</li>
                <li>Preview what students will learn</li>
                <li>Connect to previous lessons</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-3 flex items-center">
                <span class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs mr-2">2</span>
                Engage Students
            </h4>
            <ul class="space-y-2 list-disc list-inside">
                <li>Use real-world examples</li>
                <li>Include interactive elements</li>
                <li>Ask thought-provoking questions</li>
                <li>Share case studies</li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-3 flex items-center">
                <span class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs mr-2">3</span>
                Wrap Up Well
            </h4>
            <ul class="space-y-2 list-disc list-inside">
                <li>Summarize key takeaways</li>
                <li>Reinforce main concepts</li>
                <li>Prepare for next lesson</li>
                <li>Provide additional resources</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('lessonForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitBtnContent = document.getElementById('submitBtnContent');
    const titleInput = document.getElementById('title');
    const contentTextarea = document.getElementById('content');
    const moduleSelect = document.getElementById('module_id');
    
    // Auto-focus on title input
    titleInput.focus();
    
    // Character counters
    const titleCounter = document.getElementById('titleCounter');
    const contentCounter = document.getElementById('contentCounter');
    
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
    
    contentTextarea.addEventListener('input', function() {
        const length = this.value.length;
        const words = this.value.trim().split(/\s+/).filter(word => word.length > 0).length;
        contentCounter.textContent = `${length.toLocaleString()} characters • ${words.toLocaleString()} words`;
        
        if (length < 100) {
            contentCounter.className = 'absolute bottom-3 right-3 text-xs text-red-400';
        } else if (length < 300) {
            contentCounter.className = 'absolute bottom-3 right-3 text-xs text-yellow-500';
        } else {
            contentCounter.className = 'absolute bottom-3 right-3 text-xs text-green-500';
        }
    });
    
    // Initial counter updates
    titleInput.dispatchEvent(new Event('input'));
    contentTextarea.dispatchEvent(new Event('input'));
    
    // Auto-resize textarea
    function autoResize() {
        contentTextarea.style.height = 'auto';
        contentTextarea.style.height = Math.max(300, contentTextarea.scrollHeight) + 'px';
    }
    
    contentTextarea.addEventListener('input', autoResize);
    autoResize(); // Initial resize
    
    // Form validation and submission
    form.addEventListener('submit', function(e) {
        const module = moduleSelect.value;
        const title = titleInput.value.trim();
        const content = contentTextarea.value.trim();
        
        // Validation
        if (!module) {
            e.preventDefault();
            alert('Please select a module for this lesson.');
            moduleSelect.focus();
            return;
        }
        
        if (!title) {
            e.preventDefault();
            alert('Please enter a lesson title.');
            titleInput.focus();
            return;
        }
        
        if (!content) {
            e.preventDefault();
            alert('Please enter lesson content.');
            contentTextarea.focus();
            return;
        }
        
        if (content.length < 50) {
            e.preventDefault();
            alert('Please provide more detailed content for the lesson (at least 50 characters).');
            contentTextarea.focus();
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtnContent.innerHTML = `
            <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Creating Lesson...
        `;
    });
    
    // Estimated duration visual feedback
    const durationInput = document.getElementById('estimated_duration');
    
    if (durationInput) {
        const durationFeedback = document.createElement('div');
        durationFeedback.className = 'text-sm mt-1';
        durationInput.parentNode.appendChild(durationFeedback);
        
        function updateDurationFeedback() {
            const value = parseInt(durationInput.value);
            if (isNaN(value) || value <= 0) {
                durationFeedback.textContent = '';
                return;
            }
            
            let feedback = '';
            let colorClass = 'text-gray-600';
            
            if (value <= 5) {
                feedback = 'Very short lesson - ideal for quick concepts';
                colorClass = 'text-blue-600';
            } else if (value <= 15) {
                feedback = 'Short lesson - perfect for focused topics';
                colorClass = 'text-green-600';
            } else if (value <= 30) {
                feedback = 'Standard lesson - good for detailed explanations';
                colorClass = 'text-green-600';
            } else if (value <= 60) {
                feedback = 'Long lesson - ensure content is engaging';
                colorClass = 'text-yellow-600';
            } else {
                feedback = 'Very long lesson - consider breaking into parts';
                colorClass = 'text-red-600';
            }
            
            durationFeedback.textContent = feedback;
            durationFeedback.className = `text-sm mt-1 ${colorClass}`;
        }
        
        durationInput.addEventListener('input', updateDurationFeedback);
        updateDurationFeedback(); // Initial update
    }
    
    // Save draft functionality (optional enhancement)
    let draftTimer;
    const draftIndicator = document.createElement('div');
    draftIndicator.className = 'text-xs text-gray-500 mt-2';
    draftIndicator.style.display = 'none';
    form.appendChild(draftIndicator);
    
    function saveDraft() {
        const draftData = {
            title: titleInput.value,
            content: contentTextarea.value,
            module_id: moduleSelect.value,
            estimated_duration: durationInput.value,
            timestamp: Date.now()
        };
        
        localStorage.setItem('lesson_draft', JSON.stringify(draftData));
        draftIndicator.textContent = 'Draft saved automatically';
        draftIndicator.style.display = 'block';
        
        setTimeout(() => {
            draftIndicator.style.display = 'none';
        }, 2000);
    }
    
    function autoSaveDraft() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 3000); // Save after 3 seconds of inactivity
    }
    
    [titleInput, contentTextarea].forEach(input => {
        input.addEventListener('input', autoSaveDraft);
    });
    
    // Load draft on page load
    const savedDraft = localStorage.getItem('lesson_draft');
    if (savedDraft && !titleInput.value && !contentTextarea.value) {
        try {
            const draft = JSON.parse(savedDraft);
            const age = Date.now() - draft.timestamp;
            
            // Only load drafts less than 24 hours old
            if (age < 24 * 60 * 60 * 1000) {
                if (confirm('Found a saved draft from earlier. Would you like to restore it?')) {
                    titleInput.value = draft.title || '';
                    contentTextarea.value = draft.content || '';
                    if (draft.module_id && !moduleSelect.disabled) {
                        moduleSelect.value = draft.module_id;
                    }
                    if (draft.estimated_duration) {
                        durationInput.value = draft.estimated_duration;
                    }
                    
                    // Update counters
                    titleInput.dispatchEvent(new Event('input'));
                    contentTextarea.dispatchEvent(new Event('input'));
                    if (durationInput.value) {
                        updateDurationFeedback();
                    }
                }
            }
        } catch (e) {
            // Invalid draft data, ignore
        }
    }
    
    // Clear draft on successful submission
    form.addEventListener('submit', function() {
        localStorage.removeItem('lesson_draft');
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
    
    // Content suggestions based on title
    let suggestionTimer;
    titleInput.addEventListener('input', function() {
        clearTimeout(suggestionTimer);
        suggestionTimer = setTimeout(() => {
            generateContentSuggestions(this.value);
        }, 1000);
    });
    
    function generateContentSuggestions(title) {
        if (!title || title.length < 5 || contentTextarea.value.length > 100) return;
        
        const suggestions = [];
        const lowerTitle = title.toLowerCase();
        
        // Basic content structure suggestions based on title keywords
        if (lowerTitle.includes('introduction') || lowerTitle.includes('intro')) {
            suggestions.push("Start with: 'In this lesson, we will explore...'");
            suggestions.push("Include: definition, importance, overview");
        }
        
        if (lowerTitle.includes('benefit') || lowerTitle.includes('advantage')) {
            suggestions.push("Structure: List key benefits, provide evidence");
            suggestions.push("Include: real-world examples, research findings");
        }
        
        if (lowerTitle.includes('how to') || lowerTitle.includes('guide')) {
            suggestions.push("Use step-by-step format");
            suggestions.push("Include: practical tips, common mistakes to avoid");
        }
        
        if (suggestions.length > 0) {
            const existingSuggestion = document.getElementById('contentSuggestion');
            if (existingSuggestion) {
                existingSuggestion.remove();
            }
            
            const suggestionDiv = document.createElement('div');
            suggestionDiv.id = 'contentSuggestion';
            suggestionDiv.className = 'mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg';
            suggestionDiv.innerHTML = `
                <h5 class="font-medium text-yellow-800 mb-1">💡 Content Suggestions:</h5>
                <ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">
                    ${suggestions.map(s => `<li>${s}</li>`).join('')}
                </ul>
                <button type="button" onclick="this.parentElement.remove()" class="text-xs text-yellow-600 underline mt-1">Dismiss</button>
            `;
            
            contentTextarea.parentNode.appendChild(suggestionDiv);
        }
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
