<?php
// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Saffron Health - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom colors inspired by the logo */
        :root {
            --saffron-teal: #5fb3b4;
            --saffron-dark: #2d3748;
            --saffron-light: #e6fffa;
            --admin-primary: #4f46e5;
            --admin-secondary: #7c3aed;
        }
        
        .bg-saffron-teal { background-color: var(--saffron-teal); }
        .text-saffron-teal { color: var(--saffron-teal); }
        .bg-saffron-dark { background-color: var(--saffron-dark); }
        .text-saffron-dark { color: var(--saffron-dark); }
        .bg-saffron-light { background-color: var(--saffron-light); }
        .bg-admin-primary { background-color: var(--admin-primary); }
        .text-admin-primary { color: var(--admin-primary); }
        
        .hover-saffron-teal:hover { background-color: var(--saffron-teal); }
        .border-saffron-teal { border-color: var(--saffron-teal); }
        
        /* Sidebar animations */
        .sidebar {
            transition: transform 0.3s ease;
        }
        
        .sidebar-mobile {
            transform: translateX(-100%);
        }
        
        .sidebar-mobile.open {
            transform: translateX(0);
        }
        
        /* Active menu item */
        .menu-item-active {
            background-color: var(--admin-primary);
            color: white;
        }
        
        .menu-item {
            transition: all 0.3s ease;
        }
        
        .menu-item:hover {
            background-color: var(--saffron-light);
            transform: translateX(5px);
        }
        
        .menu-item-active:hover {
            background-color: var(--admin-primary);
            transform: translateX(0);
        }
        
        /* Main content adjustment */
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        /* Profile dropdown */
        .profile-dropdown {
            transition: all 0.3s ease;
            transform: translateY(-10px);
            opacity: 0;
            visibility: hidden;
        }
        
        .profile-dropdown.show {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        /* Badge animations */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* Custom scrollbar for sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: var(--admin-primary);
            border-radius: 2px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--saffron-dark);
        }
        
        /* Admin theme styling */
        .admin-gradient {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Mobile Menu Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar sidebar-mobile lg:translate-x-0 fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-xl lg:shadow-lg">
        <div class="flex flex-col h-full">
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between h-16 px-6 admin-gradient">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-white">
                        <span class="text-yellow-300">Saffron</span> Health
                    </h1>
                </div>
                <button id="sidebar-close" class="lg:hidden text-white hover:text-yellow-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Admin Info -->
            <div class="px-6 py-4 bg-purple-50 border-b">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-admin-primary rounded-full flex items-center justify-center text-white font-semibold">
                        <?php echo strtoupper(substr($_SESSION['admin_name'], 0, 1)); ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                        </p>
                        <p class="text-xs text-purple-600 font-medium">Administrator</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 px-4 py-6 sidebar-scroll overflow-y-auto">
                <div class="space-y-2">
                    <!-- Dashboard -->
                    <a href="dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v3H8V5z"/>
                        </svg>
                        Dashboard
                    </a>

                    <!-- Course Management Section -->
                    <div class="pt-4">
                        <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            Course Management
                        </h3>
                        
                        <!-- Courses -->
                        <a href="courses.php" class="menu-item <?php echo ($current_page == 'courses.php' || $current_page == 'course-add.php' || $current_page == 'course-edit.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            Courses
                        </a>

                        <!-- Modules -->
                        <a href="modules.php" class="menu-item <?php echo ($current_page == 'modules.php' || $current_page == 'module-add.php' || $current_page == 'module-edit.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            Modules
                        </a>

                        <!-- Lessons -->
                        <a href="lessons.php" class="menu-item <?php echo ($current_page == 'lessons.php' || $current_page == 'lesson-add.php' || $current_page == 'lesson-edit.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Lessons
                        </a>

                        <!-- Quizzes -->
                        <a href="quizzes.php" class="menu-item <?php echo ($current_page == 'quizzes.php' || $current_page == 'quiz-add.php' || $current_page == 'quiz-edit.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Quizzes
                        </a>

                        <!-- Questions -->
                        <a href="questions.php" class="menu-item <?php echo ($current_page == 'questions.php' || $current_page == 'question-add.php' || $current_page == 'question-edit.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Questions
                        </a>
                    </div>

                    <!-- User Management Section -->
                    <div class="pt-4">
                        <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            User Management
                        </h3>
                        
                        <!-- Students -->
                        <a href="students.php" class="menu-item <?php echo ($current_page == 'students.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                            Students
                            <?php
                            // Get student count for badge
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) as student_count FROM users");
                                $student_count = $stmt->fetch()['student_count'];
                                if ($student_count > 0) {
                                    echo '<span class="ml-auto bg-blue-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">' . $student_count . '</span>';
                                }
                            } catch(PDOException $e) {
                                // Ignore error for badge
                            }
                            ?>
                        </a>
                    </div>

                    <!-- Reports & Analytics Section -->
                    <div class="pt-4">
                        <h3 class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                            Reports & Analytics
                        </h3>
                        
                        <!-- Reports -->
                        <a href="reports.php" class="menu-item <?php echo ($current_page == 'reports.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Reports
                        </a>

                        <!-- Certificates -->
                        <a href="certificates.php" class="menu-item <?php echo ($current_page == 'certificates.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                            </svg>
                            Certificates
                            <?php
                            // Get certificate count for badge
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) as cert_count FROM certificates");
                                $cert_count = $stmt->fetch()['cert_count'];
                                if ($cert_count > 0) {
                                    echo '<span class="notification-badge ml-auto bg-yellow-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">' . $cert_count . '</span>';
                                }
                            } catch(PDOException $e) {
                                // Ignore error for badge
                            }
                            ?>
                        </a>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-gray-200 my-4"></div>

                    <!-- Profile -->
                    <a href="profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        My Profile
                    </a>

                    <!-- Settings -->
                    <a href="settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                </div>
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-gray-200">
                <a href="logout.php" class="flex items-center px-4 py-3 text-sm font-medium text-red-600 rounded-lg hover:bg-red-50 transition duration-300">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>
    </div>

    <!-- Top Navigation Bar -->
    <div class="lg:ml-64">
        <nav class="bg-white shadow-sm border-b sticky top-0 z-30">
            <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Mobile menu button -->
                    <button id="sidebar-toggle" class="lg:hidden text-gray-600 hover:text-admin-primary">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Page Title -->
                    <div class="flex-1 lg:flex-none">
                        <h1 class="text-xl font-semibold text-gray-900 ml-4 lg:ml-0">
                            <?php echo isset($page_title) ? $page_title : 'Admin Portal'; ?>
                        </h1>
                    </div>

                    <!-- Right side items -->
                    <div class="flex items-center space-x-4">
                        <!-- Quick Actions -->
                        <a href="course-add.php" class="hidden md:inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-admin-primary hover:bg-opacity-90 transition duration-300">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Course
                        </a>

                        <!-- Notifications -->
                        <button class="text-gray-600 hover:text-admin-primary relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <!-- Notification badge -->
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">2</span>
                        </button>

                        <!-- Profile Dropdown -->
                        <div class="relative">
                            <button id="profile-dropdown-btn" class="flex items-center text-gray-600 hover:text-admin-primary">
                                <div class="w-8 h-8 bg-admin-primary rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                    <?php echo strtoupper(substr($_SESSION['admin_name'], 0, 1)); ?>
                                </div>
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="profile-dropdown" class="profile-dropdown absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-50">
                                <div class="py-2">
                                    <div class="px-4 py-2 border-b">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($_SESSION['admin_email']); ?>
                                        </p>
                                    </div>
                                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        My Profile
                                    </a>
                                    <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        Settings
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <a href="../index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v3H8V5z"/>
                                        </svg>
                                        View Site
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        Sign Out
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="main-content p-6">
            <!-- Content will be added by individual pages -->