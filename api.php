<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}

/* =========================
   PostgreSQL Connection
========================= */

function createPdo(array $config): PDO
{
    if (!extension_loaded('pdo_pgsql')) {
        throw new Exception(
            'امتداد PostgreSQL غير مثبت على الخادم (pdo_pgsql). يجب إضافته إلى بيئة PHP في Render.'
        );
    }

    $databaseUrl = trim((string)(
        $config['database_url']
        ?? getenv('DATABASE_URL')
        ?? ''
    ));

    if ($databaseUrl === '') {
        throw new Exception('DATABASE_URL غير موجود في إعدادات Render');
    }

    $parts = parse_url($databaseUrl);

    if ($parts === false || !is_array($parts)) {
        throw new Exception('DATABASE_URL غير صالح');
    }

    $host = (string)($parts['host'] ?? '');
    $port = (int)($parts['port'] ?? 5432);
    $user = isset($parts['user']) ? urldecode((string)$parts['user']) : '';
    $pass = isset($parts['pass']) ? urldecode((string)$parts['pass']) : '';
    $name = ltrim((string)($parts['path'] ?? ''), '/');

    if ($host === '' || $user === '' || $name === '') {
        throw new Exception('بيانات PostgreSQL غير مكتملة داخل DATABASE_URL');
    }

    // إذا احتوى الرابط على sslmode نستخدمه، وإلا نستخدم require.
    $sslMode = 'require';
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        if (!empty($query['sslmode'])) {
            $sslMode = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$query['sslmode']);
        }
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $host,
        $port,
        $name,
        $sslMode
    );

    return new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
}

/* =========================
   Database schema
   ينشئ/يحدّث الجدول بدون حذف البيانات
========================= */

function ensureDatabaseSchema(PDO $pdo): void
{
    $pdo->exec("
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
            approved_at TIMESTAMP NULL,
            rejected_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // تحديث آمن لجدول قديم إن وُجد.
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS sms_sent BOOLEAN NOT NULL DEFAULT FALSE");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS sms_sent_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS id_card_file VARCHAR(255)");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS appointment_letter_file VARCHAR(255)");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leave_requests_status ON leave_requests(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leave_requests_student_name ON leave_requests(student_name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leave_requests_created_at ON leave_requests(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leave_requests_phone ON leave_requests(phone)");
}

/* =========================
   Database retry
========================= */

function isRetryableDbError(Throwable $e): bool
{
    $message = strtolower($e->getMessage());

    $patterns = [
        'server closed the connection',
        'connection refused',
        'connection reset',
        'could not connect',
        'connection timed out',
        'terminating connection',
        'ssl connection',
        'temporary failure',
        'connection is closed'
    ];

    foreach ($patterns as $pattern) {
        if (strpos($message, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

function withDbRetry(callable $callback, array $config)
{
    $lastException = null;

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            $pdo = createPdo($config);

            // اختبار الاتصال
            $pdo->query('SELECT 1');

            // إنشاء/تحديث بنية الجدول دون حذف أي سجل
            ensureDatabaseSchema($pdo);

            return $callback($pdo);

        } catch (Throwable $e) {
            $lastException = $e;

            if ($attempt < 3 && isRetryableDbError($e)) {
                sleep($attempt);
                continue;
            }

            throw $e;
        }
    }

    throw $lastException ?? new Exception('تعذر الاتصال بقاعدة البيانات');
}

/* =========================
   Uploads
========================= */

function ensureUploadsDir(string $dir): void
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new Exception('تعذر إنشاء مجلد المرفقات');
        }
    }

    if (!is_writable($dir)) {
        throw new Exception('مجلد المرفقات غير قابل للكتابة على الخادم');
    }
}

function uploadFile(string $fileKey, string $prefix, string $uploadDir): ?string
{
    if (!isset($_FILES[$fileKey])) {
        return null;
    }

    $file = $_FILES[$fileKey];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('حدث خطأ أثناء رفع الملف: ' . (int)$file['error']);
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new Exception('حجم المرفق يجب ألا يتجاوز 5 ميجابايت');
    }

    $tmp = (string)($file['tmp_name'] ?? '');

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new Exception('الملف المرفوع غير صالح');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf'
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception('يسمح فقط بملفات JPG وPNG وPDF');
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];

    $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new Exception('تعذر حفظ المرفق');
    }

    return 'uploads/' . $filename;
}

