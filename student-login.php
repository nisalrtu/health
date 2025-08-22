<?php
session_start();
require_once 'config/db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['student_id'])) {
    header('Location: student/dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = 'You have been successfully logged out. Thank you for using Saffron Health!';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            // Check if user exists and get their details
            $stmt = $pdo->prepare("SELECT id, email, password_hash, first_name, last_name, institute_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['student_email'] = $user['email'];
                $_SESSION['student_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['student_first_name'] = $user['first_name'];
                $_SESSION['student_last_name'] = $user['last_name'];
                $_SESSION['student_institute'] = $user['institute_name'];
                
                // Redirect to dashboard
                header('Location: student/dashboard.php');
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'An error occurred. Please try again later.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

$page_title = 'Student Login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Saffron Health</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'saffron-teal': '#5fb3b4',
                        'saffron-dark': '#2d3748',
                        'saffron-light': '#e6fffa'
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom colors inspired by the logo */
        :root {
            --saffron-teal: #5fb3b4;
            --saffron-dark: #2d3748;
            --saffron-light: #e6fffa;
        }
        
        .bg-saffron-teal { background-color: var(--saffron-teal) !important; }
        .text-saffron-teal { color: var(--saffron-teal) !important; }
        .bg-saffron-dark { background-color: var(--saffron-dark) !important; }
        .text-saffron-dark { color: var(--saffron-dark) !important; }
        .bg-saffron-light { background-color: var(--saffron-light) !important; }
        
        .hover-saffron-teal:hover { background-color: var(--saffron-teal) !important; }
        .border-saffron-teal { border-color: var(--saffron-teal) !important; }
        .focus\:border-saffron-teal:focus { border-color: var(--saffron-teal) !important; }
        .focus\:ring-saffron-teal:focus { --tw-ring-color: var(--saffron-teal) !important; }
        
        /* Gradient background */
        .login-gradient {
            background: linear-gradient(135deg, var(--saffron-teal) 0%, var(--saffron-dark) 100%);
        }
        
        /* Form container styling */
        .login-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Input focus effects */
        .form-input:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(95, 179, 180, 0.15);
        }
        
        /* Button hover effect */
        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(45, 55, 72, 0.3);
        }
        
        /* Floating elements animation */
        .floating {
            animation: floating 6s ease-in-out infinite;
        }
        
        .floating-delayed {
            animation: floating 6s ease-in-out infinite;
            animation-delay: -3s;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        /* Error message styling */
        .error-message {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Success message styling */
        .success-message {
            animation: slideInDown 0.5s ease-out;
        }
        
        @keyframes slideInDown {
            0% { transform: translateY(-100%); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }
        
        /* Loading spinner */
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--saffron-teal);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Show password toggle */
        .password-toggle {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--saffron-teal);
        }
    </style>
</head>
<body class="min-h-screen login-gradient flex items-center justify-center relative overflow-hidden">

    <!-- Floating background elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="floating absolute top-1/4 left-1/4 w-64 h-64 bg-white bg-opacity-5 rounded-full"></div>
        <div class="floating-delayed absolute top-3/4 right-1/4 w-48 h-48 bg-white bg-opacity-10 rounded-full"></div>
        <div class="floating absolute bottom-1/4 left-1/2 w-32 h-32 bg-white bg-opacity-5 rounded-full"></div>
    </div>

    <!-- Login Container -->
    <div class="relative z-10 w-full max-w-md mx-4">
        
        <!-- Logo and Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">
                <span class="text-yellow-300">Saffron</span> Health
            </h1>
            <p class="text-white text-opacity-90">Student Portal Access</p>
        </div>

        <!-- Login Form -->
        <div class="login-card rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-saffron-dark text-center mb-6">Welcome Back!</h2>
            
            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="error-message bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="success-message bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="" class="space-y-6">
                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-saffron-dark mb-2">
                        Email Address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                            </svg>
                        </div>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            class="form-input block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-saffron-teal focus:border-saffron-teal transition-all duration-300"
                            placeholder="Enter your email address"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-saffron-dark mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input block w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-saffron-teal focus:border-saffron-teal transition-all duration-300"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <button type="button" id="togglePassword" class="password-toggle text-gray-400 hover:text-saffron-teal">
                                <svg id="eyeOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="eyeClosed" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input 
                            id="remember_me" 
                            name="remember_me" 
                            type="checkbox" 
                            class="h-4 w-4 text-saffron-teal focus:ring-saffron-teal border-gray-300 rounded"
                        >
                        <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                            Remember me
                        </label>
                    </div>
                    <div class="text-sm">
                        <a href="#" class="font-medium text-saffron-teal hover:text-saffron-dark transition-colors duration-300">
                            Forgot password?
                        </a>
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="login-btn w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-saffron-dark hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-saffron-teal transition-all duration-300"
                    >
                        <span id="submitText">Sign In to Your Account</span>
                        <div id="submitSpinner" class="spinner ml-2"></div>
                    </button>
                </div>
            </form>

            <!-- Register Link -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="student-register.php" class="font-medium text-saffron-teal hover:text-saffron-dark transition-colors duration-300">
                        Register here
                    </a>
                </p>
            </div>

            <!-- Admin Login Link -->
            <div class="mt-4 text-center">
                <p class="text-xs text-gray-500">
                    Are you an administrator? 
                    <a href="admin-login.php" class="font-medium text-saffron-teal hover:text-saffron-dark transition-colors duration-300">
                        Admin Login
                    </a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-white text-opacity-70 text-sm">
                © 2025 Saffron Health. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeOpen = document.getElementById('eyeOpen');
            const eyeClosed = document.getElementById('eyeClosed');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    if (type === 'text') {
                        eyeOpen.classList.add('hidden');
                        eyeClosed.classList.remove('hidden');
                    } else {
                        eyeOpen.classList.remove('hidden');
                        eyeClosed.classList.add('hidden');
                    }
                });
            }

            // Form submission with loading state
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitSpinner = document.getElementById('submitSpinner');

            if (loginForm && submitBtn) {
                loginForm.addEventListener('submit', function() {
                    // Show loading state
                    submitBtn.disabled = true;
                    submitText.textContent = 'Signing In...';
                    submitSpinner.style.display = 'block';
                });
            }

            // Auto-hide error/success messages after 5 seconds
            const errorMessage = document.querySelector('.error-message');
            const successMessage = document.querySelector('.success-message');
            
            if (errorMessage) {
                setTimeout(function() {
                    errorMessage.style.opacity = '0';
                    setTimeout(function() {
                        errorMessage.style.display = 'none';
                    }, 300);
                }, 5000);
            }
            
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.style.display = 'none';
                    }, 300);
                }, 5000);
            }

            // Enhanced form validation
            const emailInput = document.getElementById('email');
            
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    const email = this.value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (email && !emailRegex.test(email)) {
                        this.setCustomValidity('Please enter a valid email address');
                        this.classList.add('border-red-500');
                        this.classList.remove('border-gray-300');
                    } else {
                        this.setCustomValidity('');
                        this.classList.remove('border-red-500');
                        this.classList.add('border-gray-300');
                    }
                });
            }

            // Add focus effects to form inputs
            const formInputs = document.querySelectorAll('.form-input');
            formInputs.forEach(function(input) {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-saffron-teal');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-saffron-teal');
                });
            });
        });
    </script>
</body>
</html>
