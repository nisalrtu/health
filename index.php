<?php
session_start();
require_once 'config/db.php';

// Get statistics from database
try {
    // Total courses
    $stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM courses WHERE is_active = 1");
    $total_courses = $stmt->fetch()['total_courses'];
    
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM users");
    $total_students = $stmt->fetch()['total_students'];
    
    // Total enrollments (students who have started any course)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as enrolled_students FROM user_progress");
    $enrolled_students = $stmt->fetch()['enrolled_students'];
    
    // Completed courses (students who have certificates)
    $stmt = $pdo->query("SELECT COUNT(*) as completed_courses FROM certificates");
    $completed_courses = $stmt->fetch()['completed_courses'];
    
    // Active modules
    $stmt = $pdo->query("SELECT COUNT(*) as total_modules FROM modules WHERE is_active = 1");
    $total_modules = $stmt->fetch()['total_modules'];
    
    // Total quizzes
    $stmt = $pdo->query("SELECT COUNT(*) as total_quizzes FROM quizzes WHERE is_active = 1");
    $total_quizzes = $stmt->fetch()['total_quizzes'];
    
} catch(PDOException $e) {
    // Default values if database error
    $total_courses = 0;
    $total_students = 0;
    $enrolled_students = 0;
    $completed_courses = 0;
    $total_modules = 0;
    $total_quizzes = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saffron Health - Your Partner in Well-Being</title>
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
        
        /* Animation for statistics */
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(95, 179, 180, 0.2);
        }
        
        /* Mobile menu animation */
        .mobile-menu {
            transition: transform 0.3s ease;
            transform: translateX(-100%);
        }
        
        .mobile-menu.open {
            transform: translateX(0);
        }
        
        /* Hero section gradient */
        .hero-gradient {
            background: linear-gradient(135deg, var(--saffron-teal) 0%, var(--saffron-dark) 100%);
        }
        
        /* Counter animation */
        .counter {
            font-weight: bold;
            font-size: 2.5rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Header -->
    <header class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-2xl font-bold text-saffron-dark">
                            <span class="text-saffron-teal">Saffron</span> Health
                        </h1>
                        <p class="text-xs text-gray-600 -mt-1">Your Partner in Well-Being</p>
                    </div>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:block">
                    <div class="flex items-center space-x-8">
                        <a href="#home" class="text-gray-700 hover:text-saffron-teal transition duration-300">Home</a>
                        <a href="#courses" class="text-gray-700 hover:text-saffron-teal transition duration-300">Courses</a>
                        <a href="#about" class="text-gray-700 hover:text-saffron-teal transition duration-300">About</a>
                        <a href="#stats" class="text-gray-700 hover:text-saffron-teal transition duration-300">Statistics</a>
                        <div class="flex space-x-4">
                            <a href="student-login.php" class="bg-saffron-teal text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition duration-300">
                                Student Login
                            </a>
                            <a href="admin-login.php" class="border border-saffron-teal text-saffron-teal px-4 py-2 rounded-lg hover-saffron-teal hover:text-white transition duration-300">
                                Admin
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-btn" class="text-gray-700 hover:text-saffron-teal focus:outline-none">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation -->
        <div id="mobile-menu" class="mobile-menu fixed inset-y-0 left-0 w-64 bg-white shadow-xl z-50 md:hidden">
            <div class="p-6">
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-xl font-bold text-saffron-dark">Menu</h2>
                    <button id="close-menu-btn" class="text-gray-700 hover:text-saffron-teal">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <nav class="space-y-6">
                    <a href="#home" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">Home</a>
                    <a href="#courses" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">Courses</a>
                    <a href="#about" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">About</a>
                    <a href="#stats" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">Statistics</a>
                    <div class="pt-6 space-y-4">
                        <a href="student-login.php" class="block bg-saffron-teal text-white px-4 py-3 rounded-lg text-center hover:bg-opacity-90 transition duration-300">
                            Student Login
                        </a>
                        <a href="admin-login.php" class="block border border-saffron-teal text-saffron-teal px-4 py-3 rounded-lg text-center hover-saffron-teal hover:text-white transition duration-300">
                            Admin Login
                        </a>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <!-- Overlay for mobile menu -->
    <div id="menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden"></div>

    <!-- Hero Section -->
    <section id="home" class="hero-gradient text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">
                    Welcome to <span class="text-yellow-300">Saffron Health</span>
                </h1>
                <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto">
                    Your trusted partner in lifestyle medicine education. Learn, grow, and transform your understanding of health and wellness.
                </p>
                <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
                    <a href="student-register.php" class="bg-yellow-400 text-saffron-dark px-8 py-4 rounded-lg text-lg font-semibold hover:bg-yellow-300 transition duration-300">
                        Start Learning Today
                    </a>
                    <a href="#courses" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-saffron-dark transition duration-300">
                        Explore Courses
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section id="stats" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-saffron-dark mb-4">
                    Our Impact in Numbers
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Join thousands of students on their journey to better health and wellness through evidence-based lifestyle medicine education.
                </p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Total Courses -->
                <div class="stat-card bg-gradient-to-br from-blue-50 to-blue-100 p-8 rounded-xl text-center">
                    <div class="text-4xl md:text-5xl font-bold text-blue-600 mb-2">
                        <?php echo number_format($total_courses); ?>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Active Courses</h3>
                    <p class="text-gray-600">Comprehensive lifestyle medicine curricula</p>
                </div>
                
                <!-- Total Students -->
                <div class="stat-card bg-gradient-to-br from-green-50 to-green-100 p-8 rounded-xl text-center">
                    <div class="text-4xl md:text-5xl font-bold text-green-600 mb-2">
                        <?php echo number_format($total_students); ?>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Registered Students</h3>
                    <p class="text-gray-600">Learners committed to health education</p>
                </div>
                
                <!-- Enrolled Students -->
                <div class="stat-card bg-gradient-to-br from-purple-50 to-purple-100 p-8 rounded-xl text-center">
                    <div class="text-4xl md:text-5xl font-bold text-purple-600 mb-2">
                        <?php echo number_format($enrolled_students); ?>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Active Learners</h3>
                    <p class="text-gray-600">Students currently taking courses</p>
                </div>
                
                <!-- Completed Courses -->
                <div class="stat-card bg-gradient-to-br from-yellow-50 to-yellow-100 p-8 rounded-xl text-center">
                    <div class="text-4xl md:text-5xl font-bold text-yellow-600 mb-2">
                        <?php echo number_format($completed_courses); ?>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Certificates Issued</h3>
                    <p class="text-gray-600">Successful course completions</p>
                </div>
                
                <!-- Total Modules -->
                <div class="stat-card bg-gradient-to-br from-red-50 to-red-100 p-8 rounded-xl text-center">
                    <div class="text-4xl md:text-5xl font-bold text-red-600 mb-2">
                        <?php echo number_format($total_modules); ?>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Learning Modules</h3>
                    <p class="text-gray-600">Structured learning components</p>
                </div>
                
                <!-- Total Quizzes -->
                <div class="stat-card bg-gradient-to-br from-indigo-50 to-indigo-100 p-8 rounded-xl text-center">
                    <div class="text-4xl md:text-5xl font-bold text-indigo-600 mb-2">
                        <?php echo number_format($total_quizzes); ?>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Assessment Quizzes</h3>
                    <p class="text-gray-600">Knowledge evaluation tools</p>
                </div>
            </div>
        </div>
    </section>



    <!-- About Section -->
    <section id="about" class="py-20 bg-saffron-light">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-saffron-dark mb-6">
                        About Saffron Health
                    </h2>
                    <p class="text-lg text-gray-600 mb-6">
                        Saffron Health is dedicated to providing comprehensive lifestyle medicine education to students worldwide. Our platform combines cutting-edge research with practical application to help learners understand the powerful role of lifestyle interventions in health and disease prevention.
                    </p>
                    <p class="text-lg text-gray-600 mb-8">
                        Through interactive modules, engaging quizzes, and evidence-based content, we empower the next generation of health advocates to make informed decisions about their wellbeing and help others do the same.
                    </p>
                    <a href="student-register.php" class="bg-saffron-teal text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-opacity-90 transition duration-300">
                        Join Our Community
                    </a>
                </div>
                <div class="text-center">
                    <div class="bg-gradient-to-br from-saffron-teal to-saffron-dark rounded-full w-64 h-64 mx-auto flex items-center justify-center">
                        <svg class="w-32 h-32 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="hero-gradient text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                Ready to Transform Your Health Knowledge?
            </h2>
            <p class="text-xl mb-8 max-w-2xl mx-auto">
                Join thousands of students already learning with Saffron Health. Start your journey today!
            </p>
            <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
                <a href="student-register.php" class="bg-yellow-400 text-saffron-dark px-8 py-4 rounded-lg text-lg font-semibold hover:bg-yellow-300 transition duration-300">
                    Get Started Free
                </a>
                <a href="student-login.php" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-saffron-dark transition duration-300">
                    Sign In
                </a>
            </div>
        </div>
    </section>

    <script>
        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const closeMenuBtn = document.getElementById('close-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuOverlay = document.getElementById('menu-overlay');

        function openMobileMenu() {
            mobileMenu.classList.add('open');
            menuOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            mobileMenu.classList.remove('open');
            menuOverlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        mobileMenuBtn.addEventListener('click', openMobileMenu);
        closeMenuBtn.addEventListener('click', closeMobileMenu);
        menuOverlay.addEventListener('click', closeMobileMenu);

        // Close mobile menu when clicking on links
        const mobileMenuLinks = mobileMenu.querySelectorAll('a[href^="#"]');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add animation to statistics when they come into view
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>