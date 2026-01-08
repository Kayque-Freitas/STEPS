<?php
/**
 * Sistema de Tutoriais e POP's - Página de Visualização
 * Versão: 2.0 (Revisada)
 * 
 * Página para assistir vídeos com QR code e compartilhamento
 */

require_once 'config.php';

$videoId = intval($_GET['id'] ?? 0);

if ($videoId <= 0) {
    http_response_code(404);
    die('Vídeo não encontrado');
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Obter informações do vídeo
    $stmt = $pdo->prepare('
        SELECT v.*, c.name as category_name
        FROM videos v
        JOIN categories c ON v.category_id = c.id
        WHERE v.id = ?
    ');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        http_response_code(404);
        die('Vídeo não encontrado');
    }

    // Obter vídeos relacionados
    $relatedStmt = $pdo->prepare('
        SELECT id, title, thumbnail
        FROM videos
        WHERE category_id = ? AND id != ?
        ORDER BY created_at DESC
        LIMIT 6
    ');
    $relatedStmt->execute([$video['category_id'], $videoId]);
    $relatedVideos = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Gerar token CSRF para usuários autenticados
    $csrf_token = is_logged_in() ? generate_csrf_token() : '';
} catch (Exception $e) {
    http_response_code(500);
    die('Erro ao carregar vídeo');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($video['title']); ?> - Sistema de Tutoriais e POP's</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .video-container {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .video-container video {
            width: 100%;
            height: auto;
            display: block;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .video-info {
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .video-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .video-meta {
            color: #999;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .video-meta span {
            margin-right: 1.5rem;
        }

        .qr-section {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .qr-section h5 {
            margin-bottom: 1rem;
            color: #333;
        }

        .qr-code-img {
            max-width: 200px;
            margin: 1rem auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 8px;
        }

        .related-video {
            cursor: pointer;
            transition: transform 0.3s;
        }

        .related-video:hover {
            transform: translateY(-5px);
        }

        .related-video img {
            border-radius: 8px;
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .share-buttons {
            margin-top: 1rem;
        }

        .share-buttons button {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        /* ===== RESPONSIVO PARA CELULARES ===== */
        @media (max-width: 768px) {
            /* Navbar */
            .navbar {
                padding: 0.75rem 0;
            }
            .navbar-brand {
                font-size: 1rem;
            }

            /* Container */
            .container {
                padding: 0 0.5rem;
                max-width: 100%;
            }

            /* Vídeo */
            .video-container {
                margin-bottom: 1.5rem;
                border-radius: 6px;
            }

            /* Info */
            .video-info {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }

            .video-title {
                font-size: 1.3rem;
                margin-bottom: 0.75rem;
            }

            .video-meta {
                font-size: 0.85rem;
                margin-bottom: 1rem;
            }

            .video-meta span {
                display: block;
                margin-right: 0;
                margin-bottom: 0.5rem;
            }

            /* Botões Share */
            .share-buttons {
                margin-top: 1rem;
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .share-buttons button {
                flex: 1;
                min-width: 100px;
                margin: 0;
                font-size: 0.85rem;
                padding: 0.6rem 0.5rem;
            }

            /* QR Section */
            .qr-section {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .qr-section h5 {
                font-size: 1rem;
                margin-bottom: 0.75rem;
            }

            .qr-code-img {
                max-width: 150px;
                margin: 0.75rem auto;
            }

            .qr-section .btn {
                width: 100%;
            }

            /* Cards */
            .card {
                border-radius: 6px;
                margin-bottom: 1.5rem;
            }

            .card-header {
                padding: 0.75rem;
                font-size: 0.95rem;
            }

            .card-body {
                padding: 0.75rem;
            }

            /* Related Videos */
            .related-video img {
                height: 120px;
            }

            .related-video {
                margin-bottom: 0.75rem;
            }

            /* Badge */
            .badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }

        /* Celulares Muito Pequenos (< 480px) */
        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 0.85rem;
            }

            .navbar {
                padding: 0.5rem 0;
            }

            .video-title {
                font-size: 1.1rem;
            }

            .video-meta {
                font-size: 0.75rem;
            }

            .video-info {
                padding: 0.75rem;
            }

            .share-buttons {
                flex-direction: column;
            }

            .share-buttons button {
                width: 100%;
                flex: none;
                margin-bottom: 0.5rem;
            }

            .qr-section {
                padding: 1rem;
            }

            .qr-section h5 {
                font-size: 0.9rem;
            }

            .qr-code-img {
                max-width: 120px;
            }

            .card-header {
                font-size: 0.85rem;
            }

            .related-video img {
                height: 100px;
            }

            .nav-link {
                padding: 0.75rem 0.5rem !important;
                font-size: 0.85rem;
            }
        }

        /* Melhorias de Toque */
        @media (hover: none) {
            .btn, .card, .related-video {
                -webkit-tap-highlight-color: rgba(102, 126, 234, 0.1);
            }

            .btn:active {
                transform: scale(0.98);
            }

            .related-video:active {
                transform: scale(0.98);
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo is_logged_in() ? 'index.php' : '#'; ?>">
                <i class="bi bi-play-circle"></i> Sistema de Tutoriais e POP's
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <?php if (is_logged_in()): ?>
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Sair
                        </a>
                    <?php else: ?>
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-4">
        <div class="row g-3">
            <!-- Vídeo Principal -->
            <div class="col-lg-8 col-12">
                <!-- Reprodutor de Vídeo -->
                <div class="video-container">
                    <video controls preload="metadata" style="width: 100%; height: auto;" <?php if (!empty($video['thumbnail'])): ?>poster="<?php echo htmlspecialchars('thumbs/' . $video['thumbnail']); ?>"<?php endif; ?>>
                        <source src="<?php echo htmlspecialchars('uploads/' . $video['category_id'] . '/' . $video['filename']); ?>" type="video/mp4">
                        Seu navegador não suporta reprodução de vídeo.
                    </video>
                </div>

                <!-- Informações do Vídeo -->
                <div class="video-info">
                    <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
                    <div class="video-meta">
                        <span>
                            <i class="bi bi-folder"></i>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($video['category_name']); ?></span>
                        </span>
                        <span>
                            <i class="bi bi-calendar"></i>
                            <?php echo date('d/m/Y', strtotime($video['created_at'])); ?>
                        </span>
                    </div>

                    <!-- Botões de Compartilhamento -->
                    <div class="share-buttons d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="copyToClipboard()">
                            <i class="bi bi-link-45deg"></i> Copiar Link
                        </button>
                        <button class="btn btn-outline-primary" onclick="shareWhatsApp()">
                            <i class="bi bi-whatsapp"></i> Compartilhar no WhatsApp
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar / Conteúdo Inferior em Mobile -->
            <div class="col-lg-4 col-12">
                <!-- QR Code -->
                <div class="qr-section">
                    <h5><i class="bi bi-qr-code"></i> Compartilhar via QR Code</h5>
                    <div style="text-align: center;">
                        <img src="api/generate_qr.php?video_id=<?php echo $videoId; ?>&size=200" alt="QR Code" class="qr-code-img">
                    </div>
                    <a href="api/generate_qr.php?video_id=<?php echo $videoId; ?>&size=300" download="qr_<?php echo $videoId; ?>.png" class="btn btn-primary w-100" style="margin-top: 0.5rem;">
                        <i class="bi bi-download"></i> Baixar QR Code
                    </a>
                </div>

                <!-- Vídeos Relacionados -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-film"></i> Vídeos Relacionados
                    </div>
                    <div class="card-body p-0">
                        <div class="d-grid gap-2">
                            <?php foreach ($relatedVideos as $related): ?>
                                <div class="related-video p-3" onclick="window.location.href='watch.php?id=<?php echo $related['id']; ?>' " style="cursor: pointer;">
                                    <div style="position: relative; overflow: hidden; border-radius: 6px; margin-bottom: 0.5rem;">
                                        <?php if (!empty($related['thumbnail'])): ?>
                                            <img src="<?php echo htmlspecialchars('thumbs/' . $related['thumbnail']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>" style="width: 100%; height: 120px; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 120px; background: #ddd; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                                                <i class="bi bi-film" style="font-size: 1.8rem; color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s;">
                                            <i class="bi bi-play-circle" style="font-size: 1.5rem; color: white;"></i>
                                        </div>
                                        </div>
                                        <div>
                                            <strong style="font-size: 0.9rem; display: block;">
                                                <?php echo htmlspecialchars(substr($related['title'], 0, 60)); ?>
                                            </strong>
                                        </div>
                                    </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('✓ Link copiado com sucesso!');
            }).catch(() => {
                alert('Copie este link: ' + url);
            });
        }

        function shareWhatsApp() {
            const url = window.location.href;
            const text = 'Assista este vídeo: <?php echo htmlspecialchars($video['title']); ?>';
            window.open(`https://wa.me/?text=${encodeURIComponent(text + '\n' + url)}`, '_blank');
        }

        // Melhorias para Mobile
        document.addEventListener('DOMContentLoaded', function() {
            // Previne comportamento de zoom ao tocar em inputs
            const inputs = document.querySelectorAll('input, button, a');
            inputs.forEach(input => {
                input.style.touchAction = 'manipulation';
            });

            // Feedback visual ao tocar em elementos
            const clickableElements = document.querySelectorAll('.btn, .related-video, button');
            clickableElements.forEach(el => {
                el.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                el.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        });
    </script>
</body>
</html>
