<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$config = require __DIR__ . '/config.php';

/* =========================
   Helpers
========================= */

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    $decoded = json_decode($input ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        jsonResponse([
            'success' => false,
            'message' => 'غير مصرح. يرجى تسجيل دخول الإدارة أولاً.'
        ], 401);
    }
}

/* =========================
   PostgreSQL Connection
========================= */

function createPdo(array $config): PDO
{
    if (!extension_loaded('pdo_pgsql')) {
        throw new Exception(
            'امتداد PostgreSQL غير مثبت على الخادم (pdo_pgsql).'
        );
    }

    $databaseUrl = trim((string)(
        $config['database_url']
        ?? getenv('DATABASE_URL')
        ?? ''
    ));

    if ($databaseUrl === '') {
        throw new Exception('DATABASE_URL غير موجود في إعدادات الخادم');
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

    $sslMode = 'require';

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);

        if (!empty($query['sslmode'])) {
            $sslMode = preg_replace(
                '/[^a-zA-Z0-9_-]/',
                '',
                (string)$query['sslmode']
            ) ?: 'require';
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
   تحديث آمن بدون حذف البيانات
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
            whatsapp_opened BOOLEAN NOT NULL DEFAULT FALSE,
            whatsapp_opened_at TIMESTAMP NULL,
            approved_at TIMESTAMP NULL,
            rejected_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS id_card_file VARCHAR(255)");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS appointment_letter_file VARCHAR(255)");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS sms_sent BOOLEAN NOT NULL DEFAULT FALSE");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS sms_sent_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS whatsapp_opened BOOLEAN NOT NULL DEFAULT FALSE");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS whatsapp_opened_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP NULL");

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
            $pdo->query('SELECT 1');
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

