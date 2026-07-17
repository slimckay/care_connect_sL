-- =============================================
-- CARE CONNECT SL - Complete Database Schema
-- Version: 1.0
-- =============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS careconnect_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE careconnect_db;

-- =============================================
-- USERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient', 'doctor', 'hospital', 'admin') NOT NULL DEFAULT 'patient',
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(64) NULL,
    reset_token VARCHAR(64) NULL,
    reset_token_expires DATETIME NULL,
    last_login DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- REFERRALS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_name VARCHAR(100) NOT NULL,
    age INT NULL,
    contact VARCHAR(100) NOT NULL,
    location VARCHAR(200) NOT NULL,
    preferred_clinic VARCHAR(100) NULL,
    condition TEXT NOT NULL,
    referrer VARCHAR(50) DEFAULT 'self',
    user_id INT NULL,
    assigned_to INT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_location (location(100)),
    FULLTEXT INDEX idx_search (patient_name, condition, location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- CONTACT MESSAGES TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(50) NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- ACTIVITY LOGS TABLE (Audit Trail)
-- =============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PROVIDER PROFILES TABLE (For doctors/hospitals)
-- =============================================
CREATE TABLE IF NOT EXISTS provider_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    specialty VARCHAR(100) NULL,
    qualifications TEXT NULL,
    experience_years INT DEFAULT 0,
    clinic_name VARCHAR(100) NULL,
    clinic_address VARCHAR(200) NULL,
    clinic_phone VARCHAR(50) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    is_accepting_patients BOOLEAN DEFAULT TRUE,
    consultation_fee DECIMAL(10, 2) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_specialty (specialty),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- NOTIFICATIONS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SESSIONS TABLE (For database session storage)
-- =============================================
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- INSERT SAMPLE DATA
-- =============================================

-- Insert default admin user
-- Password: Admin123!
INSERT INTO users (name, email, password, role, status, email_verified) 
VALUES (
    'System Admin',
    'admin@careconnect.sl',
    '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq',
    'admin',
    'active',
    TRUE
) ON DUPLICATE KEY UPDATE email = email;

-- Insert sample users
-- Password for all sample users: Test123!
INSERT INTO users (name, email, password, role, status, email_verified, created_at) VALUES
('Mariama Sesay', 'mariama@email.com', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'patient', 'active', TRUE, '2026-01-15 10:00:00'),
('John Koroma', 'john@email.com', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'patient', 'active', TRUE, '2026-02-20 14:30:00'),
('Dr. Aminata Kamara', 'aminata@careconnect.sl', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'doctor', 'active', TRUE, '2026-01-05 09:00:00'),
('Dr. Mohamed Bangura', 'mohamed@careconnect.sl', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'doctor', 'active', TRUE, '2026-01-10 11:00:00'),
('Freetown Central Clinic', 'clinic@careconnect.sl', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'hospital', 'active', TRUE, '2026-01-01 08:00:00'),
('East End Hospital', 'eastend@careconnect.sl', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'hospital', 'active', TRUE, '2026-01-08 10:00:00'),
('Community Health Centre', 'community@careconnect.sl', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'hospital', 'active', TRUE, '2026-01-12 13:00:00'),
('Fatmata Bangura', 'fatmata@email.com', '$2y$12$MKGfXjwYRhxgKKNgsA7Lse6MysX.MpqQ3F3iYcPdsINhUg.7RcTiq', 'patient', 'active', TRUE, '2026-03-01 16:45:00')
ON DUPLICATE KEY UPDATE email = email;

-- Insert provider profiles
INSERT INTO provider_profiles (user_id, specialty, qualifications, experience_years, clinic_name, is_accepting_patients) VALUES
(
    (SELECT id FROM users WHERE email = 'aminata@careconnect.sl'),
    'Maternal & Child Health',
    'MBBS, MPH',
    8,
    'Freetown Maternity Clinic',
    TRUE
),
(
    (SELECT id FROM users WHERE email = 'mohamed@careconnect.sl'),
    'General Medicine',
    'MBBS',
    5,
    'Community Health Practice',
    TRUE
),
(
    (SELECT id FROM users WHERE email = 'clinic@careconnect.sl'),
    'General Practice',
    'Fully Equipped Clinic',
    10,
    'Freetown Central Clinic',
    TRUE
),
(
    (SELECT id FROM users WHERE email = 'eastend@careconnect.sl'),
    'Emergency Medicine',
    'Hospital Services',
    15,
    'East End Hospital',
    TRUE
),
(
    (SELECT id FROM users WHERE email = 'community@careconnect.sl'),
    'Primary Care',
    'Community Health Services',
    7,
    'Community Health Centre',
    TRUE
)
ON DUPLICATE KEY UPDATE user_id = user_id;

-- Insert sample referrals
INSERT INTO referrals (patient_name, age, contact, location, preferred_clinic, condition, status, user_id, created_at) VALUES
(
    'Mariama Sesay',
    28,
    '+232 76 111 222',
    'Freetown, Western Area',
    'Freetown Central Clinic',
    'Routine maternal checkup and prenatal care',
    'completed',
    (SELECT id FROM users WHERE email = 'mariama@email.com'),
    '2026-07-01 09:30:00'
),
(
    'John Koroma',
    45,
    '+232 76 333 444',
    'East End, Freetown',
    'East End Hospital',
    'Chest pain and difficulty breathing - possible heart condition',
    'in_progress',
    (SELECT id FROM users WHERE email = 'john@email.com'),
    '2026-07-05 14:15:00'
),
(
    'Fatmata Bangura',
    32,
    '+232 76 555 666',
    'Western Area Rural',
    'Community Health Centre',
    'Persistent headache and high blood pressure',
    'pending',
    (SELECT id FROM users WHERE email = 'fatmata@email.com'),
    '2026-07-07 10:00:00'
),
(
    'Mariama Sesay',
    28,
    '+232 76 111 222',
    'Freetown, Western Area',
    'Freetown Central Clinic',
    'Post-natal follow up for mother and newborn',
    'pending',
    (SELECT id FROM users WHERE email = 'mariama@email.com'),
    '2026-07-08 11:30:00'
),
(
    'Unregistered Patient - Aisha Conteh',
    7,
    '+232 76 777 888',
    'Eastern Freetown',
    'Community Health Centre',
    'Child with malaria symptoms - high fever and vomiting',
    'completed',
    NULL,
    '2026-06-28 08:45:00'
),
(
    'John Koroma',
    45,
    '+232 76 333 444',
    'East End, Freetown',
    'East End Hospital',
    'Follow-up cardiology appointment',
    'pending',
    (SELECT id FROM users WHERE email = 'john@email.com'),
    '2026-07-09 09:00:00'
)
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample contact messages
INSERT INTO contact_messages (name, email, phone, message, status, created_at) VALUES
(
    'Mariama Sesay',
    'mariama@email.com',
    '+232 76 111 222',
    'I would like to know if you offer home-based care for elderly patients in Freetown. My mother is 78 and has difficulty visiting clinics.',
    'read',
    '2026-07-02 10:15:00'
),
(
    'Dr. Mohamed Bangura',
    'mohamed@careconnect.sl',
    '+232 76 444 555',
    'I would like to join the provider network. I specialize in general medicine and can accept referrals in the Western Area.',
    'replied',
    '2026-07-04 13:45:00'
),
(
    'Community Health Worker - Mary',
    'mary@email.com',
    '+232 76 999 000',
    'Is there a mobile app available for community health workers to manage referrals on the go?',
    'new',
    '2026-07-06 16:20:00'
)
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample activity logs
INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES
((SELECT id FROM users WHERE email = 'admin@careconnect.sl'), 'login', 'Admin logged in successfully', '127.0.0.1', '2026-07-07 08:00:00'),
((SELECT id FROM users WHERE email = 'mariama@email.com'), 'referral_submit', 'Submitted referral for maternal checkup', '127.0.0.1', '2026-07-01 09:30:00'),
((SELECT id FROM users WHERE email = 'john@email.com'), 'referral_submit', 'Submitted referral for chest pain', '127.0.0.1', '2026-07-05 14:15:00'),
(NULL, 'contact_form', 'Contact form submission from Mariama Sesay', '127.0.0.1', '2026-07-02 10:15:00'),
((SELECT id FROM users WHERE email = 'admin@careconnect.sl'), 'referral_update', 'Updated referral status to completed for Mariama Sesay', '127.0.0.1', '2026-07-03 11:00:00'),
(NULL, 'registration', 'New user registration - Fatmata Bangura', '127.0.0.1', '2026-03-01 16:45:00')
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample notifications
INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES
((SELECT id FROM users WHERE email = 'mariama@email.com'), 'referral_update', 'Referral Completed', 'Your maternal checkup referral has been completed at Freetown Central Clinic.', 'pages/referral.html', TRUE, '2026-07-03 11:00:00'),
((SELECT id FROM users WHERE email = 'john@email.com'), 'referral_update', 'Referral In Progress', 'Your referral for chest pain has been assigned to East End Hospital.', 'pages/referral.html', FALSE, '2026-07-05 14:30:00'),
((SELECT id FROM users WHERE email = 'fatmata@email.com'), 'referral_update', 'Referral Submitted', 'Your referral for headache and high blood pressure has been submitted successfully.', 'pages/referral.html', FALSE, '2026-07-07 10:00:00'),
((SELECT id FROM users WHERE email = 'mariama@email.com'), 'appointment', 'New Appointment Available', 'You have a post-natal appointment scheduled for July 15th at Freetown Central Clinic.', 'pages/doctors.html', FALSE, '2026-07-08 11:30:00')
ON DUPLICATE KEY UPDATE id = id;

-- =============================================
-- CREATE VIEWS FOR EASY REPORTING
-- =============================================

-- View for referral statistics
CREATE OR REPLACE VIEW referral_stats AS
SELECT 
    COUNT(*) as total_referrals,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    DATE_FORMAT(MIN(created_at), '%Y-%m-%d') as first_referral,
    DATE_FORMAT(MAX(created_at), '%Y-%m-%d') as last_referral
FROM referrals;

-- View for user activity
CREATE OR REPLACE VIEW user_activity AS
SELECT 
    u.id,
    u.name,
    u.email,
    u.role,
    u.status,
    u.last_login,
    u.created_at,
    COUNT(DISTINCT r.id) as referral_count,
    COUNT(DISTINCT a.id) as activity_count
FROM users u
LEFT JOIN referrals r ON u.id = r.user_id
LEFT JOIN activity_logs a ON u.id = a.user_id
GROUP BY u.id;

-- =============================================
-- CREATE STORED PROCEDURES
-- =============================================

-- Get monthly referral counts
DELIMITER //
CREATE PROCEDURE get_monthly_referrals(IN year_input INT)
BEGIN
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as referral_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM referrals
    WHERE YEAR(created_at) = year_input
    GROUP BY MONTH(created_at)
    ORDER BY month;
END //
DELIMITER ;

-- =============================================
-- INDEXES FOR PERFORMANCE
-- =============================================

-- Additional indexes for common queries
CREATE INDEX idx_referrals_status_created ON referrals(status, created_at);
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_contact_status ON contact_messages(status, created_at);

-- =============================================
-- FINAL CHECK: Show all tables
-- =============================================

SHOW TABLES;
SELECT '✅ Database setup complete!' as Status;
SELECT COUNT(*) as Total_Users FROM users;
SELECT COUNT(*) as Total_Referrals FROM referrals;
SELECT COUNT(*) as Total_Contacts FROM contact_messages;
SELECT COUNT(*) as Total_Logs FROM activity_logs;

-- =============================================
-- TEST QUERIES - Run these to verify everything works
-- =============================================

-- Test 1: Get all users
SELECT id, name, email, role, status FROM users LIMIT 5;

-- Test 2: Get referral counts by status
SELECT status, COUNT(*) as count FROM referrals GROUP BY status;

-- Test 3: Get recent activity
SELECT action, COUNT(*) as count FROM activity_logs 
GROUP BY action 
ORDER BY count DESC;

-- Test 4: Test the view
SELECT * FROM referral_stats;

-- Test 5: Test the stored procedure
CALL get_monthly_referrals(2026);

SELECT '✅ All tests passed!' as Status;
