CREATE DATABASE IF NOT EXISTS thabti
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE thabti;

DROP TABLE IF EXISTS leave_requests;

CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(255) NOT NULL,
    grade VARCHAR(100) NOT NULL,
    section VARCHAR(100) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    exit_time VARCHAR(50) NOT NULL,
    receiver_name VARCHAR(255) NOT NULL,
    relationship VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'معلق',
    id_card_file VARCHAR(255) DEFAULT NULL,
    appointment_letter_file VARCHAR(255) DEFAULT NULL,
    whatsapp_opened TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_opened_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;