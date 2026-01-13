<?php
/**
 * Sistema de Tutoriais e POP's - Dashboard Principal
 * Versão: 3.0 (Neobrutalism Edition)
 */

require_once 'config.php';
require_login();

$message = '';
$messageType = 'info';
$db = Database::getInstance();
$pdo = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $message = 'Token de segurança inválido.';
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        $action = sanitize($_POST['action']);

        if ($action === 'add_category') {
            $categoryName = sanitize($_POST['category_name'] ?? '');
            $categoryDesc = sanitize($_POST['category_desc'] ?? '');
            $normalizedName = normalize_category_name($categoryName);

            if (empty($normalizedName)) {
                $message = 'Nome da categoria é obrigatório.';
                $messageType = 'danger';
            } else {
                try {
                    $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
                    $stmt->execute([$normalizedName, $categoryDesc]);
                    $message = "Categoria '$normalizedName' criada com sucesso!";
                    $messageType = 'success';
                    log_audit($_SESSION['user_id'], 'CREATE_CATEGORY', "Categoria '$normalizedName' criada");
                } catch (PDOException $e) {
                    $message = (strpos($e->getMessage(), 'Duplicate') !== false) ? 'Essa categoria já existe.' : 'Erro ao criar categoria.';
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'rename_category') {
            $categoryId = intval($_POST['category_id'] ?? 0);
            $newName = sanitize($_POST['new_name'] ?? '');
            $normalizedNewName = normalize_category_name($newName);

            if ($categoryId > 0 && !empty($normalizedNewName)) {
                try {
                    $stmt = $pdo->prepare('UPDATE categories SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                    $stmt->execute([$normalizedNewName, $categoryId]);
                    $message = "Categoria renomeada com sucesso!";
                    $messageType = 'success';
                    log_audit($_SESSION['user_id'], 'RENAME_CATEGORY', "Categoria $categoryId renomeada para '$normalizedNewName'");
                } catch (PDOException $e) {
                    $message = 'Erro ao renomear categoria.';
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'delete_category') {
            $categoryId = intval($_POST['category_id'] ?? 0);
            if ($categoryId > 0) {
                try {
                    $checkStmt = $pdo->prepare('SELECT COUNT(*) as count FROM videos WHERE category_id = ?');
                    $checkStmt->execute([$categoryId]);
                    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($result['count'] > 0) {
                        $message = 'Não é possível deletar uma categoria com vídeos.';
                        $messageType = 'warning';
                    } else {
                        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                        $stmt->execute([$categoryId]);
                        $message = "Categoria deletada com sucesso!";
                        $messageType = 'success';
                        log_audit($_SESSION['user_id'], 'DELETE_CATEGORY', "Categoria $categoryId deletada");
                    }
                } catch (Exception $e) {
                    $message = 'Erro ao deletar categoria.';
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'delete_video') {
            $videoId = intval($_POST['video_id'] ?? 0);
            if ($videoId > 0) {
                try {
                    $stmt = $pdo->prepare('SELECT category_id, filename, thumbnail FROM videos WHERE id = ?');
                    $stmt->execute([$videoId]);
                    $video = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($video) {
                        $videoPath = UPLOAD_DIR . $video['category_id'] . '/' . $video['filename'];
                        if (file_exists($videoPath)) unlink($videoPath);
                        if (!empty($video['thumbnail'])) {
                            $thumbPath = THUMB_DIR . $video['thumbnail'];
                            if (file_exists($thumbPath)) unlink($thumbPath);
                        }
                        $delStmt = $pdo->prepare('DELETE FROM videos WHERE id = ?');
                        $delStmt->execute([$videoId]);
                        $message = "Vídeo deletado com sucesso!";
                        $messageType = 'success';
                        log_audit($_SESSION['user_id'], 'DELETE_VIDEO', "Vídeo $videoId deletado");
                    }
                } catch (Exception $e) {
                    $message = 'Erro ao deletar vídeo.';
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'clear_audit_logs') {
            try {
                $pdo->exec('DELETE FROM audit_logs');
                $message = "Logs de auditoria limpos!";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Erro ao limpar logs.';
                $messageType = 'danger';
            }
        }
    }
}

$categoriesStmt = $pdo->query('SELECT * FROM categories ORDER BY name ASC');
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$videosStmt = $pdo->query('SELECT v.*, c.name as category_name FROM videos v JOIN categories c ON v.category_id = c.id ORDER BY v.created_at DESC LIMIT 10');
$recentVideos = $videosStmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - STEPS</title>
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
        .brutal-input {
            border: 3px solid black;
            box-shadow: 3px 3px 0px 0px #000;
        }
        .nav-link-brutal.active {
            background-color: #A3E635;
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0px 0px #000;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navbar -->
    <nav class="bg-white border-b-3 border-black p-4 sticky top-0 z-50">
        <div class="container mx-auto flex justify-between items-center">
            <a href="#" class="text-2xl font-black uppercase tracking-tighter flex items-center">
                <i class="bi bi-play-circle mr-2 text-main"></i> STEPS
            </a>
            <div class="flex items-center space-x-4">
                <span class="font-bold hidden md:inline">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="logout.php" class="brutal-btn bg-red-400 px-4 py-2 font-black uppercase text-sm">
                    Sair <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-4 md:p-8">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Sidebar -->
            <div class="w-full md:w-64 flex-shrink-0">
                <div class="flex flex-row md:flex-column overflow-x-auto md:overflow-x-visible space-x-2 md:space-x-0 md:space-y-4 pb-4 md:pb-0">
                    <button onclick="showTab('dashboard')" id="tab-btn-dashboard" class="nav-link-brutal active w-full text-left p-4 border-3 border-black font-black uppercase tracking-tight flex items-center">
                        <i class="bi bi-speedometer2 mr-3"></i> Dashboard
                    </button>
                    <button onclick="showTab('categorias')" id="tab-btn-categorias" class="nav-link-brutal w-full text-left p-4 border-3 border-black font-black uppercase tracking-tight flex items-center">
                        <i class="bi bi-folder mr-3"></i> Categorias
                    </button>
                    <button onclick="showTab('videos')" id="tab-btn-videos" class="nav-link-brutal w-full text-left p-4 border-3 border-black font-black uppercase tracking-tight flex items-center">
                        <i class="bi bi-film mr-3"></i> Vídeos
                    </button>
                    <button onclick="showTab('auditoria')" id="tab-btn-auditoria" class="nav-link-brutal w-full text-left p-4 border-3 border-black font-black uppercase tracking-tight flex items-center">
                        <i class="bi bi-clock-history mr-3"></i> Auditoria
                    </button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex-grow">
                <?php if (!empty($message)): ?>
                    <div class="bg-<?php echo $messageType === 'danger' ? 'red' : ($messageType === 'success' ? 'main' : 'brutal-blue'); ?> border-3 border-black p-4 mb-8 shadow-brutal font-black uppercase">
                        <i class="bi bi-info-circle mr-2"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Tab -->
                <div id="tab-dashboard" class="tab-content active space-y-8">
                    <h2 class="text-4xl font-black uppercase tracking-tighter italic">Dashboard</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="brutal-card p-6 bg-brutal-yellow">
                            <div class="text-5xl font-black"><?php echo count($categories); ?></div>
                            <div class="font-black uppercase text-xl">Categorias</div>
                        </div>
                        <div class="brutal-card p-6 bg-accent">
                            <div class="text-5xl font-black"><?php echo count($recentVideos); ?></div>
                            <div class="font-black uppercase text-xl">Vídeos Recentes</div>
                        </div>
                    </div>

                    <div class="brutal-card overflow-hidden">
                        <div class="bg-black text-white p-4 font-black uppercase">Vídeos Recentes</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b-3 border-black bg-gray-100">
                                    <tr>
                                        <th class="p-4 font-black uppercase">Título</th>
                                        <th class="p-4 font-black uppercase">Categoria</th>
                                        <th class="p-4 font-black uppercase">Data</th>
                                        <th class="p-4 font-black uppercase text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y-2 divide-black">
                                    <?php foreach ($recentVideos as $video): ?>
                                        <tr class="hover:bg-main/10">
                                            <td class="p-4 font-bold"><?php echo htmlspecialchars($video['title']); ?></td>
                                            <td class="p-4">
                                                <span class="bg-secondary text-white px-2 py-1 border-2 border-black font-bold text-xs uppercase">
                                                    <?php echo htmlspecialchars($video['category_name']); ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-sm font-bold"><?php echo date('d/m/Y H:i', strtotime($video['created_at'])); ?></td>
                                            <td class="p-4 text-center">
                                                <a href="watch.php?id=<?php echo $video['id']; ?>" class="brutal-btn bg-brutal-blue p-2 inline-block" target="_blank">
                                                    <i class="bi bi-play-fill"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Categorias Tab -->
                <div id="tab-categorias" class="tab-content space-y-8">
                    <h2 class="text-4xl font-black uppercase tracking-tighter italic">Categorias</h2>

                    <div class="brutal-card p-6 bg-white">
                        <h3 class="text-xl font-black uppercase mb-4 flex items-center">
                            <i class="bi bi-plus-circle mr-2"></i> Nova Categoria
                        </h3>
                        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="add_category">
                            <div>
                                <label class="block font-black uppercase text-sm mb-1">Nome</label>
                                <input type="text" name="category_name" class="w-full p-2 brutal-input font-bold" required>
                            </div>
                            <div>
                                <label class="block font-black uppercase text-sm mb-1">Descrição</label>
                                <input type="text" name="category_desc" class="w-full p-2 brutal-input font-bold">
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="brutal-btn bg-main px-6 py-3 font-black uppercase">
                                    Criar Categoria
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="brutal-card overflow-hidden">
                        <div class="bg-black text-white p-4 font-black uppercase">Lista de Categorias</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b-3 border-black bg-gray-100">
                                    <tr>
                                        <th class="p-4 font-black uppercase">ID</th>
                                        <th class="p-4 font-black uppercase">Nome</th>
                                        <th class="p-4 font-black uppercase">Descrição</th>
                                        <th class="p-4 font-black uppercase text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y-2 divide-black">
                                    <?php foreach ($categories as $category): ?>
                                        <tr class="hover:bg-main/10">
                                            <td class="p-4 font-bold">#<?php echo $category['id']; ?></td>
                                            <td class="p-4 font-black uppercase"><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td class="p-4 text-sm font-bold"><?php echo htmlspecialchars($category['description'] ?: '-'); ?></td>
                                            <td class="p-4 text-center space-x-2">
                                                <button onclick="openRenameModal(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')" class="brutal-btn bg-brutal-yellow p-2">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="post" class="inline" onsubmit="return confirm('Tem certeza?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="brutal-btn bg-red-400 p-2">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Vídeos Tab -->
                <div id="tab-videos" class="tab-content space-y-8">
                    <h2 class="text-4xl font-black uppercase tracking-tighter italic">Gerenciar Vídeos</h2>

                    <div class="brutal-card p-6 bg-white">
                        <h3 class="text-xl font-black uppercase mb-4 flex items-center">
                            <i class="bi bi-cloud-upload mr-2"></i> Upload de Vídeo
                        </h3>
                        <form id="uploadForm" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-black uppercase text-sm mb-1">Categoria</label>
                                    <select name="category_id" class="w-full p-2 brutal-input font-bold" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-black uppercase text-sm mb-1">Título</label>
                                    <input type="text" name="title" class="w-full p-2 brutal-input font-bold" required>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block font-black uppercase text-sm mb-1">Arquivo de Vídeo</label>
                                    <input type="file" name="video" class="w-full p-2 brutal-input font-bold bg-gray-50" accept="video/*" required>
                                </div>
                            </div>
                            <button type="submit" id="uploadBtn" class="brutal-btn bg-secondary text-white px-6 py-3 font-black uppercase">
                                Iniciar Upload
                            </button>
                            <div id="uploadProgress" class="hidden mt-4">
                                <div class="w-full bg-gray-200 border-3 border-black h-8 relative overflow-hidden">
                                    <div class="progress-bar bg-main h-full transition-all duration-300 flex items-center justify-center font-black" style="width: 0%">0%</div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="brutal-card overflow-hidden">
                        <div class="bg-black text-white p-4 font-black uppercase">Todos os Vídeos</div>
                        <div id="videosList" class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Carregado via JS ou PHP -->
                            <?php
                            $allVideosStmt = $pdo->query('SELECT v.*, c.name as category_name FROM videos v JOIN categories c ON v.category_id = c.id ORDER BY v.created_at DESC');
                            $allVideos = $allVideosStmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($allVideos as $v):
                            ?>
                            <div class="brutal-card bg-white overflow-hidden flex flex-col">
                                <div class="aspect-video bg-black relative">
                                    <?php if ($v['thumbnail']): ?>
                                        <img src="thumbs/<?php echo $v['thumbnail']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-white opacity-20">
                                            <i class="bi bi-film text-5xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute top-2 left-2">
                                        <span class="bg-main border-2 border-black px-2 py-1 text-[10px] font-black uppercase">
                                            <?php echo htmlspecialchars($v['category_name']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="p-4 flex-grow">
                                    <h4 class="font-black uppercase text-sm mb-2 line-clamp-2"><?php echo htmlspecialchars($v['title']); ?></h4>
                                    <div class="text-[10px] font-bold text-gray-500 mb-4">
                                        <?php echo date('d/m/Y H:i', strtotime($v['created_at'])); ?>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="watch.php?id=<?php echo $v['id']; ?>" class="brutal-btn bg-brutal-blue flex-grow text-center py-2 font-black text-xs uppercase" target="_blank">Assistir</a>
                                        <form method="post" onsubmit="return confirm('Deletar vídeo?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="action" value="delete_video">
                                            <input type="hidden" name="video_id" value="<?php echo $v['id']; ?>">
                                            <button type="submit" class="brutal-btn bg-red-400 p-2"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Auditoria Tab -->
                <div id="tab-auditoria" class="tab-content space-y-8">
                    <div class="flex justify-between items-center">
                        <h2 class="text-4xl font-black uppercase tracking-tighter italic">Auditoria</h2>
                        <form method="post" onsubmit="return confirm('Limpar todos os logs?')">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="clear_audit_logs">
                            <button type="submit" class="brutal-btn bg-red-400 px-4 py-2 font-black uppercase text-xs">
                                Limpar Logs
                            </button>
                        </form>
                    </div>

                    <div class="brutal-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b-3 border-black bg-gray-100">
                                    <tr>
                                        <th class="p-4 font-black uppercase">Usuário</th>
                                        <th class="p-4 font-black uppercase">Ação</th>
                                        <th class="p-4 font-black uppercase">Descrição</th>
                                        <th class="p-4 font-black uppercase">Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y-2 divide-black">
                                    <?php
                                    $auditStmt = $pdo->query('SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 50');
                                    while ($log = $auditStmt->fetch(PDO::FETCH_ASSOC)):
                                    ?>
                                        <tr class="hover:bg-main/10">
                                            <td class="p-4 font-bold"><?php echo htmlspecialchars($log['username'] ?? 'Sistema'); ?></td>
                                            <td class="p-4">
                                                <span class="bg-gray-200 border-2 border-black px-2 py-1 font-bold text-[10px] uppercase">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-sm font-bold"><?php echo htmlspecialchars($log['description']); ?></td>
                                            <td class="p-4 text-xs font-bold"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rename Modal (Simple Implementation) -->
    <div id="renameModal" class="fixed inset-0 bg-black/50 z-[100] hidden flex items-center justify-center p-4">
        <div class="brutal-card bg-white w-full max-w-md p-6">
            <h3 class="text-xl font-black uppercase mb-4">Renomear Categoria</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="rename_category">
                <input type="hidden" name="category_id" id="rename_id">
                <div class="mb-4">
                    <label class="block font-black uppercase text-sm mb-1">Novo Nome</label>
                    <input type="text" name="new_name" id="rename_name" class="w-full p-2 brutal-input font-bold" required>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeRenameModal()" class="brutal-btn bg-gray-200 px-4 py-2 font-black uppercase">Cancelar</button>
                    <button type="submit" class="brutal-btn bg-main px-4 py-2 font-black uppercase">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.nav-link-brutal').forEach(b => b.classList.remove('active'));
            
            document.getElementById('tab-' + tabId).classList.add('active');
            document.getElementById('tab-btn-' + tabId).classList.add('active');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function openRenameModal(id, name) {
            document.getElementById('rename_id').value = id;
            document.getElementById('rename_name').value = name;
            document.getElementById('renameModal').classList.remove('hidden');
        }

        function closeRenameModal() {
            document.getElementById('renameModal').classList.add('hidden');
        }

        // Upload AJAX
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = uploadProgress.querySelector('.progress-bar');

            uploadBtn.disabled = true;
            uploadProgress.classList.remove('hidden');

            const xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                }
            });

            xhr.onload = () => {
                if (xhr.status === 200) {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        alert('Sucesso!');
                        location.reload();
                    } else alert('Erro: ' + res.error);
                } else alert('Erro no servidor');
                uploadBtn.disabled = false;
            };
            
            xhr.open('POST', 'api/upload_video.php');
            xhr.send(formData);
        });
    </script>
</body>
</html>
