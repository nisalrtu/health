-- Lifestyle Medicine Learning App Database
-- Drop database if exists and create new one
DROP DATABASE IF EXISTS lifestyle_medicine_app;
CREATE DATABASE lifestyle_medicine_app;
USE lifestyle_medicine_app;

-- Users Table (Students)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    institute_name VARCHAR(100),
    age INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admins Table
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Courses Table
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Modules Table
CREATE TABLE modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    order_sequence INT NOT NULL,
    pass_threshold INT DEFAULT 70,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Lessons Table
CREATE TABLE lessons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    order_sequence INT NOT NULL,
    estimated_duration INT COMMENT 'Duration in minutes',
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

-- Quizzes Table
CREATE TABLE quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT,
    title VARCHAR(200) NOT NULL,
    quiz_type ENUM('module', 'final') NOT NULL,
    pass_threshold INT DEFAULT 70,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);

-- Questions Table
CREATE TABLE questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false') NOT NULL,
    points INT DEFAULT 1,
    order_sequence INT NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Question Options Table
CREATE TABLE question_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_sequence INT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- User Progress Table
CREATE TABLE user_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    course_id INT,
    module_id INT,
    lesson_id INT,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- Quiz Attempts Table
CREATE TABLE quiz_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    quiz_id INT,
    attempt_number INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    passed BOOLEAN NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- User Answers Table
CREATE TABLE user_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT,
    question_id INT,
    selected_option_id INT,
    answer_text TEXT,
    is_correct BOOLEAN NOT NULL,
    points_earned INT DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE SET NULL
);

-- Certificates Table
CREATE TABLE certificates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    course_id INT,
    certificate_code VARCHAR(50) UNIQUE NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_url VARCHAR(500),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_admins_email ON admins(email);
CREATE INDEX idx_modules_course ON modules(course_id, order_sequence);
CREATE INDEX idx_lessons_module ON lessons(module_id, order_sequence);
CREATE INDEX idx_quizzes_module ON quizzes(module_id);
CREATE INDEX idx_questions_quiz ON questions(quiz_id, order_sequence);
CREATE INDEX idx_question_options_question ON question_options(question_id);
CREATE INDEX idx_user_progress_user ON user_progress(user_id);
CREATE INDEX idx_user_progress_course ON user_progress(course_id);
CREATE INDEX idx_quiz_attempts_user ON quiz_attempts(user_id);
CREATE INDEX idx_quiz_attempts_quiz ON quiz_attempts(quiz_id);
CREATE INDEX idx_user_answers_attempt ON user_answers(attempt_id);
CREATE INDEX idx_certificates_user ON certificates(user_id);
CREATE INDEX idx_certificates_course ON certificates(course_id);
CREATE INDEX idx_certificates_code ON certificates(certificate_code);

-- Insert sample data for testing

-- Sample admin user
INSERT INTO admins (email, password_hash, first_name, last_name) VALUES 
('admin@lifestylemedicine.com', '$2y$10$example_hash_here', 'Admin', 'User');

-- Sample course
INSERT INTO courses (title, description) VALUES 
('Introduction to Lifestyle Medicine', 'A comprehensive introduction to the principles and practices of lifestyle medicine for students.');

-- Sample modules for the course
INSERT INTO modules (course_id, title, description, order_sequence) VALUES 
(1, 'Foundations of Lifestyle Medicine', 'Understanding the basic principles of lifestyle medicine', 1),
(1, 'Nutrition and Diet', 'The role of nutrition in health and disease prevention', 2),
(1, 'Physical Activity and Exercise', 'Exercise as medicine and its health benefits', 3);

-- Sample lessons for first module
INSERT INTO lessons (module_id, title, content, order_sequence, estimated_duration) VALUES 
(1, 'What is Lifestyle Medicine?', 'Lifestyle medicine is a medical specialty that uses therapeutic lifestyle interventions as a primary modality to treat chronic conditions...', 1, 15),
(1, 'The Six Pillars of Lifestyle Medicine', 'The six pillars include nutrition, physical activity, stress management, sleep, social connections, and avoiding risky substances...', 2, 20);

-- Sample quiz for first module
INSERT INTO quizzes (module_id, title, quiz_type, pass_threshold) VALUES 
(1, 'Foundations Quiz', 'module', 70);

-- Sample questions
INSERT INTO questions (quiz_id, question_text, question_type, order_sequence) VALUES 
(1, 'Lifestyle medicine focuses primarily on which type of interventions?', 'multiple_choice', 1),
(1, 'Lifestyle medicine can help prevent chronic diseases.', 'true_false', 2);

-- Sample options for multiple choice question
INSERT INTO question_options (question_id, option_text, is_correct, order_sequence) VALUES 
(1, 'Pharmaceutical interventions', FALSE, 1),
(1, 'Surgical procedures', FALSE, 2),
(1, 'Therapeutic lifestyle interventions', TRUE, 3),
(1, 'Diagnostic procedures', FALSE, 4);

-- Sample options for true/false question
INSERT INTO question_options (question_id, option_text, is_correct, order_sequence) VALUES 
(2, 'True', TRUE, 1),
(2, 'False', FALSE, 2);

-- Final quiz for the course
INSERT INTO quizzes (module_id, title, quiz_type, pass_threshold) VALUES 
(3, 'Course Final Exam', 'final', 80);

COMMIT;

-- Display success message
SELECT 'Database created successfully!' AS Status;