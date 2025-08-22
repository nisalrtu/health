<?php
session_start();
require_once '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Set page title
$page_title = "Certificates Management";

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$course_filter = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build query with filters
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR cert.certificate_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($course_filter > 0) {
    $where_conditions[] = "cert.course_id = ?";
    $params[] = $course_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(cert.issued_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "cert.issued_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "cert.issued_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total certificates count for pagination
try {
    $count_sql = "SELECT COUNT(*) FROM certificates cert 
                  JOIN users u ON cert.user_id = u.id 
                  JOIN courses c ON cert.course_id = c.id 
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_certificates = $stmt->fetchColumn();
} catch(PDOException $e) {
    $total_certificates = 0;
}

// Pagination
$certificates_per_page = 20;
$total_pages = ceil($total_certificates / $certificates_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $certificates_per_page;

// Get certificates with user and course information
try {
    $sql = "
        SELECT 
            cert.id,
            cert.certificate_code,
            cert.issued_at,
            cert.verification_url,
            u.first_name,
            u.last_name,
            u.email,
            u.id as user_id,
            c.title as course_title,
            c.id as course_id,
            COUNT(DISTINCT m.id) as total_modules,
            AVG(CASE WHEN qa.passed = 1 THEN qa.score ELSE NULL END) as avg_quiz_score
        FROM certificates cert
        JOIN users u ON cert.user_id = u.id
        JOIN courses c ON cert.course_id = c.id
        LEFT JOIN modules m ON c.id = m.course_id AND m.is_active = 1
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = u.id AND qa.passed = 1
        $where_clause
        GROUP BY cert.id, cert.certificate_code, cert.issued_at, cert.verification_url,
                 u.first_name, u.last_name, u.email, u.id, c.title, c.id
        ORDER BY cert.issued_at DESC
        LIMIT $certificates_per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $certificates = $stmt->fetchAll();
} catch(PDOException $e) {
    $certificates = [];
    $error_message = "Failed to load certificates: " . $e->getMessage();
}

// Get courses for filter dropdown
try {
    $stmt = $pdo->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title ASC");
    $all_courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $all_courses = [];
}

// Get statistics
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_certificates,
            COUNT(CASE WHEN issued_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week,
            COUNT(CASE WHEN issued_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as this_month,
            COUNT(CASE WHEN DATE(issued_at) = CURDATE() THEN 1 END) as today
        FROM certificates
    ");
    $stats = $stmt->fetch();
    
    // Get top performing courses by certificates issued
    $stmt = $pdo->query("
        SELECT 
            c.title,
            COUNT(cert.id) as certificates_issued
        FROM courses c
        LEFT JOIN certificates cert ON c.id = cert.course_id
        WHERE c.is_active = 1
        GROUP BY c.id, c.title
        HAVING certificates_issued > 0
        ORDER BY certificates_issued DESC
        LIMIT 5
    ");
    $top_courses = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $stats = ['total_certificates' => 0, 'this_week' => 0, 'this_month' => 0, 'today' => 0];
    $top_courses = [];
}

// Include header
include '../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-yellow-500 to-orange-600 rounded-xl p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Certificates Management</h1>
                <p class="text-yellow-100 text-lg">View and manage all issued certificates</p>
            </div>
        </div>
    </div>
</div>

<!-- Error Messages -->
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

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 714.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_certificates']; ?></div>
                <div class="text-gray-600">Total Certificates</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['today']; ?></div>
                <div class="text-gray-600">Issued Today</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['this_week']; ?></div>
                <div class="text-gray-600">This Week</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $stats['this_month']; ?></div>
                <div class="text-gray-600">This Month</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <input type="hidden" name="page" value="1">
        
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Certificates</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Student name, email, or certificate code..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
        </div>

        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
            <select id="course_id" name="course_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                <option value="">All Courses</option>
                <?php foreach ($all_courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $course_filter == $course['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="date_filter" class="block text-sm font-medium text-gray-700 mb-2">Issued</label>
            <select id="date_filter" name="date_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                <option value="">All Time</option>
                <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-300">
                Apply Filters
            </button>
            <a href="certificates.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition duration-300">
                Clear
            </a>
        </div>

        <div class="text-sm text-gray-600">
            Showing <?php echo count($certificates); ?> of <?php echo $total_certificates; ?> certificates
        </div>
    </form>
</div>

<!-- Certificates Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (!empty($certificates)): ?>
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($certificates as $certificate): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                            <span class="text-sm font-medium text-yellow-600">
                                                <?php echo strtoupper(substr($certificate['first_name'], 0, 1) . substr($certificate['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($certificate['email']); ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            ID: <?php echo $certificate['user_id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($certificate['course_title']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo $certificate['total_modules']; ?> modules
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm space-y-1">
                                    <div class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                        <?php echo htmlspecialchars($certificate['certificate_code']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Certificate Code
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm space-y-1">
                                    <?php if ($certificate['avg_quiz_score']): ?>
                                        <div class="flex items-center">
                                            <span class="text-gray-600">Avg Score:</span>
                                            <span class="ml-2 font-medium text-green-600">
                                                <?php echo round($certificate['avg_quiz_score']); ?>%
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex items-center">
                                        <span class="text-gray-600">Modules:</span>
                                        <span class="ml-2 font-medium">
                                            <?php echo $certificate['total_modules']; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo date('M j, Y', strtotime($certificate['issued_at'])); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('g:i A', strtotime($certificate['issued_at'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-3">
                                    <a href="certificate.php?code=<?php echo urlencode($certificate['certificate_code']); ?>" 
                                       class="text-yellow-600 hover:text-yellow-900" title="View Certificate">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 714.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
                                        </svg>
                                    </a>
                                    <a href="student-view.php?id=<?php echo $certificate['user_id']; ?>" 
                                       class="text-indigo-600 hover:text-indigo-900" title="View Student">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </a>
                                    <a href="course-view.php?id=<?php echo $certificate['course_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900" title="View Course">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $certificates_per_page, $total_certificates); ?> of <?php echo $total_certificates; ?> certificates
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo ($current_page - 1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                               class="px-3 py-2 text-sm <?php echo $i == $current_page ? 'bg-yellow-600 text-white' : 'text-gray-600 bg-white hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo ($current_page + 1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" 
                               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 714.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 713.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 713.138-3.138z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No certificates found</h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if ($search || $course_filter || $date_filter): ?>
                    No certificates match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    No certificates have been issued yet.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="courses.php" 
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Manage Courses
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Top Performing Courses Section -->
<?php if (!empty($top_courses)): ?>
<div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Top Courses by Certificates Issued</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($top_courses as $course): ?>
                <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                    <h4 class="font-medium text-gray-900 mb-2">
                        <?php echo htmlspecialchars($course['title']); ?>
                    </h4>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Certificates Issued:</span>
                        <span class="font-bold text-yellow-600 text-lg">
                            <?php echo $course['certificates_issued']; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide error messages
    setTimeout(function() {
        const messages = document.querySelectorAll('.bg-red-100');
        messages.forEach(message => {
            message.style.transition = 'all 0.5s ease';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                message.remove();
            }, 500);
        });
    }, 5000);

    // Add hover effects to certificate cards
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#fffbeb';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });

    // Add loading state to action buttons
    const actionButtons = document.querySelectorAll('a[href*="certificate.php"], a[href*="student-view.php"], a[href*="course-view.php"]');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            const icon = this.querySelector('svg');
            if (icon) {
                icon.style.animation = 'spin 1s linear infinite';
            }
        });
    });

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);

    // Smooth transitions for stats cards
    const statCards = document.querySelectorAll('.grid .bg-white');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

</main>
</div>
</body>
</html>
