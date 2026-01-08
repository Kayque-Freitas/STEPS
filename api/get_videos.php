<?php
/**
 * Sistema de Tutoriais e POP's - API: Obter Vídeos
 * Versão: 2.0 (Revisada)
 * 
 * Endpoint para obter vídeos de uma categoria
 */

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// Validar autenticação
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

try {
    $categoryId = intval($_GET['category_id'] ?? 0);

    if ($categoryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de categoria inválido']);
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

    // Obter vídeos
    $stmt = $pdo->prepare('
        SELECT id, title, filename, thumbnail, duration, file_size
        FROM videos
        WHERE category_id = ?
        ORDER BY created_at DESC
    ');
    $stmt->execute([$categoryId]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'videos' => $videos]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar requisição']);
    error_log('Erro em get_videos.php: ' . $e->getMessage());
}
?>
