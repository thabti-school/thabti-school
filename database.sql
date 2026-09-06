DROP TABLE IF EXISTS leave_requests;

CREATE TABLE leave_requests (
    id BIGSERIAL PRIMARY KEY,

    student_name VARCHAR(255) NOT NULL,
    grade VARCHAR(100) NOT NULL,
    section VARCHAR(100) NOT NULL,
    phone VARCHAR(50) NOT NULL,

    reason VARCHAR(255) NOT NULL,
    exit_time VARCHAR(50) NOT NULL,

    receiver_name VARCHAR(255) NOT NULL,
    relationship VARCHAR(100) NOT NULL,

    status VARCHAR(50) NOT NULL DEFAULT 'معلق',

    id_card_file VARCHAR(255),
    appointment_letter_file VARCHAR(255),

    sms_sent BOOLEAN NOT NULL DEFAULT FALSE,
    sms_sent_at TIMESTAMP NULL,

    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_leave_requests_status
ON leave_requests(status);

CREATE INDEX idx_leave_requests_student_name
ON leave_requests(student_name);

CREATE INDEX idx_leave_requests_created_at
ON leave_requests(created_at);

CREATE INDEX idx_leave_requests_phone
ON leave_requests(phone);