function hasUpload(string $fileKey): bool
{
    return isset($_FILES[$fileKey])
        && (($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
}

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
        throw new Exception(
            'حدث خطأ أثناء رفع الملف: ' . (int)$file['error']
        );
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

    /* ---------- HEALTH ---------- */

    case 'health':
        try {
            $result = withDbRetry(function (PDO $pdo) {
                $version = $pdo->query("SELECT version()")->fetchColumn();
                $count = $pdo->query("SELECT COUNT(*) FROM leave_requests")->fetchColumn();

                $uploadDir = __DIR__ . '/uploads';

                return [
                    'database' => 'connected',
                    'driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                    'version' => $version,
                    'records' => (int)$count,
                    'uploads_exists' => is_dir($uploadDir),
                    'uploads_writable' => is_dir($uploadDir) ? is_writable($uploadDir) : is_writable(__DIR__)
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

    /* ---------- ADMIN LOGIN ---------- */

    case 'admin_login':
        $data = getJsonInput();
        $password = (string)($data['password'] ?? '');
        $configuredPassword = (string)($config['admin_password'] ?? '');

        if ($configuredPassword === '') {
            jsonResponse([
                'success' => false,
                'message' => 'ADMIN_PASSWORD غير مضبوط في إعدادات الخادم'
            ], 500);
        }

        if (!hash_equals($configuredPassword, $password)) {
            jsonResponse([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة'
            ], 401);
        }

        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;

        jsonResponse([
            'success' => true,
            'message' => 'تم تسجيل دخول الإدارة بنجاح'
        ]);
        break;

    /* ---------- ADMIN SESSION ---------- */

    case 'admin_session':
        jsonResponse([
            'success' => true,
            'authenticated' => isAdminLoggedIn()
        ]);
        break;

    /* ---------- ADMIN LOGOUT ---------- */

    case 'admin_logout':
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        jsonResponse([
            'success' => true,
            'message' => 'تم تسجيل الخروج'
        ]);
        break;

    /* ---------- LIST ---------- */

    case 'list':
        requireAdmin();

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
                'message' => 'تعذر جلب سجلات الاستئذان'
            ], 500);
        }
        break;

    /* ---------- CREATE ---------- */

    case 'create':
        $idCardPath = null;
        $appointmentPath = null;

        try {
            $studentName  = trim((string)($_POST['student_name'] ?? ''));
            $grade        = trim((string)($_POST['grade'] ?? ''));
            $section      = trim((string)($_POST['section'] ?? ''));
            $phone        = trim((string)($_POST['phone'] ?? ''));
            $reason       = trim((string)($_POST['reason'] ?? ''));
            $exitTime     = trim((string)($_POST['exit_time'] ?? ''));
            $receiverName = trim((string)($_POST['receiver_name'] ?? ''));
            $relationship = trim((string)($_POST['relationship'] ?? ''));

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

            if (!preg_match('/^[0-9+\s-]{8,20}$/', $phone)) {
                jsonResponse([
                    'success' => false,
                    'message' => 'رقم الهاتف غير صالح'
                ], 400);
            }

            $uploadDir = __DIR__ . '/uploads';

            // لا نحاول إنشاء/فحص مجلد uploads إلا إذا كان هناك مرفق فعلي.
            // هذا يمنع فشل الطلبات العادية بسبب صلاحيات مجلد المرفقات.
            if (hasUpload('id_card_file') || hasUpload('appointment_letter_file')) {
                ensureUploadsDir($uploadDir);
            }

            $idCardPath = uploadFile('id_card_file', 'id', $uploadDir);
            $appointmentPath = uploadFile(
                'appointment_letter_file',
                'appointment',
                $uploadDir
            );

            if ($reason === 'موعد مستشفى' && !$appointmentPath) {
                deleteUploadedFile($idCardPath);

                jsonResponse([
                    'success' => false,
                    'message' => 'رسالة الموعد إلزامية عند اختيار موعد مستشفى'
                ], 400);
            }

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
                        whatsapp_opened,
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
            deleteUploadedFile($idCardPath);
            deleteUploadedFile($appointmentPath);

            error_log('CREATE REQUEST ERROR: ' . $e->getMessage());

            $response = [
                'success' => false,
                'message' => 'تعذر حفظ الطلب. يرجى المحاولة مرة أخرى.'
            ];

            // للتشخيص المؤقت فقط: أضيفي APP_DEBUG=1 في Render لمعرفة السبب الحقيقي.
            if (getenv('APP_DEBUG') === '1') {
                $response['details'] = $e->getMessage();
            }

            jsonResponse($response, 500);
        }
        break;

    /* ---------- APPROVE ---------- */

    case 'approve':
        requireAdmin();

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
                    RETURNING *
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
                'message' => 'تم اعتماد الطلب من إدارة المدرسة',
                'data' => $request
            ]);
        } catch (Throwable $e) {
            error_log('APPROVE ERROR: ' . $e->getMessage());

            jsonResponse([
                'success' => false,
                'message' => 'تعذر اعتماد الطلب'
            ], 500);
        }
        break;

    /* ---------- REJECT ---------- */

    case 'reject':
        requireAdmin();

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
                    RETURNING *
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
                'message' => 'تم رفض الطلب',
                'data' => $request
            ]);
        } catch (Throwable $e) {
            error_log('REJECT ERROR: ' . $e->getMessage());

            jsonResponse([
                'success' => false,
                'message' => 'تعذر رفض الطلب'
            ], 500);
        }
        break;

    /* ---------- WHATSAPP OPENED ---------- */

    case 'whatsapp_opened':
        requireAdmin();

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
                        whatsapp_opened = TRUE,
                        whatsapp_opened_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                    RETURNING id, whatsapp_opened, whatsapp_opened_at
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
                'message' => 'تم تسجيل فتح واتساب',
                'data' => $request
            ]);
        } catch (Throwable $e) {
            error_log('WHATSAPP OPENED ERROR: ' . $e->getMessage());

            jsonResponse([
                'success' => false,
                'message' => 'تعذر تحديث حالة واتساب'
            ], 500);
        }
        break;

    /* ---------- DELETE ---------- */

    case 'delete':
        requireAdmin();

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
                    RETURNING id_card_file, appointment_letter_file
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
                'message' => 'تعذر حذف الطلب'
            ], 500);
        }
        break;

    default:
        jsonResponse([
            'success' => false,
            'message' => 'إجراء غير صالح'
        ], 400);
}
