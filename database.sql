-- قاعدة بيانات نظام الاستئذان الإلكتروني
-- PostgreSQL
-- هذه النسخة آمنة ولا تحذف البيانات الموجودة.

CREATE TABLE IF NOT EXISTS leave_requests (
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

    whatsapp_opened BOOLEAN NOT NULL DEFAULT FALSE,
    whatsapp_opened_at TIMESTAMP NULL,

    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- تحديث الجداول القديمة بدون حذف السجلات
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS id_card_file VARCHAR(255);
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS appointment_letter_file VARCHAR(255);
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS sms_sent BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS sms_sent_at TIMESTAMP NULL;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS whatsapp_opened BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS whatsapp_opened_at TIMESTAMP NULL;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP NULL;

CREATE INDEX IF NOT EXISTS idx_leave_requests_status
ON leave_requests(status);

CREATE INDEX IF NOT EXISTS idx_leave_requests_student_name
ON leave_requests(student_name);

CREATE INDEX IF NOT EXISTS idx_leave_requests_created_at
ON leave_requests(created_at);

CREATE INDEX IF NOT EXISTS idx_leave_requests_phone
ON leave_requests(phone);
