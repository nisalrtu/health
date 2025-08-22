<?php
// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../student-login.php');
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
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Saffron Health - Student Portal</title>
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
        
        /* Custom gradient classes for Tailwind */
        .from-saffron-teal { --tw-gradient-from: var(--saffron-teal) !important; --tw-gradient-to: rgba(95, 179, 180, 0) !important; --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important; }
        .to-saffron-dark { --tw-gradient-to: var(--saffron-dark) !important; }
        .hover\:bg-opacity-90:hover { --tw-bg-opacity: 0.9 !important; }
        
        /* Ensure text visibility */
        .text-white { color: #ffffff !important; }
        .text-gray-900 { color: #111827 !important; }
        .text-gray-600 { color: #4b5563 !important; }
        .text-gray-500 { color: #6b7280 !important; }
        .text-gray-100 { color: #f3f4f6 !important; }
        
        /* Fallback styles for custom colors in case Tailwind config doesn't load */
        .text-saffron-teal { color: #5fb3b4 !important; }
        .bg-saffron-teal { background-color: #5fb3b4 !important; }
        .text-saffron-dark { color: #2d3748 !important; }
        .hover\:text-saffron-dark:hover { color: #2d3748 !important; }
        .hover\:bg-opacity-90:hover { opacity: 0.9 !important; }
        
        /* Sidebar functionality - no animations */        
        /* Mobile sidebar - hidden by default on mobile */
        @media (max-width: 1023px) {
            .sidebar-mobile {
                transform: translateX(-100%);
            }
            
            .sidebar-mobile.open {
                transform: translateX(0);
            }
        }
        
        /* Desktop - always visible */
        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0) !important;
            }
        }
        
        /* Active menu item */
        .menu-item-active {
            background-color: var(--saffron-teal);
            color: white;
        }
        
        .menu-item:hover {
            background-color: var(--saffron-light);
        }
        
        .menu-item-active:hover {
            background-color: var(--saffron-teal);
        }
        
        /* Profile dropdown */
        .profile-dropdown {
            transform: translateY(-10px);
            opacity: 0;
            visibility: hidden;
        }
        
        .profile-dropdown.show {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        /* Custom scrollbar for sidebar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: var(--saffron-teal);
            border-radius: 2px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--saffron-dark);
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
            <div class="flex items-center justify-between h-16 px-6 bg-saffron-dark">
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

            <!-- Student Info -->
            <div class="px-6 py-4 bg-saffron-light border-b">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-saffron-teal rounded-full flex items-center justify-center text-white font-semibold">
                        <?php echo strtoupper(substr($_SESSION['student_name'], 0, 1)); ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-saffron-dark">
                            <?php echo htmlspecialchars($_SESSION['student_name']); ?>
                        </p>
                        <p class="text-xs text-gray-600">Student</p>
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

                    <!-- Courses -->
                    <a href="courses.php" class="menu-item <?php echo ($current_page == 'courses.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        All Courses
                    </a>

                    <!-- My Progress -->
                    <a href="progress.php" class="menu-item <?php echo ($current_page == 'progress.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        My Progress
                    </a>

                    <!-- Certificates -->
                    <a href="certificates.php" class="menu-item <?php echo ($current_page == 'certificates.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                        My Certificates
                        <?php
                        // Get certificate count for badge
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) as cert_count FROM certificates WHERE user_id = ?");
                            $stmt->execute([$_SESSION['student_id']]);
                            $cert_count = $stmt->fetch()['cert_count'];
                            if ($cert_count > 0) {
                                echo '<span class="notification-badge ml-auto bg-yellow-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">' . $cert_count . '</span>';
                            }
                        } catch(PDOException $e) {
                            // Ignore error for badge
                        }
                        ?>
                    </a>

                    <!-- Divider -->
                    <div class="border-t border-gray-200 my-4"></div>

                    <!-- Profile -->
                    <a href="profile.php" class="menu-item <?php echo ($current_page == 'profile.php') ? 'menu-item-active' : ''; ?> flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        My Profile
                    </a>

                    <!-- Help & Support -->
                    <a href="#" class="menu-item flex items-center px-4 py-3 text-sm font-medium rounded-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Help & Support
                    </a>
                </div>
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-gray-200">
                <a href="logout.php" class="flex items-center px-4 py-3 text-sm font-medium text-red-600 rounded-lg hover:bg-red-50">
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
                    <button id="sidebar-toggle" class="lg:hidden text-gray-600 hover:text-saffron-teal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <!-- Page Title -->
                    <div class="flex-1 lg:flex-none">
                        <h1 class="text-xl font-semibold text-saffron-dark ml-4 lg:ml-0">
                            <?php echo isset($page_title) ? $page_title : 'Student Portal'; ?>
                        </h1>
                    </div>

                    <!-- Right side items -->
                    <div class="flex items-center space-x-4">
                        <!-- Notifications (future feature) -->
                        <button class="text-gray-600 hover:text-saffron-teal relative">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <!-- Notification badge (if any) -->
                            <!-- <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">3</span> -->
                        </button>

                        <!-- Profile Dropdown -->
                        <div class="relative">
                            <button id="profile-dropdown-btn" class="flex items-center text-gray-600 hover:text-saffron-teal">
                                <div class="w-8 h-8 bg-saffron-teal rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                    <?php echo strtoupper(substr($_SESSION['student_name'], 0, 1)); ?>
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
                                            <?php echo htmlspecialchars($_SESSION['student_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($_SESSION['student_email']); ?>
                                        </p>
                                    </div>
                                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        My Profile
                                    </a>
                                    <a href="certificates.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                        </svg>
                                        My Certificates
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar functionality
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarClose = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const profileDropdownBtn = document.getElementById('profile-dropdown-btn');
    const profileDropdown = document.getElementById('profile-dropdown');

    // Function to open sidebar
    function openSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.add('open');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }

    // Function to close sidebar
    function closeSidebar() {
        if (sidebar && overlay) {
            sidebar.classList.remove('open');
            overlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }

    // Mobile sidebar toggle button
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openSidebar();
        });
    }

    // Sidebar close button
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeSidebar();
        });
    }

    // Overlay click to close sidebar
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
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

    // Close sidebar when window is resized to desktop size
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) { // lg breakpoint
            closeSidebar();
        }
    });

    // Ensure sidebar is closed on mobile initially
    function initializeSidebar() {
        if (window.innerWidth < 1024) {
            closeSidebar();
        }
    }

    // Initialize on load
    initializeSidebar();
});
</script>