<?php
header('Content-Type: application/json; charset=utf-8');

$start = microtime(true);

try {

    // تحميل الإعدادات
    $config = require __DIR__ . '/config.php';

    if (!isset($config['db'])) {
        throw new Exception('إعدادات قاعدة البيانات غير موجودة');
    }

    // إنشاء الاتصال
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";

    $pdo = new PDO(
        $dsn,
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_PERSISTENT => false,
        ]
    );

    // اختبار بسيط وخفيف
    $pdo->query("SELECT 1");

    $dbStatus = "connected";

} catch (Throwable $e) {

    $dbStatus = "error";

    $errorMessage = $e->getMessage();
}

// حساب الزمن
$executionTime = round((microtime(true) - $start) * 1000, 2);

// الرد النهائي
echo json_encode([
    'success' => true,
    'service' => 'thabti-school',
    'status' => 'online',
    'database' => $dbStatus,
    'response_time_ms' => $executionTime,
    'time' => date('c'),
    'error' => $dbStatus === 'error' ? $errorMessage : null
], JSON_UNESCAPED_UNICODE);
