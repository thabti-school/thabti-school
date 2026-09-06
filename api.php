<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
    $databaseUrl =
        $config['database_url']
        ?? getenv('DATABASE_URL')
        ?? '';

    if ($databaseUrl === '') {
        throw new Exception('DATABASE_URL غير موجود في إعدادات Render');
    }

    $parts = parse_url($databaseUrl);

    if ($parts === false) {
        throw new Exception('DATABASE_URL غير صالح');
    }

    $host = $parts['host'] ?? '';
    $port = $parts['port'] ?? 5432;
    $user = isset($parts['user']) ? urldecode($parts['user']) : '';
    $pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
    $name = ltrim($parts['path'] ?? '', '/');

    if ($host === '' || $user === '' || $name === '') {
        throw new Exception('بيانات PostgreSQL غير مكتملة');
    }

    $dsn =
        "pgsql:host={$host};" .
        "port={$port};" .
        "dbname={$name};" .
        "sslmode=require";

    return new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_PERSISTENT => false,

            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
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
        'temporary failure'
    ];

    foreach ($patterns as $pattern) {
        if (str_contains($message, $pattern)) {
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

            // التأكد من سلامة الاتصال
            $pdo->query('SELECT 1');

            return $callback($pdo);

        } catch (Throwable $e) {

            $lastException = $e;

            if (
                $attempt < 3 &&
                isRetryableDbError($e)
            ) {
                sleep($attempt);
                continue;
            }

            throw $e;
        }
    }

    throw $lastException ??
        new Exception('تعذر الاتصال بقاعدة البيانات');
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
}

function uploadFile(
    string $fileKey,
    string $prefix,
    string $uploadDir
): string {

    if (!isset($_FILES[$fileKey])) {
        return '';
    }

    $file = $_FILES[$fileKey];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('حدث خطأ أثناء رفع الملف');
    }

    // 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception(
            'حجم المرفق يجب ألا يتجاوز 5 ميجابايت'
        );
    }

    $tmp = $file['tmp_name'];

    if (!is_uploaded_file($tmp)) {
        throw new Exception('الملف المرفوع غير صالح');
    }

    $mime = mime_content_type($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf'
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception(
            'يسمح فقط بملفات JPG وPNG وPDF'
        );
    }

    $filename =
        $prefix . '_' .
        bin2hex(random_bytes(10)) .
        '.' . $allowed[$mime];

    $destination =
        rtrim($uploadDir, '/\\') .
        DIRECTORY_SEPARATOR .
        $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new Exception('تعذر حفظ المرفق');
    }

    return 'uploads/' . $filename;
}

/* =========================
   Actions
========================= */

$action = $_GET['action'] ?? '';

