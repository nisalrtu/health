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
    
    // Get featured courses (top 3 most recent active courses)
    $stmt = $pdo->query("SELECT id, title, description, created_at FROM courses WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3");
    $featured_courses = $stmt->fetchAll();
    
} catch(PDOException $e) {
    // Default values if database error
    $total_courses = 0;
    $total_students = 0;
    $enrolled_students = 0;
    $completed_courses = 0;
    $total_modules = 0;
    $total_quizzes = 0;
    $featured_courses = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saffron Health - Your Partner in Well-Being</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background pattern */
        .hero-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 255, 255, 0.03) 10px,
                rgba(255, 255, 255, 0.03) 20px
            );
            animation: slide 20s linear infinite;
        }
        
        @keyframes slide {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        /* Counter animation */
        .counter {
            font-weight: bold;
            font-size: 2.5rem;
        }
        
        /* Feature card hover effect */
        .feature-card {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .feature-card:hover::before {
            left: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        /* Course card hover effect */
        .course-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .course-card:hover {
            border-color: var(--saffron-teal);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(95, 179, 180, 0.2);
        }
        
        /* Floating animation for icons */
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        /* Scroll indicator */
        .scroll-indicator {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        /* Testimonial slider */
        .testimonial-slider {
            overflow: hidden;
        }
        
        .testimonial-track {
            display: flex;
            animation: scroll 30s linear infinite;
        }
        
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        .testimonial-track:hover {
            animation-play-state: paused;
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
                        <a href="#features" class="text-gray-700 hover:text-saffron-teal transition duration-300">Features</a>
                        <a href="#courses" class="text-gray-700 hover:text-saffron-teal transition duration-300">Courses</a>
                        <a href="#stats" class="text-gray-700 hover:text-saffron-teal transition duration-300">Statistics</a>
                        <a href="#testimonials" class="text-gray-700 hover:text-saffron-teal transition duration-300">Success Stories</a>
                        <a href="#about" class="text-gray-700 hover:text-saffron-teal transition duration-300">About</a>
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
                    <a href="#features" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">Features</a>
                    <a href="#courses" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">Courses</a>
                    <a href="#stats" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">Statistics</a>
                    <a href="#testimonials" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">Success Stories</a>
                    <a href="#about" class="block text-gray-700 hover:text-saffron-teal transition duration-300 text-lg">About</a>
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
    <section id="home" class="hero-gradient text-white py-20 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center">
                <div class="mb-8 float-animation">
                    <i class="fas fa-heartbeat text-6xl text-yellow-300"></i>
                </div>
                <h1 class="text-4xl md:text-6xl font-bold mb-6">
                    Welcome to <span class="text-yellow-300">Saffron Health</span>
                </h1>
                <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto">
                    Your trusted partner in lifestyle medicine education. Learn, grow, and transform your understanding of health and wellness.
                </p>
                <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
                    <a href="student-register.php" class="bg-yellow-400 text-saffron-dark px-8 py-4 rounded-lg text-lg font-semibold hover:bg-yellow-300 transition duration-300 transform hover:scale-105">
                        Start Learning Today
                    </a>
                    <a href="#courses" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-saffron-dark transition duration-300 transform hover:scale-105">
                        Explore Courses
                    </a>
                </div>
                
                <!-- Scroll Indicator -->
                <div class="mt-16 scroll-indicator">
                    <i class="fas fa-chevron-down text-3xl text-white opacity-70"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-saffron-dark mb-4">
                    Why Choose Saffron Health?
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Our platform combines cutting-edge technology with evidence-based content to deliver the best learning experience.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-book-medical text-4xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Expert-Curated Content</h3>
                    <p class="text-gray-600">Learn from evidence-based modules created by healthcare professionals</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="feature-card bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-users text-4xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Interactive Learning</h3>
                    <p class="text-gray-600">Engage with quizzes, activities, and real-world case studies</p>
                </div>
                
                <!-- Feature 3 -->
                <div class="feature-card bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-certificate text-4xl text-purple-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Earn Certificates</h3>
                    <p class="text-gray-600">Receive recognized certificates upon successful course completion</p>
                </div>
                
                <!-- Feature 4 -->
                <div class="feature-card bg-gradient-to-br from-yellow-50 to-yellow-100 p-6 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-chart-line text-4xl text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Track Progress</h3>
                    <p class="text-gray-600">Monitor your learning journey with detailed progress tracking</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <?php if (!empty($featured_courses)): ?>
    <section id="courses" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-saffron-dark mb-4">
                    Featured Courses
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Start your learning journey with our most popular lifestyle medicine courses.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featured_courses as $course): ?>
                <div class="course-card bg-white rounded-xl shadow-lg p-6">
                    <div class="mb-4">
                        <div class="bg-saffron-teal bg-opacity-10 rounded-lg p-4 text-center">
                            <i class="fas fa-graduation-cap text-3xl text-saffron-teal"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($course['title']); ?></h3>
                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">
                            <i class="far fa-calendar-alt mr-1"></i>
                            <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                        </span>
                        <a href="student-login.php" class="text-saffron-teal hover:text-saffron-dark transition duration-300">
                            Learn More <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="student-login.php" class="bg-saffron-teal text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-opacity-90 transition duration-300">
                    View All Courses
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
                    <div class="mb-4">
                        <i class="fas fa-book text-3xl text-blue-600"></i>
                    </div>
                    <div class="text-4xl md:text-5xl font-bold text-blue-600 mb-2 counter" data-target="<?php echo $total_courses; ?>">
                        0
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Active Courses</h3>
                    <p class="text-gray-600">Comprehensive lifestyle medicine curricula</p>
                </div>
                
                <!-- Total Students -->
                <div class="stat-card bg-gradient-to-br from-green-50 to-green-100 p-8 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-user-graduate text-3xl text-green-600"></i>
                    </div>
                    <div class="text-4xl md:text-5xl font-bold text-green-600 mb-2 counter" data-target="<?php echo $total_students; ?>">
                        0
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Registered Students</h3>
                    <p class="text-gray-600">Learners committed to health education</p>
                </div>
                
                <!-- Enrolled Students -->
                <div class="stat-card bg-gradient-to-br from-purple-50 to-purple-100 p-8 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-chalkboard-teacher text-3xl text-purple-600"></i>
                    </div>
                    <div class="text-4xl md:text-5xl font-bold text-purple-600 mb-2 counter" data-target="<?php echo $enrolled_students; ?>">
                        0
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Active Learners</h3>
                    <p class="text-gray-600">Students currently taking courses</p>
                </div>
                
                <!-- Completed Courses -->
                <div class="stat-card bg-gradient-to-br from-yellow-50 to-yellow-100 p-8 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-award text-3xl text-yellow-600"></i>
                    </div>
                    <div class="text-4xl md:text-5xl font-bold text-yellow-600 mb-2 counter" data-target="<?php echo $completed_courses; ?>">
                        0
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Certificates Issued</h3>
                    <p class="text-gray-600">Successful course completions</p>
                </div>
                
                <!-- Total Modules -->
                <div class="stat-card bg-gradient-to-br from-red-50 to-red-100 p-8 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-layer-group text-3xl text-red-600"></i>
                    </div>
                    <div class="text-4xl md:text-5xl font-bold text-red-600 mb-2 counter" data-target="<?php echo $total_modules; ?>">
                        0
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Learning Modules</h3>
                    <p class="text-gray-600">Structured learning components</p>
                </div>
                
                <!-- Total Quizzes -->
                <div class="stat-card bg-gradient-to-br from-indigo-50 to-indigo-100 p-8 rounded-xl text-center">
                    <div class="mb-4">
                        <i class="fas fa-question-circle text-3xl text-indigo-600"></i>
                    </div>
                    <div class="text-4xl md:text-5xl font-bold text-indigo-600 mb-2 counter" data-target="<?php echo $total_quizzes; ?>">
                        0
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Assessment Quizzes</h3>
                    <p class="text-gray-600">Knowledge evaluation tools</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-20 bg-saffron-light">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-saffron-dark mb-4">
                    Success Stories
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Hear from our students who have transformed their understanding of health and wellness.
                </p>
            </div>
            
            <div class="testimonial-slider">
                <div class="testimonial-track">
                    <!-- Testimonial 1 -->
                    <div class="bg-white rounded-xl shadow-lg p-8 mx-4 min-w-[350px]">
                        <div class="flex mb-4">
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                        </div>
                        <p class="text-gray-600 mb-6">"The lifestyle medicine courses at Saffron Health have completely changed my approach to healthcare. The content is evidence-based and practical."</p>
                        <div class="flex items-center">
                            <div class="bg-saffron-teal rounded-full w-12 h-12 flex items-center justify-center text-white font-bold mr-4">
                                JD
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">Jane Doe</p>
                                <p class="text-sm text-gray-500">Medical Student</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Testimonial 2 -->
                    <div class="bg-white rounded-xl shadow-lg p-8 mx-4 min-w-[350px]">
                        <div class="flex mb-4">
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                        </div>
                        <p class="text-gray-600 mb-6">"I've learned more about preventive health in these courses than in my entire nursing program. Highly recommend to all healthcare professionals!"</p>
                        <div class="flex items-center">
                            <div class="bg-saffron-teal rounded-full w-12 h-12 flex items-center justify-center text-white font-bold mr-4">
                                MS
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">Mark Smith</p>
                                <p class="text-sm text-gray-500">Registered Nurse</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Testimonial 3 -->
                    <div class="bg-white rounded-xl shadow-lg p-8 mx-4 min-w-[350px]">
                        <div class="flex mb-4">
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                        </div>
                        <p class="text-gray-600 mb-6">"As a health coach, these courses have given me the scientific foundation I needed. My clients are seeing incredible results with lifestyle interventions."</p>
                        <div class="flex items-center">
                            <div class="bg-saffron-teal rounded-full w-12 h-12 flex items-center justify-center text-white font-bold mr-4">
                                LJ
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">Lisa Johnson</p>
                                <p class="text-sm text-gray-500">Health Coach</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Testimonial 4 -->
                    <div class="bg-white rounded-xl shadow-lg p-8 mx-4 min-w-[350px]">
                        <div class="flex mb-4">
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                        </div>
                        <p class="text-gray-600 mb-6">"The interactive quizzes and real-world case studies make learning engaging. I earned my certificate in record time!"</p>
                        <div class="flex items-center">
                            <div class="bg-saffron-teal rounded-full w-12 h-12 flex items-center justify-center text-white font-bold mr-4">
                                RW
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">Robert Wilson</p>
                                <p class="text-sm text-gray-500">Dietitian</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Duplicate testimonials for seamless loop -->
                    <div class="bg-white rounded-xl shadow-lg p-8 mx-4 min-w-[350px]">
                        <div class="flex mb-4">
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                        </div>
                        <p class="text-gray-600 mb-6">"The lifestyle medicine courses at Saffron Health have completely changed my approach to healthcare. The content is evidence-based and practical."</p>
                        <div class="flex items-center">
                            <div class="bg-saffron-teal rounded-full w-12 h-12 flex items-center justify-center text-white font-bold mr-4">
                                JD
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">Jane Doe</p>
                                <p class="text-sm text-gray-500">Medical Student</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-8 mx-4 min-w-[350px]">
                        <div class="flex mb-4">
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                        </div>
                        <p class="text-gray-600 mb-6">"I've learned more about preventive health in these courses than in my entire nursing program. Highly recommend to all healthcare professionals!"</p>
                        <div class="flex items-center">
                            <div class="bg-saffron-teal rounded-full w-12 h-12 flex items-center justify-center text-white font-bold mr-4">
                                MS
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">Mark Smith</p>
                                <p class="text-sm text-gray-500">Registered Nurse</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold text-saffron-dark mb-6">
                        About Saffron Health
                    </h2>
                    <p class="text-lg text-gray-600 mb-6">
                        Saffron Health is dedicated to advancing lifestyle medicine education through evidence-based, interactive learning experiences. Our platform bridges the gap between traditional healthcare education and the growing field of lifestyle medicine.
                    </p>
                    <p class="text-lg text-gray-600 mb-8">
                        Founded by healthcare professionals and educators, we believe that empowering healthcare providers with lifestyle medicine knowledge is key to transforming patient outcomes and building a healthier society.
                    </p>
                    
                    <div class="grid grid-cols-2 gap-6">
                        <div class="text-center">
                            <div class="bg-saffron-teal bg-opacity-10 rounded-lg p-4 mb-3">
                                <i class="fas fa-stethoscope text-2xl text-saffron-teal"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800">Clinical Excellence</h4>
                            <p class="text-sm text-gray-600">Evidence-based content</p>
                        </div>
                        <div class="text-center">
                            <div class="bg-saffron-teal bg-opacity-10 rounded-lg p-4 mb-3">
                                <i class="fas fa-graduation-cap text-2xl text-saffron-teal"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800">Expert Educators</h4>
                            <p class="text-sm text-gray-600">Healthcare professionals</p>
                        </div>
                        <div class="text-center">
                            <div class="bg-saffron-teal bg-opacity-10 rounded-lg p-4 mb-3">
                                <i class="fas fa-heart text-2xl text-saffron-teal"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800">Patient-Centered</h4>
                            <p class="text-sm text-gray-600">Holistic approach</p>
                        </div>
                        <div class="text-center">
                            <div class="bg-saffron-teal bg-opacity-10 rounded-lg p-4 mb-3">
                                <i class="fas fa-globe text-2xl text-saffron-teal"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800">Global Impact</h4>
                            <p class="text-sm text-gray-600">Worldwide accessibility</p>
                        </div>
                    </div>
                </div>
                
                <div class="relative">
                    <div class="bg-gradient-to-br from-saffron-teal to-saffron-dark rounded-2xl p-8 text-white">
                        <h3 class="text-2xl font-bold mb-6">Our Mission</h3>
                        <p class="text-lg mb-6">
                            To democratize access to lifestyle medicine education and empower healthcare professionals to prevent, treat, and reverse chronic diseases through evidence-based lifestyle interventions.
                        </p>
                        
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-yellow-300 mr-3"></i>
                                <span>Evidence-based curriculum development</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-yellow-300 mr-3"></i>
                                <span>Interactive learning methodologies</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-yellow-300 mr-3"></i>
                                <span>Continuous professional development</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-yellow-300 mr-3"></i>
                                <span>Global healthcare transformation</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Floating elements -->
                    <div class="absolute -top-4 -right-4 bg-yellow-300 rounded-full w-16 h-16 flex items-center justify-center float-animation">
                        <i class="fas fa-lightbulb text-saffron-dark text-xl"></i>
                    </div>
                    <div class="absolute -bottom-6 -left-6 bg-white rounded-full w-20 h-20 flex items-center justify-center shadow-lg float-animation" style="animation-delay: 1s;">
                        <i class="fas fa-leaf text-saffron-teal text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-20 bg-gradient-to-r from-saffron-teal to-saffron-dark text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">
                Ready to Transform Your Healthcare Practice?
            </h2>
            <p class="text-xl mb-8 max-w-3xl mx-auto">
                Join thousands of healthcare professionals who are already using lifestyle medicine to improve patient outcomes and prevent chronic diseases.
            </p>
            
            <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6 mb-12">
                <a href="student-register.php" class="bg-yellow-400 text-saffron-dark px-8 py-4 rounded-lg text-lg font-semibold hover:bg-yellow-300 transition duration-300 transform hover:scale-105">
                    <i class="fas fa-user-plus mr-2"></i>
                    Start Learning Now
                </a>
                <a href="student-login.php" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-saffron-dark transition duration-300 transform hover:scale-105">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Already Have an Account?
                </a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                <div class="text-center">
                    <div class="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">Flexible Learning</h4>
                    <p class="text-sm opacity-90">Learn at your own pace, anytime, anywhere</p>
                </div>
                <div class="text-center">
                    <div class="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-certificate text-2xl"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">Get Certified</h4>
                    <p class="text-sm opacity-90">Earn recognized certificates for your achievements</p>
                </div>
                <div class="text-center">
                    <div class="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2">Join Community</h4>
                    <p class="text-sm opacity-90">Connect with like-minded healthcare professionals</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-saffron-dark text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="md:col-span-2">
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold mb-2">
                            <span class="text-saffron-teal">Saffron</span> Health
                        </h3>
                        <p class="text-gray-300 mb-4">Your Partner in Well-Being</p>
                        <p class="text-gray-400 text-sm max-w-md">
                            Advancing lifestyle medicine education through evidence-based, interactive learning experiences that empower healthcare professionals worldwide.
                        </p>
                    </div>
                    
                    <div class="flex space-x-4">
                        <a href="#" class="bg-saffron-teal bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-30 transition duration-300">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="bg-saffron-teal bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-30 transition duration-300">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="bg-saffron-teal bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-30 transition duration-300">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="bg-saffron-teal bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-30 transition duration-300">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-lg font-semibold mb-4 text-saffron-teal">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-gray-400 hover:text-white transition duration-300">Home</a></li>
                        <li><a href="#features" class="text-gray-400 hover:text-white transition duration-300">Features</a></li>
                        <li><a href="#courses" class="text-gray-400 hover:text-white transition duration-300">Courses</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white transition duration-300">About Us</a></li>
                        <li><a href="student-register.php" class="text-gray-400 hover:text-white transition duration-300">Register</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div>
                    <h4 class="text-lg font-semibold mb-4 text-saffron-teal">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">Contact Us</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition duration-300">FAQ</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 text-sm">
                        © <?php echo date('Y'); ?> Saffron Health. All rights reserved.
                    </p>
                    <p class="text-gray-400 text-sm mt-2 md:mt-0">
                        Empowering healthcare professionals through lifestyle medicine education.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const closeMenuBtn = document.getElementById('close-menu-btn');
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
        
        // Close mobile menu when clicking on navigation links
        document.querySelectorAll('#mobile-menu a[href^="#"]').forEach(link => {
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
        
        // Counter animation
        function animateCounters() {
            const counters = document.querySelectorAll('.counter');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target'));
                const increment = target / 200;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 10);
            });
        }
        
        // Intersection Observer for counter animation
        const statsSection = document.getElementById('stats');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        if (statsSection) {
            observer.observe(statsSection);
        }
        
        // Header scroll effect
        let lastScrollTop = 0;
        const header = document.querySelector('header');
        
        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                header.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                header.style.transform = 'translateY(0)';
            }
            
            // Add shadow when scrolled
            if (scrollTop > 10) {
                header.classList.add('shadow-xl');
            } else {
                header.classList.remove('shadow-xl');
            }
            
            lastScrollTop = scrollTop;
        });
        
        // Parallax effect for hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.hero-gradient');
            if (parallax) {
                const speed = scrolled * 0.5;
                parallax.style.transform = `translateY(${speed}px)`;
            }
        });
        
        // Add loading animation
        window.addEventListener('load', () => {
            document.body.classList.add('loaded');
        });
        
        // Add smooth reveal animation for elements
        const revealElements = document.querySelectorAll('.feature-card, .course-card, .stat-card');
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });
        
        revealElements.forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            revealObserver.observe(element);
        });
    </script>
</body>
</html>