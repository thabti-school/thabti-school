<?php
require_once 'config.php';

$sql = "
CREATE TABLE IF NOT EXISTS students (
    id SERIAL PRIMARY KEY,
    student_name VARCHAR(150),
    student_class VARCHAR(100),
    guardian_name VARCHAR(150),
    guardian_phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS requests (
    id SERIAL PRIMARY KEY,
    student_id INTEGER REFERENCES students(id),
    request_type VARCHAR(50),
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

try {
    $pdo->exec($sql);
    echo '✅ تم إنشاء الجداول بنجاح';
} catch (PDOException $e) {
    echo '❌ خطأ: ' . $e->getMessage();
}
?>
    <?php
require_once 'config.php';

$sql = "
CREATE TABLE IF NOT EXISTS students (
    id SERIAL PRIMARY KEY,
    student_name VARCHAR(150),
    student_class VARCHAR(100),
    guardian_name VARCHAR(150),
    guardian_phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS requests (
    id SERIAL PRIMARY KEY,
    student_id INTEGER REFERENCES students(id) ON DELETE CASCADE,
    request_type VARCHAR(50),
    reason TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

try {
    $pdo->exec($sql);
    echo 'تم إنشاء الجداول بنجاح';
} catch (PDOException $e) {
    echo 'خطأ: ' . $e->getMessage();
}
?>
