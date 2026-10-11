-- ============================================================
-- Student Test & Analysis Platform - Database Schema
-- Engine: MySQL 8.0+
-- Fluent 2 Design System
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. ADMINS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL DEFAULT 'Admin',
    role VARCHAR(50) NOT NULL DEFAULT 'super_admin' COMMENT 'super_admin, platform_admin, admin',
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default super_admin (password: admin123)
INSERT INTO admins (id, email, name, role, password_hash) VALUES
(1, 'admin@testplatform.com', 'Administrator', 'super_admin', '$2y$10$GplGcU3j94wcRek.tkhrLeAeSlf9YyEoqOZB81R9X/pnn.1Fk4R0a')
ON DUPLICATE KEY UPDATE role = 'super_admin';

-- ------------------------------------------------------------
-- 2. COLLEGES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS colleges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    college_code VARCHAR(20) UNIQUE COMMENT 'Auto-generated unique college ID (COL######)',
    nick_name VARCHAR(255) UNIQUE COMMENT 'Required unique nickname/short name',
    name VARCHAR(255) NOT NULL,
    address TEXT,
    country VARCHAR(100) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    district VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    pincode VARCHAR(10) DEFAULT NULL,
    established_year YEAR DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    logo VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    recognized_university VARCHAR(255) DEFAULT NULL,
    affiliated_university VARCHAR(255) DEFAULT NULL,
    autonomous VARCHAR(255) DEFAULT NULL,
    accreditation_naac TINYINT(1) NOT NULL DEFAULT 0,
    naac_grade VARCHAR(5) DEFAULT NULL,
    accreditation_nba TINYINT(1) NOT NULL DEFAULT 0,
    accreditation_aicte TINYINT(1) NOT NULL DEFAULT 0,
    accreditation_ugc TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_colleges_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. COLLEGE STREAMS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS college_streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    college_id INT NOT NULL,
    stream_name VARCHAR(255) NOT NULL,
    category ENUM('UGC', 'PGC') NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    UNIQUE KEY uk_college_stream (college_id, stream_name),
    INDEX idx_streams_college (college_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. COLLEGE BATCHES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS college_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    college_id INT NOT NULL,
    stream_id INT NOT NULL,
    academic_year VARCHAR(9) NOT NULL COMMENT 'e.g. 2024-2025',
    joining_year INT NOT NULL,
    joining_month INT NOT NULL COMMENT '1-12',
    course_duration INT NOT NULL COMMENT 'in years',
    ending_year INT NOT NULL,
    batch_nick_name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'upcoming', 'completed') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    FOREIGN KEY (stream_id) REFERENCES college_streams(id) ON DELETE CASCADE,
    INDEX idx_cbatches_college (college_id),
    INDEX idx_cbatches_stream (stream_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. COURSES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    college_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_college_course (college_id, name),
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    INDEX idx_courses_college (college_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. BATCHES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    section VARCHAR(10) DEFAULT NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_batches_course (course_id),
    INDEX idx_batches_course_section (course_id, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. STUDENTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    section VARCHAR(10) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    gender ENUM('male','female','other') NOT NULL,
    college_name VARCHAR(255) NOT NULL,
    branch VARCHAR(255) NOT NULL,
    roll_number VARCHAR(100) NOT NULL,
    year_of_joining INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
    INDEX idx_students_email (email),
    INDEX idx_students_batch (batch_id),
    INDEX idx_students_batch_section (batch_id, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. UNVERIFIED STUDENTS (Pre-OTP)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS unverified_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    section VARCHAR(10) DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    gender ENUM('male','female','other') NOT NULL,
    college_name VARCHAR(255) NOT NULL,
    branch VARCHAR(255) NOT NULL,
    roll_number VARCHAR(100) NOT NULL,
    year_of_joining INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    otp_hash VARCHAR(64) DEFAULT NULL,
    otp_expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    INDEX idx_unverified_email (email),
    INDEX idx_unverified_otp_expires (otp_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. FAILED LOGIN LOG
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS failed_login_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    student_name VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    attempt_type VARCHAR(50) NOT NULL COMMENT 'signup_unverified, wrong_password, invalid_email, expired_otp, wrong_otp, duplicate_email',
    reason VARCHAR(255) DEFAULT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_email (email),
    INDEX idx_failed_type (attempt_type),
    INDEX idx_failed_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. GUEST ENTRIES (QR / Guest Link)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS guest_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    test_id INT DEFAULT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    type ENUM('guest','qr') NOT NULL DEFAULT 'guest',
    temp_data JSON DEFAULT NULL,
    student_id INT DEFAULT NULL,
    status ENUM('pending','linked','expired') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_guest_token (token),
    INDEX idx_guest_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. TESTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL DEFAULT 30,
    total_marks INT NOT NULL DEFAULT 0,
    passing_marks INT NOT NULL DEFAULT 0,
    test_type VARCHAR(50) NOT NULL DEFAULT 'general',
    instructions TEXT DEFAULT NULL,
    negative_marking DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    status ENUM('upcoming','active','paused','completed','scheduled') NOT NULL DEFAULT 'upcoming',
    max_tab_switches INT DEFAULT NULL COMMENT 'NULL = unlimited, just recorded',
    shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id),
    INDEX idx_tests_batch (batch_id),
    INDEX idx_tests_status (status),
    INDEX idx_tests_type (test_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. TEST SECTIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS test_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    batch_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    UNIQUE KEY uk_test_batch (test_id, batch_id),
    INDEX idx_ts_test (test_id),
    INDEX idx_ts_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. QUESTIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    type ENUM('mcq','coding','explanation') NOT NULL DEFAULT 'mcq',
    question_text TEXT NOT NULL,
    options_json JSON DEFAULT NULL COMMENT 'For MCQ: [{"key":"A","text":"..."},...]',
    correct_answer TEXT DEFAULT NULL COMMENT 'MCQ correct option key / code / rubric reference',
    marks INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    INDEX idx_questions_test (test_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. SUBMISSIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_id INT NOT NULL,
    status ENUM('in_progress','submitted','evaluated') NOT NULL DEFAULT 'in_progress',
    evaluation_status ENUM('pending_manual_review','evaluated') NOT NULL DEFAULT 'pending_manual_review'
        COMMENT 'Hybrid grading: pending = awaiting manual review of coding/explanation answers',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME DEFAULT NULL,
    timer_extended_minutes INT NOT NULL DEFAULT 0,
    total_marks_obtained DECIMAL(10,2) DEFAULT NULL,
    total_marks DECIMAL(10,2) DEFAULT NULL,
    auto_score DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT 'MCQ points earned automatically at submit time',
    manual_score DECIMAL(6,2) DEFAULT NULL COMMENT 'Subjective points awarded by an admin',
    total_score DECIMAL(6,2) DEFAULT NULL COMMENT 'auto_score + manual_score once fully evaluated',
    evaluated_at DATETIME DEFAULT NULL,
    evaluator_id INT DEFAULT NULL,
    UNIQUE KEY uk_student_test (student_id, test_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_evaluator FOREIGN KEY (evaluator_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_submissions_status (status),
    INDEX idx_submissions_evalstatus (evaluation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 15. STUDENT ANSWERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_json JSON NOT NULL COMMENT 'MCQ: {"selected":"A"} / Coding: {"code":"..."} / Explanation: {"text":"..."}',
    marks_obtained DECIMAL(10,2) DEFAULT NULL,
    is_auto_graded TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = graded by machine (MCQ), 0 = graded by admin',
    evaluated_at DATETIME DEFAULT NULL,
    evaluation_remarks TEXT NULL COMMENT 'Evaluator feedback for coding/explanation answers',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_submission_question (submission_id, question_id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 16. TAB SWITCH LOGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tab_switch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    switch_count INT NOT NULL DEFAULT 1,
    type ENUM('switch','timer_extend') NOT NULL DEFAULT 'switch',
    metadata JSON DEFAULT NULL COMMENT 'For timer_extend: {"extended_by":5}',
    timestamp DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    INDEX idx_tab_submission (submission_id),
    INDEX idx_tab_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 17. PCI RECORDS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pci_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_id INT NOT NULL,
    pci_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '0.00 - 100.00',
    mcq_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    coding_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    explanation_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    mcq_weight DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    coding_weight DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    explanation_weight DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_test_pci (student_id, test_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    INDEX idx_pci_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 18. ADMIN NOTIFICATIONS (Bell icon feed)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_read (is_read),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 19. AUDIT LOG (Administrative change tracking)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    user_email VARCHAR(255) DEFAULT NULL,
    user_role VARCHAR(50) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT DEFAULT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
