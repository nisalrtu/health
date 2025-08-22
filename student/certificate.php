<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: ../student-login.php');
    exit();
}

// Get course ID
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$student_id = $_SESSION['student_id'];

if (!$course_id) {
    header('Location: courses.php');
    exit();
}

try {
    // Get certificate and course information
    $stmt = $pdo->prepare("
        SELECT 
            c.certificate_code,
            c.issued_at,
            c.verification_url,
            co.title as course_title,
            co.description as course_description,
            u.first_name,
            u.last_name,
            u.email,
            COUNT(DISTINCT m.id) as total_modules,
            AVG(CASE WHEN qa.passed = 1 THEN qa.score ELSE NULL END) as avg_quiz_score
        FROM certificates c
        JOIN courses co ON c.course_id = co.id
        JOIN users u ON c.user_id = u.id
        LEFT JOIN modules m ON co.id = m.course_id AND m.is_active = 1
        LEFT JOIN quizzes q ON m.id = q.module_id AND q.is_active = 1
        LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.user_id = u.id AND qa.passed = 1
        WHERE c.course_id = ? AND c.user_id = ?
        GROUP BY c.id, c.certificate_code, c.issued_at, c.verification_url, 
                 co.title, co.description, u.first_name, u.last_name, u.email
    ");
    
    $stmt->execute([$course_id, $student_id]);
    $certificate = $stmt->fetch();
    
    if (!$certificate) {
        $_SESSION['error'] = "Certificate not found or course not completed yet.";
        header('Location: courses.php');
        exit();
    }
    
    // Set page title
    $page_title = "Certificate - " . $certificate['course_title'];
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Error retrieving certificate: " . $e->getMessage();
    header('Location: courses.php');
    exit();
}

// Include header
include '../includes/student-header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap');
    
    .certificate-container {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 2rem 0;
    }
    
    .certificate {
        background: #ffffff;
        border: 15px solid #2d3748;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    
    .certificate::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 20px;
        right: 20px;
        bottom: 20px;
        border: 3px solid #5fb3b4;
        border-radius: 10px;
        pointer-events: none;
    }
    
    .certificate-header {
        background: linear-gradient(135deg, #5fb3b4 0%, #2d3748 100%);
        color: white;
        text-align: center;
        padding: 3rem 2rem 2rem;
        position: relative;
    }
    
    .certificate-title {
        font-family: 'Playfair Display', serif;
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    
    .certificate-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        font-weight: 300;
    }
    
    .certificate-body {
        padding: 3rem 4rem;
        text-align: center;
        position: relative;
        z-index: 1;
    }
    
    .recipient-text {
        font-size: 1.5rem;
        color: #4a5568;
        margin-bottom: 1rem;
        font-weight: 300;
    }
    
    .recipient-name {
        font-family: 'Playfair Display', serif;
        font-size: 3.5rem;
        font-weight: 700;
        color: #2d3748;
        margin: 1rem 0 2rem;
        text-decoration: underline;
        text-decoration-color: #5fb3b4;
        text-underline-offset: 8px;
    }
    
    .completion-text {
        font-size: 1.3rem;
        color: #4a5568;
        line-height: 1.8;
        margin-bottom: 2rem;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .course-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.2rem;
        font-weight: 700;
        color: #5fb3b4;
        margin: 1.5rem 0;
    }
    
    .certificate-footer {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 2rem;
        padding: 2rem 4rem 3rem;
        border-top: 2px solid #e2e8f0;
    }
    
    .footer-section {
        text-align: center;
    }
    
    .footer-label {
        font-size: 0.9rem;
        color: #718096;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }
    
    .footer-value {
        font-size: 1.1rem;
        color: #2d3748;
        font-weight: 600;
    }
    
    .certificate-code {
        color: #5fb3b4 !important;
        font-family: 'Courier New', monospace;
    }
    
    .decorative-element {
        position: absolute;
        width: 100px;
        height: 100px;
        background: rgba(95, 179, 180, 0.1);
        border-radius: 50%;
    }
    
    .decorative-element:nth-child(1) { top: 20px; left: 20px; }
    .decorative-element:nth-child(2) { top: 20px; right: 20px; }
    .decorative-element:nth-child(3) { bottom: 20px; left: 20px; }
    .decorative-element:nth-child(4) { bottom: 20px; right: 20px; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin: 2rem 0;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #f7fafc, #edf2f7);
        padding: 1.5rem;
        border-radius: 10px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #5fb3b4;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    @media print {
        .actions, .sidebar, .navbar, nav, header { display: none !important; }
        .certificate-container { background: white !important; padding: 0 !important; }
        .certificate { border: 2px solid #000 !important; margin: 0 !important; }
        body { margin: 0 !important; }
    }
    
    @media (max-width: 768px) {
        .certificate-container {
            padding: 1rem 0;
        }
        
        .certificate {
            border-width: 8px;
            border-radius: 15px;
            margin: 0 0.5rem;
        }
        
        .certificate::before {
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            border-width: 2px;
        }
        
        .certificate-header {
            padding: 2rem 1rem 1.5rem;
        }
        
        .certificate-title { 
            font-size: 2rem;
            line-height: 1.2;
        }
        
        .certificate-subtitle {
            font-size: 1rem;
        }
        
        .recipient-name { 
            font-size: 2.2rem;
            line-height: 1.2;
            margin: 1rem 0 1.5rem;
        }
        
        .course-title { 
            font-size: 1.6rem;
            line-height: 1.3;
            margin: 1rem 0;
        }
        
        .certificate-body { 
            padding: 2rem 1rem;
        }
        
        .recipient-text {
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
        }
        
        .completion-text {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .certificate-footer { 
            grid-template-columns: 1fr; 
            gap: 1.5rem; 
            padding: 1.5rem 1rem 2rem;
            text-align: center;
        }
        
        .footer-section {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .footer-label {
            font-size: 0.8rem;
            margin-bottom: 0.3rem;
        }
        
        .footer-value {
            font-size: 1rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-card {
            padding: 1.2rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
        }
        
        .decorative-element {
            width: 60px;
            height: 60px;
        }
        
        .decorative-element:nth-child(1) { top: 10px; left: 10px; }
        .decorative-element:nth-child(2) { top: 10px; right: 10px; }
        .decorative-element:nth-child(3) { bottom: 10px; left: 10px; }
        .decorative-element:nth-child(4) { bottom: 10px; right: 10px; }
    }
    
    @media (max-width: 480px) {
        .certificate-container {
            padding: 0.5rem 0;
        }
        
        .certificate {
            border-width: 5px;
            border-radius: 10px;
            margin: 0 0.25rem;
        }
        
        .certificate-title { 
            font-size: 1.6rem;
        }
        
        .certificate-subtitle {
            font-size: 0.9rem;
        }
        
        .recipient-name { 
            font-size: 1.8rem;
        }
        
        .course-title { 
            font-size: 1.4rem;
        }
        
        .certificate-body { 
            padding: 1.5rem 0.8rem;
        }
        
        .certificate-header {
            padding: 1.5rem 0.8rem 1rem;
        }
        
        .certificate-footer {
            padding: 1rem 0.8rem 1.5rem;
        }
        
        .recipient-text {
            font-size: 1rem;
        }
        
        .completion-text {
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .decorative-element {
            width: 40px;
            height: 40px;
        }
    }
</style>

<div class="certificate-container">
    <div class="max-w-4xl mx-auto px-4">
        <!-- Certificate -->
        <div class="certificate">
            <!-- Decorative Elements -->
            <div class="decorative-element"></div>
            <div class="decorative-element"></div>
            <div class="decorative-element"></div>
            <div class="decorative-element"></div>
            
            <!-- Certificate Header -->
            <div class="certificate-header">
                <h1 class="certificate-title">Certificate of Completion</h1>
                <p class="certificate-subtitle">Lifestyle Medicine Education Program</p>
            </div>
            
            <!-- Certificate Body -->
            <div class="certificate-body">
                <p class="recipient-text">This is to certify that</p>
                
                <h2 class="recipient-name">
                    <?php echo htmlspecialchars($certificate['first_name'] . ' ' . $certificate['last_name']); ?>
                </h2>
                
                <p class="completion-text">
                    has successfully completed the comprehensive course
                </p>
                
                <h3 class="course-title">
                    <?php echo htmlspecialchars($certificate['course_title']); ?>
                </h3>
                
                <p class="completion-text">
                    demonstrating proficiency in lifestyle medicine principles and practices, 
                    and has met all requirements for certification including completion of 
                    <strong><?php echo $certificate['total_modules']; ?> modules</strong> 
                    <?php if ($certificate['avg_quiz_score']): ?>
                        with an average quiz score of <strong><?php echo round($certificate['avg_quiz_score']); ?>%</strong>
                    <?php endif; ?>.
                </p>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $certificate['total_modules']; ?></div>
                        <div class="stat-label">Modules Completed</div>
                    </div>
                    <?php if ($certificate['avg_quiz_score']): ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo round($certificate['avg_quiz_score']); ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-card">
                        <div class="stat-value">✓</div>
                        <div class="stat-label">Certified</div>
                    </div>
                </div>
            </div>
            
            <!-- Certificate Footer -->
            <div class="certificate-footer">
                <div class="footer-section">
                    <div class="footer-label">Issue Date</div>
                    <div class="footer-value">
                        <?php echo date('F j, Y', strtotime($certificate['issued_at'])); ?>
                    </div>
                </div>
                
                <div class="footer-section">
                    <div class="footer-label">Certificate ID</div>
                    <div class="footer-value certificate-code">
                        <?php echo htmlspecialchars($certificate['certificate_code']); ?>
                    </div>
                </div>
                
                <div class="footer-section">
                    <div class="footer-label">Verification</div>
                    <div class="footer-value">
                        Digitally Verified
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const certificate = document.querySelector('.certificate');
    certificate.style.opacity = '0';
    certificate.style.transform = 'translateY(30px)';
    
    setTimeout(() => {
        certificate.style.transition = 'all 1s ease';
        certificate.style.opacity = '1';
        certificate.style.transform = 'translateY(0)';
    }, 100);
    
    // Add floating animation to decorative elements
    const decorativeElements = document.querySelectorAll('.decorative-element');
    decorativeElements.forEach((el, index) => {
        el.style.animation = `float ${3 + index * 0.5}s ease-in-out infinite`;
    });
    
    // Add keyframes for floating animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    `;
    document.head.appendChild(style);
});
</script>

</main>
</div>
</body>
</html>
