<?php
/**
 * Sistema de Tutoriais e POP's - Dashboard Principal
 * Versão: 2.0 (Revisada)
 * 
 * Página principal com gerenciamento de categorias e vídeos
 */

require_once 'config.php';
require_login();

$message = '';
$messageType = 'info';
$db = Database::getInstance();
$pdo = $db->getConnection();

// ============================================================================
// PROCESSAR AÇÕES POST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $message = 'Token de segurança inválido.';
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        $action = sanitize($_POST['action']);

        // Adicionar categoria
        if ($action === 'add_category') {
            $categoryName = sanitize($_POST['category_name'] ?? '');
            $categoryDesc = sanitize($_POST['category_desc'] ?? '');

            // Normalizar nome para evitar duplicatas por espaços
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
                    
                    // Recarregar categorias após inserção
                    $categoriesStmt = $pdo->query('SELECT * FROM categories ORDER BY name ASC');
                    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $message = 'Essa categoria já existe.';
                    } else {
                        $message = 'Erro ao criar categoria.';
                    }
                    $messageType = 'danger';
                }
            }
        }

        // Renomear categoria
        elseif ($action === 'rename_category') {
            $categoryId = intval($_POST['category_id'] ?? 0);
            $newName = sanitize($_POST['new_name'] ?? '');
            $normalizedNewName = normalize_category_name($newName);

            if ($categoryId <= 0 || empty($normalizedNewName)) {
                $message = 'Dados inválidos.';
                $messageType = 'danger';
            } else {
                try {
                    $stmt = $pdo->prepare('UPDATE categories SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                    $stmt->execute([$normalizedNewName, $categoryId]);
                    $message = "Categoria renomeada com sucesso!";
                    $messageType = 'success';
                    log_audit($_SESSION['user_id'], 'RENAME_CATEGORY', "Categoria $categoryId renomeada para '$normalizedNewName'");
                    
                    // Recarregar categorias após atualização
                    $categoriesStmt = $pdo->query('SELECT * FROM categories ORDER BY name ASC');
                    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $message = 'Já existe uma categoria com esse nome.';
                    } else {
                        $message = 'Erro ao renomear categoria.';
                    }
                    $messageType = 'danger';
                }
            }
        }

        // Deletar categoria
        elseif ($action === 'delete_category') {
            $categoryId = intval($_POST['category_id'] ?? 0);

            if ($categoryId <= 0) {
                $message = 'ID de categoria inválido.';
                $messageType = 'danger';
            } else {
                try {
                    // Verificar se há vídeos
                    $checkStmt = $pdo->prepare('SELECT COUNT(*) as count FROM videos WHERE category_id = ?');
                    $checkStmt->execute([$categoryId]);
                    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($result['count'] > 0) {
                        $message = 'Não é possível deletar uma categoria com vídeos. Delete os vídeos primeiro.';
                        $messageType = 'warning';
                    } else {
                        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
                        $stmt->execute([$categoryId]);
                        $message = "Categoria deletada com sucesso!";
                        $messageType = 'success';
                        log_audit($_SESSION['user_id'], 'DELETE_CATEGORY', "Categoria $categoryId deletada");
                        
                        // Recarregar categorias após deleção
                        $categoriesStmt = $pdo->query('SELECT * FROM categories ORDER BY name ASC');
                        $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) {
                    $message = 'Erro ao deletar categoria.';
                    $messageType = 'danger';
                }
            }
        }

        // Deletar vídeo
        elseif ($action === 'delete_video') {
            $videoId = intval($_POST['video_id'] ?? 0);

            if ($videoId <= 0) {
                $message = 'ID de vídeo inválido.';
                $messageType = 'danger';
            } else {
                try {
                    // Obter informações do vídeo
                    $stmt = $pdo->prepare('SELECT category_id, filename, thumbnail FROM videos WHERE id = ?');
                    $stmt->execute([$videoId]);
                    $video = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$video) {
                        $message = 'Vídeo não encontrado.';
                        $messageType = 'danger';
                    } else {
                        // Deletar arquivo de vídeo
                        $videoPath = UPLOAD_DIR . $video['category_id'] . '/' . $video['filename'];
                        if (file_exists($videoPath)) {
                            unlink($videoPath);
                        }

                        // Deletar thumbnail
                        if (!empty($video['thumbnail'])) {
                            $thumbPath = THUMB_DIR . $video['thumbnail'];
                            if (file_exists($thumbPath)) {
                                unlink($thumbPath);
                            }
                        }

                        // Deletar do banco de dados
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
        }

        // Limpar logs de auditoria
        elseif ($action === 'clear_audit_logs') {
            try {
                $stmt = $pdo->prepare('DELETE FROM audit_logs');
                $stmt->execute();
                $message = "Logs de auditoria limpos com sucesso!";
                $messageType = 'success';
                // Não registrar no log para evitar loop
            } catch (Exception $e) {
                $message = 'Erro ao limpar logs de auditoria.';
                $messageType = 'danger';
            }
        }
    }
}

// ============================================================================
// OBTER DADOS
// ============================================================================

$categoriesStmt = $pdo->query('SELECT * FROM categories ORDER BY name ASC');
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$videosStmt = $pdo->query('
    SELECT v.*, c.name as category_name
    FROM videos v
    JOIN categories c ON v.category_id = c.id
    ORDER BY v.created_at DESC
    LIMIT 10
');
$recentVideos = $videosStmt->fetchAll(PDO::FETCH_ASSOC);

// Gerar token CSRF
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Dashboard - Sistema de Tutoriais e POP's</title>
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

        .sidebar {
            background: white;
            border-right: 1px solid #e0e0e0;
            min-height: calc(100vh - 60px);
            padding: 2rem 0;
        }

        .sidebar .nav-link {
            color: #666;
            padding: 0.75rem 1.5rem;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: #f5f7fa;
            border-left-color: var(--primary-color);
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
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

        .video-thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-card .stat-label {
            color: #999;
            margin-top: 0.5rem;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .table thead {
            background: #f5f7fa;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-play-circle"></i> Sistema de Tutoriais e POP's
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <span class="nav-link">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <nav class="nav flex-column">
                    <a class="nav-link active" href="#dashboard" data-bs-toggle="tab">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="#categorias" data-bs-toggle="tab">
                        <i class="bi bi-folder"></i> Categorias
                    </a>
                    <a class="nav-link" href="#videos" data-bs-toggle="tab">
                        <i class="bi bi-film"></i> Vídeos
                    </a>
                    <a class="nav-link" href="#auditoria" data-bs-toggle="tab">
                        <i class="bi bi-clock-history"></i> Auditoria
                    </a>
                </nav>
            </div>

            <!-- Conteúdo Principal -->
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Mensagem de Feedback -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Dashboard -->
                    <div class="tab-pane fade show active" id="dashboard">
                        <h2 class="mb-4">Dashboard</h2>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card stat-card">
                                    <div class="stat-number"><?php echo count($categories); ?></div>
                                    <div class="stat-label">Categorias</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stat-card">
                                    <div class="stat-number"><?php echo count($recentVideos); ?></div>
                                    <div class="stat-label">Vídeos Recentes</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stat-card">
                                    <div class="stat-number"><?php echo array_sum(array_column($recentVideos, 'views')); ?></div>
                                    <div class="stat-label">Visualizações</div>
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-4 mb-3">Vídeos Recentes</h4>
                        <div class="card">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Categoria</th>
                                            <th>Visualizações</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentVideos as $video): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($video['title']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($video['category_name']); ?></span></td>
                                                <td><?php echo $video['views']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($video['created_at'])); ?></td>
                                                <td>
                                                    <a href="watch.php?id=<?php echo $video['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="bi bi-play"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Categorias -->
                    <div class="tab-pane fade" id="categorias">
                        <h2 class="mb-4">Gerenciar Categorias</h2>

                        <!-- Adicionar Categoria -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-plus-circle"></i> Adicionar Nova Categoria
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="add_category">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="category_name" class="form-label">Nome da Categoria</label>
                                            <input type="text" class="form-control" id="category_name" name="category_name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="category_desc" class="form-label">Descrição</label>
                                            <input type="text" class="form-control" id="category_desc" name="category_desc">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Criar Categoria
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Lista de Categorias -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-list"></i> Categorias Existentes
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Descrição</th>
                                            <th>Criada em</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $categoryCounter = 1; ?>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo $categoryCounter++; ?></td>
                                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                <td><?php echo htmlspecialchars($category['description'] ?? '-'); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($category['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#renameModal<?php echo $category['id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="delete_category">
                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>

                                            <!-- Modal Renomear -->
                                            <div class="modal fade" id="renameModal<?php echo $category['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Renomear Categoria</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="post">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="action" value="rename_category">
                                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label for="new_name" class="form-label">Novo Nome</label>
                                                                    <input type="text" class="form-control" name="new_name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                <button type="submit" class="btn btn-primary">Salvar</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Vídeos -->
                    <div class="tab-pane fade" id="videos">
                        <h2 class="mb-4">Gerenciar Vídeos</h2>

                        <!-- Upload de Vídeo -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-cloud-upload"></i> Upload de Vídeo
                            </div>
                            <div class="card-body">
                                <form id="uploadForm" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">Categoria</label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">Selecione uma categoria</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="title" class="form-label">Título do Vídeo</label>
                                            <input type="text" class="form-control" id="title" name="title" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="video" class="form-label">Arquivo de Vídeo (MP4, WebM, OGG)</label>
                                            <input type="file" class="form-control" id="video" name="video" accept="video/*" required>
                                            <small class="text-muted">Máximo 500 MB</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="thumbnail" class="form-label">Thumbnail (Opcional)</label>
                                            <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                                        </div>
                                    </div>

                                    <div class="progress mb-3" id="uploadProgress" style="display:none;">
                                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>

                                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                                        <i class="bi bi-upload"></i> Enviar Vídeo
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Lista de Vídeos -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-film"></i> Vídeos
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Categoria</th>
                                            <th>Tamanho</th>
                                            <th>Visualizações</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="videosTableBody">
                                        <?php foreach ($recentVideos as $video): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($video['title']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($video['category_name']); ?></span></td>
                                                <td><?php echo format_bytes($video['file_size']); ?></td>
                                                <td><?php echo $video['views']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($video['created_at'])); ?></td>
                                                <td>
                                                    <a href="watch.php?id=<?php echo $video['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="bi bi-play"></i>
                                                    </a>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="delete_video">
                                                        <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza?')">
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

                    <!-- Auditoria -->
                    <div class="tab-pane fade" id="auditoria">
                        <h2 class="mb-4">Log de Auditoria</h2>
                        <div class="card">
                            <div class="card-header">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="clear_audit_logs">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja apagar todos os logs de auditoria? Esta ação não pode ser desfeita.')">
                                        <i class="bi bi-trash"></i> Apagar Todos os Logs
                                    </button>
                                </form>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Ação</th>
                                            <th>Descrição</th>
                                            <th>IP</th>
                                            <th>Data/Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $auditStmt = $pdo->query('
                                            SELECT al.*, u.username
                                            FROM audit_logs al
                                            LEFT JOIN users u ON al.user_id = u.id
                                            ORDER BY al.created_at DESC
                                            LIMIT 50
                                        ');
                                        $auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($auditLogs as $log):
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($log['username'] ?? 'Sistema'); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                                <td><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                                                <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Upload de vídeo com AJAX
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = uploadProgress.querySelector('.progress-bar');

            uploadBtn.disabled = true;
            uploadProgress.style.display = 'block';

            try {
                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                    }
                });

                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert('Vídeo enviado com sucesso!');
                            document.getElementById('uploadForm').reset();
                            location.reload();
                        } else {
                            alert('Erro: ' + response.error);
                        }
                    } else {
                        alert('Erro ao enviar vídeo');
                    }
                    uploadBtn.disabled = false;
                    uploadProgress.style.display = 'none';
                });

                xhr.addEventListener('error', function() {
                    alert('Erro na conexão');
                    uploadBtn.disabled = false;
                    uploadProgress.style.display = 'none';
                });

                xhr.open('POST', 'api/upload_video.php');
                xhr.send(formData);
            } catch (error) {
                console.error('Erro:', error);
                uploadBtn.disabled = false;
                uploadProgress.style.display = 'none';
            }
        });
    </script>
</body>
</html>
