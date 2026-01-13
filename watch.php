<?php
/**
 * Sistema de Tutoriais e POP's - Página de Visualização
 * Versão: 3.0 (Neobrutalism Edition)
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

    $stmt = $pdo->prepare('SELECT v.*, c.name as category_name FROM videos v JOIN categories c ON v.category_id = c.id WHERE v.id = ?');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        http_response_code(404);
        die('Vídeo não encontrado');
    }

    $relatedStmt = $pdo->prepare('SELECT id, title, thumbnail FROM videos WHERE category_id = ? AND id != ? ORDER BY created_at DESC LIMIT 6');
    $relatedStmt->execute([$video['category_id'], $videoId]);
    $relatedVideos = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title><?php echo htmlspecialchars($video['title']); ?> - STEPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'main': '#A3E635',
                        'secondary': '#818CF8',
                        'accent': '#F472B6',
                        'brutal-yellow': '#FDE047',
                        'brutal-blue': '#38BDF8',
                    },
                    boxShadow: {
                        'brutal': '4px 4px 0px 0px #000',
                        'brutal-lg': '8px 8px 0px 0px #000',
                    },
                    borderWidth: {
                        '3': '3px',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-color: #F1F5F9;
            background-image: radial-gradient(#000 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .brutal-card {
            background: white;
            border: 3px solid black;
            box-shadow: 6px 6px 0px 0px #000;
        }
        .brutal-btn {
            border: 3px solid black;
            box-shadow: 3px 3px 0px 0px #000;
            transition: all 0.1s;
        }
        .brutal-btn:active {
            transform: translate(2px, 2px);
            box-shadow: 1px 1px 0px 0px #000;
        }
    </style>
</head>
<body class="min-h-screen pb-12">
    <!-- Navbar -->
    <nav class="bg-white border-b-3 border-black p-4 sticky top-0 z-50 mb-8">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-2xl font-black uppercase tracking-tighter flex items-center">
                <i class="bi bi-play-circle mr-2 text-main"></i> STEPS
            </a>
            <?php if (is_logged_in()): ?>
                <a href="index.php" class="brutal-btn bg-main px-4 py-2 font-black uppercase text-sm">
                    Dashboard
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Video Player Area -->
            <div class="lg:col-span-2 space-y-6">
                <div class="brutal-card bg-black overflow-hidden aspect-video">
                    <video controls class="w-full h-full" poster="thumbs/<?php echo $video['thumbnail']; ?>">
                        <source src="uploads/<?php echo $video['category_id'] . '/' . $video['filename']; ?>" type="video/mp4">
                        Seu navegador não suporta vídeos.
                    </video>
                </div>

                <div class="brutal-card p-6 bg-white">
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="bg-main border-2 border-black px-3 py-1 font-black uppercase text-xs">
                            <?php echo htmlspecialchars($video['category_name']); ?>
                        </span>
                        <span class="font-bold text-gray-500 text-sm">
                            <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($video['created_at'])); ?>
                        </span>
                    </div>
                    <h1 class="text-3xl font-black uppercase tracking-tighter mb-6 italic"><?php echo htmlspecialchars($video['title']); ?></h1>
                    
                    <div class="flex flex-wrap gap-3">
                        <button onclick="copyLink()" class="brutal-btn bg-brutal-blue px-4 py-2 font-black uppercase text-sm flex items-center">
                            <i class="bi bi-link-45deg mr-2"></i> Copiar Link
                        </button>
                        <a href="https://wa.me/?text=<?php echo urlencode('Assista este tutorial: ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="brutal-btn bg-green-400 px-4 py-2 font-black uppercase text-sm flex items-center">
                            <i class="bi bi-whatsapp mr-2"></i> WhatsApp
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="space-y-6">
                <!-- QR Code Section -->
                <div class="brutal-card p-6 bg-brutal-yellow text-center">
                    <h3 class="font-black uppercase mb-4 italic">Acesso Rápido</h3>
                    <div class="bg-white border-3 border-black p-4 inline-block mb-4 shadow-brutal">
                        <img src="api/generate_qr.php?id=<?php echo $video['id']; ?>" alt="QR Code" class="w-40 h-40">
                    </div>
                    <p class="text-xs font-bold uppercase">Escaneie para abrir no celular</p>
                    <a href="api/generate_qr.php?id=<?php echo $video['id']; ?>&download=1" class="brutal-btn bg-white w-full mt-4 py-2 font-black uppercase text-xs inline-block">
                        Baixar QR Code
                    </a>
                </div>

                <!-- Related Videos -->
                <div class="brutal-card overflow-hidden">
                    <div class="bg-black text-white p-4 font-black uppercase italic">Relacionados</div>
                    <div class="p-4 space-y-4">
                        <?php if (empty($relatedVideos)): ?>
                            <p class="text-sm font-bold text-gray-500 italic">Nenhum vídeo relacionado.</p>
                        <?php else: ?>
                            <?php foreach ($relatedVideos as $rv): ?>
                                <a href="watch.php?id=<?php echo $rv['id']; ?>" class="flex gap-3 group">
                                    <div class="w-24 h-16 flex-shrink-0 brutal-card overflow-hidden shadow-none group-hover:shadow-brutal transition-all">
                                        <?php if ($rv['thumbnail']): ?>
                                            <img src="thumbs/<?php echo $rv['thumbnail']; ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                                <i class="bi bi-film"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow">
                                        <h4 class="font-black uppercase text-[10px] leading-tight line-clamp-2 group-hover:text-secondary"><?php echo htmlspecialchars($rv['title']); ?></h4>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copiado para a área de transferência!');
            });
        }
    </script>
</body>
</html>
