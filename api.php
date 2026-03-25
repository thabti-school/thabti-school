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

try {
    $port = $config['db']['port'] ?? 3306;
  $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO(
        $dsn,
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} 

$action = $_GET['action'] ?? '';

function getJsonInput() {
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}

function ensureUploadsDir($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function uploadFile($fileKey, $prefix, $uploadDir) {
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

switch ($action) {
    case 'list':
        try {
            $stmt = $pdo->query("SELECT * FROM leave_requests ORDER BY id DESC");
            $rows = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $rows
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'تعذر جلب السجلات: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
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
                echo json_encode([
                    'success' => false,
                    'message' => 'يرجى تعبئة جميع الحقول المطلوبة'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $uploadDir = __DIR__ . '/uploads';
            ensureUploadsDir($uploadDir);

            $idCardPath = uploadFile('id_card_file', 'id', $uploadDir);
            $appointmentLetterPath = uploadFile('appointment_letter_file', 'appointment', $uploadDir);

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

            echo json_encode([
                'success' => true,
                'message' => 'تم حفظ الطلب بنجاح'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'تعذر حفظ الطلب: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'approve':
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'رقم الطلب غير صالح'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $checkStmt = $pdo->prepare("SELECT id FROM leave_requests WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            $exists = $checkStmt->fetch();

            if (!$exists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE leave_requests
                SET status = 'موافق عليه',
                    whatsapp_opened = 1,
                    whatsapp_opened_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);

            echo json_encode([
                'success' => true,
                'message' => 'تمت الموافقة على الطلب'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'تعذر تنفيذ الموافقة: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'reject':
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'رقم الطلب غير صالح'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $checkStmt = $pdo->prepare("SELECT id FROM leave_requests WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            $exists = $checkStmt->fetch();

            if (!$exists) {
                echo json_encode([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE leave_requests
                SET status = 'مرفوض',
                    whatsapp_opened = 1,
                    whatsapp_opened_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $id]);

            echo json_encode([
                'success' => true,
                'message' => 'تم رفض الطلب'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'تعذر تنفيذ الرفض: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'delete':
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'رقم الطلب غير صالح'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $selectStmt = $pdo->prepare("
                SELECT id_card_file, appointment_letter_file
                FROM leave_requests
                WHERE id = :id
            ");
            $selectStmt->execute([':id' => $id]);
            $record = $selectStmt->fetch();

            if (!$record) {
                echo json_encode([
                    'success' => false,
                    'message' => 'الطلب غير موجود'
                ], JSON_UNESCAPED_UNICODE);
                exit;
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

            echo json_encode([
                'success' => true,
                'message' => 'تم حذف الطلب'
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'تعذر حذف الطلب: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'إجراء غير صالح'
        ], JSON_UNESCAPED_UNICODE);
        break;
}
