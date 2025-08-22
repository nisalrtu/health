<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../student-login.php');
    exit();
}

// Set page title
$page_title = "My Certificates";

// Get student ID
$student_id = $_SESSION['student_id'];

try {
    // Get all certificates for the student
    $stmt = $pdo->prepare("
        SELECT 
            c.id as certificate_id,
            c.certificate_code,
            c.issued_at,
            c.verification_url,
            co.id as course_id,
            co.title as course_title,
            co.description as course_description,
            COUNT(DISTINCT m.id) as total_modules,
            AVG(CASE WHEN qa.passed = 1 THEN qa.score ELSE NULL END) as avg_quiz_score,
            COUNT(DISTINCT CASE WHEN qa.passed = 1 THEN qa.id END) as quizzes_passed
        FROM certificates c
        JOIN courses co ON c.course_id = co.id
        LEFT JOIN modules m ON co.id = m.course_id AND m.is_active = 1
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = c.user_id AND qa.passed = 1
        WHERE c.user_id = ?
        GROUP BY c.id, c.certificate_code, c.issued_at, c.verification_url, 
                 co.id, co.title, co.description
        ORDER BY c.issued_at DESC
    ");
    
    $stmt->execute([$student_id]);
    $certificates = $stmt->fetchAll();
    
    // Get total statistics
    $total_certificates = count($certificates);
    
    // Get completion statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.course_id) as completed_courses,
            COUNT(DISTINCT up.course_id) as enrolled_courses,
            MIN(cert.issued_at) as first_certificate,
            MAX(cert.issued_at) as latest_certificate
        FROM certificates cert
        RIGHT JOIN user_progress up ON up.user_id = ?
        LEFT JOIN certificates c ON c.user_id = up.user_id AND c.course_id = up.course_id
        WHERE up.user_id = ?
    ");
    
    $stmt->execute([$student_id, $student_id]);
    $stats = $stmt->fetch();
    
} catch(PDOException $e) {
    $certificates = [];
    $total_certificates = 0;
    $stats = [
        'completed_courses' => 0,
        'enrolled_courses' => 0,
        'first_certificate' => null,
        'latest_certificate' => null
    ];
}

// Include header
include '../includes/student-header.php';
?>

<style>
    .certificate-card {
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .certificate-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border-color: #5fb3b4;
    }
    
    .certificate-badge {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: white;
        animation: shine 2s infinite;
    }
    
    @keyframes shine {
        0% { box-shadow: 0 0 5px rgba(251, 191, 36, 0.5); }
        50% { box-shadow: 0 0 20px rgba(251, 191, 36, 0.8), 0 0 30px rgba(251, 191, 36, 0.6); }
        100% { box-shadow: 0 0 5px rgba(251, 191, 36, 0.5); }
    }
    
    .achievement-ring {
        animation: rotate 3s linear infinite;
    }
    
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .gradient-bg {
        background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);
    }
    
    .stats-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .empty-state {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
    
    .certificate-code {
        font-family: 'Courier New', monospace;
        background: linear-gradient(45deg, #5fb3b4, #2d3748);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: bold;
    }
    
    .verification-badge {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }
    
    .print-button:hover {
        background: linear-gradient(135deg, #4f46e5, #3730a3);
    }
</style>

<!-- Page Header -->
<div class="mb-8">
    <div class="gradient-bg rounded-xl p-6 text-white">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center mb-3">
                    <div class="achievement-ring w-16 h-16 border-4 border-yellow-400 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-white mb-2">
                            🏆 My Achievements
                        </h1>
                        <p class="text-white opacity-90 text-lg">
                            Your certificates and learning accomplishments
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mt-4 lg:mt-0">
                <div class="flex flex-col sm:flex-row gap-3">
                    <?php if ($total_certificates > 0): ?>
                        <button onclick="window.print()" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Print All
                        </button>
                    <?php endif; ?>
                    <a href="courses.php" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        Browse Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Certificates -->
    <div class="stats-card rounded-xl p-6 shadow-lg">
        <div class="flex items-center">
            <div class="w-12 h-12 certificate-badge rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-3xl font-bold text-gray-900"><?php echo $total_certificates; ?></div>
            <div class="text-sm text-gray-600">Certificates Earned</div>
        </div>
    </div>

    <!-- Completed Courses -->
    <div class="stats-card rounded-xl p-6 shadow-lg">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-3xl font-bold text-gray-900"><?php echo $stats['completed_courses']; ?></div>
            <div class="text-sm text-gray-600">Courses Completed</div>
        </div>
    </div>

    <!-- Completion Rate -->
    <div class="stats-card rounded-xl p-6 shadow-lg">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-3xl font-bold text-gray-900">
                <?php 
                if ($stats['enrolled_courses'] > 0) {
                    echo round(($stats['completed_courses'] / $stats['enrolled_courses']) * 100) . '%';
                } else {
                    echo '0%';
                }
                ?>
            </div>
            <div class="text-sm text-gray-600">Completion Rate</div>
        </div>
    </div>

    <!-- Learning Journey -->
    <div class="stats-card rounded-xl p-6 shadow-lg">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <div class="text-3xl font-bold text-gray-900">
                <?php 
                if ($stats['first_certificate']) {
                    $days = (new DateTime())->diff(new DateTime($stats['first_certificate']))->days;
                    echo $days;
                } else {
                    echo '0';
                }
                ?>
            </div>
            <div class="text-sm text-gray-600">Days Learning</div>
        </div>
    </div>
</div>

<!-- Certificates Section -->
<?php if (empty($certificates)): ?>
    <!-- Empty State -->
    <div class="empty-state rounded-xl p-12 text-center">
        <div class="w-24 h-24 mx-auto mb-6 bg-gray-200 rounded-full flex items-center justify-center">
            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
        </div>
        <h3 class="text-2xl font-bold text-gray-900 mb-3">No Certificates Yet</h3>
        <p class="text-gray-600 mb-6 max-w-md mx-auto">
            Start your learning journey by enrolling in courses. Complete all modules and pass the final quiz to earn your certificates!
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="courses.php" class="bg-saffron-teal hover:bg-opacity-90 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                Browse Courses
            </a>
            <a href="dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v3H8V5z"/>
                </svg>
                Go to Dashboard
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Certificates Grid -->
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">
                Your Certificates (<?php echo $total_certificates; ?>)
            </h2>
            <div class="text-sm text-gray-500">
                Latest earned: <?php echo $stats['latest_certificate'] ? date('F j, Y', strtotime($stats['latest_certificate'])) : 'N/A'; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach ($certificates as $certificate): ?>
                <div class="certificate-card bg-white rounded-xl shadow-lg overflow-hidden">
                    <!-- Certificate Header -->
                    <div class="gradient-bg px-6 py-4 text-white relative">
                        <div class="absolute top-2 right-2">
                            <div class="verification-badge px-3 py-1 rounded-full text-xs font-medium">
                                ✓ Verified
                            </div>
                        </div>
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-xl font-bold mb-1">Certificate of Completion</h3>
                                <p class="text-white opacity-90 text-sm">Lifestyle Medicine Education</p>
                            </div>
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Certificate Body -->
                    <div class="p-6">
                        <!-- Course Title -->
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">
                            <?php echo htmlspecialchars($certificate['course_title']); ?>
                        </h4>
                        
                        <!-- Course Description -->
                        <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                            <?php echo htmlspecialchars(substr($certificate['course_description'], 0, 120)) . (strlen($certificate['course_description']) > 120 ? '...' : ''); ?>
                        </p>
                        
                        <!-- Certificate Details -->
                        <div class="space-y-3 mb-4">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Certificate ID:</span>
                                <span class="certificate-code text-sm font-mono">
                                    <?php echo htmlspecialchars($certificate['certificate_code']); ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Issue Date:</span>
                                <span class="font-medium text-gray-900">
                                    <?php echo date('M j, Y', strtotime($certificate['issued_at'])); ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Modules Completed:</span>
                                <span class="font-medium text-green-600">
                                    <?php echo $certificate['total_modules']; ?> modules
                                </span>
                            </div>
                            
                            <?php if ($certificate['avg_quiz_score']): ?>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">Average Score:</span>
                                    <span class="font-medium text-blue-600">
                                        <?php echo round($certificate['avg_quiz_score']); ?>%
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Achievement Stats -->
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="text-center p-2 bg-gray-50 rounded-lg">
                                <div class="text-lg font-bold text-saffron-teal"><?php echo $certificate['total_modules']; ?></div>
                                <div class="text-xs text-gray-500">Modules</div>
                            </div>
                            <div class="text-center p-2 bg-gray-50 rounded-lg">
                                <div class="text-lg font-bold text-green-600"><?php echo $certificate['quizzes_passed']; ?></div>
                                <div class="text-xs text-gray-500">Quizzes</div>
                            </div>
                            <div class="text-center p-2 bg-gray-50 rounded-lg">
                                <div class="text-lg font-bold text-purple-600">
                                    <?php echo $certificate['avg_quiz_score'] ? round($certificate['avg_quiz_score']) . '%' : 'N/A'; ?>
                                </div>
                                <div class="text-xs text-gray-500">Avg Score</div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <a href="certificate.php?course_id=<?php echo $certificate['course_id']; ?>" 
                               class="flex-1 bg-saffron-teal hover:bg-opacity-90 text-white px-4 py-2 rounded-lg text-sm font-medium text-center transition-colors">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View Full Certificate
                            </a>
                            
                            <button onclick="downloadCertificate('<?php echo $certificate['certificate_code']; ?>')" 
                                    class="print-button bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Activity Timeline (if certificates exist) -->
<?php if (!empty($certificates)): ?>
    <div class="mt-12">
        <h3 class="text-xl font-bold text-gray-900 mb-6">🎯 Certification Timeline</h3>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="space-y-4">
                <?php 
                $sorted_certificates = $certificates;
                usort($sorted_certificates, function($a, $b) {
                    return strtotime($b['issued_at']) - strtotime($a['issued_at']);
                });
                
                foreach (array_slice($sorted_certificates, 0, 5) as $index => $cert): 
                ?>
                    <div class="flex items-center">
                        <div class="flex-shrink-0 w-8 h-8 bg-saffron-teal rounded-full flex items-center justify-center text-white text-sm font-bold">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($cert['course_title']); ?>
                                    </h4>
                                    <p class="text-sm text-gray-500">
                                        Certificate earned • <?php echo date('F j, Y', strtotime($cert['issued_at'])); ?>
                                    </p>
                                </div>
                                <div class="text-sm font-medium text-saffron-teal">
                                    <?php echo $cert['avg_quiz_score'] ? round($cert['avg_quiz_score']) . '% avg' : 'Completed'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($index < 4 && $index < count($sorted_certificates) - 1): ?>
                        <div class="ml-4 border-l-2 border-gray-200 h-4"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if (count($certificates) > 5): ?>
                    <div class="text-center pt-4 border-t border-gray-200">
                        <p class="text-sm text-gray-500">
                            and <?php echo count($certificates) - 5; ?> more certificate<?php echo count($certificates) - 5 > 1 ? 's' : ''; ?>...
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

</main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Certificate card animations
    const certificateCards = document.querySelectorAll('.certificate-card');
    certificateCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });

    // Stats cards animation
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Number counting animation for stats
    const statNumbers = document.querySelectorAll('.stats-card .text-3xl');
    statNumbers.forEach(number => {
        const finalValue = parseInt(number.textContent);
        if (!isNaN(finalValue) && finalValue > 0) {
            let currentValue = 0;
            const increment = Math.ceil(finalValue / 30);
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= finalValue) {
                    currentValue = finalValue;
                    clearInterval(timer);
                }
                number.textContent = currentValue + (number.textContent.includes('%') ? '%' : '');
            }, 50);
        }
    });
});

// Download certificate function
function downloadCertificate(certificateCode) {
    // Create a temporary link to trigger download
    const link = document.createElement('a');
    link.href = `certificate.php?course_id=${getCourseIdFromCode(certificateCode)}&download=1`;
    link.download = `certificate-${certificateCode}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Helper function to get course ID from certificate code (simplified)
function getCourseIdFromCode(certificateCode) {
    // Extract course ID from certificate code (format: LM-XXXXX-CourseID)
    const parts = certificateCode.split('-');
    return parts.length > 2 ? parts[parts.length - 1] : '1';
}

// Print functionality
function printCertificates() {
    window.print();
}

// Print styles
const printStyles = `
    @media print {
        .sidebar, .navbar, nav, header, .print-button { display: none !important; }
        .certificate-card { 
            break-inside: avoid; 
            page-break-inside: avoid;
            margin-bottom: 2rem;
        }
        body { margin: 0 !important; }
        .main-content { padding: 0 !important; }
    }
`;

// Add print styles
const styleSheet = document.createElement('style');
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);
</script>

</body>
</html>
