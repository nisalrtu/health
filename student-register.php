<?php
session_start();
require_once 'config/db.php';

$error_message = '';
$success_message = '';

// Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header('Location: student/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $institute_name = trim($_POST['institute_name']);
    $age = intval($_POST['age']);
    
    // Validation
    if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif ($age < 10 || $age > 100) {
        $error_message = 'Please enter a valid age between 10 and 100.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'An account with this email already exists.';
            } else {
                // Create new user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, institute_name, age) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$email, $password_hash, $first_name, $last_name, $institute_name, $age]);
                
                // Auto login after registration
                $_SESSION['student_id'] = $pdo->lastInsertId();
                $_SESSION['student_email'] = $email;
                $_SESSION['student_name'] = $first_name . ' ' . $last_name;
                
                header('Location: student/dashboard.php');
                exit();
            }
        } catch(PDOException $e) {
            $error_message = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Saffron Health</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom colors inspired by the logo */
        :root {
            --saffron-teal: #5fb3b4;
            --saffron-dark: #2d3748;
            --saffron-light: #e6fffa;
        }
        
        .bg-saffron-teal { background-color: var(--saffron-teal); }
        .text-saffron-teal { color: var(--saffron-teal); }
        .bg-saffron-dark { background-color: var(--saffron-dark); }
        .text-saffron-dark { color: var(--saffron-dark); }
        .bg-saffron-light { background-color: var(--saffron-light); }
        
        .hover-saffron-teal:hover { background-color: var(--saffron-teal); }
        .border-saffron-teal { border-color: var(--saffron-teal); }
        .focus-saffron-teal:focus { border-color: var(--saffron-teal); box-shadow: 0 0 0 3px rgba(95, 179, 180, 0.1); }
        
        /* Background gradient */
        .auth-gradient {
            background: linear-gradient(135deg, var(--saffron-teal) 0%, var(--saffron-dark) 100%);
        }
        
        /* Form animations */
        .form-input {
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            transform: translateY(-2px);
        }
        
        /* Loading animation */
        .loading {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Error shake animation */
        .error-shake {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <!-- Header -->
    <header class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="index.php" class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-saffron-dark">
                            <span class="text-saffron-teal">Saffron</span> Health
                        </h1>
                        <p class="text-xs text-gray-600 -mt-1">Your Partner in Well-Being</p>
                    </a>
                </div>
                
                <!-- Navigation Links -->
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-700 hover:text-saffron-teal transition duration-300">
                        Home
                    </a>
                    <a href="student-login.php" class="text-saffron-teal hover:text-saffron-dark transition duration-300">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="flex min-h-screen">
        <!-- Left Side - Form -->
        <div class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
            <div class="max-w-md w-full space-y-8">
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-saffron-dark mb-2">
                        Join Saffron Health
                    </h2>
                    <p class="text-gray-600">
                        Start your journey to better health education
                    </p>
                </div>
                
                <!-- Error/Success Messages -->
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg error-shake">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Registration Form -->
                <form method="POST" class="space-y-6" id="registrationForm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
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
                                value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                                class="form-input appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus-saffron-teal focus:outline-none focus:z-10"
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
                                value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                                class="form-input appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus-saffron-teal focus:outline-none focus:z-10"
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
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            class="form-input appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus-saffron-teal focus:outline-none focus:z-10"
                            placeholder="Enter your email address"
                        >
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- Institute Name -->
                        <div>
                            <label for="institute_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Institute/School Name
                            </label>
                            <input 
                                id="institute_name" 
                                name="institute_name" 
                                type="text" 
                                value="<?php echo isset($_POST['institute_name']) ? htmlspecialchars($_POST['institute_name']) : ''; ?>"
                                class="form-input appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus-saffron-teal focus:outline-none focus:z-10"
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
                                value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>"
                                class="form-input appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus-saffron-teal focus:outline-none focus:z-10"
                                placeholder="Your age"
                            >
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password *
                        </label>
                        <div class="relative">
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                required 
                                class="form-input appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus-saffron-teal focus:outline-none focus:z-10 pr-10"
                                placeholder="Create a password (min 6 characters)"
                            >
                            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Password *
                        </label>
                        <input 
                            id="confirm_password" 
                            name="confirm_password" 
                            type="password" 
                            required 
                            class="form-input appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus-saffron-teal focus:outline-none focus:z-10"
                            placeholder="Confirm your password"
                        >
                    </div>
                    
                    <!-- Submit Button -->
                    <div>
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-lg font-medium rounded-lg text-white bg-saffron-teal hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-saffron-teal transition duration-300 transform hover:scale-105"
                        >
                            <span id="submitText">Create Account</span>
                            <svg id="loadingIcon" class="loading w-5 h-5 ml-2 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Sign In Link -->
                    <div class="text-center">
                        <p class="text-gray-600">
                            Already have an account?
                            <a href="student-login.php" class="text-saffron-teal hover:text-saffron-dark font-medium transition duration-300">
                                Sign In
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Right Side - Image/Info (Hidden on mobile) -->
        <div class="hidden lg:flex lg:flex-1 auth-gradient relative overflow-hidden">
            <div class="flex items-center justify-center w-full p-12">
                <div class="max-w-md text-center text-white">
                    <div class="mb-8">
                        <svg class="w-24 h-24 mx-auto mb-6 text-white opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-3xl font-bold mb-4">Welcome to Your Health Journey</h3>
                    <p class="text-xl opacity-90 mb-6">
                        Join thousands of students learning evidence-based lifestyle medicine to transform their understanding of health and wellness.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center text-lg">
                            <svg class="w-6 h-6 mr-3 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Free comprehensive courses
                        </div>
                        <div class="flex items-center text-lg">
                            <svg class="w-6 h-6 mr-3 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Interactive quizzes and assessments
                        </div>
                        <div class="flex items-center text-lg">
                            <svg class="w-6 h-6 mr-3 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Digital certificates upon completion
                        </div>
                        <div class="flex items-center text-lg">
                            <svg class="w-6 h-6 mr-3 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Mobile-friendly learning platform
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Decorative elements -->
            <div class="absolute top-10 right-10 w-20 h-20 bg-white bg-opacity-10 rounded-full"></div>
            <div class="absolute bottom-10 left-10 w-16 h-16 bg-white bg-opacity-10 rounded-full"></div>
            <div class="absolute top-1/2 right-20 w-12 h-12 bg-white bg-opacity-10 rounded-full"></div>
        </div>
    </div>

    <script>
        // Form validation and UX enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const loadingIcon = document.getElementById('loadingIcon');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Password toggle functionality
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                this.innerHTML = type === 'password' 
                    ? '<svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>'
                    : '<svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/></svg>';
            });
            
            // Real-time password confirmation validation
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value && passwordInput.value !== this.value) {
                    this.classList.add('border-red-500');
                    this.classList.remove('border-gray-300');
                } else {
                    this.classList.remove('border-red-500');
                    this.classList.add('border-gray-300');
                }
            });
            
            // Form submission handling
            form.addEventListener('submit', function() {
                submitText.textContent = 'Creating Account...';
                loadingIcon.classList.remove('hidden');
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75');
            });
            
            // Auto-focus first empty field
            const inputs = form.querySelectorAll('input[required]');
            for (let input of inputs) {
                if (!input.value) {
                    input.focus();
                    break;
                }
            }
            
            // Enhanced form validation
            inputs.forEach(input => {
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
        });
    </script>
</body>
</html>