function deleteUploadedFile(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    // حماية من تمرير مسار خارج مجلد uploads.
    $normalized = str_replace('\\', '/', ltrim($relativePath, '/'));
    if (strpos($normalized, 'uploads/') !== 0) {
        return;
    }

    $file = __DIR__ . '/' . $normalized;

    if (is_file($file)) {
        @unlink($file);
    }
}

/* =========================
   Actions
========================= */

$action = $_GET['action'] ?? '';

switch ($action) {

/* =========================
   HEALTH CHECK
   افتحي api.php?action=health
========================= */

case 'health':
    try {
        $result = withDbRetry(function (PDO $pdo) {
            $version = $pdo->query("SELECT version()")->fetchColumn();
            $count = $pdo->query("SELECT COUNT(*) FROM leave_requests")->fetchColumn();

            return [
                'database' => 'connected',
                'driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                'version' => $version,
                'records' => (int)$count
            ];
        }, $config);

        jsonResponse([
            'success' => true,
            'message' => 'الاتصال بقاعدة PostgreSQL يعمل بنجاح',
            'data' => $result
        ]);
    } catch (Throwable $e) {
        error_log('HEALTH CHECK ERROR: ' . $e->getMessage());

        jsonResponse([
            'success' => false,
            'message' => 'فشل اختبار قاعدة البيانات: ' . $e->getMessage()
        ], 500);
    }
break;

/* =========================
   LIST
========================= */

case 'list':
    try {
        $rows = withDbRetry(function (PDO $pdo) {
            $stmt = $pdo->query("
                SELECT *
                FROM leave_requests
                ORDER BY id DESC
            ");
            return $stmt->fetchAll();
        }, $config);

        jsonResponse([
            'success' => true,
            'data' => $rows
        ]);

    } catch (Throwable $e) {
        error_log('LIST ERROR: ' . $e->getMessage());

        jsonResponse([
            'success' => false,
            'message' => 'تعذر جلب سجلات الاستئذان: ' . $e->getMessage()
        ], 500);
    }
break;

/* =========================
   CREATE
========================= */

case 'create':
    $idCardPath = null;
    $appointmentPath = null;

    try {
        $studentName  = trim($_POST['student_name'] ?? '');
        $grade        = trim($_POST['grade'] ?? '');
        $section      = trim($_POST['section'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $reason       = trim($_POST['reason'] ?? '');
        $exitTime     = trim($_POST['exit_time'] ?? '');
        $receiverName = trim($_POST['receiver_name'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');

        if (
            $studentName === '' ||
            $grade === '' ||
            $section === '' ||
            $phone === '' ||
            $reason === '' ||
            $exitTime === '' ||
            $receiverName === '' ||
            $relationship === ''
        ) {
            jsonResponse([
                'success' => false,
                'message' => 'يرجى تعبئة جميع الحقول المطلوبة'
            ], 400);
        }

        // يسمح برقم عُماني بصيغ مثل 9XXXXXXX أو +9689XXXXXXX.
        if (!preg_match('/^[0-9+\s-]{8,20}$/', $phone)) {
            jsonResponse([
                'success' => false,
                'message' => 'رقم الهاتف غير صالح'
            ], 400);
        }

        $uploadDir = __DIR__ . '/uploads';
        ensureUploadsDir($uploadDir);

        $idCardPath = uploadFile('id_card_file', 'id', $uploadDir);
        $appointmentPath = uploadFile('appointment_letter_file', 'appointment', $uploadDir);

        $newId = withDbRetry(function (PDO $pdo) use (
            $studentName,
            $grade,
            $section,
            $phone,
            $reason,
            $exitTime,
            $receiverName,
            $relationship,
            $idCardPath,
            $appointmentPath
        ) {
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests
                (
                    student_name,
                    grade,
                    section,
                    phone,
                    reason,
                    exit_time,
                    receiver_name,
                    relationship,
                    status,
                    id_card_file,
                    appointment_letter_file,
                    sms_sent,
                    created_at
                )
                VALUES
                (
                    :student_name,
                    :grade,
                    :section,
                    :phone,
                    :reason,
                    :exit_time,
                    :receiver_name,
                    :relationship,
                    'معلق',
                    :id_card,
                    :appointment,
                    FALSE,
                    CURRENT_TIMESTAMP
                )
                RETURNING id
            ");

            $stmt->execute([
                ':student_name' => $studentName,
                ':grade' => $grade,
                ':section' => $section,
                ':phone' => $phone,
                ':reason' => $reason,
                ':exit_time' => $exitTime,
                ':receiver_name' => $receiverName,
                ':relationship' => $relationship,
                ':id_card' => $idCardPath,
                ':appointment' => $appointmentPath
            ]);

            return $stmt->fetchColumn();
        }, $config);

        jsonResponse([
            'success' => true,
            'message' => 'تم إرسال طلب الاستئذان بنجاح',
            'id' => (int)$newId
        ]);

    } catch (Throwable $e) {
        // إذا فشل الحفظ بعد رفع الملفات، نحذف الملفات حتى لا تبقى بلا سجل.
        deleteUploadedFile($idCardPath);
        deleteUploadedFile($appointmentPath);

        $errorMessage = $e->getMessage();

        error_log('CREATE REQUEST ERROR: ' . $errorMessage);

        // تشخيص مؤقت: نعرض تفاصيل الخطأ حتى نكمل الإصلاح.
        jsonResponse([
            'success' => false,
            'message' => 'خطأ قاعدة البيانات: ' . $errorMessage
        ], 500);
    }
break;

/* =========================
   APPROVE
========================= */

case 'approve':
    $data = getJsonInput();
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'رقم الطلب غير صالح'
        ], 400);
    }

    try {
        $request = withDbRetry(function (PDO $pdo) use ($id) {
            $stmt = $pdo->prepare("
                UPDATE leave_requests
                SET
                    status = 'موافق عليه',
                    approved_at = CURRENT_TIMESTAMP,
                    rejected_at = NULL
                WHERE id = :id
                RETURNING
                    id,
                    student_name,
                    phone,
                    exit_time,
                    status
            ");

            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        }, $config);

        if (!$request) {
            jsonResponse([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        jsonResponse([
            'success' => true,
            'message' => 'تم اعتماد الطلب من مديرة المدرسة',
            'data' => $request
        ]);

    } catch (Throwable $e) {
        error_log('APPROVE ERROR: ' . $e->getMessage());

        jsonResponse([
            'success' => false,
            'message' => 'تعذر اعتماد الطلب: ' . $e->getMessage()
        ], 500);
    }
break;

/* =========================
   REJECT
========================= */

case 'reject':
    $data = getJsonInput();
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'رقم الطلب غير صالح'
        ], 400);
    }

    try {
        $request = withDbRetry(function (PDO $pdo) use ($id) {
            $stmt = $pdo->prepare("
                UPDATE leave_requests
                SET
                    status = 'مرفوض',
                    rejected_at = CURRENT_TIMESTAMP,
                    approved_at = NULL
                WHERE id = :id
                RETURNING id
            ");

            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        }, $config);

        if (!$request) {
            jsonResponse([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        jsonResponse([
            'success' => true,
            'message' => 'تم رفض الطلب'
        ]);

    } catch (Throwable $e) {
        error_log('REJECT ERROR: ' . $e->getMessage());

        jsonResponse([
            'success' => false,
            'message' => 'تعذر رفض الطلب: ' . $e->getMessage()
        ], 500);
    }
break;

/* =========================
   DELETE
========================= */

case 'delete':
    $data = getJsonInput();
    $id = (int)($data['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'رقم الطلب غير صالح'
        ], 400);
    }

    try {
        $record = withDbRetry(function (PDO $pdo) use ($id) {
            $stmt = $pdo->prepare("
                DELETE FROM leave_requests
                WHERE id = :id
                RETURNING
                    id_card_file,
                    appointment_letter_file
            ");

            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        }, $config);

        if (!$record) {
            jsonResponse([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        deleteUploadedFile($record['id_card_file'] ?? null);
        deleteUploadedFile($record['appointment_letter_file'] ?? null);

        jsonResponse([
            'success' => true,
            'message' => 'تم حذف الطلب بنجاح'
        ]);

    } catch (Throwable $e) {
        error_log('DELETE ERROR: ' . $e->getMessage());

        jsonResponse([
            'success' => false,
            'message' => 'تعذر حذف الطلب: ' . $e->getMessage()
        ], 500);
    }
break;

default:
    jsonResponse([
        'success' => false,
        'message' => 'إجراء غير صالح'
    ], 400);
break;
}
