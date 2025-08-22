<?php
session_start();
require_once '../config/db.php';

// Set page title
$page_title = "My Profile";

$error_message = '';
$success_message = '';
$student_id = $_SESSION['student_id'];

// Get current student data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        header('Location: ../student-login.php');
        exit();
    }
} catch(PDOException $e) {
    $error_message = 'Error loading profile data.';
    $student = [];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $institute_name = trim($_POST['institute_name']);
        $age = intval($_POST['age']);
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif ($age < 10 || $age > 100) {
            $error_message = 'Please enter a valid age between 10 and 100.';
        } else {
            try {
                // Check if email is already taken by another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $student_id]);
                
                if ($stmt->rowCount() > 0) {
                    $error_message = 'This email address is already in use by another account.';
                } else {
                    // Update profile
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, institute_name = ?, age = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $email, $institute_name, $age, $student_id]);
                    
                    // Update session data
                    $_SESSION['student_email'] = $email;
                    $_SESSION['student_name'] = $first_name . ' ' . $last_name;
                    
                    $success_message = 'Profile updated successfully!';
                    
                    // Refresh student data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                }
            } catch(PDOException $e) {
                $error_message = 'Error updating profile. Please try again.';
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all password fields.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'New password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (!password_verify($current_password, $student['password_hash'])) {
            $error_message = 'Current password is incorrect.';
        } else {
            try {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_password_hash, $student_id]);
                
                $success_message = 'Password changed successfully!';
            } catch(PDOException $e) {
                $error_message = 'Error changing password. Please try again.';
            }
        }
    }
}

// Get student statistics for display
try {
    // Course statistics
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT course_id) as enrolled_courses FROM user_progress WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $enrolled_courses = $stmt->fetch()['enrolled_courses'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_courses FROM certificates WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $completed_courses = $stmt->fetch()['completed_courses'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_modules FROM user_progress WHERE user_id = ? AND module_id IS NOT NULL AND status = 'completed'");
    $stmt->execute([$student_id]);
    $completed_modules = $stmt->fetch()['completed_modules'];
    
    // Recent activity
    $stmt = $pdo->prepare("
        SELECT c.title, up.completed_at
        FROM user_progress up
        JOIN courses c ON up.course_id = c.id
        WHERE up.user_id = ? AND up.completed_at IS NOT NULL
        ORDER BY up.completed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$student_id]);
    $recent_activities = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $enrolled_courses = 0;
    $completed_courses = 0;
    $completed_modules = 0;
    $recent_activities = [];
}

