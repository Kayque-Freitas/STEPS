<?php
/**
 * Sistema de Tutoriais e POP's - API: Upload de Vídeo
 * Versão: 2.0 (Revisada)
 * 
 * Endpoint para fazer upload de vídeos
 */

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// Validar autenticação
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

try {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Token de segurança inválido']);
        exit;
    }

    $categoryId = intval($_POST['category_id'] ?? 0);
    $title = sanitize($_POST['title'] ?? '');

    if ($categoryId <= 0 || empty($title)) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos']);
        exit;
    }

    if (!isset($_FILES['video'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhum vídeo enviado']);
        exit;
    }

    // Validar arquivo de vídeo
    $validation = validate_upload($_FILES['video'], ALLOWED_VIDEO_TYPES);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $validation['message']]);
        exit;
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Verificar se categoria existe
    $catStmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $catStmt->execute([$categoryId]);
    if (!$catStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Categoria não encontrada']);
        exit;
    }

    // Gerar nome único para o arquivo
    $fileExt = pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('video_') . '.' . $fileExt;
    $filepath = UPLOAD_DIR . $categoryId . '/' . $filename;

    // Criar diretório da categoria se não existir
    $categoryDir = UPLOAD_DIR . $categoryId;
    if (!is_dir($categoryDir)) {
        mkdir($categoryDir, 0755, true);
    }

    // Mover arquivo
    if (!move_uploaded_file($_FILES['video']['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar vídeo']);
        exit;
    }

    // Processar thumbnail se enviado
    $thumbnail = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $thumbValidation = validate_upload($_FILES['thumbnail'], ALLOWED_IMAGE_TYPES);
        if ($thumbValidation['valid']) {
            $thumbExt = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $thumbName = uniqid('thumb_') . '.' . $thumbExt;
            $thumbPath = THUMB_DIR . $thumbName;
            
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbPath)) {
                $thumbnail = $thumbName;
            }
        }
    }

    // Obter duração do vídeo (se ffprobe disponível)
    $duration = null;
    if (function_exists('shell_exec')) {
        $output = @shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1:noprint_wrappers=1 " . escapeshellarg($filepath) . " 2>/dev/null");
        if ($output !== null) {
            $duration = intval(floatval($output));
        }
    }

    // Salvar informações no banco de dados
    $fileSize = filesize($filepath);
    $stmt = $pdo->prepare('
        INSERT INTO videos (category_id, title, filename, thumbnail, duration, file_size)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$categoryId, $title, $filename, $thumbnail, $duration, $fileSize]);
    $videoId = $pdo->lastInsertId();

    // Registrar na auditoria
    log_audit($_SESSION['user_id'], 'UPLOAD_VIDEO', "Vídeo '$title' enviado para categoria $categoryId");

    echo json_encode([
        'success' => true,
        'message' => 'Vídeo enviado com sucesso',
        'video_id' => $videoId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar requisição']);
    error_log('Erro em upload_video.php: ' . $e->getMessage());
}
?>