switch ($action) {

/* =========================
   LIST
========================= */

case 'list':

    try {

        $rows = withDbRetry(
            function (PDO $pdo) {

                $stmt = $pdo->query("
                    SELECT *
                    FROM leave_requests
                    ORDER BY id DESC
                ");

                return $stmt->fetchAll();
            },
            $config
        );

        jsonResponse([
            'success' => true,
            'data' => $rows
        ]);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        jsonResponse([
            'success' => false,
            'message' =>
                'تعذر جلب سجلات الاستئذان'
        ], 500);
    }

break;

/* =========================
   CREATE
========================= */

case 'create':

    try {

        $studentName =
            trim($_POST['student_name'] ?? '');

        $grade =
            trim($_POST['grade'] ?? '');

        $section =
            trim($_POST['section'] ?? '');

        $phone =
            trim($_POST['phone'] ?? '');

        $reason =
            trim($_POST['reason'] ?? '');

        $exitTime =
            trim($_POST['exit_time'] ?? '');

        $receiverName =
            trim($_POST['receiver_name'] ?? '');

        $relationship =
            trim($_POST['relationship'] ?? '');

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
                'message' =>
                    'يرجى تعبئة جميع الحقول المطلوبة'
            ], 400);
        }

        /*
         * أرقام عُمان:
         * نسمح بالأرقام والمسافات و+
         */
        if (!preg_match('/^[0-9+\s-]{8,20}$/', $phone)) {
            jsonResponse([
                'success' => false,
                'message' => 'رقم الهاتف غير صالح'
            ], 400);
        }

        $uploadDir = __DIR__ . '/uploads';

        ensureUploadsDir($uploadDir);

        $idCardPath = uploadFile(
            'id_card_file',
            'id',
            $uploadDir
        );

        $appointmentPath = uploadFile(
            'appointment_letter_file',
            'appointment',
            $uploadDir
        );

        $newId = withDbRetry(
            function (PDO $pdo) use (
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
                    ':id_card' => $idCardPath ?: null,
                    ':appointment' =>
                        $appointmentPath ?: null
                ]);

                return $stmt->fetchColumn();
            },
            $config
        );

        jsonResponse([
            'success' => true,
            'message' => 'تم إرسال طلب الاستئذان بنجاح',
            'id' => (int)$newId
        ]);

   } catch (Throwable $e) {

    $errorMessage = $e->getMessage();

    error_log(
        'CREATE REQUEST ERROR: ' .
        $errorMessage
    );

    jsonResponse([
        'success' => false,
        'message' =>
            'خطأ قاعدة البيانات: ' .
            $errorMessage
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

        $request = withDbRetry(
            function (PDO $pdo) use ($id) {

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

                $stmt->execute([
                    ':id' => $id
                ]);

                return $stmt->fetch();
            },
            $config
        );

        if (!$request) {
            jsonResponse([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        /*
         * هنا سنربط SMS لاحقاً.
         * لا نضع sms_sent = TRUE
         * إلا بعد تأكيد مزود SMS نجاح الإرسال.
         */

        jsonResponse([
            'success' => true,
            'message' =>
                'تم اعتماد الطلب من مديرة المدرسة',
            'data' => $request
        ]);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        jsonResponse([
            'success' => false,
            'message' =>
                'تعذر اعتماد الطلب'
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

        $request = withDbRetry(
            function (PDO $pdo) use ($id) {

                $stmt = $pdo->prepare("
                    UPDATE leave_requests
                    SET
                        status = 'مرفوض',
                        rejected_at = CURRENT_TIMESTAMP,
                        approved_at = NULL
                    WHERE id = :id
                    RETURNING id
                ");

                $stmt->execute([
                    ':id' => $id
                ]);

                return $stmt->fetch();
            },
            $config
        );

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

        error_log($e->getMessage());

        jsonResponse([
            'success' => false,
            'message' =>
                'تعذر رفض الطلب'
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

        $record = withDbRetry(
            function (PDO $pdo) use ($id) {

                $stmt = $pdo->prepare("
                    DELETE FROM leave_requests
                    WHERE id = :id
                    RETURNING
                        id_card_file,
                        appointment_letter_file
                ");

                $stmt->execute([
                    ':id' => $id
                ]);

                return $stmt->fetch();
            },
            $config
        );

        if (!$record) {
            jsonResponse([
                'success' => false,
                'message' => 'الطلب غير موجود'
            ], 404);
        }

        foreach (
            [
                $record['id_card_file'] ?? '',
                $record['appointment_letter_file'] ?? ''
            ] as $relativePath
        ) {

            if (!$relativePath) {
                continue;
            }

            $file =
                __DIR__ . '/' .
                ltrim($relativePath, '/');

            if (is_file($file)) {
                @unlink($file);
            }
        }

        jsonResponse([
            'success' => true,
            'message' => 'تم حذف الطلب بنجاح'
        ]);

    } catch (Throwable $e) {

        error_log($e->getMessage());

        jsonResponse([
            'success' => false,
            'message' =>
                'تعذر حذف الطلب'
        ], 500);
    }

break;

default:

    jsonResponse([
        'success' => false,
        'message' => 'إجراء غير صالح'
    ], 400);
}
