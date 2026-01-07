<?php
/**
 * Sistema de Tutoriais e POP's - API: Gerar QR Code
 * Versão: 2.0 (Revisada)
 * 
 * Endpoint para gerar QR codes localmente usando a biblioteca QR Code
 */

require_once '../config.php';

// Validar autenticação
if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

try {
    $videoId = intval($_GET['video_id'] ?? 0);
    $size = intval($_GET['size'] ?? 200);

    // Limitar tamanho
    $size = min(max($size, 100), 500);

    if ($videoId <= 0) {
        http_response_code(400);
        exit;
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Obter vídeo
    $stmt = $pdo->prepare('SELECT id, filename FROM videos WHERE id = ?');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        http_response_code(404);
        exit;
    }

    // Construir URL do vídeo
    $url = BASE_URL . 'watch.php?id=' . $videoId;

    // Gerar QR code usando a biblioteca phpqrcode
    require_once '../lib/qrcode.php';
    
    $qrFile = QR_DIR . 'qr_' . $videoId . '_' . $size . '.png';
    
    // Gerar QR code
    QRcode::png($url, $qrFile, QR_ECLEVEL_L, $size / 10, 2);

    // Enviar arquivo
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="qr_' . $videoId . '.png"');
    header('Cache-Control: public, max-age=86400');
    
    readfile($qrFile);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em generate_qr.php: ' . $e->getMessage());
}
?>