// Include header
include '../includes/student-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="rounded-xl p-6 text-white" style="background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2 text-white">
                    My Profile 👤
                </h1>
                <p class="text-white opacity-90 text-lg">
                    Manage your account information and settings
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <div class="flex items-center bg-white bg-opacity-20 rounded-lg p-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-xl mr-3" style="background-color: #5fb3b4;">
                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="text-white font-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                        <div class="text-white opacity-90 text-sm">Student</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error_message): ?>
    <div class="mb-6">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div class="mb-6">
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Profile Form -->
    <div class="lg:col-span-2 space-y-8">
        <!-- Personal Information -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: #5fb3b4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Personal Information
                </h2>
            </div>
            <div class="p-6">
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- First Name -->
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                First Name *
                            </label>
                            <input 
                                id="first_name" 
                                name="first_name" 
                                type="text" 
                                required 
                                value="<?php echo htmlspecialchars($student['first_name']); ?>"
                                class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-saffron-teal focus:border-transparent"
                                placeholder="Enter your first name"
                            >
                        </div>
                        
                        <!-- Last Name -->
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Last Name *
                            </label>
                            <input 
                                id="last_name" 
                                name="last_name" 
                                type="text" 
                                required 
                                value="<?php echo htmlspecialchars($student['last_name']); ?>"
                                class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent"
                                placeholder="Enter your last name"
                            >
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address *
                        </label>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            required 
                            value="<?php echo htmlspecialchars($student['email']); ?>"
                            class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent"
                            placeholder="Enter your email address"
                        >
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Institute Name -->
                        <div>
                            <label for="institute_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Institute/School Name
                            </label>
                            <input 
                                id="institute_name" 
                                name="institute_name" 
                                type="text" 
                                value="<?php echo htmlspecialchars($student['institute_name']); ?>"
                                class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent"
                                placeholder="Your school/institute"
                            >
                        </div>
                        
                        <!-- Age -->
                        <div>
                            <label for="age" class="block text-sm font-medium text-gray-700 mb-2">
                                Age *
                            </label>
                            <input 
                                id="age" 
                                name="age" 
                                type="number" 
                                min="10" 
                                max="100" 
                                required 
                                value="<?php echo htmlspecialchars($student['age']); ?>"
                                class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent"
                                placeholder="Your age"
                            >
                        </div>
                    </div>
                    
                    <!-- Account Info -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-2">Account Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Member since:</span>
                                <?php echo date('F j, Y', strtotime($student['created_at'])); ?>
                            </div>
                            <div>
                                <span class="font-medium">Last updated:</span>
                                <?php echo date('F j, Y', strtotime($student['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button 
                            type="submit" 
                            name="update_profile"
                            class="px-6 py-3 text-white rounded-lg font-medium hover:bg-opacity-90 transition duration-300"
                            style="background-color: #5fb3b4;"
                        >
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: #5fb3b4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Change Password
                </h2>
            </div>
            <div class="p-6">
                <form method="POST" class="space-y-6" id="passwordForm">
                    <!-- Current Password -->
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Current Password *
                        </label>
                        <div class="relative">
                            <input 
                                id="current_password" 
                                name="current_password" 
                                type="password" 
                                class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent pr-10"
                                placeholder="Enter your current password"
                            >
                        </div>
                    </div>
                    
                    <!-- New Password -->
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                            New Password *
                        </label>
                        <div class="relative">
                            <input 
                                id="new_password" 
                                name="new_password" 
                                type="password" 
                                class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent pr-10"
                                placeholder="Enter new password (min 6 characters)"
                            >
                        </div>
                    </div>
                    
                    <!-- Confirm New Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm New Password *
                        </label>
                        <input 
                            id="confirm_password" 
                            name="confirm_password" 
                            type="password" 
                            class="w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:border-transparent"
                            placeholder="Confirm your new password"
                        >
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button 
                            type="submit" 
                            name="change_password"
                            class="px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition duration-300"
                        >
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Learning Statistics -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Learning Statistics</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">Courses Enrolled</div>
                            <div class="text-sm text-gray-600">Active learning</div>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $enrolled_courses; ?></div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">Modules Completed</div>
                            <div class="text-sm text-gray-600">Learning progress</div>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $completed_modules; ?></div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">Certificates</div>
                            <div class="text-sm text-gray-600">Achievements</div>
                        </div>
                    </div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $completed_courses; ?></div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <?php if (!empty($recent_activities)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background-color: #5fb3b4;">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 text-sm">
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <?php echo date('M j, Y', strtotime($activity['completed_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center pt-4">
                    <a href="progress.php" class="font-medium text-sm" style="color: #5fb3b4;">
                        View All Progress →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
            </div>
            <div class="p-6 space-y-3">
                <a href="courses.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Browse Courses</div>
                        <div class="text-sm text-gray-600">Find new courses</div>
                    </div>
                </a>
                
                <a href="certificates.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">My Certificates</div>
                        <div class="text-sm text-gray-600">View achievements</div>
                    </div>
                </a>
                
                <a href="dashboard.php" class="flex items-center p-3 rounded-lg hover:bg-gray-50 transition duration-300">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v3H8V5z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">Dashboard</div>
                        <div class="text-sm text-gray-600">Learning overview</div>
                    </div>
                </a>
            </div>
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

    // Form validation and enhancements
    const profileForm = document.querySelector('form[method="POST"]');
    const passwordForm = document.getElementById('passwordForm');
    
    // Profile form validation
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const age = parseInt(document.getElementById('age').value);
            
            if (!firstName || !lastName || !email) {
                e.preventDefault();
                showAlert('Please fill in all required fields.', 'error');
                return;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                showAlert('Please enter a valid email address.', 'error');
                return;
            }
            
            if (age < 10 || age > 100) {
                e.preventDefault();
                showAlert('Please enter a valid age between 10 and 100.', 'error');
                return;
            }
        });
    }
    
    // Password form validation
    if (passwordForm) {
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        // Real-time password confirmation
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value && newPasswordInput.value !== this.value) {
                this.classList.add('border-red-500');
                this.classList.remove('border-gray-300');
            } else {
                this.classList.remove('border-red-500');
                this.classList.add('border-gray-300');
            }
        });
        
        passwordForm.addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                showAlert('Please fill in all password fields.', 'error');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                showAlert('New password must be at least 6 characters long.', 'error');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showAlert('New passwords do not match.', 'error');
                return;
            }
        });
    }
    
    // Helper function to validate email
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Helper function to show alerts
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 ${
            type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700'
        }`;
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    ${type === 'error' 
                        ? '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>'
                        : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'
                    }
                </svg>
                ${message}
            </div>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            alertDiv.style.transform = 'translateY(-10px)';
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    }
    
    // Auto-hide success/error messages
    const alertMessages = document.querySelectorAll('.bg-red-100, .bg-green-100');
    alertMessages.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'all 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
    
    // Form field enhancements
    const formInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="number"], input[type="password"]');
    formInputs.forEach(input => {
        // Add focus styling
        input.addEventListener('focus', function() {
            this.style.borderColor = '#5fb3b4';
            this.style.boxShadow = '0 0 0 3px rgba(95, 179, 180, 0.1)';
        });
        
        input.addEventListener('blur', function() {
            this.style.borderColor = '#d1d5db';
            this.style.boxShadow = 'none';
        });
        
        // Validation styling
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('border-red-500');
                this.classList.remove('border-gray-300');
            } else {
                this.classList.remove('border-red-500');
                this.classList.add('border-gray-300');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('border-red-500') && this.value.trim()) {
                this.classList.remove('border-red-500');
                this.classList.add('border-gray-300');
            }
        });
    });
    
    // Page animations
    const sections = document.querySelectorAll('.bg-white');
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            section.style.transition = 'all 0.6s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Welcome message animation
    const headerSection = document.querySelector('[style*="background: linear-gradient"]');
    if (headerSection) {
        headerSection.style.opacity = '0';
        headerSection.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            headerSection.style.transition = 'all 0.8s ease';
            headerSection.style.opacity = '1';
            headerSection.style.transform = 'translateY(0)';
        }, 200);
    }
    
    // Clear password fields on page load for security
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        field.value = '';
    });
});
</script>
</body>
</html>