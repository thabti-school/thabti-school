<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

if (!is_array($config) || !isset($config['db'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ملف config.php لم يتم تحميله بشكل صحيح'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

function ensureUploadsDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function uploadFile(string $fileKey, string $prefix, string $uploadDir): string
{
    if (!isset($_FILES[$fileKey])) {
        return '';
    }

    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $originalName = $_FILES[$fileKey]['name'] ?? '';
    $tmpName = $_FILES[$fileKey]['tmp_name'] ?? '';

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return '';
    }

    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $fileName = $prefix . '_' . time() . '_' . mt_rand(1000, 9999);

    if ($safeExt !== '') {
        $fileName .= '.' . $safeExt;
    }

    $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;

    if (move_uploaded_file($tmpName, $targetPath)) {
        return 'uploads/' . $fileName;
    }

    return '';
}

function createPdo(array $config): PDO
{
    $host = trim((string)($config['db']['host'] ?? ''));
    $port = (string)($config['db']['port'] ?? '3306');
    $name = trim((string)($config['db']['name'] ?? ''));
    $user = trim((string)($config['db']['user'] ?? ''));
    $pass = (string)($config['db']['pass'] ?? '');
    $charset = trim((string)($config['db']['charset'] ?? 'utf8mb4'));

    if ($host === '' || $name === '' || $user === '') {
        throw new Exception('بيانات الاتصال بقاعدة البيانات غير مكتملة');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    return new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
        ]
    );
}

function isRetryableDbError(Throwable $e): bool
{
    $message = $e->getMessage();

    $patterns = [
        'server has gone away',
        'lost connection',
        'error while sending',
        'connection refused',
        'no such file or directory',
        'temporary failure',
        'resource temporarily unavailable',
        'connection timed out',
    ];

    foreach ($patterns as $pattern) {
        if (stripos($message, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

function withDbRetry(callable $callback, array $config)
{
    $attempts = 2;
    $lastException = null;

    for ($i = 1; $i <= $attempts; $i++) {
        try {
            $pdo = createPdo($config);
            return $callback($pdo);
        } catch (Throwable $e) {
            $lastException = $e;

            if ($i < $attempts && isRetryableDbError($e)) {
                usleep(700000);
                continue;
            }

            throw $e;
        }
    }

    throw $lastException ?? new Exception('حدث خطأ غير متوقع أثناء الاتصال بقاعدة البيانات');
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        try {
            $rows = withDbRetry(function (PDO $pdo) {
                $stmt = $pdo->query("SELECT * FROM leave_requests ORDER BY id DESC");
                return $stmt->fetchAll();
            }, $config);

            jsonResponse([
                'success' => true,
                'data' => $rows
            ]);
        } catch (Throwable $e) {
            jsonResponse([
                'success' => false,
                'message' => 'تعذر جلب السجلات: ' . $e->getMessage()
            ], 500);
        }
        break;

    case 'create':
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

            $uploadDir = __DIR__ . '/uploads';
            ensureUploadsDir($uploadDir);

            $idCardPath = uploadFile('id_card_file', 'id', $uploadDir);
            $appointmentLetterPath = uploadFile('appointment_letter_file', 'appointment', $uploadDir);

            withDbRetry(function (PDO $pdo) use (
                $studentName,
                $grade,
                $section,
                $phone,
                $reason,
                $exitTime,
                $receiverName,
                $relationship,
                $idCardPath,
                $appointmentLetterPath
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
                        whatsapp_opened,
                        whatsapp_opened_at,
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
                        :status,
                        :id_card_file,
                        :appointment_letter_file,
                        :whatsapp_opened,
                        :whatsapp_opened_at,
                        NOW()
                    )
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
                    ':status' => 'معلق',
                    ':id_card_file' => $idCardPath,
                    ':appointment_letter_file' => $appointmentLetterPath,
                    ':whatsapp_opened' => 0,
                    ':whatsapp_opened_at' => null
                ]);

                return true;
            }, $config);

            jsonResponse([
                'success' => true,
                'message' => 'تم حفظ الطلب بنجاح'
            ]);
        } catch (Throwable $e) {
            jsonResponse([
                'success' => false,
                'message' => 'تعذر حفظ الطلب: ' . $e->getMessage()
            ], 500);
        }
        break;

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
            withDbRetry(function (PDO $pdo) use ($id) {
                $checkStmt = $pdo->prepare("SELECT id FROM leave_requests WHERE id = :id");
                $checkStmt->execute([':id' => $id]);
                $exists = $checkStmt->fetch();

                if (!$exists) {
                    jsonResponse([
                        'success' => false,
                        'message' => 'الطلب غير موجود'
                    ], 404);
                }

                $stmt = $pdo->prepare("
                    UPDATE leave_requests
                    SET status = 'موافق عليه',
                        whatsapp_opened = 1,
                        whatsapp_opened_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $id]);

                return true;
            }, $config);

            jsonResponse([
                'success' => true,
                'message' => 'تمت الموافقة على الطلب'
            ]);
        } catch (Throwable $e) {
            jsonResponse([
                'success' => false,
                'message' => 'تعذر تنفيذ الموافقة: ' . $e->getMessage()
            ], 500);
        }
        break;

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
            withDbRetry(function (PDO $pdo) use ($id) {
                $checkStmt = $pdo->prepare("SELECT id FROM leave_requests WHERE id = :id");
                $checkStmt->execute([':id' => $id]);
                $exists = $checkStmt->fetch();

                if (!$exists) {
                    jsonResponse([
                        'success' => false,
                        'message' => 'الطلب غير موجود'
                    ], 404);
                }

                $stmt = $pdo->prepare("
                    UPDATE leave_requests
                    SET status = 'مرفوض',
                        whatsapp_opened = 1,
                        whatsapp_opened_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $id]);

                return true;
            }, $config);

            jsonResponse([
                'success' => true,
                'message' => 'تم رفض الطلب'
            ]);
        } catch (Throwable $e) {
            jsonResponse([
                'success' => false,
                'message' => 'تعذر تنفيذ الرفض: ' . $e->getMessage()
            ], 500);
        }
        break;

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
            withDbRetry(function (PDO $pdo) use ($id) {
                $selectStmt = $pdo->prepare("
                    SELECT id_card_file, appointment_letter_file
                    FROM leave_requests
                    WHERE id = :id
                ");
                $selectStmt->execute([':id' => $id]);
                $record = $selectStmt->fetch();

                if (!$record) {
                    jsonResponse([
                        'success' => false,
                        'message' => 'الطلب غير موجود'
                    ], 404);
                }

                $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE id = :id");
                $stmt->execute([':id' => $id]);

                if (!empty($record['id_card_file'])) {
                    $filePath = __DIR__ . '/' . $record['id_card_file'];
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }

                if (!empty($record['appointment_letter_file'])) {
                    $filePath = __DIR__ . '/' . $record['appointment_letter_file'];
                    if (is_file($filePath)) {
                        @unlink($filePath);
                    }
                }

                return true;
            }, $config);

            jsonResponse([
                'success' => true,
                'message' => 'تم حذف الطلب'
            ]);
        } catch (Throwable $e) {
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
